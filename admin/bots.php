<?php
/**
 * 管理后台 - Bot 管理
 */
$adminPageTitle = 'Bot管理';
require_once __DIR__ . '/header.php';
$pdo = getDB();

// 删除操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $bid = (int)($_POST['bid'] ?? 0);
    if ($bid > 0) {
        $pdo->prepare('DELETE FROM bots WHERE id=?')->execute([$bid]);
        setFlash('success', 'Bot已删除');
    }
    header('Location: /admin/bots.php');
    exit;
}

$keyword = trim($_GET['q'] ?? '');
$where = ['1=1'];
$params = [];
if ($keyword !== '') {
    $where[] = '(b.nickname LIKE ? OR b.owner LIKE ?)';
    $kw = '%'.$keyword.'%';
    $params = [$kw, $kw];
}
$whereStr = implode(' AND ', $where);

$total = (int)$pdo->prepare("SELECT COUNT(*) FROM bots b WHERE $whereStr")->execute($params) ? 0 : 0;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bots b WHERE $whereStr");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page-1)*$perPage;

$stmt = $pdo->prepare("SELECT b.id,b.nickname,b.platform,b.status,b.view_count,b.created_at,u.username,u.nickname AS un
    FROM bots b LEFT JOIN users u ON b.user_id=u.id
    WHERE $whereStr ORDER BY b.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$bots = $stmt->fetchAll();

$paginationUrl = '/admin/bots.php?' . http_build_query(array_filter(['q'=>$keyword]));
?>

<div class="d-flex justify-between align-center mb-3" style="flex-wrap:wrap;gap:12px;">
  <h1 style="font-size:1.4rem;font-weight:800;">Bot 管理 <span style="font-size:0.9rem;color:var(--text-sub);font-weight:400;">(<?= $total ?>)</span></h1>
  <form method="GET" action="/admin/bots.php" class="d-flex gap-1">
    <div class="search-input-wrap" style="min-width:220px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" placeholder="搜索Bot昵称、骰主" value="<?= e($keyword) ?>">
    </div>
    <button type="submit" class="btn btn-primary btn-sm">搜索</button>
  </form>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr><th>ID</th><th>昵称</th><th>平台</th><th>状态</th><th>浏览</th><th>发布者</th><th>时间</th><th>操作</th></tr>
    </thead>
    <tbody>
      <?php foreach ($bots as $b): ?>
      <tr>
        <td><?= $b['id'] ?></td>
        <td><a href="/detail.php?id=<?= $b['id'] ?>" style="color:var(--blue-primary);text-decoration:none;font-weight:500;"><?= e($b['nickname']) ?></a></td>
        <td><?= $b['platform'] ? '<span class="badge badge-blue">'.e($b['platform']).'</span>' : '-' ?></td>
        <td><?= $b['status'] ? '<span class="badge status-'.e($b['status']).'">'.e($b['status']).'</span>' : '-' ?></td>
        <td><?= $b['view_count'] ?></td>
        <td><?= e($b['un'] ?: $b['username']) ?></td>
        <td><?= formatTime($b['created_at']) ?></td>
        <td>
          <div class="d-flex gap-1">
            <a href="/edit.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm">编辑</a>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="bid" value="<?= $b['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="确定删除《<?= e($b['nickname']) ?>》？">删除</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= buildPagination($total, $page, $perPage, $paginationUrl) ?>

<?php require_once __DIR__ . '/footer.php'; ?>
