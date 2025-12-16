<?php
/**
 * Database Connection Class
 * Handles database connections and queries
 */

namespace LagunaPartners;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $connection;
    private $config;

    private function __construct() {
        $this->config = require __DIR__ . '/../config/config.php';
        $this->connect();
    }

    private function connect() {
        try {
            $dbConfig = $this->config['database'];
            $dsn = sprintf(
                "mysql:host=%s;port=%d;dbname=%s;charset=%s;unix_socket=",
                $dbConfig['host'],
                $dbConfig['port'],
                $dbConfig['database'],
                $dbConfig['charset']
            );

            $this->connection = new PDO(
                $dsn,
                $dbConfig['username'],
                $dbConfig['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection failed");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchColumn($sql, $params = []) {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($data as $key => $value) {
            $params[":$key"] = $value;
        }

        $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "$column = :set_$column";
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setParts),
            $where
        );

        $params = [];
        foreach ($data as $key => $value) {
            $params[":set_$key"] = $value;
        }
        
        // Convert positional parameters to named parameters for WHERE clause
        if (!empty($whereParams)) {
            $whereIndex = 0;
            foreach ($whereParams as $value) {
                $params[":where_$whereIndex"] = $value;
                $whereIndex++;
            }
            
            // Replace ? with named parameters in WHERE clause
            $whereIndex = 0;
            $sql = preg_replace_callback('/\?/', function() use (&$whereIndex) {
                return ":where_" . $whereIndex++;
            }, $sql);
        }

        return $this->query($sql, $params)->rowCount();
    }

    public function delete($table, $where, $whereParams = []) {
        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
        return $this->query($sql, $whereParams)->rowCount();
    }

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollback() {
        return $this->connection->rollBack();
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
}