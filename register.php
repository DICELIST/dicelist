<?php
/**
 * 注册页 - 强制绑定邮箱，需验证码
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

if (isLoggedIn()) { header('Location: /index.php'); exit; }

$errors   = [];
$username = $nickname = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username  = trim($_POST['username']  ?? '');
    $nickname  = trim($_POST['nickname']  ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $code      = trim($_POST['verify_code'] ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';

    // 基础验证
    if ($username === '') $errors[] = '请输入用户名';
    elseif (!preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $username))
        $errors[] = '用户名只能包含字母、数字、下划线和横线，长度3-30';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = '请输入正确的邮箱地址';

    if ($code === '') $errors[] = '请输入邮箱验证码';

    if ($password === '') $errors[] = '请输入密码';
    elseif (strlen($password) < 6) $errors[] = '密码不能少于6位';
    if ($password !== $password2) $errors[] = '两次密码不一致';

    if ($nickname === '') $nickname = $username;

    if (empty($errors)) {
        $pdo = getDB();

        // 用户名重复检查
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) { $errors[] = '用户名已被注册'; }

        // 邮箱重复检查
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) { $errors[] = '该邮箱已被注册'; }

        // 验证码校验
        if (empty($errors)) {
            if (!verifyCode($email, 'register', $code)) {
                $errors[] = '验证码错误或已过期，请重新获取';
            }
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password, nickname, email, email_verified) VALUES (?, ?, ?, ?, 1)');
            $stmt->execute([$username, $hash, $nickname, $email]);
            $userId = $pdo->lastInsertId();

            initSession();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            setFlash('success', '注册成功，欢迎加入！');
            header('Location: /index.php');
            exit;
        }
    }
}

$pageTitle = '注册';
require_once __DIR__ . '/includes/header.php';
$csrfToken = e(getCsrfToken());
?>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-card-header">
      <div class="auth-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      </div>
      <h1>创建账号</h1>
      <p>注册后即可提交你的Bot</p>
    </div>

    <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="/register.php" id="registerForm">
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

      <div class="form-group">
        <label class="form-label" for="username">用户名 <span class="required">*</span></label>
        <input type="text" id="username" name="username" class="form-control"
               placeholder="3-30位字母、数字、下划线" value="<?= e($username) ?>" required autofocus
               maxlength="30" autocomplete="username">
      </div>

      <div class="form-group">
        <label class="form-label" for="nickname">昵称</label>
        <input type="text" id="nickname" name="nickname" class="form-control"
               placeholder="显示给其他用户的名字（可选）" value="<?= e($nickname) ?>" maxlength="50">
      </div>

      <!-- 邮箱 + 验证码 -->
      <div class="form-group">
        <label class="form-label" for="email">邮箱 <span class="required">*</span></label>
        <div class="email-code-row">
          <input type="email" id="email" name="email" class="form-control"
                 placeholder="用于验证和找回密码" value="<?= e($email) ?>" required maxlength="255" autocomplete="email">
          <button type="button" class="btn btn-outline btn-sm" id="sendCodeBtn" onclick="sendCode()">
            获取验证码
          </button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="verify_code">邮箱验证码 <span class="required">*</span></label>
        <input type="text" id="verify_code" name="verify_code" class="form-control"
               placeholder="请输入6位验证码" maxlength="6" autocomplete="one-time-code"
               inputmode="numeric" pattern="[0-9]{6}">
      </div>

      <div class="form-group">
        <label class="form-label" for="password">密码 <span class="required">*</span></label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="至少6位" required minlength="6" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label" for="password2">确认密码 <span class="required">*</span></label>
        <input type="password" id="password2" name="password2" class="form-control"
               placeholder="再次输入密码" required autocomplete="new-password">
      </div>

      <button type="submit" class="btn btn-primary w-100 btn-lg">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        注册账号
      </button>
    </form>

    <div class="auth-footer">
      已有账号？<a href="/login.php">立即登录</a>
    </div>
  </div>
</div>

<style>
.email-code-row {
    display: flex;
    gap: 10px;
    align-items: stretch;
}
.email-code-row input { flex: 1; min-width: 0; }
.email-code-row .btn { flex-shrink: 0; white-space: nowrap; height: 42px; }
</style>

<script>
var sendCodeCooldown = 0;
var sendCodeTimer = null;

function sendCode() {
    if (sendCodeCooldown > 0) return;
    var email = document.getElementById('email').value.trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('请先输入正确的邮箱地址');
        document.getElementById('email').focus();
        return;
    }
    var btn = document.getElementById('sendCodeBtn');
    btn.disabled = true;
    btn.textContent = '发送中...';

    var fd = new FormData();
    fd.append('email',      email);
    fd.append('purpose',    'register');
    fd.append('csrf_token', '<?= $csrfToken ?>');

    fetch('/send_code.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                startCooldown(btn, 60);
                document.getElementById('verify_code').focus();
            } else {
                alert(data.msg || '发送失败');
                btn.disabled = false;
                btn.textContent = '获取验证码';
            }
        })
        .catch(() => {
            alert('网络错误，请重试');
            btn.disabled = false;
            btn.textContent = '获取验证码';
        });
}

function startCooldown(btn, seconds) {
    sendCodeCooldown = seconds;
    btn.disabled = true;
    btn.textContent = seconds + 's 后重试';
    sendCodeTimer = setInterval(function() {
        sendCodeCooldown--;
        if (sendCodeCooldown <= 0) {
            clearInterval(sendCodeTimer);
            btn.disabled = false;
            btn.textContent = '重新获取';
        } else {
            btn.textContent = sendCodeCooldown + 's 后重试';
        }
    }, 1000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
