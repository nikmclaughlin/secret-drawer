<?php
/**
 * Levers cubby: one-click actions against the site — proof that cubbies
 * can act, not just display.
 *
 * Every lever is a real REST endpoint with real capability checks.
 * Destructive levers confirm in the UI before they fire.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Cubby_Levers
 */
class Secret_Drawer_Cubby_Levers {

	/**
	 * The lever catalog: id → {label, description, confirm, cap, action}.
	 *
	 * `action` is a callable run inside the REST handler; `cap` is checked
	 * again at pull time. Filterable via `secret_drawer_levers` (removing
	 * or adding levers is fine; the endpoint validates against the same
	 * filtered catalog, so injected levers must ship their own endpoints
	 * — see SECRET-DRAWER-EXTENDING.md).
	 *
	 * @return array[]
	 */
	public static function levers() {
		$levers = array(
			'copy_site_url' => array(
				'label'       => __( 'Copy site URL', 'secret-drawer' ),
				'description' => __( 'Copies the site URL to your clipboard.', 'secret-drawer' ),
				'confirm'     => '',
				'cap'         => 'manage_options',
				'action'      => null, // Client-side: clipboard, no endpoint.
			),
			'empty_trash'   => array(
				'label'       => __( 'Empty trash', 'secret-drawer' ),
				'description' => __( 'Permanently deletes posts and comments in the trash.', 'secret-drawer' ),
				'confirm'     => __( 'Permanently delete everything in the trash?', 'secret-drawer' ),
				'cap'         => 'edit_others_posts',
				'action'      => array( __CLASS__, 'do_empty_trash' ),
			),
		);

		/**
		 * Register extra levers or remove built-ins.
		 *
		 * @param array[] $levers Lever catalog.
		 */
		return (array) apply_filters( 'secret_drawer_levers', $levers );
	}

	/**
	 * Server-rendered cubby body: one button per visible lever.
	 *
	 * @return string
	 */
	public static function get_html() {
		$out = '<div class="sd-levers">';

		foreach ( self::levers() as $id => $lever ) {
			if ( empty( $lever['label'] ) || '' !== ( $lever['confirm'] ?? '' ) && ! is_string( $lever['confirm'] ) ) {
				continue;
			}
			$needs_cap = (string) ( $lever['cap'] ?? '' );
			if ( '' !== $needs_cap && ! current_user_can( $needs_cap ) ) {
				continue;
			}

			$out .= '<button type="button" class="button sd-lever"'
				. ' data-sd-lever="' . esc_attr( $id ) . '"'
				. ( '' !== ( $lever['confirm'] ?? '' ) ? ' data-confirm="' . esc_attr( $lever['confirm'] ) . '"' : '' )
				. ' data-lever-label="' . esc_attr( $lever['label'] ) . '">'
				. esc_html( $lever['label'] )
				. '</button>';

			if ( ! empty( $lever['description'] ) ) {
				$out .= '<p class="sd-lever-desc sd-muted">' . esc_html( $lever['description'] ) . '</p>';
			}
		}

		$out .= '</div>';
		return $out;
	}

	/**
	 * Empty the trash: posts/pages of any public type + comments.
	 *
	 * @return array{deleted_posts:int, deleted_comments:int}
	 */
	public static function do_empty_trash() {
		$deleted_posts    = 0;
		$deleted_comments = 0;

		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
			$trashed = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'trash',
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			foreach ( $trashed as $post_id ) {
				if ( wp_delete_post( $post_id, true ) ) {
					$deleted_posts++;
				}
			}
		}

		$trashed_comments = get_comments(
			array(
				'status'  => 'trash',
				'number'  => 100,
				'fields'  => 'ids',
			)
		);
		foreach ( $trashed_comments as $comment_id ) {
			if ( wp_delete_comment( $comment_id, true ) ) {
				$deleted_comments++;
			}
		}

		return array(
			'deleted_posts'    => $deleted_posts,
			'deleted_comments' => $deleted_comments,
		);
	}
}