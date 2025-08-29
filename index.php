<!DOCTYPE html>
<html lang="en">
<head>
  <title>Rubicon</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
 
</head>

<style>

.container {
    padding-top: 83px;
}

</style>
<body>

<div class="container">
<div class="row">
    <div class="col-md-3"></div>
    <div class="col-md-6">
  <h2>Form</h2>
  </div>
    </div>
  <form action="#" id="form">
  <div class="row">
    <div class="col-md-3"></div>
    <div class="col-md-6">
  <div class="alert alert-danger" id="err" style="display:none;">
  <strong>Error!</strong> Email Can't be Blank.
</div>

<div class="alert alert-danger" id="email_err" style="display:none;">
  <strong>Error!</strong> Email Not Found.
</div>
</div>
    </div>
    <div class="row">
    <div class="col-md-3"></div>
    <div class="col-md-6">
    <div class="form-group">
      <label for="email">Email:</label>
      <input type="email" class="form-control" id="email" placeholder="Enter email" name="email" id="email">
    </div>
    </div>
</div>
<div class="row">
    <div class="col-md-3"></div>
    <div class="col-md-6">
    
    <button type="submit" class="btn btn-primary" id="submit">Submit</button>
    </div>
    </div>
  </form>
</div>

</body>


<script>
    $("#form").submit(function(e){
        e.preventDefault();
        $("#err").hide();
        $("#email_err").hide();
        var email=$("#email").val();
        if(email == ""){
$("#err").show();

        }
        else{
 $.ajax({
        url: "search_customer.php ",
        method: "POST", // First change type to method here    
        data: {
            email: email,
        },
        success: function(response) {
          //  document.getElementById("disp").innerHTML = response;



          if(response == true){
            window.location.href="/redirectlink";
          }
          else{
        $("#email_err").show();
          }
        },
        error: function(error) {
            $("#email_err").show();
        }
    });   
}
});

</script>
</html>
