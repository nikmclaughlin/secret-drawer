<?php
/**
 * Site vitals cubby: the environment at a glance, health included.
 *
 * Site Health's CliffNotes: versions, limits, flags, and the three quiet
 * failure modes that make sites slow or surprising (autoload bloat,
 * plugin sprawl, missed cron schedules). Read-only by design — this card
 * mirrors what wp-admin already shows the same user elsewhere (footer,
 * Site Health, Tools screens). Cached hourly like notifications because
 * none of it changes mid-session — except the clock, which is stamped at
 * render so it never goes stale in the drawer.
 *
 * @package Secret_Drawer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Secret_Drawer_Cubby_Vitals
 */
class Secret_Drawer_Cubby_Vitals {

	const CACHE_KEY = 'secret_drawer_vitals';

	/**
	 * Bump whenever the cached row set's identity changes — new rows,
	 * new row keys, changed thresholds. A mismatch makes the next render
	 * recompute instead of serving the previous shape's snapshot.
	 *
	 * v2: autoload, plugin split count, missed schedules added.
	 * v3: split cards render two captioned halves; warn_side localizes
	 * the amber to the offending half.
	 * v4: environment, HTTPS, object cache rows added.
	 */
	const CACHE_SCHEMA = 4;

	/**
	 * Flag rows whose `ok` is falsy when this many+ inactive plugins
	 * are installed. Inactive plugin files are still scanned on updates
	 * and are a common source of "what is even installed here".
	 */
	const INACTIVE_WARN = 5;

	/**
	 * Server-rendered cubby body: a definition list of vital signs.
	 *
	 * Display-only → no wireCubby() hook needed; the JS leaves these
	 * alone (same as notifications). Any filtering happens server-side
	 * via `secret_drawer_vitals`.
	 *
	 * @return string
	 */
	public static function get_html() {
		$vitals = self::get_vitals();

		ob_start();
		?>
		<dl class="sd-vitals">
			<?php foreach ( $vitals as $vital ) : ?>
			<div class="sd-vital<?php echo ! empty( $vital['warn_side'] ) ? ' sd-vital--splitwarn' : ( empty( $vital['ok'] ) ? ' sd-vital--warn' : '' ); ?>">
					<dt><?php echo esc_html( $vital['label'] ); ?></dt>
					<dd>
						<?php if ( isset( $vital['value2'] ) ) : ?>
							<span class="sd-vital-part<?php echo 1 === (int) ( $vital['warn_side'] ?? 0 ) ? ' sd-vital-part--warn' : ''; ?>">
								<?php echo esc_html( $vital['value'] ); ?>
								<?php if ( ! empty( $vital['note'] ) ) : ?><em class="sd-vital-sub"><?php echo esc_html( $vital['note'] ); ?></em><?php endif; ?>
							</span>
							<span class="sd-vital-div" aria-hidden="true">|</span>
							<span class="sd-vital-part<?php echo 2 === (int) ( $vital['warn_side'] ?? 0 ) ? ' sd-vital-part--warn' : ''; ?>">
								<?php echo esc_html( $vital['value2'] ); ?>
								<?php if ( ! empty( $vital['note2'] ) ) : ?><em class="sd-vital-sub"><?php echo esc_html( $vital['note2'] ); ?></em><?php endif; ?>
							</span>
						<?php else : ?>
							<?php if ( ! empty( $vital['url'] ) ) : ?><a href="<?php echo esc_url( $vital['url'] ); ?>"><?php endif; ?>
							<?php echo esc_html( $vital['value'] ); ?>
							<?php if ( ! empty( $vital['url'] ) ) : ?></a><?php endif; ?>
							<?php if ( ! empty( $vital['note'] ) ) : ?>
								<span class="sd-vital-note"><?php echo esc_html( $vital['note'] ); ?></span>
							<?php endif; ?>
						<?php endif; ?>
					</dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<p class="sd-muted sd-vital-stale"><?php esc_html_e( 'Snapshot refreshes hourly; the clock is live.', 'secret-drawer' ); ?></p>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * The vital signs. Everything except the clock comes from the hourly
	 * transient; the clock is re-read at render so it never claims to be
	 * older than it is. Filterable via `secret_drawer_vitals` — entries
	 * keep {label, value, value2?, note?, note2?, ok?, url?, warn_side?}.
	 * `ok` is truthy for a healthy row (amber edge when falsy); a
	 * `value2` row renders as two halves filling the card (captions
	 * under each) and may point `warn_side` at 1 or 2 to amber just the
	 * offending half — plus `sd-vital--splitwarn` on the card edge and
	 * divider. `url` links a single-value row, `note`/`note2` caption
	 * the halves.
	 *
	 * @return array[]
	 */
	public static function get_vitals() {
		$cached = get_transient( self::CACHE_KEY );
		if (
			! is_array( $cached )
			|| self::CACHE_SCHEMA !== ( $cached['schema'] ?? 0 )
			|| empty( $cached['rows'] )
			|| ! is_array( $cached['rows'] )
		) {
			$vitals = self::compute_vitals();
		} else {
			$vitals = $cached['rows'];
		}

		// The clock is cheap and must be fresh — never cached.
		$vitals[] = array(
			'label' => __( 'Site time', 'secret-drawer' ),
			'value' => wp_date( 'D j M, ' . get_option( 'time_format' ) ),
			'note'  => wp_timezone_string(),
			'ok'    => true,
		);

		/**
		 * Add or replace the vital signs.
		 *
		 * @param array[] $vitals Vital entries.
		 */
		return (array) apply_filters( 'secret_drawer_vitals', $vitals );
	}

	/**
	 * The cached half: everything that can honestly ride the hourly
	 * transient.
	 *
	 * @return array[]
	 */
	private static function compute_vitals() {
		$memory = defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : (string) ini_get( 'memory_limit' );

		$debug_value = defined( 'WP_DEBUG' ) && WP_DEBUG
			? __( 'On', 'secret-drawer' )
			: __( 'Off', 'secret-drawer' );

		$theme = wp_get_theme();

		$vitals = array(
			array(
				'label' => __( 'WordPress', 'secret-drawer' ),
				'value' => get_bloginfo( 'version' ),
				'ok'    => true,
			),
			array(
				'label' => __( 'PHP version', 'secret-drawer' ),
				'value' => PHP_VERSION,
				'ok'    => version_compare( PHP_VERSION, '7.4', '>=' ),
			),
			array(
				'label' => __( 'Memory limit', 'secret-drawer' ),
				'value' => (string) $memory,
				'ok'    => true,
			),
			self::vital_autoload(),
			self::vital_plugins(),
			self::vital_missed_schedules(),
			self::vital_environment(),
			self::vital_https(),
			self::vital_object_cache(),
			array(
				'label' => __( 'WP_DEBUG', 'secret-drawer' ),
				'value' => $debug_value,
				'ok'    => ! defined( 'WP_DEBUG' ) || ! WP_DEBUG,
			),
			array(
				'label' => __( 'Active theme', 'secret-drawer' ),
				'value' => self::theme_label(),
				'ok'    => true,
			),
		);

		wp_cache_delete( self::CACHE_KEY, 'transient' );

		set_transient(
			self::CACHE_KEY,
			array(
				'schema' => self::CACHE_SCHEMA,
				'rows'   => $vitals,
			),
			HOUR_IN_SECONDS
		);
		return $vitals;
	}

	/**
	 * Autoloaded data, the way Site Health measures it (serialized string
	 * length of wp_load_alloptions()). Mirrors Site Health's threshold —
	 * including its `site_status_autoloaded_options_size_limit` filter —
	 * so both tools always agree.
	 *
	 * @return array
	 */
	private static function vital_autoload() {
		$bytes = 0;
		foreach ( (array) wp_load_alloptions() as $option_value ) {
			if ( is_array( $option_value ) || is_object( $option_value ) ) {
				$option_value = maybe_serialize( $option_value );
			}
			$bytes += strlen( (string) $option_value );
		}

		/**
		 * Autoload threshold, kept identical to Site Health (800KB).
		 *
		 * @param int $limit Autoloaded options threshold size.
		 */
		$limit = (int) apply_filters( 'site_status_autoloaded_options_size_limit', 800000 );

		return array(
			'label' => __( 'Autoloaded options', 'secret-drawer' ),
			'value' => size_format( $bytes ),
			'ok'    => $bytes < $limit,
		);
	}

	/**
	 * Plugins: active | inactive on one card. Inactive-but-installed
	 * files are still scanned for updates and still a risk surface, so
	 * a long tail of them warns.
	 *
	 * @return array
	 */
	private static function vital_plugins() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$active = count( (array) get_option( 'active_plugins', array() ) );
		if ( is_multisite() ) {
			$active += count( (array) get_site_option( 'active_sitewide_plugins', array() ) );
		}
		$inactive = max( 0, count( (array) get_plugins() ) - $active );

		return array(
			'label'   => __( 'Plugins', 'secret-drawer' ),
			'value'   => (string) $active,
			'note'    => __( 'active', 'secret-drawer' ),
			'value2'  => (string) $inactive,
			'note2'   => __( 'inactive', 'secret-drawer' ),
			'warn_side' => $inactive >= self::INACTIVE_WARN ? 2 : 0,
			'ok'      => $inactive < self::INACTIVE_WARN,
		);
	}

	/**
	 * Missed cron schedules: future posts whose publish time has passed.
	 * Nonzero is the telltale of WP-Cron not running; deep-link to the
	 * stuck list so the next step is one click away.
	 *
	 * @return array
	 */
	private static function vital_missed_schedules() {
		$stuck = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'future',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'date_query'     => array(
					array(
						'column'    => 'post_date_gmt',
						'before'    => gmdate( 'Y-m-d H:i:s' ),
						'inclusive' => true,
					),
				),
			)
		);
		$missed = (int) $stuck->found_posts;

		return array(
			'label' => __( 'Missed schedules', 'secret-drawer' ),
			'value' => (string) $missed,
			'ok'    => 0 === $missed,
			'url'   => admin_url( 'edit.php?post_status=future&orderby=date&order=asc' ),
		);
	}

	/**
	 * Environment type: production / staging / development etc. (core's
	 * `wp_get_environment_type()`, filterable by hosts and dev setups).
	 * Pure identity — the "which site am I holding" glance — and never
	 * judged: a dev site is allowed to be a dev site.
	 *
	 * @return array
	 */
	private static function vital_environment() {
		return array(
			'label' => __( 'Environment', 'secret-drawer' ),
			'value' => (string) wp_get_environment_type(),
			'ok'    => true,
		);
	}

	/**
	 * HTTPS on the home URL — read from config, never probed over the
	 * network. A plaintext admin is a real risk, so "Off" trips the
	 * card; on localhost that amber is also simply true.
	 *
	 * @return array
	 */
	private static function vital_https() {
		$is_https = 0 === strpos( home_url(), 'https://' );

		return array(
			'label' => __( 'HTTPS', 'secret-drawer' ),
			'value' => $is_https
				? __( 'On', 'secret-drawer' )
				: __( 'Off', 'secret-drawer' ),
			'ok'    => $is_https,
		);
	}

	/**
	 * Persistent object cache present or not — Site Health benchmarks
	 * this for a reason: without it, every request re-runs every query.
	 * Informational: some sites are small enough not to care.
	 *
	 * @return array
	 */
	private static function vital_object_cache() {
		return array(
			'label' => __( 'Object cache', 'secret-drawer' ),
			'value' => wp_using_ext_object_cache()
				? __( 'Present', 'secret-drawer' )
				: __( 'None', 'secret-drawer' ),
			'ok'    => true,
		);
	}

	/**
	 * "ThemeName 1.4" — version only when the theme discloses one.
	 *
	 * @return string
	 */
	private static function theme_label() {
		$theme = wp_get_theme();
		$label = $theme->get( 'Name' );
		if ( '' === $label ) {
			return __( 'Unknown', 'secret-drawer' );
		}
		$version = (string) $theme->get( 'Version' );
		return '' !== $version ? $label . ' ' . $version : $label;
	}
}