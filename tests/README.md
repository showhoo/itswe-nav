# tests/ — dev & test helper scripts

The main test entry points live at the repo root: `cov-test.sh` (~55 coverage checks), `e2e-test.sh` (~148 end-to-end), `migration-test.sh` (v1 legacy DB migration), the one-shot `run-tests-ci.sh` (works locally / CI) and `run-tests.sh` (author's server only, with stale-source assertions).

Helpers in this directory:

| File | Purpose |
|---|---|
| `sim-code.php` / `credit.php` / `test-old-db.php` | Small helpers `docker cp`'d into the container by the test suites: seed a known e-mail code / manipulate credit seed & login-throttle tables / build a v1.0.0-era legacy DB |
| `touch-edit-test.js` / `viewport-scan.js` / `mobile-full-test.js` | Playwright real-browser regressions (touch-edit event sequence / multi-viewport overflow scan / mobile full flow). Env vars `BASE` (target instance, default `http://127.0.0.1:18099`), `PW_CORE` (path to playwright-core), `CHROME_EXE` (browser executable) |
| `selftest.php` / `verify.php` | Deployment smoke pages: `docker cp` into the container's `www/`, open in a browser to see results, **delete afterwards** |
