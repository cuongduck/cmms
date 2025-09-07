<?php
/**
 * Database Configuration - Fixed Collation
 * CMMS System Database Connection
 */

class Database {
    private $host = '10.18.15.43';
    private $dbname = 'cmms';
    private $username = 'cf';
    private $password = 'Baotri@123';
    private $charset = 'utf8mb4';
    private static $instance = null;
    private $pdo;

    public function __construct() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            // Đặt collation mặc định cho session
            $this->pdo->exec("SET collation_connection = utf8mb4_unicode_ci");
            $this->pdo->exec("SET collation_database = utf8mb4_unicode_ci");
            $this->pdo->exec("SET collation_server = utf8mb4_unicode_ci");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Không thể kết nối database");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }

    public function execute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function fetch($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            // Log chi tiết lỗi để debug
            error_log("Database fetch error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw $e;
        }
    }

    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Log chi tiết lỗi để debug
            error_log("Database fetchAll error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw $e;
        }
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    public function rowCount($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }

    public function commit() {
        return $this->pdo->commit();
    }

    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Helper method để xử lý query với BINARY cho so sánh string
     */
    public function fetchWithBinary($sql, $params = []) {
        // Thay thế các so sánh string với BINARY để tránh lỗi collation
        $sql = preg_replace('/(\w+)\s*=\s*\?/', 'BINARY $1 = ?', $sql);
        return $this->fetch($sql, $params);
    }
    
    public function fetchAllWithBinary($sql, $params = []) {
        // Thay thế các so sánh string với BINARY để tránh lỗi collation
        $sql = preg_replace('/(\w+)\s*=\s*\?/', 'BINARY $1 = ?', $sql);
        return $this->fetchAll($sql, $params);
    }
}

// Khởi tạo kết nối database global
$db = Database::getInstance();
$pdo = $db->getConnection();
?>