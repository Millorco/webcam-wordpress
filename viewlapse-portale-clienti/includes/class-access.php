<?php
/**
 * Area riservata dei sottositi dei clienti.
 *
 * @package Viewlapse\Portale
 */

namespace Viewlapse\Portale;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Solo gli utenti del sottosito (capacità vl_view_webcam) vedono le pagine,
 * salvo le pagine rese pubbliche una per una (meta _vl_pubblica). Vale anche
 * per l'API REST di WordPress, i feed e le mappe del sito.
 */
final class Access {

	public const META = '_vl_pubblica';

	/**
	 * Hook.
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'register_meta' ) );
		add_action( 'template_redirect', array( self::class, 'guard' ), 0 );
		add_filter( 'rest_pre_dispatch', array( self::class, 'guard_rest' ), 10, 3 );
		add_filter( 'viewlapse_webcam_context_public', array( self::class, 'context_public' ) );
		add_filter( 'viewlapse_webcam_rest_allowed', array( self::class, 'rest_allowed' ), 10, 2 );
		add_filter( 'wp_sitemaps_enabled', static fn( $on ) => Sites::is_customer_site() ? false : $on );
		add_filter( 'xmlrpc_enabled', static fn( $on ) => Sites::is_customer_site() ? false : $on );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_filter( 'login_redirect', array( self::class, 'login_redirect' ), 10, 3 );
		add_filter( 'show_admin_bar', static fn( $show ) => ( Sites::is_customer_site() && ! current_user_can( 'edit_posts' ) ) ? false : $show );
		add_action( 'admin_init', array( self::class, 'keep_customers_out_of_admin' ) );
		add_action( 'enqueue_block_editor_assets', array( self::class, 'editor_assets' ) );
		add_filter( 'page_row_actions', array( self::class, 'row_actions' ), 10, 2 );
		add_filter( 'display_post_states', array( self::class, 'post_states' ), 10, 2 );
		add_action( 'admin_post_vl_portale_toggle', array( self::class, 'toggle' ) );
	}

	/**
	 * Meta della pagina pubblica, modificabile dall'editor dei blocchi.
	 */
	public static function register_meta(): void {
		register_post_meta(
			'page',
			self::META,
			array(
				'type'          => 'boolean',
				'single'        => true,
				'default'       => false,
				'show_in_rest'  => true,
				'auth_callback' => static fn(): bool => current_user_can( 'edit_pages' ),
			)
		);
	}

	/**
	 * La pagina è pubblica.
	 *
	 * @param int $id Pagina.
	 */
	public static function is_public_page( int $id ): bool {
		return $id > 0 && 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) && (bool) get_post_meta( $id, self::META, true );
	}

	/**
	 * L'utente corrente vede l'area riservata.
	 */
	public static function can_view(): bool {
		return is_user_logged_in() && current_user_can( Sites::CAP_VIEW );
	}

	/**
	 * Pagine: login obbligatorio salvo le pagine pubbliche.
	 */
	public static function guard(): void {
		if ( ! Sites::is_customer_site() || self::can_view() ) {
			return;
		}
		if ( is_singular( 'page' ) && self::is_public_page( (int) get_queried_object_id() ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			nocache_headers();
			wp_safe_redirect( wp_login_url( self::requested_url() ) );
			exit;
		}
		wp_die(
			'<p>' . esc_html__( 'Il tuo utente non ha accesso a quest\'area riservata.', 'viewlapse-portale-clienti' ) . '</p><p><a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Esci', 'viewlapse-portale-clienti' ) . '</a></p>',
			esc_html__( 'Accesso riservato', 'viewlapse-portale-clienti' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * URL chiesto (per tornarci dopo il login).
	 */
	private static function requested_url(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$home = wp_parse_url( home_url( '/' ) );
		$base = ( $home['scheme'] ?? 'https' ) . '://' . ( $home['host'] ?? '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		return $base . $uri;
	}

	/**
	 * API REST: per chi non è utente del sito solo il ridisegno dei blocchi (che ha i suoi controlli).
	 *
	 * @param mixed           $result  Risultato.
	 * @param \WP_REST_Server $server  Server.
	 * @param WP_REST_Request $request Richiesta.
	 * @return mixed
	 */
	public static function guard_rest( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! Sites::is_customer_site() || self::can_view() ) {
			return $result;
		}
		$route = (string) $request->get_route();
		if ( str_starts_with( $route, '/viewlapse/v1/render' ) ) {
			return $result;
		}
		return new WP_Error( 'rest_forbidden', __( 'Area riservata: accesso necessario.', 'viewlapse-portale-clienti' ), array( 'status' => is_user_logged_in() ? 403 : 401 ) );
	}

	/**
	 * Nel contesto firmato dei blocchi: id della pagina se è pubblica, altrimenti false.
	 *
	 * @param mixed $public Valore di Viewlapse Webcam.
	 * @return mixed
	 */
	public static function context_public( $public ) {
		if ( ! Sites::is_customer_site() ) {
			return $public;
		}
		$id = (int) get_queried_object_id();
		return self::is_public_page( $id ) ? $id : false;
	}

	/**
	 * Ridisegno via REST: utenti del sito, oppure blocchi di una pagina ancora pubblica.
	 *
	 * @param bool                 $allowed Valore precedente.
	 * @param array<string, mixed> $ctx     Contesto verificato.
	 */
	public static function rest_allowed( $allowed, array $ctx ): bool {
		if ( ! Sites::is_customer_site() ) {
			return (bool) $allowed;
		}
		if ( self::can_view() ) {
			return true;
		}
		return is_int( $ctx['p'] ?? null ) && self::is_public_page( (int) $ctx['p'] );
	}

	/**
	 * Niente indicizzazione delle pagine riservate.
	 *
	 * @param array<string, mixed> $robots Direttive.
	 * @return array<string, mixed>
	 */
	public static function robots( array $robots ): array {
		if ( Sites::is_customer_site() && ! ( is_singular( 'page' ) && self::is_public_page( (int) get_queried_object_id() ) ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * Dopo il login i clienti tornano al sito, non alla bacheca.
	 *
	 * @param string            $to   Destinazione.
	 * @param string            $req  Destinazione chiesta.
	 * @param \WP_User|WP_Error $user Utente.
	 */
	public static function login_redirect( $to, $req, $user ): string {
		if ( Sites::is_customer_site() && $user instanceof \WP_User && ! user_can( $user, 'edit_posts' ) ) {
			return ( '' !== (string) $req && ! str_contains( (string) $req, '/wp-admin' ) ) ? (string) $req : home_url( '/' );
		}
		return (string) $to;
	}

	/**
	 * I clienti non usano la bacheca (resta il profilo, per la password).
	 */
	public static function keep_customers_out_of_admin(): void {
		if ( ! Sites::is_customer_site() || wp_doing_ajax() || current_user_can( 'edit_posts' ) || ! is_user_logged_in() ) {
			return;
		}
		global $pagenow;
		if ( in_array( $pagenow, array( 'profile.php', 'admin-post.php' ), true ) ) {
			return;
		}
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Pannello "Pagina pubblica" nell'editor (senza build).
	 */
	public static function editor_assets(): void {
		if ( ! Sites::is_customer_site() || 'page' !== get_current_screen()?->post_type ) {
			return;
		}
		wp_enqueue_script(
			'viewlapse-portale-editor',
			plugins_url( 'assets/js/editor-pubblica.js', VIEWLAPSE_PORTALE_FILE ),
			array( 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n', 'wp-edit-post' ),
			VIEWLAPSE_PORTALE_VERSION,
			true
		);
	}

	/**
	 * Azione "Rendi pubblica / riservata" nell'elenco delle pagine.
	 *
	 * @param array<string, string> $actions Azioni.
	 * @param \WP_Post              $post    Pagina.
	 * @return array<string, string>
	 */
	public static function row_actions( array $actions, $post ): array {
		if ( ! Sites::is_customer_site() || ! current_user_can( 'edit_page', $post->ID ) ) {
			return $actions;
		}
		$public = (bool) get_post_meta( $post->ID, self::META, true );
		$url    = wp_nonce_url( admin_url( 'admin-post.php?action=vl_portale_toggle&post=' . $post->ID ), 'vl_portale_toggle_' . $post->ID );
		$actions['vl_public'] = '<a href="' . esc_url( $url ) . '">' . ( $public ? esc_html__( 'Rendi riservata', 'viewlapse-portale-clienti' ) : esc_html__( 'Rendi pubblica', 'viewlapse-portale-clienti' ) ) . '</a>';
		return $actions;
	}

	/**
	 * Stato "Pubblica" nell'elenco delle pagine.
	 *
	 * @param array<string, string> $states Stati.
	 * @param \WP_Post              $post   Pagina.
	 * @return array<string, string>
	 */
	public static function post_states( array $states, $post ): array {
		if ( Sites::is_customer_site() && 'page' === $post->post_type && get_post_meta( $post->ID, self::META, true ) ) {
			$states['vl_public'] = __( 'Pubblica (senza login)', 'viewlapse-portale-clienti' );
		}
		return $states;
	}

	/**
	 * Cambia pubblica / riservata.
	 */
	public static function toggle(): void {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'vl_portale_toggle_' . $id );
		if ( ! current_user_can( 'edit_page', $id ) || 'page' !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'viewlapse-portale-clienti' ), 403 );
		}
		update_post_meta( $id, self::META, get_post_meta( $id, self::META, true ) ? 0 : 1 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=page' ) );
		exit;
	}
}
