<?php
// SPDX-License-Identifier: MIT
// terms.php — 用户协议（全站统一入口：注册页 / 登录页 / 游客页脚均链接此页）
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/i18n.php';

$pageName = site_name();
$zh = i18n_lang() !== 'en';
?><!DOCTYPE html>
<html lang="<?= i18n_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($zh ? '用户协议' : 'Terms of Service') ?> - <?= e($pageName) ?></title>
<link rel="stylesheet" href="assets/style.css?v=12">
<link rel="icon" type="image/png" href="assets/itswe-icon.png">
</head>
<body class="auth-body">
<div class="ds-bg" aria-hidden="true"><canvas></canvas></div>
<div class="auth-card" style="max-width:720px;text-align:left">
    <h1><?= e($pageName) ?> · <?= e($zh ? '用户协议' : 'Terms of Service') ?></h1>
    <div style="font-size:13px;line-height:1.9;color:var(--ds-text)">
    <?php if ($zh): ?>
        <p><b>生效日期：2026-09-18</b></p>
        <p><b>一、服务说明</b><br>
        本站是一个导航面板服务，提供网址收藏、分组管理与个性化展示功能。服务按「现状」免费提供，我们不承诺服务不中断或功能不发生变化。</p>
        <p><b>二、账号与安全</b><br>
        你应妥善保管账号与密码，并对账号下的全部操作负责。站点内置管理员账号由系统在首次部署时创建，其管理行为由站点管理者自行负责。遇到未授权使用的情况，请及时联系站点管理员。</p>
        <p><b>三、用户内容与行为规范</b><br>
        你通过本站收藏、上传的网址、图标、文字等内容，由你自行承担责任。你承诺不利用本站存储、传播违反法律法规或侵犯他人合法权益的内容；否则站点管理员有权不经通知删除相关内容并停用相关账号。</p>
        <p><b>四、隐私</b><br>
        本站仅在提供功能所必需的范围内处理你的数据（账号信息、收藏数据、可选的找回密码邮箱），不对外提供或出售。你的收藏数据可通过面板内置的导出功能自行备份。</p>
        <p><b>五、免责声明</b><br>
        对于因不可抗力、第三方服务变更、你自己保管凭据不当等原因造成的数据丢失或服务中断，本站不承担责任。本站展示的第三方网站内容与本站无关。</p>
        <p><b>六、协议变更</b><br>
        本协议可能随功能调整而更新，更新后在本页公布即生效。继续使用本站即视为接受更新后的协议。</p>
    <?php else: ?>
        <p><b>Effective date: 2026-09-18</b></p>
        <p><b>1. About the service</b><br>
        This site is a navigation panel service offering bookmark management, grouping and personalized display. The service is provided free of charge, "as is", without any commitment to uninterrupted availability or unchanged functionality.</p>
        <p><b>2. Accounts &amp; security</b><br>
        You are responsible for safeguarding your credentials and for all actions taken under your account. On sites where the first registered account receives administrator rights, administration is the site owner's own responsibility. Please contact the site administrator about any unauthorized use.</p>
        <p><b>3. User content &amp; conduct</b><br>
        You are solely responsible for the URLs, icons, text and other content you store via this site. You agree not to store or distribute content that violates applicable laws or infringes others' rights; otherwise the site administrator may remove such content and disable the relevant account without notice.</p>
        <p><b>4. Privacy</b><br>
        This site processes only the data necessary to provide its features (account information, bookmarks, optional recovery email), and never sells or shares it externally. You can back up your bookmarks anytime with the built-in export.</p>
        <p><b>5. Disclaimer</b><br>
        The site is not liable for data loss or downtime caused by force majeure, third-party service changes, or mishandled credentials. Third-party websites linked from this site are unrelated to this site.</p>
        <p><b>6. Changes to these terms</b><br>
        These terms may be updated as features evolve; updates take effect once published on this page. Continued use of the site constitutes acceptance of the updated terms.</p>
    <?php endif; ?>
    </div>
    <p style="margin-top:14px;text-align:center"><a class="btn small ghost" href="javascript:history.back()">← <?= e($zh ? '返回' : 'Back') ?></a></p>
</div>
<script src="assets/dsbg.js?v=4"></script>
</body>
</html>
