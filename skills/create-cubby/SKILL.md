---
name: create-cubby
description: >
  Build a new Secret Drawer cubby — end to end: PHP markup, client wiring,
  registry entry, i18n, checks. Use when adding a cubby to Secret Drawer,
  whether in this repo or in a self-hosted copy the user is customizing,
  building a drop-in from the extending guide, or when the user asks for
  "a new cubby" or "another panel" in Secret Drawer.
---
# Create a Secret Drawer cubby

**Pick the integration path first — it decides how much of this applies.**

- **Drop-in via filter (no core edits, update-proof).** Hook
  `secret_drawer_cubbies` from your own plugin or a `mu-plugins` file with
  a `render` callback. If that covers your cubby, the full recipe is
  **`SECRET-DRAWER-EXTENDING.md`** (field reference, rules of the road,
  paste-in example) — use it, and only steps 2, 3, and 6 below carry over
  to your render callback. A Secret Drawer update never touches your file.
  If your drop-in **enqueues its own JS**, run the same load-time smoke
  test on it before finishing:
  `node skills/create-cubby/smoke-drawer.js path/to/your-script.js`
  (from the plugin root). It prints
  `EXEC RESULT: <file> loads and runs clean` only on a full clean load.
- **Editing Secret Drawer itself** (your fork or a modified copy on your
  site). Needed for client wiring in `drawer.js`, new REST routes, or a
  built-in-grade cubby. Steps 1–6 are the process the built-ins were
  built with. Know the cost: the next Secret Drawer update overwrites
  every plugin file you touch. Maintain a real fork and re-apply changes
  across updates — don't assume pressing "update" is safe.

Either way, the schema reference — entry fields, lever schema, drop-in
example — lives in `SECRET-DRAWER-EXTENDING.md` in the plugin root. Read
it; don't restate it.

1. **Pick the shape before code.** Three exist:
   - *Server-rendered* (notifications, vitals; the markup of passphrase and
     timer): class in `includes/cubbies/class-cubby-{id}.php`, a `render()`
     case + catalog entry in `class-cubby-registry.php`, a `require_once`
     in `class-plugin.php`. If building the body is expensive, cache it in
     an hourly transient keyed by a `CACHE_SCHEMA` constant — **bump the
     constant whenever the row shape changes**, or users see yesterday's
     snapshot and file a bug.
   - *Client-wired* (dice, passphrase, timer): the same server shell, plus
     a `wire{Name}( mount )` in `drawer.js` and a dispatch branch in the
     mount switch. Idempotence via a `dataset.*Wired` flag; delegated
     clicks, never per-element listeners. State that must survive the
     panel being popped closed and re-opened lives in drawer scope, not
     the panel DOM — paint it back on mount.
   - *Display-only* (notifications, vitals): no wire call at all. Say so
     in a comment so the next cubby author knows why.
2. **Honor the data split.** User data = REST + usermeta (or options for
   site-wide data), behind the drawer's access gate — re-check the gate
   inside every REST permission callback; never trust the enqueue-time
   check alone. Session junk = `localStorage` under `secretDrawer.*`.
   Secrets (anything a user might reuse elsewhere) = never leave the
   browser: no storage of any kind, no fetch, and *prove it* by grepping
   your block. This split is the plugin's promise to its users.
3. **Strings go through i18n on first write**, both directions: `__()`
   in PHP (built-ins use the `secret-drawer` textdomain) and
   `config.strings` in JS share one msgid; keep the POT entry's line refs
   true in the same commit. A string that exists in one file but not the
   other is drift. On your own copy, use your own textdomain.
4. **Sync the words users actually see.** The launcher description in the
   registry IS the user-facing doc — keep it short, accurate, and current;
   a shipped feature with a stale blurb is drift. (Contributors to the
   Secret Drawer repo also sync `readme.txt`, `AGENTS.md`, and `PLAN.md`
   per its working rules; customizers on their own sites can skip those,
   but if you share your fork, sync its docs the same way.)
5. **Gate on real checks, in this order:**
   - `php -l` every touched file; a full sweep before declaring done.
   - **`node --check`: trust the exit code only.** Wrappers and CI logs
     have been observed printing "completed successfully" on syntax
     errors. Never read the log line.
   - **Load-time smoke test**: run
     `node skills/create-cubby/smoke-drawer.js` from the repo root. It
     executes `drawer.js` top to bottom under a stub browser and prints
     `EXEC RESULT: drawer.js loads and runs clean` ONLY on success.
     Parse-pass is not pass; the file must *run* — this is what catches an
     edit that swallowed a closing brace and silently nested half the
     file. Judge by the marker line (some wrappers exit 0 and log
     "completed successfully" no matter what), and expect your shell's
     exit code to agree in a normal terminal.
   - CSS: count braces; new animations go inside (or alongside) the
     `prefers-reduced-motion` handling in `drawer.css`.
6. **Review your own diff at the seams.** Most breakage in this cubby
   pack happened at insertion points: an edit that ate a closing brace,
   a units bug (`* 1000` vs `* 60`), a duplicated wordlist entry. After
   every edit, re-read 10 lines around the seam before moving on.

Done = full check sweep clean and a human tugs it in a browser: unlock
the trigger word, find the launcher card, open the panel, exercise what
the cubby promises. Contributors hand git ops to the repo's maintainers
per its AGENTS.md; site owners customizing their own copy — that's you.