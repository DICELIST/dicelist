<?php
/**
 * 登录页
 */
require_once __DIR__ . '/includes/functions.php';

// 已登录跳转
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '') $errors[] = '请输入用户名';
    if ($password === '') $errors[] = '请输入密码';

    if (empty($errors)) {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id, username, password, nickname, is_admin FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            initSession();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            setFlash('success', '欢迎回来，' . ($user['nickname'] ?: $user['username']) . '！');
            $redirect = $_GET['redirect'] ?? '/index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $errors[] = '用户名或密码错误';
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
      <p>登录你的账号继续使用</p>
    </div>

    <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="/login.php">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <div class="form-group">
        <label class="form-label" for="username">用户名</label>
        <input type="text" id="username" name="username" class="form-control"
               placeholder="输入用户名" value="<?= e($username) ?>" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">密码</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="输入密码" required>
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
