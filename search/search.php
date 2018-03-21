<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {

  echo("session_expired");
  header("Location: ./login/");

}

?>

<html>

  <!-- HEADER -->
  <head>

    <title>Cinematcha</title>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" type="image/png" href="../images/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="../images/favicon-16x16.png" sizes="16x16" />

    <!-- CSS -->
    <link rel="stylesheet" type="text/css" href="../css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" type="text/css" href="../css/animate.css">
    <link rel="stylesheet" type="text/css" href="../css/main.css">

  </head>

  <div id="background_image"></div>

  <a href="../" class="front logo">
    <img src="../images/cinematcha-dark.png" />
  </a>
  <a href="./login/logout.php" id="logout" class="front carousel_label" style="float: right; margin-left: 7px; margin-right: 5px; display: none;">Log out</a>
  <a id="request_permissions" class="front carousel_label" style="float: right; display: none"></a><br><br>

  <div id="search_container" class="front">

    <div class="front" style="margin-bottom: 15px;">Recommend me movies about</div>
    <input id="search" placeholder="egyptian mummies" autofocus />
    <div id="search_button"><i class="fa fa-search"></i></div>

  </div><br><br>

  <!-- HOMEPAGE RECOMMENDATION CONTAINERS -->

  <div id="search_area" class="front"></div><br>


</html>

<!-- SCRIPTS -->
<script type="text/javascript" src="../scripts/jquery.js"></script>
<script type="text/javascript" src="../scripts/main.js"></script>
<script type="text/javascript" src="../scripts/search.js"></script>

<script>

  var query = "<?php echo filter_input(INPUT_GET, 'q') ?>";
  $("#search").val(query);
  search(query);

</script>
