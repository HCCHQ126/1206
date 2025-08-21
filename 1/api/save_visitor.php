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
    if (!$data || !isset($data['ip_address'], $data['browser_fingerprint'])) {
        throw new Exception('无效的请求数据');
    }

    // 生成唯一访客ID
    $visitor_id = uuid_create();

    // 引入数据库配置
    require_once '../config/db.php';

    // 插入访客数据
    $stmt = $pdo->prepare("
        INSERT INTO visitors (visitor_id, ip_address, browser_fingerprint, login_time)
        VALUES (:visitor_id, :ip_address, :browser_fingerprint, NOW())
    ");
    
    $stmt->execute([
        ':visitor_id' => $visitor_id,
        ':ip_address' => $data['ip_address'],
        ':browser_fingerprint' => $data['browser_fingerprint']
    ]);

    // 返回成功响应
    echo json_encode([
        'success' => true,
        'visitor_id' => $visitor_id
    ]);
} catch (Exception $e) {
    // 返回错误信息
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// 生成UUID函数
function uuid_create() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
?>
