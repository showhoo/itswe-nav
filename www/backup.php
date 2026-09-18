<?php
// SPDX-License-Identifier: MIT
// backup.php — 站点数据备份导出（GET）与恢复导入（POST），仅管理员
// 导出含密码哈希，务必妥善保管备份文件；导入为整库替换（事务化），完成后需重新登录
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/i18n.php';

$user = current_user();
if (!$user) { http_response_code(401); header('Content-Type: text/plain; charset=utf-8'); exit(t('err_not_logged')); }
if ($user['role'] !== 'admin') { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); exit(t('err_admin')); }

$pdo = db();

/** 各表可导入/导出的列白名单 */
function backup_columns(): array {
    return [
        'users'  => ['id', 'username', 'password_hash', 'email', 'role', 'status', 'created_at'],
        'groups' => ['id', 'user_id', 'name', 'sort'],
        'items'  => ['id', 'user_id', 'group_id', 'title', 'url', 'url_lan', 'icon', 'description', 'sort'],
        'prefs'  => ['user_id', 'k', 'v'],
    ];
}

$act = $_SERVER['REQUEST_METHOD'] === 'GET' ? ($_GET['act'] ?? '') : ($_POST['act'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $act === 'export') {
    header('Cache-Control: no-store');
    $dump = [
        'app' => 'itswe-nav',
        'version' => 2,
        'exported_at' => date('c'),
        // smtp_pass 不随备份导出（防网盘泄密）；导入时该键缺失即保留现值，与设置页「留空不改」对称
        'site_prefs' => array_values(array_filter(
            $pdo->query('SELECT k, v FROM prefs WHERE user_id = 0 ORDER BY k')->fetchAll(),
            static fn(array $r): bool => $r['k'] !== 'smtp_pass'
        )),
    ];
    foreach (backup_columns() as $table => $cols) {
        $order = $table === 'prefs' ? 'user_id, k' : 'id';   // prefs 复合主键无 id 列
        $dump[$table] = $pdo->query('SELECT ' . implode(', ', $cols) . " FROM $table ORDER BY $order")->fetchAll();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="itswe-nav-backup-' . date('Ymd-His') . '.json"');
    echo json_encode($dump, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $act === 'import') {
    header('Content-Type: application/json; charset=utf-8');
    csrf_check();   // 整库替换是最重写操作，与全站写操作同等 CSRF 约束
    if (!isset($_FILES['file']) || ($_FILES['file']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => t('err_upload')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $json = json_decode((string)file_get_contents($_FILES['file']['tmp_name']), true);
    $bad = function (string $m) { http_response_code(400); echo json_encode(['ok' => false, 'msg' => $m], JSON_UNESCAPED_UNICODE); exit; };
    if (!is_array($json) || (! in_array($json['app'] ?? '', ['itswe-nav', 'mininav'], true))) $bad(t('import_bad_file'));
    $cols = backup_columns();
    foreach (['users', 'groups', 'items', 'prefs'] as $tbl) {
        if (!isset($json[$tbl]) || !is_array($json[$tbl])) $bad(t('import_bad_file'));
        foreach ($json[$tbl] as $row) {
            if (!is_array($row) || array_diff(array_keys($row), $cols[$tbl])) $bad(t('import_bad_file'));
        }
    }
    // 用户与分组是其余数据的锚点，必须存在；且不得导入空账号表（防「下一个注册者接管管理员」被恶意文件利用）
    if (!count($json['users']) || !in_array('admin', array_column($json['users'], 'role'), true)) $bad(t('import_bad_file'));
    foreach ($json['users'] as $u) {
        if (!isset($u['id'], $u['username'], $u['password_hash'])) $bad(t('import_bad_file'));
    }
    foreach ($json['groups'] as $g) {
        if (!isset($g['id'], $g['user_id'], $g['name'])) $bad(t('import_bad_file'));
    }
    // 旧格式备份（v1.0.0）无 email 键：归一为空串，避免 NOT NULL 约束回滚
    foreach ($json['users'] as &$u) { $u['email'] = trim((string)($u['email'] ?? '')); }
    unset($u);
    try {
        $pdo->beginTransaction();
        foreach (['items', 'groups', 'login_throttle', 'prefs', 'users'] as $tbl) {
            $pdo->exec("DELETE FROM $tbl");
        }
        $pdo->exec('DELETE FROM mail_codes');
        $ins = static function (PDO $p, string $tbl, array $cols, array $rows): void {
            if (!$rows) return;
            $ph = implode(',', array_fill(0, count($cols), '?'));
            $st = $p->prepare("INSERT INTO $tbl (" . implode(',', $cols) . ") VALUES ($ph)");
            foreach ($rows as $row) {
                $st->execute(array_map(static fn($c) => $row[$c] ?? null, $cols));
            }
        };
        $ins($pdo, 'users', $cols['users'], $json['users']);
        $ins($pdo, 'groups', $cols['groups'], $json['groups']);
        $ins($pdo, 'items', $cols['items'], $json['items']);
        $ins($pdo, 'prefs', $cols['prefs'], $json['prefs']);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log('[itswe-nav] backup.import: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'msg' => t('msg_failed')], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => true, 'msg' => t('import_ok')], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
header('Content-Type: text/plain; charset=utf-8');
exit('bad request');
