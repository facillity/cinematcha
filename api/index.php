<?php

require('../res/connect.php');

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
// error_reporting(E_ALL);

set_time_limit(0);

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['user_id'])) {

  echo("session_expired");
  header("Location: ../login/");

} else {

  $user_id = $_SESSION["user_id"];
  $action = filter_input(INPUT_GET, 'action');

  if (isset($action)) {

    switch ($action) {

      case "search":

        $query = urlencode($_REQUEST["query"]);

        $f = file_get_contents("http://localhost:5000/service/search?query=" . $query);
        $f = json_decode($f, true);
        $f = $f["data"];

        if (count($f) > 0) {

          $parameters = join(array_fill(0, count($f), "m.id=?"), " OR ");

          $stmt = $db->prepare("SELECT * FROM movies m WHERE (" . $parameters .
            ") AND NOT EXISTS (SELECT * FROM ratings r WHERE user_id=? AND m.id=r.id)");

          $params = array_keys($f);
          array_push($params, $user_id);

          $stmt->execute($params);
          $row = $stmt->fetchAll(PDO::FETCH_ASSOC);

          $weather = get_weather($db, $user_id);

          if ($weather) {

            $base_weight = 1 + 0.01 * (72 - $weather["temperature"]);
            $base_weight = ($base_weight > 0) ? $base_weight : 0;

            $romance_weight = $base_weight + (($weather["boost_romance"] == 1) ? 0.10 : 0);
            $horror_weight = $base_weight + (($weather["boost_horror"] == 1) ? 0.10 : 0);

          }

          $fav = get_favorite_genre($db, $user_id);

          if (count($fav) > 0) {
            $favorite_genre = $fav[0]["genre_id"];
          }

          $disasters = get_disasters_list($db);

          foreach ($row as &$result) {

            $result["rank"] = $f[$result["id"]];

            if (strpos($result["genre_ids"], "10749") !== false) {
              if ($weather) {
                $result["rank"] *= $romance_weight;
              }
            }

            if (strpos($result["genre_ids"], "27") !== false) {
              if ($weather) {
                $result["rank"] *= $horror_weight;
              }
            }

            $is_favorite_genre = (strpos($result["genre_ids"], (string) $favorite_genre));

            if (count($favorite_genre) > 0 && $is_favorite_genre) {
              $result["rank"] *= 1.10;
            }

            foreach ($disasters as $d) {
              if (isset($d) && isset($d["type"])) {
                if (stripos($result["synopsis"], $d["type"]) !== false) {
                  // echo ($result["title"] . "   " . $result["rank"]);
                  $result["rank"] *= 0.95;
                  // echo ("   " . $result["rank"] . "<br>");
                }
              }
            }

          }

          usort($row, "cmp");

          echo json_encode($row, JSON_UNESCAPED_UNICODE);

        } else {
          echo "[]";
        }

        break;

      case "request_user_access":

        $stmt = $db->prepare("SELECT *
          FROM users
          WHERE user_id=?
          AND access_token IS NOT NULL");


        $stmt->execute(array($user_id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {

          $row = request_user_access();

          if ($row[0] == "c") {
            echo $row;
          } else {
            $row = json_decode($row, JSON_UNESCAPED_UNICODE);
            $_SESSION["request_token"] = $row["request_token"];
            echo "https://www.themoviedb.org/auth/access?request_token=" . $row["request_token"];
          }


        } else {
          echo "linked";
        }

        break;

      case "token_granted":

        ignore_user_abort(true);
        $row = token_granted();

        if ($row[0] == "c") {
          // echo $row;
        } else {

          $json = json_decode($row, JSON_UNESCAPED_UNICODE);

          if (!isset($json["success"]) || $json["success"] != TRUE) {
            // echo $row;
          } else {

            $token = $json["access_token"];
            $account_id = $json["account_id"];

            cache_user_list($db, $user_id, $token, $account_id);

            $stmt = $db->prepare("UPDATE users
              SET access_token=?, account_id=?
              WHERE user_id=?");

            $stmt->execute(array($token, $account_id, $user_id));

            header("Location: ../");

          }

        }

        ignore_user_abort(false);
        break;

        case "cache_user_list":

          $stmt = $db->prepare("SELECT *
            FROM users
            WHERE user_id=?");
          $stmt->execute(array($user_id));
          $row = $stmt->fetch(PDO::FETCH_ASSOC);

          cache_user_list($db, $user_id, $row["access_token"], $row["account_id"]);

          header("Location: ../login/");

          break;

        case "get_user_list":

          $stmt = $db->prepare("SELECT * FROM ratings r
            JOIN movies m
            ON r.id = m.id
            WHERE user_id=?
            ORDER BY time_rated DESC");

          $stmt->execute(array($user_id));
          $row = $stmt->fetchAll(PDO::FETCH_ASSOC);

          echo json_encode($row, JSON_UNESCAPED_UNICODE);
          break;

        case "get_user_recommendations":

          $sql = "SELECT m.*, q.score FROM movies m JOIN (SELECT DISTINCT r.id, (r.rating / b.total_distance) AS score
            FROM ratings r
            JOIN (SELECT a.user2_id,
              ( (SUM(difference) + 1) / (1 / (COUNT(user2_id) + 1) + 1) ) AS total_distance
              FROM (SELECT r.*, s.user_id AS user2_id, ABS(r.rating - s.rating) AS difference
                FROM ratings r
                RIGHT JOIN ratings s
                ON r.id = s.id
                WHERE r.user_id=? AND s.user_id<>?
              ) a
              GROUP BY a.user2_id
            ) b
            ON r.user_id = b.user2_id
            WHERE NOT EXISTS (SELECT *
              FROM ratings t
              WHERE t.user_id=?
              AND r.id = t.id
            )
          ) q
          ON m.id=q.id
          ORDER BY score DESC, title ASC
          LIMIT 10";

          $stmt = $db->prepare($sql);
          $stmt->execute(array($user_id, $user_id, $user_id));
          $row = $stmt->fetchAll(PDO::FETCH_ASSOC);

          echo json_encode($row, JSON_UNESCAPED_UNICODE);
          break;

        case "get_favorite_genres":

          echo json_encode(get_favorite_genre($db, $user_id), JSON_UNESCAPED_UNICODE);
          break;

        case "get_genre_recommendations":

          $genre = $_REQUEST["genre"];

          if ($genre == "") {
            echo "[]";
          } else {

            $sql = "SELECT *
              FROM movies m
              WHERE m.vote_count > 1000
              AND m.genre_ids LIKE ?
              AND NOT EXISTS (
                SELECT * FROM ratings r WHERE user_id=? AND m.id=r.id
              )
              ORDER BY vote_average DESC
              LIMIT 10";

            $stmt = $db->prepare($sql);
            $stmt->execute(array("%" . $genre . "%", $user_id));
            $row = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($row, JSON_UNESCAPED_UNICODE);

          }

          break;

        case "get_weather":

          $row = get_weather($db, $user_id);
          echo json_encode($row, JSON_UNESCAPED_UNICODE);
          break;

        case "get_disasters_list":
          $row = get_disasters_list($db);
          echo json_encode($row, JSON_UNESCAPED_UNICODE);
          break;

    }

  }

}

// ask user for permissions
function request_user_access() {

  $curl = curl_init();


  curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api.themoviedb.org/4/auth/request_token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "{\"redirect_to\":\"https://cinematcha.me/api/token_granted\"}",
    CURLOPT_HTTPHEADER => array(
      "authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmZWFlY2E2ZmIwYzM2ZjBlYTVkZjk4NzZhMTQzZTg0YiIsInN1YiI6IjVhNjEyM2Y3MGUwYTI2MTAzMTAwYTI2ZCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.1bB5SQCrYE4hut8V8CMKyXxD3YV_bix_BAKIU-cVlTw",
      "content-type: application/json;charset=utf-8"
    ),
  ));

  $response = curl_exec($curl);
  $err = curl_error($curl);

  curl_close($curl);

  if ($err) {
    return "cURL Error #:" . $err;
  } else {
    return $response;
  }

}

// on user accept permissions
function token_granted() {

  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api.themoviedb.org/4/auth/access_token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => "{\"request_token\":\"" . $_SESSION["request_token"] . "\"}",
    CURLOPT_HTTPHEADER => array(
      "authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmZWFlY2E2ZmIwYzM2ZjBlYTVkZjk4NzZhMTQzZTg0YiIsInN1YiI6IjVhNjEyM2Y3MGUwYTI2MTAzMTAwYTI2ZCIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.1bB5SQCrYE4hut8V8CMKyXxD3YV_bix_BAKIU-cVlTw",
      "content-type: application/json;charset=utf-8"
    ),
  ));

  $response = curl_exec($curl);
  $err = curl_error($curl);

  curl_close($curl);

  if ($err) {
    return "cURL Error #:" . $err;
  } else {
    return $response;
  }

}

// caches all pages of a user's list
function cache_user_list($db, $user_id, $access_token, $account_id) {

  $stmt = $db->prepare("SELECT user_id, access_token, account_id FROM users WHERE user_id=?");
  $stmt->execute(array($user_id));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    return "error";
  }

  $stmt = $db->prepare("DELETE FROM ratings WHERE user_id=?");
  $stmt->execute(array($user_id));

  $stmt = $db->prepare("DELETE FROM user_genre_preferences WHERE user_id=?");
  $stmt->execute(array($user_id));

  $list = `{
    "28":{"total_time":0,"item_count":0,"rating_sum":0},
    "12":{"total_time":0,"item_count":0,"rating_sum":0},
    "16":{"total_time":0,"item_count":0,"rating_sum":0},
    "35":{"total_time":0,"item_count":0,"rating_sum":0},
    "80":{"total_time":0,"item_count":0,"rating_sum":0},
    "99":{"total_time":0,"item_count":0,"rating_sum":0},
    "18":{"total_time":0,"item_count":0,"rating_sum":0},
    "10751":{"total_time":0,"item_count":0,"rating_sum":0},
    "14":{"total_time":0,"item_count":0,"rating_sum":0},
    "36":{"total_time":0,"item_count":0,"rating_sum":0},
    "27":{"total_time":0,"item_count":0,"rating_sum":0},
    "10402":{"total_time":0,"item_count":0,"rating_sum":0},
    "9648":{"total_time":0,"item_count":0,"rating_sum":0},
    "10749":{"total_time":0,"item_count":0,"rating_sum":0},
    "878":{"total_time":0,"item_count":0,"rating_sum":0},
    "10770":{"total_time":0,"item_count":0,"rating_sum":0},
    "53":{"total_time":0,"item_count":0,"rating_sum":0},
    "10752":{"total_time":0,"item_count":0,"rating_sum":0},
    "37":{"total_time":0,"item_count":0,"rating_sum":0}
  }`;

  $list = json_decode($list, true);

  $pages = load_user_page($db, $user_id, $access_token, $account_id, $list);

  for ($p = 2; $p <= $pages; $p++) {
    load_user_page($db, $user_id, $access_token, $account_id, $list, $p);
    sleep(0.25);
  }

  foreach ($list as $g=>$genre_info) {

    $stmt = $db->prepare("INSERT INTO user_genre_preferences (user_id, genre_id, total_time, average_score, item_count) VALUES (?,?,?,?,?)");
    $stmt->execute(array($user_id, $g, $genre_info["total_time"], $genre_info["rating_sum"] / $genre_info["item_count"], $genre_info["item_count"]));

  }

}

// caches one page of a user's list
function load_user_page($db, $user_id, $access_token, $account_id, &$to_append, $page = 1) {

  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api.themoviedb.org/4/account/" . $account_id . "/movie/rated?page=" . $page,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_POSTFIELDS => "{}",
    CURLOPT_HTTPHEADER => array(
      "authorization: Bearer " . $access_token
    ),
  ));

  $response = curl_exec($curl);
  $err = curl_error($curl);

  curl_close($curl);

  if ($err) {
    return "cURL Error #:" . $err;
  } else {

    $results = json_decode($response, true);

    foreach ($results["results"] as &$r) {

      $date = date_create($r["account_rating"]["created_at"]);
      $stmt = $db->prepare("INSERT INTO ratings (user_id, id, rating, time_rated) VALUES (?,?,?,?)");
      $stmt->execute(array($user_id, $r["id"], $r["account_rating"]["value"], date_format($date, "Y-m-d H:i:s")));

      $stmt = $db->prepare("SELECT * FROM movies WHERE id=?");
      $stmt->execute(array($r["id"]));
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$row) {

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => "https://api.themoviedb.org/3/movie/27205?language=en-US&api_key=feaeca6fb0c36f0ea5df9876a143e84b",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_POSTFIELDS => "{}",
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
          continue;
        } else {

          $response = json_decode($response, true);
          $r["runtime"] = $response["runtime"];

        }

      } else {
        $r["runtime"] = $row["runtime"];
      }

      foreach ($r["genre_ids"] as $g) {

        $to_append[$g]["item_count"] += 1;
        $to_append[$g]["rating_sum"] += $r["account_rating"]["value"];
        $to_append[$g]["total_time"] += $r["runtime"];

      }

    }

    return $results["total_pages"];

  }

}

function get_favorite_genre($db, $user_id) {

  $stmt = $db->prepare("SELECT u.*, POWER(u.total_time / a.pt, 0.15) * u.average_score AS score
    FROM user_genre_preferences u
    JOIN (SELECT SUM(v.total_time) AS pt FROM user_genre_preferences v) a
    WHERE u.total_time<>0 AND u.user_id=?
    ORDER BY score DESC
    LIMIT 10");
  $stmt->execute(array($user_id));
  $row = $stmt->fetchAll(PDO::FETCH_ASSOC);

  return $row;

}

function get_weather($db, $user_id) {

  $stmt = $db->prepare("SELECT * FROM users WHERE user_id=?");
  $stmt->execute(array($user_id));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  $zipcode = $row["zip_code"];

  $stmt = $db->prepare("SELECT * FROM weather
    WHERE zipcode=?
    AND time_recorded > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
  $stmt->execute(array($zipcode));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    $row = cache_weather($db, $zipcode);
  }

  return $row;

}

function cache_weather($db, $zipcode) {

  $base_url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . $zipcode .  "&key=AIzaSyBZi3ETlspXeDuX9FyS8tliSdvzVmVPvUM";

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $base_url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($curl);
  curl_close($curl);

  $result =  json_decode($response, true);
  $lat = $result["results"][0]["geometry"]["location"]["lat"];
  $lng = $result["results"][0]["geometry"]["location"]["lng"];

  $base_url = "https://api.darksky.net/forecast/ca0be5723fe565d025793c205cee1bcb/" . $lat . "," . $lng;

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $base_url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($curl);
  curl_close($curl);

  $result =  json_decode($response, true);
  $temp = $result["currently"]["temperature"];
  $icon = $result["currently"]["icon"];
  $boost_romance = ($icon == "rain" || $icon == "snow" || $icon == "sleet") ? 1 : 0;
  $boost_horror = ($icon == "sleet" || $icon == "fog" || $icon == "wind") ? 1 : 0;

  $stmt = $db->prepare("INSERT INTO weather (zipcode, temperature, boost_romance, boost_horror)
    VALUES (?,?,?,?)
    ON DUPLICATE KEY
    UPDATE temperature=?, boost_romance=?, boost_horror=?, time_recorded=NOW()");
  $stmt->execute(array($zipcode, $temp, $boost_romance, $boost_horror, $temp, $boost_romance, $boost_horror));

  $row = array("zipcode"=>$zipcode, "temperature"=>$temp, "boost_romance"=> $boost_romance, "boost_horror"=>$boost_horror);
  return $row;

}

function get_disasters_list($db) {

  $stmt = $db->prepare("SELECT * FROM disasters WHERE time_cached > DATE_SUB(NOW(), INTERVAL 1 DAY)");
  $stmt->execute();
  $row = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (!$row) {
    return cache_disasters_list($db);
  }
  return $row;

}

function cache_disasters_list($db) {

  $stmt = $db->prepare("DELETE FROM disasters");
  $stmt->execute();

  $date = date_create(date("Y-m-d H:i:s"));
  date_sub($date, date_interval_create_from_date_string("60 days"));
  $date = date_format($date, "Y-m-d") . "T00:00:00%2B00:00";
  $base_url = "https://api.reliefweb.int/v1/disasters?profile=full&appname=cinematcha&filter[field]=date.created&filter[value][from]=" . $date;

  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $base_url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($curl);
  curl_close($curl);

  $r = json_decode($response, true);
  $to_return = array();

  foreach ($r["data"] as $disaster) {

    foreach ($disaster["fields"]["type"] as $d) {

      array_push($to_return, array("title"=>$disaster["fields"]["id"], "type"=>$d["name"], "time_cached"=>date("Y-m-d H:i:s")));

      $stmt = $db->prepare("INSERT INTO disasters (title, type) VALUES (?,?)");
      $stmt->execute(array($disaster["fields"]["id"], $d["name"]));

    }

  }

  return $to_return;

}

function cmp($a, $b)
{
    return $a["rank"] < $b["rank"];
}

?>
