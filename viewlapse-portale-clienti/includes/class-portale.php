<?php
/**
 * Avvio del portale clienti e requisiti.
 *
 * @package Viewlapse\Portale
 */

namespace Viewlapse\Portale;

defined( 'ABSPATH' ) || exit;

/**
 * Controlla i requisiti (Multisite, Viewlapse Webcam) e collega le parti.
 */
final class Portale {

	/**
	 * Hook.
	 */
	public static function boot(): void {
		add_action( 'plugins_loaded', array( self::class, 'loaded' ), 20 );
	}

	/**
	 * Requisiti soddisfatti.
	 */
	public static function ready(): bool {
		return is_multisite() && class_exists( '\\Viewlapse\\Webcam\\Plugin' );
	}

	/**
	 * Dopo il caricamento dei plugin.
	 */
	public static function loaded(): void {
		load_plugin_textdomain( 'viewlapse-portale-clienti', false, dirname( plugin_basename( VIEWLAPSE_PORTALE_FILE ) ) . '/languages' );
		if ( ! self::ready() ) {
			add_action( 'admin_notices', array( self::class, 'notice' ) );
			add_action( 'network_admin_notices', array( self::class, 'notice' ) );
			return;
		}
		Network::boot();
		Access::boot();
		Appearance::boot();
		Sites::boot();
	}

	/**
	 * Avviso dei requisiti mancanti.
	 */
	public static function notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$msg = ! is_multisite()
			? __( 'Viewlapse Portale clienti richiede WordPress Multisite.', 'viewlapse-portale-clienti' )
			: __( 'Viewlapse Portale clienti richiede il plugin Viewlapse Webcam attivo in tutta la rete.', 'viewlapse-portale-clienti' );
		echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * Attivazione: solo Multisite; ruoli nei sottositi già creati.
	 *
	 * @param bool $network_wide Attivazione in rete.
	 */
	public static function activate( $network_wide = false ): void {
		if ( ! is_multisite() ) {
			deactivate_plugins( plugin_basename( VIEWLAPSE_PORTALE_FILE ) );
			wp_die( esc_html__( 'Viewlapse Portale clienti richiede WordPress Multisite.', 'viewlapse-portale-clienti' ) );
		}
		unset( $network_wide );
		foreach ( Sites::customer_site_ids() as $id ) {
			switch_to_blog( $id );
			Sites::roles();
			restore_current_blog();
		}
	}
}
