<?php
require_once 'functions.php';

// 检查登录功能是否启用
if (get_system_setting('login_enabled') != '1') {
    die("登录功能已关闭");
}

// 如果用户已登录，跳转到相应页面
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = '请输入邮箱和密码';
    } else {
        global $pdo;
        
        // 查询用户
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = '邮箱或密码不正确';
            // 记录失败登录尝试
            log_login('unknown', 'failed', get_client_ip());
        } elseif (!verify_password($password, $user['password'])) {
            $error = '邮箱或密码不正确';
            // 记录失败登录尝试
            log_login($user['uid'], 'failed', get_client_ip());
        } elseif ($user['status'] == STATUS_LOCKED || $user['status'] == STATUS_FROZEN) {
            $error = '账号已被锁定或冻结，请联系管理员';
            // 记录失败登录尝试
            log_login($user['uid'], 'failed', get_client_ip());
        } else {
            // 登录成功
            log_login($user['uid'], 'success', get_client_ip());
            
            // 设置用户Cookie
            set_user_cookie($user['uid']);
            
            // 根据用户身份跳转到相应页面
            if (is_admin($user)) {
                header('Location: admin_manage.php');
            } elseif (is_service_provider($user)) {
                header('Location: service_provider.php');
            } else {
                header('Location: user_center.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <nav>
                <ul>
                    <li><a href="login.php" class="active">登录</a></li>
                    <li><a href="register.php">注册</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="container">
        <div class="form-container">
            <h2 class="form-title">用户登录</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="email">邮箱</label>
                    <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-block">登录</button>
                </div>
            </form>
            
            <div class="text-center mt-2">
                还没有账号？<a href="register.php">立即注册</a>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - 版权所有</p>
        </div>
    </footer>
</body>
</html>
