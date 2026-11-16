<?php
defined( 'ABSPATH' ) || exit;

/**
 * «Enviar a un destino»: copia a un destino remoto un respaldo de la lista que aún no tiene copia remota, hecho aquí o
 * subido (pedido del propietario del 2026-11-15: lo subido se usa «exactamente igual» que lo hecho aquí). Pantalla («⋯»
 * de la fila), `POST /backups/<archivo>/send` y `wp digitalisimo-backups send`, sobre el trabajo `send`
 * (Digitalisimo_Backups_Send_Job). Un respaldo tiene una sola copia remota (varios destinos por respaldo sigue pendiente,
 * como varios destinos por trabajo): si ya la tiene, o está en camino, no se envía otra.
 */
trait Digitalisimo_Backups_Send_Service {
	/** Por qué la fila no se puede enviar ('' si se puede): sin su archivo en el almacén, o con una copia remota. */
	private static function send_problem( array $row ) {
		if ( '' === self::local_copy( $row ) ) return 'El respaldo no tiene su archivo en el almacén privado: tráelo antes con «Traer al almacén».';
		$copy = self::remote_copy( $row );
		if ( $copy && in_array( $copy['state'], array( 'ok', 'legacy' ), true ) ) return 'Este respaldo ya tiene su copia remota en ' . self::destination_label( $copy['destination'] ) . ' (un respaldo tiene una sola copia remota).';
		if ( $copy && 'pending' === $copy['state'] ) return 'La copia de este respaldo a ' . self::destination_label( $copy['destination'] ) . ' está en camino.';
		return '';
	}
	/** Destinos a los que se puede enviar: los remotos con una conexión completa. */
	private static function send_destinations() {
		$out = self::manual_destinations( self::settings() );
		unset( $out['local'] );
		return $out;
	}
	/**
	 * Encola el envío de `$file` a `destination` y lo hace avanzar en esta petición (en WP-CLI, hasta terminar). En una red
	 * pide el alcance de red, como traer al almacén. Devuelve el resultado (`sent`), el trabajo en curso (`running`) o el
	 * error que lo impide.
	 */
	public static function service_send( $file, array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$name = sanitize_file_name( (string) $file );
		$row = '' !== $name ? self::history_item( $name ) : array();
		if ( ! $row ) return self::service_error( 'not_found', 'Ese respaldo no está en la lista de respaldos.', 404 );
		$destination = self::remote_destination_id( (string) ( $args['destination'] ?? '' ) );
		if ( is_wp_error( $destination ) ) return $destination;
		$problem = self::send_problem( $row );
		if ( '' !== $problem ) return self::service_error( 'send_copy', $problem, 409 );
		$via = (string) ( $args['via'] ?? ( defined( 'WP_CLI' ) && WP_CLI ? 'cli' : 'rest' ) );
		$spec = array(
			'scope' => is_multisite() ? 'network' : 'installation',
			'type' => 'send',
			'kind' => Digitalisimo_Backups_Send_Job::KIND,
			'destination' => $destination,
			// Un envío por respaldo: enqueue() nunca duplica un trabajo sin terminar de la misma «política».
			'policy' => 'send:' . $name,
			'retain' => 1,
			'retention' => 'none',
			'site_id' => (int) get_current_blog_id(),
			'file' => $name,
			'inline' => false,
			'send' => array( 'sha256' => strtolower( (string) ( $row['sha256'] ?? '' ) ), 'size' => (int) ( $row['size'] ?? 0 ) ),
			'origin' => array( 'via' => $via, 'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0, 'blog_id' => (int) get_current_blog_id(), 'context' => is_multisite() ? 'network' : 'site' ),
			'engine' => array( 'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '', 'job_format' => Digitalisimo_Backups_Job_Store::JOB_FORMAT ),
		);
		try {
			$queued = Digitalisimo_Backups_Jobs::enqueue( $spec );
		} catch ( Throwable $e ) {
			return self::service_error( 'storage', $e->getMessage(), 500 );
		}
		try { Digitalisimo_Backups_Jobs::schedule(); } catch ( Throwable $e ) { error_log( 'DIGITALÍSIMO Backups: ' . $e->getMessage() ); }
		$cli = array_key_exists( 'drive_cli', $args ) ? (bool) $args['drive_cli'] : 'cli' === PHP_SAPI;
		$job = self::fetch_drive( (string) $queued['job']['id'], $cli );
		if ( ! $job ) return self::service_error( 'storage', 'El trabajo del envío ya no está en el almacén privado.', 500 );
		$view = self::service_job_view( $job );
		if ( 'success' === $job['status'] ) return array( 'file' => $name, 'sent' => true, 'destination' => $destination, 'message' => (string) ( $job['progress']['message'] ?? '' ), 'job' => $view );
		if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) return self::service_error( 'send_failed', (string) ( $job['error']['message'] ?? ( $job['progress']['message'] ?? 'El envío falló.' ) ), 'cancelled' === $job['status'] ? 409 : 502, array( 'job' => (string) $job['id'] ) );
		return array( 'file' => $name, 'sent' => false, 'running' => true, 'destination' => $destination, 'message' => 'El envío a ' . self::destination_label( $destination ) . ' sigue en segundo plano (trabajo ' . $job['id'] . '): lo continúan la pantalla, WP-Cron, `POST /jobs/tick` o `wp digitalisimo-backups jobs run`.', 'job' => $view );
	}
	/** @internal Fases del trabajo `send` (Digitalisimo_Backups_Send_Job). */
	public static function send_phase( $phase, array $job ) {
		$spec = (array) $job['spec'];
		$file = basename( (string) $spec['file'] );
		$destination = (string) $spec['destination'];
		if ( 'preflight' === $phase ) {
			if ( Digitalisimo_Backups_Job_Store::JOB_FORMAT !== (int) ( $spec['engine']['job_format'] ?? 0 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El trabajo se creó con otra versión del motor de respaldos; vuelve a iniciarlo.' );
			$row = self::history_item( $file );
			if ( ! $row ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo ya no está en la lista de respaldos.' );
			$path = self::local_copy( $row );
			if ( '' === $path ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo ya no tiene su archivo en el almacén privado.' );
			$problem = self::credentials_problem( $destination );
			if ( '' !== $problem ) throw new Digitalisimo_Backups_Permanent_Error( $problem );
			// Lo que se envía es exactamente lo registrado: un archivo que cambió en el almacén no sale.
			$sha = strtolower( (string) ( $spec['send']['sha256'] ?? '' ) );
			if ( '' === $sha || ! hash_equals( $sha, strtolower( (string) ( $row['sha256'] ?? '' ) ) ) || ! hash_equals( $sha, (string) @hash_file( 'sha256', $path ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El SHA-256 del respaldo ya no coincide con el de su fila: no se envió.' );
			$claimed = self::change_history( function( &$history ) use ( $file, $destination ) {
				foreach ( $history as &$item ) {
					if ( ! is_array( $item ) || (string) ( $item['file'] ?? '' ) !== $file ) continue;
					$copy = self::remote_copy( $item );
					if ( $copy && in_array( $copy['state'], array( 'ok', 'legacy' ), true ) ) return false;
					if ( $copy && 'pending' === $copy['state'] && $copy['destination'] !== $destination ) return false;
					$item['transfer_destination'] = $destination;
					$item['transfer'] = 'pending';
					$item['transfer_error'] = '';
					return true;
				}
				return false;
			} );
			if ( ! $claimed ) throw new Digitalisimo_Backups_Permanent_Error( 'Este respaldo ya tiene una copia remota (o una en camino): no se envió otra.' );
			Digitalisimo_Backups_Jobs::progress( 5, 'Copiando a ' . self::destination_label( $destination ) . '…', true );
			return array( 'next' => 'deliver', 'checkpoint' => array( 'at' => time() ), 'message' => 'Copiando a ' . self::destination_label( $destination ) . '…' );
		}
		if ( 'deliver' === $phase ) {
			$path = self::local_path( $file );
			if ( '' === $path ) throw new Digitalisimo_Backups_Permanent_Error( 'El archivo del respaldo desapareció del almacén antes de copiarlo al destino.' );
			try {
				$remote = self::job_transfer( $path, $destination, (string) $job['id'], Digitalisimo_Backups_Step_Budget::deadline( 1.0 ) );
			} catch ( Digitalisimo_Backups_Transfer_Pending $e ) {
				return array( 'next' => 'deliver', 'checkpoint' => array( 'slice' => time() ), 'message' => $e->getMessage(), 'progress_made' => true );
			}
			self::history_update( $file, array( 'transfer_destination' => $destination, 'remote' => (array) $remote, 'transfer' => 'ok', 'transfer_error' => '' ) );
			return array( 'next' => null, 'checkpoint' => array( 'provider' => (string) ( $remote['provider'] ?? '' ), 'at' => time() ), 'message' => 'Copia en ' . self::destination_label( $destination ) . ' verificada: ' . $file . '.' );
		}
		throw new Digitalisimo_Backups_Permanent_Error( 'El envío está en una fase desconocida.' );
	}
	/** @internal El envío falló o se canceló en su copia: la fila lo dice (el ZIP local no se toca). Nunca lanza. */
	public static function send_failed( array $job, $status, $message ) {
		if ( 'deliver' !== ( $job['phase'] ?? '' ) ) return;
		$spec = (array) ( $job['spec'] ?? array() );
		try {
			self::change_history( function( &$history ) use ( $spec, $status, $message ) {
				foreach ( $history as &$item ) {
					if ( ! is_array( $item ) || (string) ( $item['file'] ?? '' ) !== basename( (string) ( $spec['file'] ?? '' ) ) ) continue;
					if ( (string) ( $item['transfer_destination'] ?? '' ) !== (string) ( $spec['destination'] ?? '' ) || 'pending' !== (string) ( $item['transfer'] ?? '' ) ) return false;
					$item['transfer'] = 'cancelled' === $status ? 'cancelled' : 'failed';
					$item['transfer_error'] = (string) $message;
					return true;
				}
				return false;
			} );
		} catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	/** «Enviar» del menú «⋯» de una fila (admin-post: capability, nonce propio y, en una red, el alcance de red). */
	public static function send_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( 'digitalisimo_backups_send' );
		$result = self::service_send( wp_unslash( (string) ( $_POST['file'] ?? '' ) ), array( 'destination' => sanitize_text_field( wp_unslash( (string) ( $_POST['destination'] ?? '' ) ) ), 'network' => is_multisite(), 'via' => 'admin-post', 'drive_cli' => false ) );
		if ( is_wp_error( $result ) ) self::redirect( false, 'No se envió: ' . $result->get_error_message(), null, 'backups' );
		self::redirect( true, ! empty( $result['sent'] ) ? (string) $result['message'] : 'Enviando a ' . self::destination_label( (string) $result['destination'] ) . ': sigue en «Trabajos».', null, 'backups' );
	}
	/** El formulario «Enviar a» de la fila: sólo si se puede enviar y hay algún destino remoto con su conexión completa. */
	private static function send_form( array $item ) {
		if ( '' !== self::send_problem( $item ) ) return '';
		$destinations = self::send_destinations();
		if ( ! $destinations ) return '';
		$options = '';
		foreach ( $destinations as $id => $label ) $options .= '<option value="' . esc_attr( (string) $id ) . '">' . esc_html( (string) $label ) . '</option>';
		ob_start();
		wp_nonce_field( 'digitalisimo_backups_send' );
		$nonce = (string) ob_get_clean();
		return '<form class="digitalisimo-backups-send" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">' . $nonce . '<input type="hidden" name="action" value="digitalisimo_backups_send"><input type="hidden" name="file" value="' . esc_attr( (string) $item['file'] ) . '">' . ( self::network_context() ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<label>Enviar a <select name="destination">' . $options . '</select></label> <button class="button button-small">Enviar</button></form>';
	}
}
