<?php
/**
 * 管理后台 - 友情链接管理
 * POST 处理必须在 require header.php 之前，否则 HTML 已输出无法再 header()
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$pdo = getDB();
$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $name   = trim($_POST['name'] ?? '');
        $url    = trim($_POST['url'] ?? '');
        $logo   = trim($_POST['logo'] ?? '');
        $sort   = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        $id     = (int)($_POST['id'] ?? 0);

        if (!$name || !$url) { setFlash('error', '名称和链接不能为空'); }
        elseif ($id > 0) {
            $pdo->prepare('UPDATE friend_links SET name=?,url=?,logo=?,sort_order=?,is_active=? WHERE id=?')
                ->execute([$name,$url,$logo,$sort,$active,$id]);
            logAdminAction('编辑友情链接', 'link', $id, $name);
            setFlash('success', '已更新');
        } else {
            $pdo->prepare('INSERT INTO friend_links (name,url,logo,sort_order,is_active) VALUES (?,?,?,?,?)')
                ->execute([$name,$url,$logo,$sort,$active]);
            logAdminAction('添加友情链接', 'link', (int)$pdo->lastInsertId(), $name);
            setFlash('success', '已添加');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM friend_links WHERE id=?')->execute([$id]);
        logAdminAction('删除友情链接', 'link', $id);
        setFlash('success', '已删除');
    }
    header('Location: /admin/links.php'); exit;
}

$adminPageTitle = '友情链接';
require_once __DIR__ . '/header.php';

$editItem = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM friend_links WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $editItem = $s->fetch();
}

$list = $pdo->query('SELECT * FROM friend_links ORDER BY sort_order ASC, id ASC')->fetchAll();
?>

<div class="d-flex justify-between align-center mb-3">
  <h1 style="font-size:1.4rem;font-weight:800;">友情链接</h1>
  <a href="/admin/links.php?edit=0" class="btn btn-primary btn-sm">+ 添加链接</a>
</div>

<?php if ($editItem !== null || isset($_GET['edit'])): ?>
<div class="card mb-3" style="max-width:600px;">
  <div class="card-header">
    <h3><?= $editItem ? '编辑链接' : '添加链接' ?></h3>
    <a href="/admin/links.php" class="btn btn-ghost btn-sm">取消</a>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
      <div class="form-group">
        <label class="form-label">链接名称 <span class="required">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= e($editItem['name'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">链接地址 <span class="required">*</span></label>
        <input type="url" name="url" class="form-control" value="<?= e($editItem['url'] ?? '') ?>" placeholder="https://" required>
      </div>
      <div class="form-group">
        <label class="form-label">Logo URL（可选）</label>
        <input type="url" name="logo" class="form-control" value="<?= e($editItem['logo'] ?? '') ?>" placeholder="https://...">
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="form-group mb-0">
          <label class="form-label">排序</label>
          <input type="number" name="sort_order" class="form-control" value="<?= $editItem['sort_order'] ?? 0 ?>">
        </div>
        <div class="form-group mb-0" style="display:flex;align-items:flex-end;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
            <input type="checkbox" name="is_active" value="1" <?= ($editItem['is_active']??1)?'checked':'' ?>>
            立即显示
          </label>
        </div>
      </div>
      <div class="mt-3">
        <button type="submit" class="btn btn-primary">保存</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead><tr><th>排序</th><th>名称</th><th>地址</th><th>状态</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($list as $item): ?>
      <tr>
        <td><?= $item['sort_order'] ?></td>
        <td>
          <?php if ($item['logo']): ?>
          <img src="<?= e($item['logo']) ?>" alt="" style="width:20px;height:20px;object-fit:contain;vertical-align:middle;margin-right:6px;border-radius:4px;">
          <?php endif; ?>
          <?= e($item['name']) ?>
        </td>
        <td style="font-size:0.82rem;color:var(--text-sub);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener"><?= e($item['url']) ?></a>
        </td>
        <td><?= $item['is_active'] ? '<span class="badge badge-green">显示</span>' : '<span class="badge badge-gray">隐藏</span>' ?></td>
        <td>
          <div class="d-flex gap-1">
            <a href="/admin/links.php?edit=<?= $item['id'] ?>" class="btn btn-ghost btn-sm">编辑</a>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $item['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" data-confirm="确定删除？">删除</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
