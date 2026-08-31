<?php
/**
 * Main plugin singleton: hooks everything together.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Plugin
 */
final class Secret_Drawer_Plugin {

	const OPTION_SETTINGS = 'secret_drawer_settings';

	/**
	 * Singleton instance.
	 *
	 * @var Secret_Drawer_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Boot the plugin. Called on plugins_loaded.
	 *
	 * @return Secret_Drawer_Plugin
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up hooks. Milestones add their own hook registrations here.
	 */
	private function __construct() {
		require_once SECRET_DRAWER_DIR . 'includes/class-assets.php';
		new Secret_Drawer_Assets();

		require_once SECRET_DRAWER_DIR . 'includes/class-settings.php';
		require_once SECRET_DRAWER_DIR . 'includes/class-rest.php';
		new Secret_Drawer_Rest();

		// Built-in cubbies (M3). M4: move the catalog to class-cubby-registry.php.
		require_once SECRET_DRAWER_DIR . 'includes/cubbies/class-cubby-notes.php';
		require_once SECRET_DRAWER_DIR . 'includes/cubbies/class-cubby-links.php';
		require_once SECRET_DRAWER_DIR . 'includes/cubbies/class-cubby-notifications.php';
		require_once SECRET_DRAWER_DIR . 'includes/cubbies/class-cubby-levers.php';
		require_once SECRET_DRAWER_DIR . 'includes/cubbies/class-cubby-socrates.php';
		require_once SECRET_DRAWER_DIR . 'includes/cubbies/class-cubby-dice.php';
		require_once SECRET_DRAWER_DIR . 'includes/cubbies/class-cubby-vitals.php';
		require_once SECRET_DRAWER_DIR . 'includes/cubbies/class-cubby-passphrase.php';
		require_once SECRET_DRAWER_DIR . 'includes/cubbies/class-cubby-timer.php';
		Secret_Drawer_Cubby_Notifications::hooks();

		require_once SECRET_DRAWER_DIR . 'includes/class-cubby-registry.php';
		require_once SECRET_DRAWER_DIR . 'includes/class-rest-cubbies.php';
		new Secret_Drawer_Rest_Cubbies();

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Levers cubby: one-click actions (ships with the Cubby API at M4).
		// M4: cubby registry now lives in class-cubby-registry.php.
	}

	/**
	 * Load translations for custom-language-directory installs (WP-CLI,
	 * mu-loading). When hosted on WordPress.org, translations for this
	 * slug load automatically — this is belt-and-braces only, so it
	 * deliberately runs after the plugin's own languages/ dir first.
	 */
	public function load_textdomain() {
		if ( is_textdomain_loaded( 'secret-drawer' ) ) {
			return;
		}
		load_plugin_textdomain(
			'secret-drawer',
			false,
			dirname( plugin_basename( SECRET_DRAWER_FILE ) ) . '/languages'
		);
	}

	/**
	 * Seed default settings on first activation only.
	 */
	public static function activate() {
		if ( false === get_option( self::OPTION_SETTINGS, false ) ) {
			add_option( self::OPTION_SETTINGS, self::default_settings() );
		}
	}

	/**
	 * Deactivation: nothing to clean up (uninstall removes all data).
	 */
	public static function deactivate() {
		// Intentionally empty.
	}

	/**
	 * Default settings. Shape is documented in AGENTS.md, Settings & data model.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'version'         => SECRET_DRAWER_VERSION,
			'roles'           => array( 'administrator' ),
			'trigger_word'    => 'hellodolly',
			'enabled_cubbies' => array( 'notes', 'links', 'notifications' ),
			'width'           => 320,
			'position'        => 'right',
		);
	}

	/**
	 * Access gate. Server is the source of truth — callers must rely on
	 * this before enqueueing anything or serving any data. Every REST
	 * permission callback re-checks via this method.
	 *
	 * @return bool
	 */
	public static function user_can_access() {
		$user   = wp_get_current_user();
		$roles  = (array) Secret_Drawer_Settings::get()['roles'];
		$allowed = false;
		foreach ( $roles as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				$allowed = true;
				break;
			}
		}

		/**
		 * Override the access gate. Returning false hides the drawer
		 * completely (no JS is ever enqueued for that user).
		 *
		 * @param bool   $allowed Whether the current user may access the drawer.
		 * @param WP_User $user    Current user.
		 */
		return (bool) apply_filters( 'secret_drawer_user_can_access', $allowed, $user );
	}

	/**
	 * No cloning.
	 */
	private function __clone() {}

	/**
	 * No unserializing.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, 'Secret_Drawer_Plugin is a singleton.', '0.1.0' );
	}
}