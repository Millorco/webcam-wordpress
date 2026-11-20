<?php
/**
 * Formattazione: date nel fuso della webcam, stati, numeri, immagini.
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Funzioni di supporto per l'HTML.
 */
final class Format {

	/** Dimensioni delle immagini del portal (lato lungo, px). */
	public const SIZES = array( 320, 640, 1280, 1920 );

	/**
	 * Fuso della webcam (Europe/Rome se non valido).
	 *
	 * @param array<string, mixed> $cam Webcam.
	 */
	public static function tz( array $cam ): DateTimeZone {
		try {
			return new DateTimeZone( (string) ( $cam['timezone'] ?? 'Europe/Rome' ) );
		} catch ( \Exception $e ) {
			return new DateTimeZone( 'Europe/Rome' );
		}
	}

	/**
	 * Data e ora leggibili.
	 *
	 * @param int          $time Secondi Unix.
	 * @param DateTimeZone $tz   Fuso.
	 */
	public static function datetime( int $time, DateTimeZone $tz ): string {
		return (string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time, $tz );
	}

	/**
	 * Solo l'ora.
	 *
	 * @param int          $time Secondi Unix.
	 * @param DateTimeZone $tz   Fuso.
	 */
	public static function time( int $time, DateTimeZone $tz ): string {
		return (string) wp_date( get_option( 'time_format' ), $time, $tz );
	}

	/**
	 * Data leggibile da AAAA-MM-GG.
	 *
	 * @param string       $date Data.
	 * @param DateTimeZone $tz   Fuso.
	 */
	public static function date( string $date, DateTimeZone $tz ): string {
		$d = date_create_immutable( $date . ' 12:00', $tz );
		return $d ? (string) wp_date( get_option( 'date_format' ), $d->getTimestamp(), $tz ) : $date;
	}

	/**
	 * Etichetta dello stato della webcam.
	 *
	 * @param string $state Stato del portal.
	 */
	public static function state_label( string $state ): string {
		$labels = array(
			'online'        => __( 'In linea', 'viewlapse-webcam' ),
			'maintenance'   => __( 'In manutenzione', 'viewlapse-webcam' ),
			'late'          => __( 'In ritardo', 'viewlapse-webcam' ),
			'offline'       => __( 'Non in linea', 'viewlapse-webcam' ),
			'never'         => __( 'Mai collegata', 'viewlapse-webcam' ),
			'not_installed' => __( 'Da installare', 'viewlapse-webcam' ),
			'archived'      => __( 'Archivio', 'viewlapse-webcam' ),
		);
		return $labels[ $state ] ?? __( 'Stato sconosciuto', 'viewlapse-webcam' );
	}

	/**
	 * Dimensione di un file leggibile (MB o kB).
	 *
	 * @param mixed $bytes Byte.
	 */
	public static function bytes( $bytes ): string {
		$b = (int) $bytes;
		if ( $b <= 0 ) {
			return '';
		}
		return $b >= 1048576 ? number_format_i18n( $b / 1048576, 1 ) . "\u{00A0}MB" : number_format_i18n( max( 1, $b / 1024 ), 0 ) . "\u{00A0}kB";
	}

	/**
	 * Durata in secondi leggibile (8 s, 1 min 05 s, 2 h 10 min).
	 *
	 * @param mixed $seconds Secondi.
	 */
	public static function duration( $seconds ): string {
		$s = (int) round( (float) $seconds );
		if ( $s < 60 ) {
			return $s . "\u{00A0}s";
		}
		if ( $s < 3600 ) {
			return sprintf( '%d min %02d s', intdiv( $s, 60 ), $s % 60 );
		}
		return sprintf( '%d h %02d min', intdiv( $s, 3600 ), intdiv( $s % 3600, 60 ) );
	}

	/**
	 * Ora locale HH:MM valida.
	 *
	 * @param mixed $v Valore.
	 */
	public static function valid_time( $v ): ?string {
		if ( ! is_string( $v ) || ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $v ), $m ) || (int) $m[1] > 23 || (int) $m[2] > 59 ) {
			return null;
		}
		return sprintf( '%02d:%02d', (int) $m[1], (int) $m[2] );
	}

	/**
	 * Numero con decimali nel formato del sito, o trattino.
	 *
	 * @param mixed  $v    Valore.
	 * @param string $unit Unità.
	 * @param int    $dec  Decimali.
	 */
	public static function num( $v, string $unit, int $dec = 1 ): string {
		if ( null === $v || '' === $v || ! is_numeric( $v ) ) {
			return '–';
		}
		return number_format_i18n( (float) $v, $dec ) . "\u{00A0}" . $unit;
	}

	/**
	 * Larghezza reale della riduzione di lato lungo $size.
	 *
	 * @param int $size Lato lungo chiesto.
	 * @param int $w    Larghezza originale.
	 * @param int $h    Altezza originale.
	 */
	public static function width_for( int $size, int $w, int $h ): int {
		if ( $w <= 0 || $h <= 0 ) {
			return $size;
		}
		$long  = max( $w, $h );
		$scale = min( 1, $size / $long );
		return (int) round( $w * $scale );
	}

	/**
	 * Attributi src, srcset, width e height di un'immagine del portal.
	 *
	 * @param array<string, mixed> $photo Oggetto foto.
	 * @param int                  $max   Dimensione massima da usare.
	 * @return array{src:string, srcset:string, width:int, height:int}|null
	 */
	public static function img( array $photo, int $max = 1920 ): ?array {
		$images = (array) ( $photo['images'] ?? array() );
		$w      = (int) ( $photo['width'] ?? 0 );
		$h      = (int) ( $photo['height'] ?? 0 );
		$set    = array();
		$src    = '';
		$seen   = array();
		foreach ( self::SIZES as $size ) {
			if ( $size > $max || empty( $images[ (string) $size ] ) ) {
				continue;
			}
			$width = self::width_for( $size, $w, $h );
			if ( isset( $seen[ $width ] ) ) {
				continue; // foto più piccola della riduzione: stessa immagine.
			}
			$seen[ $width ] = true;
			$url            = (string) $images[ (string) $size ];
			$set[]          = esc_url( $url ) . ' ' . $width . 'w';
			$src            = $url;
		}
		if ( '' === $src ) {
			return null;
		}
		$width  = self::width_for( $max, $w, $h );
		$height = ( $w > 0 && $h > 0 ) ? (int) round( $width * $h / $w ) : 0;
		return array(
			'src'    => $src,
			'srcset' => implode( ', ', $set ),
			'width'  => $w > 0 ? $width : 0,
			'height' => $height,
		);
	}

	/**
	 * Tag <img> di una foto del portal.
	 *
	 * @param array<string, mixed> $photo Oggetto foto.
	 * @param int                  $max   Dimensione massima.
	 * @param string               $sizes Attributo sizes.
	 * @param string               $alt   Testo alternativo.
	 * @param array<string, string> $extra Altri attributi.
	 */
	public static function img_tag( array $photo, int $max, string $sizes, string $alt, array $extra = array() ): string {
		$img = self::img( $photo, $max );
		if ( null === $img ) {
			return '';
		}
		$attrs = array(
			'src'      => esc_url( $img['src'] ),
			'srcset'   => esc_attr( $img['srcset'] ),
			'sizes'    => esc_attr( $sizes ),
			'alt'      => esc_attr( $alt ),
			'decoding' => 'async',
		);
		if ( $img['width'] > 0 && $img['height'] > 0 ) {
			$attrs['width']  = (string) $img['width'];
			$attrs['height'] = (string) $img['height'];
		}
		foreach ( $extra as $k => $v ) {
			$attrs[ $k ] = esc_attr( $v );
		}
		$html = '<img';
		foreach ( $attrs as $k => $v ) {
			$html .= ' ' . $k . '="' . $v . '"';
		}
		return $html . '>';
	}

	/**
	 * Data AAAA-MM-GG valida.
	 *
	 * @param mixed $v Valore.
	 */
	public static function valid_date( $v ): ?string {
		if ( ! is_string( $v ) || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) ) {
			return null;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $v : null;
	}

	/**
	 * Mese AAAA-MM valido.
	 *
	 * @param mixed $v Valore.
	 */
	public static function valid_month( $v ): ?string {
		if ( ! is_string( $v ) || ! preg_match( '/^(\d{4})-(\d{2})$/', $v, $m ) ) {
			return null;
		}
		return ( (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 2000 ) ? $v : null;
	}

	/**
	 * Istante AAAA-MM-GG o AAAA-MM-GGTHH:MM valido (una data sola diventa mezzogiorno).
	 *
	 * @param mixed $v Valore.
	 */
	public static function valid_instant( $v ): ?string {
		if ( ! is_string( $v ) ) {
			return null;
		}
		$v = str_replace( ' ', 'T', trim( $v ) );
		if ( null !== self::valid_date( $v ) ) {
			return $v . 'T12:00';
		}
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2})(?::\d{2})?$/', $v, $m ) && null !== self::valid_date( $m[1] ) && (int) $m[2] < 24 && (int) $m[3] < 60 ) {
			return $m[1] . 'T' . $m[2] . ':' . $m[3];
		}
		return null;
	}

	/**
	 * Valore booleano di un attributo (anche da shortcode: si, no, 1, 0, true, false).
	 *
	 * @param mixed $v Valore.
	 */
	public static function bool( $v ): bool {
		if ( is_bool( $v ) ) {
			return $v;
		}
		return in_array( strtolower( trim( (string) $v ) ), array( '1', 'si', 'sì', 'yes', 'true', 'on' ), true );
	}
}
