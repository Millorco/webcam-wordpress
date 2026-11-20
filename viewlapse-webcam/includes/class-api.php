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
	 * Errori definitivi: non si ripiega sulla copia vecchia (webcam non
	 * visibile, parametro sbagliato, funzione non nel listino, scope).
	 */
	public const FINAL_ERRORS = array( 'not_found', 'vl_invalid_param', 'feature_unavailable', 'scope_client_required' );

	/** Copia vecchia massima per gli elenchi dei documenti (URL firmati validi circa un giorno). */
	public const STALE_DOCS = 6 * HOUR_IN_SECONDS;

	/**
	 * Chiamata GET con cache.
	 *
	 * @param string               $path  Percorso dopo /api/site/v1/ (es. "webcams/monte-cavo").
	 * @param array<string, mixed> $query Parametri.
	 * @param int                  $ttl   Secondi di cache.
	 * @param int                  $stale_max Per quanto si usa la copia vecchia se il portal non risponde.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function get( string $path, array $query = array(), int $ttl = 60, int $stale_max = self::STALE ) {
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
		$stale = ( is_array( $cached ) && isset( $cached['d'] ) && $now - $cached['t'] < $ttl + $stale_max ) ? $cached['d'] : null;

		$backoff = get_transient( self::PREFIX . 'backoff' );
		if ( $backoff && null !== $stale ) {
			return $stale;
		}

		$res = self::fetch( $s, $url );
		if ( is_wp_error( $res ) ) {
			$code = (string) $res->get_error_code();
			// Un 404 è una risposta definitiva (webcam non visibile): non si ripiega sulla copia vecchia.
			if ( null !== $stale && ! in_array( $code, self::FINAL_ERRORS, true ) ) {
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
			$ttl + $stale_max
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

	// ------------------------------------------------------------ servizi (API dei siti 1.1)

	/**
	 * Scope della chiave del sito (anon, public, client), dalla cache di whoami.
	 */
	public static function scope(): string {
		$r = self::get( 'whoami', array(), 10 * MINUTE_IN_SECONDS );
		return is_wp_error( $r ) ? '' : (string) ( $r['scope'] ?? '' );
	}

	/**
	 * Timelapse pronti.
	 *
	 * @param string      $slug   Slug.
	 * @param string|null $kind   day, week, month, custom, final (anche separati da virgola), null = tutti.
	 * @param string|null $format landscape, portrait, null = tutti.
	 * @param int         $limit  1-200.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function timelapses( string $slug, ?string $kind, ?string $format, int $limit = 50 ) {
		return self::get(
			'webcams/' . $slug . '/timelapses',
			array(
				'kind'   => $kind,
				'format' => $format,
				'limit'  => max( 1, min( 200, $limit ) ),
			),
			10 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Stesso momento in giorni diversi.
	 *
	 * @param string $slug Slug.
	 * @param string $time HH:MM locale.
	 * @param string $from AAAA-MM-GG.
	 * @param string $to   AAAA-MM-GG.
	 * @param string $step day, week, month, year.
	 * @param bool   $good Preferire le foto buone.
	 * @param bool   $past L'intervallo è tutto nel passato (cache lunga).
	 * @return array<string, mixed>|WP_Error
	 */
	public static function same_moment( string $slug, string $time, string $from, string $to, string $step, bool $good, bool $past ) {
		return self::get(
			'webcams/' . $slug . '/same-moment',
			array(
				'time' => $time,
				'from' => $from,
				'to'   => $to,
				'step' => $step,
				'good' => $good ? 1 : 0,
			),
			$past ? (int) Settings::value( 'ttl_archive' ) : 10 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Foto del giorno (con i formati per i social).
	 *
	 * @param string      $slug Slug.
	 * @param string|null $date AAAA-MM-GG, null = quella del portal (oggi se scelta, altrimenti ieri).
	 * @param bool        $old  Giorno di almeno due giorni fa (non cambia più).
	 * @return array<string, mixed>|WP_Error
	 */
	public static function photo_of_day( string $slug, ?string $date, bool $old = false ) {
		return self::get( 'webcams/' . $slug . '/photo-of-day', array( 'date' => $date ), $old ? (int) Settings::value( 'ttl_archive' ) : 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Foto del giorno di un mese.
	 *
	 * @param string $slug    Slug.
	 * @param string $month   AAAA-MM.
	 * @param bool   $current Mese in corso.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function photos_of_day( string $slug, string $month, bool $current ) {
		return self::get( 'webcams/' . $slug . '/photos-of-day', array( 'month' => $month ), $current ? 30 * MINUTE_IN_SECONDS : (int) Settings::value( 'ttl_archive' ) );
	}

	/**
	 * Sponsor e infopoint attivi.
	 *
	 * @param string $slug Slug.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function overlay_items( string $slug ) {
		return self::get( 'webcams/' . $slug . '/overlay-items', array(), 10 * MINUTE_IN_SECONDS );
	}

	/**
	 * Statistiche di visualizzazione (solo scope client).
	 *
	 * @param string $slug  Slug.
	 * @param string $from  AAAA-MM-GG.
	 * @param string $to    AAAA-MM-GG.
	 * @param string $group day o month.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function stats( string $slug, string $from, string $to, string $group ) {
		return self::get(
			'webcams/' . $slug . '/stats',
			array(
				'from'  => $from,
				'to'    => $to,
				'group' => $group,
			),
			10 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Rapporti mensili di un anno (solo scope client). URL dei PDF validi circa un giorno.
	 *
	 * @param string $slug Slug.
	 * @param int    $year Anno.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function reports( string $slug, int $year ) {
		return self::get( 'webcams/' . $slug . '/reports', array( 'year' => $year ), 10 * MINUTE_IN_SECONDS, self::STALE_DOCS );
	}

	/**
	 * Certificati di maltempo di un anno (solo scope client).
	 *
	 * @param string $slug Slug.
	 * @param int    $year Anno.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function certificates( string $slug, int $year ) {
		return self::get( 'webcams/' . $slug . '/certificates', array( 'year' => $year ), 10 * MINUTE_IN_SECONDS, self::STALE_DOCS );
	}

	/**
	 * Registro giornaliero e giornate di maltempo di un mese (solo scope client).
	 *
	 * @param string $slug  Slug.
	 * @param string $month AAAA-MM.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function weather_days( string $slug, string $month ) {
		return self::get( 'webcams/' . $slug . '/weather-days', array( 'month' => $month ), 10 * MINUTE_IN_SECONDS, self::STALE_DOCS );
	}
}
