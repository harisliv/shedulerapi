<?php


//header('Content-Type: text/html; charset=utf-8');


  class CourseException extends Exception { }

  class Course {


    private $_id;
    private $_course_id;
    private $_name;
    private $_curr;
    private $_period;
    private $_active;
    private $_hours_theory;
    private $_hours_lab;
    private $_hours_practice;

    public function __construct($id, $course_id, $name, $curr, $period , $active , $hours_theory , $hours_lab , $hours_practice){
      $this->setID($id);
      $this->setCourseID($course_id);
      $this->setName($name);
      $this->setCurr($curr);
      $this->setPeriod($period);
      $this->setActive($active);
      $this->setHoursTheory($hours_theory);
      $this->setHoursLab($hours_lab);
      $this->setHoursPractice($hours_practice);
      }

    public function getID() {return $this->_id;}
    public function getCourseID() {return $this->_course_id;}
    public function getName() {return $this->_name;}
    public function getCurr() {return $this->_curr;}
    public function getPeriod() {return $this->_period;}
    public function getActive() {return $this->_active;}
    public function getHoursTheory() {return $this->_hours_theory;}
    public function getHoursLab() {return $this->_hours_lab;}
    public function getHoursPractice() {return $this->_hours_practice;}

    public function setID($id) {
      if(($id !== null) && (!is_numeric($id) || $id < 0)){
        throw new CourseException("Course id error");
      }
        $this->_id = $id;
    }

    public function setCourseID($course_id) {
      if(strlen($course_id) < 0 || strlen($course_id) > 150){
        throw new CourseException("Course course_id error");
      }
        $this->_course_id = $course_id;
    }

    public function setName($name) {
      if(strlen($name) < 0 || strlen($name) > 150){
        throw new CourseException("Course Name error");
      }
      $this->_name=$name;
    }

    public function setCurr($curr) {
      if(strlen($curr) < 0 || strlen($curr) > 50){
        throw new CourseException("Course curr error");
      }
      $this->_curr=$curr;
    }


    public function setPeriod($period) {
      if(strtoupper($period) !== 'X' && strtoupper($period) !== 'E'&& $period !== '-'){
        throw new CourseException("Course period error");
      }
      $this->_period=$period;
    }


    public function setActive($active) {
      if(strtoupper($active) !== 'N' && strtoupper($active) !== 'Y' ){
        throw new CourseException("Course active error");
      }
      $this->_active=$active;
    }

    public function setHoursTheory($hours_theory) {
      if(($hours_theory !== null) && (!is_numeric($hours_theory) || $hours_theory < 0 || $hours_theory > 9 ))
      {
        throw new CourseException("Course hours_theory error");
      }
        $this->_hours_theory = $hours_theory;
    }

    public function setHoursLab($hours_lab) {
      if(($hours_lab !== null) && (!is_numeric($hours_lab) || $hours_lab < 0 || $hours_lab > 9)){
        throw new CourseException("Course hours_lab error");
      }
        $this->_hours_lab = $hours_lab;
    }

    public function setHoursPractice($hours_practice) {
      if(($hours_practice !== null) && (!is_numeric($hours_practice) || $hours_practice < 0 || $hours_practice > 9)){
        throw new CourseException("Course hours_practice error");
      }
        $this->_hours_practice = $hours_practice;
    }



    public function returnCourseAsArray() {
      $course = array();
      $course['id'] = $this->getID();
      $course['course_id'] = $this->getCourseID();
      $course['name'] = $this->getName();
      $course['curr'] = $this->getCurr();
      $course['period'] = $this->getPeriod();
      $course['active'] = $this->getActive();
      $course['hours_theory'] = $this->getHoursTheory();
      $course['hours_lab'] = $this->getHoursLab();
      $course['hours_practice'] = $this->getHoursPractice();

      return $course;
    }


  }
