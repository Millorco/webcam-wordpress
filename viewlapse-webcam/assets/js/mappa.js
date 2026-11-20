/**
 * Viewlapse Webcam: mappa delle webcam con Leaflet (incluso nel plugin,
 * caricato solo dai blocchi con la mappa attiva).
 */
( function () {
	'use strict';

	function esc( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function init() {
		if ( ! window.L ) {
			return;
		}
		var base = ( document.querySelector( 'link#viewlapse-leaflet-css' ) || {} ).href || '';
		if ( base ) {
			window.L.Icon.Default.imagePath = base.replace( /leaflet\.css.*$/, 'images/' );
		}
		document.querySelectorAll( '.vl-map[data-vl-map]' ).forEach( function ( el ) {
			if ( el.dataset.vlReady ) {
				return;
			}
			el.dataset.vlReady = '1';
			var points = [];
			try {
				points = JSON.parse( el.getAttribute( 'data-vl-map' ) ) || [];
			} catch ( e ) {
				return;
			}
			if ( ! points.length ) {
				return;
			}
			var map = window.L.map( el, { scrollWheelZoom: false } );
			window.L.tileLayer( el.getAttribute( 'data-vl-tiles' ), {
				maxZoom: 18,
				attribution: el.getAttribute( 'data-vl-attribution' ) || '',
			} ).addTo( map );
			var bounds = [];
			points.forEach( function ( p ) {
				var html = '<div class="vl-map__popup">';
				if ( p.img ) {
					html += '<img src="' + esc( p.img ) + '" alt="" loading="lazy">';
				}
				html += '<strong>' + ( p.url ? '<a href="' + esc( p.url ) + '">' + esc( p.name ) + '</a>' : esc( p.name ) ) + '</strong>';
				if ( p.place ) {
					html += '<br>' + esc( p.place );
				}
				html += '<br><small>' + esc( p.state ) + '</small></div>';
				window.L.marker( [ p.lat, p.lon ], { title: p.name, alt: p.name } ).addTo( map ).bindPopup( html );
				bounds.push( [ p.lat, p.lon ] );
			} );
			if ( bounds.length === 1 ) {
				map.setView( bounds[ 0 ], 11 );
			} else {
				map.fitBounds( bounds, { padding: [ 30, 30 ], maxZoom: 12 } );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
