<?php
require_once 'jc.php';

$message = '';
$user_data = null;

// 处理验证请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify'])) {
    $provider_key = trim($_POST['provider_key']);
    $user_key = trim($_POST['user_key']);
    
    if (empty($provider_key) || empty($user_key)) {
        $message = "服务商密钥和用户密钥都不能为空";
    } else {
        $conn = db_connect();
        
        // 验证服务商密钥
        $stmt = $conn->prepare("
            SELECT pk.service_provider_id, sp.name as provider_name 
            FROM provider_keys pk
            JOIN service_providers sp ON pk.service_provider_id = sp.id
            WHERE pk.key_value = ? AND pk.expires_at > NOW() AND sp.status = 'active'
        ");
        $stmt->bind_param("s", $provider_key);
        $stmt->execute();
        $provider_result = $stmt->get_result();
        
        if ($provider_result->num_rows == 1) {
            $provider_data = $provider_result->fetch_assoc();
            $stmt->close();
            
            // 验证用户密钥
            $stmt = $conn->prepare("
                SELECT uk.id as key_id, uk.user_id, uk.used, 
                       u.uid, u.name, u.email, u.qq, u.role_id,
                       uv.show_name, uv.show_email, uv.show_qq, uv.show_role
                FROM user_keys uk
                JOIN users u ON uk.user_id = u.id
                JOIN user_key_visibility uv ON u.id = uv.user_id
                WHERE uk.key_value = ? AND u.status = 'normal'
            ");
            $stmt->bind_param("s", $user_key);
            $stmt->execute();
            $user_result = $stmt->get_result();
            
            if ($user_result->num_rows == 1) {
                $key_data = $user_result->fetch_assoc();
                $stmt->close();
                
                if ($key_data['used']) {
                    $message = "用户密钥已被使用，只能使用一次";
                } else {
                    // 标记密钥为已使用
                    $stmt = $conn->prepare("
                        UPDATE user_keys 
                        SET used = 1, used_at = NOW(), used_by_provider_id = ? 
                        WHERE id = ?
                    ");
                    $stmt->bind_param("ii", $provider_data['service_provider_id'], $key_data['key_id']);
                    $stmt->execute();
                    $stmt->close();
                    
                    // 准备要返回的用户数据（根据用户设置的可见性）
                    $role_name = get_user_role_name($key_data['role_id']);
                    $role_display_name = get_role_display_name($role_name);
                    
                    $user_data = [
                        'uid' => $key_data['uid'],
                        'name' => $key_data['show_name'] ? $key_data['name'] : null,
                        'email' => $key_data['show_email'] ? $key_data['email'] : null,
                        'qq' => $key_data['show_qq'] ? $key_data['qq'] : null,
                        'role' => $key_data['show_role'] ? $role_display_name : null,
                        'verified' => true,
                        'verified_by' => $provider_data['provider_name'],
                        'verified_time' => format_shanghai_time(date('Y-m-d H:i:s'))
                    ];
                    
                    $message = "验证成功";
                }
            } else {
                $message = "无效的用户密钥或用户账号状态异常";
            }
        } else {
            $message = "无效的服务商密钥或服务商状态异常";
        }
        
        $conn->close();
    }
}

// 检查是否启用了授权验证页面
$auth_enabled = get_system_setting('auth_enabled') !== '0';
if (!$auth_enabled) {
    $message = "授权验证功能已关闭";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 授权验证</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <nav class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3">
            <a href="auth_verify.php" class="text-xl font-bold"><?php echo SITE_NAME; ?> - 授权验证</a>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8 flex-grow">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold mb-4">用户身份验证</h2>
                
                <?php if ($message): ?>
                    <div class="mb-4 p-3 rounded <?php echo $user_data ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($auth_enabled): ?>
                    <form method="post">
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="provider_key">
                                服务商密钥
                            </label>
                            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="provider_key" type="text" name="provider_key" required>
                        </div>
                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="user_key">
                                用户密钥
                            </label>
                            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="user_key" type="text" name="user_key" required>
                        </div>
                        <div class="flex items-center justify-between">
                            <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="verify">
                                验证
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <?php if ($user_data && $user_data['verified']): ?>
                <div class="bg-white shadow-md rounded-lg p-6">
                    <h2 class="text-xl font-bold mb-4">验证结果</h2>
                    
                    <div class="space-y-3">
                        <div>
                            <p class="text-gray-700"><strong>用户唯一标识 (UID)：</strong><?php echo htmlspecialchars($user_data['uid']); ?></p>
                        </div>
                        
                        <?php if ($user_data['name']): ?>
                            <div>
                                <p class="text-gray-700"><strong>姓名：</strong><?php echo htmlspecialchars($user_data['name']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($user_data['email']): ?>
                            <div>
                                <p class="text-gray-700"><strong>邮箱：</strong><?php echo htmlspecialchars($user_data['email']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($user_data['qq']): ?>
                            <div>
                                <p class="text-gray-700"><strong>QQ号：</strong><?php echo htmlspecialchars($user_data['qq']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($user_data['role']): ?>
                            <div>
                                <p class="text-gray-700"><strong>身份：</strong><?php echo $user_data['role']; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <p class="text-gray-700"><strong>验证时间：</strong><?php echo $user_data['verified_time']; ?></p>
                        </div>
                        
                        <div>
                            <p class="text-gray-700"><strong>验证方：</strong><?php echo htmlspecialchars($user_data['verified_by']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
    