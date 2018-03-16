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

    <!-- CSS -->
    <link rel="stylesheet" type="text/css" href="./css/bootstrap.css">
    <link rel="stylesheet" type="text/css" href="./css/main.css">

    <!-- SCRIPTS -->
    <script type="text/javascript" src="./scripts/jquery.js"></script>

  </head>

  <div id="background_image"></div>

  <div class="front carousel_label">Recently rated</div>
  <a href="./login/logout.php" class="front carousel_label" style="float: right; margin-left: 12px;">Log out</a>
  <a id="request_permissions" class="front carousel_label" style="float: right;"></a><br>

  <div id="list_info" class="front"></div><br>


</html>


<script>

  $.get("./api/request_user_access", function(r) {

    if (r == "linked") {

      $.get("./api/get_user_list", function(s) {

        var temp = JSON.parse(s);
        for (var i = 0; i < temp.length; i++) {
          var poster = "https://image.tmdb.org/t/p/w600_and_h900_bestv2" + temp[i]["poster_path"];
          var movie = $("<a target='_blank'><img class='poster' draggable=false src='" + poster + "' /></a>");
          movie.attr("href", "https://www.themoviedb.org/movie/" + temp[i]["id"]);
          function addbackdrop(t){
            movie.on("mouseenter", function() {
              var backdrop = "https://image.tmdb.org/t/p/w1400_and_h450_face" + t["backdrop_path"];
              $("#background_image").animate({opacity: 0}, 100, "linear", function() {
                $("#background_image").css("background", "linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),"
                + "url('" + backdrop + "')");
                $("#background_image").animate({opacity: 1}, 100, "linear", function() {});
              });
            });
            // movie.on("mouseleave", function() {
            //   $("#background_image").animate({opacity: 0}, 100, "linear", function() {});
            // });
          }
          addbackdrop(temp[i]);
          $("#list_info").append(movie);
        }

      });

      $("#request_permissions").html("Refresh list");
      $("#request_permissions").attr("href", "./api/cache_user_list");

    } else {
      $("#request_permissions").html("Link TMDb");
      $("#request_permissions").attr("href", r);
    }

  });

</script>
