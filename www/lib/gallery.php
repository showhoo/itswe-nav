<?php
// SPDX-License-Identifier: MIT
// lib/gallery.php — 本地图标图库（Dashboard Icons + Simple Icons，构建期落盘 data/gallery/）
// 卡片 icon 字段支持 `gallery:{set}/{name}.{ext}` 前缀，渲染时转成本地 gallery.php 出口；
// 运行时零第三方外联。SVG 出口必须带 CSP（见 gallery.php），防止内嵌脚本。
declare(strict_types=1);

require_once __DIR__ . '/iconify.php';

const GALLERY_SETS = ['dashboard', 'simple-icons'];

function gallery_dir(): string {
    return dirname(__DIR__, 2) . '/data/gallery';
}

function gallery_index_path(): string {
    return gallery_dir() . '/index.json';
}

/**
 * icon 字段存储值 → 渲染用 URL。
 * gallery:dashboard/synology.webp → gallery.php?set=dashboard&f=synology.webp
 * iconify:mdi:github（及裸 prefix:name）→ api.iconify.design URL
 * 其余（http(s) URL、media.php 路径）原样返回；空值返回 null。
 */
function icon_field_url(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') return null;
    if (preg_match('/^gallery:([a-z0-9-]+)\/([a-z0-9-]+\.(?:svg|webp|png))$/i', $raw, $m)
        && in_array(strtolower($m[1]), GALLERY_SETS, true)) {
        return 'gallery.php?set=' . rawurlencode(strtolower($m[1])) . '&f=' . rawurlencode(strtolower($m[2]));
    }
    if (($u = iconify_to_url($raw)) !== null) return $u;
    return $raw;
}

/** 白名单校验并返回 [绝对路径, MIME]；集合/文件名非法或文件不存在返回 null */
function gallery_serve(string $set, string $file): ?array {
    if (!in_array($set, GALLERY_SETS, true)) return null;
    if (!preg_match('/^[a-z0-9-]+\.(svg|webp|png)$/', $file)) return null;
    $path = gallery_dir() . '/' . $set . '/' . $file;
    if (!is_file($path)) return null;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = ['svg' => 'image/svg+xml', 'webp' => 'image/webp', 'png' => 'image/png'][$ext] ?? '';
    if ($mime === '') return null;
    return [$path, $mime];
}
