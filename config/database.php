<?php
// config/database.php
class Database {
    private $host = '10.18.15.43';
    private $db_name = 'iot_cmms';
    private $username = 'mmb';
    private $password = 'ssss5';
    private $charset = 'utf8mb4';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            die("Lỗi kết nối database");
        }
        return $this->conn;
    }
}

// Khởi tạo kết nối global
$database = new Database();
$db = $database->getConnection();
?>