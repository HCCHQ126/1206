<?php
require_once 'functions.php';

// 检查授权验证功能是否启用
if (get_system_setting('auth_verify_enabled') != '1') {
    die("授权验证功能已关闭");
}

$message = '';
$message_type = '';
$verification_result = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sp_key = trim($_POST['sp_key'] ?? '');
    $user_key = trim($_POST['user_key'] ?? '');
    
    if (empty($sp_key) || empty($user_key)) {
        $message = '请输入服务商密钥和用户密钥';
        $message_type = 'danger';
    } else {
        global $pdo;
        $current_time = date('Y-m-d H:i:s');
        
        // 验证服务商密钥
        $stmt = $pdo->prepare("SELECT spk.*, sp.name as sp_name FROM service_provider_keys spk
                              JOIN service_providers sp ON spk.sp_id = sp.id
                              WHERE spk.`key` = :sp_key AND spk.status = 'valid' AND spk.expiry_time >= :current_time");
        $stmt->bindParam(':sp_key', $sp_key);
        $stmt->bindParam(':current_time', $current_time);
        $stmt->execute();
        $sp_key_data = $stmt->fetch();
        
        if (!$sp_key_data) {
            $message = '无效的服务商密钥或密钥已过期';
            $message_type = 'danger';
        } else {
            // 验证用户密钥（未使用过）
            $stmt = $pdo->prepare("SELECT uk.*, u.name, u.uid, u.email FROM user_keys uk
                                  JOIN users u ON uk.uid = u.uid
                                  WHERE uk.`key` = :user_key AND uk.used = FALSE");
            $stmt->bindParam(':user_key', $user_key);
            $stmt->execute();
            $user_key_data = $stmt->fetch();
            
            if (!$user_key_data) {
                $message = '无效的用户密钥或密钥已被使用';
                $message_type = 'danger';
            } else {
                // 标记用户密钥为已使用
                $stmt = $pdo->prepare("UPDATE user_keys SET used = TRUE, used_by = :sp_id, used_time = :current_time
                                      WHERE id = :id");
                $stmt->bindParam(':sp_id', $sp_key_data['sp_id']);
                $stmt->bindParam(':current_time', $current_time);
                $stmt->bindParam(':id', $user_key_data['id']);
                $stmt->execute();
                
                // 准备返回给服务商的用户信息
                $display_info = json_decode($user_key_data['display_info'], true);
                $user_info = [];
                
                if (!empty($display_info['uid'])) {
                    $user_info['uid'] = $user_key_data['uid'];
                }
                
                if (!empty($display_info['name'])) {
                    $user_info['name'] = $user_key_data['name'];
                }
                
                if (!empty($display_info['email'])) {
                    $user_info['email'] = $user_key_data['email'];
                }
                
                $verification_result = [
                    'success' => true,
                    'message' => '身份验证成功',
                    'user_info' => $user_info,
                    'service_provider' => $sp_key_data['sp_name'],
                    'verify_time' => $current_time
                ];
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
    <title>授权验证 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <nav>
                <ul>
                    <li><a href="login.php">登录</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="container">
        <div class="form-container">
            <h2 class="form-title">授权验证</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($verification_result): ?>
                <div class="alert alert-success">
                    <h3><?php echo $verification_result['message']; ?></h3>
                    <p>验证时间: <?php echo $verification_result['verify_time']; ?></p>
                    <p>服务商: <?php echo $verification_result['service_provider']; ?></p>
                    
                    <h4 class="mt-2">用户信息:</h4>
                    <ul>
                        <?php foreach ($verification_result['user_info'] as $key => $value): ?>
                            <li><?php echo ucfirst($key); ?>: <?php echo htmlspecialchars($value); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="text-center mt-3">
                    <a href="auth_verify.php" class="btn">进行新的验证</a>
                </div>
            <?php else: ?>
                <form method="post">
                    <div class="form-group">
                        <label for="sp_key">服务商密钥</label>
                        <input type="text" id="sp_key" name="sp_key" class="form-control" required placeholder="输入服务商密钥">
                    </div>
                    
                    <div class="form-group">
                        <label for="user_key">用户密钥</label>
                        <input type="text" id="user_key" name="user_key" class="form-control" required placeholder="输入用户密钥">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-block">验证身份</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - 版权所有</p>
        </div>
    </footer>
</body>
</html>
