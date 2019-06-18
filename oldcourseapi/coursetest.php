<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once('../model/course.php');

try{
  $course = new Course("sdawd123", "Title", "Description", "E", "Y", 1 ,1, 2);
  header('Content_type: application/json;charset=UTF-8');
  echo json_encode($course->returnCourseAsArray());
}
catch(CourseException $ex){
  echo "Error: ".$ex->getMessage();
}
