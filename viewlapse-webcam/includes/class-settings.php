<?php
/**
 * Impostazioni del plugin (opzione vl_webcam_settings).
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

defined( 'ABSPATH' ) || exit;

/**
 * Lettura e validazione delle impostazioni.
 *
 * Il filtro `viewlapse_webcam_settings` permette a un altro plugin (il
 * "portale clienti") di sostituire URL del portal e chiave per ogni sito.
 */
final class Settings {

	public const OPTION = 'vl_webcam_settings';

	/**
	 * Valori predefiniti.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'portal_url'        => '',
			'api_key'           => '',
			'refresh_min'       => 5,
			'ttl_current'       => 60,
			'ttl_archive'       => 6 * HOUR_IN_SECONDS,
			'timeout'           => 8,
			'beacon'            => 1,
			'missing_feature'   => 'message',
			'tiles_url'         => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
			'tiles_attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
		);
	}

	/**
	 * Impostazioni correnti, già filtrate.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION, array() );
		$s     = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		/**
		 * Filtra le impostazioni (es. chiave per sito nel Multisite).
		 *
		 * @param array $s Impostazioni.
		 */
		$s = (array) apply_filters( 'viewlapse_webcam_settings', $s );
		return array_merge( self::defaults(), $s );
	}

	/**
	 * Un valore delle impostazioni.
	 *
	 * @param string $key Nome.
	 * @return mixed
	 */
	public static function value( string $key ) {
		$s = self::get();
		return $s[ $key ] ?? null;
	}

	/**
	 * True se le impostazioni sono gestite da un altro plugin (non modificabili qui).
	 */
	public static function locked(): bool {
		return (bool) apply_filters( 'viewlapse_webcam_settings_locked', false );
	}

	/**
	 * Valida i dati del modulo delle impostazioni.
	 *
	 * @param array<string, mixed> $in  Dati inviati.
	 * @param array<string, mixed> $old Impostazioni salvate.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $in, array $old ): array {
		$d   = self::defaults();
		$out = array_merge( $d, $old );

		$url               = isset( $in['portal_url'] ) ? esc_url_raw( trim( (string) $in['portal_url'] ), array( 'http', 'https' ) ) : '';
		$out['portal_url'] = untrailingslashit( $url );

		$key = isset( $in['api_key'] ) ? trim( (string) $in['api_key'] ) : '';
		if ( ! empty( $in['api_key_remove'] ) ) {
			$out['api_key'] = '';
		} elseif ( '' !== $key ) {
			if ( self::valid_key( $key ) ) {
				$out['api_key'] = $key;
			} else {
				add_settings_error( self::OPTION, 'vl_key', __( 'La chiave non è valida: deve iniziare con vls_ ed essere lunga 47 caratteri. Non è stata salvata.', 'viewlapse-webcam' ) );
			}
		}

		$out['refresh_min'] = max( 0, min( 1440, absint( $in['refresh_min'] ?? $d['refresh_min'] ) ) );
		$out['ttl_current'] = max( 30, min( 3600, absint( $in['ttl_current'] ?? $d['ttl_current'] ) ) );
		$out['ttl_archive'] = max( 300, min( 2 * DAY_IN_SECONDS, absint( $in['ttl_archive'] ?? $d['ttl_archive'] ) ) );
		$out['timeout']     = max( 2, min( 30, absint( $in['timeout'] ?? $d['timeout'] ) ) );
		$out['beacon']      = empty( $in['beacon'] ) ? 0 : 1;
		$out['missing_feature'] = ( isset( $in['missing_feature'] ) && 'hide' === $in['missing_feature'] ) ? 'hide' : 'message';

		$tiles = isset( $in['tiles_url'] ) ? trim( (string) $in['tiles_url'] ) : '';
		// I segnaposto {z}/{x}/{y}/{s} non sono validi per esc_url_raw: si controllano a parte.
		$out['tiles_url']         = ( '' !== $tiles && preg_match( '#^https://[^\s"\'<>]+$#', $tiles ) ) ? $tiles : $d['tiles_url'];
		$out['tiles_attribution'] = isset( $in['tiles_attribution'] ) ? wp_kses( (string) $in['tiles_attribution'], array( 'a' => array( 'href' => true ) ) ) : $d['tiles_attribution'];
		return $out;
	}

	/**
	 * Formato della chiave dei siti: vls_ + 43 caratteri base64url.
	 *
	 * @param string $key Chiave.
	 */
	public static function valid_key( string $key ): bool {
		return 1 === preg_match( '/^vls_[A-Za-z0-9_-]{43}$/', $key );
	}

	/**
	 * Inizio della chiave, da mostrare al posto della chiave intera.
	 *
	 * @param string $key Chiave.
	 */
	public static function key_hint( string $key ): string {
		return '' === $key ? '' : substr( $key, 0, 12 ) . '…';
	}
}
