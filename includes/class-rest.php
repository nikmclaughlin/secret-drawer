<?php
/**
 * REST routes. M2 ships /settings; cubby routes join at M3, the registry
 * route surface at M4.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Rest
 */
class Secret_Drawer_Rest {

	const NAMESPACE_V1 = 'secret-drawer/v1';

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => static function () {
						return current_user_can( 'manage_options' );
					},
					'args'                => array(
						'settings' => array(
							'type'     => 'object',
							'required' => true,
						),
					),
				),
			)
		);
	}

	/**
	 * GET /settings
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( array( 'settings' => Secret_Drawer_Settings::get() ) );
	}

	/**
	 * POST /settings — sanitize, save, return the sanitized copy.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_settings( $request ) {
		$sanitized = Secret_Drawer_Settings::sanitize( (array) $request->get_param( 'settings' ) );
		Secret_Drawer_Settings::save( $sanitized );
		return rest_ensure_response( array( 'settings' => $sanitized ) );
	}
}