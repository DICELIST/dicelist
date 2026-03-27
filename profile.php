<?php
/**
 * 个人信息页
 */
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();
$currentUser = getCurrentUser();

$errors   = [];
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $nickname = trim($_POST['nickname'] ?? '');
    $newPass  = $_POST['new_password'] ?? '';
    $newPass2 = $_POST['new_password2'] ?? '';
    $oldPass  = $_POST['old_password'] ?? '';

    // 获取完整用户信息（含密码哈希）
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$currentUser['id']]);
    $fullUser = $stmt->fetch();

    $updates = [];
    $params  = [];

    if ($nickname !== '' && $nickname !== $fullUser['nickname']) {
        $updates[] = 'nickname = ?';
        $params[]  = $nickname;
    }

    if ($newPass !== '') {
        if (!password_verify($oldPass, $fullUser['password'])) {
            $errors[] = '原密码不正确';
        } elseif (strlen($newPass) < 6) {
            $errors[] = '新密码不能少于6位';
        } elseif ($newPass !== $newPass2) {
            $errors[] = '两次新密码不一致';
        } else {
            $updates[] = 'password = ?';
            $params[]  = password_hash($newPass, PASSWORD_DEFAULT);
        }
    }

    if (empty($errors) && !empty($updates)) {
        $params[] = $currentUser['id'];
        $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
        $success = true;
        $currentUser = getCurrentUser();
    }
}

// 获取该用户发布的Bot列表
$stmt = $pdo->prepare('SELECT id, nickname, status, platform, view_count, created_at FROM bots WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$currentUser['id']]);
$myBots = $stmt->fetchAll();

$pageTitle = '个人中心';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
  <div class="page-header">
    <h1>个人中心</h1>
    <p>管理你的账号信息和Bot</p>
  </div>

  <div style="display:grid;grid-template-columns:300px 1fr;gap:24px;align-items:start;" class="profile-layout">
    <!-- 修改信息 -->
    <div class="card">
      <div class="card-header">
        <h3>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:6px;color:var(--blue-primary);"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          个人信息
        </h3>
      </div>
      <div class="card-body">
        <div style="text-align:center;margin-bottom:20px;">
          <div style="width:72px;height:72px;background:linear-gradient(135deg,var(--blue-primary),var(--blue-dark));border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:1.8rem;font-weight:700;color:#fff;box-shadow:0 4px 16px var(--blue-glow);">
            <?= mb_substr($currentUser['nickname'] ?: $currentUser['username'], 0, 1) ?>
          </div>
          <div class="font-bold"><?= e($currentUser['nickname'] ?: $currentUser['username']) ?></div>
          <div class="text-sm text-gray">@<?= e($currentUser['username']) ?></div>
          <?php if ($currentUser['is_admin']): ?>
          <span class="badge badge-gold mt-1">管理员</span>
          <?php endif; ?>
          <div class="text-xs text-gray mt-1">注册于 <?= formatTime($currentUser['created_at']) ?></div>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">信息更新成功！</div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="POST" action="/profile.php">
          <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
          <div class="form-group">
            <label class="form-label">昵称</label>
            <input type="text" name="nickname" class="form-control"
                   value="<?= e($currentUser['nickname']) ?>" placeholder="留空保持原昵称">
          </div>
          <hr style="border-color:var(--border);margin:16px 0;">
          <div class="form-group">
            <label class="form-label">修改密码</label>
            <input type="password" name="old_password" class="form-control mb-1"
                   placeholder="当前密码">
            <input type="password" name="new_password" class="form-control mb-1"
                   placeholder="新密码（至少6位）">
            <input type="password" name="new_password2" class="form-control"
                   placeholder="确认新密码">
          </div>
          <button type="submit" class="btn btn-primary w-100">保存更改</button>
        </form>
      </div>
    </div>

    <!-- 我的Bot列表 -->
    <div>
      <div class="d-flex justify-between align-center mb-2">
        <h2 style="font-size:1.1rem;font-weight:700;">我发布的Bot <span style="color:var(--text-sub);font-size:0.9rem;font-weight:400;">(<?= count($myBots) ?>)</span></h2>
        <a href="/submit.php" class="btn btn-primary btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          提交新Bot
        </a>
      </div>

      <?php if (empty($myBots)): ?>
      <div class="empty-state" style="padding:40px 20px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        <h3>还没有发布的Bot</h3>
        <p>分享你的骰子机器人，让更多玩家发现！</p>
        <a href="/submit.php" class="btn btn-primary">立即提交</a>
      </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>昵称</th>
              <th>平台</th>
              <th>状态</th>
              <th>浏览</th>
              <th>发布时间</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($myBots as $bot): ?>
            <tr>
              <td><a href="/detail.php?id=<?= $bot['id'] ?>" style="color:var(--blue-primary);text-decoration:none;font-weight:500;"><?= e($bot['nickname']) ?></a></td>
              <td><?= $bot['platform'] ? '<span class="badge badge-blue">'.e($bot['platform']).'</span>' : '-' ?></td>
              <td><?= $bot['status'] ? '<span class="badge status-'.e($bot['status']).'">'.e($bot['status']).'</span>' : '-' ?></td>
              <td><?= $bot['view_count'] ?></td>
              <td><?= formatTime($bot['created_at']) ?></td>
              <td>
                <div class="d-flex gap-1">
                  <a href="/edit.php?id=<?= $bot['id'] ?>" class="btn btn-ghost btn-sm">编辑</a>
                  <a href="/delete.php?id=<?= $bot['id'] ?>" class="btn btn-danger btn-sm"
                     data-confirm="确定删除《<?= e($bot['nickname']) ?>》？">删除</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
@media (max-width: 768px) {
    .profile-layout { grid-template-columns: 1fr !important; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
