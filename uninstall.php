<?php
/**
 * Secret Drawer uninstall: remove every trace of the plugin.
 *
 * Naming convention (PLAN.md §5):
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
$cubby_keys = $GLOBALS['wpdb']->get_col(
	"SELECT DISTINCT meta_key FROM {$GLOBALS['wpdb']->usermeta} WHERE meta_key LIKE 'secret\\_drawer\\_cubby\\_%'"
);
foreach ( (array) $cubby_keys as $meta_key ) {
	delete_metadata( 'user', 0, $meta_key, '', true );
}

// Notifications cache transient (per-site on multisite).
delete_transient( 'secret_drawer_notif_' . get_current_blog_id() );