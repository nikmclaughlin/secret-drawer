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

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// M3: built-in cubbies.
		// M4: cubby registry (class-cubby-registry.php).
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
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
	 * Default settings. Shape is documented in PLAN.md §5.
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