<?php
/**
 * Notes cubby: a private, autosaving scratchpad (per-user usermeta).
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Cubby_Notes
 */
class Secret_Drawer_Cubby_Notes {

	const META_KEY = 'secret_drawer_cubby_notes';

	/**
	 * Server-rendered cubby body.
	 *
	 * @return string
	 */
	public static function get_html() {
		$content = (string) get_user_meta( get_current_user_id(), self::META_KEY, true );

		ob_start();
		?>
		<label class="sd-notes-label" for="sd-notes-field">
			<?php esc_html_e( 'Private scratchpad — autosaves as you type.', 'secret-drawer' ); ?>
		</label>
		<textarea
			id="sd-notes-field"
			class="sd-notes"
			data-sd-notes
			rows="12"
			placeholder="<?php esc_attr_e( 'Half-finished thoughts, TODOs, that snippet you keep re-googling…', 'secret-drawer' ); ?>"
		><?php echo esc_textarea( $content ); ?></textarea>
		<p class="sd-save-ind" role="status" aria-live="polite"></p>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Persist notes for the current user.
	 *
	 * @param string $content Raw content.
	 * @return bool
	 */
	public static function save( $content ) {
		return (bool) update_user_meta( get_current_user_id(), self::META_KEY, sanitize_textarea_field( wp_unslash( $content ) ) );
	}
}