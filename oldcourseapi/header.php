<?php

function headernav()
{
  ?>
<!DOCTYPE html>
<head>

  <!-- Bootstrap 4 CSS and custom CSS -->
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous" />
  <link rel="stylesheet" type="text/css" href="custom.css" />
  <style>
      body { padding-top: 5rem; }
      .starter-template { padding: 3rem 1.5rem; }
      #logout{ display:none; }
  </style>

</head>

<body>

<!-- navbar -->
<nav class="navbar navbar-expand-md navbar-dark bg-dark fixed-top">
    <a class="navbar-brand" href="#">Navbar</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNavAltMarkup">
        <div class="navbar-nav">
            <a class="nav-item nav-link" href="http://localhost/wsc/searchcoursebyid.php">Search Course by ID</a>
            <a class="nav-item nav-link" href="http://localhost/wsc/showallcourses.php">Show All Courses</a>
            <a class="nav-item nav-link" href="http://localhost/wsc/newcourse.php">Create New Course</a>
            <a class="nav-item nav-link" href="http://localhost/wsc/signup.php">Sign Up</a>
            <a class="nav-item nav-link" href="http://localhost/wsc/searchroombydatetime.php">Search ROOM by date and time</a>
            <a class="nav-item nav-link" href="http://localhost/wsc/newroomavailable.php">BIND NEW ROOM</a>
            <a class="nav-item nav-link" href="http://localhost/wsc/newcoursethisyear.php">new course this year</a>
            <a class="nav-item nav-link" href="http://localhost/wsc/newscheduler.php">new scheduler</a>
        </div>
    </div>
</nav>

<main role="main" class="container starter-template">

    <div class="row">
        <div class="col">
<?php
}
?>
