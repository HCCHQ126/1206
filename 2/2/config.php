<?php
// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'asx');
define('DB_USER', 'asx');
define('DB_PASS', 'asx');
define('DB_CHARSET', 'utf8mb4');

// 系统配置
define('SITE_NAME', '用户管理系统');
define('TIMEZONE', 'Asia/Shanghai'); // 上海时区
define('COOKIE_EXPIRE', 3600 * 24 * 7); // Cookie有效期7天
define('COOKIE_PATH', '/');
define('COOKIE_DOMAIN', '');
define('COOKIE_SECURE', false);
define('COOKIE_HTTPONLY', true);

// 密钥生成配置
define('USER_KEY_LENGTH', 32);
define('PROVIDER_KEY_LENGTH', 40);

// 身份常量定义
define('IDENTITY_USER', 'user');
define('IDENTITY_DEVELOPER', 'developer');
define('IDENTITY_AUTHORIZED_DEV', 'authorized_developer');
define('IDENTITY_MANAGING_DEV', 'managing_developer');
define('IDENTITY_SERVICE_PROVIDER', 'service_provider');
define('IDENTITY_SPECIAL_PROVIDER', 'special_authorized_provider');
define('IDENTITY_ADMIN', 'admin');
define('IDENTITY_SUPER_ADMIN', 'super_admin');

// 状态常量定义
define('STATUS_LOCKED', 'locked');
define('STATUS_RESTRICTED', 'restricted');
define('STATUS_NORMAL', 'normal');
define('STATUS_FROZEN', 'frozen');

// 服务商状态
define('SP_STATUS_AVAILABLE', 'available');
define('SP_STATUS_LOCKED', 'locked');
define('SP_STATUS_RESTRICTED', 'restricted');

// 密钥有效期选项(秒)
$key_expiry_options = [
    7200 => '2小时',    // 2H
    21600 => '6小时',   // 6H
    1555200 => '18天',  // 18D
    2592000 => '30天'   // 30D
];
?>
