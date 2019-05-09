<?php
function createSession() {

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $response = new Response();
  $response->setHttpStatusCode(405);
  $response->setSuccess(false);
  $response->addMessage("Request method not allowed");
  $response->send();
  exit;
}

// delay login by 1 second to slow down any potential brute force attacks
sleep(1);

// check request's content type header is JSON
if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
  // set up response for unsuccessful request
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  $response->addMessage("Content Type header not set to JSON");
  $response->send();
  exit;
}

// get POST request body as the POSTed data will be JSON format
$rawPostData = file_get_contents('php://input');

if(!$jsonData = json_decode($rawPostData)) {
  // set up response for unsuccessful request
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  $response->addMessage("Request body is not valid JSON");
  $response->send();
  exit;
}

// check if post request contains username and password in body as they are mandatory
if(!isset($jsonData->username) || !isset($jsonData->password)) {
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  (!isset($jsonData->username) ? $response->addMessage("Username not supplied") : false);
  (!isset($jsonData->password) ? $response->addMessage("Password not supplied") : false);
  $response->send();
  exit;
}

// check to make sure that username and password are not empty and not greater than 255 characters
if(strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) {
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  (strlen($jsonData->username) < 1 ? $response->addMessage("Username cannot be blank") : false);
  (strlen($jsonData->username) > 255 ? $response->addMessage("Username must be less than 255 characters") : false);
  (strlen($jsonData->password) < 1 ? $response->addMessage("Password cannot be blank") : false);
  (strlen($jsonData->password) > 255 ? $response->addMessage("Password must be less than 255 characters") : false);
  $response->send();
  exit;
}

// attempt to query the database to check user details - use write connection as it needs to be synchronous for password/token
try {
  $username = $jsonData->username;
  $password = $jsonData->password;
  // create db query
  $query = $writeDB->prepare('SELECT id, fullname, username, password, useractive, loginattempts from tblusers where username = :username');
  $query->bindParam(':username', $username, PDO::PARAM_STR);
  $query->execute();

  // get row count
  $rowCount = $query->rowCount();

  if($rowCount === 0) {
    // set up response for unsuccessful login attempt - obscure what is incorrect by saying username or password is wrong
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    $response->addMessage("Username or password is incorrect");
    $response->send();
    exit;
  }

  // get first row returned
  $row = $query->fetch(PDO::FETCH_ASSOC);

  // save returned details into variables
  $returned_id = $row['id'];
  $returned_fullname = $row['fullname'];
  $returned_username = $row['username'];
  $returned_password = $row['password'];
  $returned_useractive = $row['useractive'];
  $returned_loginattempts = $row['loginattempts'];

  // check if account is active
  if($returned_useractive != 'Y') {
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    $response->addMessage("User account is not active");
    $response->send();
    exit;
  }

  // check if account is locked out
  if($returned_loginattempts >= 3) {
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    $response->addMessage("User account is currently locked out");
    $response->send();
    exit;
  }

  // check if password is the same using the hash
  if(!password_verify($password, $returned_password)) {
    // create the query to increment attempts figure
    $query = $writeDB->prepare('update tblusers set loginattempts = loginattempts+1 where id = :id');
    // bind the user id
    $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
    // run the query
    $query->execute();

    // send response
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    $response->addMessage("Username or password is incorrect");
    $response->send();
    exit;
  }

  // generate access token
  // use 24 random bytes to generate a token then encode this as base64
  // suffix with unix time stamp to guarantee uniqueness (stale tokens)
  $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

  // generate refresh token
  // use 24 random bytes to generate a refresh token then encode this as base64
  // suffix with unix time stamp to guarantee uniqueness (stale tokens)
  $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

  // set access token and refresh token expiry in seconds (access token 20 minute lifetime and refresh token 14 days lifetime)
  // send seconds rather than date/time as this is not affected by timezones
  $access_token_expiry_seconds = 1200;
  $refresh_token_expiry_seconds = 1209600;
}
catch(PDOException $ex) {
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage("There was an issue logging in - please try again");
  $response->send();
  exit;
}
// new try catch as this is a transaction so should include roll back if error
try {
  // start transaction as two queries should run one after the other
  $writeDB->beginTransaction();
  // create the query string to reset attempts figure after successful login
  $query = $writeDB->prepare('update tblusers set loginattempts = 0 where id = :id');
  // bind the user id
  $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
  // run the query
  $query->execute();

  // create the query string to insert new session into sessions table and set the token and refresh token as well as their expiry dates and times
  $query = $writeDB->prepare('insert into tblsessions (userid, accesstoken, accesstokenexpiry, refreshtoken, refreshtokenexpiry) values (:userid, :accesstoken, date_add(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), :refreshtoken, date_add(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND))');
  // bind the user id
  $query->bindParam(':userid', $returned_id, PDO::PARAM_INT);
  // bind the access token
  $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
  // bind the access token expiry date
  $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
  // bind the refresh token
  $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
  // bind the refresh token expiry date
  $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
  // run the query
  $query->execute();

  // get last session id so we can return the session id in the json
  $lastSessionID = $writeDB->lastInsertId();

  // commit new row and updates if successful
  $writeDB->commit();

  // build response data array which contains the access token and refresh tokens
  $returnData = array();
  $returnData['session_id'] = intval($lastSessionID);
  $returnData['access_token'] = $accesstoken;
  $returnData['access_token_expires_in'] = $access_token_expiry_seconds;
  $returnData['refresh_token'] = $refreshtoken;
  $returnData['refresh_token_expires_in'] = $refresh_token_expiry_seconds;

  $response = new Response();
  $response->setHttpStatusCode(201);
  $response->setSuccess(true);
  $response->setData($returnData);
  $response->send();
  exit;
}
catch(PDOException $ex) {
  // roll back update/insert if error
  $writeDB->rollBack();
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage("There was an issue logging in - please try again");
  $response->send();
  exit;
}

}
