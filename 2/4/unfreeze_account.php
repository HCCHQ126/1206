<?php
require_once 'jc.php';

// 如果用户已登录，跳转到用户中心
if (get_current_user()) {
    header('Location: user_center.php');
    exit;
}

$message = '';
$success = false;

// 处理解冻请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unfreeze'])) {
    $email = trim($_POST['email']);
    $qq = trim($_POST['qq']);
    
    if (empty($email) || empty($qq)) {
        $message = "邮箱和QQ号不能为空";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "请输入有效的邮箱地址";
    } elseif (!is_numeric($qq)) {
        $message = "请输入有效的QQ号";
    } else {
        $conn = db_connect();
        $stmt = $conn->prepare("SELECT id, status FROM users WHERE email = ? AND qq = ?");
        $stmt->bind_param("ss", $email, $qq);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] != 'frozen') {
                $message = "该账号未被冻结";
            } else {
                // 解冻账号
                $stmt = $conn->prepare("UPDATE users SET status = 'normal' WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                
                if ($stmt->execute()) {
                    $message = "账号已成功解冻，您现在可以登录了";
                    $success = true;
                } else {
                    $message = "解冻失败，请稍后再试";
                }
            }
        } else {
            $message = "邮箱和QQ号不匹配";
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - 账号解冻</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-md">
        <h1 class="text-2xl font-bold text-center text-blue-600 mb-6"><?php echo SITE_NAME; ?> - 账号解冻</h1>
        
        <?php if ($message): ?>
            <div class="mb-4 p-3 rounded <?php echo $success ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
            <form method="post">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="email">
                        邮箱
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="email" type="email" name="email" required>
                </div>
                <div class="mb-6">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="qq">
                        QQ号
                    </label>
                    <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="qq" type="text" name="qq" required>
                </div>
                <div class="flex items-center justify-between">
                    <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" type="submit" name="unfreeze">
                        解冻账号
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="text-center">
                <a href="index.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    返回登录
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
    