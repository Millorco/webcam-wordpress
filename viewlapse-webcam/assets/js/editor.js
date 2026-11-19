/**
 * Viewlapse Webcam: editor dei blocchi, senza build (wp.* globali).
 * I blocchi sono dinamici: l'anteprima è l'HTML del server (ServerSideRender).
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	var SSR = wp.serverSideRender;

	var camsPromise = null;
	function loadCams() {
		if ( ! camsPromise ) {
			camsPromise = wp.apiFetch( { path: '/viewlapse/v1/webcams' } ).catch( function () {
				return { webcams: [] };
			} );
		}
		return camsPromise;
	}

	function WebcamSelect( props ) {
		var st = useState( null );
		var data = st[ 0 ];
		var setData = st[ 1 ];
		useEffect( function () {
			loadCams().then( setData );
		}, [] );
		var options = [ { value: '', label: __( 'Prima disponibile', 'viewlapse-webcam' ) } ];
		( ( data && data.webcams ) || [] ).forEach( function ( w ) {
			options.push( { value: w.slug, label: w.name + ( w.public ? '' : ' ' + __( '(privata)', 'viewlapse-webcam' ) ) } );
		} );
		if ( props.value && ! options.some( function ( o ) { return o.value === props.value; } ) ) {
			options.push( { value: props.value, label: props.value } );
		}
		return el( c.SelectControl, {
			label: __( 'Webcam', 'viewlapse-webcam' ),
			value: props.value,
			options: options,
			onChange: props.onChange,
			help: data && data.error ? data.error : __( 'Le webcam visibili con la chiave del sito.', 'viewlapse-webcam' ),
		} );
	}

	var SIZE_OPTIONS = [
		{ value: 640, label: '640 px' },
		{ value: 1280, label: '1280 px' },
		{ value: 1920, label: '1920 px' },
	];

	// Controlli di ogni blocco: [attributo, tipo, etichetta, opzioni].
	var CONTROLS = {
		foto: [
			[ 'webcam', 'webcam' ],
			[ 'scelta', 'toggle', __( 'Il visitatore sceglie la webcam', 'viewlapse-webcam' ) ],
			[ 'aggiorna', 'number', __( 'Aggiorna ogni (minuti, -1 = impostazione del sito, 0 = mai)', 'viewlapse-webcam' ), { min: -1, max: 1440 } ],
			[ 'dimensione', 'select', __( 'Dimensione massima', 'viewlapse-webcam' ), SIZE_OPTIONS ],
			[ 'titolo', 'toggle', __( 'Nome e località', 'viewlapse-webcam' ) ],
			[ 'ora', 'toggle', __( 'Data e ora della foto', 'viewlapse-webcam' ) ],
			[ 'meteo', 'toggle', __( 'Temperatura e umidità esterne', 'viewlapse-webcam' ) ],
			[ 'stato', 'toggle', __( 'Stato in linea / non in linea', 'viewlapse-webcam' ) ],
		],
		archivio: [
			[ 'webcam', 'webcam' ],
			[ 'scelta', 'toggle', __( 'Il visitatore sceglie la webcam', 'viewlapse-webcam' ) ],
			[ 'colonne', 'range', __( 'Colonne della griglia', 'viewlapse-webcam' ), { min: 1, max: 8 } ],
			[ 'ogni', 'number', __( 'Una foto ogni (minuti, 0 = tutte)', 'viewlapse-webcam' ), { min: 0, max: 1440 } ],
		],
		confronto: [
			[ 'webcam', 'webcam' ],
			[ 'scelta', 'toggle', __( 'Il visitatore sceglie la webcam', 'viewlapse-webcam' ) ],
			[ 'a', 'text', __( 'Prima (AAAA-MM-GG o AAAA-MM-GGTHH:MM, vuoto = primo giorno)', 'viewlapse-webcam' ) ],
			[ 'b', 'text', __( 'Dopo (vuoto = adesso)', 'viewlapse-webcam' ) ],
			[ 'scegli', 'toggle', __( 'Il visitatore sceglie le date', 'viewlapse-webcam' ) ],
			[ 'dimensione', 'select', __( 'Dimensione massima', 'viewlapse-webcam' ), SIZE_OPTIONS ],
		],
		grafico: [
			[ 'webcam', 'webcam' ],
			[ 'scelta', 'toggle', __( 'Il visitatore sceglie la webcam', 'viewlapse-webcam' ) ],
			[ 'giorni', 'number', __( 'Giorni mostrati', 'viewlapse-webcam' ), { min: 1, max: 400 } ],
			[ 'campi', 'select', __( 'Misure', 'viewlapse-webcam' ), [
				{ value: 'temperatura,umidita', label: __( 'Temperatura e umidità', 'viewlapse-webcam' ) },
				{ value: 'temperatura', label: __( 'Solo temperatura', 'viewlapse-webcam' ) },
				{ value: 'umidita', label: __( 'Solo umidità', 'viewlapse-webcam' ) },
			] ],
			[ 'scegli', 'toggle', __( 'Il visitatore sceglie il periodo', 'viewlapse-webcam' ) ],
			[ 'altezza', 'range', __( 'Altezza', 'viewlapse-webcam' ), { min: 160, max: 600 } ],
		],
		elenco: [
			[ 'foto', 'toggle', __( 'Mostra l\'ultima foto', 'viewlapse-webcam' ) ],
			[ 'colonne', 'range', __( 'Colonne', 'viewlapse-webcam' ), { min: 1, max: 6 } ],
			[ 'link', 'text', __( 'Link di ogni webcam ({slug} = nome della webcam), es. /webcam/?vl_cam={slug}', 'viewlapse-webcam' ) ],
			[ 'mappa', 'toggle', __( 'Mostra la mappa (Leaflet)', 'viewlapse-webcam' ) ],
			[ 'altezza', 'range', __( 'Altezza della mappa', 'viewlapse-webcam' ), { min: 200, max: 900 } ],
		],
	};

	function control( spec, attrs, set ) {
		var name = spec[ 0 ];
		var type = spec[ 1 ];
		var label = spec[ 2 ];
		var opt = spec[ 3 ] || {};
		var onChange = function ( v ) {
			var o = {};
			o[ name ] = v;
			set( o );
		};
		switch ( type ) {
			case 'webcam':
				return el( WebcamSelect, { key: name, value: attrs[ name ], onChange: onChange } );
			case 'toggle':
				return el( c.ToggleControl, { key: name, label: label, checked: !! attrs[ name ], onChange: onChange, __nextHasNoMarginBottom: true } );
			case 'number':
				return el( c.TextControl, { key: name, type: 'number', label: label, min: opt.min, max: opt.max, value: attrs[ name ], onChange: function ( v ) { onChange( parseInt( v, 10 ) || 0 ); } } );
			case 'range':
				return el( c.RangeControl, { key: name, label: label, min: opt.min, max: opt.max, value: attrs[ name ], onChange: onChange } );
			case 'select':
				return el( c.SelectControl, { key: name, label: label, value: attrs[ name ], options: opt, onChange: function ( v ) { onChange( typeof attrs[ name ] === 'number' ? parseInt( v, 10 ) : v ); } } );
			default:
				return el( c.TextControl, { key: name, label: label, value: attrs[ name ], onChange: onChange } );
		}
	}

	Object.keys( CONTROLS ).forEach( function ( kind ) {
		wp.blocks.registerBlockType( 'viewlapse/' + kind, {
			edit: function ( props ) {
				var blockProps = be.useBlockProps();
				return el(
					'div',
					blockProps,
					el(
						be.InspectorControls,
						null,
						el( c.PanelBody, { title: __( 'Impostazioni', 'viewlapse-webcam' ), initialOpen: true },
							CONTROLS[ kind ].map( function ( spec ) {
								return control( spec, props.attributes, props.setAttributes );
							} )
						)
					),
					el( c.Disabled, null, el( SSR, { block: 'viewlapse/' + kind, attributes: props.attributes } ) )
				);
			},
			save: function () {
				return null;
			},
		} );
	} );
}( window.wp ) );
