-- 创建数据库
CREATE DATABASE IF NOT EXISTS user_system DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE user_system;

-- 用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(20) NOT NULL UNIQUE COMMENT '用户唯一标识，如HCCWUD-abc12345',
    name VARCHAR(50) NOT NULL COMMENT '用户姓名',
    email VARCHAR(100) NOT NULL UNIQUE COMMENT '用户邮箱',
    qq VARCHAR(20) NOT NULL COMMENT 'QQ号',
    password VARCHAR(255) NOT NULL COMMENT '加密后的密码',
    identity ENUM('user', 'developer', 'authorized_developer', 'manage_developer', 
                 'service_provider', 'special_authorized_provider', 'admin', 'super_admin') 
                 NOT NULL DEFAULT 'user' COMMENT '用户身份',
    status ENUM('normal', 'locked', 'restricted', 'frozen') NOT NULL DEFAULT 'normal' COMMENT '用户状态',
    email_verified TINYINT(1) NOT NULL DEFAULT 0 COMMENT '邮箱是否验证',
    register_time DATETIME NOT NULL COMMENT '注册时间',
    last_login_time DATETIME NULL COMMENT '最后登录时间',
    last_login_ip VARCHAR(50) NULL COMMENT '最后登录IP',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_identity (identity),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 用户登录记录表
CREATE TABLE IF NOT EXISTS login_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '用户ID',
    login_time DATETIME NOT NULL COMMENT '登录时间',
    login_ip VARCHAR(50) NOT NULL COMMENT '登录IP',
    login_success TINYINT(1) NOT NULL COMMENT '登录是否成功',
    user_agent TEXT NULL COMMENT '用户代理信息',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户登录记录表';

-- 用户密钥表
CREATE TABLE IF NOT EXISTS user_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '用户ID',
    key_value VARCHAR(100) NOT NULL UNIQUE COMMENT '密钥值',
    message TEXT NULL COMMENT '密钥留言',
    display_fields TEXT NOT NULL COMMENT '展示的字段，JSON格式',
    used TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已使用',
    created_time DATETIME NOT NULL COMMENT '创建时间',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_key_value (key_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户密钥表';

-- 服务商表
CREATE TABLE IF NOT EXISTS service_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_name VARCHAR(100) NOT NULL COMMENT '服务商名称',
    status ENUM('available', 'locked', 'restricted') NOT NULL DEFAULT 'available' COMMENT '服务商状态',
    manager1_id INT NOT NULL COMMENT '管理员1ID',
    manager2_id INT NOT NULL COMMENT '管理员2ID',
    created_time DATETIME NOT NULL COMMENT '创建时间',
    updated_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (manager1_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (manager2_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_managers (manager1_id, manager2_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='服务商表';

-- 服务商密钥表
CREATE TABLE IF NOT EXISTS provider_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL COMMENT '服务商ID',
    key_value VARCHAR(100) NOT NULL UNIQUE COMMENT '密钥值',
    duration ENUM('2h', '6h', '18d', '30d') NOT NULL COMMENT '有效期',
    expire_time DATETIME NOT NULL COMMENT '过期时间',
    created_time DATETIME NOT NULL COMMENT '创建时间',
    created_by INT NOT NULL COMMENT '创建人ID',
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_provider_id (provider_id),
    INDEX idx_key_value (key_value),
    INDEX idx_expire_time (expire_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='服务商密钥表';

-- 密钥查询记录表
CREATE TABLE IF NOT EXISTS key_queries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_key_id INT NOT NULL COMMENT '用户密钥ID',
    provider_key_id INT NOT NULL COMMENT '服务商密钥ID',
    query_time DATETIME NOT NULL COMMENT '查询时间',
    query_ip VARCHAR(50) NOT NULL COMMENT '查询IP',
    FOREIGN KEY (user_key_id) REFERENCES user_keys(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_key_id) REFERENCES provider_keys(id) ON DELETE CASCADE,
    INDEX idx_user_key_id (user_key_id),
    INDEX idx_provider_key_id (provider_key_id),
    INDEX idx_query_time (query_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='密钥查询记录表';

-- 用户分组表
CREATE TABLE IF NOT EXISTS user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(50) NOT NULL UNIQUE COMMENT '分组名称',
    description TEXT NULL COMMENT '分组描述',
    created_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户分组表';

-- 用户-分组关联表
CREATE TABLE IF NOT EXISTS user_group_mappings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT '用户ID',
    group_id INT NOT NULL COMMENT '分组ID',
    created_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_group (user_id, group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户-分组关联表';

-- 系统设置表
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) NOT NULL UNIQUE COMMENT '设置名称',
    setting_value VARCHAR(255) NOT NULL COMMENT '设置值',
    description TEXT NULL COMMENT '设置描述',
    updated_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置表';

-- 初始化系统设置
INSERT INTO system_settings (setting_name, setting_value, description) VALUES
('register_enabled', '1', '是否启用注册页面，1-启用，0-禁用'),
('login_enabled', '1', '是否启用登录页面，1-启用，0-禁用'),
('auth_enabled', '1', '是否启用授权验证页面，1-启用，0-禁用'),
('force_email_verify', '0', '是否强制邮箱验证，1-是，0-否');
