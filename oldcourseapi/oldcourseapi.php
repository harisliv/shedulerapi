<?php

//header('Content-Type: text/html; charset=utf-8');
require_once('db.php');
require_once('../model/course.php');
require_once('../model/response.php');



// attempt to set up connections to read and write db connections
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

// BEGIN OF AUTH SCRIPT
// Authenticate user with access token
// check to see if access token is provided in the HTTP Authorization header and that the value is longer than 0 chars
// don't forget the Apache fix in .htaccess file
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

// attempt to query the database to check token details - use write connection as it needs to be synchronous for token
try {
  // create db query to check access token is equal to the one provided
  $query = $writeDB->prepare('select userid, accesstokenexpiry, useractive, loginattempts from tblsessions, tblusers where tblsessions.userid = tblusers.id and accesstoken = :accesstoken');
  $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
  $query->execute();

  // get row count
  $rowCount = $query->rowCount();

  if($rowCount === 0) {
    // set up response for unsuccessful log out response
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    $response->addMessage("Invalid access token");
    $response->send();
    exit;
  }

  // get returned row
  $row = $query->fetch(PDO::FETCH_ASSOC);

  // save returned details into variables
  $returned_userid = $row['userid'];
  $returned_accesstokenexpiry = $row['accesstokenexpiry'];
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

  // check if access token has expired
  if(strtotime($returned_accesstokenexpiry) < time()) {
    $response = new Response();
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    $response->addMessage("Access token has expired");
    $response->send();
    exit;
  }
}
catch(PDOException $ex) {
  $response = new Response();
  $response->setHttpStatusCode(500);
  $response->setSuccess(false);
  $response->addMessage("There was an issue authenticating - please try again");
  $response->send();
  exit;
}

// END OF AUTH SCRIPT

// within this if/elseif statement, it is important to get the correct order (if query string GET param is used in multiple routes)

// check if taskid is in the url e.g. /tasks/1
if (array_key_exists("courseid",$_GET)) {
  // get task id from query string
  $courseid = $_GET['courseid'];

  //check to see if task id in query string is not empty and is number, if not return json error
  if($courseid == '' ) {
    $response = new Response();
    $response->setHttpStatusCode(400);
    $response->setSuccess(false);
    $response->addMessage("Course ID cannot be blank or must be numeric");
    $response->send();
    exit;
  }

  // if request is a GET, e.g. get task
  if($_SERVER['REQUEST_METHOD'] === 'GET') {
    // attempt to query the database
    try {
      // create db query
      // ADD AUTH TO QUERY
      $query = $readDB->prepare('SELECT id, name, curr, period, active, hours_theory, hours_lab, hours_practice from course_list where id = :courseid and userid = :userid');
      $query->bindParam(':courseid', $courseid, PDO::PARAM_STR);
      $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
  		$query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // create task array to store returned task
      $courseArray = array();

      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Course not found");
        $response->send();
        exit;
      }

      // for each row returned
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object for each row
        $course = new Course($row['id'], $row['name'], $row['curr'], $row['period'], $row['active'], $row['hours_theory'], $row['hours_lab'], $row['hours_practice']);

        // create task and store in array for return in json data
  	    $courseArray[] = $course->returnCourseAsArray();
      }

      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['courses'] = $courseArray;

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
    catch(CourseException $ex) {
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
  // else if request if a DELETE e.g. delete task
  elseif($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // attempt to query the database
    try {
      // ADD AUTH TO QUERY
      // create db query
      $query = $writeDB->prepare('delete from course_list where id = :courseid and userid = :userid');
      $query->bindParam(':courseid', $courseid, PDO::PARAM_STR);
      $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
      $query->execute();

      // get row count
      $rowCount = $query->rowCount();

      if($rowCount === 0) {
        // set up response for unsuccessful return
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Course not found");
        $response->send();
        exit;
      }
      // set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->addMessage("Course deleted");
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
  // handle updating task
  elseif($_SERVER['REQUEST_METHOD'] === 'PATCH') {
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
      //$id_updated = false;
      $name_updated = false;
      $curr_updated = false;
      $period_updated = false;
      $active_updated = false;
      $hours_theory_updated = false;
      $hours_lab_updated = false;
      $hours_practice_updated = false;


      // create blank query fields string to append each field to
      $queryFields = "";

      // check if description exists in PATCH
      if(isset($jsonData->name)) {
        // set description field updated to true
        $name_updated = true;
        // add description field to query field string
        $queryFields .= "name = :name, ";
      }

      // check if completed exists in PATCH
      if(isset($jsonData->curr)) {
        // set completed field updated to true
        $curr_updated = true;
        // add completed field to query field string
        $queryFields .= "curr = :curr, ";
      }

      // check if title exists in PATCH
      if(isset($jsonData->period)) {
        // set title field updated to true
        $period_updated = true;
        // add title field to query field string
        $queryFields .= "period = :period, ";
      }

      // check if description exists in PATCH
      if(isset($jsonData->active)) {
        // set description field updated to true
        $active_updated = true;
        // add description field to query field string
        $queryFields .= "active = :active, ";
      }

      // check if completed exists in PATCH
      if(isset($jsonData->hours_theory)) {
        // set completed field updated to true
        $hours_theory_updated = true;
        // add completed field to query field string
        $queryFields .= "hours_theory = :hours_theory, ";
      }

      // check if completed exists in PATCH
      if(isset($jsonData->hours_lab)) {
        // set completed field updated to true
        $hours_lab_updated = true;
        // add completed field to query field string
        $queryFields .= "hours_lab = :hours_lab, ";
      }

      // check if completed exists in PATCH
      if(isset($jsonData->hours_practice)) {
        // set completed field updated to true
        $hours_practice_updated = true;
        // add completed field to query field string
        $queryFields .= "hours_practice = :hours_practice, ";
      }

      // remove the right hand comma and trailing space
      $queryFields = rtrim($queryFields, ", ");

      // check if any task fields supplied in JSON
      if($name_updated === false && $curr_updated === false && $period_updated === false && $active_updated === false && $hours_theory_updated === false && $hours_lab_updated === false && $hours_practice_updated === false) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("No task fields provided");
        $response->send();
        exit;
      }
      // ADD AUTH TO QUERY
      // create db query to get task from database to update - use master db
      $query = $writeDB->prepare('SELECT id, name, curr, period, active, hours_theory, hours_lab, hours_practice from course_list where id = :courseid and userid = :userid');
      $query->bindParam(':courseid', $courseid, PDO::PARAM_STR);
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
        $course = new Course($row['id'], $row['name'], $row['curr'], $row['period'], $row['active'], $row['hours_theory'], $row['hours_lab'], $row['hours_practice']);

        // create task and store in array for return in json data
  	    $courseArray[] = $course->returnCourseAsArray();
      }

      // ADD AUTH TO QUERY
      // create the query string including any query fields
      $queryString = "update course_list set ".$queryFields." where id = :courseid and userid = :userid";
      // prepare the query
      $query = $writeDB->prepare($queryString);

      // if title has been provided
      if($name_updated === true) {
        // set task object title to given value (checks for valid input)
        $course->setName($jsonData->name);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_name = $course->getName();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':name', $up_name, PDO::PARAM_STR);
      }

      // if title has been provided
      if($curr_updated === true) {
        // set task object title to given value (checks for valid input)
        $course->setCurr($jsonData->curr);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_curr = $course->getCurr();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':curr', $up_curr, PDO::PARAM_STR);
      }

      // if title has been provided
      if($period_updated === true) {
        // set task object title to given value (checks for valid input)
        $course->setPeriod($jsonData->period);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_period = $course->getPeriod();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':period', $up_period, PDO::PARAM_STR);
      }

      // if title has been provided
      if($active_updated === true) {
        // set task object title to given value (checks for valid input)
        $course->setActive($jsonData->active);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_active = $course->getActive();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':active', $up_active, PDO::PARAM_STR);
      }

      // if title has been provided
      if($hours_theory_updated === true) {
        // set task object title to given value (checks for valid input)
        $course->setHoursTheory($jsonData->hours_theory);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_hours_theory = $course->getHoursTheory();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':hours_theory', $up_hours_theory, PDO::PARAM_INT);
      }

      // if title has been provided
      if($hours_lab_updated === true) {
        // set task object title to given value (checks for valid input)
        $course->setHoursLab($jsonData->hours_lab);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_hours_lab = $course->getHoursLab();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':hours_lab', $up_hours_lab, PDO::PARAM_INT);
      }

      // if title has been provided
      if($hours_practice_updated === true) {
        // set task object title to given value (checks for valid input)
        $course->setHoursPractice($jsonData->hours_practice);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_hours_practice = $course->getHoursPractice();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':hours_practice', $up_hours_practice, PDO::PARAM_INT);
      }

      // bind the task id provided in the query string
      $query->bindParam(':courseid', $courseid, PDO::PARAM_STR);
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
        $response->addMessage("Course not updated - given values may be the same as the stored values");
        $response->send();
        exit;
      }
      // ADD AUTH TO QUERY
      // create db query to return the newly edited task - connect to master database
      $query = $writeDB->prepare('SELECT id, name, curr, period, active, hours_theory, hours_lab, hours_practice from course_list where id = :courseid and userid = :userid');
      $query->bindParam(':courseid', $courseid, PDO::PARAM_STR);
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
        $response->addMessage("No course found");
        $response->send();
        exit;
      }
      // create task array to store returned tasks
      $courseArray = array();

      // for each row returned
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object
        $course = new Course($row['id'], $row['name'], $row['curr'], $row['period'], $row['active'], $row['hours_theory'], $row['hours_lab'], $row['hours_practice']);

        // create task and store in array for return in json data
  	    $courseArray[] = $course->returnCourseAsArray();
      }
      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['courses'] = $courseArray;

      // set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(200);
      $response->setSuccess(true);
      $response->addMessage("Course updated");
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

elseif(empty($_GET)) {

  // if request is a GET e.g. get tasks
  if($_SERVER['REQUEST_METHOD'] === 'GET') {

    // attempt to query the database
    try {
      // ADD AUTH TO QUERY
      // create db query

      $query = $readDB->prepare('SELECT id, name, curr, period, active, hours_theory, hours_lab, hours_practice from course_list where userid = :userid');
      $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
  		$query->execute();

      // get row count
      $rowCount = $query->rowCount();

      // create task array to store returned tasks
      $courseArray = array();

      // for each row returned
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object for each row
        $course = new Course($row['id'], $row['name'], $row['curr'], $row['period'], $row['active'], $row['hours_theory'], $row['hours_lab'], $row['hours_practice']);

        // create task and store in array for return in json data
        $courseArray[] = $course->returnCourseAsArray();
      }

      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['courses'] = $courseArray;

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
    catch(CourseException $ex) {
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
      $response->addMessage("Failed to get tasks");
      $response->send();
      exit;
    }
  }
  // else if request is a POST e.g. create task
  elseif($_SERVER['REQUEST_METHOD'] === 'POST') {

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
      if(!isset($jsonData->id) || !isset($jsonData->name) || !isset($jsonData->curr) || !isset($jsonData->period) || !isset($jsonData->active) || !isset($jsonData->hours_theory) || !isset($jsonData->hours_lab) || !isset($jsonData->hours_practice)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (!isset($jsonData->title) ? $response->addMessage("ID field is mandatory and must be provided") : false);
        (!isset($jsonData->name) ? $response->addMessage("Name field is mandatory and must be provided") : false);
        (!isset($jsonData->curr) ? $response->addMessage("Programma spoudwn field is mandatory and must be provided") : false);
        (!isset($jsonData->period) ? $response->addMessage("Period field is mandatory and must be provided") : false);
        (!isset($jsonData->active) ? $response->addMessage("active field is mandatory and must be provided") : false);
        (!isset($jsonData->hours_theory) ? $response->addMessage("hours_theory field is mandatory and must be provided") : false);
        (!isset($jsonData->hours_lab) ? $response->addMessage("hours_lab field is mandatory and must be provided") : false);
        (!isset($jsonData->hours_practice) ? $response->addMessage("hours_practice field is mandatory and must be provided") : false);
        $response->send();
        exit;
      }

      // create new task with data, if non mandatory fields not provided then set to null
      $newCourse = new Course($jsonData->id, $jsonData->name, $jsonData->curr, (isset($jsonData->period) ? $jsonData->period : "-"), (isset($jsonData->active) ? $jsonData->active : "Y"), $jsonData->hours_theory, $jsonData->hours_lab, $jsonData->hours_practice);
      // get title, description, deadline, completed and store them in variables
      $id = $newCourse->getID();
      $name = $newCourse->getName();
      $curr = $newCourse->getCurr();
      $period = $newCourse->getPeriod();
      $active = $newCourse->getActive();
      $hours_theory = $newCourse->getHoursTheory();
      $hours_lab = $newCourse->getHoursLab();
      $hours_practice = $newCourse->getHoursPractice();

      // ADD AUTH TO QUERY
      // create db query
      $query = $writeDB->prepare('insert into course_list (id, name, curr, period, active, hours_theory, hours_lab, hours_practice, userid) values (:id, :name, :curr, :period, :active, :hours_theory, :hours_lab, :hours_practice, :userid)');
      $query->bindParam(':id', $id, PDO::PARAM_STR);
      $query->bindParam(':name', $name, PDO::PARAM_STR);
      $query->bindParam(':curr', $curr, PDO::PARAM_STR);
      $query->bindParam(':period', $period, PDO::PARAM_STR);
      $query->bindParam(':active', $active, PDO::PARAM_STR);
      $query->bindParam(':hours_theory', $hours_theory, PDO::PARAM_INT);
      $query->bindParam(':hours_lab', $hours_lab, PDO::PARAM_INT);
      $query->bindParam(':hours_practice', $hours_practice, PDO::PARAM_INT);
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
      $lastCourseID = $writeDB->lastInsertId();
      // ADD AUTH TO QUERY
      // create db query to get newly created task - get from master db not read slave as replication may be too slow for successful read
      $query = $writeDB->prepare('SELECT id, name, curr, period, active, hours_theory, hours_lab, hours_practice from course_list where id = :id and userid = :userid');
      $query->bindParam(':id', $id, PDO::PARAM_STR);
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
      $courseArray = array();

      // for each row returned - should be just one
      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
        // create new task object
        $course = new Course($row['id'], $row['name'], $row['curr'], $row['period'], $row['active'], $row['hours_theory'], $row['hours_lab'], $row['hours_practice']);

        // create task and store in array for return in json data
        $courseArray[] = $course->returnCourseAsArray();
      }
      // bundle tasks and rows returned into an array to return in the json data
      $returnData = array();
      $returnData['rows_returned'] = $rowCount;
      $returnData['courses'] = $courseArray;

      //set up response for successful return
      $response = new Response();
      $response->setHttpStatusCode(201);
      $response->setSuccess(true);
      $response->addMessage("Course created");
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
      $response->addMessage("Failed to insert course into database - check submitted data for errors");
      $response->send();
      exit;
    }
  }
  // if any other request method apart from GET or POST is used then return 405 method not allowed
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
