<?php
/**
 * Bot 编辑页
 */
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();
$currentUser = getCurrentUser();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /index.php'); exit; }

// 获取Bot
$stmt = $pdo->prepare('SELECT * FROM bots WHERE id = ?');
$stmt->execute([$id]);
$bot = $stmt->fetch();

if (!$bot) { setFlash('error', 'Bot不存在'); header('Location: /profile.php'); exit; }

// 权限校验：仅作者或管理员
if ($bot['user_id'] != $currentUser['id'] && !$currentUser['is_admin']) {
    setFlash('error', '无权限编辑此Bot');
    header('Location: /index.php');
    exit;
}

$errors = [];
$data = $bot; // 初始填入原始数据

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $fields = ['platform','nickname','id_url','framework','owner','mode','blacklist','status','invite_condition','remarks','description'];
    foreach ($fields as $f) {
        $data[$f] = trim($_POST[$f] ?? '');
    }
    if ($data['nickname'] === '') $errors[] = '昵称不能为空';
    if ($data['platform'] === '') $errors[] = '请选择对接平台';

    if (empty($errors)) {
        $sql = 'UPDATE bots SET platform=?, nickname=?, id_url=?, framework=?, owner=?, mode=?, blacklist=?, status=?, invite_condition=?, remarks=?, description=? WHERE id=?';
        $pdo->prepare($sql)->execute([
            $data['platform'], $data['nickname'], $data['id_url'],
            $data['framework'], $data['owner'], $data['mode'],
            $data['blacklist'], $data['status'], $data['invite_condition'],
            $data['remarks'], $data['description'], $id
        ]);
        setFlash('success', 'Bot信息已更新');
        header('Location: /detail.php?id=' . $id);
        exit;
    }
}

$optPlatform  = getOptions('platform');
$optFramework = getOptions('framework');
$optMode      = getOptions('mode');
$optBlacklist = getOptions('blacklist');
$optStatus    = getOptions('status');

$pageTitle = '编辑Bot - ' . e($bot['nickname']);
require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>

<div class="container">
  <div class="page-header">
    <div class="breadcrumb">
      <a href="/index.php">首页</a><span>/</span>
      <a href="/detail.php?id=<?= $id ?>">Bot详情</a><span>/</span>
      <span>编辑</span>
    </div>
    <h1>编辑 Bot</h1>
    <p>修改 <strong><?= e($bot['nickname']) ?></strong> 的信息</p>
  </div>

  <?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="/edit.php?id=<?= $id ?>">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="form-grid">
      <div class="card">
        <div class="card-header"><h3>基本信息</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Bot昵称 <span class="required">*</span></label>
            <input type="text" name="nickname" class="form-control" value="<?= e($data['nickname']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">对接平台 <span class="required">*</span></label>
            <select name="platform" class="form-control" required>
              <option value="">请选择平台</option>
              <?php foreach ($optPlatform as $opt): ?>
              <option value="<?= e($opt['value']) ?>" <?= $data['platform'] === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">ID / URL</label>
            <input type="text" name="id_url" class="form-control" value="<?= e($data['id_url']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">运行框架</label>
            <select name="framework" class="form-control">
              <option value="">请选择框架</option>
              <?php foreach ($optFramework as $opt): ?>
              <option value="<?= e($opt['value']) ?>" <?= $data['framework'] === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">骰主</label>
            <input type="text" name="owner" class="form-control" value="<?= e($data['owner']) ?>">
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>运行状态</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">模式</label>
            <select name="mode" class="form-control">
              <option value="">请选择模式</option>
              <?php foreach ($optMode as $opt): ?>
              <option value="<?= e($opt['value']) ?>" <?= $data['mode'] === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">黑名单</label>
            <select name="blacklist" class="form-control">
              <option value="">请选择</option>
              <?php foreach ($optBlacklist as $opt): ?>
              <option value="<?= e($opt['value']) ?>" <?= $data['blacklist'] === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">状态</label>
            <select name="status" class="form-control">
              <option value="">请选择状态</option>
              <?php foreach ($optStatus as $opt): ?>
              <option value="<?= e($opt['value']) ?>" <?= $data['status'] === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">邀请条件</label>
            <input type="text" name="invite_condition" class="form-control" value="<?= e($data['invite_condition']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">备注</label>
            <input type="text" name="remarks" class="form-control" value="<?= e($data['remarks']) ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header"><h3>详细介绍 <span style="font-size:0.78rem;color:var(--text-sub);font-weight:400;">（支持Markdown格式）</span></h3></div>
      <div class="card-body">
        <div class="md-editor-wrap">
          <div class="md-editor-panel">
            <label>✏️ 编辑</label>
            <textarea id="mdEditor" name="description" class="form-control"
                      style="min-height:200px;font-family:monospace;font-size:0.88rem;"><?= e($data['description']) ?></textarea>
          </div>
          <div class="md-editor-panel">
            <label>👁️ 预览</label>
            <div class="md-preview markdown-body" id="mdPreview"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-3 mb-4 justify-center">
      <a href="/detail.php?id=<?= $id ?>" class="btn btn-ghost btn-lg">取消</a>
      <button type="submit" class="btn btn-primary btn-lg">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        保存修改
      </button>
    </div>
  </form>
</div>

<style>
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr !important; } }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    initMarkdownPreview('mdEditor', 'mdPreview');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
