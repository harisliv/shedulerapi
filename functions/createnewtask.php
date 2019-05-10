<?php
function postNewTask() {

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
  
  // create task
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

    // check if post request contains title and completed data in body as these are mandatory
    if(!isset($jsonData->title) || !isset($jsonData->completed)) {
      $response = new Response();
      $response->setHttpStatusCode(400);
      $response->setSuccess(false);
      (!isset($jsonData->title) ? $response->addMessage("Title field is mandatory and must be provided") : false);
      (!isset($jsonData->completed) ? $response->addMessage("Completed field is mandatory and must be provided") : false);
      $response->send();
      exit;
    }

    // create new task with data, if non mandatory fields not provided then set to null
    $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed);
    // get title, description, deadline, completed and store them in variables
    $title = $newTask->getTitle();
    $description = $newTask->getDescription();
    $deadline = $newTask->getDeadline();
    $completed = $newTask->getCompleted();

    // ADD AUTH TO QUERY
    // create db query
    $query = $writeDB->prepare('insert into tbltasks (title, description, deadline, completed, userid) values (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed, :userid)');
    $query->bindParam(':title', $title, PDO::PARAM_STR);
    $query->bindParam(':description', $description, PDO::PARAM_STR);
    $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
    $query->bindParam(':completed', $completed, PDO::PARAM_STR);
    $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
    $query->execute();

    // get row count
    $rowCount = $query->rowCount();

    // check if row was actually inserted, PDO exception should have caught it if not.
    if($rowCount === 0) {
      // set up response for unsuccessful return
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to create task");
      $response->send();
      exit;
    }

    // get last task id so we can return the Task in the json
    $lastTaskID = $writeDB->lastInsertId();
    // ADD AUTH TO QUERY
    // create db query to get newly created task - get from master db not read slave as replication may be too slow for successful read
    $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
    $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
    $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
    $query->execute();

    // get row count
    $rowCount = $query->rowCount();

    // make sure that the new task was returned
    if($rowCount === 0) {
      // set up response for unsuccessful return
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("Failed to retrieve task after creation");
      $response->send();
      exit;
    }

    // create empty array to store tasks
    $taskArray = array();

    // for each row returned - should be just one
    while($row = $query->fetch(PDO::FETCH_ASSOC)) {
      // create new task object
      $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

      // create task and store in array for return in json data
      $taskArray[] = $task->returnTaskAsArray();
    }
    // bundle tasks and rows returned into an array to return in the json data
    $returnData = array();
    $returnData['rows_returned'] = $rowCount;
    $returnData['tasks'] = $taskArray;

    //set up response for successful return
    $response = new Response();
    $response->setHttpStatusCode(201);
    $response->setSuccess(true);
    $response->addMessage("Task created");
    $response->setData($returnData);
    $response->send();
    exit;
  }
  // if task fails to create due to data types, missing fields or invalid data then send error json
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
    $response->addMessage("Failed to insert task into database - check submitted data for errors");
    $response->send();
    exit;
  }

}
