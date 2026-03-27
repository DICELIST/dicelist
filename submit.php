<?php
/**
 * Bot 提交页
 */
require_once __DIR__ . '/includes/functions.php';
requireLogin();
$pdo = getDB();
$currentUser = getCurrentUser();

$errors = [];
$data = [
    'platform'         => '',
    'nickname'         => '',
    'id_url'           => '',
    'framework'        => '',
    'owner'            => '',
    'mode'             => '',
    'blacklist'        => '',
    'status'           => '',
    'invite_condition' => '',
    'remarks'          => '',
    'description'      => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach ($data as $k => $_) {
        $data[$k] = trim($_POST[$k] ?? '');
    }

    // 验证必填
    if ($data['nickname'] === '') $errors[] = '昵称不能为空';
    if ($data['platform'] === '') $errors[] = '请选择对接平台';

    if (empty($errors)) {
        $sql = 'INSERT INTO bots (user_id, platform, nickname, id_url, framework, owner, mode, blacklist, status, invite_condition, remarks, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $pdo->prepare($sql)->execute([
            $currentUser['id'],
            $data['platform'], $data['nickname'], $data['id_url'],
            $data['framework'], $data['owner'], $data['mode'],
            $data['blacklist'], $data['status'], $data['invite_condition'],
            $data['remarks'], $data['description'],
        ]);
        $newId = $pdo->lastInsertId();
        setFlash('success', 'Bot提交成功！');
        header('Location: /detail.php?id=' . $newId);
        exit;
    }
}

// 下拉选项
$optPlatform  = getOptions('platform');
$optFramework = getOptions('framework');
$optMode      = getOptions('mode');
$optBlacklist = getOptions('blacklist');
$optStatus    = getOptions('status');

$pageTitle = '提交Bot';
require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>

<div class="container">
  <div class="page-header">
    <div class="breadcrumb">
      <a href="/index.php">首页</a><span>/</span>
      <a href="/profile.php">个人中心</a><span>/</span>
      <span>提交Bot</span>
    </div>
    <h1>提交我的Bot</h1>
    <p>填写Bot的基本信息，分享给所有TRPG玩家</p>
  </div>

  <?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="/submit.php">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;" class="form-grid">

      <div class="card">
        <div class="card-header"><h3>基本信息</h3></div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label" for="nickname">Bot昵称 <span class="required">*</span></label>
            <input type="text" id="nickname" name="nickname" class="form-control"
                   placeholder="你的Bot名称" value="<?= e($data['nickname']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="platform">对接平台 <span class="required">*</span></label>
            <select id="platform" name="platform" class="form-control" required>
              <option value="">请选择平台</option>
              <?php foreach ($optPlatform as $opt): ?>
              <option value="<?= e($opt['value']) ?>" <?= $data['platform'] === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="id_url">ID / URL</label>
            <input type="text" id="id_url" name="id_url" class="form-control"
                   placeholder="Bot的ID、邀请链接或URL" value="<?= e($data['id_url']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label" for="framework">运行框架</label>
            <select id="framework" name="framework" class="form-control">
              <option value="">请选择框架</option>
              <?php foreach ($optFramework as $opt): ?>
              <option value="<?= e($opt['value']) ?>" <?= $data['framework'] === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label" for="owner">骰主</label>
            <input type="text" id="owner" name="owner" class="form-control"
                   placeholder="骰主昵称或联系方式" value="<?= e($data['owner']) ?>">
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
            <input type="text" name="invite_condition" class="form-control"
                   placeholder="如：无需邀请/私信骰主" value="<?= e($data['invite_condition']) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">备注</label>
            <input type="text" name="remarks" class="form-control"
                   placeholder="其他补充说明" value="<?= e($data['remarks']) ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- 介绍（Markdown） -->
    <div class="card mt-3">
      <div class="card-header">
        <h3>详细介绍 <span style="font-size:0.78rem;color:var(--text-sub);font-weight:400;">（支持Markdown格式）</span></h3>
      </div>
      <div class="card-body">
        <div class="md-editor-wrap">
          <div class="md-editor-panel">
            <label>✏️ 编辑</label>
            <textarea id="mdEditor" name="description" class="form-control"
                      style="min-height:200px;font-family:monospace;font-size:0.88rem;"
                      placeholder="支持Markdown，可以使用标题、加粗、列表、代码块等格式..."><?= e($data['description']) ?></textarea>
          </div>
          <div class="md-editor-panel">
            <label>👁️ 预览</label>
            <div class="md-preview markdown-body" id="mdPreview"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2 mt-3 mb-4 justify-center">
      <a href="/profile.php" class="btn btn-ghost btn-lg">取消</a>
      <button type="submit" class="btn btn-primary btn-lg">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
        提交Bot
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
