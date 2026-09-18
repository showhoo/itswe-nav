<?php
// SPDX-License-Identifier: MIT
// lib/favicon.php — 站点图标抓取与本地缓存
// 自动图标由服务端代为抓取（经 favicon.im）并缓存于 data/favicons/，
// 访客浏览器不直连第三方；成功缓存 30 天，失败负缓存 1 天防打爆。
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const FAVICON_TTL_OK = 2592000;    // 成功缓存 30 天
const FAVICON_TTL_FAIL = 86400;    // 失败标记 1 天
const FAVICON_MAX_BYTES = 153600;  // 单个图标上限 150KB

function favicon_dir(): string {
    return dirname(__DIR__) . '/data/favicons';
}

function favicon_ok_path(string $host): string {
    return favicon_dir() . '/' . md5($host) . '.img';
}

function favicon_fail_path(string $host): string {
    return favicon_dir() . '/' . md5($host) . '.fail';
}

/** 返回 ['data','mime']；无图标或失败缓存未过期时返回 null。
 *  $allowFetch=false（白名单外主机）只读缓存、绝不发起外联。 */
function favicon_cached(string $host, bool $allowFetch = true): ?array {
    $now = time();
    $ok = favicon_ok_path($host);
    $fail = favicon_fail_path($host);
    if (is_file($ok) && filemtime($ok) > $now - FAVICON_TTL_OK) {
        $data = (string)file_get_contents($ok);
        if ($data !== '') return ['data' => $data, 'mime' => favicon_mime($data)];
    }
    if (is_file($fail) && filemtime($fail) > $now - FAVICON_TTL_FAIL) return null;
    if (!$allowFetch) return null;
    $data = favicon_fetch($host);
    if (!is_dir(favicon_dir())) @mkdir(favicon_dir(), 0775, true);
    if ($data === null) {
        @touch($fail, $now);
        return null;
    }
    file_put_contents($ok, $data);
    @unlink($fail);
    return ['data' => $data, 'mime' => favicon_mime($data)];
}

function favicon_mime(string $data): string {
    $f = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)($f->buffer($data) ?: 'image/png');
    return str_contains($mime, 'svg') ? 'image/svg+xml' : $mime;
}

/**
 * 三级抓取链：① 直连目标站 /favicon.ico  ② 解析首页 <link rel=icon>  ③ favicon.im 兜底
 * 全部由服务端发起，访客浏览器不直连第三方。
 */
function favicon_fetch(string $host): ?string {
    // ① 直连目标站 /favicon.ico
    $d = favicon_fetch_url("https://{$host}/favicon.ico");
    if ($d !== null) return $d;

    // ② 抓首页解析 <link rel="icon">
    $d = favicon_from_homepage($host);
    if ($d !== null) return $d;

    // ③ favicon.im 兜底
    return favicon_fetch_url('https://favicon.im/' . rawurlencode($host));
}

/** 抓取指定 URL，返回图片二进制或 null */
function favicon_fetch_url(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT_MS => 4000,
        CURLOPT_CONNECTTIMEOUT_MS => 2000,
        CURLOPT_USERAGENT => 'ITSWE-Nav/1.0 (favicon proxy)',
    ]);
    $data = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($code !== 200 || !is_string($data) || $data === '' || strlen($data) > FAVICON_MAX_BYTES) return null;
    if (!str_starts_with($type, 'image/') && !str_contains($type, 'icon')) return null;
    return $data;   // svg 亦可：仅经 <img> 渲染 + 出口 CSP，脚本不会执行
}

/** 抓目标站首页解析 <link rel="icon"> href，转绝对 URL 后抓图标 */
function favicon_from_homepage(string $host): ?string {
    foreach (['https', 'http'] as $scheme) {
        $ch = curl_init("$scheme://{$host}/");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_TIMEOUT_MS => 4000,
            CURLOPT_CONNECTTIMEOUT_MS => 2000,
            CURLOPT_USERAGENT => 'ITSWE-Nav/1.0',
        ]);
        $html = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        if ($code !== 200 || !is_string($html) || strlen($html) > 512000) continue;
        // 解析 <link rel="*icon*"> 的 href（取第一个匹配）
        if (preg_match('/<link[^>]*rel=["\'][^"\']*icon[^"\']*["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m)
            || preg_match('/<link[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\'][^"\']*icon/i', $html, $m)) {
            $href = $m[1];
            // 相对路径转绝对
            if (!preg_match('#^https?://#i', $href)) {
                $p = parse_url($final);
                $base = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? $host);
                if (str_starts_with($href, '//')) $href = $base === '' ? $href : 'https:' . $href;
                elseif (!str_starts_with($href, '/')) $href = $base . '/' . $href;
                else $href = $base . $href;
            }
            return favicon_fetch_url($href);
        }
    }
    return null;
}

/** 该主机是否被任一卡片引用（外网或内网地址）；卡片数有限，全表解析可接受 */
function favicon_host_in_items(string $host): bool {
    foreach (db()->query('SELECT url, url_lan FROM items') as $r) {
        foreach ([(string)$r['url'], (string)$r['url_lan']] as $u) {
            if (strtolower((string)parse_url($u, PHP_URL_HOST)) === $host) return true;
        }
    }
    return false;
}

/** 清空图标缓存（管理操作） */
function favicon_cache_clear(): void {
    foreach (glob(favicon_dir() . '/*') ?: [] as $f) @unlink($f);
}
