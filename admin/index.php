<?php
/**
 * 管理后台 - 仪表盘
 */
$adminPageTitle = '仪表盘';
require_once __DIR__ . '/header.php';
$pdo = getDB();

$totalBots   = (int)$pdo->query('SELECT COUNT(*) FROM bots')->fetchColumn();
$totalUsers  = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalAdmin  = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin=1')->fetchColumn();
$onlineBots  = (int)$pdo->query("SELECT COUNT(*) FROM bots WHERE status='在线'")->fetchColumn();
$totalViews  = (int)$pdo->query('SELECT SUM(view_count) FROM bots')->fetchColumn();

// 最近5条Bot
$recentBots = $pdo->query(
    'SELECT b.id, b.nickname, b.status, b.platform, b.created_at, u.username, u.nickname AS un
     FROM bots b LEFT JOIN users u ON b.user_id=u.id
     ORDER BY b.created_at DESC LIMIT 5'
)->fetchAll();

// 最近5个用户
$recentUsers = $pdo->query(
    'SELECT id, username, nickname, is_admin, created_at FROM users ORDER BY created_at DESC LIMIT 5'
)->fetchAll();
?>

<h1 style="font-size:1.4rem;font-weight:800;margin-bottom:20px;">仪表盘</h1>

<!-- 统计卡片 -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px;margin-bottom:28px;">
  <?php
  $stats = [
    ['收录Bot总数', $totalBots, '#0a84ff', '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'],
    ['在线Bot', $onlineBots, '#34c759', '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'],
    ['注册用户', $totalUsers, '#f5a623', '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
    ['总浏览量', number_format($totalViews), '#5856d6', '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'],
  ];
  foreach ($stats as [$label, $value, $color, $path]):
  ?>
  <div style="background:#fff;border-radius:12px;border:1px solid var(--border);padding:20px;box-shadow:var(--shadow);">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
      <span style="font-size:0.82rem;color:var(--text-sub);"><?= $label ?></span>
      <div style="width:32px;height:32px;background:<?= $color ?>1a;border-radius:8px;display:flex;align-items:center;justify-content:center;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2"><?= $path ?></svg>
      </div>
    </div>
    <div style="font-size:1.8rem;font-weight:800;color:<?= $color ?>;"><?= $value ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- 最近Bot -->
<div class="card mb-3">
  <div class="card-header">
    <h3>最近提交的Bot</h3>
    <a href="/admin/bots.php" class="btn btn-ghost btn-sm">查看全部</a>
  </div>
  <div class="table-wrap" style="border:none;border-radius:0;">
    <table>
      <thead><tr><th>昵称</th><th>平台</th><th>状态</th><th>提交者</th><th>时间</th><th>操作</th></tr></thead>
      <tbody>
        <?php foreach ($recentBots as $b): ?>
        <tr>
          <td><a href="/detail.php?id=<?= $b['id'] ?>" style="color:var(--blue-primary);text-decoration:none;"><?= e($b['nickname']) ?></a></td>
          <td><?= $b['platform'] ? '<span class="badge badge-blue">'.e($b['platform']).'</span>' : '-' ?></td>
          <td><?= $b['status'] ? '<span class="badge status-'.e($b['status']).'">'.e($b['status']).'</span>' : '-' ?></td>
          <td><?= e($b['un'] ?: $b['username']) ?></td>
          <td><?= formatTime($b['created_at']) ?></td>
          <td><a href="/detail.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm">查看</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 最近用户 -->
<div class="card">
  <div class="card-header">
    <h3>最近注册用户</h3>
    <a href="/admin/users.php" class="btn btn-ghost btn-sm">查看全部</a>
  </div>
  <div class="table-wrap" style="border:none;border-radius:0;">
    <table>
      <thead><tr><th>用户名</th><th>昵称</th><th>权限</th><th>注册时间</th></tr></thead>
      <tbody>
        <?php foreach ($recentUsers as $u): ?>
        <tr>
          <td><?= e($u['username']) ?></td>
          <td><?= e($u['nickname'] ?: '-') ?></td>
          <td><?= $u['is_admin'] ? '<span class="badge badge-gold">管理员</span>' : '<span class="badge badge-gray">普通用户</span>' ?></td>
          <td><?= formatTime($u['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
