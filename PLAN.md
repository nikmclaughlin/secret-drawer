# Secret Drawer — Remaining plan

> Everything already implemented now lives in **AGENTS.md** (the as-built
> reference). This file holds only what's *planned next*: the M6 cubby pack,
> the preserved fun backlog, and still-open design questions.

---

## M6 — Desk-odds-and-ends cubby pack (planned next)

Nik picked four cubbies for the starting library — all client-side except
Site Vitals. Build order: dice → vitals → passphrase → timer.

- **🎲 Dice roller** — pick d2 / d6 / d12 / d20, click Roll, tumble
  animation, random result. Purely client-side (`Math.random`); CSS tumble
  honoring `prefers-reduced-motion`; a running "last 5 rolls" line so it
  feels alive. No REST endpoint needed.
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