<?php
/**
 * 唯一性检查接口
 * GET/POST 参数：
 *   type = 'username' | 'nickname'
 *   value = 待检测的值
 *   exclude_id = 当前用户ID（编辑时排除自身，可选）
 * 返回 JSON：{ ok: true|false, msg: "..." }
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$type      = trim($_REQUEST['type']  ?? '');
$value     = trim($_REQUEST['value'] ?? '');
$excludeId = (int)($_REQUEST['exclude_id'] ?? 0);

if ($value === '') {
    echo json_encode(['ok' => false, 'msg' => '不能为空']);
    exit;
}

$pdo = getDB();

if ($type === 'username') {
    // 用户名格式验证
    if (!preg_match('/^[a-zA-Z0-9_\-]{3,30}$/', $value)) {
        echo json_encode(['ok' => false, 'msg' => '用户名只能包含字母、数字、下划线和横线，长度3-30']);
        exit;
    }
    $sql    = 'SELECT id FROM users WHERE username = ?';
    $params = [$value];
    if ($excludeId > 0) {
        $sql    .= ' AND id != ?';
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '用户名已被注册']);
    } else {
        echo json_encode(['ok' => true, 'msg' => '用户名可用']);
    }

} elseif ($type === 'nickname') {
    if (mb_strlen($value) > 50) {
        echo json_encode(['ok' => false, 'msg' => '昵称不超过50字']);
        exit;
    }
    $nickLower = mb_strtolower($value, 'UTF-8');
    $sql    = 'SELECT id FROM users WHERE nickname_lower = ?';
    $params = [$nickLower];
    if ($excludeId > 0) {
        $sql    .= ' AND id != ?';
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ($stmt->fetch()) {
        echo json_encode(['ok' => false, 'msg' => '该昵称已被使用，请换一个']);
    } else {
        echo json_encode(['ok' => true, 'msg' => '昵称可用']);
    }

} else {
    echo json_encode(['ok' => false, 'msg' => '参数错误']);
}
