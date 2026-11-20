<?php
/**
 * Grafico SVG di temperatura e umidità esterne, generato sul server.
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Disegna le medie orarie di `series` (con la fascia min/max della temperatura).
 */
final class Chart {

	private const W  = 640;
	private const ML = 46;
	private const MR = 46;
	private const MT = 14;
	private const MB = 30;

	/** Ore senza dati oltre le quali la linea si interrompe. */
	private const GAP = 2 * HOUR_IN_SECONDS;

	/**
	 * SVG del grafico.
	 *
	 * @param array<int, array<string, mixed>> $points Punti di series.
	 * @param bool                             $temp   Mostra la temperatura.
	 * @param bool                             $hum    Mostra l'umidità.
	 * @param int                              $from   Inizio (Unix).
	 * @param int                              $to     Fine (Unix).
	 * @param DateTimeZone                     $tz     Fuso della webcam.
	 * @param int                              $height Altezza (unità del viewBox).
	 * @param string                           $id     Id per title/desc.
	 */
	public static function svg( array $points, bool $temp, bool $hum, int $from, int $to, DateTimeZone $tz, int $height, string $id ): string {
		$h      = max( 160, min( 600, $height ) );
		$x0     = self::ML;
		$x1     = self::W - ( $hum ? self::MR : 16 );
		$y0     = self::MT;
		$y1     = $h - self::MB;
		$to     = max( $to, $from + HOUR_IN_SECONDS );
		$sx     = static fn( int $t ): float => $x0 + ( $t - $from ) * ( $x1 - $x0 ) / ( $to - $from );
		$tvals  = array();
		foreach ( $points as $p ) {
			foreach ( array( 'temperature_ext', 'temperature_ext_min', 'temperature_ext_max' ) as $f ) {
				if ( isset( $p[ $f ] ) && is_numeric( $p[ $f ] ) ) {
					$tvals[] = (float) $p[ $f ];
				}
			}
		}
		$tmin = $tvals ? min( $tvals ) : 0.0;
		$tmax = $tvals ? max( $tvals ) : 10.0;
		$step = self::nice_step( $tmax - $tmin );
		$tmin = floor( ( $tmin - 0.5 ) / $step ) * $step;
		$tmax = ceil( ( $tmax + 0.5 ) / $step ) * $step;
		$syt  = static fn( float $v ): float => $y1 - ( $v - $tmin ) * ( $y1 - $y0 ) / max( 0.001, $tmax - $tmin );
		$syh  = static fn( float $v ): float => $y1 - $v * ( $y1 - $y0 ) / 100;
		$f    = static fn( float $v ): string => number_format( $v, 1, '.', '' );

		$title = __( 'Temperatura e umidità esterne', 'viewlapse-webcam' );
		$desc  = self::describe( $points, $tz );

		$o  = '<svg class="vl-chart__svg" viewBox="0 0 ' . self::W . ' ' . $h . '" role="img" aria-labelledby="' . esc_attr( $id ) . '-t ' . esc_attr( $id ) . '-d" xmlns="http://www.w3.org/2000/svg">';
		$o .= '<title id="' . esc_attr( $id ) . '-t">' . esc_html( $title ) . '</title>';
		$o .= '<desc id="' . esc_attr( $id ) . '-d">' . esc_html( $desc ) . '</desc>';

		// Griglia orizzontale e scala della temperatura (sinistra).
		$o .= '<g class="vl-chart__grid">';
		if ( $temp ) {
			for ( $v = $tmin; $v <= $tmax + 0.0001; $v += $step ) {
				$y  = $f( $syt( $v ) );
				$o .= '<line x1="' . $x0 . '" x2="' . $x1 . '" y1="' . $y . '" y2="' . $y . '"/>';
				$o .= '<text class="vl-chart__label vl-chart__label--temp" x="' . ( $x0 - 6 ) . '" y="' . $y . '" text-anchor="end" dominant-baseline="middle">' . esc_html( number_format_i18n( $v, $step < 1 ? 1 : 0 ) ) . '°</text>';
			}
		}
		if ( $hum ) {
			foreach ( array( 0, 25, 50, 75, 100 ) as $v ) {
				$y = $f( $syh( (float) $v ) );
				if ( ! $temp ) {
					$o .= '<line x1="' . $x0 . '" x2="' . $x1 . '" y1="' . $y . '" y2="' . $y . '"/>';
				}
				$o .= '<text class="vl-chart__label vl-chart__label--hum" x="' . ( $x1 + 6 ) . '" y="' . $y . '" dominant-baseline="middle">' . $v . '%</text>';
			}
		}
		$o .= '</g>';

		// Asse del tempo.
		$o .= '<g class="vl-chart__axis">';
		foreach ( self::time_ticks( $from, $to, $tz ) as [ $t, $label, $major ] ) {
			$x  = $f( $sx( $t ) );
			$o .= '<line class="' . ( $major ? 'vl-chart__tick--major' : 'vl-chart__tick' ) . '" x1="' . $x . '" x2="' . $x . '" y1="' . $y0 . '" y2="' . $y1 . '"/>';
			$o .= '<text class="vl-chart__label" x="' . $x . '" y="' . ( $y1 + 18 ) . '" text-anchor="middle">' . esc_html( $label ) . '</text>';
		}
		$o .= '</g>';

		$segments = self::segments( $points );
		if ( $hum ) {
			foreach ( $segments as $seg ) {
				$o .= self::line( $seg, 'humidity_ext', $sx, $syh, 'vl-chart__hum', $f );
			}
		}
		if ( $temp ) {
			foreach ( $segments as $seg ) {
				$o .= self::band( $seg, $sx, $syt, $f );
			}
			foreach ( $segments as $seg ) {
				$o .= self::line( $seg, 'temperature_ext', $sx, $syt, 'vl-chart__temp', $f );
			}
		}
		if ( ! $points ) {
			$o .= '<text class="vl-chart__empty" x="' . ( ( $x0 + $x1 ) / 2 ) . '" y="' . ( ( $y0 + $y1 ) / 2 ) . '" text-anchor="middle">' . esc_html__( 'Nessuna misura in questo periodo', 'viewlapse-webcam' ) . '</text>';
		}
		$o .= '</svg>';
		return $o;
	}

	/**
	 * Testo descrittivo (lettori di schermo).
	 *
	 * @param array<int, array<string, mixed>> $points Punti.
	 * @param DateTimeZone                     $tz     Fuso.
	 */
	private static function describe( array $points, DateTimeZone $tz ): string {
		$t = array_values( array_filter( array_column( $points, 'temperature_ext' ), 'is_numeric' ) );
		$u = array_values( array_filter( array_column( $points, 'humidity_ext' ), 'is_numeric' ) );
		if ( ! $points ) {
			return __( 'Nessuna misura.', 'viewlapse-webcam' );
		}
		$last  = end( $points );
		$parts = array();
		if ( $t ) {
			/* translators: 1: minima, 2: massima */
			$parts[] = sprintf( __( 'Temperatura media oraria tra %1$s e %2$s.', 'viewlapse-webcam' ), Format::num( min( $t ), '°C' ), Format::num( max( $t ), '°C' ) );
		}
		if ( $u ) {
			/* translators: 1: minima, 2: massima */
			$parts[] = sprintf( __( 'Umidità tra %1$s e %2$s.', 'viewlapse-webcam' ), Format::num( min( $u ), '%', 0 ), Format::num( max( $u ), '%', 0 ) );
		}
		/* translators: %s: data e ora */
		$parts[] = sprintf( __( 'Ultimo dato: %s.', 'viewlapse-webcam' ), Format::datetime( (int) $last['time'], $tz ) );
		return implode( ' ', $parts );
	}

	/**
	 * Passo "rotondo" della scala.
	 *
	 * @param float $range Intervallo.
	 */
	private static function nice_step( float $range ): float {
		foreach ( array( 0.5, 1, 2, 5, 10, 20 ) as $s ) {
			if ( $range / $s <= 6 ) {
				return (float) $s;
			}
		}
		return 50.0;
	}

	/**
	 * Tacche dell'asse del tempo, nel fuso della webcam.
	 *
	 * @param int          $from Inizio.
	 * @param int          $to   Fine.
	 * @param DateTimeZone $tz   Fuso.
	 * @return array<int, array{0:int, 1:string, 2:bool}>
	 */
	private static function time_ticks( int $from, int $to, DateTimeZone $tz ): array {
		$span  = $to - $from;
		$days  = $span / DAY_IN_SECONDS;
		$ticks = array();
		$day   = ( new \DateTimeImmutable( '@' . $from ) )->setTimezone( $tz )->setTime( 0, 0 );
		if ( $days <= 3.5 ) {
			$step_h = $days <= 1.5 ? 3 : 6;
			for ( $d = $day; $d->getTimestamp() <= $to; $d = $d->modify( '+' . $step_h . ' hours' ) ) {
				$t = $d->getTimestamp();
				if ( $t < $from ) {
					continue;
				}
				$major   = '00' === $d->format( 'H' );
				$ticks[] = array( $t, $major ? (string) wp_date( 'j/n', $t, $tz ) : $d->format( 'H:i' ), $major );
			}
			return $ticks;
		}
		$every = (int) max( 1, ceil( $days / 8 ) );
		$i     = 0;
		for ( $d = $day->modify( '+1 day' ); $d->getTimestamp() <= $to; $d = $d->modify( '+1 day' ) ) {
			if ( 0 === $i++ % $every ) {
				$ticks[] = array( $d->getTimestamp(), (string) wp_date( 'j/n', $d->getTimestamp(), $tz ), true );
			}
		}
		return $ticks;
	}

	/**
	 * Divide i punti dove mancano dati.
	 *
	 * @param array<int, array<string, mixed>> $points Punti.
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	private static function segments( array $points ): array {
		$segs = array();
		$cur  = array();
		$prev = null;
		foreach ( $points as $p ) {
			$t = (int) ( $p['time'] ?? 0 );
			if ( null !== $prev && $t - $prev > self::GAP && $cur ) {
				$segs[] = $cur;
				$cur    = array();
			}
			$cur[] = $p;
			$prev  = $t;
		}
		if ( $cur ) {
			$segs[] = $cur;
		}
		return $segs;
	}

	/**
	 * Linea di un campo (il punto è a metà dell'ora).
	 *
	 * @param array<int, array<string, mixed>> $seg   Punti.
	 * @param string                           $field Campo.
	 * @param callable                         $sx    Scala x.
	 * @param callable                         $sy    Scala y.
	 * @param string                           $class Classe.
	 * @param callable                         $f     Formato numeri.
	 */
	private static function line( array $seg, string $field, callable $sx, callable $sy, string $class, callable $f ): string {
		$pts = array();
		foreach ( $seg as $p ) {
			if ( isset( $p[ $field ] ) && is_numeric( $p[ $field ] ) ) {
				$pts[] = $f( $sx( (int) $p['time'] + 1800 ) ) . ',' . $f( $sy( (float) $p[ $field ] ) );
			}
		}
		if ( ! $pts ) {
			return '';
		}
		if ( 1 === count( $pts ) ) {
			[ $x, $y ] = explode( ',', $pts[0] );
			return '<circle class="' . $class . '-dot" cx="' . $x . '" cy="' . $y . '" r="2.5"/>';
		}
		return '<polyline class="' . $class . '" points="' . implode( ' ', $pts ) . '"/>';
	}

	/**
	 * Fascia tra minima e massima della temperatura.
	 *
	 * @param array<int, array<string, mixed>> $seg Punti.
	 * @param callable                         $sx  Scala x.
	 * @param callable                         $sy  Scala y.
	 * @param callable                         $f   Formato numeri.
	 */
	private static function band( array $seg, callable $sx, callable $sy, callable $f ): string {
		$top = array();
		$bot = array();
		foreach ( $seg as $p ) {
			if ( isset( $p['temperature_ext_min'], $p['temperature_ext_max'] ) && is_numeric( $p['temperature_ext_min'] ) && is_numeric( $p['temperature_ext_max'] ) ) {
				$x     = $f( $sx( (int) $p['time'] + 1800 ) );
				$top[] = $x . ',' . $f( $sy( (float) $p['temperature_ext_max'] ) );
				$bot[] = $x . ',' . $f( $sy( (float) $p['temperature_ext_min'] ) );
			}
		}
		if ( count( $top ) < 2 ) {
			return '';
		}
		return '<polygon class="vl-chart__band" points="' . implode( ' ', array_merge( $top, array_reverse( $bot ) ) ) . '"/>';
	}
}
