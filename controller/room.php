  <?php

  //header('Content-Type: text/html; charset=utf-8');
  require_once('db.php');
  require_once('../model/room.php');
  require_once('../model/response.php');
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  //error_reporting(0);

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

          // check if post request contains title and completed data in body as these are mandatory
          if(!isset($jsonData->id_room) || !isset($jsonData->id_ts) || !isset($jsonData->id_acadsem) || !isset($jsonData->available)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            (!isset($jsonData->id_room) ? $response->addMessage("id_room field is mandatory and must be provided") : false);
            (!isset($jsonData->id_ts) ? $response->addMessage("id_ts field is mandatory and must be provided") : false);
            (!isset($jsonData->id_acadsem) ? $response->addMessage("id_acadsem spoudwn field is mandatory and must be provided") : false);
            (!isset($jsonData->available) ? $response->addMessage("available field is mandatory and must be provided") : false);
            $response->send();
            exit;
          }

          // create new task with data, if non mandatory fields not provided then set to null
          $newRoom = new Room(null, $jsonData->id_room, $jsonData->id_ts, $jsonData->id_acadsem, $jsonData->available);
          // get title, description, deadline, completed and store them in variables
          $id_room = $newRoom->getIdRoom();
          $id_ts = $newRoom->getIdTs();
          $id_acadsem = $newRoom->getIdAcadsem();
          $available = $newRoom->getAvailable();

          // ADD AUTH TO QUERY
          // create db query
          $query = $writeDB->prepare('insert into room_availability (id_room, id_ts, id_acadsem, available) values (:id_room, :id_ts, :id_acadsem, :available)');
          $query->bindParam(':id_room', $id_room, PDO::PARAM_INT);
          $query->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
          $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
          $query->bindParam(':available', $available, PDO::PARAM_STR);
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
          $lastRoomID = $writeDB->lastInsertId();
          // ADD AUTH TO QUERY
          // create db query to get newly created task - get from master db not read slave as replication may be too slow for successful read
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id = :id');
          $query->bindParam(':id', $lastRoomID, PDO::PARAM_INT);
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
          $roomArray = array();

          // for each row returned - should be just one
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new task object
            $room = new Room($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create task and store in array for return in json data
            $roomArray[] = $room->returnRoomAsArray();
          }
          // bundle tasks and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['rooms'] = $roomArray;

          //set up response for successful return
          $response = new Response();
          $response->setHttpStatusCode(201);
          $response->setSuccess(true);
          $response->addMessage("ROOM created");
          $response->setData($returnData);
          $response->send();
          exit;
        }
        // if task fails to create due to data types, missing fields or invalid data then send error json
        catch(RoomException $ex) {
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
          $response->addMessage("Failed to insert room into database - check submitted data for errors");
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

  }

    catch(PDOException $ex) {
      $response = new Response();
      $response->setHttpStatusCode(500);
      $response->setSuccess(false);
      $response->addMessage("There was an issue authenticating - please try again");
      $response->send();
      exit;
    }
