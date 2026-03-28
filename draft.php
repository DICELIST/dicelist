<?php
/**
 * 草稿箱
 */
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();
$user = getCurrentUser();

// 删除草稿
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verifyCsrf();
    $did = (int)($_POST['did'] ?? 0);
    $pdo->prepare('DELETE FROM bot_drafts WHERE id=? AND user_id=?')->execute([$did, $user['id']]);
    setFlash('success', '草稿已删除');
    header('Location: /draft.php'); exit;
}

$drafts = $pdo->prepare('SELECT * FROM bot_drafts WHERE user_id=? ORDER BY saved_at DESC');
$drafts->execute([$user['id']]);
$drafts = $drafts->fetchAll();

$pageTitle = '草稿箱';
require_once __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="page-header">
    <div class="breadcrumb">
      <a href="/profile.php">个人中心</a><span>/</span><span>草稿箱</span>
    </div>
    <h1>草稿箱 <span style="font-size:0.85rem;font-weight:400;color:var(--text-sub);">(<?= count($drafts) ?>)</span></h1>
    <p>未发布的内容自动保存在这里</p>
  </div>

  <?php if (empty($drafts)): ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    <h3>草稿箱是空的</h3>
    <p>编辑内容时系统会自动保存草稿</p>
    <a href="/submit.php" class="btn btn-primary">提交新Bot</a>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Bot昵称</th><th>平台</th><th>最后保存</th><th>操作</th></tr>
      </thead>
      <tbody>
        <?php foreach ($drafts as $d): ?>
        <tr>
          <td><strong><?= e($d['nickname'] ?: '（未命名）') ?></strong></td>
          <td><?= $d['platform'] ? '<span class="badge badge-blue">'.e($d['platform']).'</span>' : '-' ?></td>
          <td><?= formatTime($d['saved_at']) ?></td>
          <td>
            <div class="d-flex gap-1">
              <?php if ($d['bot_id']): ?>
              <a href="/edit.php?id=<?= $d['bot_id'] ?>&draft=<?= $d['id'] ?>" class="btn btn-primary btn-sm">继续编辑</a>
              <?php else: ?>
              <a href="/submit.php?draft=<?= $d['id'] ?>" class="btn btn-primary btn-sm">继续编辑</a>
              <?php endif; ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="did" value="<?= $d['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" data-confirm="确定删除此草稿？">删除</button>
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
