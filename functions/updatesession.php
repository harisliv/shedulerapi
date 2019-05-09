<?php
function updateSession() {

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

// get PATCH request body as the PATCHed data will be JSON format
$rawPatchdata = file_get_contents('php://input');

if(!$jsonData = json_decode($rawPatchdata)) {
  // set up response for unsuccessful request
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  $response->addMessage("Request body is not valid JSON");
  $response->send();
  exit;
}

// check if patch request contains access token
if(!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1)  {
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  (!isset($jsonData->refresh_token) ? $response->addMessage("Refresh Token not supplied") : false);
  (strlen($jsonData->refresh_token) < 1 ? $response->addMessage("Refresh Token cannot be blank") : false);
  $response->send();
  exit;
}

// attempt to query the database to check token details - use write connection as it needs to be synchronous for token
try {

  $refreshtoken = $jsonData->refresh_token;

  // get user record for provided session id, access AND refresh token
  // create db query to retrieve user details from provided access and refresh token
  $query = $writeDB->prepare('SELECT tblsessions.id as sessionid, tblsessions.userid as userid, accesstoken, refreshtoken, useractive, loginattempts, accesstokenexpiry, refreshtokenexpiry from tblsessions, tblusers where tblusers.id = tblsessions.userid and tblsessions.id = :sessionid and tblsessions.accesstoken = :accesstoken and tblsessions.refreshtoken = :refreshtoken');
  $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
  $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
  $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
  $query->execute();

  // get row count
  $rowCount = $query->rowCount();

  if($rowCount === 0) {
    // set up response for unsuccessful access token refresh attempt
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    $response->addMessage("Access Token or Refresh Token is incorrect for session id");
    $response->send();
    exit;
  }

  // get returned row
  $row = $query->fetch(PDO::FETCH_ASSOC);

  // save returned details into variables
  $returned_sessionid = $row['sessionid'];
  $returned_userid = $row['userid'];
  $returned_accesstoken = $row['accesstoken'];
  $returned_refreshtoken = $row['refreshtoken'];
  $returned_useractive = $row['useractive'];
  $returned_loginattempts = $row['loginattempts'];
  $returned_accesstokenexpiry = $row['accesstokenexpiry'];
  $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

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

  // check if refresh token has expired
  if(strtotime($returned_refreshtokenexpiry) < time()) {
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    $response->addMessage("Refresh token has expired - please log in again");
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

  // create the query string to update the current session row in the sessions table and set the token and refresh token as well as their expiry dates and times
  $query = $writeDB->prepare('update tblsessions set accesstoken = :accesstoken, accesstokenexpiry = date_add(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), refreshtoken = :refreshtoken, refreshtokenexpiry = date_add(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND) where id = :sessionid and userid = :userid and accesstoken = :returnedaccesstoken and refreshtoken = :returnedrefreshtoken');
  // bind the user id
  $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
  // bind the session id
  $query->bindParam(':sessionid', $returned_sessionid, PDO::PARAM_INT);
  // bind the access token
  $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
  // bind the access token expiry date
  $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
  // bind the refresh token
  $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
  // bind the refresh token expiry date
  $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
  // bind the old access token for where clause as user could have multiple sessions
  $query->bindParam(':returnedaccesstoken', $returned_accesstoken, PDO::PARAM_STR);
  // bind the old refresh token for where clause as user could have multiple sessions
  $query->bindParam(':returnedrefreshtoken', $returned_refreshtoken, PDO::PARAM_STR);
  // run the query
  $query->execute();

  // get count of rows updated - should be 1
  $rowCount = $query->rowCount();

  // check that a row has been updated
  if($rowCount === 0) {
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    $response->addMessage("Access token could not be refreshed - please log in again");
    $response->send();
    exit;
  }

  // build response data array which contains the session id, access token and refresh token
  $returnData = array();
  $returnData['session_id'] = $returned_sessionid;
  $returnData['access_token'] = $accesstoken;
  $returnData['access_token_expiry'] = $access_token_expiry_seconds;
  $returnData['refresh_token'] = $refreshtoken;
  $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;

  $response = new Response();
  $response->setHttpStatusCode(200);
  $response->setSuccess(true);
  $response->setData($returnData);
  $response->send();
  exit;
}
catch(PDOException $ex) {
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage("There was an issue refreshing access token - please log in again");
  $response->send();
  exit;
}

}
