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
      private $_id_acadsem;


      public function __construct($id, $start_time , $day, $id_acadsem){
        $this->setID($id);
        $this->setStartTime($start_time);
        $this->setDay($day);
        $this->setIdAcadsem($id_acadsem);
        }

      public function getID() {return $this->_id;}
      public function getStartTime() {return $this->_start_time;}
      public function getDay() {return $this->_day;}
      public function getIdAcadsem() {return $this->_id_acadsem;}

      public function setID($id) {
        if(($id !== null) && (!is_numeric($id) || $id < 0)){
          throw new TimeSlotException("TimeSlot availability ID error");
        }
          $this->_id = $id;
      }

      public function setStartTime($start_time) {
        if(($id_acadsem !== null) && (!is_numeric($id_acadsem) || $id_acadsem < 0)){
          throw new TimeSlotException("_start_time error");
        }
        $this->_start_time = $start_time;

    }

      public function setDay($day) {
        if($day !== 'de' && $day !== 'tr' && $day !== 'te' && $day !== 'pe' && $day !== 'pa')
        {
          throw new TimeSlotException("Time Slot ID error");
        }
          $this->_day = $day;
      }

      public function setIdAcadsem($id_acadsem) {
        if(($id_acadsem !== null) && (!is_numeric($id_acadsem) || $id_acadsem < 0))
        {
          throw new TimeSlotException("TimeSlot id acadsem ID error");
        }
          $this->_id_acadsem = $id_acadsem;
      }


      public function returnTimeSlotAsArray() {
        $timeslot = array();
        $timeslot['id'] = $this->getID();
        $timeslot['start_time'] = $this->getStartTime();
        $timeslot['day'] = $this->getDay();
        $timeslot['id_acadsem'] = $this->getIdAcadsem();


        return $timeslot;
      }


    }
