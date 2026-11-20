<?php
/**
 * Chiave client del portal, salvata per sottosito e cifrata.
 *
 * @package Viewlapse\Portale
 */

namespace Viewlapse\Portale;

defined( 'ABSPATH' ) || exit;

/**
 * La chiave si salva nelle opzioni del sottosito (vl_portale), cifrata con
 * sodium e una chiave ricavata dai sali di wp-config.php: un dump del
 * database da solo non la rivela. Se si cambiano i sali va reinserita.
 * Non torna mai in nessuna pagina: si mostra solo l'inizio.
 */
final class Keys {

	public const OPTION  = 'vl_portale';
	public const NETWORK = 'vl_portale_rete';

	/**
	 * Impostazioni di rete.
	 *
	 * @return array{portal_url:string}
	 */
	public static function network(): array {
		$n = get_site_option( self::NETWORK, array() );
		return array( 'portal_url' => is_array( $n ) ? (string) ( $n['portal_url'] ?? '' ) : '' );
	}

	/**
	 * Dati del cliente del sito corrente (o di $blog_id).
	 *
	 * @param int|null $blog_id Sito.
	 * @return array<string, mixed>|null
	 */
	public static function site( ?int $blog_id = null ): ?array {
		$v = null === $blog_id ? get_option( self::OPTION ) : get_blog_option( $blog_id, self::OPTION );
		return is_array( $v ) && ! empty( $v['key'] ) ? $v : null;
	}

	/**
	 * Salva chiave e cliente nel sito $blog_id.
	 *
	 * @param int                  $blog_id Sito.
	 * @param string               $key     Chiave in chiaro.
	 * @param array<string, mixed> $who     Risposta di whoami.
	 */
	public static function store( int $blog_id, string $key, array $who ): void {
		$old = self::site( $blog_id ) ?? array();
		$val = array(
			'key'           => self::encrypt( $key ),
			'hint'          => substr( $key, 0, 12 ) . '…',
			'customer_id'   => (int) ( $who['customer']['id'] ?? 0 ),
			'customer_name' => (string) ( $who['customer']['name'] ?? '' ),
			'created_at'    => (int) ( $old['created_at'] ?? time() ),
			'updated_at'    => time(),
		);
		switch_to_blog( $blog_id );
		update_option( self::OPTION, $val, false );
		\Viewlapse\Webcam\Api::flush();
		restore_current_blog();
	}

	/**
	 * Chiave in chiaro del sito corrente.
	 */
	public static function current_key(): string {
		$s = self::site();
		return $s ? self::decrypt( (string) $s['key'] ) : '';
	}

	/**
	 * Cifra.
	 *
	 * @param string $plain Testo.
	 */
	public static function encrypt( string $plain ): string {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return 'plain:' . $plain;
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- dati cifrati.
		return 'v1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::secret() ) );
	}

	/**
	 * Decifra ('' se i sali sono cambiati).
	 *
	 * @param string $stored Valore salvato.
	 */
	public static function decrypt( string $stored ): string {
		if ( str_starts_with( $stored, 'plain:' ) ) {
			return substr( $stored, 6 );
		}
		if ( ! str_starts_with( $stored, 'v1:' ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- dati cifrati.
		$raw = base64_decode( substr( $stored, 3 ), true );
		if ( ! is_string( $raw ) || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::secret() );
		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Chiave di cifratura dai sali di WordPress.
	 */
	private static function secret(): string {
		return hash( 'sha256', 'vl-portale-key|' . wp_salt( 'secure_auth' ), true );
	}

	/**
	 * Verifica una chiave con whoami (senza cache).
	 *
	 * @param string $key Chiave.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function verify( string $key ) {
		if ( ! \Viewlapse\Webcam\Settings::valid_key( $key ) ) {
			return new \WP_Error( 'vl_key', __( 'La chiave non è valida: deve iniziare con vls_ ed essere lunga 47 caratteri.', 'viewlapse-portale-clienti' ) );
		}
		$url = self::network()['portal_url'];
		if ( '' === $url ) {
			return new \WP_Error( 'vl_url', __( 'Impostare prima l\'indirizzo del portal della rete.', 'viewlapse-portale-clienti' ) );
		}
		$res = wp_remote_get(
			untrailingslashit( $url ) . '/api/site/v1/whoami',
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $key,
					'Accept'        => 'application/json',
				),
				'user-agent'  => 'Viewlapse-Portale/' . VIEWLAPSE_PORTALE_VERSION . ' (WordPress)',
			)
		);
		if ( is_wp_error( $res ) ) {
			return new \WP_Error( 'vl_http', __( 'Il portal non risponde.', 'viewlapse-portale-clienti' ) );
		}
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || ! is_array( $json ) || empty( $json['ok'] ) ) {
			return new \WP_Error( 'vl_key', is_array( $json ) && isset( $json['message'] ) ? (string) $json['message'] : __( 'Chiave rifiutata dal portal.', 'viewlapse-portale-clienti' ) );
		}
		if ( 'client' !== ( $json['scope'] ?? '' ) || empty( $json['customer']['id'] ) ) {
			return new \WP_Error( 'vl_scope', __( 'Serve una chiave con scope "client" di un cliente (una chiave "public" non vede le webcam private).', 'viewlapse-portale-clienti' ) );
		}
		return $json;
	}
}
