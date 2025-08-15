<?php
require_once 'jc.php';

// 检查用户是否已登录
$current_user = get_logged_in_user();
if ($current_user) {
    // 根据用户角色跳转到相应页面
    if (is_admin($current_user)) {
        header('Location: admin/index.php');
    } elseif (is_service_provider_admin($current_user)) {
        header('Location: service_provider.php');
    } else {
        header('Location: user_center.php');
    }
    exit;
}

// 检查登录和注册是否启用
$login_enabled = get_system_setting('login_enabled') == '1';
$register_enabled = get_system_setting('register_enabled') == '1';

// 检查登录是否被禁止
$login_blocked = is_login_blocked();

// 处理登录请求
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    if (!$login_enabled) {
        $login_error = "登录功能已关闭";
    } elseif ($login_blocked) {
        $login_error = "您的IP或浏览器已被禁止登录";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // 检查邮箱是否被禁止
        if (is_blocked('email', $email)) {
            $login_error = "该邮箱已被禁止登录";
        } else {
            $conn = db_connect();
            $stmt = $conn->prepare("SELECT id, uid, password, status, qq FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // 检查QQ是否被禁止
                if (is_blocked('qq', $user['qq'])) {
                    $login_error = "该账号已被禁止登录";
                } 
                // 检查UID是否被禁止
                elseif (is_blocked('uid', $user['uid'])) {
                    $login_error = "该账号已被禁止登录";
                }
                // 检查账号状态
                elseif ($user['status'] == 'locked' || $user['status'] == 'frozen') {
                    $login_error = "账号已被锁定或冻结";
                }
                // 验证密码
                elseif (verify_password($password, $user['password'])) {
                    // 强制邮箱验证检查
                    $force_email_verify = get_system_setting('force_email_verify') == '1';
                    if ($force_email_verify) {
                        $stmt = $conn->prepare("SELECT is_email_verified FROM users WHERE id = ?");
                        $stmt->bind_param("i", $user['id']);
                        $stmt->execute();
                        $verify_result = $stmt->get_result();
                        $verify_data = $verify_result->fetch_assoc();
                        
                        if ($verify_data['is_email_verified'] != 1) {
                            $login_error = "请先验证您的邮箱";
                        } else {
                            // 登录成功，设置Cookie并记录日志
                            set_user_cookie($user['id'], $user['uid']);
                            log_login($user['id'], $_SERVER['REMOTE_ADDR'], get_browser_fingerprint());
                            
                            // 获取用户角色并跳转
                            $stmt_role = $conn->prepare("SELECT name FROM user_roles WHERE id = (SELECT role_id FROM users WHERE id = ?)");
                            $stmt_role->bind_param("i", $user['id']);
                            $stmt_role->execute();
                            $role_result = $stmt_role->get_result();
                            $role_data = $role_result->fetch_assoc();
                            
                            if ($role_data['name'] == 'general_admin' || $role_data['name'] == 'super_admin') {
                                header('Location: admin/index.php');
                            } else {
                                $stmt_sp = $conn->prepare("SELECT id FROM service_providers WHERE id = (SELECT service_provider_id FROM users WHERE id = ?)");
                                $stmt_sp->bind_param("i", $user['id']);
                                $stmt_sp->execute();
                                $sp_result = $stmt_sp->get_result();
                                
                                if ($sp_result->num_rows > 0) {
                                    header('Location: service_provider.php');
                                } else {
                                    header('Location: user_center.php');
                                }
                            }
                            exit;
                        }
                    } else {
                        // 登录成功，设置Cookie并记录日志
                        set_user_cookie($user['id'], $user['uid']);
                        log_login($user['id'], $_SERVER['REMOTE_ADDR'], get_browser_fingerprint());
                        
                        // 获取用户角色并跳转
                        $stmt_role = $conn->prepare("SELECT name FROM user_roles WHERE id = (SELECT role_id FROM users WHERE id = ?)");
                        $stmt_role->bind_param("i", $user['id']);
                        $stmt_role->execute();
                        $role_result = $stmt_role->get_result();
                        $role_data = $role_result->fetch_assoc();
                        
                        if ($role_data['name'] == 'general_admin' || $role_data['name'] == 'super_admin') {
                            header('Location: admin/index.php');
                        } else {
                            $stmt_sp = $conn->prepare("SELECT id FROM service_providers WHERE id = (SELECT service_provider_id FROM users WHERE id = ?)");
                            $stmt_sp->bind_param("i", $user['id']);
                            $stmt_sp->execute();
                            $sp_result = $stmt_sp->get_result();
                            
                            if ($sp_result->num_rows > 0) {
                                header('Location: service_provider.php');
                            } else {
                                header('Location: user_center.php');
                            }
                        }
                        exit;
                    }
                } else {
                    $login_error = "邮箱或密码错误";
                }
            } else {
                $login_error = "邮箱或密码错误";
            }
            
            $stmt->close();
            $conn->close();
        }
    }
}

// 处理注册请求
$register_error = '';
$register_success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    if (!$register_enabled) {
        $register_error = "注册功能已关闭";
    } elseif ($login_blocked) {
        $register_error = "您的IP或浏览器已被禁止注册";
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $qq = trim($_POST['qq']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        // 验证输入
        if (empty($name) || empty($email) || empty($qq) || empty($password) || empty($confirm_password)) {
            $register_error = "所有字段都是必填的";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $register_error = "请输入有效的邮箱地址";
        } elseif ($password != $confirm_password) {
            $register_error = "两次输入的密码不一致";
        } elseif (strlen($password) < 6) {
            $register_error = "密码长度至少为6位";
        } elseif (!is_numeric($qq) || strlen($qq) < 5) {
            $register_error = "请输入有效的QQ号";
        } elseif (is_blocked('email', $email)) {
            $register_error = "该邮箱已被禁止注册";
        } elseif (is_blocked('qq', $qq)) {
            $register_error = "该QQ号已被禁止注册";
        } else {
            $conn = db_connect();
            
            // 检查邮箱是否已存在
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $register_error = "该邮箱已被注册";
                $stmt->close();
                $conn->close();
            } else {
                $stmt->close();
                
                // 检查QQ是否已存在
                $stmt = $conn->prepare("SELECT id FROM users WHERE qq = ?");
                $stmt->bind_param("s", $qq);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $register_error = "该QQ号已被注册";
                    $stmt->close();
                    $conn->close();
                } else {
                    $stmt->close();
                    
                    // 获取普通用户角色ID
                    $stmt = $conn->prepare("SELECT id FROM user_roles WHERE name = 'user'");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $role = $result->fetch_assoc();
                    $role_id = $role['id'];
                    $stmt->close();
                    
                    // 生成唯一UID
                    do {
                        $uid = generate_uid();
                        $stmt = $conn->prepare("SELECT id FROM users WHERE uid = ?");
                        $stmt->bind_param("s", $uid);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $uid_exists = $result->num_rows > 0;
                        $stmt->close();
                    } while ($uid_exists);
                    
                    // 插入新用户
                    $encrypted_password = encrypt_password($password);
                    $stmt = $conn->prepare("
                        INSERT INTO users (uid, name, email, qq, password, role_id, register_time, is_email_verified)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)
                    ");
                    $stmt->bind_param("sssssi", $uid, $name, $email, $qq, $encrypted_password, $role_id);
                    
                    if ($stmt->execute()) {
                        $user_id = $conn->insert_id;
                        
                        // 如果需要强制邮箱验证，发送验证邮件
                        $force_email_verify = get_system_setting('force_email_verify') == '1';
                        if ($force_email_verify) {
                            // 生成验证链接
                            $verify_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify_email.php?uid=" . $uid . "&token=" . md5($uid . SECRET_KEY);
                            
                            // 发送验证邮件
                            $subject = SITE_NAME . " - 邮箱验证";
                            $message = "请点击以下链接验证您的邮箱：<br><a href='$verify_link'>$verify_link</a>";
                            send_email($email, $subject, $message);
                            
                            $register_success = "注册成功，请查收邮件验证您的邮箱";
                        } else {
                            // 自动登录
                            set_user_cookie($user_id, $uid);
                            log_login($user_id, $_SERVER['REMOTE_ADDR'], get_browser_fingerprint());
                            header('Location: user_center.php');
                            exit;
                        }
                    } else {
                        $register_error = "注册失败，请稍后再试";
                    }
                    
                    $stmt->close();
                    $conn->close();
                }
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
    <title><?php echo SITE_NAME; ?> - 登录/注册</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-md">
        <h1 class="text-2xl font-bold text-center text-blue-600 mb-6"><?php echo SITE_NAME; ?></h1>
        
        <?php if ($login_blocked): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-center">
                您的IP或浏览器已被禁止登录/注册
            </div>
        <?php endif; ?>
        
        <div class="border-b border-gray-200 mb-6">
            <ul class="flex -mb-px" id="tabs">
                <li class="mr-2">
                    <button class="inline-block py-4 px-4 text-blue-600 border-b-2 border-blue-500 font-semibold" data-tab="login">登录</button>
                </li>
                <li class="mr-2">
                    <button class="inline-block py-4 px-4 text-gray-500 border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300 font-semibold" data-tab="register">注册</button>
                </li>
            </ul>
        </div>
        
        <!-- 登录表单 -->
        <div id="login-form" class="tab-content">
            <?php if ($login_error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $login_error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$login_enabled): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                    登录功能已关闭
                </div>
            <?php else: ?>
                <form method="post">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="login-email">
                            邮箱
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="login-email" type="email" name="email" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="login-password">
                            密码
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="login-password" type="password" name="password" required>
                    </div>
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="login">
                            登录
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <!-- 注册表单 -->
        <div id="register-form" class="tab-content hidden">
            <?php if ($register_error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $register_error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($register_success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $register_success; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$register_enabled): ?>
                <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                    注册功能已关闭
                </div>
            <?php else: ?>
                <form method="post">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="register-name">
                            姓名
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="register-name" type="text" name="name" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="register-email">
                            邮箱
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="register-email" type="email" name="email" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="register-qq">
                            QQ号
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="register-qq" type="text" name="qq" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="register-password">
                            密码
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="register-password" type="password" name="password" required>
                    </div>
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="register-confirm-password">
                            确认密码
                        </label>
                        <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="register-confirm-password" type="password" name="confirm_password" required>
                    </div>
                    <div class="flex items-center justify-between">
                        <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="register">
                            注册
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 标签切换功能
        document.querySelectorAll('#tabs button[data-tab]').forEach(button => {
            button.addEventListener('click', () => {
                // 移除所有标签的活跃状态
                document.querySelectorAll('#tabs button[data-tab]').forEach(btn => {
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
                document.getElementById(`${tabId}-form`).classList.remove('hidden');
            });
        });
    </script>
</body>
</html>
