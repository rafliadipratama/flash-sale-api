<?php
return [
    'driver' => getenv('DB_DRIVER') ?: 'sqlite',
    'path' => getenv('DB_PATH') ?: __DIR__ . '/../database.db',
    'dsn' => function() {
        $driver = getenv('DB_DRIVER') ?: 'sqlite';
        if ($driver === 'sqlite') {
            return 'sqlite:' . (__DIR__ . '/../database.db');
        }
        // For MySQL: mysql:host=127.0.0.1;dbname=online_store;charset=utf8mb4
        return getenv('DATABASE_URL');
    }
];
