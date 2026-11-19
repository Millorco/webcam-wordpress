<?php
/**
 * Blocchi Gutenberg dinamici (block.json + render in PHP, senza build).
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

defined( 'ABSPATH' ) || exit;

/**
 * Registra viewlapse/foto, viewlapse/archivio, viewlapse/confronto,
 * viewlapse/grafico e viewlapse/elenco.
 */
final class Blocks {

	/**
	 * Registrazione dei blocchi.
	 */
	public static function register(): void {
		foreach ( Render::KINDS as $kind ) {
			register_block_type(
				VIEWLAPSE_WEBCAM_DIR . '/blocks/' . $kind,
				array(
					'render_callback' => static function ( array $attrs ) use ( $kind ): string {
						$attrs   = self::complete( $kind, $attrs );
						$wrapper = get_block_wrapper_attributes( array( 'class' => 'vl-block vl-' . $kind ) );
						return Render::render( $kind, $attrs, $wrapper );
					},
				)
			);
		}
		add_filter( 'block_categories_all', array( self::class, 'category' ) );
	}

	/**
	 * Categoria "Viewlapse" nell'inseritore dei blocchi.
	 *
	 * @param array<int, array<string, mixed>> $cats Categorie.
	 * @return array<int, array<string, mixed>>
	 */
	public static function category( array $cats ): array {
		$cats[] = array(
			'slug'  => 'viewlapse',
			'title' => __( 'Viewlapse Webcam', 'viewlapse-webcam' ),
		);
		return $cats;
	}

	/**
	 * Attributi del blocco dal suo block.json (tipo e predefinito).
	 *
	 * @param string $kind Tipo.
	 * @return array<string, array<string, mixed>>
	 */
	public static function schema( string $kind ): array {
		static $cache = array();
		if ( ! isset( $cache[ $kind ] ) ) {
			$file = VIEWLAPSE_WEBCAM_DIR . '/blocks/' . $kind . '/block.json';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file locale del plugin.
			$json           = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
			$cache[ $kind ] = is_array( $json ) ? (array) ( $json['attributes'] ?? array() ) : array();
		}
		return $cache[ $kind ];
	}

	/**
	 * Completa e normalizza gli attributi con i predefiniti di block.json.
	 *
	 * @param string               $kind  Tipo.
	 * @param array<string, mixed> $attrs Attributi ricevuti.
	 * @return array<string, mixed>
	 */
	public static function complete( string $kind, array $attrs ): array {
		$out = array();
		foreach ( self::schema( $kind ) as $name => $def ) {
			if ( in_array( $name, array( 'className', 'align', 'style', 'lock', 'metadata' ), true ) ) {
				continue;
			}
			$v    = array_key_exists( $name, $attrs ) ? $attrs[ $name ] : ( $def['default'] ?? null );
			$type = (string) ( $def['type'] ?? 'string' );
			if ( 'boolean' === $type ) {
				$v = Format::bool( $v );
			} elseif ( 'integer' === $type || 'number' === $type ) {
				$v = (int) $v;
			} else {
				$v = sanitize_text_field( (string) $v );
			}
			$out[ $name ] = $v;
		}
		if ( isset( $out['webcam'] ) ) {
			$out['webcam'] = sanitize_title( (string) $out['webcam'] );
		}
		if ( isset( $out['link'] ) && '' !== $out['link'] ) {
			// Modello di link con il segnaposto {slug}.
			$out['link'] = str_replace( '%7Bslug%7D', '{slug}', esc_url_raw( str_replace( '{slug}', '%7Bslug%7D', (string) $out['link'] ) ) );
		}
		return $out;
	}
}
