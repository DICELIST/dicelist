<?php
/**
 * 管理后台 - 网站设置
 * POST 处理必须在 require header.php 之前，否则 HTML 已输出无法再 header()
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$pdo = getDB();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $section = $_POST['section'] ?? 'basic';

    $stmt = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');

    if ($section === 'basic') {
        $fields = ['site_name','site_description','copyright','icp_number','icp_link'];
        foreach ($fields as $f) {
            $stmt->execute([$f, trim($_POST[$f] ?? '')]);
        }
    } elseif ($section === 'operate') {
        $reviewRequired     = isset($_POST['review_required'])     ? '1' : '0';
        $announcementPopup  = isset($_POST['announcement_popup'])  ? '1' : '0';
        $loginMaxAttempts   = max(1, min(99, (int)($_POST['login_max_attempts'] ?? 5)));
        $stmt->execute(['review_required',    $reviewRequired]);
        $stmt->execute(['announcement_popup', $announcementPopup]);
        $stmt->execute(['login_max_attempts', (string)$loginMaxAttempts]);
    }

    logAdminAction('edit_settings', 'settings', null, '保存了 ' . $section . ' 配置');
    setFlash('success', '设置已保存');
    header('Location: /admin/settings.php');
    exit;
}

$adminPageTitle = '网站设置';
require_once __DIR__ . '/header.php';

// 读取当前设置
$settings = [];
$rows = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll();
foreach ($rows as $row) { $settings[$row['setting_key']] = $row['setting_value']; }

$def = [
    'site_name'           => 'TRPG Bot 导航',
    'site_description'    => '',
    'copyright'           => '',
    'icp_number'          => '',
    'icp_link'            => 'https://beian.miit.gov.cn/',
    'review_required'     => '1',
    'announcement_popup'  => '0',
    'login_max_attempts'  => '5',
];
foreach ($def as $k => $v) { if (!isset($settings[$k])) $settings[$k] = $v; }
?>

<h1 style="font-size:1.4rem;font-weight:800;margin-bottom:20px;">网站设置</h1>

<!-- 基础信息 -->
<div class="card" style="max-width:640px;margin-bottom:24px;">
  <div class="card-header"><h3>基础信息</h3></div>
  <div class="card-body">
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="section" value="basic">

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
        保存基础设置
      </button>
    </form>
  </div>
</div>

<!-- 运营设置 -->
<div class="card" style="max-width:640px;">
  <div class="card-header"><h3>运营设置</h3></div>
  <div class="card-body">
    <form method="POST" action="/admin/settings.php">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="section" value="operate">

      <div class="form-group">
        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
          <span>Bot 提交需要审核</span>
          <label class="toggle-switch">
            <input type="checkbox" name="review_required" <?= $settings['review_required'] === '1' ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
          </label>
        </label>
        <div class="form-hint">开启后，用户提交的Bot将进入待审核状态，由管理员审核通过后才显示在前台。</div>
      </div>

      <div class="form-group" style="margin-top:16px;">
        <label class="form-label" style="display:flex;justify-content:space-between;align-items:center;">
          <span>公告弹窗提醒</span>
          <label class="toggle-switch">
            <input type="checkbox" name="announcement_popup" <?= $settings['announcement_popup'] === '1' ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
          </label>
        </label>
        <div class="form-hint">开启后，网站首页访问时将以弹窗形式展示公告（而非页面内嵌条目）。</div>
      </div>

      <div class="form-group" style="margin-top:16px;">
        <label class="form-label">登录失败锁定次数</label>
        <div style="display:flex;align-items:center;gap:10px;">
          <input type="number" name="login_max_attempts" class="form-control" style="max-width:100px;"
                 value="<?= e($settings['login_max_attempts']) ?>" min="1" max="99">
          <span style="font-size:0.85rem;color:var(--text-sub);">次/天（超过限制则 IP 当天无法继续尝试登录）</span>
        </div>
        <div class="form-hint">建议设置为 5~10 次，防止暴力破解。设置为 99 相当于关闭限制。</div>
      </div>

      <button type="submit" class="btn btn-primary btn-lg" style="margin-top:8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
        保存运营设置
      </button>
    </form>
  </div>
</div>

<style>
.toggle-switch { position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0; }
.toggle-switch input { opacity:0;width:0;height:0;position:absolute; }
.toggle-slider {
    position:absolute;top:0;left:0;right:0;bottom:0;
    background:var(--gray-400);border-radius:24px;cursor:pointer;transition:0.2s;
}
.toggle-slider::before {
    content:'';position:absolute;height:18px;width:18px;left:3px;bottom:3px;
    background:#fff;border-radius:50%;transition:0.2s;
}
.toggle-switch input:checked + .toggle-slider { background:var(--blue-primary); }
.toggle-switch input:checked + .toggle-slider::before { transform:translateX(20px); }
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
