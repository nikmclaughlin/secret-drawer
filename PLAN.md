# Secret Drawer — Implementation Plan

> A hidden drawer in wp-admin, unlocked by a secret interaction, that holds
> whatever you want tucked out of sight: notes, quick links, notifications,
> tools. Silly to find, genuinely useful to have.

---

## 1. Concept

**One-liner:** Every wp-admin page has a secret interaction (triple-click the
WP logo in the admin bar — by default). Users with permission who discover it
get a slide-out sidebar that persists across all admin pages and whose
contents are customizable — both by the site owner (settings) and by other
plugins (a widget API).

**The vibe:** easter-egg energy. The first time a user unlocks it, they get a
little "🔓 you found the Secret Drawer" moment. After that, it behaves like
professional tooling: fast, quiet, keyboard-friendly.

**Core tension to design around:** the *discovery* is silly, the *contents*
must not be. The drawer should feel like something a serious plugin would
ship — it just happens to have a secret door.

---

## 2. User story

1. An administrator pokes around wp-admin. Somewhere, a subtle interaction
   awaits (triple-click the WP logo in the admin bar, by default).
2. A drawer slides in from the right edge — overlay, not a layout shift.
   A one-time toast: "🔓 You found the Secret Drawer."
3. The drawer has widget tabs: **Notes**, **Quick Links**, **Notifications**
   in v1. Notes autosave to their user profile. Links are their own curated
   jump list. Notifications aggregate update/comment counts with deep links.
4. ESC or ✕ closes it. Reopening restores the last-active widget. Position
   and open state persist via `localStorage`.
5. The site owner goes to **Settings → Secret Drawer** to: choose which
   roles can discover it, enable/disable triggers, toggle & reorder widgets.
6. Other plugins register their own widgets via a PHP filter and they show
   up as new tabs.

---

## 3. Core design decisions

### 3.1 Where does the trigger live? (options considered)

| # | Trigger | Pros | Cons |
|---|---------|------|------|
| 1 | **Triple-click the WP logo in the admin bar** | Present on every admin page; invisible; discoverable by word of mouth ("click the logo 3 times"); cheap to implement | Subtle to the point of never being found — which is arguably the point |
| 2 | Konami code (↑↑↓↓←→←→BA) | Classic; zero visual footprint; works everywhere | Keyboard-only; nerds-only discovery |
| 3 | Double-click the footer credit ("Thank you for creating with WordPress") | Present on every admin page | Requires scrolling; footer often off-screen |
| 4 | Type the word `drawer` anywhere | Fun | False positives in inputs/textareas; needs exclusion logic |
| 5 | Plain keyboard shortcut (e.g. `Ctrl/Cmd+Shift+D`) | Practical | Not "secret" in spirit; collision risk |

**Decision:** Ship **#1 as the default** and **#2 as a secondary trigger**
(both enabled by default, independently toggleable). #3–#5 go in the backlog.
Triggers are additive — any enabled trigger opens the drawer. The trigger
config is delivered to the front end via `wp_localize_script`, so enabling/
disabling is server-controlled.

Trigger JS listens at the document level (event delegation), ignores events
originating inside `input, textarea, select, [contenteditable]` where
relevant, and requires the click target to be inside `#wp-admin-bar-wp-logo`
with a 3-click window (~800ms between clicks, with visual "wobble" feedback
on each click so it feels alive).

### 3.2 Access control

- **Server is the source of truth.** The trigger JS is *only enqueued* for
  users who pass the gate — non-allowed users never receive the JS at all.
- Default gate: current user's role must intersect the configured role list
  (default: `administrator`). Filterable:
  `apply_filters( 'secret_drawer_user_can_access', $allowed, $user )`.
- Every REST endpoint independently re-checks access (never trust the
  front end). Per-widget capability checks on top (e.g. Notifications
  requires `update_core`).
- Settings screen requires `manage_options`.
- Drawer content is fetched via authenticated REST calls *after* unlock —
  nothing sensitive is baked into the initial page load.

### 3.3 The drawer (UX spec)

- Fixed-position panel anchored right, full height, **320px wide**
  (setting: 280–480). Overlays content (z-index above the admin bar,
  ~`999999`), doesn't push layout — safe on every admin screen including
  ones with their own custom widths.
- Structure: header (title + gear icon → settings + ✕ close), widget tab
  strip (dashicons), active widget body, thin footer with a 🤫 wink.
- Enter animation ~200ms ease-out; respects `prefers-reduced-motion`.
- On screens < 480px the drawer becomes full-width.
- State persisted in `localStorage` under `secretDrawer.*`:
  `open`, `lastWidget`. **User data itself is NOT in localStorage** — it's
  server-side (usermeta) so it follows the user across browsers.
- First unlock: one-time toast (usermeta flag `secret_drawer_discovered`),
  plus a silly confetti burst of 🤫 emoji. 400ms. Then it never bothers
  you again.

### 3.4 Content model = Widgets

Everything in the drawer is a **widget**: a tab with an id, title, dashicon,
capability requirement, and a render strategy. v1 ships three built-ins;
the registry is filterable from day one so the architecture is proven early.

---

## 4. Architecture

### 4.1 File tree

```
secret-drawer/
├── secret-drawer.php              # Plugin header, constants, bootstrap
├── uninstall.php                  # Clean up option + usermeta
├── README.md
├── PLAN.md                        # ← this file
├── includes/
│   ├── class-plugin.php           # Singleton: hooks everything together
│   ├── class-assets.php           # Enqueues + wp_localize_script config
│   ├── class-settings.php         # Settings API: register, sanitize, page
│   ├── class-rest.php             # REST routes registration
│   ├── class-widget-registry.php  # Registry + secret_drawer_widgets filter
│   └── widgets/
│       ├── class-widget-notes.php
│       ├── class-widget-links.php
│       └── class-widget-notifications.php
├── assets/
│   ├── css/drawer.css
│   └── js/drawer.js               # Vanilla, dependency-free, no build step
└── languages/
```

**No build step.** Vanilla ES2020+ JS in one file, CSS in one file. A
plugin this size shouldn't require npm. (Revisit only if widgets demand
framework-grade rendering.)

### 4.2 Load flow

1. `secret-drawer.php`: guard `ABSPATH`, define version/constants, require
   `includes/class-plugin.php`, `Secret_Drawer_Plugin::init()` on
   `plugins_loaded`.
2. On `init`: load textdomain, register settings.
3. On `admin_enqueue_scripts` (every admin page): if
   `Secret_Drawer_Plugin::user_can_access()` → enqueue CSS/JS with filemtime
   cache-busting, localize config: REST root, `wp_rest` nonce, enabled
   widgets (id/title/icon only), triggers config, i18n strings.
4. Widget bodies are **lazy**: front end fetches `GET /secret-drawer/v1/widgets/{id}`
   on first tab activation (and on tab re-activation for the Notifications
   widget), so page-load cost is ~2KB of static assets.
5. REST routes registered on `rest_api_init`, each with capability +
   permission callbacks.

---

## 5. Data model

| Store | Key | Type | Scope |
|-------|-----|------|-------|
| option | `secret_drawer` | `{ version, roles[], triggers[], widgets[], width }` | site |
| usermeta | `secret_drawer_notes` | text | per-user |
| usermeta | `secret_drawer_links` | array of `{label, url}` | per-user |
| usermeta | `secret_drawer_discovered` | timestamp | per-user |
| transient | `secret_drawer_notif_{blog}` | counts array, 60s | site cache |

Notes: `sanitize_textarea_field` on save, `esc_html` + `nl2br` on output.
Links: `wp_kses`-safe label, `esc_url_raw` + scheme allowlist (`http, https`)
on save, `esc_url` on output.

`uninstall.php` deletes the option and `delete_metadata( 'user', 0, ... )`
for all three usermeta keys.

---

## 6. REST API surface

Namespace: `secret-drawer/v1` (nonce via `wp_rest`, standard cookie auth).

| Method | Route | Capability | Purpose |
|--------|-------|------------|---------|
| GET | `/widgets/{id}` | per-widget | Rendered HTML + optional data for one widget |
| POST | `/notes` | `read` + access gate | Save notes (returns sanitized copy) |
| GET/POST/DELETE | `/links(/…)` | `read` + access gate | Quick-links CRUD |
| GET | `/notifications` | `update_core` | Aggregated counts + deep links |
| GET/POST | `/settings` | `manage_options` | Settings read/write |

All responses: `rest_ensure_response`, sanitized server-side. Widget HTML is
rendered server-side (widgets are PHP classes with a `render()` returning
HTML) — keeps widget authors in familiar WP territory; JS just injects it.

---

## 7. Widget API (extensibility)

### PHP

```php
add_filter( 'secret_drawer_widgets', function ( array $widgets ): array {
    $widgets['todo'] = [
        'id'         => 'todo',
        'title'      => __( 'Team Todo', 'secret-drawer' ),
        'icon'       => 'dashicons-list-view',
        'capability' => 'edit_pages',
        'order'      => 40,
        'render'     => fn() => '<p>…</p>',           // HTML string
        'refresh_on' => 'open',                       // or 'never'
    ];
    return $widgets;
} );
```

Registry sorts by `order`, drops widgets whose capability the user lacks,
and hands the surviving set (id/title/icon only) to the front end.

### JS events (for widget authors)

Dispatched on `document`: `secret-drawer:open`, `secret-drawer:close`,
`secret-drawer:widget:shown` (detail: `{ id }`).
A tiny `window.SecretDrawer` global exposes `open()`, `close()`, `toggle()`,
`showWidget( id )` — enough for other plugins to drive the drawer without
touching its internals.

---

## 8. v1 built-in widgets

### 8.1 Notes (per-user scratchpad)
- Single autosaving textarea (debounced 800ms, "Saved ✓" affordance).
- Stored in usermeta. No formatting in v1; Markdown-lite is backlog.
- Seed content on first run: a winking hint about the plugin.

### 8.2 Quick Links (per-user jump list)
- User-managed list: label + URL, add/reorder/delete, renders as a styled
  list opening in a new tab.
- Seeded with: Site Health, Updates, Plugins screen.

### 8.3 Notifications (site-wide, cached 60s)
- Aggregates: core/theme/plugin update counts, comments in moderation,
  (filterable) so plugins can inject their own badge counts.
- Each item deep-links to the relevant admin screen. Zero-count items
  hidden. The drawer's tab icon shows a dot badge when count > 0.

---

## 9. Accessibility & UX details

- `<aside role="complementary" aria-label="Secret Drawer">`, `aria-hidden`
  toggling, `inert` on the rest of the page while open (or focus trap).
- Full keyboard support: trigger has a keyboard equivalent (Konami covers
  this by default), ESC closes, tabs are arrow-key navigable.
- Focus moves into the drawer on open, returns to the previously focused
  element on close.
- All colors/icons meet WCAG AA contrast on the standard admin palette;
  dark-mode (`color-scheme: dark`) respected where WP supports it.
- RTL: use logical CSS properties (`inset-inline-end`) so the drawer mirrors
  correctly under RTL locales.
- No console errors on pages with heavy JS (test on editor screens).

---

## 10. Security checklist

- [ ] `defined( 'ABSPATH' ) || exit;` at top of every PHP file
- [ ] Access gate enforced in: asset enqueue, every REST route, settings save
- [ ] Per-widget capability checks
- [ ] All output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses`)
- [ ] All input sanitized; settings whitelisted against known keys
- [ ] REST nonce (`wp_rest`) + cookie auth only — no plaintext secrets
- [ ] No transiently stored sensitive data; user data scoped to usermeta
- [ ] Capability filter cannot be lowered below `read` (hard floor)
- [ ] Rate-limit notifications endpoint via 60s transient cache

---

## 11. Milestones

### M0 — Scaffold
Plugin header (name, slug `secret-drawer`, text domain), constants,
bootstrap skeleton, `uninstall.php`, `.gitignore`, README stub.
**AC:** activates/deactivates cleanly with zero output or errors.

### M1 — Drawer shell (the magic moment)
Enqueue on all admin pages; triple-click logo + Konami triggers; slide-out
panel with header/tabs/close; ESC + focus handling; `localStorage` state;
first-unlock toast + 🤫 confetti.
**AC:** works on every admin page (plugins, editor, custom post types);
no layout shift; no dependencies.

### M2 — Access control + settings
Role gate (server-enforced), Settings screen under **Settings → Secret
Drawer** (roles multi-select, trigger toggles, widget enable/reorder,
width), REST `/settings`, gear icon inside drawer opens settings.
**AC:** a subscriber receives zero plugin JS; settings persist and
sanitize correctly.

### M3 — Built-in widgets
Notes (autosave), Quick Links (CRUD), Notifications (counts + deep links +
transient cache + tab badge).
**AC:** data survives cache clears and logins on another device; counts
match the real screens.

### M4 — Widget API + docs
Registry + `secret_drawer_widgets` filter, JS events + `window.SecretDrawer`,
example third-party widget in README, `SECRET-DRAWER-EXTENDING.md` or
README section.
**AC:** drop-in example widget renders as a fourth tab.

### M5 — Polish & release-readiness
i18n (all strings, `wp-pot` extraction), RTL audit, small-screen audit,
reduced-motion, `readme.txt`, screenshots, version `1.0.0` tag.
**AC:** passes Plugin Check (pcp) with no errors; fresh-install smoke test
on a clean WP + 2025 theme.

---

## 12. Fun backlog (the silly part, preserved for later)

- Alternate triggers: type `drawer` anywhere (non-input), double-click the
  footer credit, shake on mobile, click the 🤫 emoji that occasionally
  photobombs the admin footer.
- Profile page badge: "Secret Drawer discovered on {date}. Nobody knows."
- Konami code confetti upgrade when opened via Konami specifically.
- A "drawer within the drawer": nested hidden compartment, 1% of users
  find it. (It's just a second, smaller notes field.)
- Sound toggle: tiny paper-slide `woosh.mp3`, off by default, obviously.

---

## 13. Open questions

1. **Distribution:** personal plugin, or wp.org-bound? (Affects readme
   rigor, settings polish, and how paranoid the security bar needs to be.
   This plan assumes wp.org-grade.)
2. **Shared content:** should any widget be site-wide rather than
   per-user? (e.g. "Site notes" every admin sees — candidate for v2, or
   v1 if you want it day one.)
3. **Rich notes:** plain textarea for v1 acceptable, or markdown-ish
   rendering immediately?
4. **Multisite:** network-activate behavior — settings network-wide, or
   per-site? (Recommend: per-site for v1; network toggle later.)
5. **Trigger feedback:** when someone clicks the logo twice, do we tease
   (logo does a tiny wiggle) or stay perfectly silent? (Recommend a
   whisper-quiet wiggle — teases without revealing.)

---

## 14. Repo conventions

- Branch: `main` (initialized). Feature branches → PR → squash merge.
- Commits: conventional commits (`feat:`, `fix:`, `chore:`, `docs:`).
- Versioning: SemVer, starting at `0.1.0` for M0–M2, `1.0.0` at release.
- WordPress minimum: 6.4. PHP minimum: 7.4 (why not: max reach).
- Prefix everything `secret_drawer_` / `Secret_Drawer_`.