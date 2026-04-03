<?php
/**
 * 首页 - Bot 列表展示
 */
require_once __DIR__ . '/includes/functions.php';
$pdo = getDB();

// ======== 参数处理 ========
$keyword   = trim($_GET['q'] ?? '');
$fPlatform = $_GET['platform'] ?? '';
$fFramework= $_GET['framework'] ?? '';
$fMode     = $_GET['mode'] ?? '';
$fBlacklist= $_GET['blacklist'] ?? '';
$fStatus   = $_GET['status'] ?? '';
$sortBy    = in_array($_GET['sort'] ?? '', ['created_at', 'view_count']) ? $_GET['sort'] : 'created_at';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = PER_PAGE;

// ======== 构建查询 ========
// 已登录：审核通过的所有人可见；自己发布的待审核内容自己也能看
$currentUser = getCurrentUser();
$myId = $currentUser ? (int)$currentUser['id'] : 0;
if ($myId > 0) {
    $where  = ['(b.review_status = 1 OR (b.review_status = 0 AND b.user_id = ?))', 'u.is_banned = 0'];
    $params = [$myId];
} else {
    $where  = ['b.review_status = 1', 'u.is_banned = 0'];
    $params = [];
}

if ($keyword !== '') {
    $where[]  = '(b.nickname LIKE ? OR b.owner LIKE ? OR b.id_url LIKE ? OR b.remarks LIKE ?)';
    $kw = '%' . $keyword . '%';
    $params = array_merge($params, [$kw, $kw, $kw, $kw]);
}
if ($fPlatform !== '')  { $where[] = 'b.platform = ?';  $params[] = $fPlatform; }
if ($fFramework !== '') { $where[] = 'b.framework = ?'; $params[] = $fFramework; }
if ($fMode !== '')      { $where[] = 'b.mode = ?';      $params[] = $fMode; }
if ($fBlacklist !== '') { $where[] = 'b.blacklist = ?'; $params[] = $fBlacklist; }
if ($fStatus !== '')    { $where[] = 'b.status = ?';    $params[] = $fStatus; }

$whereStr = implode(' AND ', $where);
$orderStr = ($sortBy === 'view_count') ? 'b.view_count DESC, b.created_at DESC' : 'b.created_at DESC';

// 总数
$countSql = "SELECT COUNT(*) FROM bots b LEFT JOIN users u ON b.user_id=u.id WHERE $whereStr";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// 分页数据
$offset = ($page - 1) * $perPage;
$sql = "SELECT b.*, u.nickname AS author_nickname, u.username AS author_username
        FROM bots b
        LEFT JOIN users u ON b.user_id = u.id
        WHERE $whereStr
        ORDER BY $orderStr
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bots = $stmt->fetchAll();

// 下拉选项
$optPlatform  = getOptions('platform');
$optFramework = getOptions('framework');
$optMode      = getOptions('mode');
$optBlacklist = getOptions('blacklist');
$optStatus    = getOptions('status');

// 统计数字
$totalBots    = (int)$pdo->query('SELECT COUNT(*) FROM bots WHERE review_status=1')->fetchColumn();
$totalUsers   = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_banned=0')->fetchColumn();
$onlineBots   = (int)$pdo->query("SELECT COUNT(*) FROM bots WHERE status='在线' AND review_status=1")->fetchColumn();

// 分页链接基础URL
$baseQuery = http_build_query(array_filter([
    'q'         => $keyword,
    'platform'  => $fPlatform,
    'framework' => $fFramework,
    'mode'      => $fMode,
    'blacklist' => $fBlacklist,
    'status'    => $fStatus,
    'sort'      => $sortBy,
]));
$paginationUrl = '/index.php?' . $baseQuery;

$pageTitle = 'Bot 列表';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero 区域 -->
<div class="hero">
  <div class="hero-content container">
    <h1>TRPG <span>Bot</span> 导航</h1>
    <p>汇聚TRPG线上跑团优质骰子机器人，发现适合你的跑团伙伴</p>
    <?php if ($currentUser): ?>
    <a href="/submit.php" class="btn btn-primary btn-lg">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      提交我的Bot
    </a>
    <?php else: ?>
    <a href="/register.php" class="btn btn-primary btn-lg">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
      注册并展示你的Bot
    </a>
    <?php endif; ?>
    <div class="hero-stats">
      <div class="hero-stat">
        <div class="hero-stat-value"><?= $totalBots ?></div>
        <div class="hero-stat-label">收录Bot</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-value"><?= $onlineBots ?></div>
        <div class="hero-stat-label">在线Bot</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-value"><?= $totalUsers ?></div>
        <div class="hero-stat-label">注册骰主</div>
      </div>
    </div>
  </div>
</div>

<div class="container">
  <?php
  $announcements = getActiveAnnouncements();
  if ($announcements):
  ?>
  <div class="announcements-wrap mt-3">
    <?php foreach ($announcements as $ann): ?>
    <div class="announcement announcement-<?= e($ann['type']) ?>">
      <div class="announcement-icon">
        <?php if ($ann['type'] === 'warning'): ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <?php elseif ($ann['type'] === 'danger'): ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php elseif ($ann['type'] === 'success'): ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?php else: ?>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <?php endif; ?>
      </div>
      <div class="announcement-body">
        <?php if ($ann['title']): ?><strong><?= e($ann['title']) ?></strong><?php endif; ?>
        <div class="announcement-content"><?= $ann['content'] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <!-- 搜索 & 筛选栏 -->
  <form method="GET" action="/index.php" id="filterForm">
    <div class="search-bar mt-3 mb-2">
      <div class="search-input-wrap">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="searchKeyword" name="q" placeholder="搜索Bot昵称、骰主、ID..." value="<?= e($keyword) ?>">
      </div>

      <div class="filter-selects-row">
        <select name="platform" class="filter-select" onchange="this.form.submit()">
          <option value="">全部平台</option>
          <?php foreach ($optPlatform as $opt): ?>
          <option value="<?= e($opt['value']) ?>" <?= $fPlatform === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="framework" class="filter-select" onchange="this.form.submit()">
          <option value="">全部框架</option>
          <?php foreach ($optFramework as $opt): ?>
          <option value="<?= e($opt['value']) ?>" <?= $fFramework === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="mode" class="filter-select" onchange="this.form.submit()">
          <option value="">全部模式</option>
          <?php foreach ($optMode as $opt): ?>
          <option value="<?= e($opt['value']) ?>" <?= $fMode === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="blacklist" class="filter-select" onchange="this.form.submit()">
          <option value="">黑名单</option>
          <?php foreach ($optBlacklist as $opt): ?>
          <option value="<?= e($opt['value']) ?>" <?= $fBlacklist === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="">全部状态</option>
          <?php foreach ($optStatus as $opt): ?>
          <option value="<?= e($opt['value']) ?>" <?= $fStatus === $opt['value'] ? 'selected' : '' ?>><?= e($opt['value']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" class="btn btn-primary btn-sm">搜索</button>
    </div>
  </form>

  <!-- 结果信息 & 排序 -->
  <div class="d-flex justify-between align-center mb-2 flex-wrap gap-1" style="font-size:0.88rem;">
    <div class="text-gray">
      共找到 <strong class="text-blue"><?= $total ?></strong> 条结果
    </div>
    <div class="d-flex align-center gap-1">
      <span class="text-gray">排序：</span>
      <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'created_at', 'page'=>1])) ?>"
         class="btn btn-sm <?= $sortBy === 'created_at' ? 'btn-primary' : 'btn-ghost' ?>">最新发布</a>
      <a href="?<?= http_build_query(array_merge($_GET, ['sort'=>'view_count', 'page'=>1])) ?>"
         class="btn btn-sm <?= $sortBy === 'view_count' ? 'btn-primary' : 'btn-ghost' ?>">浏览最多</a>
    </div>
  </div>

  <!-- Bot卡片列表 -->
  <?php if (empty($bots)): ?>
  <div class="empty-state">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <h3>没有找到匹配的Bot</h3>
    <p>尝试修改搜索关键词或筛选条件</p>
    <a href="/index.php" class="btn btn-outline">清除筛选</a>
  </div>
  <?php else: ?>
  <div class="bot-grid">
    <?php foreach ($bots as $bot): ?>
    <div class="bot-card <?= $bot['review_status'] == 0 ? 'bot-card-pending' : '' ?>">
      <div class="bot-card-head">
        <a href="/detail.php?id=<?= $bot['id'] ?>" class="bot-card-title"><?= e($bot['nickname']) ?></a>
        <div class="bot-card-badges">
          <?php if ($bot['review_status'] == 0): ?>
          <span class="badge badge-warning badge-pending-review">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-1px;margin-right:2px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            审核中
          </span>
          <?php endif; ?>
          <?php if ($bot['status']): ?>
          <span class="badge status-<?= e($bot['status']) ?>"><?= e($bot['status']) ?></span>
          <?php endif; ?>
          <?php if ($bot['platform']): ?>
          <span class="badge badge-blue"><?= e($bot['platform']) ?></span>
          <?php endif; ?>
          <?php if ($bot['mode']): ?>
          <span class="badge badge-gray"><?= e($bot['mode']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="bot-card-body">
        <div class="bot-card-info">
          <div class="bot-info-item">
            <span class="bot-info-label">运行框架</span>
            <span class="bot-info-value"><?= $bot['framework'] ? e($bot['framework']) : '-' ?></span>
          </div>
          <div class="bot-info-item">
            <span class="bot-info-label">骰主</span>
            <span class="bot-info-value"><?= $bot['owner'] ? e($bot['owner']) : '-' ?></span>
          </div>
          <div class="bot-info-item">
            <span class="bot-info-label">黑名单</span>
            <span class="bot-info-value"><?= $bot['blacklist'] ? e($bot['blacklist']) : '-' ?></span>
          </div>
          <div class="bot-info-item">
            <span class="bot-info-label">邀请条件</span>
            <span class="bot-info-value" title="<?= e($bot['invite_condition']) ?>">
              <?= $bot['invite_condition'] ? e(mb_substr($bot['invite_condition'], 0, 12) . (mb_strlen($bot['invite_condition']) > 12 ? '...' : '')) : '-' ?>
            </span>
          </div>
        </div>
      </div>
      <div class="bot-card-foot">
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;margin-right:3px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <?= e($bot['author_nickname'] ?: $bot['author_username']) ?>
        </span>
        <span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;margin-right:3px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          <?= $bot['view_count'] ?>
          &nbsp;&nbsp;
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px;margin-right:3px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <?= formatTime($bot['created_at']) ?>
        </span>
        <a href="/detail.php?id=<?= $bot['id'] ?>">
          查看详情
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- 分页 -->
  <?= buildPagination($total, $page, $perPage, $paginationUrl) ?>
</div>


<style>
.bot-card-pending {
    border: 1px dashed var(--warning, #f59e0b) !important;
    opacity: 0.85;
}
.badge-pending-review {
    background: rgba(245,158,11,0.12);
    color: #b45309;
    border: 1px solid rgba(245,158,11,0.3);
}
</style>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
