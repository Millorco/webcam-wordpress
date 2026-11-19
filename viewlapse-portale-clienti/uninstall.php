<?php
/**
 * Disinstallazione: toglie impostazioni, chiavi e ruoli del portale clienti.
 * I sottositi e le loro pagine restano (si cancellano dalla rete se serve).
 *
 * @package Viewlapse\Portale
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! is_multisite() ) {
	return;
}

delete_site_option( 'vl_portale_rete' );

foreach ( get_sites(
	array(
		'fields' => 'ids',
		'number' => 0,
	)
) as $vl_site_id ) {
	switch_to_blog( (int) $vl_site_id );
	if ( false !== get_option( 'vl_portale', false ) ) {
		delete_option( 'vl_portale' );
		delete_option( 'vl_portale_aspetto' );
		remove_role( 'vl_cliente_visione' );
		remove_role( 'vl_cliente_download' );
		foreach ( array( 'administrator', 'editor' ) as $vl_role_name ) {
			$vl_role = get_role( $vl_role_name );
			if ( $vl_role ) {
				$vl_role->remove_cap( 'vl_view_webcam' );
				$vl_role->remove_cap( 'vl_download_original' );
			}
		}
		delete_post_meta_by_key( '_vl_pubblica' );
	}
	restore_current_blog();
}
