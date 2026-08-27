<?php
/**
 * Quick Links cubby: a per-user curated jump list of admin screens.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Cubby_Links
 */
class Secret_Drawer_Cubby_Links {

	const META_KEY = 'secret_drawer_cubby_links';

	/**
	 * Server-rendered cubby body: the list plus the add-row markup.
	 *
	 * @return string
	 */
	public static function get_html() {
		$links = self::get_links();

		ob_start();
		?>
		<div class="sd-links" data-sd-links>
			<ul class="sd-links-list">
				<?php if ( empty( $links ) ) : ?>
					<li class="sd-muted sd-links-empty"><?php esc_html_e( 'No links yet — add your favorite admin screens below.', 'secret-drawer' ); ?></li>
				<?php else : ?>
					<?php foreach ( $links as $i => $link ) : ?>
						<li class="sd-row sd-link-row" data-index="<?php echo esc_attr( $i ); ?>">
							<a href="<?php echo esc_url( $link['url'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
							<button type="button" class="sd-icon-button" data-sd-link-delete="<?php echo esc_attr( $i ); ?>" aria-label="<?php esc_attr_e( 'Remove link', 'secret-drawer' ); ?>">✕</button>
						</li>
					<?php endforeach; ?>
				<?php endif; ?>
			</ul>
			<div class="sd-links-add">
				<input type="text" class="sd-link-label" placeholder="<?php esc_attr_e( 'Label', 'secret-drawer' ); ?>" aria-label="<?php esc_attr_e( 'Link label', 'secret-drawer' ); ?>">
				<input type="text" class="sd-link-url" placeholder="<?php esc_attr_e( '/wp-admin/… or full URL', 'secret-drawer' ); ?>" aria-label="<?php esc_attr_e( 'Link URL', 'secret-drawer' ); ?>">
				<button type="button" class="button button-small" data-sd-link-add><?php esc_html_e( 'Add', 'secret-drawer' ); ?></button>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The user's links, always re-normalized from storage.
	 *
	 * @return array[]
	 */
	public static function get_links() {
		$raw   = get_user_meta( get_current_user_id(), self::META_KEY, true );
		$links = array();
		foreach ( (array) $raw as $link ) {
			$label = isset( $link['label'] ) ? sanitize_text_field( $link['label'] ) : '';
			$url   = isset( $link['url'] ) ? esc_url_raw( $link['url'] ) : '';
			if ( $label && $url ) {
				$links[] = array( 'label' => $label, 'url' => $url );
			}
		}
		return $links;
	}

	/**
	 * Add a link. Returns the updated list.
	 *
	 * @param string $label Label.
	 * @param string $url   URL.
	 * @return array[]
	 */
	public static function add( $label, $url ) {
		$links = self::get_links();
		$label = sanitize_text_field( $label );
		$url   = self::normalize_url( $url );
		if ( $label && $url && count( $links ) < 50 ) {
			$links[] = array( 'label' => $label, 'url' => $url );
			self::persist( $links );
		}
		return $links;
	}

	/**
	 * Remove a link by index. Returns the updated list.
	 *
	 * @param int $index Index.
	 * @return array[]
	 */
	public static function remove( $index ) {
		$links = self::get_links();
		$index = (int) $index;
		if ( isset( $links[ $index ] ) ) {
			array_splice( $links, $index, 1 );
			self::persist( $links );
		}
		return $links;
	}

	/**
	 * Persist and return normalized links.
	 *
	 * @param array[] $links Links.
	 */
	private static function persist( $links ) {
		update_user_meta( get_current_user_id(), self::META_KEY, array_values( $links ) );
	}

	/**
	 * Admin-relative paths become admin URLs; everything else must be http(s).
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	private static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( '/' === $url[0] || 0 === stripos( $url, 'admin.php' ) || 0 === stripos( $url, 'edit.php' ) || 0 === stripos( $url, 'options-' ) || 0 === stripos( $url, 'tools.php' ) ) {
			return admin_url( ltrim( $url, '/' ) );
		}
		if ( preg_match( '#^https?://#i', $url ) ) {
			return esc_url_raw( $url );
		}
		return '';
	}
}