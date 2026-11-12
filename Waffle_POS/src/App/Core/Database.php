<?php
namespace App\Core;

use PDO;
use PDOException;

class Database {
    private static $pdo = null;
    private static $host = '127.0.0.1';
    private static $name = 'GridsWaffleDB';
    private static $user = 'root';
    private static $pass = '';

    // static method: called as Database::connect(), no object needed
    public static function connect() {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(
                    "mysql:host=" . self::$host . ";dbname=" . self::$name . ";charset=utf8mb4",
                    self::$user,
                    self::$pass,
                    [PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"]   // Philippine time for every date
                );
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $exception) {
                throw new DatabaseException("Database access failure: " . $exception->getMessage());
            }
        }
        return self::$pdo;
    }
}
