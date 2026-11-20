<?php
/**
 * Disinstallazione: toglie impostazioni e cache del plugin (in tutti i siti
 * del Multisite). Nessun file da cancellare: il plugin non salva immagini.
 *
 * @package Viewlapse\Webcam
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Pulizia di un sito.
 */
function vl_webcam_uninstall_site(): void {
	global $wpdb;
	delete_option( 'vl_webcam_settings' );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- pulizia dei transient del plugin.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_vlw_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_vlw_' ) . '%',
			$wpdb->esc_like( '_transient_vl_webcam_notice_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_vl_webcam_notice_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $vl_site_id ) {
		switch_to_blog( (int) $vl_site_id );
		vl_webcam_uninstall_site();
		restore_current_blog();
	}
} else {
	vl_webcam_uninstall_site();
}
