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

		// Pull a lever: run a registered lever's action server-side.
		register_rest_route(
			'secret-drawer/v1',
			'/cubbies/levers/pull',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'pull_lever' ),
				'permission_callback' => array( $this, 'can_access' ),
				'args'                => array(
					'id' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		// Notes: save one note by id.
		register_rest_route(
			'secret-drawer/v1',
			'/cubbies/notes/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_notes' ),
				'permission_callback' => array( $this, 'can_access' ),
				'args'                => array(
					'id' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'content' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// Notes: create.
		register_rest_route(
			'secret-drawer/v1',
			'/cubbies/notes/create',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_note' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);

		// Notes: delete by id.
		register_rest_route(
			'secret-drawer/v1',
			'/cubbies/notes/delete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'delete_note' ),
				'permission_callback' => array( $this, 'can_access' ),
				'args'                => array(
					'id' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
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

		// Links: update in place.
		register_rest_route(
			'secret-drawer/v1',
			'/cubbies/links/update',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'links_update' ),
				'permission_callback' => array( $this, 'can_access' ),
				'args'                => array(
					'index' => array( 'type' => 'integer', 'required' => true ),
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
		$id   = (string) $request['id'];
		$html = Secret_Drawer_Cubby_Registry::render( $id );

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
	 * POST /cubbies/levers/pull — run a lever's server-side action.
	 *
	 * Validates the lever id against the filtered catalog, re-checks the
	 * lever's capability, then runs the action. Client-side levers
	 * (null action, e.g. copy-site-URL) never reach this route.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function pull_lever( $request ) {
		$id    = (string) $request->get_param( 'id' );
		$lever = Secret_Drawer_Cubby_Levers::levers()[ $id ] ?? null;

		if ( ! $lever || ! is_array( $lever ) ) {
			return new WP_Error(
				'sd_unknown_lever',
				__( 'Unknown lever.', 'secret-drawer' ),
				array( 'status' => 404 )
			);
		}

		$cap = (string) ( $lever['cap'] ?? '' );
		if ( '' !== $cap && ! current_user_can( $cap ) ) {
			return new WP_Error(
				'sd_forbidden_lever',
				__( 'You are not allowed to pull this lever.', 'secret-drawer' ),
				array( 'status' => 403 )
			);
		}

		if ( empty( $lever['action'] ) || ! is_callable( $lever['action'] ) ) {
			return new WP_Error(
				'sd_client_lever',
				__( 'This lever has no server-side action.', 'secret-drawer' ),
				array( 'status' => 400 )
			);
		}

		$result = call_user_func( $lever['action'] );

		return rest_ensure_response(
			array(
				'ok'     => true,
				'lever'  => $id,
				'result' => $result,
			)
		);
	}

	/**
	 * POST /cubbies/notes/save
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function save_notes( $request ) {
		$ok = Secret_Drawer_Cubby_Notes::save(
			(string) $request->get_param( 'id' ),
			(string) $request->get_param( 'content' )
		);
		return rest_ensure_response( array( 'saved' => (bool) $ok ) );
	}

	/**
	 * POST /cubbies/notes/create
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_note( $request ) {
		$id = Secret_Drawer_Cubby_Notes::create( '' );
		return rest_ensure_response( array( 'id' => $id ) );
	}

	/**
	 * POST /cubbies/notes/delete
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_note( $request ) {
		$ok = Secret_Drawer_Cubby_Notes::delete( (string) $request->get_param( 'id' ) );
		return rest_ensure_response( array( 'deleted' => (bool) $ok ) );
	}

	/**
	 * Shared error response: 400 with a human-readable message the UI can show.
	 *
	 * @param string $message Message.
	 * @param array  $links   Current links (so the client stays in sync).
	 * @return WP_REST_Response
	 */
	private function links_error( $message, $links ) {
		$response = rest_ensure_response(
			array(
				'message' => $message,
				'links'   => $links,
			)
		);
		$response->set_status( 400 );
		return $response;
	}

	/**
	 * POST /cubbies/links/add
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function links_add( $request ) {
		$result = Secret_Drawer_Cubby_Links::add(
			(string) $request->get_param( 'label' ),
			(string) $request->get_param( 'url' )
		);
		if ( ! empty( $result['error'] ) ) {
			return $this->links_error( $result['error'], $result['links'] );
		}
		return rest_ensure_response( array( 'links' => $result['links'] ) );
	}

	/**
	 * POST /cubbies/links/update
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function links_update( $request ) {
		$result = Secret_Drawer_Cubby_Links::update(
			(int) $request->get_param( 'index' ),
			(string) $request->get_param( 'label' ),
			(string) $request->get_param( 'url' )
		);
		if ( ! empty( $result['error'] ) ) {
			return $this->links_error( $result['error'], $result['links'] );
		}
		return rest_ensure_response( array( 'links' => $result['links'] ) );
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