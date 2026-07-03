/**
 * Image Alt Checker - batch scan controller.
 *
 * Vanilla JavaScript, no dependencies. Drives the secure AJAX scanner: start,
 * continue (loop), cancel and clear cache. Updates the progress bar and the
 * live statistics as each batch completes.
 */
( function () {
	'use strict';

	var config = window.iacScan || null;

	if ( ! config ) {
		return;
	}

	var scanner = document.querySelector( '[data-iac-scanner]' );

	if ( ! scanner ) {
		return;
	}

	var startBtn = scanner.querySelector( '[data-iac-start]' );
	var cancelBtn = scanner.querySelector( '[data-iac-cancel]' );
	var clearBtn = scanner.querySelector( '[data-iac-clear]' );
	var bar = scanner.querySelector( '[data-iac-progressbar]' );
	var fill = scanner.querySelector( '[data-iac-progress-fill]' );
	var progressText = scanner.querySelector( '[data-iac-progress-text]' );
	var statusEl = scanner.querySelector( '[data-iac-status]' );
	var statsRoot = document.querySelector( '[data-iac-stats]' );

	var running = false;

	function text( key, fallback ) {
		return ( config.i18n && config.i18n[ key ] ) || fallback || '';
	}

	function setStatus( message ) {
		if ( statusEl ) {
			statusEl.textContent = message;
		}
	}

	function formatNumber( value ) {
		var number = Number( value ) || 0;
		try {
			return number.toLocaleString();
		} catch ( e ) {
			return String( number );
		}
	}

	function setProgress( percent ) {
		var value = Math.max( 0, Math.min( 100, Math.round( Number( percent ) || 0 ) ) );

		if ( fill ) {
			fill.style.width = value + '%';
		}
		if ( bar ) {
			bar.setAttribute( 'aria-valuenow', String( value ) );
		}
		if ( progressText ) {
			progressText.textContent = value + '%';
		}
	}

	function updateStats( stats ) {
		if ( ! stats || ! statsRoot ) {
			return;
		}

		var nodes = statsRoot.querySelectorAll( '[data-iac-stat]' );

		nodes.forEach( function ( node ) {
			var key = node.getAttribute( 'data-iac-stat' );

			if ( key && Object.prototype.hasOwnProperty.call( stats, key ) && typeof stats[ key ] !== 'object' ) {
				node.textContent = formatNumber( stats[ key ] );
			}
		} );
	}

	function request( action ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json();
		} );
	}

	function toggleControls( isRunning ) {
		running = isRunning;

		if ( startBtn ) {
			startBtn.disabled = isRunning || scanner.getAttribute( 'data-iac-can-scan' ) !== '1';
		}
		if ( cancelBtn ) {
			cancelBtn.hidden = ! isRunning;
		}
	}

	function applyProgress( data ) {
		if ( ! data ) {
			return;
		}

		setProgress( data.percent );
		updateStats( data.stats );

		if ( typeof data.processed_posts !== 'undefined' && typeof data.total_posts !== 'undefined' ) {
			setStatus(
				text( 'scanning', 'Scanning…' ) +
				' ' + formatNumber( data.processed_posts ) + ' / ' + formatNumber( data.total_posts ) +
				' ' + text( 'posts', 'posts' )
			);
		}
	}

	function finish( message ) {
		toggleControls( false );
		setStatus( message );
	}

	function loop() {
		if ( ! running ) {
			return;
		}

		request( config.actions.continue ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				finish( ( json && json.data && json.data.message ) || text( 'error', 'Error' ) );
				return;
			}

			var data = json.data;
			applyProgress( data );

			if ( data.status === 'complete' ) {
				setProgress( 100 );
				updateStats( data.stats );
				finish( text( 'complete', 'Scan complete.' ) );
				return;
			}

			if ( data.status !== 'running' ) {
				finish( text( 'cancelled', 'Scan cancelled.' ) );
				return;
			}

			loop();
		} ).catch( function () {
			finish( text( 'error', 'Error' ) );
		} );
	}

	function start() {
		if ( running ) {
			return;
		}

		toggleControls( true );
		setStatus( text( 'starting', 'Starting…' ) );
		setProgress( 0 );

		request( config.actions.start ).then( function ( json ) {
			if ( ! json || ! json.success ) {
				finish( ( json && json.data && json.data.message ) || text( 'error', 'Error' ) );
				return;
			}

			applyProgress( json.data );
			loop();
		} ).catch( function () {
			finish( text( 'error', 'Error' ) );
		} );
	}

	function cancel() {
		if ( ! running ) {
			return;
		}

		running = false;

		request( config.actions.cancel ).then( function () {
			finish( text( 'cancelled', 'Scan cancelled.' ) );
			setProgress( 0 );
		} ).catch( function () {
			finish( text( 'cancelled', 'Scan cancelled.' ) );
		} );
	}

	function clearCache() {
		request( config.actions.clear ).then( function ( json ) {
			if ( json && json.success ) {
				setStatus( ( json.data && json.data.message ) || text( 'cacheClear', 'Cache cleared.' ) );
			}
		} ).catch( function () {
			setStatus( text( 'error', 'Error' ) );
		} );
	}

	if ( startBtn ) {
		startBtn.addEventListener( 'click', start );
	}
	if ( cancelBtn ) {
		cancelBtn.addEventListener( 'click', cancel );
	}
	if ( clearBtn ) {
		clearBtn.addEventListener( 'click', clearCache );
	}

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( running ) {
			event.preventDefault();
			event.returnValue = text( 'cleaveWarn', '' );
			return event.returnValue;
		}
	} );

	// Resume an in-progress scan, or auto-start when configured.
	if ( scanner.getAttribute( 'data-iac-active' ) === '1' ) {
		toggleControls( true );
		setStatus( text( 'scanning', 'Scanning…' ) );
		loop();
	} else if ( config.autoScan && scanner.getAttribute( 'data-iac-can-scan' ) === '1' ) {
		start();
	}
}() );
