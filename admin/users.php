<?php
/**
 * 管理后台 - 用户管理
 * 支持：封禁/解禁、设置/撤销管理员（超管专属）、删除、编辑昵称/密码
 * POST 处理必须在 require header.php 之前，否则 HTML 已输出无法再 header()
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$pdo = getDB();
$currentUser = getCurrentUser();

// ======== 操作处理 ========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $uid    = (int)($_POST['uid'] ?? 0);

    // 不能操作自己（某些操作）
    $selfOp = $uid === (int)$currentUser['id'];

    // 获取被操作用户信息
    $targetUser = null;
    if ($uid > 0) {
        $s = $pdo->prepare('SELECT id, username, is_admin, is_super, is_banned FROM users WHERE id=?');
        $s->execute([$uid]);
        $targetUser = $s->fetch();
    }

    if ($action === 'ban' && $uid > 0 && !$selfOp) {
        if ($targetUser && $targetUser['is_super']) {
            setFlash('error', '不能封禁超级管理员');
        } elseif ($targetUser && $targetUser['is_admin'] && !$currentUser['is_super']) {
            setFlash('error', '普通管理员无法封禁其他管理员，请联系超级管理员');
        } else {
            $reason = trim($_POST['ban_reason'] ?? '违规操作');
            $pdo->prepare('UPDATE users SET is_banned=1, ban_reason=? WHERE id=?')->execute([$reason, $uid]);
            logAdminAction('封禁用户', 'user', $uid, "原因：{$reason}，用户：{$targetUser['username']}");
            setFlash('success', '用户已封禁');
        }

    } elseif ($action === 'unban' && $uid > 0) {
        $pdo->prepare('UPDATE users SET is_banned=0, ban_reason="" WHERE id=?')->execute([$uid]);
        logAdminAction('解禁用户', 'user', $uid, "用户：{$targetUser['username']}");
        setFlash('success', '用户已解禁');

    } elseif ($action === 'set_admin' && $uid > 0 && !$selfOp) {
        // 只有超管可设置普通管理员
        if (!$currentUser['is_super']) {
            setFlash('error', '只有超级管理员才能授予管理员权限');
        } elseif ($targetUser && $targetUser['is_super']) {
            setFlash('error', '超级管理员无需此操作');
        } else {
            $pdo->prepare('UPDATE users SET is_admin=1 WHERE id=?')->execute([$uid]);
            logAdminAction('设为管理员', 'user', $uid, "用户：{$targetUser['username']}");
            setFlash('success', '已设为管理员');
        }

    } elseif ($action === 'remove_admin' && $uid > 0 && !$selfOp) {
        // 只有超管可撤销普通管理员；不能撤销超管
        if (!$currentUser['is_super']) {
            setFlash('error', '只有超级管理员才能撤销管理员权限');
        } elseif ($targetUser && $targetUser['is_super']) {
            setFlash('error', '无法在网页端撤销超级管理员权限');
        } else {
            $pdo->prepare('UPDATE users SET is_admin=0 WHERE id=?')->execute([$uid]);
            logAdminAction('撤销管理员', 'user', $uid, "用户：{$targetUser['username']}");
            setFlash('success', '已撤销管理员权限');
        }

    } elseif ($action === 'delete' && $uid > 0 && !$selfOp) {
        if ($targetUser && $targetUser['is_super']) {
            setFlash('error', '不能删除超级管理员');
        } elseif ($targetUser && $targetUser['is_admin'] && !$currentUser['is_super']) {
            setFlash('error', '普通管理员无法删除其他管理员');
        } else {
            $uname = $targetUser['username'] ?? $uid;
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
            logAdminAction('删除用户', 'user', $uid, "用户：{$uname}");
            setFlash('success', '用户已删除');
        }

    } elseif ($action === 'edit_user' && $uid > 0) {
        // 不能编辑超级管理员（除非自己是超管）
        if ($targetUser && $targetUser['is_super'] && !$currentUser['is_super']) {
            setFlash('error', '普通管理员无法编辑超级管理员');
            goto redirect;
        }
        // 普通管理员不能编辑其他管理员
        if ($targetUser && $targetUser['is_admin'] && !$targetUser['is_super'] && !$currentUser['is_super'] && $uid !== (int)$currentUser['id']) {
            setFlash('error', '普通管理员无法编辑其他管理员信息');
            goto redirect;
        }
        $nn = trim($_POST['nickname'] ?? '');
        $pw = $_POST['new_password'] ?? '';
        $upd = []; $params = [];
        if ($nn !== '') {
            // 昵称唯一性检查
            $chk = $pdo->prepare('SELECT id FROM users WHERE nickname_lower=? AND id!=?');
            $chk->execute([mb_strtolower($nn), $uid]);
            if ($chk->fetch()) { setFlash('error', '该昵称已被使用'); goto redirect; }
            $upd[] = 'nickname=?';       $params[] = $nn;
            $upd[] = 'nickname_lower=?'; $params[] = mb_strtolower($nn);
        }
        if ($pw !== '') {
            if (strlen($pw) < 6) { setFlash('error', '密码不能少于6位'); goto redirect; }
            $upd[] = 'password=?'; $params[] = password_hash($pw, PASSWORD_DEFAULT);
        }
        if (!empty($upd)) {
            $params[] = $uid;
            $pdo->prepare('UPDATE users SET ' . implode(', ',$upd) . ' WHERE id=?')->execute($params);
            logAdminAction('编辑用户', 'user', $uid, implode(', ', array_filter([$nn ? "昵称改为{$nn}" : '', $pw ? '修改了密码' : ''])));
            setFlash('success', '用户信息已更新');
        }
    }

    redirect:
    header('Location: /admin/users.php');
    exit;
}

$adminPageTitle = '用户管理';
require_once __DIR__ . '/header.php';

// ======== 查询用户列表 ========
$keyword = trim($_GET['q'] ?? '');
$filter  = $_GET['filter'] ?? '';
$where   = ['1=1'];
$params  = [];

if ($keyword !== '') {
    $where[] = '(username LIKE ? OR nickname LIKE ? OR email LIKE ?)';
    $kw = "%{$keyword}%";
    $params = [$kw, $kw, $kw];
}
if ($filter === 'banned')  { $where[] = 'is_banned=1'; }
if ($filter === 'admin')   { $where[] = 'is_admin=1'; }

$whereStr = implode(' AND ', $where);
$users = $pdo->prepare("SELECT id, username, nickname, email, is_admin, is_super, is_banned, ban_reason, created_at
    FROM users WHERE {$whereStr} ORDER BY created_at DESC");
$users->execute($params);
$users = $users->fetchAll();

$editUser = null;
if (!empty($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT id, username, nickname, is_admin, is_super FROM users WHERE id=?');
    $stmt->execute([$eid]);
    $editUser = $stmt->fetch();
}
?>

<div class="d-flex justify-between align-center mb-3" style="flex-wrap:wrap;gap:12px;">
  <h1 style="font-size:1.4rem;font-weight:800;">用户管理 <span style="font-size:0.9rem;color:var(--text-sub);font-weight:400;">(<?= count($users) ?>)</span></h1>
  <form method="GET" action="/admin/users.php" class="d-flex gap-1" style="flex-wrap:wrap;">
    <input type="text" name="q" placeholder="搜索用户名/昵称/邮箱" value="<?= e($keyword) ?>" class="form-control" style="min-width:180px;">
    <select name="filter" class="filter-select" onchange="this.form.submit()">
      <option value="" <?= $filter==='' ? 'selected' : '' ?>>全部</option>
      <option value="admin"  <?= $filter==='admin'  ? 'selected' : '' ?>>管理员</option>
      <option value="banned" <?= $filter==='banned' ? 'selected' : '' ?>>已封禁</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">搜索</button>
  </form>
</div>

<?php if ($editUser): ?>
<?php
// 普通管理员不允许编辑超级管理员或其他管理员
$editAllowed = $currentUser['is_super']
    || $editUser['id'] === (int)$currentUser['id']
    || (!$editUser['is_super'] && !$editUser['is_admin']);
?>
<?php if (!$editAllowed): ?>
<div class="alert alert-warning mb-3">您没有权限编辑该用户（管理员账号仅超级管理员可编辑）。<a href="/admin/users.php">返回列表</a></div>
<?php else: ?>
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
          <input type="text" name="nickname" class="form-control" value="<?= e($editUser['nickname']) ?>" placeholder="昵称">
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
<?php endif; ?>

<!-- 封禁理由弹窗 -->
<div id="banModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:12px;max-width:400px;width:100%;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <h3 style="margin-bottom:16px;font-size:1.05rem;">填写封禁原因</h3>
    <form method="POST" id="banForm">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="action" value="ban">
      <input type="hidden" name="uid" id="banUid">
      <div class="form-group">
        <textarea name="ban_reason" class="form-control" placeholder="请填写封禁原因（用户将看到此内容）" rows="3" required></textarea>
      </div>
      <div class="d-flex gap-2 justify-end">
        <button type="button" onclick="closeBanModal()" class="btn btn-ghost">取消</button>
        <button type="submit" class="btn btn-danger">确认封禁</button>
      </div>
    </form>
  </div>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>ID</th><th>用户名</th><th>昵称</th><th>邮箱</th><th>权限</th><th>状态</th><th>注册时间</th><th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr <?= $u['is_banned'] ? 'style="background:#fff8f8;opacity:0.85;"' : '' ?>>
        <td><?= $u['id'] ?></td>
        <td><strong><?= e($u['username']) ?></strong></td>
        <td><?= e($u['nickname'] ?: '-') ?></td>
        <td style="font-size:0.82rem;color:var(--text-sub);"><?= e($u['email'] ?: '-') ?></td>
        <td>
          <?php if ($u['is_super']): ?>
            <span class="badge" style="background:#7c3aed;color:#fff;">超级管理员</span>
          <?php elseif ($u['is_admin']): ?>
            <span class="badge badge-gold">管理员</span>
          <?php else: ?>
            <span class="badge badge-gray">普通用户</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($u['is_banned']): ?>
            <span class="badge badge-danger" title="<?= e($u['ban_reason']) ?>">已封禁</span>
          <?php else: ?>
            <span class="badge badge-green">正常</span>
          <?php endif; ?>
        </td>
        <td><?= formatTime($u['created_at']) ?></td>
        <td>
          <div class="d-flex gap-1" style="flex-wrap:wrap;">
            <?php
            $isSelf        = $u['id'] === (int)$currentUser['id'];
            $isSuper       = (bool)$u['is_super'];         // 目标是超管
            $targetIsAdmin = $u['is_admin'] && !$isSuper;  // 目标是普通管理员
            $opIsSuper     = (bool)$currentUser['is_super']; // 操作者是超管

            // 普通管理员对 超级管理员行：一律不显示任何操作
            $hideAll = $isSuper && !$opIsSuper && !$isSelf;
            ?>

            <?php if (!$hideAll): ?>

            <!-- 编辑：自己 / 超管对所有人 / 普通管理员对普通用户 -->
            <?php if ($isSelf || $opIsSuper || (!$isSuper && !$targetIsAdmin)): ?>
            <a href="/admin/users.php?edit=<?= $u['id'] ?>" class="btn btn-ghost btn-sm">编辑</a>
            <?php endif; ?>

            <!-- 封禁/解禁：不能操作自己和超管；普通管理员不能操作管理员 -->
            <?php if (!$isSelf && !$isSuper && ($opIsSuper || !$targetIsAdmin)): ?>
              <?php if ($u['is_banned']): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="unban">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--blue-primary);">解禁</button>
              </form>
              <?php else: ?>
              <button type="button" class="btn btn-warning btn-sm" onclick="openBanModal(<?= $u['id'] ?>)">封禁</button>
              <?php endif; ?>
            <?php endif; ?>

            <!-- 授予/撤销管理员（仅超管，不对超管本身） -->
            <?php if ($opIsSuper && !$isSelf && !$isSuper): ?>
              <?php if (!$u['is_admin']): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="set_admin">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-gold btn-sm">授予管理</button>
              </form>
              <?php else: ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="remove_admin">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm">撤销管理</button>
              </form>
              <?php endif; ?>
            <?php endif; ?>

            <!-- 删除：不能删自己和超管；普通管理员不能删管理员 -->
            <?php if (!$isSelf && !$isSuper && ($opIsSuper || !$targetIsAdmin)): ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="uid" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="确定删除用户 <?= e($u['username']) ?>？其所有Bot也将一并删除！">删除</button>
            </form>
            <?php endif; ?>

            <?php endif; // !$hideAll ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>
function openBanModal(uid) {
    document.getElementById('banUid').value = uid;
    var m = document.getElementById('banModal');
    m.style.display = 'flex';
}
function closeBanModal() {
    document.getElementById('banModal').style.display = 'none';
}
document.getElementById('banModal').addEventListener('click', function(e) {
    if (e.target === this) closeBanModal();
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
