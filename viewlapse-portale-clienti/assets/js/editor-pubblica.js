/**
 * Viewlapse Portale clienti: pannello "Accesso" nell'editor delle pagine
 * (pagina pubblica, visibile senza login). Senza build.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;
	var Panel = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	if ( ! Panel ) {
		return;
	}

	function AccessPanel() {
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var edit = useDispatch( 'core/editor' ).editPost;
		return el(
			Panel,
			{ name: 'vl-portale-accesso', title: __( 'Accesso', 'viewlapse-portale-clienti' ) },
			el( wp.components.ToggleControl, {
				label: __( 'Pagina pubblica (visibile senza login)', 'viewlapse-portale-clienti' ),
				help: __( 'Le altre pagine restano riservate agli utenti del cliente.', 'viewlapse-portale-clienti' ),
				checked: !! meta._vl_pubblica,
				onChange: function ( v ) {
					edit( { meta: { _vl_pubblica: v } } );
				},
			} )
		);
	}

	wp.plugins.registerPlugin( 'vl-portale-accesso', { render: AccessPanel } );
}( window.wp ) );
