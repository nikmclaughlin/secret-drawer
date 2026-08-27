<?php
/**
 * Built-in cubby REST routes under secret-drawer/v1.
 *
 * Every route's permission callback re-checks the access gate
 * independently of enqueue-time gating.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Rest_Cubbies
 */
class Secret_Drawer_Rest_Cubbies {

	/**
	 * Hook registration.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the per-cubby routes.
	 */
	public function register_routes() {
		// Read a cubby body (server-rendered HTML).
		register_rest_route(
			'secret-drawer/v1',
			'/cubbies/(?P<id>[a-z0-9_-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_cubby' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);

		// Notes: save.
		register_rest_route(
			'secret-drawer/v1',
			'/cubbies/notes/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_notes' ),
				'permission_callback' => array( $this, 'can_access' ),
				'args'                => array(
					'content' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// Links: add.
		register_rest_route(
			'secret-drawer/v1',
			'/cubbies/links/add',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'links_add' ),
				'permission_callback' => array( $this, 'can_access' ),
				'args'                => array(
					'label' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'url'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Links: remove by index.
		register_rest_route(
			'secret-drawer/v1',
			'/cubbies/links/remove',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'links_remove' ),
				'permission_callback' => array( $this, 'can_access' ),
				'args'                => array(
					'index' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Shared permission callback: the drawer gate.
	 *
	 * @return bool
	 */
	public function can_access() {
		return Secret_Drawer_Plugin::user_can_access();
	}

	/**
	 * GET /cubbies/{id} — server-rendered body for a known cubby.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_cubby( $request ) {
		$id    = (string) $request['id'];
		$known = Secret_Drawer_Settings::cubby_catalog();

		if ( ! isset( $known[ $id ] ) || empty( $known[ $id ]['title'] ) ) {
			return new WP_Error(
				'sd_unknown_cubby',
				__( 'Unknown cubby.', 'secret-drawer' ),
				array( 'status' => 404 )
			);
		}

		switch ( $id ) {
			case 'notes':
				$html = Secret_Drawer_Cubby_Notes::get_html();
				break;
			case 'links':
				$html = Secret_Drawer_Cubby_Links::get_html();
				break;
			case 'notifications':
				$html = Secret_Drawer_Cubby_Notifications::get_html();
				break;
			default:
				$html = '';
				break;
		}

		if ( '' === $html ) {
			return new WP_Error(
				'sd_unknown_cubby',
				__( 'Unknown cubby.', 'secret-drawer' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( array( 'html' => $html ) );
	}

	/**
	 * POST /cubbies/notes/save
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_notes( $request ) {
		Secret_Drawer_Cubby_Notes::save( (string) $request->get_param( 'content' ) );
		return rest_ensure_response( array( 'saved' => true ) );
	}

	/**
	 * POST /cubbies/links/add
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function links_add( $request ) {
		$links = Secret_Drawer_Cubby_Links::add(
			(string) $request->get_param( 'label' ),
			(string) $request->get_param( 'url' )
		);
		return rest_ensure_response( array( 'links' => $links ) );
	}

	/**
	 * POST /cubbies/links/remove
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function links_remove( $request ) {
		$links = Secret_Drawer_Cubby_Links::remove( (int) $request->get_param( 'index' ) );
		return rest_ensure_response( array( 'links' => $links ) );
	}
}