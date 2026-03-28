<?php
/**
 * 管理后台 - 协议管理（注册协议 + 发布协议）
 * POST 处理必须在 require header.php 之前，否则 HTML 已输出无法再 header()
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$pdo = getDB();
$currentUser = getCurrentUser();

// 操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $agKey   = $_POST['ag_key']   ?? '';
    $title   = trim($_POST['title']   ?? '');
    $content = trim($_POST['content'] ?? '');

    $allowed = ['register_agreement', 'publish_agreement'];
    if (!in_array($agKey, $allowed, true)) {
        setFlash('error', '无效的协议类型');
        header('Location: /admin/agreements.php'); exit;
    }

    // 检查是否已存在
    $stmt = $pdo->prepare('SELECT id FROM agreements WHERE ag_key = ?');
    $stmt->execute([$agKey]);
    if ($stmt->fetch()) {
        $pdo->prepare('UPDATE agreements SET title=?, content=?, updated_at=NOW() WHERE ag_key=?')
            ->execute([$title, $content, $agKey]);
    } else {
        $pdo->prepare('INSERT INTO agreements (ag_key, title, content) VALUES (?, ?, ?)')
            ->execute([$agKey, $title, $content]);
    }

    logAdminAction('edit_agreement', 'agreement', null, '协议类型: ' . $agKey);
    setFlash('success', '协议内容已保存');
    header('Location: /admin/agreements.php'); exit;
}

$adminPageTitle = '协议管理';
require_once __DIR__ . '/header.php';

// 读取协议内容
$agreements = [];
$rows = $pdo->query('SELECT ag_key, title, content FROM agreements')->fetchAll();
foreach ($rows as $row) { $agreements[$row['ag_key']] = $row; }

$defs = [
    'register_agreement' => ['label' => '注册协议', 'desc' => '用户注册时需要同意的服务条款'],
    'publish_agreement'  => ['label' => '发布协议', 'desc' => '用户提交Bot时需要同意的发布规则'],
];
?>

<h1 style="font-size:1.4rem;font-weight:800;margin-bottom:8px;">协议管理</h1>
<p style="color:var(--text-sub);font-size:0.88rem;margin-bottom:24px;">管理注册协议和发布协议的内容，这些内容将在用户注册/提交Bot时展示并要求勾选同意。</p>

<?php foreach ($defs as $agKey => $def): ?>
<?php $cur = $agreements[$agKey] ?? null; ?>
<div class="card" style="max-width:800px;margin-bottom:28px;">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <div>
      <h3 style="margin:0;"><?= e($def['label']) ?></h3>
      <span style="font-size:0.8rem;color:var(--text-sub);font-weight:400;"><?= e($def['desc']) ?></span>
    </div>
    <span class="badge <?= $cur ? 'badge-green' : 'badge-orange' ?>">
      <?= $cur ? '已配置' : '未配置' ?>
    </span>
  </div>
  <div class="card-body">
    <form method="POST" action="/admin/agreements.php">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="ag_key" value="<?= e($agKey) ?>">

      <div class="form-group">
        <label class="form-label">协议标题</label>
        <input type="text" name="title" class="form-control"
               value="<?= e($cur['title'] ?? '') ?>"
               placeholder="如：用户服务协议" maxlength="100" required>
      </div>

      <div class="form-group">
        <label class="form-label">
          协议内容
          <span style="font-size:0.78rem;color:var(--text-sub);font-weight:400;margin-left:6px;">支持 Markdown 格式</span>
        </label>
        <textarea name="content" class="form-control" rows="16"
                  placeholder="在此输入协议正文，支持 Markdown 格式..."
                  style="font-family:monospace;font-size:0.88rem;"><?= e($cur['content'] ?? '') ?></textarea>
        <div class="form-hint">
          支持 Markdown：**粗体**、*斜体*、## 标题、- 列表、--- 分割线等。
          前台将渲染为 HTML 展示给用户。
        </div>
      </div>

      <div style="display:flex;gap:10px;align-items:center;">
        <button type="submit" class="btn btn-primary">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
          保存<?= e($def['label']) ?>
        </button>
        <?php if ($cur): ?>
        <a href="#preview-<?= $agKey ?>" class="btn btn-ghost btn-sm" onclick="togglePreview('<?= $agKey ?>')">预览效果</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($cur && $cur['content']): ?>
    <div id="preview-<?= $agKey ?>" style="display:none;margin-top:20px;border-top:1px solid var(--border);padding-top:20px;">
      <div style="font-size:0.82rem;font-weight:600;color:var(--text-sub);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.05em;">渲染预览</div>
      <div class="agreement-preview-content" id="preview-content-<?= $agKey ?>" data-md="<?= htmlspecialchars($cur['content'], ENT_QUOTES, 'UTF-8') ?>">
        <!-- 由 JS 渲染 -->
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<style>
/* 协议预览区 Markdown 样式 */
.agreement-preview-content h1,.agreement-preview-content h2,.agreement-preview-content h3,
.agreement-preview-content h4,.agreement-preview-content h5,.agreement-preview-content h6 {
    font-weight:700;margin:0.9em 0 0.35em;
}
.agreement-preview-content h1{font-size:1.3em;}
.agreement-preview-content h2{font-size:1.15em;}
.agreement-preview-content h3{font-size:1.05em;}
.agreement-preview-content p{margin:0 0 0.65em;}
.agreement-preview-content ul,.agreement-preview-content ol{padding-left:1.5em;margin:0 0 0.65em;}
.agreement-preview-content li{margin-bottom:0.2em;}
.agreement-preview-content strong{font-weight:700;}
.agreement-preview-content em{font-style:italic;}
.agreement-preview-content hr{border:none;border-top:1px solid var(--border);margin:1em 0;}
.agreement-preview-content code{background:rgba(0,0,0,0.06);padding:1px 4px;border-radius:3px;font-family:monospace;}
.agreement-preview-content pre{background:rgba(0,0,0,0.06);padding:10px;border-radius:6px;overflow-x:auto;margin:0 0 0.65em;}
.agreement-preview-content blockquote{border-left:3px solid var(--border);padding-left:12px;color:var(--text-sub);margin:0 0 0.65em;}
</style>

<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>
<script>
function togglePreview(key) {
    var el = document.getElementById('preview-' + key);
    if (!el) return false;
    var showing = el.style.display !== 'none';
    el.style.display = showing ? 'none' : 'block';
    if (!showing) {
        // 渲染 Markdown
        var content = document.getElementById('preview-content-' + key);
        if (content && !content.dataset.rendered && typeof marked !== 'undefined') {
            content.innerHTML = marked.parse(content.dataset.md || '');
            content.dataset.rendered = '1';
        }
    }
    return false;
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
