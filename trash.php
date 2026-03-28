<?php
/**
 * 回收站（审核未通过的内容）
 */
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();
$user = getCurrentUser();

// 删除
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $bid = (int)($_POST['bid'] ?? 0);
    // 只能删除自己的
    $pdo->prepare('DELETE FROM bots WHERE id=? AND user_id=? AND review_status=2')
        ->execute([$bid, $user['id']]);
    setFlash('success', '已永久删除');
    header('Location: /trash.php'); exit;
}

// 重新提交审核
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resubmit') {
    verifyCsrf();
    $bid = (int)($_POST['bid'] ?? 0);
    $pdo->prepare('UPDATE bots SET review_status=0, review_remark="", reviewed_at=NULL, reviewed_by=NULL WHERE id=? AND user_id=? AND review_status=2')
        ->execute([$bid, $user['id']]);
    setFlash('success', '已重新提交审核，等待管理员审核');
    header('Location: /trash.php'); exit;
}

$trash = $pdo->prepare(
    'SELECT id, nickname, platform, review_remark, updated_at FROM bots
     WHERE user_id=? AND review_status=2 ORDER BY updated_at DESC'
);
$trash->execute([$user['id']]);
$trash = $trash->fetchAll();

$pageTitle = '回收站';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div class="breadcrumb">
      <a href="/profile.php">个人中心</a><span>/</span><span>回收站</span>
    </div>
    <h1>回收站 <span style="font-size:0.85rem;font-weight:400;color:var(--text-sub);">(<?= count($trash) ?>)</span></h1>
    <p>审核未通过的内容在这里，修改后可重新提交审核</p>
  </div>

  <?php if (empty($trash)): ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
    <h3>回收站是空的</h3>
    <p>审核未通过的内容会在这里显示</p>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Bot昵称</th><th>平台</th><th>拒绝原因</th><th>时间</th><th>操作</th></tr>
      </thead>
      <tbody>
        <?php foreach ($trash as $b): ?>
        <tr>
          <td><strong><?= e($b['nickname']) ?></strong></td>
          <td><?= $b['platform'] ? '<span class="badge badge-blue">'.e($b['platform']).'</span>' : '-' ?></td>
          <td style="color:#e74c3c;font-size:0.85rem;"><?= e($b['review_remark'] ?: '-') ?></td>
          <td><?= formatTime($b['updated_at']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <a href="/edit.php?id=<?= $b['id'] ?>" class="btn btn-outline btn-sm">修改内容</a>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="resubmit">
                <input type="hidden" name="bid" value="<?= $b['id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">重新提交</button>
              </form>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="bid" value="<?= $b['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="确定永久删除？">永久删除</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
