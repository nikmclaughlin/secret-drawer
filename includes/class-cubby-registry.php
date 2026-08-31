<?php
/**
 * Cubby type registry.
 *
 * Single source of truth for cubby *types*: the built-ins, plus anything
 * third parties register via the `secret_drawer_cubbies` filter (see
 * SECRET-DRAWER-EXTENDING.md for the entry schema). The registry normalizes
 * entries, sorts them by `order`, and gates each
 * type on its declared `capability`. Which types actually appear in a
 * given user's drawer is still the `enabled_cubbies` setting — the library
 * and the drawer's contents are separate concerns.
 *
 * @package SecretDrawer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of cubby types.
 */
final class Secret_Drawer_Cubby_Registry {

	/**
	 * All registered cubby types visible to the current user: id → entry.
	 *
	 * Same normalization as ungated(), then types whose capability the
	 * current user lacks are dropped. Sorted by `order` (stable:
	 * insertion order breaks ties).
	 *
	 * @return array[]
	 */
	public static function all() {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$entries = self::ungated();

		foreach ( $entries as $id => $entry ) {
			if ( '' !== $entry['capability'] && ! current_user_can( $entry['capability'] ) ) {
				unset( $entries[ $id ] ); // Per-type gate: never seen, never shown.
			}
		}

		$cache = $entries;
		return $cache;
	}

	/**
	 * The merged, normalized catalog *without* per-type capability
	 * gating — what settings sanitization must validate against (a
	 * settings save has to know every registered type, not just the ones
	 * the current user can see).
	 *
	 * @return array[]
	 */
	public static function ungated() {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$builtins = array(
			'notes'         => array(
				'title'       => __( 'Notes', 'secret-drawer' ),
				'icon'        => 'dashicons-edit-page',
				'description' => __( 'A private scratchpad, autosaved to your profile.', 'secret-drawer' ),
				'pack'        => 'essentials',
			),
			'links'         => array(
				'title'       => __( 'Quick Links', 'secret-drawer' ),
				'icon'        => 'dashicons-admin-links',
				'description' => __( 'Your own curated jump list of admin screens.', 'secret-drawer' ),
				'pack'        => 'essentials',
			),
			'notifications' => array(
				'title'       => __( 'Notifications', 'secret-drawer' ),
				'icon'        => 'dashicons-bell',
				'description' => __( 'Update and moderation counts, with deep links.', 'secret-drawer' ),
				'pack'        => 'essentials',
			),
			'levers'        => array(
				'title'       => __( 'Levers', 'secret-drawer' ),
				'icon'        => 'dashicons-controls-play',
				'description' => __( 'One-click actions on the site. Pull with care.', 'secret-drawer' ),
				'pack'        => 'essentials',
			),
			'socrates'      => array(
				'title'       => __( 'Socrates', 'secret-drawer' ),
				'icon'        => 'dashicons-universal-access-alt',
				'description' => __( 'The unexamined admin is not worth running.', 'secret-drawer' ),
				'pack'        => 'essentials',
			),
			'dice'          => array(
				'title'       => __( 'Dice', 'secret-drawer' ),
				'icon'        => '🎲',
				'description' => __( 'A d2, d6, d12, or d20 with a tumble and a short memory.', 'secret-drawer' ),
				'pack'        => 'livin-large',
			),
			'vitals'        => array(
				'title'       => __( 'Site Vitals', 'secret-drawer' ),
				'icon'        => '🩺',
				'description' => __( 'WP/PHP, plugins, cron, HTTPS, and more.', 'secret-drawer' ),
				'pack'        => 'livin-large',
			),
			'passphrase'    => array(
				'title'       => __( 'Passphrase', 'secret-drawer' ),
				'icon'        => '🔐',
				'description' => __( 'Random words and a number, made fresh in your browser.', 'secret-drawer' ),
				'pack'        => 'livin-large',
			),
			'timer'         => array(
				'title'       => __( 'Focus timer', 'secret-drawer' ),
				'icon'        => '⏱️',
				'description' => __( 'A quiet countdown that remembers itself between panels.', 'secret-drawer' ),
				'pack'        => 'livin-large',
			),
		);

		/**
		 * Register cubby types. Built-ins are included; add or override
		 * entries keyed by cubby id. See SECRET-DRAWER-EXTENDING.md for the schema.
		 *
		 * @param array[] $cubbies Cubby type catalog.
		 */
		$merged = (array) apply_filters( 'secret_drawer_cubbies', $builtins );

		$entries = array();
		$seq     = 0;
		foreach ( $merged as $id => $cubby ) {
			if ( ! is_array( $cubby ) || empty( $cubby['title'] ) || ! is_string( $id ) || '' === $id ) {
				continue; // No title = hidden type; invalid shape = ignore.
			}
			if ( ! preg_match( '/^[a-z0-9_-]+$/', $id ) ) {
				continue; // REST route constrains ids to this shape.
			}

			$entries[ $id ] = array(
				'id'          => $id,
				'title'       => (string) $cubby['title'],
				'icon'        => isset( $cubby['icon'] ) ? (string) $cubby['icon'] : 'dashicons-marker',
				'description' => isset( $cubby['description'] ) ? (string) $cubby['description'] : '',
				'capability'  => isset( $cubby['capability'] ) ? (string) $cubby['capability'] : '',
				'singleton'   => ! isset( $cubby['singleton'] ) || (bool) $cubby['singleton'],
				'order'       => isset( $cubby['order'] ) ? (int) $cubby['order'] : 50,
				'refresh_on'  => isset( $cubby['refresh_on'] ) && 'never' === $cubby['refresh_on'] ? 'never' : 'open',
				'render'      => ( isset( $cubby['render'] ) && is_callable( $cubby['render'] ) ) ? $cubby['render'] : null,
				'pack'        => ( isset( $cubby['pack'] ) && preg_match( '/^[a-z0-9_-]+$/', (string) $cubby['pack'] ) ) ? (string) $cubby['pack'] : '',
				'seq'         => $seq++,
			);
		}

		uasort(
			$entries,
			static function ( $a, $b ) {
				if ( $a['order'] === $b['order'] ) {
					return $a['seq'] <=> $b['seq']; // Stable tie-break.
				}
				return $a['order'] <=> $b['order'];
			}
		);

		$cache = $entries;
		return $cache;
	}

	/**
	 * Pack catalog for the Cubby Library.
	 *
	 * Packs are presentation grouping only — never stored user state, so
	 * reorganizing them never affects anyone's saved drawer. Metadata
	 * comes from the `secret_drawer_packs` filter; packs referenced by
	 * registered cubbies but missing from the filter still appear, with a
	 * humanized-id fallback title, so tagging cubbies alone is enough.
	 * Hidden from the library: packs with no visible cubbies.
	 *
	 * @return array[] id → { title, icon, description, order, members }
	 */
	public static function packs() {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$meta = array(
			'essentials'   => array(
				'title'       => __( 'Essentials', 'secret-drawer' ),
				'icon'        => '🧰',
				'description' => __( 'The everyday set: notes, links, counts, and one-click actions.', 'secret-drawer' ),
				'order'       => 10,
			),
			'livin-large'  => array(
				'title'       => __( 'Livin’ Large', 'secret-drawer' ),
				'icon'        => '🎉',
				'description' => __( 'Dice, vitals, a passphrase, and a timer — desk odds and ends.', 'secret-drawer' ),
				'order'       => 20,
			),
		);

		/**
		 * Register or override pack metadata for the Cubby Library.
		 * Keys are pack ids (same charset as cubby ids). Cubbies join a
		 * pack via their own `pack` field — see SECRET-DRAWER-EXTENDING.md.
		 *
		 * @param array[] $packs Pack metadata catalog.
		 */
		$meta = (array) apply_filters( 'secret_drawer_packs', $meta );

		$packs = array();
		foreach ( self::all() as $id => $entry ) {
			$pack = $entry['pack'];
			if ( '' === $pack ) {
				continue; // Ungrouped: flat library row, not a pack.
			}
			if ( ! isset( $packs[ $pack ] ) ) {
				$packs[ $pack ] = array(
					'title'       => '',
					'icon'        => 'dashicons-category',
					'description' => '',
					'order'       => 50,
					'members'     => array(),
				);
			}
			$packs[ $pack ]['members'][] = $id;
		}

		foreach ( $packs as $pack_id => $pack ) {
			$overrides = isset( $meta[ $pack_id ] ) && is_array( $meta[ $pack_id ] ) ? $meta[ $pack_id ] : array();

			// Fallback title: humanized id ("desk-odds" → "Desk odds").
			if ( empty( $overrides['title'] ) && empty( $pack['title'] ) ) {
				$pack['title'] = ucwords( str_replace( array( '-', '_' ), ' ', $pack_id ) );
			} else {
				$pack['title'] = ! empty( $overrides['title'] ) ? (string) $overrides['title'] : $pack['title'];
			}
			$pack['icon']        = ! empty( $overrides['icon'] ) ? (string) $overrides['icon'] : $pack['icon'];
			$pack['description'] = isset( $overrides['description'] ) ? (string) $overrides['description'] : $pack['description'];
			$pack['order']       = isset( $overrides['order'] ) ? (int) $overrides['order'] : $pack['order'];
			$pack['members']     = array_values( array_unique( $pack['members'] ) );
			$packs[ $pack_id ]   = $pack;
		}

		uasort(
			$packs,
			static function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);

		$cache = $packs;
		return $cache;
	}

	/**
	 * One cubby type's normalized entry, or null.
	 *
	 * @param string $id Cubby id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$id = (string) $id;
		return self::all()[ $id ] ?? null;
	}

	/**
	 * Render a cubby's body HTML for the current user.
	 *
	 * Re-checks the per-type capability at render time, then calls the
	 * type's `render` callback. Built-ins delegate to their classes.
	 *
	 * @param string $id Cubby id.
	 * @return string HTML, or '' when the cubby cannot be rendered.
	 */
	public static function render( $id ) {
		$entry = self::get( $id );

		if ( ! $entry || empty( $entry['title'] ) ) {
			return '';
		}
		if ( '' !== $entry['capability'] && ! current_user_can( $entry['capability'] ) ) {
			return '';
		}

		if ( ! empty( $entry['render'] ) ) {
			$html = (string) call_user_func( $entry['render'] );
			return '' === trim( $html ) ? '' : $html;
		}

		// Built-ins: no render callback, delegate to the class.
		switch ( $id ) {
			case 'notes':
				return Secret_Drawer_Cubby_Notes::get_html();
			case 'links':
				return Secret_Drawer_Cubby_Links::get_html();
			case 'notifications':
				return Secret_Drawer_Cubby_Notifications::get_html();
			case 'levers':
				return Secret_Drawer_Cubby_Levers::get_html();
			case 'socrates':
				return Secret_Drawer_Cubby_Socrates::get_html();
			case 'dice':
				return Secret_Drawer_Cubby_Dice::get_html();
			case 'vitals':
				return Secret_Drawer_Cubby_Vitals::get_html();
			case 'passphrase':
				return Secret_Drawer_Cubby_Passphrase::get_html();
			case 'timer':
				return Secret_Drawer_Cubby_Timer::get_html();
		}

		return '';
	}
}