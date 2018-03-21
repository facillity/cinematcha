var carousel_max = 5;

if ($(window).width() <= 800) {
  $(".logo img").css("display", "none");
  carousel_max = 6;
} else if ($(window).width() >= 1200) {
  carousel_max = 10;
}

function create_carousel(data, max_items, selector, label) {

  if (data.length == 0) {
    return;
  }

  var label = $("<div class='front c_label animated fadeIn' style='display: none;'>" + label + "</div>");
  $(selector).append(label);

  var max = (max_items < data.length) ? max_items : data.length;

  for (var i = 0; i < max; i++) {

    var poster = "https://image.tmdb.org/t/p/w154" + data[i]["poster_path"];
    var movie = $("<a target='_blank'><img class='poster animated fadeIn' draggable=false src='" + poster + "' /></a>");
    movie.attr("href", "https://www.themoviedb.org/movie/" + data[i]["id"]);

    function addbackdrop(t) {

      movie.on("mouseenter", function() {

        var backdrop = "https://image.tmdb.org/t/p/w1400_and_h450_face" + t["backdrop_path"];
        var backdrop_darken = "linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url(\"" + backdrop + "\")";

        if ($("#background_image").css("background-image") != backdrop_darken) {

          $("#background_image").animate({opacity: 0}, 100, "linear", function() {

            $("#background_image").css("background", "linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),"
            + "url('" + backdrop + "')");
            $("#background_image").animate({opacity: 1}, 100, "linear", function() {});

          });

        }

      });

    }

    addbackdrop(data[i]);
    $(selector).append(movie);

    $(".c_label").css("display", "");
    $(selector).css("display", "");

  }

}
