<?php
require_once 'functions.php';

// 检查用户是否已登录，根据用户身份跳转到相应页面
if (is_logged_in()) {
    $user = get_current_user();
    
    if (!$user) {
        clear_user_cookie();
    } else {
        // 根据用户身份跳转到相应页面
        if (is_admin($user)) {
            header('Location: admin_manage.php');
            exit;
        } elseif (is_service_provider($user)) {
            header('Location: service_provider.php');
            exit;
        } else {
            header('Location: user_center.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <nav>
                <ul>
                    <li><a href="login.php">登录</a></li>
                    <li><a href="register.php">注册</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="container">
        <div class="text-center mt-3">
            <h1>欢迎使用 <?php echo SITE_NAME; ?></h1>
            <p class="mt-2">请登录或注册以继续使用系统功能</p>
            <div class="mt-3">
                <a href="login.php" class="btn">登录</a>
                <a href="register.php" class="btn btn-secondary ml-2">注册</a>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - 版权所有</p>
        </div>
    </footer>
</body>
</html>
