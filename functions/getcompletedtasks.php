<?php
function getCompletedTasks() {
  // get completed from query string
$completed = $_GET['completed'];

// check to see if completed in query string is either Y or N
if($completed !== "Y" && $completed !== "N") {
  $response = new Response();
  $response->setHttpStatusCode(400);
  $response->setSuccess(false);
  $response->addMessage("Completed filter must be Y or N");
  $response->send();
  exit;
}

if($_SERVER['REQUEST_METHOD'] === 'GET') {
  // attempt to query the database
  try {
    // ADD AUTH TO QUERY
    // create db query
    $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where completed like :completed and userid = :userid');
    $query->bindParam(':completed', $completed, PDO::PARAM_STR);
    $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
    $query->execute();

    // get row count
    $rowCount = $query->rowCount();

    // create task array to store returned tasks
    $taskArray = array();

    // for each row returned
    while($row = $query->fetch(PDO::FETCH_ASSOC)) {
      // create new task object for each row
      $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

      // create task and store in array for return in json data
      $taskArray[] = $task->returnTaskAsArray();
    }

    // bundle task and rows returned into an array to return in the json data
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
// if any other request method apart from GET is used then return 405 method not allowed
else {
  $response = new Response();
  $response->setHttpStatusCode(405);
  $response->setSuccess(false);
  $response->addMessage("Request method not allowed");
  $response->send();
  exit;
}
}
