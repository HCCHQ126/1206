<?php
// 设置时区为上海
date_default_timezone_set('Asia/Shanghai');

// 数据库配置
$host = 'localhost';
$dbname = 'asz'; // 请替换为您的数据库名
$username = 'asz';      // 请替换为您的数据库用户名
$password = 'asz';          // 请替换为您的数据库密码

// 数据库连接
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 生成唯一的用户ID (HCCWUD-前缀)
function generate_uid() {
    $prefix = 'HCCWUD-';
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $random_str = '';
    for ($i = 0; $i < 8; $i++) {
        $random_str .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $prefix . $random_str;
}

// 检查用户是否已登录
function is_logged_in() {
    return isset($_COOKIE['uid']) && isset($_COOKIE['auth_token']);
}

// 验证用户身份
function validate_user($pdo) {
    if (!is_logged_in()) {
        return false;
    }
    
    $uid = $_COOKIE['uid'];
    $auth_token = $_COOKIE['auth_token'];
    
    // 简单验证 - 实际应用中应使用更安全的验证方式
    $stmt = $pdo->prepare("SELECT uid FROM users WHERE uid = :uid AND status != 'frozen'");
    $stmt->bindParam(':uid', $uid);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
}

// 获取当前登录用户信息
function get_current_in_user($pdo) {
    if (!is_logged_in()) {
        return null;
    }
    
    $uid = $_COOKIE['uid'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE uid = :uid");
    $stmt->bindParam(':uid', $uid);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 检查用户是否为管理员
function is_admin($user) {
    return $user && ($user['identity'] == 'admin' || $user['identity'] == 'super_admin');
}

// 检查用户是否为超级管理员
function is_super_admin($user) {
    return $user && $user['identity'] == 'super_admin';
}

// 检查用户是否为服务商
function is_service_provider($pdo, $user) {
    if (!$user) return false;
    
    $stmt = $pdo->prepare("SELECT * FROM service_provider_managers WHERE uid = :uid");
    $stmt->bindParam(':uid', $user['uid']);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;
}

// 记录登录日志
function log_login($pdo, $uid, $success) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $fingerprint = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $pdo->prepare("INSERT INTO login_records (uid, login_time, login_ip, browser_fingerprint, success) 
                          VALUES (:uid, NOW(), :ip, :fingerprint, :success)");
    $stmt->bindParam(':uid', $uid);
    $stmt->bindParam(':ip', $ip);
    $stmt->bindParam(':fingerprint', $fingerprint);
    $stmt->bindParam(':success', $success, PDO::PARAM_BOOL);
    $stmt->execute();
}

// 生成随机密钥
function generate_api_key() {
    return bin2hex(random_bytes(32));
}

// 检查是否被禁止登录
function is_blocked($pdo) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $fingerprint = $_SERVER['HTTP_USER_AGENT'];
    
    // 检查IP是否被禁止
    $stmt = $pdo->prepare("SELECT * FROM blocked_entries WHERE entry_type = 'ip' AND entry_value = :value");
    $stmt->bindParam(':value', $ip);
    $stmt->execute();
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        return true;
    }
    
    // 检查浏览器指纹是否被禁止
    $stmt = $pdo->prepare("SELECT * FROM blocked_entries WHERE entry_type = 'browser_fingerprint' AND entry_value = :value");
    $stmt->bindParam(':value', $fingerprint);
    $stmt->execute();
    if ($stmt->fetch(PDO::FETCH_ASSOC)) {
        return true;
    }
    
    return false;
}

// 获取系统设置
function get_system_setting($pdo, $setting_name) {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = :name");
    $stmt->bindParam(':name', $setting_name);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result ? $result['setting_value'] : null;
}
?>
