<?php
/**
 * Pagina delle impostazioni (Impostazioni → Viewlapse Webcam).
 *
 * @package Viewlapse\Webcam
 */

namespace Viewlapse\Webcam;

defined( 'ABSPATH' ) || exit;

/**
 * Impostazioni, prova della connessione e svuotamento della cache.
 * La chiave non torna mai nella pagina: si vede solo l'inizio.
 */
final class Admin {

	private const PAGE = 'viewlapse-webcam';

	/**
	 * Hook dell'amministrazione.
	 */
	public static function boot(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'admin_post_vl_webcam_test', array( self::class, 'test' ) );
		add_action( 'admin_post_vl_webcam_flush', array( self::class, 'flush' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( VIEWLAPSE_WEBCAM_FILE ), array( self::class, 'links' ) );
		add_action( 'admin_init', array( self::class, 'privacy' ) );
	}

	/**
	 * Testo suggerito per l'informativa sulla privacy del sito.
	 */
	public static function privacy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			__( 'Viewlapse Webcam', 'viewlapse-webcam' ),
			'<p>' . esc_html__( 'Le immagini delle webcam sono servite dal portal Viewlapse: il browser del visitatore le scarica direttamente dal portal, che vede l\'indirizzo IP come qualsiasi server web.', 'viewlapse-webcam' ) . '</p>'
			. '<p>' . esc_html__( 'Per le statistiche delle webcam il sito manda al portal un conteggio quando una webcam è visibile nella pagina (e la durata di visione, l\'avvio di un timelapse, l\'apertura di una foto). Sono statistiche aggregate senza cookie: nessun dato è salvato nel browser e il portal non conserva dati personali (l\'impronta usata per contare i visitatori distinti di un giorno non è più ricollegabile all\'indirizzo IP dopo 48 ore). Al portal arriva solo il nome del sito, non l\'indirizzo della pagina.', 'viewlapse-webcam' ) . '</p>'
		);
	}

	/**
	 * Voce di menu.
	 */
	public static function menu(): void {
		/**
		 * Mostra la pagina delle impostazioni (il portale clienti la nasconde nei sottositi).
		 *
		 * @param bool $show Predefinito true.
		 */
		if ( ! apply_filters( 'viewlapse_webcam_show_settings', true ) ) {
			return;
		}
		add_options_page( __( 'Viewlapse Webcam', 'viewlapse-webcam' ), __( 'Viewlapse Webcam', 'viewlapse-webcam' ), 'manage_options', self::PAGE, array( self::class, 'page' ) );
	}

	/**
	 * Link "Impostazioni" nell'elenco dei plugin.
	 *
	 * @param array<int|string, string> $links Link.
	 * @return array<int|string, string>
	 */
	public static function links( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Impostazioni', 'viewlapse-webcam' ) . '</a>' );
		return $links;
	}

	/**
	 * Opzione e validazione.
	 */
	public static function register(): void {
		register_setting(
			'vl_webcam',
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => static function ( $in ) {
					$old = get_option( Settings::OPTION, array() );
					if ( Settings::locked() ) {
						return $old;
					}
					return Settings::sanitize( is_array( $in ) ? $in : array(), is_array( $old ) ? $old : array() );
				},
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Pagina.
	 */
	public static function page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s      = Settings::get();
		$locked = Settings::locked();
		$o      = Settings::OPTION;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- messaggi dopo il redirect.
		$notice = isset( $_GET['vl_notice'] ) ? sanitize_key( wp_unslash( $_GET['vl_notice'] ) ) : '';
		// phpcs:enable
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Viewlapse Webcam', 'viewlapse-webcam' ); ?></h1>
			<?php
			settings_errors( Settings::OPTION );
			if ( '' !== $notice ) {
				$msg = get_transient( 'vl_webcam_notice_' . get_current_user_id() );
				delete_transient( 'vl_webcam_notice_' . get_current_user_id() );
				if ( is_array( $msg ) ) {
					printf( '<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr( 'ok' === $msg['type'] ? 'success' : 'error' ), wp_kses( (string) $msg['text'], array( 'br' => array(), 'strong' => array() ) ) );
				}
			}
			?>
			<p><?php esc_html_e( 'Il plugin legge le webcam dal portal Viewlapse con l\'API dei siti. Le chiamate partono da questo server: la chiave non arriva mai al browser dei visitatori. Senza chiave si vedono solo le webcam pubbliche.', 'viewlapse-webcam' ); ?></p>
			<?php if ( $locked ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Portal e chiave di questo sito sono gestiti dall\'amministratore della rete.', 'viewlapse-webcam' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'vl_webcam' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="vl-portal"><?php esc_html_e( 'Indirizzo del portal', 'viewlapse-webcam' ); ?></label></th>
						<td><input type="url" class="regular-text" id="vl-portal" name="<?php echo esc_attr( $o ); ?>[portal_url]" value="<?php echo esc_attr( (string) $s['portal_url'] ); ?>" placeholder="https://portal.viewlapse.com" <?php disabled( $locked ); ?>>
						<p class="description"><?php esc_html_e( 'Senza barra finale e senza /api.', 'viewlapse-webcam' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="vl-key"><?php esc_html_e( 'Chiave API del sito', 'viewlapse-webcam' ); ?></label></th>
						<td>
							<?php if ( ! $locked ) : ?>
							<input type="password" class="regular-text" id="vl-key" name="<?php echo esc_attr( $o ); ?>[api_key]" value="" autocomplete="off" placeholder="vls_...">
							<?php endif; ?>
							<p class="description">
								<?php
								if ( '' !== (string) $s['api_key'] ) {
									/* translators: %s: inizio della chiave */
									printf( esc_html__( 'Chiave impostata: %s (lasciare vuoto per non cambiarla).', 'viewlapse-webcam' ), '<code>' . esc_html( Settings::key_hint( (string) $s['api_key'] ) ) . '</code>' );
								} else {
									esc_html_e( 'Facoltativa: senza chiave solo le webcam pubbliche. Usare una chiave "public" del cliente (o "client" solo se il sito è riservato).', 'viewlapse-webcam' );
								}
								?>
							</p>
							<?php if ( '' !== (string) $s['api_key'] && ! $locked ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[api_key_remove]" value="1"> <?php esc_html_e( 'Rimuovi la chiave', 'viewlapse-webcam' ); ?></label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="vl-refresh"><?php esc_html_e( 'Aggiornamento della foto', 'viewlapse-webcam' ); ?></label></th>
						<td><input type="number" min="0" max="1440" class="small-text" id="vl-refresh" name="<?php echo esc_attr( $o ); ?>[refresh_min]" value="<?php echo esc_attr( (string) $s['refresh_min'] ); ?>"> <?php esc_html_e( 'minuti (0 = mai; predefinito dei blocchi)', 'viewlapse-webcam' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cache', 'viewlapse-webcam' ); ?></th>
						<td>
							<label><input type="number" min="30" max="3600" class="small-text" name="<?php echo esc_attr( $o ); ?>[ttl_current]" value="<?php echo esc_attr( (string) $s['ttl_current'] ); ?>"> <?php esc_html_e( 'secondi per la foto attuale e l\'elenco', 'viewlapse-webcam' ); ?></label><br>
							<label><input type="number" min="300" max="172800" class="small-text" name="<?php echo esc_attr( $o ); ?>[ttl_archive]" value="<?php echo esc_attr( (string) $s['ttl_archive'] ); ?>"> <?php esc_html_e( 'secondi per l\'archivio dei giorni passati', 'viewlapse-webcam' ); ?></label><br>
							<label><input type="number" min="2" max="30" class="small-text" name="<?php echo esc_attr( $o ); ?>[timeout]" value="<?php echo esc_attr( (string) $s['timeout'] ); ?>"> <?php esc_html_e( 'secondi di attesa massima del portal', 'viewlapse-webcam' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Statistiche', 'viewlapse-webcam' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $o ); ?>[beacon]" value="1" <?php checked( ! empty( $s['beacon'] ) ); ?>> <?php esc_html_e( 'Conta le visualizzazioni delle webcam (statistiche aggregate senza cookie, nessun dato personale conservato)', 'viewlapse-webcam' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="vl-missing"><?php esc_html_e( 'Funzioni non incluse', 'viewlapse-webcam' ); ?></label></th>
						<td><select id="vl-missing" name="<?php echo esc_attr( $o ); ?>[missing_feature]">
							<option value="message" <?php selected( $s['missing_feature'], 'message' ); ?>><?php esc_html_e( 'Mostra un messaggio cortese', 'viewlapse-webcam' ); ?></option>
							<option value="hide" <?php selected( $s['missing_feature'], 'hide' ); ?>><?php esc_html_e( 'Nascondi il blocco', 'viewlapse-webcam' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Quando un blocco (timelapse, stesso momento, foto del giorno...) usa una funzione che non è nel servizio della webcam. Chi modifica i contenuti vede sempre la spiegazione.', 'viewlapse-webcam' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="vl-tiles"><?php esc_html_e( 'Mappa', 'viewlapse-webcam' ); ?></label></th>
						<td><input type="text" class="large-text code" id="vl-tiles" name="<?php echo esc_attr( $o ); ?>[tiles_url]" value="<?php echo esc_attr( (string) $s['tiles_url'] ); ?>">
						<p class="description"><?php esc_html_e( 'URL delle tessere della mappa (Leaflet è incluso nel plugin e si carica solo nei blocchi con la mappa attiva).', 'viewlapse-webcam' ); ?></p>
						<input type="text" class="large-text code" name="<?php echo esc_attr( $o ); ?>[tiles_attribution]" value="<?php echo esc_attr( (string) $s['tiles_attribution'] ); ?>">
						<p class="description"><?php esc_html_e( 'Attribuzione delle tessere (obbligatoria per OpenStreetMap).', 'viewlapse-webcam' ); ?></p></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<hr>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:1em">
				<input type="hidden" name="action" value="vl_webcam_test">
				<?php wp_nonce_field( 'vl_webcam_test' ); ?>
				<?php submit_button( __( 'Prova connessione', 'viewlapse-webcam' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block">
				<input type="hidden" name="action" value="vl_webcam_flush">
				<?php wp_nonce_field( 'vl_webcam_flush' ); ?>
				<?php submit_button( __( 'Svuota la cache', 'viewlapse-webcam' ), 'secondary', 'submit', false ); ?>
			</form>
			<h2><?php esc_html_e( 'Blocchi e shortcode', 'viewlapse-webcam' ); ?></h2>
			<p><?php esc_html_e( 'Nell\'editor: categoria "Viewlapse Webcam". Shortcode equivalenti:', 'viewlapse-webcam' ); ?></p>
			<ul style="list-style:disc;margin-left:2em">
				<li><code>[viewlapse_foto webcam="monte-cavo" aggiorna="5"]</code></li>
				<li><code>[viewlapse_archivio webcam="monte-cavo" colonne="4"]</code></li>
				<li><code>[viewlapse_confronto webcam="monte-cavo" a="2026-08-21T12:00" b="" scegli="si"]</code></li>
				<li><code>[viewlapse_grafico webcam="monte-cavo" giorni="7"]</code></li>
				<li><code>[viewlapse_elenco mappa="si" link="/webcam/?vl_cam={slug}"]</code></li>
				<li><code>[viewlapse_timelapse webcam="monte-cavo" tipo="giorno" formato="verticale"]</code></li>
				<li><code>[viewlapse_momento webcam="monte-cavo" ora="12:00" giorni="30" vista="sfoglia"]</code></li>
				<li><code>[viewlapse_fotogiorno webcam="monte-cavo" social="si"]</code></li>
				<li><code>[viewlapse_statistiche]</code>, <code>[viewlapse_documenti]</code> <?php esc_html_e( '(solo con la chiave "client", nell\'area riservata)', 'viewlapse-webcam' ); ?></li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Prova della connessione (whoami + elenco).
	 */
	public static function test(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'viewlapse-webcam' ), 403 );
		}
		check_admin_referer( 'vl_webcam_test' );
		$who = Api::get_fresh( 'whoami' );
		if ( is_wp_error( $who ) ) {
			$msg = array(
				'type' => 'error',
				'text' => esc_html( $who->get_error_message() ),
			);
		} else {
			$list  = Api::get_fresh( 'webcams' );
			$count = is_wp_error( $list ) ? 0 : (int) ( $list['count'] ?? 0 );
			$scope = (string) ( $who['scope'] ?? '' );
			$cust  = is_array( $who['customer'] ?? null ) ? (string) $who['customer']['name'] : __( 'nessuno', 'viewlapse-webcam' );
			$first = is_wp_error( $list ) ? array() : (array) ( $list['webcams'][0] ?? array() );
			$v11   = isset( $who['server'] ) || isset( $first['features'] );
			$extra = '<br>' . ( $v11
				? esc_html__( 'API dei siti 1.1: timelapse, stesso momento, foto del giorno e statistiche secondo il servizio di ogni webcam.', 'viewlapse-webcam' )
				: esc_html__( 'API dei siti 1.0: i blocchi dei servizi (timelapse, stesso momento, foto del giorno, statistiche, documenti) restano nascosti finché il portal non viene aggiornato.', 'viewlapse-webcam' ) );
			if ( is_array( $who['server'] ?? null ) && isset( $who['server']['timelapse'] ) && ! $who['server']['timelapse'] ) {
				$extra .= '<br>' . esc_html__( 'Il portal non produce timelapse nuovi (ffmpeg mancante): si vedono quelli già pronti.', 'viewlapse-webcam' );
			}
			$msg   = array(
				'type' => 'ok',
				'text' => sprintf(
					/* translators: 1: scope, 2: cliente, 3: numero di webcam */
					esc_html__( 'Connessione riuscita. Accesso: %1$s, cliente: %2$s, webcam visibili: %3$d.', 'viewlapse-webcam' ),
					'<strong>' . esc_html( 'anon' === $scope ? __( 'senza chiave', 'viewlapse-webcam' ) : $scope ) . '</strong>',
					'<strong>' . esc_html( $cust ) . '</strong>',
					$count
				) . $extra,
			);
		}
		self::back( $msg );
	}

	/**
	 * Svuota la cache.
	 */
	public static function flush(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permesso negato.', 'viewlapse-webcam' ), 403 );
		}
		check_admin_referer( 'vl_webcam_flush' );
		Api::flush();
		self::back(
			array(
				'type' => 'ok',
				'text' => esc_html__( 'Cache svuotata.', 'viewlapse-webcam' ),
			)
		);
	}

	/**
	 * Torna alla pagina con un messaggio.
	 *
	 * @param array{type:string, text:string} $msg Messaggio (HTML già sicuro).
	 */
	private static function back( array $msg ): void {
		set_transient( 'vl_webcam_notice_' . get_current_user_id(), $msg, 60 );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE . '&vl_notice=1' ) );
		exit;
	}
}
