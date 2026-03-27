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

    if ($account === '') $errors[] = '请输入账号';
    if ($password === '') $errors[] = '请输入密码';

    if (empty($errors)) {
        $pdo = getDB();
        // 按用户名、昵称、邮箱三路查找
        $stmt = $pdo->prepare(
            'SELECT id, username, password, nickname, is_admin
             FROM users
             WHERE username = ? OR nickname = ? OR email = ?
             LIMIT 1'
        );
        $stmt->execute([$account, $account, $account]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            initSession();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            setFlash('success', '欢迎回来，' . ($user['nickname'] ?: $user['username']) . '！');
            // 防止重定向注入：只允许站内路径
            $redirect = $_GET['redirect'] ?? '';
            if (!preg_match('/^\/[a-zA-Z0-9\-_.\/\?=&%]*$/', $redirect)) {
                $redirect = '/index.php';
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            $errors[] = '账号或密码错误';
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
