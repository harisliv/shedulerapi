  <?php

  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
  error_reporting(0);

    class RoomException extends Exception { }

    class Room {


      private $_id;
      private $_lektiko_room;
      private $_room_code;

      public function __construct($id, $lektiko_room , $room_code){
        $this->setID($id);
        $this->setLektikoRoom($lektiko_room);
        $this->setRoomCode($room_code);
        }

      public function getID() {return $this->_id;}
      public function getLektikoRoom() {return $this->_lektiko_room;}
      public function getRoomCode() {return $this->_room_code;}

      public function setID($id) {
        if(($id !== null) && (!is_numeric($id) || $id < 0)){
          throw new RoomException("Room availability ID error");
        }
          $this->_id = $id;
      }

      public function setLektikoRoom($lektiko_room) {
        if(strlen($lektiko_room) < 0 || strlen($lektiko_room) > 255){
          throw new RoomException("_lektiko_room error");
        }
        $this->_lektiko_room = $lektiko_room;

    }

      public function setRoomCode($room_code) {
        if(strlen($room_code) < 0 || strlen($room_code) > 150){
          throw new RoomException("Time Slot ID error");
        }
          $this->_room_code = $room_code;
      }


      public function returnRoomAsArray() {
        $room = array();
        $room['id'] = $this->getID();
        $room['lektiko_room'] = $this->getLektikoRoom();
        $room['room_code'] = $this->getRoomCode();

        return $room;
      }


    }
