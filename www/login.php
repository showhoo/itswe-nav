<?php
// SPDX-License-Identifier: MIT
// login.php — 登录 / 找回密码（注册在独立页 register.php）
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/net.php';
require_once __DIR__ . '/lib/i18n.php';

$pageName = site_name();
$regOpen = get_pref(0, 'reg_open', '0') === '1';
$msgKey = '';   // 错误文案键（i18n）

/** 登录限速：按「IP哈希+用户名」持久化计数，换浏览器无效；窗口 10 分钟 */
function throttle_fails(string $username): int {
    $key = hash('sha256', client_ip());
    db()->exec('DELETE FROM login_throttle WHERE last_fail < ' . (time() - 600));   // 顺带清理过期行
    $st = db()->prepare('SELECT fails FROM login_throttle WHERE ip_hash = ? AND username = ?');
    $st->execute([$key, $username]);
    return (int)($st->fetchColumn() ?: 0);
}
function throttle_fail(string $username): void {
    $key = hash('sha256', client_ip());
    db()->prepare('INSERT INTO login_throttle (ip_hash, username, fails, last_fail) VALUES (?,?,1,?)
                   ON CONFLICT(ip_hash, username) DO UPDATE SET fails = fails + 1, last_fail = excluded.last_fail')
        ->execute([$key, $username, time()]);
}
function throttle_clear(string $username): void {
    db()->prepare('DELETE FROM login_throttle WHERE ip_hash = ? AND username = ?')
        ->execute([hash('sha256', client_ip()), $username]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($act === 'login') {
        if (throttle_fails($username) >= 6) { $msgKey = 'err_throttle'; }
        elseif ($username === '' || $password === '') { $msgKey = 'err_enter_both'; }
        else {
            $st = db()->prepare('SELECT id, password_hash, status FROM users WHERE username = ?');
            $st->execute([$username]);
            $u = $st->fetch();
            if ($u && (int)$u['status'] === 1 && password_verify($password, $u['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['uid'] = (int)$u['id'];
                throttle_clear($username);
                header('Location: index.php'); exit;
            }
            throttle_fail($username);
            usleep(400000 + min(throttle_fails($username), 10) * 150000);
            $msgKey = 'err_wrong';
        }
    }
}
?><!DOCTYPE html>
<html lang="<?= i18n_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('tab_login')) ?> - <?= e($pageName) ?></title>
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
            <div class="auth-sub"><?= e(t('sub_login')) ?></div>
        </div>
    </div>
    <?php if ($msgKey !== ''): ?><div class="msg"><?= e(t($msgKey)) ?></div><?php endif; ?>
    <form method="post" id="f-login">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="act" value="login">
        <label><?= e(t('f_username')) ?> <input name="username" required autocomplete="username" autofocus></label>
        <label><?= e(t('f_password')) ?> <input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn primary block"><?= e(t('btn_login')) ?></button>
    </form>
    <div class="auth-links">
        <?php if ($regOpen): ?><a class="acc" href="register.php"><?= e(t('go_register')) ?></a><?php endif; ?>
        <a href="#" id="link-forgot"><?= e(t('forgot_pw')) ?></a>
        <a href="#" id="link-back-login" class="hidden"><?= e(t('back_login')) ?></a>
        <a href="terms.php" target="_blank" rel="noopener"><?= e(t('terms')) ?></a>
        <a href="index.php"><?= e(t('back_home')) ?></a>
    </div>
    <form method="post" id="f-forgot" class="hidden">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <div class="step-cap"><b>1</b><?= e(t('step_verify')) ?></div>
        <label><?= e(t('f_username')) ?> <input name="username" required autocomplete="username"></label>
        <label><?= e(t('f_email')) ?> <input type="email" name="email" required></label>
        <label><?= e(t('f_code')) ?>
            <span style="display:flex;gap:8px">
                <input name="code" required style="flex:1">
                <button type="button" class="btn small" id="btn-reset-code"><?= e(t('send_code')) ?></button>
            </span>
        </label>
        <div class="step-cap"><b>2</b><?= e(t('step_newpw')) ?></div>
        <label><?= e(t('f_new_pw')) ?> <input type="password" name="new" required minlength="6" autocomplete="new-password"></label>
        <button class="btn primary block"><?= e(t('btn_reset')) ?></button>
    </form>
</div>
<script>
// —— 忘记密码 ↔ 登录/注册 互斥切换 ——
(function () {
    const forgot = document.getElementById('f-forgot');
    const back = document.getElementById('link-back-login');
    const link = document.getElementById('link-forgot');
    if (!forgot || !link) return;
    function showForgot(show) {
        forgot.classList.toggle('hidden', !show);
        link.classList.toggle('hidden', show);
        back.classList.toggle('hidden', !show);
        ['f-login'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', show);
        });
        const tabs = document.querySelector('.tabs');
        if (tabs) tabs.style.display = show ? 'none' : '';
    }
    link.addEventListener('click', e => { e.preventDefault(); showForgot(true); });
    back.addEventListener('click', e => { e.preventDefault(); showForgot(false); });
})();
</script>
<script>
window.__I18N__ = <?= json_encode(array_intersect_key(i18n_dict()[i18n_lang()], array_flip([
    'err_email_invalid', 'send_code', 'pw_reset_ok', 'msg_failed', 'mail_sent',
])), JSON_UNESCAPED_UNICODE) ?>;
const t = k => (window.__I18N__ && window.__I18N__[k]) || k;
document.querySelectorAll('.tab').forEach(t2 => t2.onclick = () => {
    document.querySelectorAll('.tab').forEach(x => x.classList.toggle('active', x === t2));
    document.querySelectorAll('form').forEach(f => f.classList.add('hidden'));
    document.getElementById('f-' + t2.dataset.tab).classList.remove('hidden');
});
</script>
<script src="assets/dsbg.js?v=4"></script>
<script>
// —— 邮箱验证码发送（60s 冷却）与忘记密码 ——
(function () {
    const csrfOf = form => form.querySelector('input[name=csrf]').value;
    function bindSend(btnId, formId, purpose, emailSel) {
        const btn = document.getElementById(btnId);
        if (!btn) return;
        btn.addEventListener('click', async () => {
            const form = document.getElementById(formId);
            const email = form.querySelector('input[name=email]').value.trim();
            if (!email) { alert(t('err_email_invalid')); return; }
            const fd = new FormData();
            fd.append('csrf', csrfOf(form));
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
    bindSend('btn-reset-code', 'f-forgot', 'reset');
    // 忘记密码提交
    const ff = document.getElementById('f-forgot');
    ff.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(ff);
        fd.append('act', 'password.reset');
        try {
            const r = await fetch('api.php', { method: 'POST', body: fd });
            const j = await r.json();
            alert(j.ok ? t('pw_reset_ok') : (j.msg || t('msg_failed')));
        } catch (_) { alert(t('msg_failed')); }
    });
})();
</script>
</body>
</html>
