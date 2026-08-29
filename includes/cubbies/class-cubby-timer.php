<?php
/**
 * Focus timer cubby — the pack's fiddly one.
 *
 * Server-side markup only. Every timer behavior lives in
 * `wireTimer()` (client-side): countdown, pause/reset, the finish
 * pulse, and the survive-a-panel-close guarantee. The state machine
 * lives in JS (drawer scope), never in this DOM — this panel can be
 * popped and re-opened mid-countdown at any moment, so the markup is
 * a dumb viewport the wiring re-paints.
 *
 * No REST, no PHP state, no usermeta: timer state is the user's
 * business, and it dies with the drawer (drawer close resets it).
 *
 * @package SecretDrawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Focus timer: a 20-minute countdown with presets and a finish pulse.
 */
final class Secret_Drawer_Cubby_Timer {

	/**
	 * Render the panel body.
	 *
	 * @return string HTML.
	 */
	public static function get_html() {
		ob_start();
		?>
		<div class="sd-timer" data-sd-timer>
			<div class="sd-timer-ring"><span class="sd-timer-time" role="timer">20:00</span></div>

			<fieldset class="sd-timer-picker">
				<legend class="screen-reader-text"><?php esc_html_e( 'Minutes', 'secret-drawer' ); ?></legend>
				<?php foreach ( array( 1, 5, 10, 20 ) as $mins ) : ?>
					<label class="sd-timer-chip">
						<input type="radio" name="sd-timer-mins" value="<?php echo esc_attr( $mins ); ?>" <?php checked( 20, $mins ); ?> />
						<span aria-hidden="true"><?php echo esc_html( $mins ); ?>′</span>
					</label>
				<?php endforeach; ?>
			</fieldset>

			<div class="sd-timer-actions">
				<button type="button" class="button button-primary" data-sd-timer-start><?php esc_html_e( 'Start', 'secret-drawer' ); ?></button>
				<button type="button" class="button" data-sd-timer-pause hidden><?php esc_html_e( 'Pause', 'secret-drawer' ); ?></button>
				<button type="button" class="button" data-sd-timer-reset hidden><?php esc_html_e( 'Reset', 'secret-drawer' ); ?></button>
			</div>

			<p class="sd-timer-status" role="status"></p>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}