# Configuration

## Environment variables (container)

| Variable | Default | Notes |
|---|---|---|
| `TZ` | `Asia/Shanghai` | Container timezone (affects creation-time display etc.) |
| `DB_PATH` | `/app/data/itswe-nav.db` | SQLite database path (usually inside the volume) |
| `PHP_CLI_SERVER_WORKERS` | `4` | Built-in PHP server worker count |

## Ports & data

| Item | Notes |
|---|---|
| `8080` | HTTP port (inside container), mapped to the host via compose |
| `./data` | **All runtime data**: SQLite, uploaded wallpapers/icons, favicon cache, icon gallery |
| `/var/run/docker.sock` | Optional mount; provides the Docker admin page (container list / start-stop) |

## Site settings (Admin panel → Site settings, admin only)

| Setting | Default | Notes |
|---|---|---|
| Site name (Chinese) | `思维简约导航` | Page title & footer; ≤32 chars |
| Site name (English) | empty | Shown in the English UI; falls back to Chinese name when unset |
| Site URL | empty | Click-through link for the footer site name (optional) |
| Interface language | `auto` | `auto` (follow browser) / `zh-CN` / `en`; `?lang=` URL param takes precedence |
| Open registration | on | When off, only admins can create accounts in the panel |
| LAN ranges | `192.168.0.0/16,10.0.0.0/8,172.16.0.0/12` | CIDRs, comma-separated; basis of LAN/WAN "Auto" detection |
| Server status strip | on | Resource pills below the panel search box |
| E-mail verification | off | Registration requires an e-mail code; requires SMTP configured first |
| SMTP host/port/user/pass/from/encryption | empty / `465` / … | Used for registration codes & password recovery; `ssl` / `tls` / `none` |

> The `?lang=en|zh` URL param overrides the interface language statelessly (above site settings and browser detection), handy for previewing and sharing.

## Personal settings (⚙ dialog, all logged-in users)

| Setting | Notes |
|---|---|
| Panel title | Per-user title, defaults to the site name |
| Wallpaper | 6 gradient presets / URL / local upload (≤8MB, available to all logged-in users) |
| Search engine | Bing / Baidu / Google (English UI hides Baidu; guests default to Google) |
| Change password | Requires current password + new password ≥6 chars |

## LAN/WAN switching logic

When a card has both an external and an internal URL, the 🌐 button picks one of three:

| Mode | Behavior |
|---|---|
| Auto | Client IP in the LAN ranges → use internal URL, otherwise external (behind a proxy reads first `X-Forwarded-For` IP) |
| LAN | Force internal URL for all dual-address cards |
| WAN | Force external URL for all dual-address cards |

## Footer credit

"Powered by + icon" lives in site-level prefs (`credit_*`); the admin UI deliberately offers no switch.
Deleting the rows falls back to the code defaults; clearing them hides the footer and shows a local-only missing-credit notice in the admin panel (no reporting). The default link points to [www.itswe.com](https://www.itswe.com).
