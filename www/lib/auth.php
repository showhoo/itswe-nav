<?php
// SPDX-License-Identifier: MIT
// lib/auth.php — 会话 / CSRF / 权限
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 14,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https',
    ]);
    session_name('NAVSESS');
    session_start();
}

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    static $user = false;
    if ($user === false) {
        $st = db()->prepare('SELECT id, username, role, status FROM users WHERE id = ?');
        $st->execute([$_SESSION['uid']]);
        $user = $st->fetch() ?: null;
        if ($user && (int)$user['status'] !== 1) {  // 被禁用即踢下线
            session_destroy();
            $user = null;
        }
    }
    return $user;
}

function require_login(): array {
    $u = current_user();
    if (!$u) { header('Location: login.php'); exit; }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('需要管理员权限'); }
    return $u;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    $t = $_POST['csrf'] ?? '';
    if (!$t || !hash_equals($_SESSION['csrf'] ?? '', $t)) { http_response_code(419); exit(t('err_csrf')); }
}

function get_pref(int $uid, string $k, string $def = ''): string {
    $st = db()->prepare('SELECT v FROM prefs WHERE user_id = ? AND k = ?');
    $st->execute([$uid, $k]);
    $r = $st->fetchColumn();
    return $r === false ? $def : (string)$r;
}

function set_pref(int $uid, string $k, string $v): void {
    $st = db()->prepare('INSERT INTO prefs (user_id, k, v) VALUES (?,?,?) ON CONFLICT(user_id, k) DO UPDATE SET v = excluded.v');
    $st->execute([$uid, $k, $v]);
}
