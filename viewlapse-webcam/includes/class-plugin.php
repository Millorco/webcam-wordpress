<?php
/**
 * Avvio del plugin: traduzioni, script, stili, blocchi, shortcode, REST.
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

defined( 'ABSPATH' ) || exit;

/**
 * Collega le parti del plugin agli hook di WordPress.
 */
final class Plugin {

	/**
	 * Registra gli hook.
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'init' ), 5 );
		add_action( 'init', array( Blocks::class, 'register' ) );
		add_action( 'init', array( Shortcodes::class, 'register' ) );
		add_action( 'rest_api_init', array( Rest::class, 'register' ) );
		if ( is_admin() ) {
			Admin::boot();
		}
	}

	/**
	 * Traduzioni e risorse (registrate, caricate solo dove servono).
	 */
	public static function init(): void {
		load_plugin_textdomain( 'viewlapse-webcam', false, dirname( plugin_basename( VIEWLAPSE_WEBCAM_FILE ) ) . '/languages' );

		$url = plugins_url( '', VIEWLAPSE_WEBCAM_FILE );
		$ver = VIEWLAPSE_WEBCAM_VERSION;

		wp_register_style( 'viewlapse-webcam', $url . '/assets/css/viewlapse-webcam.css', array(), $ver );
		wp_register_script( 'viewlapse-webcam', $url . '/assets/js/viewlapse-webcam.js', array(), $ver, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_register_style( 'viewlapse-leaflet', $url . '/assets/vendor/leaflet/leaflet.css', array(), '1.9.4' );
		wp_register_script( 'viewlapse-leaflet', $url . '/assets/vendor/leaflet/leaflet.js', array(), '1.9.4', array( 'in_footer' => true ) );
		wp_register_script( 'viewlapse-webcam-map', $url . '/assets/js/mappa.js', array( 'viewlapse-leaflet' ), $ver, array( 'in_footer' => true ) );
		wp_register_script(
			'viewlapse-webcam-editor',
			$url . '/assets/js/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-api-fetch', 'wp-i18n' ),
			$ver,
			array( 'in_footer' => true )
		);
		wp_set_script_translations( 'viewlapse-webcam-editor', 'viewlapse-webcam', VIEWLAPSE_WEBCAM_DIR . '/languages' );
	}

	/**
	 * Carica stile e script del frontend (una volta per pagina).
	 */
	public static function enqueue_front(): void {
		wp_enqueue_style( 'viewlapse-webcam' );
		wp_enqueue_script( 'viewlapse-webcam' );
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		wp_localize_script(
			'viewlapse-webcam',
			'VLWebcam',
			array(
				'rest'  => esc_url_raw( rest_url( Rest::NS . '/render' ) ),
				// Il nonce serve solo agli utenti collegati (autenticazione con il cookie).
				'nonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'i18n'  => array(
					'close'    => __( 'Chiudi', 'viewlapse-webcam' ),
					'prev'     => __( 'Foto precedente', 'viewlapse-webcam' ),
					'next'     => __( 'Foto successiva', 'viewlapse-webcam' ),
					'download' => __( 'Scarica l\'originale', 'viewlapse-webcam' ),
					'error'    => __( 'Aggiornamento non riuscito', 'viewlapse-webcam' ),
					'play'     => __( 'Riproduci', 'viewlapse-webcam' ),
					'pause'    => __( 'Pausa', 'viewlapse-webcam' ),
				),
			)
		);
	}

	/**
	 * Carica Leaflet e lo script della mappa (solo se la mappa è attiva).
	 */
	public static function enqueue_map(): void {
		wp_enqueue_style( 'viewlapse-leaflet' );
		wp_enqueue_script( 'viewlapse-webcam-map' );
	}
}
