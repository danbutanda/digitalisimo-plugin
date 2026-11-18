<?php
defined( 'ABSPATH' ) || exit;

/**
 * Importar un conjunto de UpdraftPlus (brecha #7 del estudio de UpdraftPlus 1.26.14): sus piezas se convierten, por tramos
 * del motor de trabajos y como archivos no confiables, en un ZIP de Backups (manifiesto v3 sin firma, `database.sql` y
 * `wordpress/…`) que entra en la lista de respaldos como «Importado» y se restaura con la restauración por pasos: la
 * verificación, la copia previa y la vuelta atrás, la reubicación de E1, la configuración de este sitio (H23), el acceso
 * de quien restaura, por partes y con el panel de avance. Ver «Importar de UpdraftPlus» en docs/BACKUPS.md.
 *
 * Formato de UpdraftPlus (leído en su código 1.26.14, `backup.php`, `class-updraftplus.php`, `restorer.php` e
 * `includes/class-updraftplus-encryption.php`):
 * - **Nombres.** `backup_<AAAA-MM-DD-HHMM>_<sitio>_<nonce de 12 hexadecimales>-<entidad><n>.<ext>`: `db.gz` (o
 *   `db.gz.crypt`), `plugins.zip`, `themes.zip`, `uploads.zip`, `mu-plugins.zip` y `others.zip`; partida a los 400 MB
 *   (`-uploads2.zip`, `-uploads3.zip`…). Con Premium, además `wpcore`, `more` y bases externas (`db1.gz`…), que no se
 *   importan (se avisa).
 * - **ZIP de cada entidad.** `plugins/…`, `themes/…`, `mu-plugins/…` y `uploads/…` (la carpeta del origen con su nombre,
 *   como la busca su `restorer.php` con get_first_directory()); `others` lleva el resto de wp-content sin carpeta
 *   delante (`languages/…`, drop-ins) y, en versiones anteriores, también `mu-plugins/…`.
 * - **Base de datos.** gzip de varios miembros (la cabecera y cada tabla se escriben aparte y se concatenan) con un SQL
 *   que empieza por comentarios `#`: «Created by UpdraftPlus version», «WordPress Version: …, running on PHP … MySQL …»,
 *   «Backup of», «Home URL», «Content URL», «Uploads URL», «Table prefix», «ABSPATH» y «Site info: multisite=…» con, en una
 *   red, `multisite_data` (dominio, ruta, siteurl y home de cada sitio). Después `/*!40101 SET … *\/`, por tabla
 *   `DROP TABLE IF EXISTS`, el `CREATE TABLE` de `SHOW CREATE TABLE` seguido de « ;» e INSERT de varias filas (enteros sin
 *   comillas, binarios `0x…`, BIT `b'…'`, cadenas con \\ \' \0 \n \r \Z); las tablas grandes pueden venir de mysqldump;
 *   vistas, y disparadores y rutinas entre `DELIMITER ;;`. El traductor de Backuply lo entiende (mismo dialecto).
 * - **Cifrado.** Sólo la base de datos (`.gz.crypt`): Rijndael con bloque de 128 bits en CBC, vector cero, la frase como
 *   clave (completada con ceros a 16, 20, 24, 28 o 32 bytes) y sin relleno salvo el del último trozo. Sin la frase no se
 *   puede descifrar: se pide al importar y nunca se guarda.
 *
 * UpdraftPlus (sin Premium) no respalda el núcleo de WordPress y, al restaurar, deja aquí lo que el conjunto no trae. El
 * ZIP convertido lleva por eso el núcleo de este WordPress (wp-admin, wp-includes y los archivos PHP de la raíz salvo
 * wp-config.php) y, si al conjunto le falta una parte gestionada (plugins, temas, medios o mu-plugins), la de este sitio:
 * así la restauración por pasos, que deja cada parte como en el respaldo, la deja como está. Se avisa.
 *
 * Nada del conjunto se ejecuta ni se deserializa; el SQL se reescribe al formato del exportador y pasa por el guardián.
 */
final class Digitalisimo_Backups_Updraft_Import {
	const FORMAT = 'updraft';
	const PACKER = 'digitalisimo-updraft-import/1';
	/** Carpeta del almacén privado para las piezas subidas o traídas de un destino. */
	const STORE_DIRECTORY = 'updraft';
	/** Un conjunto: el nombre que comparten sus piezas. */
	const SET = '/^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{4}_[A-Za-z0-9_.-]{1,64}?_[0-9a-f]{12}$/D';
	/** Una pieza: conjunto, entidad, número de parte, extensión y si está cifrada. */
	const PIECE = '/^(backup_[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{4}_[A-Za-z0-9_.-]{1,64}?_[0-9a-f]{12})-(db|plugins|themes|uploads|mu-plugins|others|wpcore|more)([0-9]{0,3})\.(zip|gz)(\.crypt)?$/D';
	/** Entidades de archivos que se importan, en el orden en que van al ZIP. */
	const ENTITIES = array( 'plugins', 'themes', 'mu-plugins', 'uploads', 'others' );
	const MAX_HEADER = 65536;
	/** Una pieza cambiada hace menos que esto puede estar escribiéndola UpdraftPlus. */
	const SETTLE = 60;
	const CONFIG_PLACEHOLDER = "<?php\n// DIGITALÍSIMO Backups: los respaldos de UpdraftPlus no traen wp-config.php.\n// Marcador del ZIP convertido: la restauración nunca escribe wp-config.php.\n";
	/** Trozo del descifrado (el de UpdraftPlus: 2 MiB, múltiplo de 16). */
	const CRYPT_CHUNK = 2097152;
	/** Código del error de decrypt() cuando el descifrado no cabe en el tope de una petición web (revisión bk-180, H6). */
	const SLOW = 408;
	const SPACE_EVERY = 16777216;

	/* ------------------------------------------------------------------ Detección y orígenes */

	/** UpdraftPlus en este sitio: su plugin, si está activo y si su carpeta de respaldos existe. */
	public static function detected() {
		$plugin = defined( 'WP_PLUGIN_DIR' ) && is_file( WP_PLUGIN_DIR . '/updraftplus/updraftplus.php' );
		$active = false;
		if ( function_exists( 'get_option' ) ) {
			$active = in_array( 'updraftplus/updraftplus.php', (array) get_option( 'active_plugins', array() ), true );
			if ( ! $active && function_exists( 'is_multisite' ) && is_multisite() ) $active = isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )['updraftplus/updraftplus.php'] );
		}
		return array( 'installed' => $plugin, 'active' => $active, 'folder' => '' !== self::updraft_directory() );
	}
	/**
	 * Carpeta de respaldos de UpdraftPlus en este sitio: su opción `updraft_dir` (relativa a wp-content si no es absoluta;
	 * por defecto `updraft`). Sólo una carpeta real dentro de wp-content, sin enlaces; '' si no la hay.
	 */
	public static function updraft_directory() {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) return '';
		$dir = 'updraft';
		if ( function_exists( 'get_option' ) ) {
			$option = function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ? get_site_option( 'updraft_dir', '' ) : get_option( 'updraft_dir', '' );
			if ( is_string( $option ) && '' !== trim( $option ) && false === strpos( $option, "\0" ) ) $dir = trim( $option );
		}
		$path = ( '/' === $dir[0] || preg_match( '/^[A-Za-z]:[\\\\\/]/', $dir ) ) ? $dir : WP_CONTENT_DIR . '/' . $dir;
		$path = rtrim( $path, '/\\' );
		if ( is_link( $path ) || ! is_dir( $path ) ) return '';
		$real = realpath( $path );
		$content = realpath( WP_CONTENT_DIR );
		if ( false === $real || false === $content || 0 !== strpos( $real, rtrim( $content, '/\\' ) . DIRECTORY_SEPARATOR ) ) return '';
		return $real;
	}
	public static function store_directory( $create = false ) {
		$dir = rtrim( Digitalisimo_Backups::storage_directory(), '/\\' ) . '/' . self::STORE_DIRECTORY;
		if ( is_link( $dir ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta de piezas de UpdraftPlus del almacén no puede ser un enlace simbólico.' );
		if ( $create && ! is_dir( $dir ) ) {
			if ( ! @mkdir( $dir, 0700, true ) && ! is_dir( $dir ) ) throw new Exception( 'No se pudo crear la carpeta de piezas de UpdraftPlus en el almacén privado.' );
			@chmod( $dir, 0700 );
		}
		return $dir;
	}
	/** Carpeta de un origen: `updraft` (la de UpdraftPlus de este sitio) o `store` (el almacén privado). '' si no existe. */
	public static function origin_directory( $origin ) {
		if ( 'updraft' === $origin ) return self::updraft_directory();
		if ( 'store' !== $origin ) return '';
		try {
			$dir = self::store_directory();
		} catch ( Exception $e ) {
			return '';
		}
		return is_dir( $dir ) ? $dir : '';
	}
	/** Una pieza por su nombre: conjunto, entidad, parte (1 la primera), extensión y si está cifrada; null si no lo es. */
	public static function piece( $name ) {
		if ( ! preg_match( self::PIECE, (string) $name, $m ) ) return null;
		$part = '' === $m[3] ? 1 : (int) $m[3];
		return array( 'set' => $m[1], 'entity' => $m[2], 'number' => $m[3], 'part' => $part, 'ext' => $m[4], 'crypt' => ! empty( $m[5] ) );
	}
	/** Las piezas de una carpeta, agrupadas por conjunto: id => nombre => piece() + size y mtime. Sin enlaces ni rutas. */
	private static function scan_directory( $dir ) {
		$sets = array();
		if ( '' === $dir ) return $sets;
		foreach ( (array) glob( rtrim( $dir, '/\\' ) . '/backup_*' ) as $path ) {
			$name = basename( (string) $path );
			$piece = self::piece( $name );
			if ( ! $piece || is_link( $path ) || ! is_file( $path ) ) continue;
			$sets[ $piece['set'] ][ $name ] = $piece + array( 'size' => (int) @filesize( $path ), 'mtime' => (int) @filemtime( $path ) );
		}
		ksort( $sets );
		return $sets;
	}
	/** Las piezas de un conjunto en un origen (nombre => datos), o array() si no está. */
	public static function set_pieces( $origin, $id ) {
		if ( ! preg_match( self::SET, (string) $id ) ) return array();
		$sets = self::scan_directory( self::origin_directory( $origin ) );
		return (array) ( $sets[ $id ] ?? array() );
	}
	/**
	 * Conjuntos que se pueden importar, de la carpeta de UpdraftPlus de este sitio (`updraft`) y del almacén (`store`:
	 * subidos o traídos de un destino), del más reciente al más antiguo. Sin rutas del servidor.
	 */
	public static function sources() {
		self::clean_partials();
		$items = array();
		foreach ( array( 'updraft', 'store' ) as $origin ) foreach ( self::scan_directory( self::origin_directory( $origin ) ) as $id => $pieces ) $items[] = self::describe( $origin, $id, $pieces );
		usort( $items, function( $a, $b ) { return array( $b['created'], $a['source'] ) <=> array( $a['created'], $b['source'] ); } );
		return $items;
	}
	/** Lo que la pantalla, REST y WP-CLI dicen de un conjunto, y si se puede importar (y por qué no). */
	public static function describe( $origin, $id, array $pieces ) {
		$plan = self::plan( $pieces );
		$created = preg_match( '/^backup_([0-9]{4})-([0-9]{2})-([0-9]{2})-([0-9]{2})([0-9]{2})_/', $id, $m ) ? (int) gmmktime( (int) $m[4], (int) $m[5], 0, (int) $m[2], (int) $m[3], (int) $m[1] ) : 0;
		$site = preg_match( '/^backup_[0-9-]{15}_(.+)_[0-9a-f]{12}$/', $id, $s ) ? $s[1] : '';
		$size = 0;
		$latest = 0;
		foreach ( $pieces as $piece ) { $size += (int) $piece['size']; $latest = max( $latest, (int) $piece['mtime'] ); }
		$entities = array();
		foreach ( $plan['entities'] as $entity => $names ) $entities[ $entity ] = count( $names );
		// Sólo en la carpeta de UpdraftPlus puede estar escribiéndolo él (una subida o una descarga ya terminaron).
		$busy = 'updraft' === $origin && $latest > time() - self::SETTLE;
		return array(
			'source' => $origin,
			'id' => $id,
			'site' => $site,
			'created' => $created,
			'date' => $created ? gmdate( 'Y-m-d H:i', $created ) : '',
			'size' => $size,
			'pieces' => array_keys( $pieces ),
			'entities' => $entities,
			'database' => '' !== $plan['db'],
			'encrypted' => $plan['encrypted'],
			'ignored' => $plan['ignored'],
			'importable' => '' === $plan['problem'] && ! $busy,
			'problem' => '' !== $plan['problem'] ? $plan['problem'] : ( $busy ? 'UpdraftPlus todavía está escribiendo este conjunto (una pieza cambió hace menos de un minuto): espera a que termine.' : '' ),
			'imported' => self::converted_name( $id ),
		);
	}
	/**
	 * Qué se importa de un conjunto: la base de datos (`db`, la pieza sin cifrar si están las dos), las piezas de cada
	 * entidad en orden, las que se ignoran y lo que impide importarlo (`problem`, '' si nada).
	 */
	public static function plan( array $pieces ) {
		$plan = array( 'db' => '', 'encrypted' => false, 'entities' => array(), 'ignored' => array(), 'problem' => '' );
		$parts = array();
		foreach ( $pieces as $name => $piece ) {
			if ( 'db' === $piece['entity'] && '' === $piece['number'] && 'gz' === $piece['ext'] ) {
				if ( '' === $plan['db'] || ( $plan['encrypted'] && ! $piece['crypt'] ) ) { $plan['db'] = $name; $plan['encrypted'] = $piece['crypt']; }
				continue;
			}
			if ( in_array( $piece['entity'], self::ENTITIES, true ) && 'zip' === $piece['ext'] ) {
				if ( $piece['crypt'] ) { $plan['problem'] = 'La pieza ' . $name . ' está cifrada: UpdraftPlus sólo cifra la base de datos y Backups no sabe leer un ZIP cifrado.'; continue; }
				$parts[ $piece['entity'] ][ $piece['part'] ][] = $name;
				continue;
			}
			$plan['ignored'][] = $name;
		}
		foreach ( self::ENTITIES as $entity ) {
			if ( empty( $parts[ $entity ] ) ) continue;
			ksort( $parts[ $entity ] );
			foreach ( $parts[ $entity ] as $number => $names ) {
				if ( count( $names ) > 1 ) $plan['problem'] = 'El conjunto trae dos piezas para la parte ' . $number . ' de ' . $entity . ' (' . implode( ', ', $names ) . ').';
				$plan['entities'][ $entity ][] = $names[0];
			}
		}
		if ( '' === $plan['problem'] && '' === $plan['db'] ) $plan['problem'] = 'El conjunto no trae la base de datos (la pieza …-db.gz): Backups sólo importa conjuntos con base de datos. Si es de otro servidor, súbela también.';
		return $plan;
	}
	/** Nombre del ZIP convertido de un conjunto (sin puntos: sanitize_file_name() los cambiaría). */
	public static function target_name( $id ) {
		return 'digitalisimo-updraft-' . trim( (string) preg_replace( '/[^A-Za-z0-9_-]+/', '-', substr( (string) $id, 7 ) ), '-_' ) . '.zip';
	}
	/** Nombre del ZIP convertido de un conjunto si ya está en el almacén ('' si no). */
	public static function converted_name( $id ) {
		$file = self::target_name( $id );
		try {
			return is_file( trailingslashit( Digitalisimo_Backups::storage_directory() ) . $file ) ? $file : '';
		} catch ( Exception $e ) {
			return '';
		}
	}
	/**
	 * Nombre de una pieza que llega por una subida, sin sanitize_file_name() (que añadiría «_» a `.gz` en una red sin `gz`
	 * en upload_filetypes): tal cual si es el de una pieza de UpdraftPlus; '' si no.
	 */
	public static function upload_name( $raw ) {
		$raw = (string) $raw;
		if ( '' === $raw || strlen( $raw ) > 200 || false !== strpbrk( $raw, "/\\\0" ) ) return '';
		return self::piece( $raw ) ? $raw : '';
	}
	/** Retira las descargas cortadas y los descifrados que quedaron de importaciones que murieron (más de 6 horas). */
	public static function clean_partials() {
		try {
			$dir = self::store_directory();
		} catch ( Exception $e ) {
			return;
		}
		if ( ! is_dir( $dir ) ) return;
		$active = array();
		try {
			foreach ( Digitalisimo_Backups_Jobs::active() as $job ) if ( '' !== (string) ( $job['spec']['source']['decrypted'] ?? '' ) ) $active[ (string) $job['spec']['source']['decrypted'] ] = true;
		} catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		foreach ( array_merge( (array) glob( $dir . '/.incoming-*.part' ), (array) glob( $dir . '/.decrypted-*.gz' ) ) as $path ) {
			if ( isset( $active[ basename( (string) $path ) ] ) ) continue;
			if ( is_file( $path ) && ! is_link( $path ) && time() - (int) @filemtime( $path ) > 21600 ) @unlink( $path );
		}
	}

	/* ------------------------------------------------------------------ Cifrado de la base de datos */

	/**
	 * Descifra la base de datos `.gz.crypt` de UpdraftPlus a `$to` con la frase `$phrase`, como su
	 * UpdraftPlus_Encryption::decrypt(): Rijndael de 128 bits de bloque en CBC con vector cero, clave de 128, 160, 192,
	 * 224 o 256 bits según la longitud de la frase (completada con ceros, o cortada a 32 bytes), sin relleno y por trozos
	 * de 2 MiB en un flujo continuo. Lo que sobra al final (el relleno del último trozo) lo ignora la descompresión. Lanza
	 * si la frase no es la del conjunto (lo descifrado no empieza como un gzip).
	 *
	 * Revisión bk-180 (H6): se descifra en la petición que encola, porque la frase no puede ir al trabajo. Con 128, 192 o
	 * 256 bits phpseclib usa OpenSSL (cientos de MB/s); con una frase de 17 a 20 o de 25 a 28 caracteres la clave es de
	 * 160 o 224 bits, que OpenSSL no admite, y el Rijndael de PHP va a unos 5 MB/s: una base de 500 MB tardaría unos
	 * 100 s, más que un proxy (Cloudflare, 100 s) o PHP-FPM, que cortarían la petición con un 504 y dejarían la copia a
	 * medias. Con `$seconds` > 0 (una petición web) el descifrado se mide desde el primer trozo: si lo que falta no cabe en
	 * ese tope, o se llega a él, se para, borra lo descifrado y lanza Digitalisimo_Backups_Permanent_Error con el código
	 * SLOW y un mensaje que pide WP-CLI, que no tiene ese límite. 0: sin tope.
	 */
	public static function decrypt( $from, $phrase, $to, $seconds = 0 ) {
		$phrase = (string) $phrase;
		if ( '' === $phrase ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos de este conjunto está cifrada: escribe la frase de cifrado de UpdraftPlus del sitio de origen.' );
		require_once dirname( __DIR__ ) . '/lib/autoload.php';
		$length = strlen( $phrase );
		$bits = $length <= 16 ? 128 : ( $length <= 20 ? 160 : ( $length <= 24 ? 192 : ( $length <= 28 ? 224 : 256 ) ) );
		$key = str_pad( substr( $phrase, 0, $bits >> 3 ), $bits >> 3, "\0" );
		$cipher = new \DigitalisimoBackups\Vendor\phpseclib3\Crypt\Rijndael( 'cbc' );
		$cipher->setBlockLength( 128 );
		$cipher->setKeyLength( $bits );
		$cipher->setKey( $key );
		$cipher->setIV( str_repeat( "\0", 16 ) );
		$cipher->disablePadding();
		$cipher->enableContinuousBuffer();
		$in = @fopen( $from, 'rb' );
		$out = @fopen( $to, 'xb' );
		if ( ! $in || ! $out ) {
			if ( $in ) fclose( $in );
			if ( $out ) fclose( $out );
			throw new Exception( 'No se pudo preparar el descifrado de la base de datos de UpdraftPlus.' );
		}
		@chmod( $to, 0600 );
		$total = (int) @filesize( $from );
		$started = microtime( true );
		$read = 0;
		try {
			$first = true;
			while ( ! feof( $in ) ) {
				$part = fread( $in, self::CRYPT_CHUNK );
				if ( false === $part ) throw new Exception( 'No se pudo leer la base de datos cifrada de UpdraftPlus.' );
				if ( '' === $part ) break;
				$read += strlen( $part );
				if ( strlen( $part ) % 16 ) $part = str_pad( $part, strlen( $part ) + 16 - strlen( $part ) % 16, "\0" );
				$plain = $cipher->decrypt( $part );
				if ( $first && "\x1f\x8b" !== substr( (string) $plain, 0, 2 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La frase no descifra la base de datos de este conjunto: no es la frase de cifrado de UpdraftPlus del sitio de origen.' );
				$first = false;
				if ( strlen( (string) $plain ) !== fwrite( $out, (string) $plain ) ) throw new Exception( 'No se pudo escribir la base de datos descifrada; revisa el espacio libre del almacén privado.' );
				if ( $seconds > 0 && $read < $total && self::too_slow( microtime( true ) - $started, $read, $total, $seconds ) ) throw new Digitalisimo_Backups_Permanent_Error( self::slow_text( $bits, $total, ( microtime( true ) - $started ) * $total / max( 1, $read ) ), self::SLOW );
			}
			if ( $first ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos cifrada de este conjunto está vacía.' );
			fflush( $out );
		} catch ( Throwable $e ) {
			fclose( $in );
			fclose( $out );
			@unlink( $to );
			throw $e;
		}
		fclose( $in );
		fclose( $out );
	}
	/**
	 * Tope del descifrado en una petición web, en segundos: 40 (por debajo de los 60 s de nginx y de los 100 s de
	 * Cloudflare), o menos si PHP sigue con un max_execution_time que no se pudo quitar; 0 (sin tope) en PHP CLI (WP-CLI).
	 * Filtro `digitalisimo_backups_updraft_decrypt_seconds`.
	 */
	public static function decrypt_seconds() {
		$seconds = 'cli' === PHP_SAPI ? 0.0 : 40.0;
		$limit = (int) @ini_get( 'max_execution_time' );
		if ( $seconds > 0 && $limit > 0 ) $seconds = min( $seconds, max( 5.0, $limit - 10.0 ) );
		return max( 0.0, (float) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_updraft_decrypt_seconds', $seconds ) : $seconds ) );
	}
	/**
	 * Si el descifrado se para: al ritmo de lo hecho, el total no cabe en el tope. Se decide tras cada trozo y no espera a
	 * llegar al tope (si se llegó con algo por leer, la proyección ya lo pasa).
	 */
	public static function too_slow( $elapsed, $read, $total, $seconds ) {
		return $elapsed * $total / max( 1, $read ) > $seconds;
	}
	private static function slow_text( $bits, $total, $projected ) {
		$why = in_array( (int) $bits, array( 160, 224 ), true ) ? ' Su frase (de 17 a 20 o de 25 a 28 caracteres) da una clave de ' . (int) $bits . ' bits, que OpenSSL no admite: se descifra en PHP, decenas de veces más despacio.' : '';
		return 'Descifrar la base de datos de este conjunto (' . Digitalisimo_Backups_Archive_Inspector::megabytes( $total ) . ') tardaría unos ' . max( 1, (int) ceil( $projected ) ) . ' s en este servidor, más de lo que espera una petición web.' . $why . ' No se importó nada: impórtalo con WP-CLI, que no tiene ese límite (wp digitalisimo-backups updraft import <conjunto> --secret-file=<archivo con la frase>).';
	}

	/* ------------------------------------------------------------------ Trabajo */

	/** Especificación de una importación (`kind = import`, `format = updraft`). `$decrypted`: la base descifrada, en el almacén. */
	public static function spec( $origin, $id, array $args = array(), $decrypted = '' ) {
		$pieces = self::set_pieces( $origin, $id );
		if ( ! $pieces ) throw new Digitalisimo_Backups_Permanent_Error( 'El conjunto de UpdraftPlus solicitado no está disponible.' );
		$plan = self::plan( $pieces );
		if ( '' !== $plan['problem'] ) throw new Digitalisimo_Backups_Permanent_Error( $plan['problem'] );
		$list = array();
		$size = 0;
		foreach ( $pieces as $name => $piece ) {
			if ( in_array( $name, $plan['ignored'], true ) ) continue;
			if ( 'db' === $piece['entity'] && $name !== $plan['db'] ) continue;
			$list[ $name ] = array( 'size' => (int) $piece['size'], 'mtime' => (int) $piece['mtime'] );
			$size += (int) $piece['size'];
		}
		return array(
			'type' => 'import',
			'format' => self::FORMAT,
			'source' => array( 'origin' => (string) $origin, 'name' => (string) $id, 'set' => (string) $id, 'size' => $size, 'pieces' => $list, 'db' => $plan['db'], 'entities' => $plan['entities'], 'ignored' => $plan['ignored'], 'encrypted' => $plan['encrypted'], 'decrypted' => (string) $decrypted ),
			'policy' => 'import:updraft:' . $origin . ':' . $id,
			'destination' => 'local',
			'file' => self::target_name( $id ),
			'scope' => is_multisite() ? 'network' : 'installation',
			'origin' => array(
				'via' => (string) ( $args['via'] ?? 'admin-post' ),
				'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'blog_id' => (int) get_current_blog_id(),
				'context' => ! empty( $args['network'] ) ? 'network' : 'site',
			),
			'engine' => array( 'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '', 'job_format' => Digitalisimo_Backups_Job_Store::JOB_FORMAT ),
		);
	}
	/** Ruta de una pieza del trabajo, comprobando que sigue siendo la misma (tamaño y fecha) que se pidió importar. */
	private static function piece_path( array $job, $name ) {
		$source = (array) $job['spec']['source'];
		$expected = (array) ( $source['pieces'][ $name ] ?? array() );
		$dir = self::origin_directory( (string) ( $source['origin'] ?? '' ) );
		$path = '' === $dir || ! self::piece( $name ) ? '' : rtrim( $dir, '/\\' ) . '/' . $name;
		if ( '' === $path || ! $expected || is_link( $path ) || ! is_file( $path ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La pieza ' . $name . ' del conjunto de UpdraftPlus ya no está en su carpeta.' );
		clearstatcache( true, $path );
		if ( (int) filesize( $path ) !== (int) $expected['size'] || (int) filemtime( $path ) !== (int) $expected['mtime'] ) throw new Digitalisimo_Backups_Permanent_Error( 'La pieza ' . $name . ' cambió durante la importación (otro proceso la está escribiendo). Espera a que UpdraftPlus termine y vuelve a importar el conjunto.' );
		return $path;
	}
	/** La base de datos comprimida que se descomprime: la pieza `db.gz` o, si estaba cifrada, su copia descifrada. */
	private static function database_source( array $job ) {
		$source = (array) $job['spec']['source'];
		if ( empty( $source['encrypted'] ) ) return self::piece_path( $job, (string) $source['db'] );
		self::piece_path( $job, (string) $source['db'] );
		$name = (string) ( $source['decrypted'] ?? '' );
		$path = preg_match( '/^\.decrypted-[0-9a-f]{16}\.gz$/D', $name ) ? self::store_directory() . '/' . $name : '';
		if ( '' === $path || is_link( $path ) || ! is_file( $path ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Falta la copia descifrada de la base de datos de UpdraftPlus: vuelve a importar el conjunto con su frase.' );
		return $path;
	}
	private static function paths( array $job ) {
		$staging = Digitalisimo_Backups_Job_Store::staging( $job['id'] );
		return array( 'sql_in' => $staging . '/source.sql', 'index' => $staging . '/index.jsonl', 'sql' => $staging . '/database.sql', 'zip' => $staging . '/converted.zip', 'entries' => $staging . '/entries.jsonl', 'note' => $staging . '/publish.json' );
	}
	private static function deadline( array $job, $phase ) {
		$cursor = (array) ( $job['checkpoint'][ $phase ] ?? array() );
		return Digitalisimo_Backups_Step_Budget::deadline( Digitalisimo_Backups_Step_Budget::scale( $job, $phase, $cursor ), Digitalisimo_Backups_Step_Budget::slice_cap() );
	}
	private static function space( $dir, $needed, $what ) {
		$free = Digitalisimo_Backups_Archive_Inspector::free_space( $dir );
		if ( null !== $free && $free < $needed + Digitalisimo_Backups_Archive_Inspector::MARGIN ) throw new Digitalisimo_Backups_Permanent_Error( 'No hay espacio en el almacén privado para ' . $what . ': se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed + Digitalisimo_Backups_Archive_Inspector::MARGIN ) . ' libres y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '. Libera espacio y vuelve a importarlo.' );
	}

	/**
	 * Fase `unpack`: la base de datos (gzip de varios miembros) se descomprime a `staging/source.sql`, por tramos, con
	 * inflate_add() sobre trozos de 4 KB y el mismo punto de control que la importación de Backuply (inicio del miembro
	 * en curso y lo ya escrito). Lo que sigue al último miembro y no es gzip (el relleno del descifrado) se ignora.
	 */
	public static function unpack( array $job ) {
		$source = self::database_source( $job );
		$paths = self::paths( $job );
		$cursor = (array) ( $job['checkpoint']['unpack'] ?? array() ) + array( 'offset' => 0, 'c' => 0, 'u' => 0 );
		clearstatcache( true, $source );
		$total = (int) filesize( $source );
		if ( 0 === (int) $cursor['offset'] ) {
			if ( "\x1f\x8b" !== (string) @file_get_contents( $source, false, null, 0, 2 ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos del conjunto no es un respaldo de UpdraftPlus: no está comprimida con gzip (…-db.gz).' );
			self::space( dirname( $paths['sql_in'] ), $total, 'descomprimir la base de datos de UpdraftPlus' );
		}
		if ( ! function_exists( 'inflate_init' ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El servidor necesita la extensión PHP zlib para leer un respaldo de UpdraftPlus.' );
		$in = @fopen( $source, 'rb' );
		if ( ! $in ) throw new Exception( 'No se pudo abrir la base de datos de UpdraftPlus.' );
		$out = @fopen( $paths['sql_in'], 'c+b' );
		if ( ! $out ) { fclose( $in ); throw new Exception( 'No se pudo escribir en el área de trabajo de la importación.' ); }
		@chmod( $paths['sql_in'], 0600 );
		$offset = (int) $cursor['offset'];
		$member = (int) $cursor['c'];
		$member_at = (int) $cursor['u'];
		$skip = $offset - $member_at;
		$units = 0;
		$deadline = null;
		$checked = $offset;
		$cap = Digitalisimo_Backups_Backuply_Import::max_expansion( $total );
		$damaged = function( $where ) { return new Digitalisimo_Backups_Permanent_Error( 'La base de datos de UpdraftPlus está dañada o truncada: falló la descompresión gzip cerca del byte ' . $where . '.' ); };
		try {
			if ( $skip < 0 || ! ftruncate( $out, $offset ) || 0 !== fseek( $out, $offset ) || 0 !== fseek( $in, $member ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La descompresión no coincide con su punto de control.', 'unpack' );
			$context = inflate_init( ZLIB_ENCODING_GZIP );
			$fed = 0;
			while ( true ) {
				if ( ZLIB_STREAM_END === inflate_get_status( $context ) ) {
					$member += (int) inflate_get_read_len( $context );
					$member_at = $offset - $skip;
					if ( 0 !== fseek( $in, $member ) ) throw $damaged( $member );
					if ( "\x1f\x8b" !== (string) fread( $in, 2 ) ) break;
					fseek( $in, $member );
					$context = inflate_init( ZLIB_ENCODING_GZIP );
					$fed = 0;
				}
				$piece = fread( $in, 4096 );
				if ( false === $piece ) throw new Exception( 'No se pudo leer la base de datos de UpdraftPlus.' );
				if ( '' === $piece ) throw $damaged( $member + $fed );
				$fed += strlen( $piece );
				$chunk = @inflate_add( $context, $piece, ZLIB_SYNC_FLUSH );
				if ( false === $chunk ) throw $damaged( $member + $fed );
				if ( $skip > 0 ) {
					$drop = min( $skip, strlen( $chunk ) );
					$chunk = (string) substr( $chunk, $drop );
					$skip -= $drop;
					if ( $skip > 0 ) continue;
				}
				if ( null === $deadline ) $deadline = self::deadline( $job, 'unpack' );
				if ( '' === $chunk ) continue;
				if ( $offset + strlen( $chunk ) > $cap ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos de UpdraftPlus se descomprime a más de ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $cap ) . ', muchas más veces su tamaño (' . Digitalisimo_Backups_Archive_Inspector::megabytes( $total ) . ') de lo que da un sitio real: no se importa (puede ser una bomba de descompresión). Si es legítima, sube el tope con el filtro digitalisimo_backups_import_max_ratio.' );
				self::put( $out, $chunk );
				$offset += strlen( $chunk );
				$units++;
				if ( $offset - $checked >= self::SPACE_EVERY ) { self::space( dirname( $paths['sql_in'] ), self::SPACE_EVERY, 'descomprimir la base de datos de UpdraftPlus' ); $checked = $offset; }
				if ( Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) {
					self::sync( $out );
					Digitalisimo_Backups_Jobs::progress( 2 + (int) min( 13, floor( 13 * ( $member + $fed ) / max( 1, $total ) ) ), 'Descomprimiendo la base de datos de UpdraftPlus: ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $offset ) . '…', true );
					return array( 'next' => 'unpack', 'checkpoint' => array( 'offset' => $offset, 'c' => $member, 'u' => $member_at, 'scale' => Digitalisimo_Backups_Step_Budget::scale( $job, 'unpack', $cursor ) ), 'message' => 'Descomprimiendo la base de datos de UpdraftPlus…' );
				}
			}
			self::sync( $out );
		} finally {
			fclose( $out );
			fclose( $in );
		}
		if ( $offset < 64 ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos de UpdraftPlus está vacía o truncada.' );
		return array( 'next' => 'scan', 'checkpoint' => array( 'offset' => $offset, 'sql_size' => $offset ), 'message' => 'Comprobando las piezas del conjunto de UpdraftPlus…' );
	}

	/**
	 * Cabecera del SQL de UpdraftPlus (`backup_db_header()`), sin ejecutar nada: versiones, direcciones, prefijo, ABSPATH,
	 * red y sus sitios, tablas omitidas. Lanza si no es la base de datos de WordPress de un respaldo de UpdraftPlus.
	 */
	public static function header( $raw ) {
		$raw = (string) $raw;
		if ( preg_match( '/^# MySQL database backup \(supplementary database/', $raw ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos del conjunto es una base externa de UpdraftPlus Premium, no la de WordPress: no se importa.' );
		if ( ! preg_match( '/^# WordPress MySQL database backup\r?\n/', $raw ) || ! preg_match( '/^# Created by UpdraftPlus version ([0-9][0-9A-Za-z.\-]{0,31})/m', $raw, $version ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos del conjunto no es la de un respaldo de UpdraftPlus (falta su cabecera «# WordPress MySQL database backup … Created by UpdraftPlus»).' );
		$meta = array( 'updraft_version' => $version[1], 'wordpress_version' => '', 'php_version' => '', 'mysql_version' => '', 'siteurl' => '', 'home' => '', 'content_url' => '', 'uploads_url' => '', 'prefix' => '', 'abspath' => '', 'multisite' => false, 'sites' => array(), 'main_site' => 0, 'skipped' => array(), 'label' => '' );
		$url = function( $value ) { $value = rtrim( trim( (string) $value ), '/' ); return preg_match( '#^https?://[^\s"\'<>\\\\]{1,2000}$#iD', $value ) ? $value : ''; };
		foreach ( preg_split( '/\r?\n/', $raw ) as $line ) {
			if ( '' === $line ) continue;
			if ( '#' !== $line[0] ) break;
			if ( 0 === strpos( $line, '# ------' ) ) break;
			if ( preg_match( '/^# WordPress Version: ([0-9A-Za-z.\-]{1,32}), running on PHP ([0-9A-Za-z.\-+~]{1,40}) \(.*\), MySQL (.{1,80})$/', $line, $m ) ) { $meta['wordpress_version'] = $m[1]; $meta['php_version'] = $m[2]; $meta['mysql_version'] = preg_replace( '/[^0-9A-Za-z.\-+~_ ]/', '', $m[3] ); continue; }
			if ( preg_match( '/^# Backup of: (.*)$/', $line, $m ) ) { $meta['siteurl'] = $url( $m[1] ); continue; }
			if ( preg_match( '/^# Home URL: (.*)$/', $line, $m ) ) { $meta['home'] = $url( $m[1] ); continue; }
			if ( preg_match( '/^# Content URL: (.*)$/', $line, $m ) ) { $meta['content_url'] = $url( $m[1] ); continue; }
			if ( preg_match( '/^# Uploads URL: (.*)$/', $line, $m ) ) { $meta['uploads_url'] = $url( $m[1] ); continue; }
			if ( preg_match( '/^# Table prefix: ([A-Za-z0-9_]{1,64})$/', $line, $m ) ) { $meta['prefix'] = $m[1]; continue; }
			if ( preg_match( '/^# ABSPATH: (.*)$/', $line, $m ) ) {
				$path = rtrim( trim( $m[1] ), '/\\' );
				if ( '' !== $path && ( '/' === $path[0] || preg_match( '/^[A-Za-z]:[\\\\\/]/', $path ) ) && false === strpos( $path, "\0" ) && false === strpos( $path, '..' ) && strlen( $path ) < 1024 ) $meta['abspath'] = str_replace( '\\', '/', $path );
				continue;
			}
			if ( preg_match( '/^# Site info: multisite=([01])$/', $line, $m ) ) { $meta['multisite'] = '1' === $m[1]; continue; }
			if ( preg_match( '/^# Site info: multisite_main_site_id=([0-9]{1,10})$/', $line, $m ) ) { $meta['main_site'] = (int) $m[1]; continue; }
			if ( preg_match( '/^# Site info: multisite_data=(.*)$/', $line, $m ) ) {
				$data = json_decode( $m[1], true, 4 );
				if ( is_array( $data ) ) foreach ( array_slice( $data, 0, 10000 ) as $site ) {
					if ( ! is_array( $site ) || ! isset( $site['blog_id'], $site['domain'] ) || ! is_string( $site['domain'] ) || ! preg_match( '/^[A-Za-z0-9.\-]{1,253}(?::[0-9]{1,5})?$/D', $site['domain'] ) ) continue;
					$meta['sites'][ (int) $site['blog_id'] ] = array( 'domain' => strtolower( $site['domain'] ), 'path' => is_string( $site['path'] ?? null ) && preg_match( '#^/[^\s"\'<>\\\\]{0,500}$#D', $site['path'] ) ? $site['path'] : '/', 'siteurl' => $url( $site['siteurl'] ?? '' ), 'home' => $url( $site['home'] ?? '' ) );
				}
				continue;
			}
			if ( preg_match( '/^# Skipped tables: (.*)$/', $line, $m ) ) { $meta['skipped'] = array_slice( array_values( array_filter( array_map( 'trim', explode( ',', $m[1] ) ), function( $t ) { return (bool) preg_match( '/^[A-Za-z0-9_$\-]{1,64}$/D', $t ); } ) ), 0, 50 ); continue; }
			if ( preg_match( '/^# Label: (.{1,120})$/', $line, $m ) ) { $meta['label'] = preg_replace( '/[\x00-\x1F\x7F<>]/', '', $m[1] ); continue; }
		}
		if ( '' === $meta['home'] ) $meta['home'] = $meta['siteurl'];
		if ( '' === $meta['siteurl'] ) $meta['siteurl'] = $meta['home'];
		return $meta;
	}

	/**
	 * Fase `scan`: (1) la cabecera del SQL; (2) cada ZIP del conjunto con el inspector de archivos no confiables y, por
	 * tramos, sus entradas al índice con su ruta en esta instalación; (3) lo que el conjunto no trae y la restauración
	 * dejaría como en el respaldo (el núcleo y las partes gestionadas que faltan), de este WordPress, al índice.
	 */
	public static function scan( array $job ) {
		$paths = self::paths( $job );
		$size = (int) ( $job['checkpoint']['unpack']['sql_size'] ?? 0 );
		self::fit( $paths['sql_in'], $size, 'El SQL descomprimido no coincide con su punto de control.', 'unpack' );
		$cursor = (array) ( $job['checkpoint']['scan'] ?? array() ) + array( 'step' => 'header', 'index' => 0, 'files' => 0, 'bytes' => 0, 'piece' => 0, 'entry' => 0, 'skipped' => array(), 'mu' => false, 'local' => array(), 'stack' => null );
		$deadline = self::deadline( $job, 'scan' );
		$layout = self::layout();
		if ( 'header' === $cursor['step'] ) {
			$meta = self::header( (string) @file_get_contents( $paths['sql_in'], false, null, 0, self::MAX_HEADER ) );
			if ( '' === $meta['siteurl'] ) throw new Digitalisimo_Backups_Permanent_Error( 'La cabecera de la base de datos de UpdraftPlus no dice de qué dirección es (# Backup of).' );
			$cursor['meta'] = $meta;
			$cursor['step'] = 'zips';
		}
		$index = self::append( $paths['index'], (int) $cursor['index'] );
		$units = 0;
		try {
			if ( 'zips' === $cursor['step'] ) {
				$list = self::zip_list( $job );
				while ( (int) $cursor['piece'] < count( $list ) ) {
					list( $entity, $name ) = $list[ (int) $cursor['piece'] ];
					$zip = new ZipArchive();
					if ( true !== $zip->open( self::piece_path( $job, $name ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La pieza ' . $name . ' no es un ZIP legible.' );
					try {
						if ( 0 === (int) $cursor['entry'] ) {
							try {
								$inspected = Digitalisimo_Backups_Archive_Inspector::entries( $zip, false, 'loose' );
							} catch ( Exception $e ) {
								throw new Digitalisimo_Backups_Permanent_Error( 'La pieza ' . $name . ' no se puede importar: ' . $e->getMessage() );
							}
							$cursor['declared'] = (int) ( $cursor['declared'] ?? 0 ) + self::expansion( $job, $name, (int) ( $inspected['declared'] ?? 0 ), (int) ( $cursor['declared'] ?? 0 ) );
						}
						$root = 'others' === $entity ? '' : self::zip_root( $zip, $entity, $name );
						$count = (int) $zip->numFiles;
						while ( (int) $cursor['entry'] < $count ) {
							$i = (int) $cursor['entry'];
							$stat = $zip->statIndex( $i );
							$cursor['entry'] = $i + 1;
							$units++;
							$entry = str_replace( '\\', '/', (string) $stat['name'] );
							if ( '/' !== substr( $entry, -1 ) ) self::classify( $entity, $root, $entry, (int) $cursor['piece'], $stat, $layout, $cursor, $index );
							if ( Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) return self::scan_slice( $job, $cursor, $index, 'Comprobando ' . $name . ' (' . $cursor['entry'] . ' de ' . $count . ' entradas)…' );
						}
					} finally {
						$zip->close();
					}
					$cursor['piece'] = (int) $cursor['piece'] + 1;
					$cursor['entry'] = 0;
				}
				$cursor['step'] = 'local';
			}
			if ( 'local' === $cursor['step'] ) {
				if ( null === $cursor['stack'] ) {
					$entities = (array) $job['spec']['source']['entities'];
					$roots = array( 'wp-admin', 'wp-includes', '' );
					$cursor['local'] = array( 'core' );
					foreach ( array( 'plugins' => 'plugins', 'themes' => 'themes', 'uploads' => 'uploads', 'mu-plugins' => 'mu' ) as $entity => $key ) {
						if ( ! empty( $entities[ $entity ] ) || ( 'mu-plugins' === $entity && $cursor['mu'] ) ) continue;
						if ( '' === $layout[ $key ] ) continue;
						$roots[] = $layout[ $key ];
						$cursor['local'][] = $entity;
					}
					$cursor['stack'] = array_reverse( $roots );
				}
				$rules = Digitalisimo_Backups_Exclusions::rules( ABSPATH, WP_CONTENT_DIR );
				$base = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
				$skip = self::local_skip();
				while ( $cursor['stack'] ) {
					$dir = (string) array_pop( $cursor['stack'] );
					$full = rtrim( $base . $dir, '/' );
					if ( is_link( $full ) || ! is_dir( $full ) ) continue;
					$names = @scandir( $full );
					if ( false === $names ) throw new Exception( 'No se pudo leer la carpeta ' . $dir . ' de este WordPress.' );
					sort( $names, SORT_STRING );
					$subdirs = array();
					foreach ( $names as $name ) {
						if ( '.' === $name || '..' === $name ) continue;
						$relative = '' === $dir ? $name : $dir . '/' . $name;
						$path = $full . '/' . $name;
						if ( isset( $skip[ $relative ] ) ) continue;
						if ( is_link( $path ) ) { $cursor['skipped']['links'] = (int) ( $cursor['skipped']['links'] ?? 0 ) + 1; continue; }
						if ( is_dir( $path ) ) {
							// La raíz: sólo sus archivos PHP (el núcleo); wp-admin, wp-includes y wp-content son raíces aparte.
							if ( '' === $dir || false !== strpos( '/' . $relative . '/', '/.digitalisimo-' ) || Digitalisimo_Backups_Exclusions::excluded( $relative, true, $rules ) ) continue;
							$subdirs[] = $relative;
							continue;
						}
						if ( ! is_file( $path ) ) continue;
						if ( '' === $dir && ( 'wp-config.php' === $name || ! preg_match( '/\.(?:php[0-9]?|phtml|pht|phar|phps)$/i', $name ) ) ) continue;
						if ( Digitalisimo_Backups_Exclusions::excluded( $relative, false, $rules ) ) continue;
						if ( ! preg_match( '//u', $relative ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Este WordPress tiene un archivo cuyo nombre no es UTF-8 válido y no puede figurar en el manifiesto: ' . Digitalisimo_Backups_Backuply_Tar::shown( $relative ) . '.' );
						$size_now = (int) @filesize( $path );
						self::put( $index, wp_json_encode( array( 'n' => $relative, 'l' => 1, 's' => $size_now, 'm' => (int) @fileperms( $path ) & 0777, 't' => (int) @filemtime( $path ) ) ) . "\n" );
						$cursor['files']++;
						$cursor['bytes'] += $size_now;
						$units++;
					}
					foreach ( array_reverse( $subdirs ) as $subdir ) $cursor['stack'][] = $subdir;
					if ( $cursor['stack'] && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) return self::scan_slice( $job, $cursor, $index, 'Tomando de este WordPress lo que el conjunto no trae (' . (int) $cursor['files'] . ' archivos)…' );
				}
			}
			self::sync( $index );
			$cursor['index'] = (int) ftell( $index );
		} finally {
			fclose( $index );
		}
		$cursor['step'] = 'done';
		$cursor['stack'] = array();
		return array( 'next' => 'database', 'checkpoint' => $cursor, 'message' => 'Convirtiendo la base de datos de UpdraftPlus…' );
	}
	private static function scan_slice( array $job, array $cursor, $index, $message ) {
		self::sync( $index );
		$cursor['index'] = (int) ftell( $index );
		$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'scan', (array) ( $job['checkpoint']['scan'] ?? array() ) );
		Digitalisimo_Backups_Jobs::progress( 15 + (int) min( 10, $cursor['files'] / 2000 ), $message, true );
		return array( 'next' => 'scan', 'checkpoint' => $cursor, 'message' => $message );
	}
	/**
	 * Revisión bk-180 (H7). Las entradas de una pieza se copian por su tamaño declarado y antes sólo se comparaban con el
	 * espacio libre, que no existe si el hosting desactiva disk_free_space(): un `uploads.zip` de 1 MB que declara 1 GB
	 * escribía 1 GB en el almacén. Ahora, con o sin espacio libre conocido, lo que declara cada pieza no pasa del tope de
	 * expansión de la base de datos (Digitalisimo_Backups_Backuply_Import::max_expansion(): 50 veces su tamaño, al menos
	 * 64 MB, filtro `digitalisimo_backups_import_max_ratio`) y lo de todas juntas, del máximo que se importa (64 GB, filtro
	 * `digitalisimo_backups_import_max_size`). Se decide en `scan`, antes de escribir nada. Devuelve lo declarado.
	 */
	private static function expansion( array $job, $name, $declared, $before ) {
		$size = (int) ( $job['spec']['source']['pieces'][ $name ]['size'] ?? 0 );
		$cap = Digitalisimo_Backups_Backuply_Import::max_expansion( $size );
		$max = Digitalisimo_Backups_Backuply_Import::max_tar();
		$mb = function( $bytes ) { return Digitalisimo_Backups_Archive_Inspector::megabytes( $bytes ); };
		if ( $declared > $cap ) throw new Digitalisimo_Backups_Permanent_Error( $cap >= $max ? 'La pieza ' . $name . ' declara ' . $mb( $declared ) . ' descomprimida, más que el máximo que se importa (' . $mb( $max ) . ', filtro digitalisimo_backups_import_max_size): no se importa.' : 'La pieza ' . $name . ' declara ' . $mb( $declared ) . ' descomprimida, más de ' . $mb( $cap ) . ', muchas más veces su tamaño (' . $mb( $size ) . ') de lo que da un sitio real: no se importa (puede ser una bomba de descompresión). Si es legítima, sube el tope con el filtro digitalisimo_backups_import_max_ratio.' );
		if ( $before + $declared > $max ) throw new Digitalisimo_Backups_Permanent_Error( 'Las piezas ZIP del conjunto declaran más de ' . $mb( $max ) . ' descomprimidas, más que el máximo que se importa (filtro digitalisimo_backups_import_max_size): no se importa.' );
		return $declared;
	}
	/** Las piezas ZIP del trabajo en el orden de ENTITIES y de sus partes: array( entidad, nombre ). */
	private static function zip_list( array $job ) {
		$out = array();
		foreach ( self::ENTITIES as $entity ) foreach ( (array) ( $job['spec']['source']['entities'][ $entity ] ?? array() ) as $name ) $out[] = array( $entity, (string) $name );
		return $out;
	}
	/**
	 * Carpeta de primer nivel de una pieza de plugins, temas, mu-plugins o medios: la entidad (`plugins/…`) o, como en su
	 * restorer.php, la única carpeta que trae (la del origen con otro nombre). Lanza si no hay una sola.
	 */
	private static function zip_root( ZipArchive $zip, $entity, $name ) {
		$dirs = array();
		$loose = false;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = str_replace( '\\', '/', (string) $zip->getNameIndex( $i ) );
			$slash = strpos( $entry, '/' );
			if ( false === $slash ) { $loose = true; break; }
			$dirs[ substr( $entry, 0, $slash ) ] = true;
			if ( count( $dirs ) > 1 ) break;
		}
		if ( ! $loose && count( $dirs ) <= 1 ) return $dirs ? (string) key( $dirs ) : $entity;
		throw new Digitalisimo_Backups_Permanent_Error( 'La pieza ' . $name . ' no tiene la forma de UpdraftPlus: sus archivos deben ir dentro de una sola carpeta (' . $entity . '/).' );
	}
	/** Dónde va en esta instalación una entrada de una pieza; '' si no se importa (con el motivo contado). */
	private static function classify( $entity, $root, $entry, $piece, array $stat, array $layout, array &$cursor, $index ) {
		$relative = '' === $root ? $entry : ( 0 === strpos( $entry, $root . '/' ) ? substr( $entry, strlen( $root ) + 1 ) : '' );
		$skip = function( $why ) use ( &$cursor ) { $cursor['skipped'][ $why ] = (int) ( $cursor['skipped'][ $why ] ?? 0 ) + 1; };
		if ( '' === $relative ) return $skip( 'outside' );
		if ( 'wp-config.php' === basename( $relative ) ) return $skip( 'config' );
		if ( 'others' === $entity ) {
			$top = explode( '/', $relative )[0];
			// Como su restorer.php: el resto de wp-content nunca trae plugins, temas, medios ni `upgrade`; mu-plugins sólo
			// de un UpdraftPlus anterior a su entidad propia (si el conjunto la trae, manda la entidad).
			if ( in_array( $top, array( 'plugins', 'themes', 'uploads', 'upgrade' ), true ) || ( 'mu-plugins' === $top && ! empty( $cursor['mu_entity'] ) ) ) return $skip( 'others' );
			if ( 'mu-plugins' === $top ) $cursor['mu'] = true;
			if ( '' === $layout['content'] ) return $skip( 'outside' );
			$target = $layout['content'] . '/' . $relative;
			foreach ( array( 'plugins', 'themes', 'uploads' ) as $key ) if ( '' !== $layout[ $key ] && 0 === strpos( $target . '/', $layout[ $key ] . '/' ) ) return $skip( 'others' );
		} else {
			$key = 'mu-plugins' === $entity ? 'mu' : $entity;
			if ( 'mu-plugins' === $entity ) { $cursor['mu'] = true; $cursor['mu_entity'] = true; }
			if ( '' === $layout[ $key ] ) throw new Digitalisimo_Backups_Permanent_Error( 'La carpeta de ' . $entity . ' de este WordPress está fuera de su carpeta de instalación: no se puede importar aquí.' );
			$target = $layout[ $key ] . '/' . $relative;
		}
		if ( Digitalisimo_Backups_Exclusions::excluded( $target, false, self::$rules ?: ( self::$rules = Digitalisimo_Backups_Exclusions::rules( ABSPATH, WP_CONTENT_DIR ) ) ) ) return $skip( 'excluded' );
		if ( ! preg_match( '//u', $target ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El conjunto de UpdraftPlus trae un nombre de archivo que no es UTF-8 válido y no puede figurar en el manifiesto: ' . Digitalisimo_Backups_Backuply_Tar::shown( $entry ) . '.' );
		self::put( $index, wp_json_encode( array( 'n' => $target, 'p' => $piece, 'e' => $entry, 's' => (int) $stat['size'], 'm' => 0644, 't' => (int) ( $stat['mtime'] ?? 0 ) ) ) . "\n" );
		$cursor['files']++;
		$cursor['bytes'] += (int) $stat['size'];
	}
	private static $rules = null;
	/** Carpetas de esta instalación relativas a ABSPATH (las de la restauración por pasos): plugins, temas, mu, medios, contenido. */
	private static function layout() {
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		$relative = function( $path ) use ( $root ) {
			$real = realpath( (string) $path );
			if ( false === $real && '' !== (string) $path ) {
				$parent = realpath( dirname( (string) $path ) );
				if ( false !== $parent ) $real = rtrim( $parent, '/\\' ) . '/' . basename( (string) $path );
			}
			$path = false === $real ? '' : trailingslashit( wp_normalize_path( $real ) );
			return '' !== $path && 0 === strpos( $path, $root ) && $path !== $root ? rtrim( substr( $path, strlen( $root ) ), '/' ) : '';
		};
		$uploads = function_exists( 'wp_upload_dir' ) ? (array) wp_upload_dir( null, false ) : array();
		return array(
			'plugins' => $relative( WP_PLUGIN_DIR ),
			'themes' => $relative( function_exists( 'get_theme_root' ) ? get_theme_root() : WP_CONTENT_DIR . '/themes' ),
			'mu' => $relative( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins' ),
			'uploads' => $relative( (string) ( $uploads['basedir'] ?? WP_CONTENT_DIR . '/uploads' ) ),
			'content' => $relative( WP_CONTENT_DIR ),
		);
	}
	/** Lo de este WordPress que nunca va al ZIP: el almacén, la carpeta de UpdraftPlus y la base SQLite activa (relativos). */
	private static function local_skip() {
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		$skip = array();
		$add = function( $path ) use ( $root, &$skip ) {
			$real = '' === (string) $path ? false : realpath( (string) $path );
			if ( false === $real ) return;
			$real = wp_normalize_path( $real );
			if ( 0 === strpos( $real, $root ) ) $skip[ rtrim( substr( $real, strlen( $root ) ), '/' ) ] = true;
		};
		try { $add( Digitalisimo_Backups::storage_directory() ); } catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		$add( self::updraft_directory() );
		foreach ( (array) glob( WP_CONTENT_DIR . '/database/*' ) as $db ) $add( $db );
		return $skip;
	}

	/** Fase `database`: el SQL de UpdraftPlus pasa al del exportador con el traductor de Backuply (mismo dialecto), por tramos. */
	public static function database( array $job ) {
		$paths = self::paths( $job );
		$scan = (array) ( $job['checkpoint']['scan'] ?? array() );
		$cursor = (array) ( $job['checkpoint']['database'] ?? array() );
		$deadline = self::deadline( $job, 'database' );
		$size = (int) ( $job['checkpoint']['unpack']['sql_size'] ?? 0 );
		$in = @fopen( $paths['sql_in'], 'rb' );
		$out = @fopen( $paths['sql'], 'c+b' );
		if ( ! $in || ! $out ) { if ( $in ) fclose( $in ); if ( $out ) fclose( $out ); throw new Exception( 'No se pudo leer el área de trabajo de la importación.' ); }
		@chmod( $paths['sql'], 0600 );
		try {
			if ( ! ftruncate( $out, (int) ( $cursor['out'] ?? 0 ) ) || 0 !== fseek( $out, (int) ( $cursor['out'] ?? 0 ) ) ) throw new Exception( 'No se pudo preparar el SQL convertido.' );
			$translator = new Digitalisimo_Backups_Backuply_Sql( $in, 0, $size, $out, $cursor, array( 'label' => 'UpdraftPlus', 'cli' => 'wp digitalisimo-backups updraft import' ) );
			$done = $translator->run( $deadline );
			$state = $translator->state();
		} finally {
			fclose( $in );
			fclose( $out );
		}
		$state['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'database', $cursor );
		if ( ! $done ) {
			Digitalisimo_Backups_Jobs::progress( 25 + (int) floor( 25 * (int) $state['pos'] / max( 1, $size ) ), 'Convirtiendo la base de datos de UpdraftPlus: ' . count( (array) $state['tables'] ) . ' tablas, ' . (int) $state['rows'] . ' filas…', true );
			return array( 'next' => 'database', 'checkpoint' => $state, 'message' => 'Convirtiendo la base de datos de UpdraftPlus…' );
		}
		$state['tables'] = array();
		$order = (array) $state['order'];
		$prefix = (string) ( $scan['meta']['prefix'] ?? '' );
		if ( '' === $prefix || ! in_array( $prefix . 'options', $order, true ) ) {
			$best = '';
			foreach ( $order as $table ) if ( preg_match( '/^([A-Za-z0-9_]*_)options$/D', $table, $m ) && ( '' === $best || strlen( $m[1] ) < strlen( $best ) ) ) $best = $m[1];
			if ( '' === $best ) throw new Digitalisimo_Backups_Permanent_Error( 'El SQL del conjunto de UpdraftPlus no trae la tabla options del prefijo de su cabecera (' . $prefix . '): no es una base de datos completa de WordPress.' );
			$prefix = $best;
		}
		$state['prefix'] = $prefix;
		return array( 'next' => 'files', 'checkpoint' => $state, 'message' => 'Base de datos convertida; escribiendo el ZIP…' );
	}

	/**
	 * Fase `files`: el ZIP con el escritor de sólo añadir de T2: `database.sql`, el marcador de wp-config.php y cada
	 * entrada del índice como `wordpress/<ruta>` (de una pieza ZIP o de este WordPress). Una entrada grande puede quedar
	 * a medias entre tramos; al retomarla se vuelve a leer su flujo hasta donde se quedó.
	 */
	public static function files( array $job ) {
		$paths = self::paths( $job );
		$scan = (array) ( $job['checkpoint']['scan'] ?? array() );
		$cursor = (array) ( $job['checkpoint']['files'] ?? array() ) + array( 'zip' => 0, 'entries' => 0, 'index' => 0, 'count' => 0, 'bytes' => 0, 'partial' => null, 'sql' => false, 'config' => false );
		if ( 0 === (int) $cursor['zip'] ) self::space( dirname( $paths['zip'] ), (int) $scan['bytes'] + (int) @filesize( $paths['sql'] ), 'escribir el ZIP convertido' );
		$deadline = self::deadline( $job, 'files' );
		$list = self::zip_list( $job );
		$zip = null;
		$entries = null;
		$index = null;
		$open = array();
		$units = 0;
		try {
			$zip = new Digitalisimo_Backups_Zip_Writer( $paths['zip'], (int) $cursor['zip'] );
			$entries = self::append( $paths['entries'], (int) $cursor['entries'] );
			$index = @fopen( $paths['index'], 'rb' );
			if ( ! $index ) throw new Exception( 'No se pudo leer el índice de la importación.' );
			$source = function( array $entry, $skip ) use ( $job, $list, &$open ) {
				if ( ! empty( $entry['l'] ) ) {
					// Revisión bk-180 (H2): el archivo debe seguir como lo anotó `scan` (tamaño y fecha), también al retomar una
					// entrada a medias; si no, entraría truncado o mezclado con el SHA-256 de lo copiado como «correcto».
					$path = self::local_unchanged( $entry );
					$handle = @fopen( $path, 'rb' );
					if ( ! $handle || ( $skip > 0 && 0 !== fseek( $handle, $skip ) ) ) throw self::local_changed( $entry );
					return $handle;
				}
				$piece = (int) $entry['p'];
				if ( ! isset( $open[ $piece ] ) ) {
					$archive = new ZipArchive();
					if ( true !== $archive->open( self::piece_path( $job, (string) $list[ $piece ][1] ) ) ) throw new Digitalisimo_Backups_Permanent_Error( 'La pieza ' . $list[ $piece ][1] . ' ya no se puede abrir.' );
					$open[ $piece ] = $archive;
				}
				$handle = $open[ $piece ]->getStream( (string) $entry['e'] );
				if ( ! $handle ) throw new Digitalisimo_Backups_Permanent_Error( 'No se pudo leer ' . $entry['e'] . ' de la pieza ' . $list[ $piece ][1] . '.' );
				while ( $skip > 0 ) {
					$drop = fread( $handle, (int) min( Digitalisimo_Backups_Zip_Writer::CHUNK, $skip ) );
					if ( false === $drop || '' === $drop ) throw new Digitalisimo_Backups_Permanent_Error( 'La entrada ' . $entry['e'] . ' de la pieza ' . $list[ $piece ][1] . ' está truncada.' );
					$skip -= strlen( $drop );
				}
				return $handle;
			};
			if ( is_array( $cursor['partial'] ) ) {
				$state = $cursor['partial'];
				$cursor['partial'] = null;
				$zip->resume( $state['zip'] );
				$raw = (int) $state['zip']['raw'];
				$handle = 'sql' === $state['src'] ? @fopen( $paths['sql'], 'rb' ) : $source( (array) $state['entry'], $raw );
				if ( 'sql' === $state['src'] && ( ! $handle || 0 !== fseek( $handle, $raw ) ) ) throw new Exception( 'No se pudo leer el SQL convertido.' );
				try {
					if ( ! self::copy( $zip, $handle, (int) $state['s'] - $raw, $cursor, $deadline, $state ) ) return self::files_slice( $job, $zip, $entries, $cursor );
					if ( 'sql' !== $state['src'] ) self::local_copied( (array) $state['entry'] );
				} finally {
					fclose( $handle );
				}
				self::done( $zip, $entries, $cursor );
				if ( 'sql' === $state['src'] ) $cursor['sql'] = true;
				else $cursor['index'] = (int) $state['next'];
			}
			if ( ! $cursor['sql'] ) {
				$sql_size = (int) $job['checkpoint']['database']['out'];
				self::fit( $paths['sql'], $sql_size, 'El SQL convertido no coincide con su punto de control.', 'database' );
				$zip->begin( 'database.sql', time(), 0600, $sql_size, Digitalisimo_Backups_Zip_Writer::method_for( 'database.sql', $sql_size ) );
				$handle = @fopen( $paths['sql'], 'rb' );
				if ( ! $handle ) throw new Exception( 'No se pudo leer el SQL convertido.' );
				try {
					if ( ! self::copy( $zip, $handle, $sql_size, $cursor, $deadline, array( 'src' => 'sql', 's' => $sql_size, 'next' => 0 ) ) ) return self::files_slice( $job, $zip, $entries, $cursor );
				} finally {
					fclose( $handle );
				}
				self::done( $zip, $entries, $cursor );
				$cursor['sql'] = true;
			}
			// El marcador de wp-config.php va después de database.sql, también si éste quedó a medias en el tramo anterior.
			if ( ! $cursor['config'] ) {
				$zip->begin( 'wordpress/wp-config.php', time(), 0600, strlen( self::CONFIG_PLACEHOLDER ), 0 );
				$zip->data( self::CONFIG_PLACEHOLDER );
				self::done( $zip, $entries, $cursor );
				$cursor['config'] = true;
			}
			$limit = (int) $scan['index'];
			fseek( $index, (int) $cursor['index'] );
			while ( (int) $cursor['index'] < $limit ) {
				if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) return self::files_slice( $job, $zip, $entries, $cursor );
				$line = fgets( $index );
				if ( false === $line ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El índice de la importación está truncado.', 'scan' );
				$next = (int) $cursor['index'] + strlen( $line );
				$entry = json_decode( $line, true );
				if ( ! is_array( $entry ) || ! isset( $entry['n'], $entry['s'] ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'El índice de la importación está dañado.', 'scan' );
				$name = 'wordpress/' . $entry['n'];
				$zip->begin( $name, (int) $entry['t'], (int) $entry['m'], (int) $entry['s'], Digitalisimo_Backups_Zip_Writer::method_for( $name, (int) $entry['s'] ) );
				$units++;
				$handle = $source( $entry, 0 );
				try {
					if ( ! self::copy( $zip, $handle, (int) $entry['s'], $cursor, $deadline, array( 'src' => 'entry', 'entry' => $entry, 's' => (int) $entry['s'], 'next' => $next ) ) ) return self::files_slice( $job, $zip, $entries, $cursor );
					self::local_copied( $entry );
				} finally {
					fclose( $handle );
				}
				self::done( $zip, $entries, $cursor );
				$cursor['index'] = $next;
			}
			$zip->commit();
			self::sync( $entries );
			$cursor['zip'] = $zip->size();
			$cursor['entries'] = (int) ftell( $entries );
		} finally {
			foreach ( $open as $archive ) $archive->close();
			if ( $index ) fclose( $index );
			if ( $zip ) $zip->close();
			if ( $entries ) fclose( $entries );
		}
		return array( 'next' => 'finish', 'checkpoint' => $cursor, 'message' => 'Escribiendo el manifiesto del ZIP convertido…' );
	}
	private static function files_slice( array $job, $zip, $entries, array $cursor ) {
		$zip->commit();
		self::sync( $entries );
		$cursor['zip'] = is_array( $cursor['partial'] ) ? (int) $cursor['partial']['zip']['p'] + (int) $cursor['partial']['zip']['z'] : $zip->size();
		$cursor['entries'] = (int) ftell( $entries );
		$cursor['scale'] = Digitalisimo_Backups_Step_Budget::scale( $job, 'files', (array) ( $job['checkpoint']['files'] ?? array() ) );
		$total = max( 1, (int) ( $job['checkpoint']['scan']['bytes'] ?? 1 ) );
		Digitalisimo_Backups_Jobs::progress( 50 + (int) floor( 44 * min( 1, (int) $cursor['bytes'] / $total ) ), 'Escribiendo el ZIP convertido: ' . (int) $cursor['count'] . ' archivos…', true );
		return array( 'next' => 'files', 'checkpoint' => $cursor, 'message' => 'Escribiendo el ZIP convertido…' );
	}
	/**
	 * Copia `$length` bytes de `$handle` (ya en su posición) a la entrada abierta. true al terminar; con el plazo vencido
	 * deja la entrada suspendida en `$cursor['partial']` y devuelve false. Un flujo que acaba antes es un error.
	 */
	private static function copy( $zip, $handle, $length, array &$cursor, $deadline, array $state ) {
		$left = (int) $length;
		$size = max( 1, (int) ( function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_zip_chunk', Digitalisimo_Backups_Zip_Writer::CHUNK ) : Digitalisimo_Backups_Zip_Writer::CHUNK ) );
		$chunks = 0;
		while ( $left > 0 ) {
			$chunk = fread( $handle, (int) min( $size, $left ) );
			$chunks++;
			if ( false === $chunk || '' === $chunk ) throw new Digitalisimo_Backups_Permanent_Error( 'Un archivo del conjunto (' . ( 'sql' === $state['src'] ? 'database.sql' : (string) ( $state['entry']['e'] ?? $state['entry']['n'] ?? '' ) ) . ') es más corto de lo que declara: la pieza está dañada o el archivo cambió.' );
			$left -= strlen( $chunk );
			$cursor['bytes'] += strlen( $chunk );
			if ( $left > 0 && is_finite( $deadline ) && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $chunks ) ) {
				$state['zip'] = $zip->suspend( $chunk );
				$cursor['partial'] = $state;
				return false;
			}
			$zip->data( $chunk );
		}
		return true;
	}
	/**
	 * Revisión bk-180 (H2). Un archivo de este WordPress (el núcleo o una parte que el conjunto no trae) puede cambiar entre
	 * `scan` y `files` (entre medias pasa `database`, que en un sitio grande dura minutos u horas por cron): una
	 * actualización automática de WordPress o de un plugin. copy() lee exactamente los bytes del índice, así que un archivo
	 * que creció entraba truncado y uno del mismo tamaño, mezclado, con el SHA-256 de lo copiado en el manifiesto: la
	 * restauración lo daba por bueno (error fatal en todo el sitio). Antes de abrirlo (y al retomar su entrada a medias) y
	 * después de copiarlo, su tamaño y su fecha deben ser los del índice (el de después también ve un archivo que creció o
	 * se reemplazó mientras se leía: lo leído no es el archivo de ahora). Si no, la importación falla con un error
	 * permanente: el ZIP nunca lleva un núcleo de dos versiones. El SHA-256 del manifiesto es siempre el de los bytes
	 * copiados (el escritor lo calcula sobre ellos). Devuelve la ruta.
	 */
	private static function local_unchanged( array $entry, $copied = false ) {
		$path = rtrim( ABSPATH, '/\\' ) . '/' . $entry['n'];
		clearstatcache( true, $path );
		if ( is_link( $path ) || ! is_file( $path ) || (int) @filesize( $path ) !== (int) $entry['s'] || (int) @filemtime( $path ) !== (int) ( $entry['t'] ?? -1 ) ) throw self::local_changed( $entry, $copied );
		return $path;
	}
	/** Tras copiar una entrada de este WordPress: el archivo no cambió mientras se leía. */
	private static function local_copied( array $entry ) {
		if ( ! empty( $entry['l'] ) ) self::local_unchanged( $entry, true );
	}
	private static function local_changed( array $entry, $copied = false ) {
		return new Digitalisimo_Backups_Permanent_Error( 'El archivo ' . $entry['n'] . ' de este WordPress cambió ' . ( $copied ? 'mientras se copiaba' : 'durante la importación' ) . ' (quizá una actualización automática de WordPress o de un plugin) o ya no se puede leer: no se convierte un núcleo de dos versiones. Vuelve a importar el conjunto.' );
	}
	private static function done( $zip, $entries, array &$cursor ) {
		self::put( $entries, wp_json_encode( $zip->finish() ) . "\n" );
		$cursor['count']++;
	}

	/**
	 * Fase `finish`: manifiesto v3 sin firma (como la importación de Backuply), comprobación del ZIP con el inspector de
	 * archivos no confiables, publicación en el almacén con rename() y su fila «Importado» en la lista de respaldos.
	 */
	public static function finish( array $job ) {
		$paths = self::paths( $job );
		$spec = (array) $job['spec'];
		$storage = trailingslashit( Digitalisimo_Backups::storage_directory() );
		$noted = is_file( $paths['note'] ) ? json_decode( (string) @file_get_contents( $paths['note'], false, null, 0, 4096 ), true ) : null;
		if ( is_array( $noted ) && is_string( $noted['file'] ?? null ) && preg_match( '/^digitalisimo-updraft-[A-Za-z0-9._-]+\.zip$/D', $noted['file'] ) && ! is_file( $paths['zip'] ) ) {
			clearstatcache( true, $storage . $noted['file'] );
			if ( is_file( $storage . $noted['file'] ) && (int) filesize( $storage . $noted['file'] ) === (int) ( $noted['size'] ?? -1 ) ) return self::published( $job, $noted['file'] );
		}
		$scan = (array) $job['checkpoint']['scan'];
		$database = (array) $job['checkpoint']['database'];
		$files = (array) $job['checkpoint']['files'];
		$meta = (array) $scan['meta'];
		$order = (array) $database['order'];
		$prefix = (string) $database['prefix'];
		$meta['multisite'] = ! empty( $meta['multisite'] ) || ( in_array( $prefix . 'blogs', $order, true ) && in_array( $prefix . 'site', $order, true ) );
		$zip = new Digitalisimo_Backups_Zip_Writer( $paths['zip'], (int) $files['zip'] );
		try {
			$totals = array( 'count' => 0, 'bytes' => 0 );
			foreach ( Digitalisimo_Backups_Packer::records( $paths['entries'], (int) $files['entries'] ) as $record ) { $totals['count']++; $totals['bytes'] += (int) $record['s']; }
			if ( $totals['count'] !== (int) $files['count'] ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La lista de entradas del ZIP convertido no coincide con su punto de control.', 'files' );
			$notices = self::notices( $job );
			$created = self::set_time( (string) $spec['source']['set'] );
			$head = array(
				'format' => 'digitalisimo-backup/v3',
				'backup_format_version' => 3,
				'backup_id' => wp_generate_uuid4(),
				'scope' => 'installation',
				'type' => 'full',
				'created_at' => gmdate( 'c', $created ?: time() ),
				'completed_at' => gmdate( 'c' ),
				'home_url' => (string) $meta['home'],
				'site_url' => (string) $meta['siteurl'],
				'wordpress_version' => (string) $meta['wordpress_version'],
				'php_version' => (string) $meta['php_version'],
				'database_version' => (string) $meta['mysql_version'],
				'database_name' => '',
				'database_size' => (int) $database['out'],
				'database_consistency' => 'updraftplus',
				'table_prefix' => $prefix,
				'origin' => self::origin( $meta, $prefix ),
				'database_tables' => array_values( $order ),
				'multisite' => (bool) $meta['multisite'],
				'file_count' => $totals['count'] - 1,
				'uncompressed_size' => $totals['bytes'],
				'checksum_algorithm' => 'sha256',
				'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '',
				'imported_from' => array(
					'format' => self::FORMAT,
					'updraft_version' => (string) $meta['updraft_version'],
					'name' => (string) $spec['source']['set'],
					'file' => (string) $spec['source']['set'],
					'files' => array_keys( (array) $spec['source']['pieces'] ),
					'size' => (int) $spec['source']['size'],
					'site_url' => (string) $meta['siteurl'],
					'encrypted' => ! empty( $spec['source']['encrypted'] ),
					'from_here' => array_values( (array) ( $scan['local'] ?? array() ) ),
					'here_wordpress_version' => self::here_version(),
					'trust' => 'untrusted',
				),
			);
			$tail = array( 'job_id' => (string) $job['id'], 'packer' => self::PACKER, 'database_snapshot_at' => gmdate( 'c', $created ?: time() ) );
			if ( $notices ) $tail['notices'] = $notices;
			Digitalisimo_Backups_Jobs::progress( 95, 'Escribiendo el manifiesto del ZIP convertido…', true );
			$zip->begin( 'manifest.json', time(), 0644, 0, function_exists( 'deflate_init' ) ? 8 : 0 );
			foreach ( Digitalisimo_Backups_Packer::manifest_pieces( $head, $paths['entries'], (int) $files['entries'], $tail ) as $piece ) $zip->data( $piece );
			$manifest = $zip->finish();
			$entries = self::append( $paths['entries'], (int) $files['entries'] );
			try {
				self::put( $entries, wp_json_encode( $manifest ) . "\n" );
				self::sync( $entries );
				$size = (int) ftell( $entries );
			} finally {
				fclose( $entries );
			}
			$count = $zip->close_archive( function() use ( $paths, $size ) { return Digitalisimo_Backups_Packer::records( $paths['entries'], $size ); }, '' );
			$zip->commit();
		} finally {
			$zip->close();
		}
		if ( $count !== $totals['count'] + 1 ) throw new Exception( 'El directorio central del ZIP convertido no tiene todas las entradas.' );
		self::check_zip( $paths['zip'] );
		$note = $paths['note'];
		$file = Digitalisimo_Backups_Job_Store::with_lock( function() use ( $paths, $storage, $spec, $note ) {
			$file = self::free_name( (string) $spec['file'] );
			clearstatcache( true, $paths['zip'] );
			if ( false === @file_put_contents( $note, wp_json_encode( array( 'file' => $file, 'size' => (int) filesize( $paths['zip'] ) ) ) ) ) throw new Exception( 'No se pudo anotar la publicación del ZIP convertido.' );
			if ( ! @rename( $paths['zip'], $storage . $file ) ) throw new Exception( 'No se pudo mover el ZIP convertido al almacén privado.' );
			@chmod( $storage . $file, 0600 );
			return $file;
		} );
		return self::published( $job, $file );
	}
	/**
	 * El ZIP publicado entra en la lista como «Importado» (si no lo estaba ya: tras una muerte, se registra al volver) y la
	 * copia descifrada de la base de datos se borra. Resultado sólo con lo que guardan los puntos de control.
	 */
	private static function published( array $job, $file ) {
		$scan = (array) ( $job['checkpoint']['scan'] ?? array() );
		$database = (array) ( $job['checkpoint']['database'] ?? array() );
		$meta = (array) ( $scan['meta'] ?? array() );
		$order = (array) ( $database['order'] ?? array() );
		$prefix = (string) ( $database['prefix'] ?? '' );
		$notices = self::notices( $job );
		$multisite = ! empty( $meta['multisite'] ) || ( in_array( $prefix . 'blogs', $order, true ) && in_array( $prefix . 'site', $order, true ) );
		$row = Digitalisimo_Backups::remember_imported( trailingslashit( Digitalisimo_Backups::storage_directory() ) . $file, array(
			'format' => self::FORMAT,
			'source' => (string) $job['spec']['source']['set'],
			'origin' => (string) $job['spec']['source']['origin'],
			'home' => (string) ( $meta['home'] ?? '' ),
			'multisite' => $multisite,
			'sites' => (array) ( $meta['sites'] ?? array() ),
			'created_at' => gmdate( 'c', self::set_time( (string) $job['spec']['source']['set'] ) ?: time() ),
			'via' => (string) ( $job['spec']['origin']['via'] ?? '' ),
			'user_id' => (int) ( $job['spec']['origin']['user_id'] ?? 0 ),
			'notices' => $notices,
		) );
		self::drop_decrypted( $job );
		$result = array( 'import' => array( 'file' => $file, 'source' => (string) $job['spec']['source']['set'], 'origin' => (string) $job['spec']['source']['origin'], 'format' => self::FORMAT, 'files' => (int) ( $scan['files'] ?? 0 ), 'tables' => count( $order ), 'rows' => (int) ( $database['rows'] ?? 0 ), 'prefix' => $prefix, 'site_url' => (string) ( $meta['home'] ?? '' ), 'multisite' => $multisite, 'notices' => $notices, 'listed' => is_array( $row ), 'confirmation' => 'RESTAURAR SIN FIRMA' ) );
		return array( 'next' => null, 'checkpoint' => array( 'published' => $file ), 'result' => $result, 'message' => 'Conjunto de UpdraftPlus importado como ' . $file . ': está en la lista de respaldos («Importado»); para restaurarlo escribe RESTAURAR SIN FIRMA.' . ( $notices ? ' Avisos: ' . implode( ' ', $notices ) : '' ) );
	}
	/** Borra la copia descifrada de la base de datos del trabajo (si la hay). */
	public static function drop_decrypted( array $job ) {
		$name = (string) ( $job['spec']['source']['decrypted'] ?? '' );
		if ( ! preg_match( '/^\.decrypted-[0-9a-f]{16}\.gz$/D', $name ) ) return;
		try {
			$path = self::store_directory() . '/' . $name;
			if ( is_file( $path ) && ! is_link( $path ) ) @unlink( $path );
		} catch ( Exception $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	/** Fecha del conjunto según su nombre (la hora local del sitio de origen, como la escribe UpdraftPlus). */
	private static function set_time( $id ) {
		return preg_match( '/^backup_([0-9]{4})-([0-9]{2})-([0-9]{2})-([0-9]{2})([0-9]{2})_/', $id, $m ) ? (int) gmmktime( (int) $m[4], (int) $m[5], 0, (int) $m[2], (int) $m[3], (int) $m[1] ) : 0;
	}
	private static function here_version() {
		$file = ABSPATH . ( defined( 'WPINC' ) ? WPINC : 'wp-includes' ) . '/version.php';
		return is_file( $file ) && preg_match( '/\$wp_version\s*=\s*[\'"]([0-9A-Za-z.\-]{1,32})[\'"]/', (string) @file_get_contents( $file, false, null, 0, 65536 ), $m ) ? $m[1] : '';
	}
	/** Avisos de la importación (van al manifiesto, a la fila y a la tarjeta del trabajo). */
	public static function notices( array $job ) {
		$scan = (array) ( $job['checkpoint']['scan'] ?? array() );
		$meta = (array) ( $scan['meta'] ?? array() );
		$out = Digitalisimo_Backups_Backuply_Import::notices( (array) ( $job['checkpoint']['database']['notes'] ?? array() ), array() );
		$here = self::here_version();
		$out[] = 'UpdraftPlus no respalda el núcleo de WordPress: el ZIP lleva el de este WordPress' . ( '' !== $here ? ' (' . $here . ')' : '' ) . ', así que restaurarlo no lo cambia' . ( '' !== (string) ( $meta['wordpress_version'] ?? '' ) && $here !== (string) $meta['wordpress_version'] ? '; el respaldo es de WordPress ' . $meta['wordpress_version'] . ' (WordPress pedirá actualizar la base de datos si es anterior)' : '' ) . '.';
		$names = array( 'plugins' => 'los plugins', 'themes' => 'los temas', 'uploads' => 'los medios', 'mu-plugins' => 'los mu-plugins' );
		$here_parts = array();
		foreach ( (array) ( $scan['local'] ?? array() ) as $part ) if ( isset( $names[ $part ] ) ) $here_parts[] = $names[ $part ];
		if ( $here_parts ) $out[] = 'El conjunto no trae ' . implode( ', ', $here_parts ) . ': el ZIP lleva los de este WordPress, como UpdraftPlus, que deja lo que su respaldo no trae.';
		$ignored = (array) ( $job['spec']['source']['ignored'] ?? array() );
		if ( $ignored ) $out[] = 'No se importan ' . count( $ignored ) . ' pieza(s) de UpdraftPlus Premium (núcleo de WordPress, otras carpetas o bases externas): ' . implode( ', ', array_slice( $ignored, 0, 4 ) ) . ( count( $ignored ) > 4 ? '…' : '' ) . '.';
		$skipped = (array) ( $scan['skipped'] ?? array() );
		if ( ! empty( $skipped['config'] ) ) $out[] = 'No se importa el wp-config.php que traía el conjunto: la restauración nunca escribe wp-config.php.';
		if ( ! empty( $skipped['excluded'] ) ) $out[] = (int) $skipped['excluded'] . ' archivo(s) de carpetas que Backups no respalda (otros plugins de respaldo, la caché de páginas, registros) no se importan.';
		if ( ! empty( $skipped['others'] ) || ! empty( $skipped['outside'] ) ) $out[] = ( (int) ( $skipped['others'] ?? 0 ) + (int) ( $skipped['outside'] ?? 0 ) ) . ' archivo(s) de «others» que UpdraftPlus tampoco restaura (plugins, temas o medios dentro de otra pieza, o fuera de su carpeta) no se importan.';
		if ( ! empty( $meta['skipped'] ) ) $out[] = 'UpdraftPlus no respaldó estas tablas, que no estarán: ' . implode( ', ', array_slice( (array) $meta['skipped'], 0, 6 ) ) . ( count( (array) $meta['skipped'] ) > 6 ? '…' : '' ) . '.';
		return $out;
	}
	/** Nombre libre en el almacén: ni un archivo ni una fila del historial con ese nombre. */
	private static function free_name( $wanted ) {
		$storage = trailingslashit( Digitalisimo_Backups::storage_directory() );
		$known = array_flip( array_map( 'strval', array_column( (array) get_option( Digitalisimo_Backups::HISTORY, array() ), 'file' ) ) );
		$free = function( $candidate ) use ( $storage, $known ) { return ! file_exists( $storage . $candidate ) && ! is_link( $storage . $candidate ) && ! isset( $known[ $candidate ] ); };
		if ( $free( $wanted ) ) return $wanted;
		$stem = substr( $wanted, 0, -4 );
		do { $candidate = $stem . '-' . bin2hex( random_bytes( 3 ) ) . '.zip'; } while ( ! $free( $candidate ) );
		return $candidate;
	}
	/** El ZIP convertido, con lo mismo que lo leerá la restauración: entradas y manifiesto v3 estricto. */
	private static function check_zip( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) return;
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) throw new Exception( 'El ZIP convertido no se puede abrir.' );
		try {
			Digitalisimo_Backups_Archive_Inspector::entries( $zip, false );
			Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
		} catch ( Exception $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( 'El conjunto de UpdraftPlus no se puede convertir: ' . $e->getMessage() );
		} finally {
			$zip->close();
		}
	}
	/**
	 * Bloque `origin` del manifiesto (el de Digitalisimo_Backups_Relocate::describe()) con lo que dice la cabecera de
	 * UpdraftPlus: direcciones, ABSPATH y, de las URL de contenido y medios, sus carpetas; en una red, su sitio principal.
	 */
	private static function origin( array $meta, $prefix ) {
		$abspath = (string) $meta['abspath'];
		$under = function( $url, $base, $dir ) {
			$url = (string) $url;
			$base = (string) $base;
			if ( '' === $url || '' === $base || '' === $dir ) return '';
			$a = parse_url( $url );
			$b = parse_url( $base );
			if ( ! is_array( $a ) || ! is_array( $b ) || strtolower( (string) ( $a['host'] ?? '' ) ) !== strtolower( (string) ( $b['host'] ?? '' ) ) ) return '';
			$pa = rtrim( (string) ( $a['path'] ?? '' ), '/' );
			$pb = rtrim( (string) ( $b['path'] ?? '' ), '/' );
			if ( '' === $pa || ( '' !== $pb && 0 !== strpos( $pa . '/', $pb . '/' ) ) || false !== strpos( $pa, '..' ) ) return '';
			return rtrim( $dir, '/' ) . substr( $pa, strlen( $pb ) );
		};
		$content = '' === $abspath ? '' : ( $under( $meta['content_url'], $meta['siteurl'], $abspath ) ?: $abspath . '/wp-content' );
		$uploads = '' === $abspath ? '' : ( $under( $meta['uploads_url'], $meta['content_url'], $content ) ?: ( $under( $meta['uploads_url'], $meta['siteurl'], $abspath ) ?: $content . '/uploads' ) );
		$out = array(
			'version' => Digitalisimo_Backups_Relocate::VERSION,
			'home' => (string) $meta['home'],
			'siteurl' => (string) $meta['siteurl'],
			'abspath' => $abspath,
			'abspath_real' => '',
			'content_dir' => $content,
			'uploads_dir' => $uploads,
			'prefix' => $prefix,
			'multisite' => (bool) $meta['multisite'],
			'subdomain' => false,
		);
		if ( $meta['multisite'] && $meta['sites'] ) {
			$sites = (array) $meta['sites'];
			$main = isset( $sites[ (int) $meta['main_site'] ] ) ? $sites[ (int) $meta['main_site'] ] : ( $sites[1] ?? reset( $sites ) );
			$path = trim( (string) $main['path'], '/' );
			$out['network'] = array( 'domain' => $main['domain'], 'path' => '' === $path ? '/' : '/' . $path . '/' );
			foreach ( $sites as $site ) if ( $site['domain'] !== $main['domain'] && '.' . $main['domain'] === substr( $site['domain'], -strlen( '.' . $main['domain'] ) ) ) $out['subdomain'] = true;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ Utilidades */

	private static function put( $handle, $bytes ) {
		$length = strlen( $bytes );
		$offset = 0;
		while ( $offset < $length ) {
			$written = fwrite( $handle, 0 === $offset ? $bytes : substr( $bytes, $offset ) );
			if ( false === $written || 0 === $written ) throw new Exception( 'No se pudo escribir en el área de trabajo de la importación; revisa el espacio libre del almacén privado.' );
			$offset += $written;
		}
	}
	private static function fit( $path, $size, $message, $phase ) {
		clearstatcache( true, $path );
		$actual = is_file( $path ) && ! is_link( $path ) ? (int) filesize( $path ) : -1;
		if ( $actual < (int) $size ) throw new Digitalisimo_Backups_Checkpoint_Invalid( $message, $phase );
		if ( $actual > (int) $size ) {
			$handle = @fopen( $path, 'r+b' );
			if ( ! $handle || ! ftruncate( $handle, (int) $size ) ) { if ( $handle ) fclose( $handle ); throw new Exception( 'No se pudo preparar el área de trabajo de la importación.' ); }
			fclose( $handle );
		}
	}
	private static function sync( $handle ) {
		if ( ! fflush( $handle ) ) throw new Exception( 'No se pudo guardar el avance de la importación en el área de trabajo.' );
		if ( function_exists( 'fsync' ) ) @fsync( $handle );
	}
	private static function append( $path, $size ) {
		$handle = @fopen( $path, 'c+b' );
		if ( ! $handle ) throw new Exception( 'No se pudo abrir ' . basename( $path ) . ' en el área de trabajo.' );
		@chmod( $path, 0600 );
		if ( ! ftruncate( $handle, (int) $size ) || 0 !== fseek( $handle, (int) $size ) ) {
			fclose( $handle );
			throw new Exception( 'No se pudo preparar ' . basename( $path ) . ' en el área de trabajo.' );
		}
		return $handle;
	}
}
