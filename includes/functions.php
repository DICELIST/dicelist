<?php
/**
 * 公共函数库
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
    if (!empty($_SESSION['user_id'])) {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id, username, nickname, email, email_verified, is_admin, created_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }
    return null;
}

function requireLogin(): void {
    $user = getCurrentUser();
    if (!$user) {
        header('Location: /login.php');
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

function isLoggedIn(): bool {
    return getCurrentUser() !== null;
}

function isAdmin(): bool {
    $user = getCurrentUser();
    return $user && (bool)$user['is_admin'];
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
    for ($i = 1; $i <= $totalPages; $i++) {
        $active = $i === $page ? ' active' : '';
        $html .= '<a href="' . $baseUrl . '&page=' . $i . '" class="page-btn' . $active . '">' . $i . '</a>';
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
