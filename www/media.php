<?php
// SPDX-License-Identifier: MIT
// media.php — 上传文件的读取出口（图标/壁纸）。文件本体在 docroot 之外，仅允许白名单文件名。
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';

$type = $_GET['type'] ?? '';
$f = $_GET['f'] ?? '';

// 文件名白名单：生成时固定为 w_/i_ 前缀 + 随机串 + 扩展名
if (!preg_match('/^[wi]_[A-Za-z0-9]{8,24}\.(jpe?g|png|webp|gif)$/', $f)) { http_response_code(404); exit; }

$dir = ['icon' => 'icons', 'wallpaper' => 'wallpapers'][$type] ?? '';
if ($dir === '') { http_response_code(404); exit; }

$path = dirname(__DIR__) . '/data/uploads/' . $dir . '/' . $f;
if (!is_file($path)) { http_response_code(404); exit; }

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][$ext];

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=2592000, immutable');
header('Content-Length: ' . (string)filesize($path));
readfile($path);
