<?php
/**
 * 管理后台 - 下拉选项管理
 */
$adminPageTitle = '下拉选项管理';
require_once __DIR__ . '/header.php';
$pdo = getDB();

$typeLabels = [
    'platform'  => '对接平台',
    'framework' => '运行框架',
    'mode'      => '模式',
    'blacklist' => '黑名单',
    'status'    => '状态',
];

// 操作处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $type  = $_POST['type'] ?? '';
        $value = trim($_POST['value'] ?? '');
        if (isset($typeLabels[$type]) && $value !== '') {
            $maxSort = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM options WHERE type=?');
            $maxSort->execute([$type]);
            $pdo->prepare('INSERT INTO options (type, value, sort_order) VALUES (?, ?, ?)')->execute([$type, $value, $maxSort->fetchColumn()]);
            setFlash('success', '选项已添加');
        }
    } elseif ($action === 'edit') {
        $oid   = (int)($_POST['oid'] ?? 0);
        $value = trim($_POST['value'] ?? '');
        if ($oid > 0 && $value !== '') {
            $pdo->prepare('UPDATE options SET value=? WHERE id=?')->execute([$value, $oid]);
            setFlash('success', '选项已更新');
        }
    } elseif ($action === 'delete') {
        $oid = (int)($_POST['oid'] ?? 0);
        if ($oid > 0) {
            $pdo->prepare('UPDATE options SET is_active=0 WHERE id=?')->execute([$oid]);
            setFlash('success', '选项已禁用（软删除）');
        }
    } elseif ($action === 'restore') {
        $oid = (int)($_POST['oid'] ?? 0);
        if ($oid > 0) {
            $pdo->prepare('UPDATE options SET is_active=1 WHERE id=?')->execute([$oid]);
            setFlash('success', '选项已恢复');
        }
    } elseif ($action === 'sort_up' || $action === 'sort_down') {
        $oid = (int)($_POST['oid'] ?? 0);
        if ($oid > 0) {
            $stmt = $pdo->prepare('SELECT id, type, sort_order FROM options WHERE id=?');
            $stmt->execute([$oid]);
            $cur = $stmt->fetch();
            if ($cur) {
                if ($action === 'sort_up') {
                    $neighbor = $pdo->prepare('SELECT id, sort_order FROM options WHERE type=? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1');
                } else {
                    $neighbor = $pdo->prepare('SELECT id, sort_order FROM options WHERE type=? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1');
                }
                $neighbor->execute([$cur['type'], $cur['sort_order']]);
                $nb = $neighbor->fetch();
                if ($nb) {
                    $pdo->prepare('UPDATE options SET sort_order=? WHERE id=?')->execute([$nb['sort_order'], $oid]);
                    $pdo->prepare('UPDATE options SET sort_order=? WHERE id=?')->execute([$cur['sort_order'], $nb['id']]);
                }
            }
        }
    }
    header('Location: /admin/options.php' . (isset($_POST['type']) ? '#type-'.$_POST['type'] : ''));
    exit;
}

$currentType = $_GET['type'] ?? 'platform';
if (!isset($typeLabels[$currentType])) $currentType = 'platform';
$showAll = (bool)($_GET['all'] ?? false);
?>

<div class="d-flex justify-between align-center mb-3" style="flex-wrap:wrap;gap:12px;">
  <h1 style="font-size:1.4rem;font-weight:800;">下拉选项管理</h1>
</div>

<!-- 类型Tab -->
<div class="d-flex gap-1 mb-3" style="flex-wrap:wrap;">
  <?php foreach ($typeLabels as $tKey => $tLabel): ?>
  <a href="/admin/options.php?type=<?= $tKey ?>" class="btn <?= $currentType===$tKey?'btn-primary':'btn-ghost' ?> btn-sm"><?= $tLabel ?></a>
  <?php endforeach; ?>
</div>

<?php
$whereActive = $showAll ? '' : 'WHERE type=? AND is_active=1';
$whereAll    = 'WHERE type=?';
$stmt = $pdo->prepare('SELECT id, value, sort_order, is_active FROM options WHERE type=? ORDER BY sort_order ASC, id ASC');
$stmt->execute([$currentType]);
$opts = $stmt->fetchAll();
?>

<!-- 添加新选项 -->
<div class="card mb-3">
  <div class="card-header"><h3>添加新选项 - <?= $typeLabels[$currentType] ?></h3></div>
  <div class="card-body">
    <form method="POST" action="/admin/options.php" class="d-flex gap-2 align-center">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="type" value="<?= e($currentType) ?>">
      <input type="text" name="value" class="form-control" placeholder="选项名称" style="max-width:280px;" required>
      <button type="submit" class="btn btn-primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        添加
      </button>
    </form>
  </div>
</div>

<!-- 选项列表 -->
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>排序</th><th>选项值</th><th>状态</th><th>操作</th></tr>
    </thead>
    <tbody>
      <?php foreach ($opts as $opt): ?>
      <tr style="<?= !$opt['is_active'] ? 'opacity:0.5;' : '' ?>">
        <td>
          <div class="d-flex gap-1">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="sort_up">
              <input type="hidden" name="oid" value="<?= $opt['id'] ?>">
              <input type="hidden" name="type" value="<?= $currentType ?>">
              <button type="submit" class="btn btn-ghost btn-sm" title="上移">▲</button>
            </form>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="sort_down">
              <input type="hidden" name="oid" value="<?= $opt['id'] ?>">
              <input type="hidden" name="type" value="<?= $currentType ?>">
              <button type="submit" class="btn btn-ghost btn-sm" title="下移">▼</button>
            </form>
            <span style="color:var(--gray-400);font-size:0.82rem;"><?= $opt['sort_order'] ?></span>
          </div>
        </td>
        <td>
          <form method="POST" action="/admin/options.php" class="d-flex gap-1">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="oid" value="<?= $opt['id'] ?>">
            <input type="hidden" name="type" value="<?= $currentType ?>">
            <input type="text" name="value" class="form-control" value="<?= e($opt['value']) ?>" style="max-width:200px;">
            <button type="submit" class="btn btn-outline btn-sm">保存</button>
          </form>
        </td>
        <td>
          <?= $opt['is_active']
            ? '<span class="badge badge-green">启用</span>'
            : '<span class="badge badge-red">已禁用</span>' ?>
        </td>
        <td>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
            <input type="hidden" name="action" value="<?= $opt['is_active'] ? 'delete' : 'restore' ?>">
            <input type="hidden" name="oid" value="<?= $opt['id'] ?>">
            <input type="hidden" name="type" value="<?= $currentType ?>">
            <button type="submit" class="btn <?= $opt['is_active'] ? 'btn-danger' : 'btn-gold' ?> btn-sm">
              <?= $opt['is_active'] ? '禁用' : '恢复' ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($opts)): ?>
      <tr><td colspan="4" class="text-center text-gray" style="padding:20px;">暂无选项</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
