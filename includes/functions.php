<?php
/**
 * 公共函数库 v3
 */

require_once __DIR__ . '/db.php';

// ====== Session 管理 ======
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function getCurrentUser(): ?array {
    initSession();
    if (empty($_SESSION['user_id'])) {
        // session 中无用户，尝试从 Remember Me cookie 恢复
        if (!empty($_COOKIE['remember_token'])) {
            $restored = tryRememberMeLogin();
            if (!$restored) return null;
        } else {
            return null;
        }
    }
    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT id, username, nickname, email, email_verified,
                is_admin, is_super, is_banned, ban_reason, created_at
         FROM users WHERE id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) return null;
    // 封禁账号强制退出
    if ($user['is_banned']) {
        session_destroy();
        clearRememberMeToken();
        return null;
    }
    return $user;
}

function requireLogin(): void {
    $user = getCurrentUser();
    if (!$user) {
        header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function requireAdmin(): void {
    $user = getCurrentUser();
    if (!$user || !$user['is_admin']) {
        header('Location: /index.php?msg=no_permission');
        exit;
    }
}

function requireSuperAdmin(): void {
    $user = getCurrentUser();
    if (!$user || !$user['is_super']) {
        header('Location: /admin/index.php?msg=no_permission');
        exit;
    }
}

function isLoggedIn(): bool {
    return getCurrentUser() !== null;
}

function isAdmin(): bool {
    $user = getCurrentUser();
    return $user && (bool)$user['is_admin'];
}

function isSuperAdmin(): bool {
    $user = getCurrentUser();
    return $user && (bool)$user['is_super'];
}

// ====== 网站设置 ======
function getSiteSetting(string $key, string $default = ''): string {
    static $settings = null;
    if ($settings === null) {
        $pdo = getDB();
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings[$key] ?? $default;
}

function setSiteSetting(string $key, string $value): void {
    $pdo = getDB();
    $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')
        ->execute([$key, $value]);
}

// ====== 下拉选项 ======
function getOptions(string $type): array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, value FROM options WHERE type = ? AND is_active = 1 ORDER BY sort_order ASC, id ASC');
    $stmt->execute([$type]);
    return $stmt->fetchAll();
}

// ====== 转义输出 ======
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ====== 生成分页HTML ======
function buildPagination(int $total, int $page, int $perPage, string $baseUrl): string {
    $totalPages = (int)ceil($total / $perPage);
    if ($totalPages <= 1) return '';

    $html = '<nav class="pagination">';
    // 上一页
    if ($page > 1) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($page-1) . '" class="page-btn">&lsaquo;</a>';
    }
    // 页码
    $start = max(1, $page - 3);
    $end   = min($totalPages, $page + 3);
    if ($start > 1) $html .= '<a href="' . $baseUrl . '&page=1" class="page-btn">1</a>' . ($start > 2 ? '<span class="page-btn page-ellipsis">…</span>' : '');
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $page ? ' active' : '';
        $html .= '<a href="' . $baseUrl . '&page=' . $i . '" class="page-btn' . $active . '">' . $i . '</a>';
    }
    if ($end < $totalPages) $html .= ($end < $totalPages - 1 ? '<span class="page-btn page-ellipsis">…</span>' : '') . '<a href="' . $baseUrl . '&page=' . $totalPages . '" class="page-btn">' . $totalPages . '</a>';
    // 下一页
    if ($page < $totalPages) {
        $html .= '<a href="' . $baseUrl . '&page=' . ($page+1) . '" class="page-btn">&rsaquo;</a>';
    }
    $html .= '</nav>';
    return $html;
}

// ====== 时间格式化 ======
function formatTime(string $datetime): string {
    $ts = strtotime($datetime);
    return date('Y-m-d H:i', $ts);
}

// ====== CSRF Token ======
function getCsrfToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    initSession();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF验证失败，请重新提交。');
    }
}

// ====== Flash Message ======
function setFlash(string $type, string $message): void {
    initSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    initSession();
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ====== 操作日志 ======
function logAdminAction(string $action, string $targetType = '', ?int $targetId = null, string $detail = ''): void {
    $user = getCurrentUser();
    if (!$user) return;
    $pdo = getDB();
    $ip = getClientIp();
    $pdo->prepare(
        'INSERT INTO admin_logs (admin_id, admin_name, action, target_type, target_id, detail, ip)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $user['id'],
        $user['username'],
        $action,
        $targetType,
        $targetId,
        $detail,
        $ip,
    ]);
}

// ====== 获取客户端 IP ======
function getClientIp(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

// ====== IP登录失败次数检查 ======
function checkLoginAttempts(): bool {
    $pdo     = getDB();
    $ip      = getClientIp();
    $maxAttempts = (int)getSiteSetting('login_max_attempts', '5');
    $today   = date('Y-m-d');
    $stmt    = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND DATE(created_at) = ?'
    );
    $stmt->execute([$ip, $today]);
    return (int)$stmt->fetchColumn() < $maxAttempts;
}

function recordLoginFailure(string $account): void {
    $pdo = getDB();
    $ip  = getClientIp();
    $pdo->prepare('INSERT INTO login_attempts (ip, account) VALUES (?, ?)')->execute([$ip, $account]);
}

// ====== 获取公告列表 ======
function getActiveAnnouncements(): array {
    $pdo = getDB();
    return $pdo->query(
        'SELECT id, title, content, type FROM announcements
         WHERE is_active=1 ORDER BY sort_order ASC, id DESC'
    )->fetchAll();
}

// ====== 获取友情链接 ======
function getFriendLinks(): array {
    $pdo = getDB();
    return $pdo->query(
        'SELECT id, name, url, logo FROM friend_links
         WHERE is_active=1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
}

// ====== 获取协议内容 ======
function getAgreement(string $key): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT title, content FROM agreements WHERE ag_key = ?');
    $stmt->execute([$key]);
    return $stmt->fetch() ?: ['title' => '', 'content' => ''];
}

// ====== 超级管理员设备指纹（用浏览器User-Agent+IP的哈希模拟，实际以绑定文件为准）======
function getSuperDeviceFingerprint(): string {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip = getClientIp();
    // 使用 UA + IP 组合生成指纹（简单实现，可替换为更精准方案）
    return hash('sha256', $ua . '|' . $ip);
}


// ====== 检查内容是否需要审核 ======
function isReviewRequired(): bool {
    return getSiteSetting('review_required', '1') === '1';
}

// ====== 保持登录（Remember Me）======
// 在 getCurrentUser() 里调用，如果 session 无效则尝试从 cookie 恢复
function tryRememberMeLogin(): ?array {
    $token = $_COOKIE['remember_token'] ?? '';
    if ($token === '') return null;

    $pdo  = getDB();
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        'SELECT rt.user_id, rt.expires_at, u.id, u.username, u.nickname,
                u.is_admin, u.is_super, u.is_banned, u.ban_reason
         FROM remember_tokens rt
         LEFT JOIN users u ON rt.user_id = u.id
         WHERE rt.token_hash = ? AND rt.expires_at > NOW()
         LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row || !$row['id']) {
        // 无效 token，清除 cookie
        setcookie('remember_token', '', time()-3600, '/', '', false, true);
        return null;
    }
    if ($row['is_banned']) {
        setcookie('remember_token', '', time()-3600, '/', '', false, true);
        return null;
    }

    // 恢复 session
    initSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $row['user_id'];

    // Token 滚动（每次使用都换新 token，防止重放）
    $newToken = bin2hex(random_bytes(32));
    $newHash  = hash('sha256', $newToken);
    $expires  = date('Y-m-d H:i:s', time() + 30 * 86400);
    $pdo->prepare('UPDATE remember_tokens SET token_hash=?, expires_at=? WHERE token_hash=?')
        ->execute([$newHash, $expires, $hash]);
    setcookie('remember_token', $newToken, time() + 30 * 86400, '/', '', false, true);

    return $row;
}

function setRememberMeToken(int $userId): void {
    $token = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 30 * 86400);
    $pdo = getDB();
    // 同一用户最多保留 5 个设备的 token，清掉多余的
    $pdo->prepare('DELETE FROM remember_tokens WHERE user_id=? AND id NOT IN (
        SELECT id FROM (SELECT id FROM remember_tokens WHERE user_id=? ORDER BY created_at DESC LIMIT 4) t
    )')->execute([$userId, $userId]);
    $pdo->prepare('INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)')
        ->execute([$userId, $hash, $expires]);
    setcookie('remember_token', $token, time() + 30 * 86400, '/', '', false, true);
}

function clearRememberMeToken(): void {
    $token = $_COOKIE['remember_token'] ?? '';
    if ($token !== '') {
        $hash = hash('sha256', $token);
        $pdo  = getDB();
        $pdo->prepare('DELETE FROM remember_tokens WHERE token_hash=?')->execute([$hash]);
        setcookie('remember_token', '', time()-3600, '/', '', false, true);
    }
}

