<?php
function updateSingleTask() {
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
  // update task
  try {
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
    $rawPatchData = file_get_contents('php://input');

    if(!$jsonData = json_decode($rawPatchData)) {
      // set up response for unsuccessful request
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      $response->addMessage("Request body is not valid JSON");
      $response->send();
      exit;
    }

    // set task field updated to false initially
    $title_updated = false;
    $description_updated = false;
    $deadline_updated = false;
    $completed_updated = false;

    // create blank query fields string to append each field to
    $queryFields = "";

    // check if title exists in PATCH
    if(isset($jsonData->title)) {
      // set title field updated to true
      $title_updated = true;
      // add title field to query field string
      $queryFields .= "title = :title, ";
    }

    // check if description exists in PATCH
    if(isset($jsonData->description)) {
      // set description field updated to true
      $description_updated = true;
      // add description field to query field string
      $queryFields .= "description = :description, ";
    }

    // check if deadline exists in PATCH
    if(isset($jsonData->deadline)) {
      // set deadline field updated to true
      $deadline_updated = true;
      // add deadline field to query field string
      $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
    }

    // check if completed exists in PATCH
    if(isset($jsonData->completed)) {
      // set completed field updated to true
      $completed_updated = true;
      // add completed field to query field string
      $queryFields .= "completed = :completed, ";
    }

    // remove the right hand comma and trailing space
    $queryFields = rtrim($queryFields, ", ");

    // check if any task fields supplied in JSON
    if($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false) {
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      $response->addMessage("No task fields provided");
      $response->send();
      exit;
    }
    // ADD AUTH TO QUERY
    // create db query to get task from database to update - use master db
    $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
    $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
    $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
    $query->execute();

    // get row count
    $rowCount = $query->rowCount();

    // make sure that the task exists for a given task id
    if($rowCount === 0) {
      // set up response for unsuccessful return
      $response = new Response();
      $response->setHttpStatusCode(404);
      $response->setSuccess(false);
      $response->addMessage("No task found to update");
      $response->send();
      exit;
    }

    // for each row returned - should be just one
    while($row = $query->fetch(PDO::FETCH_ASSOC)) {
      // create new task object
      $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
    }
    // ADD AUTH TO QUERY
    // create the query string including any query fields
    $queryString = "update tbltasks set ".$queryFields." where id = :taskid and userid = :userid";
    // prepare the query
    $query = $writeDB->prepare($queryString);

    // if title has been provided
    if($title_updated === true) {
      // set task object title to given value (checks for valid input)
      $task->setTitle($jsonData->title);
      // get the value back as the object could be handling the return of the value differently to
      // what was provided
      $up_title = $task->getTitle();
      // bind the parameter of the new value from the object to the query (prevents SQL injection)
      $query->bindParam(':title', $up_title, PDO::PARAM_STR);
    }

    // if description has been provided
    if($description_updated === true) {
      // set task object description to given value (checks for valid input)
      $task->setDescription($jsonData->description);
      // get the value back as the object could be handling the return of the value differently to
      // what was provided
      $up_description = $task->getDescription();
      // bind the parameter of the new value from the object to the query (prevents SQL injection)
      $query->bindParam(':description', $up_description, PDO::PARAM_STR);
    }

    // if deadline has been provided
    if($deadline_updated === true) {
      // set task object deadline to given value (checks for valid input)
      $task->setDeadline($jsonData->deadline);
      // get the value back as the object could be handling the return of the value differently to
      // what was provided
      $up_deadline = $task->getDeadline();
      // bind the parameter of the new value from the object to the query (prevents SQL injection)
      $query->bindParam(':deadline', $up_deadline, PDO::PARAM_STR);
    }

    // if completed has been provided
    if($completed_updated === true) {
      // set task object completed to given value (checks for valid input)
      $task->setCompleted($jsonData->completed);
      // get the value back as the object could be handling the return of the value differently to
      // what was provided
      $up_completed= $task->getCompleted();
      // bind the parameter of the new value from the object to the query (prevents SQL injection)
      $query->bindParam(':completed', $up_completed, PDO::PARAM_STR);
    }

    // bind the task id provided in the query string
    $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
    // bind the user id returned
    $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
    // run the query
    $query->execute();

    // get affected row count
    $rowCount = $query->rowCount();

    // check if row was actually updated, could be that the given values are the same as the stored values
    if($rowCount === 0) {
      // set up response for unsuccessful return
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      $response->addMessage("Task not updated - given values may be the same as the stored values");
      $response->send();
      exit;
    }
    // ADD AUTH TO QUERY
    // create db query to return the newly edited task - connect to master database
    $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
    $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
    $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
    $query->execute();

    // get row count
    $rowCount = $query->rowCount();

    // check if task was found
    if($rowCount === 0) {
      // set up response for unsuccessful return
      $response = new Response();
      $response->setHttpStatusCode(404);
      $response->setSuccess(false);
      $response->addMessage("No task found");
      $response->send();
      exit;
    }
    // create task array to store returned tasks
    $taskArray = array();

    // for each row returned
    while($row = $query->fetch(PDO::FETCH_ASSOC)) {
      // create new task object for each row returned
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
    $response->addMessage("Task updated");
    $response->setData($returnData);
    $response->send();
    exit;
  }
  catch(TaskException $ex) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage($ex->getMessage());
    $response->send();
    exit;
  }
  // if error with sql query return a json error
  catch(PDOException $ex) {
    error_log("Database Query Error: ".$ex, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Failed to update task - check your data for errors");
    $response->send();
    exit;
  }

}
