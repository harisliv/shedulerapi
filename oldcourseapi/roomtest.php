<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once('../model/room.php');

try{
  $room = new Room(1 ,1 ,1, "Y");
  header('Content_type: application/json;charset=UTF-8');
  echo json_encode($room->returnRoomAsArray());
}
catch(CourseException $ex){
  echo "Error: ".$ex->getMessage();
}
