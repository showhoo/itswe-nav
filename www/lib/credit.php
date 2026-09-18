<?php
// SPDX-License-Identifier: MIT
// lib/credit.php — 页脚项目署名（Powered by ITSWE-Nav）
//
// 署名值存于 prefs 表（user_id=0），管理界面刻意不提供任何读写入口；
// 若 DB 行被删除，get_pref 的默认值兜底，署名自动恢复；
// 若行被清空（v=''），页脚隐藏，管理后台会显示一条本地缺失提醒（无上报）。
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const CREDIT_NAME_DEFAULT = 'ITSWE-Nav 简约导航';
const CREDIT_URL_DEFAULT = 'https://www.itswe.com';
const CREDIT_ICON_DEFAULT = 'assets/itswe-icon.png';   // 图标本地化，随镜像分发

function site_credit_html(): string {
    $name = get_pref(0, 'credit_name', CREDIT_NAME_DEFAULT);
    if ($name === '') return '';
    $url = get_pref(0, 'credit_url', CREDIT_URL_DEFAULT);
    $icon = get_pref(0, 'credit_icon', CREDIT_ICON_DEFAULT);
    // Powered by + 图标；远程图标挂了回退本地
    return '<a class="powered" href="' . e($url) . '" target="_blank" rel="noopener" title="' . e($name) . '">Powered by<img src="' . e($icon) . '" alt="" onerror="this.remove()"></a>';
}

/** 署名是否被清空（供管理后台显示缺失提醒） */
function site_credit_missing(): bool {
    return get_pref(0, 'credit_name', CREDIT_NAME_DEFAULT) === '';
}
