  <?php

  //header('Content-Type: text/html; charset=utf-8');
  require_once('db.php');
  require_once('../model/scheduler.php');
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

      // if request is a GET e.g. get Schedulers
      if($_SERVER['REQUEST_METHOD'] === 'GET') {

        // attempt to query the database
        try {
          // ADD AUTH TO QUERY
          // create db query
          $query = $readDB->prepare('SELECT id, id_course, id_acadsem, type_division, lektiko_division, id_prof, id_room, id_ts, division_str from scheduler');
          $query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // create Scheduler array to store returned Schedulers
          $schedulerArray = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new scheduler object for each row
            //echo "<br>" . $row['lektiko_scheduler'];
            $scheduler = new Scheduler($row['id'], $row['id_course'], $row['id_acadsem'], $row['type_division'], $row['lektiko_division'], $row['id_prof'], $row['id_room'], $row['id_ts'], $row['division_str']);

            // create scheduler and store in array for return in json data
            $schedulerArray[] = $scheduler->returnSchedulerAsArray();
          }

          // bundle schedulers and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['schedulers'] = $schedulerArray;

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
        catch(SchedulerException $ex) {
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
          $response->addMessage("Failed to get Schedulers");
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
          if( !isset($jsonData->id_course) || !isset($jsonData->id_acadsem) || !isset($jsonData->type_division) || !isset($jsonData->lektiko_division) || !isset($jsonData->id_prof) || !isset($jsonData->id_room) || !isset($jsonData->id_ts) || !isset($jsonData->division_str) || !isset($jsonData->learn_sem)) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            //(!isset($jsonData->id) ? $response->addMessage("id field is mandatory and must be provided") : false);
            (!isset($jsonData->id_course) ? $response->addMessage("id_course field is mandatory and must be provided") : false);
            (!isset($jsonData->id_acadsem) ? $response->addMessage("id_acadsem field is mandatory and must be provided") : false);
            (!isset($jsonData->type_division) ? $response->addMessage("type_division field is mandatory and must be provided") : false);
            (!isset($jsonData->lektiko_division) ? $response->addMessage("lektiko_division field is mandatory and must be provided") : false);
            (!isset($jsonData->id_prof	) ? $response->addMessage("id_prof	 field is mandatory and must be provided") : false);
            (!isset($jsonData->id_room	) ? $response->addMessage("id_room	 field is mandatory and must be provided") : false);
            (!isset($jsonData->id_ts	) ? $response->addMessage("id_ts	 field is mandatory and must be provided") : false);
            (!isset($jsonData->division_str	) ? $response->addMessage("division_str	 field is mandatory and must be provided") : false);
            (!isset($jsonData->learn_sem) ? $response->addMessage("learn_sem field is mandatory and must be provided") : false);
            $response->send();
            exit;
          }



          // create new task with data, if non mandatory fields not provided then set to null

          $scheduler = new Scheduler(null, $jsonData->id_course, $jsonData->id_acadsem, $jsonData->type_division, $jsonData->lektiko_division, $jsonData->id_prof, $jsonData->id_room, $jsonData->id_ts, $jsonData->division_str, $jsonData->learn_sem);
          // get title, description, deadline, completed and store them in variables
          //$id = $scheduler->getID();
          $id_course = $scheduler->getIdCourse();
          $id_acadsem = $scheduler->getIdAcadsem();
          $type_division = $scheduler->getTypeDivision();
          $lektiko_division = $scheduler->getLektikoDivision();
          $id_prof = $scheduler->getIdProf();
          $id_room = $scheduler->getIdRoom();
          $id_ts = $scheduler->getIdTs();
          $division_str = $scheduler->getDivisionStr();
          $learn_sem = $scheduler->getLearnSem();



                    $query1 = $writeDB->prepare('SELECT id_course, id_acadsem, type_division, id_room, id_ts, learn_sem from scheduler where id_course = :id_course and id_acadsem = :id_acadsem and type_division = :type_division and id_room = :id_room and id_ts = :id_ts and learn_sem = :learn_sem');
                    $query1->bindParam(':id_course', $id_course, PDO::PARAM_INT);
                    $query1->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
                    $query1->bindParam(':type_division', $type_division, PDO::PARAM_STR);
                    $query1->bindParam(':id_room', $id_room, PDO::PARAM_INT);
                    $query1->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
                    $query1->bindParam(':learn_sem', $learn_sem, PDO::PARAM_STR);

                    $query1->execute();

                    // get row count
                    $rowCount1 = $query1->rowCount();

                    if($rowCount1 !== 0) {
                      // set up response for username already exists
                      $response = new Response();
                      $response->setHttpStatusCode(409);
                      $response->setSuccess(true);
                      $response->addMessage("ALREADY SCHEDULED");
                      $response->send();
                      exit;
                    }


          // ADD AUTH TO QUERY
          // create db query
          $query = $writeDB->prepare('insert into scheduler (id_course, id_acadsem, type_division, lektiko_division, id_prof, id_room, id_ts, division_str, learn_sem) values (:id_course, :id_acadsem, :type_division, :lektiko_division, :id_prof, :id_room, :id_ts, :division_str, :learn_sem)');
          //$query->bindParam(':id', $id, PDO::PARAM_INT);
          $query->bindParam(':id_course', $id_course, PDO::PARAM_STR);
          $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
          $query->bindParam(':type_division', $type_division, PDO::PARAM_STR);
          $query->bindParam(':lektiko_division', $lektiko_division, PDO::PARAM_STR);
          $query->bindParam(':id_prof', $id_prof, PDO::PARAM_INT);
          $query->bindParam(':id_room', $id_room, PDO::PARAM_INT);
          $query->bindParam(':id_ts', $id_ts, PDO::PARAM_INT);
          $query->bindParam(':division_str', $division_str, PDO::PARAM_STR);
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
            $response->addMessage("Failed to create Scheduler");
            $response->send();
            exit;
          }

          // get last task id so we can return the Task in the json
          $lastCoursethisyearID = $writeDB->lastInsertId();
          // ADD AUTH TO QUERY
          // create db query to get newly created task - get from master db not read slave as replication may be too slow for successful read
          $query = $readDB->prepare('SELECT id, id_course, id_acadsem, type_division, lektiko_division, id_prof, id_room, id_ts, division_str from scheduler where id=:id');
          $query->bindParam(':id', $lastCoursethisyearID, PDO::PARAM_INT);
          $query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // create Scheduler array to store returned Schedulers
          $schedulerArray = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new scheduler object for each row
            //echo "<br>" . $row['lektiko_scheduler'];
            $scheduler = new Scheduler($row['id'], $row['id_course'], $row['id_acadsem'], $row['type_division'], $row['lektiko_division'], $row['id_prof'], $row['id_room'], $row['id_ts'], $row['division_str'], $row['learn_sem']);

            // create scheduler and store in array for return in json data
            $schedulerArray[] = $scheduler->returnSchedulerAsArray();
          }

          // bundle schedulers and rows returned into an array to return in the json data
          $returnData = array();
          $returnData['rows_returned'] = $rowCount;
          $returnData['schedulers'] = $schedulerArray;

          //set up response for successful return
          $response = new Response();
          $response->setHttpStatusCode(201);
          $response->setSuccess(true);
          $response->addMessage("Scheduler created");
          $response->setData($returnData);
          $response->send();
          exit;
        }
        // if task fails to create due to data types, missing fields or invalid data then send error json
        catch(SchedulerException $ex) {
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

      else {
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->addMessage("Endpoint not found");
        $response->send();
        exit;
      }
    }

    elseif(array_key_exists("id_acadsem",$_GET) && array_key_exists("learn_sem",$_GET)) {

      // get available from query string
      $id_acadsem = $_GET['id_acadsem'];
      $learn_sem = $_GET['learn_sem'];
      /*
          // check to see if available in query string is either Y or N
          if($id_acadsem == " " && $id_acadsem < 0) {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("wrong acadsem");
            $response->send();
            exit;
          }
          */

          if($_SERVER['REQUEST_METHOD'] === 'GET') {
            // attempt to query the database
            try {
              // ADD AUTH TO QUERY
              // create db query
              $query = $readDB->prepare('SELECT id, id_course, id_acadsem, type_division, lektiko_division, id_prof, id_room, id_ts, division_str, learn_sem from scheduler where id_acadsem=:id_acadsem and learn_sem=:learn_sem');
              $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
              $query->bindParam(':learn_sem', $learn_sem, PDO::PARAM_STR);
          		$query->execute();

              // get row count
              $rowCount = $query->rowCount();

              // create task array to store returned tasks
              $schedulerArray = array();

              // for each row returned
              while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new task object for each row
                $scheduler = new Scheduler($row['id'], $row['id_course'], $row['id_acadsem'], $row['type_division'], $row['lektiko_division'], $row['id_prof'], $row['id_room'], $row['id_ts'], $row['division_str'], $row['learn_sem']);

                // create task and store in array for return in json data
          	    $schedulerArray[] = $scheduler->returnSchedulerAsArray();
              }

              // bundle task and rows returned into an array to return in the json data
              $returnData = array();
              $returnData['rows_returned'] = $rowCount;
              $returnData['schedulers'] = $schedulerArray;

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
            catch(SchedulerException $ex) {
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

        elseif(array_key_exists("id_course",$_GET) && array_key_exists("id_acadsem",$_GET) && array_key_exists("type_division",$_GET)) {

          // get available from query string
          $id = $_GET['id_course'];
          $id_acadsem = $_GET['id_acadsem'];
          $type_division = $_GET['type_division'];
          /*
              // check to see if available in query string is either Y or N
              if($id_acadsem == " " && $id_acadsem < 0) {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage("wrong acadsem");
                $response->send();
                exit;
              }
              */

              if($_SERVER['REQUEST_METHOD'] === 'GET') {
                // attempt to query the database
                try {
                  // ADD AUTH TO QUERY
                  // create db query
                  $query = $readDB->prepare('SELECT id, id_course, id_acadsem, type_division, lektiko_division, id_prof, id_room, id_ts, division_str, learn_sem from scheduler where id_course=:id_course and id_acadsem=:id_acadsem and type_division=:type_division');
                  $query->bindParam(':id_course', $id, PDO::PARAM_INT);
                  $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
                  $query->bindParam(':type_division', $type_division, PDO::PARAM_STR);
              		$query->execute();

                  // get row count
                  $rowCount = $query->rowCount();

                  // create task array to store returned tasks
                  $schedulerArray = array();

                  // for each row returned
                  while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                    // create new task object for each row
                    $scheduler = new Scheduler($row['id'], $row['id_course'], $row['id_acadsem'], $row['type_division'], $row['lektiko_division'], $row['id_prof'], $row['id_room'], $row['id_ts'], $row['division_str'], $row['learn_sem']);

                    // create task and store in array for return in json data
              	    $schedulerArray[] = $scheduler->returnSchedulerAsArray();
                  }

                  // bundle task and rows returned into an array to return in the json data
                  $returnData = array();
                  $returnData['rows_returned'] = $rowCount;
                  $returnData['schedulers'] = $schedulerArray;

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
                catch(SchedulerException $ex) {
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

            elseif(array_key_exists("id_course",$_GET) && array_key_exists("id_acadsem",$_GET) && array_key_exists("learn_sem",$_GET)) {

              // get available from query string
              $id = $_GET['id_course'];
              $id_acadsem = $_GET['id_acadsem'];
              $learn_sem = $_GET['learn_sem'];
              //$ls = $_GET['learn_sem'];
              /*
                  // check to see if available in query string is either Y or N
                  if($id_acadsem == " " && $id_acadsem < 0) {
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->addMessage("wrong acadsem");
                    $response->send();
                    exit;
                  }
                  */

                  if($_SERVER['REQUEST_METHOD'] === 'GET') {
                    // attempt to query the database
                    try {
                      // ADD AUTH TO QUERY
                      // create db query
                      $query = $readDB->prepare('SELECT id, id_course, id_acadsem, type_division, lektiko_division, id_prof, id_room, id_ts, division_str, learn_sem from scheduler where id_course=:id_course and id_acadsem=:id_acadsem and learn_sem=:learn_sem');
                      $query->bindParam(':id_course', $id, PDO::PARAM_STR);
                      $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
                      $query->bindParam(':learn_sem', $learn_sem, PDO::PARAM_STR);
                      //$query->bindParam(':learn_sem', $ls, PDO::PARAM_STR);
                  		$query->execute();

                      // get row count
                      $rowCount = $query->rowCount();

                      // create task array to store returned tasks
                      $schedulerArray = array();

                      // for each row returned
                      while($row = $query->fetch(PDO::FETCH_ASSOC)) {
                        // create new task object for each row
                        $scheduler = new Scheduler($row['id'], $row['id_course'], $row['id_acadsem'], $row['type_division'], $row['lektiko_division'], $row['id_prof'], $row['id_room'], $row['id_ts'], $row['division_str'], $row['learn_sem']);

                        // create task and store in array for return in json data
                  	    $schedulerArray[] = $scheduler->returnSchedulerAsArray();
                      }

                      // bundle task and rows returned into an array to return in the json data
                      $returnData = array();
                      $returnData['rows_returned'] = $rowCount;
                      $returnData['schedulers'] = $schedulerArray;

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
                    catch(SchedulerException $ex) {
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

    else {
      $response = new Response();
      $response->setHttpStatusCode(405);
      $response->setSuccess(false);
      $response->addMessage("Request method not allowed");
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
