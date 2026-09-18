# 安装详解

前置条件：任何能跑 Docker 的 x86_64 / ARM64 Linux 主机（NAS、VPS、家庭服务器均可）。
面板是**单容器**：PHP 8.3 内置服务器（4 worker）+ SQLite，无外部数据库、零第三方 PHP 依赖。

## Docker Compose（推荐）

```bash
git clone https://github.com/showhoo/itswe-nav.git
cd itswe-nav
docker compose up -d
```

默认配置：端口 `8080`、数据目录 `./data`、挂载 `docker.sock`（Docker 管理功能）、时区 `Asia/Shanghai`、开机自启。

### 常用调整

| 需求 | 做法 |
|---|---|
| 用预构建镜像 | 把 `build: .` 行删掉，`image:` 改为 `ghcr.io/showhoo/itswe-nav:<tag>` |
| 改端口 | `ports: - "9090:8080"`（左边是宿主机端口） |
| 不用 Docker 管理 | 删掉挂载 `docker.sock` 的那一行 |
| 时区改 UTC | 取消注释 `environment:` 里的 `TZ=UTC` |
| 自定义数据库路径 | `DB_PATH=/app/data/my.db` |

首次访问 `http://<主机>:8080` 自动建表并播种管理员 **admin / itswe**（仅空库播种，已有数据不会被覆盖）。

## 不用 Compose

```bash
docker build -t itswe-nav .
docker run -d --name itswe-nav -p 8080:8080 \
  -v ./data:/app/data \
  -v /var/run/docker.sock:/var/run/docker.sock \
  --restart unless-stopped \
  itswe-nav
```

不需要 Docker 管理就删掉 sock 那行。

> ⚠️ 挂载 docker.sock 等于信任面板全部管理员账号（可启停宿主机任意容器）；公网部署请保持强口令并按需关闭注册。

## 非 root 运行（进阶）

默认容器以 root 运行（访问 docker.sock 最省事）。如需非 root：

```bash
GID=$(getent group docker | cut -d: -f3)
docker build --build-arg DOCKER_GID=$GID -t itswe-nav .
chown -R 1000:$GID data          # data 卷需对 uid 1000 可写
docker run -d --user 1000:$GID ... 其余参数同上
```

要点：`data/` 需对 uid 1000 可写；docker.sock 需对该 GID 可读。两条件缺一请保持 root。

## 反向代理

任意反代均可。走 HTTPS 时会话 Cookie 自动附加 `Secure`。关键是**正确传递客户端 IP**（内外网自动判定依赖它）。

### nginx

```nginx
server {
    listen 443 ssl http2;
    server_name nav.example.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;   # 让面板正确置 Cookie Secure 位
        client_max_body_size 12m;   # 适配壁纸上传（≤8MB）
    }
}
```

### Caddy

```caddy
nav.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

Caddy 默认传递 `X-Forwarded-For`；上传大于 8MB 也不受默认限制约束。

## 图标图库

```bash
bash sync-gallery.sh            # 在 compose 所在目录执行（产物落 ./data/gallery）
```

下载 Dashboard Icons（webp，MIT）与 Simple Icons（svg，CC0）两个图标库到本地 `data/gallery/`，
全程约 1–3 分钟（视网络）。重跑即更新到最新图标。

## 升级

```bash
git pull && docker compose up -d --build
```

表结构变更由增量迁移（`PRAGMA user_version`）在首次访问时自动完成：迁移只增不改，
升级后回滚到旧版本代码也能正常运行。

## 备份与恢复

| 方式 | 操作 |
|---|---|
| 后台导出 | 管理后台 → 服务器 → 导出备份（JSON 整库，含密码哈希） |
| 停机冷备 | `docker compose stop` → 拷贝整个 `data/` → `start` |
| 一致性热备 | 容器内执行 SQLite backup API（见 FAQ） |

恢复：空 `data/` 起容器 → 管理后台 → 服务器 → 导入恢复，选择之前导出的 JSON。
