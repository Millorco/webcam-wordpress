<?php
/**
 * Sottositi dei clienti: creazione, pagine, ruoli, collegamento a Viewlapse Webcam.
 *
 * @package Viewlapse\Portale
 */

namespace Viewlapse\Portale;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Un sottosito per cliente.
 */
final class Sites {

	public const ROLE_VIEW     = 'vl_cliente_visione';
	public const ROLE_DOWNLOAD = 'vl_cliente_download';
	public const CAP_VIEW      = 'vl_view_webcam';
	public const CAP_DOWNLOAD  = 'vl_download_original';

	/**
	 * Hook.
	 */
	public static function boot(): void {
		add_filter( 'viewlapse_webcam_settings', array( self::class, 'webcam_settings' ) );
		add_filter( 'viewlapse_webcam_settings_locked', array( self::class, 'is_customer_site' ) );
		add_filter( 'viewlapse_webcam_show_settings', static fn( $show ) => self::is_customer_site() ? is_super_admin() : $show );
		add_action( 'init', array( self::class, 'ensure_roles' ) );
	}

	/**
	 * Il sito corrente è il sottosito di un cliente.
	 */
	public static function is_customer_site(): bool {
		return null !== Keys::site();
	}

	/**
	 * Id dei sottositi dei clienti.
	 *
	 * @return array<int, int>
	 */
	public static function customer_site_ids(): array {
		$ids = array();
		foreach ( get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		) as $id ) {
			if ( null !== Keys::site( (int) $id ) ) {
				$ids[] = (int) $id;
			}
		}
		return $ids;
	}

	/**
	 * Nei sottositi dei clienti Viewlapse Webcam usa la chiave del cliente e il portal della rete.
	 *
	 * @param array<string, mixed> $s Impostazioni.
	 * @return array<string, mixed>
	 */
	public static function webcam_settings( array $s ): array {
		if ( ! self::is_customer_site() ) {
			return $s;
		}
		$s['api_key'] = Keys::current_key();
		$url          = Keys::network()['portal_url'];
		if ( '' !== $url ) {
			$s['portal_url'] = $url;
		}
		return $s;
	}

	/**
	 * Crea i ruoli se mancano (sottositi dei clienti).
	 */
	public static function ensure_roles(): void {
		if ( self::is_customer_site() && ( null === get_role( self::ROLE_DOWNLOAD ) || null === get_role( self::ROLE_VIEW ) ) ) {
			self::roles();
		}
	}

	/**
	 * Ruoli "Cliente - visione" e "Cliente - download" del sito corrente.
	 */
	public static function roles(): void {
		remove_role( self::ROLE_VIEW );
		remove_role( self::ROLE_DOWNLOAD );
		add_role(
			self::ROLE_VIEW,
			__( 'Cliente - visione', 'viewlapse-portale-clienti' ),
			array(
				'read'         => true,
				self::CAP_VIEW => true,
			)
		);
		add_role(
			self::ROLE_DOWNLOAD,
			__( 'Cliente - download', 'viewlapse-portale-clienti' ),
			array(
				'read'             => true,
				self::CAP_VIEW     => true,
				self::CAP_DOWNLOAD => true,
			)
		);
		foreach ( array( 'administrator', 'editor' ) as $r ) {
			$role = get_role( $r );
			if ( $role ) {
				$role->add_cap( self::CAP_VIEW );
				$role->add_cap( self::CAP_DOWNLOAD );
			}
		}
	}

	/**
	 * Crea il sottosito di un cliente.
	 *
	 * @param array{slug:string, title:string, key:string, email:string, role:string} $in Dati del modulo.
	 * @return int|WP_Error Id del sito.
	 */
	public static function create( array $in ) {
		$who = Keys::verify( $in['key'] );
		if ( is_wp_error( $who ) ) {
			return $who;
		}
		$slug = strtolower( $in['slug'] );
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{1,62}$/', $slug ) ) {
			return new WP_Error( 'vl_slug', __( 'Indirizzo non valido: solo lettere minuscole, numeri e trattini (almeno 2 caratteri).', 'viewlapse-portale-clienti' ) );
		}
		$network = get_network();
		if ( is_subdomain_install() ) {
			$domain = $slug . '.' . preg_replace( '|^www\.|', '', $network->domain );
			$path   = $network->path;
		} else {
			if ( in_array( $slug, get_subdirectory_reserved_names(), true ) ) {
				return new WP_Error( 'vl_slug', __( 'Questo indirizzo è riservato da WordPress.', 'viewlapse-portale-clienti' ) );
			}
			$domain = $network->domain;
			$path   = $network->path . $slug . '/';
		}
		if ( domain_exists( $domain, $path, $network->id ) ) {
			return new WP_Error( 'vl_exists', __( 'Esiste già un sito con questo indirizzo.', 'viewlapse-portale-clienti' ) );
		}
		$title = '' !== trim( $in['title'] ) ? trim( $in['title'] ) : (string) $who['customer']['name'];
		$id    = wp_insert_site(
			array(
				'domain'     => $domain,
				'path'       => $path,
				'network_id' => $network->id,
				'title'      => $title,
				'user_id'    => get_current_user_id(),
				'options'    => array(
					'blog_public' => 0,
				),
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		Keys::store( (int) $id, $in['key'], $who );
		switch_to_blog( (int) $id );
		self::setup( $title );
		$user = '' !== $in['email'] ? self::add_customer_user( $in['email'], $in['role'] ) : null;
		restore_current_blog();
		if ( is_wp_error( $user ) ) {
			return new WP_Error( 'vl_user', $user->get_error_message(), array( 'site_id' => (int) $id ) );
		}
		return (int) $id;
	}

	/**
	 * Prepara il sito corrente: ruoli, pagine con i blocchi, prima pagina, impostazioni.
	 *
	 * @param string $title Nome del cliente.
	 */
	public static function setup( string $title ): void {
		self::roles();
		foreach ( array( 'Hello world!', 'Ciao mondo!' ) as $t ) {
			$p = get_page_by_path( sanitize_title( $t ), OBJECT, 'post' );
			if ( $p ) {
				wp_delete_post( $p->ID, true );
			}
		}
		foreach ( array( 'sample-page', 'pagina-di-esempio', 'privacy-policy' ) as $slug ) {
			$p = get_page_by_path( $slug );
			if ( $p ) {
				wp_delete_post( $p->ID, true );
			}
		}
		$pages = self::pages();
		$ids   = array();
		$order = 1;
		foreach ( $pages as $slug => [ $page_title, $content ] ) {
			$existing = get_page_by_path( $slug );
			$data     = array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_name'      => $slug,
				'post_title'     => $page_title,
				'post_content'   => $content,
				'menu_order'     => $order++,
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			);
			if ( $existing ) {
				$data['ID'] = $existing->ID;
			}
			$ids[ $slug ] = wp_insert_post( wp_slash( $data ) );
		}
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $ids['webcam'] );
		/* translators: %s: nome del cliente */
		update_option( 'blogdescription', sprintf( __( 'Area riservata webcam di %s', 'viewlapse-portale-clienti' ), $title ) );
		update_option( 'default_comment_status', 'closed' );
		update_option( 'default_ping_status', 'closed' );
		update_option( 'users_can_register', 0 );
		update_option( 'permalink_structure', '/%postname%/' );
		// Le regole si rigenerano alla prima richiesta del sito (flush_rewrite_rules non è affidabile dopo switch_to_blog).
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Pagine create in automatico (slug => titolo, contenuto con i blocchi).
	 *
	 * @return array<string, array{0:string, 1:string}>
	 */
	public static function pages(): array {
		$p = array(
			'webcam'    => array(
				__( 'Webcam', 'viewlapse-portale-clienti' ),
				'<!-- wp:viewlapse/foto {"scelta":true,"align":"wide"} /-->' . "\n\n"
				. '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Le tue webcam', 'viewlapse-portale-clienti' ) . '</h2><!-- /wp:heading -->' . "\n\n"
				. '<!-- wp:viewlapse/elenco {"link":"?vl_cam={slug}","align":"wide"} /-->',
			),
			'archivio'  => array(
				__( 'Archivio', 'viewlapse-portale-clienti' ),
				'<!-- wp:viewlapse/archivio {"scelta":true,"colonne":4,"align":"wide"} /-->',
			),
			'confronto' => array(
				__( 'Confronto', 'viewlapse-portale-clienti' ),
				'<!-- wp:viewlapse/confronto {"scelta":true,"align":"wide"} /-->',
			),
			'dati'      => array(
				__( 'Dati', 'viewlapse-portale-clienti' ),
				'<!-- wp:viewlapse/grafico {"scelta":true,"giorni":7,"align":"wide"} /-->',
			),
		);
		/**
		 * Filtra le pagine create nei sottositi dei clienti.
		 *
		 * @param array $p slug => [titolo, contenuto].
		 */
		return (array) apply_filters( 'viewlapse_portale_pages', $p );
	}

	/**
	 * Aggiunge (o crea) l'utente del cliente al sito corrente.
	 *
	 * @param string $email Email.
	 * @param string $role  Ruolo.
	 * @return int|WP_Error
	 */
	public static function add_customer_user( string $email, string $role ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'vl_email', __( 'Email dell\'utente non valida: il sito è stato creato senza utente.', 'viewlapse-portale-clienti' ) );
		}
		$role = in_array( $role, array( self::ROLE_VIEW, self::ROLE_DOWNLOAD ), true ) ? $role : self::ROLE_VIEW;
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$login = sanitize_user( strtok( $email, '@' ), true );
			$base  = '' !== $login ? $login : 'cliente';
			$i     = 1;
			while ( username_exists( $login ) || '' === $login ) {
				$login = $base . ( ++$i );
			}
			$uid = wpmu_create_user( $login, wp_generate_password( 24 ), $email );
			if ( ! $uid ) {
				return new WP_Error( 'vl_user', __( 'Impossibile creare l\'utente del cliente.', 'viewlapse-portale-clienti' ) );
			}
			wp_new_user_notification( (int) $uid, null, 'user' );
		} else {
			$uid = $user->ID;
		}
		add_user_to_blog( get_current_blog_id(), (int) $uid, $role );
		return (int) $uid;
	}
}
