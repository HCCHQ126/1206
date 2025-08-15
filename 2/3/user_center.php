<?php
require 'jc.php';

// 检查登录状态
checkAccess();
$user = getCurrentUser();

$error = '';
$success = '';

// 处理账号冻结
if (isset($_POST['freeze_account'])) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET status = 'frozen' WHERE id = :id");
    $stmt->bindParam(':id', $user['id']);
    $stmt->execute();
    $success = '账号已冻结';
    // 重新获取用户信息
    $user = getCurrentUser();
}

// 处理账号解冻
if (isset($_POST['unfreeze_account'])) {
    $email = trim($_POST['email']);
    $qq = trim($_POST['qq']);
    
    if ($email != $user['email'] || $qq != $user['qq']) {
        $error = '邮箱或QQ号验证失败';
    } else {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE users SET status = 'normal' WHERE id = :id");
        $stmt->bindParam(':id', $user['id']);
        $stmt->execute();
        $success = '账号已解冻';
        // 重新获取用户信息
        $user = getCurrentUser();
    }
}

// 处理信息修改
if (isset($_POST['update_info'])) {
    $name = trim($_POST['name']);
    
    if (empty($name)) {
        $error = '请输入姓名';
    } else {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE users SET name = :name WHERE id = :id");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':id', $user['id']);
        $stmt->execute();
        $success = '信息更新成功';
        // 重新获取用户信息
        $user = getCurrentUser();
    }
}

// 处理密码修改
if (isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (!verifyPassword($currentPassword, $user['password'])) {
        $error = '当前密码不正确';
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $error = '请输入新密码和确认密码';
    } elseif ($newPassword != $confirmPassword) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($newPassword) < 6) {
        $error = '密码长度至少为6位';
    } else {
        $hashedPassword = encryptPassword($newPassword);
        global $pdo;
        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':id', $user['id']);
        $stmt->execute();
        $success = '密码修改成功，请重新登录';
        // 登出用户
        setcookie('user_id', '', time() - 3600, '/');
        setcookie('user_token', '', time() - 3600, '/');
        // 延迟跳转，让用户看到成功信息
        echo "<script>setTimeout(function(){window.location.href='index.php';}, 2000);</script>";
    }
}

// 处理生成用户密钥
if (isset($_POST['generate_key'])) {
    $message = trim($_POST['message']);
    $displayFields = $_POST['display_fields'] ?? [];
    
    // 确保HCCWID被选中
    if (!in_array('uid', $displayFields)) {
        $displayFields[] = 'uid';
    }
    
    $keyValue = generateRandomKey();
    $createdTime = getShanghaiTime();
    
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO user_keys (user_id, key_value, message, display_fields, created_time) 
                          VALUES (:user_id, :key_value, :message, :display_fields, :created_time)");
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->bindParam(':key_value', $keyValue);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':display_fields', json_encode($displayFields));
    $stmt->bindParam(':created_time', $createdTime);
    $stmt->execute();
    
    $success = '用户密钥生成成功';
}

// 获取用户密钥列表
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM user_keys WHERE user_id = :user_id ORDER BY created_time DESC");
$stmt->bindParam(':user_id', $user['id']);
$stmt->execute();
$userKeys = $stmt->fetchAll();

// 获取密钥查询记录
$keyQueries = [];
foreach ($userKeys as $key) {
    $stmt = $pdo->prepare("SELECT kq.*, pk.provider_id, sp.provider_name 
                          FROM key_queries kq
                          JOIN provider_keys pk ON kq.provider_key_id = pk.id
                          JOIN service_providers sp ON pk.provider_id = sp.id
                          WHERE kq.user_key_id = :key_id");
    $stmt->bindParam(':key_id', $key['id']);
    $stmt->execute();
    $keyQueries[$key['id']] = $stmt->fetchAll();
}

// 获取登录记录
$stmt = $pdo->prepare("SELECT * FROM login_records WHERE user_id = :user_id AND login_success = 1 ORDER BY login_time DESC LIMIT 10");
$stmt->bindParam(':user_id', $user['id']);
$stmt->execute();
$loginRecords = $stmt->fetchAll();

// 登出功能
if (isset($_GET['logout'])) {
    setcookie('user_id', '', time() - 3600, '/');
    setcookie('user_token', '', time() - 3600, '/');
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户个人中心</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
        }
        .tab.active {
            border-bottom: 3px solid #4CAF50;
            color: #4CAF50;
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
        input, textarea {
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
        }
        button:hover {
            background-color: #45a049;
        }
        button.secondary {
            background-color: #f44336;
        }
        button.secondary:hover {
            background-color: #d32f2f;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .success {
            color: green;
            margin-bottom: 15px;
        }
        .info-item {
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .field-group {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>用户个人中心</h2>
            <button class="secondary" onclick="window.location.href='?logout'">退出登录</button>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="openTab(event, 'info')">个人信息</button>
            <button class="tab" onclick="openTab(event, 'edit')">信息修改</button>
            <button class="tab" onclick="openTab(event, 'password')">密码修改</button>
            <button class="tab" onclick="openTab(event, 'login-records')">登录记录</button>
            <button class="tab" onclick="openTab(event, 'user-keys')">用户密钥</button>
            <button class="tab" onclick="openTab(event, 'account-status')">账号状态</button>
        </div>
        
        <!-- 个人信息 -->
        <div id="info" class="tab-content active">
            <h3>个人信息</h3>
            <div class="info-item">
                <span class="info-label">用户ID：</span>
                <span><?php echo $user['uid']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">姓名：</span>
                <span><?php echo $user['name']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">邮箱：</span>
                <span><?php echo $user['email']; ?> <?php echo $user['email_verified'] ? '(已验证)' : '(未验证)'; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">QQ号：</span>
                <span><?php echo $user['qq']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">身份：</span>
                <span><?php 
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
                    echo $identityMap[$user['identity']];
                ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">账号状态：</span>
                <span><?php 
                    $statusMap = [
                        'normal' => '正常',
                        'locked' => '已锁定',
                        'restricted' => '受限制',
                        'frozen' => '已冻结'
                    ];
                    echo $statusMap[$user['status']];
                ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">注册时间：</span>
                <span><?php echo $user['register_time']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">最后登录：</span>
                <span><?php echo $user['last_login_time'] ?: '从未登录'; ?></span>
            </div>
        </div>
        
        <!-- 信息修改 -->
        <div id="edit" class="tab-content">
            <h3>修改个人信息</h3>
            <form method="post">
                <div class="form-group">
                    <label for="name">姓名</label>
                    <input type="text" id="name" name="name" value="<?php echo $user['name']; ?>" required>
                </div>
                <div class="form-group">
                    <label>邮箱</label>
                    <input type="email" value="<?php echo $user['email']; ?>" disabled>
                    <small>邮箱不可修改</small>
                </div>
                <div class="form-group">
                    <label>QQ号</label>
                    <input type="text" value="<?php echo $user['qq']; ?>" disabled>
                    <small>QQ号不可修改</small>
                </div>
                <button type="submit" name="update_info">保存修改</button>
            </form>
        </div>
        
        <!-- 密码修改 -->
        <div id="password" class="tab-content">
            <h3>修改密码</h3>
            <form method="post">
                <div class="form-group">
                    <label for="current_password">当前密码</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">确认新密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password">修改密码</button>
            </form>
        </div>
        
        <!-- 登录记录 -->
        <div id="login-records" class="tab-content">
            <h3>最近登录记录</h3>
            <table>
                <tr>
                    <th>登录时间</th>
                    <th>登录IP</th>
                </tr>
                <?php if (empty($loginRecords)): ?>
                    <tr>
                        <td colspan="2">暂无登录记录</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($loginRecords as $record): ?>
                        <tr>
                            <td><?php echo $record['login_time']; ?></td>
                            <td><?php echo $record['login_ip']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- 用户密钥 -->
        <div id="user-keys" class="tab-content">
            <h3>用户密钥管理</h3>
            <h4>生成新密钥</h4>
            <form method="post">
                <div class="form-group">
                    <label for="message">密钥留言（可选）</label>
                    <textarea id="message" name="message" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>选择向服务商展示的信息</label>
                    <div class="field-group">
                        <input type="checkbox" id="field_uid" name="display_fields[]" value="uid" checked disabled>
                        <label for="field_uid">用户ID（必须显示）</label>
                    </div>
                    <div class="field-group">
                        <input type="checkbox" id="field_name" name="display_fields[]" value="name" checked>
                        <label for="field_name">姓名</label>
                    </div>
                    <div class="field-group">
                        <input type="checkbox" id="field_email" name="display_fields[]" value="email">
                        <label for="field_email">邮箱</label>
                    </div>
                    <div class="field-group">
                        <input type="checkbox" id="field_identity" name="display_fields[]" value="identity" checked>
                        <label for="field_identity">用户身份</label>
                    </div>
                </div>
                <button type="submit" name="generate_key">生成密钥</button>
            </form>
            
            <h4>我的密钥列表</h4>
            <table>
                <tr>
                    <th>密钥值</th>
                    <th>创建时间</th>
                    <th>状态</th>
                    <th>查询记录</th>
                </tr>
                <?php if (empty($userKeys)): ?>
                    <tr>
                        <td colspan="4">暂无密钥</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($userKeys as $key): ?>
                        <tr>
                            <td><?php echo $key['key_value']; ?></td>
                            <td><?php echo $key['created_time']; ?></td>
                            <td><?php echo $key['used'] ? '已使用' : '未使用'; ?></td>
                            <td>
                                <?php if (!empty($keyQueries[$key['id']])): ?>
                                    <ul>
                                        <?php foreach ($keyQueries[$key['id']] as $query): ?>
                                            <li><?php echo $query['provider_name']; ?> - <?php echo $query['query_time']; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    未被查询
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- 账号状态 -->
        <div id="account-status" class="tab-content">
            <h3>账号状态管理</h3>
            <p>当前账号状态：<?php 
                $statusMap = [
                    'normal' => '正常',
                    'locked' => '已锁定',
                    'restricted' => '受限制',
                    'frozen' => '已冻结'
                ];
                echo $statusMap[$user['status']];
            ?></p>
            
            <?php if ($user['status'] != 'frozen'): ?>
                <form method="post" onsubmit="return confirm('确定要冻结账号吗？冻结后需要验证邮箱和QQ号才能解冻。');">
                    <button type="submit" name="freeze_account" class="secondary">冻结账号</button>
                </form>
            <?php else: ?>
                <h4>解冻账号</h4>
                <form method="post">
                    <div class="form-group">
                        <label for="email">邮箱</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="qq">QQ号</label>
                        <input type="text" id="qq" name="qq" required>
                    </div>
                    <button type="submit" name="unfreeze_account">解冻账号</button>
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
        
        // 确保HCCWID始终被选中
        document.getElementById('field_uid').checked = true;
    </script>
</body>
</html>
