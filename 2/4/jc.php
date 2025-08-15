<?php
// 设置上海时区
date_default_timezone_set('Asia/Shanghai');

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'qwd');
define('DB_PASS', 'qwd'); // 替换为你的数据库密码
define('DB_NAME', 'qwd');
define('SECRET_KEY', 'wqd');
define('SITE_NAME', '用户管理系统');
define('COOKIE_EXPIRE', 30 * 24 * 3600); // Cookie 有效期 30 天

/**
 * 数据库连接函数
 * @return mysqli 数据库连接对象
 */
function db_connect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("数据库连接失败: " . $conn->connect_error);
    }
    // 设置字符集为 utf8mb4
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * 初始化系统必需的表结构
 */
function init_system_tables() {
    $conn = db_connect();
    
    // 1. 创建用户角色表
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            description VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 插入默认角色（如果不存在）
    $roles = [
        ['name' => 'user', 'description' => '一般用户'],
        ['name' => 'developer', 'description' => '开发者'],
        ['name' => 'authorized_developer', 'description' => '授权开发者'],
        ['name' => 'management_developer', 'description' => '管理开发者（特别身份）'],
        ['name' => 'service_provider', 'description' => '服务商（特别身份）'],
        ['name' => 'special_authorized_provider', 'description' => '特别授权服务商（特别身份）'],
        ['name' => 'general_admin', 'description' => '一般管理员（特别身份）'],
        ['name' => 'super_admin', 'description' => '超级管理员（特别身份）']
    ];
    
    foreach ($roles as $role) {
        $stmt = $conn->prepare("INSERT IGNORE INTO user_roles (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $role['name'], $role['description']);
        $stmt->execute();
        $stmt->close();
    }
    
    // 2. 创建用户表
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            uid VARCHAR(50) NOT NULL UNIQUE COMMENT '用户唯一标识',
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            qq VARCHAR(20) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role_id INT NOT NULL,
            status ENUM('normal', 'locked', 'restricted', 'frozen') DEFAULT 'normal',
            register_time DATETIME NOT NULL,
            is_email_verified TINYINT(1) DEFAULT 0,
            service_provider_id INT DEFAULT NULL,
            FOREIGN KEY (role_id) REFERENCES user_roles(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 3. 创建登录日志表
    $conn->query("
        CREATE TABLE IF NOT EXISTS login_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            login_time DATETIME NOT NULL,
            ip_address VARCHAR(50) NOT NULL,
            browser_fingerprint VARCHAR(255) DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 4. 创建服务商表
    $conn->query("
        CREATE TABLE IF NOT EXISTS service_providers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            status ENUM('available', 'locked', 'restricted') DEFAULT 'available',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_by INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 5. 创建服务商密钥表
    $conn->query("
        CREATE TABLE IF NOT EXISTS provider_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            provider_id INT NOT NULL,
            key_value VARCHAR(100) NOT NULL UNIQUE,
            expire_time DATETIME NOT NULL,
            created_time DATETIME NOT NULL,
            FOREIGN KEY (provider_id) REFERENCES service_providers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 6. 创建用户密钥表
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            key_value VARCHAR(100) NOT NULL UNIQUE,
            display_fields TEXT NOT NULL,
            message TEXT DEFAULT NULL,
            created_time DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            used_time DATETIME DEFAULT NULL,
            used_by_provider INT DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 7. 创建用户分组表
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 8. 创建用户-分组关联表
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_group_mapping (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            group_id INT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (group_id) REFERENCES user_groups(id),
            UNIQUE KEY user_group_unique (user_id, group_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 9. 创建系统设置表
    $conn->query("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(50) NOT NULL UNIQUE COMMENT '设置键',
            `value` VARCHAR(255) NOT NULL DEFAULT '1' COMMENT '设置值',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统全局设置表'
    ");
    
    // 插入默认系统设置（如果不存在）
    $settings = [
        ['key' => 'login_enabled', 'value' => '1'],
        ['key' => 'register_enabled', 'value' => '1'],
        ['key' => 'force_email_verify', 'value' => '0']
    ];
    
    foreach ($settings as $setting) {
        $stmt = $conn->prepare("INSERT IGNORE INTO system_settings (`key`, `value`) VALUES (?, ?)");
        $stmt->bind_param("ss", $setting['key'], $setting['value']);
        $stmt->execute();
        $stmt->close();
    }
    
    // 10. 创建阻止登录表
    $conn->query("
        CREATE TABLE IF NOT EXISTS blocked_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('ip', 'browser', 'email', 'qq', 'uid') NOT NULL,
            value VARCHAR(255) NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY type_value_unique (type, value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    $conn->close();
}

// 初始化系统表（首次运行时自动创建所有必需的表）
init_system_tables();

/**
 * 密码加密函数
 * @param string $password 明文密码
 * @return string 加密后的密码
 */
function encrypt_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * 密码验证函数
 * @param string $password 明文密码
 * @param string $hash 加密后的密码
 * @return bool 验证结果
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * 生成用户唯一标识符 UID
 * @return string 生成的 UID
 */
function generate_uid() {
    $prefix = "HCCWUD-";
    $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
    $random_str = substr(str_shuffle($chars), 0, 8);
    return $prefix . $random_str;
}

/**
 * 设置用户登录 Cookie
 * @param int $user_id 用户 ID
 * @param string $user_uid 用户 UID
 */
function set_user_cookie($user_id, $user_uid) {
    setcookie('user_id', $user_id, time() + COOKIE_EXPIRE, '/', '', false, true);
    setcookie('user_uid', $user_uid, time() + COOKIE_EXPIRE, '/', '', false, true);
    setcookie('login_time', date('Y-m-d H:i:s'), time() + COOKIE_EXPIRE, '/', '', false, true);
}

/**
 * 清除用户登录 Cookie
 */
function clear_user_cookie() {
    setcookie('user_id', '', time() - 3600, '/');
    setcookie('user_uid', '', time() - 3600, '/');
    setcookie('login_time', '', time() - 3600, '/');
}

/**
 * 获取当前登录用户信息
 * @return array|null 用户信息数组或 null
 */
function get_logged_in_user() {
    if (isset($_COOKIE['user_id']) && isset($_COOKIE['user_uid'])) {
        $user_id = intval($_COOKIE['user_id']);
        $user_uid = $_COOKIE['user_uid'];
        
        $conn = db_connect();
        $stmt = $conn->prepare("SELECT u.*, r.name as role_name FROM users u JOIN user_roles r ON u.role_id = r.id WHERE u.id = ? AND u.uid = ?");
        $stmt->bind_param("is", $user_id, $user_uid);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $stmt->close();
            $conn->close();
            return $user;
        }
        
        $stmt->close();
        $conn->close();
    }
    return null;
}

/**
 * 检查用户是否为管理员
 * @param array|null $user 用户信息数组
 * @return bool 是否为管理员
 */
function is_admin($user = null) {
    if (!is_array($user) || !isset($user['role_name'])) {
        return false;
    }
    return in_array($user['role_name'], ['general_admin', 'super_admin']);
}

/**
 * 检查用户是否为超级管理员
 * @param array|null $user 用户信息数组
 * @return bool 是否为超级管理员
 */
function is_super_admin($user = null) {
    if (!is_array($user) || !isset($user['role_name'])) {
        return false;
    }
    return $user['role_name'] == 'super_admin';
}

/**
 * 检查用户是否为服务商管理员
 * @param array $user 用户信息数组
 * @return bool 是否为服务商管理员
 */
function is_service_provider_admin($user) {
    if (!is_array($user) || !isset($user['id'])) {
        return false;
    }
    
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT id FROM service_providers WHERE id = (SELECT service_provider_id FROM users WHERE id = ?)");
    $stmt->bind_param("i", $user['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $is_sp_admin = $result->num_rows > 0;
    
    $stmt->close();
    $conn->close();
    return $is_sp_admin;
}

/**
 * 记录用户登录日志
 * @param int $user_id 用户 ID
 * @param string $ip IP 地址
 * @param string $fingerprint 浏览器指纹
 */
function log_login($user_id, $ip, $fingerprint) {
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, browser_fingerprint) VALUES (?, NOW(), ?, ?)");
    $stmt->bind_param("iss", $user_id, $ip, $fingerprint);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

/**
 * 获取浏览器指纹
 * @return string 浏览器指纹
 */
function get_browser_fingerprint() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? 'unknown';
    $language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown';
    return md5($user_agent . $accept . $language);
}

/**
 * 检查登录是否被阻止
 * @return bool 是否被阻止
 */
function is_login_blocked() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $fingerprint = get_browser_fingerprint();
    
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT id FROM blocked_items WHERE (type = 'ip' AND value = ?) OR (type = 'browser' AND value = ?)");
    $stmt->bind_param("ss", $ip, $fingerprint);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $is_blocked = $result->num_rows > 0;
    
    $stmt->close();
    $conn->close();
    return $is_blocked;
}

/**
 * 检查指定类型的值是否被阻止
 * @param string $type 类型
 * @param string $value 要检查的值
 * @return bool 是否被阻止
 */
function is_blocked($type, $value) {
    $conn = db_connect();
    $stmt = $conn->prepare("SELECT id FROM blocked_items WHERE type = ? AND value = ?");
    $stmt->bind_param("ss", $type, $value);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $is_blocked = $result->num_rows > 0;
    
    $stmt->close();
    $conn->close();
    return $is_blocked;
}

/**
 * 获取系统设置
 * @param string $key 设置键名
 * @return string 设置值
 */
function get_system_setting($key) {
    $conn = db_connect();
    // 明确指定表结构，确保value列存在
    $stmt = $conn->prepare("SELECT `value` FROM system_settings WHERE `key` = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $value = '1'; // 默认值
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $value = $row['value'];
    }
    
    $stmt->close();
    $conn->close();
    return $value;
}

/**
 * 发送邮件（简化版）
 * @param string $to 收件人邮箱
 * @param string $subject 邮件主题
 * @param string $message 邮件内容
 * @return bool 发送结果
 */
function send_email($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . SITE_NAME . '<noreply@example.com>' . "\r\n";
    return mail($to, $subject, $message, $headers);
}

/**
 * 确保用户已登录
 * @return array 用户信息数组
 */
function require_login() {
    $user = get_logged_in_user();
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    return $user;
}

/**
 * 确保用户是管理员
 * @return array 用户信息数组
 */
function require_admin() {
    $user = get_logged_in_user();
    if (!$user || !is_admin($user)) {
        if ($user) {
            header('Location: ../user_center.php');
        } else {
            header('Location: ../index.php');
        }
        exit;
    }
    return $user;
}

/**
 * 确保用户是超级管理员
 * @return array 用户信息数组
 */
function require_super_admin() {
    $user = get_logged_in_user();
    if (!$user || !is_super_admin($user)) {
        if ($user && is_admin($user)) {
            header('Location: index.php');
        } else if ($user) {
            header('Location: ../user_center.php');
        } else {
            header('Location: ../index.php');
        }
        exit;
    }
    return $user;
}

/**
 * 生成用户密钥
 * @param int $user_id 用户 ID
 * @param array $display_fields 要展示的字段
 * @param string $message 密钥留言
 * @return string 生成的密钥
 */
function generate_user_key($user_id, $display_fields, $message) {
    $key = bin2hex(random_bytes(16));
    $display_fields_json = json_encode($display_fields);
    
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO user_keys (user_id, key_value, display_fields, message, created_time, used) VALUES (?, ?, ?, ?, NOW(), 0)");
    $stmt->bind_param("isss", $user_id, $key, $display_fields_json, $message);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $key;
}

/**
 * 生成服务商密钥
 * @param int $provider_id 服务商 ID
 * @param string $expire_type 过期类型
 * @return string 生成的密钥
 */
function generate_provider_key($provider_id, $expire_type) {
    switch ($expire_type) {
        case '2h': $expire_seconds = 2 * 3600; break;
        case '6h': $expire_seconds = 6 * 3600; break;
        case '18d': $expire_seconds = 18 * 24 * 3600; break;
        case '30d': 
        default: $expire_seconds = 30 * 24 * 3600; break;
    }
    
    $expire_time = date('Y-m-d H:i:s', time() + $expire_seconds);
    $key = bin2hex(random_bytes(20));
    
    $conn = db_connect();
    $stmt = $conn->prepare("INSERT INTO provider_keys (provider_id, key_value, expire_time, created_time) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $provider_id, $key, $expire_time);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return $key;
}
?>