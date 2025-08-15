<?php
require '../jc.php';

// 检查登录状态和超级管理员权限
if (!is_logged_in() || !validate_user($pdo)) {
    header("Location: ../login.php");
    exit;
}

$user = get_logged_in_user($pdo); // 使用修复后的函数名

if (!is_super_admin($user)) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

// 获取当前系统设置
$settings = [];
$stmt = $pdo->query("SELECT * FROM system_settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_name']] = $row['setting_value'];
}

// 获取已禁止的条目
$stmt = $pdo->query("SELECT * FROM blocked_entries ORDER BY blocked_time DESC");
$blocked_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 处理系统设置更新
    if (isset($_POST['update_settings'])) {
        $enable_registration = $_POST['enable_registration'] ?? '0';
        $enable_login = $_POST['enable_login'] ?? '0';
        $enable_authorization = $_POST['enable_authorization'] ?? '0';
        $force_email_verification = $_POST['force_email_verification'] ?? '0';
        
        try {
            // 使用事务确保所有设置都更新成功
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = :value, updated_time = NOW(), updated_by = :uid WHERE setting_name = :name");
            
            $stmt->bindParam(':value', $enable_registration);
            $stmt->bindParam(':uid', $user['uid']);
            $stmt->bindParam(':name', $name = 'enable_registration');
            $stmt->execute();
            
            $stmt->bindParam(':value', $enable_login);
            $stmt->bindParam(':name', $name = 'enable_login');
            $stmt->execute();
            
            $stmt->bindParam(':value', $enable_authorization);
            $stmt->bindParam(':name', $name = 'enable_authorization');
            $stmt->execute();
            
            $stmt->bindParam(':value', $force_email_verification);
            $stmt->bindParam(':name', $name = 'force_email_verification');
            $stmt->execute();
            
            $pdo->commit();
            
            // 更新本地设置变量
            $settings['enable_registration'] = $enable_registration;
            $settings['enable_login'] = $enable_login;
            $settings['enable_authorization'] = $enable_authorization;
            $settings['force_email_verification'] = $force_email_verification;
            
            $success = "系统设置已更新";
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = "更新失败: " . $e->getMessage();
        }
    }
    
    // 处理添加禁止条目
    if (isset($_POST['add_block'])) {
        $entry_type = $_POST['entry_type'];
        $entry_value = trim($_POST['entry_value']);
        $reason = trim($_POST['reason']);
        
        if (empty($entry_value)) {
            $error = "禁止内容不能为空";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO blocked_entries (entry_type, entry_value, blocked_time, blocked_by, reason) 
                                      VALUES (:type, :value, NOW(), :uid, :reason)");
                $stmt->bindParam(':type', $entry_type);
                $stmt->bindParam(':value', $entry_value);
                $stmt->bindParam(':uid', $user['uid']);
                $stmt->bindParam(':reason', $reason);
                $stmt->execute();
                
                $success = "已添加禁止条目";
                
                // 重新获取禁止条目列表
                $stmt = $pdo->query("SELECT * FROM blocked_entries ORDER BY blocked_time DESC");
                $blocked_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                $error = "添加失败: " . $e->getMessage();
            }
        }
    }
    
    // 处理移除禁止条目
    if (isset($_POST['remove_block'])) {
        $block_id = $_POST['block_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM blocked_entries WHERE id = :id");
            $stmt->bindParam(':id', $block_id);
            $stmt->execute();
            
            $success = "已移除禁止条目";
            
            // 重新获取禁止条目列表
            $stmt = $pdo->query("SELECT * FROM blocked_entries ORDER BY blocked_time DESC");
            $blocked_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            $error = "移除失败: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - 超级管理员</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .section {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        h1, h2, h3 {
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input, select, textarea {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .checkbox-group input {
            width: auto;
            margin-right: 10px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
        }
        button:hover {
            opacity: 0.9;
            transition: opacity 0.3s;
        }
        button.btn-danger {
            background-color: #f44336;
        }
        .error {
            color: red;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ffebee;
            border: 1px solid #ffebee;
            background-color: #ffebee;
            border-radius: 4px;
        }
        .success {
            color: green;
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #e8f5e9;
            background-color: #e8f5e9;
            border-radius: 4px;
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
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .tab-container {
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            display: inline-block;
            padding: 10px 15px;
            background-color: #f2f2f2;
            cursor: pointer;
            border: 1px solid #ddd;
            border-bottom: none;
            margin-right: 5px;
            border-radius: 4px 4px 0 0;
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
        
        function confirmAction(message) {
            return confirm(message);
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>系统设置</h1>
            <div>
                <span>欢迎, <?php echo htmlspecialchars($user['name']); ?> (超级管理员)</span>
                |
                <a href="dashboard.php">返回后台</a>
                |
                <a href="../login.php?action=logout">退出登录</a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="tab-container">
            <div class="tab active" data-tab="system-settings-tab" onclick="showTab('system-settings-tab')">系统设置</div>
            <div class="tab" data-tab="blocked-entries-tab" onclick="showTab('blocked-entries-tab')">禁止登录管理</div>
        </div>
        
        <!-- 系统设置标签内容 -->
        <div id="system-settings-tab" class="tab-content active section">
            <h2>系统功能设置</h2>
            <form method="post">
                <div class="checkbox-group">
                    <input type="checkbox" id="enable_registration" name="enable_registration" value="1" 
                           <?php echo $settings['enable_registration'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_registration">启用注册页面</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="enable_login" name="enable_login" value="1" 
                           <?php echo $settings['enable_login'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_login">启用登录页面</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="enable_authorization" name="enable_authorization" value="1" 
                           <?php echo $settings['enable_authorization'] == '1' ? 'checked' : ''; ?>>
                    <label for="enable_authorization">启用授权验证页面</label>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="force_email_verification" name="force_email_verification" value="1" 
                           <?php echo $settings['force_email_verification'] == '1' ? 'checked' : ''; ?>>
                    <label for="force_email_verification">启用邮箱强制验证</label>
                </div>
                
                <button type="submit" name="update_settings">保存设置</button>
            </form>
        </div>
        
        <!-- 禁止登录管理标签内容 -->
        <div id="blocked-entries-tab" class="tab-content section">
            <h2>禁止登录管理</h2>
            
            <h3>添加禁止条目</h3>
            <form method="post">
                <div class="form-group">
                    <label for="entry_type">禁止类型:</label>
                    <select id="entry_type" name="entry_type" required>
                        <option value="ip">IP地址</option>
                        <option value="browser_fingerprint">浏览器指纹</option>
                        <option value="uid">用户ID</option>
                        <option value="qq">QQ号</option>
                        <option value="email">邮箱</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="entry_value">禁止内容:</label>
                    <input type="text" id="entry_value" name="entry_value" required placeholder="输入要禁止的内容">
                </div>
                
                <div class="form-group">
                    <label for="reason">原因:</label>
                    <textarea id="reason" name="reason" rows="3" placeholder="输入禁止原因（可选）"></textarea>
                </div>
                
                <button type="submit" name="add_block">添加禁止</button>
            </form>
            
            <h3>已禁止的条目</h3>
            <?php if (empty($blocked_entries)): ?>
                <p>暂无禁止条目</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>类型</th>
                        <th>内容</th>
                        <th>禁止时间</th>
                        <th>禁止者</th>
                        <th>原因</th>
                        <th>操作</th>
                    </tr>
                    <?php foreach ($blocked_entries as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['id']); ?></td>
                            <td><?php 
                                $typeMap = [
                                    'ip' => 'IP地址',
                                    'browser_fingerprint' => '浏览器指纹',
                                    'uid' => '用户ID',
                                    'qq' => 'QQ号',
                                    'email' => '邮箱'
                                ];
                                echo htmlspecialchars($typeMap[$entry['entry_type']] ?? $entry['entry_type']);
                            ?></td>
                            <td><?php echo htmlspecialchars($entry['entry_value']); ?></td>
                            <td><?php echo htmlspecialchars($entry['blocked_time']); ?></td>
                            <td><?php echo htmlspecialchars($entry['blocked_by']); ?></td>
                            <td><?php echo htmlspecialchars($entry['reason'] ?? '无'); ?></td>
                            <td>
                                <form method="post" onsubmit="return confirmAction('确定要移除该禁止条目吗？');">
                                    <input type="hidden" name="block_id" value="<?php echo htmlspecialchars($entry['id']); ?>">
                                    <button type="submit" name="remove_block" class="btn-danger">移除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>