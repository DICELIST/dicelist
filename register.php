<?php
/**
 * 注册页
 */
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$errors = [];
$username = $nickname = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2= $_POST['password2'] ?? '';

    // 验证
    if ($username === '') $errors[] = '请输入用户名';
    elseif (!preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $username))
        $errors[] = '用户名只能包含字母、数字、下划线和横线，长度3-30';
    if ($password === '') $errors[] = '请输入密码';
    elseif (strlen($password) < 6) $errors[] = '密码不能少于6位';
    if ($password !== $password2) $errors[] = '两次密码不一致';
    if ($nickname === '') $nickname = $username;

    if (empty($errors)) {
        $pdo = getDB();
        // 检查用户名重复
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $errors[] = '用户名已被注册';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password, nickname) VALUES (?, ?, ?)');
            $stmt->execute([$username, $hash, $nickname]);
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

    <form method="POST" action="/register.php">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <div class="form-group">
        <label class="form-label" for="username">用户名 <span class="required">*</span></label>
        <input type="text" id="username" name="username" class="form-control"
               placeholder="3-30位字母、数字、下划线" value="<?= e($username) ?>" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label" for="nickname">昵称</label>
        <input type="text" id="nickname" name="nickname" class="form-control"
               placeholder="显示给其他用户的名字（可选）" value="<?= e($nickname) ?>">
      </div>
      <div class="form-group">
        <label class="form-label" for="password">密码 <span class="required">*</span></label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="至少6位" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="password2">确认密码 <span class="required">*</span></label>
        <input type="password" id="password2" name="password2" class="form-control"
               placeholder="再次输入密码" required>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
