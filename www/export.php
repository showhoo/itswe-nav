<?php
// SPDX-License-Identifier: MIT
// export.php — 用户级书签数据导出（CSV / Netscape HTML），仅登录用户可导自己的数据
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/i18n.php';

$user = current_user();
if (!$user) { http_response_code(401); exit; }
$uid = (int)$user['id'];
$pdo = db();

$format = $_GET['format'] ?? 'csv';
$groups = $pdo->prepare('SELECT id, name FROM "groups" WHERE user_id = ? ORDER BY sort, id');
$groups->execute([$uid]);
$groups = $groups->fetchAll();
$items = $pdo->prepare('SELECT group_id, title, url, description FROM items WHERE user_id = ? ORDER BY sort, id');
$items->execute([$uid]);
$items = $items->fetchAll();
$gmap = [];
foreach ($groups as $g) $gmap[(int)$g['id']] = $g['name'];

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="itswe-nav-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF";   // UTF-8 BOM（Excel 中文兼容）
    echo '"分组","名称","网址","备注"', "\r\n";
    foreach ($items as $it) {
        $g = $gmap[(int)$it['group_id']] ?? '';
        $fields = [$g, $it['title'], $it['url'], $it['description']];
        // 公式注入中和：= + - @ 制表符开头的单元格前加单引号（Excel/WPS/Sheets 通用惯例）
        $fields = array_map(static fn($f) => preg_match('/^[=+\-@	
]/', $f) ? "'" . $f : $f, $fields);
        echo implode(',', array_map(fn($f) => '"' . str_replace('"', '""', $f) . '"', $fields)), "\r\n";
    }
    exit;
}

if ($format === 'html') {
    // Netscape Bookmark Format（浏览器通用导入格式）
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="itswe-nav-' . date('Ymd') . '.html"');
    echo '<!DOCTYPE NETSCAPE-Bookmark-file-1>', "\n";
    echo '<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">', "\n";
    echo '<TITLE>ITSWE-Nav Bookmarks</TITLE>', "\n";
    echo '<H1>ITSWE-Nav Bookmarks</H1>', "\n";
    echo '<DL><p>', "\n";
    $currentGroup = null;
    foreach ($items as $it) {
        $g = $gmap[(int)$it['group_id']] ?? '';
        if ($g !== $currentGroup) {
            if ($currentGroup !== null) echo '</DL><p>', "\n";
            echo "<DT><H3>", htmlspecialchars($g, ENT_QUOTES), "</H3>\n";
            echo "<DL><p>\n";
            $currentGroup = $g;
        }
        echo '<DT><A HREF="', htmlspecialchars($it['url'], ENT_QUOTES), '">', htmlspecialchars($it['title'], ENT_QUOTES), '</A>', "\n";
        if ($it['description'] !== '') echo '<DD>', htmlspecialchars($it['description'], ENT_QUOTES), "\n";
    }
    if ($currentGroup !== null) echo '</DL><p>', "\n";
    echo '</DL><p>', "\n";
    exit;
}

http_response_code(400);
header('Content-Type: text/plain; charset=utf-8');
exit('bad format');
