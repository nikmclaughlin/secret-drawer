<?php
/**
 * Settings storage, sanitization, and the cubby type catalog.
 *
 * No admin-menu page: settings persist via the REST /settings route and
 * are edited only inside the drawer (gear icon).
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Settings
 */
class Secret_Drawer_Settings {

	/**
	 * Settings with defaults applied and sanitized.
	 *
	 * @return array
	 */
	public static function get() {
		$raw = (array) get_option( Secret_Drawer_Plugin::OPTION_SETTINGS, array() );
		return self::sanitize( $raw );
	}

	/**
	 * Sanitize a settings array. Never trust the shape — everything that
	 * ends up in the option row passes through here (REST writes included).
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = Secret_Drawer_Plugin::default_settings();
		$input    = is_array( $input ) ? $input : array();

		// Roles: valid role slugs only; never allow an empty list (lockout guard).
		$known_roles = array_keys( wp_roles()->roles );
		$roles       = array_values( array_intersect(
			(array) ( $input['roles'] ?? $defaults['roles'] ),
			$known_roles
		) );
		if ( empty( $roles ) ) {
			$roles = array( 'administrator' );
		}

		// Trigger word: plain text, lowercased, short.
		$trigger = sanitize_text_field( (string) ( $input['trigger_word'] ?? $defaults['trigger_word'] ) );
		$trigger = strtolower( mb_substr( $trigger, 0, 32 ) );
		if ( '' === $trigger ) {
			$trigger = $defaults['trigger_word'];
		}

		// Enabled cubbies: known ids only, unique, sane cap.
		$catalog  = self::cubby_catalog();
		$enabled  = array_values( array_unique( array_intersect(
			(array) ( $input['enabled_cubbies'] ?? $defaults['enabled_cubbies'] ),
			array_keys( $catalog )
		) ) );
		$enabled  = array_slice( $enabled, 0, 20 );

		$width    = (int) ( $input['width'] ?? $defaults['width'] );
		$position = ( 'bottom' === ( $input['position'] ?? '' ) ) ? 'bottom' : 'right';

		return array(
			'version'         => SECRET_DRAWER_VERSION,
			'roles'           => $roles,
			'trigger_word'    => $trigger,
			'enabled_cubbies' => $enabled,
			'width'           => min( 480, max( 280, $width ) ),
			'position'        => $position,
		);
	}

	/**
	 * Persist already-sanitized settings.
	 *
	 * @param array $settings Sanitized settings.
	 * @return bool
	 */
	public static function save( array $settings ) {
		return update_option( Secret_Drawer_Plugin::OPTION_SETTINGS, $settings, false );
	}

	/**
	 * The cubby type catalog: id → {title, icon, description}.
	 *
	 * M4: class-cubby-registry.php takes this over and applies the
	 * secret_drawer_cubbies filter; the shape below is the contract.
	 *
	 * @return array[]
	 */
	public static function cubby_catalog() {
		$catalog = array(
			'notes'         => array(
				'title'       => __( 'Notes', 'secret-drawer' ),
				'icon'        => 'dashicons-edit-page',
				'description' => __( 'A private scratchpad, autosaved to your profile.', 'secret-drawer' ),
			),
			'links'         => array(
				'title'       => __( 'Quick Links', 'secret-drawer' ),
				'icon'        => 'dashicons-admin-links',
				'description' => __( 'Your own curated jump list of admin screens.', 'secret-drawer' ),
			),
			'notifications' => array(
				'title'       => __( 'Notifications', 'secret-drawer' ),
				'icon'        => 'dashicons-bell',
				'description' => __( 'Update and moderation counts, with deep links.', 'secret-drawer' ),
			),
		);

		/**
		 * Register cubby types. See PLAN.md §7 for the schema.
		 *
		 * @param array[] $catalog Cubby type catalog.
		 */
		return (array) apply_filters( 'secret_drawer_cubbies', $catalog );
	}
}