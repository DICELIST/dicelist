<?php
/**
 * Bot 详情页
 */
require_once __DIR__ . '/includes/functions.php';
$pdo = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /index.php');
    exit;
}

// 获取Bot信息
$stmt = $pdo->prepare(
    'SELECT b.*, u.nickname AS author_nickname, u.username AS author_username, u.id AS author_id
     FROM bots b
     LEFT JOIN users u ON b.user_id = u.id
     WHERE b.id = ?'
);
$stmt->execute([$id]);
$bot = $stmt->fetch();

if (!$bot) {
    header('HTTP/1.1 404 Not Found');
    $pageTitle = '页面不存在';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="empty-state"><h3>Bot不存在</h3><p>该Bot可能已被删除。</p><a href="/index.php" class="btn btn-outline">返回首页</a></div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// 增加浏览次数（每次访问+1，简单实现）
$pdo->prepare('UPDATE bots SET view_count = view_count + 1 WHERE id = ?')->execute([$id]);
$bot['view_count'] += 1;

$currentUser = getCurrentUser();
$canEdit = $currentUser && ($currentUser['id'] == $bot['user_id'] || $currentUser['is_admin']);

$pageTitle = e($bot['nickname']) . ' - Bot详情';
require_once __DIR__ . '/includes/header.php';
?>

<!-- 引入 Markdown 渲染 -->
<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>

<div class="container">
  <!-- 面包屑 -->
  <div class="page-header">
    <div class="breadcrumb">
      <a href="/index.php">首页</a>
      <span>/</span>
      <span>Bot详情</span>
    </div>
  </div>

  <!-- 详情主体 -->
  <div class="detail-fields">
    <!-- 标题区 -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
      <div>
        <h1 class="detail-title"><?= e($bot['nickname']) ?></h1>
        <div class="detail-meta">
          <div class="detail-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            发布者：<?= e($bot['author_nickname'] ?: $bot['author_username']) ?>
          </div>
          <div class="detail-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            发布时间：<?= formatTime($bot['created_at']) ?>
          </div>
          <?php if ($bot['updated_at'] !== $bot['created_at']): ?>
          <div class="detail-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
            更新：<?= formatTime($bot['updated_at']) ?>
          </div>
          <?php endif; ?>
          <div class="detail-meta-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            浏览：<?= $bot['view_count'] ?>
          </div>
        </div>
      </div>
      <?php if ($canEdit): ?>
      <div class="d-flex gap-1">
        <a href="/edit.php?id=<?= $bot['id'] ?>" class="btn btn-outline btn-sm">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          编辑
        </a>
        <a href="/delete.php?id=<?= $bot['id'] ?>" class="btn btn-danger btn-sm"
           data-confirm="确定要删除《<?= e($bot['nickname']) ?>》吗？">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
          删除
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- 字段信息网格 -->
    <div class="detail-grid">
      <div class="detail-field">
        <div class="detail-field-label">对接平台</div>
        <div class="detail-field-value">
          <?php if ($bot['platform']): ?>
          <span class="badge badge-blue"><?= e($bot['platform']) ?></span>
          <?php else: ?><span class="text-gray">-</span><?php endif; ?>
        </div>
      </div>
      <div class="detail-field">
        <div class="detail-field-label">ID / URL</div>
        <div class="detail-field-value" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
          <?php
          $idUrl = $bot['id_url'] ?? '';
          if ($idUrl === '') {
              echo '<span class="text-gray">-</span>';
          } elseif (preg_match('/^\d{1,11}$/', $idUrl)) {
              // 纯数字 ≤11位：显示数字 + 复制按钮
              echo '<span id="idUrlText">' . e($idUrl) . '</span>';
              echo '<button type="button" class="btn btn-outline btn-sm" onclick="copyIdUrl()" title="复制" style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;">';
              echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
              echo '复制</button>';
          } elseif (preg_match('/^https?:\/\//i', $idUrl)) {
              // URL：<a> 标签（支持右键新标签），由 JS 拦截 click 弹出确认框
              echo '<a href="' . e($idUrl) . '" target="_blank" rel="noopener noreferrer"'
                 . ' class="btn btn-primary btn-sm leave-link"'
                 . ' data-url="' . e($idUrl) . '"'
                 . ' style="display:inline-flex;align-items:center;gap:5px;">';
              echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
              echo '点击跳转</a>';
          } else {
              // 其他文本直接展示
              echo '<span style="word-break:break-all;">' . e($idUrl) . '</span>';
          }
          ?>
        </div>
      </div>
      <div class="detail-field">
        <div class="detail-field-label">运行框架</div>
        <div class="detail-field-value"><?= $bot['framework'] ? e($bot['framework']) : '-' ?></div>
      </div>
      <div class="detail-field">
        <div class="detail-field-label">骰主</div>
        <div class="detail-field-value"><?= $bot['owner'] ? e($bot['owner']) : '-' ?></div>
      </div>
      <div class="detail-field">
        <div class="detail-field-label">模式</div>
        <div class="detail-field-value"><?= $bot['mode'] ? e($bot['mode']) : '-' ?></div>
      </div>
      <div class="detail-field">
        <div class="detail-field-label">黑名单</div>
        <div class="detail-field-value"><?= $bot['blacklist'] ? e($bot['blacklist']) : '-' ?></div>
      </div>
      <div class="detail-field">
        <div class="detail-field-label">状态</div>
        <div class="detail-field-value">
          <?php if ($bot['status']): ?>
          <span class="badge status-<?= e($bot['status']) ?>"><?= e($bot['status']) ?></span>
          <?php else: ?><span class="text-gray">-</span><?php endif; ?>
        </div>
      </div>
      <div class="detail-field">
        <div class="detail-field-label">邀请条件</div>
        <div class="detail-field-value"><?= $bot['invite_condition'] ? e($bot['invite_condition']) : '-' ?></div>
      </div>
    </div>

    <?php if ($bot['remarks']): ?>
    <div class="detail-field mt-2" style="border-left-color:var(--gold);">
      <div class="detail-field-label">备注</div>
      <div class="detail-field-value"><?= e($bot['remarks']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- 介绍（Markdown） -->
  <?php if ($bot['description']): ?>
  <div class="detail-description">
    <h3>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:6px;color:var(--blue-primary);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      详细介绍
    </h3>
    <div class="markdown-body" id="mdContent"></div>
    <script>
      (function() {
        var raw = <?= json_encode($bot['description']) ?>;
        var el  = document.getElementById('mdContent');
        if (typeof marked !== 'undefined') {
          el.innerHTML = typeof DOMPurify !== 'undefined'
            ? DOMPurify.sanitize(marked.parse(raw))
            : marked.parse(raw);
        } else {
          el.textContent = raw;
        }
      })();
    </script>
  </div>
  <?php endif; ?>

  <div class="mb-4 text-center">
    <a href="/index.php" class="btn btn-ghost">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
      返回列表
    </a>
  </div>
</div>

<!-- 复制 & 离站弹窗 -->
<script>
/* ---- 复制 ID ---- */
function copyIdUrl() {
    var el = document.getElementById('idUrlText');
    if (!el) return;
    var text = el.textContent.trim();
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(showCopyTip);
    } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        try { document.execCommand('copy'); showCopyTip(); } catch(e) {}
        document.body.removeChild(ta);
    }
}
function showCopyTip() {
    var tip = document.createElement('div');
    tip.textContent = '✓ 已复制到剪贴板';
    tip.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(0);'
        + 'background:var(--gray-900,#1a1d24);color:#fff;padding:10px 22px;border-radius:24px;'
        + 'font-size:0.88rem;z-index:9999;pointer-events:none;'
        + 'box-shadow:0 4px 20px rgba(0,0,0,0.25);transition:opacity 0.4s;';
    document.body.appendChild(tip);
    setTimeout(function() {
        tip.style.opacity = '0';
        setTimeout(function() { if (tip.parentNode) tip.parentNode.removeChild(tip); }, 400);
    }, 1800);
}

/* ---- 离站确认弹窗 ---- */
function leaveConfirm(url) {
    // 关闭已有弹窗
    var old = document.querySelector('.leave-modal');
    if (old) old.parentNode.removeChild(old);

    var overlay = document.createElement('div');
    overlay.className = 'leave-modal';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;'
        + 'display:flex;align-items:center;justify-content:center;padding:20px;';

    var box = document.createElement('div');
    box.style.cssText = 'background:#fff;border-radius:16px;max-width:420px;width:100%;'
        + 'padding:32px;box-shadow:0 20px 60px rgba(0,0,0,0.3);';

    // 警告图标
    var iconWrap = document.createElement('div');
    iconWrap.style.cssText = 'width:48px;height:48px;background:#fff8e8;border-radius:50%;'
        + 'display:flex;align-items:center;justify-content:center;margin:0 auto 16px;';
    iconWrap.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#f5a623" stroke-width="2">'
        + '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>'
        + '<line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

    var title = document.createElement('h3');
    title.textContent = '即将离开本站';
    title.style.cssText = 'text-align:center;font-size:1.1rem;font-weight:700;margin-bottom:10px;color:#1a1d24;';

    var desc = document.createElement('p');
    desc.style.cssText = 'text-align:center;font-size:0.88rem;color:#5a5f70;margin-bottom:20px;line-height:1.6;';
    desc.innerHTML = '你即将跳转至外部链接，本站不对第三方内容负责，<br>请注意甄别信息安全，谨防诈骗。';

    var urlBox = document.createElement('div');
    urlBox.textContent = url;
    urlBox.style.cssText = 'background:#f5f7fc;border-radius:8px;padding:10px 14px;word-break:break-all;'
        + 'font-size:0.78rem;color:#8a8fa0;margin-bottom:20px;';

    var btnRow = document.createElement('div');
    btnRow.style.cssText = 'display:flex;gap:12px;';

    var btnCancel = document.createElement('button');
    btnCancel.textContent = '取消';
    btnCancel.style.cssText = 'flex:1;padding:10px;border:1.5px solid #d8dce8;border-radius:8px;'
        + 'background:#fff;cursor:pointer;font-size:0.9rem;color:#5a5f70;';
    btnCancel.addEventListener('click', function() { overlay.parentNode.removeChild(overlay); });

    var btnGo = document.createElement('button');
    btnGo.textContent = '确认跳转';
    btnGo.style.cssText = 'flex:1;padding:10px;border:none;border-radius:8px;'
        + 'background:linear-gradient(135deg,#0a84ff,#0060cc);cursor:pointer;'
        + 'font-size:0.9rem;color:#fff;font-weight:600;';
    btnGo.addEventListener('click', function() {
        window.open(url, '_blank', 'noopener,noreferrer');
        overlay.parentNode.removeChild(overlay);
    });

    btnRow.appendChild(btnCancel);
    btnRow.appendChild(btnGo);
    box.appendChild(iconWrap);
    box.appendChild(title);
    box.appendChild(desc);
    box.appendChild(urlBox);
    box.appendChild(btnRow);
    overlay.appendChild(box);

    // 点击蒙层关闭
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.parentNode.removeChild(overlay);
    });
    document.body.appendChild(overlay);
}

/* ---- 拦截 .leave-link 的左键单击（右键不拦截，保留系统菜单） ---- */
document.addEventListener('click', function(e) {
    var link = e.target.closest('.leave-link');
    if (!link) return;
    // Ctrl/Cmd/Shift 按下时直接走浏览器原生行为（在新标签页打开）
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    leaveConfirm(link.dataset.url);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
