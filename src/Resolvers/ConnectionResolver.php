<?php
// src/ConnectionResolver.php

namespace Eril\TblClass\Resolvers;

use Eril\TblClass\Config;
use PDO;
use PDOException;
use Exception;

class ConnectionResolver
{
    public static function fromConfig(Config $config): PDO
    {
        // 1. Try connection callback first
        if ($config->hasConnectionCallback()) {
            $callback = $config->getConnectionCallback();
            $pdo = $callback();

            if (!$pdo instanceof PDO) {
                throw new Exception("Connection callback must return PDO");
            }

            return $pdo;
        }

        // 2. Create based on driver
        $driver = $config->getDriver();

        return match ($driver) {
            'mysql'  => self::createMysqlConnection($config),
            'pgsql'  => self::createPgsqlConnection($config),
            'sqlite' => self::createSqliteConnection($config),
            default  => throw new Exception("Unsupported driver: $driver")
        };
    }

    private static function createMysqlConnection(Config $config): PDO
    {
        $dbName = $config->getDatabaseName();
        if (!$dbName) {
            throw new Exception(
                "MySQL database name not configured.\n" .
                "Please set 'database.name' or define DB_NAME"
            );
        }

        $host = $config->get('database.host', 'localhost');
        $port = (int) $config->get('database.port', 3306);
        $user = $config->get('database.user', 'root');
        $password = $config->get('database.password', '');

        if ($host === '') {
            throw new Exception("Database host not configured (DB_HOST).");
        }

        if ($user === '') {
            throw new Exception("Database user not configured (DB_USER).");
        }

        $dsn = "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4";

        if (($host === 'localhost' || $host === '127.0.0.1') && PHP_OS_FAMILY === 'Linux') {
            foreach (['/var/run/mysqld/mysqld.sock', '/tmp/mysql.sock'] as $socket) {
                if (file_exists($socket)) {
                    $dsn = "mysql:unix_socket=$socket;dbname=$dbName";
                    break;
                }
            }
        }

        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new Exception("MySQL connection failed: " . $e->getMessage());
        }
    }

    private static function createPgsqlConnection(Config $config): PDO
    {
        $dbName = $config->getDatabaseName();
        if (!$dbName) {
            throw new Exception(
                "PostgreSQL database name not configured.\n" .
                "Please set 'database.name' or define DB_NAME"
            );
        }

        $host = $config->get('database.host', 'localhost');
        $port = (int) $config->get('database.port', 5432);
        $user = $config->get('database.user', 'postgres');
        $password = $config->get('database.password', '');

        if ($host === '') {
            throw new Exception("Database host not configured (DB_HOST).");
        }

        if ($user === '') {
            throw new Exception("Database user not configured (DB_USER).");
        }

        $dsn = "pgsql:host=$host;port=$port;dbname=$dbName";

        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            $errorMsg = "PostgreSQL connection failed: " . $e->getMessage();

            if (str_contains($e->getMessage(), 'could not translate host name')) {
                $errorMsg .= "\n💡 Cannot resolve host '$host'. Check database.host.";
            } elseif (str_contains($e->getMessage(), 'password authentication failed')) {
                $errorMsg .= "\n💡 Check database credentials (user/password).";
            } elseif (str_contains($e->getMessage(), 'does not exist')) {
                $errorMsg .= "\n💡 Database '$dbName' does not exist.";
            }

            throw new Exception($errorMsg);
        }
    }

    private static function createSqliteConnection(Config $config): PDO
    {
        $path = $config->get('database.path', 'database.sqlite');

        if (!file_exists($path)) {
            throw new Exception("SQLite database file not found: $path");
        }

        try {
            return new PDO("sqlite:$path", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new Exception("SQLite connection failed: " . $e->getMessage());
        }
    }
}
