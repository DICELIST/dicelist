<?php
/**
 * 管理后台 - 邮件设置（SMTP配置 + 邮件模板）
 */
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
requireAdmin();
$pdo = getDB();

$errors  = [];
$success = '';
$tab     = $_GET['tab'] ?? 'smtp';

// ===== 保存SMTP =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp'])) {
    verifyCsrf();
    $fields = ['mail_host','mail_port','mail_username','mail_password','mail_from_name','mail_encryption'];
    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?')
            ->execute([$key, $val, $val]);
    }
    $success = 'SMTP配置已保存';
}

// ===== 测试发送 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_mail'])) {
    verifyCsrf();
    $testTo = trim($_POST['test_to'] ?? '');
    if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '请输入正确的收件邮箱';
    } else {
        $cfg = getMailConfig();
        $siteName = getSiteSetting('site_name', 'TRPG Bot 导航');
        $ok = smtpSend($testTo, "【{$siteName}】SMTP测试邮件", "<p>这是一封测试邮件，如果你能看到它，说明SMTP配置正确。</p><p>发送时间：" . date('Y-m-d H:i:s') . "</p>");
        if ($ok) $success = '测试邮件已发送至 ' . htmlspecialchars($testTo);
        else $errors[] = '发送失败，请检查SMTP配置及PHP error_log';
    }
}

// ===== 保存模板 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tpl'])) {
    verifyCsrf();
    $tplKey  = trim($_POST['tpl_key']  ?? '');
    $subject = trim($_POST['subject']  ?? '');
    $body    = $_POST['body']          ?? '';
    $allowed = ['register','reset_pwd','rebind','bot_approved','bot_rejected','bot_revoked'];
    if (!in_array($tplKey, $allowed, true)) {
        $errors[] = '无效的模板类型';
    } elseif ($subject === '') {
        $errors[] = '主题不能为空';
    } else {
        $pdo->prepare('UPDATE mail_templates SET subject=?, body=? WHERE tpl_key=?')
            ->execute([$subject, $body, $tplKey]);
        $success = '模板已保存';
        $tab = 'templates';
    }
}

// 读取现有配置
$smtpCfg = getMailConfig();

// 读取模板列表
$tplRows  = $pdo->query('SELECT * FROM mail_templates ORDER BY id')->fetchAll();
$tplMap   = [];
foreach ($tplRows as $t) { $tplMap[$t['tpl_key']] = $t; }

// 编辑某个模板
$editTpl = null;
if (isset($_GET['edit_tpl'])) {
    $editKey = $_GET['edit_tpl'];
    if (isset($tplMap[$editKey])) {
        $editTpl = $tplMap[$editKey];
        $tab = 'templates';
    }
}

$adminPageTitle = '邮件设置';
require_once __DIR__ . '/header.php';
$csrfToken = e(getCsrfToken());
?>

<div class="admin-content">
  <h2 style="font-size:1.2rem;font-weight:700;margin-bottom:20px;">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:8px;color:var(--blue-primary);"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
    邮件设置
  </h2>

  <?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>
  <?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <!-- Tab 切换 -->
  <div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid var(--border);">
    <a href="?tab=smtp" style="padding:10px 20px;font-size:0.9rem;font-weight:600;text-decoration:none;border-bottom:2px solid <?= $tab==='smtp' ? 'var(--blue-primary)' : 'transparent' ?>;color:<?= $tab==='smtp' ? 'var(--blue-primary)' : 'var(--text-sub)' ?>;margin-bottom:-2px;">
      SMTP 配置
    </a>
    <a href="?tab=templates" style="padding:10px 20px;font-size:0.9rem;font-weight:600;text-decoration:none;border-bottom:2px solid <?= $tab==='templates' ? 'var(--blue-primary)' : 'transparent' ?>;color:<?= $tab==='templates' ? 'var(--blue-primary)' : 'var(--text-sub)' ?>;margin-bottom:-2px;">
      邮件模板
    </a>
  </div>

  <?php if ($tab === 'smtp'): ?>
  <!-- ===== SMTP配置 ===== -->
  <div class="card" style="max-width:600px;">
    <div class="card-header"><h3>SMTP 服务器配置</h3></div>
    <div class="card-body">
      <form method="POST" action="?tab=smtp">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="save_smtp" value="1">

        <div class="form-group">
          <label class="form-label">SMTP 服务器地址</label>
          <input type="text" name="mail_host" class="form-control"
                 placeholder="如 smtp.qq.com" value="<?= e($smtpCfg['mail_host'] ?? '') ?>">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div class="form-group">
            <label class="form-label">端口</label>
            <input type="number" name="mail_port" class="form-control"
                   placeholder="465 / 587" value="<?= e($smtpCfg['mail_port'] ?? '465') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">加密方式</label>
            <select name="mail_encryption" class="form-control">
              <option value="ssl"  <?= ($smtpCfg['mail_encryption']??'ssl')==='ssl'  ? 'selected' : '' ?>>SSL（端口465）</option>
              <option value="tls"  <?= ($smtpCfg['mail_encryption']??'')==='tls'  ? 'selected' : '' ?>>TLS/STARTTLS（端口587）</option>
              <option value="none" <?= ($smtpCfg['mail_encryption']??'')==='none' ? 'selected' : '' ?>>无加密（不推荐）</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">SMTP 用户名（发件邮箱）</label>
          <input type="email" name="mail_username" class="form-control"
                 placeholder="noreply@example.com" value="<?= e($smtpCfg['mail_username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">SMTP 密码 / 授权码</label>
          <input type="password" name="mail_password" class="form-control"
                 placeholder="留空则不修改现有密码"
                 autocomplete="new-password">
          <div class="form-hint">QQ邮箱请填写授权码，不是登录密码</div>
        </div>
        <div class="form-group">
          <label class="form-label">发件人显示名称</label>
          <input type="text" name="mail_from_name" class="form-control"
                 placeholder="TRPG Bot 导航" value="<?= e($smtpCfg['mail_from_name'] ?? '') ?>">
        </div>
        <button type="submit" class="btn btn-primary">保存配置</button>
      </form>

      <hr style="border-color:var(--border);margin:24px 0;">

      <h4 style="font-size:0.9rem;font-weight:600;margin-bottom:14px;color:var(--text-main);">发送测试邮件</h4>
      <form method="POST" action="?tab=smtp" style="display:flex;gap:10px;align-items:flex-end;">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="test_mail" value="1">
        <div class="form-group" style="flex:1;margin-bottom:0;">
          <input type="email" name="test_to" class="form-control" placeholder="收件邮箱">
        </div>
        <button type="submit" class="btn btn-outline">发送测试</button>
      </form>
    </div>
  </div>

  <?php elseif ($editTpl): ?>
  <!-- ===== 编辑模板 ===== -->
  <div class="card">
    <div class="card-header">
      <h3>编辑模板：<?= [
        'register'     => '注册验证码',
        'reset_pwd'    => '重置密码验证码',
        'rebind'       => '换绑邮箱验证码',
        'bot_approved' => '审核通过通知',
        'bot_rejected' => '审核拒绝通知',
        'bot_revoked'  => '审核撤回通知',
      ][$editTpl['tpl_key']] ?? $editTpl['tpl_key'] ?></h3>
      <a href="?tab=templates" class="btn btn-ghost btn-sm">← 返回列表</a>
    </div>
    <div class="card-body">
      <div class="alert alert-info" style="margin-bottom:16px;font-size:0.85rem;">
        支持占位符：<code>{code}</code>（验证码）、<code>{site_name}</code>（站名）、<code>{username}</code>（用户名/邮箱）<br>
        审核通知额外支持：<code>{bot_name}</code>（Bot昵称）、<code>{bot_url}</code>（详情页链接）、<code>{reject_reason}</code>（拒绝原因）、<code>{revoke_reason}</code>（撤回原因）、<code>{edit_url}</code>（编辑页链接）
      </div>
      <form method="POST" action="?tab=templates">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="save_tpl" value="1">
        <input type="hidden" name="tpl_key" value="<?= e($editTpl['tpl_key']) ?>">
        <div class="form-group">
          <label class="form-label">邮件主题</label>
          <input type="text" name="subject" class="form-control" value="<?= e($editTpl['subject']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">邮件正文（支持HTML）</label>
          <textarea name="body" class="form-control" style="min-height:400px;font-family:Consolas,monospace;font-size:0.82rem;"><?= e($editTpl['body']) ?></textarea>
        </div>
        <div style="display:flex;gap:12px;">
          <button type="submit" class="btn btn-primary">保存模板</button>
          <a href="?tab=templates" class="btn btn-ghost">取消</a>
        </div>
      </form>
    </div>
  </div>

  <?php else: ?>
  <!-- ===== 模板列表 ===== -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>模板类型</th><th>邮件主题</th><th>最后修改</th><th>操作</th></tr>
      </thead>
      <tbody>
        <?php
        $tplNames = [
          'register'     => '📧 注册验证码',
          'reset_pwd'    => '🔑 重置密码验证码',
          'rebind'       => '🔄 换绑邮箱验证码',
          'bot_approved' => '✅ 审核通过通知',
          'bot_rejected' => '❌ 审核拒绝通知',
          'bot_revoked'  => '↩️ 审核撤回通知',
        ];
        foreach ($tplNames as $key => $name):
          $t = $tplMap[$key] ?? null;
        ?>
        <tr>
          <td><strong><?= $name ?></strong><div class="text-xs text-gray mt-1"><code><?= $key ?></code></div></td>
          <td><?= $t ? e($t['subject']) : '<span class="text-gray">未找到</span>' ?></td>
          <td><?= $t ? formatTime($t['updated_at']) : '-' ?></td>
          <td>
            <a href="?tab=templates&edit_tpl=<?= $key ?>" class="btn btn-outline btn-sm">编辑</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
