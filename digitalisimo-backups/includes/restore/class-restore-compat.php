<?php
defined( 'ABSPATH' ) || exit;

// Archivos y lo de después al restaurar, sobre todo el respaldo de otra instalación (estudio de UpdraftPlus 1.26.14 del
// 2026-11-16, brechas 5, 6, 9, 12, 17 a 22; ver docs/BACKUPS.md, «Restauración por pasos» → «Archivos y lo de después»).
// El motor (Digitalisimo_Backups_Restore_Engine) sólo la llama con una línea en cada punto; toda la lógica vive aquí:
//  - verificación: compat.json con lo que se decide con el sitio intacto (otra instalación, este plugin, drop-ins);
//  - archivos: los drop-ins del origen no se preparan (5), lo preparado lleva los permisos de WordPress de forma aditiva
//    (6) y la carpeta de este plugin no se retira si el respaldo no la trae (22);
//  - cambio de tablas: este plugin queda activo en la base de datos restaurada antes del RENAME (22);
//  - limpieza, por tramos: caché de páginas (9), CSS de constructores (20), tema que falta (19) y servicios del origen
//    que siguen conectados (21); al final, el bloque de WordPress de .htaccess (12) y el informe (17, 18 en verify).
// Se carga con el plugin, como Digitalisimo_Backups_Restore_Parts (revisión A1): el final de una restauración pone la carpeta
// de este plugin del respaldo, quizá de una versión sin este archivo, y después todavía escribe el informe. Comentarios
// `//`: sin opcache (php-wasm) un docblock ocupa memoria en cada petición.
final class Digitalisimo_Backups_Restore_Compat {
	const FILE = 'compat.json';
	// Las carpetas de caché de página que se vacían al terminar cualquier restauración (relativas a wp-content): la de
	// WP Super Cache, WP Rocket, W3TC, Cache Enabler, WP Fastest Cache, Autoptimize, Comet Cache, Breeze, SG Optimizer…
	// (`cache`), Hummingbird, la del hosting Endurance y la del CSS estático de Divi.
	const CONTENT_CACHES = array( 'cache', 'wphb-cache', 'endurance-page-cache', 'et-cache' );
	// Las de los medios de cada sitio: CSS y JS compilados de Avada (Fusion).
	const UPLOAD_CACHES = array( 'fusion-styles', 'fusion-scripts' );
	// Temas por defecto de WordPress, del más nuevo al más antiguo (si WP_Theme::get_core_default_theme() no responde).
	const CORE_THEMES = array( 'twentytwentysix', 'twentytwentyfive', 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone', 'twentytwenty', 'twentynineteen', 'twentyseventeen', 'twentysixteen', 'twentyfifteen', 'twentyfourteen', 'twentythirteen', 'twentytwelve', 'twentyeleven', 'twentyten' );

	private static $modes = null;
	private static $normalized = array();
	private static $found_themes = null;

	/* --------------------------------------------------------------------------------------------- Verificación */

	// Con el sitio intacto (al final de la verificación): si el respaldo es de otra instalación (la identidad de H23, un
	// sitio extraído o una migración; nunca la vuelta a la copia previa), si trae la carpeta de este plugin y cómo está
	// activo aquí, qué drop-ins trae que no se pondrán y qué tablas propias de aquí no trae (brecha 17, sólo se informa).
	public static function plan( $staging, array $job, $foreign, array $layout, $zip, array $rows, $parts ) {
		global $wpdb;
		$plugin = Digitalisimo_Backups_Restore_Maintenance::plugin();
		$self = (string) ( $layout['self'] ?? '' );
		// Importar un sitio en una red (Digitalisimo_Backups_Site_Import): sólo vuelven sus tablas y sus medios; ni código,
		// ni drop-ins, ni las tablas de la red, que no son «de más».
		$import = is_array( $job['spec']['into'] ?? null );
		$was = '';
		if ( is_multisite() && isset( ( (array) get_site_option( 'active_sitewide_plugins', array() ) )[ $plugin ] ) ) $was = 'network';
		elseif ( in_array( $plugin, (array) get_option( 'active_plugins', array() ), true ) ) $was = 'site';
		$out = array(
			'v' => 1,
			'foreign' => (bool) $foreign,
			'import' => $import,
			// Sólo importa si la carpeta de este plugin está en este sitio (un plugin cargado desde otra carpeta no se retira).
			'self' => array( 'plugin' => $plugin, 'dir' => $self, 'in_backup' => $import || '' === $self || ! is_dir( $layout['root'] . $self ) || false !== $zip->locateName( 'wordpress/' . $self . '/' . basename( $plugin ) ), 'was' => $was ),
			'dropins' => array(),
			'extra_tables' => array(),
			'extra_count' => 0,
			// Revisión bk-180 (H9): si este servidor usa .htaccess, decidido ahora y no en el último tramo (ver server()).
			'server' => self::server( $job, $layout ),
		);
		if ( $foreign && ! $import && '' !== (string) $layout['content'] ) {
			foreach ( Digitalisimo_Backups_Site_Extract::DROP_INS as $name ) {
				$relative = $layout['content'] . '/' . $name;
				$stat = $zip->statName( 'wordpress/' . $relative );
				if ( ! $stat || ( null !== $parts && ! Digitalisimo_Backups_Restore_Parts::selected( $parts, $relative, $layout ) ) ) continue;
				$live = $layout['root'] . $relative;
				$here = is_file( $live ) && ! is_link( $live );
				$same = $here && (int) $stat['size'] === (int) @filesize( $live ) && (int) $stat['size'] <= 4194304 && hash_equals( hash( 'sha256', (string) $zip->getFromName( 'wordpress/' . $relative ) ), (string) @hash_file( 'sha256', $live ) );
				if ( ! $same ) $out['dropins'][ $name ] = $here ? 'kept' : 'skipped';
			}
		}
		// Brecha 17 (decisión: se informan, no se apartan): tablas con el prefijo de este WordPress que el respaldo de otra
		// instalación no trae. Las de una migración ya se apartan (`exact_tables`); las de otra instalación vecina no son suyas.
		if ( $foreign && ! $import && $rows && empty( $job['spec']['exact_tables'] ) && is_object( $wpdb ) ) {
			$base = (string) $wpdb->base_prefix;
			$dests = array_flip( array_map( 'strval', array_column( $rows, 'dest' ) ) );
			$neighbors = (array) ( self::read( $staging, 'neighbors.json' )['prefixes'] ?? array() );
			$extra = array();
			foreach ( (array) $wpdb->get_col( 'SHOW TABLES' ) as $table ) {
				$table = (string) $table;
				if ( '' === $base || 0 !== strncmp( $table, $base, strlen( $base ) ) || isset( $dests[ $table ] ) || preg_match( '/dgbk[rok]_/', $table ) ) continue;
				foreach ( $neighbors as $prefix ) if ( '' !== (string) $prefix && 0 === strncmp( $table, (string) $prefix, strlen( (string) $prefix ) ) ) continue 2;
				$extra[] = $table;
			}
			sort( $extra );
			$out['extra_count'] = count( $extra );
			$out['extra_tables'] = array_slice( $extra, 0, 20 );
		}
		self::write( $staging, self::FILE, $out );
		return $out;
	}

	/* --------------------------------------------------------------------------------------------- Archivos */

	// Brecha 5: un drop-in de wp-content (db.php, object-cache.php, advanced-cache.php, sunrise.php…) del respaldo de otra
	// instalación es de su servidor: no se prepara ni se pone (la regla de «Extraer un sitio de la red»); el de aquí, si
	// hay, se queda (los drop-ins están fuera de las raíces gestionadas, así que nunca se retiran). Mismo sitio: como siempre.
	public static function skip_entry( $staging, $relative, array $layout ) {
		if ( '' === (string) $layout['content'] ) return false;
		$prefix = $layout['content'] . '/';
		if ( 0 !== strpos( (string) $relative, $prefix ) || ! in_array( substr( (string) $relative, strlen( $prefix ) ), Digitalisimo_Backups_Site_Extract::DROP_INS, true ) ) return false;
		return ! empty( self::cached( $staging )['foreign'] );
	}
	// Brecha 22: la carpeta de este plugin no es «ajena» si el respaldo no la trae (un paquete importado de Backuply o de
	// otro plugin): se queda tal cual, porque es la que termina la restauración y la que la deshace.
	public static function keep_live( $staging, $unit, array $layout ) {
		if ( '' === (string) $unit || (string) $unit !== (string) ( $layout['self'] ?? '' ) ) return false;
		$compat = self::cached( $staging );
		return isset( $compat['self']['in_backup'] ) && ! $compat['self']['in_backup'];
	}
	// Brecha 6: el área de trabajo es privada (`0700`) y wp_mkdir_p() hereda los permisos de la primera carpeta que existe,
	// así que cada carpeta preparada nacía `0700` y una carpeta de código entera (cada plugin y tema, wp-admin, wp-includes)
	// se ponía así con rename(): donde el servidor web no es el usuario de PHP, su CSS, JS e imágenes respondían 403. Cada
	// archivo preparado y sus carpetas (hasta la raíz del área) reciben los permisos de WordPress (FS_CHMOD_FILE y
	// FS_CHMOD_DIR, o su valor por defecto) sumados a los que ya tenían y a los del archivo o carpeta que reemplazan: nunca
	// se quita un permiso que había. El área sigue privada: su carpeta raíz no cambia.
	public static function stage_mode( $base, $relative ) {
		if ( DIRECTORY_SEPARATOR !== '/' ) return;
		$modes = self::modes();
		$relative = (string) $relative;
		self::union( $base . '/' . $relative, $modes['file'], $modes['root'] . $relative, false );
		$dir = dirname( $relative );
		while ( '.' !== $dir && '' !== $dir && '/' !== $dir ) {
			$staged = $base . '/' . $dir;
			if ( isset( self::$normalized[ $staged ] ) ) break;
			self::$normalized[ $staged ] = true;
			self::union( $staged, $modes['dir'], $modes['root'] . $dir, true );
			$dir = dirname( $dir );
		}
	}
	// Copia entre discos (una carpeta o un archivo que cruza de volumen): la copia lleva los permisos de lo preparado.
	public static function same_mode( $from, $to ) {
		if ( DIRECTORY_SEPARATOR !== '/' ) return true;
		$mode = @fileperms( $from );
		if ( false !== $mode && ! is_link( $to ) ) @chmod( $to, $mode & 02777 );
		return true;
	}
	// Permisos de WordPress para lo que escribe: las constantes de wp-config.php o el valor que les da WP_Filesystem().
	public static function modes() {
		if ( null !== self::$modes ) return self::$modes;
		$root = trailingslashit( wp_normalize_path( (string) realpath( ABSPATH ) ) );
		$abspath = @fileperms( ABSPATH );
		$index = @fileperms( ABSPATH . 'index.php' );
		$dir = defined( 'FS_CHMOD_DIR' ) ? (int) FS_CHMOD_DIR : ( ( false === $abspath ? 0755 : ( $abspath & 0777 ) ) | 0755 );
		$file = defined( 'FS_CHMOD_FILE' ) ? (int) FS_CHMOD_FILE : ( ( false === $index ? 0644 : ( $index & 0777 ) ) | 0644 );
		return self::$modes = array( 'root' => $root, 'dir' => $dir & 0777, 'file' => $file & 0777 );
	}
	private static function union( $path, $default, $live, $is_dir ) {
		clearstatcache( true, $path );
		if ( is_link( $path ) ) return;
		$now = @fileperms( $path );
		if ( false === $now ) return;
		$had = 0;
		if ( ! is_link( $live ) && ( $is_dir ? is_dir( $live ) : is_file( $live ) ) ) $had = (int) @fileperms( $live );
		// El bit setgid de una carpeta (herencia de grupo de un hosting compartido) también se conserva.
		$mask = $is_dir ? 02777 : 0777;
		$want = ( ( $now & 0777 ) | $default | ( $had & $mask ) ) & $mask;
		if ( ( $now & $mask ) !== $want ) @chmod( $path, $want );
	}

	/* --------------------------------------------------------------------------------------------- Cambio de tablas */

	// Brecha 22: justo antes del RENAME, en la tabla temporal de opciones (o en `sitemeta` si aquí está activo en la red),
	// este plugin queda activo como lo estaba aquí si la base de datos del respaldo no lo activa (un paquete de Backuply, o
	// un origen que lo tenía desactivado). Sin esto, después del cambio los ticks (que sólo cargan este plugin si está activo)
	// no lo cargarían y la restauración se quedaría en mantenimiento. Otra copia de DIGITALÍSIMO Backups activa en el
	// respaldo (otra carpeta) se desactiva: dos a la vez son un error fatal. Idempotente; nunca hace fallar el cambio.
	public static function activate( $staging, array $tables ) {
		global $wpdb;
		$compat = self::read( $staging );
		$plugin = (string) ( $compat['self']['plugin'] ?? '' );
		$was = (string) ( $compat['self']['was'] ?? '' );
		if ( '' === $plugin || '' === $was || ! is_object( $wpdb ) ) return;
		try {
			$temp = array();
			foreach ( $tables as $row ) $temp[ (string) ( $row['dest'] ?? '' ) ] = (string) ( $row['temp'] ?? '' );
			$base = (string) $wpdb->base_prefix;
			$options = (string) ( $temp[ $base . 'options' ] ?? '' );
			$sitemeta = is_multisite() ? (string) ( $temp[ $base . 'sitemeta' ] ?? '' ) : '';
			if ( '' === $options && '' === $sitemeta ) return;
			// Activo en la red y la red no cambia (`sitemeta` no entra): sigue activo.
			if ( 'network' === $was && '' === $sitemeta ) return;
			$network = is_multisite() && function_exists( 'get_current_network_id' ) ? (int) get_current_network_id() : 1;
			$list = '' === $options ? array() : self::unserialize_array( $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM ' . self::q( $options ) . ' WHERE option_name = %s', 'active_plugins' ) ) );
			$wide = '' === $sitemeta ? array() : self::unserialize_array( $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM ' . self::q( $sitemeta ) . ' WHERE meta_key = %s AND site_id = %d', 'active_sitewide_plugins', $network ) ) );
			if ( in_array( $plugin, $list, true ) || isset( $wide[ $plugin ] ) ) return;
			$main = basename( $plugin );
			$replaced = array();
			foreach ( $list as $n => $other ) if ( is_string( $other ) && basename( $other ) === $main ) { $replaced[] = $other; unset( $list[ $n ] ); }
			foreach ( array_keys( $wide ) as $other ) if ( is_string( $other ) && basename( $other ) === $main ) { $replaced[] = $other; unset( $wide[ $other ] ); }
			$mode = 'network' === $was && '' !== $sitemeta ? 'network' : 'site';
			if ( 'network' === $mode ) $wide[ $plugin ] = time();
			else $list[] = $plugin;
			$list = array_values( array_filter( $list, 'is_string' ) );
			sort( $list );
			if ( '' !== $options && ( 'site' === $mode || $replaced ) ) self::put_value( $options, 'option_name', 'option_value', 'active_plugins', serialize( $list ) );
			if ( '' !== $sitemeta && ( 'network' === $mode || $replaced ) ) self::put_value( $sitemeta, 'meta_key', 'meta_value', 'active_sitewide_plugins', serialize( $wide ), $network );
			self::write( $staging, 'activation.json', array( 'activated' => $mode, 'replaced' => array_values( array_unique( $replaced ) ) ) );
		} catch ( Throwable $e ) {
			self::write( $staging, 'activation.json', array( 'error' => $e->getMessage() ) );
		}
	}
	private static function put_value( $table, $key_column, $value_column, $key, $value, $site = null ) {
		global $wpdb;
		$where = null === $site ? $wpdb->prepare( ' WHERE ' . $key_column . ' = %s', $key ) : $wpdb->prepare( ' WHERE ' . $key_column . ' = %s AND site_id = %d', $key, $site );
		$exists = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::q( $table ) . $where );
		if ( (int) $exists > 0 ) {
			if ( false === $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::q( $table ) . ' SET ' . $value_column . ' = %s', $value ) . $where ) ) throw new Exception( (string) $wpdb->last_error );
			return;
		}
		$sql = null === $site
			? $wpdb->prepare( 'INSERT INTO ' . self::q( $table ) . ' (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)', $key, $value, 'yes' )
			: $wpdb->prepare( 'INSERT INTO ' . self::q( $table ) . ' (`site_id`, `meta_key`, `meta_value`) VALUES (%s, %s, %s)', (string) $site, $key, $value );
		if ( false === $wpdb->query( $sql ) ) throw new Exception( (string) $wpdb->last_error );
	}

	/* --------------------------------------------------------------------------------------------- Limpieza */

	// Lo de después de cualquier restauración (también en el mismo sitio, y en la vuelta a la copia previa), por tramos:
	// `pages` caché de páginas y de constructores en disco (brechas 9 y 20), `builders` CSS generado de Elements/Elementor
	// si la base de datos y sus archivos no vinieron juntos (20), `theme` tema que no existe (19) y `origin` servicios del
	// origen que siguen conectados en la copia (21). $state es el de la llamada anterior; nunca lanza.
	public static function cleanup( array $state, $deadline, array $job, array $layout, $staging, $with_database, $parts ) {
		$state += array( 'step' => 'pages', 'files' => 0, 'dirs' => array(), 'notes' => array(), 'warnings' => array() );
		$state['units'] = 0;
		$ctx = array( 'job' => $job, 'layout' => $layout, 'root' => $layout['root'], 'staging' => $staging, 'database' => (bool) $with_database, 'parts' => $parts, 'compat' => self::read( $staging ) );
		$steps = array( 'pages', 'builders', 'theme', 'origin', 'done' );
		while ( 'done' !== $state['step'] && in_array( $state['step'], $steps, true ) ) {
			try {
				if ( 'pages' === $state['step'] && ! self::empty_caches( $ctx, $state, $deadline ) ) return $state;
				if ( 'builders' === $state['step'] ) self::builder_css( $ctx, $state );
				if ( 'theme' === $state['step'] ) self::theme( $ctx, $state );
				if ( 'origin' === $state['step'] ) self::origin_services( $ctx, $state );
			} catch ( Throwable $e ) {
				$state['warnings'][] = 'No se pudo terminar «' . $state['step'] . '» de la limpieza: ' . $e->getMessage();
			}
			$state['step'] = $steps[ array_search( $state['step'], $steps, true ) + 1 ];
		}
		$state['step'] = 'done';
		$state['done'] = true;
		return $state;
	}
	// Carpetas de caché de este sitio (relativas a la raíz) que existen.
	private static function cache_dirs( array $ctx ) {
		$layout = $ctx['layout'];
		$root = $ctx['root'];
		$out = array();
		if ( '' !== (string) $layout['content'] ) foreach ( self::CONTENT_CACHES as $name ) $out[] = $layout['content'] . '/' . $name;
		if ( defined( 'ET_CORE_CACHE_DIR' ) ) {
			$divi = wp_normalize_path( (string) ET_CORE_CACHE_DIR );
			if ( 0 === strpos( $divi, $root ) ) $out[] = rtrim( substr( $divi, strlen( $root ) ), '/' );
		}
		if ( '' !== (string) $layout['uploads'] ) {
			$bases = array( $layout['uploads'] );
			foreach ( (array) glob( $root . $layout['uploads'] . '/sites/*', GLOB_ONLYDIR ) as $site ) if ( preg_match( '#/sites/[0-9]+$#', (string) $site ) ) $bases[] = $layout['uploads'] . '/sites/' . basename( (string) $site );
			foreach ( $bases as $base ) foreach ( self::UPLOAD_CACHES as $name ) $out[] = $base . '/' . $name;
		}
		return array_values( array_unique( array_filter( $out, function( $relative ) use ( $root ) { return '' !== $relative && false === strpos( '/' . $relative . '/', '/../' ) && is_dir( $root . $relative ) && ! is_link( $root . $relative ); } ) ) );
	}
	private static function empty_caches( array $ctx, array &$state, $deadline ) {
		foreach ( self::cache_dirs( $ctx ) as $relative ) {
			$path = $ctx['root'] . $relative;
			$real = realpath( $path );
			if ( false === $real || 0 !== strpos( trailingslashit( wp_normalize_path( $real ) ), $ctx['root'] ) ) continue;
			$before = (int) $state['files'];
			if ( ! self::empty_dir( $path, $deadline, $state ) ) return false;
			if ( (int) $state['files'] > $before && ! in_array( $relative, $state['dirs'], true ) ) $state['dirs'][] = $relative;
		}
		return true;
	}
	// Vacía una carpeta de caché sin seguir enlaces (un enlace se retira; su destino no se toca) y sin borrarla. Se quedan
	// los `.htaccess` y `web.config`: son reglas, no páginas guardadas. Devuelve false si el plazo cortó el tramo.
	private static function empty_dir( $dir, $deadline, array &$state ) {
		$items = @scandir( $dir );
		if ( ! is_array( $items ) ) return true;
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) continue;
			if ( $state['units'] > 0 && microtime( true ) >= $deadline ) return false;
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				if ( ! self::empty_dir( $path, $deadline, $state ) ) return false;
				@rmdir( $path );
				continue;
			}
			if ( in_array( strtolower( $item ), array( '.htaccess', 'web.config' ), true ) && ! is_link( $path ) ) continue;
			if ( @unlink( $path ) ) { $state['files']++; $state['units']++; }
		}
		return true;
	}
	// Brecha 20: el CSS que Elements/Elementor genera por página vive en los medios (`elementor/css`) y su estado en la
	// base de datos (`_elementor_css` y las opciones globales). Si vino una cosa sin la otra (restaurar por partes: la base
	// sin los medios, o los medios sin la base), las páginas cargarían un CSS de otro momento: se vacía todo y se regenera
	// en la primera visita, como al reubicar (E1). Con los dos o con ninguno, nada.
	private static function builder_css( array $ctx, array &$state ) {
		global $wpdb;
		$layout = $ctx['layout'];
		if ( '' === (string) $layout['uploads'] || null === $ctx['parts'] ) return;
		$cleared = 0;
		$rows = 0;
		foreach ( self::site_tables( $ctx ) as $site => $tables ) {
			$dir = $layout['uploads'] . ( is_multisite() && $site > 1 ? '/sites/' . $site : '' ) . '/elementor/css';
			$media = Digitalisimo_Backups_Restore_Parts::selected( $ctx['parts'], $dir . '/post-1.css', $layout );
			if ( $media === $ctx['database'] ) continue;
			foreach ( (array) glob( $ctx['root'] . $dir . '/*' ) as $file ) if ( is_file( $file ) && ! is_link( $file ) && @unlink( $file ) ) $cleared++;
			if ( isset( $tables['postmeta'] ) ) $rows += (int) $wpdb->query( 'DELETE FROM ' . self::q( $tables['postmeta'] ) . " WHERE meta_key IN ('_elementor_css', '_elementor_element_cache', '_elementor_page_assets')" );
			if ( isset( $tables['options'] ) ) $rows += (int) $wpdb->query( 'DELETE FROM ' . self::q( $tables['options'] ) . " WHERE option_name IN ('_elementor_global_css', 'elementor-custom-breakpoints-files', '_elementor_assets_data')" );
			$state['builders'] = true;
		}
		if ( ! empty( $state['builders'] ) ) {
			$state['notes'][] = 'Se vació el CSS generado de DIGITALÍSIMO Elements (o Elementor), porque la base de datos y los medios no se restauraron juntos: se regenera en la primera visita de cada página.';
			// Las metas y opciones vaciadas por SQL, en cada sitio: sólo lo de esta instalación, nunca la caché global.
			if ( class_exists( 'Digitalisimo_Same_Site', false ) ) Digitalisimo_Backups::cache_invalidate( 'all' );
		}
	}
	// Brecha 19: si el tema activo de un sitio (o su padre) no existe o no es válido después de restaurar (sólo la base de
	// datos, un tema de otro nombre, un respaldo de otro servidor), la portada saldría en blanco: vuelve al tema por defecto
	// de WordPress que sí está, como validate_current_theme(), pero sin switch_theme() (su gancho correría con WordPress
	// mínimo) y nunca al tema DIGITALÍSIMO, que sólo activa el administrador. Antes, un nombre con otras mayúsculas que sí
	// existe en minúsculas (un respaldo de Windows) se corrige.
	private static function theme( array $ctx, array &$state ) {
		global $wpdb;
		$changed = false;
		self::$found_themes = null;
		foreach ( self::site_tables( $ctx ) as $site => $tables ) {
			if ( ! isset( $tables['options'] ) ) continue;
			$opts = self::options_of( $tables['options'], array( 'template', 'stylesheet', 'template_root', 'stylesheet_root' ) );
			$template = (string) ( $opts['template'] ?? '' );
			$stylesheet = (string) ( $opts['stylesheet'] ?? '' );
			if ( '' === $template && '' === $stylesheet ) continue;
			if ( '' === $template ) $template = $stylesheet;
			if ( '' === $stylesheet ) $stylesheet = $template;
			if ( self::theme_valid( $ctx, $template, $stylesheet, $opts ) ) continue;
			$where = is_multisite() ? ' (sitio ' . (int) $site . ')' : '';
			$lower = array( strtolower( $template ), strtolower( $stylesheet ) );
			if ( $lower !== array( $template, $stylesheet ) && self::theme_valid( $ctx, $lower[0], $lower[1], $opts ) ) {
				self::set_option( $tables['options'], 'template', $lower[0] );
				self::set_option( $tables['options'], 'stylesheet', $lower[1] );
				$state['notes'][] = 'El tema activo' . $where . ' estaba guardado como «' . $stylesheet . '» y en el disco es «' . $lower[1] . '»: se corrigió el nombre.';
				$changed = true;
				continue;
			}
			$fallback = self::default_theme( $ctx );
			if ( '' === $fallback ) {
				$state['warnings'][] = 'El tema activo' . $where . ' («' . $stylesheet . '») no existe en este servidor y no hay un tema por defecto de WordPress instalado: sube ese tema o instala uno y actívalo, o la portada saldrá en blanco.';
				continue;
			}
			self::set_option( $tables['options'], 'template', $fallback );
			self::set_option( $tables['options'], 'stylesheet', $fallback );
			$wpdb->query( 'DELETE FROM ' . self::q( $tables['options'] ) . " WHERE option_name IN ('template_root', 'stylesheet_root')" );
			$name = self::theme_name( $ctx, $fallback );
			if ( '' !== $name ) self::set_option( $tables['options'], 'current_theme', $name, false );
			$state['theme'][] = array( 'site' => (int) $site, 'from' => $stylesheet, 'to' => $fallback );
			$state['notes'][] = 'El tema activo' . $where . ' («' . $stylesheet . '») no existe en este servidor: se activó «' . $fallback . '», el tema por defecto de WordPress, para que la portada no salga en blanco. Sube ese tema y vuelve a activarlo si lo necesitas.';
			$changed = true;
		}
		if ( $changed && class_exists( 'Digitalisimo_Same_Site', false ) ) Digitalisimo_Same_Site::invalidate( array( 'template', 'stylesheet', 'template_root', 'stylesheet_root', 'current_theme' ), array(), 'all' );
	}
	private static function theme_dir( array $ctx, $slug, $root_option ) {
		if ( ! Digitalisimo_Backups_Site_Extract::safe_name( $slug ) ) return '';
		$roots = array();
		$root_option = (string) $root_option;
		if ( '' !== $root_option && false === strpos( $root_option, '..' ) ) {
			if ( defined( 'WP_CONTENT_DIR' ) ) $roots[] = rtrim( WP_CONTENT_DIR, '/' ) . '/' . ltrim( $root_option, '/' );
			$roots[] = rtrim( $root_option, '/' );
		}
		foreach ( self::theme_roots( $slug ) as $root ) $roots[] = $root;
		if ( '' !== (string) $ctx['layout']['themes'] ) $roots[] = $ctx['root'] . $ctx['layout']['themes'];
		foreach ( $roots as $root ) if ( '' !== $root && is_dir( $root . '/' . $slug ) && ! is_link( $root . '/' . $slug ) ) return $root . '/' . $slug;
		return '';
	}
	// Revisión bk-180 (H8): las carpetas de temas de WordPress, también fuera de ABSPATH (Bedrock: `web/app/themes`, donde
	// la carpeta de temas de la restauración por pasos es '' y antes todo tema salía «inexistente»): la de ese tema según
	// search_theme_directories() (todas las registradas con register_theme_directory(), vueltas a leer: la restauración
	// pudo cambiarlas) y get_theme_root(), con su filtro `theme_root`.
	private static function theme_roots( $slug ) {
		$roots = array();
		if ( function_exists( 'search_theme_directories' ) ) {
			if ( null === self::$found_themes ) {
				$found = search_theme_directories( true );
				self::$found_themes = is_array( $found ) ? $found : array();
			}
			if ( isset( self::$found_themes[ $slug ]['theme_root'] ) && is_string( self::$found_themes[ $slug ]['theme_root'] ) ) $roots[] = rtrim( self::$found_themes[ $slug ]['theme_root'], '/\\' );
		}
		if ( function_exists( 'get_theme_root' ) ) $roots[] = rtrim( (string) get_theme_root( $slug ), '/\\' );
		return $roots;
	}
	private static function theme_valid( array $ctx, $template, $stylesheet, array $opts ) {
		$parent = self::theme_dir( $ctx, $template, (string) ( $opts['template_root'] ?? '' ) );
		if ( '' === $parent || ! is_file( $parent . '/style.css' ) ) return false;
		if ( ! is_file( $parent . '/index.php' ) && ! is_file( $parent . '/templates/index.html' ) && ! is_file( $parent . '/block-templates/index.html' ) ) return false;
		if ( $stylesheet === $template ) return true;
		$child = self::theme_dir( $ctx, $stylesheet, (string) ( $opts['stylesheet_root'] ?? '' ) );
		return '' !== $child && is_file( $child . '/style.css' );
	}
	private static function default_theme( array $ctx ) {
		$candidates = array();
		if ( defined( 'WP_DEFAULT_THEME' ) ) $candidates[] = (string) WP_DEFAULT_THEME;
		if ( class_exists( 'WP_Theme', false ) && method_exists( 'WP_Theme', 'get_core_default_theme' ) && function_exists( 'wp_get_theme' ) ) {
			try {
				$core = WP_Theme::get_core_default_theme();
				if ( is_object( $core ) ) $candidates[] = (string) $core->get_stylesheet();
			} catch ( Throwable $e ) {} // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
		}
		foreach ( array_unique( array_merge( $candidates, self::CORE_THEMES ) ) as $slug ) {
			if ( self::ours_theme( $ctx, $slug ) ) continue;
			if ( self::theme_valid( $ctx, $slug, $slug, array() ) ) return $slug;
		}
		return '';
	}
	// El tema DIGITALÍSIMO se reconoce por su carpeta habitual o por su `Update URI` (AGENTS.md: nunca se activa solo).
	private static function ours_theme( array $ctx, $slug ) {
		if ( 'digitalisimo-theme' === $slug ) return true;
		$dir = self::theme_dir( $ctx, $slug, '' );
		$head = '' !== $dir && is_file( $dir . '/style.css' ) ? (string) @file_get_contents( $dir . '/style.css', false, null, 0, 8192 ) : '';
		return false !== stripos( $head, 'digitalisimo-plugin/digitalisimo-theme' );
	}
	private static function theme_name( array $ctx, $slug ) {
		$dir = self::theme_dir( $ctx, $slug, '' );
		$head = '' !== $dir ? (string) @file_get_contents( $dir . '/style.css', false, null, 0, 8192 ) : '';
		return preg_match( '/^[ \t\/*#@]*Theme Name:(.*)$/mi', $head, $m ) ? substr( trim( strip_tags( $m[1] ) ), 0, 100 ) : '';
	}
	// Brecha 21: con el respaldo de otra instalación, lo que sigue conectado al sitio de origen se avisa (no se borra en
	// silencio): Jetpack con la identidad del origen y la CDN de WP Rocket del origen.
	private static function origin_services( array $ctx, array &$state ) {
		global $wpdb;
		if ( empty( $ctx['compat']['foreign'] ) || ! $ctx['database'] ) return;
		$wide = array();
		if ( is_multisite() ) {
			$sitemeta = (string) $wpdb->base_prefix . 'sitemeta';
			$row = $wpdb->get_var( $wpdb->prepare( 'SELECT meta_value FROM ' . self::q( $sitemeta ) . ' WHERE meta_key = %s', 'active_sitewide_plugins' ) );
			$wide = array_keys( self::unserialize_array( $row ) );
		}
		$jetpack = array();
		$cdn = array();
		foreach ( self::site_tables( $ctx ) as $site => $tables ) {
			if ( ! isset( $tables['options'] ) ) continue;
			$opts = self::options_of( $tables['options'], array( 'active_plugins', 'jetpack_options', 'wp_rocket_settings' ) );
			$active = array_merge( $wide, array_values( self::unserialize_array( $opts['active_plugins'] ?? '' ) ) );
			$on = function( $main ) use ( $active ) { foreach ( $active as $plugin ) if ( is_string( $plugin ) && basename( $plugin ) === $main ) return true; return false; };
			$options = self::unserialize_array( $opts['jetpack_options'] ?? '' );
			if ( $on( 'jetpack.php' ) && ! empty( $options['id'] ) ) $jetpack[] = (int) $site;
			$rocket = self::unserialize_array( $opts['wp_rocket_settings'] ?? '' );
			if ( $on( 'wp-rocket.php' ) && ! empty( $rocket['cdn'] ) && ! empty( $rocket['cdn_cnames'] ) ) {
				foreach ( (array) $rocket['cdn_cnames'] as $cname ) {
					$cname = substr( preg_replace( '/[^A-Za-z0-9.:\/_-]/', '', (string) $cname ), 0, 100 );
					if ( '' !== $cname ) $cdn[ $cname ] = true;
				}
			}
		}
		$sites = function( array $ids ) { return is_multisite() ? ' (sitios ' . implode( ', ', array_slice( $ids, 0, 10 ) ) . ')' : ''; };
		if ( $jetpack ) $state['warnings'][] = 'Jetpack sigue conectado con la identidad del sitio de origen' . $sites( $jetpack ) . ': desconéctalo y vuelve a conectarlo desde Jetpack antes de usar esta copia, o WordPress.com la tomará por el sitio de origen.';
		if ( $cdn ) $state['warnings'][] = 'WP Rocket sirve los archivos desde la CDN del sitio de origen (' . implode( ', ', array_slice( array_keys( $cdn ), 0, 3 ) ) . '): revisa «CDN» en WP Rocket para que esta copia no cargue los archivos del origen.';
	}

	/* --------------------------------------------------------------------------------------------- Final */

	// Brecha 12: con un servidor que usa .htaccess (Apache, LiteSpeed) y enlaces permanentes, el bloque «WordPress» de la
	// raíz debe existir y apuntar a la ruta del sitio, aunque la ruta no cambie: un respaldo de nginx (sin .htaccess)
	// restaurado aquí dejaba todas las páginas internas en 404. Si falta, o sus reglas son de otra ruta, se escribe como
	// lo hace WordPress al guardar los enlaces permanentes, entre sus marcas y sin tocar nada fuera de ellas (las reglas
	// propias de este sitio se quedan); un bloque correcto no se toca (puede llevar lo que añaden los plugins). Se escribe
	// una sola vez y al final: en el .htaccess del respaldo ya preparado (que el paso siguiente pone en su sitio) o en el de
	// aquí. En una red sólo se avisa (sus reglas son las de la configuración de la red). El filtro
	// `digitalisimo_backups_restore_htaccess` fuerza (true) o apaga (false) la comprobación.
	public static function htaccess( $tree, array $layout, $staging ) {
		try {
			$result = self::htaccess_fix( (string) $tree, $layout, $staging );
		} catch ( Throwable $e ) {
			$result = array( 'state' => 'failed', 'error' => $e->getMessage() );
		}
		if ( $result ) self::write( $staging, 'htaccess.json', $result );
		return $result;
	}
	private static function htaccess_fix( $tree, array $layout, $staging ) {
		global $wp_rewrite;
		$forced = function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_restore_htaccess', null ) : null;
		if ( false === $forced ) return null;
		// Lo decidido en la verificación (compat.json); una restauración verificada por una versión anterior, como antes.
		$server = true === $forced ? 'apache' : (string) ( self::read( $staging )['server'] ?? '' );
		if ( '' === $server ) $server = self::web_server() ?: 'unknown';
		if ( 'other' === $server || '' === (string) get_option( 'permalink_structure' ) ) return null;
		$home = '/' . trim( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ), '/' );
		$site = '/' . trim( (string) wp_parse_url( (string) get_option( 'siteurl' ), PHP_URL_PATH ), '/' );
		// WordPress en su propia carpeta: su .htaccess vive fuera de esta raíz.
		if ( $home !== $site ) return null;
		$base = '/' === $home ? '/' : $home . '/';
		$staged = '' !== $tree ? $tree . '/new/.htaccess' : '';
		$use_staged = '' !== $staged && is_file( $staged ) && ! is_link( $staged );
		$file = $use_staged ? $staged : $layout['root'] . '.htaccess';
		if ( is_link( $file ) || ( file_exists( $file ) && ! is_file( $file ) ) ) return null;
		$text = is_file( $file ) ? (string) @file_get_contents( $file, false, null, 0, 1048576 ) : '';
		$block = self::wp_block( $text );
		if ( is_multisite() ) return null === $block ? array( 'state' => 'apache' === $server ? 'network_missing' : 'skipped_cli' ) : null;
		if ( null !== $block && preg_match( '/^\s*RewriteBase\s+' . preg_quote( $base, '/' ) . '\s*$/mi', $block ) && preg_match( '/^\s*RewriteRule\s+\.\s+' . preg_quote( $base, '/' ) . 'index\.php\b/mi', $block ) ) return null;
		if ( 'apache' !== $server ) return array( 'state' => 'skipped_cli' );
		if ( ! is_object( $wp_rewrite ) || ! method_exists( $wp_rewrite, 'mod_rewrite_rules' ) ) return array( 'state' => 'failed', 'error' => 'sin WP_Rewrite' );
		if ( method_exists( $wp_rewrite, 'init' ) ) $wp_rewrite->init();
		$rules = trim( (string) $wp_rewrite->mod_rewrite_rules() );
		if ( '' === $rules ) return null;
		$new = self::with_block( $text, $rules );
		$mode = is_file( $file ) ? ( (int) @fileperms( $file ) & 0777 ) : self::modes()['file'];
		$temp = $file . '.digitalisimo-' . bin2hex( random_bytes( 4 ) );
		if ( strlen( $new ) !== @file_put_contents( $temp, $new ) || ! @rename( $temp, $file ) ) {
			@unlink( $temp );
			return array( 'state' => 'failed', 'error' => 'no se pudo escribir' );
		}
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $file, $mode );
		return array( 'state' => null === $block ? 'added' : 'fixed', 'base' => $base );
	}
	// Revisión bk-180 (H9): $is_apache sólo es verdadero en una petición web (WordPress lo deduce de SERVER_SOFTWARE), así
	// que antes el bloque de .htaccess dependía de quién corría el último tramo: en el mismo Apache, la web lo escribía y
	// WP-CLI o el cron del sistema (PHP CLI) no, sin decirlo. Ahora se decide una vez, en una petición web: la que lanza la
	// restauración (la pantalla o REST, anotada al encolar en `origin.web_server`) o, si ésa fue de WP-CLI, la de la
	// verificación. Sin ninguna petición web, cuenta el .htaccess de aquí: si tiene el bloque de WordPress, WordPress lo
	// escribió porque el servidor lo usa. Si tampoco, `unknown`: el bloque no se toca y el informe lo dice (`skipped_cli`).
	// 'apache' (Apache o LiteSpeed con mod_rewrite), 'other' (una petición web de nginx, IIS…) o '' (PHP CLI: no se sabe).
	public static function web_server() {
		global $is_apache;
		if ( ! empty( $is_apache ) ) return ! function_exists( 'apache_mod_loaded' ) || apache_mod_loaded( 'mod_rewrite', true ) ? 'apache' : 'other';
		return 'cli' === PHP_SAPI ? '' : 'other';
	}
	private static function server( array $job, array $layout ) {
		$server = (string) ( $job['spec']['origin']['web_server'] ?? '' );
		if ( in_array( $server, array( 'apache', 'other' ), true ) ) return $server;
		$server = self::web_server();
		if ( '' !== $server ) return $server;
		$live = (string) ( $layout['root'] ?? '' ) . '.htaccess';
		$text = is_file( $live ) && ! is_link( $live ) ? (string) @file_get_contents( $live, false, null, 0, 1048576 ) : '';
		return null !== self::wp_block( $text ) ? 'apache' : 'unknown';
	}
	// El contenido entre `# BEGIN WordPress` y `# END WordPress`, o null si no están.
	private static function wp_block( $text ) {
		if ( ! preg_match( '/^[ \t]*# BEGIN WordPress[ \t]*\r?$(.*?)^[ \t]*# END WordPress[ \t]*\r?$/ms', (string) $text, $m ) ) return null;
		return $m[1];
	}
	private static function with_block( $text, $rules ) {
		$block = "# BEGIN WordPress\n# The directives (lines) between \"BEGIN WordPress\" and \"END WordPress\" are\n# dynamically generated, and should only be modified via WordPress filters.\n# Any changes to the directives between these markers will be overwritten.\n" . str_replace( array( "\r\n", "\r" ), "\n", $rules ) . "\n# END WordPress";
		$count = 0;
		$out = preg_replace_callback( '/^[ \t]*# BEGIN WordPress[ \t]*\r?$.*?^[ \t]*# END WordPress[ \t]*\r?$/ms', function() use ( $block ) { return $block; }, (string) $text, 1, $count );
		if ( $count ) return $out;
		$text = rtrim( (string) $text );
		return ( '' === $text ? '' : $text . "\n\n" ) . $block . "\n";
	}
	// Lo que esta restauración hizo de más (y lo que conviene revisar), añadido al informe público: el texto del aviso y la
	// clave `compat`, sin rutas del servidor ni secretos. $public es restore_public_text() del motor.
	public static function report( array $result, $staging, array $cleanup, callable $public ) {
		$compat = self::read( $staging );
		$activation = self::read( $staging, 'activation.json' );
		$htaccess = self::read( $staging, 'htaccess.json' );
		$lines = array();
		$dropins = (array) ( $compat['dropins'] ?? array() );
		$skipped = array_keys( array_filter( $dropins, function( $state ) { return 'skipped' === $state; } ) );
		$kept = array_keys( array_filter( $dropins, function( $state ) { return 'kept' === $state; } ) );
		if ( $dropins ) $lines[] = 'Los drop-ins del sitio de origen son de su servidor y no se instalaron' . ( $skipped ? ': ' . implode( ', ', $skipped ) . ' (este servidor no tiene)' : '' ) . ( $kept ? ( $skipped ? '; ' : ': ' ) . 'se conservaron los de este servidor (' . implode( ', ', $kept ) . ')' : '' ) . '. Si este sitio necesita alguno, instálalo con su plugin (caché de objetos, base de datos) para este servidor.';
		$self = (array) ( $compat['self'] ?? array() );
		$activated = (string) ( $activation['activated'] ?? '' );
		if ( isset( $self['in_backup'] ) && ! $self['in_backup'] ) $lines[] = 'El respaldo no traía DIGITALÍSIMO Backups: se conservó el de este sitio' . ( '' !== $activated ? ' y quedó activo' . ( 'network' === $activated ? ' en la red' : '' ) : '' ) . '.';
		elseif ( '' !== $activated ) $lines[] = 'DIGITALÍSIMO Backups no estaba activo en la base de datos del respaldo: quedó activo' . ( 'network' === $activated ? ' en la red' : '' ) . '.';
		if ( ! empty( $activation['replaced'] ) ) $lines[] = 'Se desactivó la otra copia de DIGITALÍSIMO Backups que activaba el respaldo (' . implode( ', ', array_slice( (array) $activation['replaced'], 0, 3 ) ) . '): dos a la vez darían un error fatal; bórrala desde Plugins si no la necesitas.';
		if ( '' !== (string) ( $activation['error'] ?? '' ) ) $lines[] = 'No se pudo comprobar que DIGITALÍSIMO Backups quedara activo: ' . $activation['error'] . '.';
		if ( (int) ( $cleanup['files'] ?? 0 ) > 0 ) $lines[] = 'Se vació la caché de páginas (' . (int) $cleanup['files'] . ' archivo(s) en ' . implode( ', ', array_slice( (array) ( $cleanup['dirs'] ?? array() ), 0, 6 ) ) . '): las páginas de antes de restaurar ya no se sirven.';
		foreach ( (array) ( $cleanup['notes'] ?? array() ) as $note ) $lines[] = rtrim( (string) $note, '.' ) . '.';
		$state = (string) ( $htaccess['state'] ?? '' );
		if ( 'added' === $state ) $lines[] = 'Se escribió en .htaccess el bloque de WordPress con los enlaces permanentes, que faltaba (sin él las páginas internas darían 404); las demás reglas de este sitio se conservaron.';
		elseif ( 'fixed' === $state ) $lines[] = 'Se corrigió en .htaccess el bloque de WordPress, que era de otra ruta (' . (string) ( $htaccess['base'] ?? '/' ) . ' ahora); las demás reglas se conservaron.';
		elseif ( 'network_missing' === $state ) $lines[] = 'El .htaccess de esta red no tiene el bloque de WordPress: cópialo de Red → Configuración de la red, o las páginas de los sitios darán 404.';
		elseif ( 'failed' === $state ) $lines[] = 'No se pudo escribir en .htaccess el bloque de WordPress (' . (string) ( $htaccess['error'] ?? '' ) . '): guarda los enlaces permanentes en Ajustes → Enlaces permanentes.';
		elseif ( 'skipped_cli' === $state ) $lines[] = 'No se comprobó el bloque de WordPress de .htaccess: la restauración corrió entera fuera de una petición web (WP-CLI o el cron) y no se sabe si este servidor usa .htaccess. Si es Apache o LiteSpeed, guarda los enlaces permanentes en Ajustes → Enlaces permanentes (sin ese bloque las páginas internas darían 404).';
		$extra = (int) ( $compat['extra_count'] ?? 0 );
		if ( $extra ) $lines[] = 'Quedan ' . $extra . ' tabla(s) de este WordPress que el respaldo no trae (' . implode( ', ', array_slice( (array) $compat['extra_tables'], 0, 5 ) ) . ( $extra > 5 ? '…' : '' ) . '): son de plugins de este sitio y no estorban; bórralas si ya no las necesitas.';
		foreach ( (array) ( $cleanup['warnings'] ?? array() ) as $warning ) $lines[] = rtrim( (string) $warning, '.' ) . '.';
		if ( $lines ) $result['notice'] = ltrim( (string) ( $result['notice'] ?? '' ) . ' ' . call_user_func( $public, implode( ' ', $lines ) ) );
		$result['compat'] = array(
			'foreign' => ! empty( $compat['foreign'] ),
			'dropins' => array( 'not_installed' => $skipped, 'kept_here' => $kept ),
			'backups_plugin' => array( 'in_backup' => ! isset( $self['in_backup'] ) || (bool) $self['in_backup'], 'activated' => $activated, 'replaced' => array_values( (array) ( $activation['replaced'] ?? array() ) ) ),
			'page_cache' => array( 'files' => (int) ( $cleanup['files'] ?? 0 ), 'dirs' => array_values( (array) ( $cleanup['dirs'] ?? array() ) ) ),
			'builder_css' => ! empty( $cleanup['builders'] ),
			'theme' => array_values( (array) ( $cleanup['theme'] ?? array() ) ),
			'htaccess' => $state,
			'extra_tables' => array( 'count' => $extra, 'sample' => array_values( (array) ( $compat['extra_tables'] ?? array() ) ) ),
			'warnings' => array_values( array_map( function( $warning ) use ( $public ) { return call_user_func( $public, (string) $warning ); }, (array) ( $cleanup['warnings'] ?? array() ) ) ),
		);
		return $result;
	}
	// Brecha 18 (decisión: avisar, no bloquear): con DISALLOW_FILE_MODS el sitio no deja instalar ni actualizar código
	// desde el escritorio, y restaurar sí lo cambia. El informe de wp-config.php (verify, la subida, el resultado y el
	// correo) lo dice con la salida que respeta esa política: restaurar sólo la base de datos y los medios.
	public static function file_mods( array $report ) {
		if ( ! defined( 'DISALLOW_FILE_MODS' ) || ! DISALLOW_FILE_MODS ) return $report;
		$report['disallow_file_mods'] = true;
		$report['text'] = ltrim( (string) ( $report['text'] ?? '' ) . ' Este sitio define DISALLOW_FILE_MODS (no se instala ni se actualiza código desde el escritorio) y restaurar reemplaza el núcleo, los plugins y los temas por los del respaldo: hazlo sólo si quien administra el servidor lo permite; si sólo necesitas el contenido, restaura por partes la base de datos y los medios.' );
		return $report;
	}

	/* --------------------------------------------------------------------------------------------- Comunes */

	// Tablas de opciones y de metadatos de entradas de cada sitio que hay ahora (en un sitio individual sólo las suyas: una
	// `wp_2_options` es de otra instalación). Sitio => array( 'options' => tabla, 'postmeta' => tabla ).
	private static function site_tables( array $ctx ) {
		global $wpdb;
		if ( ! is_object( $wpdb ) ) return array();
		// Al importar un sitio en una red, sólo ése (el de into.json: el nuevo ya creado o el que se reemplaza).
		$only = 0;
		if ( ! empty( $ctx['compat']['import'] ) ) {
			$into = self::read( $ctx['staging'], 'into.json' );
			$only = (int) ( $into['target']['site_id'] ?? $ctx['job']['spec']['into']['site_id'] ?? 0 );
			if ( $only < 1 ) return array();
		}
		$base = (string) $wpdb->base_prefix;
		$out = array();
		$like = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $base ) : addcslashes( $base, '_%\\' );
		foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like . '%' ) ) as $table ) {
			$table = (string) $table;
			if ( ! preg_match( '/^' . preg_quote( $base, '/' ) . '(?:([0-9]+)_)?(options|postmeta)$/D', $table, $m ) ) continue;
			if ( '' !== $m[1] && ! is_multisite() ) continue;
			$site = '' === $m[1] ? 1 : (int) $m[1];
			if ( $only && $site !== $only ) continue;
			$out[ $site ][ $m[2] ] = $table;
		}
		ksort( $out );
		return $out;
	}
	private static function options_of( $table, array $names ) {
		global $wpdb;
		$quoted = implode( ', ', array_map( function( $name ) use ( $wpdb ) { return $wpdb->prepare( '%s', $name ); }, $names ) );
		$out = array();
		foreach ( (array) $wpdb->get_results( 'SELECT option_name, option_value FROM ' . self::q( $table ) . ' WHERE option_name IN (' . $quoted . ')', ARRAY_A ) as $row ) {
			$row = (array) $row;
			if ( isset( $row['option_name'] ) && in_array( (string) $row['option_name'], $names, true ) ) $out[ (string) $row['option_name'] ] = (string) ( $row['option_value'] ?? '' );
		}
		return $out;
	}
	private static function set_option( $table, $name, $value, $create = true ) {
		global $wpdb;
		$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::q( $table ) . ' WHERE option_name = %s', $name ) );
		if ( $exists ) $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::q( $table ) . ' SET option_value = %s WHERE option_name = %s', $value, $name ) );
		elseif ( $create ) $wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::q( $table ) . ' (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)', $name, $value, 'yes' ) );
	}
	// Un valor serializado de la base de datos restaurada (dato no confiable): sólo un array, nunca objetos.
	private static function unserialize_array( $raw ) {
		if ( ! is_string( $raw ) || ! preg_match( '/^a:[0-9]+:\{/', $raw ) ) return array();
		$value = @unserialize( $raw, array( 'allowed_classes' => false ) );
		return is_array( $value ) ? $value : array();
	}
	private static function q( $name ) { return '`' . str_replace( '`', '``', (string) $name ) . '`'; }
	// compat.json no cambia después de la verificación: se lee una vez por petición (el recorrido la consulta por archivo).
	private static function cached( $staging ) {
		static $cache = array();
		$key = (string) $staging;
		if ( ! isset( $cache[ $key ] ) ) $cache = array( $key => self::read( $staging ) );
		return $cache[ $key ];
	}
	public static function read( $staging, $name = self::FILE ) {
		$path = rtrim( (string) $staging, '/' ) . '/' . $name;
		$data = is_file( $path ) && ! is_link( $path ) ? json_decode( (string) @file_get_contents( $path ), true ) : null;
		return is_array( $data ) ? $data : array();
	}
	private static function write( $staging, $name, array $data ) {
		$path = rtrim( (string) $staging, '/' ) . '/' . $name;
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data ) : json_encode( $data );
		if ( ! is_string( $json ) || is_link( $path ) || strlen( $json ) !== @file_put_contents( $path . '.tmp', $json ) || ! @rename( $path . '.tmp', $path ) ) throw new Exception( 'No se pudo guardar el avance de la restauración en el área de trabajo; revisa el espacio libre del almacén.' );
		if ( DIRECTORY_SEPARATOR === '/' ) @chmod( $path, 0600 );
	}
}
