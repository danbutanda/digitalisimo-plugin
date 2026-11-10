<?php
defined( 'ABSPATH' ) || exit;

/**
 * Avisos por correo de los respaldos, las copias remotas y las restauraciones (pestaña «Avisos»).
 *
 * - Fallo: activo por defecto. Sólo el definitivo: un reintento con espera nunca avisa; un trabajo avisa una sola vez,
 *   cuando termina fallido (también caducado o con los intentos agotados tras morir su proceso).
 * - Éxito: opcional.
 * - Destinatario: el correo configurado o, sin él, el de administración del sitio (en una red, el de la red).
 * - Ajustes de toda la instalación: `sitemeta` en una red (pantalla de red o sitio principal) y `options` en un sitio.
 *
 * Un trabajo terminado queda marcado `notice: pending` en su estado del almacén; flush_jobs() reclama la marca con el
 * cerrojo de los trabajos (a lo sumo una vez, aunque varios procesos vacíen a la vez) y envía fuera del cerrojo. Así un
 * proceso que muere después de terminar el trabajo no pierde el aviso y ninguno se repite.
 *
 * El correo nunca lleva un secreto (credenciales de los destinos, claves de wp-config.php, frases) ni una ruta del
 * servidor: los mensajes de error pasan por clean().
 */
final class Digitalisimo_Backups_Notify {
	const OPTION = 'digitalisimo_backups_notifications';
	const ACTION_SAVE = 'digitalisimo_backups_save_notifications';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'save' ) );
	}

	/** Fallos activos por defecto, éxitos no; `email` vacío usa el correo de administración. */
	public static function settings() {
		$saved = Digitalisimo_Backups_Keys::get( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'failure' => array_key_exists( 'failure', $saved ) ? ! empty( $saved['failure'] ) : true,
			'success' => ! empty( $saved['success'] ),
			'email' => self::valid_email( $saved['email'] ?? '' ),
		);
	}
	/** Correo de administración del sitio o, en una red, el de la red. */
	public static function admin_email() {
		return self::valid_email( is_multisite() ? get_site_option( 'admin_email', '' ) : get_option( 'admin_email', '' ) );
	}
	/**
	 * Ajustes y destinatario de este momento. Una restauración los toma antes del SQL (que trae los del respaldo) para
	 * avisar a quien la pidió.
	 */
	public static function snapshot() {
		$settings = self::settings();
		$settings['to'] = '' !== $settings['email'] ? $settings['email'] : self::admin_email();
		return $settings;
	}
	/** Estado público para `status` de REST y WP-CLI: sin el correo. */
	public static function status() {
		$settings = self::settings();
		return array( 'failure' => $settings['failure'], 'success' => $settings['success'], 'recipient' => '' !== $settings['email'] ? 'custom' : 'admin' );
	}
	private static function valid_email( $email ) {
		$email = trim( (string) $email );
		if ( '' === $email || strlen( $email ) > 254 ) return '';
		$valid = function_exists( 'is_email' ) ? is_email( $email ) : filter_var( $email, FILTER_VALIDATE_EMAIL );
		return $valid ? $email : '';
	}

	/* ------------------------------------------------------------------ Trabajos */

	/**
	 * Marca de un trabajo terminado que debe avisar: éxito o fallo definitivo. Nunca la copia previa de una restauración
	 * ni el paquete que el origen prepara para una migración (no es un respaldo suyo: su uso queda en el registro de la
	 * clave); la migración que recibe el destino avisa con su propio texto (migration()).
	 */
	public static function mark( array $job ) {
		if ( ! empty( $job['spec']['inline'] ) || 'source' === ( $job['spec']['migration']['role'] ?? '' ) || ! in_array( $job['status'] ?? '', array( 'success', 'failed' ), true ) ) return $job;
		$job['notice'] = 'pending';
		return $job;
	}
	/**
	 * Envía los avisos pendientes de los trabajos terminados. Cada marca se reclama con el cerrojo de los trabajos antes
	 * de enviar: dos procesos que vacían a la vez no envían el mismo aviso dos veces. Nunca lanza.
	 */
	public static function flush_jobs() {
		try {
			$jobs = Digitalisimo_Backups_Job_Store::all();
		} catch ( Throwable $e ) {
			return 0;
		}
		$sent = 0;
		foreach ( $jobs as $job ) {
			if ( 'pending' !== ( $job['notice'] ?? '' ) || ! Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) continue;
			try {
				$claimed = self::claim( (string) $job['id'] );
				if ( $claimed && self::job( $claimed ) ) $sent++;
			} catch ( Throwable $e ) {
				if ( function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: no se pudo enviar un aviso: ' . $e->getMessage() );
			}
		}
		return $sent;
	}
	/**
	 * Reclama el aviso pendiente de un trabajo con el cerrojo de los trabajos: devuelve el trabajo (ya marcado `sent`)
	 * sólo al primero que lo pide; otro proceso que leyó la lista antes recibe null y no envía.
	 */
	public static function claim( $id ) {
		return Digitalisimo_Backups_Job_Store::with_lock( function() use ( $id ) {
			$fresh = Digitalisimo_Backups_Job_Store::read( $id );
			if ( ! $fresh || ! Digitalisimo_Backups_Job_Store::is_terminal( $fresh ) || 'pending' !== ( $fresh['notice'] ?? '' ) ) return null;
			$fresh['notice'] = 'sent';
			return Digitalisimo_Backups_Job_Store::write( $fresh );
		} );
	}
	/** Aviso de un trabajo terminado: respaldo creado, respaldo fallido o copia remota fallida (el ZIP local se conserva). */
	public static function job( array $job ) {
		if ( 'migrate' === ( $job['spec']['kind'] ?? '' ) ) return self::migration( $job );
		$snapshot = self::snapshot();
		$spec = (array) ( $job['spec'] ?? array() );
		$ok = 'success' === ( $job['status'] ?? '' );
		$destination = (string) ( $spec['destination'] ?? 'local' );
		$target = Digitalisimo_Backups::destination_name( $destination );
		$file = basename( (string) ( $job['result']['file'] ?? $spec['file'] ?? '' ) );
		$phase = (string) ( $job['phase'] ?? '' );
		$transfer = ! $ok && 'transfer' === $phase && 'local' !== $destination;
		if ( $ok ) $subject = 'Respaldo completo creado y verificado';
		elseif ( $transfer ) $subject = 'La copia a ' . $target . ' falló';
		else $subject = 'El respaldo falló';
		$lines = array( self::origin_label( $spec ) );
		if ( $ok ) {
			$lines[] = 'Respaldo: ' . $file . ( ! empty( $job['result']['size'] ) ? ' (' . self::bytes( (int) $job['result']['size'] ) . ')' : '' );
			$lines[] = 'Destino: ' . ( 'local' === $destination ? $target : Digitalisimo_Backups::destination_name( 'local' ) . ' y ' . $target . ' (copia verificada)' );
			if ( ! empty( $spec['security']['encrypt'] ) ) $lines[] = 'Cifrado: sí';
		} else {
			if ( $transfer ) {
				$lines[] = 'Respaldo: ' . $file;
				$lines[] = 'El respaldo local está verificado y se conserva en el historial; la copia a ' . $target . ' no se completó.';
			} else {
				$lines[] = 'Destino: ' . $target;
				$lines[] = 'No se registró ningún respaldo parcial.';
			}
			if ( '' !== $phase ) $lines[] = 'Fase: ' . Digitalisimo_Backups_Jobs::phase_label( $phase );
			$lines[] = 'Motivo: ' . self::clean( (string) ( $job['error']['message'] ?? 'El trabajo falló.' ) );
		}
		return self::send( $snapshot, $ok, $subject, $lines );
	}
	/**
	 * Aviso de una migración recibida: terminada o fallida. Va a quien se avisaba en este sitio antes de migrar (la foto
	 * se toma al encolarla): los ajustes de avisos que llegan con el SQL del origen son los del origen.
	 */
	public static function migration( array $job ) {
		$spec = (array) ( $job['spec'] ?? array() );
		$snapshot = is_array( $spec['notify'] ?? null ) && array_key_exists( 'to', $spec['notify'] ) ? $spec['notify'] : self::snapshot();
		$ok = 'success' === ( $job['status'] ?? '' );
		$source = (string) ( $spec['migration']['source_host'] ?? '' );
		$lines = array( 'Origen de la migración: ' . ( '' !== $source ? $source : 'otro sitio' ) . ' (' . self::via_label( (string) ( $spec['origin']['via'] ?? '' ) ) . ')' );
		$phase = (string) ( $job['phase'] ?? '' );
		if ( $ok ) {
			if ( ! empty( $job['result']['safety'] ) ) $lines[] = 'Copia previa bloqueada: ' . basename( (string) $job['result']['safety'] ) . ' («Deshacer la migración» en Backups → Migrar la restaura)';
			$lines[] = 'Este sitio es ahora una copia del origen con su propia dirección: inicia sesión con un usuario del sitio de origen.';
			foreach ( (array) ( $job['warnings'] ?? array() ) as $warning ) if ( '' !== (string) $warning ) $lines[] = 'Aviso: ' . self::clean( (string) $warning );
			return self::send( $snapshot, true, 'Migración terminada', $lines );
		}
		$own = class_exists( 'Digitalisimo_Backups_Migrate' ) ? Digitalisimo_Backups_Migrate::job_phase( $phase ) : null;
		if ( '' !== $phase ) $lines[] = 'Fase: ' . ( 'preflight' === $phase ? 'Conexión con el origen' : ( $own ? $own['label'] : Digitalisimo_Backups_Jobs::phase_label( $phase ) ) );
		if ( ! in_array( $phase, array( 'restore', 'finish' ), true ) ) $lines[] = 'No se cambió nada de este sitio.';
		$lines[] = 'Motivo: ' . self::clean( (string) ( $job['error']['message'] ?? 'La migración falló.' ) );
		return self::send( $snapshot, false, 'La migración falló', $lines );
	}
	private static function origin_label( array $spec ) {
		$policy = (string) ( $spec['policy'] ?? 'manual' );
		if ( 'manual' === $policy ) return 'Origen: respaldo manual (' . self::via_label( (string) ( $spec['origin']['via'] ?? '' ) ) . ')';
		if ( 'legacy-full' === $policy ) return 'Origen: programación anterior';
		$found = Digitalisimo_Backups::policy( $policy );
		return 'Origen: automatización' . ( $found ? ' «' . Digitalisimo_Backups::automation_name( $found ) . '»' : '' );
	}
	private static function via_label( $via ) {
		return array( 'admin-post' => 'pantalla', 'ajax' => 'pantalla', 'rest' => 'API REST', 'cli' => 'WP-CLI', 'cron' => 'WP-Cron' )[ $via ] ?? 'pantalla';
	}

	/* ------------------------------------------------------------------ Restauraciones */

	/**
	 * Aviso de una restauración que empezó (se abrió el paquete para restaurarlo): terminada, rechazada antes de cambiar
	 * nada o fallida (con lo que se recuperó). $snapshot son los ajustes tomados antes del SQL.
	 */
	public static function restore( array $snapshot, $ok, array $facts ) {
		$kind = (string) ( $facts['kind'] ?? 'backup' );
		$lines = array();
		if ( '' !== (string) ( $facts['file'] ?? '' ) ) $lines[] = 'Archivo: ' . basename( (string) $facts['file'] ) . ( 'updraft' === $kind ? ' (UpdraftPlus)' : '' );
		if ( ! empty( $facts['via'] ) ) $lines[] = 'Origen: ' . self::via_label( (string) $facts['via'] );
		if ( $ok ) {
			if ( ! empty( $facts['safety'] ) ) $lines[] = 'Copia previa bloqueada: ' . basename( (string) $facts['safety'] );
			foreach ( (array) ( $facts['notes'] ?? array() ) as $note ) if ( '' !== (string) $note ) $lines[] = self::clean( (string) $note );
			return self::send( $snapshot, true, 'Restauración terminada', $lines );
		}
		$lines[] = 'Motivo: ' . self::clean( (string) ( $facts['message'] ?? 'La restauración falló.' ) );
		return self::send( $snapshot, false, ! empty( $facts['started'] ) ? 'La restauración falló' : 'La restauración se rechazó sin cambiar nada', $lines );
	}

	/* ------------------------------------------------------------------ Envío */

	private static function send( array $snapshot, $ok, $subject, array $lines ) {
		if ( ! ( $ok ? ! empty( $snapshot['success'] ) : ! empty( $snapshot['failure'] ) ) ) return false;
		$to = (string) ( $snapshot['to'] ?? '' );
		if ( '' === $to || ! function_exists( 'wp_mail' ) ) return false;
		$site = self::site_name();
		$body = array_merge(
			array( 'Sitio: ' . $site . ' (' . self::site_url() . ')', 'Fecha: ' . self::now() ),
			$lines,
			array( '', ( $ok ? 'Detalle' : 'Revisa el detalle' ) . ' en Backups: ' . self::screen_url(), '', '— DIGITALÍSIMO Backups' )
		);
		$sent = (bool) wp_mail( $to, '[' . $site . '] ' . $subject, implode( "\n", $body ) );
		if ( ! $sent && function_exists( 'error_log' ) ) error_log( 'DIGITALÍSIMO Backups: wp_mail() no envió el aviso «' . $subject . '».' );
		return $sent;
	}
	/**
	 * Texto apto para un correo: sin secretos (credenciales de los destinos y claves de wp-config.php), sin rutas del
	 * servidor (la raíz de WordPress, el almacén, el temporal, cualquier ruta absoluta de Unix o Windows aunque lleve
	 * espacios, rutas de un nivel, `file:` y otros esquemas de PHP, listas de `open_basedir`) y sin datos del sitio: el
	 * detalle de un error de MySQL (que cita valores de las filas) no va, y lo que va entre comillas se sustituye por «…».
	 * Ante la duda tapa de más: el detalle completo está en la pantalla y en el registro del trabajo.
	 */
	public static function clean( $text ) {
		$text = (string) $text;
		$secrets = array();
		try {
			foreach ( Digitalisimo_Backups::secret_values() as $value ) if ( strlen( $value ) >= 4 ) $secrets[] = $value;
		} catch ( Throwable $e ) {
			// Sin el almacén cifrado no hay credenciales que ocultar.
		}
		foreach ( array( 'DB_PASSWORD', 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT', 'DIGITALISIMO_BACKUPS_KEY' ) as $constant ) {
			if ( defined( $constant ) && is_string( constant( $constant ) ) && strlen( constant( $constant ) ) >= 4 ) $secrets[] = constant( $constant );
		}
		usort( $secrets, function( $a, $b ) { return strlen( $b ) <=> strlen( $a ); } );
		foreach ( $secrets as $secret ) $text = str_replace( $secret, '[oculto]', $text );
		// Un error de MySQL o SQLite cita valores de las filas (correos, nombres): sólo queda que falló.
		$text = preg_replace( '/Falló una sentencia SQL:.*$/su', 'Falló una sentencia SQL (el detalle, con datos del sitio, no va en el correo).', $text );
		// Rutas: primero las conocidas (por si contienen espacios), después cualquier ruta.
		$roots = array( ABSPATH, defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '', function_exists( 'sys_get_temp_dir' ) ? sys_get_temp_dir() : '' );
		try { $roots[] = Digitalisimo_Backups::storage_directory(); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		foreach ( $roots as $root ) {
			$root = rtrim( str_replace( '\\', '/', (string) $root ), '/' );
			if ( strlen( $root ) > 1 ) $text = str_replace( array( $root, str_replace( '/', '\\', $root ) ), '[ruta]', $text );
		}
		$stop = '\s\'"«»,;()';
		// file:, phar://, zip://… (la ruta va pegada al esquema).
		$text = preg_replace( '#\b(?:file|phar|zip|compress\.zlib|compress\.bzip2|glob|php):/{0,2}[^' . $stop . ']*#iu', '[ruta]', $text );
		// Windows: C:\Program Files\Datos Cliente\respaldo.zip (las carpetas pueden llevar espacios).
		$text = preg_replace( '#\b[A-Za-z]:\\\\(?:[^\\\\' . $stop . ':]+(?: [^\\\\' . $stop . ':]+)*\\\\)*[^\\\\' . $stop . ':]*#u', '[ruta]', $text );
		// Unix: /mnt/Copias de seguridad/cliente/viejo.zip (las carpetas pueden llevar espacios) y /backups (un nivel).
		$text = preg_replace( '#(?<![\w:/.\]\\\\])/(?:[^/' . $stop . ':]+(?: [^/' . $stop . ':]+)*/)*[^/' . $stop . ':]+#u', '[ruta]', $text );
		// Lo que quede pegado a una ruta ya tapada, también el resto de una lista (open_basedir: /home/cliente:/tmp).
		$text = preg_replace( '#\[ruta\][^' . $stop . ']*#u', '[ruta]', $text );
		// Lo que va entre comillas suele ser un dato (un valor de una fila, un nombre de archivo).
		$text = preg_replace( array( "/'[^'\n]{1,300}'/u", '/"[^"\n]{1,300}"/u' ), array( "'…'", '"…"' ), $text );
		return trim( preg_replace( '/\s+/', ' ', $text ) );
	}
	private static function site_name() {
		$name = is_multisite() ? (string) get_site_option( 'site_name', '' ) : (string) get_bloginfo( 'name' );
		$name = trim( function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $name ) : strip_tags( $name ) );
		return '' !== $name ? $name : (string) wp_parse_url( self::site_url(), PHP_URL_HOST );
	}
	private static function site_url() {
		return is_multisite() && function_exists( 'network_home_url' ) ? network_home_url() : home_url();
	}
	private static function screen_url() {
		return is_multisite() ? network_admin_url( 'admin.php?page=digitalisimo-network-backups' ) : admin_url( 'admin.php?page=digitalisimo-backups' );
	}
	private static function now() {
		return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i' ) . ' (hora del sitio)' : gmdate( 'Y-m-d H:i' ) . ' UTC';
	}
	private static function bytes( $bytes ) {
		return function_exists( 'size_format' ) ? (string) size_format( (int) $bytes ) : round( $bytes / 1048576, 1 ) . ' MB';
	}

	/* ------------------------------------------------------------------ Pestaña «Avisos» */

	/** Pestaña «Avisos»: un formulario propio y ningún name de otra pestaña. */
	public static function panel( $network ) {
		echo '<section class="digitalisimo-backups-card"><h2>Avisos por correo</h2>';
		if ( is_multisite() && ! $network && ! is_main_site() ) { echo '<p>Los avisos de los respaldos son de toda la red: se configuran desde la administración de red o desde el sitio principal.</p></section>'; return; }
		$settings = self::settings();
		$admin = self::admin_email();
		echo '<p>Un aviso por respaldo, copia remota o restauración terminada. Un fallo sólo avisa cuando es definitivo: los reintentos automáticos no envían correo. El correo nunca lleva contraseñas, claves ni rutas del servidor.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( self::ACTION_SAVE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION_SAVE ) . '">';
		if ( $network ) echo '<input type="hidden" name="digitalisimo_backups_network" value="1">';
		echo '<table class="form-table">';
		echo '<tr><th>Fallos</th><td><label><input type="checkbox" name="digitalisimo_backups_notify[failure]" value="1"' . checked( $settings['failure'], true, false ) . '> Avisar cuando falle un respaldo, su copia remota o una restauración</label></td></tr>';
		echo '<tr><th>Éxitos</th><td><label><input type="checkbox" name="digitalisimo_backups_notify[success]" value="1"' . checked( $settings['success'], true, false ) . '> Avisar también cuando terminen bien</label></td></tr>';
		echo '<tr><th><label for="digitalisimo-backups-notify-email">Correo de los avisos</label></th><td><input class="regular-text" type="email" id="digitalisimo-backups-notify-email" name="digitalisimo_backups_notify[email]" value="' . esc_attr( $settings['email'] ) . '" placeholder="' . esc_attr( '' !== $admin ? $admin : 'correo@ejemplo.com' ) . '"><p class="description">Vacío: el correo de administración ' . ( is_multisite() ? 'de la red' : 'del sitio' ) . ( '' !== $admin ? ' (' . esc_html( $admin ) . ')' : '' ) . '.</p></td></tr>';
		echo '</table><p><button class="button button-primary" name="digitalisimo_backups_notify[save]" value="1">Guardar avisos</button> <button class="button" name="digitalisimo_backups_notify[test]" value="1">Guardar y enviar un correo de prueba</button></p></form></section>';
	}
	public static function save() {
		if ( ! Digitalisimo_Backups::can_manage_backups() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_SAVE );
		if ( is_multisite() && ! is_main_site() ) Digitalisimo_Backups::redirect( false, 'Los avisos de los respaldos se configuran desde la administración de red o el sitio principal.', null, 'notifications' );
		$input = isset( $_POST['digitalisimo_backups_notify'] ) && is_array( $_POST['digitalisimo_backups_notify'] ) ? wp_unslash( $_POST['digitalisimo_backups_notify'] ) : array();
		$email = trim( (string) ( $input['email'] ?? '' ) );
		if ( '' !== $email && '' === self::valid_email( $email ) ) Digitalisimo_Backups::redirect( false, 'El correo de los avisos no es válido. No se cambió nada.', null, 'notifications' );
		Digitalisimo_Backups_Keys::put( self::OPTION, array( 'failure' => ! empty( $input['failure'] ), 'success' => ! empty( $input['success'] ), 'email' => $email ) );
		$message = 'Avisos guardados.';
		if ( ! empty( $input['test'] ) ) {
			$snapshot = self::snapshot();
			$snapshot['success'] = true;
			$message .= self::send( $snapshot, true, 'Correo de prueba de los avisos', array( 'Los avisos de DIGITALÍSIMO Backups llegan a este correo.' ) ) ? ' Se envió un correo de prueba a ' . $snapshot['to'] . '.' : ' No se pudo enviar el correo de prueba: revisa el envío de correo de WordPress.';
		}
		Digitalisimo_Backups::redirect( true, $message, null, 'notifications' );
	}
}
