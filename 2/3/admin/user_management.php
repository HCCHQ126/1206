<?php
require '../jc.php';

// 检查是否为管理员
checkAccess(['admin', 'super_admin']);
$user = getCurrentUser();
$isSuperAdmin = isSuperAdmin($user);

$error = '';
$success = '';

// 处理用户状态更新
if (isset($_POST['update_status'])) {
    $userId = $_POST['user_id'];
    $status = $_POST['status'];
    
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    
    $success = '用户状态已更新';
}

// 处理用户身份更新
if (isset($_POST['update_identity'])) {
    $userId = $_POST['user_id'];
    $identity = $_POST['identity'];
    
    // 检查是否为超级管理员，只有超级管理员可以设置特别身份
    if (!$isSuperAdmin) {
        $specialIdentities = ['manage_developer', 'service_provider', 'special_authorized_provider', 'admin', 'super_admin'];
        if (in_array($identity, $specialIdentities)) {
            $error = '没有权限设置该身份';
        }
    }
    
    if (!$error) {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE users SET identity = :identity WHERE id = :id");
        $stmt->bindParam(':identity', $identity);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        $success = '用户身份已更新';
    }
}

// 搜索用户
$search = trim($_GET['search'] ?? '');
$whereClause = '';
$params = [];

if (!empty($search)) {
    $whereClause = "WHERE name LIKE :search OR email LIKE :search OR qq LIKE :search OR uid LIKE :search";
    $params[':search'] = "%$search%";
}

// 获取用户列表
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM users $whereClause ORDER BY register_time DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }
        .header {
            background-color: #333;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .container {
            display: flex;
            min-height: calc(100vh - 62px);
        }
        .sidebar {
            width: 200px;
            background-color: #555;
            color: white;
            padding: 20px 0;
        }
        .sidebar a {
            display: block;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: #777;
        }
        .content {
            flex: 1;
            padding: 20px;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h2 {
            margin-top: 0;
        }
        .search-form {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .search-form input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
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
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
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
        .status {
            padding: 3px 8px;
            border-radius: 4px;
            color: white;
            font-size: 12px;
        }
        .status-normal {
            background-color: #4CAF50;
        }
        .status-locked {
            background-color: #f44336;
        }
        .status-restricted {
            background-color: #ff9800;
        }
        .status-frozen {
            background-color: #9e9e9e;
        }
        select {
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>管理员后台</h1>
        <div>
            <span>欢迎，<?php echo $user['name']; ?></span>
            <button class="secondary" onclick="window.location.href='admin_center.php?logout'">退出登录</button>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_center.php">后台首页</a>
            <a href="user_management.php" class="active">用户管理</a>
            <a href="user_groups.php">用户分组</a>
            <a href="identity_management.php">身份管理</a>
            <a href="login_records.php">登录记录</a>
            <?php if ($isSuperAdmin): ?>
                <a href="service_providers.php">服务商管理</a>
                <a href="system_settings.php">系统设置</a>
            <?php endif; ?>
        </div>
        
        <div class="content">
            <div class="card">
                <h2>用户管理</h2>
                
                <?php if ($error): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <form class="search-form" method="get">
                    <input type="text" name="search" placeholder="搜索姓名、邮箱、QQ号或用户ID" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">搜索</button>
                    <?php if (!empty($search)): ?>
                        <button type="button" onclick="window.location.href='user_management.php'">清除</button>
                    <?php endif; ?>
                </form>
                
                <table>
                    <tr>
                        <th>用户ID</th>
                        <th>姓名</th>
                        <th>邮箱</th>
                        <th>QQ号</th>
                        <th>身份</th>
                        <th>状态</th>
                        <th>注册时间</th>
                        <th>最后登录</th>
                        <th>操作</th>
                    </tr>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">没有找到用户</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $userItem): ?>
                            <tr>
                                <td><?php echo $userItem['uid']; ?></td>
                                <td><?php echo $userItem['name']; ?></td>
                                <td><?php echo $userItem['email']; ?></td>
                                <td><?php echo $userItem['qq']; ?></td>
                                <td>
                                    <?php 
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
                                        echo $identityMap[$userItem['identity']];
                                    ?>
                                </td>
                                <td>
                                    <span class="status status-<?php echo $userItem['status']; ?>">
                                        <?php 
                                            $statusMap = [
                                                'normal' => '正常',
                                                'locked' => '锁定',
                                                'restricted' => '限制',
                                                'frozen' => '冻结'
                                            ];
                                            echo $statusMap[$userItem['status']];
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo $userItem['register_time']; ?></td>
                                <td><?php echo $userItem['last_login_time'] ?: '从未登录'; ?></td>
                                <td>
                                    <!-- 状态更新表单 -->
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $userItem['id']; ?>">
                                        <select name="status" onchange="this.form.submit()">
                                            <option value="normal" <?php echo $userItem['status'] == 'normal' ? 'selected' : ''; ?>>正常</option>
                                            <option value="locked" <?php echo $userItem['status'] == 'locked' ? 'selected' : ''; ?>>锁定</option>
                                            <option value="restricted" <?php echo $userItem['status'] == 'restricted' ? 'selected' : ''; ?>>限制</option>
                                            <option value="frozen" <?php echo $userItem['status'] == 'frozen' ? 'selected' : ''; ?>>冻结</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                    
                                    <!-- 身份更新表单 -->
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $userItem['id']; ?>">
                                        <select name="identity" onchange="this.form.submit()">
                                            <?php 
                                                // 定义身份选项
                                                $identities = [
                                                    'user' => '用户',
                                                    'developer' => '开发者',
                                                    'authorized_developer' => '授权开发者'
                                                ];
                                                
                                                // 超级管理员可以看到更多身份选项
                                                if ($isSuperAdmin) {
                                                    $identities += [
                                                        'manage_developer' => '管理开发者',
                                                        'service_provider' => '服务商',
                                                        'special_authorized_provider' => '特别授权服务商',
                                                        'admin' => '一般管理员'
                                                        // 不允许直接设置为超级管理员，除非通过特殊方式
                                                    ];
                                                }
                                                
                                                foreach ($identities as $key => $value) {
                                                    $selected = $userItem['identity'] == $key ? 'selected' : '';
                                                    echo "<option value=\"$key\" $selected>$value</option>";
                                                }
                                            ?>
                                        </select>
                                        <input type="hidden" name="update_identity" value="1">
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
