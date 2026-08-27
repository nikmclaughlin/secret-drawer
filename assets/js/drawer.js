/**
 * Secret Drawer front end.
 *
 * Plain JS, no build step. Mounts a wp-components drawer UI via
 * wp.element.createElement. Cubby bodies are lazy: each tab fetches
 * GET /secret-drawer/v1/cubbies/{id} when activated.
 */
( function () {
	'use strict';

	if ( ! window.SECRET_DRAWER || ! window.wp || ! window.wp.element ) {
		return;
	}

	var config = window.SECRET_DRAWER;
	var h = window.wp.element.createElement;
	var createRoot = window.wp.element.createRoot;
	var C = window.wp.components || {};
	var TabPanel = C.TabPanel;
	var ToggleControl = C.ToggleControl;
	var SelectControl = C.SelectControl;
	var TextControl = C.TextControl;
	var Button = C.Button;
	var STORE_KEY = 'secretDrawer';

	var state = {
		open: false,
		lastActiveElement: null,
		view: 'drawer', // 'drawer' | 'settings'
		saving: false,
		draft: null, // Working copy of settings in the settings view.
		firstRun: ! config.discovered && ! window.localStorage.getItem( STORE_KEY + '.discovered' )
	};

	/* ------------------------------------------------------------------ *
	 * Storage helpers
	 * ------------------------------------------------------------------ */

	function lsGet( key ) {
		try {
			return window.localStorage.getItem( STORE_KEY + '.' + key );
		} catch ( e ) {
			return null;
		}
	}

	function lsSet( key, value ) {
		try {
			window.localStorage.setItem( STORE_KEY + '.' + key, key === 'open' ? ( value ? '1' : '0' ) : value );
		} catch ( e ) {
			/* private mode etc. — state just won't persist */
		}
	}

	/* ------------------------------------------------------------------ *
	 * Trigger: typed secret word
	 * ------------------------------------------------------------------ */

	var buffer = '';
	var trigger = String( config.trigger || '' ).toLowerCase();

	function isTextField( el ) {
		if ( ! el || ! el.tagName ) {
			return false;
		}
		var tag = el.tagName.toLowerCase();
		if ( tag === 'input' || tag === 'textarea' || tag === 'select' ) {
			return true;
		}
		return el.isContentEditable === true || tag === 'body' && el.getAttribute && el.getAttribute( 'contenteditable' ) === 'true';
	}

	function onKeydown( event ) {
		if ( event.ctrlKey || event.metaKey || event.altKey ) {
			return;
		}
		if ( isTextField( event.target ) ) {
			return; // Typing in a post is never a secret word.
		}
		if ( typeof event.key !== 'string' || event.key.length !== 1 ) {
			return;
		}
		buffer = ( buffer + event.key.toLowerCase() ).slice( -16 );
		if ( trigger.length > 1 && buffer.slice( -trigger.length ) === trigger ) {
			buffer = '';
			ghostFlash();
			open();
		}
	}

	/** Brief confirmation of the matched word, near the bottom center. */
	function ghostFlash() {
		var el = document.createElement( 'div' );
		el.className = 'sd-ghost';
		el.textContent = '…' + trigger;
		el.setAttribute( 'aria-hidden', 'true' );
		document.body.appendChild( el );
		window.setTimeout( function () {
			el.remove();
		}, 400 );
	}

	/* ------------------------------------------------------------------ *
	 * First-unlock celebration (one-time)
	 * ------------------------------------------------------------------ */

	function celebrate() {
		if ( ! state.firstRun ) {
			return;
		}
		state.firstRun = false;
		lsSet( 'discovered', '1' );

		var toast = document.createElement( 'div' );
		toast.className = 'sd-toast';
		toast.setAttribute( 'role', 'status' );
		toast.textContent = config.strings.toast;
		document.body.appendChild( toast );
		window.setTimeout( function () {
			toast.remove();
		}, 4200 );

		confetti();
	}

	function confetti() {
		var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		if ( reduceMotion ) {
			return;
		}
		var field = document.createElement( 'div' );
		field.className = 'sd-confetti';
		field.setAttribute( 'aria-hidden', 'true' );
		for ( var i = 0; i < 28; i++ ) {
			var bit = document.createElement( 'span' );
			bit.textContent = '🤫';
			bit.style.left = 20 + Math.random() * 60 + '%';
			bit.style.animationDelay = ( Math.random() * 0.6 ).toFixed( 2 ) + 's';
			bit.style.animationDuration = 1.6 + Math.random() * 1.4 + 's';
			bit.style.fontSize = 14 + Math.random() * 14 + 'px';
			field.appendChild( bit );
		}
		document.body.appendChild( field );
		window.setTimeout( function () {
			field.remove();
		}, 3400 );
	}

	/* ------------------------------------------------------------------ *
	 * Cubby bodies: lazy fetch per tab
	 * ------------------------------------------------------------------ */

	function fetchCubby( id, mount ) {
		mount.innerHTML = '';
		var loading = document.createElement( 'p' );
		loading.className = 'sd-muted';
		loading.textContent = '…';
		mount.appendChild( loading );

		window.fetch( config.restRoot + '/cubbies/' + encodeURIComponent( id ), {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': config.nonce }
		} ).then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( String( res.status ) );
			}
			return res.json();
		} ).then( function ( data ) {
			mount.innerHTML = data && data.html ? String( data.html ) : '';
		} ).catch( function () {
			// Routes for a cubby may not exist yet (M3); show a quiet placeholder.
			mount.innerHTML = '';
			var p = document.createElement( 'p' );
			p.className = 'sd-muted';
			p.textContent = config.strings.emptyCubby;
			mount.appendChild( p );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Drawer UI
	 * ------------------------------------------------------------------ */

	function onOpen() {
		document.dispatchEvent( new CustomEvent( 'secret-drawer:open' ) );
	}

	function onClose() {
		document.dispatchEvent( new CustomEvent( 'secret-drawer:close' ) );
	}

	function open() {
		if ( state.open ) {
			return;
		}
		state.lastActiveElement = document.activeElement;
		state.open = true;
		lsSet( 'open', true );
		render();
		onOpen();
	}

	function close() {
		if ( ! state.open ) {
			return;
		}
		state.open = false;
		state.view = 'drawer'; // ESC from settings lands back on the drawer, not a half-open settings view.
		lsSet( 'open', false );
		render();
		onClose();
		if ( state.lastActiveElement && state.lastActiveElement.focus ) {
			state.lastActiveElement.focus();
		}
	}

	function toggle() {
		if ( state.open ) {
			close();
		} else {
			open();
		}
	}

	function onKeydownGlobal( event ) {
		if ( 'Escape' === event.key && state.open ) {
			event.stopPropagation();
			close();
		}
	}

	function Drawer() {
		if ( 'settings' === state.view ) {
			return h( Settings );
		}

		var position = 'bottom' === config.position ? 'bottom' : 'right';
		var className = 'sd-drawer sd-drawer--' + position + ( state.open ? ' is-open' : '' );

		var tabs = ( config.cubbies || [] ).map( function ( cubby ) {
			return { name: cubby.id, title: cubby.title, className: 'sd-tab' };
		} );

		var body;
		if ( ! config.cubbies || ! config.cubbies.length ) {
			body = h( 'p', { className: 'sd-muted sd-empty' }, 'This drawer is empty. Add something from the library.' );
		} else if ( TabPanel ) {
			body = h( TabPanel, {
				className: 'sd-tabs',
				tabs: tabs,
				initialTabName: lsGet( 'lastCubby' ) || ( config.cubbies[ 0 ] && config.cubbies[ 0 ].id ),
				onSelect: function ( name ) {
					lsSet( 'lastCubby', name );
				},
				children: function ( tab ) {
					var mount = h( 'div', { className: 'sd-cubby-body', 'data-cubby': tab.name } );
					// Populate after mount.
					window.setTimeout( function () {
						fetchCubby( tab.name, mount );
					}, 0 );
					return mount;
				}
			} );
		} else {
			body = h( 'p', { className: 'sd-muted' }, config.strings.emptyCubby );
		}

		return h( 'aside', {
			className: className,
			role: 'complementary',
			'aria-label': config.strings.title,
			'aria-hidden': state.open ? 'false' : 'true',
			inert: state.open ? undefined : '',
			style: { width: position === 'bottom' ? undefined : config.width + 'px' }
		},
			h( 'header', { className: 'sd-header' },
				h( 'h2', { className: 'sd-title' }, config.strings.title ),
				h( 'div', { className: 'sd-header-actions' },
					h( 'button', {
						className: 'sd-icon-button',
						type: 'button',
						title: config.strings.settings,
						'aria-label': config.strings.settings,
						disabled: ! config.manageSettings,
						onClick: function () {
							state.view = 'settings';
							state.draft = {
								roles: ( config.roles || [] ).slice(),
								trigger_word: config.trigger,
								position: config.position,
								width: config.width,
								enabled_cubbies: ( config.cubbies || [] ).map( function ( c ) { return c.id; } )
							};
							render();
						}
					}, '⚙️' ),
					h( 'button', {
						className: 'sd-icon-button',
						type: 'button',
						title: config.strings.close,
						'aria-label': config.strings.close,
						onClick: close
					}, '✕' )
				)
			),
			h( 'div', { className: 'sd-body' }, body ),
			h( 'footer', { className: 'sd-footer' }, '🤫' )
		);
	}

	var root = null;
	var container = null;

	function render() {
		if ( ! container ) {
			container = document.createElement( 'div' );
			container.id = 'secret-drawer-root';
			document.body.appendChild( container );
			root = createRoot( container );
		}
		root.render( h( Drawer ) );
	}

	/** Dark pill, bottom center — reused for "Saved ✓". */
	function snackbar( text ) {
		var el = document.createElement( 'div' );
		el.className = 'sd-toast';
		el.setAttribute( 'role', 'status' );
		el.textContent = text;
		document.body.appendChild( el );
		window.setTimeout( function () {
			el.remove();
		}, 2500 );
	}

	/* ------------------------------------------------------------------ *
	 * Applying saved settings — config is rebuilt in place, no reload.
	 * ------------------------------------------------------------------ */

	function applySettings( s ) {
		if ( ! s || typeof s !== 'object' ) {
			return;
		}
		config.trigger = String( s.trigger_word || config.trigger );
		config.width = parseInt( s.width, 10 ) || config.width;
		config.position = ( 'bottom' === s.position ) ? 'bottom' : 'right';
		config.roles = ( s.roles || config.roles || [] ).slice();

		// Rebuild tab list from the sanitized enabled list + catalog.
		// (Server already validated ids against the catalog — trust it.)
		config.cubbies = ( s.enabled_cubbies || [] ).map( function ( id ) {
			var meta = ( config.catalog && config.catalog[ id ] ) || {};
			return {
				id: id,
				title: meta.title || id,
				icon: meta.icon || 'dashicons-marker'
			};
		} );

		// A saved trigger word may have been lowercased/trimmed by the server.
		state.firstRun = false;
		lsSet( 'discovered', '1' );
	}

	/* ------------------------------------------------------------------ *
	 * In-drawer settings view
	 * ------------------------------------------------------------------ */

	function Settings() {
		var draft = state.draft || {};
		var S = config.strings;

		var position = 'bottom' === ( draft.position || config.position ) ? 'bottom' : 'right';
		// The settings view replaces the drawer body inside the same shell;
		// it is always shown open (you got here via an open drawer).
		var className = 'sd-drawer sd-drawer--' + position + ' is-open';

		function toggleCubby( id, on ) {
			draft.enabled_cubbies = on
				? draft.enabled_cubbies.concat( [ id ] )
				: draft.enabled_cubbies.filter( function ( x ) { return x !== id; } );
			render();
		}

		function save() {
			state.saving = true;
			render();

			window.fetch( config.restRoot + '/settings', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				body: JSON.stringify( { settings: draft } )
			} ).then( function ( res ) {
				if ( ! res.ok ) {
					throw new Error( String( res.status ) );
				}
				return res.json();
			} ).then( function ( data ) {
				// REST returns the sanitized settings — apply them live, no reload.
				applySettings( data && data.settings ? data.settings : {} );
				state.saving = false;
				state.view = 'drawer';
				state.draft = null;
				render();
				snackbar( config.strings.saved );
			} ).catch( function () {
				state.saving = false;
				var bar = document.querySelector( '.sd-save-status' );
				if ( bar ) {
					bar.textContent = config.strings.saveError;
					bar.className = 'sd-save-status sd-error';
				}
			} );
		}

		return h( 'aside', {
			className: className,
			role: 'complementary',
			'aria-label': S.settings,
			'aria-hidden': 'false',
			style: { width: position === 'bottom' ? undefined : ( parseInt( draft.width, 10 ) || config.width ) + 'px' }
		},
			h( 'header', { className: 'sd-header' },
				h( 'button', {
					className: 'sd-icon-button',
					type: 'button',
					'aria-label': S.back,
					onClick: function () {
						state.view = 'drawer';
						render();
					}
				}, '←' ),
				h( 'h2', { className: 'sd-title' }, S.settings )
			),
			h( 'div', { className: 'sd-body sd-settings-body' },
				// Who can find it.
				h( 'h3', null, S.roles ),
				h( 'div', { className: 'sd-field-grid' },
					Object.keys( config.roleOptions || {} ).map( function ( slug ) {
						var on = draft.roles.indexOf( slug ) !== -1;
						return h( 'label', { key: slug, className: 'sd-check' },
							h( 'input', {
								type: 'checkbox',
								checked: on,
								onChange: function ( e ) {
									draft.roles = e.target.checked
										? draft.roles.concat( [ slug ] )
										: draft.roles.filter( function ( r ) { return r !== slug; } );
									render();
								}
							} ),
							' ' + ( config.roleOptions[ slug ] || slug )
						);
					} )
				),

				// Secret word.
				TextControl ? h( TextControl, {
					label: S.secretWord,
					help: S.secretHelp,
					value: draft.trigger_word,
					onChange: function ( v ) { draft.trigger_word = v; render(); }
				} ) : h( 'label', { className: 'sd-field' },
					S.secretWord,
					h( 'input', {
						type: 'text',
						value: draft.trigger_word,
						onChange: function ( e ) { draft.trigger_word = e.target.value; render(); }
					} )
				),

				// Position + width.
				h( 'div', { className: 'sd-field-row' },
					SelectControl ? h( SelectControl, {
						label: S.position,
						value: draft.position,
						options: [
							{ value: 'right', label: 'Right →' },
							{ value: 'bottom', label: 'Bottom ↑' }
						],
						onChange: function ( v ) { draft.position = v; render(); }
					} ) : h( 'label', { className: 'sd-field' },
						S.position,
						h( 'select', {
							value: draft.position,
							onChange: function ( e ) { draft.position = e.target.value; render(); }
						},
							h( 'option', { value: 'right' }, 'Right →' ),
							h( 'option', { value: 'bottom' }, 'Bottom ↑' )
						)
					),
					TextControl ? h( TextControl, {
						type: 'number',
						label: S.width,
						value: draft.width,
						onChange: function ( v ) { draft.width = v; render(); }
					} ) : null
				),

				// Cubby library: in-drawer (toggles) + available to add.
				h( 'h3', null, S.inDrawer ),
				( draft.enabled_cubbies.length ? draft.enabled_cubbies.map( function ( id ) {
					var meta = config.catalog[ id ] || {};
					return h( 'div', { key: id, className: 'sd-row' },
						h( 'span', { className: 'sd-row-title' }, meta.title || id ),
						h( 'button', {
							className: 'sd-icon-button',
							type: 'button',
							'aria-label': 'Remove ' + ( meta.title || id ),
							onClick: function () { toggleCubby( id, false ); }
						}, '−' )
					);
				} ) : h( 'p', { className: 'sd-muted' }, 'This drawer is empty. Add something from the library.' ) ),

				h( 'h3', null, S.library ),
				Object.keys( config.catalog ).filter( function ( id ) {
					return draft.enabled_cubbies.indexOf( id ) === -1;
				} ).map( function ( id ) {
					var meta = config.catalog[ id ];
					return h( 'div', { key: id, className: 'sd-row' },
						h( 'span', null,
							h( 'strong', null, meta.title ),
							h( 'span', { className: 'sd-muted sd-row-desc' }, meta.description || '' )
						),
						h( 'button', {
							className: 'sd-icon-button',
							type: 'button',
							'aria-label': 'Add ' + meta.title,
							onClick: function () { toggleCubby( id, true ); }
						}, '+' )
					);
				} )
			),
			h( 'footer', { className: 'sd-footer sd-settings-footer' },
				h( 'span', { className: 'sd-save-status', role: 'status' } ),
				h( Button, {
					variant: 'primary',
					isBusy: state.saving,
					disabled: state.saving,
					onClick: save
				}, S.save )
			)
		);
	}

	document.addEventListener( 'keydown', onKeydown, true );
	document.addEventListener( 'keydown', onKeydownGlobal, true );

	window.SecretDrawer = {
		open: open,
		close: close,
		toggle: toggle,
		showCubby: function ( id ) {
			open();
			lsSet( 'lastCubby', id );
			render();
		}
	};

	// Restore previous open state without the celebration.
	if ( lsGet( 'open' ) === '1' ) {
		state.open = true;
	}

	render();
} )();