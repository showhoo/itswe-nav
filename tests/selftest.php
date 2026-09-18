<?php
// selftest.php — 自包含全功能验证页（部署后浏览器打开即看结果）
// 安全：验证完毕后自行删除本文件
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/net.php';
require_once __DIR__ . '/lib/mail.php';
require_once __DIR__ . '/lib/favicon.php';
require_once __DIR__ . '/lib/iconify.php';

$user = current_user();
$results = [];
$t = function(string $name, bool $ok, string $detail = '') use (&$results) {
    $results[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
};

// ===== 数据库 =====
$t('SQLite 连接', (function() { try { db()->query('SELECT 1'); return true; } catch (Throwable) { return false; } })());
$userCount = 0; $itemCount = 0; $groupCount = 0;
try {
    $userCount = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $itemCount = (int)db()->query('SELECT COUNT(*) FROM items')->fetchColumn();
    $groupCount = (int)db()->query('SELECT COUNT(*) FROM "groups"')->fetchColumn();
    $t('数据完整性', $userCount > 0, "users=$userCount items=$itemCount groups=$groupCount");
} catch (Throwable $e) {
    $t('数据完整性', false, $e->getMessage());
}

// ===== 迁移 =====
$t('DB version >= 3', (int)db()->query('PRAGMA user_version')->fetchColumn() >= 3);
$emailCol = false;
foreach (db()->query('PRAGMA table_info(users)') as $c) { if ($c['name'] === 'email') { $emailCol = true; break; } }
$t('users.email 字段', $emailCol);
$mailTable = (bool)db()->query("SELECT COUNT(*) FROM sqlite_master WHERE name='mail_codes'")->fetchColumn();
$t('mail_codes 表', $mailTable);

// ===== 站点偏好 =====
$siteName = get_pref(0, 'site_name', '');
$t('site_name 非空', $siteName !== '', $siteName);
$lang = i18n_lang();
$t('语言设置', in_array($lang, ['zh-CN', 'en'], true), $lang);
$srvOn = get_pref(0, 'srv_status', '1') === '1';
$t('状态条开关', true, $srvOn ? 'on' : 'off');

// ===== SMTP =====
$smtpCfg = smtp_config();
$t('SMTP host 配置', $smtpCfg['host'] !== '', $smtpCfg['host'] ?: '(空)');
$t('SMTP port 合法', $smtpCfg['port'] > 0 && $smtpCfg['port'] <= 65535, (string)$smtpCfg['port']);

// ===== favicon 代理 =====
$faviconDir = dirname(__DIR__) . '/data/favicons';
$t('favicon 缓存目录', is_dir($faviconDir), $faviconDir);

// ===== Docker =====
$t('docker.sock 存在', file_exists('/var/run/docker.sock') || is_file('/var/run/docker.sock'));

// ===== 用户 =====
$t('管理员存在', $userCount > 0 && $user && $user['role'] === 'admin', $user ? $user['username'] . ' (' . $user['role'] . ')' : '未登录');

// ===== 文件检查 =====
foreach (['index.php', 'login.php', 'admin.php', 'api.php', 'export.php', 'backup.php', 'favicon.php', 'media.php', 'health.php'] as $f) {
    $t("文件 $f", file_exists(__DIR__ . "/$f"));
}
foreach (['auth.php', 'db.php', 'i18n.php', 'mail.php', 'smtp.php', 'net.php', 'favicon.php', 'docker.php', 'sysinfo.php', 'credit.php'] as $f) {
    $t("lib/$f", file_exists(__DIR__ . "/lib/$f"));
}
foreach (['app.js', 'style.css', 'dsbg.js', 'itswe-icon.png'] as $f) {
    $t("assets/$f", file_exists(__DIR__ . "/assets/$f"));
}

// ===== 安全 =====
$t('display_errors 关闭', (bool)ini_get('display_errors') === false, 'display_errors=' . ini_get('display_errors'));

// ===== 输出 =====
header('Content-Type: text/html; charset=utf-8');
$pass = count(array_filter($results, fn($r) => $r['ok']));
$fail = count($results) - $pass;
?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ITSWE-Nav 自检</title><style>
body{font-family:system-ui,sans-serif;max-width:640px;margin:20px auto;padding:0 16px;background:#16181d;color:#e8eaed}
h1{font-size:1.3em} .pass{color:#4ade80}.fail{color:#f87171}
.r{padding:6px 0;border-bottom:1px solid rgba(255,255,255,.06);font-size:14px}
.r .d{color:#86909c;font-size:12px;margin-left:8px}
.sum{margin:16px 0;font-size:1.1em}
</style></head><body>
<h1>ITSWE-Nav 自检报告</h1>
<p class="sum"><span class="<?= $fail ? 'fail' : 'pass' ?>"><?= $pass ?> pass / <?= $fail ?> fail</span> · 共 <?= count($results) ?> 项</p>
<?php foreach ($results as $r): ?>
<div class="r"><span class="<?= $r['ok'] ? 'pass' : 'fail' ?>"><?= $r['ok'] ? '✓' : '✗' ?></span> <?= htmlspecialchars($r['name']) ?>
<?php if ($r['detail']): ?><span class="d"><?= htmlspecialchars($r['detail']) ?></span><?php endif; ?></div>
<?php endforeach; ?>
<p style="margin-top:20px;font-size:12px;color:#86909c">⚠ 请验证后删除本文件（selftest.php）</p>
</body></html>
