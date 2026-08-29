# AGENTS.md — Secret Drawer

> Describes the plugin **as built** and the rules for working on it. Migrated
> from PLAN.md (2026-08-28 — plan was an implementation plan; this is the
> as-built reference). Planned work lives in PLAN.md; this file records only
> what exists. Markers: ✅ implemented · ⏳ pending.

## What this is

A WordPress plugin: a hidden drawer in wp-admin, unlocked by typing a secret
word (default `hellodolly`), holding **cubbies** — small panels (notes, quick
links, notifications, levers, …) the site owner curates and other plugins can
extend. Silly to find, genuinely useful to have. Released: **v1.0.0**
(tagged, GitHub release zip, one-click WordPress Playground demo).

## Working rules (non-negotiables)

1. **Server is the source of truth for access.**
   `Secret_Drawer_Plugin::user_can_access()` gates the enqueue and is
   re-checked in every REST permission callback and settings save.
   Non-allowed users never receive any plugin JS.
2. **No admin-menu settings page, ever.** Settings live only inside the
   drawer (gear icon) and persist via the REST `/settings` route.
3. **House style is `wp-components`, no build step.** Single-file
   `assets/js/drawer.js` mounting components via `wp.element.createElement`;
   no npm, no JSX, no Babel. Cubby authors are expected to follow it.
4. **Naming is public API** (bake in, never rename casually):
   `secret_drawer_settings`, `secret_drawer_cubby_{id}` (user data per
   cubby), `secret_drawer_cubbies` filter, `secret-drawer/v1` REST namespace,
   `secret-drawer:*` JS events, `window.SecretDrawer` global.
5. **wp.org-ready**: i18n-ready strings, `readme.txt`, no registration/key/
   telemetry checks. Nothing about the plugin leaks onto normal admin screens.
6. **The dev site is Nik's territory.** Agents write code and run static
   checks (`php -l`, `node --check`); no activating/deactivating plugins, no
   WP-CLI writes, no touching the local dev site unless asked. Human
   checkpoints (CP1–CP4) are taste gates: agent proposes, human disposes.
7. If anything here is ambiguous or stale, fix it in the same commit as the
   code — the docs must not drift.

## Architecture

```
secret-drawer/
├── secret-drawer.php               # Header, constants, bootstrap
├── includes/
│   ├── class-plugin.php            # Singleton: hooks, gate, defaults
│   ├── class-assets.php            # Enqueue + wp_localize_script config
│   ├── class-settings.php          # Sanitize/persist (REST only)
│   ├── class-rest.php              # /settings routes
│   ├── class-rest-cubbies.php      # Cubby routes
│   ├── class-cubby-registry.php    # Catalog: filter, normalize, sort, gate
│   └── cubbies/                    # notes, links, notifications, levers, socrates, dice,
│                                   #   vitals, passphrase
├── assets/
│   ├── css/drawer.css              # Single stylesheet
│   └── js/drawer.js                # wp-components UI, no build step
├── bin/build.sh                    # Versioned zip; --release = tag + GH release
├── .github/workflows/release.yml   # On-demand release workflow
├── playground/blueprint.json       # WP Playground demo (installs release zip)
├── .wordpress.org/                 # wp.org SVN assets (screenshots)
└── languages/                      # POT + script translations
```

**Load flow:** bootstrap on `plugins_loaded` → on `init` load textdomain →
on every admin page, if the gate passes: enqueue `wp-components` + drawer
CSS/JS (filemtime cache-busting) and localize config (REST root, `wp_rest`
nonce, trigger word, enabled cubby strip, i18n strings, discovered flag).
Cubby bodies are lazy: fetched from REST on first tab activation. No build
step, no layout in any admin screen.

## Settings & data model

Option `secret_drawer_settings` (seeded on activation):

| Key               | Default                             |
| ----------------- | ----------------------------------- |
| `roles`           | `['administrator']`                 |
| `trigger_word`    | `'hellodolly'`                      |
| `enabled_cubbies` | `['notes','links','notifications']` |
| `width`           | `320`                               |
| `position`        | `'right'`                           |
| `version`         | plugin version                      |

Usermeta: `secret_drawer_cubby_notes` (notes list),
`secret_drawer_cubby_links` (`{label,url}` list), `secret_drawer_discovered`
(first-unlock flag). Transient `secret_drawer_notifications` (aggregated
counts, **1 hour** — the plan's 60s was superseded). Drawer UI state
(open, last cubby) lives in `localStorage` (`secretDrawer.*`); user data never
does. `uninstall.php` removes the option and sweeps all
`secret_drawer_cubby_%` usermeta plus `secret_drawer_discovered`.

## REST API (as built — namespace `secret-drawer/v1`)

| Method   | Route                                                                   | Permission                                                |
| -------- | ----------------------------------------------------------------------- | --------------------------------------------------------- |
| GET/POST | `/settings`                                                             | `manage_options` (sanitize + return sanitized copy)       |
| GET      | `/cubbies/{id}`                                                         | Access gate + per-type capability at render (404 unknown) |
| POST     | `/cubbies/notes/save`, `/cubbies/notes/create`, `/cubbies/notes/delete` | Access gate                                               |
| POST     | `/cubbies/links/add`, `/cubbies/links/update`, `/cubbies/links/remove`  | Access gate                                               |
| POST     | `/cubbies/levers/pull`                                                  | Access gate + lever's own cap (403/404)                   |

Every permission callback re-checks the gate independently of enqueue-time
gating. Cubby HTML is server-rendered (PHP `render()`), JS injects it.
Note: routes are `/cubbies/…` — the plan's earlier `/notes`, `/links`,
`/notifications` paths were superseded during M3/M4.

## Cubby API (extensibility)

`Secret_Drawer_Cubby_Registry` owns the catalog: applies the
`secret_drawer_cubbies` filter over the built-ins, normalizes entries,
stable-sorts by `order`, drops types whose `capability` the current user
lacks (checked at enqueue **and** at render). Full entry schema, lever
schema, and a complete drop-in example cubby:
**`SECRET-DRAWER-EXTENDING.md`**.

JS events (on `document`): `secret-drawer:open`, `secret-drawer:close`,
`secret-drawer:cubby:shown` (detail `{id}`).
Global: `window.SecretDrawer.{open, close, toggle, showCubby(id)}`.

## Built-in cubbies

| Cubby         | What it does                                                                                                                                                       | Status  |
| ------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------- |
| Notes         | Multi-note list; each note pops out as an editor panel; debounced autosave (~1.2s) with inline save indicator; delete cascade-closes its editor; per-user usermeta | ✅      |
| Quick Links   | Per-user CRUD list of admin jump links (✎/✕ rows, add form, validation errors server-side). Ships empty — the plan's seed list was dropped                         | ✅      |
| Notifications | Update + moderation counts, filterable via `secret_drawer_notifications`, deep links, 1-hour transient cache, count badge                                          | ✅      |
| Levers        | One-click actions: copy-site-URL (client clipboard) + empty-trash (server, `edit_others_posts`, confirmation), filterable via `secret_drawer_levers`               | ✅ (M4) |
| Socrates      | The bust that started it all — portrait + caption (post-plan addition)                                                                                             | ✅      |
| Dice          | d2/d6/d12/d20 picker, roll with CSS tumble, last-five history in localStorage. Client-side only, no REST                                                            | ✅      |
| Site Vitals   | WP/PHP versions, memory, WP_DEBUG, theme; autoload size, plugins (active | inactive split card, warns on inactive side at 5+), missed schedules, env type, HTTPS, object cache, live site clock. 1-hour transient (schema-keyed, clock excluded), `secret_drawer_vitals` filter | ✅      |
| Passphrase    | Random words (3–6) + optional two-digit suffix, ~8 bits/word from a 256-word list, entropy readout; crypto.getRandomValues, **no REST, no storage of any kind**, copy via clipboard API (denied/no-API failures show an amber `copyFail`
      snackbar — new `sd-toast--warn` tone, `role="alert"`). Client-only cubby #2 | ✅      |

## UX & accessibility (as built)

- Drawer: fixed overlay (z-index above admin bar), right (default) or bottom
  sheet (width setting drives sheet height in bottom mode, relabeled in
  settings; stored key unchanged). Content-fit pop-out panels, no fixed rows.
- **Launcher cascade (M3.5)**: drawer shows a grid of cubby cards; clicking a
  card pops the cubby out as its own sidebar; clicking a note pops the editor
  out again — drawers all the way down. One cubby instance at a time
  (re-click toggles; `showCubby()` focuses instead). ESC/✕ pops one level;
  closing the launcher closes and flushes everything. Verified by Nik,
  2026-08-27 (CP3.5).
- Trigger: typed secret word on any admin page, text fields exempt
  (`isTextField`), case-insensitive, ghost-flash confirmation
  ("It's a secret!"), first unlock = toast + 🤫 confetti, once only.
- A11y: `aria-hidden` + `inert` on the page while closed, `role="status"`
  toasts/alerts, focus handling, ESC closes, `prefers-reduced-motion`
  collapses all animation, logical CSS props (RTL mirrors), panels span the
  viewport under 480px.

## Security posture

- [x] `defined( 'ABSPATH' )` guard in every PHP file (`uninstall.php` uses
      the `WP_UNINSTALL_PLUGIN` guard)
- [x] Access gate in: asset enqueue, every REST route, settings save
- [x] Per-cubby capability checks (registry + render + lever pull)
- [x] Output escaped (`esc_html`/`esc_attr`/`esc_url`), input sanitized
      (`sanitize_textarea_field` on notes, scheme allowlist + `esc_url_raw`
      on links); settings validated against the known catalog/roles
- [x] REST auth = `wp_rest` nonce + cookie only; no sensitive data baked
      into page load (bodies fetch after unlock)
- [x] Notifications rate-limiting via transient cache
- [x] Capability hard floor requirement from the original plan was **dropped
      by decision** (2026-08-28): an empty `capability` (= drawer gate only)
      is acceptable. There is no per-cubby minimum below the drawer gate;
      cap checks exist only where a cubby or lever actually declares one.

## Status

| Milestone                                                                           | Status                                                                 |
| ----------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| M0 Scaffold                                                                         | ✅                                                                     |
| M1 Drawer shell (trigger, drawer, focus, localStorage, toast/confetti)              | ✅ (CP1 passed)                                                        |
| M2 Access control + in-drawer settings + REST `/settings`                           | ✅ (CP2 passed)                                                        |
| M3 Built-in cubbies (notes/links/notifications)                                     | ✅ (CP3 passed)                                                        |
| M3.5 Launcher cascade + layout refinements                                          | ✅ (CP3.5 verified 2026-08-27)                                         |
| M4 Cubby API, registry, levers, extending doc                                       | ✅                                                                     |
| M5 Polish & release-readiness (i18n audit, `readme.txt`, RTL, Plugin Check, v1.0.0) | ✅ — tagged & released                                                 |
| CP4 pre-release human pass                                                          | ✅                                                                     |
| M6 Desk-odds-and-ends cubby pack                                                    | ⏳ in progress — dice ✅, vitals ✅, passphrase ✅; timer pending          |
| Post-release extras                                                                 | ✅ Socrates cubby, Playground demo, CI release workflow, README revamp |

## What's planned next

→ **PLAN.md** is the single source of truth for future work. Update the plan
there, and record what ships here.

## Repo conventions

- Branch `main`; feature branches → PR → squash merge; conventional commits
  (`feat:`, `fix:`, `chore:`, `docs:`, `ci:`, `release:`).
- SemVer (currently 1.0.0). WP minimum 6.4, PHP minimum 7.4.
- Prefix PHP: `secret_drawer_` / `Secret_Drawer_`.
- Release: `./bin/build.sh --release` (builds zip, excludes dev files, tags,
  pushes, cuts GH release; workflow verifies version/tag match). The build
  export doubles as the wp.org SVN trunk; `.wordpress.org/` = SVN assets dir.
- Lint: `find . -name '*.php' ! -path './vendor/*' -exec php -l {} \;`
