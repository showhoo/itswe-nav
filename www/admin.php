<?php
// SPDX-License-Identifier: MIT
// admin.php — 管理后台：用户管理 / 个人设置 / 站点设置 / 服务器（监控 + Docker）
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/net.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/credit.php';
$me = require_admin();
$uid = (int)$me['id'];

$users = db()->query('SELECT id, username, role, status, created_at FROM users ORDER BY id')->fetchAll();
$regOpen = get_pref(0, 'reg_open', '0') === '1';
$siteName = get_pref(0, 'site_name', '');
$siteUrl = get_pref(0, 'site_url', '');
$lanCidrs = get_pref(0, 'lan_cidrs', '');
$siteNameEn = get_pref(0, 'site_name_en', '');
$srvOn = get_pref(0, 'srv_status', '1') === '1';
$statsAdminOnly = get_pref(0, 'stats_admin_only', '0') === '1';
$langRaw = get_pref(0, 'lang', 'auto');   // auto = 跟随浏览器
$smtp = [
    'host'   => get_pref(0, 'smtp_host', ''),
    'port'   => get_pref(0, 'smtp_port', '465'),
    'user'   => get_pref(0, 'smtp_user', ''),
    'pass'   => get_pref(0, 'smtp_pass', ''),
    'from'   => get_pref(0, 'smtp_from', ''),
    'secure' => get_pref(0, 'smtp_secure', 'ssl'),
];
$mailVerify = get_pref(0, 'mail_verify', '0') === '1';

$pTitle = get_pref($uid, 'title', '') ?: site_name();
$pWall = get_pref($uid, 'wallpaper', 'preset:1');
$pEngine = get_pref($uid, 'engine', 'bing');
$pWallUrl = str_starts_with($pWall, 'preset:') || str_starts_with($pWall, 'upload:') ? '' : $pWall;
$pPreset = str_starts_with($pWall, 'preset:') ? (int)substr($pWall, 7) : 0;
$pWallIsUpload = str_starts_with($pWall, 'upload:');
$csrf = csrf_token();
?><!DOCTYPE html>
<html lang="<?= i18n_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('admin_panel')) ?></title>
<link rel="stylesheet" href="assets/style.css?v=12">
<link rel="icon" type="image/png" href="assets/itswe-icon.png">
</head>
<body class="admin-body">
<div class="ds-bg" aria-hidden="true"><canvas></canvas></div>
<div class="wrap">
    <header class="top">
        <div class="brand"><?= e(t('admin_panel')) ?></div>
        <nav class="who">
            <a class="btn ghost" href="index.php"><?= e(t('back_panel')) ?></a>
        </nav>
    </header>

    <?php if (site_credit_missing()): ?>
    <div class="msg" id="credit-warn"><?= e(t('credit_missing')) ?></div>
    <?php endif; ?>

    <div class="tabbar admin-tabs">
        <button type="button" class="tab active" data-tab="users"><?= e(t('tab_users')) ?></button>
        <button type="button" class="tab" data-tab="pref"><?= e(t('tab_pref')) ?></button>
        <button type="button" class="tab" data-tab="site"><?= e(t('tab_site')) ?></button>
        <button type="button" class="tab" data-tab="server"><?= e(t('server_tab')) ?></button>
    </div>

    <!-- 用户管理 -->
    <section class="admin-page" id="page-users">
        <h3><?= e(t('admin_users')) ?></h3>
        <div style="overflow-x:auto">
        <table class="tbl">
            <tr><th><?= e(t('th_id')) ?></th><th><?= e(t('th_username')) ?></th><th><?= e(t('th_role')) ?></th><th><?= e(t('th_status')) ?></th><th><?= e(t('th_created')) ?></th><th><?= e(t('th_ops')) ?></th></tr>
            <?php foreach ($users as $u): ?>
            <tr data-id="<?= (int)$u['id'] ?>">
                <td><?= (int)$u['id'] ?></td>
                <td><?= e($u['username']) ?><?= (int)$u['id'] === $uid ? e(t('me')) : '' ?></td>
                <td><?= $u['role'] === 'admin' ? e(t('role_admin')) : e(t('role_user')) ?></td>
                <td class="st"><?= (int)$u['status'] === 1 ? e(t('st_ok')) : e(t('st_disabled')) ?></td>
                <td><?= e($u['created_at']) ?></td>
                <td class="ops-row">
                    <?php if ((int)$u['id'] !== $uid): ?>
                    <button class="btn small" data-act="resetpw" data-id="<?= (int)$u['id'] ?>"><?= e(t('resetpw')) ?></button>
                    <button class="btn small" data-act="status" data-id="<?= (int)$u['id'] ?>" data-on="<?= (int)$u['status'] === 1 ? '0' : '1' ?>"><?= (int)$u['status'] === 1 ? e(t('disable')) : e(t('enable')) ?></button>
                    <button class="btn small danger" data-act="del" data-id="<?= (int)$u['id'] ?>"><?= e(t('del')) ?></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
        <h3><?= e(t('admin_new_user')) ?></h3>
        <form id="f-add" class="inline-form">
            <input name="username" placeholder="<?= e(t('f_username')) ?>" required>
            <input name="password" type="password" placeholder="<?= e(t('f_new_password')) ?>" required minlength="6">
            <select name="role"><option value="user"><?= e(t('role_user')) ?></option><option value="admin"><?= e(t('role_admin')) ?></option></select>
            <button class="btn primary"><?= e(t('create')) ?></button>
        </form>
    </section>

    <!-- 个人设置 -->
    <section class="admin-page hidden" id="page-pref">
        <h3><?= e(t('pref_title')) ?> <small style="font-weight:400;color:var(--ds-text-secondary)">（<?= e($me['username']) ?>）</small></h3>
        <form id="f-pref" class="settings-form">
            <label><?= e(t('panel_title_label')) ?> <input name="title" maxlength="32" value="<?= e($pTitle) ?>"></label>
            <label><?= e(t('wall_url')) ?> <input name="wallurl" id="wallurl" maxlength="500" value="<?= e($pWallUrl) ?>" placeholder="<?= e(t('wall_ph')) ?>"></label>
            <div class="upload-row">
                <input type="file" id="wall-file" accept=".jpg,.jpeg,.png,.webp,.gif" hidden>
                <button type="button" class="btn small" id="btn-wall-upload"><?= e(t('upload_wall')) ?></button>
                <span class="hint" id="wall-upload-state"><?= $pWallIsUpload ? e(t('wall_in_use')) : e(t('wall_hint')) ?></span>
            </div>
            <label><?= e(t('preset_label')) ?></label>
            <div class="swatches" id="swatches">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                <button type="button" class="swatch sw<?= $i ?><?= $pPreset === $i ? ' active' : '' ?>" data-preset="preset:<?= $i ?>" title="<?= e(t('preset_n')) ?> <?= $i ?>"></button>
                <?php endfor; ?>
            </div>
            <label><?= e(t('default_engine')) ?>
                <select name="engine">
                    <option value="bing"  <?= $pEngine === 'bing' ? 'selected' : '' ?>><?= e(t('engine_bing')) ?></option>
                    <option value="baidu" <?= $pEngine === 'baidu' ? 'selected' : '' ?>><?= e(t('engine_baidu')) ?></option>
                    <option value="google"<?= $pEngine === 'google'? 'selected' : '' ?>><?= e(t('engine_google')) ?></option>
                </select>
            </label>
            <div><button class="btn primary"><?= e(t('save_pref')) ?></button></div>
        </form>
        <h3 style="margin-top:24px"><?= e(t('pw_title')) ?></h3>
        <form id="f-pw-admin" class="settings-form">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="act" value="password.change">
            <label><?= e(t('pw_old')) ?> <input type="password" name="old" required autocomplete="current-password"></label>
            <label><?= e(t('pw_new')) ?> <input type="password" name="new" required minlength="6" autocomplete="new-password"></label>
            <label><?= e(t('pw_confirm')) ?> <input type="password" name="confirm" required minlength="6" autocomplete="new-password"></label>
            <div><button class="btn primary"><?= e(t('pw_title')) ?></button></div>
        </form>
    </section>

    <!-- 站点设置 -->
    <section class="admin-page hidden" id="page-site">
        <h3><?= e(t('site_title')) ?></h3>
        <form id="f-site" class="settings-form">
            <label><?= e(t('site_name')) ?> <input name="name" maxlength="32" value="<?= e($siteName) ?>" placeholder="<?= e(t('site_name_ph')) ?>"></label>
            <label><?= e(t('site_name_en_label')) ?> <input name="name_en" maxlength="32" value="<?= e($siteNameEn) ?>" placeholder="ITSWE-Nav"></label>
            <label><?= e(t('site_url')) ?> <input name="url" maxlength="200" value="<?= e($siteUrl) ?>" placeholder="<?= e(t('site_url_ph')) ?>"></label>
            <label><?= e(t('lan_cidrs_label')) ?> <input name="lan_cidrs" maxlength="200" value="<?= e($lanCidrs) ?>" placeholder="<?= e(t('lan_cidrs_ph')) ?>"></label>
            <div><button class="btn primary"><?= e(t('save_site')) ?></button></div>
        </form>
        <label class="switch-label" style="display:inline-flex;align-items:center;gap:8px;margin-top:16px"><input type="checkbox" id="reg-open" <?= $regOpen ? 'checked' : '' ?>> <?= e(t('reg_open_label')) ?></label>
        <label class="switch-label" style="display:inline-flex;align-items:center;gap:8px;margin-top:10px"><input type="checkbox" id="srv-status-on" <?= $srvOn ? 'checked' : '' ?>> <?= e(t('srv_status_on')) ?></label>
        <label class="switch-label" style="display:inline-flex;align-items:center;gap:8px;margin-top:10px"><input type="checkbox" id="mail-verify" <?= $mailVerify ? 'checked' : '' ?>> <?= e(t('mail_verify_label')) ?></label>

        <h3 style="margin-top:24px"><?= e(t('smtp_h')) ?></h3>
        <form id="f-smtp" class="settings-form">
            <label><?= e(t('smtp_host')) ?> <input name="smtp_host" value="<?= e($smtp['host']) ?>" placeholder="smtp.example.com"></label>
            <label><?= e(t('smtp_port')) ?> <input name="smtp_port" type="number" min="1" max="65535" value="<?= e($smtp['port']) ?>"></label>
            <label><?= e(t('smtp_secure')) ?>
                <select name="smtp_secure">
                    <option value="ssl"    <?= $smtp['secure'] === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                    <option value="tls"    <?= $smtp['secure'] === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                    <option value="none"   <?= $smtp['secure'] === 'none' ? 'selected' : '' ?>><?= e(t('lang_none')) ?></option>
                </select>
            </label>
            <label><?= e(t('smtp_user')) ?> <input name="smtp_user" id="smtp-user" value="<?= e($smtp['user']) ?>"></label>
            <label><?= e(t('smtp_pass')) ?> <input name="smtp_pass" type="password" value="" autocomplete="new-password" placeholder="<?= $smtp['pass'] !== '' ? e(t('smtp_pass_saved')) : '' ?>"></label>
            <label><?= e(t('smtp_from')) ?> <input name="smtp_from" value="<?= e($smtp['from']) ?>" placeholder="noreply@example.com"></label>
            <div style="display:flex;gap:10px">
                <button type="button" class="btn small" id="btn-smtp-test"><?= e(t('mail_test')) ?></button>
                <button type="submit" class="btn primary"><?= e(t('save_smtp')) ?></button>
            </div>
            <span class="hint" id="smtp-state"></span>
        </form>
        <label class="switch-label" style="display:inline-flex;align-items:center;gap:8px;margin-top:10px"><input type="checkbox" id="stats-admin-only" <?= $statsAdminOnly ? 'checked' : '' ?>> <?= e(t('stats_admin_label')) ?></label>
        <label class="switch-label" style="display:inline-flex;align-items:center;gap:8px;margin-top:10px"><?= e(t('lang_label')) ?>
            <select id="lang-select">
                <option value="auto" <?= $langRaw !== 'en' && $langRaw !== 'zh-CN' ? 'selected' : '' ?>><?= e(t('lang_auto')) ?></option>
                <option value="zh-CN" <?= $langRaw === 'zh-CN' ? 'selected' : '' ?>><?= e(t('lang_zh')) ?></option>
                <option value="en" <?= $langRaw === 'en' ? 'selected' : '' ?>><?= e(t('lang_en')) ?></option>
            </select>
        </label>
    </section>

    <!-- 服务器：状态监控 + Docker 管理 -->
    <section class="admin-page hidden" id="page-server">
        <h3><?= e(t('srv_status_h')) ?> <button type="button" class="btn small" id="btn-stats-refresh"><?= e(t('refresh')) ?></button></h3>
        <div class="stats-grid" id="stats-grid"><span class="hint"><?= e(t('loading')) ?></span></div>

        <h3 style="margin-top:28px"><?= e(t('docker_h')) ?></h3>
        <div id="docker-box"><span class="hint"><?= e(t('loading')) ?></span></div>

        <h3 style="margin-top:28px"><?= e(t('backup_h')) ?></h3>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <a class="btn small" href="backup.php?act=export"><?= e(t('export_backup')) ?></a>
            <input type="file" id="backup-file" accept=".json" hidden>
            <button type="button" class="btn small danger" id="btn-backup-import"><?= e(t('import_backup')) ?></button>
            <button type="button" class="btn small" id="btn-favicon-clear"><?= e(t('clear_favicons')) ?></button>
            <span class="hint" id="backup-state"></span>
        </div>
    </section>
</div>
<script>
const CSRF = '<?= $csrf ?>';
window.__I18N__ = <?= json_encode(i18n_dict()[i18n_lang()], JSON_UNESCAPED_UNICODE) ?>;
const t = k => (window.__I18N__ && window.__I18N__[k]) || k;
async function api(fd) {
    fd.append('csrf', CSRF);
    const r = await fetch('api.php', { method: 'POST', body: fd });
    return r.json();
}
function fmtB(b) {
    if (b === null || b === undefined || isNaN(b)) return '-';
    const u = ['B','KB','MB','GB','TB']; let i = 0; let n = Number(b);
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return (i === 0 ? n : n.toFixed(1)) + ' ' + u[i];
}
// —— 支持 admin.php?tab=server 直达指定标签 ——
const wantTab = new URLSearchParams(location.search).get('tab');
if (['users', 'pref', 'site', 'server'].includes(wantTab)) document.querySelector(`.admin-tabs .tab[data-tab="${wantTab}"]`)?.click();
// —— 标签页切换 ——
document.querySelectorAll('.admin-tabs .tab').forEach(t => t.onclick = () => {
    document.querySelectorAll('.admin-tabs .tab').forEach(x => x.classList.toggle('active', x === t));
    document.querySelectorAll('.admin-page').forEach(p => p.classList.toggle('hidden', p.id !== 'page-' + t.dataset.tab));
    if (t.dataset.tab === 'server') { loadStats(); loadDocker(); }
});
// —— 用户管理 ——
document.querySelectorAll('[data-act]').forEach(b => b.onclick = async () => {
    const act = b.dataset.act, id = b.dataset.id;
    const fd = new FormData();
    fd.append('act', act === 'resetpw' ? 'user.resetpw' : act === 'status' ? 'user.status' : 'user.del');
    fd.append('id', id);
    if (act === 'resetpw') {
        const pw = prompt(t('prompt_new_pw'));
        if (!pw || pw.length < 6) return;
        fd.append('password', pw);
    }
    if (act === 'status') fd.append('on', b.dataset.on);
    if (act === 'del' && !confirm('确定删除该用户及其全部数据？')) return;
    const j = await api(fd);
    if (j.ok) location.reload();
    else alert(j.msg || t('msg_failed'))
});
document.getElementById('f-add').onsubmit = async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('act', 'user.add');
    const j = await api(fd);
    if (j.ok) location.reload();
    else alert(j.msg || t('msg_failed'))
};
// —— 个人设置 ——
let preset = <?= $pPreset ? json_encode('preset:' . $pPreset) : "''" ?>;
document.querySelectorAll('#swatches .swatch').forEach(b => b.onclick = () => {
    document.querySelectorAll('#swatches .swatch').forEach(x => x.classList.toggle('active', x === b));
    preset = b.dataset.preset;
    document.getElementById('wall-upload-state').textContent = t('preset_chosen') + b.dataset.preset.slice(7);
});
// 壁纸上传：成功后直接写入个人壁纸
document.getElementById('btn-wall-upload').onclick = () => document.getElementById('wall-file').click();
document.getElementById('wall-file').addEventListener('change', async () => {
    const f = document.getElementById('wall-file').files[0];
    if (!f) return;
    const st = document.getElementById('wall-upload-state');
    st.textContent = '上传中…';
    const fd = new FormData();
    fd.append('act', 'wallpaper.upload');
    fd.append('file', f);
    try {
        const j = await api(fd);
        if (j.ok) { st.textContent = '已上传并应用 ✓'; document.getElementById('wallurl').value = ''; }
        else st.textContent = j.msg || t('upload_fail')
    } catch (_) { st.textContent = t('upload_fail'); }
});
document.getElementById('f-pref').onsubmit = async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('act', 'pref.set');
    // URL 输入为空且选择了预设时用预设；刚上传的壁纸已在服务端生效，URL 为空且无预设则不动壁纸
    const wallurl = document.getElementById('wallurl').value.trim();
    if (wallurl) fd.set('wallpaper', wallurl);
    else if (preset) fd.set('wallpaper', preset);
    const j = await api(fd);
    alert(j.ok ? t('msg_saved') : (j.msg || t('msg_failed')))
};
// —— 站点设置 ——
document.getElementById('f-site').onsubmit = async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('act', 'site.set');
    const j = await api(fd);
    alert(j.ok ? t('msg_saved') : (j.msg || t('msg_failed')))
};
document.getElementById('reg-open').onchange = async e => {
    const fd = new FormData();
    fd.append('act', 'reg.toggle');
    fd.append('open', e.target.checked ? '1' : '0');
    await api(fd);
};
document.getElementById('srv-status-on').onchange = async e => {
    const fd = new FormData();
    fd.append('act', 'site.set');
    fd.append('srv_status', e.target.checked ? '1' : '0');
    await api(fd);
};
document.getElementById('stats-admin-only').onchange = async e => {
    const fd = new FormData();
    fd.append('act', 'site.set');
    fd.append('stats_admin_only', e.target.checked ? '1' : '0');
    await api(fd);
};
document.getElementById('lang-select').onchange = async e => {
    const fd = new FormData();
    fd.append('act', 'site.set');
    fd.append('lang', e.target.value);
    await api(fd);
    location.reload();
};
// —— SMTP 配置：保存 / 测试 ——
document.getElementById('f-smtp').onsubmit = async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('act', 'site.set');
    const j = await api(fd);
    alert(j.ok ? t('msg_saved') : (j.msg || t('msg_failed')));
};
document.getElementById('btn-smtp-test').onclick = async () => {
    // 先保存当前表单，再发测试邮件到 SMTP 账号
    const fdSave = new FormData(document.getElementById('f-smtp'));
    fdSave.append('act', 'site.set');
    await api(fdSave);
    const to = document.getElementById('smtp-user').value.trim();
    if (!to) { alert(t('err_email_invalid')); return; }
    const st = document.getElementById('smtp-state');
    st.textContent = t('uploading');
    const fd = new FormData();
    fd.append('csrf', CSRF); fd.append('act', 'mail.test'); fd.append('to', to);
    try {
        const r = await fetch('api.php', { method: 'POST', body: fd });
        const j = await r.json();
        st.textContent = j.ok ? t('mail_test_sent') : (j.msg || t('msg_failed'));
    } catch (_) { st.textContent = t('msg_failed'); }
};
// 注册邮箱验证开关（即时生效；开启前提=SMTP 已配置）
document.getElementById('mail-verify').onchange = async e => {
    const fd = new FormData();
    fd.append('csrf', CSRF); fd.append('act', 'site.set'); fd.append('mail_verify', e.target.checked ? '1' : '0');
    const j = await api(fd);
    if (j.ok) { document.getElementById('mail-verify').checked = e.target.checked; }
    else { e.target.checked = !e.target.checked; alert(j.msg || t('msg_failed')); }
};
document.getElementById('f-pw-admin').onsubmit = async e => {
    e.preventDefault();
    const f = e.target;
    if (f.new.value !== f.confirm.value) { alert(t('err_pw_mismatch')); return; }
    const j = await api(new FormData(f));
    alert(j.ok ? t('pw_changed') : (j.msg || t('msg_failed')));
    if (j.ok) f.reset();
};
// —— 服务器状态 ——
async function loadStats() {
    const box = document.getElementById('stats-grid');
    const fd = new FormData(); fd.append('act', 'server.stats');
    try {
        const j = await api(fd);
        if (!j.ok) { box.textContent = j.msg || t('fetch_fail'); return; }
        const s = j.stats;
        const card = (label, val, sub) => `<div class="stat-card"><div class="stat-val">${val}</div><div class="stat-label">${label}</div>${sub ? `<div class="stat-sub">${sub}</div>` : ''}</div>`;
        box.innerHTML =
            card(t('stat_cpu'), s.cpu_pct === null ? '-' : s.cpu_pct + '%', t('load') + ' ' + (s.load || []).map(x => Number(x).toFixed(2)).join(' / ')) +
            card(t('stat_mem'), s.mem_pct === null ? '-' : s.mem_pct + '%', fmtB(s.mem_used) + ' / ' + fmtB(s.mem_total)) +
            card(t('stat_disk'), s.disk_pct === null ? '-' : s.disk_pct + '%', t('disk_free') + fmtB(s.disk_free) + ' / ' + fmtB(s.disk_total)) +
            card(t('stat_rx'), fmtB(s.net_rx_bps) + '/s', '↑ ' + fmtB(s.net_tx_bps) + '/s') +
            card(t('stat_uptime'), s.uptime_s >= 86400 ? Math.floor(s.uptime_s / 86400) + t('days') : Math.floor(s.uptime_s / 3600) + t('hours'), t('sys_uptime'));
    } catch (_) { box.textContent = t('fetch_fail'); }
}
document.getElementById('btn-stats-refresh').onclick = loadStats;
// —— Docker 容器管理 ——
async function loadDocker() {
    const box = document.getElementById('docker-box');
    const fd = new FormData(); fd.append('act', 'docker.list');
    try {
        const j = await api(fd);
        if (!j.ok) { box.innerHTML = '<span class="hint">' + (j.msg || t('fetch_fail')) + '</span>'; return; }
        if (!j.containers.length) { box.innerHTML = '<span class="hint">' + t('no_containers') + '</span>'; return; }
        const stMap = { running: [t('st_running'), 'ok'], exited: [t('st_exited'), 'off'], paused: [t('st_paused'), 'warn'], created: [t('st_created'), 'off'], restarting: [t('st_restarting'), 'warn'], dead: [t('st_dead'), 'off'] };
        let html = '<table class="tbl"><tr><th>' + t('th_name') + '</th><th>' + t('th_image') + '</th><th>' + t('th_status') + '</th><th>' + t('th_ports') + '</th><th>' + t('th_ops') + '</th></tr>';
        for (const c of j.containers) {
            const [stName, stCls] = stMap[c.state] || [c.state, 'off'];
            html += `<tr>
                <td><b>${c.name}</b></td>
                <td class="docker-img">${c.image}</td>
                <td><span class="docker-st ${stCls}">${stName}</span> <span class="hint">${c.status}</span></td>
                <td class="hint">${c.ports || '-'}</td>
                <td class="ops-row">
                    ${c.state === 'running'
                        ? `<button class="btn small" data-do="stop" data-id="${c.id}">${t('do_stop')}</button>`
                        : `<button class="btn small primary" data-do="start" data-id="${c.id}">${t('do_start')}</button>`}
                    <button class="btn small" data-do="restart" data-id="${c.id}">${t('do_restart')}</button>
                </td>
            </tr>`;
        }
        box.innerHTML = html + '</table>';
        box.querySelectorAll('[data-do]').forEach(b => b.onclick = async () => {
            if (!confirm(`${{ start: t('do_start'), stop: t('do_stop'), restart: t('do_restart') }[b.dataset.do]}?`)) return;
            const fd = new FormData();
            fd.append('act', 'docker.action');
            fd.append('id', b.dataset.id);
            fd.append('do', b.dataset.do);
            const r = await api(fd);
            if (!r.ok) alert(r.msg || t('msg_failed'));
            loadDocker();
        });
    } catch (_) { box.innerHTML = '<span class="hint">' + t('fetch_fail') + '</span>'; }
}
// —— 备份导出/导入 与 图标缓存 ——
document.getElementById('btn-backup-import').onclick = () => document.getElementById('backup-file').click();
document.getElementById('backup-file').addEventListener('change', async () => {
    const f = document.getElementById('backup-file').files[0];
    if (!f) return;
    if (!confirm(t('confirm_import'))) { document.getElementById('backup-file').value = ''; return; }
    const st = document.getElementById('backup-state');
    st.textContent = t('uploading');
    const fd = new FormData();
    fd.append('csrf', CSRF); fd.append('act', 'import'); fd.append('file', f);
    try {
        const r = await fetch('backup.php', { method: 'POST', body: fd });
        const j = await r.json();
        if (j.ok) { st.textContent = t('import_ok'); setTimeout(() => { location.href = 'logout.php'; }, 1200); }
        else { st.textContent = ''; alert(t('import_fail') + (j.msg ? ': ' + j.msg : '')); }
    } catch (_) { st.textContent = t('msg_failed'); }
});
document.getElementById('btn-favicon-clear').onclick = async () => {
    const fd = new FormData();
    fd.append('csrf', CSRF); fd.append('act', 'cache.clear');
    const j = await api(fd);
    document.getElementById('backup-state').textContent = j.ok ? t('favicons_cleared') : (j.msg || t('msg_failed'));
};
</script>
<script src="assets/dsbg.js?v=1"></script>
</body>
</html>
