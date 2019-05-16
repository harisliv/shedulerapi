<?php

class DB {

  private static $writeDBConnection;
  private static $readDBConnection;

  public static function connectWriteDB() {
    if(self::$writeDBConnection == null) {
      self::$writeDBConnection = new PDO('mysql:host=136.243.5.115;dbname=live9824_scheduler;utf8', 'live9824_udemyv1', '=snJ5o.-4_yw');
      self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    return self::$writeDBConnection;
  }

  public static function connectReadDB() {
    if(self::$readDBConnection == null) {
      self::$readDBConnection = new PDO('mysql:host=136.243.5.115;dbname=live9824_scheduler;utf8', 'live9824_udemyv1', '=snJ5o.-4_yw');
      self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    return self::$readDBConnection;
  }

}
