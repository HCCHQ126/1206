<?php
// 1. 引入数据库配置
require_once '../config/db.php';

// 2. 接收前端传递的搜索关键词（POST/GET均可，这里兼容两种方式）
$searchTerm = trim($_REQUEST['keyword'] ?? ''); // 若没有关键词，默认空字符串
if (empty($searchTerm)) {
    echo json_encode([
        'code' => 400,
        'msg' => '请输入搜索关键词',
        'data' => []
    ]);
    exit;
}

try {
    // 3. 搜索常规公会（模糊匹配名称）
    $normalSql = "
        SELECT a.name AS alliance_name, t.level AS tier_level, t.title AS tier_title
        FROM alliance a
        LEFT JOIN tier t ON a.tier_id = t.id
        WHERE a.name LIKE :keyword
        ORDER BY t.sort ASC, a.sort ASC
    ";
    $normalStmt = $pdo->prepare($normalSql);
    $keywordParam = "%" . $searchTerm . "%"; // 模糊匹配格式（前后加%）
    $normalStmt->bindParam(':keyword', $keywordParam, PDO::PARAM_STR);
    $normalStmt->execute();
    $normalResult = $normalStmt->fetchAll();

    // 4. 搜索特殊联盟（模糊匹配名称）
    $specialSql = "
        SELECT sa.name AS alliance_name, st.title AS tier_title
        FROM special_alliance sa
        LEFT JOIN special_tier st ON sa.special_tier_id = st.id
        WHERE sa.name LIKE :keyword
        ORDER BY st.sort ASC, sa.sort ASC
    ";
    $specialStmt = $pdo->prepare($specialSql);
    $specialStmt->bindParam(':keyword', $keywordParam, PDO::PARAM_STR);
    $specialStmt->execute();
    $specialResult = $specialStmt->fetchAll();

    // 5. 组装最终搜索结果
    $searchResult = [
        'normal' => $normalResult, // 常规公会结果（含所属梯队）
        'special' => $specialResult // 特殊联盟结果（含所属特殊梯队）
    ];

    // 6. 返回JSON
    echo json_encode([
        'code' => 200,
        'msg' => '搜索成功（共' . (count($normalResult) + count($specialResult)) . '条结果）',
        'data' => $searchResult
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'code' => 500,
        'msg' => '搜索失败：' . $e->getMessage(),
        'data' => []
    ]);
}

// 7. 释放资源
$pdo = null;
$normalStmt = null;
$specialStmt = null;