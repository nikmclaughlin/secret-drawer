# Secret Drawer — Implementation Plan

> A hidden drawer in wp-admin, unlocked by a secret interaction, that holds
> whatever you want tucked out of sight: notes, quick links, notifications,
> tools. Silly to find, genuinely useful to have.

---

## 1. Concept

**One-liner:** Every wp-admin page hides a secret interaction (typing the
word `hellodolly` — by default). Users with permission who discover it
get a slide-out sidebar that persists across all admin pages and whose
contents are customizable — both by the site owner (settings) and by other
plugins (a panel API).

**The vibe:** easter-egg energy. The first time a user unlocks it, they get a
little "🔓 you found the Secret Drawer" moment. After that, it behaves like
professional tooling: fast, quiet, keyboard-friendly.

**Core tension to design around:** the *discovery* is silly, the *contents*
must not be. The drawer should feel like something a serious plugin would
ship — it just happens to have a secret door.

---

## 2. User story

1. An administrator pokes around wp-admin. Somewhere, a secret word awaits
   (`hellodolly` — by default).
2. A drawer slides in from the right edge — overlay, not a layout shift.
   A one-time toast: "🔓 You found the Secret Drawer."
3. The drawer has panel tabs: **Notes**, **Quick Links**, **Notifications**
   in v1. Notes autosave to their user profile. Links are their own curated
   jump list. Notifications aggregate update/comment counts with deep links.
4. ESC or ✕ closes it. Reopening restores the last-active panel. Position
   and open state persist via `localStorage`.
5. The site owner opens the drawer's gear icon → an **in-drawer settings
   view** — never a normal admin-menu page — to choose which roles can
   discover it, change the trigger word, toggle & reorder panels, pick
   drawer position and width.
6. Other plugins register their own panels via a PHP filter and they show
   up as new tabs.

---

## 3. Core design decisions

### 3.1 The trigger — a typed secret word

**Decision:** the drawer opens when you type a secret word anywhere on an
admin page. Default: **`hellodolly`** (WP-themed, exactly what the plugin
joke wants; the Hello Dolly lyrics are admin folklore, and typing a word
feels like whispering a password rather than clicking a button).

Rejected: triple-clicking the WP logo — the logo has expected behavior
(about screen / site link) and the plugin refuses to get in its way. Konami
(↑↑↓↓←→←→BA) stays in the backlog as an alternate trigger; the typed word is
strictly more discoverable in conversation ("type hellodolly").

**Mechanics (get the details right or it's broken):**

- Listen on `keydown` at the document level; accumulate a rolling buffer of
  the last ~16 printable characters.
- **Text fields are exempt:** if focus is inside `input, textarea, select,
  [contenteditable]` (which includes the block editor), keystrokes do NOT
  feed the buffer. You can type `hellodolly` into a post without ever
  opening the drawer.
- Match is case-insensitive; on match, clear the buffer and open.
- A **subtle confirmation**: on match, briefly flash the matched text as a
  tiny ghost overlay near the keyboard-focus point ("…ellodolly" fading out)
  before the drawer slides in — confirms *why* it opened without needing a
  visible affordance beforehand. 300ms, `prefers-reduced-motion` respected.
- The secret word is **server-configured** and delivered via
  `wp_localize_script` along with the enabled flag — changing or disabling
  it requires no JS changes. Filterable:
  `apply_filters( 'secret_drawer_trigger_word', 'hellodolly' )`.
- **Why a word and not a shortcut:** works on every admin page with zero UI
  footprint; shareable in one sentence; and its "cost" (typing 10 letters)
  is exactly the silly ceremony the plugin is about.

**Backlog alternate triggers** (each independently toggleable, same
registry): Konami code, type `drawer`, double-click the footer credit,
5-finger tap on mobile, click the 🤫 emoji that occasionally photobombs
the admin footer.

### 3.2 Access control

- **Server is the source of truth.** The trigger JS is *only enqueued* for
  users who pass the gate — non-allowed users never receive the JS at all.
- Default gate: current user's role must intersect the configured role list
  (default: `administrator`). Filterable:
  `apply_filters( 'secret_drawer_user_can_access', $allowed, $user )`.
- Every REST endpoint independently re-checks access (never trust the
  front end). Per-panel capability checks on top (e.g. Notifications
  requires `update_core`).
- Settings live *inside the drawer* (gear icon); saving requires
  `manage_options`. There is no admin-menu settings page — nothing about
  the plugin leaks onto normal admin screens.
- Drawer content is fetched via authenticated REST calls *after* unlock —
  nothing sensitive is baked into the initial page load.

### 3.3 The drawer (UX spec)

- Fixed-position overlay panel, full height, **320px wide** (setting:
  280–480). Overlays content (z-index above the admin bar, ~`999999`),
  doesn't push layout — safe on every admin screen including ones with
  their own custom widths.
- **Position is a setting:** `right` (default) or `bottom` (sheet-style,
  capped ~70vh). Recommendation: right — the admin already owns the top bar
  and left nav, so a right-hand overlay completes the frame, and drawer
  content (notes, link lists, notifications) is tall and list-like, which
  suits a sidebar. Bottom exists because it's one CSS modifier
  (`.sd-drawer--bottom`) and some people prefer it on small screens.
- Structure: header (title + gear icon → settings + ✕ close), panel tab
  strip (dashicons), active panel body, thin footer with a 🤫 wink.
- Enter animation ~200ms ease-out; respects `prefers-reduced-motion`.
- On screens < 480px the drawer becomes full-width.
- State persisted in `localStorage` under `secretDrawer.*`:
  `open`, `lastPanel`. **User data itself is NOT in localStorage** — it's
  server-side (usermeta) so it follows the user across browsers.
- First unlock: one-time toast (usermeta flag `secret_drawer_discovered`),
  plus a silly confetti burst of 🤫 emoji. 400ms. Then it never bothers
  you again.

### 3.4 Content model = Panels

Everything in the drawer is a **panel**: a tab with an id, title, dashicon,
capability requirement, and a render strategy. v1 ships three built-ins;
the registry is filterable from day one so the architecture is proven early.

### 3.5 UI direction — modern admin style, minimal custom CSS

The drawer should read as *modern WordPress admin*: block-editor energy,
`wp-components` visual language — clean panels with subtle borders,
`TabPanel`-style tab strips, `ToggleControl`-style switches, muted secondary
meta text, proper focus states — not the classic `wp-list-table` /
"admin widget" look.

Two grounding facts shape the approach:

- **Modern admin screens mostly just load `wp-components`.** Core has
  bundled it since 5.4 (React 18 since 6.2; current core ships React
  18.3), it's a normal dependency-safe script handle, and it's exactly what
  Gutenberg itself runs on inside wp-admin. It is a *frontend library of
  pre-styled, consistent components* — not a framework rewrite.
- **No DataViews.** `wp-dataviews` is a Gutenberg-plugin package, not
  core-bundled, and the drawer has no bulk/table-of-records use case. We
  won't build or bundle one.

**Decision:** enqueue `wp-components` (+ its React deps) for the drawer and
build its UI from core-bundled components — `TabPanel` for the strip,
`ToggleControl`, `SelectControl`, `TextControl` for the in-drawer settings,
`Button`, `Snackbar`/toast — styled with a small layer of drawer-specific
CSS (`assets/css/drawer.css`, ~300 lines: layout, position, animation,
and a few overrides to fit a 320px column). Consistency comes free from
`wp-components`' stylesheet; the maintenance surface stays tiny.

**Panel authoring follows the same rule:** panels render with
`wp-components` primitives (a Notes panel is a `TextareaControl`, links
render as clean bordered rows with `Button` actions) rather than bespoke
HTML. A panel *may* server-render HTML or load its own extra scripts —
that's the escape hatch for heavier integrations — but core-bundled
components are the house style, and third-party panels are expected to
follow it to stay visually at home.

**Why not a hand-rolled vanilla kit:** we'd be re-deriving toggle switches,
tab strips, and focus management that `wp-components` already ships,
tested, and styles — for a plugin whose whole point is that its *contents*
look professionally native. If bundle size ever matters (it's ~a few
hundred KB before WP's min+concat caches kick in, loaded only for
gate-passing users), the fallback is plain DOM — but that's a measured
later decision, not the starting point.

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
│   ├── class-settings.php         # Settings storage: register, sanitize,
│   │                              #   REST-persisted (no admin-menu page)
│   ├── class-rest.php             # REST routes registration
│   ├── class-panel-registry.php   # Registry + secret_drawer_panels filter
│   └── panels/
│       ├── class-panel-notes.php
│       ├── class-panel-links.php
│       └── class-panel-notifications.php
├── assets/
│   ├── css/drawer.css
│   └── js/drawer.js               # wp-components UI, no build step
└── languages/
```

**No build step.** `drawer.js` is a single plain-JS file (no JSX/Babel/npm)
that mounts `wp-components` UI via `wp.element.createElement` inside the
drawer container; CSS is one file. Plugin stays npm-free while still
looking native-modern. (Revisit only if a panel genuinely demands JSX
ergonomics — it can ship its own build.)

### 4.2 Load flow

1. `secret-drawer.php`: guard `ABSPATH`, define version/constants, require
   `includes/class-plugin.php`, `Secret_Drawer_Plugin::init()` on
   `plugins_loaded`.
2. On `init`: load textdomain, register settings.
3. On `admin_enqueue_scripts` (every admin page): if
   `Secret_Drawer_Plugin::user_can_access()` → enqueue `wp-components` (+ its
   React deps), drawer CSS/JS with filemtime cache-busting, localize config:
   REST root, `wp_rest` nonce, secret trigger word, enabled panels
   (id/title/icon only), i18n strings.
4. Panel bodies are **lazy**: front end fetches `GET /secret-drawer/v1/panels/{id}`
   on first tab activation (and on tab re-activation for the Notifications
   panel), so page-load cost is ~2KB of static assets.
5. REST routes registered on `rest_api_init`, each with capability +
   permission callbacks.

---

## 5. Data model

| Store | Key | Type | Scope |
|-------|-----|------|-------|
| option | `secret_drawer` | `{ version, roles[], trigger_word, panels[], width, position }` | site |
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
| GET | `/panels/{id}` | per-panel | Rendered HTML + optional data for one panel |
| POST | `/notes` | `read` + access gate | Save notes (returns sanitized copy) |
| GET/POST/DELETE | `/links(/…)` | `read` + access gate | Quick-links CRUD |
| GET | `/notifications` | `update_core` | Aggregated counts + deep links |
| GET/POST | `/settings` | `manage_options` | Settings read/write (REST, not a settings page) |

All responses: `rest_ensure_response`, sanitized server-side. Panel HTML is
rendered server-side (panels are PHP classes with a `render()` returning
HTML) — keeps panel authors in familiar WP territory; JS just injects it.

---

## 7. Panel API (extensibility)

### PHP

```php
add_filter( 'secret_drawer_panels', function ( array $panels ): array {
    $panels['todo'] = [
        'id'         => 'todo',
        'title'      => __( 'Team Todo', 'secret-drawer' ),
        'icon'       => 'dashicons-list-view',
        'capability' => 'edit_pages',
        'order'      => 40,
        'render'     => fn() => '<p>…</p>',           // HTML string
        'refresh_on' => 'open',                       // or 'never'
    ];
    return $panels;
} );
```

Registry sorts by `order`, drops panels whose capability the user lacks,
and hands the surviving set (id/title/icon only) to the front end.

### JS events (for panel authors)

Dispatched on `document`: `secret-drawer:open`, `secret-drawer:close`,
`secret-drawer:panel:shown` (detail: `{ id }`).
A tiny `window.SecretDrawer` global exposes `open()`, `close()`, `toggle()`,
`showPanel( id )` — enough for other plugins to drive the drawer without
touching its internals.

---

## 8. v1 built-in panels

### 8.1 Notes (per-user scratchpad)
- A `TextareaControl` with debounced autosave (800ms) and a "Saved ✓"
  affordance (`Snackbar` or inline text).
- Stored in usermeta. No formatting in v1; Markdown-lite is backlog.
- Seed content on first run: a winking hint about the plugin.

### 8.2 Quick Links (per-user jump list)
- User-managed list: `TextControl` label + URL, add/reorder/delete via
  `Button` actions, rendered as clean bordered rows (`wp-components` list
  style) opening in a new tab.
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
- Full keyboard support: the trigger is typed text (inherently
  keyboard-first), ESC closes, tabs are arrow-key navigable.
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
- [ ] Per-panel capability checks
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
Enqueue on all admin pages; typed secret word (`hellodolly`) trigger;
slide-out panel with header/tabs/close; ESC + focus handling;
`localStorage` state; first-unlock toast + 🤫 confetti.
**AC:** works on every admin page (plugins, editor, custom post types);
no layout shift; no dependencies.

### M2 — Access control + settings
Role gate (server-enforced), **in-drawer settings view** (accessed only via
the drawer's gear icon: roles multi-select, trigger word field, panel
enable/reorder, width, position right/bottom), REST `/settings`.
**AC:** a subscriber receives zero plugin JS; settings persist and
sanitize correctly; no admin-menu page exists at all.

### M3 — Built-in panels
Notes (autosave), Quick Links (CRUD), Notifications (counts + deep links +
transient cache + tab badge).
**AC:** data survives cache clears and logins on another device; counts
match the real screens.

### M4 — Panel API + docs
Registry + `secret_drawer_panels` filter, JS events + `window.SecretDrawer`,
example third-party panel in README, `SECRET-DRAWER-EXTENDING.md` or
README section.
**AC:** drop-in example panel renders as a fourth tab.

### M5 — Polish & release-readiness
i18n (all strings, `wp-pot` extraction), RTL audit, small-screen audit,
reduced-motion, `readme.txt`, screenshots, version `1.0.0` tag.
**AC:** passes Plugin Check (pcp) with no errors; fresh-install smoke test
on a clean WP + 2025 theme.

---

## 12. Fun backlog (the silly part, preserved for later)

- Alternate triggers: Konami code, double-click the footer credit,
  5-finger tap on mobile, click the 🤫 emoji that occasionally photobombs
  the admin footer.
- Profile page badge: "Secret Drawer discovered on {date}. Nobody knows."
- Secret-word confetti upgrade when unlocked with a perfect no-typo
  run of `hellodolly`.
- A "drawer within the drawer": nested hidden compartment, 1% of users
  find it. (It's just a second, smaller notes field.)
- Sound toggle: tiny paper-slide `woosh.mp3`, off by default, obviously.

---

## 13. Distribution strategy

**Today:** personal project / built for the joke — you're going to tweet it
and enjoy whatever happens. **But:** the wp.org slug `secret-drawer` is
*currently unclaimed* (verified against the plugins API), and wp.org listings
require GPL-compatible code with no registration locks — so the plan
deliberately keeps the door open:

- **Plugin header ships release-grade from day one:** proper
  `Plugin Name/Description/Text Domain`, `Requires WP/PHP`, license field.
  Zero rework if you publish.
- **No registration/key checks ever** — every trigger, setting, and panel
  works out of the box; nothing "phones home." (Also just correct for a
  secret feature: it shouldn't leak its own existence.)
- **wp.org-clean repo layout:** `readme.txt` added at M5; `languages/` dir
  present; no dev clutter (`node_modules` etc.) in tagged exports; all
  strings i18n-ready so a future translation community isn't locked out.
- **Extensibility filter is public API** — documented, versioned, and not
  renamed casually; third-party panels only make a future listing stronger.

The trade-off accepted: we *do* carry i18n/readme/polish costs earlier than
a purely personal plugin would. That cost is small (vanilla JS, no build
step) and keeps both futures open.
1. ~~Distribution~~ → **resolved:** personal-now, wp.org-ready (see §13).
2. **Shared content:** should any panel be site-wide rather than
   per-user? (e.g. "Site notes" every admin sees — candidate for v2, or
   v1 if you want it day one.)
3. **Rich notes:** plain textarea for v1 acceptable, or markdown-ish
   rendering immediately?
4. **Multisite:** network-activate behavior — settings network-wide, or
   per-site? (Recommend: per-site for v1; network toggle later.)

---

## 14. Repo conventions

- Branch: `main` (initialized). Feature branches → PR → squash merge.
- Commits: conventional commits (`feat:`, `fix:`, `chore:`, `docs:`).
- Versioning: SemVer, starting at `0.1.0` for M0–M2, `1.0.0` at release.
- WordPress minimum: 6.4. PHP minimum: 7.4 (why not: max reach).
- Prefix everything `secret_drawer_` / `Secret_Drawer_`.