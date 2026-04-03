<?php
/**
 * 登录页 - 支持用户名/昵称/邮箱三合一登录
 */
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) { header('Location: /index.php'); exit; }

$errors  = [];
$account = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $account  = trim($_POST['account']  ?? '');
    $password = $_POST['password'] ?? '';
    $captcha  = strtolower(trim($_POST['captcha'] ?? ''));

    if ($account === '') $errors[] = '请输入账号';
    if ($password === '') $errors[] = '请输入密码';

    // 图形验证码校验
    initSession();
    if (empty($errors)) {
        $savedCaptcha = strtolower($_SESSION['captcha_code'] ?? '');
        if ($savedCaptcha === '' || $captcha === '') {
            $errors[] = '请输入图形验证码';
        } elseif ($captcha !== $savedCaptcha) {
            $errors[] = '图形验证码错误，请重试';
            unset($_SESSION['captcha_code']);
        } else {
            unset($_SESSION['captcha_code']);
        }
    }

    // IP 登录失败次数限制
    if (empty($errors) && !checkLoginAttempts()) {
        $errors[] = '今日登录失败次数过多，请明天再试或联系管理员';
    }

    if (empty($errors)) {
        $pdo = getDB();
        // 按用户名、昵称、邮箱三路查找
        $stmt = $pdo->prepare(
            'SELECT id, username, password, nickname, is_admin, is_super, is_banned, ban_reason
             FROM users
             WHERE username = ? OR nickname = ? OR email = ?
             LIMIT 1'
        );
        $stmt->execute([$account, $account, $account]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // 封禁检查
            if ($user['is_banned']) {
                $reason = $user['ban_reason'] ? '原因：' . $user['ban_reason'] : '';
                $errors[] = '该账号已被封禁，无法登录。' . $reason;
            } else {
                // 超级管理员设备绑定校验
                if ($user['is_super']) {
                    $boundDevice = getSiteSetting('super_admin_device', '');
                    if ($boundDevice !== '') {
                        $curDevice = getSuperDeviceFingerprint();
                        if ($curDevice !== $boundDevice) {
                            $errors[] = '超级管理员账号只能从绑定设备登录，当前设备未授权。';
                            recordLoginFailure($account);
                        }
                    } else {
                        // 首次登录，自动绑定当前设备
                        $curDevice = getSuperDeviceFingerprint();
                        setSiteSetting('super_admin_device', $curDevice);
                    }
                }

                if (empty($errors)) {
                    initSession();
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    // 保持登录
                    if (!empty($_POST['remember_me'])) {
                        setRememberMeToken((int)$user['id']);
                    }
                    setFlash('success', '欢迎回来，' . ($user['nickname'] ?: $user['username']) . '！');
                    // 防止重定向注入：只允许站内路径
                    $redirect = $_GET['redirect'] ?? '';
                    if (!preg_match('/^\/[a-zA-Z0-9\-_.\/\?=&%]*$/', $redirect)) {
                        $redirect = '/index.php';
                    }
                    header('Location: ' . $redirect);
                    exit;
                }
            }
        } else {
            $errors[] = '账号或密码错误';
            recordLoginFailure($account);
        }
    }
}

$pageTitle = '登录';
require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-card-header">
      <div class="auth-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      </div>
      <h1>欢迎回来</h1>
      <p>使用用户名、昵称或邮箱登录</p>
    </div>

    <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="/login.php">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <div class="form-group">
        <label class="form-label" for="account">账号</label>
        <input type="text" id="account" name="account" class="form-control"
               placeholder="用户名 / 昵称 / 邮箱" value="<?= e($account) ?>"
               required autofocus autocomplete="username">
      </div>
      <div class="form-group">
        <label class="form-label" for="password" style="display:flex;justify-content:space-between;align-items:center;">
          <span>密码</span>
          <a href="/forgot_password.php" style="font-size:0.8rem;color:var(--blue-primary);font-weight:400;">忘记密码？</a>
        </label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="输入密码" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label class="form-label">图形验证码</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="text" id="loginCaptcha" name="captcha" class="form-control"
                 placeholder="输入图中字符" maxlength="4" required autocomplete="off"
                 style="flex:1;letter-spacing:4px;font-size:1rem;font-weight:600;text-transform:uppercase;">
          <img src="/captcha.php" id="loginCaptchaImg"
               style="height:40px;border-radius:6px;border:1px solid var(--border);cursor:pointer;flex-shrink:0;"
               onclick="this.src='/captcha.php?t='+Date.now()" title="点击刷新">
        </div>
        <div style="font-size:0.78rem;color:var(--text-sub);margin-top:4px;">看不清？<a href="#" onclick="document.getElementById('loginCaptchaImg').src='/captcha.php?t='+Date.now();return false;" style="color:var(--blue-primary);">点击刷新</a></div>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:0.88rem;color:var(--text-main);user-select:none;">
          <input type="checkbox" name="remember_me" value="1"
                 style="width:16px;height:16px;accent-color:var(--blue-primary);cursor:pointer;">
          保持登录（30天）
        </label>
      </div>
      <button type="submit" class="btn btn-primary w-100 btn-lg">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        登录
      </button>
    </form>

    <div class="auth-footer">
      还没有账号？<a href="/register.php">立即注册</a>
    </div>
  </div>
</div>


<?php if (!empty($errors)): ?>
<script>
// 登录失败：自动刷新图形验证码
document.addEventListener('DOMContentLoaded', function() {
    var img = document.getElementById('loginCaptchaImg');
    if (img) img.src = '/captcha.php?t=' + Date.now();
    var inp = document.getElementById('loginCaptcha');
    if (inp) inp.value = '';
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
