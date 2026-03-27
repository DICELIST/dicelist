<?php
/**
 * 管理后台 - 网站设置
 */
$adminPageTitle = '网站设置';
require_once __DIR__ . '/header.php';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fields = ['site_name','site_description','copyright','icp_number','icp_link'];
    $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    foreach ($fields as $f) {
        $val = trim($_POST[$f] ?? '');
        $stmt->execute([$f, $val]);
    }
    setFlash('success', '设置已保存');
    header('Location: /admin/settings.php');
    exit;
}

// 读取当前设置
$settings = [];
$rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
foreach ($rows as $row) { $settings[$row['setting_key']] = $row['setting_value']; }

$def = ['site_name'=>'TRPG Bot 导航','site_description'=>'','copyright'=>'','icp_number'=>'','icp_link'=>'https://beian.miit.gov.cn/'];
foreach ($def as $k => $v) { if (!isset($settings[$k])) $settings[$k] = $v; }
?>

<h1 style="font-size:1.4rem;font-weight:800;margin-bottom:20px;">网站设置</h1>

<div class="card" style="max-width:640px;">
  <div class="card-header"><h3>基础信息</h3></div>
  <div class="card-body">
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

      <div class="form-group">
        <label class="form-label">网站名称</label>
        <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name']) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">网站描述</label>
        <input type="text" name="site_description" class="form-control" value="<?= e($settings['site_description']) ?>" placeholder="SEO描述">
      </div>

      <div class="form-group">
        <label class="form-label">版权信息</label>
        <textarea name="copyright" class="form-control" style="min-height:80px;" placeholder="如：Copyright © 2026 TRPG Bot 导航."><?= e($settings['copyright']) ?></textarea>
        <div class="form-hint">显示在网站底部，支持换行</div>
      </div>

      <div class="form-group">
        <label class="form-label">ICP 备案号</label>
        <input type="text" name="icp_number" class="form-control" value="<?= e($settings['icp_number']) ?>" placeholder="如：粤ICP备XXXXXXXX号">
        <div class="form-hint">留空则不显示备案信息</div>
      </div>

      <div class="form-group">
        <label class="form-label">备案链接</label>
        <input type="url" name="icp_link" class="form-control" value="<?= e($settings['icp_link']) ?>" placeholder="https://beian.miit.gov.cn/">
      </div>

      <button type="submit" class="btn btn-primary btn-lg">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
        保存设置
      </button>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
