# User guide

## Roles

| Role | Capabilities |
|---|---|
| Guest | Browse recommended sites, search, view terms of service, register |
| Member (regular user) | Own panel: full group/card management, personal settings, export |
| Admin | Member capabilities + user management, site settings, server monitoring, Docker management, full backup |

## Guest homepage

- **Search box**: type a keyword and press Enter to search the selected engine; type a URL directly (e.g. `example.com`) and press Enter to go there; switchable engine that is remembered
- **Recommended panel**: two sets (Chinese / English) of common sites, shown by interface language; click to open
- The **Register** button in the top-right opens the sign-up page (username + password + optional e-mail + accept terms); when registration is off, only a notice is shown

## Member panel

### Groups & cards
- **New group / Add card**: click the corresponding button in Edit mode
- **Edit mode**: click ✎ to enter/exit; while on you can drag to sort, move cards across groups, drag group titles to reorder, and nudge the selected card with ↑/↓
- **Card edit dialog**: group, name, external URL (required), internal URL (optional, for LAN/WAN switching), icon, note
- **Mobile**: long-press a card to bring up the action menu (edit / iframe preview / delete); drag to arrange works too

### LAN/WAN switching 🌐
- Once a card has an internal URL, the top-bar 🌐 button cycles Auto / LAN / WAN, rewriting all dual-address cards **without a reload**
- Auto mode judges your source by the site's LAN ranges; hovering the button shows the current verdict

### Search
- Keyword + Enter goes to the engine; typing a URL + Enter goes straight there; engine switches apply immediately and are remembered

### Personal settings (⚙)
- Panel title, wallpaper (gradient presets / URL / upload ≤8MB), search engine, change password

### Data export
- The export entry beside "Edit mode" in the panel header: CSV (table) or Netscape HTML (importable into browser bookmarks)

## Admin panel (admin)

Entry: 👤 menu → 🔧 System admin (or open `admin.php` directly).

| Tab | Contents |
|---|---|
| Users | User list, create user (choose role), reset password, disable/enable, delete |
| Personal settings | Same personal settings as a member |
| Site settings | Site name (ZH/EN), interface language, open registration, LAN ranges, status-strip toggle, SMTP & e-mail verification |
| Server | Real-time CPU/memory/disk/network/load/uptime; Docker container list with start/stop/restart (needs sock mounted); backup export/import; clear icon cache |

## Keyboard & gestures quick reference

| Action | How |
|---|---|
| Move card up/down | ↑ / ↓ with the card selected in Edit mode |
| Drag to sort | drag cards / drag group titles in Edit mode |
| Edit on mobile | long-press the card |
| Open a URL from search | type the domain and press Enter |