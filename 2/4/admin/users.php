<?php
require_once '../jc.php';
$user = require_admin();

// 检查是否为超级管理员
$is_super_admin = is_super_admin($user);

// 获取所有用户角色
$roles = [];
$general_roles = []; // 一般身份
$special_roles = []; // 特别身份

$conn = db_connect();
$stmt = $conn->prepare("SELECT id, name FROM user_roles ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $roles[$row['id']] = $row['name'];
    
    // 区分一般身份和特别身份
    if (in_array($row['name'], ['user', 'developer', 'authorized_developer'])) {
        $general_roles[$row['id']] = $row['name'];
    } else {
        $special_roles[$row['id']] = $row['name'];
    }
}
$stmt->close();

// 处理用户状态和角色更新
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_user'])) {
    $user_id = intval($_POST['user_id']);
    $status = $_POST['status'];
    $role_id = intval($_POST['role_id']);
    
    // 检查权限：一般管理员只能设置一般身份
    if (!$is_super_admin && isset($special_roles[$role_id])) {
        $message = "没有权限设置特别身份";
    } else {
        $conn = db_connect();
        $stmt = $conn->prepare("UPDATE users SET status = ?, role_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $status, $role_id, $user_id);
        
        if ($stmt->execute()) {
            $message = "用户信息更新成功";
        } else {
            $message = "更新失败，请稍后再试";
        }
        
        $stmt->close();
        $conn->close();
    }
}

// 处理用户搜索
$search_term = '';
$search_results = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = trim($_GET['search']);
    
    $conn = db_connect();
    $search_param = "%" . $search_term . "%";
    
    $stmt = $conn->prepare("
        SELECT u.*, r.name as role_name 
        FROM users u
        JOIN user_roles r ON u.role_id = r.id
        WHERE u.uid LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.qq LIKE ? OR r.name LIKE ?
        ORDER BY u.register_time DESC
    ");
    $stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $search_results[] = $row;
    }
    
    $stmt->close();
    $conn->close();
} else {
    // 获取所有用户（分页）
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $conn = db_connect();
    
    // 获取总用户数
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $result = $stmt->get_result();
    $total_users = $result->fetch_assoc()['count'];
    $total_pages = ceil($total_users / $limit);
    $stmt->close();
    
    // 获取当前页用户
    $stmt = $conn->prepare("
        SELECT u.*, r.name as role_name 
        FROM users u
        JOIN user_roles r ON u.role_id = r.id
        ORDER BY u.register_time DESC
        LIMIT ?, ?
    ");
    $stmt->bind_param("ii", $offset, $limit);