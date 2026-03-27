<?php
/**
 * 忘记密码 - 邮箱验证码重置密码
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

if (isLoggedIn()) { header('Location: /profile.php'); exit; }

$step   = $_GET['step'] ?? 'email';   // email → verify → done
$errors = [];
$email  = trim($_SESSION['fp_email'] ?? '');

// Step 1: 输入邮箱 & 发送验证码（表单提交）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'email') {
    verifyCsrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '请输入正确的邮箱地址';
    } else {
        $pdo  = getDB();
        $stmt = $pdo->prepare('SELECT id, username, nickname FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $errors[] = '该邮箱未绑定任何账号';
        } else {
            // 60秒限频
            $stmt2 = $pdo->prepare(
                'SELECT id FROM verify_codes WHERE email=? AND purpose="reset_pwd" AND used=0 AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND) LIMIT 1'
            );
            $stmt2->execute([$email]);
            if ($stmt2->fetch()) {
                $errors[] = '发送太频繁，请60秒后重试';
            } else {
                $uname = $user['nickname'] ?: $user['username'];
                if (sendVerifyCode($email, 'reset_pwd', $uname)) {
                    initSession();
                    $_SESSION['fp_email'] = $email;
                    header('Location: /forgot_password.php?step=verify');
                    exit;
                } else {
                    $errors[] = '邮件发送失败，请检查邮箱或稍后重试';
                }
            }
        }
    }
}

// Step 2: 输入验证码 + 新密码
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'verify') {
    verifyCsrf();
    initSession();
    $email    = $_SESSION['fp_email'] ?? '';
    $code     = trim($_POST['verify_code'] ?? '');
    $newPass  = $_POST['new_password']  ?? '';
    $newPass2 = $_POST['new_password2'] ?? '';

    if ($email === '')            $errors[] = '会话已过期，请重新操作';
    if ($code === '')             $errors[] = '请输入验证码';
    if (strlen($newPass) < 6)    $errors[] = '密码不能少于6位';
    if ($newPass !== $newPass2)  $errors[] = '两次密码不一致';

    if (empty($errors)) {
        if (!verifyCode($email, 'reset_pwd', $code)) {
            $errors[] = '验证码错误或已过期，请重新获取';
        } else {
            $pdo  = getDB();
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password=? WHERE email=?')->execute([$hash, $email]);
            unset($_SESSION['fp_email']);
            setFlash('success', '密码重置成功，请用新密码登录');
            header('Location: /login.php');
            exit;
        }
    }
}

$pageTitle = '忘记密码';
require_once __DIR__ . '/includes/header.php';
$csrfToken = e(getCsrfToken());
?>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-card-header">
      <div class="auth-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>
      <h1>重置密码</h1>
      <p><?= $step === 'verify' ? '输入验证码和新密码' : '通过绑定邮箱验证身份' ?></p>
    </div>

    <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 'email'): ?>
    <!-- Step1: 输入邮箱 -->
    <form method="POST" action="/forgot_password.php?step=email">
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
      <div class="form-group">
        <label class="form-label">绑定邮箱</label>
        <input type="email" name="email" class="form-control"
               placeholder="输入注册时绑定的邮箱" required autofocus autocomplete="email">
        <div class="form-hint">验证码将发送至该邮箱</div>
      </div>
      <button type="submit" class="btn btn-primary w-100 btn-lg">
        发送验证码
      </button>
    </form>

    <?php else: ?>
    <!-- Step2: 输入验证码 + 新密码 -->
    <div class="alert alert-info" style="margin-bottom:16px;">
      验证码已发送至 <strong><?= e($email) ?></strong>
    </div>
    <form method="POST" action="/forgot_password.php?step=verify">
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
      <div class="form-group">
        <label class="form-label">邮箱验证码</label>
        <div class="email-code-row">
          <input type="text" name="verify_code" id="verify_code" class="form-control"
                 placeholder="6位验证码" maxlength="6" required inputmode="numeric" pattern="[0-9]{6}">
          <button type="button" class="btn btn-outline btn-sm" id="resendBtn" onclick="resendCode()">重新获取</button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">新密码</label>
        <input type="password" name="new_password" class="form-control"
               placeholder="至少6位" required minlength="6" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label">确认新密码</label>
        <input type="password" name="new_password2" class="form-control"
               placeholder="再次输入新密码" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary w-100 btn-lg">重置密码</button>
    </form>
    <?php endif; ?>

    <div class="auth-footer">
      <a href="/login.php">← 返回登录</a>
    </div>
  </div>
</div>

<style>
.email-code-row { display:flex;gap:10px;align-items:stretch; }
.email-code-row input { flex:1;min-width:0; }
.email-code-row .btn { flex-shrink:0;white-space:nowrap;height:42px; }
</style>

<?php if ($step === 'verify'): ?>
<script>
var cooldown = 0;
function resendCode() {
    if (cooldown > 0) return;
    var btn = document.getElementById('resendBtn');
    btn.disabled = true;
    btn.textContent = '发送中...';
    var fd = new FormData();
    fd.append('email',      '<?= e($email) ?>');
    fd.append('purpose',    'reset_pwd');
    fd.append('csrf_token', '<?= $csrfToken ?>');
    fetch('/send_code.php', { method:'POST', body:fd })
        .then(r => r.json())
        .then(data => {
            alert(data.msg || (data.ok ? '已发送' : '发送失败'));
            if (data.ok) startCD(btn, 60);
            else { btn.disabled = false; btn.textContent = '重新获取'; }
        });
}
function startCD(btn, s) {
    cooldown = s;
    btn.textContent = s + 's 后重试';
    var t = setInterval(function() {
        cooldown--;
        if (cooldown <= 0) { clearInterval(t); btn.disabled=false; btn.textContent='重新获取'; }
        else btn.textContent = cooldown + 's 后重试';
    }, 1000);
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
