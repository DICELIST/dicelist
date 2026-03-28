<?php
/**
 * 管理后台 - 公告管理
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
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $type    = in_array($_POST['type'] ?? '', ['info','warning','success','danger']) ? $_POST['type'] : 'info';
        $sort    = (int)($_POST['sort_order'] ?? 0);
        $active  = isset($_POST['is_active']) ? 1 : 0;
        $id      = (int)($_POST['id'] ?? 0);

        if ($id > 0) {
            $pdo->prepare('UPDATE announcements SET title=?,content=?,type=?,sort_order=?,is_active=? WHERE id=?')
                ->execute([$title,$content,$type,$sort,$active,$id]);
            logAdminAction('编辑公告', 'announcement', $id, $title);
            setFlash('success', '公告已更新');
        } else {
            $pdo->prepare('INSERT INTO announcements (title,content,type,sort_order,is_active,created_by) VALUES (?,?,?,?,?,?)')
                ->execute([$title,$content,$type,$sort,$active,$currentUser['id']]);
            logAdminAction('发布公告', 'announcement', (int)$pdo->lastInsertId(), $title);
            setFlash('success', '公告已发布');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM announcements WHERE id=?')->execute([$id]);
        logAdminAction('删除公告', 'announcement', $id);
        setFlash('success', '公告已删除');
    } elseif ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE announcements SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
        setFlash('success', '状态已切换');
    }
    header('Location: /admin/announcements.php'); exit;
}

$adminPageTitle = '公告管理';
require_once __DIR__ . '/header.php';

$editItem = null;
if (!empty($_GET['edit'])) {
    $s = $pdo->prepare('SELECT * FROM announcements WHERE id=?');
    $s->execute([(int)$_GET['edit']]);
    $editItem = $s->fetch();
}

$list = $pdo->query('SELECT * FROM announcements ORDER BY sort_order ASC, id DESC')->fetchAll();
?>

<div class="d-flex justify-between align-center mb-3">
  <h1 style="font-size:1.4rem;font-weight:800;">公告管理</h1>
  <a href="/admin/announcements.php?edit=0" class="btn btn-primary btn-sm">+ 新建公告</a>
</div>

<?php if ($editItem !== null || isset($_GET['edit'])): ?>
<div class="card mb-3" style="max-width:700px;">
  <div class="card-header">
    <h3><?= $editItem ? '编辑公告' : '新建公告' ?></h3>
    <a href="/admin/announcements.php" class="btn btn-ghost btn-sm">取消</a>
  </div>
  <div class="card-body">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="action" value="save">
      <?php if ($editItem): ?><input type="hidden" name="id" value="<?= $editItem['id'] ?>"><?php endif; ?>
      <div class="form-group">
        <label class="form-label">公告标题</label>
        <input type="text" name="title" class="form-control" value="<?= e($editItem['title'] ?? '') ?>" placeholder="（可选）">
      </div>
      <div class="form-group">
        <label class="form-label">公告内容 <span class="required">*</span></label>
        <textarea name="content" class="form-control" rows="4" required><?= e($editItem['content'] ?? '') ?></textarea>
        <div class="form-hint">支持简单HTML标签</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
        <div class="form-group mb-0">
          <label class="form-label">类型</label>
          <select name="type" class="form-control">
            <?php foreach (['info'=>'普通信息','warning'=>'警告','success'=>'成功/好消息','danger'=>'重要/危险'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= ($editItem['type']??'info')===$k?'selected':'' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group mb-0">
          <label class="form-label">排序（小→靠前）</label>
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
        <button type="submit" class="btn btn-primary">保存公告</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="table-wrap">
  <table>
    <thead><tr><th>ID</th><th>标题</th><th>类型</th><th>排序</th><th>状态</th><th>时间</th><th>操作</th></tr></thead>
    <tbody>
      <?php foreach ($list as $item): ?>
      <tr>
        <td><?= $item['id'] ?></td>
        <td><?= e($item['title'] ?: mb_substr(strip_tags($item['content']),0,30).'…') ?></td>
        <td><span class="badge announcement-badge-<?= e($item['type']) ?>"><?= e($item['type']) ?></span></td>
        <td><?= $item['sort_order'] ?></td>
        <td><?= $item['is_active'] ? '<span class="badge badge-green">显示中</span>' : '<span class="badge badge-gray">已隐藏</span>' ?></td>
        <td><?= formatTime($item['created_at']) ?></td>
        <td>
          <div class="d-flex gap-1">
            <a href="/admin/announcements.php?edit=<?= $item['id'] ?>" class="btn btn-ghost btn-sm">编辑</a>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $item['id'] ?>">
              <button type="submit" class="btn btn-ghost btn-sm"><?= $item['is_active'] ? '隐藏' : '显示' ?></button>
            </form>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $item['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" data-confirm="确定删除此公告？">删除</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
