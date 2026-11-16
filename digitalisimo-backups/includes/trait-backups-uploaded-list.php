<?php
defined( 'ABSPATH' ) || exit;

/**
 * Un respaldo subido queda en la lista de respaldos como uno hecho aquí (pedido del propietario, 2026-11-15).
 *
 * Al cerrarse bien una subida (después de toda la validación de archivos no confiables de «Seguridad», que no cambia),
 * el archivo entra en el almacén **y** en el historial con una fila propia: fecha de creación (la del manifiesto), tamaño,
 * tipo (completo o punto incremental, con su cadena), su SHA-256 exacto, la identidad de su manifiesto (`backup_id` y el
 * SHA-256 de `manifest.json`) y su origen (`origin` = `uploaded`, con el bloque `upload`: cuándo se subió, por qué vía, de
 * qué dirección viene, si está firmado con una clave de este sitio y si es de este mismo sitio). Desde la lista se
 * restaura (también por partes), se verifica, se descarga, se envía a un destino, se bloquea y se borra como cualquier otro
 * respaldo.
 *
 * Qué no cambia por estar en la lista (docs/BACKUPS.md, «Seguridad» → «Respaldos subidos»):
 * - Confianza: la fila no vuelve «de este sitio» al archivo. Sin una firma válida de una clave de este sitio sigue siendo
 *   `untrusted` para la validación de la restauración (entradas y guardián SQL estrictos); lo único que cambia es que
 *   basta RESTAURAR (`vouched` = `uploaded` en Digitalisimo_Backups_Security::open()), porque subirlo ya fue el acto de
 *   confianza de una cuenta con la capability. Atado a los bytes exactos: un archivo que cambie después en el almacén
 *   no coincide con el SHA-256 de su fila y se rechaza.
 * - Otro sitio: H23 (la configuración de Backups de aquí se conserva), «Conservar mi acceso» y la reubicación siguen
 *   tratándolo como de otra instalación (keep_config_same_installation()).
 * - Retención: ni la global ni la de una automatización lo cuentan ni borran su archivo (sólo retiran su fila si se quedó
 *   sin ninguna copia); el tope del historial lo cuenta aparte, con su propio tope de 100 filas subidas o agregadas
 *   (nunca desplaza la fila de un respaldo hecho aquí).
 * - Cadenas: un punto subido sin la firma de una clave de este sitio sólo depende de las filas que son exactamente el
 *   eslabón que nombra (mismo `backup_id`, mismo SHA-256 de manifiesto y misma posición), hechas aquí o subidas: nombrar
 *   un respaldo por su nombre no lo fija ni bloquea su «Borrar» (Digitalisimo_Backups_Chain::anchored_links()).
 * - «Respaldo reciente válido» (antes de actualizar): sólo si es de este mismo sitio con firma válida de su clave
 *   (`upload.same_site`).
 * - Un `.dgbk` cifrado con una clave que este sitio no tiene no se registra sin su frase (su tipo y su cadena no se
 *   saben): entra «sin registro» y se agrega a la lista al cerrar la subida o «Agregar al listado» con su frase.
 *
 * «Agregar al listado» (Herramientas → «Restaurar un archivo suelto», `POST /backups/<archivo>/register`,
 * `wp digitalisimo-backups register`) pasa a la lista, con la misma validación completa, un archivo «sin registro» que ya
 * estaba en el almacén (subido con 1.0.77 o antes, traído de un destino o copiado a mano). Como no se sabe quién lo puso
 * ahí, pide escribir AGREGAR SIN FIRMA si no lleva una firma válida de este sitio, y su fila dice «Agregado» (`origin` =
 * `added`), no «Subido».
 */
trait Digitalisimo_Backups_Uploaded_List {
	/** Una fila del historial que entró de fuera: «Subir un respaldo» (`uploaded`) o «Agregar al listado» (`added`). */
	public static function is_uploaded_row( $row ) {
		return is_array( $row ) && in_array( (string) ( $row['origin'] ?? '' ), array( 'uploaded', 'added' ), true );
	}
	/** Una fila de «Agregar al listado»: un archivo del almacén de origen desconocido que alguien agregó a mano. */
	public static function is_added_row( $row ) {
		return is_array( $row ) && 'added' === (string) ( $row['origin'] ?? '' );
	}
	/** Un punto subido o agregado sin la firma de una clave de este sitio: su manifiesto (y su cadena) no son confiables. */
	private static function uploaded_untrusted( $row ) {
		return self::is_uploaded_row( $row ) && empty( $row['upload']['site_key'] );
	}
	/**
	 * Fecha de la fila: la de creación que dice el respaldo (ISO 8601 del manifiesto o de la cabecera DGBK), en la hora
	 * del sitio y con el formato de las demás filas (`Y-m-d H:i:s`). Sin una fecha legible, la de ahora.
	 */
	private static function uploaded_date( $iso ) {
		$iso = trim( (string) $iso );
		try {
			if ( '' !== $iso && preg_match( '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $iso ) ) {
				$at = new DateTimeImmutable( $iso, new DateTimeZone( 'UTC' ) );
				return $at->setTimezone( self::site_timezone() )->format( 'Y-m-d H:i:s' );
			}
		} catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		return current_time( 'mysql' );
	}
	/**
	 * Alcance de la fila de un respaldo subido: el que tendría hecho en su instalación de origen (`network` si es de una
	 * red, `installation` si no, `extracted-site` para un sitio extraído). Se restaura aquí sólo si coincide con esta
	 * instalación (uploaded_restorable_scope()).
	 */
	private static function uploaded_scope( array $info ) {
		if ( in_array( (string) ( $info['scope'] ?? '' ), array( Digitalisimo_Backups_Site_Extract::SCOPE, Digitalisimo_Backups_Site_Import::SCOPE ), true ) ) return (string) $info['scope'];
		return ! empty( $info['multisite'] ) ? 'network' : 'installation';
	}
	/** Una fila subida cuyo alcance se puede restaurar en esta instalación (una red en una red, un sitio en un sitio). */
	private static function uploaded_restorable_scope( array $row ) {
		// En una red, el respaldo de un sitio (individual, extraído o la copia de un sitio) se importa como un sitio de la red.
		if ( Digitalisimo_Backups_Site_Import::row_applies( $row ) ) return true;
		$scope = (string) ( $row['scope'] ?? '' );
		if ( Digitalisimo_Backups_Site_Extract::SCOPE === $scope ) return ! is_multisite();
		return $scope === ( is_multisite() ? 'network' : 'installation' );
	}
	/**
	 * Registra el respaldo subido `$path` (ya en el almacén, validado y abierto entero: nunca un `.dgbk` sin su frase) con
	 * su SHA-256 `$checksum`. `$info` es el informe de la validación (upload_inspect()) más `via`, `name` (el nombre con que
	 * se subió), `user_id` y `origin` (`uploaded` o `added`). Arriba del historial, como un respaldo recién hecho: es lo
	 * último que llegó al almacén. Devuelve la fila.
	 */
	private static function remember_uploaded( $path, $checksum, array $info ) {
		$name = wp_basename( $path );
		$row_info = is_array( $info['_row'] ?? null ) ? $info['_row'] : array();
		$chain = is_array( $row_info['chain'] ?? null ) ? $row_info['chain'] : null;
		$origin = 'added' === (string) ( $info['origin'] ?? '' ) ? 'added' : 'uploaded';
		$row = array(
			'date' => self::uploaded_date( (string) ( $info['created_at'] ?? '' ) ),
			'file' => $name,
			'size' => (int) filesize( $path ),
			'sha256' => (string) $checksum,
			'destination' => 'local',
			'scope' => self::uploaded_scope( $info ),
			'site_id' => (int) get_current_blog_id(),
			'policy' => $origin,
			'backup_type' => $chain && (int) $chain['sequence'] > 0 ? 'incremental' : 'full',
			'remote' => array(),
			'locked' => false,
		) + Digitalisimo_Backups_Security::describe( $path ) + array(
			'origin' => $origin,
			'upload' => array(
				'at' => current_time( 'mysql' ),
				'via' => (string) ( $info['via'] ?? '' ),
				'user_id' => (int) ( $info['user_id'] ?? 0 ),
				'name' => (string) ( $info['name'] ?? '' ),
				// De qué dirección viene (su manifiesto).
				'home' => (string) ( $row_info['home'] ?? '' ),
				'verified' => ! empty( $info['verified'] ),
				'signature' => (string) ( $info['signature'] ?? '' ),
				// La identidad de su manifiesto: con ella lo reconoce el punto incremental que lo nombra en su cadena.
				'manifest_sha256' => (string) ( $row_info['manifest_sha256'] ?? '' ),
				// Firmado con una clave del llavero de aquí: su manifiesto (y su cadena) son de este sitio.
				'site_key' => ! empty( $row_info['site_key'] ),
				// De este mismo sitio: firmado con una clave de su llavero y con su dirección, ruta y prefijo.
				'same_site' => ! empty( $row_info['same_site'] ),
				'problems' => array_values( array_map( 'strval', (array) ( $info['problems'] ?? array() ) ) ),
			),
		);
		if ( $chain ) $row['chain'] = $chain;
		if ( Digitalisimo_Backups_Site_Extract::SCOPE === $row['scope'] ) $row['extract'] = array( 'site_id' => 0, 'label' => (string) ( $row_info['extract'] ?? '' ) );
		self::change_history( function( &$history ) use ( $name, $row ) {
			$history = array_values( array_filter( $history, function( $entry ) use ( $name ) { return ( $entry['file'] ?? '' ) !== $name; } ) );
			array_unshift( $history, $row );
			$history = self::history_cap( $history );
		} );
		return $row;
	}
	/**
	 * Lo que la fila necesita del paquete abierto en la validación (upload_inspect()): su cadena (si es un punto
	 * incremental, como Digitalisimo_Backups_Chain_Restore::loose_item()), el SHA-256 de su manifiesto, la dirección de
	 * origen, la etiqueta de un sitio extraído, si está firmado con una clave de aquí y si es de este mismo sitio. Nunca
	 * lanza: lo que no se puede leer queda vacío.
	 */
	private static function uploaded_row_info( array $report, array $data, $relocation, array $problems ) {
		$info = array( 'chain' => null, 'manifest_sha256' => '', 'home' => '', 'extract' => '', 'site_key' => false, 'same_site' => false );
		try {
			$loose = Digitalisimo_Backups_Chain_Restore::loose_item( $report['zip'], 'x' );
			if ( is_array( $loose ) && is_array( $loose['chain'] ?? null ) ) $info['chain'] = $loose['chain'];
		} catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		try { $info['manifest_sha256'] = (string) Digitalisimo_Backups_Chain_Restore::manifest_sha( $report['zip'] ); } catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		$info['home'] = is_array( $relocation ) && '' !== (string) ( $relocation['origin']['home'] ?? '' ) ? (string) $relocation['origin']['home'] : (string) ( $data['home_url'] ?? '' );
		$info['extract'] = (string) ( $data['extract']['site']['label'] ?? '' );
		// Firmado con una clave que este sitio tiene en su llavero (no basta una frase escrita: la clave tiene que ser de
		// aquí). De este mismo sitio, además, sin nada que reubicar (misma dirección, ruta del servidor y prefijo).
		$key_id = (string) ( $report['key']['id'] ?? '' );
		$info['site_key'] = 'signed' === (string) $report['trust'] && '' !== $key_id && Digitalisimo_Backups_Keys::can_open( $key_id );
		$info['same_site'] = $info['site_key'] && ! $problems && is_array( $relocation ) && empty( $relocation['needed'] ) && empty( $relocation['problems'] );
		return $info;
	}
	/**
	 * Si el eslabón `$link` de la cadena del punto subido `$item` está en la lista de verdad: su fila tiene su archivo en
	 * el almacén y es exactamente ese eslabón (Digitalisimo_Backups_Chain::exact_link_row(): `backup_id`, SHA-256 de su
	 * manifiesto y posición en la cadena), no sólo un archivo con su nombre (M1 de la revisión). Vale también una fila
	 * hecha aquí: esa identidad no se adivina sin tener el respaldo, y así un incremental propio sin firma, descargado y
	 * vuelto a subir, se restaura otra vez sobre su base de aquí (N1 de la verificación). Un eslabón sin fila sólo cuenta
	 * para un punto firmado con una clave de este sitio (la restauración comprueba su manifiesto).
	 */
	private static function uploaded_link_ok( array $item, array $link, array $history ) {
		$named = null;
		foreach ( $history as $row ) if ( is_array( $row ) && (string) ( $row['file'] ?? '' ) === (string) $link['file'] ) { $named = $row; break; }
		if ( ! $named ) return ! self::uploaded_untrusted( $item ) && '' !== self::local_copy( array( 'file' => (string) $link['file'] ) );
		return '' !== self::local_copy( $named ) && null !== Digitalisimo_Backups_Chain::exact_link_row( $history, $link );
	}
	/** Fila del historial con este contenido (SHA-256 y tamaño) cuyo archivo sigue en el almacén, o null. */
	private static function history_same_content( $sha256, $size ) {
		foreach ( self::fresh_history() as $row ) {
			if ( ! is_array( $row ) || ! hash_equals( (string) $sha256, strtolower( (string) ( $row['sha256'] ?? '' ) ) ) || (int) ( $row['size'] ?? -1 ) !== (int) $size ) continue;
			$path = self::local_copy( $row );
			if ( '' !== $path && hash_equals( (string) $sha256, (string) @hash_file( 'sha256', $path ) ) ) return $row;
		}
		return null;
	}
	/** Lo público de una fila subida o agregada (REST, WP-CLI y la pantalla): nunca la cuenta ni rutas. */
	private static function uploaded_summary( $row ) {
		if ( ! self::is_uploaded_row( $row ) ) return null;
		$upload = (array) ( $row['upload'] ?? array() );
		return array(
			'origin' => (string) $row['origin'],
			'label' => self::is_added_row( $row ) ? 'Agregado' : 'Subido',
			'at' => (string) ( $upload['at'] ?? '' ),
			'via' => (string) ( $upload['via'] ?? '' ),
			'name' => (string) ( $upload['name'] ?? '' ),
			'home' => (string) ( $upload['home'] ?? '' ),
			'verified' => ! empty( $upload['verified'] ),
			'site_key' => ! empty( $upload['site_key'] ),
			'same_site' => ! empty( $upload['same_site'] ),
			'restorable_here' => self::uploaded_restorable_scope( (array) $row ),
		);
	}
	/**
	 * Por qué un respaldo subido (con su archivo en el almacén) no se restaura aquí: es de otro tipo de instalación (una
	 * red en un sitio individual o al revés; un sitio extraído en una red) o su cadena está incompleta. '' si se restaura.
	 */
	private static function uploaded_blocked_text( array $row ) {
		if ( ! self::uploaded_restorable_scope( $row ) ) {
			$scope = (string) ( $row['scope'] ?? '' );
			if ( Digitalisimo_Backups_Site_Extract::SCOPE === $scope ) return 'Es un sitio extraído de una red: se restaura en un WordPress individual, no en una red.';
			return 'network' === $scope ? 'Es el respaldo de una red y este WordPress es un sitio individual: restáuralo en una red del mismo tipo.' : 'Es el respaldo de un sitio individual y este WordPress es una red: restáuralo en una instalación individual.';
		}
		if ( Digitalisimo_Backups_Chain::is_incremental( $row ) ) {
			$missing = array_keys( self::chain_missing( $row ) );
			if ( $missing ) return 'Cadena incompleta: es un punto incremental y faltan en la lista ' . count( $missing ) . ' archivos de su cadena (' . implode( ', ', array_slice( $missing, 0, 3 ) ) . ( count( $missing ) > 3 ? '…' : '' ) . '). ' . ( self::uploaded_untrusted( $row ) ? 'Súbelos o agrégalos a la lista con ese mismo nombre (este punto no está firmado con una clave de este sitio: sólo cuenta el eslabón exacto que nombra su manifiesto, no otro archivo con su nombre)' : 'Súbelos con ese mismo nombre o tráelos de su destino' ) . ' y se podrá restaurar.';
		}
		return '';
	}
	/** Uno subido sin una firma válida de este sitio, con «Restaurar sólo respaldos firmados»: dice si se restaurará y cómo. */
	private static function uploaded_signed_only_text( array $row ) {
		if ( ! self::is_uploaded_row( $row ) || ! Digitalisimo_Backups_Security::settings()['require_signed'] ) return '';
		if ( 'dgbk' === (string) ( $row['container'] ?? '' ) ) return '';
		if ( 'signed' === (string) ( $row['upload']['signature'] ?? '' ) ) {
			if ( Digitalisimo_Backups_Keys::can_open( (string) ( $row['key_id'] ?? '' ) ) ) return '';
			// Firmado con una frase que se escribió al subirlo: con esa misma frase se comprueba su firma y se restaura.
			return 'Este sitio sólo restaura respaldos firmados: este respaldo está firmado con la clave ' . Digitalisimo_Backups_Keys::short( (string) ( $row['key_id'] ?? '' ) ) . ', que este sitio no tiene; escribe su frase en «Opciones» al restaurarlo.';
		}
		return 'Este sitio sólo restaura respaldos firmados (Ajustes → Cifrado y firma) y este respaldo no tiene una firma válida de una clave de este sitio: no se restaurará.';
	}
	/* ------------------------------------------------------------------ «Agregar al listado» */

	/** Archivos del almacén que «Restaurar un archivo suelto» lista y que se pueden pasar a la lista (o borrar a mano). */
	public static function loose_name( $file ) {
		return (bool) preg_match( '/^(?:digitalisimo-[A-Za-z0-9._-]+\.(?:zip|dgbk)|safety-before-restore-[A-Za-z0-9._-]+\.zip|' . preg_quote( Digitalisimo_Backups_Site_Extract::FILE_PREFIX, '/' ) . '[A-Za-z0-9._-]+\.(?:zip|dgbk)|' . preg_quote( Digitalisimo_Backups_Site_Import::FILE_PREFIX, '/' ) . '[A-Za-z0-9._-]+\.zip)$/', (string) $file );
	}
	/** El aviso de «Agregar al listado»: el origen de un archivo suelto no se conoce. */
	public static function register_warning() {
		return 'No sabemos quién puso este archivo en el almacén: agrégalo sólo si sabes de dónde viene. Restaurarlo instala su código y sus datos.';
	}
	/**
	 * «Agregar al listado»: un archivo «sin registro» que ya está en el almacén (subido con 1.0.77 o antes, traído de un
	 * destino, importado de Backuply o copiado a mano) pasa a la lista con su fila «Agregado» (`origin` = `added`), después
	 * de la misma validación completa de archivos no confiables que la subida (upload_inspect()).
	 * - Su origen no se conoce (M2 de la revisión): sin una firma válida de una clave de este sitio pide escribir
	 *   AGREGAR SIN FIRMA (`confirm`), que sustituye a la frase de sin firma de la restauración; firmado con una clave de
	 *   aquí basta AGREGAR.
	 * - En una red escribe en el historial de la red: pide el alcance de red (`network`), como traer, enviar o restaurar.
	 * - Atado a los bytes validados: el SHA-256 se calcula antes y después de la validación y tienen que coincidir.
	 * - Un `.dgbk` con una clave que este sitio no tiene necesita su frase (`secret`): sin ella no se sabe su tipo ni su
	 *   cadena y no se agrega.
	 * Si no pasa la validación, el archivo sigue en el almacén como estaba (nunca se borra desde aquí).
	 */
	public static function service_register( $file, array $args = array() ) {
		$allowed = self::service_scope( ! empty( $args['network'] ), true );
		if ( is_wp_error( $allowed ) ) return $allowed;
		$confirm = strtoupper( trim( preg_replace( '/\s+/', ' ', (string) ( $args['confirm'] ?? '' ) ) ) );
		if ( ! in_array( $confirm, array( 'AGREGAR', 'AGREGAR SIN FIRMA' ), true ) ) return self::service_error( 'register_confirm', self::register_warning() . ' Escribe AGREGAR SIN FIRMA para agregarlo a la lista.', 400 );
		$name = sanitize_file_name( (string) $file );
		if ( ! self::loose_name( $name ) ) return self::service_error( 'register_name', 'Ese archivo no es un respaldo del almacén.', 400 );
		$path = self::service_local_file( $name );
		if ( is_wp_error( $path ) ) return $path;
		if ( self::history_item( $name ) ) return self::service_error( 'register_listed', 'Ese respaldo ya está en la lista de respaldos.', 409 );
		if ( ! class_exists( 'ZipArchive' ) ) return self::service_error( 'zip', 'El servidor necesita la extensión PHP ZipArchive para comprobar el respaldo.', 500 );
		$secret = is_string( $args['secret'] ?? null ) ? $args['secret'] : '';
		// Descifrar un .dgbk usa la carpeta .staging/ del almacén, que una restauración también usa: con el bloqueo.
		$lock = self::service_lock();
		if ( is_wp_error( $lock ) ) return $lock;
		if ( function_exists( 'ignore_user_abort' ) ) ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );
		try {
			$checksum = @hash_file( 'sha256', $path );
			if ( false === $checksum ) return self::service_error( 'storage', 'No se pudo leer el respaldo en el almacén privado.', 500 );
			$report = self::upload_inspect( $path, $secret );
			if ( is_wp_error( $report ) ) {
				$code = substr( $report->get_error_code(), strlen( 'digitalisimo_backups_' ) );
				$code = 'upload_invalid' === $code ? 'register_invalid' : ( 'upload_checksum' === $code ? 'register_invalid' : $code );
				return self::service_error( $code, self::register_text( $report->get_error_message() ), (int) ( $report->get_error_data()['status'] ?? 422 ) );
			}
			if ( ! empty( $report['needs_secret'] ) ) return self::service_error( 'register_secret', 'Está cifrado con la clave ' . (string) $report['key'] . ', que este sitio no tiene: da su frase para agregarlo a la lista (sin ella no se sabe si es un respaldo completo o un punto incremental).', 400, array( 'key' => (string) $report['key'] ) );
			$site_key = ! empty( $report['_row']['site_key'] );
			if ( ! $site_key && 'AGREGAR SIN FIRMA' !== $confirm ) return self::service_error( 'register_confirm', 'Este archivo no tiene una firma válida de una clave de este sitio. ' . self::register_warning() . ' Escribe AGREGAR SIN FIRMA para agregarlo a la lista.', 400 );
			// Los bytes validados son los que quedan atados a la fila: el SHA-256 de después tiene que ser el de antes.
			if ( ! hash_equals( $checksum, (string) @hash_file( 'sha256', $path ) ) ) return self::service_error( 'register_changed', 'El archivo cambió en el almacén mientras se comprobaba: no se agregó a la lista. Vuelve a intentarlo.', 409 );
			$via = (string) ( $args['via'] ?? ( defined( 'WP_CLI' ) && WP_CLI ? 'cli' : 'rest' ) );
			$info = $report + array( 'via' => $via, 'name' => $name, 'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0, 'origin' => 'added' );
			$placed = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $name, $path, $checksum, $info ) {
				if ( self::history_item( $name ) ) return self::service_error( 'register_listed', 'Ese respaldo ya está en la lista de respaldos.', 409 );
				$same = self::history_same_content( $checksum, (int) @filesize( $path ) );
				if ( $same ) return self::service_error( 'register_duplicate', 'Ese mismo respaldo (mismo SHA-256) ya está en la lista como ' . (string) $same['file'] . ': este archivo es una copia; bórralo si no lo necesitas.', 409, array( 'file' => (string) $same['file'] ) );
				return self::remember_uploaded( $path, $checksum, $info );
			} );
			if ( is_wp_error( $placed ) ) return $placed;
			$public = self::upload_public_result( $report );
			$vouched = 'untrusted' === ( $public['trust'] ?? 'untrusted' );
			return array( 'file' => $name, 'size' => (int) $placed['size'], 'registered' => true, 'listed' => true, 'duplicate' => false, 'date' => (string) $placed['date'] ) + array_merge( $public, array(
				'confirmation' => Digitalisimo_Backups_Security::confirmation_word( array( 'trust' => (string) ( $public['trust'] ?? 'untrusted' ), 'vouched' => $vouched ? 'uploaded' : '' ) ),
				'vouched' => $vouched ? 'uploaded' : '',
				'origin' => 'added',
				'restorable' => self::restorable_item( $placed ),
				'uploaded' => self::uploaded_summary( $placed ),
			) );
		} catch ( Throwable $e ) {
			return self::service_error( 'storage', 'No se pudo agregar el respaldo a la lista: ' . $e->getMessage(), 500 );
		} finally {
			self::release_operation_lock( $lock );
		}
	}
	/** Los textos de la validación de la subida, dichos de un archivo que ya está en el almacén (nunca se descarta). */
	private static function register_text( $message ) {
		return strtr( (string) $message, array(
			' y se descartó' => ' y no se agregó a la lista (el archivo sigue en el almacén)',
			'; la subida se conserva para reintentar' => '',
			' La subida se conserva: libera espacio y vuelve a terminarla, o descártala.' => ' Libera espacio y vuelve a intentarlo.',
			' La subida se conserva: vuelve a terminarla con la frase correcta o sin frase.' => ' Vuelve a intentarlo con la frase correcta o sin frase.',
			'la subida se conserva.' => 'no se agregó a la lista.',
		) );
	}
	/** «Agregar al listado» de «Restaurar un archivo suelto» (admin-post: capability, nonce propio y sitio principal). */
	public static function register_loose_request() {
		if ( ! self::can_manage() ) wp_die( 'No autorizado.' );
		check_admin_referer( self::ACTION_REGISTER_LOOSE );
		if ( is_multisite() && ! is_main_site() ) self::redirect( false, 'Los archivos del almacén se gestionan desde el sitio principal o la administración de red.', null, 'tools' );
		$secret = isset( $_POST['backup_secret'] ) && is_string( $_POST['backup_secret'] ) ? (string) wp_unslash( $_POST['backup_secret'] ) : '';
		// En una red, esta pantalla es la de la red: el alcance de red va con el formulario, como «Restaurar».
		$result = self::service_register( wp_unslash( (string) ( $_POST['file'] ?? '' ) ), array( 'secret' => $secret, 'confirm' => wp_unslash( (string) ( $_POST['confirmation'] ?? '' ) ), 'network' => is_multisite(), 'via' => 'admin-post' ) );
		if ( is_wp_error( $result ) ) self::redirect( false, 'No se agregó a la lista: ' . $result->get_error_message() );
		self::redirect( true, 'Agregado a la lista de respaldos («Agregado»): ' . $result['file'] . '. Para restaurarlo escribe ' . $result['confirmation'] . '.' . ( $result['problems'] ? ' ' . implode( ' ', array_map( 'strval', (array) $result['problems'] ) ) : '' ), null, 'backups' );
	}
	/** «Subido el … desde …» (o «Agregado a mano el …») para la lista y los detalles. */
	private static function uploaded_text( $row ) {
		$upload = (array) ( ( (array) $row )['upload'] ?? array() );
		$at = self::short_date( (string) ( $upload['at'] ?? '' ) );
		$home = (string) ( $upload['home'] ?? '' );
		$text = ( self::is_added_row( $row ) ? 'Agregado a mano desde el almacén' : 'Subido' ) . ( '' !== $at ? ' el ' . $at : '' );
		if ( ! empty( $upload['same_site'] ) ) $text .= ' · de este mismo sitio (firmado con su clave)';
		elseif ( '' !== $home ) $text .= ' · de ' . $home;
		if ( self::is_added_row( $row ) && empty( $upload['site_key'] ) ) $text .= ' · origen desconocido, sin firma de este sitio';
		return $text;
	}
}
