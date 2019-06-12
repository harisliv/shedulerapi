<?php


//header('Content-Type: text/html; charset=utf-8');


  class SchedulerException extends Exception { }

  class Scheduler {


    private $_id;
    private $_id_course;
    private $_id_acadsem;
    private $_type_division;
    private $_lektiko_division;
    private $_id_prof;
    private $_id_room;
    private $_id_ts;
    private $_division_str;

    public function __construct($id, $id_course, $id_acadsem, $type_division, $lektiko_division, $id_prof, $id_room, $id_ts, $division_str){
      $this->setID($id);
      $this->setIdCourse($id_course);
      $this->setIdAcadsem($id_acadsem);
      $this->setTypeDivision($type_division);
      $this->setLektikoDivision($lektiko_division);
      $this->setIdProf($id_prof);
      $this->setIdRoom($id_room);
      $this->setIdTs($id_ts);
      $this->setDivisionStr($division_str);
      }

    public function getID() {return $this->_id;}
    public function getIdCourse() {return $this->_id_course;}
    public function getIdAcadsem() {return $this->_id_acadsem;}
    public function getTypeDivision() {return $this->_type_division;}
    public function getLektikoDivision() {return $this->_lektiko_division;}
    public function getIdProf() {return $this->_id_prof;}
    public function getIdRoom() {return $this->_id_room;}
    public function getIdTs() {return $this->_id_ts;}
    public function getDivisionStr() {return $this->_division_str;}

    public function setID($id) {
      if(($id !== null) && (!is_numeric($id) || $id < 0 )){
        throw new SchedulerException("Course ID error");
      }
        $this->_id = $id;
    }

    public function setIdCourse($id_course) {
      if(strlen($id_course) < 0 || strlen($id_course) > 150){
        throw new SchedulerException("Course id_course error");
      }
      $this->_id_course=$id_course;
    }

    public function setIdAcadsem($id_acadsem) {
      if(($id_acadsem !== null) && (!is_numeric($id_acadsem) || $id_acadsem < 0))
      {
        throw new SchedulerException("_id_acadsem error");
      }
        $this->_id_acadsem = $id_acadsem;
    }


    public function setTypeDivision($type_division) {
      if(strtoupper($type_division) !== 'LAB' && strtoupper($type_division) !== 'THEORY'&& $type_division !== 'PRACTICE'){
        throw new SchedulerException("Course type_division must by X or E or 0");
      }
      $this->_type_division=$type_division;
    }

    public function setLektikoDivision($lektiko_division) {
      if(strlen($lektiko_division) < 0 || strlen($lektiko_division) > 255){
        throw new SchedulerException("_lektiko_division error");
      }
      $this->_lektiko_division = $lektiko_division;
    }

    public function setIdProf($id_prof) {
      if(($id_prof !== null) && (!is_numeric($id_prof) || $id_prof < 0)){
        throw new SchedulerException("_id_prof error");
      }
        $this->_id_prof = $id_prof;
    }

    public function setIdRoom($id_room) {
      if(($id_room !== null) && (!is_numeric($id_room) || $id_room < 0)){
        throw new SchedulerException("_id_room error");
      }
        $this->_id_room = $id_room;
    }

    public function setIdTs($id_ts) {
      if(($id_ts !== null) && (!is_numeric($id_ts) || $id_ts < 0))
      {
        throw new SchedulerException("_id_ts error");
      }
        $this->_id_ts = $id_ts;
    }

    public function setDivisionStr($division_str) {
      if(strlen($division_str) < 0 || strlen($division_str) > 255){
        throw new SchedulerException("_division_str error");
      }
      $this->_division_str = $division_str;
    }



    public function returnSchedulerAsArray() {
      $scheduler = array();
      $scheduler['id'] = $this->getID();
      $scheduler['id_course'] = $this->getIdCourse();
      $scheduler['id_acadsem'] = $this->getIdAcadsem();
      $scheduler['type_division'] = $this->getTypeDivision();
      $scheduler['lektiko_division'] = $this->getLektikoDivision();
      $scheduler['id_prof'] = $this->getIdProf();
      $scheduler['id_room'] = $this->getIdRoom();
      $scheduler['id_ts'] = $this->getIdTs();
      $scheduler['division_str'] = $this->getDivisionStr();

      return $scheduler;
    }


  }
