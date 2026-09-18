#!/bin/bash
# deploy-ds.sh — 作者自用部署参考脚本（原自用实例/HK 宿主机环境，域名、路径按自己的部署改）
# 亮点可复用：解包 → 逐层版本断言（tar/宿主机/镜像）→ 重建镜像 → 原参数重建容器 → 健康检查，
# 三层断言专治"部署跑的是陈旧源码"问题；通用部署不需要本脚本，docker compose up -d 即可
# 回滚：docker tag itswe-nav:rollback-<日期> itswe-nav:latest 后重跑本脚本
set -eu
BUILD=/data/tmp-itswe-nav-build
DEST=/data/wwwroot/itswe-nav
EXPECT='style.css?v=12'   # 与 www/*.php 引用版本保持一致，改样式后同步更新

cd "$BUILD"
rm -rf deploy && mkdir deploy
tar xzf src.tar.gz -C deploy
V=$(grep -o 'style.css?v=[0-9]*' deploy/itswe-nav/www/register.php)
echo "EXTRACTED=$V"
[ "$V" = "$EXPECT" ] || { echo "ABORT: 解包版本 $V ≠ $EXPECT"; exit 9; }

cd "$DEST"
rm -rf www Dockerfile docker-compose.yml
cp -r "$BUILD/deploy/itswe-nav/www" .
cp "$BUILD/deploy/itswe-nav/Dockerfile" "$BUILD/deploy/itswe-nav/docker-compose.yml" .
V2=$(grep -o 'style.css?v=[0-9]*' www/register.php)
echo "HOST=$V2"
[ "$V2" = "$EXPECT" ] || { echo "ABORT: 宿主机版本 $V2 ≠ $EXPECT"; exit 9; }

docker build -q -t itswe-nav:latest . >/dev/null
VI=$(docker run --rm itswe-nav:latest grep -o 'style.css?v=[0-9]*' /app/www/register.php)
echo "IMAGE=$VI"
[ "$VI" = "$EXPECT" ] || { echo "ABORT: 镜像版本 $VI ≠ $EXPECT"; exit 9; }

docker rm -f itswe-nav >/dev/null
docker run -d --name itswe-nav --user 1000:999 -p 127.0.0.1:18080:8080 \
  -v "$DEST/data:/app/data" -v /var/run/docker.sock:/var/run/docker.sock \
  -e TZ=Asia/Shanghai --restart unless-stopped itswe-nav:latest >/dev/null
sleep 3
docker exec itswe-nav grep -o 'style.css?v=[0-9]*' /app/www/register.php
curl -s -o /dev/null -w 'health=%{http_code}\n' http://127.0.0.1:18080/health.php
echo 'DEPLOY-OK'
