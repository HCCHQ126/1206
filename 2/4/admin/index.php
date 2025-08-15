<?php
require_once '../jc.php';
$user = require_admin();

// 检查是否为超级管理员
$is_super_admin = is_super_admin($user);

// 获取统计数据
$stats = [
    'total_users' => 0,
    'total_admins' => 0,
    'total_service_providers' => 0,
    'total_login_today' => 0
];

$conn = db_connect();

// 总用户数
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$result = $stmt->get_result();
$stats['total_users'] = $result->fetch_assoc()['count'];
$stmt->close();

// 管理员数量
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE role_id IN (SELECT id FROM user_roles WHERE name = 'general_admin' OR name = 'super_admin')
");
$stmt->execute();
$result = $stmt->get_result();
$stats['total_admins'] = $result->fetch_assoc()['count'];
$stmt->close();

// 服务商数量
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM service_providers");
$stmt->execute();
$result = $stmt->get_result();
$stats['total_service_providers'] = $result->fetch_assoc()['count'];
$stmt->close();

// 今日登录次数
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM login_logs 
    WHERE DATE(login_time) = DATE(NOW())
");
$stmt->execute();
$result = $stmt->get_result();
$stats['total_login_today'] = $result->fetch_assoc()['count'];
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 管理员后台</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <a href="index.php" class="text-xl font-bold"><?php echo SITE_NAME; ?> - 管理员后台</a>
            <div class="flex items-center space-x-4">
                <a href="../user_center.php" class="hover:text-blue-200">个人中心</a>
                <span><?php echo $user['name']; ?> (<?php echo $is_super_admin ? '超级管理员' : '一般管理员'; ?>)</span>
                <a href="../logout.php" class="hover:text-blue-200">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white shadow-md rounded-lg p-6 text-center">
                <div class="text-4xl font-bold text-blue-600 mb-2"><?php echo $stats['total_users']; ?></div>
                <div class="text-gray-600">总用户数</div>
            </div>
            <div class="bg-white shadow-md rounded-lg p-6 text-center">
                <div class="text-4xl font-bold text-green-600 mb-2"><?php echo $stats['total_admins']; ?></div>
                <div class="text-gray-600">管理员数量</div>
            </div>
            <div class="bg-white shadow-md rounded-lg p-6 text-center">
                <div class="text-4xl font-bold text-purple-600 mb-2"><?php echo $stats['total_service_providers']; ?></div>
                <div class="text-gray-600">服务商数量</div>
            </div>
            <div class="bg-white shadow-md rounded-lg p-6 text-center">
                <div class="text-4xl font-bold text-orange-600 mb-2"><?php echo $stats['total_login_today']; ?></div>
                <div class="text-gray-600">今日登录次数</div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <a href="users.php" class="block bg-white shadow-md rounded-lg p-6 hover:shadow-lg transition-shadow">
                <h3 class="text-xl font-bold text-blue-600 mb-2">用户管理</h3>
                <p class="text-gray-600">管理所有用户，包括修改用户身份、状态等</p>
            </a>
            
            <a href="groups.php" class="block bg-white shadow-md rounded-lg p-6 hover:shadow-lg transition-shadow">
                <h3 class="text-xl font-bold text-blue-600 mb-2">用户分组</h3>
                <p class="text-gray-600">创建和管理用户分组，对用户进行分类</p>
            </a>
            
            <a href="login_logs.php" class="block bg-white shadow-md rounded-lg p-6 hover:shadow-lg transition-shadow">
                <h3 class="text-xl font-bold text-blue-600 mb-2">登录记录</h3>
                <p class="text-gray-600">查看所有用户的登录记录，包括IP地址等信息</p>
            </a>
            
            <?php if ($is_super_admin): ?>
                <a href="service_providers.php" class="block bg-white shadow-md rounded-lg p-6 hover:shadow-lg transition-shadow">
                    <h3 class="text-xl font-bold text-blue-600 mb-2">服务商管理</h3>
                    <p class="text-gray-600">创建和管理服务商账号，设置服务商状态</p>
                </a>
                
                <a href="system_settings.php" class="block bg-white shadow-md rounded-lg p-6 hover:shadow-lg transition-shadow">
                    <h3 class="text-xl font-bold text-blue-600 mb-2">系统设置</h3>
                    <p class="text-gray-600">设置登录、注册、授权验证等系统功能</p>
                </a>
                
                <a href="login_bans.php" class="block bg-white shadow-md rounded-lg p-6 hover:shadow-lg transition-shadow">
                    <h3 class="text-xl font-bold text-blue-600 mb-2">登录禁令</h3>
                    <p class="text-gray-600">设置禁止登录的IP、浏览器、账号等</p>
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
    