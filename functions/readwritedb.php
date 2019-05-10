<?php
function ReadWriteDB()
require_once('../controller/db.php');
require_once('../model/response.php');

{
// attempt to set up connections to read and write db connections
try {
  $writeDB = DB::connectWriteDB();
  $readDB = DB::connectReadDB();
}
catch(PDOException $ex) {
  // log connection error for troubleshooting and return a json error response
  error_log("Connection Error: ".$ex, 0);
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage("Database connection error");
  $response->send();
  exit;
}
}
