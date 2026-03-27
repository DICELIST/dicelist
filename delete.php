<?php
/**
 * 删除Bot
 */
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();
$currentUser = getCurrentUser();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /index.php'); exit; }

$stmt = $pdo->prepare('SELECT * FROM bots WHERE id = ?');
$stmt->execute([$id]);
$bot = $stmt->fetch();

if (!$bot) { setFlash('error', 'Bot不存在'); header('Location: /profile.php'); exit; }

if ($bot['user_id'] != $currentUser['id'] && !$currentUser['is_admin']) {
    setFlash('error', '无权限删除此Bot');
    header('Location: /index.php');
    exit;
}

$pdo->prepare('DELETE FROM bots WHERE id = ?')->execute([$id]);
setFlash('success', 'Bot已删除');
header('Location: /profile.php');
exit;
