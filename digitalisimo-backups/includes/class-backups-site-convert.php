<?php
defined( 'ABSPATH' ) || exit;

/**
 * Sitio N de un respaldo de red → paquete de sitio individual (docs/BACKUPS.md, «Restaurar una red en un WordPress
 * individual»). Es «Extraer como WordPress individual» aplicado a un sitio **dentro del respaldo**, no a la red viva: las
 * mismas decisiones (Digitalisimo_Backups_Site_Extract y su exportador fila a fila) sobre el SQL y los archivos del ZIP de
 * la red, y el mismo paquete (alcance `extracted-site`, prefijo del sitio en la red, bloques `extract` y `origin`), que la
 * restauración de siempre restaura en un WordPress individual con la reubicación de E1.
 *
 * Se carga sólo al usarse (Digitalisimo_Backups_Site_Convert_Job::load()). La usan:
 * - el trabajo `convert` (Digitalisimo_Backups_Site_Convert_Job), por tramos reanudables, antes de la restauración;
 * - la verificación (inspect_site(): qué sitio y qué viaja, sin convertir) y materialize() (todo en una petición: las
 *   pruebas y quien necesite el paquete, como «un sitio de una red → otra red»).
 *
 * Pasos, cada uno con su punto de control en el área del trabajo:
 * 1. prepare(): el respaldo abierto (Security::open: SHA-256, firma, descifrado) es v3 y de una red; su manifiesto y su
 *    índice por posición quedan en la carpeta (convert.json, source.json, source.idx).
 * 2. read_slice(): database.sql a source.sql (comprobado contra su manifiesto) y, del SQL, lo que el exportador lee de
 *    la red viva: sitios, opciones del sitio, ajustes de la red, miembros, superadministradores, autores, bloqueados y
 *    metas propias del sitio (facts.json). Sólo se leen las filas que hacen falta.
 * 3. sql_slice(): site.sql con las tablas del sitio, `users`/`usermeta` filtradas y con su prefijo, las opciones
 *    materializadas y las filas que añade el exportador; sin las tablas de la red (`blogs`, `site`, `sitemeta`…) ni las
 *    de otros sitios. Cada sentencia pasa por el guardián SQL.
 * 4. files_slice(): package.zip con database.sql y los archivos que viajan (núcleo, mu-plugins, idiomas, los plugins
 *    activos del sitio y de la red, el tema y su padre, y los medios del sitio, que pasan a `uploads/`), cada uno
 *    comprobado contra el SHA-256 del manifiesto de la red.
 * 5. seal(): el manifiesto v3 del sitio, firmado con la clave del respaldo si este sitio la tiene.
 */
final class Digitalisimo_Backups_Site_Convert {
	const VERSION = 1;
	const PLAN = 'convert.json';
	const SOURCE = 'source.json';
	const INDEX = 'source.idx';
	const SQL_IN = 'source.sql';
	const FACTS = 'facts.json';
	const SQL_OUT = 'site.sql';
	const FILES = 'files.json';
	const ZIP = 'package.zip';
	const ENTRIES = 'package.entries.jsonl';
	const MARGIN = 67108864;
	/** Sitios que se listan en la pantalla y en `verify` (el resto se elige por su número). */
	const SITES_LISTED = 500;

	/* ------------------------------------------------------------------ Petición */

	/**
	 * El respaldo (su manifiesto) es el de una red y esta instalación es un WordPress individual: se restaura un sitio
	 * suyo, convertido.
	 */
	public static function applies( array $manifest ) {
		return ! is_multisite() && ! empty( $manifest['multisite'] ) && 'installation' === ( $manifest['scope'] ?? 'installation' ) && Digitalisimo_Backups_Site_Extract::SCOPE !== ( $manifest['scope'] ?? '' );
	}
	/** Opciones de la conversión: el sitio (0: el principal) y las de «Extraer» (superadministradores, claves de la red). */
	public static function options( array $args ) {
		$site = $args['site'] ?? null;
		if ( null !== $site && '' !== $site && ( ! is_scalar( $site ) || ! preg_match( '/^[0-9]{1,10}$/D', trim( (string) $site ) ) || (int) $site < 1 ) ) throw new InvalidArgumentException( 'El sitio de la red se elige por su número (1 es el principal): «' . substr( is_scalar( $site ) ? (string) $site : gettype( $site ), 0, 40 ) . '» no lo es.' );
		$extract = Digitalisimo_Backups_Site_Extract::options( array( 'super_admins' => $args['super_admins'] ?? '', 'network_secrets' => ! empty( $args['network_secrets'] ) ) );
		if ( is_wp_error( $extract ) ) throw new InvalidArgumentException( $extract->get_error_message() );
		return array( 'site' => null === $site || '' === $site ? 0 : (int) $site, 'super_admins' => $extract['super_admins'], 'network_secrets' => $extract['network_secrets'] );
	}
	/**
	 * Comprueba el respaldo abierto ($report de Digitalisimo_Backups_Security::open(), con su ZIP) y deja en $dir el plan
	 * de la conversión. $source: dónde está el ZIP para los pasos siguientes (`path`, que puede estar en $dir, y su
	 * huella). Nada de esto toca el sitio. Lanza Exception con el motivo.
	 */
	public static function prepare( array $report, array $options, $dir, array $source ) {
		$zip = $report['zip'];
		if ( ! $zip instanceof ZipArchive ) throw new Exception( 'No se pudo abrir el respaldo de la red.' );
		$trusted = 'untrusted' !== ( $report['trust'] ?? 'untrusted' );
		Digitalisimo_Backups_Archive_Inspector::entries( $zip, $trusted );
		if ( false === $zip->locateName( 'manifest.json' ) ) throw new Exception( 'Este respaldo de red no tiene manifiesto v3: no se puede convertir uno de sus sitios. Crea un respaldo nuevo de la red.' );
		$built = Digitalisimo_Backups_Manifest_Index::build( $zip, $dir . '/' . self::INDEX );
		if ( $built['index'] ) $built['index']->close();
		$manifest = Digitalisimo_Backups_Archive_Inspector::manifest( $zip );
		if ( 'digitalisimo-backup/v3' !== ( $manifest['format'] ?? '' ) || 'full' !== ( $manifest['type'] ?? '' ) || empty( $manifest['multisite'] ) || 'installation' !== ( $manifest['scope'] ?? '' ) || null === $built['index'] ) throw new Exception( 'Sólo se convierte el sitio de un respaldo completo v3 de una red.' );
		$database = $zip->statName( 'database.sql' );
		if ( ! $database || empty( $database['size'] ) ) throw new Exception( 'El respaldo de la red no contiene una base de datos válida.' );
		$free = Digitalisimo_Backups_Archive_Inspector::free_space( $dir );
		$needed = 2 * (int) $database['size'] + self::MARGIN;
		if ( null !== $free && $free < $needed ) throw new Exception( 'No hay espacio para convertir el sitio: se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed ) . ' libres en el almacén privado y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '. No se cambió nada del sitio.' );
		self::write_json( $dir . '/' . self::SOURCE, $manifest );
		$plan = array(
			'v' => self::VERSION,
			'site' => (int) $options['site'],
			'super_admins' => (string) $options['super_admins'],
			'network_secrets' => ! empty( $options['network_secrets'] ),
			'source' => array( 'file' => (string) ( $source['file'] ?? '' ), 'path' => (string) $source['path'], 'fp' => Digitalisimo_Backups_Backup_Job::fingerprint( (string) $source['path'] ), 'entries' => (int) $zip->numFiles, 'manifest_sha256' => Digitalisimo_Backups_Chain_Restore::manifest_sha( $zip ), 'backup_id' => (string) ( $manifest['backup_id'] ?? '' ) ),
			'trust' => (string) $report['trust'],
			'vouched' => (string) ( $report['vouched'] ?? '' ),
			'key' => 'signed' === $report['trust'] && is_array( $report['key'] ?? null ) ? $report['key'] : null,
			'via' => (string) ( $report['via'] ?? '' ),
			'prefix' => (string) $manifest['table_prefix'],
		);
		self::write_json( $dir . '/' . self::PLAN, $plan );
		return $plan;
	}

	/* ------------------------------------------------------------------ 2. Lo que el exportador lee de la red */

	/**
	 * Un tramo de la lectura hasta $deadline (INF: entera). $cursor vacío al empezar. Etapas: `copy` (database.sql a
	 * source.sql, comprobado contra el manifiesto), `all` (una pasada por todo el SQL), `users` y `usermeta` (sólo la
	 * parte de esas tablas, para lo que depende de lo leído antes). Devuelve true al terminar (facts.json completo).
	 */
	public static function read_slice( array $plan, $dir, array &$cursor, $deadline = INF ) {
		$cursor += array( 'stage' => 'copy', 'offset' => 0, 'units' => 0 );
		if ( 'done' === $cursor['stage'] ) return true;
		$facts = 'copy' === $cursor['stage'] ? array() : self::read_json( $dir . '/' . self::FACTS );
		if ( 'copy' === $cursor['stage'] ) {
			self::copy_sql( $plan, $dir );
			$cursor['stage'] = 'all';
			$cursor['offset'] = 0;
			$facts = self::blank_facts( $plan );
			self::write_json( $dir . '/' . self::FACTS, $facts );
			$cursor['units'] = 1;
			if ( Digitalisimo_Backups_Step_Budget::exhausted( $deadline, 1 ) ) return false;
		}
		$path = $dir . '/' . self::SQL_IN;
		self::check_sql( $plan, $dir );
		$guard = self::guard( $plan, $dir );
		$base = (string) $facts['base'];
		$prefix = (string) $facts['prefix'];
		if ( 'all' === $cursor['stage'] ) {
			$wanted = array(
				$prefix . 'options' => array( 'option_id', 'option_name', 'option_value' ),
				$base . 'blogs' => array( 'blog_id', 'site_id', 'domain', 'path' ),
				$base . 'site' => array( 'id', 'domain', 'path' ),
				$base . 'sitemeta' => array( 'site_id', 'meta_key', 'meta_value' ),
				$base . 'usermeta' => array( 'umeta_id', 'user_id', 'meta_key' ),
				$prefix . 'posts' => array( 'post_author' ),
				$prefix . 'comments' => array( 'user_id' ),
			);
			if ( $prefix !== $base ) $wanted[ $base . 'options' ] = array( 'option_name', 'option_value' );
			$reads = array_flip( array_merge( Digitalisimo_Backups_Site_Extract::SITE_READS, array( 'fileupload_url' ) ) );
			$network_reads = array_flip( array_merge( Digitalisimo_Backups_Site_Extract::NETWORK_READS, array( 'main_site' ) ) );
			$capabilities = $prefix . 'capabilities';
			$done = self::each_statement( $path, $cursor, PHP_INT_MAX, $deadline, function( $statement, $start ) use ( $guard, $wanted, &$facts, $base, $prefix, $reads, $network_reads, $capabilities ) {
				$head = self::statement_head( $statement );
				if ( ! $head ) return;
				list( $kind, $table ) = $head;
				if ( 'INSERT' !== $kind ) {
					if ( ! isset( $facts['sections'][ $table ] ) ) {
						if ( '' !== (string) $facts['open'] ) $facts['sections'][ $facts['open'] ][1] = $start;
						$facts['sections'][ $table ] = array( $start, null );
						$facts['open'] = $table;
					}
					if ( 'CREATE' === $kind && ! in_array( $table, $facts['tables'], true ) ) $facts['tables'][] = $table;
					return;
				}
				if ( ! isset( $wanted[ $table ] ) ) return;
				foreach ( self::rows( $guard, $statement, $wanted[ $table ] ) as $row ) {
					if ( $table === $prefix . 'options' ) {
						$facts['max_option'] = max( (int) $facts['max_option'], (int) $row['option_id'] );
						$name = (string) $row['option_name'];
						if ( isset( $reads[ $name ] ) ) $facts['site_values'][ $name ] = base64_encode( (string) $row['option_value'] );
						if ( $prefix === $base && in_array( $name, array( 'home', 'siteurl' ), true ) ) $facts['main_values'][ $name ] = (string) $row['option_value'];
					} elseif ( $table === $base . 'options' ) {
						if ( in_array( (string) $row['option_name'], array( 'home', 'siteurl' ), true ) ) $facts['main_values'][ (string) $row['option_name'] ] = (string) $row['option_value'];
					} elseif ( $table === $base . 'blogs' ) {
						if ( count( $facts['sites'] ) < 100000 ) $facts['sites'][ (int) $row['blog_id'] ] = array( 'domain' => strtolower( (string) $row['domain'] ), 'path' => (string) $row['path'], 'network' => (int) $row['site_id'] );
					} elseif ( $table === $base . 'site' ) {
						$facts['networks'][ (int) $row['id'] ] = array( 'domain' => strtolower( (string) $row['domain'] ), 'path' => (string) $row['path'] );
					} elseif ( $table === $base . 'sitemeta' ) {
						$network = (int) $row['site_id'];
						$key = (string) $row['meta_key'];
						if ( count( (array) ( $facts['sitemeta'][ $network ]['keys'] ?? array() ) ) < 5000 ) $facts['sitemeta'][ $network ]['keys'][] = $key;
						if ( isset( $network_reads[ $key ] ) ) $facts['sitemeta'][ $network ]['values'][ $key ] = base64_encode( (string) $row['meta_value'] );
					} elseif ( $table === $base . 'usermeta' ) {
						$facts['max_meta'] = max( (int) $facts['max_meta'], (int) $row['umeta_id'] );
						if ( $capabilities === (string) $row['meta_key'] ) $facts['members'][ (int) $row['user_id'] ] = 1;
					} elseif ( $table === $prefix . 'posts' ) {
						if ( (int) $row['post_author'] > 0 ) $facts['authors'][ (int) $row['post_author'] ] = 1;
					} elseif ( $table === $prefix . 'comments' ) {
						if ( (int) $row['user_id'] > 0 ) $facts['authors'][ (int) $row['user_id'] ] = 1;
					}
				}
			}, array( 5, 20, 'Leyendo el sitio ' . (int) $facts['site'] . ' en la base de datos de la red…' ) );
			if ( ! $done ) {
				self::write_json( $dir . '/' . self::FACTS, $facts );
				return false;
			}
			if ( '' !== (string) $facts['open'] ) $facts['sections'][ $facts['open'] ][1] = (int) $cursor['offset'];
			$facts['open'] = '';
			$facts = self::settle_site( $plan, $facts );
			self::write_json( $dir . '/' . self::FACTS, $facts );
			$cursor['stage'] = 'users';
			$cursor['offset'] = (int) $facts['sections'][ $base . 'users' ][0];
			if ( Digitalisimo_Backups_Step_Budget::exhausted( $deadline, (int) $cursor['units'] ) ) return false;
		}
		if ( 'users' === $cursor['stage'] ) {
			$admins = array_flip( array_map( 'strval', (array) self::value( $facts['network_values']['site_admins'] ?? '' ) ) );
			$done = self::each_statement( $path, $cursor, (int) $facts['sections'][ $base . 'users' ][1], $deadline, function( $statement ) use ( $guard, &$facts, $base, $admins ) {
				$head = self::statement_head( $statement );
				if ( ! $head || 'INSERT' !== $head[0] || $head[1] !== $base . 'users' ) return;
				foreach ( self::rows( $guard, $statement, array( 'ID', 'user_login', 'user_email', 'spam', 'deleted' ) ) as $row ) {
					$id = (int) $row['ID'];
					if ( isset( $admins[ (string) $row['user_login'] ] ) ) $facts['super'][ $id ] = (string) $row['user_email'];
					if ( '1' === (string) $row['spam'] || '1' === (string) $row['deleted'] ) $facts['blocked'][ $id ] = 1;
				}
			} );
			if ( ! $done ) {
				self::write_json( $dir . '/' . self::FACTS, $facts );
				return false;
			}
			$cursor['stage'] = $prefix !== $base ? 'usermeta' : 'done';
			$cursor['offset'] = (int) $facts['sections'][ $base . 'usermeta' ][0];
			self::write_json( $dir . '/' . self::FACTS, $facts );
			if ( 'usermeta' === $cursor['stage'] && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, (int) $cursor['units'] ) ) return false;
		}
		if ( 'usermeta' === $cursor['stage'] ) {
			// Las metas por sitio que cada usuario que viaja ya tiene: la del sitio principal con el mismo nombre no viaja.
			$export = self::export( $plan, $facts );
			$movers = array_filter( $export->users(), function( $kind ) { return 'author' !== $kind; } );
			$done = self::each_statement( $path, $cursor, (int) $facts['sections'][ $base . 'usermeta' ][1], $deadline, function( $statement ) use ( $guard, &$facts, $base, $prefix, $movers ) {
				$head = self::statement_head( $statement );
				if ( ! $head || 'INSERT' !== $head[0] || $head[1] !== $base . 'usermeta' ) return;
				foreach ( self::rows( $guard, $statement, array( 'user_id', 'meta_key' ) ) as $row ) {
					$id = (int) $row['user_id'];
					$key = (string) $row['meta_key'];
					if ( isset( $movers[ $id ] ) && 0 === strncmp( $key, $prefix, strlen( $prefix ) ) && strlen( $key ) > strlen( $prefix ) ) $facts['own_suffixes'][ $id ][ substr( $key, strlen( $prefix ) ) ] = 1;
				}
			} );
			self::write_json( $dir . '/' . self::FACTS, $facts );
			if ( ! $done ) return false;
			$cursor['stage'] = 'done';
		}
		return 'done' === $cursor['stage'];
	}
	/** Datos vacíos de la lectura, con los prefijos del respaldo y del sitio pedido (0: se decide al leer `blogs`). */
	private static function blank_facts( array $plan ) {
		$base = (string) $plan['prefix'];
		$site = (int) $plan['site'];
		// El sitio 1 usa el prefijo de la red (como $wpdb->get_blog_prefix()); sin número pedido, el principal se decide al
		// leer `blogs` y `sitemeta`, y la lectura se repite si no es el 1 (casi nunca: WordPress crea la red con el 1).
		$id = $site > 0 ? $site : 1;
		return array( 'base' => $base, 'site' => $id, 'requested' => $site, 'prefix' => 1 === $id ? $base : $base . $id . '_', 'network' => 1, 'sites' => array(), 'networks' => array(), 'site_values' => array(), 'main_values' => array(), 'max_option' => 0, 'sitemeta' => array(), 'max_meta' => 0, 'members' => array(), 'authors' => array(), 'super' => array(), 'blocked' => array(), 'own_suffixes' => array(), 'sections' => array(), 'open' => '', 'tables' => array() );
	}
	/**
	 * Tras la pasada por todo el SQL: el sitio existe en `blogs`, tiene sus tablas y su red; los ajustes de esa red. Sin
	 * número pedido es el principal de la red (`main_site` en sitemeta o el que tiene el dominio y la ruta de la red; si
	 * no, el 1). Lanza Digitalisimo_Backups_Permanent_Error con el motivo: el respaldo no cambia al repetir.
	 */
	private static function settle_site( array $plan, array $facts ) {
		$base = (string) $facts['base'];
		$listed = array_keys( $facts['sites'] );
		sort( $listed );
		if ( ! $listed ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de la red no trae su tabla de sitios (' . $base . 'blogs): no se puede elegir un sitio. No se cambió nada del sitio.' );
		$id = (int) $facts['site'];
		if ( 0 === (int) $facts['requested'] ) {
			$main = self::main_site( $facts );
			if ( $main !== $id ) throw new Digitalisimo_Backups_Permanent_Error( 'El sitio principal de esta red es el ' . $main . ': elige su número para restaurarlo (sitio ' . $main . '). No se cambió nada del sitio.' );
		}
		if ( ! isset( $facts['sites'][ $id ] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de la red no tiene el sitio ' . $id . '. Sus sitios: ' . self::sites_text( $facts ) . '. No se cambió nada del sitio.' );
		$prefix = (string) $facts['prefix'];
		$tables = self::source_tables( $plan, $facts );
		foreach ( array( 'options', 'posts', 'postmeta' ) as $core ) if ( ! in_array( $prefix . $core, $tables, true ) ) throw new Digitalisimo_Backups_Permanent_Error( 'Al sitio ' . $id . ' del respaldo le falta la tabla ' . $prefix . $core . '. No se cambió nada del sitio.' );
		if ( $prefix !== $base ) foreach ( array( 'users', 'usermeta' ) as $own ) if ( in_array( $prefix . $own, $tables, true ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El sitio ' . $id . ' del respaldo tiene una tabla propia ' . $prefix . $own . ' que el sitio convertido usaría para sus usuarios. No se cambió nada del sitio.' );
		foreach ( array( 'users', 'usermeta' ) as $global ) if ( ! isset( $facts['sections'][ $base . $global ] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de la red no trae la tabla ' . $base . $global . '. No se cambió nada del sitio.' );
		$facts['network'] = (int) ( $facts['sites'][ $id ]['network'] ?? 1 );
		$meta = (array) ( $facts['sitemeta'][ $facts['network'] ] ?? array() );
		$facts['network_values'] = array();
		foreach ( (array) ( $meta['values'] ?? array() ) as $key => $raw ) $facts['network_values'][ $key ] = base64_decode( (string) $raw );
		$facts['network_keys'] = array_values( array_unique( array_map( 'strval', (array) ( $meta['keys'] ?? array() ) ) ) );
		unset( $facts['sitemeta'] );
		return $facts;
	}
	/** El sitio principal de la red según el respaldo: `main_site` de sitemeta, el del dominio y la ruta de la red o el 1. */
	public static function main_site( array $facts ) {
		$sites = (array) ( $facts['sites'] ?? array() );
		foreach ( (array) ( $facts['sitemeta'] ?? array() ) as $network => $meta ) {
			$main = (int) base64_decode( (string) ( $meta['values']['main_site'] ?? '' ) );
			if ( $main > 0 && isset( $sites[ $main ] ) && 1 === (int) $network ) return $main;
		}
		$network = (array) ( $facts['networks'][1] ?? array() );
		if ( $network ) foreach ( $sites as $id => $site ) if ( (string) $site['domain'] === (string) $network['domain'] && (string) $site['path'] === (string) $network['path'] ) return (int) $id;
		if ( isset( $sites[1] ) || ! $sites ) return 1;
		$ids = array_keys( $sites );
		sort( $ids );
		return (int) $ids[0];
	}
	private static function sites_text( array $facts ) {
		$out = array();
		foreach ( array_slice( (array) $facts['sites'], 0, 20, true ) as $id => $site ) $out[] = $id . ' (' . Digitalisimo_Backups_Site_Extract::label( (string) $site['domain'], (string) $site['path'] ) . ')';
		return implode( ', ', $out ) . ( count( (array) $facts['sites'] ) > 20 ? '…' : '' );
	}
	/** Tablas del respaldo: la lista de su manifiesto v3 (o las que crea su SQL). */
	private static function source_tables( array $plan, array $facts ) {
		unset( $plan );
		return array_values( array_unique( array_map( 'strval', (array) ( $facts['tables'] ?? array() ) ) ) );
	}
	/** El exportador del sitio con los datos leídos del respaldo (las mismas decisiones que al extraer de la red viva). */
	private static function export( array $plan, array $facts ) {
		$values = array();
		foreach ( (array) $facts['site_values'] as $name => $raw ) $values[ (string) $name ] = Digitalisimo_Backups_Site_Extract_Export::value( base64_decode( (string) $raw ) );
		$network = array();
		foreach ( (array) ( $facts['network_values'] ?? array() ) as $name => $raw ) $network[ (string) $name ] = Digitalisimo_Backups_Site_Extract_Export::value( (string) $raw );
		$site = self::site_info( $facts );
		$export = new Digitalisimo_Backups_Site_Extract_Export( $site, array( 'super_admins' => (string) $plan['super_admins'], 'network_secrets' => ! empty( $plan['network_secrets'] ) ), self::site_tables( $facts ), self::uploads( $facts ), array( (string) $facts['base'], (string) $facts['prefix'] ) );
		$excluded = (int) ( $network['blog_count'] ?? count( (array) $facts['sites'] ) ) > Digitalisimo_Backups_Site_Extract::LIST_TABLES_UP_TO ? null : self::global_tables( $facts );
		$export->from_facts( array(
			'site_values' => $values,
			'network_values' => $network,
			'network_keys' => (array) ( $facts['network_keys'] ?? array() ),
			'max_option' => (int) $facts['max_option'],
			'max_meta' => (int) $facts['max_meta'],
			'members' => array_keys( (array) $facts['members'] ),
			'super' => (array) $facts['super'],
			'authors' => array_keys( (array) $facts['authors'] ),
			'blocked' => array_keys( (array) $facts['blocked'] ),
			'own_suffixes' => (array) $facts['own_suffixes'],
			'present' => array_keys( $values ),
			'excluded_tables' => $excluded,
		) );
		return $export;
	}
	/** El sitio como lo describe Site_Extract::site(): id, dominio, ruta, red, dirección y etiqueta. */
	private static function site_info( array $facts ) {
		$id = (int) $facts['site'];
		$row = (array) ( $facts['sites'][ $id ] ?? array( 'domain' => '', 'path' => '/' ) );
		$home = untrailingslashit( (string) Digitalisimo_Backups_Site_Extract_Export::value( base64_decode( (string) ( $facts['site_values']['home'] ?? '' ) ) ) );
		if ( '' === $home ) $home = 'https://' . $row['domain'] . untrailingslashit( (string) $row['path'] );
		return array( 'id' => $id, 'domain' => (string) $row['domain'], 'path' => (string) $row['path'], 'network_id' => (int) ( $facts['network'] ?? 1 ), 'url' => $home, 'label' => Digitalisimo_Backups_Site_Extract::label( (string) $row['domain'], (string) $row['path'] ) );
	}
	/** Tablas del sitio en el respaldo (las de su prefijo; del principal, sin las globales ni las de otros sitios) más usuarios y metas. */
	private static function site_tables( array $facts ) {
		$base = (string) $facts['base'];
		$prefix = (string) $facts['prefix'];
		$out = array();
		foreach ( (array) $facts['tables'] as $table ) {
			$table = (string) $table;
			if ( 0 !== strncmp( $table, $prefix, strlen( $prefix ) ) ) continue;
			if ( $prefix === $base ) {
				$rest = substr( $table, strlen( $base ) );
				if ( in_array( $rest, Digitalisimo_Backups_Site_Extract::GLOBAL_TABLES, true ) || preg_match( '/^[0-9]+_/', $rest ) ) continue;
			}
			$out[] = $table;
		}
		foreach ( array( 'users', 'usermeta' ) as $global ) if ( in_array( $base . $global, (array) $facts['tables'], true ) ) $out[] = $base . $global;
		return $out;
	}
	/** Tablas con el prefijo de la red y sin número de sitio que no son de WordPress (no viajan; al principal, todas viajan). */
	private static function global_tables( array $facts ) {
		if ( (string) $facts['prefix'] === (string) $facts['base'] ) return array();
		$base = (string) $facts['base'];
		$out = array();
		foreach ( (array) $facts['tables'] as $table ) {
			$table = (string) $table;
			if ( 0 !== strncmp( $table, $base, strlen( $base ) ) ) continue;
			$rest = substr( $table, strlen( $base ) );
			if ( in_array( $rest, Digitalisimo_Backups_Site_Extract::GLOBAL_TABLES, true ) || in_array( $rest, Digitalisimo_Backups_Site_Extract::CORE_SITE_TABLES, true ) || preg_match( '/^[0-9]+_/', $rest ) ) continue;
			$out[] = $table;
		}
		sort( $out );
		return $out;
	}

	/* ------------------------------------------------------------------ Medios y archivos */

	/** Ruta relativa a ABSPATH del origen (o null si queda fuera), con barras normales y sin barra final. */
	private static function relative( $path, $abspath ) {
		$path = rtrim( str_replace( '\\', '/', (string) $path ), '/' );
		$abspath = rtrim( str_replace( '\\', '/', (string) $abspath ), '/' );
		if ( '' === $path || '' === $abspath ) return null;
		if ( $path === $abspath ) return '';
		return 0 === strpos( $path . '/', $abspath . '/' ) ? substr( $path, strlen( $abspath ) + 1 ) : null;
	}
	/**
	 * Carpetas del sitio en el ZIP del respaldo (rutas relativas a la raíz de WordPress del origen) y la carpeta absoluta y
	 * las URL de sus medios, como las calcula WordPress dentro del sitio (`_wp_upload_dir()`): el principal, la de la red;
	 * con `ms_files_rewriting`, `blogs.dir/N/files`; si no, `uploads/sites/N`. Un `upload_path` propio del sitio manda.
	 */
	public static function layout( array $origin, array $facts ) {
		$abspath = (string) ( $origin['abspath'] ?? '' );
		$content_abs = rtrim( str_replace( '\\', '/', (string) ( $origin['content_dir'] ?? '' ) ), '/' );
		$content = self::relative( $content_abs, $abspath );
		if ( null === $content || '' === $content ) { $content = 'wp-content'; $content_abs = rtrim( str_replace( '\\', '/', $abspath ), '/' ) . '/wp-content'; }
		$main_uploads_abs = rtrim( str_replace( '\\', '/', (string) ( $origin['uploads_dir'] ?? '' ) ), '/' );
		if ( '' === $main_uploads_abs ) $main_uploads_abs = $content_abs . '/uploads';
		$id = (int) $facts['site'];
		$values = array();
		foreach ( array( 'upload_path', 'upload_url_path', 'fileupload_url', 'siteurl', 'home' ) as $name ) {
			$value = Digitalisimo_Backups_Site_Extract_Export::value( base64_decode( (string) ( $facts['site_values'][ $name ] ?? '' ) ) );
			$values[ $name ] = is_scalar( $value ) ? trim( (string) $value ) : '';
		}
		$network_values = (array) ( $facts['network_values'] ?? array() );
		$ms_files = ! empty( Digitalisimo_Backups_Site_Extract_Export::value( (string) ( $network_values['ms_files_rewriting'] ?? '' ) ) );
		$join = function( $path ) use ( $abspath ) {
			$path = str_replace( '\\', '/', $path );
			return preg_match( '#^(?:/|[A-Za-z]:/)#', $path ) ? rtrim( $path, '/' ) : rtrim( str_replace( '\\', '/', $abspath ), '/' ) . '/' . trim( $path, '/' );
		};
		if ( (string) $facts['prefix'] === (string) $facts['base'] ) $uploads_abs = $main_uploads_abs;
		elseif ( $ms_files ) $uploads_abs = '' !== $values['upload_path'] ? $join( $values['upload_path'] ) : $content_abs . '/blogs.dir/' . $id . '/files';
		else $uploads_abs = ( '' !== $values['upload_path'] && 'wp-content/uploads' !== $values['upload_path'] ? $join( $values['upload_path'] ) : $content_abs . '/uploads' ) . '/sites/' . $id;
		$uploads = self::relative( $uploads_abs, $abspath );
		$main_uploads = self::relative( $main_uploads_abs, $abspath );
		// URL de los medios: WordPress la arma con WP_CONTENT_URL, la del sitio que atiende la petición. Lo subido desde el
		// propio sitio lleva su dirección y lo creado desde el principal, la del principal (como upload_aliases()).
		$siteurl = untrailingslashit( $values['siteurl'] );
		$home = untrailingslashit( $values['home'] );
		$main_siteurl = untrailingslashit( (string) ( $facts['main_values']['siteurl'] ?? $siteurl ) );
		$main_home = untrailingslashit( (string) ( $facts['main_values']['home'] ?? $home ) );
		$tail = null === $uploads ? '' : '/' . $uploads;
		$baseurl = '' !== $siteurl && null !== $uploads ? $siteurl . $tail : '';
		$aliases = array();
		if ( null !== $uploads ) foreach ( array( $home, $main_siteurl, $main_home ) as $base ) if ( '' !== $base && $base . $tail !== $baseurl && ! in_array( $base . $tail, $aliases, true ) ) $aliases[] = $base . $tail;
		if ( $ms_files ) foreach ( array( $values['fileupload_url'], '' !== $siteurl ? $siteurl . '/files' : '' ) as $files ) { $files = untrailingslashit( $files ); if ( '' !== $files && preg_match( '#^https?://#i', $files ) && $files !== $baseurl && ! in_array( $files, $aliases, true ) ) $aliases[] = $files; }
		if ( preg_match( '#^https?://#i', $values['upload_url_path'] ) && untrailingslashit( $values['upload_url_path'] ) !== $baseurl ) $aliases[] = untrailingslashit( $values['upload_url_path'] );
		return array( 'content' => $content, 'uploads' => $uploads, 'main_uploads' => $main_uploads, 'main' => (string) $facts['prefix'] === (string) $facts['base'], 'basedir' => $uploads_abs, 'baseurl' => $baseurl, 'aliases' => $aliases );
	}
	private static function uploads( array $facts, $origin = null ) {
		$layout = self::layout( is_array( $origin ) ? $origin : (array) ( $facts['origin'] ?? array() ), $facts );
		return array( 'basedir' => $layout['basedir'], 'baseurl' => $layout['baseurl'], 'aliases' => $layout['aliases'] );
	}
	/**
	 * Dónde va una entrada del respaldo de la red en el paquete del sitio (ruta relativa a la raíz de WordPress), o null si
	 * no viaja, con su categoría: las reglas de Site_Extract::file_map() sobre los nombres del ZIP. Viajan los archivos de
	 * la raíz que trae WordPress (nunca wp-config.php ni otra configuración), wp-admin y wp-includes, `wp-content/index.php`,
	 * mu-plugins, idiomas, los plugins activos (del sitio, de la red y este mismo), el tema y su padre, y los medios del
	 * sitio, que pasan a `uploads/` (del principal, sin `sites/`). Los drop-ins no viajan (son del servidor de la red).
	 * $map: layout() más `plugins` (carpeta o archivo => true) y `themes` (carpeta => true).
	 */
	public static function target( $relative, array $map ) {
		$relative = (string) $relative;
		$content = (string) $map['content'];
		// Los medios del sitio primero: pueden estar en una carpeta propia (upload_path, blogs.dir).
		if ( null !== $map['uploads'] && '' !== (string) $map['uploads'] && 0 === strpos( $relative, $map['uploads'] . '/' ) ) {
			$rest = substr( $relative, strlen( $map['uploads'] ) + 1 );
			if ( $map['main'] && 0 === strpos( $rest, 'sites/' ) ) return array( null, 'uploads' );
			return array( $content . '/uploads/' . $rest, 'uploads' );
		}
		if ( null !== $map['main_uploads'] && '' !== (string) $map['main_uploads'] && 0 === strpos( $relative, $map['main_uploads'] . '/' ) ) return array( null, 'uploads' );
		if ( false === strpos( $relative, '/' ) ) return in_array( $relative, Digitalisimo_Backups_Site_Extract::CORE_ROOT_FILES, true ) ? array( $relative, 'core' ) : array( null, 'root:' . $relative );
		$top = strstr( $relative, '/', true );
		if ( 'wp-admin' === $top || 'wp-includes' === $top ) return array( $relative, 'core' );
		if ( 0 !== strpos( $relative, $content . '/' ) ) return array( null, 'root:' . $top );
		$rest = substr( $relative, strlen( $content ) + 1 );
		if ( false === strpos( $rest, '/' ) ) {
			if ( 'index.php' === $rest ) return array( $relative, 'core' );
			return in_array( $rest, Digitalisimo_Backups_Site_Extract::DROP_INS, true ) ? array( null, 'dropin:' . $rest ) : array( null, 'content:' . $rest );
		}
		$dir = strstr( $rest, '/', true );
		$inner = substr( $rest, strlen( $dir ) + 1 );
		$item = false === strpos( $inner, '/' ) ? $inner : strstr( $inner, '/', true );
		switch ( $dir ) {
			case 'mu-plugins': return array( $relative, 'mu:' . $item );
			case 'languages': return array( $relative, 'languages' );
			case 'plugins':
				if ( 'index.php' === $inner ) return array( $relative, 'plugins' );
				return isset( $map['plugins'][ $item ] ) ? array( $relative, 'plugins' ) : array( null, 'plugins:' . $item );
			case 'themes':
				if ( 'index.php' === $inner ) return array( $relative, 'themes' );
				return isset( $map['themes'][ $item ] ) ? array( $relative, 'themes' ) : array( null, 'themes:' . $item );
		}
		return array( null, 'content:' . $dir );
	}
	/** Plugins y temas que viajan (por carpeta o archivo de un plugin de un solo archivo) y los nombres que no se usan. */
	private static function items( array $report ) {
		$plugins = array();
		$themes = array();
		$rejected = array();
		foreach ( (array) ( $report['plugins']['active'] ?? array() ) as $plugin ) {
			$parts = explode( '/', str_replace( '\\', '/', (string) $plugin ) );
			if ( count( $parts ) > 2 || ! Digitalisimo_Backups_Site_Extract::safe_name( $parts[0] ) || ( isset( $parts[1] ) && ! Digitalisimo_Backups_Site_Extract::safe_name( $parts[1] ) ) ) { $rejected[] = (string) $plugin; continue; }
			$plugins[ $parts[0] ] = true;
		}
		foreach ( array( 'stylesheet', 'template' ) as $which ) {
			$theme = (string) ( $report['themes'][ $which ] ?? '' );
			if ( '' === $theme ) continue;
			if ( ! Digitalisimo_Backups_Site_Extract::safe_name( $theme ) ) { $rejected[] = $theme; continue; }
			$themes[ $theme ] = true;
		}
		return array( $plugins, $themes, array_values( array_unique( $rejected ) ) );
	}

	/* ------------------------------------------------------------------ 3. El SQL del sitio */

	/**
	 * Un tramo de site.sql hasta $deadline. Cada sentencia de una tabla que viaja pasa por el guardián (tablas de usuarios
	 * con el prefijo del sitio) y, en usuarios, metas y opciones del sitio, por el exportador (Site_Extract_Export::rows());
	 * al terminar cada una de esas tablas, sus filas nuevas (finish()). Las demás tablas no se leen. Devuelve true al
	 * terminar; $cursor guarda dónde va la entrada y la salida, la tabla en curso y las tablas escritas.
	 */
	public static function sql_slice( array $plan, $dir, array &$cursor, $deadline = INF ) {
		$cursor += array( 'offset' => 0, 'out' => 0, 'table' => null, 'flushed' => false, 'tables' => array(), 'units' => 0, 'done' => false );
		if ( $cursor['done'] ) return true;
		self::check_sql( $plan, $dir );
		$facts = self::read_json( $dir . '/' . self::FACTS );
		$export = self::export( $plan, $facts );
		$travel = array_flip( self::site_tables( $facts ) );
		$transform = array_flip( array( (string) $facts['base'] . 'users', (string) $facts['base'] . 'usermeta', (string) $facts['prefix'] . 'options' ) );
		$guard = self::guard( $plan, $dir );
		if ( (string) $facts['prefix'] !== (string) $facts['base'] ) $guard->rename_tables( array( $facts['base'] . 'users' => $export->name( $facts['base'] . 'users' ), $facts['base'] . 'usermeta' => $export->name( $facts['base'] . 'usermeta' ) ) );
		$pending = null;
		$guard->on_insert( function( array $insert, $sql ) use ( $export, $transform, &$pending ) {
			if ( ! isset( $transform[ $insert['table'] ] ) || ! is_array( $insert['columns'] ) ) return array();
			$out = array();
			$changed = false;
			foreach ( $insert['rows'] as $literals ) {
				$row = array();
				foreach ( $insert['columns'] as $i => $column ) $row[ $column ] = self::literal( $literals[ $i ] ?? null, $sql );
				$rows = $export->rows( $insert['table'], $row );
				if ( 1 !== count( $rows ) || $rows[0] !== $row ) $changed = true;
				foreach ( $rows as $one ) $out[] = $one;
			}
			if ( ! $changed ) return array();
			$pending = $out;
			return false;
		} );
		$path = $dir . '/' . self::SQL_OUT;
		$output = @fopen( $path, 'c+b' );
		if ( ! $output || ! ftruncate( $output, (int) $cursor['out'] ) || 0 !== fseek( $output, (int) $cursor['out'] ) ) { if ( $output ) fclose( $output ); throw new Exception( 'No se pudo preparar la base de datos del sitio convertido en el área de trabajo.' ); }
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
		$flush = function( &$cursor ) use ( $export, $output, $travel ) {
			$table = $cursor['table'];
			if ( null === $table || $cursor['flushed'] || ! isset( $travel[ $table ] ) ) return;
			foreach ( $export->finish( $table ) as $row ) self::write( $output, self::insert( $export->name( $table ), $row ) );
			$cursor['flushed'] = true;
		};
		try {
			$done = self::each_statement( $dir . '/' . self::SQL_IN, $cursor, PHP_INT_MAX, $deadline, function( $statement ) use ( $guard, $export, $travel, $output, $flush, &$cursor, &$pending ) {
				$head = self::statement_head( $statement );
				$table = $head ? $head[1] : null;
				if ( $table !== $cursor['table'] ) {
					$flush( $cursor );
					$cursor['table'] = $table;
					$cursor['flushed'] = false;
					$cursor['out'] = (int) ftell( $output );
				}
				if ( null !== $table && ! isset( $travel[ $table ] ) ) return;
				$pending = null;
				if ( ! $guard->check( $statement ) ) return;
				if ( null !== $pending ) {
					foreach ( $pending as $row ) self::write( $output, self::insert( $export->name( (string) $table ), $row ) );
				} elseif ( ! $guard->skipped() ) {
					self::write( $output, $guard->rewritten() . ";\n" );
				}
				if ( $head && 'CREATE' === $head[0] && ! in_array( $export->name( $table ), $cursor['tables'], true ) ) $cursor['tables'][] = $export->name( $table );
				$cursor['out'] = (int) ftell( $output );
			}, array( 25, 48, 'Convirtiendo la base de datos del sitio…' ) );
			if ( $done ) {
				$flush( $cursor );
				$cursor['table'] = null;
				$cursor['done'] = true;
			}
			if ( ! fflush( $output ) ) throw new Exception( 'No se pudo guardar la base de datos del sitio convertido en el área de trabajo.' );
			$cursor['out'] = (int) ftell( $output );
		} finally {
			fclose( $output );
		}
		if ( $cursor['done'] && ! $cursor['tables'] ) throw new Digitalisimo_Backups_Permanent_Error( 'El sitio del respaldo no tiene tablas que convertir. No se cambió nada del sitio.' );
		return (bool) $cursor['done'];
	}
	/** INSERT con columnas, como el exportador (un INSERT por fila, cada valor como cadena o NULL). */
	private static function insert( $table, array $row ) {
		$columns = array_map( function( $column ) { return '`' . str_replace( '`', '``', (string) $column ) . '`'; }, array_keys( $row ) );
		return 'INSERT INTO `' . str_replace( '`', '``', (string) $table ) . '` (' . implode( ',', $columns ) . ') VALUES (' . implode( ',', array_map( array( 'Digitalisimo_Backups', 'sql_value' ), array_values( $row ) ) ) . ");\n";
	}

	/* ------------------------------------------------------------------ 4. El ZIP del sitio */

	/**
	 * Un tramo del ZIP del sitio hasta $deadline. Primero calcula qué viaja y si cabe (`map`), después database.sql desde
	 * site.sql y cada entrada del respaldo que viaja, comprobada contra el SHA-256 de su manifiesto. Devuelve true al
	 * terminar. Lo que no viaja se cuenta por categoría (files.json).
	 */
	public static function files_slice( array $plan, $dir, array &$cursor, $deadline = INF ) {
		$cursor += array( 'stage' => 'map', 'i' => 0, 'zip' => 0, 'entries' => 0, 'files' => 0, 'bytes' => 0, 'database' => 0, 'units' => 0 );
		if ( 'done' === $cursor['stage'] ) return true;
		$facts = self::read_json( $dir . '/' . self::FACTS );
		$manifest = self::read_json( $dir . '/' . self::SOURCE );
		$zip = self::open_source( $plan );
		try {
			if ( 'map' === $cursor['stage'] ) {
				$export = self::export( $plan, $facts );
				$report = $export->report();
				$map = self::layout( (array) ( $manifest['origin'] ?? array() ), $facts );
				list( $map['plugins'], $map['themes'], $rejected ) = self::items( $report );
				$needed = self::MARGIN;
				$largest = 0;
				for ( $i = 0; $i < $zip->numFiles; $i++ ) {
					$stat = $zip->statIndex( $i );
					$name = str_replace( '\\', '/', (string) ( $stat['name'] ?? '' ) );
					if ( '/' === substr( $name, -1 ) || 0 !== strpos( $name, 'wordpress/' ) ) continue;
					list( $to ) = self::target( substr( $name, 10 ), $map );
					if ( null === $to ) continue;
					$needed += (int) $stat['size'];
					$largest = max( $largest, (int) $stat['size'] );
				}
				$needed += (int) @filesize( $dir . '/' . self::SQL_OUT );
				$free = Digitalisimo_Backups_Archive_Inspector::free_space( $dir );
				if ( null !== $free && $free < $needed ) throw new Digitalisimo_Backups_Permanent_Error( 'No hay espacio para el sitio convertido: se necesitan ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $needed ) . ' libres en el almacén privado y hay ' . Digitalisimo_Backups_Archive_Inspector::megabytes( $free ) . '. No se cambió nada del sitio.' );
				self::write_json( $dir . '/' . self::FILES, array( 'map' => array( 'content' => $map['content'], 'uploads' => $map['uploads'], 'main_uploads' => $map['main_uploads'], 'main' => $map['main'], 'plugins' => array_keys( $map['plugins'] ), 'themes' => array_keys( $map['themes'] ) ), 'rejected' => $rejected, 'excluded' => array(), 'mu_plugins' => array(), 'drop_ins' => array() ) );
				$cursor['stage'] = 'sql';
				@unlink( $dir . '/' . self::ZIP );
				@unlink( $dir . '/' . self::ENTRIES );
				$cursor['zip'] = 0;
				$cursor['entries'] = 0;
			}
			$files = self::read_json( $dir . '/' . self::FILES );
			$map = (array) $files['map'];
			$map['plugins'] = array_flip( (array) $map['plugins'] );
			$map['themes'] = array_flip( (array) $map['themes'] );
			$index = Digitalisimo_Backups_Manifest_Index::open( $dir . '/' . self::INDEX, (int) $plan['source']['entries'] );
			$entries = self::append_handle( $dir . '/' . self::ENTRIES, (int) $cursor['entries'] );
			$writer = null;
			$units = 0;
			try {
				$writer = new Digitalisimo_Backups_Zip_Writer( $dir . '/' . self::ZIP, (int) $cursor['zip'] );
				if ( 'sql' === $cursor['stage'] ) {
					$record = self::copy_file( $writer, $dir . '/' . self::SQL_OUT, 'database.sql' );
					Digitalisimo_Backups_Chain::write_all( $entries, json_encode( $record ) . "\n" );
					$cursor['database'] = (int) $record['s'];
					$cursor['stage'] = 'files';
					$units++;
				}
				if ( 'files' === $cursor['stage'] ) {
					$count = (int) $zip->numFiles;
					if ( $count !== (int) $plan['source']['entries'] ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de la red cambió mientras se convertía su sitio. No se cambió nada del sitio.' );
					for ( $i = (int) $cursor['i']; $i < $count; $i++ ) {
						if ( $units > 0 && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) break;
						$stat = $zip->statIndex( $i );
						$name = str_replace( '\\', '/', (string) ( $stat['name'] ?? '' ) );
						$cursor['i'] = $i + 1;
						if ( 0 === $i % 50 ) Digitalisimo_Backups_Jobs::progress( 50 + (int) floor( 40 * $i / max( 1, $count ) ), 'Convirtiendo los archivos del sitio (' . ( $i + 1 ) . ' de ' . $count . ')…' );
						if ( '/' === substr( $name, -1 ) || 0 !== strpos( $name, 'wordpress/' ) ) continue;
						list( $to, $kind ) = self::target( substr( $name, 10 ), $map );
						$units++;
						if ( null === $to ) {
							self::note( $files, $kind );
							continue;
						}
						if ( 0 === strpos( $kind, 'mu:' ) ) self::note( $files, $kind );
						$expected = $index->get( $i );
						if ( ! is_array( $expected ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El manifiesto del respaldo de la red no describe ' . substr( $name, 10, 120 ) . '. No se cambió nada del sitio.' );
						$mode = 0644;
						if ( method_exists( $zip, 'getExternalAttributesIndex' ) && $zip->getExternalAttributesIndex( $i, $opsys, $attributes ) && ZipArchive::OPSYS_UNIX === $opsys && ( $attributes >> 16 ) & 0777 ) $mode = ( $attributes >> 16 ) & 0777;
						$record = (int) $stat['size'] === (int) $expected['size'] ? self::copy_entry( $writer, $zip, $name, 'wordpress/' . $to, (int) $expected['size'], $mode, (string) $expected['sha256'], (int) ( $stat['mtime'] ?? time() ) ) : null;
						if ( null === $record ) throw new Digitalisimo_Backups_Permanent_Error( 'El archivo ' . substr( $name, 10, 120 ) . ' del respaldo de la red no coincide con su manifiesto: el respaldo está dañado o fue modificado. No se cambió nada del sitio.' );
						Digitalisimo_Backups_Chain::write_all( $entries, json_encode( $record ) . "\n" );
						$cursor['files']++;
						$cursor['bytes'] += (int) $record['s'];
					}
					if ( (int) $cursor['i'] >= $count ) $cursor['stage'] = 'done';
				}
				if ( ! fflush( $entries ) ) throw new Exception( 'No se pudo guardar el avance del sitio convertido en el almacén privado.' );
				$cursor['zip'] = $writer->commit();
				$cursor['entries'] = (int) ftell( $entries );
			} finally {
				if ( $writer ) $writer->close();
				fclose( $entries );
				$index->close();
			}
			self::write_json( $dir . '/' . self::FILES, $files );
			$cursor['units'] = $units;
		} finally {
			$zip->close();
		}
		if ( 'done' === $cursor['stage'] ) self::check_required( $dir, (int) $cursor['entries'] );
		return 'done' === $cursor['stage'];
	}
	/** Lo que no viaja, por categoría y nombre (sólo los recuentos y los mu-plugins y drop-ins van al manifiesto). */
	private static function note( array &$files, $kind ) {
		list( $group, $name ) = array_pad( explode( ':', (string) $kind, 2 ), 2, '' );
		$keys = array( 'root' => 'root', 'content' => 'content', 'plugins' => 'plugins', 'themes' => 'themes' );
		if ( 'mu' === $group ) { if ( '' !== $name && 'index.php' !== $name && ! in_array( $name, $files['mu_plugins'], true ) && count( $files['mu_plugins'] ) < Digitalisimo_Backups_Site_Extract::REPORT_LIMIT ) $files['mu_plugins'][] = $name; return; }
		if ( 'dropin' === $group ) { if ( ! in_array( $name, $files['drop_ins'], true ) ) $files['drop_ins'][] = $name; return; }
		if ( ! isset( $keys[ $group ] ) || '' === $name ) return;
		if ( ! isset( $files['excluded'][ $group ] ) ) $files['excluded'][ $group ] = array();
		if ( ! in_array( $name, $files['excluded'][ $group ], true ) && count( $files['excluded'][ $group ] ) < 10000 ) $files['excluded'][ $group ][] = $name;
	}
	/** Las entradas que exige un paquete de un sitio (Site_Extract::required_entries()). */
	private static function check_required( $dir, $size ) {
		$need = array_flip( Digitalisimo_Backups_Site_Extract::required_entries() );
		foreach ( Digitalisimo_Backups_Packer::records( $dir . '/' . self::ENTRIES, $size ) as $record ) unset( $need[ (string) $record['n'] ] );
		if ( $need ) throw new Digitalisimo_Backups_Permanent_Error( 'Al respaldo de la red le falta ' . implode( ', ', array_keys( $need ) ) . ': no se puede restaurar un sitio suyo. No se cambió nada del sitio.' );
	}

	/* ------------------------------------------------------------------ 5. El manifiesto del sitio */

	/**
	 * Cierra el paquete con el manifiesto v3 de un sitio extraído (`scope` extracted-site, `multisite` false, prefijo del
	 * sitio en la red, `origin` del sitio como el WordPress individual que será, `extract` con lo que viajó y
	 * `converted_from` con el respaldo de la red y el sitio), firmado con $root (la clave del respaldo) si la hay.
	 * Idempotente. Devuelve la ruta del ZIP.
	 */
	public static function seal( array $plan, $dir, array $sql, array $files_cursor, $root = null ) {
		if ( 'done' !== ( $files_cursor['stage'] ?? '' ) || empty( $sql['done'] ) ) throw new Exception( 'El sitio aún no está convertido.' );
		$facts = self::read_json( $dir . '/' . self::FACTS );
		$manifest = self::read_json( $dir . '/' . self::SOURCE );
		$files = self::read_json( $dir . '/' . self::FILES );
		$report = self::report( $plan, $facts, $manifest );
		$counts = array();
		foreach ( (array) $files['excluded'] as $group => $names ) $counts[ $group ] = count( (array) $names );
		$extract = Digitalisimo_Backups_Site_Extract::manifest_block( $report, array( 'excluded_count' => $counts, 'mu_plugins' => (array) $files['mu_plugins'], 'drop_ins' => (array) $files['drop_ins'], 'rejected' => (array) $files['rejected'] ) );
		$head = array(
			'format' => 'digitalisimo-backup/v3',
			'backup_format_version' => 3,
			'backup_id' => wp_generate_uuid4(),
			'scope' => Digitalisimo_Backups_Site_Extract::SCOPE,
			'type' => 'full',
			'created_at' => (string) ( $manifest['created_at'] ?? gmdate( 'c' ) ),
			'completed_at' => gmdate( 'c' ),
			'home_url' => (string) $report['site']['url'],
			'site_url' => (string) $report['site']['site_url'],
			'wordpress_version' => (string) ( $manifest['wordpress_version'] ?? '' ),
			'php_version' => (string) ( $manifest['php_version'] ?? '' ),
			'database_version' => (string) ( $manifest['database_version'] ?? '' ),
			'database_name' => (string) ( $manifest['database_name'] ?? '' ),
			'database_size' => (int) $files_cursor['database'],
			'database_consistency' => (string) ( $manifest['database_consistency'] ?? '' ),
			'table_prefix' => (string) $facts['prefix'],
			'origin' => Digitalisimo_Backups_Site_Extract::origin_block( $report, (array) ( $manifest['origin'] ?? array() ) ),
			'database_tables' => array_values( (array) $sql['tables'] ),
			'multisite' => false,
			'file_count' => (int) $files_cursor['files'],
			'uncompressed_size' => (int) $files_cursor['bytes'] + (int) $files_cursor['database'],
			'checksum_algorithm' => 'sha256',
			'plugin_version' => defined( 'DIGITALISIMO_BACKUPS_VERSION' ) ? DIGITALISIMO_BACKUPS_VERSION : '',
			'excluded_secrets' => (array) ( $manifest['excluded_secrets'] ?? array() ),
			'extract' => $extract,
			// De dónde sale: el respaldo de la red (su identidad y la de su manifiesto) y el sitio. Restaurar por partes lo
			// admite (un sitio extraído de la red viva, no).
			'converted_from' => array( 'backup_id' => (string) $plan['source']['backup_id'], 'manifest_sha256' => (string) $plan['source']['manifest_sha256'], 'file' => (string) $plan['source']['file'], 'site' => (int) $facts['site'], 'label' => (string) $report['site']['label'] ),
		);
		$tail = array( 'packer' => 'site-convert', 'database_snapshot_at' => (string) ( $manifest['database_snapshot_at'] ?? '' ) );
		$target = $dir . '/' . self::ZIP;
		$entries_path = $dir . '/' . self::ENTRIES;
		$listed = (int) $files_cursor['entries'];
		$mac = null;
		$key_id = '';
		$key = is_array( $plan['key'] ?? null ) ? $plan['key'] : null;
		if ( $root && $key && Digitalisimo_Backups_Keys::valid_descriptor( $key ) ) {
			$key_id = Digitalisimo_Backups_Keys::key_id( $root );
			if ( ! hash_equals( (string) $key['id'], $key_id ) ) throw new Exception( 'La clave del respaldo de la red no es la de su firma.' );
			$tail['auth'] = Digitalisimo_Backups_Manifest_Auth::block( $key, false );
			$mac = hash_init( 'sha512', HASH_HMAC, Digitalisimo_Backups_Keys::mac_key( $root ) );
			hash_update( $mac, Digitalisimo_Backups_Manifest_Auth::PREFIX . "\n" . $key_id . "\n" );
		}
		$writer = new Digitalisimo_Backups_Zip_Writer( $target, (int) $files_cursor['zip'] );
		$entries = self::append_handle( $entries_path, $listed );
		try {
			$writer->begin( 'manifest.json', time(), 0644, 0, function_exists( 'deflate_init' ) ? 8 : 0 );
			foreach ( Digitalisimo_Backups_Packer::manifest_pieces( $head, $entries_path, $listed, $tail ) as $piece ) {
				$writer->data( $piece );
				if ( $mac ) hash_update( $mac, $piece );
			}
			$record = $writer->finish();
			Digitalisimo_Backups_Chain::write_all( $entries, json_encode( $record ) . "\n" );
			if ( ! fflush( $entries ) ) throw new Exception( 'No se pudo cerrar el sitio convertido en el almacén privado.' );
			$size = (int) ftell( $entries );
			$comment = $mac ? Digitalisimo_Backups_Manifest_Auth::PREFIX . ' ' . $key_id . ' ' . Digitalisimo_Backups_Keys::b64url_encode( substr( hash_final( $mac, true ), 0, 32 ) ) : '';
			$count = $writer->close_archive( function() use ( $entries_path, $size ) { return Digitalisimo_Backups_Packer::records( $entries_path, $size ); }, $comment );
			$writer->commit();
			if ( $count !== (int) $files_cursor['files'] + 2 ) throw new Exception( 'El sitio convertido no tiene todas sus entradas.' );
		} finally {
			$writer->close();
			fclose( $entries );
		}
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $target, 0600 );
		return $target;
	}
	/** El informe del exportador (bloque `extract`), con los datos leídos del respaldo. */
	private static function report( array $plan, array $facts, array $manifest ) {
		$facts['origin'] = (array) ( $manifest['origin'] ?? array() );
		$export = self::export( $plan, $facts );
		$report = $export->report();
		$layout = self::layout( (array) ( $manifest['origin'] ?? array() ), $facts );
		$report['uploads'] = array( 'basedir' => $layout['basedir'], 'baseurl' => $layout['baseurl'], 'aliases' => $layout['aliases'] );
		return $report;
	}
	/** Texto para quien restaura: qué sitio de la red queda como este WordPress. */
	public static function notice( array $facts_or_report ) {
		$label = (string) ( $facts_or_report['site']['label'] ?? '' );
		$id = (int) ( $facts_or_report['site']['id'] ?? 0 );
		return 'Se restauró el sitio ' . $label . ' (ID ' . $id . ') del respaldo de la red como este WordPress individual: sus tablas, sus usuarios con sus roles, sus plugins y su tema y sus medios en uploads/; lo demás de la red no entró.';
	}

	/* ------------------------------------------------------------------ Verificar y en una petición */

	/**
	 * Lee el sitio pedido en el respaldo de la red sin convertirlo (`verify`): sitios de la red, cuál se restauraría y qué
	 * viaja (usuarios, plugins, tema) y su origen como WordPress individual para el plan de E1. $report abierto. Usa una
	 * carpeta de `.staging/` que borra al terminar. Lanza Exception con el motivo (sitio que no existe, respaldo que no
	 * sirve).
	 */
	public static function inspect_site( array $report, array $options, $storage ) {
		$dir = Digitalisimo_Backups_Security::new_staging( $storage );
		try {
			$plan = self::prepare( $report, $options, $dir, array( 'path' => (string) $report['path'], 'file' => wp_basename( (string) $report['source'] ) ) );
			$cursor = array();
			try {
				self::read_slice( $plan, $dir, $cursor, INF );
			} catch ( Digitalisimo_Backups_Permanent_Error $e ) {
				throw new Exception( $e->getMessage(), 0, $e );
			}
			$facts = self::read_json( $dir . '/' . self::FACTS );
			$manifest = self::read_json( $dir . '/' . self::SOURCE );
			$site = self::report( $plan, $facts, $manifest );
			$tables = array();
			foreach ( self::site_tables( $facts ) as $table ) $tables[] = in_array( $table, array( $facts['base'] . 'users', $facts['base'] . 'usermeta' ), true ) ? $facts['prefix'] . substr( $table, strlen( $facts['base'] ) ) : $table;
			return array(
				'site' => (int) $facts['site'],
				'label' => (string) $site['site']['label'],
				'url' => (string) $site['site']['url'],
				'sites' => self::sites_list( $facts ),
				'main' => self::main_site( $facts ),
				'users' => (array) $site['users'],
				'plugins' => (array) $site['plugins']['active'],
				'theme' => (string) $site['themes']['stylesheet'],
				'tables' => $tables,
				'origin' => Digitalisimo_Backups_Site_Extract::origin_block( $site, (array) ( $manifest['origin'] ?? array() ) ),
				'manifest' => array( 'scope' => Digitalisimo_Backups_Site_Extract::SCOPE, 'extract' => array( 'uploads' => $site['uploads'], 'site' => $site['site'] ), 'table_prefix' => (string) $facts['prefix'] ),
				'text' => 'Se restaura el sitio ' . $site['site']['label'] . ' (ID ' . (int) $facts['site'] . ') de la red como este WordPress individual.',
			);
		} finally {
			Digitalisimo_Backups_Security::remove_tree( $dir );
		}
	}
	/** Sitios de la red (los SITES_LISTED primeros): ID => array( label, url ). */
	public static function sites_list( array $facts ) {
		$out = array();
		foreach ( array_slice( (array) $facts['sites'], 0, self::SITES_LISTED, true ) as $id => $site ) $out[ (int) $id ] = array( 'label' => Digitalisimo_Backups_Site_Extract::label( (string) $site['domain'], (string) $site['path'] ) );
		ksort( $out );
		return $out;
	}
	/**
	 * Convierte en esta petición (verificar con el paquete entero, las pruebas, «un sitio de una red → otra red»):
	 * prepare() + los cuatro pasos + seal() en una carpeta nueva de `.staging/`. $report abierto (su ZIP), $root la clave
	 * del respaldo (o null). Devuelve array( path, staging, plan, facts ). Quien llama borra `staging`.
	 */
	public static function materialize( array $report, array $options, $storage, $root = null ) {
		$dir = Digitalisimo_Backups_Security::new_staging( $storage );
		try {
			$plan = self::prepare( $report, $options, $dir, array( 'path' => (string) $report['path'], 'file' => wp_basename( (string) $report['source'] ) ) );
			$read = array();
			self::read_slice( $plan, $dir, $read, INF );
			$sql = array();
			self::sql_slice( $plan, $dir, $sql, INF );
			$files = array();
			self::files_slice( $plan, $dir, $files, INF );
			$path = self::seal( $plan, $dir, $sql, $files, $root );
			return array( 'path' => $path, 'staging' => $dir, 'plan' => $plan, 'facts' => self::read_json( $dir . '/' . self::FACTS ) );
		} catch ( Throwable $e ) {
			Digitalisimo_Backups_Security::remove_tree( $dir );
			throw $e instanceof Exception ? $e : new Exception( $e->getMessage(), 0, $e );
		}
	}

	/* ------------------------------------------------------------------ Utilidades */

	/** El ZIP del respaldo de la red, el mismo que se comprobó al preparar (su huella). */
	public static function open_source( array $plan ) {
		$path = (string) $plan['source']['path'];
		clearstatcache( true, $path );
		if ( is_link( $path ) || ! is_file( $path ) || ! Digitalisimo_Backups_Backup_Job::same_fingerprint( Digitalisimo_Backups_Backup_Job::fingerprint( $path ), (array) $plan['source']['fp'] ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El respaldo de la red cambió o desapareció del almacén mientras se convertía su sitio. No se cambió nada del sitio: vuelve a restaurarlo.' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) throw new Digitalisimo_Backups_Permanent_Error( 'No se puede abrir el respaldo de la red.' );
		return $zip;
	}
	/** database.sql del respaldo a source.sql, comprobado contra el tamaño y el SHA-256 de su manifiesto. */
	private static function copy_sql( array $plan, $dir ) {
		$zip = self::open_source( $plan );
		$index = Digitalisimo_Backups_Manifest_Index::open( $dir . '/' . self::INDEX, (int) $plan['source']['entries'] );
		try {
			$at = $zip->locateName( 'database.sql' );
			$expected = false === $at ? null : $index->get( (int) $at );
			if ( ! is_array( $expected ) ) throw new Digitalisimo_Backups_Permanent_Error( 'El manifiesto del respaldo de la red no describe su base de datos. No se cambió nada del sitio.' );
			$stream = Digitalisimo_Backups::zip_entry_reader( $zip, 'database.sql', (int) $expected['size'] );
			$target = $dir . '/' . self::SQL_IN;
			@unlink( $target );
			$out = @fopen( $target . '.part', 'wb' );
			if ( ! $stream || ! $out ) { if ( $stream ) fclose( $stream ); if ( $out ) fclose( $out ); throw new Exception( 'No se pudo copiar la base de datos del respaldo de la red al área de trabajo.' ); }
			if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $target . '.part', 0600 );
			$hash = hash_init( 'sha256' );
			$bytes = 0;
			try {
				while ( ! feof( $stream ) ) {
					$chunk = @fread( $stream, 1048576 );
					if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) throw new Exception( 'No se pudo leer la base de datos del respaldo de la red.' );
					$bytes += strlen( $chunk );
					hash_update( $hash, $chunk );
					Digitalisimo_Backups_Chain::write_all( $out, $chunk );
				}
			} finally {
				fclose( $stream );
				fclose( $out );
			}
			if ( $bytes !== (int) $expected['size'] || ! hash_equals( (string) $expected['sha256'], hash_final( $hash ) ) ) { @unlink( $target . '.part' ); throw new Digitalisimo_Backups_Permanent_Error( 'La base de datos del respaldo de la red no coincide con su manifiesto: el respaldo está dañado o fue modificado. No se cambió nada del sitio.' ); }
			if ( ! @rename( $target . '.part', $target ) ) throw new Exception( 'No se pudo cerrar la base de datos del respaldo de la red en el área de trabajo.' );
			$plan_path = $dir . '/' . self::PLAN;
			$saved = self::read_json( $plan_path );
			$saved['sql'] = array( 'size' => $bytes, 'fp' => Digitalisimo_Backups_Backup_Job::fingerprint( $target ) );
			self::write_json( $plan_path, $saved );
		} finally {
			$index->close();
			$zip->close();
		}
	}
	/** source.sql sigue siendo el que se copió y comprobó. */
	private static function check_sql( array $plan, $dir ) {
		$saved = self::read_json( $dir . '/' . self::PLAN );
		$path = $dir . '/' . self::SQL_IN;
		if ( ! is_file( $path ) || is_link( $path ) || ! Digitalisimo_Backups_Backup_Job::same_fingerprint( Digitalisimo_Backups_Backup_Job::fingerprint( $path ), (array) ( $saved['sql']['fp'] ?? array() ) ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'La base de datos del respaldo de la red ya no está íntegra en el área de trabajo; se lee otra vez.', Digitalisimo_Backups_Site_Convert_Job::READ );
		unset( $plan );
	}
	/** El guardián SQL del respaldo de la red (el de su manifiesto y su confianza). */
	private static function guard( array $plan, $dir ) {
		$manifest = self::read_json( $dir . '/' . self::SOURCE );
		return Digitalisimo_Backups_Sql_Guard::for_backup( $manifest, 'untrusted' !== ( $plan['trust'] ?? 'untrusted' ) );
	}
	/**
	 * Recorre las sentencias de $path desde $cursor['offset'] hasta $to (posición) o el final, llamando a $each( sentencia,
	 * inicio ). Tras cada una, `offset` es el fin de esa sentencia (un lector nuevo puede seguir desde ahí). Devuelve true si
	 * llegó al final del tramo pedido; false si se acabó el plazo.
	 */
	private static function each_statement( $path, array &$cursor, $to, $deadline, callable $each, array $progress = array() ) {
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo leer la base de datos del respaldo de la red en el área de trabajo.' );
		$size = (int) filesize( $path );
		$offset = (int) $cursor['offset'];
		$units = 0;
		try {
			if ( $offset >= min( $size, $to ) ) return true;
			if ( $offset > 0 && 0 !== fseek( $handle, $offset ) ) throw new Exception( 'No se pudo seguir la base de datos del respaldo de la red donde se quedó.' );
			$reader = new Digitalisimo_Backups_Sql_Reader();
			$origin = $offset;
			while ( true ) {
				$chunk = fread( $handle, 262144 );
				if ( false === $chunk ) throw new Exception( 'No se pudo leer la base de datos del respaldo de la red.' );
				$last = '' === $chunk;
				$statements = $last ? $reader->finish() : $reader->feed( $chunk );
				$ends = $last ? array_fill( 0, count( $statements ), $size - $origin ) : $reader->ends();
				foreach ( $statements as $k => $statement ) {
					$start = $offset;
					if ( $start >= $to ) { $cursor['units'] = (int) ( $cursor['units'] ?? 0 ) + $units; return true; }
					call_user_func( $each, $statement, $start );
					$offset = $origin + (int) ( $ends[ $k ] ?? 0 );
					$cursor['offset'] = $offset;
					$units++;
					if ( $progress && 0 === $units % 500 ) Digitalisimo_Backups_Jobs::progress( (int) floor( $progress[0] + ( $progress[1] - $progress[0] ) * $offset / max( 1, $size ) ), (string) $progress[2] );
					if ( ! $last && Digitalisimo_Backups_Step_Budget::exhausted( $deadline, $units ) ) { $cursor['units'] = (int) ( $cursor['units'] ?? 0 ) + $units; return false; }
				}
				if ( $last ) break;
			}
			$cursor['offset'] = max( (int) $cursor['offset'], $size );
			$cursor['units'] = (int) ( $cursor['units'] ?? 0 ) + $units;
			return true;
		} finally {
			fclose( $handle );
		}
	}
	/** Tipo (DROP, CREATE o INSERT) y tabla de una sentencia del exportador, o null (SET, otras). */
	private static function statement_head( $statement ) {
		if ( ! preg_match( '/^(DROP\s+TABLE\s+IF\s+EXISTS|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT(?:\s+IGNORE)?\s+INTO)\s+`((?:[^`]|``){1,192})`/i', substr( (string) $statement, 0, 512 ), $m ) ) return null;
		$word = strtoupper( strtok( $m[1], " \t\r\n" ) );
		return array( $word, str_replace( '``', '`', $m[2] ) );
	}
	/**
	 * Filas de un INSERT comprobado por el guardián, sólo con las columnas pedidas (decodificadas). Una fila sin alguna de
	 * ellas lleva null.
	 */
	private static function rows( $guard, $statement, array $columns ) {
		$insert = null;
		$guard->on_insert( function( array $value ) use ( &$insert ) { $insert = $value; return array(); } );
		$guard->check( $statement );
		$guard->on_insert( function() { return array(); } );
		if ( ! is_array( $insert ) || ! is_array( $insert['columns'] ) ) return array();
		$positions = array();
		foreach ( $columns as $column ) { $at = array_search( $column, $insert['columns'], true ); $positions[ $column ] = false === $at ? null : $at; }
		$out = array();
		foreach ( $insert['rows'] as $literals ) {
			$row = array();
			foreach ( $positions as $column => $at ) $row[ $column ] = null === $at ? null : self::literal( $literals[ $at ] ?? null, $statement );
			$out[] = $row;
		}
		return $out;
	}
	/** Valor de un literal del guardián: el texto de una cadena, el número tal cual o null. */
	private static function literal( $literal, $sql ) {
		if ( ! is_array( $literal ) ) return null;
		if ( 'raw' === ( $literal['type'] ?? '' ) ) return (string) $literal['raw'];
		if ( 'str' !== ( $literal['type'] ?? '' ) ) return null;
		return Digitalisimo_Backups_Relocate::sql_unescape( substr( $sql, $literal['start'], $literal['end'] - $literal['start'] ), $literal['quote'] );
	}
	/** Un valor de una opción de red leído del respaldo (sin instanciar objetos). */
	private static function value( $raw ) { return Digitalisimo_Backups_Site_Extract_Export::value( (string) $raw ); }
	private static function write( $handle, $bytes ) {
		Digitalisimo_Backups_Chain::write_all( $handle, $bytes );
	}
	/** Una entrada del respaldo al ZIP del sitio con otro nombre, sin comprimir, comprobando tamaño y SHA-256. */
	private static function copy_entry( $writer, $zip, $name, $to, $size, $mode, $sha256, $mtime ) {
		$stream = Digitalisimo_Backups::zip_entry_reader( $zip, $name, $size );
		if ( ! $stream ) return null;
		$writer->begin( $to, $mtime, $mode, $size, 0 );
		try {
			while ( ! feof( $stream ) ) {
				$chunk = @fread( $stream, 1048576 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $stream ) ) ) break;
				$writer->data( $chunk );
			}
		} finally {
			fclose( $stream );
		}
		$record = $writer->finish();
		if ( (int) $record['s'] !== (int) $size || ! hash_equals( (string) $sha256, (string) $record['h'] ) ) {
			$writer->drop( $record );
			return null;
		}
		return $record;
	}
	/** Un archivo del área de trabajo como entrada del ZIP. */
	private static function copy_file( $writer, $path, $name ) {
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) throw new Exception( 'No se pudo leer la base de datos del sitio convertido.' );
		$size = (int) filesize( $path );
		$writer->begin( $name, time(), 0644, $size, function_exists( 'deflate_init' ) ? 8 : 0 );
		try {
			while ( ! feof( $handle ) ) {
				$chunk = fread( $handle, 1048576 );
				if ( false === $chunk ) throw new Exception( 'No se pudo leer la base de datos del sitio convertido.' );
				$writer->data( $chunk );
			}
		} finally {
			fclose( $handle );
		}
		$record = $writer->finish();
		if ( (int) $record['s'] !== $size ) throw new Exception( 'No se pudo copiar la base de datos del sitio convertido.' );
		return $record;
	}
	private static function append_handle( $path, $size ) {
		$handle = @fopen( $path, 'c+b' );
		if ( ! $handle || ! ftruncate( $handle, (int) $size ) || 0 !== fseek( $handle, (int) $size ) ) { if ( $handle ) fclose( $handle ); throw new Exception( 'No se pudo preparar el sitio convertido en el almacén privado.' ); }
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
		return $handle;
	}
	public static function read_json( $path ) {
		$data = is_file( $path ) && ! is_link( $path ) ? json_decode( (string) @file_get_contents( $path ), true ) : null;
		if ( ! is_array( $data ) ) throw new Digitalisimo_Backups_Checkpoint_Invalid( 'Falta lo que la conversión del sitio dejó en el área de trabajo; se repite.', Digitalisimo_Backups_Site_Convert_Job::READ );
		return $data;
	}
	public static function write_json( $path, $data ) {
		$json = wp_json_encode( $data );
		if ( ! is_string( $json ) || is_link( $path ) || strlen( $json ) !== @file_put_contents( $path . '.tmp', $json ) || ! @rename( $path . '.tmp', $path ) ) throw new Exception( 'No se pudo guardar el avance de la conversión del sitio en el almacén privado; revisa el espacio libre.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
	}
}
