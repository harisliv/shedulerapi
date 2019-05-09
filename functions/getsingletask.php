<?php
function getSingleTask() {
try {
  // create db query
  // ADD AUTH TO QUERY
  $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
  $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
  $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
  $query->execute();

  // get row count
  $rowCount = $query->rowCount();

  // create task array to store returned task
  $taskArray = array();

  if($rowCount === 0) {
    // set up response for unsuccessful return
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Task not found");
    $response->send();
    exit;
  }

  // for each row returned
  while($row = $query->fetch(PDO::FETCH_ASSOC)) {
    // create new task object for each row
    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

    // create task and store in array for return in json data
    $taskArray[] = $task->returnTaskAsArray();
  }

  // bundle tasks and rows returned into an array to return in the json data
  $returnData = array();
  $returnData['rows_returned'] = $rowCount;
  $returnData['tasks'] = $taskArray;

  // set up response for successful return
  $response = new Response();
  $response->setHttpStatusCode(200);
  $response->setSuccess(true);
  $response->toCache(true);
  $response->setData($returnData);
  $response->send();
  exit;
}
// if error with sql query return a json error
catch(TaskException $ex) {
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage($ex->getMessage());
  $response->send();
  exit;
}
catch(PDOException $ex) {
  error_log("Database Query Error: ".$ex, 0);
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage("Failed to get task");
  $response->send();
  exit;
}

}
