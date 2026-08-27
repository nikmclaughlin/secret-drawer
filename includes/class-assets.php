<?php
/**
 * Asset enqueue + front-end configuration.
 *
 * Nothing is enqueued unless the user passes the access gate — the drawer
 * simply does not exist for users who aren't allowed to find it.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Assets
 */
class Secret_Drawer_Assets {

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue drawer assets + localize config on every admin page the user
	 * may discover the drawer on.
	 */
	public function enqueue() {
		if ( ! Secret_Drawer_Plugin::user_can_access() ) {
			return;
		}

		$settings = self::settings();

		// Core-bundled wp-components pulls react/react-dom with it.
		wp_enqueue_script( 'wp-components' );

		wp_enqueue_style(
			'secret-drawer',
			SECRET_DRAWER_URL . 'assets/css/drawer.css',
			array(),
			self::version( 'assets/css/drawer.css' )
		);

		wp_enqueue_script(
			'secret-drawer',
			SECRET_DRAWER_URL . 'assets/js/drawer.js',
			array( 'wp-components' ),
			self::version( 'assets/js/drawer.js' ),
			true
		);

		wp_localize_script( 'secret-drawer', 'SECRET_DRAWER', self::config( $settings ) );
	}

	/**
	 * filemtime() cache-busting during development; version between releases.
	 *
	 * @param string $relative_path Path relative to plugin root.
	 * @return string
	 */
	private static function version( $relative_path ) {
		$path = SECRET_DRAWER_DIR . $relative_path;
		return file_exists( $path ) ? (string) filemtime( $path ) : SECRET_DRAWER_VERSION;
	}

	/**
	 * Settings with defaults applied. Replaced by class-settings.php at M2.
	 *
	 * @return array
	 */
	private static function settings() {
		return wp_parse_args( (array) get_option( Secret_Drawer_Plugin::OPTION_SETTINGS, array() ), Secret_Drawer_Plugin::default_settings() );
	}

	/**
	 * Everything the front end needs. No user data beyond the trigger word
	 * and drawer chrome — cubby bodies are fetched lazily over REST.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	private static function config( $settings ) {
		return array(
			'restRoot'   => esc_url_raw( rest_url( 'secret-drawer/v1' ) ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'trigger'    => (string) apply_filters( 'secret_drawer_trigger_word', $settings['trigger_word'] ),
			'width'      => (int) $settings['width'],
			'position'   => 'bottom' === $settings['position'] ? 'bottom' : 'right',
			'discovered' => (bool) get_user_meta( get_current_user_id(), 'secret_drawer_discovered', true ),
			'cubbies'    => self::cubbies_for_user( $settings ),
			'strings'    => array(
				'title'      => __( 'Secret Drawer', 'secret-drawer' ),
				'close'      => __( 'Close', 'secret-drawer' ),
				'settings'   => __( 'Drawer settings', 'secret-drawer' ),
				'toast'      => __( '🔓 You found the Secret Drawer.', 'secret-drawer' ),
				'emptyCubby' => __( 'Nothing here yet.', 'secret-drawer' ),
				'loadError'  => __( 'Could not load this cubby.', 'secret-drawer' ),
			),
		);
	}

	/**
	 * Enabled cubby metadata for the tab strip. M4: this moves to
	 * class-cubby-registry.php + the secret_drawer_cubbies filter; the
	 * shape (id/title/icon) is fixed now so the JS never changes.
	 *
	 * @param array $settings Settings.
	 * @return array[]
	 */
	private static function cubbies_for_user( $settings ) {
		$catalog = array(
			'notes'         => array(
				'title' => __( 'Notes', 'secret-drawer' ),
				'icon'  => 'dashicons-edit-page',
			),
			'links'         => array(
				'title' => __( 'Quick Links', 'secret-drawer' ),
				'icon'  => 'dashicons-admin-links',
			),
			'notifications' => array(
				'title' => __( 'Notifications', 'secret-drawer' ),
				'icon'  => 'dashicons-bell',
			),
		);

		$out = array();
		foreach ( (array) $settings['enabled_cubbies'] as $id ) {
			if ( isset( $catalog[ $id ] ) ) {
				$out[] = array_merge( array( 'id' => $id ), $catalog[ $id ] );
			}
		}
		return $out;
	}
}