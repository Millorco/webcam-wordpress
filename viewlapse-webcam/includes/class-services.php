<?php
/**
 * HTML dei blocchi dei servizi (API dei siti 1.1, contratto 1.3).
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

use DateTimeImmutable;
use DateTimeZone;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Timelapse, stesso momento in giorni diversi, foto del giorno e, solo con
 * la chiave `client`, statistiche e documenti (rapporti mensili,
 * certificati e registro delle giornate di maltempo).
 *
 * Ogni blocco mostra solo quello che è nel campo `features` della webcam:
 * il plugin non ricalcola il listino. Con un portal che non manda
 * `features` (API dei siti 1.0) i blocchi si nascondono ai visitatori.
 */
final class Services {

	/** Tipi di timelapse: attributo `tipo` => kind del portal. */
	public const TL_KINDS = array(
		'giorno'    => 'day',
		'settimana' => 'week',
		'mese'      => 'month',
		'periodo'   => 'custom',
		'finale'    => 'final',
	);

	/** Funzione del listino che serve per ogni kind. */
	private const TL_FEATURE = array(
		'day'    => 'timelapse',
		'week'   => 'timelapse',
		'month'  => 'timelapse',
		'custom' => 'timelapse_custom',
		'final'  => 'final_film',
	);

	/** Passo dello stesso momento: attributo `passo` => step del portal. */
	public const STEPS = array(
		'giorno'    => 'day',
		'settimana' => 'week',
		'mese'      => 'month',
		'anno'      => 'year',
	);

	/**
	 * Webcam del blocco con il controllo della funzione.
	 *
	 * @param array<string, mixed>  $attrs   Attributi.
	 * @param array<string, string> $req     Scelte del visitatore.
	 * @param string                $id      Id del blocco.
	 * @param string                $feature Funzione.
	 * @param string|null           $sel     Selettore (uscita).
	 * @return array<string, mixed>|string Webcam, o HTML da mostrare al suo posto.
	 */
	private static function start( array $attrs, array $req, string $id, string $feature, ?string &$sel ) {
		$cam = Render::camera( $attrs, $req, $sel, $id, $feature );
		if ( is_wp_error( $cam ) ) {
			return Render::error( $cam );
		}
		$has = Render::has( $cam, $feature );
		if ( null === $has ) {
			return Render::unavailable( 'portal', $feature );
		}
		if ( false === $has ) {
			return Render::unavailable( 'feature', $feature, (string) $sel );
		}
		return $cam;
	}

	/**
	 * Oggi nel fuso della webcam.
	 *
	 * @param DateTimeZone $tz Fuso.
	 */
	private static function today( DateTimeZone $tz ): string {
		return ( new DateTimeImmutable( 'now', $tz ) )->format( 'Y-m-d' );
	}

	/**
	 * Ultimo giorno utile: oggi, o l'ultimo dell'archivio per le webcam archiviate.
	 *
	 * @param array<string, mixed> $cam Webcam.
	 * @param DateTimeZone         $tz  Fuso.
	 */
	private static function last_day( array $cam, DateTimeZone $tz ): string {
		$today = self::today( $tz );
		$last  = (string) ( $cam['archive']['last_day'] ?? '' );
		return ( 'archived' === ( $cam['state'] ?? '' ) && '' !== $last && $last < $today ) ? $last : $today;
	}

	/**
	 * Data spostata di N giorni.
	 *
	 * @param string       $date AAAA-MM-GG.
	 * @param int          $days Giorni (anche negativi).
	 * @param DateTimeZone $tz   Fuso.
	 */
	private static function shift( string $date, int $days, DateTimeZone $tz ): string {
		return ( new DateTimeImmutable( $date . ' 12:00', $tz ) )->modify( sprintf( '%+d days', $days ) )->format( 'Y-m-d' );
	}

	/**
	 * Mese leggibile (novembre 2026).
	 *
	 * @param string       $month AAAA-MM.
	 * @param DateTimeZone $tz    Fuso.
	 */
	private static function month_label( string $month, DateTimeZone $tz ): string {
		$d = date_create_immutable( $month . '-15 12:00', $tz );
		return $d ? ucfirst( (string) wp_date( 'F Y', $d->getTimestamp(), $tz ) ) : $month;
	}

	// ------------------------------------------------------------ timelapse

	/**
	 * Lettore dei timelapse (orizzontali e verticali) con elenco facoltativo.
	 *
	 * @param array<string, mixed>  $attrs Attributi.
	 * @param array<string, string> $req   Scelte del visitatore.
	 * @param string                $id    Id.
	 */
	public static function timelapse( array $attrs, array $req, string $id ): string {
		$cam = self::start( $attrs, $req, $id, 'timelapse', $sel );
		if ( ! is_array( $cam ) ) {
			return $cam;
		}
		$tz     = Format::tz( $cam );
		$slug   = (string) $cam['slug'];
		$list   = Format::bool( $attrs['elenco'] );
		$tipo   = (string) $attrs['tipo'];
		$fixed  = self::TL_KINDS[ $tipo ] ?? null;
		$kind   = $fixed ?? ( $list ? ( $req['tlk'] ?? null ) : null );
		$format = array(
			'orizzontale' => 'landscape',
			'verticale'   => 'portrait',
		)[ (string) $attrs['formato'] ] ?? null;

		if ( 'portrait' === $format && true !== Render::has( $cam, 'timelapse_portrait' ) ) {
			return Render::unavailable( 'feature', 'timelapse_portrait', (string) $sel );
		}
		if ( null !== $kind && true !== Render::has( $cam, self::TL_FEATURE[ $kind ] ) ) {
			if ( null !== $fixed ) {
				return Render::unavailable( 'feature', self::TL_FEATURE[ $kind ], (string) $sel );
			}
			$kind = null;
		}

		$o = (string) $sel;
		if ( $list && null === $fixed ) {
			$o .= self::tl_tabs( $cam, $kind, $sel, $id );
		}
		$r = Api::timelapses( $slug, $kind, $format, $list ? max( 1, min( 50, (int) $attrs['numero'] ) ) : 1 );
		if ( is_wp_error( $r ) ) {
			return Render::service_error( $r, 'timelapse', $o );
		}
		$items = array_values( array_filter( (array) ( $r['timelapses'] ?? array() ), static fn( $t ) => is_array( $t ) && ! empty( $t['video_url'] ) ) );
		if ( ! $items ) {
			return $o . '<p class="vl-message">' . esc_html__( 'Nessun timelapse ancora pronto.', 'viewlapse-webcam' ) . '</p>';
		}
		$cur = $items[0];
		if ( isset( $req['tl'] ) ) {
			foreach ( $items as $t ) {
				if ( (string) ( $t['id'] ?? '' ) === $req['tl'] ) {
					$cur = $t;
					break;
				}
			}
		}
		$o .= self::tl_player( $cam, $cur, $tz, Format::bool( $attrs['automatico'] ) );
		if ( $list && count( $items ) > 1 ) {
			$keep = Render::keep( $cam, (string) $sel ) + ( null !== $kind && null === $fixed ? array( 'vl_tlk' => $kind ) : array() );
			$o   .= '<ul class="vl-tl__list">';
			foreach ( $items as $t ) {
				$is   = ( $t['id'] ?? null ) === ( $cur['id'] ?? null );
				$port = 'portrait' === ( $t['format'] ?? '' );
				$o   .= '<li class="vl-tl__item' . ( $port ? ' vl-tl__item--portrait' : '' ) . '"><a class="vl-tl__link" href="' . Render::link( $keep + array( 'vl_tl' => (string) $t['id'] ), $id ) . '" data-vl-nav' . ( $is ? ' aria-current="true"' : '' ) . '>';
				if ( ! empty( $t['poster_url'] ) ) {
					$o .= '<img src="' . esc_url( (string) $t['poster_url'] ) . '" alt="" loading="lazy" decoding="async">';
				}
				$o .= '<span class="vl-tl__name">' . esc_html( self::tl_label( $t, $tz ) ) . '</span>';
				$o .= '<span class="vl-tl__meta">' . esc_html( Format::duration( $t['duration_s'] ?? 0 ) . ( $port ? ' · ' . __( 'verticale', 'viewlapse-webcam' ) : '' ) ) . '</span>';
				$o .= '</a></li>';
			}
			$o .= '</ul>';
		}
		return $o;
	}

	/**
	 * Schede per tipo di timelapse (solo i tipi delle funzioni attive).
	 *
	 * @param array<string, mixed> $cam  Webcam.
	 * @param string|null          $kind Tipo scelto.
	 * @param string|null          $sel  Selettore.
	 * @param string               $id   Id.
	 */
	private static function tl_tabs( array $cam, ?string $kind, ?string $sel, string $id ): string {
		$labels = array(
			''       => __( 'Tutti', 'viewlapse-webcam' ),
			'day'    => __( 'Giorno', 'viewlapse-webcam' ),
			'week'   => __( 'Settimana', 'viewlapse-webcam' ),
			'month'  => __( 'Mese', 'viewlapse-webcam' ),
			'custom' => __( 'Periodi', 'viewlapse-webcam' ),
			'final'  => __( 'Filmato finale', 'viewlapse-webcam' ),
		);
		$keep = Render::keep( $cam, (string) $sel );
		$o    = '<nav class="vl-tl__tabs" aria-label="' . esc_attr__( 'Tipo di timelapse', 'viewlapse-webcam' ) . '">';
		foreach ( $labels as $k => $label ) {
			if ( '' !== $k && true !== Render::has( $cam, self::TL_FEATURE[ $k ] ) ) {
				continue;
			}
			$on = (string) $kind === $k;
			$o .= '<a class="vl-button' . ( $on ? ' is-active' : '' ) . '" href="' . Render::link( $keep + array( 'vl_tlk' => $k ), $id ) . '" data-vl-nav' . ( $on ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		return $o . '</nav>';
	}

	/**
	 * Lettore video di un timelapse.
	 *
	 * @param array<string, mixed> $cam  Webcam.
	 * @param array<string, mixed> $t    Timelapse.
	 * @param DateTimeZone         $tz   Fuso.
	 * @param bool                 $auto Avvio automatico (senza audio, in ciclo).
	 */
	private static function tl_player( array $cam, array $t, DateTimeZone $tz, bool $auto ): string {
		$w     = max( 1, (int) ( $t['width'] ?? 1920 ) );
		$h     = max( 1, (int) ( $t['height'] ?? 1080 ) );
		$port  = $h > $w;
		$label = self::tl_label( $t, $tz );
		$o     = '<figure class="vl-tl__figure' . ( $port ? ' vl-tl__figure--portrait' : '' ) . '">';
		$o    .= '<div class="vl-tl__player" style="aspect-ratio:' . $w . ' / ' . $h . '"' . Render::beacon_attrs( $cam ) . '>';
		$o    .= '<video class="vl-tl__video" controls playsinline preload="' . ( $auto ? 'auto' : 'metadata' ) . '" width="' . $w . '" height="' . $h . '"';
		if ( ! empty( $t['poster_url'] ) ) {
			$o .= ' poster="' . esc_url( (string) $t['poster_url'] ) . '"';
		}
		if ( $auto ) {
			$o .= ' autoplay muted loop';
		}
		/* translators: %s: periodo del timelapse */
		$o .= ' aria-label="' . esc_attr( sprintf( __( 'Timelapse: %s', 'viewlapse-webcam' ), $label ) ) . '">';
		$o .= '<source src="' . esc_url( (string) $t['video_url'] ) . '" type="video/mp4">';
		$o .= '<a href="' . esc_url( (string) $t['video_url'] ) . '">' . esc_html__( 'Guarda il video', 'viewlapse-webcam' ) . '</a>';
		$o .= '</video></div><figcaption class="vl-tl__caption"><span class="vl-tl__title">' . esc_html( $label ) . '</span>';
		$meta = array( Format::duration( $t['duration_s'] ?? 0 ) );
		if ( ! empty( $t['frames'] ) && is_numeric( $t['frames'] ) ) {
			/* translators: %s: numero di fotogrammi */
			$meta[] = sprintf( _n( '%s fotogramma', '%s fotogrammi', (int) $t['frames'], 'viewlapse-webcam' ), number_format_i18n( (int) $t['frames'] ) );
		}
		$o .= ' <span class="vl-tl__meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
		if ( Render::can_download() ) {
			$size = Format::bytes( $t['size_bytes'] ?? 0 );
			/* translators: %s: dimensione del file */
			$o .= ' <a class="vl-download" href="' . esc_url( (string) $t['video_url'] ) . '" target="_blank" rel="noopener">' . esc_html( '' !== $size ? sprintf( __( 'Scarica il video (MP4, %s)', 'viewlapse-webcam' ), $size ) : __( 'Scarica il video', 'viewlapse-webcam' ) ) . '</a>';
		}
		return $o . '</figcaption></figure>';
	}

	/**
	 * Nome di un timelapse dal tipo e dal periodo.
	 *
	 * @param array<string, mixed> $t  Timelapse.
	 * @param DateTimeZone         $tz Fuso.
	 */
	private static function tl_label( array $t, DateTimeZone $tz ): string {
		$from = (string) ( $t['period_from'] ?? '' );
		$to   = (string) ( $t['period_to'] ?? $from );
		$df   = '' !== $from ? Format::date( $from, $tz ) : '';
		$dt   = '' !== $to ? Format::date( $to, $tz ) : '';
		switch ( (string) ( $t['kind'] ?? '' ) ) {
			case 'day':
				return $df;
			case 'week':
				/* translators: 1: primo giorno, 2: ultimo giorno */
				return sprintf( __( 'Settimana dal %1$s al %2$s', 'viewlapse-webcam' ), $df, $dt );
			case 'month':
				return self::month_label( substr( $from, 0, 7 ), $tz );
			case 'final':
				/* translators: 1: primo giorno, 2: ultimo giorno */
				return sprintf( __( 'Filmato finale, dal %1$s al %2$s', 'viewlapse-webcam' ), $df, $dt );
			default:
				/* translators: 1: primo giorno, 2: ultimo giorno */
				return $from === $to ? $df : sprintf( __( 'Dal %1$s al %2$s', 'viewlapse-webcam' ), $df, $dt );
		}
	}

	// ------------------------------------------------------------ stesso momento

	/**
	 * Stesso momento in giorni diversi: griglia o sfoglia con il cursore.
	 *
	 * @param array<string, mixed>  $attrs Attributi.
	 * @param array<string, string> $req   Scelte del visitatore.
	 * @param string                $id    Id.
	 */
	public static function momento( array $attrs, array $req, string $id ): string {
		$cam = self::start( $attrs, $req, $id, 'same_moment', $sel );
		if ( ! is_array( $cam ) ) {
			return $cam;
		}
		$tz      = Format::tz( $cam );
		$archive = is_array( $cam['archive'] ?? null ) ? $cam['archive'] : null;
		if ( ! $archive || empty( $archive['first_day'] ) ) {
			return $sel . '<p class="vl-message">' . esc_html__( 'L\'archivio è ancora vuoto.', 'viewlapse-webcam' ) . '</p>';
		}
		$choose = Format::bool( $attrs['scegli'] );
		$time   = Format::valid_time( (string) $attrs['ora'] ) ?? '12:00';
		$step   = self::STEPS[ (string) $attrs['passo'] ] ?? 'day';
		$days   = max( 1, min( 3660, (int) $attrs['giorni'] ) );
		if ( $choose ) {
			$time = $req['ora'] ?? $time;
			$step = $req['passo'] ?? $step;
			$days = isset( $req['giorni'] ) ? (int) $req['giorni'] : $days;
		}
		// Al più 400 elementi (limite del portal).
		$per  = array(
			'day'   => 1,
			'week'  => 7,
			'month' => 28,
			'year'  => 365,
		)[ $step ];
		$days = min( $days, 399 * $per + 1 );
		$to   = min( self::last_day( $cam, $tz ), (string) ( $archive['last_day'] ?? '9999' ) );
		$from = max( (string) $archive['first_day'], self::shift( $to, 1 - $days, $tz ) );
		$past = $to < self::today( $tz );

		$o = (string) $sel;
		if ( $choose ) {
			$o .= self::momento_form( $cam, (string) $sel, $id, $time, $step, $days );
		}
		$r = Api::same_moment( (string) $cam['slug'], $time, $from, $to, $step, Format::bool( $attrs['buone'] ), $past );
		if ( is_wp_error( $r ) ) {
			return Render::service_error( $r, 'same_moment', $o );
		}
		$items = array_values( array_filter( (array) ( $r['items'] ?? array() ), static fn( $i ) => is_array( $i ) && is_array( $i['photo'] ?? null ) ) );
		$total = count( (array) ( $r['items'] ?? array() ) );
		if ( ! $items ) {
			return $o . '<p class="vl-message">' . esc_html__( 'Nessuna foto vicina all\'ora scelta in questo periodo.', 'viewlapse-webcam' ) . '</p>';
		}
		$o .= '<p class="vl-momento__count">' . esc_html(
			sprintf(
				/* translators: 1: ora, 2: giorni con foto, 3: giorni del periodo */
				__( 'Ore %1$s: %2$s foto su %3$s giorni.', 'viewlapse-webcam' ),
				$time,
				number_format_i18n( count( $items ) ),
				number_format_i18n( $total )
			)
		) . '</p>';

		$frames = array();
		$grid   = '';
		$cols   = max( 1, min( 8, (int) $attrs['colonne'] ) );
		foreach ( $items as $it ) {
			$p    = (array) $it['photo'];
			$full = Format::img( $p, 1920 );
			if ( null === $full ) {
				continue;
			}
			$date    = Format::date( (string) ( $it['date'] ?? '' ), $tz );
			$caption = Format::datetime( (int) $p['time'], $tz );
			$weather = Render::weather_text( $p['temperature_ext'] ?? null, $p['humidity_ext'] ?? null );
			if ( '' !== $weather ) {
				$caption .= ' · ' . $weather;
			}
			$frames[] = array(
				'src'    => esc_url_raw( $full['src'] ),
				'srcset' => $full['srcset'],
				'label'  => $caption,
			);
			$grid .= '<li><a class="vl-thumb" href="' . esc_url( $full['src'] ) . '" data-vl-srcset="' . esc_attr( $full['srcset'] ) . '" data-vl-caption="' . esc_attr( $caption ) . '">'
				. Format::img_tag( $p, 640, '(min-width: 900px) 200px, 50vw', $caption, array( 'loading' => 'lazy' ) )
				. '<span class="vl-thumb__time">' . esc_html( $date ) . '</span></a></li>';
		}
		if ( 'sfoglia' === (string) $attrs['vista'] && count( $frames ) > 1 ) {
			$last = count( $frames ) - 1;
			$p    = (array) $items[ $last ]['photo'];
			$o   .= '<div class="vl-flip"' . Render::beacon_attrs( $cam ) . ' data-vl-frames="' . esc_attr( (string) wp_json_encode( $frames ) ) . '">';
			$o   .= '<div class="vl-flip__media">' . Format::img_tag( $p, 1920, Render::SIZES_LARGE, $frames[ $last ]['label'], array( 'class' => 'vl-flip__img' ) ) . '</div>';
			$o   .= '<div class="vl-flip__bar"><button type="button" class="vl-button vl-flip__play" aria-pressed="false">' . esc_html__( 'Riproduci', 'viewlapse-webcam' ) . '</button>';
			$o   .= '<input class="vl-flip__range" type="range" min="0" max="' . $last . '" value="' . $last . '" step="1" aria-label="' . esc_attr__( 'Scorri i giorni', 'viewlapse-webcam' ) . '">';
			$o   .= '<output class="vl-flip__label" aria-live="polite">' . esc_html( $frames[ $last ]['label'] ) . '</output></div></div>';
			$o   .= '<ul class="vl-grid vl-grid--fallback" style="--vl-cols:' . $cols . '" data-vl-gallery>' . $grid . '</ul>';
		} else {
			$o .= '<ul class="vl-grid" style="--vl-cols:' . $cols . '" data-vl-gallery' . Render::beacon_attrs( $cam ) . '>' . $grid . '</ul>';
		}
		return $o;
	}

	/**
	 * Modulo dello stesso momento (ora, passo, periodo).
	 *
	 * @param array<string, mixed> $cam  Webcam.
	 * @param string               $sel  Selettore.
	 * @param string               $id   Id.
	 * @param string               $time Ora.
	 * @param string               $step Passo.
	 * @param int                  $days Giorni.
	 */
	private static function momento_form( array $cam, string $sel, string $id, string $time, string $step, int $days ): string {
		$o = '<form class="vl-cmp__form" method="get" action="#' . esc_attr( $id ) . '" data-vl-nav>';
		if ( '' !== $sel ) {
			$o .= '<input type="hidden" name="vl_cam" value="' . esc_attr( (string) $cam['slug'] ) . '">';
		}
		$o .= '<label class="vl-cmp__field"><span>' . esc_html__( 'Ora', 'viewlapse-webcam' ) . '</span> <input type="time" name="vl_ora" value="' . esc_attr( $time ) . '" step="300"></label> ';
		$o .= '<label class="vl-cmp__field"><span>' . esc_html__( 'Ogni', 'viewlapse-webcam' ) . '</span> <select name="vl_passo">';
		foreach ( array(
			'day'   => __( 'giorno', 'viewlapse-webcam' ),
			'week'  => __( 'settimana', 'viewlapse-webcam' ),
			'month' => __( 'mese', 'viewlapse-webcam' ),
			'year'  => __( 'anno', 'viewlapse-webcam' ),
		) as $k => $label ) {
			$o .= '<option value="' . esc_attr( $k ) . '"' . selected( $k, $step, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$o      .= '</select></label> <label class="vl-cmp__field"><span>' . esc_html__( 'Periodo', 'viewlapse-webcam' ) . '</span> <select name="vl_giorni">';
		$periods = array(
			7    => __( '7 giorni', 'viewlapse-webcam' ),
			30   => __( '30 giorni', 'viewlapse-webcam' ),
			90   => __( '3 mesi', 'viewlapse-webcam' ),
			365  => __( '1 anno', 'viewlapse-webcam' ),
			1095 => __( '3 anni', 'viewlapse-webcam' ),
		);
		if ( ! isset( $periods[ $days ] ) ) {
			/* translators: %s: numero di giorni */
			$periods[ $days ] = sprintf( __( '%s giorni', 'viewlapse-webcam' ), number_format_i18n( $days ) );
			ksort( $periods );
		}
		foreach ( $periods as $n => $label ) {
			$o .= '<option value="' . esc_attr( (string) $n ) . '"' . selected( $n, $days, false ) . '>' . esc_html( $label ) . '</option>';
		}
		return $o . '</select></label> <button type="submit" class="vl-button">' . esc_html__( 'Mostra', 'viewlapse-webcam' ) . '</button></form>';
	}

	// ------------------------------------------------------------ foto del giorno

	/**
	 * Foto del giorno con i formati per i social e le foto del mese.
	 *
	 * @param array<string, mixed>  $attrs Attributi.
	 * @param array<string, string> $req   Scelte del visitatore.
	 * @param string                $id    Id.
	 */
	public static function fotogiorno( array $attrs, array $req, string $id ): string {
		$cam = self::start( $attrs, $req, $id, 'photo_of_day', $sel );
		if ( ! is_array( $cam ) ) {
			return $cam;
		}
		$tz    = Format::tz( $cam );
		$slug  = (string) $cam['slug'];
		$today = self::today( $tz );
		$date  = $req['date'] ?? null;
		if ( null !== $date && $date > $today ) {
			$date = null;
		}
		$r = Api::photo_of_day( $slug, $date, null !== $date && $date < self::shift( $today, -1, $tz ) );
		if ( is_wp_error( $r ) ) {
			return Render::service_error( $r, 'photo_of_day', (string) $sel );
		}
		$day   = Format::valid_date( $r['date'] ?? null ) ?? ( $date ?? $today );
		$photo = is_array( $r['photo'] ?? null ) ? $r['photo'] : null;
		$max   = in_array( (int) $attrs['dimensione'], Format::SIZES, true ) ? (int) $attrs['dimensione'] : 1920;
		$name  = (string) ( $cam['name'] ?? '' );

		$o  = (string) $sel . '<figure class="vl-pod__figure">';
		$o .= '<div class="vl-foto__media"' . ( $photo ? Render::beacon_attrs( $cam ) : '' ) . '>';
		if ( $photo ) {
			/* translators: 1: nome della webcam, 2: data */
			$o .= Format::img_tag( $photo, $max, Render::SIZES_LARGE, sprintf( __( 'Webcam %1$s, foto del giorno %2$s', 'viewlapse-webcam' ), $name, Format::date( $day, $tz ) ) );
		} else {
			$o .= '<p class="vl-foto__empty">' . esc_html__( 'Nessuna foto del giorno per questa data.', 'viewlapse-webcam' ) . '</p>';
		}
		$o .= '</div><figcaption class="vl-foto__caption"><span class="vl-foto__name">' . esc_html(
			/* translators: %s: data */
			sprintf( __( 'Foto del giorno, %s', 'viewlapse-webcam' ), Format::date( $day, $tz ) )
		) . '</span>';
		if ( $photo ) {
			$o .= ' <time class="vl-foto__time" datetime="' . esc_attr( (string) ( $photo['datetime'] ?? '' ) ) . '">' . esc_html( Format::time( (int) $photo['time'], $tz ) ) . '</time>';
			$w  = Render::weather_text( $photo['temperature_ext'] ?? null, $photo['humidity_ext'] ?? null );
			if ( '' !== $w ) {
				$o .= ' <span class="vl-weather">' . esc_html( $w ) . '</span>';
			}
			if ( ! empty( $photo['images']['orig'] ) && Render::can_download() ) {
				$o .= ' <a class="vl-download" href="' . esc_url( (string) $photo['images']['orig'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Scarica l\'originale', 'viewlapse-webcam' ) . '</a>';
			}
		}
		$o     .= '</figcaption></figure>';
		$social = $photo && is_array( $photo['social'] ?? null ) ? $photo['social'] : array();
		if ( Format::bool( $attrs['social'] ) && $social ) {
			$o .= '<p class="vl-pod__social"><span>' . esc_html__( 'Per i social:', 'viewlapse-webcam' ) . '</span> ';
			foreach ( array(
				'square'   => __( 'quadrata 1080 × 1080', 'viewlapse-webcam' ),
				'portrait' => __( 'verticale 1080 × 1350', 'viewlapse-webcam' ),
				'story'    => __( 'storia 1080 × 1920', 'viewlapse-webcam' ),
			) as $k => $label ) {
				if ( ! empty( $social[ $k ] ) ) {
					$o .= '<a class="vl-button" href="' . esc_url( (string) $social[ $k ] ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a> ';
				}
			}
			$o .= '</p>';
		}
		if ( Format::bool( $attrs['mese'] ) ) {
			$o .= self::pod_month( $cam, $req, $day, $tz, (string) $sel, $id );
		}
		return $o;
	}

	/**
	 * Foto del giorno di un mese, con i link ai giorni.
	 *
	 * @param array<string, mixed>  $cam Webcam.
	 * @param array<string, string> $req Scelte.
	 * @param string                $day Giorno mostrato.
	 * @param DateTimeZone          $tz  Fuso.
	 * @param string                $sel Selettore.
	 * @param string                $id  Id.
	 */
	private static function pod_month( array $cam, array $req, string $day, DateTimeZone $tz, string $sel, string $id ): string {
		$today = self::today( $tz );
		$first = substr( (string) ( $cam['archive']['first_day'] ?? $day ), 0, 7 );
		$last  = substr( self::last_day( $cam, $tz ), 0, 7 );
		$month = $req['month'] ?? substr( $day, 0, 7 );
		$month = max( $first, min( $last, $month ) );
		$r     = Api::photos_of_day( (string) $cam['slug'], $month, substr( $today, 0, 7 ) === $month );
		if ( is_wp_error( $r ) ) {
			return Render::error( $r );
		}
		$keep  = Render::keep( $cam, $sel );
		$start = new DateTimeImmutable( $month . '-01 12:00', $tz );
		$prev  = $start->modify( '-1 month' )->format( 'Y-m' );
		$next  = $start->modify( '+1 month' )->format( 'Y-m' );
		$o     = '<div class="vl-pod__month"><div class="vl-cal__head">';
		$o    .= $prev >= $first ? '<a class="vl-cal__nav" href="' . Render::link( $keep + array( 'vl_month' => $prev, 'vl_date' => $day ), $id ) . '" data-vl-nav aria-label="' . esc_attr__( 'Mese precedente', 'viewlapse-webcam' ) . '">&lsaquo;</a>' : '<span class="vl-cal__nav" aria-hidden="true"></span>';
		$o    .= '<h3 class="vl-cal__title">' . esc_html( self::month_label( $month, $tz ) ) . '</h3>';
		$o    .= $next <= $last ? '<a class="vl-cal__nav" href="' . Render::link( $keep + array( 'vl_month' => $next, 'vl_date' => $day ), $id ) . '" data-vl-nav aria-label="' . esc_attr__( 'Mese successivo', 'viewlapse-webcam' ) . '">&rsaquo;</a>' : '<span class="vl-cal__nav" aria-hidden="true"></span>';
		$o    .= '</div>';
		$days  = array_filter( (array) ( $r['days'] ?? array() ), static fn( $d ) => is_array( $d ) && is_array( $d['photo'] ?? null ) );
		if ( ! $days ) {
			return $o . '<p class="vl-message">' . esc_html__( 'Nessuna foto del giorno in questo mese.', 'viewlapse-webcam' ) . '</p></div>';
		}
		$o .= '<ul class="vl-grid" style="--vl-cols:6">';
		foreach ( $days as $d ) {
			$date = (string) $d['date'];
			$cur  = $date === $day ? ' aria-current="date"' : '';
			$o   .= '<li><a class="vl-thumb" href="' . Render::link( $keep + array( 'vl_date' => $date, 'vl_month' => $month ), $id ) . '" data-vl-nav' . $cur . '>'
				. Format::img_tag( (array) $d['photo'], 320, '160px', Format::date( $date, $tz ), array( 'loading' => 'lazy' ) )
				. '<span class="vl-thumb__time">' . esc_html( (string) (int) substr( $date, 8, 2 ) ) . '</span></a></li>';
		}
		return $o . '</ul></div>';
	}

	// ------------------------------------------------------------ statistiche (client)

	/**
	 * Statistiche di visualizzazione della webcam (solo chiave client).
	 *
	 * @param array<string, mixed>  $attrs Attributi.
	 * @param array<string, string> $req   Scelte del visitatore.
	 * @param string                $id    Id.
	 */
	public static function statistiche( array $attrs, array $req, string $id ): string {
		if ( 'client' !== Api::scope() ) {
			return Render::unavailable( 'scope', 'stats' );
		}
		$cam = self::start( $attrs, $req, $id, 'stats', $sel );
		if ( ! is_array( $cam ) ) {
			return $cam;
		}
		if ( 'archived' === ( $cam['state'] ?? '' ) ) {
			return Render::unavailable( 'archived', 'stats', (string) $sel );
		}
		$tz     = Format::tz( $cam );
		$choose = Format::bool( $attrs['scegli'] );
		$days   = max( 1, min( 400, (int) $attrs['giorni'] ) );
		if ( $choose && isset( $req['giorni'] ) ) {
			$days = min( 400, (int) $req['giorni'] );
		}
		$to    = self::today( $tz );
		$from  = self::shift( $to, 1 - $days, $tz );
		$group = $days > 92 ? 'month' : 'day';

		$o = (string) $sel;
		if ( $choose ) {
			$keep = Render::keep( $cam, (string) $sel );
			$o   .= '<nav class="vl-chart__range" aria-label="' . esc_attr__( 'Periodo', 'viewlapse-webcam' ) . '">';
			foreach ( array(
				7   => __( '7 giorni', 'viewlapse-webcam' ),
				30  => __( '30 giorni', 'viewlapse-webcam' ),
				90  => __( '90 giorni', 'viewlapse-webcam' ),
				365 => __( '12 mesi', 'viewlapse-webcam' ),
			) as $n => $label ) {
				$o .= '<a class="vl-button' . ( $n === $days ? ' is-active' : '' ) . '" href="' . Render::link( $keep + array( 'vl_giorni' => (string) $n ), $id ) . '" data-vl-nav' . ( $n === $days ? ' aria-current="true"' : '' ) . '>' . esc_html( $label ) . '</a>';
			}
			$o .= '</nav>';
		}
		$r = Api::stats( (string) $cam['slug'], $from, $to, $group );
		if ( is_wp_error( $r ) ) {
			return Render::service_error( $r, 'stats', $o );
		}
		$tot   = (array) ( $r['totals'] ?? array() );
		$n     = static fn( $k ) => number_format_i18n( (int) ( $tot[ $k ] ?? 0 ) );
		$tiles = array(
			array( __( 'Visualizzazioni', 'viewlapse-webcam' ), $n( 'views' ), __( 'pagine con la webcam (siti e pannello)', 'viewlapse-webcam' ) ),
			array( __( 'Visitatori', 'viewlapse-webcam' ), $n( 'visitors' ), __( 'distinti per giorno', 'viewlapse-webcam' ) ),
			array( __( 'Tempo medio', 'viewlapse-webcam' ), Format::duration( $tot['avg_view_s'] ?? 0 ), __( 'di visione', 'viewlapse-webcam' ) ),
			array( __( 'Timelapse avviati', 'viewlapse-webcam' ), $n( 'timelapse_plays' ), '' ),
			array( __( 'Foto aperte', 'viewlapse-webcam' ), $n( 'photo_opens' ), __( 'a schermo intero', 'viewlapse-webcam' ) ),
			array( __( 'Richieste dell\'immagine', 'viewlapse-webcam' ), $n( 'image_requests' ), __( 'Windy e siti che la incorporano', 'viewlapse-webcam' ) ),
		);
		if ( (int) ( $tot['telegram'] ?? 0 ) > 0 || true === Render::has( $cam, 'telegram' ) ) {
			$tiles[] = array( __( 'Telegram', 'viewlapse-webcam' ), $n( 'telegram' ), __( 'comandi /foto e /meteo', 'viewlapse-webcam' ) );
		}
		$o .= '<ul class="vl-stats__tiles">';
		foreach ( $tiles as [ $label, $value, $hint ] ) {
			$o .= '<li class="vl-stats__tile"><span class="vl-stats__label">' . esc_html( $label ) . '</span><strong class="vl-stats__value">' . esc_html( $value ) . '</strong>' . ( '' !== $hint ? '<span class="vl-stats__hint">' . esc_html( $hint ) . '</span>' : '' ) . '</li>';
		}
		$o     .= '</ul>';
		$series = array_values( array_filter( (array) ( $r['series'] ?? array() ), 'is_array' ) );
		$o     .= '<div class="vl-chart__wrap">' . self::bars( $series, $group, $tz, max( 140, min( 500, (int) $attrs['altezza'] ) ), $id . '-svg' ) . '</div>';
		$o     .= '<ul class="vl-chart__legend"><li class="vl-stats__key vl-stats__key--views">' . esc_html__( 'Visualizzazioni', 'viewlapse-webcam' ) . '</li><li class="vl-stats__key vl-stats__key--visitors">' . esc_html__( 'Visitatori', 'viewlapse-webcam' ) . '</li></ul>';

		if ( $series ) {
			$o .= '<details class="vl-stats__details"><summary>' . esc_html__( 'Tabella dei dati', 'viewlapse-webcam' ) . '</summary><div class="vl-table__wrap"><table class="vl-table"><thead><tr>';
			$o .= '<th scope="col">' . esc_html( 'month' === $group ? __( 'Mese', 'viewlapse-webcam' ) : __( 'Giorno', 'viewlapse-webcam' ) ) . '</th><th scope="col">' . esc_html__( 'Visualizzazioni', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Visitatori', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Tempo di visione', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Timelapse', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Immagine', 'viewlapse-webcam' ) . '</th></tr></thead><tbody>';
			foreach ( $series as $p ) {
				$o .= '<tr><td>' . esc_html( self::period_label( $p, $group, $tz ) ) . '</td><td>' . esc_html( number_format_i18n( (int) ( $p['views'] ?? 0 ) ) ) . '</td><td>' . esc_html( number_format_i18n( (int) ( $p['visitors'] ?? 0 ) ) ) . '</td><td>' . esc_html( Format::duration( $p['view_seconds'] ?? 0 ) ) . '</td><td>' . esc_html( number_format_i18n( (int) ( $p['timelapse_plays'] ?? 0 ) ) ) . '</td><td>' . esc_html( number_format_i18n( (int) ( $p['image_requests'] ?? 0 ) ) ) . '</td></tr>';
			}
			$o .= '</tbody></table></div></details>';
		}
		$hosts = array_values( array_filter( (array) ( $r['hosts'] ?? array() ), 'is_array' ) );
		if ( $hosts ) {
			$o .= '<h3 class="vl-stats__title">' . esc_html__( 'Siti che mostrano la webcam', 'viewlapse-webcam' ) . '</h3><div class="vl-table__wrap"><table class="vl-table"><thead><tr><th scope="col">' . esc_html__( 'Sito', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Visualizzazioni', 'viewlapse-webcam' ) . '</th></tr></thead><tbody>';
			foreach ( $hosts as $h ) {
				$host = (string) ( $h['host'] ?? '-' );
				$o   .= '<tr><td>' . esc_html( '-' === $host ? __( '(sconosciuto)', 'viewlapse-webcam' ) : ( 'altri' === $host ? __( '(altri)', 'viewlapse-webcam' ) : $host ) ) . '</td><td>' . esc_html( number_format_i18n( (int) ( $h['views'] ?? 0 ) ) ) . '</td></tr>';
			}
			$o .= '</tbody></table></div>';
		}
		$o .= '<p class="vl-message vl-stats__note">' . esc_html__( 'Contatori aggregati, raccolti senza cookie e senza conservare dati personali. Il giorno in corso è parziale.', 'viewlapse-webcam' ) . '</p>';
		return $o;
	}

	/**
	 * Etichetta di un punto della serie.
	 *
	 * @param array<string, mixed> $p     Punto.
	 * @param string               $group day o month.
	 * @param DateTimeZone         $tz    Fuso.
	 */
	private static function period_label( array $p, string $group, DateTimeZone $tz ): string {
		$d = (string) ( $p['date'] ?? $p['month'] ?? '' );
		return 'month' === $group ? self::month_label( substr( $d, 0, 7 ), $tz ) : Format::date( $d, $tz );
	}

	/**
	 * Grafico SVG a barre di visualizzazioni e visitatori.
	 *
	 * @param array<int, array<string, mixed>> $series Serie.
	 * @param string                           $group  day o month.
	 * @param DateTimeZone                     $tz     Fuso.
	 * @param int                              $height Altezza.
	 * @param string                           $id     Id per title.
	 */
	private static function bars( array $series, string $group, DateTimeZone $tz, int $height, string $id ): string {
		$w   = 640;
		$ml  = 46;
		$mr  = 10;
		$mt  = 12;
		$mb  = 26;
		$o   = '<svg class="vl-chart__svg vl-stats__svg" viewBox="0 0 ' . $w . ' ' . $height . '" role="img" aria-labelledby="' . esc_attr( $id ) . '-t" xmlns="http://www.w3.org/2000/svg">';
		$o  .= '<title id="' . esc_attr( $id ) . '-t">' . esc_html__( 'Visualizzazioni e visitatori', 'viewlapse-webcam' ) . '</title>';
		$n   = count( $series );
		$max = 0;
		foreach ( $series as $p ) {
			$max = max( $max, (int) ( $p['views'] ?? 0 ), (int) ( $p['visitors'] ?? 0 ) );
		}
		if ( 0 === $n || 0 === $max ) {
			return $o . '<text class="vl-chart__empty" x="' . ( $w / 2 ) . '" y="' . ( $height / 2 ) . '" text-anchor="middle">' . esc_html__( 'Nessuna visualizzazione nel periodo.', 'viewlapse-webcam' ) . '</text></svg>';
		}
		// Scala con passo "tondo".
		$raw  = $max / 4;
		$mag  = 10 ** floor( log10( max( 1, $raw ) ) );
		$step = (int) max( 1, ( $raw / $mag > 5 ? 10 : ( $raw / $mag > 2 ? 5 : ( $raw / $mag > 1 ? 2 : 1 ) ) ) * $mag );
		$top  = (int) ( ceil( $max / $step ) * $step );
		$y1   = $height - $mb;
		$sy   = static fn( float $v ): float => $y1 - $v * ( $y1 - $mt ) / $top;
		$f    = static fn( float $v ): string => number_format( $v, 1, '.', '' );
		$o   .= '<g class="vl-chart__grid">';
		for ( $v = 0; $v <= $top; $v += $step ) {
			$o .= '<line x1="' . $ml . '" x2="' . ( $w - $mr ) . '" y1="' . $f( $sy( $v ) ) . '" y2="' . $f( $sy( $v ) ) . '"/>';
			$o .= '<text class="vl-chart__label" x="' . ( $ml - 6 ) . '" y="' . $f( $sy( $v ) ) . '" text-anchor="end" dominant-baseline="middle">' . esc_html( number_format_i18n( $v ) ) . '</text>';
		}
		$o    .= '</g>';
		$slot  = ( $w - $ml - $mr ) / $n;
		$bw    = max( 1.0, $slot * 0.8 );
		$every = max( 1, (int) ceil( $n / 8 ) );
		foreach ( $series as $i => $p ) {
			$x     = $ml + $i * $slot + ( $slot - $bw ) / 2;
			$views = (int) ( $p['views'] ?? 0 );
			$vis   = (int) ( $p['visitors'] ?? 0 );
			$label = self::period_label( $p, $group, $tz );
			$o    .= '<g><title>' . esc_html( $label . ': ' . number_format_i18n( $views ) . ' / ' . number_format_i18n( $vis ) ) . '</title>';
			$o    .= '<rect class="vl-stats__bar vl-stats__bar--views" x="' . $f( $x ) . '" y="' . $f( $sy( $views ) ) . '" width="' . $f( $bw ) . '" height="' . $f( $y1 - $sy( $views ) ) . '"/>';
			$o    .= '<rect class="vl-stats__bar vl-stats__bar--visitors" x="' . $f( $x + $bw * 0.25 ) . '" y="' . $f( $sy( $vis ) ) . '" width="' . $f( $bw * 0.5 ) . '" height="' . $f( $y1 - $sy( $vis ) ) . '"/></g>';
			if ( 0 === $i % $every ) {
				$d     = (string) ( $p['date'] ?? $p['month'] ?? '' );
				$short = 'month' === $group ? substr( $d, 5, 2 ) . '/' . substr( $d, 2, 2 ) : substr( $d, 8, 2 ) . '/' . substr( $d, 5, 2 );
				$o    .= '<text class="vl-chart__label" x="' . $f( $x + $bw / 2 ) . '" y="' . ( $height - 8 ) . '" text-anchor="middle">' . esc_html( $short ) . '</text>';
			}
		}
		return $o . '</svg>';
	}

	// ------------------------------------------------------------ documenti (client)

	/**
	 * Rapporti mensili, certificati di maltempo e registro delle impronte.
	 *
	 * @param array<string, mixed>  $attrs Attributi.
	 * @param array<string, string> $req   Scelte del visitatore.
	 * @param string                $id    Id.
	 */
	public static function documenti( array $attrs, array $req, string $id ): string {
		if ( 'client' !== Api::scope() ) {
			return Render::unavailable( 'scope', 'monthly_report' );
		}
		$want_rep  = Format::bool( $attrs['rapporti'] );
		$want_cert = Format::bool( $attrs['certificati'] );
		$want_reg  = Format::bool( $attrs['registro'] );
		$feature   = $want_rep ? 'monthly_report' : 'weather_cert';
		$cam       = Render::camera( $attrs, $req, $sel, $id, $feature );
		if ( is_wp_error( $cam ) ) {
			return Render::error( $cam );
		}
		$sel  = (string) $sel;
		$rep  = Render::has( $cam, 'monthly_report' );
		$cert = Render::has( $cam, 'weather_cert' );
		if ( null === $rep ) {
			return Render::unavailable( 'portal', $feature );
		}
		$show_rep  = $want_rep && $rep;
		$show_cert = ( $want_cert || $want_reg ) && $cert;
		if ( ! $show_rep && ! $show_cert ) {
			return Render::unavailable( 'feature', $feature, $sel );
		}
		$tz    = Format::tz( $cam );
		$slug  = (string) $cam['slug'];
		$keep  = Render::keep( $cam, $sel );
		$now   = (int) substr( self::last_day( $cam, $tz ), 0, 4 );
		$first = (int) substr( (string) ( $cam['archive']['first_day'] ?? ( $now . '-01-01' ) ), 0, 4 );
		$year  = isset( $req['year'] ) ? max( $first, min( $now, (int) $req['year'] ) ) : $now;
		$dl    = Render::can_download_docs();

		$o = $sel;
		if ( ! $dl ) {
			/**
			 * Filtra la nota per chi vede i documenti ma non li può scaricare.
			 *
			 * @param string $msg Messaggio.
			 */
			$o .= '<p class="vl-message vl-docs__denied">' . esc_html( (string) apply_filters( 'viewlapse_webcam_docs_denied_message', __( 'Il download dei documenti è riservato agli utenti autorizzati.', 'viewlapse-webcam' ) ) ) . '</p>';
		}
		if ( $show_rep || ( $want_cert && $cert ) ) {
			$o .= '<nav class="vl-docs__years vl-cal__head" aria-label="' . esc_attr__( 'Anno', 'viewlapse-webcam' ) . '">';
			$o .= $year > $first ? '<a class="vl-cal__nav" href="' . Render::link( $keep + array( 'vl_year' => (string) ( $year - 1 ) ), $id ) . '" data-vl-nav aria-label="' . esc_attr__( 'Anno precedente', 'viewlapse-webcam' ) . '">&lsaquo;</a>' : '<span class="vl-cal__nav" aria-hidden="true"></span>';
			$o .= '<span class="vl-cal__title">' . esc_html( (string) $year ) . '</span>';
			$o .= $year < $now ? '<a class="vl-cal__nav" href="' . Render::link( $keep + array( 'vl_year' => (string) ( $year + 1 ) ), $id ) . '" data-vl-nav aria-label="' . esc_attr__( 'Anno successivo', 'viewlapse-webcam' ) . '">&rsaquo;</a>' : '<span class="vl-cal__nav" aria-hidden="true"></span>';
			$o .= '</nav>';
		}
		if ( $want_rep ) {
			$o .= '<section class="vl-docs__section"><h3 class="vl-docs__title">' . esc_html__( 'Rapporti mensili', 'viewlapse-webcam' ) . '</h3>';
			$o .= $show_rep ? self::reports( Api::reports( $slug, $year ), $tz, $dl ) : Render::unavailable( 'feature', 'monthly_report' );
			$o .= '</section>';
		}
		if ( $want_cert ) {
			$o .= '<section class="vl-docs__section"><h3 class="vl-docs__title">' . esc_html__( 'Certificati delle giornate di maltempo', 'viewlapse-webcam' ) . '</h3>';
			$o .= $cert ? self::certificates( Api::certificates( $slug, $year ), $tz, $dl ) : Render::unavailable( 'feature', 'weather_cert' );
			$o .= '</section>';
		}
		if ( $want_reg && $cert ) {
			$month = $req['month'] ?? substr( self::last_day( $cam, $tz ), 0, 7 );
			$o    .= self::register( $cam, $month, $tz, $dl, $keep, $id );
		}
		return $o;
	}

	/**
	 * Link di download di un documento, o solo il nome.
	 *
	 * @param mixed  $url   URL firmato.
	 * @param string $label Testo.
	 * @param bool   $dl    L'utente può scaricare.
	 */
	private static function doc_link( $url, string $label, bool $dl ): string {
		if ( ! $dl || empty( $url ) ) {
			return '<span class="vl-docs__nolink">' . esc_html( $label ) . '</span>';
		}
		return '<a class="vl-button vl-docs__link" href="' . esc_url( (string) $url ) . '" rel="noopener" download>' . esc_html( $label ) . '</a>';
	}

	/**
	 * Hash abbreviato con quello intero nel title.
	 *
	 * @param mixed $hash SHA-256.
	 */
	private static function hash( $hash ): string {
		$h = (string) $hash;
		return '' === $h ? '–' : '<code class="vl-hash" title="' . esc_attr( $h ) . '">' . esc_html( substr( $h, 0, 16 ) ) . '…</code>';
	}

	/**
	 * Tabella dei rapporti mensili.
	 *
	 * @param array<string, mixed>|WP_Error $r  Risposta.
	 * @param DateTimeZone                  $tz Fuso.
	 * @param bool                          $dl Download permesso.
	 */
	private static function reports( $r, DateTimeZone $tz, bool $dl ): string {
		if ( is_wp_error( $r ) ) {
			return Render::service_error( $r, 'monthly_report' );
		}
		$list = array_values( array_filter( (array) ( $r['reports'] ?? array() ), 'is_array' ) );
		if ( ! $list ) {
			return '<p class="vl-message">' . esc_html__( 'Nessun rapporto in questo anno. Il rapporto di un mese si prepara nei primi giorni del mese dopo.', 'viewlapse-webcam' ) . '</p>';
		}
		$o = '<div class="vl-table__wrap"><table class="vl-table"><thead><tr><th scope="col">' . esc_html__( 'Mese', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Disponibilità', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Foto', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Giorni di maltempo', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Inviato per email', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'PDF', 'viewlapse-webcam' ) . '</th></tr></thead><tbody>';
		foreach ( $list as $rep ) {
			$sum   = (array) ( $rep['summary'] ?? array() );
			$month = (string) ( $rep['month'] ?? substr( (string) ( $rep['period_from'] ?? '' ), 0, 7 ) );
			$size  = Format::bytes( $rep['size_bytes'] ?? 0 );
			$o    .= '<tr><th scope="row">' . esc_html( self::month_label( $month, $tz ) ) . '</th>';
			$o    .= '<td>' . esc_html( isset( $sum['availability_pct'] ) && is_numeric( $sum['availability_pct'] ) ? number_format_i18n( (float) $sum['availability_pct'], 2 ) . '%' : '–' ) . '</td>';
			$o    .= '<td>' . esc_html( isset( $sum['photos'] ) ? number_format_i18n( (int) $sum['photos'] ) : '–' ) . '</td>';
			$o    .= '<td>' . esc_html( isset( $sum['bad_weather_days'] ) ? number_format_i18n( (int) $sum['bad_weather_days'] ) : '–' ) . '</td>';
			$o    .= '<td>' . esc_html( ! empty( $rep['emailed'] ) ? Format::datetime( (int) $rep['emailed'], $tz ) : __( 'no', 'viewlapse-webcam' ) ) . '</td>';
			/* translators: %s: dimensione */
			$o .= '<td>' . self::doc_link( $rep['pdf_url'] ?? '', '' !== $size ? sprintf( __( 'Scarica (%s)', 'viewlapse-webcam' ), $size ) : __( 'Scarica', 'viewlapse-webcam' ), $dl ) . '</td></tr>';
		}
		return $o . '</tbody></table></div>';
	}

	/**
	 * Tabella dei certificati di maltempo.
	 *
	 * @param array<string, mixed>|WP_Error $r  Risposta.
	 * @param DateTimeZone                  $tz Fuso.
	 * @param bool                          $dl Download permesso.
	 */
	private static function certificates( $r, DateTimeZone $tz, bool $dl ): string {
		if ( is_wp_error( $r ) ) {
			return Render::service_error( $r, 'weather_cert' );
		}
		$list = array_values( array_filter( (array) ( $r['certificates'] ?? array() ), 'is_array' ) );
		if ( ! $list ) {
			return '<p class="vl-message">' . esc_html__( 'Nessun certificato in questo anno.', 'viewlapse-webcam' ) . '</p>';
		}
		$o = '<div class="vl-table__wrap"><table class="vl-table"><thead><tr><th scope="col">' . esc_html__( 'Periodo', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Maltempo', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Non certificabili', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'Emesso', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'SHA-256 del PDF', 'viewlapse-webcam' ) . '</th><th scope="col">' . esc_html__( 'PDF', 'viewlapse-webcam' ) . '</th></tr></thead><tbody>';
		foreach ( $list as $c ) {
			$from   = (string) ( $c['period_from'] ?? '' );
			$to     = (string) ( $c['period_to'] ?? '' );
			$period = 'month' === ( $c['kind'] ?? '' ) ? self::month_label( substr( $from, 0, 7 ), $tz ) : Format::date( $from, $tz ) . ' – ' . Format::date( $to, $tz );
			$size   = Format::bytes( $c['size_bytes'] ?? 0 );
			$o     .= '<tr><th scope="row">' . esc_html( $period ) . '</th>';
			/* translators: %s: numero di giorni */
			$o .= '<td>' . esc_html( sprintf( _n( '%s giorno', '%s giorni', (int) ( $c['days_bad'] ?? 0 ), 'viewlapse-webcam' ), number_format_i18n( (int) ( $c['days_bad'] ?? 0 ) ) ) ) . '</td>';
			$o .= '<td>' . esc_html( number_format_i18n( (int) ( $c['days_not_certifiable'] ?? 0 ) ) ) . '</td>';
			$o .= '<td>' . esc_html( ! empty( $c['generated'] ) ? Format::datetime( (int) $c['generated'], $tz ) : '–' ) . '</td>';
			$o .= '<td>' . self::hash( $c['sha256'] ?? '' ) . '</td>';
			/* translators: %s: dimensione */
			$o .= '<td>' . self::doc_link( $c['pdf_url'] ?? '', '' !== $size ? sprintf( __( 'Scarica (%s)', 'viewlapse-webcam' ), $size ) : __( 'Scarica', 'viewlapse-webcam' ), $dl ) . '</td></tr>';
		}
		return $o . '</tbody></table></div>';
	}

	/**
	 * Registro giornaliero e classificazione meteo di un mese.
	 *
	 * @param array<string, mixed>  $cam   Webcam.
	 * @param string                $month AAAA-MM.
	 * @param DateTimeZone          $tz    Fuso.
	 * @param bool                  $dl    Download permesso.
	 * @param array<string, string> $keep  Parametri da conservare.
	 * @param string                $id    Id.
	 */
	private static function register( array $cam, string $month, DateTimeZone $tz, bool $dl, array $keep, string $id ): string {
		$first = substr( (string) ( $cam['archive']['first_day'] ?? $month . '-01' ), 0, 7 );
		$last  = substr( self::last_day( $cam, $tz ), 0, 7 );
		$month = max( $first, min( $last, $month ) );
		$start = new DateTimeImmutable( $month . '-01 12:00', $tz );
		$prev  = $start->modify( '-1 month' )->format( 'Y-m' );
		$next  = $start->modify( '+1 month' )->format( 'Y-m' );
		$o     = '<section class="vl-docs__section"><h3 class="vl-docs__title">' . esc_html__( 'Registro giornaliero e giornate di maltempo', 'viewlapse-webcam' ) . '</h3>';
		$o    .= '<nav class="vl-cal__head" aria-label="' . esc_attr__( 'Mese del registro', 'viewlapse-webcam' ) . '">';
		$o    .= $prev >= $first ? '<a class="vl-cal__nav" href="' . Render::link( $keep + array( 'vl_month' => $prev ), $id ) . '" data-vl-nav aria-label="' . esc_attr__( 'Mese precedente', 'viewlapse-webcam' ) . '">&lsaquo;</a>' : '<span class="vl-cal__nav" aria-hidden="true"></span>';
		$o    .= '<span class="vl-cal__title">' . esc_html( self::month_label( $month, $tz ) ) . '</span>';
		$o    .= $next <= $last ? '<a class="vl-cal__nav" href="' . Render::link( $keep + array( 'vl_month' => $next ), $id ) . '" data-vl-nav aria-label="' . esc_attr__( 'Mese successivo', 'viewlapse-webcam' ) . '">&rsaquo;</a>' : '<span class="vl-cal__nav" aria-hidden="true"></span>';
		$o    .= '</nav>';
		$r     = Api::weather_days( (string) $cam['slug'], $month );
		if ( is_wp_error( $r ) ) {
			return $o . Render::service_error( $r, 'weather_cert' ) . '</section>';
		}
		$days = array_values( array_filter( (array) ( $r['days'] ?? array() ), 'is_array' ) );
		if ( ! $days ) {
			return $o . '<p class="vl-message">' . esc_html__( 'Nessun giorno registrato in questo mese (i giorni si sigillano il giorno dopo).', 'viewlapse-webcam' ) . '</p></section>';
		}
		$sum = (array) ( $r['summary'] ?? array() );
		if ( $sum ) {
			$o .= '<p class="vl-docs__summary">' . esc_html(
				sprintf(
					/* translators: 1: giorni registrati, 2: giorni di maltempo, 3: giorni non certificabili, 4: ore di lavoro */
					__( '%1$s giorni registrati, %2$s di maltempo, %3$s non certificabili. Ore di lavoro: %4$s.', 'viewlapse-webcam' ),
					number_format_i18n( (int) ( $sum['days'] ?? count( $days ) ) ),
					number_format_i18n( (int) ( $sum['bad_weather'] ?? 0 ) ),
					number_format_i18n( (int) ( $sum['not_certifiable'] ?? 0 ) ),
					(string) ( $r['work_hours'] ?? '' )
				)
			) . '</p>';
		}
		$cls    = array(
			'ok'              => __( 'Regolare', 'viewlapse-webcam' ),
			'bad_weather'     => __( 'Maltempo', 'viewlapse-webcam' ),
			'not_certifiable' => __( 'Non certificabile', 'viewlapse-webcam' ),
			'no_data'         => __( 'Nessun dato', 'viewlapse-webcam' ),
		);
		$why    = array(
			'wet'            => __( 'umido', 'viewlapse-webcam' ),
			'frost'          => __( 'gelo', 'viewlapse-webcam' ),
			'fog'            => __( 'nebbia', 'viewlapse-webcam' ),
			'dark'           => __( 'scuro', 'viewlapse-webcam' ),
			'sensor_missing' => __( 'sensore esterno assente', 'viewlapse-webcam' ),
			'few_samples'    => __( 'pochi campioni', 'viewlapse-webcam' ),
			'few_photos'     => __( 'poche foto', 'viewlapse-webcam' ),
			'time_unsynced'  => __( 'ora non sincronizzata', 'viewlapse-webcam' ),
		);
		$o .= '<div class="vl-table__wrap"><table class="vl-table vl-table--compact"><thead><tr>';
		foreach ( array( __( 'Giorno', 'viewlapse-webcam' ), __( 'Esito', 'viewlapse-webcam' ), __( 'Ore umide / gelo / nebbia', 'viewlapse-webcam' ), __( 'Temperatura min–max', 'viewlapse-webcam' ), __( 'Umidità media', 'viewlapse-webcam' ), __( 'Foto', 'viewlapse-webcam' ), __( 'Impronta del giorno', 'viewlapse-webcam' ), __( 'Depositata', 'viewlapse-webcam' ), __( 'Elenco', 'viewlapse-webcam' ) ) as $h ) {
			$o .= '<th scope="col">' . esc_html( $h ) . '</th>';
		}
		$o .= '</tr></thead><tbody>';
		foreach ( $days as $d ) {
			$c       = (string) ( $d['classification'] ?? '' );
			$reasons = array_map( static fn( $x ) => $why[ (string) $x ] ?? (string) $x, (array) ( $d['reasons'] ?? array() ) );
			$label   = ( $cls[ $c ] ?? $c ) . ( $reasons ? ' (' . implode( ', ', $reasons ) . ')' : '' );
			$o      .= '<tr class="vl-wd--' . esc_attr( sanitize_key( $c ) ) . '"><th scope="row">' . esc_html( Format::date( (string) ( $d['date'] ?? '' ), $tz ) ) . ( empty( $d['workday'] ) ? ' <span class="vl-wd__off">' . esc_html__( 'non lavorativo', 'viewlapse-webcam' ) . '</span>' : '' ) . '</th>';
			$o      .= '<td><span class="vl-wd__class">' . esc_html( $label ) . '</span></td>';
			$o      .= '<td>' . esc_html( (int) ( $d['wet_hours'] ?? 0 ) . ' / ' . (int) ( $d['frost_hours'] ?? 0 ) . ' / ' . (int) ( $d['fog_hours'] ?? 0 ) ) . '</td>';
			$o      .= '<td>' . esc_html( Format::num( $d['t_ext_min'] ?? null, '°C' ) . ' – ' . Format::num( $d['t_ext_max'] ?? null, '°C' ) ) . '</td>';
			$o      .= '<td>' . esc_html( Format::num( $d['rh_ext_avg'] ?? null, '%', 0 ) ) . '</td>';
			$o      .= '<td>' . esc_html( number_format_i18n( (int) ( $d['photos'] ?? 0 ) ) ) . '</td>';
			$o      .= '<td>' . self::hash( $d['day_hash'] ?? '' ) . '</td>';
			$o      .= '<td>' . esc_html( ! empty( $d['registered_at'] ) ? Format::datetime( (int) $d['registered_at'], $tz ) : __( 'non ancora', 'viewlapse-webcam' ) ) . '</td>';
			$o      .= '<td>' . self::doc_link( $d['manifest_url'] ?? '', __( '.txt', 'viewlapse-webcam' ), $dl ) . '</td></tr>';
		}
		$o .= '</tbody></table></div>';
		$o .= '<p class="vl-message vl-docs__limits">' . esc_html__( 'Il registro prova integrità e ora delle foto: ogni elenco contiene gli SHA-256 delle foto del giorno e si verifica con sha256sum; l\'impronta del giorno è depositata in Dolibarr il giorno dopo. La classificazione meteo è un indizio ricavato da umidità, punto di rugiada, temperatura e foto (nessuna misura di pioggia, vento o neve) secondo le soglie concordate nel contratto.', 'viewlapse-webcam' ) . '</p>';
		return $o . '</section>';
	}
}
