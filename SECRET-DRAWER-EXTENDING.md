# Extending Secret Drawer

Secret Drawer ships with nine built-in cubbies — Notes, Quick Links,
Notifications, Levers, Socrates, Dice, Site Vitals, Passphrase, and
Focus Timer — but the drawer is meant to be extended.
This guide shows how other plugins (or your site's own `functions.php`,
if you insist) add their own cubbies and levers.

Everything below hooks the two public filters and, optionally, the
public JS API. Nothing in this document touches Secret Drawer's
internals.

---

## Registering a cubby type

Hook the `secret_drawer_cubbies` filter. You receive the full catalog
(built-ins included, keyed by cubby id) and return it, adding your own
entry or overriding a built-in.

```php
add_filter( 'secret_drawer_cubbies', function ( array $cubbies ): array {
	$cubbies['todo'] = [
		'id'          => 'todo',                  // [a-z0-9_-]+ — used in the REST route
		'title'       => __( 'Team Todo', 'my-plugin' ),
		'description' => __( 'A shared list of things to do.', 'my-plugin' ),
		'icon'        => 'dashicons-list-view',   // any dashicons-* class, or a literal glyph (e.g. an emoji)
		'capability'  => 'edit_pages',            // per-type gate (optional)
		'singleton'   => true,                    // v1: all types are single-instance
		'order'       => 40,                      // sort position (default 50)
		'refresh_on'  => 'open',                  // or 'never'
		'render'      => function (): string {    // server-rendered body HTML
			return '<p>Nothing to do. Go home.</p>';
		},
	];
	return $cubbies;
} );
```

### Field reference

| Field         | Type     | Required | Notes                                                                 |
|---------------|----------|----------|-----------------------------------------------------------------------|
| `id`          | string   | yes      | Array key; must match `[a-z0-9_-]+` (it becomes a REST route segment). |
| `title`       | string   | yes      | Entries without a title are treated as hidden and dropped.             |
| `description` | string   | no       | Shown in the cubby library / settings picker.                          |
| `icon`        | string   | no       | Dashicon class (e.g. `dashicons-smiley`) or a literal glyph (emoji works — anything not prefixed `dashicons-` renders as the text). Defaults to `dashicons-marker`. |
| `capability`  | string   | no       | Per-type gate, checked against the current user at enqueue *and* at render time. |
| `singleton`   | bool     | no       | Reserved. All v1 cubby types are single-instance.                      |
| `order`       | int      | no       | Sort position in the launcher; ties keep registration order.           |
| `refresh_on`  | string   | no       | `'open'` (default) re-fetches the body each time the cubby opens; `'never'` fetches once per page load. |
| `render`      | callable | no       | Returns the cubby's body HTML. Built-ins omit this and use their dedicated classes. |

### How it surfaces

- **Launcher card** — your cubby appears in the card grid if the user has
  enabled it (Cubby library → your type). The library lists every
  registered type the current user can see (capability gate applies).
- **Body HTML** — when the user opens the cubby, Secret Drawer calls your
  `render` callback over REST and mounts the returned HTML in the panel.
  Return an empty string and the cubby 404s.
- **Settings sanitizer** — `enabled_cubbies` validates against *all*
  registered ids, regardless of who is saving or what they can see, so a
  cubby a user can't access can still be enabled for others.

### Rules of the road

- **The server is the source of truth.** Your `render` callback runs
  server-side; anything capability-gated must be checked *again* inside
  your render (the registry only checks the type's declared
  `capability` field).
- **Escape your output.** The panel mounts your HTML as-is — use
  `esc_html()`, `esc_attr()`, `wp_kses_post()` as appropriate. Secret
  Drawer does not sanitize cubby HTML.
- **Keep it user-scoped or site-scoped deliberately.** Notes are
  per-user (usermeta); Notifications are site-wide (transient-cached).
  Decide which your data is before you store it.
- **IDs are forever.** Once a cubby id ships, third parties may enable
  it by id in `enabled_cubbies`; renaming breaks their settings.

---

## Adding levers (one-click actions)

Levers are the "pull" half of the drawer. Hook `secret_drawer_levers`
to add your own or remove built-ins:

```php
add_filter( 'secret_drawer_levers', function ( array $levers ): array {
	$levers['regenerate_my_cache'] = [
		'label'       => __( 'Flush my plugin cache', 'my-plugin' ),
		'description' => __( 'Clears the My Plugin object cache.', 'my-plugin' ),
		'confirm'     => __( 'Flush the cache now?', 'my-plugin' ), // '' = no confirmation
		'cap'         => 'manage_options',  // re-checked at pull time
		'action'      => [ My_Cache::class, 'flush' ], // callable, runs in the REST handler
	];
	return $levers;
} );
```

| Field         | Notes |
|---------------|-------|
| `label`       | Button text. Required. |
| `description` | Small line under the button. Optional. |
| `confirm`     | Confirmation message shown before pulling. Empty string = pull immediately. |
| `cap`         | Capability checked against the current user at pull time (the lever only renders if the user passes this check too). |
| `action`      | PHP callable run server-side. Return anything JSON-serializable; it's echoed back to the JS. `null` = client-side lever (no REST call). |

The REST route is `POST /secret-drawer/v1/cubbies/levers/pull` with
`{ "id": "your_lever_id" }`. It validates the id against the filtered
catalog, re-checks `cap`, and returns `{ ok: true, lever, result }`.
Lever ids not in the catalog 404; capability failures 403.

**Destructive levers must set `confirm`.** Empty Trash is the example to
follow.

---

## Driving the drawer from JS

A `window.SecretDrawer` global and a couple of document events are the
public front-end surface:

```js
// Open/close/toggle the whole drawer.
window.SecretDrawer.open();
window.SecretDrawer.close();
window.SecretDrawer.toggle();

// Open the drawer and pop a cubby out (no-op if it's already open).
window.SecretDrawer.showCubby( 'todo' );

// Events dispatched on `document`:
document.addEventListener( 'secret-drawer:open', () => { /* ... */ } );
document.addEventListener( 'secret-drawer:close', () => { /* ... */ } );
document.addEventListener( 'secret-drawer:cubby:shown', ( e ) => {
	console.log( 'cubby shown:', e.detail.id ); // e.g. 'todo'
} );
```

---

## A complete drop-in example

Paste into a plugin file (or a one-off `wp-content/mu-plugins/` file)
and the cubby appears in the library — enable it from the drawer's ⚙️
settings and it becomes another card:

```php
<?php
/**
 * Plugin Name: Secret Drawer — Example Cubby
 * Description: Registers a "Mood" cubby via the Secret Drawer cubby API.
 */

add_filter( 'secret_drawer_cubbies', function ( array $cubbies ): array {
	$cubbies['mood'] = [
		'id'          => 'mood',
		'title'       => __( 'Mood', 'example' ),
		'description' => __( 'How is the site feeling today?', 'example' ),
		'icon'        => '🎭',
		'order'       => 45,
		'render'      => function (): string {
			$moods = [ 'thrilled', 'fine', 'holding on', 'chaotic' ];
			$mood  = $moods[ wp_rand( 0, count( $moods ) - 1 ) ];
			return '<p>Today the site feels <strong>' . esc_html( $mood ) . '</strong>.</p>';
		},
	];
	return $cubbies;
} );
```

That's the whole AC: a drop-in file, one filter, one render callback —
and the drawer grows a new cubby with its own card, panel, and REST
route, with zero changes to Secret Drawer itself.

---

## Need more than a filter callback?

Drop-ins cover server-rendered bodies well. If your cubby needs its own
client wiring in `drawer.js`, its own REST routes, or anything else that
means editing Secret Drawer itself, point your coding assistant at
**`skills/create-cubby/SKILL.md`** in this folder — it walks the full
recipe the plugin's own cubbies use: shape choice, the user-data /
session-data / secrets split, i18n, and the load-time smoke test
(`node skills/create-cubby/smoke-drawer.js`) that proves `drawer.js`
still runs after your edits. Remember that editing the plugin's files
means the next Secret Drawer update will overwrite your work — keep a
real fork.