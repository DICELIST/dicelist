<?php
/**
 * 退出登录
 */
require_once __DIR__ . '/includes/functions.php';
initSession();
session_destroy();
header('Location: /index.php');
exit;
