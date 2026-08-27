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
	var TabPanel = window.wp.components && window.wp.components.TabPanel;
	var STORE_KEY = 'secretDrawer';

	var state = {
		open: false,
		lastActiveElement: null,
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
						disabled: true // M2: in-drawer settings view.
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

	/* ------------------------------------------------------------------ *
	 * Boot
	 * ------------------------------------------------------------------ */

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