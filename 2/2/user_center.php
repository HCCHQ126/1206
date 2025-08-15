<?php
require_once 'functions.php';

// 替换原来的 get_current_user() 函数
function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    global $pdo;
    $uid = $_COOKIE['uid'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE uid = :uid");
    $stmt->bindParam(':uid', $uid);
    $stmt->execute();
    return $stmt->fetch();
}

// 同时需要修改调用这个函数的地方
function is_admin($user = null) {
    if ($user === null) {
        $user = get_logged_in_user(); // 这里也需要修改
    }
    return $user && ($user['identity'] == IDENTITY_ADMIN || $user['identity'] == IDENTITY_SUPER_ADMIN);
}

// 其他调用处也需要相应修改
$message = '';
$message_type = '';

// 处理密码修改
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = '请填写所有字段';
        $message_type = 'danger';
    } elseif (!verify_password($current_password, $user['password'])) {
        $message = '当前密码不正确';
        $message_type = 'danger';
    } elseif ($new_password != $confirm_password) {
        $message = '两次输入的新密码不一致';
        $message_type = 'danger';
    } elseif (strlen($new_password) < 6) {
        $message = '新密码长度至少为6位';
        $message_type = 'danger';
    } else {
        global $pdo;
        $new_password_hash = encrypt_password($new_password);
        
        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE uid = :uid");
        $stmt->bindParam(':password', $new_password_hash);
        $stmt->bindParam(':uid', $user['uid']);
        
        if ($stmt->execute()) {
            $message = '密码修改成功';
            $message_type = 'success';
        } else {
            $message = '密码修改失败，请稍后再试';
            $message_type = 'danger';
        }
    }
}

// 处理个人信息修改
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $qq = trim($_POST['qq'] ?? '');
    
    if (empty($name) || empty($qq)) {
        $message = '请填写所有字段';
        $message_type = 'danger';
    } else {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE users SET name = :name, qq = :qq WHERE uid = :uid");
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':qq', $qq);
        $stmt->bindParam(':uid', $user['uid']);
        
        if ($stmt->execute()) {
            $message = '个人信息修改成功';
            $message_type = 'success';
            // 更新当前用户信息
            $user['name'] = $name;
            $user['qq'] = $qq;
        } else {
            $message = '个人信息修改失败，请稍后再试';
            $message_type = 'danger';
        }
    }
}

// 处理账号冻结
if (isset($_POST['freeze_account'])) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE uid = :uid");
    $stmt->bindParam(':status', STATUS_FROZEN);
    $stmt->bindParam(':uid', $user['uid']);
    
    if ($stmt->execute()) {
        clear_user_cookie();
        $message = '账号已冻结，请使用邮箱和QQ号进行解锁';
        $message_type = 'success';
        header('Location: login.php?frozen=1');
        exit;
    } else {
        $message = '账号冻结失败，请稍后再试';
        $message_type = 'danger';
    }
}

// 生成用户密钥
if (isset($_POST['generate_key'])) {
    $message_key = trim($_POST['message_key'] ?? '');
    $display_uid = isset($_POST['display_uid']) ? 1 : 0;
    $display_name = isset($_POST['display_name']) ? 1 : 0;
    $display_email = isset($_POST['display_email']) ? 1 : 0;
    
    // UID必须显示
    $display_info = json_encode([
        'uid' => 1,
        'name' => $display_name,
        'email' => $display_email
    ]);
    
    $key = generate_key(USER_KEY_LENGTH);
    $created_at = date('Y-m-d H:i:s');
    
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO user_keys (uid, `key`, message, display_info, created_at) 
                          VALUES (:uid, :key, :message, :display_info, :created_at)");
    $stmt->bindParam(':uid', $user['uid']);
    $stmt->bindParam(':key', $key);
    $stmt->bindParam(':message', $message_key);
    $stmt->bindParam(':display_info', $display_info);
    $stmt->bindParam(':created_at', $created_at);
    
    if ($stmt->execute()) {
        $message = '用户密钥生成成功：' . $key . '（请妥善保存，仅显示一次）';
        $message_type = 'success';
    } else {
        $message = '用户密钥生成失败，请稍后再试';
        $message_type = 'danger';
    }
}

// 获取用户登录记录
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM login_records WHERE uid = :uid ORDER BY login_time DESC LIMIT 10");
$stmt->bindParam(':uid', $user['uid']);
$stmt->execute();
$login_records = $stmt->fetchAll();

// 获取用户密钥记录
$stmt = $pdo->prepare("SELECT uk.*, sp.name as sp_name FROM user_keys uk 
                      LEFT JOIN service_providers sp ON uk.used_by = sp.id
                      WHERE uk.uid = :uid ORDER BY created_at DESC");
$stmt->bindParam(':uid', $user['uid']);
$stmt->execute();
$user_keys = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人中心 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <nav>
                <ul>
                    <li><a href="user_center.php" class="active">个人中心</a></li>
                    <li><a href="logout.php">退出登录</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="container">
        <h2 class="mt-3 mb-3">个人中心</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <ul class="nav-tabs">
            <li class="active"><a href="#profile">个人信息</a></li>
            <li><a href="#password">修改密码</a></li>
            <li><a href="#login-records">登录记录</a></li>
            <li><a href="#user-keys">用户密钥</a></li>
            <li><a href="#account-settings">账号设置</a></li>
        </ul>
        
        <!-- 个人信息 -->
        <div id="profile" class="card">
            <h3 class="card-title">个人信息</h3>
            <form method="post">
                <div class="form-group">
                    <label>用户UID</label>
                    <input type="text" class="form-control" value="<?php echo $user['uid']; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>注册时间</label>
                    <input type="text" class="form-control" value="<?php echo $user['register_time']; ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label>身份</label>
                    <input type="text" class="form-control" value="<?php 
                        switch($user['identity']) {
                            case 'user': echo '普通用户'; break;
                            case 'developer': echo '开发者'; break;
                            case 'authorized_developer': echo '授权开发者'; break;
                            case 'managing_developer': echo '管理开发者'; break;
                            case 'service_provider': echo '服务商'; break;
                            case 'special_authorized_provider': echo '特别授权服务商'; break;
                            case 'admin': echo '一般管理员'; break;
                            case 'super_admin': echo '超级管理员'; break;
                        }
                    ?>" readonly>
                </div>
                
                <div class="form-group">
                    <label for="name">姓名</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">邮箱</label>
                    <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    <small class="text-muted">邮箱不可修改</small>
                </div>
                
                <div class="form-group">
                    <label for="qq">QQ号</label>
                    <input type="text" id="qq" name="qq" class="form-control" value="<?php echo htmlspecialchars($user['qq']); ?>" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="update_profile" class="btn">更新信息</button>
                </div>
            </form>
        </div>
        
        <!-- 修改密码 -->
        <div id="password" class="card" style="display: none;">
            <h3 class="card-title">修改密码</h3>
            <form method="post">
                <div class="form-group">
                    <label for="current_password">当前密码</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">确认新密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="change_password" class="btn">修改密码</button>
                </div>
            </form>
        </div>
        
        <!-- 登录记录 -->
        <div id="login-records" class="card" style="display: none;">
            <h3 class="card-title">最近登录记录</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>登录时间</th>
                        <th>登录状态</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($login_records)): ?>
                        <tr>
                            <td colspan="2" class="text-center">暂无登录记录</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($login_records as $record): ?>
                            <tr>
                                <td><?php echo $record['login_time']; ?></td>
                                <td><?php echo $record['status'] == 'success' ? '<span class="text-success">成功</span>' : '<span class="text-danger">失败</span>'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 用户密钥 -->
        <div id="user-keys" class="card" style="display: none;">
            <h3 class="card-title">用户密钥管理</h3>
            <p>生成用户密钥，用于服务商验证您的身份</p>
            
            <form method="post" class="mb-3">
                <div class="form-group">
                    <label for="message_key">密钥留言（可选）</label>
                    <textarea id="message_key" name="message_key" class="form-control" rows="2" placeholder="输入关于此密钥的说明信息"></textarea>
                </div>
                
                <div class="form-group">
                    <label>向服务商展示的信息</label>
                    <div>
                        <label>
                            <input type="checkbox" name="display_uid" checked disabled> 用户UID（必须显示）
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" name="display_name" checked> 姓名
                        </label>
                    </div>
                    <div>
                        <label>
                            <input type="checkbox" name="display_email"> 邮箱
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" name="generate_key" class="btn">生成新密钥</button>
                </div>
            </form>
            
            <h4>密钥记录</h4>
            <table class="table">
                <thead>
                    <tr>
                        <th>创建时间</th>
                        <th>状态</th>
                        <th>使用情况</th>
                        <th>留言</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($user_keys)): ?>
                        <tr>
                            <td colspan="4" class="text-center">暂无密钥记录</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($user_keys as $key): ?>
                            <tr>
                                <td><?php echo $key['created_at']; ?></td>
                                <td><?php echo $key['used'] ? '已使用' : '未使用'; ?></td>
                                <td>
                                    <?php if ($key['used']): ?>
                                        被 <?php echo $key['sp_name'] ?? '未知服务商'; ?> 于 <?php echo $key['used_time']; ?> 使用
                                    <?php else: ?>
                                        未使用
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($key['message'] ?? '无'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 账号设置 -->
        <div id="account-settings" class="card" style="display: none;">
            <h3 class="card-title">账号设置</h3>
            
            <div class="alert alert-info">
                账号冻结后，您将无法登录系统。解除冻结需要验证您的邮箱和QQ号。
            </div>
            
            <form method="post" onsubmit="return confirm('确定要冻结您的账号吗？冻结后需要验证邮箱和QQ号才能解除。');">
                <button type="submit" name="freeze_account" class="btn btn-danger">冻结我的账号</button>
            </form>
        </div>
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
                e.preventDefault();
                
                // 移除所有活动状态
                document.querySelectorAll('.nav-tabs li').forEach(li => {
                    li.classList.remove('active');
                });
                document.querySelectorAll('.card').forEach(card => {
                    card.style.display = 'none';
                });
                
                // 添加当前活动状态
                this.parentElement.classList.add('active');
                const targetId = this.getAttribute('href').substring(1);
                document.getElementById(targetId).style.display = 'block';
            });
        });
    </script>
</body>
</html>
