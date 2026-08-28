=== Secret Drawer ===
Contributors: nik
Tags: admin, drawer, notes, privacy, productivity
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A hidden drawer in wp-admin, unlocked by a secret word. Silly to find, genuinely useful to have.

== Description ==

Type the secret word on any wp-admin page and a drawer slides in from the edge: your notes, your quick links, site notifications — whatever you've tucked away.

Nobody else sees it. Nobody else knows it's there.

**How it works**

1. Pick your secret word and who's allowed in (Drawer settings, inside the drawer).
2. On any admin screen, type the word — anywhere, outside a text field.
3. The drawer slides out. Your cubbies are inside.

**Cubbies included**

* **Notes** — per-user scratch notes with autosave. Toss in a thought, find it later.
* **Quick Links** — your personal jump list to the admin screens you actually use.
* **Notifications** — truthful counts of things needing attention, with deep links.
* **Levers** — one-click actions like emptying the trash, with confirmations on anything destructive.
* **The Cubby Library** — enable, disable, and (with one filter) add your own cubbies.

**A few design promises**

* The drawer's contents are per-user. Roles only decide who can find it, not what's inside.
* The trigger word is never shown on screen — even the unlock flash just says "It's a secret!"
* Nothing phones home. No registration, no keys, no telemetry.
* Everything respects `prefers-reduced-motion`.

**For developers:** add your own cubbies and levers with one filter — see `SECRET-DRAWER-EXTENDING.md` in the plugin folder.

== Installation ==

1. Upload the `secret-drawer` folder to `/wp-content/plugins/`, or install the zip via Plugins → Add New → Upload.
2. Activate the plugin.
3. Open any wp-admin page and type `hellodolly` (the default secret word, outside a text field).
4. Click ⚙️ in the drawer to choose your own word, roles, position, and cubbies.

== Frequently Asked Questions ==

= Who can see the drawer? =

Only users whose role you've allowed. Each user gets their own notes and quick links; the drawer's existence is never advertised — no admin-bar link, no menu entry, no badge.

= What's the default secret word? =

`hellodolly` — you should change it immediately in the drawer settings (⚙️). It's typed on any admin screen outside text fields; modifier keys are ignored, so it won't fight your shortcuts.

= Does it work on the front end? =

No. Secret Drawer lives entirely in wp-admin.

= Can I add my own cubbies? =

Yes — one PHP filter registers a cubby type (icon, title, render callback) and it appears in the Cubby Library. See `SECRET-DRAWER-EXTENDING.md`.

= Where does my data live? =

Notes and links are stored per-user in user meta. Notifications are computed from your site (with a short cache). Nothing is sent anywhere else.

== Screenshots ==

1. The launcher drawer, tucked against the right edge.
2. A Notes cubby popped out beside it, with an open editor.
3. Drawer settings: secret word, roles, position, and the Cubby Library.
4. The bottom-sheet variant, with cubby panels rising from its edge.

== Changelog ==

= 1.0.0 =
* First tagged release: secret-word trigger, launcher drawer with right and bottom positions, per-user Notes / Quick Links / Notifications / Levers cubbies, cascading pop-out panels, settings, the Cubby Library, and a developer API (`secret_drawer_cubbies` filter, `window.SecretDrawer`, document events).

= 0.1.0 =
* Development releases (GitHub only).

== Upgrade Notice ==

= 1.0.0 =
First release.