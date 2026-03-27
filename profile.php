<?php
/**
 * 个人中心 - 改昵称、邮箱验证改密码、换绑邮箱
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
requireLogin();
$pdo         = getDB();
$currentUser = getCurrentUser();

// 获取完整用户信息（含email）
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$currentUser['id']]);
$fullUser = $stmt->fetch();

$errors  = [];
$success = '';
$action  = $_POST['action'] ?? '';

// ======== 操作处理 ========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // --- 更改昵称 ---
    if ($action === 'nickname') {
        $nickname = trim($_POST['nickname'] ?? '');
        if ($nickname === '') { $errors[] = '昵称不能为空'; }
        elseif (mb_strlen($nickname) > 50) { $errors[] = '昵称不超过50字'; }
        else {
            $pdo->prepare('UPDATE users SET nickname=? WHERE id=?')
                ->execute([$nickname, $fullUser['id']]);
            $success = '昵称已更新';
            $fullUser['nickname'] = $nickname;
        }
    }

    // --- 邮箱验证改密码 ---
    if ($action === 'change_password') {
        $code     = trim($_POST['verify_code'] ?? '');
        $newPass  = $_POST['new_password']  ?? '';
        $newPass2 = $_POST['new_password2'] ?? '';

        if ($fullUser['email'] === '') {
            $errors[] = '请先绑定邮箱后才能通过邮箱验证改密码';
        } elseif ($code === '') {
            $errors[] = '请输入邮箱验证码';
        } elseif (strlen($newPass) < 6) {
            $errors[] = '新密码不能少于6位';
        } elseif ($newPass !== $newPass2) {
            $errors[] = '两次密码不一致';
        } else {
            if (!verifyCode($fullUser['email'], 'reset_pwd', $code)) {
                $errors[] = '验证码错误或已过期';
            } else {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                    ->execute([$hash, $fullUser['id']]);
                $success = '密码修改成功';
            }
        }
    }

    // --- 换绑邮箱 ---
    if ($action === 'rebind_email') {
        $newEmail = strtolower(trim($_POST['new_email'] ?? ''));
        $code     = trim($_POST['rebind_code'] ?? '');

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '请输入正确的邮箱地址';
        } elseif ($newEmail === $fullUser['email']) {
            $errors[] = '新邮箱与当前邮箱相同';
        } elseif ($code === '') {
            $errors[] = '请输入验证码';
        } else {
            // 检查邮箱是否已被使用
            $stmt2 = $pdo->prepare('SELECT id FROM users WHERE email=? AND id!=?');
            $stmt2->execute([$newEmail, $fullUser['id']]);
            if ($stmt2->fetch()) {
                $errors[] = '该邮箱已被其他账号绑定';
            } elseif (!verifyCode($newEmail, 'rebind', $code)) {
                $errors[] = '验证码错误或已过期';
            } else {
                $pdo->prepare('UPDATE users SET email=?, email_verified=1 WHERE id=?')
                    ->execute([$newEmail, $fullUser['id']]);
                $success = '邮箱更换成功';
                $fullUser['email'] = $newEmail;
            }
        }
    }
}

// 获取该用户发布的Bot列表
$stmt = $pdo->prepare('SELECT id, nickname, status, platform, view_count, created_at FROM bots WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$currentUser['id']]);
$myBots = $stmt->fetchAll();

$pageTitle = '个人中心';
require_once __DIR__ . '/includes/header.php';
$csrfToken = e(getCsrfToken());
?>

<div class="container">
  <div class="page-header">
    <h1>个人中心</h1>
    <p>管理你的账号信息和Bot</p>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>
  <?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <div class="profile-layout">

    <!-- 左侧：账号信息 -->
    <div style="display:flex;flex-direction:column;gap:20px;">

      <!-- 头像 & 基本信息 -->
      <div class="card">
        <div class="card-body" style="text-align:center;padding-top:28px;">
          <div style="width:72px;height:72px;background:linear-gradient(135deg,var(--blue-primary),var(--blue-dark));border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:1.8rem;font-weight:700;color:#fff;box-shadow:0 4px 16px var(--blue-glow);">
            <?= mb_substr($fullUser['nickname'] ?: $fullUser['username'], 0, 1) ?>
          </div>
          <div class="font-bold" style="font-size:1.05rem;"><?= e($fullUser['nickname'] ?: $fullUser['username']) ?></div>
          <div class="text-sm text-gray">@<?= e($fullUser['username']) ?></div>
          <?php if ($fullUser['is_admin']): ?>
          <span class="badge badge-gold mt-1" style="display:inline-flex;">管理员</span>
          <?php endif; ?>
          <div class="text-xs text-gray mt-1">注册于 <?= formatTime($fullUser['created_at']) ?></div>
        </div>
      </div>

      <!-- 修改昵称 -->
      <div class="card">
        <div class="card-header">
          <h3>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:6px;color:var(--blue-primary);"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            修改昵称
          </h3>
        </div>
        <div class="card-body">
          <form method="POST" action="/profile.php">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="nickname">
            <div class="form-group">
              <input type="text" name="nickname" class="form-control"
                     value="<?= e($fullUser['nickname']) ?>" placeholder="昵称" maxlength="50">
            </div>
            <button type="submit" class="btn btn-primary btn-sm w-100">保存昵称</button>
          </form>
        </div>
      </div>

      <!-- 修改密码（邮箱验证） -->
      <div class="card">
        <div class="card-header">
          <h3>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:6px;color:var(--blue-primary);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            修改密码
          </h3>
        </div>
        <div class="card-body">
          <?php if (!$fullUser['email']): ?>
          <div class="alert alert-warning" style="font-size:0.85rem;">请先绑定邮箱，才能通过邮箱验证修改密码。</div>
          <?php else: ?>
          <form method="POST" action="/profile.php" id="pwdForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
              <label class="form-label" style="font-size:0.82rem;">邮箱验证码
                <span class="text-gray text-xs">（发至 <?= e($fullUser['email']) ?>）</span>
              </label>
              <div class="email-code-row">
                <input type="text" name="verify_code" id="pwdCode" class="form-control"
                       placeholder="6位验证码" maxlength="6" inputmode="numeric" pattern="[0-9]{6}">
                <button type="button" class="btn btn-outline btn-sm" id="pwdCodeBtn"
                        onclick="sendPwdCode()">获取验证码</button>
              </div>
            </div>
            <div class="form-group">
              <input type="password" name="new_password" class="form-control mb-1"
                     placeholder="新密码（至少6位）" minlength="6" autocomplete="new-password">
            </div>
            <div class="form-group">
              <input type="password" name="new_password2" class="form-control"
                     placeholder="确认新密码" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-sm w-100">修改密码</button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- 换绑邮箱 -->
      <div class="card">
        <div class="card-header">
          <h3>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:6px;color:var(--blue-primary);"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            绑定/换绑邮箱
          </h3>
        </div>
        <div class="card-body">
          <?php if ($fullUser['email']): ?>
          <div class="form-hint mb-2" style="font-size:0.85rem;">
            当前绑定：<strong><?= e($fullUser['email']) ?></strong>
            <?= $fullUser['email_verified'] ? '<span class="badge badge-green" style="font-size:0.72rem;margin-left:6px;">已验证</span>' : '<span class="badge badge-orange" style="font-size:0.72rem;margin-left:6px;">未验证</span>' ?>
          </div>
          <?php endif; ?>
          <form method="POST" action="/profile.php" id="rebindForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="rebind_email">
            <div class="form-group">
              <input type="email" name="new_email" id="newEmailInput" class="form-control"
                     placeholder="新邮箱地址" required maxlength="255" autocomplete="email">
            </div>
            <div class="form-group">
              <div class="email-code-row">
                <input type="text" name="rebind_code" id="rebindCode" class="form-control"
                       placeholder="发至新邮箱的验证码" maxlength="6" inputmode="numeric" pattern="[0-9]{6}">
                <button type="button" class="btn btn-outline btn-sm" id="rebindCodeBtn"
                        onclick="sendRebindCode()">获取验证码</button>
              </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm w-100">
              <?= $fullUser['email'] ? '确认换绑' : '绑定邮箱' ?>
            </button>
          </form>
        </div>
      </div>

    </div><!-- /左侧 -->

    <!-- 右侧：Bot列表 -->
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
            <tr><th>昵称</th><th>平台</th><th>状态</th><th>浏览</th><th>发布时间</th><th>操作</th></tr>
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
    </div><!-- /右侧 -->

  </div><!-- /profile-layout -->
</div>

<style>
.profile-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 24px;
    align-items: start;
}
.email-code-row { display:flex;gap:10px;align-items:stretch; }
.email-code-row input { flex:1;min-width:0; }
.email-code-row .btn { flex-shrink:0;white-space:nowrap;height:42px; }
@media (max-width: 768px) {
    .profile-layout { grid-template-columns: 1fr; }
}
</style>

<script>
var pwdCooldown = 0, rebindCooldown = 0;

function sendPwdCode() {
    if (pwdCooldown > 0) return;
    var btn = document.getElementById('pwdCodeBtn');
    btn.disabled = true; btn.textContent = '发送中...';
    var fd = new FormData();
    fd.append('email',      '<?= e($fullUser['email']) ?>');
    fd.append('purpose',    'reset_pwd');
    fd.append('csrf_token', '<?= $csrfToken ?>');
    fetch('/send_code.php', {method:'POST',body:fd}).then(r=>r.json()).then(data => {
        alert(data.msg);
        if (data.ok) startCD('pwd', btn, 60);
        else { btn.disabled=false; btn.textContent='获取验证码'; }
    });
}

function sendRebindCode() {
    if (rebindCooldown > 0) return;
    var email = document.getElementById('newEmailInput').value.trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('请先填写新邮箱地址'); return;
    }
    var btn = document.getElementById('rebindCodeBtn');
    btn.disabled = true; btn.textContent = '发送中...';
    var fd = new FormData();
    fd.append('email',      email);
    fd.append('purpose',    'rebind');
    fd.append('csrf_token', '<?= $csrfToken ?>');
    fetch('/send_code.php', {method:'POST',body:fd}).then(r=>r.json()).then(data => {
        alert(data.msg);
        if (data.ok) startCD('rebind', btn, 60);
        else { btn.disabled=false; btn.textContent='获取验证码'; }
    });
}

function startCD(type, btn, s) {
    if (type === 'pwd') pwdCooldown = s;
    else rebindCooldown = s;
    btn.textContent = s + 's 后重试';
    var t = setInterval(function() {
        if (type === 'pwd') pwdCooldown--;
        else rebindCooldown--;
        var cur = (type === 'pwd') ? pwdCooldown : rebindCooldown;
        if (cur <= 0) { clearInterval(t); btn.disabled=false; btn.textContent='重新获取'; }
        else btn.textContent = cur + 's 后重试';
    }, 1000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
