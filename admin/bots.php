<?php
/**
 * 管理后台 - Bot 管理（含内容审核）
 * POST 处理必须在 require header.php 之前，否则 HTML 已输出无法再 header()
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();
$pdo = getDB();
$currentUser = getCurrentUser();

// ======== 操作处理（必须在输出任何HTML前完成跳转）========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $bid    = (int)($_POST['bid'] ?? 0);

    if ($action === 'delete' && $bid > 0) {
        $b = $pdo->prepare('SELECT nickname FROM bots WHERE id=?');
        $b->execute([$bid]);
        $bname = $b->fetchColumn();
        $pdo->prepare('DELETE FROM bots WHERE id=?')->execute([$bid]);
        logAdminAction('delete_bot', 'bot', $bid, "Bot：{$bname}");
        setFlash('success', 'Bot已删除');

    } elseif ($action === 'approve' && $bid > 0) {
        // 查询 Bot 及发布者邮箱（用于通知）
        $bInfo = $pdo->prepare('SELECT b.nickname, b.user_id, u.email, u.username, u.nickname AS unick
            FROM bots b LEFT JOIN users u ON b.user_id=u.id WHERE b.id=?');
        $bInfo->execute([$bid]);
        $bRow = $bInfo->fetch();

        $pdo->prepare('UPDATE bots SET review_status=1, reviewed_at=NOW(), reviewed_by=? WHERE id=?')
            ->execute([$currentUser['id'], $bid]);
        logAdminAction('approve_bot', 'bot', $bid);

        // 发送通知邮件
        if ($bRow && $bRow['email']) {
            $siteName = getSiteSetting('site_name', 'TRPG Bot 导航');
            $uname = $bRow['unick'] ?: $bRow['username'];
            $tpl = renderMailTemplate('bot_approved', [
                'site_name' => $siteName,
                'username'  => $uname,
                'bot_name'  => $bRow['nickname'],
                'bot_url'   => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dicelist.cn') . '/detail.php?id=' . $bid,
            ]);
            smtpSend($bRow['email'], $tpl['subject'], $tpl['body']);
        }

        setFlash('success', '已通过审核，Bot对外可见');

    } elseif ($action === 'reject' && $bid > 0) {
        $remark = trim($_POST['review_remark'] ?? '');
        if ($remark === '') $remark = '内容违规';

        // 查询 Bot 及发布者邮箱
        $bInfo = $pdo->prepare('SELECT b.nickname, b.user_id, u.email, u.username, u.nickname AS unick
            FROM bots b LEFT JOIN users u ON b.user_id=u.id WHERE b.id=?');
        $bInfo->execute([$bid]);
        $bRow = $bInfo->fetch();

        $pdo->prepare('UPDATE bots SET review_status=2, review_remark=?, reviewed_at=NOW(), reviewed_by=? WHERE id=?')
            ->execute([$remark, $currentUser['id'], $bid]);
        logAdminAction('reject_bot', 'bot', $bid, "原因：{$remark}");

        // 发送通知邮件
        if ($bRow && $bRow['email']) {
            $siteName = getSiteSetting('site_name', 'TRPG Bot 导航');
            $uname = $bRow['unick'] ?: $bRow['username'];
            $tpl = renderMailTemplate('bot_rejected', [
                'site_name' => $siteName,
                'username'  => $uname,
                'bot_name'  => $bRow['nickname'],
                'reject_reason' => $remark,
                'edit_url'  => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dicelist.cn') . '/edit.php?id=' . $bid,
            ]);
            smtpSend($bRow['email'], $tpl['subject'], $tpl['body']);
        }

        setFlash('success', '已拒绝，内容移入用户回收站');

    } elseif ($action === 'revoke' && $bid > 0) {
        $remark = trim($_POST['review_remark'] ?? '');
        if ($remark === '') $remark = '内容违规';

        // 查询 Bot 及发布者邮箱
        $bInfo = $pdo->prepare('SELECT b.nickname, b.user_id, u.email, u.username, u.nickname AS unick
            FROM bots b LEFT JOIN users u ON b.user_id=u.id WHERE b.id=?');
        $bInfo->execute([$bid]);
        $bRow = $bInfo->fetch();

        $pdo->prepare('UPDATE bots SET review_status=2, review_remark=?, reviewed_at=NOW(), reviewed_by=? WHERE id=?')
            ->execute([$remark, $currentUser['id'], $bid]);
        logAdminAction('revoke_bot', 'bot', $bid, "原因：{$remark}");

        // 发送撤回通知邮件
        if ($bRow && $bRow['email']) {
            $siteName = getSiteSetting('site_name', 'TRPG Bot 导航');
            $uname = $bRow['unick'] ?: $bRow['username'];
            $tpl = renderMailTemplate('bot_revoked', [
                'site_name'     => $siteName,
                'username'      => $uname,
                'bot_name'      => $bRow['nickname'],
                'revoke_reason' => $remark,
                'edit_url'      => 'https://' . ($_SERVER['HTTP_HOST'] ?? 'dicelist.cn') . '/edit.php?id=' . $bid,
            ]);
            smtpSend($bRow['email'], $tpl['subject'], $tpl['body']);
        }

        setFlash('success', '已撤回，内容移入用户回收站');
    }

    header('Location: /admin/bots.php' . ($_SERVER['QUERY_STRING'] ? '?'.$_SERVER['QUERY_STRING'] : ''));
    exit;
}
// 兜底：POST 有 action/bid 但没匹配任何分支，记录到错误日志
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST['action']) || !empty($_POST['bid']))) {
    error_log("[Admin/bots] 未匹配的操作: action=" . ($_POST['action'] ?? '') . ", bid=" . ((int)($_POST['bid'] ?? 0)));
}

$adminPageTitle = 'Bot管理';
require_once __DIR__ . '/header.php';

// ======== 查询 ========
$keyword     = trim($_GET['q'] ?? '');
$reviewFilter = $_GET['review'] ?? '';   // '' all / '0' pending / '1' approved / '2' rejected

$where  = ['1=1'];
$params = [];
if ($keyword !== '') {
    $where[] = '(b.nickname LIKE ? OR b.owner LIKE ? OR u.username LIKE ?)';
    $kw = "%{$keyword}%";
    $params = [$kw, $kw, $kw];
}
if ($reviewFilter !== '') {
    $where[] = 'b.review_status = ?';
    $params[] = (int)$reviewFilter;
}
$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bots b LEFT JOIN users u ON b.user_id=u.id WHERE $whereStr");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page-1)*$perPage;

$stmt = $pdo->prepare("SELECT b.id,b.nickname,b.platform,b.status,b.review_status,b.review_remark,b.view_count,b.created_at,
    u.username,u.nickname AS un, u.is_banned
    FROM bots b LEFT JOIN users u ON b.user_id=u.id
    WHERE $whereStr ORDER BY b.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$bots = $stmt->fetchAll();

$pendingCount = (int)$pdo->query('SELECT COUNT(*) FROM bots WHERE review_status=0')->fetchColumn();

$paginationUrl = '/admin/bots.php?' . http_build_query(array_filter(['q'=>$keyword,'review'=>$reviewFilter]));
?>

<div class="d-flex justify-between align-center mb-3" style="flex-wrap:wrap;gap:12px;">
  <h1 style="font-size:1.4rem;font-weight:800;">Bot 管理
    <span style="font-size:0.9rem;color:var(--text-sub);font-weight:400;">(<?= $total ?>)</span>
    <?php if ($pendingCount > 0): ?>
    <span class="badge badge-danger" style="font-size:0.75rem;vertical-align:middle;"><?= $pendingCount ?> 待审核</span>
    <?php endif; ?>
  </h1>
  <form method="GET" action="/admin/bots.php" class="d-flex gap-1" style="flex-wrap:wrap;">
    <div class="search-input-wrap" style="min-width:200px;">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" placeholder="搜索Bot昵称、骰主、用户名" value="<?= e($keyword) ?>">
    </div>
    <select name="review" class="filter-select" onchange="this.form.submit()">
      <option value=""  <?= $reviewFilter===''  ? 'selected':'' ?>>全部状态</option>
      <option value="0" <?= $reviewFilter==='0' ? 'selected':'' ?>>待审核</option>
      <option value="1" <?= $reviewFilter==='1' ? 'selected':'' ?>>已通过</option>
      <option value="2" <?= $reviewFilter==='2' ? 'selected':'' ?>>已拒绝</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">搜索</button>
  </form>
</div>

<!-- 拒绝原因弹窗 -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:12px;max-width:420px;width:100%;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <h3 style="margin-bottom:6px;">填写拒绝原因</h3>
    <p style="font-size:0.82rem;color:var(--text-sub);margin-bottom:16px;">不填写则默认使用「内容违规」</p>
    <form method="POST" id="rejectForm">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="bid" id="rejectBid">
      <div class="form-group">
        <textarea name="review_remark" id="rejectRemark" class="form-control" rows="3"
                  placeholder="不填写则默认「内容违规」（用户可见）"></textarea>
      </div>
      <div class="d-flex gap-2 justify-end">
        <button type="button" onclick="document.getElementById('rejectModal').style.display='none'" class="btn btn-ghost">取消</button>
        <button type="submit" class="btn btn-danger">确认拒绝</button>
      </div>
    </form>
  </div>
</div>

<!-- 撤回原因弹窗 -->
<div id="revokeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:12px;max-width:420px;width:100%;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <h3 style="margin-bottom:6px;">填写撤回原因</h3>
    <p style="font-size:0.82rem;color:var(--text-sub);margin-bottom:16px;">不填写则默认使用「内容违规」，将通过邮件通知用户</p>
    <form method="POST" id="revokeForm">
      <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
      <input type="hidden" name="action" value="revoke">
      <input type="hidden" name="bid" id="revokeBid">
      <div class="form-group">
        <textarea name="review_remark" id="revokeRemark" class="form-control" rows="3"
                  placeholder="不填写则默认「内容违规」（用户可见）"></textarea>
      </div>
      <div class="d-flex gap-2 justify-end">
        <button type="button" onclick="document.getElementById('revokeModal').style.display='none'" class="btn btn-ghost">取消</button>
        <button type="submit" class="btn btn-danger">确认撤回</button>
      </div>
    </form>
  </div>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr><th>ID</th><th>昵称</th><th>平台</th><th>审核</th><th>状态</th><th>浏览</th><th>发布者</th><th>时间</th><th>操作</th></tr>
    </thead>
    <tbody>
      <?php foreach ($bots as $b): ?>
      <tr <?= $b['is_banned'] ? 'style="opacity:0.6;"' : '' ?>>
        <td><?= $b['id'] ?></td>
        <td><a href="/detail.php?id=<?= $b['id'] ?>" style="color:var(--blue-primary);text-decoration:none;font-weight:500;" target="_blank"><?= e($b['nickname']) ?></a></td>
        <td><?= $b['platform'] ? '<span class="badge badge-blue">'.e($b['platform']).'</span>' : '-' ?></td>
        <td>
          <?php
          $rl = ['0'=>['待审核','badge-warning'],'1'=>['已通过','badge-green'],'2'=>['已拒绝','badge-danger']];
          $rs = $rl[$b['review_status']] ?? ['未知','badge-gray'];
          echo "<span class=\"badge {$rs[1]}\">{$rs[0]}</span>";
          if ($b['review_status'] == 2 && $b['review_remark']) {
              echo '<span style="font-size:0.75rem;color:var(--text-sub);margin-left:6px;" title="'.e($b['review_remark']).'">原因</span>';
          }
          ?>
        </td>
        <td><?= $b['status'] ? '<span class="badge status-'.e($b['status']).'">'.e($b['status']).'</span>' : '-' ?></td>
        <td><?= $b['view_count'] ?></td>
        <td>
          <?= e($b['un'] ?: $b['username']) ?>
          <?php if ($b['is_banned']): ?><span class="badge badge-danger" style="font-size:0.7rem;">封禁</span><?php endif; ?>
        </td>
        <td><?= formatTime($b['created_at']) ?></td>
        <td>
          <div class="d-flex gap-1" style="flex-wrap:wrap;">
            <a href="/edit.php?id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm">编辑</a>
            <?php if ($b['review_status'] == 0): ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="bid" value="<?= $b['id'] ?>">
              <button type="submit" class="btn btn-sm" style="background:#34c759;color:#fff;">通过</button>
            </form>
            <button type="button" class="btn btn-danger btn-sm" onclick="openReject(<?= $b['id'] ?>)">拒绝</button>
            <?php elseif ($b['review_status'] == 1): ?>
            <button type="button" class="btn btn-ghost btn-sm" onclick="openRevoke(<?= $b['id'] ?>)">撤回</button>
            <?php else: ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="bid" value="<?= $b['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline" style="color:var(--blue-primary);">重新通过</button>
            </form>
            <?php endif; ?>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="bid" value="<?= $b['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm"
                      data-confirm="确定删除《<?= e($b['nickname']) ?>》？">删除</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= buildPagination($total, $page, $perPage, $paginationUrl) ?>

<script>
function openReject(bid) {
    document.getElementById('rejectBid').value = bid;
    document.getElementById('rejectRemark').value = '';
    document.getElementById('rejectModal').style.display = 'flex';
}
function openRevoke(bid) {
    document.getElementById('revokeBid').value = bid;
    document.getElementById('revokeRemark').value = '';
    document.getElementById('revokeModal').style.display = 'flex';
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
document.getElementById('revokeModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
