<?php
require '../jc.php';

// 检查是否为管理员
checkAccess(['admin', 'super_admin']);
$user = getCurrentUser();
$isSuperAdmin = isSuperAdmin($user);

// 登出功能
if (isset($_GET['logout'])) {
    setcookie('user_id', '', time() - 3600, '/');
    setcookie('user_token', '', time() - 3600, '/');
    redirect('../index.php');
}
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
        h2, h3 {
            margin-top: 0;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-box {
            flex: 1;
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 8px 15px;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>管理员后台</h1>
        <div>
            <span>欢迎，<?php echo $user['name']; ?>（<?php echo $isSuperAdmin ? '超级管理员' : '一般管理员'; ?>）</span>
            <button class="secondary" onclick="window.location.href='?logout'">退出登录</button>
        </div>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <a href="admin_center.php" class="active">后台首页</a>
            <a href="user_management.php">用户管理</a>
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
                <h2>后台首页</h2>
                <p>欢迎使用管理员后台系统。请使用左侧导航菜单访问各项功能。</p>
            </div>
            
            <h3>系统统计</h3>
            <div class="stats">
                <?php
                // 获取用户总数
                global $pdo;
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
                $userCount = $stmt->fetch()['count'];
                
                // 获取今日注册用户数
                $today = date('Y-m-d');
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(register_time) = :today");
                $stmt->bindParam(':today', $today);
                $stmt->execute();
                $todayRegCount = $stmt->fetch()['count'];
                
                // 获取服务商数量
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM service_providers");
                $providerCount = $stmt->fetch()['count'];
                
                // 获取今日登录次数
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM login_records WHERE DATE(login_time) = :today AND login_success = 1");
                $stmt->bindParam(':today', $today);
                $stmt->execute();
                $todayLoginCount = $stmt->fetch()['count'];
                ?>
                <div class="stat-box">
                    <div>总用户数</div>
                    <div class="stat-value"><?php echo $userCount; ?></div>
                    <a href="user_management.php"><button>查看详情</button></a>
                </div>
                <div class="stat-box">
                    <div>今日注册</div>
                    <div class="stat-value"><?php echo $todayRegCount; ?></div>
                </div>
                <div class="stat-box">
                    <div>服务商数</div>
                    <div class="stat-value"><?php echo $providerCount; ?></div>
                    <?php if ($isSuperAdmin): ?>
                        <a href="service_providers.php"><button>查看详情</button></a>
                    <?php endif; ?>
                </div>
                <div class="stat-box">
                    <div>今日登录</div>
                    <div class="stat-value"><?php echo $todayLoginCount; ?></div>
                    <a href="login_records.php"><button>查看详情</button></a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
