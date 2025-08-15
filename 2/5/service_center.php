<?php
require '../jc.php';

// 检查登录状态
if (!is_logged_in() || !validate_user($pdo)) {
    header("Location: ../login.php");
    exit;
}

$user = get_current_in_user($pdo);

// 检查是否为服务商
if (!is_service_provider($pdo, $user)) {
    header("Location: ../user_center.php");
    exit;
}

// 获取服务商信息
$stmt = $pdo->prepare("SELECT sp.* FROM service_providers sp
                      JOIN service_provider_managers spm ON sp.id = spm.sp_id
                      WHERE spm.uid = :uid");
$stmt->bindParam(':uid', $user['uid']);
$stmt->execute();
$service_provider = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 处理服务商密钥生成
    if (isset($_POST['generate_sp_key'])) {
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
                                  VALUES ('service_provider', :sp_id, :key_value, :comment, :expiry_time, NOW())");
            $stmt->bindParam(':sp_id', $service_provider['id']);
            $stmt->bindParam(':key_value', $api_key);
            $stmt->bindParam(':comment', $comment);
            $stmt->bindParam(':expiry_time', $expiry_time);
            $stmt->execute();
            
            $success = "服务商密钥生成成功: " . $api_key;
        } catch(PDOException $e) {
            $error = "密钥生成失败: " . $e->getMessage();
        }
    }
    
    // 处理用户身份验证
    if (isset($_POST['verify_user'])) {
        $user_key = trim($_POST['user_key'] ?? '');
        $sp_key = trim($_POST['sp_key'] ?? '');
        
        if (empty($user_key) || empty($sp_key)) {
            $error = "用户密钥和服务商密钥都是必填的";
        } else {
            // 验证服务商密钥
            $stmt = $pdo->prepare("SELECT * FROM api_keys 
                                  WHERE key_type = 'service_provider' 
                                  AND related_id = :sp_id 
                                  AND key_value = :sp_key
                                  AND expiry_time > NOW()
                                  AND used = 0");
            $stmt->bindParam(':sp_id', $service_provider['id']);
            $stmt->bindParam(':sp_key', $sp_key);
            $stmt->execute();
            $valid_sp_key = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$valid_sp_key) {
                $error = "服务商密钥无效或已过期";
            } else {
                // 验证用户密钥
                $stmt = $pdo->prepare("SELECT ak.*, u.uid, u.name, u.identity 
                                      FROM api_keys ak
                                      JOIN users u ON ak.related_id = u.uid
                                      WHERE ak.key_type = 'user' 
                                      AND ak.key_value = :user_key
                                      AND ak.expiry_time > NOW()
                                      AND ak.used = 0");
                $stmt->bindParam(':user_key', $user_key);
                $stmt->execute();
                $user_key_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user_key_data) {
                    $error = "用户密钥无效或已过期";
                } else {
                    // 标记密钥为已使用
                    try {
                        // 标记用户密钥为已使用
                        $stmt = $pdo->prepare("UPDATE api_keys SET used = 1, used_time = NOW() WHERE id = :id");
                        $stmt->bindParam(':id', $user_key_data['id']);
                        $stmt->execute();
                        
                        // 记录密钥访问日志
                        $stmt = $pdo->prepare("INSERT INTO api_key_access_logs (api_key_id, accessed_by, access_time, access_ip) 
                                              VALUES (:key_id, :sp_id, NOW(), :ip)");
                        $stmt->bindParam(':key_id', $user_key_data['id']);
                        $stmt->bindParam(':sp_id', $service_provider['id']);
                        $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                        $stmt->execute();
                        
                        $success = "用户身份验证成功";
                        
                        // 存储验证结果供显示
                        $verification_result = [
                            'uid' => $user_key_data['uid'],
                            'name' => $user_key_data['name'],
                            'identity' => $user_key_data['identity']
                        ];
                    } catch(PDOException $e) {
                        $error = "验证过程中出错: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

// 获取服务商的API密钥
$stmt = $pdo->prepare("SELECT * FROM api_keys WHERE key_type = 'service_provider' AND related_id = :sp_id ORDER BY created_time DESC");
$stmt->bindParam(':sp_id', $service_provider['id']);
$stmt->execute();
$sp_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取验证记录
$stmt = $pdo->prepare("SELECT ak.key_value, u.uid, u.name, u.identity, akal.access_time, akal.access_ip
                      FROM api_key_access_logs akal
                      JOIN api_keys ak ON akal.api_key_id = ak.id
                      JOIN users u ON ak.related_id = u.uid
                      WHERE akal.accessed_by = :sp_id
                      ORDER BY akal.access_time DESC");
$stmt->bindParam(':sp_id', $service_provider['id']);
$stmt->execute();
$verification_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服务商中心</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
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
        input, textarea, select {
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
        .verification-result {
            padding: 15px;
            background-color: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 4px;
            margin-top: 15px;
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
        <a href="../login.php?action=logout">退出登录</a>
        <br><a href="../user_center.php">返回用户中心</a>
    </div>
    
    <h2>服务商中心</h2>
    <h3><?php echo htmlspecialchars($service_provider['name']); ?></h3>
    
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
        
        <?php if (isset($verification_result)): ?>
            <div class="verification-result">
                <h4>验证结果:</h4>
                <p><strong>用户ID:</strong> <?php echo htmlspecialchars($verification_result['uid']); ?></p>
                <p><strong>姓名:</strong> <?php echo htmlspecialchars($verification_result['name']); ?></p>
                <p><strong>身份:</strong> <?php 
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
                    echo htmlspecialchars($identityMap[$verification_result['identity']] ?? $verification_result['identity']);
                ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div class="tab-container">
        <div class="tab active" data-tab="verify-tab" onclick="showTab('verify-tab')">用户身份验证</div>
        <div class="tab" data-tab="keys-tab" onclick="showTab('keys-tab')">密钥管理</div>
        <div class="tab" data-tab="logs-tab" onclick="showTab('logs-tab')">验证记录</div>
    </div>
    
    <!-- 用户身份验证标签内容 -->
    <div id="verify-tab" class="tab-content active section">
        <h3>用户身份验证</h3>
        <p>使用用户提供的用户密钥和您的服务商密钥来验证用户身份</p>
        
        <form method="post">
            <div class="form-group">
                <label for="user_key">用户密钥:</label>
                <input type="text" id="user_key" name="user_key" required placeholder="输入用户提供的密钥">
            </div>
            
            <div class="form-group">
                <label for="sp_key">服务商密钥:</label>
                <input type="text" id="sp_key" name="sp_key" required placeholder="输入您的服务商密钥">
            </div>
            
            <button type="submit" name="verify_user">验证用户身份</button>
        </form>
    </div>
    
    <!-- 密钥管理标签内容 -->
    <div id="keys-tab" class="tab-content section">
        <h3>服务商密钥管理</h3>
        
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
            
            <button type="submit" name="generate_sp_key">生成密钥</button>
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
            <?php if (empty($sp_keys)): ?>
                <tr>
                    <td colspan="5">暂无密钥</td>
                </tr>
            <?php else: ?>
                <?php foreach ($sp_keys as $key): ?>
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
    </div>
    
    <!-- 验证记录标签内容 -->
    <div id="logs-tab" class="tab-content section">
        <h3>验证记录</h3>
        <table>
            <tr>
                <th>用户密钥</th>
                <th>用户ID</th>
                <th>用户姓名</th>
                <th>用户身份</th>
                <th>验证时间</th>
                <th>验证IP</th>
            </tr>
            <?php if (empty($verification_logs)): ?>
                <tr>
                    <td colspan="6">暂无验证记录</td>
                </tr>
            <?php else: ?>
                <?php foreach ($verification_logs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($log['key_value'], 0, 10) . '...' . substr($log['key_value'], -10)); ?></td>
                        <td><?php echo htmlspecialchars($log['uid']); ?></td>
                        <td><?php echo htmlspecialchars($log['name']); ?></td>
                        <td><?php 
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
                            echo htmlspecialchars($identityMap[$log['identity']] ?? $log['identity']);
                        ?></td>
                        <td><?php echo htmlspecialchars($log['access_time']); ?></td>
                        <td><?php echo htmlspecialchars($log['access_ip']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>
