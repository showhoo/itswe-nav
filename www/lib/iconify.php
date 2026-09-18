<?php
// SPDX-License-Identifier: MIT
// lib/iconify.php — Iconify 图标库接入（20 万+ 开源图标即选即用）
// 用法：卡片编辑弹窗「图标」字段填入 `iconify:mdi:github` 之类的标识，
// 系统自动转为 `https://api.iconify.design/mdi/github.svg` 图标 URL。
declare(strict_types=1);

/**
 * 检测 iconify 标识并转为图标 URL。
 * 标识格式：`{prefix}:{name}`（如 mdi:github、logos:chrome）
 * 返回 null = 不是 iconify 标识（走原有 URL 逻辑）
 */
function iconify_to_url(string $input): ?string {
    $input = trim($input);
    if (!preg_match('/^([a-z0-9-]+):([a-z0-9-]+)$/i', $input, $m)) return null;
    // 排除常见协议前缀（非图标库标识）
    if (in_array(strtolower($m[1]), ['http', 'https', 'ftp', 'mailto'], true)) return null;
    return 'https://api.iconify.design/' . rawurlencode($m[1]) . '/' . rawurlencode($m[2]) . '.svg';
}
