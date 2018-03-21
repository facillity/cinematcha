<?php

require('../res/connect.php');

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

if (isset($_SESSION['user_id'])) {

  header("Location: ../");

}

?>

<html>

  <head>

    <title>Cinematcha Login</title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="image/png" href="../images/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="../images/favicon-16x16.png" sizes="16x16" />

    <!-- CSS -->
    <link rel="stylesheet" href="../css/bootstrap.css" />
    <link rel="stylesheet" href="../css/login.css" />

  </head>

  <video playsinline autoplay muted loop id="login_movie">
    <source src="login_movie.mp4" type="video/mp4">
  </video>

  <div class="col-xs-12">


    <form class="login_form col-xs-10 col-md-4">

      <img class="logo" src="../images/cinematcha-light-sm.png" draggable="false" /><br><br>

      <p id="errors" class="col-xs-10 col-md-10 text-center" style="display:none;"></p>
      <input id="username" class="login_field col-xs-10 col-md-10" type="text" placeholder="username" /><br>
      <input id="password" class="login_field col-xs-10 col-md-10" type="password" placeholder="password" /><br>
      <input id="zipcode" class="login_field col-xs-10 col-md-10" style="display:none;" type="text" placeholder="zip code" pattern="(\d{5}([\-]\d{4})?)" /><br id="zipcode_br" style="display:none;">
      <div id="login_verify" class="login_field login_button col-xs-10 col-md-10 offset-xs-1 offset-md-1 text-center">Login</div><br>
      <p class="sign_up col-xs-10 col-md-10 offset-xs-1 offset-md-1 text-center"><a id="sign_up">Sign up</a></p>

    </form>

  </div>

</html>

<!-- SCRIPTS -->
<script type="text/javascript" src="../scripts/jquery.js"></script>
<script type="text/javascript" src="../scripts/login.js"></script>
