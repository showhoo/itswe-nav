<?php
// SPDX-License-Identifier: MIT
// index.php — 主面板 / 游客首页
declare(strict_types=1);
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/net.php';
require_once __DIR__ . '/lib/credit.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/iconify.php';
require_once __DIR__ . '/lib/gallery.php';
$user = current_user();

// —— 游客首页：无需登录，提供搜索与常用站点/工具导航 ——
if (!$user) {
    $siteTitle = site_name();
    $siteName = site_name();
    $siteUrl = get_pref(0, 'site_url', '');
    $regOpen = get_pref(0, 'reg_open', '0') === '1';
    require_once __DIR__ . '/lib/seed.php';
    $guestSections = bookmark_seed()[i18n_lang() === 'en' ? 'en' : 'zh'];
    ?><!DOCTYPE html>
    <html lang="<?= i18n_lang() ?>">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(i18n_lang() === 'en'
        ? 'ITSWE-Nav is a lightweight self-hosted start page (homepage dashboard): grouped bookmarks with LAN/WAN dual addresses, server monitoring and Docker management. Single-container PHP + SQLite.'
        : 'ITSWE-Nav 简约导航——轻量级自托管导航页：分组书签、内外网双地址切换、服务器监控、Docker 管理。单容器 PHP + SQLite 部署。') ?>">
    <title><?= e($siteTitle) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=12">
<link rel="icon" type="image/png" href="assets/itswe-icon.png">
    <script>var ICON_FALLBACK = "assets/itswe-icon.png";if (!window.HTMLDialogElement) document.write('<link rel="stylesheet" href="assets/dialog-polyfill.css"><scr' + 'ipt src="assets/dialog-polyfill.min.js"><\/scr' + 'ipt>');</script>
    </head>
    <body>
    <div class="ds-bg" aria-hidden="true"><canvas></canvas></div>
    <div class="guest-shell">
        <header class="guest-top">
            <div class="brand">⯈ <?= e($siteTitle) ?></div>
            <nav class="who">
                <a class="btn ghost" href="login.php"><?= e(t('login')) ?></a>
                <?php if ($regOpen): ?><a class="btn primary" href="register.php"><?= e(t('register')) ?></a><?php endif; ?>
            </nav>
        </header>
        <section class="guest-hero">
            <h1><?= e(t('hero_title')) ?></h1>
            <p><?= e(t('hero_sub_guest')) ?></p>
            <div class="searchbar guest-search">
                <select id="engine">
                <?php if (i18n_lang() === 'en'): ?>
                    <option value="google" selected><?= e(t('engine_google')) ?></option>
                    <option value="bing"><?= e(t('engine_bing')) ?></option>
                <?php else: ?>
                    <option value="bing" selected><?= e(t('engine_bing')) ?></option>
                    <option value="baidu"><?= e(t('engine_baidu')) ?></option>
                    <option value="google"><?= e(t('engine_google')) ?></option>
                <?php endif; ?>
                </select>
                <input id="q" placeholder="<?= e(t('search_ph')) ?>" autofocus>
            </div>
        </section>
        <main class="guest-main">
            <?php foreach ($guestSections as $sec => $links): ?>
            <section class="guest-sec">
                <h2><?= e(t($secKeys[$sec] ?? $sec)) ?></h2>
                <div class="guest-grid">
                    <?php foreach ($links as [$name, $url]): ?>
                    <a class="card guest-card" href="<?= e($url) ?>" target="_blank" rel="noopener">
                        <img class="ic" src="favicon.php?h=<?= e(parse_url($url, PHP_URL_HOST)) ?>" alt="" loading="lazy"
                             onerror="this.onerror=null;this.src=window.ICON_FALLBACK">
                        <span class="meta"><b><?= e($name) ?></b></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endforeach; ?>
        </main>
        <footer class="guest-foot"><?= e(t('guest_foot')) ?><a href="login.php"><?= e(t('login')) ?></a><?= e(t('guest_foot2')) ?><a href="terms.php" target="_blank" rel="noopener" style="margin-left:10px"><?= e(t('terms')) ?></a></footer>
    </div>
    <footer class="foot-brand-bar"><a class="gh-link" href="https://github.com/showhoo/itswe-nav" target="_blank" rel="noopener">GitHub</a><?= site_credit_html() ?><?php if ($siteName !== ''): ?><?php if ($siteUrl !== ''): ?><a class="site-name" href="<?= e($siteUrl) ?>" target="_blank" rel="noopener"><?= e($siteName) ?></a><?php else: ?><span class="site-name"><?= e($siteName) ?></span><?php endif; ?><?php endif; ?></footer>
    <script src="assets/dsbg.js?v=1"></script>
    <script>
    /* 默认必应；访客自己选过就记住；任何异常一律退回必应 */
    var ENGINES = { bing: 'https://www.bing.com/search?q=', baidu: 'https://www.baidu.com/s?wd=', google: 'https://www.google.com/search?q=' };
    var sel = document.getElementById('engine');
    var pick = <?= i18n_lang() === 'en' ? "'google'" : "'bing'" ?>;
    try {
        var saved = localStorage.getItem('navEngine');
        if (saved && ENGINES[saved]) pick = saved;
    } catch (err) { /* 隐私模式等：忽略，保持必应 */ }
    sel.value = pick;
    sel.addEventListener('change', function () {
        try { localStorage.setItem('navEngine', sel.value); } catch (err) {}
    });
    var q = document.getElementById('q');
    q.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var v = q.value.trim();
        if (!v) return;
        var eng = ENGINES[sel.value] ? sel.value : 'bing';
        var loc = /^https?:\/\//.test(v) || /^[\w-]+(\.[\w-]+)+(\/.*)?$/.test(v)
            ? (/^https?:\/\//.test(v) ? v : 'https://' + v)
            : ENGINES[eng] + encodeURIComponent(v);
        window.open(loc, '_blank', 'noopener');
    });
    </script>
    </body>
    </html>
    <?php
    exit;
}

$uid = (int)$user['id'];

// —— 内外网模式：auto 按客户端网段自动判定，lan/wan 为手动强制 ——
$netmodePref = get_pref($uid, 'netmode', 'auto');
$netmode = in_array($netmodePref, ['auto', 'lan', 'wan'], true) ? $netmodePref : 'auto';
$cidrs = lan_cidr_list();
$clientLan = client_is_lan(client_ip(), $cidrs);

$groups = db()->prepare('SELECT id, name FROM "groups" WHERE user_id = ? ORDER BY sort, id');
$groups->execute([$uid]);
$groups = $groups->fetchAll();

$items = db()->prepare('SELECT id, group_id, title, url, url_lan, icon, description FROM items WHERE user_id = ? ORDER BY sort, id');
$items->execute([$uid]);
$allItems = $items->fetchAll();
$byGroup = [];
foreach ($allItems as $it) $byGroup[(int)$it['group_id']][] = $it;

$wallpaper = get_pref($uid, 'wallpaper', 'preset:1');
$engine    = get_pref($uid, 'engine', 'bing');
$srvOn     = get_pref(0, 'srv_status', '1') === '1';   // 主面板服务器状态条（站点级开关，默认开）
$statsAdminOnly = get_pref(0, 'stats_admin_only', '0') === '1';
$siteTitle = get_pref($uid, 'title', '') ?: site_name();
$siteName = site_name();
$siteUrl = get_pref(0, 'site_url', '');
// 预设 1 = 首屏背景（#f9f8f8 底 + 顶部淡蓝渐变 + 流体云雾，见 assets/dsbg.js）
$flow = $wallpaper === 'preset:1';
$bgCss = $flow
    ? 'background:#f9f8f8;'
    : (str_starts_with($wallpaper, 'preset:')
        ? 'background:var(--bg-p' . ((int)substr($wallpaper, 7) ?: 1) . ');'
        : (str_starts_with($wallpaper, 'upload:')
            ? "background:url('media.php?type=wallpaper&f=" . e(substr($wallpaper, 7)) . "') center/cover no-repeat;"
            : ($wallpaper !== '' ? "background:url('" . e($wallpaper) . "') center/cover no-repeat;" : 'background:var(--bg-p1);')));
?>
<!DOCTYPE html>
<html lang="<?= i18n_lang() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($siteTitle) ?></title>
<link rel="stylesheet" href="assets/style.css?v=12">
<link rel="icon" type="image/png" href="assets/itswe-icon.png">
<script>var ICON_FALLBACK = "assets/itswe-icon.png";if (!window.HTMLDialogElement) document.write('<link rel="stylesheet" href="assets/dialog-polyfill.css"><scr' + 'ipt src="assets/dialog-polyfill.min.js"><\/scr' + 'ipt>');</script>
</head>
<body style="<?= $bgCss ?>">
<?php if ($flow): ?><div class="ds-bg" aria-hidden="true"><canvas></canvas></div><?php endif; ?>
<div class="shell">
    <header class="guest-top">
        <div class="brand">⯈ <?= e($siteTitle) ?></div>
        <nav class="who">
            <?php $netLabel = $netmode === 'auto' ? t('netmode_auto') . '·' . t($clientLan ? 'netmode_lan' : 'netmode_wan') : t($netmode === 'lan' ? 'netmode_lan' : 'netmode_wan'); ?>
            <button class="btn ghost" id="btn-netmode" title="<?= e(t('netmode_title')) ?>">🌐 <?= e($netLabel) ?></button>
            <button class="btn ghost" id="btn-edit-mode" title="<?= e(t('edit_mode_title')) ?>"><?= e(t('edit_mode')) ?></button>
            <div class="user-menu">
                <button type="button" class="btn ghost user-btn" id="btn-user" title="<?= e(t('account')) ?>" aria-haspopup="true" aria-expanded="false">👤</button>
                <div class="user-drop" id="user-drop" hidden>
                    <div class="u-head"><b><?= e($user['username']) ?></b><i class="role-badge"><?= e($user['role'] === 'admin' ? t('role_admin') : t('role_user')) ?></i></div>
                    <button type="button" class="u-item" id="btn-pw" title="<?= e(t('user_settings')) ?>">⚙ <?= e(t('personal_settings')) ?></button>
                    <?php if ($user['role'] === 'admin'): ?>
                    <a class="u-item" href="admin.php" title="管理后台：用户管理 / 站点设置 / 服务器·Docker">🔧 <?= e(t('menu_sysadmin')) ?></a>
                    <?php endif; ?>
                    <div class="u-sep"></div>
                    <a class="u-item u-danger" href="logout.php">🚪 <?= e(t('logout')) ?></a>
                </div>
            </div>
        </nav>
    </header>

    <!-- 首屏：与游客页同款标题 + 搜索（顶部布局完全镜像游客页） -->
    <section class="guest-hero">
        <h1><?= e(t('hero_title')) ?></h1>
        <p><?= e(t('hero_sub_member')) ?></p>
        <div class="searchbar guest-search">
            <select id="engine">
                <?php if (i18n_lang() === 'en'): ?>
                    <option value="google" <?= $engine === 'google' ? 'selected' : '' ?>><?= e(t('engine_google')) ?></option>
                    <option value="bing"   <?= $engine === 'bing'   ? 'selected' : '' ?>><?= e(t('engine_bing')) ?></option>
                <?php else: ?>
                    <option value="bing"  <?= $engine === 'bing'  ? 'selected' : '' ?>><?= e(t('engine_bing')) ?></option>
                    <option value="baidu" <?= $engine === 'baidu' ? 'selected' : '' ?>><?= e(t('engine_baidu')) ?></option>
                    <option value="google"<?= $engine === 'google'? 'selected' : '' ?>><?= e(t('engine_google')) ?></option>
                <?php endif; ?>
            </select>
            <input id="q" placeholder="<?= e(t('search_ph')) ?>">
        </div>
        <?php if ($srvOn && (!$statsAdminOnly || $user['role'] === 'admin')): ?><div class="srv-status" id="srv-status" hidden></div><?php endif; ?>
    </section>

    <div class="board-head">
        <button class="btn small primary" id="btn-add-group"><?= e(t('new_group')) ?></button>
        <span class="hint"><?= e(t('board_hint')) ?></span>
        <a class="btn small ghost" href="export.php?format=csv" download title="导出 CSV"><?= e(t('export_csv')) ?></a>
        <a class="btn small ghost" href="export.php?format=html" download title="导出 Netscape HTML"><?= e(t('export_html')) ?></a>
    </div>

    <main class="board" id="board">
        <?php foreach ($groups as $g): $gid = (int)$g['id']; ?>
        <section class="group-sec" data-gid="<?= $gid ?>">
            <div class="group-head" draggable="true">
                <span class="grip">≡</span>
                <h2 class="gname"><?= e($g['name']) ?></h2>
                <button class="btn small g-action" data-g-up title="<?= e(t('move_up')) ?>">↑</button>
                <button class="btn small g-action" data-g-down title="<?= e(t('move_down')) ?>">↓</button>
                <button class="btn small g-action" data-edit-group="<?= $gid ?>"><?= e(t('rename_group')) ?></button>
                <button class="btn small danger g-action" data-del-g="<?= $gid ?>"><?= e(t('del_group')) ?></button>
                <button class="btn small primary g-action" data-add-item="<?= $gid ?>"><?= e(t('add_card')) ?></button>
            </div>
            <div class="grid" data-gid="<?= $gid ?>">
                <?php foreach ($byGroup[$gid] ?? [] as $it): ?>
                <a class="card" href="<?= e(item_effect_url($it, $netmode, $clientLan)) ?>" target="_blank" rel="noopener" draggable="true" data-id="<?= (int)$it['id'] ?>" data-wan="<?= e($it['url']) ?>" data-icon="<?= e($it['icon']) ?>"<?php if (trim($it['url_lan']) !== ''): ?> data-lan="<?= e($it['url_lan']) ?>"<?php endif; ?>>
                    <?php $iconSrc = $it['icon'] !== '' ? (string)icon_field_url($it['icon']) : (($h = parse_url($it['url'], PHP_URL_HOST)) ? 'favicon.php?h=' . $h : ''); ?>
                <?php if ($iconSrc !== ''): ?>
                    <img class="ic" src="<?= e($iconSrc) ?>" alt="" loading="lazy"<?= $it['icon'] === '' ? ' data-autoicon="1"' : '' ?> onerror="this.onerror=null;this.src=window.ICON_FALLBACK">
                    <?php else: ?>
                    <span class="ic ic-fallback"><?= e(mb_strtoupper(mb_substr($it['title'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <span class="meta">
                        <b><?= e($it['title']) ?></b>
                        <?php if ($it['description'] !== ''): ?><i title="<?= e($it['description']) ?>"><?= e($it['description']) ?></i><?php endif; ?>
                    </span>
                    <span class="ops">
                        <button class="op" data-move-up title="<?= e(t('move_up')) ?>">↑</button>
                        <button class="op" data-move-down title="<?= e(t('move_down')) ?>">↓</button>
                        <button class="op" data-iframe="<?= e(item_effect_url($it, $netmode, $clientLan)) ?>" title="iframe 预览">📱</button>
                        <button class="op" data-edit-item="<?= (int)$it['id'] ?>" title="<?= e(t('edit_card')) ?>">✎</button>
                        <button class="op" data-del-item="<?= (int)$it['id'] ?>" title="<?= e(t('del_card')) ?>">×</button>
                    </span>
                </a>
                <?php endforeach; ?>
<?php if (empty($byGroup[$gid])): ?><div class="g-empty"><?= e(t('empty_group')) ?></div><?php endif; ?>
                <div class="drop-hint"><?= e(t('drop_hint')) ?></div>
            </div>
        </section>
        <?php endforeach; ?>
        <?php if (!$groups): ?>
        <div class="empty"><?= t('empty_board') ?></div>
        <?php endif; ?>
    </main>
</div>
<footer class="foot-brand-bar"><a class="gh-link" href="https://github.com/showhoo/itswe-nav" target="_blank" rel="noopener">GitHub</a><?= site_credit_html() ?><?php if ($siteName !== ''): ?><?php if ($siteUrl !== ''): ?><a class="site-name" href="<?= e($siteUrl) ?>" target="_blank" rel="noopener"><?= e($siteName) ?></a><?php else: ?><span class="site-name"><?= e($siteName) ?></span><?php endif; ?><?php endif; ?></footer>

<!-- 卡片模态框 -->
<dialog id="dlg-item">
    <form method="post" id="f-item">
        <h3 id="dlg-item-title"><?= e(t('dlg_item_add')) ?></h3>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="act" value="item.add">
        <input type="hidden" name="id" value="">
        <label><?= e(t('f_group')) ?> <select name="group_id" id="dlg-item-group"></select></label>
        <label><?= e(t('f_title')) ?> * <input name="title" required maxlength="64"></label>
        <label><?= e(t('f_url')) ?> * <input name="url" required maxlength="500" placeholder="example.com"></label>
        <label><?= e(t('f_url_lan')) ?> <input name="url_lan" maxlength="500" placeholder="http://192.168.x.x:port"></label>
        <label><?= e(t('f_icon')) ?> <input name="icon" id="f-item-icon" maxlength="500" placeholder="<?= e(t('f_icon_ph')) ?>"></label>
        <span class="hint" style="font-size:11px;color:var(--ds-text-placeholder)"><?= e(t('f_icon_hint')) ?></span>
        <div class="upload-row">
            <input type="file" id="icon-file" accept=".jpg,.jpeg,.png,.webp,.gif" hidden>
            <button type="button" class="btn small" id="btn-icon-upload"><?= e(t('upload_icon')) ?></button>
            <button type="button" class="btn small" id="btn-gallery">🖼 <?= e(t('gallery_pick')) ?></button>
            <span class="hint" id="icon-upload-state"></span>
        </div>
        <label><?= e(t('f_desc')) ?> <input name="description" maxlength="100"></label>
        <div class="dlg-foot">
            <button type="button" class="btn" data-close><?= e(t('cancel')) ?></button>
            <button class="btn primary"><?= e(t('save')) ?></button>
        </div>
    </form>
</dialog>

<!-- 图标图库选择器 -->
<dialog id="dlg-gallery" style="width:94vw;max-width:760px">
    <h3>🖼 <?= e(t('gallery')) ?></h3>
    <div class="g-bar">
        <input id="g-search" placeholder="<?= e(t('gallery_search')) ?>" style="flex:1" autocomplete="off">
        <select id="g-set">
            <option value="all"><?= e(t('set_all')) ?></option>
            <option value="dashboard"><?= e(t('set_dashboard')) ?></option>
            <option value="simple-icons"><?= e(t('set_simple')) ?></option>
        </select>
    </div>
    <div class="g-grid" id="g-grid"></div>
    <div class="msg" id="g-empty" hidden><?= e(t('gallery_empty')) ?></div>
    <div class="dlg-foot">
        <button type="button" class="btn" data-close><?= e(t('cancel')) ?></button>
        <button type="button" class="btn" id="g-more"><?= e(t('gallery_more')) ?></button>
    </div>
</dialog>

<!-- 分组模态框 -->
<dialog id="dlg-group">
    <form method="post" id="f-group">
        <h3 id="dlg-group-title"><?= e(t('dlg_group_add')) ?></h3>
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <input type="hidden" name="act" value="group.add">
        <input type="hidden" name="id" value="">
        <label><?= e(t('f_name_group')) ?> * <input name="name" required maxlength="32"></label>
        <div class="dlg-foot">
            <button type="button" class="btn" data-close><?= e(t('cancel')) ?></button>
            <button class="btn primary"><?= e(t('save')) ?></button>
        </div>
    </form>
</dialog>

<!-- iframe 预览弹窗 -->
<dialog id="dlg-iframe" style="width:92vw;max-width:960px;height:80vh;max-height:80vh;padding:0;border:none">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;background:var(--ds-surface-solid,#fff);border-bottom:1px solid var(--ds-border-subtle)">
        <span id="iframe-url" style="font-size:13px;color:var(--ds-text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1"></span>
        <a id="iframe-open" target="_blank" rel="noopener" class="btn small ghost" style="margin-left:8px;text-decoration:none">↗</a>
        <button class="btn small ghost" id="iframe-close" style="margin-left:4px">✕</button>
    </div>
    <iframe id="iframe-frame" style="width:100%;height:calc(100% - 41px);border:none" sandbox="allow-same-origin allow-scripts allow-forms allow-popups"></iframe>
</dialog>

<!-- 改密码弹窗（所有登录用户） -->
<dialog id="dlg-pw" style="max-height:85vh;overflow-y:auto">
    <h3>⚙ <?= e(t('user_settings')) ?></h3>

    <!-- 个人偏好 -->
    <form id="f-prefs-panel">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <label style="margin-top:8px"><?= e(t('panel_title_label')) ?> <input name="panel_title" value="<?= e($siteTitle) ?>" maxlength="32"></label>
        <label style="margin-top:8px"><?= e(t('wall_url')) ?>
            <select name="wall_sel" id="set-wall-sel">
                <?php for ($i = 1; $i <= 6; $i++): ?>
                <option value="preset:<?= $i ?>" <?= $wallpaper === "preset:$i" ? 'selected' : '' ?>>预设 <?= $i ?></option>
                <?php endfor; ?>
                <option value="custom" <?= !str_starts_with($wallpaper, 'preset:') ? 'selected' : '' ?>>自定义</option>
            </select>
        </label>
        <input name="wall_url" id="set-wall-url" placeholder="https://…jpg" value="<?= e(str_starts_with($wallpaper, 'preset:') ? '' : $wallpaper) ?>" style="margin-top:4px">
        <label style="margin-top:8px"><?= e(t('default_engine')) ?>
            <select name="engine">
                <?php if (i18n_lang() === 'en'): ?>
                    <option value="google" <?= $engine === 'google' ? 'selected' : '' ?>><?= e(t('engine_google')) ?></option>
                    <option value="bing"   <?= $engine === 'bing'   ? 'selected' : '' ?>><?= e(t('engine_bing')) ?></option>
                <?php else: ?>
                    <option value="bing"  <?= $engine === 'bing'  ? 'selected' : '' ?>><?= e(t('engine_bing')) ?></option>
                    <option value="baidu" <?= $engine === 'baidu' ? 'selected' : '' ?>><?= e(t('engine_baidu')) ?></option>
                    <option value="google"<?= $engine === 'google' ? 'selected' : '' ?>><?= e(t('engine_google')) ?></option>
                <?php endif; ?>
            </select>
        </label>
        <div class="dlg-foot"><button type="submit" class="btn primary"><?= e(t('save')) ?></button></div>
    </form>

    <hr style="margin:14px 0;border-color:var(--ds-border-subtle)">

    <!-- 修改密码 -->
    <h4 style="margin:0 0 8px"><?= e(t('pw_title')) ?></h4>
    <form id="f-pw-change">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <label><?= e(t('pw_old')) ?> <input type="password" name="old" required autocomplete="current-password"></label>
        <label><?= e(t('pw_new')) ?> <input type="password" name="new" required minlength="6" autocomplete="new-password"></label>
        <label><?= e(t('pw_confirm')) ?> <input type="password" name="confirm" required minlength="6" autocomplete="new-password"></label>
        <div class="dlg-foot"><button type="submit" class="btn primary"><?= e(t('pw_title')) ?></button></div>
    </form>

    <div class="dlg-foot" style="margin-top:12px">
        <button type="button" class="btn" onclick="document.getElementById('dlg-pw').close()"><?= e(t('cancel')) ?></button>
    </div>
</dialog>

<script>
window.__BOOT__ = { csrf: '<?= csrf_token() ?>', uid: <?= $uid ?>, netmode: '<?= e($netmode) ?>', clientLan: <?= $clientLan ? 'true' : 'false' ?> };
window.__I18N__ = <?= json_encode(i18n_dict()[i18n_lang()], JSON_UNESCAPED_UNICODE) ?>;
window.__ICONIFY__ = true;
</script>
<?php if ($flow): ?><script src="assets/dsbg.js?v=1"></script><?php endif; ?>
<script src="assets/app.js?v=10"></script>
</body>
</html>
