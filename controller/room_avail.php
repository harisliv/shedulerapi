  <?php

  //header('Content-Type: text/html; charset=utf-8');
  require_once('db.php');
  require_once('../model/room_avail.php');
  require_once('../model/room.php');
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


      // else if request is a POST e.g. create task
      if($_SERVER['REQUEST_METHOD'] === 'POST') {

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

          // check if post request contains title and available data in body as these are mandatory
          if(!isset($jsonData->id_room) || !isset($jsonData->id_ts) || !isset($jsonData->id_acadsem) || !isset($jsonData->available) || !isset($jsonData->learn_sem)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            (!isset($jsonData->id_room) ? $response->addMessage("id_room field is mandatory and must be provided") : false);
            (!isset($jsonData->id_ts) ? $response->addMessage("id_ts field is mandatory and must be provided") : false);
            (!isset($jsonData->id_acadsem) ? $response->addMessage("id_acadsem spoudwn field is mandatory and must be provided") : false);
            (!isset($jsonData->available) ? $response->addMessage("available field is mandatory and must be provided") : false);
            (!isset($jsonData->learn_sem) ? $response->addMessage("learn_sem field is mandatory and must be provided") : false);

            $response->send();
            exit;
          }

          // create new task with data, if non mandatory fields not provided then set to null
          $newRoom_avail = new Room_avail(null, $jsonData->id_room, $jsonData->id_ts, $jsonData->id_acadsem, $jsonData->available, $jsonData->learn_sem);
          // get title, description, deadline, available and store them in variables
          $id_room = $newRoom_avail->getIdRoom();
          $id_ts = $newRoom_avail->getIdTs();
          $id_acadsem = $newRoom_avail->getIdAcadsem();
          $available = $newRoom_avail->getAvailable();
          $learn_sem = $newRoom_avail->getLearnSem();


          $query1 = $writeDB->prepare('SELECT id_room, id_ts, id_acadsem, learn_sem from room_availability where id_room = :id_room and id_ts = :id_ts and id_acadsem = :id_acadsem and learn_sem = :learn_sem');
          $query1->bindParam(':id_room', $id_room, PDO::PARAM_INT);
          $query1->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
          $query1->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
          $query1->bindParam(':learn_sem', $learn_sem, PDO::PARAM_STR);
          $query1->execute();

          // get row count
          $rowCount1 = $query1->rowCount();

          if($rowCount1 !== 0) {
            // set up response for username already exists
            $response = new Response();
            $response->setHttpStatusCode(409);
            $response->setSuccess(true);
            $response->addMessage("Room is taken");
            $response->send();
            exit;
          }

          // ADD AUTH TO QUERY
          // create db query
          $query = $writeDB->prepare('insert into room_availability (id_room, id_ts, id_acadsem, available, learn_sem) values (:id_room, :id_ts, :id_acadsem, :available, :learn_sem)');
          $query->bindParam(':id_room', $id_room, PDO::PARAM_INT);
          $query->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
          $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
          $query->bindParam(':available', $available, PDO::PARAM_STR);
          $query->bindParam(':learn_sem', $learn_sem, PDO::PARAM_STR);

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
          $lastRoom_availID = $writeDB->lastInsertId();
          // ADD AUTH TO QUERY
          // create db query to get newly created task - get from master db not read slave as replication may be too slow for successful read
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available, learn_sem from room_availability where id = :id');
          $query->bindParam(':id', $lastRoom_availID, PDO::PARAM_INT);
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
          $room_availArray = array();

          // for each row returned - should be just one
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new task object
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available'], $row['learn_sem']);

            // create task and store in array for return in json data
            $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }
          // bundle tasks and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['rooms_avail'] = $room_availArray;

          //set up response for successful return
          $response = new Response();
          $response->setHttpStatusCode(201);
          $response->setSuccess(true);
          $response->addMessage("Room_avail created");
          $response->setData($returnData);
          $response->send();
          exit;
        }
        // if task fails to create due to data types, missing fields or invalid data then send error json
        catch(Room_availException $ex) {
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
          $response->addMessage("Failed to insert room_avail into database - check submitted data for errors");
          $response->send();
          exit;
        }
      }
      // if any other request method apart from GET or POST is used then return 405 method not allowed
      //else {
      //  $response = new Response();
      //  $response->setHttpStatusCode(405);
      //  $response->setSuccess(false);
      //  $response->addMessage("Request method not allowed");
      //  $response->send();
      //  exit;
      //}
    }

    elseif (array_key_exists("day",$_GET) && array_key_exists("start_time",$_GET) && array_key_exists("room_code",$_GET)) {
      // get task id from query string
      $day = $_GET['day'];
      $start_time = $_GET['start_time'];
      $room_code = $_GET['room_code'];



      //check to see if task id in query string is not empty and is number, if not return json error
      if( ($day !== 'de' && $day !== 'tr' && $day !== 'te' && $day !== 'pe' && $day !== 'pa') && $start_time < 8 || $start_time > 20 ) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        ( ($day !== 'de' && $day !== 'tr' && $day !== 'te' && $day !== 'pe' && $day !== 'pa') ? $response->addMessage("wrong day") : false);
        ( $start_time < 8 || $start_time > 20 ? $response->addMessage("Wrong start time") : false);
        $response->send();
        exit;
      }



      // if request is a GET, e.g. get task
      if($_SERVER['REQUEST_METHOD'] === 'GET') {
        // attempt to query the database
        try {
          // create db query
          // ADD AUTH TO QUERY
          $query = $readDB->prepare('SELECT id, start_time, day, id_acadsem from time_slots where start_time = :start_time and day = :day');
          $query->bindParam(':day', $day, PDO::PARAM_STR);
          $query->bindParam(':start_time', $start_time, PDO::PARAM_INT);
      		$query->execute();
          //echo "reeeeeee :" . $room_code . "<br>";
          $query1 = $readDB->prepare('SELECT id, lektiko_room, room_code from room where room_code = :room_code');
          $query1->bindParam(':room_code', $room_code, PDO::PARAM_STR);
      		$query1->execute();
          $room_availArray = array();
          $rowCount_new = 0;
          $availableyes = "Y";
          while($row1 = $query1->fetch(PDO::FETCH_ASSOC)) {
            // create new room object for each row
            //echo "<br>" . $row['lektiko_room'];
            $room = new Room($row1['id'], $row1['lektiko_room'], $row1['room_code']);
            //echo "while room id: " . $row1['id'] . "room_code: " . $row1['room_code'] . "<br>";
            $roomid = $row1['id'];
            // create room and store in array for return in json data
            //$roomArray[] = $room->returnRoomAsArray();
          }
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            //echo "room id after while: " . $roomid . "<br>";
            //echo "time_slot " . $row['id'] . "<br>";
            $query_new = $readDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available, learn_sem from room_availability where id_room = :id_room and id_ts = :id_ts and available = :available');
            $query_new->bindParam(':id_room', $roomid, PDO::PARAM_INT);
            $query_new->bindParam(':id_ts', $row['id'], PDO::PARAM_INT);
            $query_new->bindParam(':available', $availableyes, PDO::PARAM_STR);
            $query_new->execute();
            $rowCount_new += $query_new->rowCount();
            //echo "rowcount " . $rowCount_new . "<br>";

            if($rowCount_new === 0) {
              // set up response for unsuccessful return
              $response = new Response();
              $response->setHttpStatusCode(404);
              $response->setSuccess(false);
              $response->addMessage("Task not found");
              $response->send();
              exit;
            }

            // for each row returned
            while($row_new = $query_new->fetch(PDO::FETCH_ASSOC)) {
              // create new task object for each row
              //echo "room avail " . $row_new['available'] . "<br>";

              $room_avail = new Room_avail($row_new['id'], $row_new['id_room'], $row_new['id_ts'], $row_new['id_acadsem'], $row_new['available'], $row_new['learn_sem']);
              // create task and store in array for return in json data

        	    $room_availArray[] = $room_avail->returnRoom_availAsArray();

            }

          }

          // bundle tasks and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount_new;
          $returnData['rooms_avail'] = $room_availArray;

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
        catch(Room_availException $ex) {
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

    elseif(array_key_exists("id_acadsem",$_GET) && array_key_exists("available",$_GET) && array_key_exists("learn_sem",$_GET)) {

      // get available from query string
      $id_acadsem = $_GET['id_acadsem'];
      $available = $_GET['available'];
      $learn_sem = $_GET['learn_sem'];

      // check to see if available in query string is either Y or N
      /*
      if($available !== "Y" && $available !== "N") {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("available filter must be Y or N");
        $response->send();
        exit;
      }
      */

      if($_SERVER['REQUEST_METHOD'] === 'GET') {
        // attempt to query the database
        try {
          // ADD AUTH TO QUERY
          // create db query
          $query = $readDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available, learn_sem from room_availability where id_acadsem = :id_acadsem and available = :available and learn_sem = :learn_sem');
          $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
          $query->bindParam(':available', $available, PDO::PARAM_STR);
          $query->bindParam(':learn_sem', $learn_sem, PDO::PARAM_STR);
      		$query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // create task array to store returned tasks
          $room_availArray = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new task object for each row
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available'], $row['learn_sem']);

            // create task and store in array for return in json data
      	    $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }

          // bundle task and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['rooms_avail'] = $room_availArray;

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
        catch(Room_availException $ex) {
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

    elseif (array_key_exists("id",$_GET)) {
      // get task id from query string
      $room_availid = $_GET['id'];

      //check to see if task id in query string is not empty and is number, if not return json error
      if($room_availid == '' ) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("room_avail ID cannot be blank or must be numeric");
        $response->send();
        exit;
      }

      // if request is a GET, e.g. get task
      if($_SERVER['REQUEST_METHOD'] === 'GET') {
        // attempt to query the database
        try {
          // create db query
          // ADD AUTH TO QUERY
          $availableyes = "Y";
          $query = $readDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available, learn_sem from room_availability where id = :id and available = :available');
          $query->bindParam(':id', $room_availid, PDO::PARAM_INT);
          $query->bindParam(':available', $availableyes, PDO::PARAM_STR);
          $query->execute();
          // get row count
          $rowCount = $query->rowCount();

          // create task array to store returned task
          $room_availArray = array();

          if($rowCount === 0) {
            // set up response for unsuccessful return
            $response = new Response();
            $response->setHttpStatusCode(404);
            $response->setSuccess(false);
            $response->addMessage("room_avail not found");
            $response->send();
            exit;
          }

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new task object for each row
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available'], $row['learn_sem']);

            // create task and store in array for return in json data
            $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }

          // bundle tasks and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['rooms_avail'] = $room_availArray;

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
        catch(Room_availException $ex) {
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


          // ADD AUTH TO QUERY
          // create db query to get task from database to update - use master db
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id = :id');
          $query->bindParam(':id', $room_availid, PDO::PARAM_INT);
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
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create task and store in array for return in json data
            $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }

          // ADD AUTH TO QUERY
          // create the query string including any query fields
          // prepare the query
          $query = $writeDB->prepare('UPDATE room_availability set available = :available where id = :id');

          $availableno = "N";
          // bind the task id provided in the query string
          $query->bindParam(':id', $room_availid, PDO::PARAM_INT);
          $query->bindParam(':available', $availableno, PDO::PARAM_STR);
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
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id = :id');
          $query->bindParam(':id', $room_availid, PDO::PARAM_INT);
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
          $room_avail = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new task object
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create task and store in array for return in json data
            $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }
          // bundle tasks and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['rooms_avail'] = $room_availArray;
          print_r($room_availArray);

          // set up response for successful return
          $response = new Response();
          $response->setHttpStatusCode(200);
          $response->setSuccess(true);
          $response->addMessage("Course updated");
          $response->setData($returnData);
          $response->send();
          exit;
        }
        catch(Room_availException $ex) {
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

    elseif (array_key_exists("id_ts",$_GET)) {
      // get task id from query string
      $id_ts = $_GET['id_ts'];

      //check to see if task id in query string is not empty and is number, if not return json error
      if($id_ts == '' ) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("room_avail ID cannot be blank or must be numeric");
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
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id_ts = :id_ts');
          $query->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
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
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create task and store in array for return in json data
            $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }

          // ADD AUTH TO QUERY
          // create the query string including any query fields
          // prepare the query
          $query = $writeDB->prepare('UPDATE room_availability set available = :available where id_ts = :id_ts');

          $availableno = "N";
          // bind the task id provided in the query string
          $query->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
          $query->bindParam(':available', $availableno, PDO::PARAM_STR);
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
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id_ts = :id_ts');
          $query->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
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
          $room_avail = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new task object
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create task and store in array for return in json data
            $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }
          // bundle tasks and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['rooms_avail'] = $room_availArray;
          print_r($room_availArray);

          // set up response for successful return
          $response = new Response();
          $response->setHttpStatusCode(200);
          $response->setSuccess(true);
          $response->addMessage("Room_avail updated");
          $response->setData($returnData);
          $response->send();
          exit;
        }
        catch(Room_availException $ex) {
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
