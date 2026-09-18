# Changelog

All notable changes are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

- *(no unreleased changes yet)*

## [v1.0.0-beta] - 2026-09-18

First public preview. Single-container deployment (PHP 8.3 + SQLite), zero third-party PHP dependencies.

### Added
- **Groups & cards**: unlimited groups, notes, drag-and-drop sorting (nearest-center prediction) / cross-group moves / keyboard ↑↓ nudging; long-press to edit on mobile
- **LAN/WAN switching**: every card can hold both an external and an internal URL; the 🌐 button cycles Auto (CIDR detection) / LAN / WAN instantly (no reload); Auto mode honours `X-Forwarded-For`
- **Multi-user**: a built-in admin (admin / itswe) is created on first visit; registration toggle, registered users are regular users (if every admin is removed, the next registration takes over for recovery); dedicated sign-up page with optional e-mail, terms of service, e-mail-verified password recovery; roles, create / reset / disable / delete accounts
- **Guest homepage**: browse recommended sites + search for anonymous visitors (separate EN/ZH panels), sign-up prompt
- **Server monitoring**: CPU, memory, disk, network throughput, load, uptime; status strip polls every 30 s
- **Docker management**: container list, state, port mappings, start/stop/restart (zero-dependency Engine API client, adapts to daemon version)
- **Icon gallery**: Dashboard Icons (4300+ webp) + Simple Icons (3400+ svg) served locally, searchable/filterable/paginated picker
- **Icon fallback chain**: proxied favicon → upload → gallery → iconify prefix → initial letter
- **Interface language**: Chinese / English, browser-detected by default, overridable with `?lang=en|zh`
- **Appearance**: 6 gradient wallpapers, wallpaper via URL or upload; dark mode follows the system
- **Data**: JSON backup export/import, card export to CSV / Netscape HTML, in-app iframe preview
- **Security**: CSRF everywhere, bcrypt, login rate limiting, upload MIME sniffing + random file names, security headers, incremental automatic migrations (`PRAGMA user_version`, upgrade-safe and rollback-friendly)

### Tests
- 55 coverage + 148 e2e (incl. password-recovery flow, data export, cross-group move regression) + 8 migration assertions (`run-tests-ci.sh` one-shot)

[v1.0.0-beta]: https://github.com/showhoo/itswe-nav/releases/tag/v1.0.0-beta
