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

    // 发布协议勾选
    if (empty($_POST['agree_publish'])) {
        $errors[] = '请阅读并同意发布协议后提交';
    }

    if (empty($errors)) {
        $reviewStatus = isReviewRequired() ? 0 : 1; // 0=待审核 1=直接通过
        $sql = 'INSERT INTO bots (user_id, review_status, platform, nickname, id_url, framework, owner, mode, blacklist, status, invite_condition, remarks, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $pdo->prepare($sql)->execute([
            $currentUser['id'],
            $reviewStatus,
            $data['platform'], $data['nickname'], $data['id_url'],
            $data['framework'], $data['owner'], $data['mode'],
            $data['blacklist'], $data['status'], $data['invite_condition'],
            $data['remarks'], $data['description'],
        ]);
        $newId = $pdo->lastInsertId();
        // 删除关联草稿
        if (!empty($_POST['draft_id'])) {
            $pdo->prepare('DELETE FROM bot_drafts WHERE id=? AND user_id=?')
                ->execute([(int)$_POST['draft_id'], $currentUser['id']]);
        }
        if ($reviewStatus === 0) {
            setFlash('success', 'Bot已提交，等待管理员审核后将对外显示！');
            header('Location: /profile.php');
        } else {
            setFlash('success', 'Bot提交成功！');
            header('Location: /detail.php?id=' . $newId);
        }
        exit;
    }
}

// 下拉选项
$optPlatform  = getOptions('platform');
$optFramework = getOptions('framework');
$optMode      = getOptions('mode');
$optBlacklist = getOptions('blacklist');
$optStatus    = getOptions('status');

// 读取草稿
$draftId = (int)($_GET['draft'] ?? 0);
$draft = null;
if ($draftId > 0) {
    $ds = $pdo->prepare('SELECT * FROM bot_drafts WHERE id=? AND user_id=?');
    $ds->execute([$draftId, $currentUser['id']]);
    $draft = $ds->fetch();
    if ($draft) {
        foreach ($data as $k => $_) {
            if (isset($draft[$k])) $data[$k] = $draft[$k];
        }
    }
}

// 读取发布协议
$publishAgreement = getAgreement('publish_agreement');

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

    <div class="d-flex gap-2 mt-3 mb-4 justify-center" style="flex-direction:column;align-items:center;gap:14px;">
      <input type="hidden" name="draft_id" value="<?= $draftId ?>">

      <!-- 发布协议 -->
      <?php if ($publishAgreement['content']): ?>
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.88rem;color:var(--text-main);">
        <input type="checkbox" name="agree_publish" id="agreePublishCheck" value="1" required
               style="width:16px;height:16px;cursor:pointer;accent-color:var(--blue-primary);"
               <?= !empty($_POST['agree_publish']) ? 'checked' : '' ?>>
        我已阅读并同意
        <a href="javascript:void(0)" onclick="showPublishAgreementModal()"
           style="color:var(--blue-primary);text-decoration:underline;text-underline-offset:3px;">
          《<?= e($publishAgreement['title']) ?>》
        </a>
      </label>

      <!-- 发布协议弹窗 -->
      <div id="publishAgreementModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:16px;max-width:600px;width:100%;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
          <div style="padding:20px 24px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;">
            <h3 style="font-size:1.05rem;font-weight:700;margin:0;"><?= e($publishAgreement['title']) ?></h3>
            <button type="button" onclick="document.getElementById('publishAgreementModal').style.display='none'"
                    style="background:none;border:none;cursor:pointer;font-size:1.4rem;line-height:1;color:var(--text-sub);padding:4px;">×</button>
          </div>
          <div class="agreement-md-body" id="publishAgreementBody" style="overflow-y:auto;padding:20px 24px;font-size:0.88rem;line-height:1.7;color:var(--text-main);"><!-- 由 JS 渲染 --></div>
          <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:12px;flex-shrink:0;">
            <button type="button" onclick="document.getElementById('publishAgreementModal').style.display='none'"
                    class="btn btn-ghost" style="flex:1;">关闭</button>
            <button type="button" onclick="publishAgreeAndClose()" class="btn btn-primary" style="flex:1;">同意并继续</button>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div class="d-flex gap-2">
        <a href="/profile.php" class="btn btn-ghost btn-lg">取消</a>
        <button type="button" class="btn btn-outline btn-lg" id="saveDraftBtn" onclick="saveDraft()">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
          保存草稿
        </button>
        <button type="submit" class="btn btn-primary btn-lg">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9l20-7z"/></svg>
          提交Bot
        </button>
      </div>
    </div>
  </form>
</div>

<style>
@media (max-width: 768px) { .form-grid { grid-template-columns: 1fr !important; } }
/* 协议弹窗 Markdown 渲染样式 */
.agreement-md-body h1,.agreement-md-body h2,.agreement-md-body h3,
.agreement-md-body h4,.agreement-md-body h5,.agreement-md-body h6 {
    font-weight:700;margin:0.9em 0 0.35em;line-height:1.3;
}
.agreement-md-body h1{font-size:1.15em;}
.agreement-md-body h2{font-size:1.05em;}
.agreement-md-body h3{font-size:1em;}
.agreement-md-body p{margin:0 0 0.65em;}
.agreement-md-body ul,.agreement-md-body ol{padding-left:1.5em;margin:0 0 0.65em;}
.agreement-md-body li{margin-bottom:0.2em;}
.agreement-md-body strong{font-weight:700;}
.agreement-md-body em{font-style:italic;}
.agreement-md-body hr{border:none;border-top:1px solid var(--border);margin:1em 0;}
.agreement-md-body code{background:rgba(0,0,0,0.06);padding:1px 4px;border-radius:3px;font-family:monospace;}
.agreement-md-body pre{background:rgba(0,0,0,0.06);padding:10px;border-radius:6px;overflow-x:auto;margin:0 0 0.65em;}
.agreement-md-body blockquote{border-left:3px solid var(--border);padding-left:12px;color:var(--text-sub);margin:0 0 0.65em;}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initMarkdownPreview('mdEditor', 'mdPreview');
    // 每60秒自动保存草稿
    setInterval(saveDraftAuto, 60000);

    // 点击协议弹窗蒙层关闭
    var modal = document.getElementById('publishAgreementModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) modal.style.display = 'none';
        });
    }
});

function showPublishAgreementModal() {
    var m = document.getElementById('publishAgreementModal');
    if (!m) return;
    m.style.display = 'flex';
    var body = document.getElementById('publishAgreementBody');
    if (body && !body.dataset.rendered) {
        var md = <?= json_encode($publishAgreement['content']) ?>;
        if (typeof marked !== 'undefined') {
            body.innerHTML = marked.parse(md);
        } else {
            body.textContent = md;
        }
        body.dataset.rendered = '1';
    }
}

function publishAgreeAndClose() {
    var cb = document.getElementById('agreePublishCheck');
    if (cb) cb.checked = true;
    var m = document.getElementById('publishAgreementModal');
    if (m) m.style.display = 'none';
}

var draftId = <?= $draftId ?: 'null' ?>;

function getFormData() {
    var form = document.querySelector('form[method="POST"]');
    var fd = new FormData(form);
    return fd;
}

function saveDraft() {
    var btn = document.getElementById('saveDraftBtn');
    btn.disabled = true;
    btn.textContent = '保存中...';
    var fd = getFormData();
    fd.append('_draft_action', 'save');
    if (draftId) fd.set('draft_id', draftId);
    fetch('/draft_save.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(data=>{
            if (data.ok) {
                draftId = data.draft_id;
                document.querySelector('input[name="draft_id"]').value = draftId;
                btn.textContent = '已保存 ✓';
                setTimeout(()=>{ btn.disabled=false; btn.innerHTML='<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg> 保存草稿'; }, 2000);
            } else {
                btn.disabled=false; btn.textContent='保存失败，重试';
            }
        }).catch(()=>{ btn.disabled=false; btn.textContent='保存草稿'; });
}

function saveDraftAuto() {
    var fd = getFormData();
    fd.append('_draft_action', 'save');
    if (draftId) fd.set('draft_id', draftId);
    fetch('/draft_save.php', {method:'POST', body:fd})
        .then(r=>r.json()).then(data=>{ if(data.ok && data.draft_id) draftId=data.draft_id; });
}

// 离开页面提示
window.addEventListener('beforeunload', function(e) {
    var form = document.querySelector('form[method="POST"]');
    if (form && form.querySelector('textarea[name="description"]').value.trim()) {
        e.preventDefault();
        e.returnValue = '';
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
