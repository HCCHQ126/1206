<?php
require_once 'jc.php';
$user = require_login();

// 获取用户角色名称
$role_name = get_user_role_name($user['role_id']);
$role_display_name = get_role_display_name($role_name);

// 处理账号冻结请求
$freeze_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['freeze_account'])) {
    $conn = db_connect();
    $stmt = $conn->prepare("UPDATE users SET status = 'frozen' WHERE id = ?");
    $stmt->bind_param("i", $user['id']);
    
    if ($stmt->execute()) {
        $freeze_message = "账号已成功冻结";
        // 登出用户
        clear_user_cookie();
        header('Refresh: 2; URL=index.php');
    } else {
        $freeze_message = "冻结账号失败，请稍后再试";
    }
    
    $stmt->close();
    $conn->close();
}

// 处理信息修改请求
$profile_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $qq = trim($_POST['qq']);
    
    if (empty($name) || empty($qq)) {
        $profile_message = "姓名和QQ号不能为空";
    } elseif (!is_numeric($qq) || strlen($qq) < 5) {
        $profile_message = "请输入有效的QQ号";
    } else {
        $conn = db_connect();
        
        // 检查QQ是否已被其他用户使用
        $stmt = $conn->prepare("SELECT id FROM users WHERE qq = ? AND id != ?");
        $stmt->bind_param("si", $qq, $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $profile_message = "该QQ号已被使用";
            $stmt->close();
        } else {
            $stmt->close();
            
            $stmt = $conn->prepare("UPDATE users SET name = ?, qq = ? WHERE id = ?");
            $stmt->bind_param("ssi", $name, $qq, $user['id']);
            
            if ($stmt->execute()) {
                $profile_message = "个人信息更新成功";
                // 更新当前用户信息
                $user['name'] = $name;
                $user['qq'] = $qq;
            } else {
                $profile_message = "更新失败，请稍后再试";
            }
            
            $stmt->close();
        }
        
        $conn->close();
    }
}

// 处理密码修改请求
$password_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_message = "所有字段都是必填的";
    } elseif ($new_password != $confirm_password) {
        $password_message = "两次输入的新密码不一致";
    } elseif (strlen($new_password) < 6) {
        $password_message = "新密码长度至少为6位";
    } else {
        $conn = db_connect();
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        if (verify_password($current_password, $user_data['password'])) {
            $encrypted_password = encrypt_password($new_password);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $encrypted_password, $user['id']);
            
            if ($stmt->execute()) {
                $password_message = "密码修改成功，请重新登录";
                // 登出用户
                clear_user_cookie();
                header('Refresh: 2; URL=index.php');
            } else {
                $password_message = "密码修改失败，请稍后再试";
            }
        } else {
            $password_message = "当前密码不正确";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// 处理用户密钥生成请求
$key_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_key'])) {
    $message = trim($_POST['key_message']);
    $show_name = isset($_POST['show_name']) ? 1 : 0;
    $show_email = isset($_POST['show_email']) ? 1 : 0;
    $show_qq = isset($_POST['show_qq']) ? 1 : 0;
    $show_role = isset($_POST['show_role']) ? 1 : 0;
    
    // 更新可见性设置
    $conn = db_connect();
    $stmt = $conn->prepare("
        UPDATE user_key_visibility 
        SET show_name = ?, show_email = ?, show_qq = ?, show_role = ? 
        WHERE user_id = ?
    ");
    $stmt->bind_param("iiiis", $show_name, $show_email, $show_qq, $show_role, $user['id']);
    $stmt->execute();
    $stmt->close();
    
    // 生成新的用户密钥
    $key_value = bin2hex(random_bytes(16));
    
    $stmt = $conn->prepare("INSERT INTO user_keys (user_id, key_value, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user['id'], $key_value, $message);
    
    if ($stmt->execute()) {
        $key_message = "用户密钥生成成功：<strong>$key_value</strong><br>请妥善保存，密钥只能使用一次";
    } else {
        $key_message = "生成密钥失败，请稍后再试";
    }
    
    $stmt->close();
    $conn->close();
}

// 获取用户最近登录记录
$login_logs = [];
$conn = db_connect();
$stmt = $conn->prepare("SELECT login_time, ip_address FROM login_logs WHERE user_id = ? ORDER BY login_time DESC LIMIT 10");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $login_logs[] = [
        'time' => format_shanghai_time($row['login_time']),
        'ip' => $row['ip_address']
    ];
}

$stmt->close();

// 获取用户密钥查询记录
$key_logs = [];
$stmt = $conn->prepare("
    SELECT uk.used_at, sp.name 
    FROM user_keys uk
    JOIN service_providers sp ON uk.used_by_provider_id = sp.id
    WHERE uk.user_id = ? AND uk.used = 1
    ORDER BY uk.used_at DESC
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $key_logs[] = [
        'time' => format_shanghai_time($row['used_at']),
        'provider' => $row['name']
    ];
}

$stmt->close();
$conn->close();

// 获取用户密钥可见性设置
$visibility_settings = [
    'show_name' => 0,
    'show_email' => 0,
    'show_qq' => 0,
    'show_role' => 0
];
$conn = db_connect();
$stmt = $conn->prepare("SELECT show_name, show_email, show_qq, show_role FROM user_key_visibility WHERE user_id = ?");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $visibility_settings = $result->fetch_assoc();
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 用户中心</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <a href="user_center.php" class="text-xl font-bold"><?php echo SITE_NAME; ?></a>
            <div class="flex items-center space-x-4">
                <?php if (is_service_provider_admin($user)): ?>
                    <a href="service_provider.php" class="hover:text-blue-200">服务商中心</a>
                <?php endif; ?>
                <span><?php echo $user['name']; ?></span>
                <a href="logout.php" class="hover:text-blue-200">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <?php if ($freeze_message): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-6">
                <?php echo $freeze_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="border-b border-gray-200 mb-6">
            <ul class="flex -mb-px" id="tabs">
                <li class="mr-2">
                    <button class="inline-block py-4 px-4 text-blue-600 border-b-2 border-blue-500 font-semibold" data-tab="profile">个人信息</button>
                </li>
                <li class="mr-2">
                    <button class="inline-block py-4 px-4 text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 font-semibold" data-tab="login-logs">登录记录</button>
                </li>
                <li class="mr-2">
                    <button class="inline-block py-4 px-4 text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 font-semibold" data-tab="user-key">用户密钥</button>
                </li>
                <li class="mr-2">
                    <button class="inline-block py-4 px-4 text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 font-semibold" data-tab="account-settings">账号设置</button>
                </li>
            </ul>
        </div>
        
        <!-- 个人信息 -->
        <div id="profile-tab" class="tab-content">
            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold mb-4">个人基本信息</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-gray-700"><strong>姓名：</strong><?php echo htmlspecialchars($user['name']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-700"><strong>邮箱：</strong><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-700"><strong>QQ号：</strong><?php echo htmlspecialchars($user['qq']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-700"><strong>用户ID：</strong><?php echo htmlspecialchars($user['uid']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-700"><strong>身份：</strong><?php echo $role_display_name; ?></p>
                    </div>
                    <div>
                        <p class="text-gray-700"><strong>注册时间：</strong><?php echo format_shanghai_time($user['register_time']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-700"><strong>邮箱验证：</strong><?php echo $user['is_email_verified'] ? '已验证' : '未验证'; ?></p>
                    </div>
                    <div>
                        <p class="text-gray-700"><strong>账号状态：</strong><?php 
                            $status_map = ['normal' => '正常', 'locked' => '已锁定', 'restricted' => '受限', 'frozen' => '已冻结'];
                            echo $status_map[$user['status']];
                        ?></p>
                    </div>
                </div>
                
                <?php if ($profile_message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $profile_message; ?>
                    </div>
                <?php endif; ?>
                
                <h3 class="text-lg font-semibold mb-4">修改个人信息</h3>
                <form method="post" class="max-w-md">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="name">
                            姓名
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="name" type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="qq">
                            QQ号
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="qq" type="text" name="qq" value="<?php echo htmlspecialchars($user['qq']); ?>" required>
                    </div>
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="update_profile">
                            更新信息
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- 登录记录 -->
        <div id="login-logs-tab" class="tab-content hidden">
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4">最近登录记录</h2>
                
                <?php if (empty($login_logs)): ?>
                    <p class="text-gray-500">暂无登录记录</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">登录时间</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">IP地址</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($login_logs as $log): ?>
                                    <tr class="border-b border-gray-200">
                                        <td class="py-3 px-4 text-sm text-gray-700"><?php echo $log['time']; ?></td>
                                        <td class="py-3 px-4 text-sm text-gray-700"><?php echo $log['ip']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 用户密钥 -->
        <div id="user-key-tab" class="tab-content hidden">
            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold mb-4">生成用户密钥</h2>
                
                <?php if ($key_message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $key_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="max-w-md">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="key_message">
                            密钥留言（可选）
                        </label>
                        <textarea class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="key_message" name="key_message" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-2">选择向服务商展示的信息</h3>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="show_name" class="form-checkbox h-5 w-5 text-blue-600" <?php echo $visibility_settings['show_name'] ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-700">姓名</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="show_email" class="form-checkbox h-5 w-5 text-blue-600" <?php echo $visibility_settings['show_email'] ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-700">邮箱</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="show_qq" class="form-checkbox h-5 w-5 text-blue-600" <?php echo $visibility_settings['show_qq'] ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-700">QQ号</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" name="show_role" class="form-checkbox h-5 w-5 text-blue-600" <?php echo $visibility_settings['show_role'] ? 'checked' : ''; ?>>
                                <span class="ml-2 text-gray-700">身份</span>
                            </label>
                            <p class="text-sm text-gray-500 mt-2">注：用户UID将始终显示</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="generate_key">
                            生成密钥
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4">密钥查询记录</h2>
                
                <?php if (empty($key_logs)): ?>
                    <p class="text-gray-500">暂无密钥查询记录</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">查询时间</th>
                                    <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">查询服务商</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($key_logs as $log): ?>
                                    <tr class="border-b border-gray-200">
                                        <td class="py-3 px-4 text-sm text-gray-700"><?php echo $log['time']; ?></td>
                                        <td class="py-3 px-4 text-sm text-gray-700"><?php echo $log['provider']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 账号设置 -->
        <div id="account-settings-tab" class="tab-content hidden">
            <div class="bg-white shadow-md rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold mb-4">修改密码</h2>
                
                <?php if ($password_message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?php echo $password_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" class="max-w-md">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="current_password">
                            当前密码
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="current_password" type="password" name="current_password" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="new_password">
                            新密码
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="new_password" type="password" name="new_password" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">
                            确认新密码
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="confirm_password" type="password" name="confirm_password" required>
                    </div>
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="change_password">
                            修改密码
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4">账号冻结</h2>
                <p class="text-red-600 mb-4">警告：冻结账号后，您将无法登录系统，解除冻结需要验证邮箱和QQ号。</p>
                
                <form method="post" onsubmit="return confirm('确定要冻结账号吗？');">
                    <button class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="freeze_account">
                        冻结我的账号
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 标签切换功能
        document.querySelectorAll('#tabs button').forEach(button => {
            button.addEventListener('click', () => {
                // 移除所有标签的活跃状态
                document.querySelectorAll('#tabs button').forEach(btn => {
                    btn.classList.remove('text-blue-600', 'border-blue-500');
                    btn.classList.add('text-gray-500', 'border-transparent', 'hover:text-gray-700', 'hover:border-gray-300');
                });
                
                // 添加当前标签的活跃状态
                button.classList.remove('text-gray-500', 'border-transparent', 'hover:text-gray-700', 'hover:border-gray-300');
                button.classList.add('text-blue-600', 'border-blue-500');
                
                // 隐藏所有内容
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                });
                
                // 显示当前内容
                const tabId = button.getAttribute('data-tab');
                document.getElementById(tabId + '-tab').classList.remove('hidden');
            });
        });
    </script>
</body>
</html>
    