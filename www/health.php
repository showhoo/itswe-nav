<?php
// SPDX-License-Identifier: MIT
// health.php — 容器健康检查：数据库可读写即健康
declare(strict_types=1);
require_once __DIR__ . '/lib/db.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

header('Content-Type: text/plain; charset=utf-8');

try {
    db()->exec('SELECT 1');
    $dir = dirname(__DIR__) . '/../data';
    if (is_dir($dir) && !is_writable($dir)) {
        http_response_code(500);
        echo 'data not writable';
        exit;
    }
    http_response_code(200);
    echo 'ok';
} catch (Throwable) {
    http_response_code(500);
    echo 'db error';
}
