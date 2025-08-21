<?php
// 设置JSON响应头
header('Content-Type: application/json');

try {
    // 引入数据库配置
    require_once '../config/db.php';

    // 查询所有问答数据
    $stmt = $pdo->query("
        SELECT q.*, c.name as category_name 
        FROM questions q
        JOIN categories c ON q.category_id = c.id
        ORDER BY q.id ASC
    ");
    
    $questions = $stmt->fetchAll();
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'data' => $questions
    ]);
} catch (Exception $e) {
    // 返回错误信息
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
    