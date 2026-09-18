# ITSWE-Nav 简约导航 — 单容器镜像（PHP 8.3 内置服务器 + SQLite）
# 无需外部数据库；cURL / pdo_sqlite / fileinfo 均为官方镜像自带，无需额外编译
# 基础镜像按 digest 钉版（供应链稳妥）；升级时改为新 tag 并重建
FROM php:8.3-cli-alpine@sha256:afdf8b1fee58486ccc0dab5f30f634b86873d56dac985f71ba217945647c05ad

LABEL org.opencontainers.image.title="ITSWE-Nav 简约导航" \
      org.opencontainers.image.description="Lightweight self-hosted start page & bookmark dashboard: LAN/WAN dual addresses, server monitoring, Docker management, multi-user. 轻量级自托管导航页：内外网切换 · 服务器监控 · Docker 管理 · 多用户" \
      org.opencontainers.image.licenses="MIT"

WORKDIR /app
COPY www/ /app/www/

# 预创建 nav 用户（uid 1000），供非 root 运行时切换；默认容器仍以 root 运行
# 非 root 用法见 README「非 root 运行」：构建时 --build-arg DOCKER_GID=<宿主机 docker 组 GID>
ARG DOCKER_GID=999
RUN apk add --no-cache tzdata && mkdir -p /app/data \
 && { addgroup -g "$DOCKER_GID" dockersock 2>/dev/null || true; } \
 && adduser -D -u 1000 -G dockersock nav 2>/dev/null || true

# 面向中文用户默认东八区；compose 里设 TZ=UTC 可改
ENV TZ=Asia/Shanghai

# 4 个 worker，避免单线程内置服务器在并发请求时排队
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 8080
VOLUME ["/app/data"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s \
    CMD wget -q -O /dev/null http://127.0.0.1:8080/health.php || exit 1

CMD ["php", "-S", "0.0.0.0:8080", "-t", "/app/www", \
     "-d", "upload_max_filesize=10M", \
     "-d", "post_max_size=12M", \
     "-d", "memory_limit=128M", \
     "-d", "expose_php=Off", \
     "-d", "display_errors=0", \
     "-d", "log_errors=1", \
     "-d", "error_log=/app/data/php-error.log"]
