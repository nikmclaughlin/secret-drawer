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
  (`secretDrawer.dice.last5`), shown as "1 · 20 · 4 · 8 · 2".
  (`secretDrawer.dice.last5`), shown as "1 · 20 · 4 · 8 · 2".
  No REST endpoint. Launcher icons for the whole pack are literal emoji
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
- **📊 Site vitals** — quick-glance card: WP/PHP versions, memory limit,
  debug on/off, active theme. Server-rendered via the registry (one cached
  query), like Site Health's CliffNotes.
- **🔐 Passphrase generator** — random words + numbers, length picker,
  one-click copy. Client-side only; nothing leaves the browser (this is a
  security-adjacent plugin — the passphrase must never hit the server).
- **⏱️ Focus timer** — 25-minute pomodoro in a panel. When the timer
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