<?php
defined( 'ABSPATH' ) || exit;
/**
 * Pantalla de DIGITALÍSIMO Backups («interfaz simple», 1.0.74): cuatro pestañas como Backuply, sin quitar funciones.
 *   Respaldos     estado del último respaldo y del próximo, «Respaldar ahora» y la lista con Restaurar, Descargar y
 *                 Borrar en cada fila (Bloquear, Traer al almacén y los detalles en «⋯»).
 *   Ajustes       programación de la automatización principal, avisos por correo, antes de actualizar y, plegado,
 *                 cifrado y firma.
 *   Destinos      la lista de conexiones; el formulario sólo al pedirlo («Añadir destino» o «Editar»).
 *   Herramientas  todo lo demás, plegado: migrar, importar, subir, restaurar un archivo suelto, automatizaciones,
 *                 extraer un sitio de la red y trabajos.
 * Los avisos van en una sola franja arriba. Sólo cambian la presentación y el flujo: los formularios, sus `name`, las
 * acciones de admin-post, los nonces, REST, WP-CLI y las opciones son los de antes. Las pestañas anteriores
 * (`servers`, `security`, `notifications`, `migrate`) siguen abriendo su sitio nuevo.
 */
trait Digitalisimo_Backups_Screen {
	/** Pestañas visibles (slug => nombre). `servers` conserva su slug: enlaces guardados y redirecciones. */
	private static function screen_tabs() { return array( 'backups' => 'Respaldos', 'settings' => 'Ajustes', 'servers' => 'Destinos', 'tools' => 'Herramientas' ); }
	/** Pestañas de antes de 1.0.74 → pestaña y sección que abren. */
	private static function screen_aliases() { return array( 'security' => array( 'settings', 'security' ), 'notifications' => array( 'settings', 'notifications' ), 'migrate' => array( 'tools', 'migrate' ), 'jobs' => array( 'tools', 'jobs' ) ); }
	/** Pestaña y sección pedidas por la URL (`tab`, `open`), con los alias de antes. */
	public static function screen_route( $tab = null, $open = null ) {
		$tab = sanitize_key( (string) ( null === $tab ? ( $_GET['tab'] ?? 'backups' ) : $tab ) );
		$open = sanitize_key( (string) ( null === $open ? ( $_GET['open'] ?? '' ) : $open ) );
		$aliases = self::screen_aliases();
		if ( isset( $aliases[ $tab ] ) ) { if ( '' === $open ) $open = $aliases[ $tab ][1]; $tab = $aliases[ $tab ][0]; }
		if ( ! isset( self::screen_tabs()[ $tab ] ) ) $tab = 'backups';
		return array( $tab, $open );
	}
	/** Secciones de cada pestaña a las que puede volver un formulario (la lista blanca de return_target()). */
	private static function screen_sections() { return array( 'backups' => array(), 'settings' => array( 'schedule', 'notifications', 'pre-update', 'security' ), 'servers' => array( 'remote' ), 'tools' => array( 'migrate', 'import', 'upload', 'restore-file', 'automations', 'extract', 'jobs' ) ); }
	/** Campo oculto con el que un formulario vuelve a su pestaña y sección tras guardar (ver redirect()). */
	public static function return_field( $tab, $section = '' ) { return '<input type="hidden" name="digitalisimo_backups_return" value="' . esc_attr( $tab . ( '' !== $section ? ':' . $section : '' ) ) . '">'; }
	/** Pestaña y sección a la que vuelve un formulario enviado (array vacío si no lo pide); una sección desconocida se ignora. */
	private static function return_target() {
		$raw = isset( $_POST['digitalisimo_backups_return'] ) && is_string( $_POST['digitalisimo_backups_return'] ) ? (string) wp_unslash( $_POST['digitalisimo_backups_return'] ) : '';
		if ( '' === $raw ) return array();
		$parts = explode( ':', $raw, 2 );
		$tab = sanitize_key( $parts[0] );
		if ( ! isset( self::screen_tabs()[ $tab ] ) && ! isset( self::screen_aliases()[ $tab ] ) ) return array();
		$section = sanitize_key( $parts[1] ?? '' );
		list( $real ) = self::screen_route( $tab, '' );
		if ( '' !== $section && ! in_array( $section, self::screen_sections()[ $real ] ?? array(), true ) ) $section = '';
		return array( $tab, $section );
	}
	/** La pantalla de Backups (sitio o red): sus avisos van en la franja, no repetidos por admin_notices. */
	public static function is_backups_screen() { return in_array( sanitize_key( (string) ( $_GET['page'] ?? '' ) ), array( 'digitalisimo-backups', 'digitalisimo-network-backups' ), true ); }
	/** URL de una pestaña (y sección) en el contexto actual (sitio o red). */
	private static function tab_url( $tab, $open = '', array $more = array() ) {
		$args = array( 'tab' => $tab ) + ( '' !== $open ? array( 'open' => $open ) : array() ) + $more;
		return add_query_arg( $args, self::admin_url() ) . ( '' !== $open ? '#digitalisimo-backups-section-' . $open : '' );
	}
	/** Ayuda corta: hasta 140 caracteres a la vista; el resto, plegado en «Más». */
	public static function help_html( $text, $html = false ) {
		$plain = trim( function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $text ) : strip_tags( (string) $text ) );
		if ( '' === $plain ) return '';
		$body = $html ? (string) $text : esc_html( (string) $text );
		if ( self::text_length( $plain ) <= 140 ) return '<p class="description">' . $body . '</p>';
		return '<p class="description">' . esc_html( self::text_head( $plain ) ) . '</p><details class="digitalisimo-backups-help"><summary>Más</summary><p class="description" data-folded="1">' . $body . '</p></details>';
	}
	/** Lo que se ve de un texto largo: su primera frase si cabe en 140 caracteres; si no, sus primeras palabras y «…». */
	private static function text_head( $plain ) {
		// Una frase entera antes que media: el primer punto que quepa; si no hay, el primer punto y coma o dos puntos.
		foreach ( array( '. ', '; ', ': ' ) as $mark ) {
			$at = strpos( $plain, $mark );
			if ( false !== $at && $at > 20 && self::text_length( substr( $plain, 0, $at + 1 ) ) <= 140 ) return substr( $plain, 0, $at + 1 );
		}
		$head = function_exists( 'mb_substr' ) ? mb_substr( $plain, 0, 125, 'UTF-8' ) : substr( $plain, 0, 125 );
		$space = strrpos( $head, ' ' );
		return rtrim( false !== $space ? substr( $head, 0, $space ) : $head, ' ,;:' ) . '…';
	}
	/**
	 * Pliega los párrafos de más de 140 caracteres de un HTML ya escapado (las secciones de «Herramientas», «Cifrado y
	 * firma», los respaldos en los destinos y las advertencias de un campo): a la vista, su primera frase; el párrafo
	 * entero, en «Más». Sólo párrafos sin otros bloques ni campos dentro y que se pueden plegar (foldable()). Si la
	 * expresión regular falla (UTF-8 inválido, límite de retroceso), devuelve el HTML tal cual: nunca una cadena vacía.
	 */
	public static function fold_long( $html ) {
		$html = (string) $html;
		$folded = preg_replace_callback( '#<p(\s[^>]*)?>((?:(?!</?(?:p|div|form|table|ul|ol|details|section|fieldset|select|textarea|input|button|label|script)\b).)*?)</p>#su', function( $m ) {
			$attrs = (string) ( $m[1] ?? '' );
			$plain = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $m[2] ), ENT_QUOTES, 'UTF-8' ) ) );
			if ( self::text_length( $plain ) <= 140 || ! self::foldable( $attrs, $m[2] ) ) return $m[0];
			return '<p' . $attrs . '>' . esc_html( self::text_head( $plain ) ) . '</p><details class="digitalisimo-backups-help"><summary>Más</summary><p' . $attrs . ' data-folded="1">' . $m[2] . '</p></details>';
		}, $html );
		return null === $folded ? $html : $folded;
	}
	/**
	 * Un párrafo que fold_long() puede plegar. Nunca: uno con id (los scripts lo usan), uno ya plegado (`data-folded`: el
	 * de help_html() o el de fold_long()) ni una advertencia, que se ve entera: `<strong>`, la clase
	 * `digitalisimo-backups-warning`, `notice…` o cualquier otra de aviso o error, o `role="alert"`.
	 */
	private static function foldable( $attrs, $body ) {
		if ( preg_match( '/\s(?:id|data-folded)=/', $attrs ) || preg_match( '/\srole=["\']alert/', $attrs ) ) return false;
		if ( preg_match( '/\sclass=["\'][^"\']*\b(?:notice|[a-z-]*warning|[a-z-]*error)\b/', $attrs ) ) return false;
		return ! preg_match( '#<(?:strong|b)\b#i', $body );
	}
	private static function text_length( $text ) { return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $text, 'UTF-8' ) : strlen( (string) preg_replace( '/[\x80-\xBF]/', '', (string) $text ) ); }
	/** Fecha del historial sin segundos («2026-11-11 19:07»). */
	private static function short_date( $date ) { $date = (string) $date; return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/', $date ) ? substr( $date, 0, 16 ) : $date; }

	/* ------------------------------------------------------------------ Armazón */

	private static function render( $network ) {
		try { self::local_directory(); }
		catch ( Throwable $e ) { echo '<div class="wrap digitalisimo-backups"><h1>Backups · Digitalisimo</h1><div class="notice notice-error"><p>' . esc_html( $e->getMessage() ) . '</p></div></div>'; return; }
		// H17: abrir la pantalla, en cualquier pestaña, deja en 0600 lo que dejó una versión anterior (hasta 1.0.73 lo hacía
		// «Restaurar», que se pintaba siempre; ahora vive plegado en «Herramientas»).
		self::private_storage_files( self::local_directory() );
		delete_option( self::manual_job_key() );
		// Una restauración interrumpida: al tomar el bloqueo se aplica su diario (si otra operación lo tiene, espera).
		if ( is_file( trailingslashit( self::local_directory() ) . self::RESTORE_JOURNAL ) ) { try { self::release_operation_lock( self::acquire_operation_lock() ); } catch ( Exception $e ) {} } // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		list( $tab, $open ) = self::screen_route();
		echo '<div class="wrap digitalisimo-backups" data-tab="' . esc_attr( $tab ) . '"><h1>Backups · Digitalisimo</h1>';
		self::result_notice();
		// Un subsitio de la red: los respaldos son de toda la red y se hacen desde su administración.
		if ( is_multisite() && ! $network && ! is_main_site() ) { self::subsite_screen(); echo '</div>'; return; }
		$s = self::settings();
		$all = (array) get_option( self::HISTORY, array() );
		$history = array_slice( $all, 0, 10 );
		$credentials = self::credentials_status();
		self::notice_strip( $network, $credentials );
		self::tabs_nav( $tab );
		if ( 'settings' === $tab ) self::settings_tab( $network, $all, $open );
		elseif ( 'servers' === $tab ) self::destinations_tab( $s, $credentials, $open );
		elseif ( 'tools' === $tab ) self::tools_tab( $network, $s, $history, $open );
		else self::home_tab( $network, $s, $history, $all );
		echo '</div>';
	}
	/** Resultado de la última acción (?backup=ok|error&message=…): un aviso de WordPress aparte, pasajero. */
	private static function result_notice() {
		if ( ! isset( $_GET['backup'] ) ) return;
		echo '<div class="notice notice-' . ( 'ok' === $_GET['backup'] ? 'success' : 'error' ) . ' is-dismissible digitalisimo-backups-result"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['message'] ?? '' ) ) ) . '</p></div>';
	}
	private static function subsite_screen() {
		echo '<section class="digitalisimo-backups-card digitalisimo-backups-subsite"><p>Los respaldos de esta red se hacen desde la administración de red.</p><p><a class="button button-primary" href="' . esc_url( network_admin_url( 'admin.php?page=digitalisimo-network-backups' ) ) . '">Ir a Backups de la red</a></p></section>';
	}
	private static function tabs_nav( $tab ) {
		echo '<nav class="digitalisimo-backups-tabs" aria-label="Backups">';
		foreach ( self::screen_tabs() as $slug => $label ) echo '<a class="' . ( $slug === $tab ? 'is-active' : '' ) . '"' . ( $slug === $tab ? ' aria-current="page"' : '' ) . ' href="' . esc_url( add_query_arg( 'tab', $slug, self::admin_url() ) ) . '">' . esc_html( $label ) . '</a>';
		echo '</nav>';
	}

	/* ------------------------------------------------------------------ Franja de avisos */

	/**
	 * Una sola franja con lo que pide atención: el estado de una restauración (con «Volver a la copia previa»), la
	 * configuración pendiente o sin automatizaciones activas tras restaurar, las credenciales de los destinos y las
	 * actualizaciones retenidas o sin respaldo. Cada línea es corta; el detalle va plegado.
	 */
	private static function notice_strip( $network, array $credentials ) {
		$items = array();
		$restore = self::restore_status();
		if ( 'none' !== $restore['state'] ) {
			$titles = array( 'running' => 'Restauración en curso.', 'interrupted' => 'Restauración interrumpida: se reanudará sola.', 'rollback' => 'Volviendo a la copia previa.', 'failed' => 'La última restauración no se completó.', 'failed_midway' => 'La última restauración falló a mitad del cambio.', 'rolled_back' => 'La última restauración falló y el sitio volvió a la copia previa.', 'cancelled' => 'La última restauración se canceló.' );
			$level = in_array( $restore['state'], array( 'failed_midway', 'rollback', 'interrupted' ), true ) ? 'error' : 'warning';
			$actions = '';
			// Un sitio a medio cambiar se recupera desde aquí: otra vuelta a la copia previa (revisión, C2).
			if ( 'failed_midway' === $restore['state'] && '' !== (string) ( $restore['recover'] ?? '' ) && ! Digitalisimo_Backups_Jobs::restore_active() ) {
				ob_start();
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
				wp_nonce_field( self::ACTION_RESTORE );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RESTORE ) . '"><input type="hidden" name="rollback_retry" value="1"><button class="button button-primary" title="' . esc_attr( 'Restaura la copia previa ' . (string) $restore['recover'] . ' sin hacer otra; el sitio sigue en mantenimiento hasta que termine.' ) . '">Volver a la copia previa</button></form>';
				$actions = (string) ob_get_clean();
			}
			$items[] = array( 'class' => 'digitalisimo-backups-restore-state', 'state' => $restore['state'], 'level' => $level, 'text' => $titles[ $restore['state'] ] ?? '', 'detail' => (string) $restore['notice'], 'actions' => $actions );
			$active = Digitalisimo_Backups_Jobs::restore_active();
			// La pantalla sigue la restauración con su testigo (vale aunque la sesión cambie con la base de datos restaurada).
			if ( $active && Digitalisimo_Backups_Restore_Maintenance::request_has( (string) $active['spec']['token'] ) ) echo '<script>window.digitalisimoBackupsRestore=' . wp_json_encode( array( 'token' => (string) $active['spec']['token'], 'action' => Digitalisimo_Backups_Jobs::AJAX_RESTORE_TICK ) ) . ';</script>';
		}
		// Tras restaurar (H23): la configuración del respaldo que no se aplicó, o un sitio sin automatizaciones activas.
		try { $config = self::restore_config_strip_item(); if ( $config ) $items[] = $config; } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		// Credenciales de los destinos que no se abren o que siguen sin cifrar: qué hacer, sin ningún valor.
		if ( '' !== $credentials['notice'] ) {
			$short = array( 'needs_reentry' => 'Hay destinos que piden volver a escribir sus credenciales.', 'unprotected' => 'Las credenciales de los destinos están sin cifrar: falta AUTH_KEY en wp-config.php.', 'pending' => 'Algunas credenciales de los destinos se cifrarán en la siguiente carga.' );
			$items[] = array( 'class' => 'digitalisimo-backups-credentials', 'state' => $credentials['state'], 'level' => 'warning', 'text' => $short[ $credentials['state'] ] ?? 'Sin AUTH_KEY en wp-config.php, las credenciales de un destino se guardarían sin cifrar.', 'detail' => (string) $credentials['notice'], 'actions' => '<a href="' . esc_url( self::tab_url( 'servers' ) ) . '">Destinos</a>' );
		}
		// Actualizaciones automáticas retenidas, pospuestas o sin respaldo previo (el mismo aviso del escritorio).
		try { $pre = Digitalisimo_Backups_Pre_Update::strip_item(); if ( $pre ) $items[] = $pre + array( 'actions' => '<a href="' . esc_url( self::tab_url( 'settings', 'pre-update' ) ) . '">Antes de actualizar</a>' ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		if ( ! $items ) return;
		$worst = 'warning';
		foreach ( $items as $item ) if ( 'error' === $item['level'] ) $worst = 'error';
		echo '<div class="notice notice-' . esc_attr( $worst ) . ' digitalisimo-backups-strip" role="status"><ul>';
		foreach ( $items as $item ) {
			echo '<li class="' . esc_attr( $item['class'] ) . ' is-' . esc_attr( $item['level'] ) . '"' . ( isset( $item['state'] ) ? ' data-state="' . esc_attr( $item['state'] ) . '"' : '' ) . '><span class="digitalisimo-backups-strip-text">' . esc_html( $item['text'] ) . '</span>';
			// Una advertencia (lo que una acción reemplaza) va siempre a la vista, antes de sus botones; nunca plegada.
			if ( '' !== (string) ( $item['warning'] ?? '' ) ) echo '<p class="digitalisimo-backups-warning digitalisimo-backups-strip-warning">' . esc_html( $item['warning'] ) . '</p>';
			if ( '' !== (string) ( $item['actions'] ?? '' ) ) echo ' <span class="digitalisimo-backups-strip-actions">' . $item['actions'] . '</span>'; // Marcado propio, ya escapado.
			if ( '' !== trim( (string) ( $item['detail'] ?? '' ) ) && trim( (string) $item['detail'] ) !== $item['text'] ) echo '<details class="digitalisimo-backups-help"><summary>Detalle</summary><p>' . esc_html( (string) $item['detail'] ) . '</p></details>';
			echo '</li>';
		}
		echo '</ul></div>';
	}
	/**
	 * La línea de la franja del aviso tras restaurar (H23), con «Aplicar la del respaldo» y «Descartar». Con lo pendiente,
	 * lo que se pierde al aplicarla va a la vista y aplicarla pide escribir APLICAR (H26).
	 */
	private static function restore_config_strip_item() {
		$notice = self::restore_notice_view();
		$text = self::restore_notice_text( $notice );
		if ( '' === $text ) return null;
		$form = function( $do, $label, $primary ) {
			ob_start();
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'digitalisimo_backups_restore_config' );
			echo '<input type="hidden" name="action" value="digitalisimo_backups_restore_config"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">' . ( is_multisite() ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . ( 'apply' === $do ? self::restore_config_confirm_field() : '' ) . '<button class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></form>';
			return (string) ob_get_clean();
		};
		if ( ! empty( $notice['pending'] ) ) $short = 'El respaldo restaurado traía su configuración de Backups: no se aplicó.';
		elseif ( ! empty( $notice['paused'] ) ) $short = 'Se aplicó la configuración del respaldo con sus automatizaciones en pausa.';
		elseif ( ! empty( $notice['automations'] ) || ! empty( $notice['connections'] ) ) $short = 'Este sitio conservó su configuración de Backups al restaurar.';
		else $short = 'Ninguna automatización activa: el sitio no se respalda solo.';
		$actions = ( ! empty( $notice['pending'] ) ? $form( 'apply', 'Aplicar la del respaldo', true ) : '' ) . ( ! empty( $notice['no_active'] ) || ! empty( $notice['paused'] ) ? '<a href="' . esc_url( self::tab_url( 'settings', 'schedule' ) ) . '">Programar</a> ' : '' ) . $form( 'discard', 'Descartar', false );
		return array( 'class' => 'digitalisimo-backups-restore-notice', 'level' => 'warning', 'text' => $short, 'warning' => ! empty( $notice['pending'] ) ? $notice['replaces'] : '', 'detail' => $text, 'actions' => $actions );
	}

	/* ------------------------------------------------------------------ Respaldos */

	private static function home_tab( $network, $s, array $history, array $all ) {
		echo '<section class="digitalisimo-backups-card digitalisimo-backups-home">';
		self::status_block( $all );
		echo '<form id="digitalisimo-backups-manual-form" class="digitalisimo-backups-now" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_RUN );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RUN ) . '"><input type="hidden" name="digitalisimo_backup_scope" value="installation">';
		if ( $network ) echo '<input type="hidden" name="digitalisimo_backups_network" value="1">';
		$choices = self::manual_destinations( $s );
		$current = isset( $choices[ $s['destination'] ] ) ? $s['destination'] : 'local';
		echo '<button class="button button-primary button-hero" title="' . esc_attr( is_multisite() ? 'Respaldo completo de la red: todos sus sitios, archivos y base de datos.' : 'Respaldo completo: archivos de WordPress, plugins, temas, medios y base de datos.' ) . '">Respaldar ahora</button>';
		echo '<details class="digitalisimo-backups-destination-choice"><summary>Destino: <span data-destination-label>' . esc_html( $choices[ $current ] ) . '</span></summary><p>' . self::manual_destination_select( $s ) . '</p>' . self::remote_only_field( false, 'digitalisimo_backup_remote_only' ) . '</details>';
		echo '</form><div id="digitalisimo-backups-manual-progress" class="digitalisimo-backups-manual-progress" hidden><p id="digitalisimo-backups-manual-message" role="status" aria-live="polite"></p><progress id="digitalisimo-backups-manual-bar" max="100" value="0"></progress><p id="digitalisimo-backups-manual-detail" aria-live="polite"></p></div>';
		echo '</section>';
		// Trabajos en curso (automatizaciones, restauraciones, migraciones): sólo mientras haya alguno.
		self::jobs_panel( $network, true );
		self::history_panel( $history, $network, count( $all ) );
	}
	/** «Último respaldo» y «Próximo» (la automatización activa que antes toca). */
	private static function status_block( array $history ) {
		$last = null;
		foreach ( $history as $item ) if ( in_array( (string) ( $item['scope'] ?? '' ), array( 'installation', 'network', 'site' ), true ) ) { $last = $item; break; }
		$next = self::next_automation();
		echo '<dl class="digitalisimo-backups-status"><div><dt>Último respaldo</dt><dd>';
		echo $last ? esc_html( self::short_date( $last['date'] ?? '' ) . ' · ' . size_format( (int) ( $last['size'] ?? 0 ) ) . ' · ' . self::copies_label( $last ) ) : 'Aún no hay respaldos';
		echo '</dd></div><div><dt>Próximo</dt><dd>';
		if ( $next ) echo esc_html( $next['when'] . ' · ' . $next['label'] . ' · se conservan ' . (int) $next['retain'] ) . ' <a href="' . esc_url( self::tab_url( 'settings', 'schedule' ) ) . '">Cambiar</a>';
		else echo 'Sin programar · <a href="' . esc_url( self::tab_url( 'settings', 'schedule' ) ) . '">Programar</a>';
		echo '</dd></div></dl>';
	}
	/** La próxima ejecución de las automatizaciones activas: cuándo (hora del sitio), cuál y cuántas conserva. */
	private static function next_automation() {
		if ( ! function_exists( 'wp_next_scheduled' ) ) return null;
		$best = null;
		$schedules = self::schedule_options();
		foreach ( self::policies() as $policy ) {
			if ( empty( $policy['enabled'] ) || 'full' !== ( $policy['type'] ?? '' ) || 'disabled' === ( $policy['schedule'] ?? 'disabled' ) || empty( $policy['id'] ) ) continue;
			$at = wp_next_scheduled( 'digitalisimo_backups_policy', array( (string) $policy['id'], get_current_blog_id() ) );
			if ( ! $at || ( $best && $best['at'] <= $at ) ) continue;
			$best = array( 'at' => (int) $at, 'label' => $schedules[ $policy['schedule'] ] ?? $policy['schedule'], 'retain' => (int) ( $policy['retain'] ?? 4 ) );
		}
		if ( ! $best ) return null;
		$best['when'] = ( new DateTimeImmutable( '@' . $best['at'] ) )->setTimezone( self::site_timezone() )->format( 'Y-m-d H:i' );
		return $best;
	}
	/** Dónde hay copia de una fila: «Hosting / local», «SFTP», «Hosting / local + SFTP»…; o «Sin copias». */
	private static function copies_label( $item ) {
		$parts = array();
		foreach ( self::item_copies( $item ) as $copy ) $parts[] = self::destination_label( $copy['destination'] );
		$remote = self::remote_copy( $item );
		$states = array( 'pending' => 'copia remota pendiente', 'failed' => 'la copia remota falló', 'cancelled' => 'copia remota cancelada', 'pruned' => 'copia remota borrada' );
		if ( $remote && isset( $states[ $remote['state'] ] ) ) $parts[] = $states[ $remote['state'] ];
		return $parts ? implode( ' + ', $parts ) : 'Sin copias';
	}
	/** Lista de respaldos: Restaurar, Descargar y Borrar en cada fila; lo demás en «⋯». $total: cuántos hay (la lista enseña los últimos). */
	private static function history_panel( array $history, $network, $total = null ) {
		$total = null === $total ? count( $history ) : max( (int) $total, count( $history ) );
		echo '<section class="digitalisimo-backups-card digitalisimo-backups-history" id="digitalisimo-backups-history"><div class="digitalisimo-backups-head"><h2>Respaldos' . ( $history ? ' <span class="count">(' . $total . ')</span>' : '' ) . '</h2>';
		if ( ! is_multisite() || is_main_site() ) echo '<a href="' . esc_url( self::tab_url( 'tools', 'upload' ) ) . '">Subir un respaldo</a>';
		echo '</div>';
		if ( ! $history ) { echo '<p>Aún no hay respaldos.</p></section>'; return; }
		$open = isset( $_GET['restore'] ) ? sanitize_file_name( wp_unslash( (string) $_GET['restore'] ) ) : '';
		echo '<table class="widefat digitalisimo-backups-list"><thead><tr><th scope="col">Fecha</th><th scope="col">Tamaño</th><th scope="col">Dónde</th><th scope="col"><span class="screen-reader-text">Acciones</span></th></tr></thead><tbody>';
		foreach ( array_values( $history ) as $index => $item ) self::history_row( (array) $item, $index, $network, $open );
		echo '</tbody></table>' . ( $total > count( $history ) ? '<p class="description">Se muestran los ' . count( $history ) . ' más recientes.</p>' : '' ) . '</section>';
	}
	private static function history_row( array $item, $index, $network, $open ) {
		$file = (string) ( $item['file'] ?? '' );
		$has_local = '' !== self::local_copy( $item );
		$restorable = self::restorable_item( $item, $has_local );
		$fetchable = ! $has_local && ! empty( ( (array) ( $item['remote'] ?? array() ) )['provider'] );
		$row_id = 'digitalisimo-backups-restore-' . (int) $index;
		$scope = (string) ( $item['scope'] ?? 'site' );
		$extract = Digitalisimo_Backups_Site_Extract::SCOPE === $scope;
		echo '<tr class="digitalisimo-backups-row" data-file="' . esc_attr( $file ) . '"><td data-label="Fecha"><strong>' . esc_html( self::short_date( $item['date'] ?? '' ) ) . '</strong>';
		$badges = array();
		if ( ! empty( $item['locked'] ) ) $badges[] = '<span class="digitalisimo-backups-badge" title="La retención no lo borra">Bloqueado</span>';
		if ( 'dgbk' === ( $item['container'] ?? '' ) ) $badges[] = Digitalisimo_Backups_Security::history_badge( $item );
		if ( '' !== self::history_notices_text( $item ) ) $badges[] = '<span class="digitalisimo-backups-badge is-warning" title="' . esc_attr( self::history_notices_text( $item ) ) . '">Con avisos</span>';
		if ( $extract ) $badges[] = '<span class="digitalisimo-backups-badge">Sitio extraído: ' . esc_html( (string) ( $item['extract']['label'] ?? '' ) ) . '</span>';
		elseif ( ! in_array( $scope, array( 'installation', 'network' ), true ) ) $badges[] = '<span class="digitalisimo-backups-badge">Exportación anterior</span>';
		if ( $badges ) echo ' ' . implode( ' ', $badges );
		echo '</td><td data-label="Tamaño">' . esc_html( size_format( (int) ( $item['size'] ?? 0 ) ) ) . '</td><td data-label="Dónde">' . esc_html( self::copies_label( $item ) ) . '</td><td class="digitalisimo-backups-actions">';
		if ( $restorable ) echo '<a class="button button-small" data-restore-toggle="' . esc_attr( $row_id ) . '" aria-controls="' . esc_attr( $row_id ) . '" aria-expanded="' . ( $open === $file ? 'true' : 'false' ) . '" href="' . esc_url( self::tab_url( 'backups', '', array( 'restore' => rawurlencode( $file ) ) ) . '#' . $row_id ) . '">Restaurar</a> ';
		elseif ( $fetchable && ! $extract ) self::fetch_button( $item );
		if ( $has_local || ! empty( $item['remote']['provider'] ) ) echo '<a class="button button-small" href="' . esc_url( self::download_url( $file ) ) . '">Descargar</a> ';
		// «Borrar» siempre: también una fila que ya no tiene ninguna copia.
		echo '<form class="digitalisimo-backups-inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'¿Borrar este respaldo?\')">';
		wp_nonce_field( self::ACTION_DELETE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_DELETE ) . '"><input type="hidden" name="file" value="' . esc_attr( $file ) . '"><button class="button button-small">Borrar</button></form> ';
		echo '<details class="digitalisimo-backups-more"><summary title="Más acciones" aria-label="Más acciones">⋯</summary><div class="digitalisimo-backups-more-menu">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_LOCK );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_LOCK ) . '"><input type="hidden" name="file" value="' . esc_attr( $file ) . '"><input type="hidden" name="locked" value="' . ( empty( $item['locked'] ) ? '1' : '0' ) . '"><button class="button button-small">' . ( empty( $item['locked'] ) ? 'Bloquear' : 'Desbloquear' ) . '</button></form>';
		if ( $restorable || $extract ) self::fetch_button( $item );
		$type = 'network' === $scope ? 'Red completa' : ( 'installation' === $scope ? 'Instalación completa' : ( $extract ? 'Sitio extraído: ' . (string) ( $item['extract']['label'] ?? '' ) : 'Exportación anterior de sitio' ) );
		echo '<dl><dt>Archivo</dt><dd>' . esc_html( $file ) . '</dd><dt>Tipo</dt><dd>' . esc_html( $type ) . '</dd><dt>Copias</dt><dd>' . esc_html( self::history_destination( $item ) ) . '</dd><dt>Seguridad</dt><dd>' . Digitalisimo_Backups_Security::history_badge( $item ) . '</dd>';
		if ( '' !== self::history_notices_text( $item ) ) echo '<dt>Avisos</dt><dd>' . esc_html( self::history_notices_text( $item ) ) . '</dd>';
		if ( ! $has_local && ! $fetchable && self::remote_exists( $item ) ) echo '<dt>Copia remota</dt><dd>Respaldo remoto anterior: sin identificador</dd>';
		echo '</dl></div></details></td></tr>';
		if ( $restorable ) self::row_restore_form( $item, $row_id, $open === $file );
	}
	/**
	 * «Conservar mi acceso» (encendido): al restaurar el respaldo de otro sitio (otra URL, prefijo o ruta, o un sitio extraído
	 * de una red), quien restaura sigue entrando con su cuenta y el sitio conserva su ruta de acceso. En el mismo sitio no
	 * aplica (vuelven las cuentas del respaldo): el texto lo dice. El campo oculto distingue «desmarcado» de «sin campo».
	 */
	private static function keep_access_field() {
		return '<p class="digitalisimo-backups-keep-access"><label><input type="checkbox" name="keep_access" value="1" checked> Conservar mi acceso</label><input type="hidden" name="keep_access_shown" value="1"></p>' . self::help_html( 'Si el respaldo es de otro sitio (otra dirección, carpeta o prefijo, o un sitio extraído de una red), tu usuario, tu contraseña y tus contraseñas de aplicación siguen sirviendo y este sitio conserva su ruta de acceso. Restaurando el mismo sitio vuelven las cuentas del respaldo.' );
	}
	/** El panel «Restaurar» bajo su fila: una frase, escribir RESTAURAR y el botón; las opciones, plegadas. */
	private static function row_restore_form( array $item, $row_id, $shown ) {
		$file = (string) $item['file'];
		$path = self::local_copy( $item );
		$label = Digitalisimo_Backups_Security::restore_label( $path, $item );
		$needs_key = false !== strpos( $label, 'escribe su frase' );
		$hint = self::relocation_hint( $path, Digitalisimo_Backups_Relocate::describe() );
		echo '<tr class="digitalisimo-backups-restore-row" id="' . esc_attr( $row_id ) . '"' . ( $shown ? '' : ' hidden' ) . '><td colspan="4"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_RESTORE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_RESTORE ) . '"><input type="hidden" name="backup_file" value="' . esc_attr( $file ) . '">';
		echo '<p><strong>Restaurar el respaldo del ' . esc_html( self::short_date( $item['date'] ?? '' ) ) . '.</strong> Antes se guarda una copia del sitio actual; escribe RESTAURAR para confirmar.</p>';
		echo '<p class="digitalisimo-backups-restore-confirm"><label><span class="screen-reader-text">Confirmación</span><input type="text" name="confirmation" placeholder="RESTAURAR" autocomplete="off" required></label> <button class="button button-primary">Restaurar</button> <a class="digitalisimo-backups-cancel" data-restore-cancel="' . esc_attr( $row_id ) . '" href="' . esc_url( self::tab_url( 'backups' ) ) . '">Cancelar</a></p>';
		echo '<details class="digitalisimo-backups-restore-options"' . ( $needs_key || '' !== $hint['label'] ? ' open' : '' ) . '><summary>Opciones</summary>';
		if ( '' !== $hint['label'] ) echo self::help_html( $hint['text'] );
		echo '<fieldset><legend>Direcciones del sitio después de restaurar</legend><label><input type="radio" name="addresses" value="current" checked> Las de este sitio</label> <label><input type="radio" name="addresses" value="backup"> Las del respaldo</label></fieldset>';
		echo self::keep_access_field();
		echo '<p><label>Frase o clave del respaldo <input type="password" name="backup_secret" value="" autocomplete="off"></label>' . ( $needs_key ? '<br><span class="description">Este respaldo está cifrado con una clave que este sitio no tiene.</span>' : '' ) . '</p><p><label><input type="checkbox" name="backup_secret_adopt" value="1"> Usar esta frase para los respaldos nuevos</label></p></details>';
		echo '</form></td></tr>';
	}

	/* ------------------------------------------------------------------ Ajustes */

	private static function settings_tab( $network, array $history, $open ) {
		echo '<section class="digitalisimo-backups-card" id="digitalisimo-backups-section-schedule"><h2>Programación</h2>';
		self::schedule_form();
		echo '</section>';
		echo '<div id="digitalisimo-backups-section-notifications">';
		Digitalisimo_Backups_Notify::panel( $network );
		echo '</div><div id="digitalisimo-backups-section-pre-update">';
		Digitalisimo_Backups_Pre_Update::panel( $network, 'pre-update' === $open );
		echo '</div>';
		echo '<details class="digitalisimo-backups-section" id="digitalisimo-backups-section-security"' . ( 'security' === $open ? ' open' : '' ) . '><summary><strong>Cifrado y firma</strong> <span class="description">opcional: frase, cifrar los respaldos nuevos, sólo firmados, llavero</span></summary>';
		ob_start();
		try { Digitalisimo_Backups_Security::panel( $network, $history ); } finally { echo self::fold_long( (string) ob_get_clean() ); }
		echo '</details>';
	}
	/** La automatización principal: la primera completa y activa (o la primera completa); sin ninguna, se crea. */
	private static function principal_policy() {
		$first = null;
		foreach ( self::policies() as $policy ) {
			if ( 'full' !== ( $policy['type'] ?? '' ) || empty( $policy['id'] ) ) continue;
			if ( ! empty( $policy['enabled'] ) && 'disabled' !== ( $policy['schedule'] ?? 'disabled' ) ) return $policy;
			if ( ! $first ) $first = $policy;
		}
		return $first;
	}
	/**
	 * «Programación»: frecuencia, hora, cuántos conservar y destino de la automatización principal (mismos handlers y el
	 * mismo formato: `enabled` y `schedule`). Una en pausa (`enabled` = 0) enseña su frecuencia y la casilla «Activa»
	 * sin marcar, así que guardar otra cosa no la reactiva ni pierde la frecuencia. «Desactivado» sólo deja sin programar
	 * esta automatización; sin ninguna no se ofrece, porque guardar crearía una.
	 */
	private static function schedule_form() {
		$policy = self::principal_policy();
		$schedules = self::schedule_options();
		if ( $policy ) $schedules['disabled'] = 'Desactivado (sólo esta automatización)';
		else unset( $schedules['disabled'] );
		$paused = $policy && empty( $policy['enabled'] );
		$current = $policy ? (string) $policy['schedule'] : 'weekly';
		$action = $policy ? self::ACTION_UPDATE_AUTOMATION : self::ACTION_CREATE_AUTOMATION;
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="digitalisimo-backups-schedule">';
		wp_nonce_field( $action );
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">' . ( $policy ? '<input type="hidden" name="policy_id" value="' . esc_attr( $policy['id'] ) . '">' : '' ) . '<input type="hidden" name="digitalisimo_backup_automation[type]" value="full">' . ( $paused ? '' : '<input type="hidden" name="digitalisimo_backup_automation[enabled]" value="1">' ) . self::return_field( 'settings', 'schedule' );
		echo '<table class="form-table">';
		if ( $paused ) echo '<tr><th>Estado</th><td><label><input type="checkbox" name="digitalisimo_backup_automation[enabled]" value="1"> Activa</label> <span class="description">En pausa: no se ejecuta hasta activarla.</span></td></tr>';
		echo '<tr><th><label for="digitalisimo-backups-schedule">Respaldar</label></th><td><select id="digitalisimo-backups-schedule" name="digitalisimo_backup_automation[schedule]">';
		foreach ( $schedules as $value => $label ) echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		$zone = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC';
		echo '</select> <label>a las <input type="time" step="60" name="digitalisimo_backup_automation[time]" value="' . esc_attr( (string) ( $policy['time'] ?? '' ) ) . '"></label><p class="description">Hora del sitio (' . esc_html( $zone ) . '). Vacía: cuando WP-Cron pueda.</p></td></tr>';
		echo '<tr><th><label for="digitalisimo-backups-retain">Conservar</label></th><td><input id="digitalisimo-backups-retain" type="number" min="1" max="100" name="digitalisimo_backup_automation[retain]" value="' . esc_attr( (string) ( $policy['retain'] ?? 4 ) ) . '"> respaldos</td></tr>';
		echo '<tr><th><label for="digitalisimo-backups-schedule-destination">Destino</label></th><td><select id="digitalisimo-backups-schedule-destination" name="digitalisimo_backup_automation[destination]">';
		foreach ( array_keys( self::destination_choices() ) as $destination ) echo '<option value="' . esc_attr( $destination ) . '"' . selected( (string) ( $policy['destination'] ?? 'local' ), $destination, false ) . '>' . esc_html( self::destination_label( $destination ) ) . '</option>';
		echo '</select><details class="digitalisimo-backups-help"><summary>Más opciones</summary>' . self::remote_only_field( ! empty( $policy['remote_only'] ) ) . '</details></td></tr></table>';
		$count = count( array_filter( self::policies(), function( $row ) { return 'full' === ( $row['type'] ?? '' ); } ) );
		if ( $count > 1 ) echo '<p class="description">Tienes ' . (int) $count . ' automatizaciones · <a href="' . esc_url( self::tab_url( 'tools', 'automations' ) ) . '">Gestionarlas en Herramientas</a></p>';
		echo '<p><button class="button button-primary">Guardar</button></p></form>';
	}

	/* ------------------------------------------------------------------ Destinos */

	private static function destinations_tab( $s, array $credentials, $open ) {
		if ( ! self::credentials_here() ) { echo '<section class="digitalisimo-backups-card"><h2>Destinos</h2><p>Las conexiones de los destinos son de toda la red: se gestionan desde la administración de red o el sitio principal.</p></section>'; return; }
		self::migrate_server_catalog();
		self::servers_panel( $s, $credentials['locked'] );
		echo '<details class="digitalisimo-backups-section" id="digitalisimo-backups-section-remote"' . ( 'remote' === $open ? ' open' : '' ) . '><summary><strong>Respaldos guardados en los destinos</strong> <span class="description">también los de otros sitios: sincronizar y traer</span></summary>';
		ob_start();
		try { self::remote_panel(); } finally { echo self::fold_long( (string) ob_get_clean() ); }
		echo '</details>';
	}

	/* ------------------------------------------------------------------ Herramientas */

	private static function tools_tab( $network, $s, array $history, $open ) {
		$main = ! is_multisite() || is_main_site();
		$jobs = Digitalisimo_Backups_Jobs::views();
		$active = (bool) array_filter( $jobs, function( $view ) { return ! $view['terminal']; } );
		$migrating = (bool) array_filter( $jobs, function( $view ) { return ! $view['terminal'] && 'migration' === ( $view['kind'] ?? '' ); } );
		$status = self::migration_status_data();
		self::tool_section( 'migrate', 'Migrar', 'clave de un solo uso, migrar desde otro sitio, copia de pruebas, deshacer', 'migrate' === $open || $migrating, function() use ( $network, $status ) { Digitalisimo_Backups_Migrate::panel( $network, $status ); } );
		// Una sola búsqueda para el resumen y la sección (sources() también retira las descargas cortadas).
		$updraft = self::detect_updraft_sets();
		$backuply = array();
		try { $backuply = $main ? Digitalisimo_Backups_Backuply_Import::sources() : array(); } catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		$found = count( $updraft ) + count( $backuply );
		self::tool_section( 'import', 'Importar de Backuply o UpdraftPlus', $found ? $found . ' ' . ( 1 === $found ? 'respaldo detectado' : 'respaldos detectados' ) : 'no se detectaron respaldos', 'import' === $open, function() use ( $network, $updraft, $backuply ) { self::backuply_panel( $network, $backuply ); echo '<section class="digitalisimo-backups-card digitalisimo-backups-updraft">'; self::updraft_panel( $updraft ); echo '</section>'; } );
		if ( $main ) self::tool_section( 'upload', 'Subir un respaldo', '.zip o .dgbk de DIGITALÍSIMO, o .tar.gz de Backuply', 'upload' === $open, function() use ( $network ) { Digitalisimo_Backups_Uploads::panel( $network ); } );
		self::tool_section( 'restore-file', 'Restaurar un archivo suelto', 'archivos sin registro, de otro servidor o con su frase, y la última restauración en otro dominio', 'restore-file' === $open, function() use ( $history ) { self::restore_panel( $history ); } );
		$count = count( self::policies() );
		self::tool_section( 'automations', 'Automatizaciones (' . $count . ')', 'crear, editar, ejecutar ahora y borrar; cada una con su destino y su retención', 'automations' === $open, function() { self::automation_fields(); } );
		if ( $network && is_multisite() ) self::tool_section( 'extract', 'Extraer un sitio de la red', 'un sitio de la red como WordPress individual, para otro servidor', 'extract' === $open, function() use ( $s ) { self::extract_panel( $s ); } );
		self::tool_section( 'jobs', 'Trabajos y registros', $active ? 'hay trabajos en curso' : 'los últimos respaldos, restauraciones y migraciones', 'jobs' === $open || $active, function() use ( $network ) { self::jobs_panel( $network ); } );
	}
	/** Una sección plegada de «Herramientas»: título, resumen de una línea y su contenido de antes. */
	private static function tool_section( $id, $title, $summary, $open, callable $body ) {
		echo '<details class="digitalisimo-backups-section" id="digitalisimo-backups-section-' . esc_attr( $id ) . '"' . ( $open ? ' open' : '' ) . '><summary><strong>' . esc_html( $title ) . '</strong> <span class="description">' . esc_html( $summary ) . '</span></summary>';
		ob_start();
		try { $body(); } finally { echo self::fold_long( (string) ob_get_clean() ); }
		echo '</details>';
	}
}
