<?php
require_once 'jc.php';

$error = '';
$success = '';

if (isset($_GET['uid']) && isset($_GET['token'])) {
    $uid = $_GET['uid'];
    $token = $_GET['token'];
    
    // 验证token
    if (md5($uid . SECRET_KEY) === $token) {
        $conn = db_connect();
        $stmt = $conn->prepare("UPDATE users SET is_email_verified = 1 WHERE uid = ?");
        $stmt->bind_param("s", $uid);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = "邮箱验证成功，您现在可以登录了";
            } else {
                $error = "该邮箱已验证过";
            }
        } else {
            $error = "验证失败，请稍后再试";
        }
        
        $stmt->close();
        $conn->close();
    } else {
        $error = "无效的验证链接";
    }
} else {
    $error = "缺少验证参数";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 邮箱验证</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-md text-center">
        <h1 class="text-2xl font-bold text-blue-600 mb-6"><?php echo SITE_NAME; ?> - 邮箱验证</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo $error; ?>
            </div>
        <?php elseif ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <a href="index.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
            返回登录
        </a>
    </div>
</body>
</html>
    