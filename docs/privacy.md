# Privacy

## Where your data lives

All runtime data stays in your own `data/` directory: the SQLite database (users, groups, cards, prefs), uploaded wallpapers and icons, the favicon cache, and the icon gallery. The panel contains **no analytics, telemetry, or remote-check component** — there is no "phone home" code.

## Outbound requests the panel makes

| Scenario | Target | Notes |
|---|---|---|
| Auto card icons (cards without a manually set icon) | the target site's `/favicon.ico`, its homepage, `favicon.im` (fallback) | proxied by the server-side `favicon.php` and cached locally for 30 days; visitor browsers never connect to third parties |
| `iconify:` icon identifiers | `api.iconify.design` | only when a card icon is written as an `iconify:` prefix; the browser loads that open-source icon directly |
| Gallery sync (optional, manual) | `codeload.github.com` | only when the operator runs `sync-gallery.sh` on the Docker host to download the two open-source icon libraries; never happens at runtime |

Beyond the table above, the panel makes **no other outbound requests** while running.

### Note: which hosts the panel may contact for card icons

To keep server-side fetch limited (and avoid arbitrary external calls), `favicon.php` will only fetch a host that is **either a built-in recommended-site seed domain, or a domain referenced by a card saved in the panel by any logged-in user**. That means a user could add a card pointing at an internal address and cause the panel's server to send a favicon request to that host. The response is constrained to an image (bounded size, cached, SVG served with `default-src 'none'`), and is only a best-effort probe — no page content is read or returned. If you run the panel on a network with sensitive internal hosts and allow arbitrary users to add cards, be aware of this surface; it is the only way any user can trigger a server-side request to an arbitrary host.

## Requests your browser makes

- Cards you click, iframe previews (📱 button) — sites you actively visit
- Icons of cards that use an `iconify:` identifier

## Sessions & credentials

- Session cookie: `HttpOnly + SameSite=Lax`, `Secure` added automatically over HTTPS
- Passwords: stored as bcrypt hashes; e-mail verification codes are stored as hashes, valid 10 minutes, attempt-limited
- Full backup exports (JSON) **include password hashes** — keep them safe

## Footer credit

"Powered by" is the project's only promotional slot: purely static, no tracking parameters.
