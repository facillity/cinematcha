$.get("./api/get_favorite_genres", function(g) {

  var g = JSON.parse(g);

  if (g.length >= 2) {

    var genres = {

      12: "Adventure", 14: "Fantasy", 16: "Animation",18: "Drama", 27: "Horror", 28: "Action",
      35: "Comedy", 36: "History", 37: "Western", 53: "Thriller", 80: "Crime", 99: "Documentary",
      878: "Science Fiction", 9648: "Mystery", 10402: "Music", 10749: "Romance", 10751: "Family",
      10752: "War", 10770: "TV Movie"

    };

    $.get("./api/get_genre_recommendations", {genre: g[0].genre_id} , function(r) {

      var temp = JSON.parse(r);
      create_carousel(temp, carousel_max, "#genre_recommendation1", "More " + genres[g[0].genre_id]);

    });

    $.get("./api/get_genre_recommendations", {genre: g[1].genre_id} , function(r) {

      var temp = JSON.parse(r);
      create_carousel(temp, carousel_max, "#genre_recommendation2", "More " + genres[g[1].genre_id]);

    });

  }

});

$.get("./api/get_user_recommendations", function(r) {

  var temp = JSON.parse(r);
  create_carousel(temp, carousel_max, "#user_recommendations", "Users with similar taste also liked");

});

// ------------------------------------------------------------------------------- //

$.get("./api/request_user_access", function(r) {

  if (r == "linked") {

    $("#request_permissions").html("Refresh list");
    $("#request_permissions").attr("href", "./api/cache_user_list");

  } else {

    $("#request_permissions").html("Link TMDb");
    $("#request_permissions").attr("href", r);

  }

  $("#request_permissions").css("display", "");
  $("#logout").css("display", "");

});

var h = 0.08 * window.screen.availHeight;
$("#search_container").css("margin-bottom", h + "px");

$(window).scroll(function() {

	var h = 0.08 * window.screen.availHeight - $(document).scrollTop();
	h = (h > 0) ? h : 0;

  $("#search_container").stop().animate({
      marginBottom: h + "px"
  }, 0);

});

$("#search").keypress( function(e) {

    if (e.which == 13) {

      var query = $("#search").val();
      if (query) {
        window.location.href = "./q/" + query.replace(/ /g, "+");
      }

    }

});

$("#search_button").on("click", function() {

      var query = $("#search").val();
      if (query) {
        window.location.href = "./q/" + query.replace(/ /g, "+");
      }

});
