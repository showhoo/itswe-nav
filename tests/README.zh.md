# tests/ — 开发与测试辅助脚本

正式测试入口在仓库根：`cov-test.sh`（约 55 项覆盖）、`e2e-test.sh`（约 148 项端到端）、
`migration-test.sh`（v1 旧库迁移）、一键入口 `run-tests-ci.sh`（本地/CI 通用）与
`run-tests.sh`（作者服务器专用，含解包防陈旧断言）。

本目录存放配套辅助：

| 文件 | 用途 |
|---|---|
| `sim-code.php` / `credit.php` / `test-old-db.php` | 被测试套件 `docker cp` 进容器执行的小助手：播种已知邮箱验证码 / 操作署名种子与登录限速表 / 构造 v1.0.0 时代旧库 |
| `touch-edit-test.js` / `viewport-scan.js` / `mobile-full-test.js` | Playwright 真浏览器回归（触屏编辑事件序列 / 多视口溢出扫描 / 移动端全流程）。环境变量 `BASE`（目标实例，默认 `http://127.0.0.1:18099`）、`PW_CORE`（playwright-core 路径）、`CHROME_EXE`（浏览器可执行文件） |
| `selftest.php` / `verify.php` | 部署冒烟页：`docker cp` 进容器 `www/` 后浏览器打开看结果，**看完即删** |
