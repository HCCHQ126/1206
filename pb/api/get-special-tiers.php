<?php
// 1. 引入数据库配置
require_once '../config/db.php';

try {
    // 2. 第一步：查询所有特殊梯队（按排序权重升序）
    $tierSql = "SELECT id, title, icon_class FROM special_tier ORDER BY sort ASC";
    $tierStmt = $pdo->prepare($tierSql);
    $tierStmt->execute();
    $specialTiers = $tierStmt->fetchAll();

    // 3. 第二步：循环查询每个特殊梯队下的联盟
    $result = [];
    foreach ($specialTiers as $tier) {
        $tierId = $tier['id'];
        // 子查询：获取当前特殊梯队的联盟
        $allianceSql = "
            SELECT name FROM special_alliance 
            WHERE special_tier_id = :tier_id 
            ORDER BY sort ASC
        ";
        $allianceStmt = $pdo->prepare($allianceSql);
        $allianceStmt->bindParam(':tier_id', $tierId, PDO::PARAM_INT); // 绑定参数防注入
        $allianceStmt->execute();
        $alliances = $allianceStmt->fetchAll(PDO::FETCH_COLUMN); // 只取"name"列，返回一维数组

        // 组装当前特殊梯队的完整数据
        $result[] = [
            'title' => $tier['title'],
            'icon' => $tier['icon_class'],
            'alliances' => $alliances
        ];
    }

    // 4. 返回成功JSON
    echo json_encode([
        'code' => 200,
        'msg' => '特殊梯队数据获取成功',
        'data' => $result
    ]);

} catch (PDOException $e) {
    // 5. 处理错误
    echo json_encode([
        'code' => 500,
        'msg' => '特殊梯队数据查询失败：' . $e->getMessage(),
        'data' => []
    ]);
}

// 6. 释放资源
$pdo = null;
$tierStmt = null;
$allianceStmt = null;