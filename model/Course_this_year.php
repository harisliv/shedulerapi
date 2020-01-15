<?php


//header('Content-Type: text/html; charset=utf-8');


  class Course_this_yearException extends Exception { }

  class Course_this_year {


    private $_id;
    private $_id_course;
    private $_name;
    private $_learn_sem;
    private $_id_responsible_prof;
    private $_id_acadsem;
    private $_count_div_theory;
    private $_count_div_lab;
    private $_count_div_practice;
    //private $_userid;

    public function __construct($id, $id_course, $name, $learn_sem, $id_responsible_prof, $id_acadsem, $count_div_theory , $count_div_lab , $count_div_practice){
      $this->setID($id);
      $this->setIdCourse($id_course);
      $this->setName($name);
      $this->setLearnSem($learn_sem);
      $this->setIdResponsibleProf($id_responsible_prof);
      $this->setIdAcadsem($id_acadsem);
      $this->setCountDivTheory($count_div_theory);
      $this->setCountDivLab($count_div_lab);
      $this->setCountDivPractice($count_div_practice);
      }

    public function getID() {return $this->_id;}
    public function getIdCourse() {return $this->_id_course;}
    public function getName() {return $this->_name;}
    public function getLearnSem() {return $this->_learn_sem;}
    public function getIdResponsibleProf() {return $this->_id_responsible_prof;}
    public function getIdAcadsem() {return $this->_id_acadsem;}
    public function getCountDivTheory() {return $this->_count_div_theory;}
    public function getCountDivLab() {return $this->_count_div_lab;}
    public function getCountDivPractice() {return $this->_count_div_practice;}

    public function setID($id) {
      if(($id !== null) && (!is_numeric($id) || $id < 0)){
        throw new Course_this_yearException("AcadSem availability ID error");
      }
        $this->_id = $id;
    }

    public function setIdCourse($id_course) {
      if(strlen($id_course) < 0 || strlen($id_course) > 150){
        throw new Course_this_yearException("Course_this_year id_course error");
      }
      $this->_id_course=$id_course;
    }

    public function setName($name) {
      if(strlen($name) < 0 || strlen($name) > 150){
        throw new Course_this_yearException("Course Name error");
      }
      $this->_name=$name;
    }


    public function setLearnSem($learn_sem) {
      if(strtoupper($learn_sem) !== 'A' && strtoupper($learn_sem) !== 'B' && $learn_sem !== 'C' && $learn_sem !== 'D' && $learn_sem !== 'E' && $learn_sem !== 'F' && $learn_sem !== 'G'){
        throw new Course_this_yearException("Course period must by Α or Β or Γ");
      }
      $this->_learn_sem=$learn_sem;
    }

    public function setIdResponsibleProf($id_responsible_prof) {
      if(strlen($id_responsible_prof) < 0 || strlen($id_responsible_prof) > 150){
        throw new Course_this_yearException("id_responsible_prof Programma Spoudwn error");
      }
      $this->_id_responsible_prof=$id_responsible_prof;
    }

    public function setIdAcadsem($id_acadsem) {
      if(($id_acadsem !== null) && (!is_numeric($id_acadsem) || $id_acadsem < 0)){
        throw new Course_this_yearException("Course_this_year Programma Spoudwn error");
      }
      $this->_id_acadsem=$id_acadsem;
    }

    public function setCountDivTheory($count_div_theory) {
      if(($count_div_theory !== null) && (!is_numeric($count_div_theory) || $count_div_theory < 0 || $count_div_theory > 15 ))
      {
        throw new Course_this_yearException("HOURS THEORY error");
      }
        $this->_count_div_theory = $count_div_theory;
    }

    public function setCountDivLab($count_div_lab) {
      if(($count_div_lab !== null) && (!is_numeric($count_div_lab) || $count_div_lab < 0 || $count_div_lab > 15)){
        throw new Course_this_yearException("HOURS LAB error");
      }
        $this->_count_div_lab = $count_div_lab;
    }

    public function setCountDivPractice($count_div_practice) {
      if(($count_div_practice !== null) && (!is_numeric($count_div_practice) || $count_div_practice < 0 || $count_div_practice > 15)){
        throw new Course_this_yearException("HOURS PRACTICE ID error");
      }
        $this->_count_div_practice = $count_div_practice;
    }


    public function returnCourse_this_yearAsArray() {
      $coursethisyear = array();
      $coursethisyear['id'] = $this->getID();
      $coursethisyear['id_course'] = $this->getIdCourse();
      $coursethisyear['name'] = $this->getName();
      $coursethisyear['learn_sem'] = $this->getLearnSem();
      $coursethisyear['id_responsible_prof'] = $this->getIdResponsibleProf();
      $coursethisyear['id_acadsem'] = $this->getIdAcadsem();
      $coursethisyear['count_div_theory'] = $this->getCountDivTheory();
      $coursethisyear['count_div_lab'] = $this->getCountDivLab();
      $coursethisyear['count_div_practice'] = $this->getCountDivPractice();

      return $coursethisyear;
    }


  }
