<?php
require_once 'functions.php';

// 检查用户是否登录且为服务商
if (!is_logged_in() || !is_service_provider()) {
    header('Location: login.php');
    exit;
}

// 获取当前服务商用户信息
$user = get_current_user();
if (!$user) {
    clear_user_cookie();
    header('Location: login.php');
    exit;
}

// 获取服务商信息
$service_provider = null;
global $pdo;

// 查找用户所属的服务商
$stmt = $pdo->prepare("SELECT sp.* FROM service_providers sp
                      JOIN service_provider_admins spa ON sp.id = spa.sp_id
                      WHERE spa.uid = :uid");
$stmt->bindParam(':uid', $user['uid']);
$stmt->execute();
$service_provider = $stmt->fetch();

if (!$service_provider) {
    $message = '您不是任何服务商的管理员';
    $message_type = 'danger';
}

$message = '';
$message_type = '';
$current_page = isset($_GET['page']) ? $_GET['page'] : 'keys';

// 生成服务商密钥
if (isset($_POST['generate_sp_key']) && $service_provider) {
    $expiry_option = $_POST['expiry_option'] ?? 7200; // 默认2小时
    
    // 验证有效期选项是否有效
    global $key_expiry_options;
    if (!in_array($expiry_option, array_keys($key_expiry_options))) {
        $expiry_option = 7200;
    }
    
    $key = generate_key(PROVIDER_KEY_LENGTH);
    $created_at = date('Y-m-d H:i:s');
    $expiry_time = date('Y-m-d H:i:s', strtotime("+$expiry_option seconds"));
    
    $stmt = $pdo->prepare("INSERT INTO service_provider_keys (sp_id, `key`, expiry_time, created_at) 
                          VALUES (:sp_id, :key, :expiry_time, :created_at)");
    $stmt->bindParam(':sp_id', $service_provider['id']);
    $stmt->bindParam(':key', $key);
    $stmt->bindParam(':expiry_time', $expiry_time);
    $stmt->bindParam(':created_at', $created_at);
    
    if ($stmt->execute()) {
        $message = '服务商密钥生成成功：' . $key . '（有效期：' . $key_expiry_options[$expiry_option] . '）';
        $message_type = 'success';
    } else {
        $message = '服务商密钥生成失败，请稍后再试';
        $message_type = 'danger';
    }
}

// 获取服务商密钥记录
$sp_keys = [];
if ($service_provider) {
    $stmt = $pdo->prepare("SELECT * FROM service_provider_keys WHERE sp_id = :sp_id ORDER BY created_at DESC");
    $stmt->bindParam(':sp_id', $service_provider['id']);
    $stmt->execute();
    $sp_keys = $stmt->fetchAll();
    
    // 更新过期密钥状态
    $current_time = date('Y-m-d H:i:s');
    foreach ($sp_keys as &$key) {
        if ($key['status'] == 'valid' && $key['expiry_time'] < $current_time) {
            $stmt = $pdo->prepare("UPDATE service_provider_keys SET status = 'expired' WHERE id = :id");
            $stmt->bindParam(':id', $key['id']);
            $stmt->execute();
            $key['status'] = 'expired';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>服务商中心 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <nav>
                <ul>
                    <li><a href="service_provider.php" class="active">服务商中心</a></li>
                    <li><a href="user_center.php">个人中心</a></li>
                    <li><a href="logout.php">退出登录</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="container">
        <h2 class="mt-3 mb-3">服务商中心</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (!$service_provider): ?>
            <div class="alert alert-danger"><?php echo $message; ?></div>
        <?php else: ?>
            <p>当前服务商: <?php echo htmlspecialchars($service_provider['name']); ?> (状态: <?php 
                switch($service_provider['status']) {
                    case 'available': echo '<span class="text-success">可用</span>'; break;
                    case 'locked': echo '<span class="text-danger">锁定</span>'; break;
                    case 'restricted': echo '<span class="text-warning">限制</span>'; break;
                }
            ?>)</p>
            
            <ul class="nav-tabs">
                <li <?php echo $current_page == 'keys' ? 'class="active"' : ''; ?>>
                    <a href="service_provider.php?page=keys">服务商密钥</a>
                </li>
                <li <?php echo $current_page == 'verify' ? 'class="active"' : ''; ?>>
                    <a href="service_provider.php?page=verify">身份验证</a>
                </li>
                <li <?php echo $current_page == 'history' ? 'class="active"' : ''; ?>>
                    <a href="service_provider.php?page=history">验证历史</a>
                </li>
            </ul>
            
            <!-- 服务商密钥页面 -->
            <?php if ($current_page == 'keys'): ?>
                <div class="card">
                    <h3 class="card-title">服务商密钥管理</h3>
                    <p>生成用于验证用户身份的服务商密钥</p>
                    
                    <form method="post" class="mb-3">
                        <div class="form-group">
                            <label for="expiry_option">密钥有效期</label>
                            <select id="expiry_option" name="expiry_option" class="form-control">
                                <?php global $key_expiry_options; ?>
                                <?php foreach ($key_expiry_options as $value => $label): ?>
                                    <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="generate_sp_key" class="btn">生成新密钥</button>
                        </div>
                    </form>
                    
                    <h4>密钥记录</h4>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>创建时间</th>
                                <th>有效期至</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sp_keys)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">暂无密钥记录</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sp_keys as $key): ?>
                                    <tr>
                                        <td><?php echo $key['created_at']; ?></td>
                                        <td><?php echo $key['expiry_time']; ?></td>
                                        <td><?php echo $key['status'] == 'valid' ? '<span class="text-success">有效</span>' : '<span class="text-danger">已过期</span>'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- 身份验证页面 -->
            <?php if ($current_page == 'verify'): ?>
                <div class="card">
                    <h3 class="card-title">用户身份验证</h3>
                    <p>输入服务商密钥和用户密钥验证用户身份</p>
                    
                    <form method="post" id="verifyForm">
                        <div class="form-group">
                            <label for="sp_key">服务商密钥</label>
                            <input type="text" id="sp_key" name="sp_key" class="form-control" required placeholder="输入您的服务商密钥">
                        </div>
                        
                        <div class="form-group">
                            <label for="user_key">用户密钥</label>
                            <input type="text" id="user_key" name="user_key" class="form-control" required placeholder="输入用户提供的密钥">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn">验证身份</button>
                        </div>
                    </form>
                    
                    <div id="verificationResult" class="mt-3" style="display: none;"></div>
                </div>
            <?php endif; ?>
            
            <!-- 验证历史页面 -->
            <?php if ($current_page == 'history'): ?>
                <div class="card">
                    <h3 class="card-title">验证历史记录</h3>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>验证时间</th>
                                <th>用户UID</th>
                                <th>用户姓名</th>
                                <th>验证结果</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-center">验证历史记录将在这里显示</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    
    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - 版权所有</p>
        </div>
    </footer>
    
    <script>
        // 导航标签切换
        document.querySelectorAll('.nav-tabs a').forEach(link => {
            link.addEventListener('click', function(e) {
                // 不需要阻止默认行为，因为是链接跳转
            });
        });
        
        // 身份验证表单提交
        document.getElementById('verifyForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // 在实际应用中，这里应该通过AJAX发送验证请求到服务器
            const spKey = document.getElementById('sp_key').value;
            const userKey = document.getElementById('user_key').value;
            const resultDiv = document.getElementById('verificationResult');
            
            // 模拟验证结果
            if (spKey && userKey) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h4>验证成功</h4>
                        <p>用户UID: HCCWUD-8f7e6d5c</p>
                        <p>用户姓名: 张三</p>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h4>验证失败</h4>
                        <p>无效的密钥，请检查后重试</p>
                    </div>
                `;
            }
            
            resultDiv.style.display = 'block';
        });
    </script>
</body>
</html>
