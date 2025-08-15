-- 设置默认时区为上海时间
SET GLOBAL time_zone = '+8:00';
SET SESSION time_zone = '+8:00';

-- 用户表
CREATE TABLE users (
    uid VARCHAR(20) PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    qq VARCHAR(20) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    identity ENUM('user', 'developer', 'authorized_developer', 'developer_manager', 'service_provider', 'special_authorized_service_provider', 'admin', 'super_admin') NOT NULL DEFAULT 'user',
    status ENUM('normal', 'locked', 'restricted', 'frozen') NOT NULL DEFAULT 'normal',
    email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    register_time DATETIME NOT NULL,
    last_login_time DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    browser_fingerprint TEXT NULL,
    frozen_by_self BOOLEAN NOT NULL DEFAULT FALSE
);

-- 登录记录表
CREATE TABLE login_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(20) NOT NULL,
    login_time DATETIME NOT NULL,
    login_ip VARCHAR(45) NOT NULL,
    browser_fingerprint TEXT NULL,
    success BOOLEAN NOT NULL,
    FOREIGN KEY (uid) REFERENCES users(uid)
);

-- 服务商表
CREATE TABLE service_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'locked', 'restricted') NOT NULL DEFAULT 'active',
    created_time DATETIME NOT NULL,
    created_by VARCHAR(20) NOT NULL,
    FOREIGN KEY (created_by) REFERENCES users(uid)
);

-- 服务商管理员表
CREATE TABLE service_provider_managers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sp_id INT NOT NULL,
    uid VARCHAR(20) NOT NULL,
    added_time DATETIME NOT NULL,
    FOREIGN KEY (sp_id) REFERENCES service_providers(id),
    FOREIGN KEY (uid) REFERENCES users(uid),
    UNIQUE KEY (sp_id, uid)
);

-- API密钥表
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_type ENUM('user', 'service_provider') NOT NULL,
    related_id VARCHAR(20) NOT NULL, -- 用户uid或服务商id
    key_value VARCHAR(100) NOT NULL UNIQUE,
    comment TEXT,
    expiry_time DATETIME NOT NULL,
    created_time DATETIME NOT NULL,
    used BOOLEAN NOT NULL DEFAULT FALSE,
    used_time DATETIME NULL
);

-- API密钥访问日志表
CREATE TABLE api_key_access_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT NOT NULL,
    accessed_by INT NULL, -- 服务商ID
    access_time DATETIME NOT NULL,
    access_ip VARCHAR(45) NOT NULL,
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id),
    FOREIGN KEY (accessed_by) REFERENCES service_providers(id)
);

-- 系统设置表
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_time DATETIME NOT NULL,
    updated_by VARCHAR(20) NOT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(uid)
);

-- 禁止登录表
CREATE TABLE blocked_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_type ENUM('ip', 'browser_fingerprint', 'uid', 'qq', 'email') NOT NULL,
    entry_value TEXT NOT NULL,
    blocked_time DATETIME NOT NULL,
    blocked_by VARCHAR(20) NOT NULL,
    reason TEXT,
    FOREIGN KEY (blocked_by) REFERENCES users(uid),
    UNIQUE KEY (entry_type, entry_value(255))
);

-- 初始化系统设置
INSERT INTO system_settings (setting_name, setting_value, description, updated_time, updated_by) VALUES
('enable_registration', '1', '是否启用注册页面', NOW(), 'system'),
('enable_login', '1', '是否启用登录页面', NOW(), 'system'),
('enable_authorization', '1', '是否启用授权验证页面', NOW(), 'system'),
('force_email_verification', '0', '是否强制邮箱验证', NOW(), 'system');
