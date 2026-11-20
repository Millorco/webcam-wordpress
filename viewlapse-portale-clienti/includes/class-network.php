<?php
/**
 * Amministrazione della rete: portal, sottositi dei clienti, chiavi, domini.
 *
 * @package Viewlapse\Portale
 */

namespace Viewlapse\Portale;

defined( 'ABSPATH' ) || exit;

/**
 * Pagina "Clienti Viewlapse" in Amministrazione network.
 */
final class Network {

	private const PAGE = 'vl-portale';

	/**
	 * Hook.
	 */
	public static function boot(): void {
		add_action( 'network_admin_menu', array( self::class, 'menu' ) );
		foreach ( array( 'settings', 'create', 'key', 'test', 'domain' ) as $a ) {
			add_action( 'admin_post_vl_portale_' . $a, array( self::class, 'handle_' . $a ) );
		}
	}

	/**
	 * Voce di menu.
	 */
	public static function menu(): void {
		add_menu_page( __( 'Clienti Viewlapse', 'viewlapse-portale-clienti' ), __( 'Clienti Viewlapse', 'viewlapse-portale-clienti' ), 'manage_network_options', self::PAGE, array( self::class, 'page' ), 'dashicons-camera', 22 );
	}

	/**
	 * URL di un'azione.
	 */
	private static function action_url(): string {
		return admin_url( 'admin-post.php' );
	}

	/**
	 * Campi comuni dei moduli.
	 *
	 * @param string $action Azione.
	 */
	private static function form_fields( string $action ): void {
		echo '<input type="hidden" name="action" value="vl_portale_' . esc_attr( $action ) . '">';
		wp_nonce_field( 'vl_portale_' . $action );
	}

	/**
	 * Pagina.
	 */
	public static function page(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}
		$net   = Keys::network();
		$msg   = get_site_transient( 'vl_portale_msg_' . get_current_user_id() );
		$sites = Sites::customer_site_ids();
		delete_site_transient( 'vl_portale_msg_' . get_current_user_id() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Clienti Viewlapse', 'viewlapse-portale-clienti' ); ?></h1>
			<?php
			if ( is_array( $msg ) ) {
				printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( 'ok' === $msg['type'] ? 'success' : 'error' ), wp_kses( (string) $msg['text'], array( 'a' => array( 'href' => true ), 'strong' => array() ) ) );
			}
			?>
			<h2><?php esc_html_e( 'Portal', 'viewlapse-portale-clienti' ); ?></h2>
			<form method="post" action="<?php echo esc_url( self::action_url() ); ?>">
				<?php self::form_fields( 'settings' ); ?>
				<table class="form-table" role="presentation"><tr>
					<th scope="row"><label for="vl-portal-url"><?php esc_html_e( 'Indirizzo del portal', 'viewlapse-portale-clienti' ); ?></label></th>
					<td><input type="url" class="regular-text" id="vl-portal-url" name="portal_url" value="<?php echo esc_attr( $net['portal_url'] ); ?>" placeholder="https://portal.viewlapse.com" required>
					<p class="description"><?php esc_html_e( 'Vale per tutti i sottositi dei clienti.', 'viewlapse-portale-clienti' ); ?></p></td>
				</tr></table>
				<?php submit_button( __( 'Salva', 'viewlapse-portale-clienti' ), 'secondary' ); ?>
			</form>

			<h2><?php esc_html_e( 'Sottositi dei clienti', 'viewlapse-portale-clienti' ); ?></h2>
			<?php if ( ! $sites ) : ?>
				<p><?php esc_html_e( 'Nessun sottosito cliente.', 'viewlapse-portale-clienti' ); ?></p>
			<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Sito', 'viewlapse-portale-clienti' ); ?></th>
					<th><?php esc_html_e( 'Cliente del portal', 'viewlapse-portale-clienti' ); ?></th>
					<th><?php esc_html_e( 'Chiave', 'viewlapse-portale-clienti' ); ?></th>
					<th><?php esc_html_e( 'Cambia chiave', 'viewlapse-portale-clienti' ); ?></th>
					<th><?php esc_html_e( 'Dominio proprio', 'viewlapse-portale-clienti' ); ?></th>
				</tr></thead>
				<tbody>
				<?php
				foreach ( $sites as $id ) :
					$info = (array) Keys::site( $id );
					$site = get_site( $id );
					?>
					<tr>
						<td><strong><a href="<?php echo esc_url( get_home_url( $id, '/' ) ); ?>"><?php echo esc_html( (string) get_blog_option( $id, 'blogname' ) ); ?></a></strong><br>
							<code><?php echo esc_html( $site ? $site->domain . $site->path : '' ); ?></code><br>
							<a href="<?php echo esc_url( get_admin_url( $id ) ); ?>"><?php esc_html_e( 'Bacheca', 'viewlapse-portale-clienti' ); ?></a> |
							<a href="<?php echo esc_url( get_admin_url( $id, 'users.php' ) ); ?>"><?php esc_html_e( 'Utenti', 'viewlapse-portale-clienti' ); ?></a> |
							<a href="<?php echo esc_url( get_admin_url( $id, 'customize.php' ) ); ?>"><?php esc_html_e( 'Logo e colori', 'viewlapse-portale-clienti' ); ?></a>
						</td>
						<td><?php echo esc_html( (string) ( $info['customer_name'] ?? '' ) ); ?> <span class="description">#<?php echo esc_html( (string) ( $info['customer_id'] ?? '' ) ); ?></span></td>
						<td><code><?php echo esc_html( (string) ( $info['hint'] ?? '' ) ); ?></code>
							<form method="post" action="<?php echo esc_url( self::action_url() ); ?>" style="margin-top:.3em">
								<?php self::form_fields( 'test' ); ?>
								<input type="hidden" name="site" value="<?php echo esc_attr( (string) $id ); ?>">
								<button class="button button-small"><?php esc_html_e( 'Prova', 'viewlapse-portale-clienti' ); ?></button>
							</form>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( self::action_url() ); ?>">
								<?php self::form_fields( 'key' ); ?>
								<input type="hidden" name="site" value="<?php echo esc_attr( (string) $id ); ?>">
								<input type="password" name="key" autocomplete="off" placeholder="vls_..." size="20" required>
								<button class="button button-small"><?php esc_html_e( 'Sostituisci', 'viewlapse-portale-clienti' ); ?></button>
							</form>
						</td>
						<td>
							<form method="post" action="<?php echo esc_url( self::action_url() ); ?>">
								<?php self::form_fields( 'domain' ); ?>
								<input type="hidden" name="site" value="<?php echo esc_attr( (string) $id ); ?>">
								<input type="text" name="domain" placeholder="webcam.cliente.it" size="18" required>
								<button class="button button-small"><?php esc_html_e( 'Usa', 'viewlapse-portale-clienti' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>

			<h2><?php esc_html_e( 'Nuovo sottosito cliente', 'viewlapse-portale-clienti' ); ?></h2>
			<p><?php esc_html_e( 'Il cliente si sceglie con la sua chiave "client" del portal (Pannello del portal → Clienti → scheda del cliente → Chiavi API dei siti, oppure bin/site-key.php --crea --cliente ID --scope client): il plugin la verifica e mostra il nome del cliente. Il sottosito nasce con le pagine Webcam, Archivio, Confronto e Dati, riservate agli utenti del sito.', 'viewlapse-portale-clienti' ); ?></p>
			<form method="post" action="<?php echo esc_url( self::action_url() ); ?>">
				<?php self::form_fields( 'create' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="vl-new-key"><?php esc_html_e( 'Chiave client del cliente', 'viewlapse-portale-clienti' ); ?></label></th>
						<td><input type="password" class="regular-text" id="vl-new-key" name="key" autocomplete="off" placeholder="vls_..." required></td></tr>
					<tr><th scope="row"><label for="vl-new-slug"><?php esc_html_e( 'Indirizzo del sottosito', 'viewlapse-portale-clienti' ); ?></label></th>
						<td><?php echo esc_html( is_subdomain_install() ? '' : network_home_url( '/' ) ); ?><input type="text" id="vl-new-slug" name="slug" pattern="[a-z0-9][a-z0-9-]+" placeholder="impresa-rossi" required><?php echo esc_html( is_subdomain_install() ? '.' . get_network()->domain : '/' ); ?>
						<p class="description"><?php esc_html_e( 'Si può passare in seguito a un dominio proprio del cliente.', 'viewlapse-portale-clienti' ); ?></p></td></tr>
					<tr><th scope="row"><label for="vl-new-title"><?php esc_html_e( 'Titolo', 'viewlapse-portale-clienti' ); ?></label></th>
						<td><input type="text" class="regular-text" id="vl-new-title" name="title" placeholder="<?php esc_attr_e( 'Vuoto = nome del cliente nel portal', 'viewlapse-portale-clienti' ); ?>"></td></tr>
					<tr><th scope="row"><label for="vl-new-email"><?php esc_html_e( 'Primo utente del cliente', 'viewlapse-portale-clienti' ); ?></label></th>
						<td><input type="email" class="regular-text" id="vl-new-email" name="email" placeholder="nome@cliente.it">
						<select name="role">
							<option value="<?php echo esc_attr( Sites::ROLE_DOWNLOAD ); ?>"><?php esc_html_e( 'Cliente - download', 'viewlapse-portale-clienti' ); ?></option>
							<option value="<?php echo esc_attr( Sites::ROLE_VIEW ); ?>"><?php esc_html_e( 'Cliente - visione', 'viewlapse-portale-clienti' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Facoltativo. Riceve l\'email di WordPress per scegliere la password. Altri utenti: Utenti del sottosito.', 'viewlapse-portale-clienti' ); ?></p></td></tr>
				</table>
				<?php submit_button( __( 'Crea il sottosito', 'viewlapse-portale-clienti' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Controllo comune: super amministratore e nonce.
	 *
	 * @param string $action Azione.
	 */
	private static function check( string $action ): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'viewlapse-portale-clienti' ), 403 );
		}
		check_admin_referer( 'vl_portale_' . $action );
	}

	/**
	 * Torna alla pagina con un messaggio.
	 *
	 * @param string $type ok|error.
	 * @param string $text Testo (HTML già sicuro).
	 */
	private static function back( string $type, string $text ): void {
		set_site_transient(
			'vl_portale_msg_' . get_current_user_id(),
			array(
				'type' => $type,
				'text' => $text,
			),
			120
		);
		wp_safe_redirect( network_admin_url( 'admin.php?page=' . self::PAGE ) );
		exit;
	}

	/**
	 * Valore POST come testo.
	 *
	 * @param string $k Nome.
	 */
	private static function post( string $k ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- il nonce si controlla in check().
		return isset( $_POST[ $k ] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST[ $k ] ) ) ) : '';
	}

	/**
	 * Sito cliente dal modulo.
	 */
	private static function site_from_post(): int {
		$id = absint( self::post( 'site' ) );
		if ( ! $id || null === Keys::site( $id ) ) {
			self::back( 'error', esc_html__( 'Sottosito non trovato.', 'viewlapse-portale-clienti' ) );
		}
		return $id;
	}

	/**
	 * Salva l'URL del portal.
	 */
	public static function handle_settings(): void {
		self::check( 'settings' );
		$url = untrailingslashit( esc_url_raw( self::post( 'portal_url' ), array( 'http', 'https' ) ) );
		update_site_option( Keys::NETWORK, array( 'portal_url' => $url ) );
		foreach ( Sites::customer_site_ids() as $id ) {
			switch_to_blog( $id );
			\Viewlapse\Webcam\Api::flush();
			restore_current_blog();
		}
		self::back( 'ok', esc_html__( 'Indirizzo del portal salvato.', 'viewlapse-portale-clienti' ) );
	}

	/**
	 * Crea un sottosito.
	 */
	public static function handle_create(): void {
		self::check( 'create' );
		$res = Sites::create(
			array(
				'slug'  => self::post( 'slug' ),
				'title' => self::post( 'title' ),
				'key'   => self::post( 'key' ),
				'email' => self::post( 'email' ),
				'role'  => sanitize_key( self::post( 'role' ) ),
			)
		);
		if ( is_wp_error( $res ) ) {
			$data = $res->get_error_data();
			if ( is_array( $data ) && ! empty( $data['site_id'] ) ) {
				self::back( 'error', esc_html__( 'Sottosito creato, ma:', 'viewlapse-portale-clienti' ) . ' ' . esc_html( $res->get_error_message() ) );
			}
			self::back( 'error', esc_html( $res->get_error_message() ) );
		}
		$info = (array) Keys::site( $res );
		self::back(
			'ok',
			sprintf(
				/* translators: 1: cliente, 2: link al sito */
				esc_html__( 'Sottosito creato per %1$s: %2$s', 'viewlapse-portale-clienti' ),
				'<strong>' . esc_html( (string) ( $info['customer_name'] ?? '' ) ) . '</strong>',
				'<a href="' . esc_url( get_home_url( $res, '/' ) ) . '">' . esc_html( get_home_url( $res, '/' ) ) . '</a>'
			)
		);
	}

	/**
	 * Sostituisce la chiave di un sottosito.
	 */
	public static function handle_key(): void {
		self::check( 'key' );
		$id  = self::site_from_post();
		$key = self::post( 'key' );
		$who = Keys::verify( $key );
		if ( is_wp_error( $who ) ) {
			self::back( 'error', esc_html( $who->get_error_message() ) );
		}
		$old = (array) Keys::site( $id );
		Keys::store( $id, $key, $who );
		$text = esc_html__( 'Chiave sostituita.', 'viewlapse-portale-clienti' );
		if ( (int) ( $old['customer_id'] ?? 0 ) !== (int) $who['customer']['id'] ) {
			/* translators: %s: cliente */
			$text .= ' ' . sprintf( esc_html__( 'Attenzione: ora il sito mostra le webcam di %s.', 'viewlapse-portale-clienti' ), '<strong>' . esc_html( (string) $who['customer']['name'] ) . '</strong>' );
		}
		self::back( 'ok', $text );
	}

	/**
	 * Prova la chiave di un sottosito.
	 */
	public static function handle_test(): void {
		self::check( 'test' );
		$id = self::site_from_post();
		switch_to_blog( $id );
		$who  = Keys::verify( Keys::current_key() );
		$list = is_wp_error( $who ) ? $who : \Viewlapse\Webcam\Api::get_fresh( 'webcams' );
		restore_current_blog();
		if ( is_wp_error( $list ) ) {
			self::back( 'error', esc_html( $list->get_error_message() ) );
		}
		$names = array_map(
			static function ( $w ): string {
				$name = (string) $w['name'];
				if ( 'archived' === ( $w['state'] ?? '' ) ) {
					$name .= ' ' . __( '(archivio)', 'viewlapse-portale-clienti' );
				}
				// 1.1: funzioni del listino della webcam, come le manda il portal.
				if ( isset( $w['features'] ) && is_array( $w['features'] ) ) {
					$name .= ' [' . implode( ', ', array_map( 'strval', $w['features'] ) ) . ']';
				}
				return $name;
			},
			(array) ( $list['webcams'] ?? array() )
		);
		$first = (array) ( $list['webcams'][0] ?? array() );
		$note  = '';
		if ( ! isset( $who['server'] ) && ! isset( $first['features'] ) ) {
			$note = ' ' . esc_html__( 'Il portal usa ancora l\'API dei siti 1.0: le pagine Timelapse, Stesso momento, Foto del giorno, Statistiche e Rapporti restano vuote per i clienti finché non viene aggiornato.', 'viewlapse-portale-clienti' );
		} elseif ( is_array( $who['server'] ?? null ) && isset( $who['server']['timelapse'] ) && ! $who['server']['timelapse'] ) {
			$note = ' ' . esc_html__( 'Il portal non produce timelapse nuovi (ffmpeg mancante).', 'viewlapse-portale-clienti' );
		}
		self::back(
			'ok',
			sprintf(
				/* translators: 1: cliente, 2: elenco delle webcam */
				esc_html__( 'Chiave valida per %1$s. Webcam: %2$s', 'viewlapse-portale-clienti' ),
				'<strong>' . esc_html( (string) $who['customer']['name'] ) . '</strong>',
				esc_html( $names ? implode( '; ', $names ) : __( 'nessuna', 'viewlapse-portale-clienti' ) )
			) . $note
		);
	}

	/**
	 * Dominio proprio del cliente (mappatura nativa di Multisite).
	 */
	public static function handle_domain(): void {
		self::check( 'domain' );
		$id     = self::site_from_post();
		$domain = strtolower( self::post( 'domain' ) );
		$domain = (string) preg_replace( '#^https?://#', '', rtrim( $domain, '/' ) );
		if ( ! preg_match( '/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain ) ) {
			self::back( 'error', esc_html__( 'Dominio non valido (es. webcam.cliente.it).', 'viewlapse-portale-clienti' ) );
		}
		if ( domain_exists( $domain, '/' ) ) {
			self::back( 'error', esc_html__( 'Il dominio è già usato da un altro sito.', 'viewlapse-portale-clienti' ) );
		}
		$scheme = wp_parse_url( network_home_url(), PHP_URL_SCHEME ) ?: 'https';
		$res    = wp_update_site(
			$id,
			array(
				'domain' => $domain,
				'path'   => '/',
			)
		);
		if ( is_wp_error( $res ) ) {
			self::back( 'error', esc_html( $res->get_error_message() ) );
		}
		update_blog_option( $id, 'home', $scheme . '://' . $domain );
		update_blog_option( $id, 'siteurl', $scheme . '://' . $domain );
		self::back(
			'ok',
			sprintf(
				/* translators: %s: dominio */
				esc_html__( 'Il sito ora risponde su %s. Il DNS del dominio deve puntare a questo server e il server web deve accettarlo (vedi README).', 'viewlapse-portale-clienti' ),
				'<strong>' . esc_html( $scheme . '://' . $domain ) . '</strong>'
			)
		);
	}
}
