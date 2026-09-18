# Installation

Prerequisites: any Linux host that runs Docker on x86_64 / ARM64 (NAS, VPS, home server). The panel is a **single container**: PHP 8.3 built-in server (4 workers) + SQLite — no external database, zero third-party PHP dependencies.

## Docker Compose (recommended)

```bash
git clone https://github.com/showhoo/itswe-nav.git
cd itswe-nav
docker compose up -d
```

Defaults: port `8080`, data dir `./data`, `docker.sock` mounted (Docker management), timezone `Asia/Shanghai`, restart policy `unless-stopped`.

### Common tweaks

| Need | How |
|---|---|
| Use a prebuilt image | Delete the `build: .` line and set `image: ghcr.io/showhoo/itswe-nav:<tag>` |
| Change port | `ports: - "9090:8080"` (left side is the host port) |
| Skip Docker management | Remove the `docker.sock` mount line |
| Switch to UTC | Uncomment `TZ=UTC` under `environment:` |
| Custom DB path | `DB_PATH=/app/data/my.db` |

On first visit, `http://<host>:8080` creates the schema and seeds the admin account **admin / itswe** (seeded only on an empty database; existing data is never overwritten).

## Without Compose

```bash
docker build -t itswe-nav .
docker run -d --name itswe-nav -p 8080:8080 \
  -v ./data:/app/data \
  -v /var/run/docker.sock:/var/run/docker.sock \
  --restart unless-stopped \
  itswe-nav
```

Drop the sock line if you don't need Docker management.

> ⚠️ Mounting `docker.sock` grants every panel admin the ability to start/stop arbitrary host containers. On public deployments keep strong passwords and disable registration as needed.

## Non-root (advanced)

The container runs as root by default (simplest for docker.sock access). To run non-root:

```bash
GID=$(getent group docker | cut -d: -f3)
docker build --build-arg DOCKER_GID=$GID -t itswe-nav .
chown -R 1000:$GID data          # data volume must be writable by uid 1000
docker run -d --user 1000:$GID ... rest of the args as above
```

Notes: `data/` must be writable by uid 1000; docker.sock must be readable by that GID. Keep root if either condition can't be met.

## Reverse proxy

Any reverse proxy works. When running over HTTPS the session cookie automatically gets `Secure`. The key thing is to **pass the real client IP** (the LAN/WAN auto-detection relies on it).

### nginx

```nginx
server {
    listen 443 ssl http2;
    server_name nav.example.com;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;   # lets the panel set the Cookie Secure bit correctly
        client_max_body_size 12m;   # fits wallpaper uploads (≤8MB)
    }
}
```

### Caddy

```caddy
nav.example.com {
    reverse_proxy 127.0.0.1:8080
}
```

Caddy passes `X-Forwarded-For` by default and has no restrictive upload-size default.

## Icon gallery

```bash
bash sync-gallery.sh            # run in the compose directory (outputs to ./data/gallery)
```

Downloads Dashboard Icons (webp, MIT) and Simple Icons (svg, CC0) into `data/gallery/` — about 1–3 minutes depending on your network. Re-run to update to the latest icons.

## Upgrading

```bash
git pull && docker compose up -d --build
```

Schema changes apply automatically through incremental migrations (`PRAGMA user_version`) on first request: migrations only add — rolling back to an older release keeps working.

## Backup & restore

| Method | How |
|---|---|
| Admin export | Admin panel → Server → Export backup (full JSON, includes password hashes) |
| Cold copy | `docker compose stop` → copy the whole `data/` → `start` |
| Consistent hot backup | Run the SQLite backup API inside the container (see FAQ) |

Restore: start a container with an empty `data/` → Admin panel → Server → Import, then pick the previously exported JSON.
