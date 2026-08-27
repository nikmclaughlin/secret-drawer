<?php
/**
 * Notifications cubby: truthful counts of what needs attention,
 * deep-linked to the real admin screens.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Cubby_Notifications
 */
class Secret_Drawer_Cubby_Notifications {

	const CACHE_KEY = 'secret_drawer_notifications';

	/**
	 * Server-rendered cubby body.
	 *
	 * @return string
	 */
	public static function get_html() {
		$items = self::get_counts();

		ob_start();
		?>
		<ul class="sd-notif-list">
			<?php if ( empty( $items ) ) : ?>
				<li class="sd-muted"><?php esc_html_e( 'Everything handled. Nothing needs you. 🧘', 'secret-drawer' ); ?></li>
			<?php else : ?>
				<?php foreach ( $items as $item ) : ?>
					<li class="sd-row sd-notif-row">
						<span class="sd-notif-badge"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></span>
						<a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
					</li>
				<?php endforeach; ?>
				<li class="sd-muted sd-notif-stale"><?php esc_html_e( 'Counts refresh hourly.', 'secret-drawer' ); ?></li>
			<?php endif; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Counts, cached one hour (transient), filterable.
	 *
	 * @return array[]
	 */
	public static function get_counts() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return (array) $cached;
		}

		$items = array();

		// Updates available (plugins, themes, core, translations).
		$update_data = wp_get_update_data();
		$updates     = isset( $update_data['counts']['total'] ) ? (int) $update_data['counts']['total'] : 0;
		if ( $updates > 0 ) {
			$items[] = array(
				'label' => __( 'Updates available', 'secret-drawer' ),
				'url'   => admin_url( 'update-core.php' ),
				'count' => $updates,
			);
		}

		// Comments awaiting moderation.
		$pending = (int) wp_count_comments()->moderated;
		if ( $pending > 0 ) {
			$items[] = array(
				'label' => __( 'Comments awaiting moderation', 'secret-drawer' ),
				'url'   => admin_url( 'edit-comments.php?comment_status=moderated' ),
				'count' => $pending,
			);
		}

		/**
		 * Add or replace notification items.
		 *
		 * @param array[] $items Notification items.
		 */
		$items = (array) apply_filters( 'secret_drawer_notifications', $items );

		set_transient( self::CACHE_KEY, $items, HOUR_IN_SECONDS );
		return $items;
	}

	/**
	 * Cache invalidation on relevant core events.
	 */
	public static function flush_cache() {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Hook registration.
	 */
	public static function hooks() {
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush_cache' ) );
		add_action( 'wp_insert_comment', array( __CLASS__, 'flush_cache' ) );
		add_action( 'trashed_post_comments', array( __CLASS__, 'flush_cache' ) );
	}
}