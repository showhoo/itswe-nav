/* dsbg.js — 首屏背景：淡蓝流体云雾
 *
 * 自研实现（不引用 DeepSeek 官网任何脚本、图片或字体资源）：
 * 值噪声 + 域扭曲（domain warping）：低频场先给出扭曲向量，再去采样被扭曲的
 * 密度场，得到连续翻滚的丝絮状云雾。让「流动」看得见的关键是采样坐标以恒定
 * 速度平移，同时扭曲场自身缓慢演化 —— 于是整片雾持续向右上漂移、边漂边变形
 * （纯噪声求值，不累积，因此不会像平流反馈那样越跑越糊）。
 *
 * 性能：低分辨率（约 1.5 万格）逐像素求值后平滑放大铺满全屏；24fps；
 * 页面不可见时停算；respects prefers-reduced-motion（只画静态一帧）。
 *
 * 约定：页面里放一个 .ds-bg 容器，内含一个 <canvas>。
 */
(function () {
  'use strict';

  var host = document.querySelector('.ds-bg');
  if (!host) return;
  var canvas = host.querySelector('canvas');
  if (!canvas || !canvas.getContext) return;

  var ctx = canvas.getContext('2d');
  var media = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  var reduce = !!(media && media.matches);

  /* —— 噪声表（位势场与初始密度都用它） —— */
  var P = new Uint8Array(512);
  var V = new Float32Array(256);
  (function () {
    var a = new Uint8Array(256), i, j, t;
    for (i = 0; i < 256; i++) { a[i] = i; V[i] = Math.random(); }
    for (i = 255; i > 0; i--) { j = (Math.random() * (i + 1)) | 0; t = a[i]; a[i] = a[j]; a[j] = t; }
    for (i = 0; i < 512; i++) P[i] = a[i & 255];
  })();

  function noise(x, y) {
    var xi = Math.floor(x), yi = Math.floor(y);
    var xf = x - xi, yf = y - yi;
    var u = xf * xf * (3 - 2 * xf), v = yf * yf * (3 - 2 * yf);
    var X = xi & 255, Y = yi & 255;
    var aa = V[(P[X] + Y) & 255], ba = V[(P[(X + 1) & 255] + Y) & 255];
    var ab = V[(P[X] + Y + 1) & 255], bb = V[(P[(X + 1) & 255] + Y + 1) & 255];
    var x1 = aa + (ba - aa) * u, x2 = ab + (bb - ab) * u;
    return x1 + (x2 - x1) * v;
  }

  function fbm(x, y) {
    return noise(x, y) * 0.65 + noise(x * 2.03, y * 2.01) * 0.35;
  }

  var W = 0, H = 0, dpr = 1;
  var off = document.createElement('canvas');
  var octx = off.getContext('2d');
  var img = null;
  var SW = 0, SH = 0;                 /* 渲染分辨率（格） */
  var t = 0, last = 0, raf = 0;

  var FRAME = 1000 / 24;              /* 24fps 足够表现流动，且省电 */

  function pickScale() {
    var budget = 15000;
    var nav = navigator || {};
    if (nav.hardwareConcurrency && nav.hardwareConcurrency <= 4) budget = 8000;
    if (nav.connection && nav.connection.saveData) budget = 6000;
    var s = Math.round(Math.sqrt((W * H) / budget));
    return Math.max(4, Math.min(14, s || 10));
  }

  function alloc() {
    off.width = SW;
    off.height = SH;
    img = octx.createImageData(SW, SH);
  }

  function resize() {
    W = host.clientWidth || window.innerWidth || 1;
    H = host.clientHeight || window.innerHeight || 1;
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.max(1, Math.round(W * dpr));
    canvas.height = Math.max(1, Math.round(H * dpr));
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    var s = pickScale();
    SW = Math.max(8, Math.round(W / s));
    SH = Math.max(8, Math.round(H / s));
    alloc();
    ctx.imageSmoothingEnabled = true;
    if ('imageSmoothingQuality' in ctx) ctx.imageSmoothingQuality = 'high';
    draw();
  }

  /* 密度 → 颜色（近白→淡蓝→蓝），再整帧放大铺满 */
  function draw() {
    if (!W || !H || !img) return;

    var n1 = 1 / Math.min(SW, SH);
    var T = t * 0.001;                                 /* 秒 */
    var sc = 2.6 * n1;                                 /* 特征尺度 */
    var wsc = sc * 0.55;
    var du = T * 0.26;                                 /* 每秒漂移（噪声单位） */
    var dv = T * -0.10;                                /* 负值=向上，像雾气升腾 */
    var evo = T * 0.06;                                /* 扭曲场自身演化 */
    var WARP = 2.7;
    var d = img.data, x, y, i, o;
    var invH = 1 / SH;

    for (y = 0; y < SH; y++) {
      var v = y * sc;
      var vw = y * wsc;
      var fall = 1 - 0.42 * (y * invH);
      for (x = 0, i = y * SW, o = i * 4; x < SW; x++, i++, o += 4) {
        var u = x * sc;
        var uw = x * wsc;
        /* 域扭曲：低频场先给出扭曲向量，再采样被扭曲的密度场；两者都在平移演化 */
        var q1 = fbm(uw, vw + evo);
        var q2 = fbm(uw + 5.2, vw + 1.3 - evo * 0.8);
        var dens = fbm(u + du + WARP * q1, v + dv + WARP * q2);

        dens = dens * dens * (3 - 2 * dens);           /* 提对比，出丝絮感 */
        var a = dens * 0.5 * fall;
        if (a < 0.012) { d[o + 3] = 0; continue; }
        var c = dens * 1.35 > 1 ? 1 : dens * 1.35;
        d[o]     = 205 - 85 * c;
        d[o + 1] = 222 - 62 * c;
        d[o + 2] = 245 - 22 * c;
        d[o + 3] = a > 1 ? 255 : (a * 255) | 0;
      }
    }
    octx.putImageData(img, 0, 0);
    ctx.clearRect(0, 0, W, H);
    ctx.drawImage(off, 0, 0, SW, SH, 0, 0, W, H);
  }

  function tick(now) {
    raf = window.requestAnimationFrame(tick);
    if (document.hidden) return;
    if (now - last < FRAME) return;
    last = now;
    t = now;
    draw();
  }

  resize();
  if (!reduce) raf = window.requestAnimationFrame(tick);

  var rt = 0;
  window.addEventListener('resize', function () {
    clearTimeout(rt);
    rt = setTimeout(resize, 180);
  });
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !reduce) { last = 0; draw(); }
  });
})();
