<?php
/**
 * 注册页 - 强制绑定邮箱，需验证码
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

if (isLoggedIn()) { header('Location: /index.php'); exit; }

$errors   = [];
$username = $nickname = $email = '';

// 读取注册协议（前台展示用）
$regAgreement = getAgreement('register_agreement');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username  = trim($_POST['username']  ?? '');
    $nickname  = trim($_POST['nickname']  ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $code      = trim($_POST['verify_code'] ?? '');
    $password  = $_POST['password']  ?? '';
    $password2 = $_POST['password2'] ?? '';
    $agreeReg  = isset($_POST['agree_register']);

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

    // 协议勾选验证（仅在有协议内容时强制）
    if ($regAgreement['content'] && !$agreeReg) {
        $errors[] = '请阅读并同意注册协议后继续';
    }

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
            $stmt = $pdo->prepare('INSERT INTO users (username, password, nickname, nickname_lower, email, email_verified) VALUES (?, ?, ?, ?, ?, 1)');
            $stmt->execute([$username, $hash, $nickname, mb_strtolower($nickname, 'UTF-8'), $email]);
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
        <div class="unique-input-wrap">
          <input type="text" id="username" name="username" class="form-control"
                 placeholder="3-30位字母、数字、下划线" value="<?= e($username) ?>" required autofocus
                 maxlength="30" autocomplete="username">
          <span class="unique-icon" id="usernameIcon"></span>
        </div>
        <div class="unique-hint" id="usernameHint"></div>
      </div>

      <div class="form-group">
        <label class="form-label" for="nickname">昵称</label>
        <div class="unique-input-wrap">
          <input type="text" id="nickname" name="nickname" class="form-control"
                 placeholder="显示给其他用户的名字（可选）" value="<?= e($nickname) ?>" maxlength="50">
          <span class="unique-icon" id="nicknameIcon"></span>
        </div>
        <div class="unique-hint" id="nicknameHint"></div>
      </div>

      <!-- 邮箱 + 验证码 -->
      <div class="form-group">
        <label class="form-label" for="email">邮箱 <span class="required">*</span></label>
        <div class="email-code-row">
          <input type="email" id="email" name="email" class="form-control"
                 placeholder="用于验证和找回密码" value="<?= e($email) ?>" required maxlength="255" autocomplete="email">
          <button type="button" class="btn btn-outline btn-sm" id="sendCodeBtn" onclick="checkCaptchaThenSend()">
            获取验证码
          </button>
        </div>
      </div>

      <!-- 图形验证码行（点击获取验证码时显示） -->
      <div class="form-group" id="captchaRow" style="display:none;">
        <label class="form-label">图形验证码</label>
        <div class="captcha-row">
          <input type="text" id="captchaInput" class="form-control captcha-input"
                 placeholder="输入图中字符" maxlength="4" autocomplete="off" style="text-transform:uppercase;">
          <img src="/captcha.php" id="captchaImg" class="captcha-img" onclick="refreshCaptcha()" title="点击刷新">
          <button type="button" class="btn btn-ghost btn-sm" onclick="refreshCaptcha()">刷新</button>
        </div>
        <div id="captchaHint" class="unique-hint" style="margin-top:4px;"></div>
        <button type="button" class="btn btn-primary btn-sm mt-1" id="captchaConfirmBtn" onclick="verifyCaptchaThenSendCode()">
          确认并发送验证码
        </button>
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

      <?php if ($regAgreement['content']): ?>
      <div class="form-group agreement-check-row">
        <label class="agreement-check-label">
          <input type="checkbox" name="agree_register" id="agreeRegister"
                 <?= (isset($_POST['agree_register'])) ? 'checked' : '' ?> required>
          <span>
            我已阅读并同意
            <a href="#" class="agreement-link" onclick="showAgreementModal('reg'); return false;">
              《<?= e($regAgreement['title'] ?: '注册协议') ?>》
            </a>
          </span>
        </label>
      </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary w-100 btn-lg">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
        注册账号
      </button>
    </form>
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
.agreement-check-row { margin-top:4px; }
.agreement-check-label { display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:0.88rem;line-height:1.5; }
.agreement-check-label input[type=checkbox] { margin-top:3px;flex-shrink:0;width:16px;height:16px;accent-color:var(--blue-primary); }
.agreement-link { color:var(--blue-primary);text-decoration:underline; }
/* 图形验证码 */
.captcha-row { display:flex;gap:8px;align-items:center;flex-wrap:wrap; }
.captcha-input { flex:1;min-width:0;max-width:120px;letter-spacing:4px;font-size:1.1rem;font-weight:600; }
.captcha-img { height:40px;border-radius:6px;border:1px solid var(--border);cursor:pointer;flex-shrink:0;display:block; }
/* 协议弹窗 */
.agreement-modal-overlay {
    display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:2000;
    align-items:center;justify-content:center;padding:16px;
}
.agreement-modal-overlay.open { display:flex; }
.agreement-modal {
    background:var(--white);border-radius:var(--radius-lg);width:100%;max-width:560px;
    max-height:80vh;display:flex;flex-direction:column;box-shadow:var(--shadow-lg);
}
.agreement-modal-head {
    padding:16px 20px;border-bottom:1px solid var(--border);
    display:flex;justify-content:space-between;align-items:center;
}
.agreement-modal-head h3 { font-size:1rem;font-weight:700;margin:0; }
.agreement-modal-body {
    flex:1;overflow-y:auto;padding:20px;
    font-size:0.88rem;line-height:1.8;color:var(--text-main);
}
/* Markdown 渲染样式 */
.agreement-modal-body h1,.agreement-modal-body h2,.agreement-modal-body h3,
.agreement-modal-body h4,.agreement-modal-body h5,.agreement-modal-body h6 {
    font-weight:700;margin:1em 0 0.4em;line-height:1.3;
}
.agreement-modal-body h1{font-size:1.2em;}
.agreement-modal-body h2{font-size:1.1em;}
.agreement-modal-body h3{font-size:1em;}
.agreement-modal-body p{margin:0 0 0.7em;}
.agreement-modal-body ul,.agreement-modal-body ol{padding-left:1.5em;margin:0 0 0.7em;}
.agreement-modal-body li{margin-bottom:0.2em;}
.agreement-modal-body strong{font-weight:700;}
.agreement-modal-body em{font-style:italic;}
.agreement-modal-body hr{border:none;border-top:1px solid var(--border);margin:1em 0;}
.agreement-modal-body code{background:rgba(0,0,0,0.06);padding:1px 4px;border-radius:3px;font-family:monospace;}
.agreement-modal-body pre{background:rgba(0,0,0,0.06);padding:10px;border-radius:6px;overflow-x:auto;margin:0 0 0.7em;}
.agreement-modal-body blockquote{border-left:3px solid var(--border);padding-left:12px;color:var(--text-sub);margin:0 0 0.7em;}
.agreement-modal-foot {
    padding:14px 20px;border-top:1px solid var(--border);
    display:flex;justify-content:flex-end;gap:10px;
}
</style>

<?php if ($regAgreement['content']): ?>
<!-- 协议弹窗 -->
<div class="agreement-modal-overlay" id="agreeModal">
  <div class="agreement-modal">
    <div class="agreement-modal-head">
      <h3><?= e($regAgreement['title'] ?: '注册协议') ?></h3>
      <button type="button" onclick="closeAgreementModal()" style="background:none;border:none;cursor:pointer;font-size:1.4rem;color:var(--text-sub);line-height:1;">&times;</button>
    </div>
    <div class="agreement-modal-body" id="agreeModalBody"><!-- 由 JS 渲染 --></div>
    <div class="agreement-modal-foot">
      <button type="button" class="btn btn-ghost btn-sm" onclick="closeAgreementModal()">关闭</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="agreeAndClose()">我已阅读，同意</button>
    </div>
  </div>
</div>
<script>
var _regAgreementMd = <?= json_encode($regAgreement['content']) ?>;

function _renderAgreementMd(target, mdText) {
    if (typeof marked !== 'undefined') {
        target.innerHTML = marked.parse(mdText);
    } else {
        // fallback：plain text
        target.textContent = mdText;
    }
}

function showAgreementModal(type) {
    var modal = document.getElementById('agreeModal');
    var body  = document.getElementById('agreeModalBody');
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    if (!body.dataset.rendered) {
        _renderAgreementMd(body, _regAgreementMd);
        body.dataset.rendered = '1';
    }
}
function closeAgreementModal() {
    document.getElementById('agreeModal').classList.remove('open');
    document.body.style.overflow = '';
}
function agreeAndClose() {
    var cb = document.getElementById('agreeRegister');
    if (cb) cb.checked = true;
    closeAgreementModal();
}
document.getElementById('agreeModal').addEventListener('click', function(e) {
    if (e.target === this) closeAgreementModal();
});
</script>
<?php endif; ?>

<style>
/* 唯一性检测输入框 */
.unique-input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.unique-input-wrap .form-control {
    padding-right: 36px;
}
.unique-icon {
    position: absolute;
    right: 10px;
    font-size: 1.1rem;
    line-height: 1;
    pointer-events: none;
    transition: opacity 0.15s;
}
.unique-icon.ok    { color: #34c759; }
.unique-icon.error { color: #ff3b30; }
.unique-icon.loading { color: #aaa; font-size: 0.85rem; }
.unique-hint {
    font-size: 0.80rem;
    margin-top: 4px;
    min-height: 1.1em;
    transition: all 0.15s;
}
.unique-hint.ok    { color: #34c759; }
.unique-hint.error { color: #ff3b30; }
</style>

<script>
/* ===== 用户名 / 昵称 唯一性实时检测 ===== */
function setupUniqueCheck(inputId, iconId, hintId, type, excludeId) {
    var input = document.getElementById(inputId);
    var icon  = document.getElementById(iconId);
    var hint  = document.getElementById(hintId);
    if (!input) return;

    var timer = null;

    function clear() {
        icon.textContent = '';
        icon.className   = 'unique-icon';
        hint.textContent = '';
        hint.className   = 'unique-hint';
    }

    function check() {
        var val = input.value.trim();
        if (val === '') { clear(); return; }

        // 立即显示加载状态
        icon.textContent = '…';
        icon.className   = 'unique-icon loading';
        hint.textContent = '检测中...';
        hint.className   = 'unique-hint';

        var url = '/check_unique.php?type=' + encodeURIComponent(type)
                + '&value=' + encodeURIComponent(val)
                + (excludeId ? '&exclude_id=' + excludeId : '');

        fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) {
                    icon.textContent = '✓';
                    icon.className   = 'unique-icon ok';
                    hint.textContent = data.msg;
                    hint.className   = 'unique-hint ok';
                } else {
                    icon.textContent = '✗';
                    icon.className   = 'unique-icon error';
                    hint.textContent = data.msg;
                    hint.className   = 'unique-hint error';
                }
            })
            .catch(function() { clear(); });
    }

    // 失焦时立即检测
    input.addEventListener('blur', function() {
        clearTimeout(timer);
        check();
    });

    // 输入停止 600ms 后检测（防抖）
    input.addEventListener('input', function() {
        clear();
        clearTimeout(timer);
        if (input.value.trim() !== '') {
            timer = setTimeout(check, 600);
        }
    });
}

// 注册页：用户名（无 exclude_id）、昵称（可选，无 exclude_id）
document.addEventListener('DOMContentLoaded', function() {
    setupUniqueCheck('username', 'usernameIcon', 'usernameHint', 'username', 0);
    setupUniqueCheck('nickname', 'nicknameIcon', 'nicknameHint', 'nickname', 0);
});
/* ===== END 唯一性检测 ===== */

var sendCodeCooldown = 0;
var sendCodeTimer = null;

/* 图形验证码 */
function refreshCaptcha() {
    document.getElementById('captchaImg').src = '/captcha.php?t=' + Date.now();
    document.getElementById('captchaInput').value = '';
    document.getElementById('captchaHint').textContent = '';
    document.getElementById('captchaHint').className = 'unique-hint';
}

function checkCaptchaThenSend() {
    if (sendCodeCooldown > 0) return;
    var email = document.getElementById('email').value.trim();
    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        alert('请先输入正确的邮箱地址');
        document.getElementById('email').focus();
        return;
    }
    // 显示图形验证码区域
    document.getElementById('captchaRow').style.display = 'block';
    refreshCaptcha();
    document.getElementById('captchaInput').focus();
}

function verifyCaptchaThenSendCode() {
    var captchaVal = document.getElementById('captchaInput').value.trim();
    if (!captchaVal) {
        document.getElementById('captchaHint').textContent = '请输入图中字符';
        document.getElementById('captchaHint').className = 'unique-hint error';
        return;
    }
    var confirmBtn = document.getElementById('captchaConfirmBtn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = '验证中...';

    fetch('/captcha.php?verify=1&code=' + encodeURIComponent(captchaVal))
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                document.getElementById('captchaHint').textContent = '✓ ' + data.msg;
                document.getElementById('captchaHint').className = 'unique-hint ok';
                // 隐藏图形验证码区域，执行发送
                document.getElementById('captchaRow').style.display = 'none';
                sendCode();
            } else {
                document.getElementById('captchaHint').textContent = data.msg;
                document.getElementById('captchaHint').className = 'unique-hint error';
                refreshCaptcha();
                confirmBtn.disabled = false;
                confirmBtn.textContent = '确认并发送验证码';
            }
        })
        .catch(() => {
            confirmBtn.disabled = false;
            confirmBtn.textContent = '确认并发送验证码';
        });
}

function sendCode() {
    if (sendCodeCooldown > 0) return;
    var email = document.getElementById('email').value.trim();
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

<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
