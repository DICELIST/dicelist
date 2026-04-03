<?php
/**
 * Bot 编辑页 - 支持被拒绝后"先修改再重新提交"流程
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
$saveSuccess = false; // 本次保存成功标志（用于显示重新提交按钮）

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = $_POST['post_action'] ?? 'save';

    // —— 重新提交审核 ——
    if ($postAction === 'resubmit') {
        // 只有被拒绝的 Bot 才能重新提交
        if ($bot['review_status'] == 2 && ($bot['user_id'] == $currentUser['id'] || $currentUser['is_admin'])) {
            $pdo->prepare('UPDATE bots SET review_status=0, review_remark="", reviewed_at=NULL, reviewed_by=NULL WHERE id=?')
                ->execute([$id]);
            setFlash('success', '已重新提交审核，等待管理员审核');
            header('Location: /profile.php');
            exit;
        }
        setFlash('error', '操作不合法');
        header('Location: /edit.php?id=' . $id);
        exit;
    }

    // —— 保存修改 ——
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

        // 保存后：被拒绝的 Bot 留在编辑页，显示重新提交按钮；其他状态跳转详情页
        if ($bot['review_status'] == 2) {
            $saveSuccess = true;
            // 刷新 bot 数据
            $stmt2 = $pdo->prepare('SELECT * FROM bots WHERE id = ?');
            $stmt2->execute([$id]);
            $bot  = $stmt2->fetch();
            $data = $bot;
        } else {
            setFlash('success', 'Bot信息已更新');
            header('Location: /detail.php?id=' . $id);
            exit;
        }
    }
}

$optPlatform  = getOptions('platform');
$optFramework = getOptions('framework');
$optMode      = getOptions('mode');
$optBlacklist = getOptions('blacklist');
$optStatus    = getOptions('status');

$isRejected = ($bot['review_status'] == 2);

$pageTitle = '编辑Bot - ' . e($bot['nickname']);
require_once __DIR__ . '/includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>

<div class="container">
  <div class="page-header">
    <div class="breadcrumb">
      <a href="/index.php">首页</a><span>/</span>
      <?php if ($isRejected): ?>
      <a href="/trash.php">回收站</a>
      <?php else: ?>
      <a href="/detail.php?id=<?= $id ?>">Bot详情</a>
      <?php endif; ?>
      <span>/</span>
      <span>编辑</span>
    </div>
    <h1>编辑 Bot</h1>
    <p>修改 <strong><?= e($bot['nickname']) ?></strong> 的信息</p>
  </div>

  <?php if ($isRejected && !$saveSuccess): ?>
  <!-- 被拒绝警告 banner -->
  <div class="alert alert-error" style="display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div>
      <div style="font-weight:700;margin-bottom:4px;">此内容审核未通过</div>
      <?php if ($bot['review_remark']): ?>
      <div style="font-size:0.9rem;">拒绝原因：<?= e($bot['review_remark']) ?></div>
      <?php endif; ?>
      <div style="font-size:0.85rem;margin-top:6px;color:inherit;opacity:0.85;">请根据上述原因修改内容，保存后会出现"重新提交审核"按钮。</div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($saveSuccess): ?>
  <!-- 保存成功 + 重新提交提示 -->
  <div class="alert alert-success" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:10px;">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <span>修改已保存。现在可以重新提交审核，等待管理员审核通过后将重新公开显示。</span>
    </div>
    <form method="POST" action="/edit.php?id=<?= $id ?>">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="post_action" value="resubmit">
      <button type="submit" class="btn btn-primary"
              style="background:#34c759;border-color:#34c759;white-space:nowrap;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
        重新提交审核
      </button>
    </form>
  </div>
  <?php endif; ?>

  <?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="POST" action="/edit.php?id=<?= $id ?>">
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <input type="hidden" name="post_action" value="save">

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
      <?php if ($isRejected): ?>
      <a href="/trash.php" class="btn btn-ghost btn-lg">返回回收站</a>
      <?php else: ?>
      <a href="/detail.php?id=<?= $id ?>" class="btn btn-ghost btn-lg">取消</a>
      <?php endif; ?>
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
