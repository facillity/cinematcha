<?php

require('../res/connect.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

      case "get_movies":

        $stmt = $db->prepare("SELECT * FROM movies
          WHERE vote_count >= 2000
          ORDER BY vote_average DESC
          LIMIT 6");
        $stmt->execute();
        $row = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($row, JSON_UNESCAPED_UNICODE);
        break;

      case "request_user_access":

        $stmt = $db->prepare("SELECT * FROM users WHERE user_id=? AND access_token IS NOT NULL");
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
          echo $row;
        } else {
          $json = json_decode($row, JSON_UNESCAPED_UNICODE);
          if (!isset($json["success"]) || $json["success"] != TRUE) {
            echo $row;
          } else {
            $token = $json["access_token"];
            $account_id = $json["account_id"];
            $stmt = $db->prepare("UPDATE users
              SET access_token=?, account_id=?
              WHERE user_id=?");
            $stmt->execute(array($token, $account_id, $user_id));
            cache_user_list($db, $user_id);
            header("Location: ../");
          }
        }
        ignore_user_abort(false);
        break;

        case "cache_user_list":
          try {
            cache_user_list($db, $user_id);
          } catch (Exception $e) {
            $stmt = $db->prepare("UPDATE users
              SET access_token=NULL, account_id=NULL
              WHERE user_id=?");
            $stmt->execute(array($user_id));
          }

          header("Location: ../");
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
    CURLOPT_POSTFIELDS => "{\"redirect_to\":\"http://cinematcha.me/api/token_granted\"}",
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
function cache_user_list($db, $user_id) {
  $stmt = $db->prepare("SELECT user_id, access_token, account_id FROM users WHERE user_id=?");
  $stmt->execute(array($user_id));
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    return "error";
  }

  $stmt = $db->prepare("DELETE FROM ratings WHERE user_id=?");
  $stmt->execute(array($user_id));

  $access_token = $row["access_token"];
  $account_id = $row["account_id"];

  $pages = load_user_page($db, $user_id, $access_token, $account_id);

  for ($p = 2; $p <= $pages; $p++) {
    load_user_page($db, $user_id, $access_token, $account_id, $p);
    sleep(0.25);
  }
}

// caches one page of a user's list
function load_user_page($db, $user_id, $access_token, $account_id, $page = 1) {
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
    $results = json_decode($response, JSON_UNESCAPED_UNICODE);
    foreach ($results["results"] as &$r) {
      $date = date_create($r["account_rating"]["created_at"]);
      $date = date_format($date, 'Y-m-d H:i:s');
      $stmt = $db->prepare("INSERT INTO ratings (user_id, id, rating, time_rated) VALUES (?,?,?,?)");
      $stmt->execute(array($user_id, $r["id"], $r["account_rating"]["value"], $date));
    }
    return $results["total_pages"];
  }
}

?>
