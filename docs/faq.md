# FAQ

### 1. What is the default admin account?
`admin / itswe` (seeded on the first visit to an empty database). **Change the password immediately** after logging in; existing data is never overwritten by the weak default.

### 2. The "Pick from gallery" is empty?
The icon gallery needs one run of `bash sync-gallery.sh` on the Docker host first (downloads 7700+ icons into `data/gallery/`). Without it, the other icon sources still work (auto-fetch / upload / iconify identifiers).

### 3. Auto LAN/WAN detection picks the wrong address?
- Make sure your reverse proxy passes `X-Forwarded-For` (the panel judges the first IP)
- Check the LAN ranges in site settings (CIDRs, comma-separated; default `192.168.0.0/16,10.0.0.0/8,172.16.0.0/12`)
- Docker's default bridge (172.17.x.x) is container-network; for direct host access the panel uses the real source IP

### 4. I can't see server monitoring / Docker management?
Both are under the admin panel's **Server** tab (admins only). The panel status strip can be toggled in site settings. Docker management requires mounting `/var/run/docker.sock`; without it a friendly notice is shown and everything else keeps working.

### 5. No verification e-mail when registering / recovering a password?
Configure SMTP in site settings first (host/port/user/pass/encryption; SSL, TLS and plain are supported). Without SMTP, registration and recovery still work — just without the e-mail-verification step. Enabling "e-mail verification" requires SMTP to be configured and working.

### 6. I forgot the admin password?
- If another admin exists: ask them to reset it
- Otherwise: restore from a full backup, or stop the container and edit `data/itswe-nav.db` to replace that user's password hash (bcrypt)

### 7. How do I back up and restore?
See [install.md backups](install.md#backup--restore). Key point: SQLite runs in WAL mode — prefer the admin "Export backup" (full JSON) or a stopped-container cold copy over `cp`-ing a live database file. Backups contain all user data (email included since v1.0.1). Backups exported by v1.0.0 lack the email field — after importing one, accounts must re-bind their email to use password recovery.

### 8. Change the port / disable Docker management?
Change `ports` in compose; for Docker management remove the sock mount line and the panel degrades gracefully.

### 9. Will upgrading touch my data?
Schema changes apply automatically through incremental migrations (`PRAGMA user_version`), which only add; rolling back to an older release also stays compatible. A backup before upgrading is still a good idea.

### 10. How do I switch the interface language?
Browser-detected by default; pin it to Chinese / English in admin → site settings; add `?lang=en|zh` to any page for a temporary override (nice for sharing/preview).

### 11. Dark mode?
Follows the system via `prefers-color-scheme` — nothing to configure.
