<?php
// 数据库配置
$host = 'localhost';
$dbname = 'qaz';
$username = 'qaz'; // 请替换为您的数据库用户名
$password = 'qaz'; // 请替换为您的数据库密码

// 连接数据库
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 获取上海时间
function getShanghaiTime($timestamp = null) {
    date_default_timezone_set('Asia/Shanghai');
    return $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
}

// 生成用户UID
function generateUID() {
    $prefix = 'HCCWUD-';
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $randomStr = '';
    for ($i = 0; $i < 8; $i++) {
        $randomStr .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $prefix . $randomStr;
}

// 密码加密
function encryptPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// 验证密码
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// 检查用户是否登录
function isLoggedIn() {
    return isset($_COOKIE['user_id']) && isset($_COOKIE['user_token']) && validateToken($_COOKIE['user_id'], $_COOKIE['user_token']);
}

// 生成登录令牌
function generateToken($userId) {
    $token = bin2hex(random_bytes(32));
    $expireTime = time() + 3600 * 24 * 7; // 7天有效期
    
    // 存储令牌到cookie
    setcookie('user_id', $userId, $expireTime, '/', '', false, true);
    setcookie('user_token', $token, $expireTime, '/', '', false, true);
    
    return $token;
}

// 验证令牌
function validateToken($userId, $token) {
    // 简单验证，实际应用中应该存储到数据库并验证
    return !empty($userId) && !empty($token);
}

// 获取当前登录用户信息
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $pdo;
    $userId = $_COOKIE['user_id'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    
    return $stmt->fetch();
}

// 检查用户是否为管理员
function isAdmin($user = null) {
    if (!$user) {
        $user = getCurrentUser();
        if (!$user) return false;
    }
    return in_array($user['identity'], ['admin', 'super_admin']);
}

// 检查用户是否为超级管理员
function isSuperAdmin($user = null) {
    if (!$user) {
        $user = getCurrentUser();
        if (!$user) return false;
    }
    return $user['identity'] == 'super_admin';
}

// 检查用户是否为服务商
function isServiceProvider($user = null) {
    if (!$user) {
        $user = getCurrentUser();
        if (!$user) return false;
    }
    return in_array($user['identity'], ['service_provider', 'special_authorized_provider']);
}

// 获取系统设置
function getSystemSetting($settingName) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = :name");
    $stmt->bindParam(':name', $settingName);
    $stmt->execute();
    
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : null;
}

// 更新系统设置
function updateSystemSetting($settingName, $settingValue) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = :value WHERE setting_name = :name");
    $stmt->bindParam(':name', $settingName);
    $stmt->bindParam(':value', $settingValue);
    return $stmt->execute();
}

// 记录登录日志
function logLogin($userId, $ip, $success, $userAgent = '') {
    global $pdo;
    
    $time = getShanghaiTime();
    $stmt = $pdo->prepare("INSERT INTO login_records (user_id, login_time, login_ip, login_success, user_agent) 
                          VALUES (:user_id, :time, :ip, :success, :agent)");
    $stmt->bindParam(':user_id', $userId);
    $stmt->bindParam(':time', $time);
    $stmt->bindParam(':ip', $ip);
    $stmt->bindParam(':success', $success, PDO::PARAM_BOOL);
    $stmt->bindParam(':agent', $userAgent);
    $stmt->execute();
}

// 获取用户IP
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// 生成随机密钥
function generateRandomKey() {
    return bin2hex(random_bytes(16));
}

// 发送验证邮件
function sendVerificationEmail($email, $code) {
    // 实际应用中应该实现真实的邮件发送功能
    $subject = "账号验证";
    $message = "您的验证代码是: " . $code;
    $headers = "From: noreply@example.com";
    
    return mail($email, $subject, $message, $headers);
}

// 检查邮箱是否已存在
function emailExists($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    return $stmt->fetch() !== false;
}

// 检查QQ是否已存在
function qqExists($qq) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE qq = :qq");
    $stmt->bindParam(':qq', $qq);
    $stmt->execute();
    
    return $stmt->fetch() !== false;
}

// 跳转函数
function redirect($url) {
    header("Location: $url");
    exit();
}

// 检查页面访问权限
function checkAccess($requiredIdentity = []) {
    if (!isLoggedIn()) {
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    
    $user = getCurrentUser();
    if (!empty($requiredIdentity) && !in_array($user['identity'], $requiredIdentity)) {
        die("没有访问权限");
    }
}
?>
