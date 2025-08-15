<?php
require_once 'db.php';

// 生成用户唯一标识 UID
function generate_uid() {
    $prefix = 'HCCWUD-';
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $uid_suffix = '';
    for ($i = 0; $i < 8; $i++) {
        $uid_suffix .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $prefix . $uid_suffix;
}

// 密码加密
function encrypt_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// 验证密码
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// 生成随机密钥
function generate_key($length) {
    return bin2hex(random_bytes($length / 2));
}

// 检查用户是否登录
function is_logged_in() {
    return isset($_COOKIE['uid']) && isset($_COOKIE['token']);
}

// 获取当前登录用户信息
// 替换原来的 get_current_user() 函数
function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    global $pdo;
    $uid = $_COOKIE['uid'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE uid = :uid");
    $stmt->bindParam(':uid', $uid);
    $stmt->execute();
    return $stmt->fetch();
}

// 同时需要修改调用这个函数的地方
function is_admin($user = null) {
    if ($user === null) {
        $user = get_logged_in_user(); // 这里也需要修改
    }
    return $user && ($user['identity'] == IDENTITY_ADMIN || $user['identity'] == IDENTITY_SUPER_ADMIN);
}

// 其他调用处也需要相应修改

// 检查用户是否为管理员

// 检查用户是否为超级管理员
function is_super_admin($user = null) {
    if ($user === null) {
        $user = get_current_user();
    }
    return $user && $user['identity'] == IDENTITY_SUPER_ADMIN;
}

// 检查用户是否为服务商
function is_service_provider($user = null) {
    if ($user === null) {
        $user = get_current_user();
    }
    return $user && ($user['identity'] == IDENTITY_SERVICE_PROVIDER || $user['identity'] == IDENTITY_SPECIAL_PROVIDER);
}

// 记录登录日志
function log_login($uid, $status, $ip) {
    global $pdo;
    $login_time = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("INSERT INTO login_records (uid, login_time, ip_address, status) 
                          VALUES (:uid, :login_time, :ip, :status)");
    $stmt->bindParam(':uid', $uid);
    $stmt->bindParam(':login_time', $login_time);
    $stmt->bindParam(':ip', $ip);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    
    // 更新用户最后登录时间
    $stmt = $pdo->prepare("UPDATE users SET last_login_time = :login_time WHERE uid = :uid");
    $stmt->bindParam(':login_time', $login_time);
    $stmt->bindParam(':uid', $uid);
    $stmt->execute();
}

// 获取客户端IP
function get_client_ip() {
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

// 设置用户Cookie
function set_user_cookie($uid, $token = null) {
    if ($token === null) {
        $token = generate_key(32);
    }
    setcookie('uid', $uid, time() + COOKIE_EXPIRE, COOKIE_PATH, COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_HTTPONLY);
    setcookie('token', $token, time() + COOKIE_EXPIRE, COOKIE_PATH, COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_HTTPONLY);
}

// 清除用户Cookie
function clear_user_cookie() {
    setcookie('uid', '', time() - 3600, COOKIE_PATH, COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_HTTPONLY);
    setcookie('token', '', time() - 3600, COOKIE_PATH, COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_HTTPONLY);
}

// 获取系统设置
function get_system_setting($name) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = :name");
    $stmt->bindParam(':name', $name);
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : null;
}

// 更新系统设置
function update_system_setting($name, $value) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = :value WHERE setting_name = :name");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':value', $value);
    return $stmt->execute();
}
?>
