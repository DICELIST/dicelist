<?php
/**
 * 公共头部模板
 * 使用方式: require_once __DIR__ . '/includes/header.php';
 * 调用前设置: $pageTitle, $bodyClass
 */
require_once __DIR__ . '/functions.php';
$currentUser = getCurrentUser();
$siteName = getSiteSetting('site_name', 'TRPG Bot 导航');
$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . $siteName : $siteName;
$bodyClass = $bodyClass ?? '';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e(getSiteSetting('site_description', 'TRPG线上跑团Bot展示平台')) ?>">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">
<div class="page-wrapper">

<!-- 导航栏 -->
<nav class="navbar">
  <div class="navbar-inner">
    <a href="/index.php" class="navbar-brand">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </div>
      <?= e($siteName) ?>
    </a>

    <ul class="navbar-nav" id="mainNav">
      <li><a href="/index.php" <?= (basename($_SERVER['PHP_SELF']) === 'index.php') ? 'class="active"' : '' ?>>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        首页
      </a></li>
      <?php if ($currentUser): ?>
      <li><a href="/submit.php" <?= (basename($_SERVER['PHP_SELF']) === 'submit.php') ? 'class="active"' : '' ?>>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        提交Bot
      </a></li>
      <li><a href="/profile.php" <?= (basename($_SERVER['PHP_SELF']) === 'profile.php') ? 'class="active"' : '' ?>>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        个人中心
      </a></li>
      <?php if ($currentUser['is_admin']): ?>
      <li><a href="/admin/index.php" <?= (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'class="active"' : '' ?>>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        管理后台
      </a></li>
      <?php endif; ?>
      <?php endif; ?>
    </ul>

    <div class="navbar-actions">
      <?php if ($currentUser): ?>
        <span class="text-sm text-gray" style="display:none;" id="usernameShow">
          <?= e($currentUser['nickname'] ?: $currentUser['username']) ?>
        </span>
        <a href="/logout.php" class="btn btn-ghost btn-sm">退出</a>
      <?php else: ?>
        <a href="/login.php" class="btn btn-outline btn-sm">登录</a>
        <a href="/register.php" class="btn btn-primary btn-sm">注册</a>
      <?php endif; ?>
    </div>

    <button class="hamburger" id="hamburgerBtn" aria-label="菜单">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
  </div>
</nav>

<main>
<?php if ($flash): ?>
<div class="container mt-2">
  <div class="alert alert-<?= e($flash['type']) ?>">
    <?= e($flash['message']) ?>
  </div>
</div>
<?php endif; ?>
