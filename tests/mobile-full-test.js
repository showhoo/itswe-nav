// mobile-full-test.js — ITSWE-Nav 移动端全流程 5 轮 × 3 设备矩阵
// 在 HK 服务器运行（Playwright 路径 /data/dsh/node_modules/playwright）
const { chromium } = require('/data/dsh/node_modules/playwright');
const BASE = 'http://127.0.0.1:18099';
const DEVICES = [
  { name: 'iPhone-SE',   vp: { width: 375, height: 667 } },
  { name: 'iPhone-14',   vp: { width: 390, height: 844 } },
  { name: 'iPad-Mini',   vp: { width: 768, height: 1024 } },
];
const ROUNDS = 5;
let totalPass = 0, totalFail = 0;
const allFails = [];

function ok(name, cond, detail) {
  if (cond) { totalPass++; console.log('  ✓ ' + name); }
  else { totalFail++; allFails.push(name + (detail ? ' [' + detail + ']' : '')); console.log('  ✗ ' + name + (detail ? ' [' + detail + ']' : '')); }
}

(async () => {
  const b = await chromium.launch();

  for (let round = 1; round <= ROUNDS; round++) {
    for (const dev of DEVICES) {
      const tag = `R${round}/${dev.name}`;
      console.log(`\n--- ${tag} ---`);
      const ctx = await b.newContext({
        viewport: dev.vp, locale: 'zh-CN', hasTouch: true, isMobile: dev.vp.width < 768,
      });
      const pg = await ctx.newPage();
      const errs = [];
      pg.on('pageerror', e => errs.push(e.message));
      const uname = `mx${round}${dev.vp.width}${Date.now() % 1000}`;

      // 1. 注册
      await pg.goto(BASE + '/login.php', { waitUntil: 'load', timeout: 10000 });
      await pg.click('button[data-tab="register"]');
      await pg.waitForSelector('#f-register:not(.hidden)', { timeout: 5000 });
      await pg.fill('#f-register input[name=username]', uname);
      await pg.fill('#f-register input[name=password]', 'mx123456');
      await pg.click('#f-register button.primary');
      let navOk = true;
      await pg.waitForURL('**/index.php', { timeout: 8000 }).catch(() => { navOk = false; });
      ok(`${tag} 注册302`, navOk);
      if (!navOk) { await ctx.close(); continue; }

      // 2. 主面板渲染
      await pg.waitForLoadState('load');
      ok(`${tag} 编辑按钮`, await pg.evaluate(() => !!document.getElementById('btn-edit-mode')));
      ok(`${tag} ⚙设置按钮`, await pg.evaluate(() => !!document.getElementById('btn-pw')));
      ok(`${tag} 🌐内外网按钮`, await pg.evaluate(() => !!document.getElementById('btn-netmode')));
      ok(`${tag} 页脚署名`, await pg.evaluate(() => document.body.innerHTML.includes('Powered by')));

      // 3. 编辑模式
      await pg.click('#btn-edit-mode');
      ok(`${tag} 编辑模式激活`, await pg.evaluate(() => document.body.classList.contains('edit-mode')));

      // 4. 建组
      await pg.click('#btn-add-group');
      await pg.fill('#dlg-group input[name=name]', 'G' + round);
      await pg.click('#dlg-group button.primary');
      await pg.waitForSelector('body.edit-mode .group-sec', { timeout: 8000 });
      const gid = await pg.evaluate(() => document.querySelector('.group-sec')?.dataset.gid);
      ok(`${tag} 建组 gid=${gid}`, !!gid);

      // 5. 加卡×2
      for (const t of ['卡1', '卡2']) {
        await pg.click('[data-add-item]');
        await pg.fill('#dlg-item input[name=title]', t);
        await pg.fill('#dlg-item input[name=url]', t.toLowerCase() + '.example.com');
        await pg.click('#dlg-item button.primary');
        await pg.waitForFunction(n => document.querySelectorAll('.card[data-id]').length >= n, parseInt(t.slice(-1)), { timeout: 8000 });
      }
      const cardCount = await pg.evaluate(() => document.querySelectorAll('.card[data-id]').length);
      ok(`${tag} 两卡渲染 (${cardCount})`, cardCount >= 2);

      // 6. 图标走本地代理
      const iconSrc = await pg.evaluate(() => document.querySelector('.card[data-id] img.ic')?.src || '');
      ok(`${tag} 图标走本地代理`, iconSrc.includes('favicon.php') || iconSrc.includes('itswe-icon'), iconSrc.substring(0, 50));

      // 7. 编辑模式退出
      await pg.click('#btn-edit-mode');
      const editOff = await pg.evaluate(() => !document.body.classList.contains('edit-mode'));
      ok(`${tag} 编辑模式退出`, editOff);

      // 8. 编辑模式再进
      await pg.click('#btn-edit-mode');
      ok(`${tag} 编辑模式再进`, await pg.evaluate(() => document.body.classList.contains('edit-mode')));

      // 9. 触屏拖拽（第一张拖到第二张后）
      const orderBefore = await pg.evaluate(() => [...document.querySelectorAll('.card[data-id]')].map(c => c.querySelector('b')?.textContent || '').join(','));
      const cdp = await ctx.newCDPSession(pg);
      const cardBoxes = await pg.evaluate(() => [...document.querySelectorAll('.card[data-id]')].map(c => {
        const r = c.getBoundingClientRect();
        return { x: r.x + r.width / 2, y: r.y + r.height / 2 };
      }));
      if (cardBoxes.length >= 2) {
        const s = cardBoxes[0], d = cardBoxes[1];
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: s.x, y: s.y }] });
        await pg.waitForTimeout(80);
        for (let i = 1; i <= 8; i++) {
          await cdp.send('Input.dispatchTouchEvent', {
            type: 'touchMove',
            touchPoints: [{ x: s.x + (d.x - s.x) * i / 8, y: s.y + (d.y - s.y) * i / 8 }],
          });
          await pg.waitForTimeout(50);
        }
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await pg.waitForTimeout(600);
        const orderAfterDrag = await pg.evaluate(() => [...document.querySelectorAll('.card[data-id]')].map(c => c.querySelector('b')?.textContent || '').join(','));
        ok(`${tag} 触屏拖拽排序变化`, orderAfterDrag !== orderBefore, orderBefore + ' → ' + orderAfterDrag);
      } else {
        ok(`${tag} 卡片数≥2`, false, '卡片不足');
      }

      // 10. 持久化
      await pg.reload({ waitUntil: 'load' });
      await pg.click('#btn-edit-mode');
      const orderPersisted = await pg.evaluate(() => [...document.querySelectorAll('.card[data-id]')].map(c => c.querySelector('b')?.textContent || '').join(','));
      ok(`${tag} 排序持久化`, orderPersisted === orderAfterDrag || orderPersisted !== orderBefore, orderPersisted);

      // 11. 状态条
      const srv = await pg.evaluate(() => !!document.getElementById('srv-status'));
      ok(`${tag} 状态条渲染`, srv);

      // 12. JS 错误
      ok(`${tag} 无 JS 错误`, errs.length === 0, errs.join('|'));

      await ctx.close();
    }
  }

  console.log('\n========== 总计 ==========');
  console.log(`PASS: ${totalPass}  FAIL: ${totalFail}`);
  if (allFails.length) { console.log('失败项:'); allFails.forEach(f => console.log('  ✗ ' + f)); }
  await b.close();
  process.exit(totalFail);
})().catch(e => { console.error('FATAL:', e.message.split('\n')[0]); process.exit(1); });
