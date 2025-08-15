<?php
require_once 'config.php';

// 设置时区
date_default_timezone_set(TIMEZONE);

// 数据库连接类
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->conn = new PDO($dsn, DB_USER, DB_PASS);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die("数据库连接失败: " . $e->getMessage());
        }
    }

    // 单例模式获取数据库连接
    public static function getInstance() {
        if(self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->conn;
    }
}

// 获取数据库连接
$pdo = Database::getInstance();
?>
