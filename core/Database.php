<?php
date_default_timezone_set('Asia/Manila');

class Database {
    private static ?mysqli $conn = null;

    public static function getConn(): mysqli {
        if(self::$conn === null){
            self::$conn = new mysqli('localhost', 'root', '', 'qr_attendance_system');
            if(self::$conn->connect_error){
                die("Connection failed: " . self::$conn->connect_error);
            }
        }
        return self::$conn;
    }
}
