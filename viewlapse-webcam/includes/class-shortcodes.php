<?php
/**
 * Shortcode equivalenti ai blocchi.
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

defined( 'ABSPATH' ) || exit;

/**
 * [viewlapse_foto], [viewlapse_archivio], [viewlapse_confronto],
 * [viewlapse_grafico], [viewlapse_elenco]: stessi attributi dei blocchi
 * (block.json), in minuscolo.
 */
final class Shortcodes {

	/**
	 * Registrazione.
	 */
	public static function register(): void {
		foreach ( Render::KINDS as $kind ) {
			add_shortcode(
				'viewlapse_' . $kind,
				static function ( $atts ) use ( $kind ): string {
					return self::render( $kind, is_array( $atts ) ? $atts : array() );
				}
			);
		}
	}

	/**
	 * HTML dello shortcode.
	 *
	 * @param string               $kind Tipo.
	 * @param array<string, mixed> $atts Attributi dello shortcode.
	 */
	public static function render( string $kind, array $atts ): string {
		$defaults = array();
		foreach ( Blocks::schema( $kind ) as $name => $def ) {
			$defaults[ strtolower( $name ) ] = $def['default'] ?? '';
		}
		$atts  = shortcode_atts( $defaults, $atts, 'viewlapse_' . $kind );
		$attrs = Blocks::complete( $kind, $atts );
		$class = 'vl-block vl-' . $kind . ' vl-shortcode';
		return Render::render( $kind, $attrs, 'class="' . esc_attr( $class ) . '"' );
	}
}
