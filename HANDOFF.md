# HANDOFF — M7 pack panels (pick up from fresh session)

State: **Library cubby removed** (registry entry, render case, plugin require,
class-cubby-library.php, all gone — verified lint + smoke clean). Pack panels
are **half-built**: shell/wiring exists, three + one pieces missing. Nothing
committed. Repo is secret-drawer (WP plugin, no build step).

## Done already (in working tree)

- `includes/class-cubby-registry.php` — `pack` field on all built-ins
  (`essentials` / `livin-large`), `packs()` method, `secret_drawer_packs`
  filter, humanized-id fallback. `'library'` entry + case **deleted**.
- `includes/class-plugin.php` — library require **deleted**.
- `includes/cubbies/class-cubby-library.php` — **deleted**.
- `assets/js/drawer.js`:
  - `openPanel(cubbyId, parent)` branches on `pack:` prefix → calls
    `packPanelBody(el.querySelector('.sd-body'), cubbyId)`, wires close
    button, skips REST + `emitCubbyShown`. Titles via `panelTitle()`
    (pack title from `config.packs`, already written).
  - `wirePackPanel(mount)` written: delegated clicks on
    `[data-pack-cubby]` rows + `.sd-pack-addall`; guarded on
    `state.draft && packPanelToggle`; re-wire guard via
    `mount.dataset.packWired`; calls `renderPackPanels()` after each toggle.
  - Module vars `packPanelToggle = null`, `packRepaints = []`,
    `renderPackPanels()` (prunes dead mounts via `document.contains`),
    `registerPackRepaint(mount)`.
  - `packPanelBody(mount, cubbyId)` exists — sets `mount.packRepaint`,
    registers it; **calls `packPanelHtml` which does NOT exist yet**.
  - Settings `toggleCubby(id,on)` now ends with `renderPackPanels()`.
  - Settings save: on success `packPanelToggle = null; closeAllPanels();`.
- Settings library (`libraryTop()`) keeps pack cards + ungrouped rows;
  pack card onClick already calls `openPackPanel(pid)`.

## Remaining work (in order)

1. **`packPanelHtml( packId, pack )`** — returns HTML string:
   optional `<p class="sd-muted sd-pack-desc">description</p>`, then one
   `<div class="sd-row" data-pack-cubby="{id}" data-on="1|0">` per member
   (strong title + muted description from `config.catalog`, button
   `sd-icon-button` label + / − mirroring draft membership, `aria-label`
   "(Add|Remove) {title}"), then Add-all `<button class="sd-pack-addall"
   data-pack="{packId}">Add all (N)</button>` only when N>0 (N = members
   minus draft-enabled). HTML-escape titles/descriptions (no escapeHtml
   helper exists — write a tiny one). Read draft via `state.draft`.
2. **`openPackPanel( pid )`** — one-liner near `openPanel`:
   `openPanel( 'pack:' + pid, null );`. (openPanel dedupes by cubbyId,
   restacks, so re-click toggling works like cubby cards.)
3. **Bridge assignment in `Settings()`** — on component run:
   `packPanelToggle = function ( id, on ) { draft.enabled_cubbies = on
   ? draft.enabled_cubbies.concat([id]) : draft.enabled_cubbies.filter(
   function(x){ return x !== id; } ); render(); renderPackPanels(); };`
   (or reuse existing toggleCubby — just assign it). Note: `render()`
   inside must NOT rebuild panels; repaint handles row state.
4. **Back button gap** — Settings back (←) sets `state.view='drawer'` but
   does NOT `closeAllPanels()` / clear `packPanelToggle`; back with open
   pack panels leaves stale panels. Treat like save: add both lines.

## Checks (hermit shell wraps node — exit codes lie!)

- `find . -name '*.php' ! -path './vendor/*' -exec php -l {} \; | grep -v 'No syntax errors'`
- Syntax: `node --check /Users/nik/Documents/GitHub/secret-drawer/assets/js/drawer.js`
  — ignore wrapper noise; **trust output lines, never exit code**.
- Behavior: `node /Users/nik/Documents/GitHub/secret-drawer/skills/create-cubby/smoke-drawer.js`
  → success = line `EXEC RESULT: drawer.js loads and runs clean`. Any
  `LOAD ERROR:` = failure, even if wrapper says "completed successfully".
- CSS braces balanced (currently 214/214); reduced-motion intact.
- Use **absolute paths** for node; hermit resolves relative from its own dir.

## Docs sync + gotchas (do same commit)

- **POT**: `class-cubby-registry.php` shrank (library entry removed) → refs
  staled again. Recompute with the Python pattern from session history
  (match `__\(\s*'…'`/`"…"`, rewrite `#:` refs; verify 0 unmatched + no dupes).
  Also remove now-dead msgids: 'Cubby library', the
  'Add and remove cubbies…' description (refs only pointed at the deleted class).
- **AGENTS.md**: delete the Library row in the built-in cubby table; update
  the M7/Cubby Library mentions to the new design: "library lives in
  settings; clicking a pack opens a pop-out panel (client-rendered from
  `config.packs`, edits the settings draft, Save commits, panels close on
  Save/Back; `pack:{id}` synthetic panel id; no REST for packs)".
- **readme.txt**: packs bullet still describes the old per-pack page — rewrite
  (settings library → pack cards → pop-out panel with Add all).
- **PLAN.md**: mark the design change (user call: library NEVER in main
  drawer screen; whole-menu cubby scrapped).
- **SECRET-DRAWER-EXTENDING.md / SKILL.md**: only if they name the library
  cubby itself — check, don't assume.
- Edits kept failing earlier due to malformed before/after strings — always
  re-read the exact region (`sed -n`) immediately before each `edit`, keep
  replacements minimal, and verify with `node --check` + grep after.
- Test flow for Nik (LocalWP dev site): settings gear → Cubby Library →
  click Essentials card → panel pops with rows + Add all → toggles mark
  draft → Save applies live → panels close → launcher never shows packs.