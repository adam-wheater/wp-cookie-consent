/* Simple Cookie Consent - no dependencies, no third-party requests. */
( function () {
	'use strict';

	var cfg = window.SCC || {};
	var NAME = 'scc_consent';
	var DAYS = cfg.days || 30;
	var box = null;

	function read() {
		var m = document.cookie.match( '(?:^|; *)' + NAME + '=([^;]*)' );
		return m ? decodeURIComponent( m[1] ) : '';
	}

	function write( value ) {
		var expires = new Date( Date.now() + DAYS * 864e5 ).toUTCString();
		document.cookie = NAME + '=' + value + ';expires=' + expires + ';path=/;SameSite=Lax' +
			( location.protocol === 'https:' ? ';Secure' : '' );
	}

	/* gtag() is `dataLayer.push(arguments)` - the arguments object, not an array. */
	function gtag() {
		window.dataLayer = window.dataLayer || [];
		window.dataLayer.push( arguments );
	}

	function consentUpdate( granted ) {
		if ( ! cfg.consentMode ) {
			return;
		}
		var v = granted ? 'granted' : 'denied';
		gtag( 'consent', 'update', {
			ad_storage: v,
			ad_user_data: v,
			ad_personalization: v,
			analytics_storage: v
		} );
	}

	/* Delete the tracking cookies that are already on the device. */
	function wipe() {
		var kill = /^(_ga|_gid|_gat|_gcl|_fbp|_fbc|__utm|_hj|IDE|test_cookie|NID|__gads|__gpi)/;
		var host = location.hostname;
		var bare = host.replace( /^www\./, '' );
		var domains = [ '', host, '.' + host, bare, '.' + bare ];
		var paths = [ '/', location.pathname ];

		document.cookie.split( ';' ).forEach( function ( pair ) {
			var name = pair.split( '=' )[0].trim();
			if ( ! name || ! kill.test( name ) ) {
				return;
			}
			paths.forEach( function ( path ) {
				domains.forEach( function ( domain ) {
					document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=' + path +
						( domain ? ';domain=' + domain : '' );
				} );
			} );
		} );
	}

	function close() {
		if ( box ) {
			box.remove();
			box = null;
		}
	}

	function decide( value ) {
		write( value );
		consentUpdate( value === 'allow' );
		if ( value === 'deny' ) {
			wipe();
		}
		close();
	}

	function button( label, className, onClick ) {
		var el = document.createElement( 'button' );
		el.type = 'button';
		el.className = className;
		el.textContent = label;
		el.addEventListener( 'click', onClick );
		return el;
	}

	function open() {
		if ( box ) {
			return;
		}

		box = document.createElement( 'div' );
		box.className = 'scc scc--' + ( cfg.position || 'bottom-right' );
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-label', 'Cookie consent' );

		var text = document.createElement( 'p' );
		text.className = 'scc__text';
		text.textContent = cfg.message || '';

		if ( cfg.policyUrl ) {
			var link = document.createElement( 'a' );
			link.className = 'scc__link';
			link.href = cfg.policyUrl;
			link.textContent = cfg.policyLabel || 'Privacy policy';
			text.appendChild( document.createTextNode( ' ' ) );
			text.appendChild( link );
		}

		var row = document.createElement( 'div' );
		row.className = 'scc__actions';
		row.appendChild( button( cfg.decline || 'Decline', 'scc__btn scc__btn--decline', function () {
			decide( 'deny' );
		} ) );
		row.appendChild( button( cfg.accept || 'Accept', 'scc__btn scc__btn--accept', function () {
			decide( 'allow' );
		} ) );

		box.appendChild( text );
		box.appendChild( row );
		document.body.appendChild( box );
	}

	window.sccOpen = open;
	window.sccConsent = read;

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target || ! e.target.closest ) {
			return;
		}
		var trigger = e.target.closest( '.scc-open, a[href="#cookie-settings"]' );
		if ( trigger ) {
			e.preventDefault();
			open();
		}
	} );

	if ( ! read() ) {
		open();
	}
} )();
