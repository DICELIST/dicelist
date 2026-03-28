<?php
/**
 * 草稿保存接口
 * POST: csrf_token, draft_id(可选), 各bot字段
 */
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['ok'=>false,'msg'=>'请先登录']);
    exit;
}
verifyCsrf();

$pdo = getDB();
$user = getCurrentUser();
$draftId = (int)($_POST['draft_id'] ?? 0);

$fields = ['platform','nickname','id_url','framework','owner','mode','blacklist','status','invite_condition','remarks','description'];
$data = [];
foreach ($fields as $f) {
    $data[$f] = trim($_POST[$f] ?? '');
}

// bot_id：编辑已有bot时传入
$botId = (int)($_POST['bot_id'] ?? 0) ?: null;

if ($draftId > 0) {
    // 更新已有草稿（需归属当前用户）
    $check = $pdo->prepare('SELECT id FROM bot_drafts WHERE id=? AND user_id=?');
    $check->execute([$draftId, $user['id']]);
    if (!$check->fetch()) {
        echo json_encode(['ok'=>false,'msg'=>'草稿不存在']);
        exit;
    }
    $sql = 'UPDATE bot_drafts SET platform=?,nickname=?,id_url=?,framework=?,owner=?,mode=?,blacklist=?,status=?,invite_condition=?,remarks=?,description=?,bot_id=?,saved_at=NOW() WHERE id=?';
    $pdo->prepare($sql)->execute([
        $data['platform'],$data['nickname'],$data['id_url'],$data['framework'],
        $data['owner'],$data['mode'],$data['blacklist'],$data['status'],
        $data['invite_condition'],$data['remarks'],$data['description'],
        $botId, $draftId
    ]);
    echo json_encode(['ok'=>true,'draft_id'=>$draftId]);
} else {
    // 新建草稿
    $sql = 'INSERT INTO bot_drafts (user_id,bot_id,platform,nickname,id_url,framework,owner,mode,blacklist,status,invite_condition,remarks,description)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';
    $pdo->prepare($sql)->execute([
        $user['id'],$botId,
        $data['platform'],$data['nickname'],$data['id_url'],$data['framework'],
        $data['owner'],$data['mode'],$data['blacklist'],$data['status'],
        $data['invite_condition'],$data['remarks'],$data['description'],
    ]);
    echo json_encode(['ok'=>true,'draft_id'=>(int)$pdo->lastInsertId()]);
}
