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

		// Let JS translation bundles (languages/secret-drawer-*.json) override
		// the localized strings at runtime.
		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'secret-drawer', 'secret-drawer', SECRET_DRAWER_DIR . 'languages' );
		}
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
	 * Settings with defaults applied and sanitized.
	 *
	 * @return array
	 */
	private static function settings() {
		return Secret_Drawer_Settings::get();
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
			'restRoot'       => esc_url_raw( rest_url( 'secret-drawer/v1' ) ),
			'nonce'          => wp_create_nonce( 'wp_rest' ),
			'session'        => wp_get_session_token(),
			'trigger'        => (string) apply_filters( 'secret_drawer_trigger_word', $settings['trigger_word'] ),
			'width'          => (int) $settings['width'],
			'position'       => 'bottom' === $settings['position'] ? 'bottom' : 'right',
			'siteUrl'        => home_url( '/' ),
			'discovered'     => (bool) get_user_meta( get_current_user_id(), 'secret_drawer_discovered', true ),
			'cubbies'        => self::cubbies_for_user( $settings ),
			'catalog'        => self::catalog_for_user( $settings ),
			'roleOptions'    => self::role_options(),
			'roles'          => (array) $settings['roles'],
			'manageSettings' => current_user_can( 'manage_options' ),
			'strings'        => array(
				'title'       => __( 'Secret Drawer', 'secret-drawer' ),
				'close'       => __( 'Close', 'secret-drawer' ),
				'settings'    => __( 'Drawer settings', 'secret-drawer' ),
				'back'        => __( 'Back', 'secret-drawer' ),
				'save'        => __( 'Save changes', 'secret-drawer' ),
				'saved'       => __( 'Saved ✓', 'secret-drawer' ),
				'saveError'   => __( 'Could not save settings.', 'secret-drawer' ),
				'roles'       => __( 'Who can find it', 'secret-drawer' ),
				'secretWord'  => __( 'Secret word', 'secret-drawer' ),
				'secretHelp'  => __( 'Typed on any admin screen (outside text fields) to open the drawer.', 'secret-drawer' ),
				'position'    => __( 'Drawer position', 'secret-drawer' ),
				'width'       => __( 'Width (px)', 'secret-drawer' ),
				'inDrawer'    => __( 'In your drawer', 'secret-drawer' ),
				'library'     => __( 'Cubby library', 'secret-drawer' ),
				'toast'       => __( '🔓 You found the Secret Drawer.', 'secret-drawer' ),
				'ghost'       => __( "It's a secret!", 'secret-drawer' ),
				'emptyCubby'  => __( 'Nothing here yet.', 'secret-drawer' ),
				'emptyNotes'  => __( 'No notes yet. Toss one in.', 'secret-drawer' ),
				'loadError'   => __( 'Could not load this cubby.', 'secret-drawer' ),
				'saving'      => __( 'Saving…', 'secret-drawer' ),
				'saveFailed'  => __( 'Save failed — will retry on next edit.', 'secret-drawer' ),
				'add'         => __( 'Add', 'secret-drawer' ),
				'update'      => __( 'Update', 'secret-drawer' ),
				'deleteNote'  => __( 'Delete note', 'secret-drawer' ),
				'removeLabel' => __( 'Remove', 'secret-drawer' ),
				'emptyDrawer' => __( 'This drawer is empty. Add something from the library.', 'secret-drawer' ),
				'copied'      => __( 'Copied ✓', 'secret-drawer' ),
				'leverDone'   => __( 'Done', 'secret-drawer' ),
				'leverEmpty'  => __( 'nothing to delete', 'secret-drawer' ),
				// translators: %d is the number of posts deleted.
				'nPosts'      => __( '%d posts', 'secret-drawer' ),
				// translators: %d is the number of comments deleted.
				'nComments'   => __( '%d comments', 'secret-drawer' ),
				'leverFail'   => __( 'Could not pull that lever.', 'secret-drawer' ),
				'roll'        => __( 'Roll', 'secret-drawer' ),
				'lastFive'    => __( 'Last five', 'secret-drawer' ),
				// translators: %d is the number of sides on the chosen die.
				'diceOf'      => __( 'of %d', 'secret-drawer' ),
			),
		);
	}

	/**
	 * Library catalog for the in-drawer settings picker: only types whose
	 * titles are safe for this user. Hidden types (no title) are dropped.
	 *
	 * @param array $settings Settings.
	 * @return array[]
	 */
	private static function catalog_for_user( $settings ) {
		$out = array();
		foreach ( Secret_Drawer_Cubby_Registry::all() as $id => $cubby ) {
			$out[ $id ] = array(
				'title'       => $cubby['title'],
				'icon'        => (string) $cubby['icon'],
				'description' => (string) $cubby['description'],
			);
		}
		return $out;
	}

	/**
	 * Role display names for the settings multi-select.
	 *
	 * @return array
	 */
	private static function role_options() {
		$out = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			$out[ $slug ] = $role['name'];
		}
		return $out;
	}

	/**
	 * Enabled cubby metadata for the launcher card grid (id/title/icon).
	 *
	 * @param array $settings Settings.
	 * @return array[]
	 */
	private static function cubbies_for_user( $settings ) {
		$out = array();
		foreach ( (array) $settings['enabled_cubbies'] as $id ) {
			$meta = Secret_Drawer_Cubby_Registry::get( $id );
			if ( $meta ) {
				$out[] = array(
					'id'    => $id,
					'title' => $meta['title'],
					'icon'  => (string) $meta['icon'],
				);
			}
		}
		return $out;
	}
}