<?php
require 'jc.php';

// 检查登录是否启用
if (get_system_setting($pdo, 'enable_login') != '1') {
    die("登录功能已关闭");
}

// 检查是否已被禁止登录
if (is_blocked($pdo)) {
    die("您的IP或设备已被禁止登录");
}

// 如果已登录，跳转到相应页面
if (is_logged_in() && validate_user($pdo)) {
    $user = get_current_in_user($pdo);
    if (is_admin($user)) {
        header("Location: admin/dashboard.php");
    } elseif (is_service_provider($pdo, $user)) {
        header("Location: service_center.php");
    } else {
        header("Location: user_center.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "邮箱和密码都是必填的";
    } else {
        // 查找用户
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 验证用户
        $login_success = false;
        if ($user) {
            // 检查账号状态
            if ($user['status'] == 'locked' || $user['status'] == 'restricted' || $user['status'] == 'frozen') {
                $error = "账号状态异常，无法登录";
            } elseif (password_verify($password, $user['password_hash'])) {
                // 密码正确
                $login_success = true;
                
                // 更新最后登录时间和IP
                $stmt = $pdo->prepare("UPDATE users SET last_login_time = NOW(), last_login_ip = :ip WHERE uid = :uid");
                $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                $stmt->bindParam(':uid', $user['uid']);
                $stmt->execute();
                
                // 设置Cookie
                setcookie('uid', $user['uid'], time() + 3600 * 24 * 7, '/');
                setcookie('auth_token', md5($user['uid'] . $user['password_hash']), time() + 3600 * 24 * 7, '/');
                
                // 跳转到相应页面
                if (is_admin($user)) {
                    header("Location: admin/dashboard.php");
                } elseif (is_service_provider($pdo, $user)) {
                    header("Location: service_center.php");
                } else {
                    header("Location: user_center.php");
                }
                exit;
            } else {
                $error = "邮箱或密码错误";
            }
        } else {
            $error = "邮箱或密码错误";
        }
        
        // 记录登录日志
        log_login($pdo, $user ? $user['uid'] : $email, $login_success);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户登录</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            opacity: 0.8;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .toggle-link {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <h2>用户登录</h2>
    
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="post">
        <div class="form-group">
            <label for="email">邮箱:</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="password">密码:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit">登录</button>
    </form>
    
    <div class="toggle-link">
        还没有账号？<a href="register.php">注册</a>
    </div>
</body>
</html>
