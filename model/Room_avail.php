  <?php

  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  //error_reporting(0);

    class Room_availException extends Exception { }

    class Room_avail {


      private $_id;
      private $_id_room;
      private $_id_ts;
      private $_id_acadsem;
      private $_available;
      //private $_userid;

      public function __construct($id, $id_room , $id_ts , $id_acadsem , $available){
        $this->setID($id);
        $this->setIdRoom($id_room);
        $this->setIdTs($id_ts);
        $this->setIdAcadsem($id_acadsem);
        $this->setAvailable($available);
        }

      public function getID() {return $this->_id;}
      public function getIdRoom() {return $this->_id_room;}
      public function getIdTs() {return $this->_id_ts;}
      public function getIdAcadsem() {return $this->_id_acadsem;}
      public function getAvailable() {return $this->_available;}

      public function setID($id) {
        if(($id !== null) && (!is_numeric($id) || $id < 0)){
          throw new Room_availException("Room_avail availability ID error");
        }
          $this->_id = $id;
      }

      public function setIdRoom($id_room) {
        if(($id_room !== null) && (!is_numeric($id_room) || $id_room < 0))
        {
          throw new Room_availException("Room_avail ID error");
        }
          $this->_id_room = $id_room;
      }

      public function setIdTs($id_ts) {
        if(($id_ts !== null) && (!is_numeric($id_ts) || $id_ts < 0)){
          throw new Room_availException("Time Slot ID error");
        }
          $this->_id_ts = $id_ts;
      }

      public function setIdAcadsem($id_acadsem) {
        if(($id_acadsem !== null) && (!is_numeric($id_acadsem) || $id_acadsem < 0)){
          throw new Room_availException("Acad Sem ID error");
        }
          $this->_id_acadsem = $id_acadsem;
      }

      public function setAvailable($available) {
            if(strtoupper($available) !== 'Y' && strtoupper($available) !== 'N'){
              throw new Room_availException("Room_avail available must by X or E");
            }
            $this->_available=$available;
          }


      public function returnRoom_availAsArray() {
        $room_avail = array();
        $room_avail['id'] = $this->getID();
        $room_avail['id_room'] = $this->getIdRoom();
        $room_avail['id_ts'] = $this->getIdTs();
        $room_avail['id_acadsem'] = $this->getIdAcadsem();
        $room_avail['available'] = $this->getAvailable();

        return $room_avail;
      }


    }
