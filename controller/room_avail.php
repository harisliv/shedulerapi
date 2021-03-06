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
    $query = $writeDB->prepare('select userid, useractive, loginattempts from tblsessions, tblusers where tblsessions.userid = tblusers.id and accesstoken = :accesstoken');
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

    if(empty($_GET)) {

      // if request is a GET e.g. get room_avails
      if($_SERVER['REQUEST_METHOD'] === 'GET') {

        // attempt to query the database
        try {
          // ADD AUTH TO QUERY
          // create db query
          $query = $readDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability');
      		$query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // create room_avail array to store returned room_avails
          $room_availArray = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new room_avail object for each row
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create room_avail and store in array for return in json data
      	    $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }

          // bundle room_avail and rows returned into an array to return in the json data
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
          $response->addMessage("Failed to get room_avails");
          $response->send();
          exit;
        }
      }


      // else if request is a POST e.g. create room_avail
      elseif($_SERVER['REQUEST_METHOD'] === 'POST') {

        // create room_avail
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

          // create new room_avail with data, if non mandatory fields not provided then set to null
          $newRoom_avail = new Room_avail(null, $jsonData->id_room, $jsonData->id_ts, $jsonData->id_acadsem, $jsonData->available);
          // get title, description, deadline, available and store them in variables
          $id_room = $newRoom_avail->getIdRoom();
          $id_ts = $newRoom_avail->getIdTs();
          $id_acadsem = $newRoom_avail->getIdAcadsem();
          $available = $newRoom_avail->getAvailable();


          $query1 = $writeDB->prepare('SELECT id_room, id_ts, id_acadsem from room_availability where id_room = :id_room and id_ts = :id_ts and id_acadsem = :id_acadsem');
          $query1->bindParam(':id_room', $id_room, PDO::PARAM_INT);
          $query1->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
          $query1->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
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
            $response->addMessage("Failed to create room_avail");
            $response->send();
            exit;
          }

          // get last room_avail id so we can return the room_avail in the json
          $lastRoom_availID = $writeDB->lastInsertId();
          // ADD AUTH TO QUERY
          // create db query to get newly created room_avail - get from master db not read slave as replication may be too slow for successful read
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id = :id');
          $query->bindParam(':id', $lastRoom_availID, PDO::PARAM_INT);
          $query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // make sure that the new room_avail was returned
          if($rowCount === 0) {
            // set up response for unsuccessful return
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("Failed to retrieve room_avail after creation");
            $response->send();
            exit;
          }

          // create empty array to store room_avails
          $room_availArray = array();

          // for each row returned - should be just one
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new room_avail object
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create room_avail and store in array for return in json data
            $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }
          // bundle room_avails and rows returned into an array to return in the json data
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
        // if room_avail fails to create due to data types, missing fields or invalid data then send error json
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
      else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
      }
    }

    elseif(array_key_exists("id_room",$_GET) && array_key_exists("id_ts",$_GET) && array_key_exists("id_acadsem",$_GET)) {

      // get available from query string
      $id_room = $_GET['id_room'];
      $id_ts = $_GET['id_ts'];
      $id_acadsem = $_GET['id_acadsem'];

      // check to see if available in query string is either Y or N

      if($id_room == '' || $id_room < 0 || !is_numeric($id_room) || $id_ts == '' || $id_ts < 0 || !is_numeric($id_ts) || $id_acadsem == '' || $id_acadsem < 0 || !is_numeric($id_acadsem)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        ( $id_room == '' || $id_room < 0 || !is_numeric($id_room) ? $response->addMessage("Wrong id_room") : false);
        ( $id_ts == '' || $id_ts < 0 || !is_numeric($id_ts) ? $response->addMessage("Wrong id_ts") : false);
        ( $id_acadsem == '' || $id_acadsem < 0 || !is_numeric($id_acadsem) ? $response->addMessage("Wrong id_acadsem") : false);
        $response->send();
        exit;
      }


      if($_SERVER['REQUEST_METHOD'] === 'GET') {
        // attempt to query the database
        try {
          // ADD AUTH TO QUERY
          // create db query
          $query = $readDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id_room =:id_room and id_ts =:id_ts and id_acadsem = :id_acadsem');
          $query->bindParam(':id_room', $id_room, PDO::PARAM_INT);
          $query->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
          $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
      		$query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // create room_avail array to store returned room_avails
          $room_availArray = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new room_avail object for each row
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create room_avail and store in array for return in json data
      	    $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }

          // bundle room_avail and rows returned into an array to return in the json data
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
          $response->addMessage("Failed to get room_avail");
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

        elseif(array_key_exists("id_acadsem",$_GET)) {

          // get available from query string
          $id_acadsem = $_GET['id_acadsem'];

          // check to see if available in query string is either Y or N

          if($id_acadsem == '' || $id_acadsem < 0 || !is_numeric($id_acadsem)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("id_acadsem error");
            $response->send();
            exit;
          }


          if($_SERVER['REQUEST_METHOD'] === 'GET') {
            // attempt to query the database
            try {
              // ADD AUTH TO QUERY
              // create db query
              $query = $readDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id_acadsem = :id_acadsem');
              $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
          		$query->execute();

              // get row count
              $rowCount = $query->rowCount();

              // create room_avail array to store returned room_avails
              $room_availArray = array();

              // for each row returned
              while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new room_avail object for each row
                $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

                // create room_avail and store in array for return in json data
          	    $room_availArray[] = $room_avail->returnRoom_availAsArray();
              }

              // bundle room_avail and rows returned into an array to return in the json data
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
              $response->addMessage("Failed to get room_avail");
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
      // get room_avail id from query string
      $room_availid = $_GET['id'];

      //check to see if room_avail id in query string is not empty and is number, if not return json error
      if($room_availid == '' ) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("room_avail ID cannot be blank or must be numeric");
        $response->send();
        exit;
      }

      // if request is a GET, e.g. get room_avail
      if($_SERVER['REQUEST_METHOD'] === 'GET') {
        // attempt to query the database
        try {
          // create db query
          // ADD AUTH TO QUERY
          $query = $readDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id = :id');
          $query->bindParam(':id', $room_availid, PDO::PARAM_INT);
          $query->execute();
          // get row count
          $rowCount = $query->rowCount();

          // create room_avail array to store returned room_avail
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
            // create new room_avail object for each row
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create room_avail and store in array for return in json data
            $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }

          // bundle room_avails and rows returned into an array to return in the json data
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
          $response->addMessage("Failed to get room_avail");
          $response->send();
          exit;
        }
      }


      // handle updating room_avail
      elseif($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        // update room_avail
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

          $available_updated = false;
          $queryFields = "";

          if(isset($jsonData->available)) {
                  // set title field updated to true
                  $available_updated = true;
                  // add title field to query field string
                  $queryFields .= "available = :available, ";
                }

                $queryFields = rtrim($queryFields, ", ");

                if($available_updated === false) {
                        $response = new Response();
                        $response->setHttpStatusCode(400);
                        $response->setSuccess(false);
                        $response->addMessage("No room availability fields provided");
                        $response->send();
                        exit;
                      }


          // ADD AUTH TO QUERY
          // create db query to get room_avail from database to update - use master db
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id = :id');
          $query->bindParam(':id', $room_availid, PDO::PARAM_INT);
          $query->execute();
          // get row count
          $rowCount = $query->rowCount();

          // make sure that the room_avail exists for a given room_avail id
          if($rowCount === 0) {
            // set up response for unsuccessful return
            $response = new Response();
            $response->setHttpStatusCode(404);
            $response->setSuccess(false);
            $response->addMessage("No room_avail found to update");
            $response->send();
            exit;
          }

          // for each row returned - should be just one
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new room_avail object
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

          }

          // ADD AUTH TO QUERY
          // create the query string including any query fields
          // prepare the query

          $queryString = "update room_availability set ".$queryFields." where id = :id";
      // prepare the query
          $query = $writeDB->prepare($queryString);
          $query->bindParam(':id', $room_availid, PDO::PARAM_INT);

          if($available_updated === true) {
        // set room_avail object title to given value (checks for valid input)
        $room_avail->setAvailable($jsonData->available);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_available = $room_avail->getAvailable();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':available', $up_available, PDO::PARAM_STR);
      }

      $query->execute();


          // get affected row count
          $rowCount = $query->rowCount();

          // check if row was actually updated, could be that the given values are the same as the stored values
          if($rowCount === 0) {
            // set up response for unsuccessful return
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("room_avail not updated - given values may be the same as the stored values");
            $response->send();
            exit;
          }
          // ADD AUTH TO QUERY
          // create db query to return the newly edited room_avail - connect to master database
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id = :id');
          $query->bindParam(':id', $room_availid, PDO::PARAM_INT);
          $query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // check if room_avail was found
          if($rowCount === 0) {
            // set up response for unsuccessful return
            $response = new Response();
            $response->setHttpStatusCode(404);
            $response->setSuccess(false);
            $response->addMessage("No room avail found");
            $response->send();
            exit;
          }
          // create room_avail array to store returned room_avails
          $room_avail = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new room_avail object
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create room_avail and store in array for return in json data
            $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }
          // bundle room_avails and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['rooms_avail'] = $room_availArray;

          // set up response for successful return
          $response = new Response();
          $response->setHttpStatusCode(200);
          $response->setSuccess(true);
          $response->addMessage("room_avail updated");
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
          $response->addMessage("Failed to update room_avail - check your data for errors");
          $response->send();
          exit;
        }
      }
    }

    elseif (array_key_exists("id_ts",$_GET)) {
      // get room_avail id from query string
      $id_ts = $_GET['id_ts'];

      //check to see if room_avail id in query string is not empty and is number, if not return json error
      if($id_ts == '' ) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("room_avail ID cannot be blank or must be numeric");
        $response->send();
        exit;
      }


      // handle updating room_avail
      elseif($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        // update room_avail
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

          $available_updated = false;
          $queryFields = "";

          if(isset($jsonData->available)) {
                  // set title field updated to true
                  $available_updated = true;
                  // add title field to query field string
                  $queryFields .= "available = :available, ";
                }

                $queryFields = rtrim($queryFields, ", ");

                if($available_updated === false) {
                        $response = new Response();
                        $response->setHttpStatusCode(400);
                        $response->setSuccess(false);
                        $response->addMessage("No room availability fields provided");
                        $response->send();
                        exit;
                      }


          // ADD AUTH TO QUERY
          // create db query to get room_avail from database to update - use master db
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id_ts = :id_ts');
          $query->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
          $query->execute();
          // get row count
          $rowCount = $query->rowCount();

          // make sure that the room_avail exists for a given room_avail id
          if($rowCount === 0) {
            // set up response for unsuccessful return
            $response = new Response();
            $response->setHttpStatusCode(404);
            $response->setSuccess(false);
            $response->addMessage("No room_avail found to update");
            $response->send();
            exit;
          }

          // for each row returned - should be just one
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new room_avail object
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);
          }

          // ADD AUTH TO QUERY
          // create the query string including any query fields
          // prepare the query

          $queryString = "update room_availability set ".$queryFields." where id_ts = :id_ts";
      // prepare the query
          $query = $writeDB->prepare($queryString);
          $query->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);

          if($available_updated === true) {
        // set room_avail object title to given value (checks for valid input)
        $room_avail->setAvailable($jsonData->available);
        // get the value back as the object could be handling the return of the value differently to
        // what was provided
        $up_available = $room_avail->getAvailable();
        // bind the parameter of the new value from the object to the query (prevents SQL injection)
        $query->bindParam(':available', $up_available, PDO::PARAM_STR);
      }

      $query->execute();


          // get affected row count
          $rowCount = $query->rowCount();

          // check if row was actually updated, could be that the given values are the same as the stored values
          if($rowCount === 0) {
            // set up response for unsuccessful return
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Room_avail not updated - given values may be the same as the stored values");
            $response->send();
            exit;
          }
          // ADD AUTH TO QUERY
          // create db query to return the newly edited room_avail - connect to master database
          $query = $writeDB->prepare('SELECT id, id_room, id_ts, id_acadsem, available from room_availability where id_ts = :id_ts');
          $query->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
          $query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // check if room_avail was found
          if($rowCount === 0) {
            // set up response for unsuccessful return
            $response = new Response();
            $response->setHttpStatusCode(404);
            $response->setSuccess(false);
            $response->addMessage("No room avail found");
            $response->send();
            exit;
          }
          // create room_avail array to store returned room_avails
          $room_avail = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new room_avail object
            $room_avail = new Room_avail($row['id'], $row['id_room'], $row['id_ts'], $row['id_acadsem'], $row['available']);

            // create room_avail and store in array for return in json data
            $room_availArray[] = $room_avail->returnRoom_availAsArray();
          }
          // bundle room_avails and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['rooms_avail'] = $room_availArray;
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
          $response->addMessage("Failed to update room_avail - check your data for errors");
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
