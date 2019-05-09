<?php
function deleteSession() {

// attempt to query the database to check token details - use write connection as it needs to be synchronous for token
try {
  // create db query to delete session where access token is equal to the one provided (leave other sessions active)
  // doesn't matter about if access token has expired as we are deleting the session
  $query = $writeDB->prepare('delete from tblsessions where id = :sessionid and accesstoken = :accesstoken');
  $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
  $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
  $query->execute();

  // get row count
  $rowCount = $query->rowCount();

  if($rowCount === 0) {
    // set up response for unsuccessful log out response
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Failed to log out of this session using access token provided");
    $response->send();
    exit;
  }

  // build response data array which contains the session id that has been deleted (logged out)
  $returnData = array();
  $returnData['session_id'] = intval($sessionid);

  // send successful response for log out
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
  $response->addMessage("There was an issue logging out - please try again");
  $response->send();
  exit;
}

}
