<?php
// 数据库配置
$db_config = [
    'host' => 'localhost',
    'dbname' => 'pd',
    'username' => 'pd',
    'password' => 'GJkLipeeNYPtnA7D',
    'charset' => 'utf8mb4'
];

try {
    // 创建PDO连接
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    // 数据库连接失败时返回错误JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '数据库连接失败: ' . $e->getMessage()
    ]);
    exit;
}
    