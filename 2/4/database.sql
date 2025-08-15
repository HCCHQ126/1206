-- 创建数据库
CREATE DATABASE IF NOT EXISTS user_management_system DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE user_management_system;

-- 设置时区为上海时间
SET GLOBAL time_zone = '+8:00';

-- 用户角色表
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 初始角色数据
INSERT INTO user_roles (name, description) VALUES
('user', '一般用户'),
('developer', '开发者'),
('authorized_developer', '授权开发者'),
('management_developer', '管理开发者'),
('service_provider', '服务商'),
('special_authorized_provider', '特别授权服务商'),
('general_admin', '一般管理员'),
('super_admin', '超级管理员');

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(50) NOT NULL UNIQUE COMMENT '用户唯一标识，如HCCWUD-abc12345',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    qq VARCHAR(20) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('normal', 'locked', 'restricted', 'frozen') DEFAULT 'normal',
    register_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_email_verified TINYINT(1) DEFAULT 0,
    last_login_time TIMESTAMP NULL,
    service_provider_id INT NULL,
    FOREIGN KEY (role_id) REFERENCES user_roles(id)
);

-- 用户登录记录表
CREATE TABLE IF NOT EXISTS login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    browser_fingerprint TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 用户分组表
CREATE TABLE IF NOT EXISTS user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 用户-分组关联表
CREATE TABLE IF NOT EXISTS user_group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (group_id) REFERENCES user_groups(id),
    UNIQUE KEY unique_user_group (user_id, group_id)
);

-- 服务商表
CREATE TABLE IF NOT EXISTS service_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'locked', 'restricted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 服务商管理员关联表
CREATE TABLE IF NOT EXISTS service_provider_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_provider_id INT NOT NULL,
    user_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (service_provider_id) REFERENCES service_providers(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_provider_admin (service_provider_id, user_id)
);

-- 服务商密钥表
CREATE TABLE IF NOT EXISTS provider_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_provider_id INT NOT NULL,
    key_value VARCHAR(255) NOT NULL UNIQUE,
    expiry_duration ENUM('2h', '6h', '18d', '30d') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_by INT NOT NULL,
    FOREIGN KEY (service_provider_id) REFERENCES service_providers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 用户密钥表
CREATE TABLE IF NOT EXISTS user_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    key_value VARCHAR(255) NOT NULL UNIQUE,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    used_by_provider_id INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (used_by_provider_id) REFERENCES service_providers(id)
);

-- 用户密钥可见信息设置表
CREATE TABLE IF NOT EXISTS user_key_visibility (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    show_name BOOLEAN DEFAULT FALSE,
    show_email BOOLEAN DEFAULT FALSE,
    show_qq BOOLEAN DEFAULT FALSE,
    show_role BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_visibility (user_id)
);

-- 系统设置表
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 初始系统设置
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', '用户管理系统', '网站名称'),
('login_enabled', '1', '是否启用登录功能，1=启用，0=禁用'),
('register_enabled', '1', '是否启用注册功能，1=启用，0=禁用'),
('force_email_verify', '0', '是否强制邮箱验证，1=启用，0=禁用');

-- 登录禁令表
CREATE TABLE IF NOT EXISTS login_bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ban_type ENUM('ip', 'browser', 'uid', 'qq', 'email') NOT NULL,
    ban_value VARCHAR(255) NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    UNIQUE KEY unique_ban (ban_type, ban_value),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
    