#!/bin/bash
# run-tests-ci.sh — 可移植全量测试入口（本地有 docker 的机器 / GitHub Actions 通用）
# 与 run-tests.sh（HK/156 专用，带 tar 解包防陈旧断言）互补：本脚本假定当前目录即最新源码。
# 用法：bash run-tests-ci.sh                # 全量：覆盖 + e2e + 老库迁移
#       bash run-tests-ci.sh cov e2e       # 只跑指定套件（cov|e2e|migration）
set -eu
IMG=itswe-nav:ci
NAME=itswe-nav-test
trap 'docker rm -f $NAME >/dev/null 2>&1 || true' EXIT   # set -e 中断也保证收尾
PORT=18099
SUITES="${*:-cov e2e migration}"

cd "$(dirname "$0")"
docker build -q -t "$IMG" .

SOCK=""
[ -S /var/run/docker.sock ] && SOCK="-v /var/run/docker.sock:/var/run/docker.sock"

clean_dir() {  # clean_dir <数据目录> — 套件上传文件属容器 root，宿主普通用户删不掉，借镜像内 root 清理
  [ -d "$1" ] || return 0
  docker run --rm -v "$(pwd)/$1":/d "$IMG" find /d -mindepth 1 -maxdepth 1 -exec rm -rf {} + >/dev/null 2>&1 || true
  rmdir "$1" 2>/dev/null || true
}

start_fresh() {  # start_fresh <数据目录>
  docker rm -f $NAME >/dev/null 2>&1 || true
  clean_dir "$1"
  mkdir -p "$1"
  docker run -d --name $NAME -p 127.0.0.1:$PORT:8080 \
    -v "$(pwd)/$1":/app/data $SOCK "$IMG" >/dev/null
  sleep 3
}

for s in $SUITES; do
  echo "===== 套件: $s ====="
  case $s in
    cov)
      start_fresh .tmp-cov
      bash cov-test.sh
      ;;
    e2e)
      start_fresh .tmp-e2e
      bash e2e-test.sh
      ;;
    migration)
      start_fresh .tmp-mig
      docker cp tests/test-old-db.php $NAME:/tmp/test-old-db.php
      docker exec $NAME php /tmp/test-old-db.php
      curl -s -o /dev/null "http://127.0.0.1:$PORT/"   # 首访触发自动迁移
      bash migration-test.sh
      ;;
    *) echo "unknown suite: $s"; exit 1 ;;
  esac
done

docker rm -f $NAME >/dev/null 2>&1 || true
clean_dir .tmp-cov
clean_dir .tmp-e2e
clean_dir .tmp-mig
echo "===== ALL SUITES DONE ====="
