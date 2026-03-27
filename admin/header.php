<?php
/**
 * 管理后台公共头部
 */
require_once dirname(__DIR__) . '/includes/functions.php';
requireAdmin();
$currentUser = getCurrentUser();
$siteName = getSiteSetting('site_name', 'TRPG Bot 导航');
$adminPageTitle = isset($adminPageTitle) ? $adminPageTitle . ' - 管理后台' : '管理后台';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($adminPageTitle) ?> - <?= e($siteName) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="page-wrapper">

<nav class="navbar">
  <div class="navbar-inner">
    <a href="/" class="navbar-brand">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
      </div>
      <?= e($siteName) ?>
    </a>
    <div style="font-size:0.82rem;color:var(--text-sub);flex:1;padding-left:16px;">
      <span class="badge badge-gold">管理后台</span>
    </div>
    <div class="navbar-actions">
      <span class="text-sm text-gray"><?= e($currentUser['nickname'] ?: $currentUser['username']) ?></span>
      <a href="/" class="btn btn-ghost btn-sm">前台</a>
      <a href="/logout.php" class="btn btn-ghost btn-sm">退出</a>
    </div>
  </div>
</nav>

<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-title">管理菜单</div>
    <nav class="admin-nav">
      <a href="/admin/index.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        仪表盘
      </a>
      <a href="/admin/bots.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Bot 管理
      </a>
      <a href="/admin/users.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        用户管理
      </a>
      <a href="/admin/options.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
        下拉选项管理
      </a>
      <a href="/admin/mail.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        邮件设置
      </a>
      <a href="/admin/settings.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M5 19.07A10 10 0 0 1 5 4.93"/></svg>
        网站设置
      </a>
    </nav>
  </aside>

  <div class="admin-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
