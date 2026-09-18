'use strict';
// app.js — 面板交互：页签、模态框、增删改、拖拽排序、搜索、设置
function t(k) { return (window.__I18N__ && window.__I18N__[k]) || k; }
function safe(fn) {   // 初始化分段隔离：任一功能初始化失败不影响其余功能
    try { fn(); } catch (e) { if (window.console) console.error('ITSWE-Nav init:', e); }
}
function openDlg(id) { document.getElementById(id).showModal(); }
function saveGridSort(grid) {
    const fd = new FormData();
    fd.append('act', 'item.sort');
    fd.append('group_id', grid.dataset.gid);
    fd.append('ids', [...grid.querySelectorAll('.card')].map(c => c.dataset.id).join(','));
    return api(fd).catch(() => alert(t('save_sort_fail')));
}
function saveGroupOrder() {
    const board = document.getElementById('board');
    if (!board) return Promise.resolve();
    const fd = new FormData();
    fd.append('act', 'group.sort');
    fd.append('ids', [...board.querySelectorAll('.group-sec')].map(s => s.dataset.gid).join(','));
    return api(fd).catch(() => alert(t('save_sort_fail')));
}
safe(function () {
    if (window.dialogPolyfill) document.querySelectorAll('dialog').forEach(function (d) { dialogPolyfill.registerDialog(d); });
});
const CSRF = window.__BOOT__.csrf;
const isEditing = () => document.body.classList.contains('edit-mode');   /* 默认浏览态 */

async function api(fd) {
    fd.append('csrf', CSRF);
    const r = await fetch('api.php', { method: 'POST', body: fd });
    if (r.status === 419) { alert(t('err_page_expired')); throw 0; }
    return r.json();
}

/** 网格内拖拽：按最近卡片中心点（行优先加权）预判插入位置；空网格返回 null 表示放末尾 */
function pickInsertRef(grid, x, y, dragging) {
    let best = null;
    for (const c of grid.querySelectorAll('.card:not(.dragging)')) {
        const r = c.getBoundingClientRect();
        const dx = x - (r.left + r.width / 2);
        const dy = y - (r.top + r.height / 2);
        const d = dy * dy * 4 + dx * dx;
        if (!best || d < best.d) best = { c, d, r };
    }
    if (!best) return null;
    return { ref: best.c, after: x > best.r.left + best.r.width / 2 };
}


// ---------- 通用模态框 ----------
safe(function () {
document.querySelectorAll('dialog [data-close]').forEach(b =>
    b.onclick = () => b.closest('dialog').close());
document.querySelectorAll('dialog').forEach(d =>
    d.addEventListener('click', e => { if (e.target === d) d.close(); }));
});
// ---------- 分组 ----------
safe(function () {
document.getElementById('btn-add-group')?.addEventListener('click', () => {
    const f = document.getElementById('f-group');
    f.act.value = 'group.add'; f.id.value = ''; f.name.value = '';
    document.getElementById('dlg-group-title').textContent = t('dlg_group_add');
    openDlg('dlg-group');
});
document.querySelectorAll('[data-edit-group]').forEach(b => b.onclick = () => {
    const gid = b.dataset.editGroup;
    const name = document.querySelector(`.group-sec[data-gid="${gid}"] .gname`).textContent;
    const f = document.getElementById('f-group');
    f.act.value = 'group.rename'; f.id.value = gid; f.name.value = name.trim();
    document.getElementById('dlg-group-title').textContent = t('dlg_group_rename');
    openDlg('dlg-group');
});
document.querySelectorAll('[data-del-g]').forEach(d => d.onclick = async e => {
    e.stopPropagation();
    if (!confirm(t('del_group_confirm'))) return;
    const fd = new FormData(); fd.append('act', 'group.del'); fd.append('id', d.dataset.delG);
    if ((await api(fd)).ok) location.reload();
});
document.getElementById('f-group').onsubmit = async e => {
    e.preventDefault();
    const j = await api(new FormData(e.target));
    if (j.ok) { sessionStorage.setItem('navEditMode', '1'); location.reload(); } else alert(j.msg);
};
});
// ---------- 卡片 ----------
safe(function () {
function fillGroupSelect(sel, gid) {
    sel.innerHTML = '';
    for (const sec of document.querySelectorAll('.group-sec[data-gid]')) {
        sel.add(new Option(sec.querySelector('.gname').textContent, sec.dataset.gid));   // Option 走 textContent，分组名不进 HTML 解析
    }
    if (gid) sel.value = gid;
}
document.querySelectorAll('[data-add-item]').forEach(b => b.onclick = () => {
    const f = document.getElementById('f-item');
    f.reset(); f.act.value = 'item.add'; f.id.value = '';
    document.getElementById('dlg-item-title').textContent = t('dlg_item_add');
    fillGroupSelect(f.group_id, b.dataset.addItem);
    openDlg('dlg-item');
});
document.querySelectorAll('[data-edit-item]').forEach(b => b.onclick = async e => {
    e.preventDefault(); e.stopPropagation();
    const id = b.dataset.editItem;
    const card = document.querySelector(`.card[data-id="${id}"]`);
    const f = document.getElementById('f-item');
    f.act.value = 'item.update'; f.id.value = id;
    f.title.value = card.querySelector('b').textContent;
    f.url.value = card.dataset.wan || '';                       // 存储的外网地址（href 会随内外网切换变化，不可作回填源）
    f.url_lan.value = card.dataset.lan || '';                   // 回填存储的内网地址
    f.description.value = card.querySelector('.meta i')?.textContent || '';
    const img = card.querySelector('img.ic');
    f.icon.value = card.dataset.icon || ((img && !img.hasAttribute('data-autoicon')) ? img.src : '');   // 回填存储值（data-icon），渲染 URL 会把 gallery:/iconify: 前缀固化
    document.getElementById('dlg-item-title').textContent = t('dlg_item_edit');
    fillGroupSelect(f.group_id, card.closest('.grid').dataset.gid);
    openDlg('dlg-item');
});
document.querySelectorAll('[data-del-item]').forEach(b => b.onclick = async e => {
    e.preventDefault(); e.stopPropagation();
    if (!confirm(t('del_item_confirm'))) return;
    const fd = new FormData(); fd.append('act', 'item.del'); fd.append('id', b.dataset.delItem);
    if ((await api(fd)).ok) b.closest('.card').remove();
});
document.getElementById('f-item').onsubmit = async e => {
    e.preventDefault();
    const j = await api(new FormData(e.target));
    if (j.ok) { sessionStorage.setItem('navEditMode', '1'); location.reload(); } else alert(j.msg);
};
});
// ---------- 卡片拖拽排序（含跨组移动） ----------
safe(function () {
let dragId = null;
document.querySelectorAll('.grid').forEach(grid => {
    grid.addEventListener('dragstart', e => {
        const card = e.target.closest('.card');
        if (!card) return;
        if (!isEditing()) { e.preventDefault(); return; }
        dragId = card.dataset.id;
        card.classList.add('dragging');
        grid.classList.add('dragging');
    });
    grid.addEventListener('dragend', () => {
        document.querySelectorAll('.card.dragging').forEach(c => c.classList.remove('dragging'));
        document.querySelectorAll('.grid').forEach(g => g.classList.remove('dragging'));
    });
    grid.addEventListener('dragover', e => {
        e.preventDefault();
        const dragging = document.querySelector('.card.dragging');
        if (!dragging) return;
        const pos = pickInsertRef(grid, e.clientX, e.clientY, dragging);
        if (!pos) grid.appendChild(dragging);
        else pos.after ? pos.ref.after(dragging) : pos.ref.before(dragging);
    });
    grid.addEventListener('drop', async e => {
        e.preventDefault();
        if (!dragId) return;
        const gid = grid.dataset.gid;
        await saveGridSort(grid);
        draggingCardToGroup(dragId, gid);
        dragId = null;
    });
});
function draggingCardToGroup(id, gid) {
    const card = document.querySelector(`.card[data-id="${id}"]`);
    if (card) card.dataset.gid = gid;
}

// 分组拖动排序（拖分组标题）
{
    const board = document.getElementById('board');
    let src = null;
    board.addEventListener('dragstart', e => {
        if (!isEditing()) { e.preventDefault(); return; }
        const h = e.target.closest('.group-head');
        if (h) { src = h.closest('.group-sec'); e.dataTransfer.effectAllowed = 'move'; }
    });
    board.addEventListener('dragover', e => {
        if (!src) return;
        const sec = e.target.closest('.group-sec');
        if (!sec || sec === src) return;
        e.preventDefault();
        const r = sec.getBoundingClientRect();
        board.insertBefore(src, e.clientY < r.top + r.height / 2 ? sec : sec.nextSibling);
    });
    board.addEventListener('drop', async e => {
        if (!src) return;
        e.preventDefault();
        const ids = [...board.querySelectorAll('.group-sec')].map(s => s.dataset.gid).join(',');
        const fd = new FormData(); fd.append('act', 'group.sort'); fd.append('ids', ids);
        await api(fd);
        src = null;
    });
}
});
// ---------- 搜索 ----------
safe(function () {
const ENGINES = {
    bing: 'https://www.bing.com/search?q=',
    baidu: 'https://www.baidu.com/s?wd=',
    google: 'https://www.google.com/search?q='
};
document.getElementById('q').addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.value.trim()) {
        const sel = document.getElementById('engine').value;
        const eng = ENGINES[sel] ? sel : 'bing';   /* 兜底：非法值一律用必应 */
        window.open(ENGINES[eng] + encodeURIComponent(e.target.value.trim()), '_blank');
    }
});
document.getElementById('engine').addEventListener('change', e => {
    const fd = new FormData(); fd.append('act', 'pref.set'); fd.append('engine', e.target.value);
    api(fd);
});
});
// ---------- 长按 2 秒显示编辑菜单 ----------
safe(function () {
{
    let pressTimer = null, pressCard = null, sx = 0, sy = 0;
    const hideOps = () => {
        clearTimeout(pressTimer);
        document.querySelectorAll('.card.ops-show').forEach(c => c.classList.remove('ops-show'));
    };
    document.addEventListener('pointerdown', e => {
        if (e.target.closest('.op')) return;               /* 按✎/×瞬间不能收菜单：按钮随即 display:none，click 会落空（iOS 点不到编辑的根因） */
        if (e.target.closest('.card.ops-show')) return;    /* 已弹菜单的卡片本体：保留状态交给 click 分支收起，否则收了又立即重弹 */
        hideOps();
        const card = e.target.closest('.card');
        if (!card || !isEditing()) return;
        pressCard = card; sx = e.clientX; sy = e.clientY;
        pressTimer = setTimeout(() => {
            card.classList.add('ops-show');
            if (navigator.vibrate) navigator.vibrate(30);
            pressCard = null;
        }, 2000);
    });
    document.addEventListener('pointermove', e => {
        if (pressCard && Math.hypot(e.clientX - sx, e.clientY - sy) > 10) {
            clearTimeout(pressTimer); pressCard = null;
        }
    });
    document.addEventListener('pointerup', () => { clearTimeout(pressTimer); pressCard = null; });
    document.addEventListener('pointercancel', () => { clearTimeout(pressTimer); pressCard = null; });
    // 菜单弹出后，点卡片本体=收起菜单（不再跳转），点✎/×=对应操作
    const coarse = matchMedia('(hover: none)').matches;  // 触屏设备
    document.addEventListener('click', e => {
        if (!isEditing()) return;   /* 浏览态：卡片就是普通链接，直接跳转（此前触屏全被拦成弹菜单，点击永远无反应） */
        const card = e.target.closest('.card');
        const onOp = e.target.closest('.op');
        // 触屏：第一次点卡片只弹出编辑按钮不跳转；再点本体收起，点✎/×执行操作
        if (coarse && card && !onOp && !card.classList.contains('ops-show')) {
            e.preventDefault();
            hideOps();
            card.classList.add('ops-show');
            return;
        }
        const shown = e.target.closest('.card.ops-show');
        if (shown && !onOp) {
            e.preventDefault();
            shown.classList.remove('ops-show');
        }
    });
}
});
// ---------- 触屏拖拽（卡片排序 / 跨组移动 / 页签排序） ----------
safe(function () {
{
    let src = null, ghost = null, timer = null;
    let sx = 0, sy = 0, gx = 0, gy = 0;
    let curGrid = null, curTab = null;

    const endVisual = () => {
        if (ghost) { ghost.remove(); ghost = null; }
        document.querySelectorAll('.dragging').forEach(c => c.classList.remove('dragging'));
        document.querySelectorAll('.tab.drop-target').forEach(t => t.classList.remove('drop-target'));
        src = null; curGrid = null; curTab = null;
    };

    const startDrag = () => {
        timer = null;
        if (!src) return;
        const r = src.getBoundingClientRect();
        gx = r.left; gy = r.top;
        ghost = src.cloneNode(true);
        ghost.classList.add('dragging', 'touch-ghost');
        Object.assign(ghost.style, {
            position: 'fixed', left: gx + 'px', top: gy + 'px',
            width: r.width + 'px', margin: '0', zIndex: 9999,
            pointerEvents: 'none', opacity: '.9'
        });
        document.body.appendChild(ghost);
        src.classList.add('dragging');
        document.querySelectorAll('.card.ops-show').forEach(c => c.classList.remove('ops-show'));
        if (navigator.vibrate) navigator.vibrate(20);
    };

    document.addEventListener('touchstart', e => {
        if (e.touches.length !== 1) return;
        if (!isEditing()) return;   /* 浏览态：不进入拖拽（否则按住 >300ms 会误生成拖拽幽灵并吞掉点击） */
        const t = e.touches[0];
        const card = e.target.closest('.card');
        const tab = e.target.closest('.tab[data-gid]');
        if (!card && !tab) return;
        if (card && e.target.closest('.op')) return;
        src = card || tab;
        sx = t.clientX; sy = t.clientY;
        timer = setTimeout(startDrag, 300);
    }, { passive: true });

    document.addEventListener('touchmove', e => {
        if (!src) return;
        const t = e.touches[0];
        if (timer) {  // 未进入拖动态前移动视作滚动
            if (Math.hypot(t.clientX - sx, t.clientY - sy) > 10) { clearTimeout(timer); timer = null; src = null; }
            return;
        }
        if (!ghost) return;
        e.preventDefault();  // 拖动中禁止页面滚动
        ghost.style.left = (gx + t.clientX - sx) + 'px';
        ghost.style.top = (gy + t.clientY - sy) + 'px';

        curGrid = null; curTab = null;
        document.querySelectorAll('.tab.drop-target').forEach(x => x.classList.remove('drop-target'));
        const el = document.elementFromPoint(t.clientX, t.clientY);
        if (src.classList.contains('card')) {
            curGrid = el ? el.closest('.grid') : null;
            const tabUnder = el ? el.closest('.tab[data-gid]') : null;
            if (tabUnder) {
                curTab = tabUnder;
                curGrid = document.querySelector(`.grid[data-gid="${tabUnder.dataset.gid}"]`);
                tabUnder.classList.add('drop-target');
            }
            if (curGrid) {  // 实时预览排序
                const pos = pickInsertRef(curGrid, t.clientX, t.clientY);
                const real = document.querySelector(`.card[data-id="${src.dataset.id}"]`) || src;
                if (!pos) curGrid.appendChild(real);
                else pos.after ? pos.ref.after(real) : pos.ref.before(real);
            }
        } else {  // 页签排序预览
            const bar = document.getElementById('tabbar');
            const tabUnder = el ? el.closest('.tab[data-gid]') : null;
            if (tabUnder && tabUnder !== src) bar.insertBefore(src, tabUnder);
        }
    }, { passive: false });

    document.addEventListener('touchend', async e => {
        if (!ghost || !src) { if (timer) { clearTimeout(timer); timer = null; } return; }
        e.preventDefault();
        const wasCard = src.classList.contains('card');
        const dropGrid = curGrid, dropTab = curTab;
        const id = src.dataset.id;
        const cardEl = wasCard ? document.querySelector(`.card[data-id="${id}"]`) : null;
        endVisual();
        try {
            if (wasCard && dropGrid) {
                await saveGridSort(dropGrid);
                if (cardEl && dropTab) cardEl.dataset.gid = dropTab.dataset.gid;
            } else if (!wasCard) {
                const bar = document.getElementById('tabbar');
                const fd = new FormData();
                fd.append('act', 'group.sort');
                fd.append('ids', [...bar.querySelectorAll('.tab[data-gid]')].map(t => t.dataset.gid).join(','));
                await api(fd);
            }
        } catch (_) { /* 保存失败时刷新恢复真实顺序 */ }
        if (wasCard) location.reload();
    }, { passive: false });

    document.addEventListener('touchcancel', () => { if (timer) { clearTimeout(timer); timer = null; } endVisual(); });
}
});
// ---------- 编辑模式开关（默认浏览态：与游客页一致的干净展示） ----------
safe(function () {
{
    const btn = document.getElementById('btn-edit-mode');
    const sync = () => {
        const on = isEditing();
        btn.className = on ? 'btn primary' : 'btn ghost';
        btn.textContent = on ? t('done') : t('edit_mode');
        document.querySelectorAll('.card, .group-head').forEach(el => { el.draggable = on; });
        if (!on) document.querySelectorAll('.card.ops-show').forEach(c => c.classList.remove('ops-show'));
    };
    btn.addEventListener('click', () => { document.body.classList.toggle('edit-mode'); sync(); });
    if (sessionStorage.getItem('navEditMode') === '1') {   // 保存后刷新恢复编辑态，避免连续添加被打断
        sessionStorage.removeItem('navEditMode');
        document.body.classList.add('edit-mode');
    }
    sync();
}
});
// ---------- 🖼 图标图库选择器（本地 data/gallery，运行时零外联） ----------
safe(function () {
    const btn = document.getElementById('btn-gallery');
    const dlg = document.getElementById('dlg-gallery');
    if (!btn || !dlg) return;
    const q = document.getElementById('g-search');
    const setSel = document.getElementById('g-set');
    const grid = document.getElementById('g-grid');
    const empty = document.getElementById('g-empty');
    const more = document.getElementById('g-more');
    const EXT = { dashboard: 'webp', 'simple-icons': 'svg' };
    const PAGE = 120;
    let index = null;   // {set: [name,...]}，来自 gallery.php?index=1
    let shown = 0;

    async function loadIndex() {
        if (index) return index;
        index = { dashboard: [], 'simple-icons': [] };
        try {
            const j = await (await fetch('gallery.php?index=1')).json();
            if (j && typeof j === 'object') index = j;
        } catch (_) { /* 图库未同步：保持空列表 */ }
        return index;
    }
    function rows() {
        const s = (q.value || '').trim().toLowerCase();
        const f = setSel.value;
        const out = [];
        for (const k of Object.keys(index)) {
            if (f !== 'all' && f !== k) continue;
            for (const n of (index[k] || [])) {
                if (!s || n.indexOf(s) >= 0) out.push([k, n]);
            }
        }
        return out;
    }
    function render(reset) {
        const list = rows();
        if (reset) { grid.innerHTML = ''; shown = 0; }
        const frag = document.createDocumentFragment();
        for (const [k, n] of list.slice(shown, shown + PAGE)) {
            const d = document.createElement('div');
            d.className = 'g-item';
            d.dataset.g = k + '/' + n;
            d.title = n;
            const im = document.createElement('img');
            im.loading = 'lazy';
            im.src = 'gallery.php?set=' + encodeURIComponent(k) + '&f=' + encodeURIComponent(n + '.' + (EXT[k] || 'svg'));
            im.onerror = () => { d.remove(); };
            d.appendChild(im);
            frag.appendChild(d);
        }
        grid.appendChild(frag);
        shown = Math.min(shown + PAGE, list.length);
        empty.hidden = list.length > 0;
        more.style.display = shown < list.length ? '' : 'none';
    }
    btn.addEventListener('click', e => {
        e.preventDefault();
        dlg.showModal();
        loadIndex().then(() => render(true));
    });
    q.addEventListener('input', () => render(true));
    setSel.addEventListener('change', () => render(true));
    more.addEventListener('click', () => render(false));
    grid.addEventListener('click', ev => {
        const it = ev.target.closest('[data-g]');
        if (!it) return;
        document.getElementById('f-item-icon').value = 'gallery:' + it.dataset.g;
        dlg.close();
    });
});
// ---------- 👤 账号下拉菜单（点头像开合，点外部/Esc/选中项收起） ----------
safe(function () {
    const btn = document.getElementById('btn-user');
    const drop = document.getElementById('user-drop');
    if (!btn || !drop) return;
    const setOpen = open => { drop.hidden = !open; btn.setAttribute('aria-expanded', open ? 'true' : 'false'); };
    btn.addEventListener('click', e => { e.stopPropagation(); setOpen(drop.hidden); });
    document.addEventListener('click', e => { if (!drop.hidden && !drop.contains(e.target)) setOpen(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setOpen(false); });
    drop.addEventListener('click', e => { if (e.target.closest('.u-item')) setOpen(false); });
});
// ---------- 自助改密码（所有登录用户，需旧密码验证） ----------
safe(function () {
    const btn = document.getElementById('btn-pw');
    const dlg = document.getElementById('dlg-pw');
    if (btn && dlg) {
        btn.addEventListener('click', () => dlg.showModal());
        // 偏好设置保存
        const prefsForm = document.getElementById('f-prefs-panel');
        if (prefsForm) prefsForm.addEventListener('submit', async e => {
            e.preventDefault();
            const fd = new FormData(e.target);
            fd.append('act', 'pref.set');
            const wallSel = fd.get('wall_sel') || '';
            const wallUrl = (fd.get('wall_url') || '').trim();
            fd.set('wallpaper', wallSel === 'custom' ? wallUrl : wallSel);
            fd.delete('wall_sel'); fd.delete('wall_url');
            const j = await api(fd);
            if (j.ok) { dlg.close(); location.reload(); }
            else alert(j.msg || t('msg_failed'));
        });
        // 改密码
        const pwForm = document.getElementById('f-pw-change');
        if (pwForm) pwForm.addEventListener('submit', async e => {
            e.preventDefault();
            const f = e.target;
            if (f.new.value !== f.confirm.value) { alert(t('err_pw_mismatch')); return; }
            const j = await api(new FormData(f));
            if (j.ok) { f.reset(); alert(t('pw_changed')); dlg.close(); }
            else alert(j.msg || t('msg_failed'));
        });
        // 关闭按钮
        const closeBtn = dlg.querySelector('.dlg-foot .btn[data-close], .dlg-foot button[onclick*=close]');
        if (closeBtn) closeBtn.addEventListener('click', () => dlg.close());
        dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
    }
});

// ---------- 键盘/触屏友好的顺序微调（编辑模式 ↑↓ 按钮） ----------
{
    document.querySelectorAll('[data-move-up], [data-move-down]').forEach(b => b.onclick = e => {
        e.preventDefault(); e.stopPropagation();
        const card = b.closest('.card');
        const up = b.hasAttribute('data-move-up');
        let sib = card[up ? 'previousElementSibling' : 'nextElementSibling'];
        while (sib && !sib.matches('.card')) sib = sib[up ? 'previousElementSibling' : 'nextElementSibling'];
        if (!sib) return;
        up ? sib.before(card) : sib.after(card);
        saveGridSort(card.closest('.grid'));
    });
    document.querySelectorAll('[data-g-up], [data-g-down]').forEach(b => b.onclick = e => {
        e.preventDefault(); e.stopPropagation();
        const sec = b.closest('.group-sec');
        const up = b.hasAttribute('data-g-up');
        const sib = sec[up ? 'previousElementSibling' : 'nextElementSibling'];
        if (!sib || !sib.matches('.group-sec')) return;
        up ? sib.before(sec) : sib.after(sec);
        saveGroupOrder();
    });
}

// ---------- 内外网模式切换（auto → lan → wan 循环，即时改写卡片链接不刷新） ----------
safe(function () {
{
    const btn = document.getElementById('btn-netmode');
    if (btn) {
        const boot = window.__BOOT__;
        const order = ['auto', 'lan', 'wan'];
        const names = { auto: t('netmode_auto'), lan: t('netmode_lan'), wan: t('netmode_wan') };
        const apply = m => {
            document.querySelectorAll('.card[data-id]').forEach(c => {
                const lan = c.dataset.lan;
                c.href = (lan && (m === 'lan' || (m === 'auto' && boot.clientLan))) ? lan : c.dataset.wan;
            });
            btn.textContent = '🌐 ' + (m === 'auto' ? t('netmode_auto') + '·' + t(boot.clientLan ? 'netmode_lan' : 'netmode_wan') : t('netmode_' + m));
            btn.title = m === 'auto'
                ? t('netmode_auto_hint')
                : t('netmode_force_hint').replace('%s', names[m]);
        };
        let mode = boot.netmode;
        apply(boot.netmode);
        btn.addEventListener('click', () => {
            const next = order[(order.indexOf(mode) + 1) % order.length];
            apply(next);
            mode = next;
            const fd = new FormData();
            fd.append('act', 'pref.set');
            fd.append('netmode', next);
            api(fd).catch(() => {});
        });
    }
}
});
// ---------- 卡片图标上传 ----------
safe(function () {
{
    const fileInput = document.getElementById('icon-file');
    const btn = document.getElementById('btn-icon-upload');
    const state = document.getElementById('icon-upload-state');
    if (fileInput && btn) {
        btn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', async () => {
            const f = fileInput.files[0];
            if (!f) return;
            state.textContent = t('uploading');
            const fd = new FormData();
            fd.append('act', 'icon.upload');
            fd.append('file', f);
            try {
                const j = await api(fd);
                if (j.ok) {
                    document.getElementById('f-item-icon').value = j.url;
                    state.textContent = t('uploaded');
                } else {
                    state.textContent = j.msg || t('upload_fail');
                }
            } catch (_) { state.textContent = t('upload_fail'); }
        });
    }
}
});
// ---------- 主面板服务器状态条（搜索框下方，站点开关控制，30s 刷新） ----------
safe(function () {
{
    const box = document.getElementById('srv-status');
    if (box) {
        const fmtB = b => {
            if (b == null || isNaN(b)) return '-';
            const u = ['B', 'KB', 'MB', 'GB', 'TB'];
            let i = 0, n = Number(b);
            while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
            return (i ? n.toFixed(1) : n) + ' ' + u[i];
        };
        const load = async () => {
            try {
                const fd = new FormData();
                fd.append('act', 'server.stats');
                fd.append('csrf', CSRF);   // 必须带 token，否则被 CSRF 校验 419 拒绝
                const r = await fetch('api.php', { method: 'POST', body: fd });
                if (!r.ok) return;
                const j = await r.json();
                if (!j.ok || !j.stats) return;
                const s = j.stats;
                const item = (k, v) => `<span class="srv-item"><b>${k}</b>${v}</span>`;
                box.hidden = false;
                box.innerHTML =
                    item(t('stat_cpu'), s.cpu_pct == null ? '-' : s.cpu_pct + '%') +
                    item(t('stat_mem'), s.mem_pct == null ? '-' : s.mem_pct + '%') +
                    item(t('stat_disk'), s.disk_pct == null ? '-' : s.disk_pct + '%') +
                    item(t('stat_rx'), fmtB(s.net_rx_bps) + '/s') +
                    item(t('stat_tx'), fmtB(s.net_tx_bps) + '/s') +
                    item(t('stat_uptime'), s.uptime_s >= 86400 ? Math.floor(s.uptime_s / 86400) + t('days') : Math.floor(s.uptime_s / 3600) + t('hours'));
            } catch (_) { /* 静默：状态条失败不影响面板 */ }
        };
        load();
        setInterval(load, 30000);
    }
}
});