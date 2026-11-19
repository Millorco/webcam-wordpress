<?php
/**
 * Endpoint REST interno del plugin (namespace viewlapse/v1).
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * GET viewlapse/v1/render: ridisegna un blocco della pagina (aggiornamento
 * della foto, cambio di giorno, di mese, di webcam, date del confronto).
 * Accetta solo contesti firmati dal sito (Context). Gli utenti collegati
 * mandano il nonce wp_rest (X-WP-Nonce), che WordPress controlla.
 *
 * GET viewlapse/v1/webcams: elenco delle webcam per l'editor dei blocchi.
 */
final class Rest {

	public const NS = 'viewlapse/v1';

	/**
	 * Rotte.
	 */
	public static function register(): void {
		register_rest_route(
			self::NS,
			'/render',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'render' ),
				'permission_callback' => array( self::class, 'can_render' ),
				'args'                => array(
					'ctx'       => array(
						'type'     => 'string',
						'required' => true,
					),
					'vl_cam'    => array( 'type' => 'string' ),
					'vl_date'   => array( 'type' => 'string' ),
					'vl_month'  => array( 'type' => 'string' ),
					'vl_a'      => array( 'type' => 'string' ),
					'vl_b'      => array( 'type' => 'string' ),
					'vl_giorni' => array( 'type' => 'string' ),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/webcams',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'webcams' ),
				'permission_callback' => static fn(): bool => current_user_can( 'edit_posts' ),
			)
		);
	}

	/**
	 * Permesso: contesto valido e, se un altro plugin lo chiede, utente autorizzato.
	 *
	 * @param WP_REST_Request $req Richiesta.
	 * @return bool|WP_Error
	 */
	public static function can_render( WP_REST_Request $req ) {
		$ctx = Context::verify( (string) $req->get_param( 'ctx' ) );
		if ( null === $ctx || ! in_array( $ctx['k'], Render::KINDS, true ) ) {
			return new WP_Error( 'vl_bad_context', __( 'Contesto non valido.', 'viewlapse-webcam' ), array( 'status' => 400 ) );
		}
		/**
		 * Filtra il permesso di ridisegnare un blocco via REST.
		 *
		 * @param bool  $allowed Predefinito true.
		 * @param array $ctx     Contesto verificato (k = tipo, a = attributi, p = pagina pubblica).
		 */
		$ok = (bool) apply_filters( 'viewlapse_webcam_rest_allowed', true, $ctx );
		if ( ! $ok ) {
			return new WP_Error( 'rest_forbidden', __( 'Accesso riservato.', 'viewlapse-webcam' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
		}
		return true;
	}

	/**
	 * Contenuto del blocco ridisegnato.
	 *
	 * @param WP_REST_Request $req Richiesta.
	 */
	public static function render( WP_REST_Request $req ): WP_REST_Response {
		$ctx    = (array) Context::verify( (string) $req->get_param( 'ctx' ) );
		$params = array();
		foreach ( array( 'vl_cam', 'vl_date', 'vl_month', 'vl_a', 'vl_b', 'vl_giorni' ) as $k ) {
			$v = $req->get_param( $k );
			if ( is_string( $v ) ) {
				$params[ $k ] = $v;
			}
		}
		$kind  = (string) $ctx['k'];
		$attrs = Blocks::complete( $kind, (array) $ctx['a'] );
		$id    = preg_match( '/^[a-z0-9-]{1,64}$/', (string) $req->get_param( 'id' ) ) ? (string) $req->get_param( 'id' ) : 'vl-' . $kind;
		$html  = Render::inner( $kind, $attrs, Render::request_params( $params ), $id );
		$res   = new WP_REST_Response( array( 'html' => $html ) );
		// Contenuti diversi per utente (download) e per chiave: niente cache condivise.
		$res->header( 'Cache-Control', 'private, max-age=30' );
		return $res;
	}

	/**
	 * Webcam visibili con la chiave del sito (solo per l'editor).
	 */
	public static function webcams(): WP_REST_Response {
		$list = Api::webcams();
		if ( is_wp_error( $list ) ) {
			return new WP_REST_Response(
				array(
					'webcams' => array(),
					'error'   => $list->get_error_message(),
				)
			);
		}
		$out = array();
		foreach ( $list as $c ) {
			$out[] = array(
				'slug'   => (string) $c['slug'],
				'name'   => (string) $c['name'],
				'public' => ! empty( $c['public'] ),
			);
		}
		return new WP_REST_Response( array( 'webcams' => $out ) );
	}
}
