<?php
// 设置JSON响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // 验证请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('只允许POST请求');
    }

    // 获取请求数据
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // 验证数据
    if (!$data || !isset($data['username'], $data['password'])) {
        throw new Exception('请提供用户名和密码');
    }

    // 引入数据库配置
    require_once '../config/db.php';

    // 查询管理员信息
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = :username");
    $stmt->execute([':username' => $data['username']]);
    $admin = $stmt->fetch();

    // 验证管理员
    if (!$admin || !password_verify($data['password'], $admin['password'])) {
        throw new Exception('用户名或密码错误');
    }

    // 更新最后登录时间
    $stmt = $pdo->prepare("
        UPDATE admin 
        SET last_login_time = NOW() 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $admin['id']]);

    // 返回成功响应
    echo json_encode([
        'success' => true,
        'message' => '登录成功'
    ]);
} catch (Exception $e) {
    // 返回错误信息
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
