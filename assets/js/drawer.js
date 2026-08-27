/**
 * Secret Drawer front end.
 *
 * Plain JS, no build step. Mounts the launcher drawer via
 * wp.element.createElement; cubbies pop out as attached sidebars
 * (panels), each lazily fetching GET /secret-drawer/v1/cubbies/{id}.
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
		panels: [], // Open pop-out panels, launcher-first: { el, parent, cubbyId }.
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

	/** The active secret word, read live so applySettings() takes effect immediately. */
	function currentTrigger() {
		return String( config.trigger || '' ).toLowerCase();
	}

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
		var trigger = currentTrigger();
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
		el.textContent = '…' + currentTrigger();
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

	function fetchCubby( id, mount, panelEl ) {
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
			wireCubby( id, mount, panelEl );
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
	 * Cubby interactions (event delegation — survives body re-renders)
	 * ------------------------------------------------------------------ */

	function wireCubby( id, mount, panelEl ) {
		if ( 'notes' === id ) {
			wireNotes( mount, panelEl );
		} else if ( 'links' === id ) {
			wireLinks( mount );
		}
		// Notifications is display-only.
	}

	/** Hide/show the links add-form (it starts hidden behind a New link button). */
	function setLinksFormVisible( mount, visible ) {
		var form = mount.querySelector( '.sd-links-add' );
		var newBtn = mount.querySelector( '[data-sd-link-new]' );
		var err = mount.querySelector( '.sd-link-error' );
		if ( form ) {
			form.hidden = ! visible;
		}
		if ( newBtn ) {
			newBtn.textContent = visible ? ( newBtn.dataset.labelCancel || 'Cancel' ) : ( newBtn.dataset.labelNew || '＋ New link' );
		}
		if ( err && ! visible ) {
			err.hidden = true;
			err.textContent = '';
		}
	}

	/**
	 * Notes: each note editor is its own pop-out panel with a private
	 * autosave closure — no shared pending state, so two open editors
	 * never bleed into each other.
	 */
	var NOTE_DEBOUNCE = 1200;

	/** Keep the list row in sync with edited content (stored copy + preview). */
	function syncNoteRow( listMount, id, content ) {
		if ( ! listMount ) {
			return;
		}
		var row = listMount.querySelector( '[data-note-id="' + id + '"]' );
		var open = row ? row.querySelector( '[data-sd-note-open]' ) : null;
		if ( ! open ) {
			return;
		}
		var text = String( content ).replace( /\s+/g, ' ' ).trim();
		open.setAttribute( 'data-content', content );
		open.textContent = text ? ( text.length > 60 ? text.slice( 0, 60 ) + '…' : text ) : '(empty note)';
	}

	/** Open a note editor as its own panel, attached to the notes list panel. */
	function openEditorPanel( note, parentEl, listMount, focusEditor ) {
		var layer = panelLayer();
		if ( ! layer ) {
			return;
		}
		// One editor per note: re-focus an already-open editor, never duplicate.
		var existing = state.panels.filter( function ( p ) {
			return p.editor && p.noteId === note.id;
		} )[ 0 ];
		if ( existing ) {
			var openTa = existing.el.querySelector( 'textarea' );
			if ( openTa ) {
				openTa.focus();
			}
			return;
		}

		var el = document.createElement( 'aside' );
		el.className = 'sd-panel sd-panel--editor';
		el.setAttribute( 'role', 'complementary' );
		el.setAttribute( 'aria-label', 'Note' );
		el.innerHTML =
			'<header class="sd-header"><h2 class="sd-title">Note</h2>' +
			'<button type="button" class="sd-icon-button sd-panel-close" aria-label="' + ( config.strings.close || 'Close' ) + '">✕</button></header>' +
			'<div class="sd-body sd-editor-body">' +
			'<textarea class="sd-notes" rows="8"></textarea>' +
			'<p class="sd-note-meta"><span class="sd-save-ind" aria-live="polite"></span></p>' +
			'</div>';
		layer.appendChild( el );

		var record = { el: el, parent: parentEl, cubbyId: 'notes', editor: true, noteId: note.id, dirty: false, timer: null };
		state.panels.push( record );
		restackPanels();

		var field = el.querySelector( 'textarea' );
		var indicator = el.querySelector( '.sd-save-ind' );
		field.value = note.content || '';

		function saveEditor() {
			record.dirty = false;
			window.fetch( config.restRoot + '/cubbies/notes/save', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				body: JSON.stringify( { id: record.noteId, content: field.value } )
			} ).then( function ( res ) {
				if ( ! res.ok ) {
					throw new Error( 'status ' + res.status );
				}
				if ( indicator.isConnected ) {
					indicator.textContent = 'Saved ✓';
				}
				syncNoteRow( listMount, record.noteId, field.value );
			} ).catch( function () {
				record.dirty = true;
				if ( indicator.isConnected ) {
					indicator.textContent = 'Save failed — will retry on next edit.';
				}
			} );
		}

		field.addEventListener( 'input', function () {
			record.dirty = true;
			if ( indicator.isConnected ) {
				indicator.textContent = 'Saving…';
			}
			if ( record.timer ) {
				window.clearTimeout( record.timer );
			}
			record.timer = window.setTimeout( saveEditor, NOTE_DEBOUNCE );
		} );

		el.querySelector( '.sd-panel-close' ).addEventListener( 'click', function () {
			popPanel( el ); // popPanel flushes via the panel's flush closure first.
		} );

		// Flush closure used by popPanel/closeAllPanels (ESC, drawer close).
		record.flush = function () {
			if ( record.timer ) {
				window.clearTimeout( record.timer );
				record.timer = null;
			}
			if ( record.dirty ) {
				saveEditor();
			}
		};

		if ( focusEditor ) {
			field.focus();
		}
	}

	function wireNotes( mount, panelEl ) {
		// One delegated listener per cubby mount (panels included);
		// targets are resolved at event time, never cached.
		if ( mount.dataset.sdNotesWired ) {
			return;
		}
		mount.dataset.sdNotesWired = '1';
		// The notes list panel this editor panels will attach to.
		var listPanel = panelEl || null;

		mount.addEventListener( 'click', function ( event ) {
			var newBtn = event.target.closest( '[data-sd-note-new]' );
			var openBtn = event.target.closest( '[data-sd-note-open]' );
			var delBtn = event.target.closest( '[data-sd-note-delete]' );

			// ＋ New note: create, then pop the editor out of this panel.
			if ( newBtn ) {
				window.fetch( config.restRoot + '/cubbies/notes/create', {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': config.nonce }
				} ).then( function ( res ) {
					if ( ! res.ok ) {
						throw new Error( 'status ' + res.status );
					}
					return res.json();
				} ).then( function ( data ) {
					openEditorPanel( { id: data.id, content: '' }, listPanel, mount, true );
				} ).catch( function () {} );
				return;
			}

			// Clicking a note row: the editor pops out as its own sidebar.
			if ( openBtn ) {
				openEditorPanel( {
					id: openBtn.getAttribute( 'data-sd-note-open' ),
					content: openBtn.getAttribute( 'data-content' ) || ''
				}, listPanel, mount, false );
				return;
			}

			// ✕ on a row: delete server-side; close that note's editor if open.
			if ( delBtn ) {
				var row = delBtn.closest( '.sd-note-row' );
				var id = row ? row.getAttribute( 'data-note-id' ) : null;
				if ( ! id ) {
					return;
				}
				window.fetch( config.restRoot + '/cubbies/notes/delete', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': config.nonce
					},
					body: JSON.stringify( { id: id } )
				} ).then( function ( res ) {
					if ( ! res.ok ) {
						throw new Error( 'status ' + res.status );
					}
					// If this note's editor is open, cascade-close it.
					var editorPanel = state.panels.filter( function ( p ) {
						return p.editor && p.noteId === id;
					} )[ 0 ];
					if ( editorPanel ) {
						popPanel( editorPanel.el );
					}
					return row.remove();
				} ).then( function () {
					if ( ! mount.querySelector( '.sd-note-row' ) ) {
						var list = mount.querySelector( '.sd-notes-list' );
						if ( list ) {
							list.insertAdjacentHTML( 'beforebegin', '<p class="sd-muted">' + ( config.strings.emptyNotes || 'No notes yet.' ) + '</p>' );
						}
					}
				} ).catch( function () {} );
			}
		} );
	}

	/**
	 * Links: delegated add/edit/remove. Validation errors surface in the
	 * form's role=alert slot (server is the validation authority; the
	 * client just displays what it returns).
	 */
	function wireLinks( mount ) {
		// Wire once per cubby instance; listeners on the mount outlive re-fetches.
		if ( mount.dataset.sdLinksWired ) {
			return;
		}
		mount.dataset.sdLinksWired = '1';
		var editing = null; // null | { index: int }

		function labelInput() {
			return mount.querySelector( '.sd-link-label' );
		}

		function urlInputEl() {
			return mount.querySelector( '.sd-link-url' );
		}

		function errEl() {
			return mount.querySelector( '.sd-link-error' );
		}

		function formEl() {
			return mount.querySelector( '.sd-links-add' );
		}

		function showError( message ) {
			var el = errEl();
			if ( el ) {
				el.textContent = message;
				el.hidden = false;
			}
		}

		function clearError() {
			var el = errEl();
			if ( el ) {
				el.hidden = true;
				el.textContent = '';
			}
		}

		function rowLabel( row ) {
			var a = row ? row.querySelector( 'a' ) : null;
			return a ? a.textContent : '';
		}

		function rowUrl( row ) {
			var a = row ? row.querySelector( 'a' ) : null;
			return a ? a.getAttribute( 'href' ) : '';
		}

		function setMode( mode, index ) {
			editing = 'edit' === mode ? { index: index } : null;
			var addBtn = mount.querySelector( '[data-sd-link-add]' );
			var cancelBtn = mount.querySelector( '[data-sd-link-cancel]' );
			if ( addBtn ) {
				addBtn.textContent = 'edit' === mode ? 'Update' : 'Add';
			}
			if ( cancelBtn ) {
				cancelBtn.hidden = 'edit' !== mode;
			}
		}

		function fetchLinks( action, payload, onOk ) {
			window.fetch( config.restRoot + '/cubbies/links/' + action, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': config.nonce
				},
				body: JSON.stringify( payload )
			} ).then( function ( res ) {
				// Validation errors arrive as 400 + JSON { message, links }.
				return res.json().then( function ( data ) {
					if ( ! res.ok ) {
						throw { handled: true, message: data && data.message ? data.message : 'Something went wrong.' };
					}
					return data;
				} );
			} ).then( function ( data ) {
				renderLinks( mount, data.links || [] );
				if ( onOk ) {
					onOk( data );
				}
			} ).catch( function ( err ) {
				showError( err && err.handled ? err.message : 'Connection failed — try again.' );
			} );
		}

		mount.addEventListener( 'click', function ( event ) {
			var addBtn = event.target.closest( '[data-sd-link-add]' );
			var delBtn = event.target.closest( '[data-sd-link-delete]' );
			var editBtn = event.target.closest( '[data-sd-link-edit]' );
			var cancelBtn = event.target.closest( '[data-sd-link-cancel]' );
			var newBtn = event.target.closest( '[data-sd-link-new]' );

			if ( newBtn ) {
				if ( formEl() && ! formEl().hidden ) {
					// Already open → acts as Cancel: close, discard, no save.
					setLinksFormVisible( mount, false );
					setMode( 'reset' );
					labelInput().value = '';
					urlInputEl().value = '';
					return;
				}
				setLinksFormVisible( mount, true );
				labelInput().focus();
				return;
			}

			if ( cancelBtn && editing ) {
				setMode( 'reset' );
				labelInput().value = '';
				urlInputEl().value = '';
				clearError();
				setLinksFormVisible( mount, false );
				return;
			}

			if ( editBtn ) {
				var editRow = editBtn.closest( '.sd-link-row' );
				var editIndex = editRow ? editRow.getAttribute( 'data-index' ) : null;
				if ( null === editIndex ) {
					return;
				}
				// Pre-fill the form and enter edit mode.
				setLinksFormVisible( mount, true );
				setMode( 'edit', parseInt( editIndex, 10 ) );
				labelInput().value = rowLabel( editRow );
				urlInputEl().value = rowUrl( editRow );
				clearError();
				urlInputEl().focus();
				return;
			}

			if ( addBtn ) {
				var label = labelInput();
				var url = urlInputEl();
				if ( ! label || ! url ) {
					return;
				}
				var payload = {
					label: label.value,
					url: url.value
				};
				if ( editing ) {
					payload.index = editing.index;
				}
				fetchLinks( editing ? 'update' : 'add', payload, function () {
					if ( editing ) {
						setMode( 'reset' );
					}
					label.value = '';
					url.value = '';
					clearError();
					label.focus();
				} );
			}

			if ( delBtn ) {
				var row = delBtn.closest( '.sd-link-row' );
				var index = row ? row.getAttribute( 'data-index' ) : null;
				if ( null === index ) {
					return;
				}
				fetchLinks( 'remove', { index: parseInt( index, 10 ) }, function () {
					if ( editing && editing.index === parseInt( index, 10 ) ) {
						setMode( 'reset' );
						labelInput().value = '';
						urlInputEl().value = '';
						clearError();
					}
				} );
			}
		} );

		// Enter inside the add/edit form submits it.
		mount.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' !== event.key || 'BUTTON' === event.target.tagName ) {
				return;
			}
			if ( event.target.closest( '.sd-links-add' ) ) {
				var addBtn = mount.querySelector( '[data-sd-link-add]' );
				if ( addBtn ) {
					addBtn.click();
				}
			}
		} );
	}

	/** Re-render the links list from fresh data. */
	function renderLinks( mount, links ) {
		// The server's markup for the list is the source of truth; simplest
		// correct approach is to re-fetch the body HTML.
		fetchCubby( 'links', mount );
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
		closeAllPanels();
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
			// Cascade: ESC pops the top panel first, then the launcher.
			if ( state.panels.length ) {
				popPanel();
			} else {
				close();
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * Pop-out panels: drawers attached to the drawer.
	 *
	 * The launcher stays open; each cubby opens as its own sidebar stacked
	 * outboard of its parent. Panels are plain DOM (outside React) so the
	 * wire-once delegation model works per-mount, unchanged.
	 * ------------------------------------------------------------------ */

	/** Horizontal gap between stacked panels; grows the cascade outward. */
	var PANEL_STEP = 340; // Panel width (320) + 20 breathing room.
	var PANEL_WIDTH = 320;

	function panelLayer() {
		return document.getElementById( 'secret-drawer-panels' );
	}

	function isRTL() {
		return 'rtl' === document.documentElement.dir;
	}

	/** Viewport-rect of the panel anchor (launcher, or bottom-sheet right edge). */
	function launcherRect() {
		var drawer = document.querySelector( '.sd-drawer' );
		if ( drawer ) {
			return drawer.getBoundingClientRect();
		}
		// No launcher in the DOM (edge case): synthesize a full-height anchor.
		return isRTL()
			? { left: 0, right: 0, top: 0, height: window.innerHeight }
			: { left: window.innerWidth, right: window.innerWidth, top: 0, height: window.innerHeight };
	}

	/** Panels are 1/4 the launcher's height (the joke needs room, not a monolith). */
	function panelHeightFor( rect ) {
		return Math.max( 180, Math.round( rect.height / 4 ) );
	}

	/** Where a panel chained to a parent rect should sit (viewport left). */
	function panelLeftFor( parentRect ) {
		if ( isRTL() ) {
			// Launcher hugs the left edge; the cascade grows rightward.
			return Math.min( parentRect.right + 20, window.innerWidth - PANEL_WIDTH - 8 );
		}
		// Launcher hugs the right edge; the cascade grows leftward.
		return Math.max( 8, parentRect.left - PANEL_STEP );
	}

	/** Open a cubby panel attached to a parent (launcher or another panel). */
	function openPanel( cubbyId, parent ) {
		if ( ! state.open ) {
			open();
		}
		var layer = panelLayer();
		if ( ! layer ) {
			return;
		}
		var parentRect = parent ? parent.getBoundingClientRect() : launcherRect();
		var el = document.createElement( 'aside' );
		el.className = 'sd-panel';
		el.setAttribute( 'role', 'complementary' );
		el.setAttribute( 'aria-label', cubbyTitle( cubbyId ) );
		el.style.left = panelLeftFor( parentRect ) + 'px';
		el.style.top = parentRect.top + 'px';
		el.style.height = panelHeightFor( parentRect ) + 'px';
		el.innerHTML =
			'<header class="sd-header"><h2 class="sd-title"></h2>' +
			'<button type="button" class="sd-icon-button sd-panel-close" aria-label="' + ( config.strings.close || 'Close' ) + '">✕</button></header>' +
			'<div class="sd-body"></div>';
		layer.appendChild( el );
		el.querySelector( '.sd-title' ).textContent = cubbyTitle( cubbyId );
		state.panels.push( { el: el, parent: parent, cubbyId: cubbyId } );
		restackPanels();
		fetchCubby( cubbyId, el.querySelector( '.sd-body' ), el );
		el.querySelector( '.sd-panel-close' ).addEventListener( 'click', function () {
			popPanel( el );
		} );
	}

	/** Close the topmost panel (or a specific one, cascade-closing children). */
	function popPanel( target ) {
		var idx = target ? state.panels.findIndex( function ( p ) { return p.el === target; } ) : state.panels.length - 1;
		if ( idx === -1 ) {
			return;
		}
		// Close everything above it too (children of the panel going away).
		while ( state.panels.length > idx ) {
			var panel = state.panels.pop();
			flushPanelNotes( panel );
			panel.el.remove();
		}
		restackPanels();
	}

	/** Close every panel (launcher closing / drawer close). */
	function closeAllPanels() {
		while ( state.panels.length ) {
			var panel = state.panels.pop();
			flushPanelNotes( panel );
			panel.el.remove();
		}
	}

	/** Flush a panel's pending notes save before its DOM goes away. */
	function flushPanelNotes( panel ) {
		if ( panel && typeof panel.flush === 'function' ) {
			panel.flush();
		}
	}

	/** Re-anchor the stack: each panel sits outboard of its parent. */
	function restackPanels() {
		var rect = launcherRect();
		state.panels.forEach( function ( panel ) {
			var left = panelLeftFor( rect );
			panel.el.style.left = left + 'px';
			panel.el.style.top = rect.top + 'px';
			panel.el.style.height = panelHeightFor( rect ) + 'px';
			// Track positions arithmetically — mid-animation rects lie.
			rect = { left: left, right: left + PANEL_WIDTH, top: rect.top, height: rect.height };
		} );
	}

	function cubbyTitle( id ) {
		var meta = ( config.cubbies || [] ).filter( function ( c ) { return c.id === id; } )[ 0 ];
		return meta ? meta.title : id;
	}

	function Drawer() {
		if ( 'settings' === state.view ) {
			return h( Settings );
		}

		var position = 'bottom' === config.position ? 'bottom' : 'right';
		var className = 'sd-drawer sd-drawer--' + position + ( state.open ? ' is-open' : '' );

		// Launcher body: a grid of cubby cards. Clicking pops the cubby
		// out as its own sidebar; the launcher itself stays put.
		var cards = ( config.cubbies || [] ).map( function ( cubby ) {
			return h( 'button', {
				key: cubby.id,
				type: 'button',
				className: 'sd-card',
				onClick: function () {
					openPanel( cubby.id, null );
				}
			},
				h( 'span', { className: 'sd-card-icon dashicons ' + ( cubby.icon || 'dashicons-marker' ), 'aria-hidden': 'true' } ),
				h( 'span', { className: 'sd-card-title' }, cubby.title )
			);
		} );

		var body = h( 'div', { className: 'sd-cubby-grid' },
			cards.length ? cards : h( 'p', { className: 'sd-muted sd-empty' }, 'This drawer is empty. Add something from the library.' )
		);

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

			// Pop-out panels live in their own layer (outside React) so the
			// delegation model works per-mount and panels survive re-renders.
			var layer = document.createElement( 'div' );
			layer.id = 'secret-drawer-panels';
			document.body.appendChild( layer );

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
		// Cubby list may have changed: collapse any open panels first.
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
		// Open the drawer (if needed) and pop the cubby out immediately.
		showCubby: function ( id ) {
			open();
			openPanel( id, null );
		}
	};

	// A fresh login (logout/login) starts with the drawer closed: the
	// server stamps every login with its own session token in the config.
	// Page loads *within* one session keep the drawer open, as before.
	// (Panel state is deliberately not persisted: a fresh open starts
	// at the launcher — the cascade is a choice, not a surprise.)
	if ( config.session ) {
		if ( lsGet( 'session' ) !== config.session ) {
			lsSet( 'open', false );
		}
		lsSet( 'session', config.session );
	}

	// Restore previous open state without the celebration.
	if ( lsGet( 'open' ) === '1' ) {
		state.open = true;
	}

	render();
} )();