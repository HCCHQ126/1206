<?php
// 设置JSON响应头
header('Content-Type: application/json');

try {
    // 获取用户IP地址
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    // 返回JSON响应
    echo json_encode([
        'success' => true,
        'ip' => $ip
    ]);
} catch (Exception $e) {
    // 捕获所有异常并返回JSON格式的错误
    echo json_encode([
        'success' => false,
        'message' => '获取IP地址失败: ' . $e->getMessage()
    ]);
}
?>
