# Contributing

Welcome! Issues and PRs are appreciated. Please read this before submitting, especially the **Testing discipline** section.

## Local development

```bash
git clone https://github.com/showhoo/itswe-nav.git
cd itswe-nav
docker compose up -d          # or docker build -t itswe-nav . && run as needed
bash run-tests-ci.sh          # full test suite (see below)
```

Tech stack: PHP 8.3 (built-in server, 4 workers) + SQLite (PDO, WAL) + vanilla JS (no framework, no build step).
UI design tokens live at the top of `www/assets/style.css` — reuse the tokens so colors and spacing stay consistent.

## Tests

| Suite | Contents | Entry |
|---|---|---|
| Coverage | ~55 checks: auth / CSRF / permission matrix / multi-user isolation / validation / escaping / user management / rate limit / i18n | `cov-test.sh` |
| e2e | ~148 checks: registration trio / password recovery / cross-group move / gallery / terms / LAN+WAN / backup / export | `e2e-test.sh` |
| Migration | v1.0.0 legacy DB → automatic in-app migration, no data loss | `migration-test.sh` |
| One-shot | All three above, each on a fresh container + empty volume | `run-tests-ci.sh` |

Playwright browser regression scripts live in [tests/](tests/) (touch event sequence, multi-viewport overflow scan), run locally as needed.

**Make sure `bash run-tests-ci.sh` is fully green before submitting a PR.**

## Testing discipline (hard-won lessons)

1. **Any CSS / JS change must bump the asset version** (`style.css?v=N` / `app.js?v=N` in `index.php`), otherwise browsers keep serving stale cache
2. **Any new `t('key')` must be added to BOTH the Chinese and English dictionaries**; JS dialogs/toasts must use `t()` too, never hardcode Chinese
3. **After writing PHP through multiple hops, always run `php -l`**; a `?>` or `*/` inside a PHP comment silently breaks the code block, and even `php -l` won't always catch it
4. **e2e assertions should use render-specific markers**: `window.__I18N__` embeds the same strings as the page, so a plain grep can give false positives
5. **Migrations only add, never modify**: new tables/columns must go into BOTH `init_schema` and the migrate steps (legacy DBs never run `init_schema`)

## Commit conventions

- Prefix with `feat:` / `fix:` / `chore:` / `test:` and describe the motivation and approach in one line
- One PR = one focused thing; split incidental refactors
- Include screenshots for UI changes (mobile 390px width included)

## Showcase

Deployed with the footer credit kept? Submit a PR to add your site to the README "Showcase" (format: `[Site](link) — one-line description`).

## Security

Do not disclose vulnerabilities in public issues. Use GitHub private vulnerability reporting via [SECURITY.md](SECURITY.md).