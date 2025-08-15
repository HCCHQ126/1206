<?php
require_once 'functions.php';

// 清除用户Cookie
clear_user_cookie();

// 跳转到登录页面
header('Location: login.php');
exit;
?>
