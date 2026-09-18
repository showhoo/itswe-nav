// touch-edit-test.js — 触屏「编辑卡片」真机验证（iOS 事件序列：pointerdown → click）
// 用法：BASE=http://127.0.0.1:18099 PW_CORE=/path/to/playwright-core CHROME_EXE=/path/to/chrome node tests/touch-edit-test.js
const { chromium } = require(process.env.PW_CORE || 'playwright-core');
const EXE = process.env.CHROME_EXE || undefined;
const BASE = process.env.BASE || 'http://127.0.0.1:18099';
let pass = 0, fail = 0; const fails = [];
const ok = (n, c, d) => { c ? pass++ : (fail++, fails.push(n)); console.log((c ? '  ✓ ' : '  ✗ ') + n + (d ? ' [' + d + ']' : '')); };
const uname = 'tw' + (Date.now() % 100000);

(async () => {
  const b = await chromium.launch({ executablePath: EXE });
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, locale: 'zh-CN' });
  const pg = await ctx.newPage();
  const errs = []; pg.on('pageerror', e => errs.push(e.message));

  // 1. 注册（UI 流程）
  await pg.goto(BASE + '/login.php', { waitUntil: 'load', timeout: 15000 });
  await pg.click('button[data-tab="register"]');
  await pg.fill('#f-register input[name=username]', uname);
  await pg.fill('#f-register input[name=password]', 'tw123456');
  await Promise.all([pg.waitForURL('**/index.php', { timeout: 10000 }), pg.click('#f-register button.primary')]);
  ok('注册成功进入面板', pg.url().includes('index.php'), pg.url());

  // 2. 建组+建卡（页内 API）
  const made = await pg.evaluate(async () => {
    const api = fd => fetch('api.php', { method: 'POST', body: fd }).then(r => r.json());
    let fd = new FormData();
    fd.append('csrf', window.__BOOT__.csrf); fd.append('act', 'group.add'); fd.append('name', '触测组');
    const g = await api(fd);
    fd = new FormData();
    fd.append('csrf', window.__BOOT__.csrf); fd.append('act', 'item.add');
    fd.append('group_id', g.id); fd.append('title', '示例站'); fd.append('url', 'https://example.com');
    return api(fd);
  });
  ok('建组+建卡', made && made.ok, JSON.stringify(made));
  await pg.reload({ waitUntil: 'load' });

  // 3. 进入编辑模式（触摸）
  try { await pg.tap('#btn-edit-mode', { timeout: 5000 }); }
  catch (_) { await pg.evaluate(() => document.getElementById('btn-edit-mode').click()); }
  ok('进入编辑模式', await pg.evaluate(() => document.body.classList.contains('edit-mode')));

  // 4. 点卡片本体 → 弹出操作菜单
  const cb = await pg.locator('.card').first().boundingBox();
  await pg.touchscreen.tap(cb.x + cb.width / 2, cb.y + cb.height / 2);
  await pg.waitForTimeout(250);
  ok('点卡片弹出操作菜单', await pg.evaluate(() => !!document.querySelector('.card.ops-show')));

  // 5. 点 ✎ → 编辑弹窗打开（本次 bug 的核心断言）
  const eb = await pg.locator('.card .op[data-edit-item]').boundingBox();
  ok('✎ 按钮可见', !!eb && eb.height > 0);
  if (eb) {
    await pg.touchscreen.tap(eb.x + eb.width / 2, eb.y + eb.height / 2);
    await pg.waitForTimeout(350);
    ok('点✎打开编辑弹窗', await pg.evaluate(() => document.getElementById('dlg-item').open));
    await pg.evaluate(() => document.getElementById('dlg-item').close());
  }

  // 5b. WebKit 事件序列不变量：pointerdown(✎) 后强制样式提交，按钮必须仍可命中
  //     （iOS WebKit 在事件间提交样式；Blink 同帧不提交，所以真 tap 在桌面内核测不出此 bug）
  if (!await pg.evaluate(() => !!document.querySelector('.card.ops-show'))) {
    const b1 = await pg.locator('.card').first().boundingBox();
    await pg.touchscreen.tap(b1.x + b1.width / 2, b1.y + b1.height / 2);
    await pg.waitForTimeout(250);
  }
  const hit = await pg.evaluate(() => {
    if (document.activeElement && document.activeElement.blur) document.activeElement.blur();
    const card = document.querySelector('.card.ops-show');
    if (!card) return 'NO_MENU';
    const btn = card.querySelector('.op[data-edit-item]');
    const r = btn.getBoundingClientRect();
    btn.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, clientX: r.x + r.width / 2, clientY: r.y + r.height / 2 }));
    void document.body.offsetHeight;   // 强制样式/布局提交（模拟 WebKit 行为）
    const el = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
    document.dispatchEvent(new PointerEvent('pointerup', { bubbles: true }));
    card.classList.remove('ops-show'); // 复位，供后续步骤使用
    return el === btn || btn.contains(el) ? 'HIT' : 'MISS:' + (el ? el.className : 'null');
  });
  ok('✎ 在 pointerdown+样式提交后仍可命中', hit === 'HIT', hit);

  // 6. 状态机三连击：弹 → 收 → 重弹
  const sb = await pg.locator('.card').first().boundingBox();
  const BLUR = () => pg.evaluate(() => { if (document.activeElement && document.activeElement.blur) document.activeElement.blur(); });
  const dbg = () => pg.evaluate(() => {
    const c = document.querySelector('.card');
    const r = c.getBoundingClientRect();
    const el = document.elementFromPoint(r.x + 20, r.y + r.height - 14);
    return { shown: document.querySelectorAll('.card.ops-show').length, at: el ? el.tagName + '.' + el.className : 'null' };
  });
  const tapCard = async () => { await BLUR(); await pg.touchscreen.tap(sb.x + 20, sb.y + sb.height - 14); await pg.waitForTimeout(250); console.log('   [dbg]', JSON.stringify(await dbg())); };
  await tapCard();
  ok('点卡片弹出菜单', await pg.evaluate(() => !!document.querySelector('.card.ops-show')));
  await tapCard();
  ok('再点卡片本体收起菜单', await pg.evaluate(() => !document.querySelector('.card.ops-show')));
  await tapCard();
  ok('再次点卡片重新弹菜单', await pg.evaluate(() => !!document.querySelector('.card.ops-show')));

  ok('无 JS 报错', errs.length === 0, errs.join('; '));
  console.log(`\nRESULT PASS=${pass} FAIL=${fail}`);
  if (fails.length) console.log('FAILED: ' + fails.join(' | '));
  console.log('ACCOUNT=' + uname);
  await b.close();
  process.exit(fail ? 1 : 0);
})().catch(e => { console.error('FATAL', e.message); process.exit(2); });
