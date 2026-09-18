<?php
// SPDX-License-Identifier: MIT
// gallery.php — 图标图库出口：文件本体在 docroot 之外（data/gallery/），白名单路径 + immutable 缓存
declare(strict_types=1);
require_once __DIR__ . '/lib/gallery.php';

if (isset($_GET['index'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300');
    $idx = gallery_index_path();
    if (is_file($idx)) readfile($idx); else echo '{}';
    exit;
}

$out = gallery_serve($_GET['set'] ?? '', $_GET['f'] ?? '');
if ($out === null) { http_response_code(404); exit; }
[$path, $mime] = $out;
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=2592000, immutable');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . (string)filesize($path));
if ($mime === 'image/svg+xml') header("Content-Security-Policy: default-src 'none'");
readfile($path);
