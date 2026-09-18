<?php
// SPDX-License-Identifier: MIT
// register.php — 独立注册页：用户名 + 密码 + 可选邮箱（找回密码用）+ 用户协议
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/net.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/mail.php';   // mail_verify 开启时的验证码校验

$pageName = site_name();
$regOpen = get_pref(0, 'reg_open', '0') === '1';
$mailVerify = get_pref(0, 'mail_verify', '0') === '1';
$msgKey = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $email = strtolower(trim($_POST['email'] ?? ''));
    $code = trim($_POST['code'] ?? '');

    if (!$regOpen) { $msgKey = 'err_reg_closed'; }
    elseif (!preg_match('/^[a-zA-Z0-9_]{2,32}$/', $username)) { $msgKey = 'err_username_rule'; }
    elseif (strlen($password) < 6) { $msgKey = 'err_pw_short'; }
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $msgKey = 'err_email_invalid'; }
    elseif (empty($_POST['agree'])) { $msgKey = 'err_agree'; }
    else {
        // 注意：以下校验不得并入上面的 elseif 链——链会吞并后续分支
        if ($email !== '') {
            // 邮箱全站唯一（找回密码按「用户名+邮箱」匹配，重复邮箱会造成混淆）
            $st = db()->prepare('SELECT COUNT(*) FROM users WHERE lower(email) = ?');
            $st->execute([$email]);
            if ((int)$st->fetchColumn() > 0) $msgKey = 'err_email_taken';
        }
        if ($msgKey === '' && $mailVerify) {
            if ($email === '') { $msgKey = 'err_email_invalid'; }
            elseif ($code === '') { $msgKey = 'err_code_wrong'; }
            elseif (($err = mail_check_code($email, 'register', $code)) !== null) { $msgKey = 'err_code_wrong'; }
        }
    }
    if ($msgKey === '') {
        $st = db()->prepare('SELECT COUNT(*) FROM users');
        $st->execute();
        $count = (int)$st->fetchColumn();
        $role = $count === 0 ? 'admin' : 'user';   // 第一个注册用户为管理员
        $st = db()->prepare('INSERT INTO users (username, password_hash, role, email) VALUES (?,?,?,?)');
        try {
            $st->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $email]);
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)db()->lastInsertId();
            header('Location: index.php'); exit;
        } catch (PDOException) {
            $msgKey = 'err_taken';
        }
    }
}
?><!DOCTYPE html>
<html lang="<?= i18n_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('tab_register')) ?> - <?= e($pageName) ?></title>
<link rel="stylesheet" href="assets/style.css?v=12">
<link rel="icon" type="image/png" href="assets/itswe-icon.png">
</head>
<body class="auth-body">
<div class="ds-bg" aria-hidden="true"><canvas></canvas></div>
<div class="auth-card">
    <div class="auth-head">
        <span class="brand-chip"><img src="assets/itswe-icon.png" alt=""></span>
        <div>
            <div class="auth-name"><?= e($pageName) ?></div>
            <div class="auth-sub"><?= e(t('sub_register')) ?></div>
        </div>
    </div>
    <?php if (!$regOpen): ?>
    <div class="msg"><?= e(t('reg_closed')) ?></div>
    <div class="auth-links"><a class="acc" href="login.php"><?= e(t('back_login')) ?></a><a href="index.php"><?= e(t('back_home')) ?></a></div>
    <?php else: ?>
    <?php if ($msgKey !== ''): ?><div class="msg"><?= e(t($msgKey)) ?></div><?php endif; ?>
    <form method="post" id="f-register">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <label><?= e(t('f_username')) ?> <input name="username" required autofocus
               pattern="[A-Za-z0-9_]{2,32}" title="2-32 位字母/数字/下划线"></label>
        <label><?= e(t('f_password')) ?> <input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
        <label><?= e($mailVerify ? t('f_email') : t('email_optional')) ?>
            <input type="email" name="email" <?= $mailVerify ? 'required' : '' ?> placeholder="<?= e(t('email_ph')) ?>">
        </label>
        <?php if ($mailVerify): ?>
        <label><?= e(t('f_code')) ?>
            <span style="display:flex;gap:8px">
                <input name="code" required style="flex:1">
                <button type="button" class="btn small" id="btn-send-code"><?= e(t('send_code')) ?></button>
            </span>
        </label>
        <?php endif; ?>
        <label class="agree-row">
            <input type="checkbox" name="agree" value="1"><?= e(t('agree_prefix')) ?>
            <a href="terms.php" target="_blank" rel="noopener"><?= e(t('terms')) ?></a>
        </label>
        <button class="btn primary block"><?= e(t('btn_register')) ?></button>
    </form>
    <div class="auth-links">
        <a href="terms.php" target="_blank" rel="noopener"><?= e(t('terms')) ?></a>
        <a class="acc" href="login.php"><?= e(t('back_login')) ?></a>
        <a href="index.php"><?= e(t('back_home')) ?></a>
    </div>
    <?php endif; ?>
</div>
<script>
window.__I18N__ = <?= json_encode(array_intersect_key(i18n_dict()[i18n_lang()], array_flip([
    'send_code', 'err_email_invalid', 'msg_failed', 'mail_sent',
])), JSON_UNESCAPED_UNICODE) ?>;
const t = k => (window.__I18N__ && window.__I18N__[k]) || k;
<?php if ($regOpen && $mailVerify): ?>
// —— 注册验证码发送（60s 冷却）——
(function () {
    function bindSend(btnId, formId, purpose) {
        const btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', async () => {
            const form = document.getElementById(formId);
            const email = form.querySelector('input[name=email]').value.trim();
            if (!email) { alert(t('err_email_invalid')); return; }
            const fd = new FormData();
            fd.append('csrf', form.querySelector('input[name=csrf]').value);
            fd.append('act', 'mail.send_code');
            fd.append('email', email);
            fd.append('purpose', purpose);
            try {
                const r = await fetch('api.php', { method: 'POST', body: fd });
                const j = await r.json();
                if (j.ok) { alert(j.msg || t('mail_sent')); startCountdown(btn); }
                else alert(j.msg || t('msg_failed'));
            } catch (_) { alert(t('msg_failed')); }
        });
    }
    function startCountdown(btn) {
        let wait = 60;
        btn.disabled = true;
        const iv = setInterval(() => { wait--; btn.textContent = wait + 's'; if (wait <= 0) { clearInterval(iv); btn.textContent = btn.dataset.label || t('send_code'); btn.disabled = false; } }, 1000);
        btn.textContent = '60s';
    }
    bindSend('btn-send-code', 'f-register', 'register');
})();
<?php endif; ?>
</script>
<script src="assets/dsbg.js?v=4"></script>
</body>
</html>
