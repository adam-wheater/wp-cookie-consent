/* Runnable check for the plugin's JavaScript, on a stub DOM.
 * Covers the parts that fail silently in a browser: when the banner shows,
 * what the cookie says, and that Decline actually deletes tracking cookies.
 *
 *   node tools/check.js
 */
'use strict';

const assert = require( 'assert' );
const fs = require( 'fs' );
const vm = require( 'vm' );

const source = fs.readFileSync( __dirname + '/../simple-cookie-consent/assets/consent.js', 'utf8' );

function makeNode( tag ) {
	return {
		tag,
		className: '',
		textContent: '',
		type: '',
		href: '',
		children: [],
		attrs: {},
		handlers: {},
		removed: false,
		setAttribute( k, v ) { this.attrs[ k ] = v; },
		appendChild( c ) { c.parent = this; this.children.push( c ); return c; },
		addEventListener( ev, fn ) { this.handlers[ ev ] = fn; },
		remove() { this.removed = true; if ( this.parent ) { this.parent.children = this.parent.children.filter( n => n !== this ); } },
		closest() { return null; }
	};
}

/* Build a page with the given cookie jar, run the plugin in it. */
function page( jar, cfg ) {
	const cookies = Object.assign( {}, jar );
	const body = makeNode( 'body' );
	const listeners = {};

	const document = {
		body,
		createElement: makeNode,
		createTextNode: ( t ) => ( { text: t } ),
		addEventListener: ( ev, fn ) => { listeners[ ev ] = fn; },
		get cookie() {
			return Object.keys( cookies ).map( k => k + '=' + cookies[ k ] ).join( '; ' );
		},
		set cookie( str ) {
			const parts = str.split( ';' );
			const [ name, value ] = parts[ 0 ].split( '=' );
			const expires = ( parts.find( p => /^\s*expires=/i.test( p ) ) || '' ).split( '=' )[ 1 ];
			if ( expires && new Date( expires ) < new Date() ) {
				delete cookies[ name.trim() ];
			} else {
				cookies[ name.trim() ] = value;
			}
		}
	};

	const sandbox = {
		document,
		location: { hostname: 'www.example.test', protocol: 'https:', pathname: '/page/' },
		Date,
		window: {}
	};
	sandbox.window = sandbox;
	sandbox.SCC = Object.assign( { message: 'hi', accept: 'Accept', decline: 'Decline', days: 30, position: 'bottom-right', consentMode: 1 }, cfg );

	vm.createContext( sandbox );
	vm.runInContext( source, sandbox );

	return { sandbox, cookies, body, listeners };
}

function banner( body ) {
	return body.children.find( n => n.className && n.className.indexOf( 'scc ' ) === 0 );
}

function buttons( box ) {
	return box.children.find( n => n.className === 'scc__actions' ).children;
}

/* 1. No cookie -> banner opens, in the configured corner, labelled. */
{
	const { body } = page( {}, { position: 'top-left' } );
	const box = banner( body );
	assert.ok( box, 'banner should open when no consent cookie is set' );
	assert.ok( box.className.includes( 'scc--top-left' ), 'banner uses the configured corner' );
	assert.strictEqual( box.attrs.role, 'dialog' );
	assert.deepStrictEqual( buttons( box ).map( b => b.textContent ), [ 'Decline', 'Accept' ] );
}

/* 2. Existing cookie -> no banner, and it can be reopened on demand. */
{
	const { body, sandbox } = page( { scc_consent: 'allow' } );
	assert.strictEqual( banner( body ), undefined, 'no banner once a choice is stored' );
	sandbox.window.sccOpen();
	assert.ok( banner( body ), 'sccOpen() reopens it' );
}

/* 3. Accept -> cookie, Consent Mode granted, banner gone, tracking left alone. */
{
	const { body, cookies, sandbox } = page( { _ga: 'GA1.1.99' } );
	buttons( banner( body ) )[ 1 ].handlers.click();
	assert.strictEqual( cookies.scc_consent, 'allow' );
	assert.strictEqual( cookies._ga, 'GA1.1.99', 'accepting keeps existing analytics cookies' );
	assert.strictEqual( banner( body ), undefined, 'banner closes after a choice' );
	const update = sandbox.dataLayer.map( a => Array.from( a ) ).find( a => a[ 1 ] === 'update' );
	assert.deepStrictEqual( update[ 2 ].ad_storage, 'granted' );
	assert.deepStrictEqual( update[ 2 ].analytics_storage, 'granted' );
}

/* 4. Decline -> cookie, Consent Mode denied, and the tracking cookies are wiped. */
{
	const { body, cookies, sandbox } = page( { _ga: 'GA1.1.99', _gid: 'x', _fbp: 'y', wordpress_logged_in: 'keep', woocommerce_cart_hash: 'keep' } );
	buttons( banner( body ) )[ 0 ].handlers.click();
	assert.strictEqual( cookies.scc_consent, 'deny' );
	assert.strictEqual( cookies._ga, undefined, 'declining deletes _ga' );
	assert.strictEqual( cookies._gid, undefined, 'declining deletes _gid' );
	assert.strictEqual( cookies._fbp, undefined, 'declining deletes _fbp' );
	assert.strictEqual( cookies.wordpress_logged_in, 'keep', 'declining must not touch the login cookie' );
	assert.strictEqual( cookies.woocommerce_cart_hash, 'keep', 'declining must not touch the cart' );
	const update = sandbox.dataLayer.map( a => Array.from( a ) ).find( a => a[ 1 ] === 'update' );
	assert.strictEqual( update[ 2 ].ad_storage, 'denied' );
}

/* 5. Consent Mode off -> nothing pushed to dataLayer. */
{
	const { body, sandbox } = page( {}, { consentMode: 0 } );
	buttons( banner( body ) )[ 1 ].handlers.click();
	assert.strictEqual( sandbox.dataLayer, undefined, 'consent mode off pushes nothing' );
}

console.log( 'all checks passed' );
