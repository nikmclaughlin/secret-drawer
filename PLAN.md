# Secret Drawer — Remaining plan

> Everything already implemented now lives in **AGENTS.md** (the as-built
> reference). This file holds only what's *planned next*: the M6 cubby pack,
> the preserved fun backlog, and still-open design questions.

---

## M6 — Desk-odds-and-ends cubby pack (planned next)

Nik picked four cubbies for the starting library — all client-side except
Site Vitals. Build order: dice → vitals → passphrase → timer.

- ✅ **🎲 Dice roller** — SHIPPED. Registered as the `dice` cubby. Picker is
  a piped radio chip group (d2/d6/d12/d20, keyboard-focusable), Roll button
  decides instantly via Math.random; the 🎲 face tumbles in CSS
  (`sd-dice-tumble`, skipped under `prefers-reduced-motion`) and the number
  settles ~650ms later. Last five rolls persist in localStorage
  (`secretDrawer.dice.last5`), shown as "1 · 20 · 4 · 8 · 2". No REST
  endpoint. Launcher icons for the whole pack are literal emoji
  glyphs — the launcher treats any icon value not prefixed `dashicons-`
  as the glyph text itself (`sd-glyph` class).
- ✅ **📊 Site vitals** — SHIPPED. The pack's one server-rendered cubby:
  `<dl>` of vital cards: WordPress + PHP versions, memory limit, WP_DEBUG,
  active theme w/ version; plus a quiet health block — **autoloaded
  options size** (Site Health's own algorithm + 800KB threshold,
  including its filter, so the two tools always agree), **plugins as an
  active | inactive split count** (warns at 5+ inactive, `INACTIVE_WARN`),
  **missed schedules** (future posts past publish time = WP-Cron telltale,
  deep links to the stuck list) and a **live site clock** (re-computed at
  render, never cached — hourly snapshot of "now" would be a lie).
  Rows support {label, value, value2 (second half of a split card),
  note/note2 (small captions), ok, url, warn_side}: split cards fill
  the card width with a centered divider, and warn_side (1|2) ambers
  just the offending half + divider + card edge (plugins: inactive
  side when 5+ inactive, `INACTIVE_WARN`). Later rows: environment
  type (identity, never judged), HTTPS on home_url (off trips warn,
  config-read — no network probe), object cache present/none.
  Filterable via `secret_drawer_vitals`; hourly transient (schema-
  keyed, bump `CACHE_SCHEMA` when the row shape changes) except the
  clock; display-only (no `wireCubby()`, no REST). Launcher icon 🩺.
- ✅ **🔐 Passphrase** — SHIPPED. Client-only cubby #2: 3–6 word picker
  + "Add number" toggle; words drawn **without replacement** from a
  256-word list (8 bits/word — verified exactly 256 unique entries,
  one dup caught and fixed in review) + optional two-digit suffix
  (≈6.6 bits), via `crypto.getRandomValues`; the readout shows an
  approximate bit count and the note states the promise: generated in
  your browser, **never sent anywhere** — no REST, no storage at all,
  and the JS wiring block contains no fetch() to contradict it.
  Copy via clipboard API (denied/no-API failures show an amber warn
  snackbar: "Copy failed — couldn’t access the clipboard." —
  `sd-toast--warn`, `role="alert"`), honest degradation with
  a translated message if crypto is unavailable. Launcher icon 🔐.
- ✅ **⏱️ Focus timer** — SHIPPED. The state machine lives in drawer
  scope, NOT the panel DOM: `timer` = {remaining, total, running,
  endAt, tick}; any panel pop/re-open re-paints from state on mount
  (`paintTimer()` on wire). 250ms `setTimeout` tick compares
  `Date.now()` to `endAt` — drift-free and pause-safe. Finish: pulse
  animation on the ring (skipped under `prefers-reduced-motion`),
  auto re-pop via `showCubby('timer')` ("show" semantics won't fight
  an open panel), "Time's up — nice focus." snackbar, and a
  `secret-drawer:cubby:timer:done` event for extension authors.
  Reset-to-duration after finish; drawer close flushes state (the
  plan's rule); picking a chip mid-count resets (deliberate — no
  surprise jumps on a running timer). No REST, no storage. Launcher
  icon ⏱️.
- **📊 Site vitals** — quick-glance card: WP/PHP versions, memory limit,
  debug on/off, active theme. Server-rendered via the registry (one cached
  query), like Site Health's CliffNotes.
- **🔐 Passphrase generator** — random words + numbers, length picker,
  one-click copy. Client-side only; nothing leaves the browser (this is a
  security-adjacent plugin — the passphrase must never hit the server).
- **⏱️ Focus timer** — short pomodoro in a panel (1/5/10/20 presets, 20 default). When the timer
  finishes it re-opens its own cubby panel if closed and plays an
  attention-grabby-but-not-overdone finish animation. Timer state is
  cleared when the whole drawer closes (assume the user doesn't want it).
  Careful bits: countdown must survive panel close (state lives in the
  drawer, not the panel element), and the finish pulse respects
  reduced-motion.

**AC:** the four cubbies appear in the Cubby library, render in right and
bottom modes, respect reduced-motion for all animations, and the passphrase
is provably never sent to the server (no REST call on generate/copy).

---

## M7 — Cubby packs (planned next)

Packs group cubbies in the Cubby Library: top level shows pack cards,
clicking one enters its detail list, and "Add all N" bulk-enables members.
**Packs are presentation metadata only** — `enabled_cubbies` stays a flat
cubbid list, so reorganizing packs never touches anyone's saved drawer.
Derived fresh from the registry each request; nothing pack-level is ever
persisted, and that rule keeps reorgs free forever.

- Registry: entry field `pack` (id, `^[a-z0-9_-]+$`, default `''`); new
  `packs()` catalog from a `secret_drawer_packs` filter (`id → {title,
  icon, description, order}`), humanized-id fallback when cubbies reference
  an unlisted pack. Built-ins: **Essentials** (Notes, Quick Links,
  Notifications, Levers, Socrates — Socrates stays: originating joke),
  **Livin' Large** (Dice, Site Vitals, Passphrase, Focus timer — Sims
  throwback; "pack" pun pays it off). Ungrouped cubbies stay flat library
  rows (third-party default).
- Localization: `catalog[id]` gains `pack`; new `config.packs`; strings
  "Add all", "Back to packs", "%d of %d added".
- UI: library top level = pack cards (icon/title/description/count chip);
  pack detail sub-view in Settings via `state.libraryPack` (back arrow);
  "Add all N" is idempotent, disabled at completion. Launcher and the
  "In your drawer" list stay pack-blind.
- CSS: pack cards reuse the launcher-card pattern; detail rows reuse
  `sd-row`.
- Docs (same commit): EXTENDING (`pack` field + `secret_drawer_packs` +
  fallback note), create-cubby skill step 1 (pick-or-create the pack — the
  thematic surface), AGENTS.md registry blurb, readme.txt bullet, POT.
- Checks: php -l, node --check exit code, smoke-drawer.js (harness config
  gains `packs`), CSS brace count. Then Nik tugs the library flow.

**Design change (2026-08-31, Nik's call):** the Library never appears in the
main drawer, and the whole-sub-view detail menu is scrapped. As built: the
Cubby Library lives in Settings; pack cards open pop-out panels,
client-rendered from `config.packs` (synthetic `pack:{id}` panel id, no
REST); rows edit the settings draft, Save commits, panels close on Save and
Back. `state.libraryPack`, the back-arrow sub-view, and the library cubby
itself never shipped — that v1 attempt is in git history only.

## Fun backlog (the silly part, preserved for later)

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

## Still-open design questions

1. **Shared content:** should any cubby be site-wide rather than
   per-user? (e.g. "Site notes" every admin sees — candidate for v2.)
2. **Rich notes:** plain textarea for v1 acceptable, or markdown-ish
   rendering immediately?
3. **Multisite:** network-activate behavior — settings network-wide, or
   per-site? (Recommend: per-site for v1; network toggle later.)