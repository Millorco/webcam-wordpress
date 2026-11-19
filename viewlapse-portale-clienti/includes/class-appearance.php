<?php
/**
 * Logo e colori del cliente (Personalizza), barra in alto, pagina di login.
 *
 * @package Viewlapse\Portale
 */

namespace Viewlapse\Portale;

defined( 'ABSPATH' ) || exit;

/**
 * Sezione "Cliente Viewlapse" del Personalizza: logo, colore principale e
 * colore del testo sul colore principale. Valgono per i blocchi di
 * Viewlapse Webcam (variabili CSS), per la barra del cliente in cima alle
 * pagine e per la pagina di login.
 */
final class Appearance {

	public const OPTION = 'vl_portale_aspetto';

	/**
	 * Hook.
	 */
	public static function boot(): void {
		add_action( 'customize_register', array( self::class, 'customize' ) );
		add_action( 'customize_save_after', array( self::class, 'sync_logo' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'styles' ) );
		add_action( 'wp_body_open', array( self::class, 'bar' ) );
		add_action( 'login_head', array( self::class, 'login_styles' ) );
		add_filter( 'login_headerurl', static fn( $url ) => Sites::is_customer_site() ? home_url( '/' ) : $url );
		add_filter( 'login_headertext', static fn( $text ) => Sites::is_customer_site() ? get_bloginfo( 'name' ) : $text );
		add_filter( 'login_message', array( self::class, 'login_message' ) );
		add_action( 'admin_menu', array( self::class, 'customize_menu' ) );
	}

	/**
	 * Valori correnti.
	 *
	 * @return array{logo:int, accent:string, accent_text:string}
	 */
	public static function get(): array {
		$v = get_option( self::OPTION, array() );
		$v = is_array( $v ) ? $v : array();
		return array(
			'logo'        => absint( $v['logo'] ?? 0 ),
			'accent'      => sanitize_hex_color( (string) ( $v['accent'] ?? '' ) ) ?: '#0b6e99',
			'accent_text' => sanitize_hex_color( (string) ( $v['accent_text'] ?? '' ) ) ?: '#ffffff',
		);
	}

	/**
	 * Nei temi a blocchi il Personalizza non ha una voce di menu: la si aggiunge.
	 */
	public static function customize_menu(): void {
		if ( Sites::is_customer_site() && wp_is_block_theme() ) {
			add_theme_page( __( 'Personalizza', 'viewlapse-portale-clienti' ), __( 'Personalizza', 'viewlapse-portale-clienti' ), 'customize', 'customize.php' );
		}
	}

	/**
	 * Controlli del Personalizza.
	 *
	 * @param \WP_Customize_Manager $wp_customize Personalizza.
	 */
	public static function customize( $wp_customize ): void {
		if ( ! Sites::is_customer_site() ) {
			return;
		}
		$wp_customize->add_section(
			'vl_portale',
			array(
				'title'       => __( 'Cliente Viewlapse', 'viewlapse-portale-clienti' ),
				'description' => __( 'Logo e colori del cliente: barra in alto, blocchi delle webcam e pagina di login.', 'viewlapse-portale-clienti' ),
				'priority'    => 25,
			)
		);
		$wp_customize->add_setting(
			self::OPTION . '[logo]',
			array(
				'type'              => 'option',
				'capability'        => 'customize',
				'sanitize_callback' => 'absint',
			)
		);
		$wp_customize->add_control(
			new \WP_Customize_Media_Control(
				$wp_customize,
				self::OPTION . '[logo]',
				array(
					'label'     => __( 'Logo del cliente', 'viewlapse-portale-clienti' ),
					'section'   => 'vl_portale',
					'mime_type' => 'image',
				)
			)
		);
		foreach ( array(
			'accent'      => array( __( 'Colore principale', 'viewlapse-portale-clienti' ), '#0b6e99' ),
			'accent_text' => array( __( 'Testo sul colore principale', 'viewlapse-portale-clienti' ), '#ffffff' ),
		) as $k => [ $label, $default ] ) {
			$wp_customize->add_setting(
				self::OPTION . '[' . $k . ']',
				array(
					'type'              => 'option',
					'capability'        => 'customize',
					'default'           => $default,
					'sanitize_callback' => 'sanitize_hex_color',
				)
			);
			$wp_customize->add_control(
				new \WP_Customize_Color_Control(
					$wp_customize,
					self::OPTION . '[' . $k . ']',
					array(
						'label'   => $label,
						'section' => 'vl_portale',
					)
				)
			);
		}
	}

	/**
	 * Il logo vale anche come logo del sito (blocco "Logo del sito" del tema).
	 */
	public static function sync_logo(): void {
		if ( ! Sites::is_customer_site() ) {
			return;
		}
		$logo = self::get()['logo'];
		if ( $logo > 0 ) {
			update_option( 'site_logo', $logo );
			set_theme_mod( 'custom_logo', $logo );
		}
	}

	/**
	 * CSS delle variabili (dopo lo stile di Viewlapse Webcam).
	 */
	private static function css(): string {
		$a = self::get();
		return ':root{--vl-portale-accent:' . $a['accent'] . ';--vl-portale-accent-text:' . $a['accent_text'] . '}'
			. '.vl-block{--vl-accent:' . $a['accent'] . ';--vl-accent-text:' . $a['accent_text'] . '}';
	}

	/**
	 * Stili del frontend.
	 */
	public static function styles(): void {
		if ( ! Sites::is_customer_site() ) {
			return;
		}
		wp_enqueue_style( 'viewlapse-portale', plugins_url( 'assets/css/portale.css', VIEWLAPSE_PORTALE_FILE ), array(), VIEWLAPSE_PORTALE_VERSION );
		wp_add_inline_style( 'viewlapse-portale', self::css() );
		// Le variabili valgono anche per i blocchi (stile caricato dal blocco).
		wp_add_inline_style( 'viewlapse-webcam', self::css() );
	}

	/**
	 * Barra del cliente in cima alle pagine: logo, nome, utente, Esci.
	 */
	public static function bar(): void {
		if ( ! Sites::is_customer_site() ) {
			return;
		}
		$a    = self::get();
		$logo = $a['logo'] ? wp_get_attachment_image( $a['logo'], 'medium', false, array( 'class' => 'vl-portale-bar__logo', 'alt' => get_bloginfo( 'name' ) ) ) : '';
		echo '<div class="vl-portale-bar"><a class="vl-portale-bar__home" href="' . esc_url( home_url( '/' ) ) . '">';
		echo $logo ? wp_kses_post( $logo ) : '<span class="vl-portale-bar__name">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
		echo '</a><span class="vl-portale-bar__user">';
		if ( is_user_logged_in() ) {
			$u = wp_get_current_user();
			echo '<span>' . esc_html( $u->display_name ) . '</span> <a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Esci', 'viewlapse-portale-clienti' ) . '</a>';
		} else {
			echo '<a href="' . esc_url( wp_login_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Accedi', 'viewlapse-portale-clienti' ) . '</a>';
		}
		echo '</span></div>';
	}

	/**
	 * Pagina di login con logo e colori del cliente.
	 */
	public static function login_styles(): void {
		if ( ! Sites::is_customer_site() ) {
			return;
		}
		$a   = self::get();
		$url = $a['logo'] ? wp_get_attachment_image_url( $a['logo'], 'medium' ) : '';
		$css = 'body.login{background:#f4f6f8}'
			. '.login .button-primary{background:' . $a['accent'] . ';border-color:' . $a['accent'] . ';color:' . $a['accent_text'] . '}'
			. '.login .button-primary:hover,.login .button-primary:focus{background:' . $a['accent'] . ';filter:brightness(1.1);border-color:' . $a['accent'] . ';color:' . $a['accent_text'] . '}'
			. '.login #backtoblog a,.login #nav a{color:' . $a['accent'] . '}'
			. 'input[type=text]:focus,input[type=password]:focus,input[type=checkbox]:focus{border-color:' . $a['accent'] . ';box-shadow:0 0 0 1px ' . $a['accent'] . '}';
		if ( $url ) {
			$css .= '.login h1 a{background-image:url("' . esc_url( $url ) . '");background-size:contain;background-position:center;width:100%;max-width:320px;height:110px}';
		} else {
			$css .= '.login h1 a{background:none;text-indent:0;width:auto;height:auto;font-size:1.6rem;font-weight:600;color:#1d2327;line-height:1.3}';
		}
		echo '<style id="vl-portale-login">' . wp_strip_all_tags( $css ) . '</style>';
	}

	/**
	 * Messaggio sopra il modulo di login.
	 *
	 * @param string $msg Messaggio.
	 */
	public static function login_message( $msg ): string {
		if ( ! Sites::is_customer_site() || '' !== (string) $msg ) {
			return (string) $msg;
		}
		return '<p class="message">' . esc_html__( 'Area riservata: accedi con l\'utente che ti abbiamo inviato.', 'viewlapse-portale-clienti' ) . '</p>';
	}
}
