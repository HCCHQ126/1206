<?php
require_once 'functions.php';

// 检查用户是否登录且为管理员
if (!is_logged_in() || !is_admin()) {
    header('Location: login.php');
    exit;
}

// 获取当前管理员信息
$admin = get_current_user();
if (!$admin) {
    clear_user_cookie();
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';
$current_page = isset($_GET['page']) ? $_GET['page'] : 'user_management';

// 处理用户状态和身份修改
if (isset($_POST['update_user'])) {
    $uid = $_POST['uid'] ?? '';
    $status = $_POST['status'] ?? '';
    $identity = $_POST['identity'] ?? '';
    
    // 超级管理员可以修改所有身份，普通管理员只能修改一般身份
    if (!is_super_admin($admin)) {
        $allowed_identities = [
            IDENTITY_USER, 
            IDENTITY_DEVELOPER, 
            IDENTITY_AUTHORIZED_DEV
        ];
        
        if (!in_array($identity, $allowed_identities)) {
            $identity = $allowed_identities[0];
        }
    }
    
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET status = :status, identity = :identity WHERE uid = :uid");
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':identity', $identity);
    $stmt->bindParam(':uid', $uid);
    
    if ($stmt->execute()) {
        $message = '用户信息更新成功';
        $message_type = 'success';
    } else {
        $message = '用户信息更新失败';
        $message_type = 'danger';
    }
}

// 处理系统设置更新
if (isset($_POST['update_settings']) && is_super_admin($admin)) {
    $login_enabled = isset($_POST['login_enabled']) ? 1 : 0;
    $register_enabled = isset($_POST['register_enabled']) ? 1 : 0;
    $auth_verify_enabled = isset($_POST['auth_verify_enabled']) ? 1 : 0;
    $email_verification_required = isset($_POST['email_verification_required']) ? 1 : 0;
    
    update_system_setting('login_enabled', $login_enabled);
    update_system_setting('register_enabled', $register_enabled);
    update_system_setting('auth_verify_enabled', $auth_verify_enabled);
    update_system_setting('email_verification_required', $email_verification_required);
    
    $message = '系统设置更新成功';
    $message_type = 'success';
}

// 处理用户搜索
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$users = [];

global $pdo;
if (!empty($search_term)) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE 
                          uid LIKE :search OR 
                          name LIKE :search OR 
                          email LIKE :search OR 
                          qq LIKE :search");
    $search_param = "%{$search_term}%";
    $stmt->bindParam(':search', $search_param);
    $stmt->execute();
    $users = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY register_time DESC");
    $users = $stmt->fetchAll();
}

// 获取所有用户登录记录
$login_records = [];
$stmt = $pdo->prepare("SELECT lr.*, u.name FROM login_records lr
                      LEFT JOIN users u ON lr.uid = u.uid
                      ORDER BY lr.login_time DESC LIMIT 100");
$stmt->execute();
$login_records = $stmt->fetchAll();

// 获取所有服务商
$service_providers = [];
$stmt = $pdo->query("SELECT * FROM service_providers ORDER BY created_at DESC");
$service_providers = $stmt->fetchAll();

// 获取系统设置
$system_settings = [
    'login_enabled' => get_system_setting('login_enabled'),
    'register_enabled' => get_system_setting('register_enabled'),
    'auth_verify_enabled' => get_system_setting('auth_verify_enabled'),
    'email_verification_required' => get_system_setting('email_verification_required')
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员后台 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <div class="container header-content">
            <a href="index.php" class="logo"><?php echo SITE_NAME; ?></a>
            <nav>
                <ul>
                    <li><a href="admin_manage.php" class="active">管理后台</a></li>
                    <li><a href="user_center.php">个人中心</a></li>
                    <li><a href="logout.php">退出登录</a></li>
                </ul>
            </nav>
        </div>
    </header>
    
    <main class="container">
        <h2 class="mt-3 mb-3">管理员后台</h2>
        <p>当前管理员: <?php echo $admin['name']; ?> (<?php echo $admin['identity'] == IDENTITY_SUPER_ADMIN ? '超级管理员' : '一般管理员'; ?>)</p>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <ul class="nav-tabs">
            <li <?php echo $current_page == 'user_management' ? 'class="active"' : ''; ?>>
                <a href="admin_manage.php?page=user_management">用户管理</a>
            </li>
            <li <?php echo $current_page == 'login_records' ? 'class="active"' : ''; ?>>
                <a href="admin_manage.php?page=login_records">登录记录</a>
            </li>
            <li <?php echo $current_page == 'service_providers' ? 'class="active"' : ''; ?>>
                <a href="admin_manage.php?page=service_providers">服务商管理</a>
            </li>
            <?php if (is_super_admin($admin)): ?>
                <li <?php echo $current_page == 'system_settings' ? 'class="active"' : ''; ?>>
                    <a href="admin_manage.php?page=system_settings">系统设置</a>
                </li>
            <?php endif; ?>
        </ul>
        
        <!-- 用户管理页面 -->
        <?php if ($current_page == 'user_management'): ?>
            <div class="card">
                <h3 class="card-title">用户管理</h3>
                
                <form method="get" class="mb-3">
                    <div class="form-group">
                        <input type="hidden" name="page" value="user_management">
                        <input type="text" name="search" class="form-control" placeholder="搜索用户UID、姓名、邮箱或QQ号" value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    <button type="submit" class="btn">搜索</button>
                    <?php if (!empty($search_term)): ?>
                        <a href="admin_manage.php?page=user_management" class="btn btn-secondary">重置</a>
                    <?php endif; ?>
                </form>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>用户UID</th>
                            <th>姓名</th>
                            <th>邮箱</th>
                            <th>QQ号</th>
                            <th>注册时间</th>
                            <th>身份</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center">没有找到匹配的用户</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['uid']; ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['qq']); ?></td>
                                    <td><?php echo $user['register_time']; ?></td>
                                    <td><?php 
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
                                    ?></td>
                                    <td><?php 
                                        switch($user['status']) {
                                            case 'locked': echo '<span class="text-danger">锁定</span>'; break;
                                            case 'restricted': echo '<span class="text-warning">限制</span>'; break;
                                            case 'normal': echo '<span class="text-success">正常</span>'; break;
                                            case 'frozen': echo '<span class="text-secondary">冻结</span>'; break;
                                        }
                                    ?></td>
                                    <td>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="uid" value="<?php echo $user['uid']; ?>">
                                            
                                            <div class="form-group">
                                                <select name="status" class="form-control">
                                                    <option value="locked" <?php echo $user['status'] == 'locked' ? 'selected' : ''; ?>>锁定</option>
                                                    <option value="restricted" <?php echo $user['status'] == 'restricted' ? 'selected' : ''; ?>>限制</option>
                                                    <option value="normal" <?php echo $user['status'] == 'normal' ? 'selected' : ''; ?>>正常</option>
                                                    <option value="frozen" <?php echo $user['status'] == 'frozen' ? 'selected' : ''; ?>>冻结</option>
                                                </select>
                                            </div>
                                            
                                            <div class="form-group">
                                                <select name="identity" class="form-control">
                                                    <?php if (is_super_admin($admin) || $user['identity'] != IDENTITY_SUPER_ADMIN): ?>
                                                        <option value="user" <?php echo $user['identity'] == 'user' ? 'selected' : ''; ?>>普通用户</option>
                                                        <option value="developer" <?php echo $user['identity'] == 'developer' ? 'selected' : ''; ?>>开发者</option>
                                                        <option value="authorized_developer" <?php echo $user['identity'] == 'authorized_developer' ? 'selected' : ''; ?>>授权开发者</option>
                                                        
                                                        <?php if (is_super_admin($admin)): ?>
                                                            <option value="managing_developer" <?php echo $user['identity'] == 'managing_developer' ? 'selected' : ''; ?>>管理开发者</option>
                                                            <option value="service_provider" <?php echo $user['identity'] == 'service_provider' ? 'selected' : ''; ?>>服务商</option>
                                                            <option value="special_authorized_provider" <?php echo $user['identity'] == 'special_authorized_provider' ? 'selected' : ''; ?>>特别授权服务商</option>
                                                            <option value="admin" <?php echo $user['identity'] == 'admin' ? 'selected' : ''; ?>>一般管理员</option>
                                                            
                                                            <?php if ($admin['identity'] == IDENTITY_SUPER_ADMIN && $user['identity'] == IDENTITY_SUPER_ADMIN): ?>
                                                                <option value="super_admin" selected>超级管理员</option>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <option value="super_admin" selected>超级管理员</option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                            
                                            <button type="submit" name="update_user" class="btn btn-sm">更新</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- 登录记录页面 -->
        <?php if ($current_page == 'login_records'): ?>
            <div class="card">
                <h3 class="card-title">登录记录</h3>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>登录时间</th>
                            <th>用户名</th>
                            <th>用户UID</th>
                            <th>登录状态</th>
                            <?php if (is_super_admin($admin)): ?>
                                <th>IP地址</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($login_records)): ?>
                            <tr>
                                <td colspan="<?php echo is_super_admin($admin) ? 5 : 4; ?>" class="text-center">没有登录记录</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($login_records as $record): ?>
                                <tr>
                                    <td><?php echo $record['login_time']; ?></td>
                                    <td><?php echo htmlspecialchars($record['name'] ?? '未知用户'); ?></td>
                                    <td><?php echo $record['uid']; ?></td>
                                    <td><?php echo $record['status'] == 'success' ? '<span class="text-success">成功</span>' : '<span class="text-danger">失败</span>'; ?></td>
                                    <?php if (is_super_admin($admin)): ?>
                                        <td><?php echo $record['ip_address']; ?></td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- 服务商管理页面 -->
        <?php if ($current_page == 'service_providers'): ?>
            <div class="card">
                <h3 class="card-title">服务商管理</h3>
                
                <?php if (is_super_admin($admin)): ?>
                    <div class="mb-3">
                        <button class="btn" id="addProviderBtn">创建新服务商</button>
                    </div>
                <?php endif; ?>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>服务商名称</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($service_providers)): ?>
                            <tr>
                                <td colspan="5" class="text-center">没有服务商记录</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($service_providers as $provider): ?>
                                <tr>
                                    <td><?php echo $provider['id']; ?></td>
                                    <td><?php echo htmlspecialchars($provider['name']); ?></td>
                                    <td><?php 
                                        switch($provider['status']) {
                                            case 'available': echo '<span class="text-success">可用</span>'; break;
                                            case 'locked': echo '<span class="text-danger">锁定</span>'; break;
                                            case 'restricted': echo '<span class="text-warning">限制</span>'; break;
                                        }
                                    ?></td>
                                    <td><?php echo $provider['created_at']; ?></td>
                                    <td>
                                        <a href="service_provider.php?id=<?php echo $provider['id']; ?>" class="btn btn-sm">查看</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <!-- 系统设置页面（仅超级管理员可见） -->
        <?php if ($current_page == 'system_settings' && is_super_admin($admin)): ?>
            <div class="card">
                <h3 class="card-title">系统设置</h3>
                
                <form method="post">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="login_enabled" <?php echo $system_settings['login_enabled'] == 1 ? 'checked' : ''; ?>>
                            启用登录页面
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="register_enabled" <?php echo $system_settings['register_enabled'] == 1 ? 'checked' : ''; ?>>
                            启用注册页面
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="auth_verify_enabled" <?php echo $system_settings['auth_verify_enabled'] == 1 ? 'checked' : ''; ?>>
                            启用授权验证页面
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="email_verification_required" <?php echo $system_settings['email_verification_required'] == 1 ? 'checked' : ''; ?>>
                            启用邮箱强制验证
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="update_settings" class="btn">保存设置</button>
                    </div>
                </form>
            </div>
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
        
        // 创建服务商按钮点击事件
        document.getElementById('addProviderBtn')?.addEventListener('click', function() {
            alert('创建服务商功能将在这里实现');
            // 实际实现中应该打开一个模态框让管理员输入服务商信息
        });
    </script>
</body>
</html>
