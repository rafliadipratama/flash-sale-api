<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../config/database.php';
            $dsn = is_callable($config['dsn']) ? $config['dsn']() : $config['dsn'];

            try {
                self::$instance = new PDO(
                    $dsn,
                    getenv('DB_USER') ?: null,
                    getenv('DB_PASS') ?: null,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                die('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function initialize(): void
    {
        $db = self::connect();
        $sql = file_get_contents(__DIR__ . '/../database/init.sql');
        $db->exec($sql);
    }
}
