  <?php

  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  error_reporting(0);

    class TimeSlotException extends Exception { }

    class TimeSlot {


      private $_id;
      private $_start_time;
      private $_day;


      public function __construct($id, $start_time , $day){
        $this->setID($id);
        $this->setStartTime($start_time);
        $this->setDay($day);
        }

      public function getID() {return $this->_id;}
      public function getStartTime() {return $this->_start_time;}
      public function getDay() {return $this->_day;}

      public function setID($id) {
        if(($id !== null) && (!is_numeric($id) || $id < 0)){
          throw new TimeSlotException("TimeSlot id error");
        }
          $this->_id = $id;
      }

      public function setStartTime($start_time) {
        if(($id_acadsem !== null) && (!is_numeric($id_acadsem) || $id_acadsem < 0)){
          throw new TimeSlotException("TimeSlot start_time error");
        }
        $this->_start_time = $start_time;

    }

      public function setDay($day) {
        if($day !== 'de' && $day !== 'tr' && $day !== 'te' && $day !== 'pe' && $day !== 'pa')
        {
          throw new TimeSlotException("TimeSlot day error");
        }
          $this->_day = $day;
      }




      public function returnTimeSlotAsArray() {
        $timeslot = array();
        $timeslot['id'] = $this->getID();
        $timeslot['start_time'] = $this->getStartTime();
        $timeslot['day'] = $this->getDay();


        return $timeslot;
      }


    }
