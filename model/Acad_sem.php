  <?php

  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  error_reporting(0);

    class AcadSemException extends Exception { }

    class AcadSem {


      private $_id;
      private $_acad_year;
      private $_type_sem;
      private $_lektiko_acadsem;

      public function __construct($id, $acad_year,  $type_sem, $lektiko_acadsem){
        $this->setID($id);
        $this->setAcadYear($acad_year);
        $this->setTypeSem($type_sem);
        $this->setLektikoAcadsem($lektiko_acadsem);
        }

      public function getID() {return $this->_id;}
      public function getAcadYear() {return $this->_acad_year;}
      public function getTypeSem() {return $this->_type_sem;}
      public function getLektikoAcadsem() {return $this->_lektiko_acadsem;}

      public function setID($id) {
        if(($id !== null) && (!is_numeric($id) || $id < 0)){
          throw new AcadSemException("AcadSem availability ID error");
        }
          $this->_id = $id;
      }

      public function setAcadYear($acad_year) {
        if(($acad_year !== null) && (!is_numeric($acad_year) || $acad_year < 0)){
          throw new AcadSemException("AcadSem availability ID error");
        }
          $this->_acad_year = $acad_year;
      }

        public function setTypeSem($type_sem) {
          if(strtoupper($type_sem) !== 'W' && strtoupper($type_sem) !== 'S'){
            throw new AcadSemException("type_sem must by w or s");
          }
            $this->_type_sem = $type_sem;
        }

      public function setLektikoAcadsem($lektiko_acadsem) {
        if(strlen($lektiko_acadsem) < 0 || strlen($lektiko_acadsem) > 255){
          throw new AcadSemException("_lektiko_acadsem error");
        }
        $this->_lektiko_acadsem = $lektiko_acadsem;
    }


      public function returnAcadSemAsArray() {
        $acadsem = array();
        $acadsem['id'] = $this->getID();
        $acadsem['acad_year'] = $this->getAcadYear();
        $acadsem['type_sem'] = $this->getTypeSem();
        $acadsem['lektiko_acadsem'] = $this->getLektikoAcadsem();

        return $acadsem;
      }


    }
