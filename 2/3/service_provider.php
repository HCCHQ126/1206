<?php
require 'jc.php';

// 检查是否为服务商
checkAccess(['service_provider', 'special_authorized_provider']);
$user = getCurrentUser();

// 获取服务商信息
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM service_providers WHERE manager1_id = :user_id OR manager2_id = :user_id");
$stmt->bindParam(':user_id', $user['id']);
$stmt->execute();
$provider = $stmt->fetch();

if (!$provider) {
    die("您不是任何服务商的管理员");
}

$error = '';
$success = '';

// 处理生成服务商密钥
if (isset($_POST['generate_provider_key'])) {
    $duration = $_POST['duration'];
    
    // 计算过期时间
    $expireTime = '';
    switch ($duration) {
        case '2h':
            $expireTime = date('Y-m-d H:i:s', strtotime('+2 hours'));
            break;
        case '6h':
            $expireTime = date('Y-m-d H:i:s', strtotime('+6 hours'));
            break;
        case '18d':
            $expireTime = date('Y-m-d H:i:s', strtotime('+18 days'));
            break;
        case '30d':
            $expireTime = date('Y-m-d H:i:s', strtotime('+30 days'));
            break;
        default:
            $error = '无效的有效期';
    }
    
    if (!$error) {
        $keyValue = generateRandomKey();
        $createdTime = getShanghaiTime();
        
        $stmt = $pdo->prepare("INSERT INTO provider_keys (provider_id, key_value, duration, expire_time, created_time, created_by) 
                              VALUES (:provider_id, :key_value, :duration, :expire_time, :created_time, :created_by)");
        $stmt->bindParam(':provider_id', $provider['id']);
        $stmt->bindParam(':key_value', $keyValue);
        $stmt->bindParam(':duration', $duration);
        $stmt->bindParam(':expire_time', $expireTime);
        $stmt->bindParam(':created_time', $createdTime);
        $stmt->bindParam(':created_by', $user['id']);
        $stmt->execute();
        
        $success = '服务商密钥生成成功';
    }
}

// 获取服务商密钥列表
$stmt = $pdo->prepare("SELECT pk.*, u.name as creator_name FROM provider_keys pk
                      JOIN users u ON pk.created_by = u.id
                      WHERE provider_id = :provider_id ORDER BY created_time DESC");
$stmt->bindParam(':provider_id', $provider['id']);
$stmt->execute();
$providerKeys = $stmt->fetchAll();

// 获取密钥查询记录
$stmt = $pdo->prepare("SELECT kq.*, uk.key_value as user_key, uk.user_id, u.uid, u.name as user_name, pk.key_value as provider_key
                      FROM key_queries kq
                      JOIN user_keys uk ON kq.user_key_id = uk.id
                      JOIN users u ON uk.user_id = u.id
                      JOIN provider_keys pk ON kq.provider_key_id = pk.id
                      WHERE pk.provider_id = :provider_id
                      ORDER BY kq.query_time DESC");
$stmt->bindParam(':provider_id', $provider['id']);
$stmt->execute();
$queryRecords = $stmt->fetchAll();

// 登出功能
if (isset($_GET['logout'])) {
    setcookie('user_id', '', time() - 3600, '/');
    setcookie('user_token', '', time() - 3600, '/');
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服务商中心</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f0f0;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
        }
        .tab.active {
            border-bottom: 3px solid #4CAF50;
            color: #4CAF50;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
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
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .status {
            padding: 3px 8px;
            border-radius: 4px;
            color: white;
            font-size: 12px;
        }
        .status-available {
            background-color: #4CAF50;
        }
        .status-locked {
            background-color: #f44336;
        }
        .status-restricted {
            background-color: #ff9800;
        }
        .status-active {
            background-color: #4CAF50;
        }
        .status-expired {
            background-color: #f44336;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>服务商中心 - <?php echo $provider['provider_name']; ?></h2>
            <button class="secondary" onclick="window.location.href='?logout'">退出登录</button>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="tabs">
            <button class="tab active" onclick="openTab(event, 'provider-info')">服务商信息</button>
            <button class="tab" onclick="openTab(event, 'provider-keys')">服务商密钥</button>
            <button class="tab" onclick="openTab(event, 'query-records')">查询记录</button>
        </div>
        
        <!-- 服务商信息 -->
        <div id="provider-info" class="tab-content active">
            <h3>服务商信息</h3>
            <div class="info-item">
                <strong>服务商名称：</strong> <?php echo $provider['provider_name']; ?>
            </div>
            <div class="info-item">
                <strong>状态：</strong> 
                <span class="status status-<?php echo $provider['status']; ?>">
                    <?php 
                        $statusMap = [
                            'available' => '可用',
                            'locked' => '锁定',
                            'restricted' => '限制'
                        ];
                        echo $statusMap[$provider['status']];
                    ?>
                </span>
            </div>
            <div class="info-item">
                <strong>管理员1：</strong>
                <?php 
                    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
                    $stmt->bindParam(':id', $provider['manager1_id']);
                    $stmt->execute();
                    $manager1 = $stmt->fetch();
                    echo $manager1['name'];
                ?>
            </div>
            <div class="info-item">
                <strong>管理员2：</strong>
                <?php 
                    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = :id");
                    $stmt->bindParam(':id', $provider['manager2_id']);
                    $stmt->execute();
                    $manager2 = $stmt->fetch();
                    echo $manager2['name'];
                ?>
            </div>
            <div class="info-item">
                <strong>创建时间：</strong> <?php echo $provider['created_time']; ?>
            </div>
            <div class="info-item">
                <strong>最近更新：</strong> <?php echo $provider['updated_time']; ?>
            </div>
        </div>
        
        <!-- 服务商密钥 -->
        <div id="provider-keys" class="tab-content">
            <h3>服务商密钥管理</h3>
            <h4>生成新密钥</h4>
            <form method="post">
                <div class="form-group">
                    <label for="duration">密钥有效期</label>
                    <select id="duration" name="duration" required>
                        <option value="2h">2小时</option>
                        <option value="6h">6小时</option>
                        <option value="18d">18天</option>
                        <option value="30d">30天</option>
                    </select>
                </div>
                <button type="submit" name="generate_provider_key">生成密钥</button>
            </form>
            
            <h4>密钥列表</h4>
            <table>
                <tr>
                    <th>密钥值</th>
                    <th>有效期</th>
                    <th>过期时间</th>
                    <th>创建人</th>
                    <th>创建时间</th>
                    <th>状态</th>
                </tr>
                <?php if (empty($providerKeys)): ?>
                    <tr>
                        <td colspan="6">暂无密钥</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($providerKeys as $key): ?>
                        <tr>
                            <td><?php echo $key['key_value']; ?></td>
                            <td><?php 
                                $durationMap = [
                                    '2h' => '2小时',
                                    '6h' => '6小时',
                                    '18d' => '18天',
                                    '30d' => '30天'
                                ];
                                echo $durationMap[$key['duration']];
                            ?></td>
                            <td><?php echo $key['expire_time']; ?></td>
                            <td><?php echo $key['creator_name']; ?></td>
                            <td><?php echo $key['created_time']; ?></td>
                            <td>
                                <?php 
                                    $now = getShanghaiTime();
                                    $status = strtotime($key['expire_time']) > strtotime($now) ? 'active' : 'expired';
                                    $statusText = $status == 'active' ? '有效' : '已过期';
                                ?>
                                <span class="status status-<?php echo $status; ?>"><?php echo $statusText; ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
        
        <!-- 查询记录 -->
        <div id="query-records" class="tab-content">
            <h3>密钥查询记录</h3>
            <table>
                <tr>
                    <th>查询时间</th>
                    <th>用户ID</th>
                    <th>用户姓名</th>
                    <th>用户密钥</th>
                    <th>服务商密钥</th>
                </tr>
                <?php if (empty($queryRecords)): ?>
                    <tr>
                        <td colspan="5">暂无查询记录</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($queryRecords as $record): ?>
                        <tr>
                            <td><?php echo $record['query_time']; ?></td>
                            <td><?php echo $record['uid']; ?></td>
                            <td><?php echo $record['user_name']; ?></td>
                            <td><?php echo $record['user_key']; ?></td>
                            <td><?php echo $record['provider_key']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove("active");
            }
            tablinks = document.getElementsByClassName("tab");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove("active");
            }
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>
