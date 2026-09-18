#!/bin/bash
# 标准化构建+测试：强制新解包 + 防陈旧断言 + 覆盖测试 + e2e 两段式
set -e
cd /data/tmp-itswe-nav-build
rm -rf itswe-nav data covdata
tar xzf src.tar.gz
cd itswe-nav
grep -q 'function pickInsertRef' www/assets/app.js || { echo 'STALE: app.js'; exit 9; }
grep -q 'function t(' www/lib/i18n.php || { echo 'STALE: i18n.php'; exit 9; }
grep -q 'Start your browsing here' www/lib/i18n.php || { echo 'STALE: i18n.php 未含英文字典'; exit 9; }
grep -q 'data-move-up' www/index.php    || { echo 'STALE: index.php'; exit 9; }
grep -q 'login_throttle' www/lib/db.php || { echo 'STALE: db.php'; exit 9; }
# 测试脚本随包同步（防顶层陈旧副本：cov/e2e 必须与本次解包的源码同版本）
cp cov-test.sh /data/tmp-itswe-nav-build/cov-test.sh
cp e2e-test.sh /data/tmp-itswe-nav-build/e2e.sh
mkdir -p /data/tmp-itswe-nav-build/tests
cp tests/sim-code.php tests/credit.php /data/tmp-itswe-nav-build/tests/ 2>/dev/null || cp sim-code.php credit.php /data/tmp-itswe-nav-build/tests/
docker build -q -t itswe-nav:latest .
RUN_SUITE() {   # RUN_SUITE <数据目录> <测试脚本>
  docker rm -f itswe-nav-test 2>/dev/null || true
  rm -rf "$1"
  mkdir -p "$1"
  docker run -d --name itswe-nav-test -p 127.0.0.1:18099:8080 \
    -v "$1":/app/data \
    -v /var/run/docker.sock:/var/run/docker.sock itswe-nav:latest >/dev/null
  sleep 2
  bash "/data/tmp-itswe-nav-build/$2"
}
echo '===== 覆盖测试 ====='
RUN_SUITE /data/tmp-itswe-nav-build/covdata cov-test.sh || COVFAIL=1
echo '===== e2e ====='
RUN_SUITE /data/tmp-itswe-nav-build/data e2e.sh || E2EFAIL=1
docker rm -f itswe-nav-test >/dev/null 2>&1 || true
[ "${COVFAIL:-0}" = 0 ] && [ "${E2EFAIL:-0}" = 0 ] || exit 1
