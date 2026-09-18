# Security Policy

## Reporting a vulnerability

**Do not** disclose security vulnerabilities in public issues.

Use GitHub **private vulnerability reporting**: repo page → **Security** tab → **Report a vulnerability**. We will reply and follow up as soon as possible.

## Supported scope

Only the default deployment of the latest release (single container, SQLite).

## Security design overview

- Passwords hashed with bcrypt; session cookie `HttpOnly + SameSite=Lax`, `Secure` added automatically under HTTPS
- Every write operation goes through CSRF validation; login rate-limited (username + IP, 10-minute window)
- Uploads: MIME sniffed + random file names, stored outside the docroot and served through a whitelist outlet
- Gallery files served with `default-src 'none'` CSP for SVG
- The panel embeds no analytics, telemetry, or remote checks
