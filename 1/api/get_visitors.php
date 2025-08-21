<?php
// 设置JSON响应头
header('Content-Type: application/json');

try {
    // 引入数据库配置
    require_once '../config/db.php';

    // 获取总访问量
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM visitors");
    $total = $stmt->fetch()['total'];

    // 获取今日访问量
    $stmt = $pdo->query("SELECT COUNT(*) as today FROM visitors WHERE DATE(login_time) = CURDATE()");
    $today = $stmt->fetch()['today'];

    // 获取最近访客记录
    $stmt = $pdo->query("SELECT * FROM visitors ORDER BY login_time DESC LIMIT 10");
    $visitors = $stmt->fetchAll();

    // 返回成功响应
    echo json_encode([
        'success' => true,
        'total' => $total,
        'today' => $today,
        'visitors' => $visitors
    ]);
} catch (Exception $e) {
    // 返回错误信息
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
