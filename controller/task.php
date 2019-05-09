<?php

require_once('db.php');
require_once('../model/task.php');
require_once('../model/response.php');
require_once('../functions/readwritedb.php');
require_once('../functions/authtoken.php');
require_once('../functions/createnewtask.php');
require_once('../functions/deletesingletask.php');
require_once('../functions/getalltasks.php');
require_once('../functions/getcompletedtasks.php');
require_once('../functions/getpagetasks.php');
require_once('../functions/getsingletask.php');
require_once('../functions/readwritedb.php');
require_once('../functions/updatesingletask.php');

// attempt to set up connections to read and write db connections
ReadWriteDB();
// BEGIN OF AUTH SCRIPT
AuthToken();
// END OF AUTH SCRIPT

// within this if/elseif statement, it is important to get the correct order (if query string GET param is used in multiple routes)

// check if taskid is in the url e.g. /tasks/1
if (array_key_exists("taskid",$_GET)) {
      // get task id from query string
      $taskid = $_GET['taskid'];

      //check to see if task id in query string is not empty and is number, if not return json error
      if($taskid == '' || !is_numeric($taskid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Task ID cannot be blank or must be numeric");
        $response->send();
        exit;
      }
        // if request is a GET, e.g. get task
      if($_SERVER['REQUEST_METHOD'] === 'GET') {
          getSingleTask();
        }
        // else if request if a DELETE e.g. delete task
        elseif($_SERVER['REQUEST_METHOD'] === 'DELETE') {
          deleteSingleTask();
        }
        // handle updating task
        elseif($_SERVER['REQUEST_METHOD'] === 'PATCH') {
          updateSingleTask();
        }
        // if any other request method apart from GET, PATCH, DELETE is used then return 405 method not allowed
        else {
          $response = new Response();
          $response->setHttpStatusCode(405);
          $response->setSuccess(false);
          $response->addMessage("Request method not allowed");
          $response->send();
          exit;
        }
}

// get tasks that have submitted a completed filter
elseif(array_key_exists("completed",$_GET)) {
    getCompletedTasks();
}

// handle getting all tasks page of 20 at a time
elseif(array_key_exists("page",$_GET)) {
      getPageTasks();
}

// handle getting all tasks or creating a new one
elseif(empty($_GET)) {
  // if request is a GET e.g. get tasks
  if($_SERVER['REQUEST_METHOD'] === 'GET') {
      getAllTasks();
  }
  // else if request is a POST e.g. create task
  elseif($_SERVER['REQUEST_METHOD'] === 'POST') {
      postNewTask();
  }

  else {
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
  }
}

// return 404 error if endpoint not available
else {
  $response = new Response();
  $response->setHttpStatusCode(404);
  $response->setSuccess(false);
  $response->addMessage("Endpoint not found");
  $response->send();
  exit;
}
