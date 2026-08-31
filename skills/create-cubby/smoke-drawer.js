/**
 * Load-time smoke test for Secret Drawer JavaScript.
 *
 * Runs the real file in Node under a stub browser environment. A file can
 * pass `node --check` and still fail to LOAD — e.g. an edit that swallowed
 * a closing brace and nested half the file, ending in a stray `)`. This
 * harness executes drawer.js in full, so that class of breakage is caught
 * here instead of on a live admin screen.
 *
 * Usage:
 *   node skills/create-cubby/smoke-drawer.js [path/to/your-script.js]
 *
 * Default target: the plugin's assets/js/drawer.js. Pass another JS file
 * (e.g. the script your own drop-in cubby enqueues) to smoke it with the
 * same stubs. If your script needs globals the stub section lacks, edit
 * that section below — it's yours.
 *
 * Pass = "EXEC RESULT: <file> loads and runs clean" printed AND exit 0.
 * Any other output or exit code = fix the script.
 *
 * Note: some execution wrappers (CI runners, agent shells) log "completed
 * successfully" and exit 0 even when the child fails. The marker line is
 * the ground truth — it only prints if drawer.js fully executed.
 */
'use strict';

const path = require( 'path' );
const defaultTarget = path.join( __dirname, '..', '..', 'assets', 'js', 'drawer.js' );
const file = process.argv[ 2 ] ? path.resolve( process.argv[ 2 ] ) : defaultTarget;

const store = {};

global.window = {
	SECRET_DRAWER: { restRoot: '', nonce: 'x', session: 's1', strings: {}, cubbies: [], catalog: {}, packs: {} },
	wp: {
		element: { createElement: () => ( {} ), createRoot: () => ( { render: () => {} } ) },
		components: {},
	},
	location: { origin: 'http://localhost' },
	innerHeight: 800,
};

global.document = {
	dispatchEvent() {}, addEventListener() {}, querySelector: () => null, querySelectorAll: () => [],
	createElement: () => ( {
		style: {}, setAttribute() {}, addEventListener() {}, appendChild() {},
		classList: { add() {}, remove() {} },
	} ),
	body: { appendChild() {}, removeChild() {} },
	documentElement: { style: { setProperty() {} } },
};

window.localStorage = window.sessionStorage = {
	getItem: ( k ) => ( store[ k ] ?? null ),
	setItem: ( k, v ) => { store[ k ] = v; },
	removeItem: ( k ) => { delete store[ k ]; },
};
global.localStorage = window.localStorage;
global.sessionStorage = window.sessionStorage;
global.CustomEvent = function () {};
global.matchMedia = () => ( { matches: false } );
window.navigator = {};

// Node 21+ exposes `navigator` on globalThis as a getter-only property;
// assignment throws there, so go through defineProperty when possible.
try {
	Object.defineProperty( global, 'navigator', { value: window.navigator, configurable: true, writable: true } );
} catch ( e ) {
	// Irreplacable global — fine; drawer.js only reads navigator inside
	// event handlers, never at load time, so the load test is unaffected.
}

try {
	require( file );
	console.log( `EXEC RESULT: ${ path.basename( file ) } loads and runs clean` );
} catch ( e ) {
	console.error( 'LOAD ERROR:', e.message );
	process.exitCode = 1;
}