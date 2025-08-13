<?php

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null)
        {
            $host = 'mysql'; // docker-compose servis adı
            $db   = $_ENV['MYSQL_DATABASE'] ?? 'qr_login';
            $user = $_ENV['MYSQL_USER'] ?? 'qr_user';
            $pass = $_ENV['MYSQL_PASSWORD'] ?? 'qr_pass';
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try
            {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            }
            catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }

        return self::$instance;
    }
}