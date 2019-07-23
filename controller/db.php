<?php

class DB {

  private static $writeDBConnection;
  private static $readDBConnection;

  public static function connectWriteDB() {
    if(self::$writeDBConnection == null){
      self::$writeDBConnection = new PDO("mysql:host=localhost;dbname=scheduler;utf8", 'root', '', array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET UTF8"));
      //self::$writeDBConnection = new PDO('mysql:host=149.202.214.7;dbname=ine10378_scheduler;utf8', 'ine10378_scheduler', 'VOQY2kJ{Bd2G');
      self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    return self::$writeDBConnection;
  }

  public static function connectReadDB() {
    if(self::$readDBConnection == null) {
      self::$readDBConnection = new PDO("mysql:host=localhost;dbname=scheduler;utf8", 'root', '', array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET UTF8"));
      //self::$readDBConnection = new PDO('mysql:host=149.202.214.7;dbname=ine10378_scheduler;utf8', 'ine10378_scheduler', 'VOQY2kJ{Bd2G');
      self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    return self::$readDBConnection;
  }



}
