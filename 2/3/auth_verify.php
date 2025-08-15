<?php
require 'jc.php';

// 检查授权验证功能是否启用
$authEnabled = getSystemSetting('auth_enabled') == 1;
if (!$authEnabled) {
    die("授权验证功能已关闭");
}

$error = '';
$userInfo = null;

// 处理验证请求
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userKey = trim($_POST['user_key']);
    $providerKey = trim($_POST['provider_key']);
    
    if (empty($userKey) || empty($providerKey)) {
        $error = '请输入用户密钥和服务商密钥';
    } else {
        global $pdo;
        
        // 验证服务商密钥
        $stmt = $pdo->prepare("SELECT pk.*, sp.provider_name 
                              FROM provider_keys pk
                              JOIN service_providers sp ON pk.provider_id = sp.id
                              WHERE pk.key_value = :key_value AND sp.status = 'available'");
        $stmt->bindParam(':key_value', $providerKey);
        $stmt->execute();
        $providerKeyInfo = $stmt->fetch();
        
        if (!$providerKeyInfo) {
            $error = '无效的服务商密钥或服务商不可用';
        } else {
            // 检查服务商密钥是否过期
            $now = getShanghaiTime();
            if (strtotime($providerKeyInfo['expire_time']) < strtotime($now)) {
                $error = '服务商密钥已过期';
            } else {
                // 验证用户密钥
                $stmt = $pdo->prepare("SELECT uk.*, u.* 
                                      FROM user_keys uk
                                      JOIN users u ON uk.user_id = u.id
                                      WHERE uk.key_value = :key_value AND u.status = 'normal'");
                $stmt->bindParam(':key_value', $userKey);
                $stmt->execute();
                $userKeyInfo = $stmt->fetch();
                
                if (!$userKeyInfo) {
                    $error = '无效的用户密钥或用户账号异常';
                } else {
                    // 检查用户密钥是否已使用
                    if ($userKeyInfo['used']) {
                        $error = '用户密钥已被使用（用户密钥只能使用一次）';
                    } else {
                        // 记录查询
                        $stmt = $pdo->prepare("INSERT INTO key_queries (user_key_id, provider_key_id, query_time, query_ip) 
                                              VALUES (:user_key_id, :provider_key_id, :query_time, :query_ip)");
                        $stmt->bindParam(':user_key_id', $userKeyInfo['id']);
                        $stmt->bindParam(':provider_key_id', $providerKeyInfo['id']);
                        $stmt->bindParam(':query_time', $now);
                        $stmt->bindParam(':query_ip', getUserIP());
                        $stmt->execute();
                        
                        // 标记用户密钥为已使用
                        $stmt = $pdo->prepare("UPDATE user_keys SET used = 1 WHERE id = :id");
                        $stmt->bindParam(':id', $userKeyInfo['id']);
                        $stmt->execute();
                        
                        // 准备要显示的用户信息
                        $displayFields = json_decode($userKeyInfo['display_fields'], true);
                        $identityMap = [
                            'user' => '用户',
                            'developer' => '开发者',
                            'authorized_developer' => '授权开发者',
                            'manage_developer' => '管理开发者',
                            'service_provider' => '服务商',
                            'special_authorized_provider' => '特别授权服务商',
                            'admin' => '一般管理员',
                            'super_admin' => '超级管理员'
                        ];
                        
                        $userInfo = [
                            'provider_name' => $providerKeyInfo['provider_name'],
                            'display_fields' => $displayFields,
                            'data' => [
                                'uid' => $userKeyInfo['uid'],
                                'name' => $userKeyInfo['name'],
                                'email' => $userKeyInfo['email'],
                                'identity' => $identityMap[$userKeyInfo['identity']],
                                'register_time' => $userKeyInfo['register_time']
                            ],
                            'message' => $userKeyInfo['message'],
                            'verify_time' => $now
                        ];
                    }
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
    <title>授权验证</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            text-align: center;
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
            text-align: center;
        }
        .success {
            color: green;
            margin-bottom: 15px;
            text-align: center;
        }
        .result {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }
        .info-item {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        .message {
            margin-top: 15px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>授权验证</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($userInfo): ?>
            <div class="success">验证成功</div>
            <div class="result">
                <div class="info-item">
                    <span class="info-label">验证时间：</span>
                    <span><?php echo $userInfo['verify_time']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">服务商：</span>
                    <span><?php echo $userInfo['provider_name']; ?></span>
                </div>
                <hr>
                <h3>用户信息</h3>
                <?php if (in_array('uid', $userInfo['display_fields'])): ?>
                    <div class="info-item">
                        <span class="info-label">用户ID：</span>
                        <span><?php echo $userInfo['data']['uid']; ?></span>
                    </div>
                <?php endif; ?>
                <?php if (in_array('name', $userInfo['display_fields'])): ?>
                    <div class="info-item">
                        <span class="info-label">姓名：</span>
                        <span><?php echo $userInfo['data']['name']; ?></span>
                    </div>
                <?php endif; ?>
                <?php if (in_array('email', $userInfo['display_fields'])): ?>
                    <div class="info-item">
                        <span class="info-label">邮箱：</span>
                        <span><?php echo $userInfo['data']['email']; ?></span>
                    </div>
                <?php endif; ?>
                <?php if (in_array('identity', $userInfo['display_fields'])): ?>
                    <div class="info-item">
                        <span class="info-label">身份：</span>
                        <span><?php echo $userInfo['data']['identity']; ?></span>
                    </div>
                <?php endif; ?>
                <?php if (in_array('register_time', $userInfo['display_fields'])): ?>
                    <div class="info-item">
                        <span class="info-label">注册时间：</span>
                        <span><?php echo $userInfo['data']['register_time']; ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($userInfo['message'])): ?>
                    <div class="message">
                        <strong>用户留言：</strong><?php echo $userInfo['message']; ?>
                    </div>
                <?php endif; ?>
            </div>
            <button onclick="window.location.reload()">验证新的密钥</button>
        <?php else: ?>
            <form method="post">
                <div class="form-group">
                    <label for="user_key">用户密钥</label>
                    <input type="text" id="user_key" name="user_key" placeholder="请输入用户提供的密钥" required>
                </div>
                <div class="form-group">
                    <label for="provider_key">服务商密钥</label>
                    <input type="text" id="provider_key" name="provider_key" placeholder="请输入服务商密钥" required>
                </div>
                <button type="submit">验证</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
