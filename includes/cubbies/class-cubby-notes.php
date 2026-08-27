<?php
/**
 * Notes cubby: a per-user list of private scratch notes.
 *
 * Storage: usermeta `secret_drawer_cubby_notes` as a JSON-ish array of
 * [ 'id' => string, 'content' => string ]. The M3 single-string format is
 * migrated on read.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Cubby_Notes
 */
class Secret_Drawer_Cubby_Notes {

	/**
	 * Render the cubby body: new-note button + list of note cards.
	 *
	 * @return string
	 */
	public static function get_html() {
		$notes = self::get_notes();

		ob_start();
		?>
		<div class="sd-notes-wrap">
			<p class="sd-notes-new">
				<button type="button" class="button button-small" data-sd-note-new data-label-new="<?php esc_attr_e( '＋ New note', 'secret-drawer' ); ?>" data-label-all="<?php esc_attr_e( '← All notes', 'secret-drawer' ); ?>"><?php esc_html_e( '＋ New note', 'secret-drawer' ); ?></button>
			</p>
			<?php if ( empty( $notes ) ) : ?>
				<p class="sd-muted"><?php esc_html_e( 'No notes yet. Toss one in.', 'secret-drawer' ); ?></p>
			<?php else : ?>
				<ul class="sd-notes-list">
					<?php foreach ( $notes as $note ) : ?>
						<li class="sd-row sd-note-row" data-note-id="<?php echo esc_attr( $note['id'] ); ?>">
							<button type="button" class="sd-note-open" data-sd-note-open="<?php echo esc_attr( $note['id'] ); ?>" data-content="<?php echo esc_attr( $note['content'] ); ?>"><?php echo esc_html( self::preview( $note['content'] ) ); ?></button>
							<span class="sd-row-actions">
								<button type="button" class="sd-icon-button" data-sd-note-delete="<?php echo esc_attr( $note['id'] ); ?>" aria-label="<?php esc_attr_e( 'Delete note', 'secret-drawer' ); ?>">✕</button>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<div class="sd-note-editor" hidden></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Get the user's notes, migrating the M3 single-string format on read.
	 *
	 * @return array[]
	 */
	public static function get_notes() {
		$raw = get_user_meta( get_current_user_id(), 'secret_drawer_cubby_notes', true );
		if ( is_array( $raw ) ) {
			return array_values( $raw );
		}
		if ( is_string( $raw ) && '' !== $raw ) {
			return array( self::make_note( $raw ) );
		}
		return array();
	}

	/**
	 * Create a note. Returns the new note's id.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function create( $content ) {
		$notes   = self::get_notes();
		$note    = self::make_note( (string) $content );
		$notes[] = $note;
		update_user_meta( get_current_user_id(), 'secret_drawer_cubby_notes', $notes );
		return $note['id'];
	}

	/**
	 * Autosave a note by id.
	 *
	 * @param string $id      Note id.
	 * @param string $content Content.
	 * @return bool Whether the note existed.
	 */
	public static function save( $id, $content ) {
		$notes = self::get_notes();
		$id    = (string) $id;
		foreach ( $notes as $i => $note ) {
			if ( $note['id'] === $id ) {
				$notes[ $i ]['content'] = sanitize_textarea_field( (string) $content );
				update_user_meta( get_current_user_id(), 'secret_drawer_cubby_notes', $notes );
				return true;
			}
		}
		return false;
	}

	/**
	 * Delete a note by id.
	 *
	 * @param string $id Note id.
	 * @return bool Whether something was deleted.
	 */
	public static function delete( $id ) {
		$notes   = self::get_notes();
		$kept    = array_values(
			array_filter(
				$notes,
				function ( $note ) use ( $id ) {
					return $note['id'] !== (string) $id;
				}
			)
		);
		if ( count( $kept ) === count( $notes ) ) {
			return false;
		}
		update_user_meta( get_current_user_id(), 'secret_drawer_cubby_notes', $kept );
		return true;
	}

	/**
	 * One-line preview for the list view.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	private static function preview( $content ) {
		$flat = trim( preg_replace( '/\s+/u', ' ', (string) $content ) );
		if ( '' === $flat ) {
			return __( '(empty note)', 'secret-drawer' );
		}
		return wp_html_excerpt( $flat, 60, '…' );
	}

	/**
	 * Build a note record.
	 *
	 * @param string $content Content.
	 * @return array
	 */
	private static function make_note( $content ) {
		return array(
			'id'      => uniqid( 'note_' ),
			'content' => sanitize_textarea_field( (string) $content ),
		);
	}
}