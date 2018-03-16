<?php

require("../res/connect.php");

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

$is_login = $_REQUEST["is_login"];
$username = $_REQUEST["username"];
$password = $_REQUEST["password"];
$zipcode = $_REQUEST["zipcode"];

if ($username == "" || $password == "" || ($is_login == "false" && $zipcode == "")) {
  echo "Cannot leave fields empty";
  exit;
}

$stmt = $db->prepare("SELECT * FROM users WHERE user_id=?");
$stmt->execute(array($username));
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($is_login == "true") {
  if ($row) {
    if (password_verify($password, $row["password"])) {
      $_SESSION["user_id"] = $username;
      $params = session_get_cookie_params();
      setcookie(session_name(), $_COOKIE[session_name()], time() + 60*60*24*30, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
      echo "success";
      exit;
    }
  }
  echo "Incorrect username or password";
} else {
  if ($row) {
    echo "Username already taken";
  } else {
    $stmt = $db->prepare("INSERT INTO users (user_id, password, zip_code) VALUES (?,?,?)");
    $stmt->execute(array($username, password_hash($password, PASSWORD_DEFAULT), $zipcode));
    $_SESSION["user_id"] = $username;
    $params = session_get_cookie_params();
    setcookie(session_name(), $_COOKIE[session_name()], time() + 60*60*24*30, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    echo "success";
  }
}

?>
