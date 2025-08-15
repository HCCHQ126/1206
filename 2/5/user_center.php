<?php
require 'jc.php';

// 检查登录状态
if (!is_logged_in() || !validate_user($pdo)) {
    header("Location: login.php");
    exit;
}

$user = get_current_in_user($pdo);
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 处理个人信息更新
    if (isset($_POST['update_info'])) {
        $name = trim($_POST['name'] ?? '');
        $qq = trim($_POST['qq'] ?? '');
        
        if (empty($name) || empty($qq)) {
            $error = "姓名和QQ号不能为空";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = :name, qq = :qq WHERE uid = :uid");
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':qq', $qq);
                $stmt->bindParam(':uid', $user['uid']);
                $stmt->execute();
                
                $success = "个人信息更新成功";
                // 刷新用户信息
                $user = get_current_in_user($pdo);
            } catch(PDOException $e) {
                $error = "更新失败: " . $e->getMessage();
            }
        }
    }
    
    // 处理密码更新
    if (isset($_POST['update_password'])) {
        $old_password = $_POST['old_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
            $error = "所有字段都是必填的";
        } elseif (!password_verify($old_password, $user['password_hash'])) {
            $error = "原密码不正确";
        } elseif ($new_password != $confirm_password) {
            $error = "两次输入的新密码不一致";
        } elseif (strlen($new_password) < 8) {
            $error = "新密码长度至少为8位";
        } else {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE uid = :uid");
                $stmt->bindParam(':password_hash', $new_password_hash);
                $stmt->bindParam(':uid', $user['uid']);
                $stmt->execute();
                
                // 更新Cookie中的认证令牌
                setcookie('auth_token', md5($user['uid'] . $new_password_hash), time() + 3600 * 24 * 7, '/');
                
                $success = "密码更新成功，请重新登录";
                // 登出用户
                setcookie('uid', '', time() - 3600, '/');
                setcookie('auth_token', '', time() - 3600, '/');
                header("Location: login.php");
                exit;
            } catch(PDOException $e) {
                $error = "密码更新失败: " . $e->getMessage();
            }
        }
    }
    
    // 处理账号冻结
    if (isset($_POST['freeze_account'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = 'frozen', frozen_by_self = 1 WHERE uid = :uid");
            $stmt->bindParam(':uid', $user['uid']);
            $stmt->execute();
            
            $success = "账号已冻结";
            // 登出用户
            setcookie('uid', '', time() - 3600, '/');
            setcookie('auth_token', '', time() - 3600, '/');
            header("Location: login.php");
            exit;
        } catch(PDOException $e) {
            $error = "冻结失败: " . $e->getMessage();
        }
    }
    
    // 处理用户密钥生成
    if (isset($_POST['generate_user_key'])) {
        $comment = trim($_POST['key_comment'] ?? '');
        $expiry_option = $_POST['expiry_option'] ?? '2h';
        
        // 根据选择的选项计算过期时间
        switch ($expiry_option) {
            case '2h':
                $expiry_time = date('Y-m-d H:i:s', strtotime('+2 hours'));
                break;
            case '6h':
                $expiry_time = date('Y-m-d H:i:s', strtotime('+6 hours'));
                break;
            case '18d':
                $expiry_time = date('Y-m-d H:i:s', strtotime('+18 days'));
                break;
            case '30d':
                $expiry_time = date('Y-m-d H:i:s', strtotime('+30 days'));
                break;
            default:
                $expiry_time = date('Y-m-d H:i:s', strtotime('+2 hours'));
        }
        
        $api_key = generate_api_key();
        
        try {
            $stmt = $pdo->prepare("INSERT INTO api_keys (key_type, related_id, key_value, comment, expiry_time, created_time) 
                                  VALUES ('user', :uid, :key_value, :comment, :expiry_time, NOW())");
            $stmt->bindParam(':uid', $user['uid']);
            $stmt->bindParam(':key_value', $api_key);
            $stmt->bindParam(':comment', $comment);
            $stmt->bindParam(':expiry_time', $expiry_time);
            $stmt->execute();
            
            $success = "用户密钥生成成功: " . $api_key;
        } catch(PDOException $e) {
            $error = "密钥生成失败: " . $e->getMessage();
        }
    }
}

// 获取最近登录记录
$stmt = $pdo->prepare("SELECT * FROM login_records WHERE uid = :uid AND success = 1 ORDER BY login_time DESC LIMIT 10");
$stmt->bindParam(':uid', $user['uid']);
$stmt->execute();
$login_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取用户的API密钥
$stmt = $pdo->prepare("SELECT * FROM api_keys WHERE key_type = 'user' AND related_id = :uid ORDER BY created_time DESC");
$stmt->bindParam(':uid', $user['uid']);
$stmt->execute();
$user_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取密钥访问记录
$key_access_logs = [];
foreach ($user_keys as $key) {
    $stmt = $pdo->prepare("SELECT * FROM api_key_access_logs 
                          JOIN service_providers ON api_key_access_logs.accessed_by = service_providers.id
                          WHERE api_key_id = :key_id");
    $stmt->bindParam(':key_id', $key['id']);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($logs as $log) {
        $key_access_logs[] = [
            'key_id' => $key['id'],
            'key_value' => $key['key_value'],
            'provider_name' => $log['name'],
            'access_time' => $log['access_time'],
            'access_ip' => $log['access_ip']
        ];
    }
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
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        h2, h3 {
            color: #333;
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
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
        }
        button:hover {
            opacity: 0.8;
        }
        .btn-danger {
            background-color: #f44336;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .success {
            color: green;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
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
        .logout-link {
            text-align: right;
            margin-bottom: 20px;
        }
        .tab-container {
            margin-bottom: 20px;
        }
        .tab {
            display: inline-block;
            padding: 10px 15px;
            background-color: #f2f2f2;
            cursor: pointer;
            border: 1px solid #ddd;
            border-bottom: none;
        }
        .tab.active {
            background-color: white;
            font-weight: bold;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
    <script>
        function showTab(tabId) {
            // 隐藏所有标签内容
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('active');
            });
            
            // 移除所有标签的活跃状态
            document.querySelectorAll('.tab').forEach(el => {
                el.classList.remove('active');
            });
            
            // 显示选中的标签内容和设置标签为活跃状态
            document.getElementById(tabId).classList.add('active');
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
        }
    </script>
</head>
<body>
    <div class="logout-link">
        <a href="login.php?action=logout">退出登录</a>
        <?php if (is_service_provider($pdo, $user)): ?>
            <br><a href="service_center.php">前往服务商中心</a>
        <?php endif; ?>
    </div>
    
    <h2>用户个人中心</h2>
    
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="tab-container">
        <div class="tab active" data-tab="profile-tab" onclick="showTab('profile-tab')">个人信息</div>
        <div class="tab" data-tab="password-tab" onclick="showTab('password-tab')">修改密码</div>
        <div class="tab" data-tab="login-log-tab" onclick="showTab('login-log-tab')">登录记录</div>
        <div class="tab" data-tab="api-key-tab" onclick="showTab('api-key-tab')">API密钥</div>
        <div class="tab" data-tab="security-tab" onclick="showTab('security-tab')">账号安全</div>
    </div>
    
    <!-- 个人信息标签内容 -->
    <div id="profile-tab" class="tab-content active section">
        <h3>个人信息</h3>
        <form method="post">
            <div class="form-group">
                <label>用户ID (UID):</label>
                <input type="text" value="<?php echo htmlspecialchars($user['uid']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label for="name">姓名:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">邮箱:</label>
                <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                <small>邮箱不可修改</small>
            </div>
            
            <div class="form-group">
                <label for="qq">QQ号:</label>
                <input type="text" id="qq" name="qq" value="<?php echo htmlspecialchars($user['qq']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>身份:</label>
                <input type="text" value="<?php 
                    $identityMap = [
                        'user' => '用户',
                        'developer' => '开发者',
                        'authorized_developer' => '授权开发者',
                        'developer_manager' => '管理开发者',
                        'service_provider' => '服务商',
                        'special_authorized_service_provider' => '特别授权服务商',
                        'admin' => '一般管理员',
                        'super_admin' => '超级管理员'
                    ];
                    echo htmlspecialchars($identityMap[$user['identity']] ?? $user['identity']);
                ?>" readonly>
            </div>
            
            <div class="form-group">
                <label>注册时间:</label>
                <input type="text" value="<?php echo htmlspecialchars($user['register_time']); ?>" readonly>
            </div>
            
            <div class="form-group">
                <label>最后登录时间:</label>
                <input type="text" value="<?php echo htmlspecialchars($user['last_login_time'] ?? '从未登录'); ?>" readonly>
            </div>
            
            <button type="submit" name="update_info">更新信息</button>
        </form>
    </div>
    
    <!-- 修改密码标签内容 -->
    <div id="password-tab" class="tab-content section">
        <h3>修改密码</h3>
        <form method="post">
            <div class="form-group">
                <label for="old_password">原密码:</label>
                <input type="password" id="old_password" name="old_password" required>
            </div>
            
            <div class="form-group">
                <label for="new_password">新密码:</label>
                <input type="password" id="new_password" name="new_password" required>
                <small>密码长度至少为8位</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">确认新密码:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" name="update_password">更新密码</button>
        </form>
    </div>
    
    <!-- 登录记录标签内容 -->
    <div id="login-log-tab" class="tab-content section">
        <h3>最近登录记录</h3>
        <table>
            <tr>
                <th>登录时间</th>
                <th>登录IP</th>
            </tr>
            <?php if (empty($login_records)): ?>
                <tr>
                    <td colspan="2">暂无登录记录</td>
                </tr>
            <?php else: ?>
                <?php foreach ($login_records as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['login_time']); ?></td>
                        <td><?php echo htmlspecialchars($record['login_ip']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
    
    <!-- API密钥标签内容 -->
    <div id="api-key-tab" class="tab-content section">
        <h3>用户密钥管理</h3>
        
        <h4>生成新密钥</h4>
        <form method="post">
            <div class="form-group">
                <label for="key_comment">密钥留言:</label>
                <textarea id="key_comment" name="key_comment" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>有效期:</label>
                <select name="expiry_option">
                    <option value="2h">2小时</option>
                    <option value="6h">6小时</option>
                    <option value="18d">18天</option>
                    <option value="30d">30天</option>
                </select>
            </div>
            
            <button type="submit" name="generate_user_key">生成密钥</button>
        </form>
        
        <h4>我的密钥</h4>
        <table>
            <tr>
                <th>密钥</th>
                <th>留言</th>
                <th>创建时间</th>
                <th>有效期至</th>
                <th>状态</th>
            </tr>
            <?php if (empty($user_keys)): ?>
                <tr>
                    <td colspan="5">暂无密钥</td>
                </tr>
            <?php else: ?>
                <?php foreach ($user_keys as $key): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($key['key_value'], 0, 10) . '...' . substr($key['key_value'], -10)); ?></td>
                        <td><?php echo htmlspecialchars($key['comment'] ?? '无'); ?></td>
                        <td><?php echo htmlspecialchars($key['created_time']); ?></td>
                        <td><?php echo htmlspecialchars($key['expiry_time']); ?></td>
                        <td>
                            <?php 
                            if ($key['used']) {
                                echo "已使用";
                            } elseif (strtotime($key['expiry_time']) < time()) {
                                echo "已过期";
                            } else {
                                echo "未使用";
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
        
        <h4>密钥查询记录</h4>
        <table>
            <tr>
                <th>密钥</th>
                <th>查询方</th>
                <th>查询时间</th>
                <th>查询IP</th>
            </tr>
            <?php if (empty($key_access_logs)): ?>
                <tr>
                    <td colspan="4">暂无查询记录</td>
                </tr>
            <?php else: ?>
                <?php foreach ($key_access_logs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($log['key_value'], 0, 10) . '...' . substr($log['key_value'], -10)); ?></td>
                        <td><?php echo htmlspecialchars($log['provider_name']); ?></td>
                        <td><?php echo htmlspecialchars($log['access_time']); ?></td>
                        <td><?php echo htmlspecialchars($log['access_ip']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
    
    <!-- 账号安全标签内容 -->
    <div id="security-tab" class="tab-content section">
        <h3>账号安全</h3>
        
        <?php if ($user['status'] != 'frozen'): ?>
            <p>您可以选择冻结自己的账号，冻结后需要验证邮箱和QQ号才能解除。</p>
            <form method="post" onsubmit="return confirm('确定要冻结账号吗？冻结后需要验证邮箱和QQ号才能解除。');">
                <button type="submit" name="freeze_account" class="btn-danger">冻结我的账号</button>
            </form>
        <?php else: ?>
            <p class="error">您的账号已冻结。</p>
            <form method="post">
                <button type="submit" name="unfreeze_account">解除冻结（需要验证）</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
