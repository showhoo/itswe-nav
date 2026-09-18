# 参与贡献

欢迎 Issue 与 PR！提交前请读完本文，特别是「测试纪律」。

## 本地开发

```bash
git clone https://github.com/showhoo/itswe-nav.git
cd itswe-nav
docker compose up -d          # 或 docker build -t itswe-nav . && 按需 docker run
bash run-tests-ci.sh          # 全量测试（见下）
```

技术栈：PHP 8.3（内置服务器 4 worker）+ SQLite（PDO，WAL）+ 原生 JS（无框架、无构建步骤）。
UI 设计令牌在 `www/assets/style.css` 顶部定义，改样式请沿用令牌以保证配色与间距一致。

## 测试

| 套件 | 内容 | 入口 |
|---|---|---|
| 覆盖测试 | 约 55 项：认证/CSRF/权限矩阵/多用户隔离/校验/转义/用户管理/限速/i18n | `cov-test.sh` |
| e2e | 约 148 项：注册三件套/找回密码/跨组移动/图库/协议/内外网/备份/导出等全流程 | `e2e-test.sh` |
| 迁移 | v1.0.0 旧库 → 新版自动迁移，数据无损 | `migration-test.sh` |
| 一键 | 全部三套，每套全新容器 + 空数据卷 | `run-tests-ci.sh` |

Playwright 真浏览器回归脚本在 [tests/](tests/)（触屏事件序列、多视口溢出扫描），按需本地运行。

**提交 PR 前请确保 `bash run-tests-ci.sh` 全绿。**

## 测试纪律（血泪教训浓缩）

1. **改 CSS / JS 必须 bump 引用版本号**（`index.php` 里 `style.css?v=N` / `app.js?v=N`），否则用户浏览器吃旧缓存
2. **新增 `t('key')` 必须同步中英字典**，双语都补；JS 侧弹窗/提示文案同样走 `t()`，禁止硬编码中文
3. **多层转手写 PHP 后必跑 `php -l`**；PHP 注释里出现 `?>` 或 `*/` 会静默终止代码块，`php -l` 都查不出
4. **e2e 断言要用渲染态特有串**：`window.__I18N__` 内嵌 JSON 会与页面同文命中，纯 grep 会假阳性
5. **迁移只增不改**：新表/新列必须同时进 `init_schema` 和 migrate 步骤（老库不会执行 init_schema）

## 提交规范

- 提交信息用 `feat:` / `fix:` / `chore:` / `test:` 前缀，一句话说清动机与方案
- 一个 PR 聚焦一件事；顺手的重构请拆开
- 涉及 UI 的改动请附截图（含移动端 390px 宽）

## 展示墙

部署后在 README「展示墙」提交 PR：格式 `[站点名](链接) — 一句话介绍`。

## 安全问题

不要在公开 Issue 披露，走 [SECURITY.zh.md](SECURITY.zh.md) 的私密漏洞报告。
