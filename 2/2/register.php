<?php
require_once 'functions.php';

// 检查注册功能是否启用
if (get_system_setting('register_enabled') != '1') {
    die("注册功能已关闭");
}

// 如果用户已登录，跳转到相应页面
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $qq = trim($_POST['qq'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // 验证表单数据
    if (empty($name) || empty($email) || empty($qq) || empty($password) || empty($confirm_password)) {
        $error = '请填写所有必填字段';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } elseif ($password != $confirm_password) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少为6位';
    } else {
        global $pdo;
        
        // 检查邮箱是否已被注册
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->fetch()) {
            $error = '该邮箱已被注册';
        } else {
            // 生成唯一UID
            do {
                $uid = generate_uid();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE uid = :uid");
                $stmt->bindParam(':uid', $uid);
                $stmt->execute();
            } while ($stmt->fetch());
            
            // 加密密码
            $password_hash = encrypt_password($password);
            
            // 获取当前上海时间
            $register_time = date('Y-m-d H:i:s');
            
            // 插入用户数据
            $stmt = $pdo->prepare("INSERT INTO users (uid, name, email, qq, password, register_time) 
                                  VALUES (:uid, :name, :email, :qq, :password, :register_time)");
            $stmt->bindParam(':uid', $uid);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':qq', $qq);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':register_time', $register_time);
            
            if ($stmt->execute()) {
                // 记录登录日志
                log_login($uid, 'success', get_client_ip());
                
                // 设置用户Cookie
                set_user_cookie($uid);
                
                $success = '注册成功，正在跳转到个人中心...';
                header('Refresh: 2; URL=user_center.php');
            } else {
                $error = '注册失败，请稍后再试';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <nav>
                <ul>
                    <li><a href="login.php">登录</a></li>
                    <li><a href="register.php" class="active">注册</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="container">
        <div class="form-container">
            <h2 class="form-title">用户注册</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php else: ?>
                <form method="post">
                    <div class="form-group">
                        <label for="name">姓名</label>
                        <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">邮箱</label>
                        <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="qq">QQ号</label>
                        <input type="text" id="qq" name="qq" class="form-control" required value="<?php echo htmlspecialchars($_POST['qq'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">密码</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">确认密码</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-block">注册</button>
                    </div>
                </form>
                
                <div class="text-center mt-2">
                    已有账号？<a href="login.php">立即登录</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - 版权所有</p>
        </div>
    </footer>
</body>
</html>
