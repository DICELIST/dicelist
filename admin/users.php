<?php
/**
 * 管理后台 - 用户管理
 */
$adminPageTitle = '用户管理';
require_once __DIR__ . '/header.php';
$pdo = getDB();

// 操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['uid'] ?? 0);

    if ($uid === $currentUser['id'] && in_array($action, ['delete','set_admin','remove_admin'])) {
        setFlash('error', '不能对自己进行此操作');
    } elseif ($action === 'delete' && $uid > 0) {
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
        setFlash('success', '用户已删除');
    } elseif ($action === 'set_admin' && $uid > 0) {
        $pdo->prepare('UPDATE users SET is_admin=1 WHERE id=?')->execute([$uid]);
        setFlash('success', '已设为管理员');
    } elseif ($action === 'remove_admin' && $uid > 0) {
        $pdo->prepare('UPDATE users SET is_admin=0 WHERE id=?')->execute([$uid]);
        setFlash('success', '已取消管理员权限');
    } elseif ($action === 'edit_user' && $uid > 0) {
        $nn = trim($_POST['nickname'] ?? '');
        $pw = $_POST['new_password'] ?? '';
        $upd = []; $params = [];
        if ($nn !== '') { $upd[] = 'nickname=?'; $params[] = $nn; }
        if ($pw !== '') {
            if (strlen($pw) < 6) { setFlash('error', '密码不能少于6位'); goto redirect; }
            $upd[] = 'password=?'; $params[] = password_hash($pw, PASSWORD_DEFAULT);
        }
        if (!empty($upd)) {
            $params[] = $uid;
            $pdo->prepare('UPDATE users SET ' . implode(', ',$upd) . ' WHERE id=?')->execute($params);
            setFlash('success', '用户信息已更新');
        }
    }
    redirect:
    header('Location: /admin/users.php');
    exit;
}

$users = $pdo->query('SELECT id, username, nickname, is_admin, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$editUser = null;
if (!empty($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT id, username, nickname, is_admin FROM users WHERE id=?');
    $stmt->execute([$eid]);
    $editUser = $stmt->fetch();
}
?>

<div class="d-flex justify-between align-center mb-3">
  <h1 style="font-size:1.4rem;font-weight:800;">用户管理</h1>
  <span class="badge badge-blue"><?= count($users) ?> 个用户</span>
</div>

<?php if ($editUser): ?>
<div class="card mb-3">
  <div class="card-header">
    <h3>编辑用户：<?= e($editUser['username']) ?></h3>
    <a href="/admin/users.php" class="btn btn-ghost btn-sm">取消</a>
  </div>
  <div class="card-body">
    <form method="POST" action="/admin/users.php">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="uid" value="<?= $editUser['id'] ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group mb-0">
          <label class="form-label">昵称</label>
          <input type="text" name="nickname" class="form-control" value="<?= e($editUser['nickname']) ?>">
        </div>
        <div class="form-group mb-0">
          <label class="form-label">新密码（留空不修改）</label>
          <input type="password" name="new_password" class="form-control" placeholder="至少6位">
        </div>
      </div>
      <div class="mt-2">
        <button type="submit" class="btn btn-primary">保存修改</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>ID</th><th>用户名</th><th>昵称</th><th>权限</th><th>注册时间</th><th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><strong><?= e($u['username']) ?></strong></td>
        <td><?= e($u['nickname'] ?: '-') ?></td>
        <td>
          <?= $u['is_admin']
            ? '<span class="badge badge-gold">管理员</span>'
            : '<span class="badge badge-gray">普通用户</span>' ?>
        </td>
        <td><?= formatTime($u['created_at']) ?></td>
        <td>
          <div class="d-flex gap-1" style="flex-wrap:wrap;">
            <a href="/admin/users.php?edit=<?= $u['id'] ?>" class="btn btn-ghost btn-sm">编辑</a>
            <?php if (!$u['is_admin']): ?>
            <form method="POST" action="/admin/users.php" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="set_admin">
              <input type="hidden" name="uid" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-gold btn-sm">设为管理员</button>
            </form>
            <?php else: ?>
            <form method="POST" action="/admin/users.php" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="remove_admin">
              <input type="hidden" name="uid" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm" <?= $u['id']==$currentUser['id']?'disabled':'' ?>>取消管理</button>
            </form>
            <?php endif; ?>
            <?php if ($u['id'] !== $currentUser['id']): ?>
            <form method="POST" action="/admin/users.php" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="uid" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="确定删除用户 <?= e($u['username']) ?>？其所有Bot也将一并删除！">删除</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
