<?php
require '../jc.php';

// 检查登录状态和管理员权限
if (!is_logged_in() || !validate_user($pdo)) {
    header("Location: ../login.php");
    exit;
}

$user = get_current_in_user($pdo);

if (!is_admin($user)) {
    header("Location: ../user_center.php");
    exit;
}

$error = '';
$success = '';
$users = [];
$login_records = [];

// 处理用户搜索
if (isset($_GET['search']) || isset($_POST['action'])) {
    $search_term = trim($_GET['search'] ?? $_POST['search_term'] ?? '');
    
    $where_clause = [];
    $params = [];
    
    if (!empty($search_term)) {
        $where_clause[] = "(uid LIKE :search OR name LIKE :search OR email LIKE :search OR qq LIKE :search)";
        $params[':search'] = "%{$search_term}%";
    }
    
    // 处理用户状态更新
    if (isset($_POST['update_status'])) {
        $uid = $_POST['uid'];
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE uid = :uid");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':uid', $uid);
            $stmt->execute();
            
            $success = "用户状态已更新";
        } catch(PDOException $e) {
            $error = "更新失败: " . $e->getMessage();
        }
    }
    
    // 处理用户身份更新
    if (isset($_POST['update_identity'])) {
        $uid = $_POST['uid'];
        $identity = $_POST['identity'];
        
        // 检查是否为超级管理员，只有超级管理员可以设置特殊身份
        $allowed_identities = ['user', 'developer', 'authorized_developer'];
        
        if (is_super_admin($user)) {
            $allowed_identities = [
                'user', 'developer', 'authorized_developer', 
                'developer_manager', 'service_provider', 
                'special_authorized_service_provider', 'admin', 'super_admin'
            ];
        }
        
        // 检查身份是否在允许的列表中
        if (!in_array($identity, $allowed_identities)) {
            $error = "您没有权限设置该身份";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET identity = :identity WHERE uid = :uid");
                $stmt->bindParam(':identity', $identity);
                $stmt->bindParam(':uid', $uid);
                $stmt->execute();
                
                $success = "用户身份已更新";
            } catch(PDOException $e) {
                $error = "更新失败: " . $e->getMessage();
            }
        }
    }
    
    // 构建查询
    $where_sql = $where_clause ? "WHERE " . implode(" AND ", $where_clause) : "";
    
    // 获取用户列表
    $stmt = $pdo->prepare("SELECT * FROM users {$where_sql} ORDER BY register_time DESC");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 获取登录记录
    $login_where_sql = $search_term ? "WHERE uid LIKE :search OR login_ip LIKE :search" : "";
    $stmt = $pdo->prepare("SELECT * FROM login_records {$login_where_sql} ORDER BY login_time DESC LIMIT 100");
    if ($search_term) {
        $stmt->bindParam(':search', "%{$search_term}%");
    }
    $stmt->execute();
    $login_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取所有服务商
$stmt = $pdo->prepare("SELECT * FROM service_providers ORDER BY created_time DESC");
$stmt->execute();
$service_providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员后台</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
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
        input, select {
            padding: 8px;
            box-sizing: border-box;
        }
        .search-form {
            display: flex;
            margin-bottom: 20px;
        }
        .search-form input {
            flex-grow: 1;
            margin-right: 10px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            cursor: pointer;
            border-radius: 4px;
        }
        button:hover {
            opacity: 0.8;
        }
        button.btn-danger {
            background-color: #f44336;
        }
        button.btn-secondary {
            background-color: #2196F3;
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
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status-normal {
            color: green;
        }
        .status-locked {
            color: orange;
        }
        .status-restricted {
            color: #ff9800;
        }
        .status-frozen {
            color: red;
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
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .action-buttons form {
            margin: 0;
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
            <h1>管理员后台</h1>
            <div>
                <span>欢迎, <?php echo htmlspecialchars($user['name']); ?> (<?php 
                    echo $user['identity'] == 'super_admin' ? '超级管理员' : '一般管理员';
                ?>)</span>
                |
                <a href="../login.php?action=logout">退出登录</a>
                <?php if (is_super_admin($user)): ?>
                    |
                    <a href="settings.php">系统设置</a>
                    |
                    <a href="create_svc.php">创建服务商</a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="section">
            <h2>搜索</h2>
            <form method="get" class="search-form">
                <input type="text" name="search" placeholder="搜索用户ID、姓名、邮箱或QQ号..." 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <button type="submit">搜索</button>
            </form>
        </div>
        
        <div class="tab-container">
            <div class="tab active" data-tab="users-tab" onclick="showTab('users-tab')">用户管理</div>
            <div class="tab" data-tab="login-logs-tab" onclick="showTab('login-logs-tab')">登录记录</div>
            <div class="tab" data-tab="service-providers-tab" onclick="showTab('service-providers-tab')">服务商管理</div>
        </div>
        
        <!-- 用户管理标签内容 -->
        <div id="users-tab" class="tab-content active section">
            <h2>用户管理</h2>
            
            <?php if (empty($users)): ?>
                <p><?php echo isset($_GET['search']) ? '没有找到匹配的用户' : '请使用上方搜索框查找用户'; ?></p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>用户ID (UID)</th>
                        <th>姓名</th>
                        <th>邮箱</th>
                        <th>QQ号</th>
                        <th>身份</th>
                        <th>状态</th>
                        <th>注册时间</th>
                        <th>最后登录</th>
                        <th>操作</th>
                    </tr>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['uid']); ?></td>
                            <td><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['qq']); ?></td>
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
                                echo htmlspecialchars($identityMap[$u['identity']] ?? $u['identity']);
                            ?></td>
                            <td class="status-<?php echo htmlspecialchars($u['status']); ?>">
                                <?php 
                                $statusMap = [
                                    'normal' => '正常',
                                    'locked' => '锁定',
                                    'restricted' => '限制',
                                    'frozen' => '冻结'
                                ];
                                echo htmlspecialchars($statusMap[$u['status']] ?? $u['status']);
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($u['register_time']); ?></td>
                            <td><?php echo htmlspecialchars($u['last_login_time'] ?? '从未登录'); ?></td>
                            <td class="action-buttons">
                                <!-- 更新用户状态表单 -->
                                <form method="post" onsubmit="return confirmAction('确定要更改用户状态吗？');">
                                    <input type="hidden" name="uid" value="<?php echo htmlspecialchars($u['uid']); ?>">
                                    <input type="hidden" name="search_term" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="normal" <?php echo $u['status'] == 'normal' ? 'selected' : ''; ?>>正常</option>
                                        <option value="locked" <?php echo $u['status'] == 'locked' ? 'selected' : ''; ?>>锁定</option>
                                        <option value="restricted" <?php echo $u['status'] == 'restricted' ? 'selected' : ''; ?>>限制</option>
                                        <option value="frozen" <?php echo $u['status'] == 'frozen' ? 'selected' : ''; ?>>冻结</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                                
                                <!-- 更新用户身份表单 -->
                                <form method="post" onsubmit="return confirmAction('确定要更改用户身份吗？');">
                                    <input type="hidden" name="uid" value="<?php echo htmlspecialchars($u['uid']); ?>">
                                    <input type="hidden" name="search_term" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                                    <select name="identity" onchange="this.form.submit()">
                                        <?php 
                                        $allowed_identities = ['user', 'developer', 'authorized_developer'];
                                        $identityLabels = [
                                            'user' => '用户',
                                            'developer' => '开发者',
                                            'authorized_developer' => '授权开发者'
                                        ];
                                        
                                        if (is_super_admin($user)) {
                                            $allowed_identities = [
                                                'user', 'developer', 'authorized_developer', 
                                                'developer_manager', 'service_provider', 
                                                'special_authorized_service_provider', 'admin', 'super_admin'
                                            ];
                                            $identityLabels['developer_manager'] = '管理开发者';
                                            $identityLabels['service_provider'] = '服务商';
                                            $identityLabels['special_authorized_service_provider'] = '特别授权服务商';
                                            $identityLabels['admin'] = '一般管理员';
                                            $identityLabels['super_admin'] = '超级管理员';
                                        }
                                        
                                        foreach ($allowed_identities as $identity):
                                        ?>
                                        <option value="<?php echo $identity; ?>" <?php echo $u['identity'] == $identity ? 'selected' : ''; ?>>
                                            <?php echo $identityLabels[$identity]; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="update_identity" value="1">
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- 登录记录标签内容 -->
        <div id="login-logs-tab" class="tab-content section">
            <h2>登录记录</h2>
            
            <?php if (empty($login_records)): ?>
                <p><?php echo isset($_GET['search']) ? '没有找到匹配的登录记录' : '请使用上方搜索框查找登录记录'; ?></p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>用户ID</th>
                        <th>登录时间</th>
                        <th>登录IP</th>
                        <th>浏览器指纹</th>
                        <th>登录结果</th>
                    </tr>
                    <?php foreach ($login_records as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['id']); ?></td>
                            <td><?php echo htmlspecialchars($log['uid']); ?></td>
                            <td><?php echo htmlspecialchars($log['login_time']); ?></td>
                            <td><?php echo htmlspecialchars($log['login_ip']); ?></td>
                            <td><?php echo htmlspecialchars(substr($log['browser_fingerprint'], 0, 30) . (strlen($log['browser_fingerprint']) > 30 ? '...' : '')); ?></td>
                            <td><?php echo $log['success'] ? '<span class="status-normal">成功</span>' : '<span class="status-locked">失败</span>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- 服务商管理标签内容 -->
        <div id="service-providers-tab" class="tab-content section">
            <h2>服务商管理</h2>
            
            <?php if (empty($service_providers)): ?>
                <p>暂无服务商记录</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>名称</th>
                        <th>描述</th>
                        <th>状态</th>
                        <th>创建时间</th>
                        <th>创建者</th>
                        <th>操作</th>
                    </tr>
                    <?php foreach ($service_providers as $sp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sp['id']); ?></td>
                            <td><?php echo htmlspecialchars($sp['name']); ?></td>
                            <td><?php echo htmlspecialchars($sp['description'] ?? '无'); ?></td>
                            <td><?php 
                                $statusMap = [
                                    'active' => '可用',
                                    'locked' => '锁定',
                                    'restricted' => '限制'
                                ];
                                echo htmlspecialchars($statusMap[$sp['status']] ?? $sp['status']);
                            ?></td>
                            <td><?php echo htmlspecialchars($sp['created_time']); ?></td>
                            <td><?php echo htmlspecialchars($sp['created_by']); ?></td>
                            <td class="action-buttons">
                                <?php if (is_super_admin($user)): ?>
                                    <form method="post" onsubmit="return confirmAction('确定要更改服务商状态吗？');">
                                        <input type="hidden" name="sp_id" value="<?php echo htmlspecialchars($sp['id']); ?>">
                                        <select name="sp_status" onchange="this.form.submit()">
                                            <option value="active" <?php echo $sp['status'] == 'active' ? 'selected' : ''; ?>>可用</option>
                                            <option value="locked" <?php echo $sp['status'] == 'locked' ? 'selected' : ''; ?>>锁定</option>
                                            <option value="restricted" <?php echo $sp['status'] == 'restricted' ? 'selected' : ''; ?>>限制</option>
                                        </select>
                                        <input type="hidden" name="update_sp_status" value="1">
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
