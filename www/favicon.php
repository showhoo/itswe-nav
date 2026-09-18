<?php
// SPDX-License-Identifier: MIT
// favicon.php — 站点图标本地代理出口：?h=<host>
// 服务端缓存命中直接回源文件，未命中由服务端抓取（访客浏览器不直连第三方）
declare(strict_types=1);

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/seed.php';
require_once __DIR__ . '/lib/favicon.php';

$host = strtolower(trim($_GET['h'] ?? ''));
// 主机名白名单：杜绝路径/协议注入。注意：主机本身不限内外网（内网卡图标属正常需求），
// 抓取链对公网 host 的重定向仍可触达内网但响应被约束为图片≤150KB（有限探测面，见审计 R1-4/R10）
if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $host) || !str_contains($host, '.')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('not found');
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
// 抓取白名单 = 种子站点域名 ∪ 任一卡片引用的域名；白名单外只回缓存、绝不发起外联
// （收敢单请求 6-12s 的上游阻塞被滥用为 worker 占用/内网探测的攻击面，见审计 R10-3/R10-13）
$allowFetch = in_array($host, bookmark_seed_hosts(), true) || favicon_host_in_items($host);
$res = favicon_cached($host, $allowFetch);
if ($res === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('no icon');
}
header('Content-Type: ' . $res['mime']);
header('Cache-Control: public, max-age=604800');
if ($res['mime'] === 'image/svg+xml') {
    // 图标仅经 <img> 渲染（脚本不执行）；直开 URL 也以 CSP 兜底禁脚本
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
}   // 浏览器 7 天；服务端另有 30 天磁盘缓存
header('Content-Length: ' . (string)strlen($res['data']));
echo $res['data'];
