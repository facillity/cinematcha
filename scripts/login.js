var is_login = true;

$("#sign_up").on("click", function() {
  if ($("#zipcode").css("display") == "none") {
    $("#zipcode").css("display", "");
    $("#zipcode_br").css("display", "");
    $("#sign_up").html("Login");
    $("#login_verify").html("Sign up");
    is_login = false;
  } else {
    $("#zipcode").css("display", "none");
    $("#zipcode_br").css("display", "none");
    $("#sign_up").html("Sign up");
    $("#login_verify").html("Login");
    is_login = true;
  }
});

$("#login_verify").on("click", function() {
  var username = $("#username").val();
  var password = $("#password").val();
  var zipcode = $("#zipcode").val();
  var to_push = [
    {name: "is_login", value: is_login},
    {name: "username", value: username},
    {name: "password", value: password},
    {name: "zipcode", value: zipcode}
  ];
  $.post("./login_verify.php", to_push, function(r) {
    if (r == "success") {
      window.location.href = "../";
    } else {
      $("#errors").html(r);
      $("#errors").css("display", "");
      $("#username, #password, #zipcode").css("background-color", "#ffe4e4");
    }
  });
});

$("#username, #password, #zipcode").keyup(function(event) {
    if (event.keyCode === 13) {
        $("#login_verify").click();
    }
});
