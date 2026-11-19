<?php
/**
 * Contesto firmato di un blocco, per l'endpoint REST del plugin.
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

defined( 'ABSPATH' ) || exit;

/**
 * Ogni blocco mostrato in pagina porta un contesto firmato (tipo, attributi,
 * sito): l'endpoint REST ridisegna solo blocchi che il sito ha davvero
 * pubblicato, così nessuno può usare la chiave del sito per leggere altre
 * webcam o altri formati. Il contesto non scade: le pagine si possono
 * mettere in cache.
 */
final class Context {

	/**
	 * Firma un contesto.
	 *
	 * @param string               $kind  Tipo del blocco.
	 * @param array<string, mixed> $attrs Attributi.
	 */
	public static function sign( string $kind, array $attrs ): string {
		ksort( $attrs );
		$payload = array(
			'k' => $kind,
			'a' => $attrs,
			'b' => get_current_blog_id(),
			/**
			 * Il contesto è di una pagina pubblica (true, o l'id della pagina
			 * pubblica) o riservata (false).
			 *
			 * @param bool|int $public Predefinito true.
			 */
			'p' => apply_filters( 'viewlapse_webcam_context_public', true ),
		);
		$json = (string) wp_json_encode( $payload );
		$b64  = rtrim( strtr( base64_encode( $json ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- codifica del contesto, non offuscamento.
		return $b64 . '.' . self::mac( $b64 );
	}

	/**
	 * Verifica un contesto.
	 *
	 * @param string $token Contesto firmato.
	 * @return array{k:string, a:array<string, mixed>, b:int, p:bool|int}|null
	 */
	public static function verify( string $token ): ?array {
		$parts = explode( '.', $token );
		if ( 2 !== count( $parts ) || strlen( $token ) > 4096 ) {
			return null;
		}
		if ( ! hash_equals( self::mac( $parts[0] ), $parts[1] ) ) {
			return null;
		}
		$json = base64_decode( strtr( $parts[0], '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- vedi sopra.
		$data = is_string( $json ) ? json_decode( $json, true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['k'], $data['a'], $data['b'] ) || (int) $data['b'] !== get_current_blog_id() ) {
			return null;
		}
		return array(
			'k' => (string) $data['k'],
			'a' => (array) $data['a'],
			'b' => (int) $data['b'],
			'p' => is_int( $data['p'] ?? null ) ? (int) $data['p'] : ! empty( $data['p'] ),
		);
	}

	/**
	 * HMAC con i sali di WordPress.
	 *
	 * @param string $data Dati.
	 */
	private static function mac( string $data ): string {
		return substr( hash_hmac( 'sha256', 'vl-ctx|' . $data, wp_salt( 'auth' ) ), 0, 32 );
	}
}
