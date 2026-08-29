<?php
/**
 * Dice cubby: d2 to d20 with a tumble and a short memory.
 *
 * Purely client-side — Math.random behind a button, no REST endpoint,
 * no user data. The tumble is CSS (skipped under prefers-reduced-motion)
 * and the "last five" line keeps it feeling alive. For cubby authors this
 * is the minimal display-plus-JS shape: server-rendered markup, a
 * wireCubby() hook, zero server round trips.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Cubby_Dice
 */
class Secret_Drawer_Cubby_Dice {

	/**
	 * Die sizes offered, matching the plan's d2 / d6 / d12 / d20 picks.
	 *
	 * @var int[]
	 */
	const SIDES = array( 2, 6, 12, 20 );

	/**
	 * Render the cubby body: die picker, roll button, result, history.
	 *
	 * The markup is inert on its own; wireDice() in drawer.js brings it
	 * to life with delegated listeners (no REST calls involved).
	 *
	 * @return string
	 */
	public static function get_html() {
		ob_start();
		?>
		<div class="sd-dice">
			<fieldset class="sd-dice-picker">
				<legend class="sd-dice-legend"><?php esc_html_e( 'Dice', 'secret-drawer' ); ?></legend>
				<?php foreach ( self::SIDES as $sides ) : ?>
					<label class="sd-dice-chip">
						<input
							type="radio"
							class="sd-dice-sides"
							name="sd-dice-sides"
							value="<?php echo esc_attr( (string) $sides ); ?>"
							<?php checked( 20, $sides ); ?>
						/>
						<span class="sd-dice-chip-face">d<?php echo esc_html( (string) $sides ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<div class="sd-dice-actions">
				<button type="button" class="button" data-sd-dice-roll><?php esc_html_e( 'Roll', 'secret-drawer' ); ?></button>
			</div>

			<p class="sd-dice-result" role="status">
				<span class="sd-dice-face" aria-hidden="true">🎲</span>
				<span class="sd-dice-value">—</span>
				<span class="sd-dice-max"><?php echo esc_html( self::of_label( 20 ) ); ?></span>
			</p>

			<p class="sd-dice-history sd-muted">
				<span><?php esc_html_e( 'Last five', 'secret-drawer' ); ?></span>
				<span class="sd-dice-roll-list" aria-hidden="true">—</span>
			</p>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Translated "of N" label for the result line. The JS rewrites this
	 * on every roll using the same string from the localized config, so
	 * both sides stay in sync.
	 *
	 * @param int $sides Die size.
	 * @return string
	 */
	private static function of_label( $sides ) {
		// translators: %d is the number of sides on the chosen die.
		return sprintf( __( 'of %d', 'secret-drawer' ), $sides );
	}
}