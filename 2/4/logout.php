<?php
require_once 'jc.php';

// 清除用户Cookie
clear_user_cookie();

// 跳转到登录页
header('Location: index.php');
exit;
?>
    