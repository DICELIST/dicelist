<?php
/**
 * 发送验证码 AJAX 接口
 * POST: email, purpose, csrf_token
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => '请求方式错误']);
    exit;
}

// CSRF
verifyCsrf();

$email   = trim($_POST['email']   ?? '');
$purpose = trim($_POST['purpose'] ?? '');
$allowed = ['register', 'reset_pwd', 'rebind'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => '邮箱格式不正确']);
    exit;
}
if (!in_array($purpose, $allowed, true)) {
    echo json_encode(['ok' => false, 'msg' => '无效的操作类型']);
    exit;
}

$pdo = getDB();

// 注册时：邮箱不能已被使用
if ($purpose === 'register') {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '该邮箱已被注册']);
        exit;
    }
}

// 重置密码时：邮箱必须存在
if ($purpose === 'reset_pwd') {
    $stmt = $pdo->prepare('SELECT id, username, nickname FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        echo json_encode(['ok' => false, 'msg' => '该邮箱未绑定任何账号']);
        exit;
    }
    $username = $user['nickname'] ?: $user['username'];
} else {
    $username = $email;
}

// 60秒内不能重复发送
$stmt = $pdo->prepare(
    'SELECT id FROM verify_codes WHERE email=? AND purpose=? AND used=0 AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1'
);
$stmt->execute([$email, $purpose]);
if ($stmt->fetch()) {
    echo json_encode(['ok' => false, 'msg' => '发送太频繁，请60秒后重试']);
    exit;
}

$ok = sendVerifyCode($email, $purpose, $username);
if ($ok) {
    echo json_encode(['ok' => true, 'msg' => '验证码已发送，请查收邮件']);
} else {
    echo json_encode(['ok' => false, 'msg' => '邮件发送失败，请检查SMTP配置或稍后重试']);
}
