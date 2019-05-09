<?php
function deleteSingleTask() {
  // attempt to query the database
  try {
    // ADD AUTH TO QUERY
    // create db query
    $query = $writeDB->prepare('delete from tbltasks where id = :taskid and userid = :userid');
    $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
    $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
    $query->execute();

    // get row count
    $rowCount = $query->rowCount();

    if($rowCount === 0) {
      // set up response for unsuccessful return
      $response = new Response();
      $response->setHttpStatusCode(404);
      $response->setSuccess(false);
      $response->addMessage("Task not found");
      $response->send();
      exit;
    }
    // set up response for successful return
    $response = new Response();
    $response->setHttpStatusCode(200);
    $response->setSuccess(true);
    $response->addMessage("Task deleted");
    $response->send();
    exit;
  }
  // if error with sql query return a json error
  catch(PDOException $ex) {
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Failed to delete task");
    $response->send();
    exit;
  }

}
