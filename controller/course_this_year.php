  <?php

  //header('Content-Type: text/html; charset=utf-8');
  require_once('db.php');
  require_once('../model/course_this_year.php');
  require_once('../model/response.php');
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  error_reporting(0);

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

    if(empty($_GET)) {

      // if request is a GET e.g. get AcadSems
      if($_SERVER['REQUEST_METHOD'] === 'GET') {

        // attempt to query the database
        try {
          // ADD AUTH TO QUERY
          // create db query
          $query = $readDB->prepare('SELECT id, id_course, name, learn_sem, id_responsible_prof, id_acadsem, count_div_theory, count_div_lab, count_div_practice from course_this_year');
          $query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // create AcadSem array to store returned AcadSems
          $coursethisyearArray = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new AcadSem object for each row
            //echo "<br>" . $row['lektiko_AcadSem'];
            $coursethisyear = new Course_this_year($row['id'], $row['id_course'], $row['name'], $row['learn_sem'], $row['id_responsible_prof'], $row['id_acadsem'], $row['count_div_theory'], $row['count_div_lab'], $row['count_div_practice']);

            // create AcadSem and store in array for return in json data
            $coursethisyearArray[] = $coursethisyear->returnCourse_this_yearAsArray();
          }

          // bundle AcadSems and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['coursethisyears'] = $coursethisyearArray;

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
        catch(Course_this_yearException $ex) {
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
          $response->addMessage("Failed to get AcadSems");
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
          if(!isset($jsonData->id_course) || !isset($jsonData->name) || !isset($jsonData->learn_sem) || !isset($jsonData->id_responsible_prof) || !isset($jsonData->id_acadsem) || !isset($jsonData->count_div_theory) || !isset($jsonData->count_div_lab) || !isset($jsonData->count_div_practice)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            (!isset($jsonData->id_course) ? $response->addMessage("id_course field is mandatory and must be provided") : false);
            (!isset($jsonData->name) ? $response->addMessage("name field is mandatory and must be provided") : false);
            (!isset($jsonData->learn_sem) ? $response->addMessage("learn_sem field is mandatory and must be provided") : false);
            (!isset($jsonData->id_responsible_prof) ? $response->addMessage("id_responsible_prof field is mandatory and must be provided") : false);
            (!isset($jsonData->id_acadsem) ? $response->addMessage("id_acadsem field is mandatory and must be provided") : false);
            (!isset($jsonData->count_div_theory) ? $response->addMessage("count_div_theory field is mandatory and must be provided") : false);
            (!isset($jsonData->count_div_lab) ? $response->addMessage("count_div_lab field is mandatory and must be provided") : false);
            (!isset($jsonData->count_div_practice	) ? $response->addMessage("count_div_practice	 field is mandatory and must be provided") : false);
            $response->send();
            exit;
          }

          // create new task with data, if non mandatory fields not provided then set to null

          $coursethisyear = new Course_this_year(null, $jsonData->id_course, $jsonData->name, $jsonData->learn_sem, $jsonData->id_responsible_prof, $jsonData->id_acadsem, (isset($jsonData->count_div_theory) ? $jsonData->count_div_theory : 0), (isset($jsonData->count_div_lab	) ? $jsonData->count_div_lab	 : 0), (isset($jsonData->count_div_practice) ? $jsonData->count_div_practice : 0));
          // get title, description, deadline, completed and store them in variables
          $id_course = $coursethisyear->getIdCourse();
          $name = $coursethisyear->getName();
          $learn_sem = $coursethisyear->getLearnSem();
          $id_responsible_prof = $coursethisyear->getIdResponsibleProf();
          $id_acadsem = $coursethisyear->getIdAcadsem();
          $count_div_theory = $coursethisyear->getCountDivTheory();
          $count_div_lab = $coursethisyear->getCountDivLab();
          $count_div_practice = $coursethisyear->getCountDivPractice();

          $query1 = $writeDB->prepare('SELECT id_course, id_acadsem from course_this_year where id_course = :id_course and id_acadsem = :id_acadsem');
          $query1->bindParam(':id_course', $id_course, PDO::PARAM_STR);
          $query1->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
          $query1->execute();

          // get row count
          $rowCount1 = $query1->rowCount();

          if($rowCount1 !== 0) {
            // set up response for username already exists
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(true);
            $response->addMessage("Course this year already created");
            $response->send();
            exit;
          }

          // ADD AUTH TO QUERY
          // create db query
          $query = $writeDB->prepare('insert into course_this_year (id_course, name, learn_sem, id_responsible_prof, id_acadsem, count_div_theory, count_div_lab, count_div_practice) values (:id_course, :name, :learn_sem, :id_responsible_prof, :id_acadsem, :count_div_theory, :count_div_lab, :count_div_practice)');
          $query->bindParam(':id_course', $id_course, PDO::PARAM_STR);
          $query->bindParam(':name', $name, PDO::PARAM_STR);
          $query->bindParam(':learn_sem', $learn_sem, PDO::PARAM_STR);
          $query->bindParam(':id_responsible_prof', $id_responsible_prof, PDO::PARAM_INT);
          $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
          $query->bindParam(':count_div_theory', $count_div_theory, PDO::PARAM_INT);
          $query->bindParam(':count_div_lab', $count_div_lab, PDO::PARAM_INT);
          $query->bindParam(':count_div_practice', $count_div_practice, PDO::PARAM_INT);
          $query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // check if row was actually inserted, PDO exception should have caught it if not.
          if($rowCount === 0) {
            // set up response for unsuccessful return
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to create course this year");
            $response->send();
            exit;
          }

          // get last task id so we can return the Task in the json
          $lastCoursethisyearID = $writeDB->lastInsertId();
          // ADD AUTH TO QUERY
          // create db query to get newly created task - get from master db not read slave as replication may be too slow for successful read
          $query = $writeDB->prepare('SELECT id, id_course, name, learn_sem, id_responsible_prof, id_acadsem, count_div_theory, count_div_lab, count_div_practice from course_this_year where id = :id');
          $query->bindParam(':id', $lastCoursethisyearID, PDO::PARAM_STR);
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
          $coursethisyearArray = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new AcadSem object for each row
            //echo "<br>" . $row['lektiko_AcadSem'];
            $coursethisyear = new Course_this_year($row['id'], $row['id_course'], $row['name'], $row['learn_sem'], $row['id_responsible_prof'], $row['id_acadsem'], $row['count_div_theory'], $row['count_div_lab'], $row['count_div_practice']);

            // create AcadSem and store in array for return in json data
            $coursethisyearArray[] = $coursethisyear->returnCourse_this_yearAsArray();
          }

          // bundle AcadSems and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['coursethisyears'] = $coursethisyearArray;

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
        catch(Course_this_yearException $ex) {
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


    }

    elseif(array_key_exists("id_course",$_GET) && array_key_exists("id_acadsem",$_GET)) {

      // get available from query string
      $id_course = $_GET['id_course'];
      $id_acadsem = $_GET['id_acadsem'];
      // get task id from query string

      //check to see if task id in query string is not empty and is number, if not return json error
      if($id_course == '' ) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("course ID cannot be blank or must be numeric");
        $response->send();
        exit;
      }

      if($_SERVER['REQUEST_METHOD'] === 'PATCH') {
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


          // ADD AUTH TO QUERY
          // create db query to get task from database to update - use master db
          $query = $writeDB->prepare('SELECT id, id_course, name, learn_sem, id_responsible_prof, id_acadsem, count_div_theory, count_div_lab, count_div_practice from course_this_year where id_course = :id_course and id_acadsem = :id_acadsem');
          $query->bindParam(':id_course', $id_course, PDO::PARAM_STR);
          $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
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
            $coursethisyear = new Course_this_year($row['id'], $row['id_course'], $row['name'], $row['learn_sem'], $row['id_responsible_prof'], $row['id_acadsem'], $row['count_div_theory'], $row['count_div_lab'], $row['count_div_practice']);

            // create task and store in array for return in json data
            $coursethisyearArray[] = $coursethisyear->returnCourse_this_yearAsArray();
          }

          // ADD AUTH TO QUERY
          // create the query string including any query fields
          // prepare the query
          $query = $writeDB->prepare('UPDATE course_this_year set count_div_lab = :count_div_lab where id_course = :id_course and id_acadsem = :id_acadsem');

          $now = $row['count_div_lab'] - 1;
          // bind the task id provided in the query string
          $query->bindParam(':count_div_lab', $now, PDO::PARAM_INT);
          $query->bindParam(':id_course', $id_course, PDO::PARAM_STR);
          $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
          // bind the user id returned
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
          $query = $writeDB->prepare('SELECT id, id_course, name, learn_sem, id_responsible_prof, id_acadsem, count_div_theory, count_div_lab, count_div_practice from course_this_year where id_course = :id_course and id_acadsem = :id_acadsem');
          $query->bindParam(':id_course', $id_course, PDO::PARAM_STR);
          $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
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
          $coursethisyearArray = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new task object for each row
            $coursethisyear = new Course_this_year($row['id'], $row['id_course'], $row['name'], $row['learn_sem'], $row['id_responsible_prof'], $row['id_acadsem'], $row['count_div_theory'], $row['count_div_lab'], $row['count_div_practice']);

            // create AcadSem and store in array for return in json data
            $coursethisyearArray[] = $coursethisyear->returnCourse_this_yearAsArray();
          }
          // bundle tasks and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['coursethisyears'] = $coursethisyearArray;
          //print_r($room_availArray);

          // set up response for successful return
          $response = new Response();
          $response->setHttpStatusCode(200);
          $response->setSuccess(true);
          $response->addMessage("Room_avail updated");
          $response->setData($returnData);
          $response->send();
          exit;
        }
        catch(Course_this_yearException $ex) {
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
    }

    elseif (array_key_exists("id_course",$_GET)) {
      // get task id from query string
      $id_course = $_GET['id_course'];

      //check to see if task id in query string is not empty and is number, if not return json error
      if($id_course == '' ) {
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
          $query = $readDB->prepare('SELECT id, id_course, name, learn_sem, id_responsible_prof, id_acadsem, count_div_theory, count_div_lab, count_div_practice from course_this_year where id_course = :id_course');
          $query->bindParam(':id_course', $id_course, PDO::PARAM_STR);
          $query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // create task array to store returned task
          $coursethisyearArray = array();

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
            $coursethisyear = new Course_this_year($row['id'], $row['id_course'], $row['name'], $row['learn_sem'], $row['id_responsible_prof'], $row['id_acadsem'], $row['count_div_theory'], $row['count_div_lab'], $row['count_div_practice']);

            // create AcadSem and store in array for return in json data
            $coursethisyearArray[] = $coursethisyear->returnCourse_this_yearAsArray();
          }

          // bundle tasks and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['coursethisyears'] = $coursethisyearArray;

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
        catch(Course_this_yearException $ex) {
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
    }

    elseif (array_key_exists("learn_sem",$_GET) && array_key_exists("acad_sem",$_GET)) {
      // get task id from query string
      $learn_sem = $_GET['learn_sem'];
      $acad_sem = $_GET['acad_sem'];



      //check to see if task id in query string is not empty and is number, if not return json error
      /*
      if( ($learn_sem !== 'A' && $learn_sem !== 'B' && $learn_sem !== 'Γ' && $learn_sem !== 'Δ' && $learn_sem !== 'Ε') && $acad_sem < 2016) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        ( ($learn_sem !== 'A' && $learn_sem !== 'B' && $learn_sem !== 'Γ' && $learn_sem !== 'Δ' && $learn_sem !== 'Ε') ? $response->addMessage("wrong day") : false);
        ( $acad_sem < 2017? $response->addMessage("Wrong start time") : false);
        $response->send();
        exit;
      } */



      // if request is a GET, e.g. get task
      if($_SERVER['REQUEST_METHOD'] === 'GET') {
        // attempt to query the database
        try {
          // create db query
          // ADD AUTH TO QUERY
          $query = $readDB->prepare('SELECT id, id_course, name, learn_sem, id_responsible_prof, id_acadsem, count_div_theory, count_div_lab, count_div_practice from course_this_year where learn_sem = :learn_sem and id_acadsem = :id_acadsem');
          $query->bindParam(':learn_sem', $learn_sem, PDO::PARAM_STR);
          $query->bindParam(':id_acadsem', $acad_sem, PDO::PARAM_INT);
      		$query->execute();

          $rowCount = $query->rowCount();

          // create task array to store returned task
          $coursethisyearArray = array();

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
            $coursethisyear = new Course_this_year($row['id'], $row['id_course'], $row['name'], $row['learn_sem'], $row['id_responsible_prof'], $row['id_acadsem'], $row['count_div_theory'], $row['count_div_lab'], $row['count_div_practice']);

            // create task and store in array for return in json data
            $coursethisyearArray[] = $coursethisyear->returnCourse_this_yearAsArray();
          }

          // bundle tasks and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['coursethisyears'] = $coursethisyearArray;

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
        catch(Course_this_yearException $ex) {
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
}

    else {
      $response = new Response();
      $response->setHttpStatusCode(404);
      $response->setSuccess(false);
      $response->addMessage("Endpoint not found");
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
