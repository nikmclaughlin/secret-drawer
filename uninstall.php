<?php
/**
 * Secret Drawer uninstall: remove every trace of the plugin.
 *
 * Naming convention (see AGENTS.md, Settings & data model):
 * - Drawer-scoped: `secret_drawer_{thing}` (settings, discovered).
 * - Cubby-scoped per-user data: `secret_drawer_cubby_{id}` — swept by
 *   pattern so third-party cubbies are cleaned up too.
 *
 * @package Secret_Drawer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Site-level settings.
delete_option( 'secret_drawer_settings' );

// Drawer-level user flag.
delete_metadata( 'user', 0, 'secret_drawer_discovered', '', true );

// Sweep all cubby-scoped usermeta (secret_drawer_cubby_*).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$secret_drawer_cubby_keys = $GLOBALS['wpdb']->get_col(
	"SELECT DISTINCT meta_key FROM {$GLOBALS['wpdb']->usermeta} WHERE meta_key LIKE 'secret\\_drawer\\_cubby\\_%'"
);
foreach ( (array) $secret_drawer_cubby_keys as $secret_drawer_meta_key ) {
	delete_metadata( 'user', 0, $secret_drawer_meta_key, '', true );
}

// Cached transients. Keys live in the cubby classes but are inlined here:
// uninstall runs outside the plugin bootstrap, no class loading needed.
// delete_transient() applies the current blog's prefix on multisite, and
// WordPress executes this file once per site, so per-site caches are swept.
delete_transient( 'secret_drawer_notifications' );
delete_transient( 'secret_drawer_vitals' );