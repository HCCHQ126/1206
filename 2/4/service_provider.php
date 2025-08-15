<?php
require_once 'jc.php';
$user = require_service_provider();

// 获取服务商信息
$service_provider = null;
$conn = db_connect();
$stmt = $conn->prepare("
    SELECT sp.* 
    FROM service_providers sp
    JOIN service_provider_admins spa ON sp.id = spa.service_provider_id
    WHERE spa.user_id = ?
");
$stmt->bind_param("i", $user['id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $service_provider = $result->fetch_assoc();
}

$stmt->close();
$conn->close();

if (!$service_provider) {
    die("无法获取服务商信息");
}

// 处理生成服务商密钥请求
$key_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_provider_key'])) {
    $expiry_duration = $_POST['expiry_duration'];
    
    if (!in_array($expiry_duration, ['2h', '6h', '18d', '30d'])) {
        $key_message = "无效的有效期";
    } else {
        // 计算过期时间
        $expires_at = '';
        switch ($expiry_duration) {
            case '2h':
                $expires_at = date('Y-m-d H:i:s', strtotime('+2 hours'));
                break;
            case '6h':
                $expires_at = date('Y-m-d H:i:s', strtotime('+6 hours'));
                break;
            case '18d':
                $expires_at = date('Y-m-d H:i:s', strtotime('+18 days'));
                break;
            case '30d':
                $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
                break;
        }
        
        // 生成密钥
        $key_value = bin2hex(random_bytes(16));
        
        $conn = db_connect();
        $stmt = $conn->prepare("
            INSERT INTO provider_keys (service_provider_id, key_value, expiry_duration, expires_at, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isssi", $service_provider['id'], $key_value, $expiry_duration, $expires_at, $user['id']);
        
        if ($stmt->execute()) {
            $key_message = "服务商密钥生成成功：<strong>$key_value</strong><br>有效期至：$expires_at";
        } else {
            $key_message = "生成密钥失败，请稍后再试";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// 获取服务商的密钥列表
$provider_keys = [];
$conn = db_connect();
$stmt = $conn->prepare("
    SELECT id, key_value, expiry_duration, created_at, expires_at 
    FROM provider_keys 
    WHERE service_provider_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $service_provider['id']);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $provider_keys[] = [
        'id' => $row['id'],
        'key_value' => substr($row['key_value'], 0, 8) . '...' . substr($row['key_value'], -8),
        'full_key' => $row['key_value'],
        'expiry_duration' => $row['expiry_duration'],
        'created_at' => format_shanghai_time($row['created_at']),
        'expires_at' => format_shanghai_time($row['expires_at']),
        'is_expired' => strtotime($row['expires_at']) < time()
    ];
}

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 服务商中心</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <a href="service_provider.php" class="text-xl font-bold"><?php echo SITE_NAME; ?> - 服务商中心</a>
            <div class="flex items-center space-x-4">
                <a href="user_center.php" class="hover:text-blue-200">用户中心</a>
                <span><?php echo $user['name']; ?></span>
                <a href="logout.php" class="hover:text-blue-200">退出登录</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-4 py-8">
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">服务商信息</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <p class="text-gray-700"><strong>服务商名称：</strong><?php echo htmlspecialchars($service_provider['name']); ?></p>
                </div>
                <div>
                    <p class="text-gray-700"><strong>状态：</strong><?php 
                        $status_map = ['active' => '可用', 'locked' => '已锁定', 'restricted' => '受限'];
                        echo $status_map[$service_provider['status']];
                    ?></p>
                </div>
                <div>
                    <p class="text-gray-700"><strong>创建时间：</strong><?php echo format_shanghai_time($service_provider['created_at']); ?></p>
                </div>
            </div>
            
            <?php if (!empty($service_provider['description'])): ?>
                <div>
                    <p class="text-gray-700"><strong>描述：</strong><?php echo nl2br(htmlspecialchars($service_provider['description'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">生成服务商密钥</h2>
            
            <?php if ($key_message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $key_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="post" class="max-w-md">
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="expiry_duration">
                        密钥有效期
                    </label>
                    <select class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="expiry_duration" name="expiry_duration" required>
                        <option value="2h">2小时</option>
                        <option value="6h">6小时</option>
                        <option value="18d">18天</option>
                        <option value="30d">30天</option>
                    </select>
                </div>
                
                <div class="flex items-center justify-between">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="generate_provider_key">
                        生成密钥
                    </button>
                </div>
            </form>
        </div>
        
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-bold mb-4">服务商密钥列表</h2>
            
            <?php if (empty($provider_keys)): ?>
                <p class="text-gray-500">暂无服务商密钥</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">密钥</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">有效期</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">创建时间</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">过期时间</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($provider_keys as $key): ?>
                                <tr class="border-b border-gray-200">
                                    <td class="py-3 px-4 text-sm text-gray-700">
                                        <div class="flex items-center">
                                            <span><?php echo $key['key_value']; ?></span>
                                            <button class="ml-2 text-blue-600 hover:text-blue-800 text-xs" onclick="copyKey('<?php echo $key['full_key']; ?>')">复制</button>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-700"><?php 
                                        $duration_map = ['2h' => '2小时', '6h' => '6小时', '18d' => '18天', '30d' => '30天'];
                                        echo $duration_map[$key['expiry_duration']];
                                    ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-700"><?php echo $key['created_at']; ?></td>
                                    <td class="py-3 px-4 text-sm text-gray-700"><?php echo $key['expires_at']; ?></td>
                                    <td class="py-3 px-4 text-sm <?php echo $key['is_expired'] ? 'text-red-600' : 'text-green-600'; ?>">
                                        <?php echo $key['is_expired'] ? '已过期' : '有效'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    
    </div>

    <script>
        // 复制密钥到剪贴板
        function copyKey(key) {
            navigator.clipboard.writeText(key).then(() => {
                alert('密钥已复制到剪贴板');
            }).catch(err => {
                console.error('无法复制文本: ', err);
                alert('复制失败，请手动复制');
            });
        }
    </script>
</body>
</html>
    