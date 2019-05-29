<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
//error_reporting(0);

//header('Content-Type: text/html; charset=utf-8');


  class CourseException extends Exception { }

  class Course {


    private $_id;
    private $_name;
    private $_curr;
    private $_period;
    private $_active;
    private $_hours_theory;
    private $_hours_lab;
    private $_hours_practice;
    //private $_userid;

    public function __construct($id, $name, $curr, $period , $active , $hours_theory , $hours_lab , $hours_practice){
      $this->setID($id);
      $this->setName($name);
      $this->setCurr($curr);
      $this->setPeriod($period);
      $this->setActive($active);
      $this->setHoursTheory($hours_theory);
      $this->setHoursLab($hours_lab);
      $this->setHoursPractice($hours_practice);
      //$this->setIdUser($userid);
      }

    public function getID() {return $this->_id;}
    public function getName() {return $this->_name;}
    public function getCurr() {return $this->_curr;}
    public function getPeriod() {return $this->_period;}
    public function getActive() {return $this->_active;}
    public function getHoursTheory() {return $this->_hours_theory;}
    public function getHoursLab() {return $this->_hours_lab;}
    public function getHoursPractice() {return $this->_hours_practice;}
    //public function getIdUser() {return $this->_userid;}

    public function setID($id) {
      if(strlen($id) < 0 || strlen($id) > 150){
        throw new CourseException("Course ID error");
      }
        $this->_id = $id;
    }

    public function setName($name) {
      //if(strlen($name) < 0 || strlen($name) > 150){
      //  throw new CourseException("Course Name error");
      //}
      $this->_name=$name;
    }

    public function setCurr($curr) {
      if(strlen($curr) < 0 || strlen($curr) > 12){
        throw new CourseException("Course Programma Spoudwn error");
      }
      $this->_curr=$curr;
    }


    public function setPeriod($period) {
      if(strtoupper($period) !== 'X' && strtoupper($period) !== 'E' && $period !== '-'){
        throw new CourseException("Course period must by X or E or 0");
      }
      $this->_period=$period;
    }


    public function setActive($active) {
      if(strtoupper($active) !== 'N' && strtoupper($active) !== 'Y'){
        throw new CourseException("Course active must by Y or N");
      }
      $this->_active=$active;
    }

    public function setHoursTheory($hours_theory) {
      if(($hours_theory !== null) && (!is_numeric($hours_theory) || $hours_theory < 0 || $hours_theory > 9 ))
      {
        throw new CourseException("HOURS THEORY error");
      }
        $this->_hours_theory = $hours_theory;
    }

    public function setHoursLab($hours_lab) {
      if(($hours_lab !== null) && (!is_numeric($hours_lab) || $hours_lab < 0 || $hours_lab > 9)){
        throw new CourseException("Course ID error");
      }
        $this->_hours_lab = $hours_lab;
    }

    public function setHoursPractice($hours_practice) {
      if(($hours_practice !== null) && (!is_numeric($hours_practice) || $hours_practice < 0 || $hours_practice > 9)){
        throw new CourseException("Course ID error");
      }
        $this->_hours_practice = $hours_practice;
    }

    

    public function returnCourseAsArray() {
      $course = array();
      $course['id'] = $this->getID();
      $course['name'] = $this->getName();
      $course['curr'] = $this->getCurr();
      $course['period'] = $this->getPeriod();
      $course['active'] = $this->getActive();
      $course['hours_theory'] = $this->getHoursTheory();
      $course['hours_lab'] = $this->getHoursLab();
      $course['hours_practice'] = $this->getHoursPractice();
      //$course['userid'] = $this->getIdUser();

      return $course;
    }


  }
