<?php
/**
 * Socrates cubby: the bust that started it all.
 *
 * A quiet nod to the original material behind the joke: the unexamined
 * admin life is not worth living. Displays the portrait that every
 * intro-to-philosophy course (and this plugin) leans on.
 *
 * Image: "Portrait of Socrates" — marble, Roman artwork (1st century),
 * perhaps a copy of a lost bronze by Lysippos. Louvre. Photo by Eric
 * Gaba (2005), Wikimedia Commons, CC BY-SA.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Cubby_Socrates
 */
class Secret_Drawer_Cubby_Socrates {

	/**
	 * Render the cubby body: the portrait plus a one-line caption.
	 *
	 * @return string
	 */
	public static function get_html() {
		$url = plugins_url( 'assets/socrates.jpg', SECRET_DRAWER_FILE );

		ob_start();
		?>
		<figure class="sd-socrates">
			<img
				src="<?php echo esc_url( $url ); ?>"
				alt="<?php esc_attr_e( 'Portrait of Socrates — marble, Roman artwork (1st century), Louvre.', 'secret-drawer' ); ?>"
				width="540"
				height="720"
				loading="lazy"
			/>
			<figcaption>
				<?php esc_html_e( 'The unexamined admin is not worth running.', 'secret-drawer' ); ?>
			</figcaption>
		</figure>
		<?php
		return (string) ob_get_clean();
	}
}