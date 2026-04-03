<?php
/**
 * 退出登录
 */
require_once __DIR__ . '/includes/functions.php';
initSession();
clearRememberMeToken();  // 清除 Remember Me cookie 及数据库 token
session_destroy();
header('Location: /index.php');
exit;

