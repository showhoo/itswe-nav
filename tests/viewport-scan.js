// viewport-scan.js — 多视口横向溢出 + JS 错误批量扫描（对本地测试实例跑）
// 用法：BASE=http://127.0.0.1:18099 PW_CORE=/path/to/playwright-core node tests/viewport-scan.js
const { chromium } = require(process.env.PW_CORE || 'playwright-core');
const BASE = process.env.BASE || 'http://127.0.0.1:18099';

const SIZES = [
  { w: 320,  h: 568,  name: 'iPhone SE 1st' },
  { w: 375,  h: 667,  name: 'iPhone SE 2/3' },
  { w: 390,  h: 844,  name: 'iPhone 14' },
  { w: 414,  h: 896,  name: 'iPhone 11' },
  { w: 430,  h: 932,  name: 'iPhone 14 Pro Max' },
  { w: 768,  h: 1024, name: 'iPad Mini' },
  { w: 820,  h: 1180, name: 'iPad Air' },
  { w: 1024, h: 1366, name: 'iPad Pro' },
  { w: 1280, h: 800,  name: 'Desktop' },
];
const PAGES = ['/', '/login.php', '/admin.php'];

(async () => {
  const b = await chromium.launch();
  let totalOverflow = 0;

  for (const sz of SIZES) {
    const ctx = await b.newContext({ viewport: { width: sz.w, height: sz.h }, locale: 'zh-CN' });
    const pg = await ctx.newPage();
    for (const path of PAGES) {
      try {
        await pg.goto(BASE + path, { waitUntil: 'load', timeout: 15000 });
      } catch (e) {
        console.log('SKIP', sz.name, path, '(load timeout)');
        continue;
      }
      // admin.php 未登录会 302→login，跳过
      if (path === '/admin.php') {
        const resp = await pg.goto(BASE + '/admin.php', { waitUntil: 'load' }).catch(() => null);
        continue;
      }
      const overflow = await pg.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      const jsErrs = [];
      pg.on('pageerror', e => jsErrs.push(e.message));
      if (overflow > 0) {
        totalOverflow++;
        console.log('OVERFLOW:', sz.name, path, '+' + overflow + 'px');
      }
    }
    await ctx.close();
  }

  // 管理页在 admin 登录后
  const ctx2 = await b.newContext({ viewport: { width: 1280, height: 800 }, locale: 'zh-CN' });
  const pg2 = await ctx2.newPage();
  await pg2.goto(BASE + '/login.php', { waitUntil: 'load' });
  const c = await pg2.evaluate(() => document.querySelector('#f-login input[name=csrf]')?.value);
  await pg2.evaluate(async tok => {
    const fd = new FormData();
    fd.append('csrf', tok); fd.append('act', 'login');
    fd.append('username', 'admin'); fd.append('password', 'itswe');
    const r = await fetch('api.php', { method: 'POST', body: fd });
  }, c);
  await pg2.goto(BASE + '/admin.php', { waitUntil: 'load' });
  // 测多个视口
  for (const sz of SIZES) {
    await pg2.setViewportSize({ width: sz.w, height: sz.h });
    await pg2.waitForTimeout(200);
    const overflow = await pg2.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    if (overflow > 0) {
      totalOverflow++;
      console.log('OVERFLOW:', sz.name, '/admin.php', '+' + overflow + 'px');
    }
  }

  console.log('总横向溢出:', totalOverflow, totalOverflow === 0 ? '✓ 全绿' : '✗ 需修');
  await b.close();
})().catch(e => { console.error('FATAL:', e.message); process.exit(1); });
