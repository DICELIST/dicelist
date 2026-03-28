<?php
/**
 * 管理后台 - 操作日志（列表 + CSV 导出）
 * CSV 导出的 header() 必须在 require header.php 之前，否则 HTML 已输出无法再 header()
 */
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
$pdo = getDB();
$currentUser = getCurrentUser();

// ---- CSV 导出 ----
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    verifyCsrf();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="admin_logs_' . date('Ymd_His') . '.csv"');
    // BOM for Excel UTF-8
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', '操作管理员', '操作类型', '目标类型', '目标ID', '详情', 'IP地址', '操作时间']);

    $rows = $pdo->query(
        'SELECT id, admin_name, action, target_type, target_id, detail, ip, created_at
         FROM admin_logs ORDER BY id DESC LIMIT 5000'
    )->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['admin_name'], $r['action'],
            $r['target_type'], $r['target_id'] ?? '',
            $r['detail'], $r['ip'],
            $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$adminPageTitle = '操作日志';
require_once __DIR__ . '/header.php';

// ---- 筛选参数 ----
$adminFilter  = trim($_GET['admin']  ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

// ---- 构建 WHERE ----
$where  = [];
$params = [];
if ($adminFilter !== '') { $where[] = 'admin_name LIKE ?'; $params[] = '%' . $adminFilter . '%'; }
if ($actionFilter !== '') { $where[] = 'action = ?'; $params[] = $actionFilter; }
$whereStr = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// ---- 总数 ----
$stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_logs$whereStr");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

// ---- 数据 ----
$stmt = $pdo->prepare(
    "SELECT id, admin_name, action, target_type, target_id, detail, ip, created_at
     FROM admin_logs$whereStr ORDER BY id DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// ---- 所有操作类型（用于筛选下拉）----
$actionTypes = $pdo->query('SELECT DISTINCT action FROM admin_logs ORDER BY action ASC')->fetchAll(PDO::FETCH_COLUMN);

// ---- 操作类型中文映射 ----
$actionLabels = [
    'approve_bot'       => '通过Bot',
    'reject_bot'        => '拒绝Bot',
    'delete_bot'        => '删除Bot',
    'ban_user'          => '封禁用户',
    'unban_user'        => '解封用户',
    'set_admin'         => '设置管理员',
    'remove_admin'      => '撤销管理员',
    'delete_user'       => '删除用户',
    'edit_user'         => '编辑用户',
    'edit_agreement'    => '编辑协议',
    'add_announcement'  => '新增公告',
    'edit_announcement' => '编辑公告',
    'del_announcement'  => '删除公告',
    'toggle_announcement'=> '切换公告',
    'add_link'          => '新增友链',
    'edit_link'         => '编辑友链',
    'del_link'          => '删除友链',
    'add_option'        => '添加选项',
    'del_option'        => '删除选项',
];

// ---- 分页 baseUrl ----
$baseQuery = http_build_query(array_filter([
    'admin'  => $adminFilter,
    'action' => $actionFilter,
]));
$baseUrl = '/admin/logs.php?' . ($baseQuery ? $baseQuery . '&' : '');

// CSRF for export link
$csrfToken = e(getCsrfToken());
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
  <h1 style="font-size:1.4rem;font-weight:800;margin:0;">操作日志</h1>
  <div style="display:flex;gap:8px;">
    <form method="GET" action="/admin/logs.php" style="display:flex;gap:8px;flex-wrap:wrap;">
      <input type="hidden" name="export" value="csv">
      <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
      <button type="submit" class="btn btn-outline btn-sm">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        导出 CSV
      </button>
    </form>
  </div>
</div>

<!-- 筛选栏 -->
<form method="GET" action="/admin/logs.php" class="filter-bar" style="margin-bottom:20px;">
  <input type="text" name="admin" class="form-control" style="max-width:160px;"
         placeholder="管理员用户名" value="<?= e($adminFilter) ?>">
  <select name="action" class="form-control" style="max-width:160px;">
    <option value="">全部操作</option>
    <?php foreach ($actionTypes as $at): ?>
    <option value="<?= e($at) ?>" <?= $actionFilter === $at ? 'selected' : '' ?>>
      <?= e($actionLabels[$at] ?? $at) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">筛选</button>
  <?php if ($adminFilter || $actionFilter): ?>
  <a href="/admin/logs.php" class="btn btn-ghost btn-sm">清除</a>
  <?php endif; ?>
</form>

<div class="card" style="margin-bottom:24px;">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">日志列表</h3>
    <span style="font-size:0.82rem;color:var(--text-sub);">共 <?= $total ?> 条记录</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:60px;">ID</th>
          <th>管理员</th>
          <th>操作</th>
          <th>目标</th>
          <th>详情</th>
          <th>IP 地址</th>
          <th>操作时间</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text-sub);">暂无日志记录</td></tr>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td style="color:var(--text-sub);font-size:0.8rem;">#<?= $log['id'] ?></td>
          <td>
            <strong><?= e($log['admin_name']) ?></strong>
          </td>
          <td>
            <?php
            $actionKey = $log['action'];
            $actionLabel = $actionLabels[$actionKey] ?? $actionKey;
            // 颜色分类
            $badgeClass = 'badge-blue';
            if (in_array($actionKey, ['ban_user','delete_bot','delete_user','del_announcement','del_link'])) {
                $badgeClass = 'badge-red';
            } elseif (in_array($actionKey, ['approve_bot','unban_user','set_admin'])) {
                $badgeClass = 'badge-green';
            } elseif (in_array($actionKey, ['reject_bot','remove_admin'])) {
                $badgeClass = 'badge-orange';
            }
            ?>
            <span class="badge <?= $badgeClass ?>"><?= e($actionLabel) ?></span>
          </td>
          <td style="font-size:0.82rem;color:var(--text-sub);">
            <?php if ($log['target_type']): ?>
            <?= e($log['target_type']) ?>
            <?= $log['target_id'] ? '<span style="color:var(--blue-primary);">#' . $log['target_id'] . '</span>' : '' ?>
            <?php else: ?>
            -
            <?php endif; ?>
          </td>
          <td style="font-size:0.82rem;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($log['detail']) ?>">
            <?= $log['detail'] ? e(mb_strimwidth($log['detail'], 0, 50, '...')) : '-' ?>
          </td>
          <td style="font-size:0.78rem;color:var(--text-sub);font-family:monospace;"><?= e($log['ip']) ?></td>
          <td style="font-size:0.78rem;white-space:nowrap;"><?= formatTime($log['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="pagination">
  <?php if ($page > 1): ?>
  <a href="<?= $baseUrl ?>page=<?= $page-1 ?>" class="page-btn">&lsaquo;</a>
  <?php endif; ?>
  <?php
  $start = max(1, $page - 3);
  $end   = min($totalPages, $page + 3);
  if ($start > 1) echo '<a href="' . $baseUrl . 'page=1" class="page-btn">1</a>' . ($start > 2 ? '<span class="page-btn page-ellipsis">…</span>' : '');
  for ($i = $start; $i <= $end; $i++) {
      $active = $i === $page ? ' active' : '';
      echo '<a href="' . $baseUrl . 'page=' . $i . '" class="page-btn' . $active . '">' . $i . '</a>';
  }
  if ($end < $totalPages) echo ($end < $totalPages - 1 ? '<span class="page-btn page-ellipsis">…</span>' : '') . '<a href="' . $baseUrl . 'page=' . $totalPages . '" class="page-btn">' . $totalPages . '</a>';
  ?>
  <?php if ($page < $totalPages): ?>
  <a href="<?= $baseUrl ?>page=<?= $page+1 ?>" class="page-btn">&rsaquo;</a>
  <?php endif; ?>
</nav>
<?php endif; ?>

<style>
.filter-bar { display:flex;gap:10px;align-items:center;flex-wrap:wrap; }
.badge-red { background:rgba(239,68,68,0.12);color:#ef4444; }
.badge-orange { background:rgba(245,158,11,0.12);color:#d97706; }
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
