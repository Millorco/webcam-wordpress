<?php
/**
 * Client dell'API dei siti del portal (api-siti-v1.md), solo lato server.
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Chiamate GET al portal con wp_remote_get e cache nei transient.
 *
 * La chiave resta sul server: al browser arrivano solo gli URL firmati delle
 * immagini. Ogni risposta si tiene per `$ttl` secondi; dopo, se il portal non
 * risponde, si usa ancora la copia vecchia (gli URL delle immagini valgono
 * almeno 7 giorni) fino a STALE secondi.
 */
final class Api {

	/** Prefisso dei transient (anche per svuotare la cache). */
	public const PREFIX = 'vlw_';

	/** Per quanto si usa una risposta scaduta se il portal non risponde. */
	public const STALE = 2 * DAY_IN_SECONDS;

	/** Durata della cache degli errori (per non insistere sul portal). */
	public const ERROR_TTL = 30;

	/**
	 * Chiamata GET con cache.
	 *
	 * @param string               $path  Percorso dopo /api/site/v1/ (es. "webcams/monte-cavo").
	 * @param array<string, mixed> $query Parametri.
	 * @param int                  $ttl   Secondi di cache.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get( string $path, array $query = array(), int $ttl = 60 ) {
		$s   = Settings::get();
		$url = self::url( $s, $path, $query );
		if ( '' === $url ) {
			return new WP_Error( 'vl_not_configured', __( 'Impostare l\'indirizzo del portal Viewlapse.', 'viewlapse-webcam' ) );
		}
		$cache_key = self::PREFIX . md5( $url . '|' . hash( 'sha256', (string) $s['api_key'] ) );
		$cached    = get_transient( $cache_key );
		$now       = time();

		if ( is_array( $cached ) && isset( $cached['t'] ) ) {
			if ( isset( $cached['e'] ) && $now - $cached['t'] < self::ERROR_TTL ) {
				return self::error_from( $cached['e'] );
			}
			if ( isset( $cached['d'] ) && $now - $cached['t'] < $ttl ) {
				return $cached['d'];
			}
		}
		$stale = ( is_array( $cached ) && isset( $cached['d'] ) ) ? $cached['d'] : null;

		$backoff = get_transient( self::PREFIX . 'backoff' );
		if ( $backoff && null !== $stale ) {
			return $stale;
		}

		$res = self::fetch( $s, $url );
		if ( is_wp_error( $res ) ) {
			$code = (string) $res->get_error_code();
			// Un 404 è una risposta definitiva (webcam non visibile): non si ripiega sulla copia vecchia.
			if ( null !== $stale && 'not_found' !== $code && 'vl_invalid_param' !== $code ) {
				return $stale;
			}
			$data = $res->get_error_data();
			set_transient(
				$cache_key,
				array(
					't' => $now,
					'e' => array(
						'code'    => $code,
						'message' => $res->get_error_message(),
						'status'  => is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0,
					),
				),
				self::ERROR_TTL
			);
			return $res;
		}
		set_transient(
			$cache_key,
			array(
				't' => $now,
				'd' => $res,
			),
			$ttl + self::STALE
		);
		return $res;
	}

	/**
	 * Chiamata senza cache (prova della connessione).
	 *
	 * @param string               $path  Percorso.
	 * @param array<string, mixed> $query Parametri.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get_fresh( string $path, array $query = array() ) {
		$s   = Settings::get();
		$url = self::url( $s, $path, $query );
		if ( '' === $url ) {
			return new WP_Error( 'vl_not_configured', __( 'Impostare l\'indirizzo del portal Viewlapse.', 'viewlapse-webcam' ) );
		}
		return self::fetch( $s, $url );
	}

	/**
	 * URL completo della chiamata.
	 *
	 * @param array<string, mixed> $s     Impostazioni.
	 * @param string               $path  Percorso.
	 * @param array<string, mixed> $query Parametri.
	 */
	private static function url( array $s, string $path, array $query ): string {
		$base = untrailingslashit( (string) $s['portal_url'] );
		if ( '' === $base ) {
			return '';
		}
		$parts = array_map( 'rawurlencode', explode( '/', trim( $path, '/' ) ) );
		$url   = $base . '/api/site/v1/' . implode( '/', $parts );
		$query = array_filter( $query, static fn( $v ) => null !== $v && '' !== $v );
		if ( $query ) {
			ksort( $query );
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}
		return $url;
	}

	/**
	 * Esegue la richiesta HTTP.
	 *
	 * @param array<string, mixed> $s   Impostazioni.
	 * @param string               $url URL.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function fetch( array $s, string $url ) {
		$headers = array( 'Accept' => 'application/json' );
		if ( '' !== (string) $s['api_key'] ) {
			$headers['Authorization'] = 'Bearer ' . $s['api_key'];
		}
		$res = wp_remote_get(
			$url,
			array(
				'timeout'     => (int) $s['timeout'],
				'redirection' => 0,
				'headers'     => $headers,
				'user-agent'  => 'Viewlapse-Webcam/' . VIEWLAPSE_WEBCAM_VERSION . ' (WordPress)',
			)
		);
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'vl_http', __( 'Il portal Viewlapse non risponde.', 'viewlapse-webcam' ), array( 'detail' => $res->get_error_message() ) );
		}
		$status = (int) wp_remote_retrieve_response_code( $res );
		$json   = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( 429 === $status ) {
			$retry = max( 5, min( 600, (int) wp_remote_retrieve_header( $res, 'retry-after' ) ) );
			set_transient( self::PREFIX . 'backoff', 1, $retry );
		}
		if ( 200 !== $status || ! is_array( $json ) || empty( $json['ok'] ) ) {
			$code = is_array( $json ) && isset( $json['error'] ) ? (string) $json['error'] : 'vl_http_' . $status;
			$msg  = is_array( $json ) && isset( $json['message'] ) ? (string) $json['message'] : sprintf(
				/* translators: %d: codice HTTP */
				__( 'Risposta inattesa dal portal (HTTP %d).', 'viewlapse-webcam' ),
				$status
			);
			if ( 'invalid_param' === $code ) {
				$code = 'vl_invalid_param';
			}
			return new WP_Error( $code, $msg, array( 'status' => $status ) );
		}
		return $json;
	}

	/**
	 * Ricrea un WP_Error dalla cache.
	 *
	 * @param array<string, mixed> $e Errore salvato.
	 */
	private static function error_from( array $e ): WP_Error {
		return new WP_Error( (string) ( $e['code'] ?? 'vl_error' ), (string) ( $e['message'] ?? '' ), array( 'status' => (int) ( $e['status'] ?? 0 ) ) );
	}

	/**
	 * Svuota la cache delle risposte del sito corrente.
	 */
	public static function flush(): int {
		global $wpdb;
		$like = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';
		$like2 = $wpdb->esc_like( '_transient_timeout_' . self::PREFIX ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- pulizia dei transient del plugin.
		$n = (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $like2 ) );
		wp_cache_flush_group( 'transient' );
		return $n;
	}

	// ------------------------------------------------------------ accesso ai dati

	/**
	 * Elenco delle webcam visibili.
	 *
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function webcams() {
		$r = self::get( 'webcams', array(), (int) Settings::value( 'ttl_current' ) );
		return is_wp_error( $r ) ? $r : (array) ( $r['webcams'] ?? array() );
	}

	/**
	 * Dettaglio di una webcam.
	 *
	 * @param string $slug Slug.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function webcam( string $slug ) {
		$r = self::get( 'webcams/' . $slug, array(), (int) Settings::value( 'ttl_current' ) );
		return is_wp_error( $r ) ? $r : (array) ( $r['webcam'] ?? array() );
	}

	/**
	 * Foto di un giorno.
	 *
	 * @param string $slug  Slug.
	 * @param string $date  AAAA-MM-GG.
	 * @param int    $every Minuti (0 = tutte).
	 * @param bool   $today Il giorno è oggi (cache breve).
	 * @return array<string, mixed>|WP_Error
	 */
	public static function photos( string $slug, string $date, int $every = 0, bool $today = false ) {
		$ttl = $today ? (int) Settings::value( 'ttl_current' ) * 5 : (int) Settings::value( 'ttl_archive' );
		return self::get(
			'webcams/' . $slug . '/photos',
			array(
				'date'  => $date,
				'every' => $every > 0 ? $every : null,
			),
			$ttl
		);
	}

	/**
	 * Giorni con foto di un mese.
	 *
	 * @param string $slug    Slug.
	 * @param string $month   AAAA-MM.
	 * @param bool   $current Mese in corso (cache breve).
	 * @return array<string, mixed>|WP_Error
	 */
	public static function days( string $slug, string $month, bool $current = false ) {
		$ttl = $current ? 10 * MINUTE_IN_SECONDS : (int) Settings::value( 'ttl_archive' );
		return self::get( 'webcams/' . $slug . '/days', array( 'month' => $month ), $ttl );
	}

	/**
	 * Medie orarie delle misure esterne.
	 *
	 * @param string $slug   Slug.
	 * @param string $from   Inizio (formato del portal).
	 * @param string $to     Fine.
	 * @param string $fields Campi.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function series( string $slug, string $from, string $to, string $fields ) {
		return self::get(
			'webcams/' . $slug . '/series',
			array(
				'from'   => $from,
				'to'     => $to,
				'fields' => $fields,
			),
			10 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Due foto per il confronto.
	 *
	 * @param string $slug  Slug.
	 * @param string $a     Istante A.
	 * @param string $b     Istante B.
	 * @param bool   $fixed Entrambi gli istanti nel passato (cache lunga).
	 * @return array<string, mixed>|WP_Error
	 */
	public static function compare( string $slug, string $a, string $b, bool $fixed ) {
		$ttl = $fixed ? (int) Settings::value( 'ttl_archive' ) : (int) Settings::value( 'ttl_current' ) * 5;
		return self::get(
			'webcams/' . $slug . '/compare',
			array(
				'a' => $a,
				'b' => $b,
			),
			$ttl
		);
	}
}
