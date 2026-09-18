<?php
// SPDX-License-Identifier: MIT
// api.php — 全部 AJAX 动作（POST + JSON 返回）
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/sysinfo.php';
require_once __DIR__ . '/lib/docker.php';
require_once __DIR__ . '/lib/net.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/favicon.php';
require_once __DIR__ . '/lib/mail.php';
require_once __DIR__ . '/lib/iconify.php';
require_once __DIR__ . '/lib/smtp.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$pdo = db();
$uid = $user ? (int)$user['id'] : 0;   // 公开动作（验证码）允许未登录
$act = $_POST['act'] ?? '';

$PUBLIC_ACTS = ['mail.send_code', 'password.reset'];   // 未登录可用（CSRF 仍必须）
if (!$user && !in_array($act, $PUBLIC_ACTS, true)) {
    http_response_code(401); echo json_encode(['ok' => false, 'msg' => t('err_not_logged')]); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok' => false, 'msg' => t('err_method')]); exit; }
csrf_check();

// —— 邮件验证码动作：访客与登录用户均可请求 ——
if ($act === 'mail.send_code' || $act === 'password.reset') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if ($act === 'mail.send_code') {
        if (!smtp_configured()) out(false, ['msg' => t('err_mail_not_configured')]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, ['msg' => t('err_email_invalid')]);
        $purpose = ($_POST['purpose'] ?? '') === 'reset' ? 'reset' : 'register';
        if ($purpose === 'reset') {
            $st = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $st->execute([$email]);
            if ((int)$st->fetchColumn() === 0) out(false, ['msg' => t('err_email_not_bound')]);
        }
        if (($err = mail_send_code($email, $purpose)) !== null) out(false, ['msg' => $err]);
        out(true, ['msg' => t('mail_sent')]);
    }
    if ($act === 'password.reset') {
        if (!smtp_configured()) out(false, ['msg' => t('err_mail_not_configured')]);
        $code = trim($_POST['code'] ?? '');
        $newPw = $_POST['new'] ?? '';
        if (strlen($newPw) < 6) out(false, ['msg' => t('err_pw_min')]);
        if (($err = mail_check_code($email, 'reset', $code)) !== null) out(false, ['msg' => $err]);
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        $uid2 = $st->fetchColumn();
        if (!$uid2) out(false, ['msg' => t('err_code_wrong')]);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPw, PASSWORD_DEFAULT), $uid2]);
        out(true);
    }
}

function out(bool $ok, array $extra = []) {
    echo json_encode(['ok' => $ok] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

/** 校验分组归属 */
function own_group(int $uid, int $gid): bool {
    $st = db()->prepare('SELECT 1 FROM "groups" WHERE id = ? AND user_id = ?');
    $st->execute([$gid, $uid]);
    return (bool)$st->fetchColumn();
}

/** 规范化卡片 URL：补协议 + 校验，空串放行（内网地址可为空）
 *  外网地址默认补 https://；内网地址默认补 http://（内网服务基本无 TLS） */
function norm_url(string $raw, bool $allowEmpty = false, string $defaultScheme = 'https://'): string {
    $u = trim($raw);
    if ($u === '') {
        if ($allowEmpty) return '';
        out(false, ['msg' => t('err_url_empty')]);
    }
    if (!preg_match('#^https?://#i', $u)) $u = $defaultScheme . $u;
    if (!filter_var($u, FILTER_VALIDATE_URL)) out(false, ['msg' => t('err_url_invalid') . $u]);
    return $u;
}

/** 保存上传图片：MIME 实测 + 扩展名白名单 + 随机文件名，落盘 data/uploads/<dir>/ */
function save_upload(string $field, string $dir, int $maxBytes, string $prefix): string {
    $f = $_FILES[$field] ?? null;
    if (!is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) out(false, ['msg' => t('err_upload')]);
    if (($f['size'] ?? 0) <= 0 || $f['size'] > $maxBytes) out(false, ['msg' => t('err_upload_size')]);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? '';
    if ($ext === '') out(false, ['msg' => t('err_upload_type')]);
    $base = __DIR__ . '/../data/uploads/' . $dir;
    if (!is_dir($base) && !mkdir($base, 0775, true)) out(false, ['msg' => t('err_upload_dir')]);
    $name = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $base . '/' . $name)) out(false, ['msg' => t('err_upload_save')]);
    return $name;
}

switch ($act) {
    case 'group.add': {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') out(false, ['msg' => t('err_name_empty')]);
        $st = $pdo->prepare('SELECT COALESCE(MAX(sort),0)+1 FROM "groups" WHERE user_id = ?');
        $st->execute([$uid]);
        $sort = (int)$st->fetchColumn();
        $pdo->prepare('INSERT INTO "groups" (user_id, name, sort) VALUES (?,?,?)')->execute([$uid, $name, $sort]);
        out(true, ['id' => (int)$pdo->lastInsertId()]);
    }
    case 'group.rename': {
        $gid = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$gid || $name === '' || !own_group($uid, $gid)) out(false, ['msg' => t('err_param')]);
        $pdo->prepare('UPDATE "groups" SET name = ? WHERE id = ? AND user_id = ?')->execute([$name, $gid, $uid]);
        out(true);
    }
    case 'group.del': {
        $gid = (int)($_POST['id'] ?? 0);
        if (!$gid || !own_group($uid, $gid)) out(false, ['msg' => t('err_param')]);
        $pdo->prepare('DELETE FROM items WHERE group_id = ? AND user_id = ?')->execute([$gid, $uid]);
        $pdo->prepare('DELETE FROM "groups" WHERE id = ? AND user_id = ?')->execute([$gid, $uid]);
        out(true);
    }
    case 'group.sort': {   // ids: 逗号分隔的分组 id 顺序
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        $st = $pdo->prepare('UPDATE "groups" SET sort = ? WHERE id = ? AND user_id = ?');
        $pdo->beginTransaction();
        try {
            foreach (array_values($ids) as $i => $gid) $st->execute([$i + 1, $gid, $uid]);
            $pdo->commit();
        } catch (Throwable) { $pdo->rollBack(); out(false, ['msg' => t('err_sort_fail')]); }
        out(true);
    }
    case 'item.add': {
        $gid = (int)($_POST['group_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        if (!$gid || !own_group($uid, $gid) || $title === '') out(false, ['msg' => t('err_param')]);
        $url = norm_url($_POST['url'] ?? '');
        $urlLan = norm_url($_POST['url_lan'] ?? '', true, 'http://');
        $st = $pdo->prepare('SELECT COALESCE(MAX(sort),0)+1 FROM items WHERE group_id = ? AND user_id = ?');
        $st->execute([$gid, $uid]);
        $pdo->prepare('INSERT INTO items (user_id, group_id, title, url, url_lan, icon, description, sort) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$uid, $gid, $title, $url, $urlLan, trim($_POST['icon'] ?? ''), trim($_POST['description'] ?? ''), (int)$st->fetchColumn()]);
        out(true, ['id' => (int)$pdo->lastInsertId()]);
    }
    case 'item.update': {
        $id = (int)($_POST['id'] ?? 0);
        $gid = (int)($_POST['group_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        if (!$id || !$gid || !own_group($uid, $gid) || $title === '') out(false, ['msg' => t('err_param')]);
        $url = norm_url($_POST['url'] ?? '');
        $urlLan = norm_url($_POST['url_lan'] ?? '', true, 'http://');
        $pdo->prepare('UPDATE items SET group_id=?, title=?, url=?, url_lan=?, icon=?, description=? WHERE id=? AND user_id=?')
            ->execute([$gid, $title, $url, $urlLan, trim($_POST['icon'] ?? ''), trim($_POST['description'] ?? ''), $id, $uid]);
        out(true);
    }
    case 'item.del': {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM items WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        out(true);
    }
    case 'item.sort': {   // group_id + ids: 该分组内卡片顺序
        $gid = (int)($_POST['group_id'] ?? 0);
        if (!own_group($uid, $gid)) out(false, ['msg' => t('err_param')]);
        $ids = array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')));
        // 跨组移动语义：ids 为目标组最终顺序，凡属本用户的卡一律迁入该组（drag/touch 共用）
        $st = $pdo->prepare('UPDATE items SET sort = ?, group_id = ? WHERE id = ? AND user_id = ?');
        $pdo->beginTransaction();
        try {
            foreach (array_values($ids) as $i => $iid) $st->execute([$i + 1, $gid, $iid, $uid]);
            $pdo->commit();
        } catch (Throwable) { $pdo->rollBack(); out(false, ['msg' => t('err_sort_fail')]); }
        out(true);
    }
    case 'pref.set': {
        foreach (['wallpaper', 'engine', 'title', 'netmode'] as $k) {
            if (isset($_POST[$k])) {
                $v = trim((string)$_POST[$k]);
                if ($k === 'wallpaper' && $v !== '') {
                    if (preg_match('#^https?://#i', $v)) {
                        // 收紧：括号/引号可逃出 CSS url('...') 造成样式注入
                        if (!filter_var($v, FILTER_VALIDATE_URL) || preg_match("/[()'\"]/", $v)) out(false, ['msg' => t('err_url_chars')]);
                    } elseif (!preg_match('#^(preset:\d+$|upload:[wi]_[A-Za-z0-9]{12,24}\.(jpe?g|png|webp|gif)$)#i', $v)) {
                        out(false, ['msg' => t('err_wallpaper')]);
                    }
                }
                if ($k === 'engine' && !in_array($v, ['bing', 'baidu', 'google'], true)) $v = 'bing';
                if ($k === 'title' && mb_strlen($v) > 32) $v = '';   // 空=跟随站点名
                if ($k === 'netmode' && !in_array($v, ['auto', 'lan', 'wan'], true)) $v = 'auto';
                set_pref($uid, $k, $v);
            }
        }
        out(true);
    }
    case 'wallpaper.upload': {   // 自定义背景上传（≤8MB）
        $name = save_upload('file', 'wallpapers', 8 * 1048576, 'w');
        set_pref($uid, 'wallpaper', 'upload:' . $name);
        out(true, ['wallpaper' => 'upload:' . $name]);
    }
    case 'icon.upload': {        // 卡片图标上传（≤2MB）
        $name = save_upload('file', 'icons', 2 * 1048576, 'i');
        out(true, ['url' => 'media.php?type=icon&f=' . $name]);
    }
    case 'server.stats': {       // 服务器状态（默认登录即可见，站点设置可限管理员）
        if (get_pref(0, 'stats_admin_only', '0') === '1' && $user['role'] !== 'admin') {
            out(false, ['msg' => t('err_admin')]);
        }
        out(true, ['stats' => sys_stats()]);
    }
    case 'docker.list': {        // 容器列表（管理员）
        if ($user['role'] !== 'admin') out(false, ['msg' => t('err_admin')]);
        if (!docker_available()) out(false, ['msg' => t('err_sock')]);
        try { out(true, ['containers' => docker_list()]); }
        catch (RuntimeException $ex) { error_log('[itswe-nav] docker.list: ' . $ex->getMessage()); out(false, ['msg' => $ex->getMessage()]); }
    }
    case 'docker.action': {      // 容器启动/停止/重启（管理员）
        if ($user['role'] !== 'admin') out(false, ['msg' => t('err_admin')]);
        if (!docker_available()) out(false, ['msg' => t('err_sock')]);
        try {
            docker_action(trim((string)($_POST['id'] ?? '')), trim((string)($_POST['do'] ?? '')));
            out(true);
        } catch (RuntimeException $ex) { error_log('[itswe-nav] docker.action: ' . $ex->getMessage()); out(false, ['msg' => $ex->getMessage()]); }
    }
    case 'password.change': {    // 自助改密：需验证旧密码
        $oldPw = $_POST['old'] ?? '';
        $newPw = $_POST['new'] ?? '';
        if (strlen($newPw) < 6) out(false, ['msg' => t('err_pw_min')]);
        $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$uid]);
        $hash = (string)$st->fetchColumn();
        if ($hash === '' || !password_verify($oldPw, $hash)) out(false, ['msg' => t('err_old_wrong')]);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPw, PASSWORD_DEFAULT), $uid]);
        out(true);
    }
    case 'mail.test': {          // 发送测试邮件（管理员）
        if ($user['role'] !== 'admin') out(false, ['msg' => t('err_admin')]);
        $to = trim($_POST['to'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) out(false, ['msg' => t('err_email_invalid')]);
        if (($err = smtp_send($to, t('mail_test_sent'), '<p>' . e(t('mail_test_sent')) . '</p>')) !== null) {
            out(false, ['msg' => $err]);
        }
        out(true, ['msg' => t('mail_test_sent')]);
    }
    case 'cache.clear': {        // 清除图标缓存（管理员）
        if ($user['role'] !== 'admin') out(false, ['msg' => t('err_admin')]);
        require_once __DIR__ . '/lib/favicon.php';
        favicon_cache_clear();
        out(true);
    }
    case 'user.add': {   // 管理员
        if ($user['role'] !== 'admin') out(false, ['msg' => t('err_admin')]);
        $name = trim($_POST['username'] ?? '');
        $pw = $_POST['password'] ?? '';
        if (!preg_match('/^[a-zA-Z0-9_]{2,32}$/', $name) || strlen($pw) < 6) out(false, ['msg' => t('err_user_rule')]);
        $role = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $st = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?,?,?)');
        try { $st->execute([$name, password_hash($pw, PASSWORD_DEFAULT), $role]); }
        catch (PDOException) { out(false, ['msg' => t('err_user_exists')]); }
        out(true);
    }
    case 'user.resetpw': {
        if ($user['role'] !== 'admin') out(false, ['msg' => t('err_admin')]);
        $id = (int)($_POST['id'] ?? 0);
        $pw = $_POST['password'] ?? '';
        if ($id <= 0 || strlen($pw) < 6) out(false, ['msg' => t('err_pw_min')]);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
        out(true);
    }
    case 'user.status': {
        if ($user['role'] !== 'admin') out(false, ['msg' => t('err_admin')]);
        $id = (int)($_POST['id'] ?? 0);
        $on = ($_POST['on'] ?? '0') === '1';
        if ($id === $uid) out(false, ['msg' => t('err_cant_disable_self')]);
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$on ? 1 : 0, $id]);
        out(true);
    }
    case 'user.del': {
        if ($user['role'] !== 'admin') out(false, ['msg' => t('err_admin')]);
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $uid) out(false, ['msg' => t('err_cant_delete_self')]);
        $pdo->beginTransaction();
        try {
            foreach (['items', '"groups"', 'prefs', 'users'] as $tbl) {
                $pdo->prepare("DELETE FROM $tbl WHERE " . ($tbl === 'users' ? 'id' : 'user_id') . ' = ?')->execute([$id]);
            }
            $pdo->commit();
        } catch (Throwable) { $pdo->rollBack(); out(false, ['msg' => t('err_del_fail')]); }
        out(true);
    }
    case 'site.set': {
        if ($user['role'] !== 'admin') out(false, ['msg' => t('err_admin')]);
        if (isset($_POST['name'])) {
            $v = trim((string)$_POST['name']);
            if ($v !== '' && mb_strlen($v) > 32) out(false, ['msg' => t('err_site_name')]);
            set_pref(0, 'site_name', $v);
        }
        if (isset($_POST['name_en'])) {
            $vEn = trim((string)$_POST['name_en']);
            if ($vEn !== '' && mb_strlen($vEn) > 32) out(false, ['msg' => t('err_site_name')]);
            set_pref(0, 'site_name_en', $vEn);
        }
        if (isset($_POST['url'])) {
            $u = trim((string)$_POST['url']);
            if ($u !== '') $u = norm_url($u);
            set_pref(0, 'site_url', $u);
        }
        if (isset($_POST['lan_cidrs'])) {
            $cidrs = trim((string)$_POST['lan_cidrs']);
            if ($cidrs !== '' && !preg_match('/^[0-9a-fA-F:.]{2,45}\/\d{1,3}(\s*,\s*[0-9a-fA-F:.]{2,45}\/\d{1,3})*$/', $cidrs)) {
                out(false, ['msg' => t('err_cidrs')]);
            }
            set_pref(0, 'lan_cidrs', $cidrs);
        }
        if (isset($_POST['srv_status'])) {
            set_pref(0, 'srv_status', $_POST['srv_status'] === '1' ? '1' : '0');
        }
        if (isset($_POST['stats_admin_only'])) {
            set_pref(0, 'stats_admin_only', $_POST['stats_admin_only'] === '1' ? '1' : '0');
        }
        if (isset($_POST['lang'])) {
            $l = $_POST['lang'];
            set_pref(0, 'lang', $l === 'en' || $l === 'zh-CN' ? $l : 'auto');
        }
        // SMTP 配置：逐字段更新；密码支持占位符「保持不变」
        if (isset($_POST['smtp_host'])) set_pref(0, 'smtp_host', trim((string)$_POST['smtp_host']));
        if (isset($_POST['smtp_port'])) set_pref(0, 'smtp_port', (string)max(1, min(65535, (int)$_POST['smtp_port'])));
        if (isset($_POST['smtp_user'])) set_pref(0, 'smtp_user', trim((string)$_POST['smtp_user']));
        if (isset($_POST['smtp_pass']) && $_POST['smtp_pass'] !== '') set_pref(0, 'smtp_pass', (string)$_POST['smtp_pass']);
        if (isset($_POST['smtp_from'])) set_pref(0, 'smtp_from', trim((string)$_POST['smtp_from']));
        if (isset($_POST['smtp_secure']) && in_array($_POST['smtp_secure'], ['ssl', 'tls', 'none'], true)) set_pref(0, 'smtp_secure', $_POST['smtp_secure']);
        if (isset($_POST['mail_verify'])) {
            // 开启注册邮箱验证的前提：SMTP 已配置
            if ($_POST['mail_verify'] === '1' && !smtp_configured()) out(false, ['msg' => t('err_mail_not_configured')]);
            set_pref(0, 'mail_verify', $_POST['mail_verify'] === '1' ? '1' : '0');
        }
        out(true);
    }
    case 'reg.toggle': {
        if ($user['role'] !== 'admin') out(false, ['msg' => t('err_admin')]);
        set_pref(0, 'reg_open', ($_POST['open'] ?? '0') === '1' ? '1' : '0');
        out(true);
    }
    default:
        out(false, ['msg' => t('unknown_act')]);
}
