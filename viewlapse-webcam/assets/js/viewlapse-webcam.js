/**
 * Viewlapse Webcam: frontend senza dipendenze.
 * - aggiornamento automatico della foto attuale;
 * - cambio di giorno, mese, webcam e date senza ricaricare la pagina
 *   (i link e i moduli funzionano anche senza JavaScript);
 * - visualizzatore a schermo intero con frecce, tastiera e scorrimento;
 * - cursore del prima/dopo.
 */
( function () {
	'use strict';

	var cfg = window.VLWebcam || {};
	var t = cfg.i18n || {};
	var PARAMS = [ 'vl_cam', 'vl_date', 'vl_month', 'vl_a', 'vl_b', 'vl_giorni' ];

	document.documentElement.classList.add( 'js-vl' );

	function request( block, params ) {
		if ( ! cfg.rest ) {
			return Promise.reject( new Error( 'rest' ) );
		}
		var url = new URL( cfg.rest, window.location.href );
		url.searchParams.set( 'ctx', block.getAttribute( 'data-vl-ctx' ) );
		url.searchParams.set( 'id', block.id );
		Object.keys( params ).forEach( function ( k ) {
			if ( PARAMS.indexOf( k ) !== -1 && params[ k ] !== '' && params[ k ] != null ) {
				url.searchParams.set( k, params[ k ] );
			}
		} );
		var headers = { Accept: 'application/json' };
		if ( cfg.nonce ) {
			headers[ 'X-WP-Nonce' ] = cfg.nonce;
		}
		return fetch( url.toString(), { credentials: 'same-origin', headers: headers } ).then( function ( r ) {
			if ( ! r.ok ) {
				throw new Error( 'HTTP ' + r.status );
			}
			return r.json();
		} );
	}

	function paramsFrom( search ) {
		var out = {};
		new URLSearchParams( search ).forEach( function ( v, k ) {
			if ( PARAMS.indexOf( k ) !== -1 ) {
				out[ k ] = v;
			}
		} );
		return out;
	}

	function currentParams() {
		return paramsFrom( window.location.search );
	}

	function updateUrl( params, replaceKeys ) {
		var url = new URL( window.location.href );
		( replaceKeys || PARAMS ).forEach( function ( k ) {
			url.searchParams.delete( k );
		} );
		Object.keys( params ).forEach( function ( k ) {
			if ( params[ k ] ) {
				url.searchParams.set( k, params[ k ] );
			}
		} );
		url.hash = '';
		window.history.replaceState( null, '', url.toString() );
	}

	function load( block, params, keys ) {
		block.classList.add( 'is-loading' );
		block.setAttribute( 'aria-busy', 'true' );
		return request( block, params )
			.then( function ( data ) {
				block.innerHTML = data.html;
				updateUrl( params, keys );
				setup( block );
			} )
			.catch( function () {
				// Senza REST si ricarica la pagina con i parametri: funziona sempre.
				var url = new URL( window.location.href );
				( keys || PARAMS ).forEach( function ( k ) {
					url.searchParams.delete( k );
				} );
				Object.keys( params ).forEach( function ( k ) {
					url.searchParams.set( k, params[ k ] );
				} );
				url.hash = block.id;
				window.location.href = url.toString();
			} )
			.finally( function () {
				block.classList.remove( 'is-loading' );
				block.removeAttribute( 'aria-busy' );
			} );
	}

	// ------------------------------------------------------------ navigazione

	function onClick( e ) {
		var a = e.target.closest( 'a[data-vl-nav]' );
		var block = a && a.closest( '[data-vl-ctx]' );
		if ( ! block || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0 ) {
			return;
		}
		e.preventDefault();
		var url = new URL( a.href, window.location.href );
		load( block, paramsFrom( url.search ), keysOf( block ) );
	}

	function onSubmit( e ) {
		var form = e.target.closest( 'form[data-vl-nav]' );
		var block = form && form.closest( '[data-vl-ctx]' );
		if ( ! block ) {
			return;
		}
		e.preventDefault();
		var params = {};
		new FormData( form ).forEach( function ( v, k ) {
			params[ k ] = String( v );
		} );
		if ( form.classList.contains( 'vl-select' ) ) {
			// Cambio di webcam: si riparte dal giorno più recente.
			load( block, params, keysOf( block ) );
			return;
		}
		var keep = currentParams();
		if ( keep.vl_cam && ! params.vl_cam ) {
			params.vl_cam = keep.vl_cam;
		}
		load( block, params, keysOf( block ) );
	}

	function onChange( e ) {
		if ( e.target.matches( '.vl-select select' ) ) {
			e.target.form.requestSubmit ? e.target.form.requestSubmit() : e.target.form.submit();
		}
	}

	/** Parametri dell'URL che il blocco usa (gli altri restano). */
	function keysOf( block ) {
		switch ( block.getAttribute( 'data-vl-kind' ) ) {
			case 'archivio':
				return [ 'vl_cam', 'vl_date', 'vl_month' ];
			case 'confronto':
				return [ 'vl_cam', 'vl_a', 'vl_b' ];
			case 'grafico':
				return [ 'vl_cam', 'vl_giorni' ];
			default:
				return [ 'vl_cam' ];
		}
	}

	// ------------------------------------------------------------ foto attuale

	function startRefresh( block ) {
		var min = parseInt( block.getAttribute( 'data-vl-refresh' ), 10 );
		if ( ! min || min < 1 ) {
			return;
		}
		var every = min * 60000;
		var last = Date.now();
		function tick() {
			if ( document.hidden ) {
				return;
			}
			last = Date.now();
			var params = {};
			var sel = block.querySelector( '.vl-select select' );
			if ( sel ) {
				params.vl_cam = sel.value;
			}
			request( block, params )
				.then( function ( data ) {
					var tmp = document.createElement( 'div' );
					tmp.innerHTML = data.html;
					var oldFig = block.querySelector( '[data-vl-sig]' );
					var newFig = tmp.querySelector( '[data-vl-sig]' );
					if ( oldFig && newFig && oldFig.getAttribute( 'data-vl-sig' ) === newFig.getAttribute( 'data-vl-sig' ) ) {
						return;
					}
					var img = tmp.querySelector( 'img' );
					var swap = function () {
						block.innerHTML = data.html;
					};
					if ( ! img ) {
						swap();
						return;
					}
					// Si scarica la foto nuova prima di sostituirla: nessuno sfarfallio.
					var pre = new Image();
					if ( img.getAttribute( 'sizes' ) ) {
						pre.sizes = img.getAttribute( 'sizes' );
					}
					if ( img.getAttribute( 'srcset' ) ) {
						pre.srcset = img.getAttribute( 'srcset' );
					}
					pre.src = img.getAttribute( 'src' );
					( pre.decode ? pre.decode() : Promise.resolve() ).then( swap, swap );
				} )
				.catch( function () {} );
		}
		setInterval( tick, every );
		document.addEventListener( 'visibilitychange', function () {
			if ( ! document.hidden && Date.now() - last >= every ) {
				tick();
			}
		} );
	}

	// ------------------------------------------------------------ prima e dopo

	function setupCompare( root ) {
		root.querySelectorAll( '.vl-cmp' ).forEach( function ( box ) {
			var range = box.querySelector( '.vl-cmp__range' );
			if ( ! range || range.dataset.vlReady ) {
				return;
			}
			range.dataset.vlReady = '1';
			var set = function () {
				box.style.setProperty( '--vl-pos', range.value + '%' );
			};
			range.addEventListener( 'input', set );
			set();
		} );
	}

	// ------------------------------------------------------------ visualizzatore

	var lb = null;
	var lbItems = [];
	var lbIndex = 0;
	var lbOpener = null;

	function buildLightbox() {
		lb = document.createElement( 'div' );
		lb.className = 'vl-lightbox';
		lb.hidden = true;
		lb.setAttribute( 'role', 'dialog' );
		lb.setAttribute( 'aria-modal', 'true' );
		lb.innerHTML =
			'<div class="vl-lightbox__stage"><img class="vl-lightbox__img" alt=""></div>' +
			'<div class="vl-lightbox__bar"><span class="vl-lightbox__caption"></span><span class="vl-lightbox__pos"></span><a class="vl-lightbox__orig" target="_blank" rel="noopener" hidden></a></div>' +
			'<button type="button" class="vl-lightbox__prev">&lsaquo;</button>' +
			'<button type="button" class="vl-lightbox__next">&rsaquo;</button>' +
			'<button type="button" class="vl-lightbox__close">&times;</button>';
		lb.querySelector( '.vl-lightbox__prev' ).setAttribute( 'aria-label', t.prev || 'Precedente' );
		lb.querySelector( '.vl-lightbox__next' ).setAttribute( 'aria-label', t.next || 'Successiva' );
		lb.querySelector( '.vl-lightbox__close' ).setAttribute( 'aria-label', t.close || 'Chiudi' );
		lb.querySelector( '.vl-lightbox__orig' ).textContent = t.download || 'Originale';
		lb.querySelector( '.vl-lightbox__prev' ).addEventListener( 'click', function () {
			show( lbIndex - 1 );
		} );
		lb.querySelector( '.vl-lightbox__next' ).addEventListener( 'click', function () {
			show( lbIndex + 1 );
		} );
		lb.querySelector( '.vl-lightbox__close' ).addEventListener( 'click', close );
		lb.addEventListener( 'click', function ( e ) {
			if ( e.target === lb || e.target.classList.contains( 'vl-lightbox__stage' ) ) {
				close();
			}
		} );
		lb.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				close();
			} else if ( e.key === 'ArrowLeft' ) {
				show( lbIndex - 1 );
			} else if ( e.key === 'ArrowRight' ) {
				show( lbIndex + 1 );
			} else if ( e.key === 'Tab' ) {
				var f = Array.prototype.slice.call( lb.querySelectorAll( 'button, a:not([hidden])' ) );
				var i = f.indexOf( document.activeElement );
				if ( e.shiftKey && i <= 0 ) {
					f[ f.length - 1 ].focus();
					e.preventDefault();
				} else if ( ! e.shiftKey && i === f.length - 1 ) {
					f[ 0 ].focus();
					e.preventDefault();
				}
			}
		} );
		var x0 = null;
		lb.addEventListener( 'touchstart', function ( e ) {
			x0 = e.touches[ 0 ].clientX;
		}, { passive: true } );
		lb.addEventListener( 'touchend', function ( e ) {
			if ( x0 === null ) {
				return;
			}
			var dx = e.changedTouches[ 0 ].clientX - x0;
			x0 = null;
			if ( Math.abs( dx ) > 50 ) {
				show( lbIndex + ( dx < 0 ? 1 : -1 ) );
			}
		}, { passive: true } );
		document.body.appendChild( lb );
	}

	function show( i ) {
		if ( ! lbItems.length ) {
			return;
		}
		lbIndex = ( i + lbItems.length ) % lbItems.length;
		var a = lbItems[ lbIndex ];
		var img = lb.querySelector( '.vl-lightbox__img' );
		img.removeAttribute( 'srcset' );
		img.src = a.href;
		if ( a.dataset.vlSrcset ) {
			img.sizes = '100vw';
			img.srcset = a.dataset.vlSrcset;
		}
		var inner = a.querySelector( 'img' );
		img.alt = inner ? inner.alt : '';
		lb.querySelector( '.vl-lightbox__caption' ).textContent = a.dataset.vlCaption || '';
		lb.querySelector( '.vl-lightbox__pos' ).textContent = ( lbIndex + 1 ) + ' / ' + lbItems.length;
		var orig = lb.querySelector( '.vl-lightbox__orig' );
		orig.hidden = ! a.dataset.vlOrig;
		orig.href = a.dataset.vlOrig || '#';
		lb.setAttribute( 'aria-label', a.dataset.vlCaption || '' );
		// Precarica le vicine.
		[ lbIndex - 1, lbIndex + 1 ].forEach( function ( j ) {
			var n = lbItems[ ( j + lbItems.length ) % lbItems.length ];
			if ( n ) {
				var p = new Image();
				p.sizes = '100vw';
				p.srcset = n.dataset.vlSrcset || '';
				p.src = n.href;
			}
		} );
	}

	function open( a ) {
		if ( ! lb ) {
			buildLightbox();
		}
		var gallery = a.closest( '[data-vl-gallery]' );
		lbItems = Array.prototype.slice.call( gallery.querySelectorAll( 'a.vl-thumb' ) );
		lbOpener = a;
		lb.hidden = false;
		document.documentElement.classList.add( 'vl-noscroll' );
		show( lbItems.indexOf( a ) );
		lb.querySelector( '.vl-lightbox__close' ).focus();
	}

	function close() {
		lb.hidden = true;
		document.documentElement.classList.remove( 'vl-noscroll' );
		if ( lbOpener ) {
			lbOpener.focus();
		}
	}

	function onThumb( e ) {
		var a = e.target.closest( '[data-vl-gallery] a.vl-thumb' );
		if ( ! a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0 ) {
			return;
		}
		e.preventDefault();
		open( a );
	}

	// ------------------------------------------------------------ avvio

	function setup( root ) {
		setupCompare( root );
	}

	function init() {
		document.addEventListener( 'click', onThumb );
		document.addEventListener( 'click', onClick );
		document.addEventListener( 'submit', onSubmit );
		document.addEventListener( 'change', onChange );
		document.querySelectorAll( '[data-vl-ctx]' ).forEach( function ( block ) {
			setup( block );
			if ( block.getAttribute( 'data-vl-kind' ) === 'foto' ) {
				startRefresh( block );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
