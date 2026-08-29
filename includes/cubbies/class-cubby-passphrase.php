<?php
/**
 * Passphrase cubby: random words plus a number, ready to copy.
 *
 * The security stance is the whole point: generation happens entirely in
 * the browser (crypto.getRandomValues in wirePassphrase(); the wordlist
 * lives in drawer.js), nothing is ever POSTed to the server, and nothing
 * is persisted — no REST route, no usermeta, no localStorage. The card
 * states the promise right under the result so nobody has to trust the
 * black box.
 *
 * Entropy is honest but modest: 256 words = 8 bits each, a two-digit
 * suffix adds ~7. Four words plus a number ≈ 39 bits — fine for
 * throwaway logins, not for anything you actually care about. Cubby
 * authors wanting more should extend the JS wordlist, not this label.
 *
 * For cubby authors this is the minimal client-only shape: server-rendered
 * markup, a wireCubby() hook, zero server round trips.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Cubby_Passphrase
 */
class Secret_Drawer_Cubby_Passphrase {

	/**
	 * Word counts offered in the length picker.
	 *
	 * @var int[]
	 */
	const WORD_COUNTS = array( 3, 4, 5, 6 );

	/**
	 * Render the cubby body: length picker, result readout, buttons.
	 *
	 * The markup is inert on its own; wirePassphrase() in drawer.js
	 * generates, wires copy, and re-rolls on control changes — all
	 * client-side, no REST calls involved.
	 *
	 * @return string
	 */
	public static function get_html() {
		ob_start();
		?>
		<div class="sd-pass">
			<div class="sd-pass-row">
				<label class="sd-pass-field">
					<span class="sd-pass-field-label"><?php esc_html_e( 'Words', 'secret-drawer' ); ?></span>
					<select class="sd-pass-count">
						<?php foreach ( self::WORD_COUNTS as $count ) : ?>
							<option value="<?php echo esc_attr( (string) $count ); ?>" <?php selected( 4, $count ); ?>>
								<?php echo esc_html( (string) $count ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="sd-pass-check">
					<input type="checkbox" class="sd-pass-number" checked="checked" />
					<span><?php esc_html_e( 'Add number', 'secret-drawer' ); ?></span>
				</label>
			</div>

			<p class="sd-pass-result" role="status">
				<code class="sd-pass-readout" aria-label="<?php esc_attr_e( 'Generated passphrase', 'secret-drawer' ); ?>">—</code>
				<span class="sd-pass-bits"></span>
			</p>

			<div class="sd-pass-actions">
				<button type="button" class="button" data-sd-pass-regen><?php esc_html_e( 'Regenerate', 'secret-drawer' ); ?></button>
				<button type="button" class="button button-primary" data-sd-pass-copy><?php esc_html_e( 'Copy', 'secret-drawer' ); ?></button>
			</div>

			<p class="sd-pass-note sd-muted">
				<?php esc_html_e( 'Generated in your browser — never sent anywhere.', 'secret-drawer' ); ?>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}