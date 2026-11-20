<?php
/**
 * HTML dei blocchi (lo stesso per blocchi, shortcode e REST).
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Disegna i tipi di blocco: foto, archivio, confronto, grafico, elenco e,
 * dalla 1.1, i servizi (timelapse, stesso momento, foto del giorno,
 * statistiche, documenti) che sono in Services.
 *
 * Le scelte del visitatore (webcam, giorno, mese, date del confronto,
 * periodo del grafico) arrivano come parametri vl_* dell'URL della pagina
 * (funziona anche senza JavaScript) o dall'endpoint REST del plugin.
 */
final class Render {

	public const KINDS = array( 'foto', 'archivio', 'confronto', 'grafico', 'elenco', 'timelapse', 'momento', 'fotogiorno', 'statistiche', 'documenti' );

	/** Parametri vl_* che i blocchi leggono dall'URL della pagina. */
	public const PARAMS = array( 'vl_cam', 'vl_date', 'vl_month', 'vl_a', 'vl_b', 'vl_giorni', 'vl_tl', 'vl_tlk', 'vl_ora', 'vl_passo', 'vl_year' );

	/** Attributo sizes delle immagini grandi. */
	public const SIZES_LARGE = '(min-width: 1200px) 1200px, 100vw';

	/**
	 * Blocco completo (contenitore con il contesto firmato e contenuto).
	 *
	 * @param string               $kind    Tipo.
	 * @param array<string, mixed> $attrs   Attributi (già completati con i predefiniti).
	 * @param string               $wrapper Attributi HTML del contenitore (classe, id...), già sicuri.
	 */
	public static function render( string $kind, array $attrs, string $wrapper = '' ): string {
		if ( ! in_array( $kind, self::KINDS, true ) ) {
			return '';
		}
		Plugin::enqueue_front();
		if ( 'elenco' === $kind && Format::bool( $attrs['mappa'] ?? false ) ) {
			Plugin::enqueue_map();
		}
		$id    = 'vl-' . $kind . '-' . wp_unique_id();
		$inner = self::inner( $kind, $attrs, self::request_params(), $id );
		if ( '' === $inner ) {
			return ''; // funzione non inclusa e blocco da nascondere.
		}
		$data  = ' data-vl-kind="' . esc_attr( $kind ) . '" data-vl-ctx="' . esc_attr( Context::sign( $kind, $attrs ) ) . '"';
		if ( 'foto' === $kind ) {
			$data .= ' data-vl-refresh="' . esc_attr( (string) self::refresh_minutes( $attrs ) ) . '"';
		}
		if ( '' === $wrapper ) {
			$wrapper = 'class="vl-block vl-' . esc_attr( $kind ) . '"';
		}
		return '<div ' . $wrapper . ' id="' . esc_attr( $id ) . '"' . $data . '>' . $inner . '</div>';
	}

	/**
	 * Contenuto del blocco.
	 *
	 * @param string               $kind  Tipo.
	 * @param array<string, mixed> $attrs Attributi.
	 * @param array<string, string> $req  Scelte del visitatore.
	 * @param string               $id    Id del contenitore (per gli anchor).
	 */
	public static function inner( string $kind, array $attrs, array $req, string $id ): string {
		switch ( $kind ) {
			case 'foto':
				return self::foto( $attrs, $req, $id );
			case 'archivio':
				return self::archivio( $attrs, $req, $id );
			case 'confronto':
				return self::confronto( $attrs, $req, $id );
			case 'grafico':
				return self::grafico( $attrs, $req, $id );
			case 'elenco':
				return self::elenco( $attrs );
			case 'timelapse':
				return Services::timelapse( $attrs, $req, $id );
			case 'momento':
				return Services::momento( $attrs, $req, $id );
			case 'fotogiorno':
				return Services::fotogiorno( $attrs, $req, $id );
			case 'statistiche':
				return Services::statistiche( $attrs, $req, $id );
			case 'documenti':
				return Services::documenti( $attrs, $req, $id );
		}
		return '';
	}

	/**
	 * Parametri vl_* dell'URL della pagina, validati.
	 *
	 * @param array<string, mixed>|null $src Sorgente (null = $_GET).
	 * @return array<string, string>
	 */
	public static function request_params( ?array $src = null ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- parametri di sola visualizzazione, validati qui sotto.
		$src = $src ?? wp_unslash( $_GET );
		$out = array();
		if ( isset( $src['vl_cam'] ) && is_string( $src['vl_cam'] ) && preg_match( '/^[a-z0-9-]{1,64}$/', $src['vl_cam'] ) ) {
			$out['cam'] = $src['vl_cam'];
		}
		$date = Format::valid_date( $src['vl_date'] ?? null );
		if ( null !== $date ) {
			$out['date'] = $date;
		}
		$month = Format::valid_month( $src['vl_month'] ?? null );
		if ( null !== $month ) {
			$out['month'] = $month;
		}
		foreach ( array( 'a', 'b' ) as $k ) {
			$v = Format::valid_instant( $src[ 'vl_' . $k ] ?? null );
			if ( null !== $v ) {
				$out[ $k ] = $v;
			}
		}
		if ( isset( $src['vl_giorni'] ) && is_scalar( $src['vl_giorni'] ) ) {
			$g = absint( $src['vl_giorni'] );
			if ( $g >= 1 && $g <= 400 ) {
				$out['giorni'] = (string) $g;
			}
		}
		if ( isset( $src['vl_tl'] ) && is_scalar( $src['vl_tl'] ) && preg_match( '/^[0-9]{1,12}$/', (string) $src['vl_tl'] ) ) {
			$out['tl'] = (string) $src['vl_tl'];
		}
		if ( isset( $src['vl_tlk'] ) && is_string( $src['vl_tlk'] ) && in_array( $src['vl_tlk'], array( 'day', 'week', 'month', 'custom', 'final' ), true ) ) {
			$out['tlk'] = $src['vl_tlk'];
		}
		$ora = Format::valid_time( $src['vl_ora'] ?? null );
		if ( null !== $ora ) {
			$out['ora'] = $ora;
		}
		if ( isset( $src['vl_passo'] ) && is_string( $src['vl_passo'] ) && in_array( $src['vl_passo'], array( 'day', 'week', 'month', 'year' ), true ) ) {
			$out['passo'] = $src['vl_passo'];
		}
		if ( isset( $src['vl_year'] ) && is_scalar( $src['vl_year'] ) && preg_match( '/^20[0-9]{2}$/', (string) $src['vl_year'] ) ) {
			$out['year'] = (string) $src['vl_year'];
		}
		return $out;
	}

	/**
	 * Minuti tra un aggiornamento e l'altro della foto attuale (0 = mai).
	 *
	 * @param array<string, mixed> $attrs Attributi.
	 */
	public static function refresh_minutes( array $attrs ): int {
		$v = (int) ( $attrs['aggiorna'] ?? -1 );
		if ( $v < 0 ) {
			$v = (int) Settings::value( 'refresh_min' );
		}
		return max( 0, min( 1440, $v ) );
	}

	/**
	 * L'utente corrente può scaricare l'originale.
	 */
	public static function can_download(): bool {
		/**
		 * Filtra il permesso di scaricare la foto originale (URL "orig", solo con chiave client).
		 *
		 * @param bool $can Predefinito: capacità vl_download_original.
		 */
		return (bool) apply_filters( 'viewlapse_webcam_can_download', current_user_can( 'vl_download_original' ) );
	}

	/**
	 * L'utente corrente può scaricare rapporti, certificati ed elenchi del registro.
	 */
	public static function can_download_docs(): bool {
		/**
		 * Filtra il permesso di scaricare i documenti del cliente (PDF dei rapporti e dei certificati, registro).
		 *
		 * @param bool $can Predefinito: capacità vl_download_documents o manage_options.
		 */
		return (bool) apply_filters( 'viewlapse_webcam_can_download_docs', current_user_can( 'vl_download_documents' ) || current_user_can( 'manage_options' ) );
	}

	// ------------------------------------------------------------ funzioni del listino (1.1)

	/**
	 * Funzioni attive della webcam, o null se il portal non le manda (API dei siti 1.0).
	 *
	 * @param array<string, mixed> $cam Webcam.
	 * @return array<int, string>|null
	 */
	public static function features( array $cam ): ?array {
		if ( ! isset( $cam['features'] ) || ! is_array( $cam['features'] ) ) {
			return null;
		}
		return array_values( array_map( 'strval', $cam['features'] ) );
	}

	/**
	 * La webcam ha la funzione: true, false o null (portal senza `features`).
	 *
	 * @param array<string, mixed> $cam     Webcam.
	 * @param string               $feature Funzione.
	 */
	public static function has( array $cam, string $feature ): ?bool {
		$f = self::features( $cam );
		return null === $f ? null : in_array( $feature, $f, true );
	}

	/**
	 * Messaggio per una funzione che manca, o '' se il blocco va nascosto.
	 *
	 * Ai visitatori: messaggio cortese o niente (impostazione "Funzioni non
	 * incluse"); a chi modifica i contenuti sempre la spiegazione.
	 *
	 * @param string $why     feature (non nel listino), portal (portal senza API 1.1), scope (serve la chiave client), archived.
	 * @param string $feature Funzione.
	 * @param string $sel     Selettore della webcam da tenere visibile.
	 */
	public static function unavailable( string $why, string $feature, string $sel = '' ): string {
		$editor = current_user_can( 'edit_posts' );
		switch ( $why ) {
			case 'portal':
				$msg = __( 'Il portal Viewlapse non offre ancora questa funzione: serve l\'API dei siti 1.1 (portal 0.4).', 'viewlapse-webcam' );
				break;
			case 'scope':
				$msg = __( 'Questa funzione è disponibile solo nell\'area riservata del cliente (chiave "client").', 'viewlapse-webcam' );
				break;
			case 'archived':
				$msg = __( 'Non disponibile per le webcam in archivio dopo la fine del noleggio.', 'viewlapse-webcam' );
				break;
			default:
				/**
				 * Filtra il messaggio cortese per una funzione non inclusa nel servizio della webcam.
				 *
				 * @param string $msg     Messaggio.
				 * @param string $feature Funzione (timelapse, same_moment, photo_of_day, stats, monthly_report, weather_cert...).
				 */
				$msg = (string) apply_filters( 'viewlapse_webcam_feature_message', __( 'Questa funzione non è compresa nel servizio attivo per questa webcam.', 'viewlapse-webcam' ), $feature );
		}
		if ( $editor ) {
			/* translators: %s: nome della funzione nel listino */
			$msg .= ' ' . sprintf( __( '(funzione "%s"; messaggio visibile a chi modifica i contenuti)', 'viewlapse-webcam' ), $feature );
		} elseif ( 'portal' === $why || 'scope' === $why ) {
			return ''; // ai visitatori non serve sapere perché manca: blocco nascosto.
		} elseif ( 'hide' === Settings::value( 'missing_feature' ) ) {
			// Resta il selettore, per scegliere un'altra webcam.
			return '' !== $sel ? $sel . '<p class="vl-message">' . esc_html__( 'Non disponibile per questa webcam.', 'viewlapse-webcam' ) . '</p>' : '';
		}
		return $sel . '<p class="vl-message vl-message--feature" role="status">' . esc_html( $msg ) . '</p>';
	}

	/**
	 * Messaggio per un errore dell'API dei servizi (403 feature_unavailable,
	 * scope_client_required) o null se è un errore qualsiasi.
	 *
	 * @param WP_Error $e       Errore.
	 * @param string   $feature Funzione.
	 * @param string   $sel     Selettore.
	 */
	public static function service_error( WP_Error $e, string $feature, string $sel = '' ): string {
		$code = (string) $e->get_error_code();
		if ( 'feature_unavailable' === $code ) {
			return self::unavailable( 'feature', $feature, $sel );
		}
		if ( 'scope_client_required' === $code ) {
			return self::unavailable( 'scope', $feature, $sel );
		}
		return $sel . self::error( $e );
	}

	/**
	 * Attributi del beacon delle visualizzazioni (api-siti-v1.md), se attivo.
	 *
	 * Il token arriva dal portal com'è; si usa solo se l'URL è quello del
	 * portal configurato. Il browser manda il conteggio senza cookie.
	 *
	 * @param array<string, mixed> $cam Webcam.
	 */
	public static function beacon_attrs( array $cam ): string {
		$b = is_array( $cam['beacon'] ?? null ) ? $cam['beacon'] : null;
		if ( ! $b || empty( $b['url'] ) || empty( $b['token'] ) || ! Settings::value( 'beacon' ) ) {
			return '';
		}
		/**
		 * Attiva o spegne il conteggio delle visualizzazioni per una webcam.
		 *
		 * @param bool  $on  Predefinito: impostazione del sito.
		 * @param array $cam Webcam.
		 */
		if ( ! apply_filters( 'viewlapse_webcam_beacon', true, $cam ) ) {
			return '';
		}
		$url   = (string) $b['url'];
		$token = (string) $b['token'];
		$host  = wp_parse_url( (string) Settings::value( 'portal_url' ), PHP_URL_HOST );
		if ( ! $host || wp_parse_url( $url, PHP_URL_HOST ) !== $host || ! preg_match( '#^https?://#', $url ) || ! preg_match( '/^[A-Za-z0-9._-]{8,200}$/', $token ) ) {
			return '';
		}
		return ' data-vl-beacon="' . esc_attr( $token ) . '" data-vl-beacon-url="' . esc_url( $url ) . '"';
	}

	// ------------------------------------------------------------ parti comuni

	/**
	 * Messaggio d'errore (dettagliato solo per chi modifica i contenuti).
	 *
	 * @param WP_Error|string $e Errore.
	 */
	public static function error( $e ): string {
		$msg = __( 'Webcam non disponibile al momento.', 'viewlapse-webcam' );
		if ( is_string( $e ) ) {
			$msg = $e;
		} elseif ( current_user_can( 'edit_posts' ) ) {
			$msg .= ' (' . $e->get_error_message() . ')';
		} elseif ( 'not_found' === $e->get_error_code() ) {
			$msg = __( 'Webcam non trovata.', 'viewlapse-webcam' );
		}
		return '<p class="vl-message vl-message--error" role="status">' . esc_html( $msg ) . '</p>';
	}

	/**
	 * Sceglie la webcam: quella fissata, quella scelta dal visitatore o la prima.
	 *
	 * @param array<string, mixed>  $attrs Attributi.
	 * @param array<string, string> $req   Scelte del visitatore.
	 * @param string                $sel   HTML del selettore (uscita).
	 * @param string                $id    Id del blocco.
	 * @param string                $feature Funzione richiesta (1.1): nel selettore solo le webcam che ce l'hanno.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function camera( array $attrs, array $req, ?string &$sel, string $id, string $feature = '' ) {
		$sel      = '';
		$slug     = sanitize_title( (string) ( $attrs['webcam'] ?? '' ) );
		$selector = Format::bool( $attrs['scelta'] ?? false ) || '' === $slug;
		if ( $selector ) {
			$list = Api::webcams();
			if ( is_wp_error( $list ) ) {
				return $list;
			}
			if ( '' !== $feature ) {
				$with = array_values( array_filter( $list, static fn( $c ) => false !== self::has( (array) $c, $feature ) ) );
				$list = $with ? $with : $list; // nessuna con la funzione: si mostra il messaggio sulla prima.
			}
			$slugs = array_column( $list, 'slug' );
			if ( isset( $req['cam'] ) && in_array( $req['cam'], $slugs, true ) ) {
				$slug = $req['cam'];
			} elseif ( '' === $slug || ! in_array( $slug, $slugs, true ) ) {
				$slug = (string) ( $slugs[0] ?? '' );
			}
			if ( '' === $slug ) {
				return new WP_Error( 'vl_none', __( 'Nessuna webcam disponibile.', 'viewlapse-webcam' ) );
			}
			if ( count( $list ) > 1 ) {
				$sel = self::selector( $list, $slug, $id );
			}
		}
		$cam = Api::webcam( $slug );
		if ( ! is_wp_error( $cam ) && empty( $cam['slug'] ) ) {
			return new WP_Error( 'not_found', __( 'Webcam non trovata.', 'viewlapse-webcam' ) );
		}
		return $cam;
	}

	/**
	 * Modulo per scegliere la webcam.
	 *
	 * @param array<int, array<string, mixed>> $list Webcam.
	 * @param string                           $slug Webcam scelta.
	 * @param string                           $id   Id del blocco.
	 */
	private static function selector( array $list, string $slug, string $id ): string {
		$o  = '<form class="vl-select" method="get" action="#' . esc_attr( $id ) . '" data-vl-nav>';
		$o .= '<label for="' . esc_attr( $id ) . '-cam">' . esc_html__( 'Webcam', 'viewlapse-webcam' ) . '</label> ';
		$o .= '<select id="' . esc_attr( $id ) . '-cam" name="vl_cam">';
		foreach ( $list as $c ) {
			$name = (string) $c['name'] . ( 'archived' === ( $c['state'] ?? '' ) ? ' ' . __( '(archivio)', 'viewlapse-webcam' ) : '' );
			$o   .= '<option value="' . esc_attr( (string) $c['slug'] ) . '"' . selected( $c['slug'], $slug, false ) . '>' . esc_html( $name ) . '</option>';
		}
		$o .= '</select> <button type="submit" class="vl-button vl-select__go">' . esc_html__( 'Mostra', 'viewlapse-webcam' ) . '</button></form>';
		return $o;
	}

	/**
	 * Link relativo con i parametri vl_* (e l'ancora del blocco).
	 *
	 * @param array<string, string> $args Parametri.
	 * @param string                $id   Id del blocco.
	 */
	public static function link( array $args, string $id ): string {
		$args = array_filter( $args, static fn( $v ) => '' !== (string) $v );
		return esc_url( '?' . http_build_query( $args, '', '&' ) . '#' . $id );
	}

	/**
	 * Parametri da conservare nei link (webcam scelta).
	 *
	 * @param array<string, mixed>  $cam Webcam.
	 * @param string                $sel Selettore presente.
	 */
	public static function keep( array $cam, string $sel ): array {
		return '' !== $sel ? array( 'vl_cam' => (string) $cam['slug'] ) : array();
	}

	/**
	 * Testo meteo di una foto o del campione più recente.
	 *
	 * @param mixed $t Temperatura.
	 * @param mixed $u Umidità.
	 */
	public static function weather_text( $t, $u ): string {
		$parts = array();
		if ( is_numeric( $t ) ) {
			$parts[] = Format::num( $t, '°C' );
		}
		if ( is_numeric( $u ) ) {
			$parts[] = Format::num( $u, '%', 0 );
		}
		return implode( ' · ', $parts );
	}

	/**
	 * Distintivo dello stato.
	 *
	 * @param string $state Stato.
	 */
	public static function badge( string $state ): string {
		return '<span class="vl-state vl-state--' . esc_attr( sanitize_key( $state ) ) . '">' . esc_html( Format::state_label( $state ) ) . '</span>';
	}

	// ------------------------------------------------------------ foto attuale

	/**
	 * Foto attuale.
	 *
	 * @param array<string, mixed>  $attrs Attributi.
	 * @param array<string, string> $req   Scelte del visitatore.
	 * @param string                $id    Id.
	 */
	private static function foto( array $attrs, array $req, string $id ): string {
		$cam = self::camera( $attrs, $req, $sel, $id );
		if ( is_wp_error( $cam ) ) {
			return self::error( $cam );
		}
		$tz    = Format::tz( $cam );
		$photo = is_array( $cam['last_photo'] ?? null ) ? $cam['last_photo'] : null;
		$state = (string) ( $cam['state'] ?? '' );
		$max   = in_array( (int) $attrs['dimensione'], Format::SIZES, true ) ? (int) $attrs['dimensione'] : 1920;
		$name  = (string) ( $cam['name'] ?? '' );

		$archived = 'archived' === $state;
		$items    = ( Format::bool( $attrs['sponsor'] ?? true ) && true === self::has( $cam, 'sponsor' ) && ! $archived ) ? Api::overlay_items( (string) $cam['slug'] ) : null;
		$items    = is_array( $items ) ? $items : null;

		$o  = $sel;
		$o .= '<figure class="vl-foto__figure" data-vl-sig="' . esc_attr( ( $photo['id'] ?? 0 ) . '|' . $state ) . '"' . ( $archived ? ' data-vl-archived' : '' ) . '>';
		$o .= '<div class="vl-foto__media"' . ( $photo ? self::beacon_attrs( $cam ) : '' ) . '>';
		if ( $photo ) {
			/* translators: 1: nome della webcam, 2: data e ora */
			$alt = sprintf( __( 'Webcam %1$s, foto del %2$s', 'viewlapse-webcam' ), $name, Format::datetime( (int) $photo['time'], $tz ) );
			$o  .= Format::img_tag( $photo, $max, self::SIZES_LARGE, $alt, array( 'fetchpriority' => 'high' ) );
		} else {
			$o .= '<p class="vl-foto__empty">' . esc_html__( 'Nessuna foto disponibile.', 'viewlapse-webcam' ) . '</p>';
		}
		if ( $photo && $items ) {
			$o .= self::infopoints( (array) ( $items['infopoints'] ?? array() ) );
		}
		if ( Format::bool( $attrs['stato'] ) && '' !== $state ) {
			$o .= self::badge( $state );
		}
		$o .= '</div><figcaption class="vl-foto__caption">';
		if ( $archived && ! empty( $cam['archive_until'] ) ) {
			/* translators: %s: data */
			$o .= '<span class="vl-foto__archived">' . esc_html( sprintf( __( 'Noleggio concluso: archivio visibile fino al %s.', 'viewlapse-webcam' ), Format::date( (string) $cam['archive_until'], $tz ) ) ) . '</span> ';
		}
		if ( Format::bool( $attrs['titolo'] ) ) {
			$o .= '<span class="vl-foto__name">' . esc_html( $name ) . '</span>';
			if ( ! empty( $cam['location'] ) ) {
				$o .= ' <span class="vl-foto__place">' . esc_html( (string) $cam['location'] ) . '</span>';
			}
		}
		if ( $photo && Format::bool( $attrs['ora'] ) ) {
			$o .= ' <time class="vl-foto__time" datetime="' . esc_attr( (string) ( $photo['datetime'] ?? '' ) ) . '">' . esc_html( Format::datetime( (int) $photo['time'], $tz ) ) . '</time>';
		}
		if ( Format::bool( $attrs['meteo'] ) ) {
			$w   = is_array( $cam['weather'] ?? null ) ? $cam['weather'] : null;
			$txt = $w ? self::weather_text( $w['temperature_ext'] ?? null, $w['humidity_ext'] ?? null )
				: ( $photo ? self::weather_text( $photo['temperature_ext'] ?? null, $photo['humidity_ext'] ?? null ) : '' );
			if ( '' !== $txt ) {
				$o .= ' <span class="vl-weather" title="' . esc_attr__( 'Temperatura e umidità esterne', 'viewlapse-webcam' ) . '">' . esc_html( $txt ) . '</span>';
			}
		}
		if ( $photo && ! empty( $photo['images']['orig'] ) && self::can_download() ) {
			$o .= ' <a class="vl-download" href="' . esc_url( (string) $photo['images']['orig'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Scarica l\'originale', 'viewlapse-webcam' ) . '</a>';
		}
		$o .= '</figcaption></figure>';
		if ( $items ) {
			$o .= self::sponsors( (array) ( $items['sponsors'] ?? array() ) );
		}
		return $o;
	}

	/**
	 * Infopoint cliccabili sopra la foto (x_pct, y_pct dell'immagine intera).
	 *
	 * @param array<int, array<string, mixed>> $points Infopoint di overlay-items.
	 */
	private static function infopoints( array $points ): string {
		$o = '';
		foreach ( $points as $p ) {
			if ( ! is_numeric( $p['x_pct'] ?? null ) || ! is_numeric( $p['y_pct'] ?? null ) ) {
				continue;
			}
			$x     = max( 0, min( 100, (float) $p['x_pct'] ) );
			$y     = max( 0, min( 100, (float) $p['y_pct'] ) );
			$label = (string) ( $p['label'] ?? '' );
			$text  = trim( $label . ( empty( $p['text'] ) ? '' : ' – ' . (string) $p['text'] ) );
			$cls   = 'vl-info' . ( ! empty( $p['burned'] ) ? ' vl-info--burned' : '' );
			$style = 'left:' . number_format( $x, 2, '.', '' ) . '%;top:' . number_format( $y, 2, '.', '' ) . '%';
			$inner = '<span class="vl-info__dot" aria-hidden="true"></span>' . ( empty( $p['burned'] ) ? '<span class="vl-info__label">' . esc_html( $label ) . '</span>' : '<span class="screen-reader-text vl-sr">' . esc_html( $label ) . '</span>' );
			if ( ! empty( $p['url'] ) ) {
				$o .= '<a class="' . esc_attr( $cls ) . '" style="' . esc_attr( $style ) . '" href="' . esc_url( (string) $p['url'] ) . '" title="' . esc_attr( $text ) . '" target="_blank" rel="noopener">' . $inner . '</a>';
			} else {
				$o .= '<span class="' . esc_attr( $cls ) . '" style="' . esc_attr( $style ) . '" title="' . esc_attr( $text ) . '" tabindex="0">' . $inner . '</span>';
			}
		}
		return $o;
	}

	/**
	 * Sponsor sotto la foto (logo e link).
	 *
	 * @param array<int, array<string, mixed>> $list Sponsor di overlay-items.
	 */
	private static function sponsors( array $list ): string {
		if ( ! $list ) {
			return '';
		}
		$o = '<ul class="vl-sponsors" aria-label="' . esc_attr__( 'Sponsor', 'viewlapse-webcam' ) . '">';
		foreach ( $list as $sp ) {
			$label = (string) ( $sp['label'] ?? '' );
			$in    = ! empty( $sp['logo_url'] ) ? '<img src="' . esc_url( (string) $sp['logo_url'] ) . '" alt="" loading="lazy" decoding="async">' : '';
			$in   .= '<span class="vl-sponsor__label">' . esc_html( $label ) . '</span>';
			if ( ! empty( $sp['text'] ) ) {
				$in .= ' <span class="vl-sponsor__text">' . esc_html( (string) $sp['text'] ) . '</span>';
			}
			$o .= '<li class="vl-sponsor">' . ( ! empty( $sp['url'] ) ? '<a href="' . esc_url( (string) $sp['url'] ) . '" target="_blank" rel="sponsored noopener">' . $in . '</a>' : $in ) . '</li>';
		}
		return $o . '</ul>';
	}

	// ------------------------------------------------------------ archivio

	/**
	 * Archivio per giorno: calendario, griglia, visualizzatore.
	 *
	 * @param array<string, mixed>  $attrs Attributi.
	 * @param array<string, string> $req   Scelte del visitatore.
	 * @param string                $id    Id.
	 */
	private static function archivio( array $attrs, array $req, string $id ): string {
		$cam = self::camera( $attrs, $req, $sel, $id, 'archive' );
		if ( is_wp_error( $cam ) ) {
			return self::error( $cam );
		}
		if ( false === self::has( $cam, 'archive' ) ) {
			return self::unavailable( 'feature', 'archive', $sel );
		}
		$tz      = Format::tz( $cam );
		$slug    = (string) $cam['slug'];
		$today   = ( new \DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
		$archive = is_array( $cam['archive'] ?? null ) ? $cam['archive'] : null;
		if ( ! $archive || empty( $archive['last_day'] ) ) {
			return $sel . '<p class="vl-message">' . esc_html__( 'L\'archivio è ancora vuoto.', 'viewlapse-webcam' ) . '</p>';
		}
		$first = (string) $archive['first_day'];
		$last  = (string) $archive['last_day'];
		$date  = $req['date'] ?? $last;
		$date  = max( $first, min( $last, $date ) );
		$month = $req['month'] ?? substr( $date, 0, 7 );
		$month = max( substr( $first, 0, 7 ), min( substr( $last, 0, 7 ), $month ) );
		$keep  = self::keep( $cam, $sel );

		$days = Api::days( $slug, $month, substr( $today, 0, 7 ) === $month );
		$o    = $sel . '<div class="vl-archivio__layout">';
		$o   .= self::calendar( $month, is_wp_error( $days ) ? array() : (array) ( $days['days'] ?? array() ), $date, $first, $last, $keep, $id, $tz );

		$every  = max( 0, min( 1440, (int) $attrs['ogni'] ) );
		$photos = Api::photos( $slug, $date, $every, $date === $today );
		$o     .= '<div class="vl-archivio__day">';
		if ( is_wp_error( $photos ) ) {
			$o .= self::error( $photos );
		} else {
			$list = (array) ( $photos['photos'] ?? array() );
			$o   .= '<h3 class="vl-archivio__title">' . esc_html( Format::date( $date, $tz ) ) . ' <span class="vl-archivio__count">' . esc_html(
				sprintf(
					/* translators: %s: numero di foto */
					_n( '%s foto', '%s foto', count( $list ), 'viewlapse-webcam' ),
					number_format_i18n( count( $list ) )
				)
			) . '</span></h3>';
			if ( ! $list ) {
				$o .= '<p class="vl-message">' . esc_html__( 'Nessuna foto in questo giorno.', 'viewlapse-webcam' ) . '</p>';
			} else {
				$cols     = max( 1, min( 8, (int) $attrs['colonne'] ) );
				$download = self::can_download();
				$o       .= '<ul class="vl-grid" style="--vl-cols:' . esc_attr( (string) $cols ) . '" data-vl-gallery' . self::beacon_attrs( $cam ) . '>';
				foreach ( $list as $p ) {
					$full = Format::img( $p, 1920 );
					if ( null === $full ) {
						continue;
					}
					$time    = Format::time( (int) $p['time'], $tz );
					$weather = self::weather_text( $p['temperature_ext'] ?? null, $p['humidity_ext'] ?? null );
					$caption = Format::datetime( (int) $p['time'], $tz ) . ( '' !== $weather ? ' · ' . $weather : '' );
					/* translators: %s: ora */
					$alt = sprintf( __( 'Foto delle %s', 'viewlapse-webcam' ), $time );
					$o  .= '<li><a class="vl-thumb" href="' . esc_url( $full['src'] ) . '" data-vl-srcset="' . esc_attr( $full['srcset'] ) . '" data-vl-caption="' . esc_attr( $caption ) . '"';
					if ( $download && ! empty( $p['images']['orig'] ) ) {
						$o .= ' data-vl-orig="' . esc_url( (string) $p['images']['orig'] ) . '"';
					}
					$o .= '>' . Format::img_tag( $p, 640, '(min-width: 900px) 240px, 50vw', $alt, array( 'loading' => 'lazy' ) );
					$o .= '<span class="vl-thumb__time">' . esc_html( $time ) . '</span></a></li>';
				}
				$o .= '</ul>';
			}
		}
		$o .= '</div></div>';
		return $o;
	}

	/**
	 * Calendario di un mese con i giorni che hanno foto.
	 *
	 * @param string                           $month Mese AAAA-MM.
	 * @param array<int, array<string, mixed>> $days  Giorni con foto.
	 * @param string                           $date  Giorno scelto.
	 * @param string                           $first Primo giorno dell'archivio.
	 * @param string                           $last  Ultimo giorno.
	 * @param array<string, string>            $keep  Parametri da conservare.
	 * @param string                           $id    Id del blocco.
	 * @param \DateTimeZone                    $tz    Fuso.
	 */
	private static function calendar( string $month, array $days, string $date, string $first, string $last, array $keep, string $id, \DateTimeZone $tz ): string {
		global $wp_locale;
		$with = array();
		foreach ( $days as $d ) {
			$with[ (string) $d['date'] ] = (int) ( $d['photos'] ?? 0 );
		}
		$start = new \DateTimeImmutable( $month . '-01 12:00', $tz );
		$prev  = $start->modify( '-1 month' )->format( 'Y-m' );
		$next  = $start->modify( '+1 month' )->format( 'Y-m' );
		$title = ucfirst( (string) wp_date( 'F Y', $start->getTimestamp(), $tz ) );

		$o  = '<div class="vl-cal">';
		$o .= '<div class="vl-cal__head">';
		$o .= $prev >= substr( $first, 0, 7 )
			? '<a class="vl-cal__nav" href="' . self::link( $keep + array( 'vl_month' => $prev, 'vl_date' => $date ), $id ) . '" data-vl-nav aria-label="' . esc_attr__( 'Mese precedente', 'viewlapse-webcam' ) . '">&lsaquo;</a>'
			: '<span class="vl-cal__nav" aria-hidden="true"></span>';
		$o .= '<span class="vl-cal__title">' . esc_html( $title ) . '</span>';
		$o .= $next <= substr( $last, 0, 7 )
			? '<a class="vl-cal__nav" href="' . self::link( $keep + array( 'vl_month' => $next, 'vl_date' => $date ), $id ) . '" data-vl-nav aria-label="' . esc_attr__( 'Mese successivo', 'viewlapse-webcam' ) . '">&rsaquo;</a>'
			: '<span class="vl-cal__nav" aria-hidden="true"></span>';
		$o .= '</div><table class="vl-cal__table"><thead><tr>';

		$sow = (int) get_option( 'start_of_week', 1 );
		for ( $i = 0; $i < 7; $i++ ) {
			$wd = ( $sow + $i ) % 7;
			$o .= '<th scope="col" abbr="' . esc_attr( $wp_locale->get_weekday( $wd ) ) . '">' . esc_html( $wp_locale->get_weekday_initial( $wp_locale->get_weekday( $wd ) ) ) . '</th>';
		}
		$o .= '</tr></thead><tbody><tr>';

		$pad = ( (int) $start->format( 'w' ) - $sow + 7 ) % 7;
		$o  .= str_repeat( '<td></td>', $pad );
		$n   = (int) $start->format( 't' );
		for ( $d = 1; $d <= $n; $d++ ) {
			if ( $d > 1 && 0 === ( $pad + $d - 1 ) % 7 ) {
				$o .= '</tr><tr>';
			}
			$day = $month . '-' . sprintf( '%02d', $d );
			if ( isset( $with[ $day ] ) ) {
				$cur   = $day === $date ? ' aria-current="date"' : '';
				$label = sprintf(
					/* translators: 1: data, 2: numero di foto */
					_n( '%1$s, %2$s foto', '%1$s, %2$s foto', $with[ $day ], 'viewlapse-webcam' ),
					Format::date( $day, $tz ),
					number_format_i18n( $with[ $day ] )
				);
				$o .= '<td><a class="vl-cal__day" href="' . self::link( $keep + array( 'vl_date' => $day ), $id ) . '" data-vl-nav' . $cur . ' aria-label="' . esc_attr( $label ) . '">' . $d . '</a></td>';
			} else {
				$o .= '<td><span class="vl-cal__day vl-cal__day--empty">' . $d . '</span></td>';
			}
		}
		$o .= str_repeat( '<td></td>', ( 7 - ( $pad + $n ) % 7 ) % 7 );
		$o .= '</tr></tbody></table></div>';
		return $o;
	}

	// ------------------------------------------------------------ confronto

	/**
	 * Prima/dopo con cursore.
	 *
	 * @param array<string, mixed>  $attrs Attributi.
	 * @param array<string, string> $req   Scelte del visitatore.
	 * @param string                $id    Id.
	 */
	private static function confronto( array $attrs, array $req, string $id ): string {
		$cam = self::camera( $attrs, $req, $sel, $id, 'archive' );
		if ( is_wp_error( $cam ) ) {
			return self::error( $cam );
		}
		if ( false === self::has( $cam, 'archive' ) ) {
			return self::unavailable( 'feature', 'archive', $sel );
		}
		$tz      = Format::tz( $cam );
		$archive = is_array( $cam['archive'] ?? null ) ? $cam['archive'] : null;
		if ( ! $archive || empty( $archive['first_day'] ) ) {
			return $sel . '<p class="vl-message">' . esc_html__( 'L\'archivio è ancora vuoto.', 'viewlapse-webcam' ) . '</p>';
		}
		$choose = Format::bool( $attrs['scegli'] );
		$now    = new \DateTimeImmutable( 'now', $tz );
		$fix_a  = Format::valid_instant( (string) $attrs['a'] );
		$fix_b  = Format::valid_instant( (string) $attrs['b'] );
		$a      = ( $choose && isset( $req['a'] ) ) ? $req['a'] : ( $fix_a ?? $archive['first_day'] . 'T12:00' );
		// Adesso, arrotondato a 10 minuti (per la cache).
		$now_s = $now->setTime( (int) $now->format( 'H' ), (int) ( floor( (int) $now->format( 'i' ) / 10 ) * 10 ) )->format( 'Y-m-d\TH:i' );
		$b     = ( $choose && isset( $req['b'] ) ) ? $req['b'] : ( $fix_b ?? $now_s );
		$fixed = max( $a, $b ) < $now->modify( '-1 day' )->format( 'Y-m-d\TH:i' );

		$r = Api::compare( (string) $cam['slug'], $a, $b, $fixed );
		$o = $sel;
		if ( $choose ) {
			$min = $archive['first_day'] . 'T00:00';
			$max = $archive['last_day'] . 'T23:59';
			$o  .= '<form class="vl-cmp__form" method="get" action="#' . esc_attr( $id ) . '" data-vl-nav>';
			if ( '' !== $sel ) {
				$o .= '<input type="hidden" name="vl_cam" value="' . esc_attr( (string) $cam['slug'] ) . '">';
			}
			foreach ( array(
				'a' => array( __( 'Prima', 'viewlapse-webcam' ), $a ),
				'b' => array( __( 'Dopo', 'viewlapse-webcam' ), $b ),
			) as $k => [ $label, $val ] ) {
				$o .= '<label class="vl-cmp__field"><span>' . esc_html( $label ) . '</span> <input type="datetime-local" name="vl_' . $k . '" value="' . esc_attr( $val ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" step="60"></label> ';
			}
			$o .= '<button type="submit" class="vl-button">' . esc_html__( 'Confronta', 'viewlapse-webcam' ) . '</button></form>';
		}
		if ( is_wp_error( $r ) ) {
			return $o . self::error( $r );
		}
		$pa = is_array( $r['a']['photo'] ?? null ) ? $r['a']['photo'] : null;
		$pb = is_array( $r['b']['photo'] ?? null ) ? $r['b']['photo'] : null;
		if ( ! $pa || ! $pb ) {
			return $o . '<p class="vl-message">' . esc_html__( 'Nessuna foto vicina alle date scelte.', 'viewlapse-webcam' ) . '</p>';
		}
		$size = in_array( (int) $attrs['dimensione'], Format::SIZES, true ) ? (int) $attrs['dimensione'] : 1920;
		$la   = Format::datetime( (int) $pa['time'], $tz );
		$lb   = Format::datetime( (int) $pb['time'], $tz );
		$o   .= '<div class="vl-cmp" style="--vl-pos:50%"' . self::beacon_attrs( $cam ) . '>';
		/* translators: %s: data e ora */
		$o .= Format::img_tag( $pa, $size, self::SIZES_LARGE, sprintf( __( 'Prima: %s', 'viewlapse-webcam' ), $la ), array( 'class' => 'vl-cmp__img vl-cmp__img--a' ) );
		/* translators: %s: data e ora */
		$o .= '<div class="vl-cmp__after">' . Format::img_tag( $pb, $size, self::SIZES_LARGE, sprintf( __( 'Dopo: %s', 'viewlapse-webcam' ), $lb ), array( 'class' => 'vl-cmp__img vl-cmp__img--b' ) ) . '</div>';
		$o .= '<span class="vl-cmp__line" aria-hidden="true"></span>';
		$o .= '<span class="vl-cmp__label vl-cmp__label--a">' . esc_html( $la ) . '</span>';
		$o .= '<span class="vl-cmp__label vl-cmp__label--b">' . esc_html( $lb ) . '</span>';
		$o .= '<input class="vl-cmp__range" type="range" min="0" max="100" value="50" step="0.5" aria-label="' . esc_attr__( 'Sposta il cursore tra prima e dopo', 'viewlapse-webcam' ) . '">';
		$o .= '</div>';
		return $o;
	}

	// ------------------------------------------------------------ grafico

	/**
	 * Grafico di temperatura e umidità esterne.
	 *
	 * @param array<string, mixed>  $attrs Attributi.
	 * @param array<string, string> $req   Scelte del visitatore.
	 * @param string                $id    Id.
	 */
	private static function grafico( array $attrs, array $req, string $id ): string {
		$cam = self::camera( $attrs, $req, $sel, $id, 'archive' );
		if ( is_wp_error( $cam ) ) {
			return self::error( $cam );
		}
		if ( false === self::has( $cam, 'archive' ) ) {
			return self::unavailable( 'feature', 'archive', $sel );
		}
		$tz     = Format::tz( $cam );
		$choose = Format::bool( $attrs['scegli'] );
		$days   = max( 1, min( 400, (int) $attrs['giorni'] ) );
		if ( $choose && isset( $req['giorni'] ) ) {
			$days = (int) $req['giorni'];
		}
		$fields = array_intersect( array_map( 'trim', explode( ',', (string) $attrs['campi'] ) ), array( 'temperatura', 'umidita' ) );
		if ( ! $fields ) {
			$fields = array( 'temperatura', 'umidita' );
		}
		$temp = in_array( 'temperatura', $fields, true );
		$hum  = in_array( 'umidita', $fields, true );
		$to   = time();
		$from = (int) ( floor( ( $to - $days * DAY_IN_SECONDS ) / HOUR_IN_SECONDS ) * HOUR_IN_SECONDS );
		$api  = implode( ',', array_filter( array( $temp ? 'temperature_ext' : '', $hum ? 'humidity_ext' : '' ) ) );
		$r    = Api::series( (string) $cam['slug'], (string) $from, '', $api );

		$o = $sel;
		if ( $choose ) {
			$keep = self::keep( $cam, $sel );
			$o   .= '<nav class="vl-chart__range" aria-label="' . esc_attr__( 'Periodo', 'viewlapse-webcam' ) . '">';
			foreach ( array(
				1  => __( '24 ore', 'viewlapse-webcam' ),
				2  => __( '48 ore', 'viewlapse-webcam' ),
				7  => __( '7 giorni', 'viewlapse-webcam' ),
				30 => __( '30 giorni', 'viewlapse-webcam' ),
			) as $n => $label ) {
				$o .= '<a class="vl-button' . ( $n === $days ? ' is-active' : '' ) . '" href="' . self::link( $keep + array( 'vl_giorni' => (string) $n ), $id ) . '" data-vl-nav' . ( $n === $days ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
			}
			$o .= '</nav>';
		}
		if ( is_wp_error( $r ) ) {
			return $o . self::error( $r );
		}
		$points = (array) ( $r['points'] ?? array() );
		$o     .= '<div class="vl-chart__wrap">' . Chart::svg( $points, $temp, $hum, $from, $to, $tz, (int) $attrs['altezza'], $id . '-svg' ) . '</div>';
		$o     .= '<ul class="vl-chart__legend">';
		if ( $temp ) {
			$o .= '<li class="vl-chart__key vl-chart__key--temp">' . esc_html__( 'Temperatura esterna (media oraria, fascia minima–massima)', 'viewlapse-webcam' ) . '</li>';
		}
		if ( $hum ) {
			$o .= '<li class="vl-chart__key vl-chart__key--hum">' . esc_html__( 'Umidità esterna', 'viewlapse-webcam' ) . '</li>';
		}
		$o .= '</ul>';
		return $o;
	}

	// ------------------------------------------------------------ elenco e mappa

	/**
	 * Elenco delle webcam con mappa facoltativa.
	 *
	 * @param array<string, mixed> $attrs Attributi.
	 */
	private static function elenco( array $attrs ): string {
		$list = Api::webcams();
		if ( is_wp_error( $list ) ) {
			return self::error( $list );
		}
		if ( ! $list ) {
			return '<p class="vl-message">' . esc_html__( 'Nessuna webcam disponibile.', 'viewlapse-webcam' ) . '</p>';
		}
		$tpl    = (string) $attrs['link'];
		$cols   = max( 1, min( 6, (int) $attrs['colonne'] ) );
		$photo  = Format::bool( $attrs['foto'] );
		$o      = '';
		$points = array();
		$cards  = '';
		foreach ( $list as $c ) {
			$slug  = (string) $c['slug'];
			$tz    = Format::tz( $c );
			$url   = '' !== $tpl ? str_replace( '{slug}', rawurlencode( $slug ), $tpl ) : '';
			$last  = is_array( $c['last_photo'] ?? null ) ? $c['last_photo'] : null;
			$state = (string) ( $c['state'] ?? '' );

			$cards .= '<li class="vl-card">';
			if ( $photo ) {
				$img    = $last ? Format::img_tag( $last, 640, '(min-width: 900px) 33vw, 100vw', (string) $c['name'], array( 'loading' => 'lazy' ) ) : '<span class="vl-card__noimg">' . esc_html__( 'Nessuna foto', 'viewlapse-webcam' ) . '</span>';
				$cards .= '<div class="vl-card__media"' . ( $last ? self::beacon_attrs( $c ) : '' ) . '>' . ( '' !== $url ? '<a href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>' : $img ) . self::badge( $state ) . '</div>';
			}
			$name   = esc_html( (string) $c['name'] );
			$cards .= '<h3 class="vl-card__title">' . ( '' !== $url ? '<a href="' . esc_url( $url ) . '">' . $name . '</a>' : $name ) . '</h3>';
			$meta   = array();
			if ( ! empty( $c['location'] ) ) {
				$meta[] = '<span class="vl-card__place">' . esc_html( (string) $c['location'] ) . '</span>';
			}
			if ( ! $photo ) {
				$meta[] = self::badge( $state );
			}
			if ( $last ) {
				$meta[] = '<time datetime="' . esc_attr( (string) ( $last['datetime'] ?? '' ) ) . '">' . esc_html( Format::datetime( (int) $last['time'], $tz ) ) . '</time>';
			}
			$cards .= '<p class="vl-card__meta">' . implode( ' ', $meta ) . '</p></li>';

			if ( is_numeric( $c['latitude'] ?? null ) && is_numeric( $c['longitude'] ?? null ) ) {
				$thumb    = $last ? Format::img( $last, 320 ) : null;
				$points[] = array(
					'name'  => (string) $c['name'],
					'place' => (string) ( $c['location'] ?? '' ),
					'lat'   => (float) $c['latitude'],
					'lon'   => (float) $c['longitude'],
					'url'   => '' !== $url ? esc_url_raw( $url ) : '',
					'img'   => $thumb ? esc_url_raw( $thumb['src'] ) : '',
					'state' => Format::state_label( $state ),
				);
			}
		}
		if ( Format::bool( $attrs['mappa'] ) && $points ) {
			$s  = Settings::get();
			$o .= '<div class="vl-map" style="height:' . esc_attr( (string) max( 200, min( 900, (int) $attrs['altezza'] ) ) ) . 'px"'
				. ' data-vl-map="' . esc_attr( (string) wp_json_encode( $points ) ) . '"'
				. ' data-vl-tiles="' . esc_attr( (string) $s['tiles_url'] ) . '"'
				. ' data-vl-attribution="' . esc_attr( (string) $s['tiles_attribution'] ) . '"'
				. ' role="region" aria-label="' . esc_attr__( 'Mappa delle webcam', 'viewlapse-webcam' ) . '"></div>';
		}
		$o .= '<ul class="vl-list" style="--vl-cols:' . esc_attr( (string) $cols ) . '">' . $cards . '</ul>';
		return $o;
	}
}
