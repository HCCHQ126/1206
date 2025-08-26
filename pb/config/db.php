<?php
/**
 * MySQL 5.7 数据库配置（PHP 8.1 PDO 连接）
 * 注意：生产环境需隐藏错误详情，仅返回"连接失败"
 */
header("Content-Type: application/json; charset=utf8"); // 统一JSON响应头

// 数据库连接参数（根据实际环境修改）
$dbConfig = [
    'host' => 'localhost',    // 数据库主机（默认localhost）
    'dbname' => 'pb', // 数据库名（之前创建的）
    'username' => 'pb',     // 数据库用户名（如root）
    'password' => 'pb',   // 数据库密码（根据实际设置）
    'charset' => 'utf8mb4'    // 字符集（兼容 emoji 和特殊字符）
];

try {
    // 建立PDO连接（MySQL 5.7 兼容）
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}",
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // 开启异常模式（便于调试）
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC // 默认关联数组返回
        ]
    );
} catch (PDOException $e) {
    // 连接失败：返回JSON错误（生产环境可删除 $e->getMessage() 避免暴露敏感信息）
    echo json_encode([
        'code' => 500,
        'msg' => '数据库连接失败：' . $e->getMessage(),
        'data' => []
    ]);
    exit; // 终止脚本
}