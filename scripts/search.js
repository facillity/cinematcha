$.get("../api/request_user_access", function(r) {

  if (r == "linked") {

    $("#request_permissions").html("Refresh list");
    $("#request_permissions").attr("href", "../api/cache_user_list");

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
        window.location.href = "../q/" + query.replace(/ /g, "+");
      }

    }

});

$("#search_button").on("click", function() {

      var query = $("#search").val();
      if (query) {
        window.location.href = "../q/" + query.replace(/ /g, "+");
      }

});

function search(query) {

  if (query != "") {

    $.get("../api/search", {query: query}, function(s) {

      $("#search_area").html("");
      var temp = JSON.parse(s);
      create_carousel(temp, carousel_max * 2, "#search_area", "Showing results for '" + query + "'");

    });

  }

}
