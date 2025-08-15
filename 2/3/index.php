<?php
require 'jc.php';

// 检查登录/注册功能是否启用
$loginEnabled = getSystemSetting('login_enabled') == 1;
$registerEnabled = getSystemSetting('register_enabled') == 1;

// 如果已登录，根据用户身份跳转
if (isLoggedIn()) {
    $user = getCurrentUser();
    if (isAdmin($user)) {
        redirect('admin/admin_center.php');
    } elseif (isServiceProvider($user)) {
        redirect('service_provider.php');
    } else {
        redirect('user_center.php');
    }
}

$error = '';
$success = '';

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if (!$loginEnabled) {
        $error = '登录功能已关闭';
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = '请填写邮箱和密码';
        } else {
            global $pdo;
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = '邮箱或密码错误';
                logLogin(0, getUserIP(), false, $_SERVER['HTTP_USER_AGENT']);
            } elseif ($user['status'] != 'normal') {
                $statusText = [
                    'locked' => '已锁定',
                    'restricted' => '受限制',
                    'frozen' => '已冻结'
                ];
                $error = '账号' . $statusText[$user['status']];
                logLogin($user['id'], getUserIP(), false, $_SERVER['HTTP_USER_AGENT']);
            } elseif (verifyPassword($password, $user['password'])) {
                // 检查是否需要强制邮箱验证
                $forceVerify = getSystemSetting('force_email_verify') == 1;
                if ($forceVerify && !$user['email_verified']) {
                    $error = '请先验证邮箱';
                    logLogin($user['id'], getUserIP(), false, $_SERVER['HTTP_USER_AGENT']);
                } else {
                    // 登录成功
                    generateToken($user['id']);
                    
                    // 更新最后登录时间和IP
                    $stmt = $pdo->prepare("UPDATE users SET last_login_time = :time, last_login_ip = :ip WHERE id = :id");
                    $stmt->bindParam(':time', getShanghaiTime());
                    $stmt->bindParam(':ip', getUserIP());
                    $stmt->bindParam(':id', $user['id']);
                    $stmt->execute();
                    
                    // 记录登录日志
                    logLogin($user['id'], getUserIP(), true, $_SERVER['HTTP_USER_AGENT']);
                    
                    // 根据用户身份跳转
                    if (isAdmin($user)) {
                        redirect('admin/admin_center.php');
                    } elseif (isServiceProvider($user)) {
                        redirect('service_provider.php');
                    } else {
                        redirect('user_center.php');
                    }
                }
            } else {
                $error = '邮箱或密码错误';
                logLogin($user['id'], getUserIP(), false, $_SERVER['HTTP_USER_AGENT']);
            }
        }
    }
}

// 处理注册请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    if (!$registerEnabled) {
        $error = '注册功能已关闭';
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $qq = trim($_POST['qq']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // 验证表单
        if (empty($name) || empty($email) || empty($qq) || empty($password) || empty($confirmPassword)) {
            $error = '请填写所有字段';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '请输入有效的邮箱地址';
        } elseif ($password != $confirmPassword) {
            $error = '两次输入的密码不一致';
        } elseif (strlen($password) < 6) {
            $error = '密码长度至少为6位';
        } elseif (emailExists($email)) {
            $error = '该邮箱已被注册';
        } elseif (qqExists($qq)) {
            $error = '该QQ号已被注册';
        } else {
            // 生成用户UID
            $uid = generateUID();
            // 确保UID唯一
            global $pdo;
            while (true) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE uid = :uid");
                $stmt->bindParam(':uid', $uid);
                $stmt->execute();
                if (!$stmt->fetch()) {
                    break;
                }
                $uid = generateUID();
            }
            
            // 加密密码
            $hashedPassword = encryptPassword($password);
            $registerTime = getShanghaiTime();
            
            // 插入用户记录
            try {
                $stmt = $pdo->prepare("INSERT INTO users (uid, name, email, qq, password, register_time) 
                                      VALUES (:uid, :name, :email, :qq, :password, :register_time)");
                $stmt->bindParam(':uid', $uid);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':qq', $qq);
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':register_time', $registerTime);
                $stmt->execute();
                
                $userId = $pdo->lastInsertId();
                
                // 检查是否需要发送验证邮件
                $forceVerify = getSystemSetting('force_email_verify') == 1;
                if ($forceVerify) {
                    $verifyCode = rand(100000, 999999);
                    // 这里应该将验证码存储到数据库
                    sendVerificationEmail($email, $verifyCode);
                    $success = '注册成功，请查收邮件验证您的邮箱';
                } else {
                    // 自动验证邮箱
                    $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE id = :id");
                    $stmt->bindParam(':id', $userId);
                    $stmt->execute();
                    $success = '注册成功，请登录';
                }
            } catch (PDOException $e) {
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
    <title>用户登录与注册</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }
        .tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            background-color: #e0e0e0;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }
        .tab.active {
            background-color: #4CAF50;
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
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
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        button:hover {
            background-color: #45a049;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .success {
            color: green;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>用户系统</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="openTab(event, 'login')">登录</button>
            <button class="tab" onclick="openTab(event, 'register')">注册</button>
        </div>
        
        <div id="login" class="tab-content active">
            <h3>用户登录</h3>
            <?php if (!$loginEnabled): ?>
                <div class="error">登录功能已关闭</div>
            <?php else: ?>
                <form method="post">
                    <div class="form-group">
                        <label for="login_email">邮箱</label>
                        <input type="email" id="login_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="login_password">密码</label>
                        <input type="password" id="login_password" name="password" required>
                    </div>
                    <button type="submit" name="login">登录</button>
                </form>
            <?php endif; ?>
        </div>
        
        <div id="register" class="tab-content">
            <h3>用户注册</h3>
            <?php if (!$registerEnabled): ?>
                <div class="error">注册功能已关闭</div>
            <?php else: ?>
                <form method="post">
                    <div class="form-group">
                        <label for="register_name">姓名</label>
                        <input type="text" id="register_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="register_email">邮箱</label>
                        <input type="email" id="register_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="register_qq">QQ号</label>
                        <input type="text" id="register_qq" name="qq" required>
                    </div>
                    <div class="form-group">
                        <label for="register_password">密码</label>
                        <input type="password" id="register_password" name="password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="register_confirm_password">确认密码</label>
                        <input type="password" id="register_confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" name="register">注册</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("tab");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>
