<?php
// 1. 引入数据库配置（建立连接）
require_once '../config/db.php';

try {
    // 2. 关联查询：梯队表（tier）+ 公会表（alliance），按梯队排序权重升序
    $sql = "
        SELECT t.id AS tier_id, t.level, t.title, t.color_class, 
               a.id AS alliance_id, a.name AS alliance_name
        FROM tier t
        LEFT JOIN alliance a ON t.id = a.tier_id
        ORDER BY t.sort ASC, a.sort ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll();

    // 3. 组装数据结构（按梯队分组，嵌套公会列表）
    $tiers = [];
    foreach ($result as $item) {
        $tierId = $item['tier_id'];
        // 首次遇到该梯队：初始化梯队基础信息
        if (!isset($tiers[$tierId])) {
            $tiers[$tierId] = [
                'level' => $item['level'],
                'title' => $item['title'],
                'color' => $item['color_class'],
                'alliances' => [] // 用于存放该梯队下的公会
            ];
        }
        // 若有公会数据：添加到该梯队的alliances数组
        if (!empty($item['alliance_name'])) {
            $tiers[$tierId]['alliances'][] = $item['alliance_name'];
        }
    }

    // 4. 转换为索引数组（便于前端遍历），返回成功JSON
    echo json_encode([
        'code' => 200,
        'msg' => '数据获取成功',
        'data' => array_values($tiers) // 去除关联数组的key，转为索引数组
    ]);

} catch (PDOException $e) {
    // 5. 处理查询错误
    echo json_encode([
        'code' => 500,
        'msg' => '常规梯队数据查询失败：' . $e->getMessage(),
        'data' => []
    ]);
}

// 6. 释放资源（PDO自动关闭，显式置空更规范）
$pdo = null;
$stmt = null;