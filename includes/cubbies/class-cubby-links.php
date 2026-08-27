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
							<span class="sd-row-actions">
								<button type="button" class="sd-icon-button" data-sd-link-edit="<?php echo esc_attr( $i ); ?>" aria-label="<?php esc_attr_e( 'Edit link', 'secret-drawer' ); ?>">✎</button>
								<button type="button" class="sd-icon-button" data-sd-link-delete="<?php echo esc_attr( $i ); ?>" aria-label="<?php esc_attr_e( 'Remove link', 'secret-drawer' ); ?>">✕</button>
							</span>
						</li>
					<?php endforeach; ?>
				<?php endif; ?>
			</ul>
			<p class="sd-links-new">
				<button type="button" class="button button-small" data-sd-link-new><?php esc_html_e( '＋ New link', 'secret-drawer' ); ?></button>
			</p>
			<div class="sd-links-add" hidden>
				<input type="text" class="sd-link-label" placeholder="<?php esc_attr_e( 'Label', 'secret-drawer' ); ?>" aria-label="<?php esc_attr_e( 'Link label', 'secret-drawer' ); ?>">
				<input type="text" class="sd-link-url" placeholder="<?php esc_attr_e( '/wp-admin/… or full URL', 'secret-drawer' ); ?>" aria-label="<?php esc_attr_e( 'Link URL', 'secret-drawer' ); ?>">
				<button type="button" class="button button-small" data-sd-link-add><?php esc_html_e( 'Add', 'secret-drawer' ); ?></button>
				<button type="button" class="button-link" data-sd-link-cancel hidden><?php esc_html_e( 'Cancel', 'secret-drawer' ); ?></button>
			</div>
			<p class="sd-link-error" role="alert" hidden></p>
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
	 * Add a link. Returns array( 'links' => list, 'error' => message|null ).
	 *
	 * @param string $label Label.
	 * @param string $url   URL.
	 * @return array
	 */
	public static function add( $label, $url ) {
		$links = self::get_links();
		$label = sanitize_text_field( $label );
		$url   = self::normalize_url( $url );

		if ( '' === $label ) {
			return array( 'links' => $links, 'error' => __( 'A label is required.', 'secret-drawer' ) );
		}
		if ( '' === $url ) {
			return array( 'links' => $links, 'error' => __( 'That URL looks invalid — use an admin path like /edit.php or a full https:// URL.', 'secret-drawer' ) );
		}
		if ( count( $links ) >= 50 ) {
			return array( 'links' => $links, 'error' => __( 'Fifty links is plenty for one drawer.', 'secret-drawer' ) );
		}

		$links[] = array( 'label' => $label, 'url' => $url );
		self::persist( $links );
		return array( 'links' => $links, 'error' => null );
	}

	/**
	 * Update a link in place. Returns array( 'links' => list, 'error' => message|null ).
	 *
	 * @param int    $index Index.
	 * @param string $label Label.
	 * @param string $url   URL.
	 * @return array
	 */
	public static function update( $index, $label, $url ) {
		$links = self::get_links();
		$index = (int) $index;
		$label = sanitize_text_field( $label );
		$url   = self::normalize_url( $url );

		if ( ! isset( $links[ $index ] ) ) {
			return array( 'links' => $links, 'error' => __( 'That link no longer exists.', 'secret-drawer' ) );
		}
		if ( '' === $label ) {
			return array( 'links' => $links, 'error' => __( 'A label is required.', 'secret-drawer' ) );
		}
		if ( '' === $url ) {
			return array( 'links' => $links, 'error' => __( 'That URL looks invalid — use an admin path like /edit.php or a full https:// URL.', 'secret-drawer' ) );
		}

		$links[ $index ] = array( 'label' => $label, 'url' => $url );
		self::persist( $links );
		return array( 'links' => $links, 'error' => null );
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
	 * Admin-relative paths become admin URLs; anything pasted with a
	 * wp-admin/ prefix is unwound to the path first, so the admin base
	 * never doubles. Everything else must be a full http(s) URL.
	 *
	 * @param string $url Raw URL.
	 * @return string Normalized URL, or '' if invalid.
	 */
	private static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		// Path, optionally with a pasted admin base or full URL around it.
		if ( '/' === $url[0] || preg_match( '#wp-admin/#i', $url ) ) {
			$path = $url;
			if ( preg_match( '#wp-admin/(.+)$#i', $path, $m ) ) {
				$path = $m[1];
			}
			return admin_url( ltrim( $path, '/' ) );
		}
		if ( preg_match( '#^https?://#i', $url ) ) {
			return esc_url_raw( $url );
		}
		return '';
	}
}