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

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// M2: settings (class-settings.php) + role gate.
		// M3: built-in cubbies.
		// M4: cubby registry (class-cubby-registry.php) + REST (class-rest.php).
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
	 * this before enqueueing anything or serving any data.
	 *
	 * M2: settings-driven role list + per-role checks land here; the
	 * filter is already live so the shape of the API is fixed at M0.
	 *
	 * @return bool
	 */
	public static function user_can_access() {
		$allowed = current_user_can( 'manage_options' );
		return (bool) apply_filters( 'secret_drawer_user_can_access', $allowed, wp_get_current_user() );
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