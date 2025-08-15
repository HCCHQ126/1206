<?php
require 'jc.php';

// 检查注册是否启用
if (get_system_setting($pdo, 'enable_registration') != '1') {
    die("注册功能已关闭");
}

// 检查是否已被禁止登录
if (is_blocked($pdo)) {
    die("您的IP或设备已被禁止注册/登录");
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $qq = trim($_POST['qq'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // 验证输入
    if (empty($name) || empty($email) || empty($qq) || empty($password) || empty($confirm_password)) {
        $error = "所有字段都是必填的";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "请输入有效的邮箱地址";
    } elseif ($password != $confirm_password) {
        $error = "两次输入的密码不一致";
    } elseif (strlen($password) < 8) {
        $error = "密码长度至少为8位";
    } else {
        // 检查邮箱是否已被注册
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $error = "该邮箱已被注册";
        } else {
            // 检查QQ是否已被注册
            $stmt = $pdo->prepare("SELECT * FROM users WHERE qq = :qq");
            $stmt->bindParam(':qq', $qq);
            $stmt->execute();
            
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $error = "该QQ号已被注册";
            } else {
                // 生成用户ID
                $uid = generate_uid();
                
                // 哈希密码
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // 检查是否需要强制邮箱验证
                $email_verified = get_system_setting($pdo, 'force_email_verification') == '1' ? 0 : 1;
                
                // 插入用户记录
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (uid, name, email, qq, password_hash, register_time, email_verified) 
                                          VALUES (:uid, :name, :email, :qq, :password_hash, NOW(), :email_verified)");
                    $stmt->bindParam(':uid', $uid);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':qq', $qq);
                    $stmt->bindParam(':password_hash', $password_hash);
                    $stmt->bindParam(':email_verified', $email_verified, PDO::PARAM_INT);
                    $stmt->execute();
                    
                    $success = true;
                    
                    // 如果需要邮箱验证，这里可以添加发送验证邮件的代码
                } catch(PDOException $e) {
                    $error = "注册失败: " . $e->getMessage();
                }
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
    <title>用户注册</title>
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
        .success {
            color: green;
            margin-bottom: 15px;
        }
        .toggle-link {
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <h2>用户注册</h2>
    
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success">注册成功！您可以<a href="login.php">登录</a>了。</div>
    <?php else: ?>
        <form method="post">
            <div class="form-group">
                <label for="name">姓名:</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">邮箱:</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="qq">QQ号:</label>
                <input type="text" id="qq" name="qq" required>
            </div>
            
            <div class="form-group">
                <label for="password">密码:</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">确认密码:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit">注册</button>
        </form>
        
        <div class="toggle-link">
            已有账号？<a href="login.php">登录</a>
        </div>
    <?php endif; ?>
</body>
</html>
