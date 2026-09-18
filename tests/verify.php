<?php
// verify.php — 全功能验证（需要已登录会话）
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/net.php';
require_once __DIR__ . '/lib/mail.php';
require_once __DIR__ . '/lib/favicon.php';
require_once __DIR__ . '/lib/credit.php';

$user = current_user();
if (!$user) { echo "NOT_LOGGED_IN\n"; exit; }
echo "user={$user['username']} role={$user['role']}\n";

// 页面 HTML
ob_start();
include __DIR__ . '/index.php';
$html = ob_get_clean();

$checks = [
    'iframe 按钮'     => strpos($html, 'data-iframe') !== false,
    '导出 CSV 链接'   => strpos($html, 'export.php?format=csv') !== false,
    '导出 HTML 链接'  => strpos($html, 'export.php?format=html') !== false,
    'favicon 本地代理' => strpos($html, 'favicon.php?h=') !== false,
    '长按提示'         => strpos($html, '长按卡片编辑') !== false,
    'description tooltip' => strpos($html, 'title="') !== false,
    '状态条容器'       => strpos($html, 'srv-status') !== false,
    '暗色模式 CSS'     => strpos(file_get_contents(__DIR__ . '/assets/style.css'), 'prefers-color-scheme') !== false,
    'SMTP 设置表单'    => strpos(file_get_contents(__DIR__ . '/admin.php'), 'f-smtp') !== false,
    '改密码弹窗'       => strpos($html, 'dlg-pw') !== false,
    '⚙ 设置按钮'      => strpos($html, 'btn-pw') !== false,
    '页脚 Powered by'  => strpos($html, 'Powered by') !== false,
    '中英文站点名'     => strpos($html, '思维简约导航') !== false || strpos($html, 'ITSWE-Nav') !== false,
];
$pass = 0; $fail = 0;
foreach ($checks as $name => $ok) {
    if ($ok) { $pass++; echo "PASS: $name\n"; }
    else { $fail++; echo "FAIL: $name\n"; }
}
echo "VERIFY: $pass pass, $fail fail\n";
