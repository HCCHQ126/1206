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
    if (!$data || !isset($data['question_id'])) {
        throw new Exception('请提供问题ID');
    }

    // 引入数据库配置
    require_once '../config/db.php';

    // 更新浏览次数
    $stmt = $pdo->prepare("
        UPDATE questions 
        SET view_count = view_count + 1,
            last_view_time = NOW()
        WHERE id = :id
    ");
    $stmt->execute([':id' => $data['question_id']]);

    // 获取更新后的浏览次数
    $stmt = $pdo->prepare("SELECT view_count FROM questions WHERE id = :id");
    $stmt->execute([':id' => $data['question_id']]);
    $result = $stmt->fetch();

    // 返回成功响应
    echo json_encode([
        'success' => true,
        'view_count' => $result['view_count']
    ]);
} catch (Exception $e) {
    // 返回错误信息
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
