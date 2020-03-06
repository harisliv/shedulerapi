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

      // if request is a GET e.g. get course_this_year
      if($_SERVER['REQUEST_METHOD'] === 'GET') {

        // attempt to query the database
        try {
          // ADD AUTH TO QUERY
          // create db query
          $query = $readDB->prepare('SELECT id, id_course, name, learn_sem, id_responsible_prof, id_acadsem, count_div_theory, count_div_lab, count_div_practice from course_this_year');
          $query->execute();

          // get row count
          $rowCount = $query->rowCount();

          // create AcadSem array to store returned course_this_year
          $coursethisyearArray = array();

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new course_this_year object for each row

            $coursethisyear = new Course_this_year($row['id'], $row['id_course'], $row['name'], $row['learn_sem'], $row['id_responsible_prof'], $row['id_acadsem'], $row['count_div_theory'], $row['count_div_lab'], $row['count_div_practice']);

            // create course_this_year and store in array for return in json data
            $coursethisyearArray[] = $coursethisyear->returnCourse_this_yearAsArray();
          }

          // bundle course_this_year and rows returned into an array to return in the json data
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
          $response->addMessage("Failed to get Course_this_year");
          $response->send();
          exit;
        }
      }


    }

    elseif (array_key_exists("learn_sem",$_GET) && array_key_exists("id_acadsem",$_GET)) {
      // get course_this_year id from query string
      $learn_sem = $_GET['learn_sem'];
      $id_acadsem = $_GET['id_acadsem'];

      if(($learn_sem !== 'A' && $learn_sem !== 'B' && $learn_sem !== 'C' && $learn_sem !== 'D' && $learn_sem !== 'E' && $learn_sem !== 'F' && $learn_sem !== 'G') || ($id_acadsem == '' || $id_acadsem < 0
      || !is_numeric($id_acadsem))) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        ($learn_sem !== 'A' && $learn_sem !== 'B' && $learn_sem !== 'C' && $learn_sem !== 'D' && $learn_sem !== 'E' && $learn_sem !== 'F' && $learn_sem !== 'G' ? $response->addMessage("Wrong learn sem") : false);
        ( $id_acadsem == '' || $id_acadsem < 0 || !is_numeric($id_acadsem) ? $response->addMessage("Wrong id_acadsem") : false);
        $response->send();
        exit;
      }

      // if request is a GET, e.g. get course_this_year
      if($_SERVER['REQUEST_METHOD'] === 'GET') {
        // attempt to query the database
        try {
          // create db query
          // ADD AUTH TO QUERY
          $query = $readDB->prepare('SELECT id, id_course, name, learn_sem, id_responsible_prof, id_acadsem, count_div_theory, count_div_lab, count_div_practice from course_this_year where learn_sem = :learn_sem and id_acadsem = :id_acadsem');
          $query->bindParam(':learn_sem', $learn_sem, PDO::PARAM_STR);
          $query->bindParam(':id_acadsem', $id_acadsem, PDO::PARAM_INT);
      		$query->execute();

          $rowCount = $query->rowCount();

          // create course_this_year array to store returned course_this_year
          $coursethisyearArray = array();

          if($rowCount === 0) {
            // set up response for unsuccessful return
            $response = new Response();
            $response->setHttpStatusCode(404);
            $response->setSuccess(false);
            $response->addMessage("Course_this_year not found");
            $response->send();
            exit;
          }

          // for each row returned
          while($row = $query->fetch(PDO::FETCH_ASSOC)) {
            // create new course_this_year object for each row
            $coursethisyear = new Course_this_year($row['id'], $row['id_course'], $row['name'], $row['learn_sem'], $row['id_responsible_prof'], $row['id_acadsem'], $row['count_div_theory'], $row['count_div_lab'], $row['count_div_practice']);

            // create course_this_year and store in array for return in json data
            $coursethisyearArray[] = $coursethisyear->returnCourse_this_yearAsArray();
          }

          // bundle course_this_years and rows returned into an array to return in the json data
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
          $response->addMessage("Failed to get Course_this_year");
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
