  <?php

  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  error_reporting(0);

    class ProfessorException extends Exception { }

    class Professor {


      private $_id;
      private $_fullname;
      private $_id_type;
      private $_active_prof;

      public function __construct($id, $fullname,  $id_type, $active_prof){
        $this->setID($id);
        $this->setFullname($fullname);
        $this->setIdType($id_type);
        $this->setActiveProf($active_prof);
        }

      public function getID() {return $this->_id;}
      public function getFullname() {return $this->_fullname;}
      public function getIdType() {return $this->_id_type;}
      public function getActiveProf() {return $this->_active_prof;}

      public function setID($id) {
        if(($id !== null) && (!is_numeric($id) || $id < 0)){
          throw new ProfessorException("Professor ID error");
        }
          $this->_id = $id;
      }

      public function setFullname($fullname) {
        if(strlen($fullname) < 0 || strlen($fullname) > 255){
          throw new ProfessorException("Professor fullname error");
        }
        $this->_fullname = $fullname;
    }

      public function setIdType($id_type) {
        if(strtoupper($id_type) !== 'MONIMOS' && strtoupper($id_type) !== 'ANAPLHRWTHS'){
          throw new ProfessorException("Professor id_type error");
        }
          $this->_id_type = $id_type;
      }

      public function setActiveProf($active_prof) {
        if(strtoupper($active_prof) !== 'N' && strtoupper($active_prof) !== 'Y'){
          throw new ProfessorException("Professor active_prof error");
        }
          $this->_active_prof = $active_prof;
      }


      public function returnProfessorAsArray() {
        $professor = array();
        $professor['id'] = $this->getID();
        $professor['fullname'] = $this->getFullname();
        $professor['id_type'] = $this->getIdType();
        $professor['active_prof'] = $this->getActiveProf();

        return $professor;
      }


    }
