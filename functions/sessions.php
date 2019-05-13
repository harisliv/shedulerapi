<?php

require_once('db.php');
require_once('../model/response.php');
require_once('../functions/deletesession.php');
require_once('../functions/createsession.php');
require_once('../functions/updatesession.php');
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// note: never cache login or token http requests/responses
// (our response model defaults to no cache unless specifically set)

// attempt to set up connections to db connections
try {

  $writeDB = DB::connectWriteDB();

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

// check if sessionid is in the url e.g. /sessions/1
if (array_key_exists("sessionid",$_GET)) {
  // get sessions id from query string
  $sessionid = $_GET['sessionid'];

  // check to see if sessions id in query string is not empty and is number, if not return json error
  if($sessionid == '' || !is_numeric($sessionid)) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    ($sessionid == '' ? $response->addMessage("Session ID cannot be blank") : false);
    (!is_numeric($sessionid) ? $response->addMessage("Session ID must be numeric") : false);
    $response->send();
    exit;
  }

  // check to see if access token is provided in the HTTP Authorization header and that the value is longer than 0 chars
  // don't forget the Apache fix in .htaccess file
  // 401 error is for authentication failed or has not yet been provided
  if(!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1)
  {
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
    (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false);
    $response->send();
    exit;
  }

  // get supplied access token from authorisation header - used for delete (log out) and patch (refresh)
  $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

  // if request is a DELETE, e.g. delete session
  if($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    deleteSession();
  }

  // if request is a PATCH, e.g. renew access token
  elseif($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    updateSession();
  }

  // error when not DELETE or PATCH
  else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
  }



}

// handle creating new session, e.g. log in
elseif(empty($_GET)) {
  // handle creating new session, e.g. logging in
  // check to make sure the request is POST only - else exit with error response
    createSession();
}
else {
  $response = new Response();
  $response->setHttpStatusCode(404);
  $response->setSuccess(false);
  $response->addMessage("Endpoint not found");
  $response->send();
  exit;
}
