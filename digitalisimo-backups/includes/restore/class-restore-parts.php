<?php
defined( 'ABSPATH' ) || exit;

/**
 * Restaurar por partes (decisión del propietario del 2026-11-13: «para restaurar únicamente lo que se necesita en un caso
 * dado»). Los respaldos siguen siendo siempre completos; lo que se elige es qué parte de un respaldo completo vuelve al
 * sitio: la base de datos, el núcleo de WordPress (todo lo que está fuera de wp-content), y en wp-content los plugins, los
 * temas, los medios, los mu-plugins y el resto (idiomas, drop-ins, otras carpetas); dentro de plugins y temas, plugins o
 * temas concretos, y en una red, los medios de un sitio concreto.
 *
 * Esta clase sólo decide (qué es cada ruta, qué se eligió, qué se avisa): la restauración por pasos
 * (Digitalisimo_Backups_Restore_Engine) es la misma que la completa, con la misma verificación del paquete, la copia
 * previa completa, el mantenimiento, la reanudación y la vuelta a la copia previa; lo que no se eligió no entra en el
 * recorrido, la comparación, la preparación ni el cambio. La selección viaja en la especificación del trabajo (`parts`):
 * null es «todo», como siempre.
 *
 * Selección normalizada: array( 'parts' => partes enteras (en el orden de PARTS), 'plugins' => carpetas o archivos de
 * plugins concretos, 'themes' => carpetas de temas concretos, 'sites' => sitios de la red cuyos medios se restauran ).
 * Un plugin concreto no se lista si se eligieron los plugins enteros (lo mismo con temas y medios).
 */
final class Digitalisimo_Backups_Restore_Parts {
	/** Partes de un respaldo completo, con su nombre en la pantalla, REST, WP-CLI, el informe y el correo. */
	const PARTS = array(
		'database' => 'base de datos',
		'core' => 'núcleo de WordPress',
		'plugins' => 'plugins',
		'themes' => 'temas',
		'uploads' => 'medios',
		'mu-plugins' => 'mu-plugins',
		'content' => 'resto de wp-content',
	);
	/** Qué reemplaza cada parte (la confirmación lo dice antes de restaurar). */
	const REPLACES = array(
		'database' => 'todas las tablas de este WordPress, con las mismas reglas que la restauración completa',
		'core' => 'wp-admin, wp-includes y lo demás fuera de wp-content (nunca wp-config.php)',
		'plugins' => 'la carpeta de plugins entera: se retiran los que el respaldo no tiene',
		'themes' => 'la carpeta de temas entera: se retiran los que el respaldo no tiene',
		'uploads' => 'los archivos de medios del respaldo',
		'mu-plugins' => 'la carpeta de mu-plugins entera',
		'content' => 'idiomas, drop-ins y otras carpetas de wp-content',
	);
	/** Otros nombres que aceptan REST y WP-CLI. */
	const ALIASES = array( 'db' => 'database', 'base' => 'database', 'nucleo' => 'core', 'wordpress' => 'core', 'media' => 'uploads', 'medios' => 'uploads', 'mu' => 'mu-plugins', 'muplugins' => 'mu-plugins', 'resto' => 'content', 'wp-content' => 'content', 'temas' => 'themes' );
	/** Máximo de plugins, temas o sitios concretos en una selección. */
	const MAX_ITEMS = 200;
	/**
	 * Avisos de coherencia (nunca bloquean): se muestran si está elegida alguna de `any`, ninguna de `none` y falta alguna
	 * de `missing`. Claves: las partes y `plugin-items`, `theme-items`, `site-items` (concretos). La pantalla pinta estas
	 * mismas reglas y su script las evalúa con las casillas marcadas; REST, WP-CLI y el informe usan coherence().
	 */
	const RULES = array(
		'plugins_without_database' => array( 'any' => array( 'plugins', 'plugin-items', 'mu-plugins' ), 'none' => array( 'database' ), 'text' => 'Plugins sin la base de datos: si un plugin del respaldo es de otra versión, sus tablas y ajustes siguen siendo los de ahora y pueden no coincidir (también DIGITALÍSIMO Backups, que puede volver a una versión anterior a sus datos de ahora). Conviene elegir también la base de datos.' ),
		'themes_without_database' => array( 'any' => array( 'themes', 'theme-items' ), 'none' => array( 'database' ), 'text' => 'Temas sin la base de datos: sus ajustes, menús y widgets siguen siendo los de ahora; si el tema cambió de versión, pueden no coincidir.' ),
		'core_without_database' => array( 'any' => array( 'core' ), 'none' => array( 'database' ), 'text' => 'Núcleo sin la base de datos: si el WordPress del respaldo es más antiguo que el de ahora, la base de datos puede ser demasiado nueva para él.' ),
		'uploads_without_database' => array( 'any' => array( 'uploads', 'site-items' ), 'none' => array( 'database' ), 'text' => 'Medios sin la base de datos: vuelven los archivos del respaldo y se conservan los subidos después (la biblioteca de ahora los usa).' ),
		'database_without_plugins' => array( 'any' => array( 'database' ), 'missing' => array( 'plugins', 'mu-plugins' ), 'text' => 'Base de datos sin todos los plugins: vuelven los plugins activos y sus ajustes del respaldo con el código de ahora; uno instalado o actualizado después puede quedar desactivado o con datos de otra versión. Conviene elegir también los plugins.' ),
		'database_without_themes' => array( 'any' => array( 'database' ), 'missing' => array( 'themes' ), 'text' => 'Base de datos sin los temas: si el tema activo del respaldo ya no está o es de otra versión, el sitio puede verse distinto. Conviene elegir también los temas.' ),
		'database_without_core' => array( 'any' => array( 'database' ), 'missing' => array( 'core' ), 'text' => 'Base de datos sin el núcleo: si WordPress cambió de versión después del respaldo, puede pedir «Actualizar la base de datos» o no reconocerla.' ),
		'database_without_uploads' => array( 'any' => array( 'database' ), 'missing' => array( 'uploads' ), 'text' => 'Base de datos sin los medios: la biblioteca vuelve al respaldo con los archivos de ahora; un medio borrado después aparecerá sin archivo. Conviene elegir también los medios.' ),
		'content_without_plugins' => array( 'any' => array( 'content' ), 'missing' => array( 'plugins' ), 'text' => 'El resto de wp-content trae los drop-ins del respaldo (object-cache.php, advanced-cache.php, db.php): sin sus plugins pueden no coincidir.' ),
	);

	/* ------------------------------------------------------------------------------------------- Selección */

	/**
	 * La selección pedida, normalizada, o null si es «todo» (también sin `parts` ni concretos: la restauración de siempre).
	 * $input: `parts` (lista o texto separado por comas; `all` o `todo` es todo), `plugins`, `themes` y `sites` (listas o
	 * texto separado por comas). Lanza InvalidArgumentException con un mensaje para quien restaura. Una clave presente y
	 * vacía (`parts=` o `plugins=` sin nada más) no es «todo»: no se eligió nada, y se dice (revisión M2). Con
	 * $environment = false sólo se valida la forma (lo guardado en un trabajo): las reglas de esta red, sólo al encolar
	 * (revisión M4: tras el cambio de tablas, la red es la del respaldo).
	 */
	public static function normalize( array $input, $environment = true ) {
		$raw = $input['parts'] ?? null;
		$given = false;
		foreach ( array( 'parts', 'plugins', 'themes', 'sites' ) as $key ) if ( null !== ( $input[ $key ] ?? null ) ) $given = true;
		$items = array(
			'plugins' => self::items( $input['plugins'] ?? array(), 'plugins' ),
			'themes' => self::items( $input['themes'] ?? array(), 'themes' ),
			'sites' => self::sites( $input['sites'] ?? array() ),
		);
		if ( ! self::words( $raw ) && ! $items['plugins'] && ! $items['themes'] && ! $items['sites'] ) {
			// Sin `parts` ni concretos: todo, la restauración de siempre. Una selección presente y vacía (`parts=`, `parts=[]`, la
			// pantalla con «Todo» y cada parte desmarcados, `--plugins=`) no restaura nada: se dice.
			if ( $given ) throw new InvalidArgumentException( 'Elige al menos una parte del respaldo para restaurar (o «Todo»).' );
			return null;
		}
		$parts = array();
		foreach ( self::words( $raw ) as $word ) {
			$key = strtolower( trim( $word ) );
			if ( in_array( $key, array( 'all', 'todo', 'everything', 'full' ), true ) ) return null;
			$key = self::ALIASES[ $key ] ?? $key;
			if ( ! isset( self::PARTS[ $key ] ) ) throw new InvalidArgumentException( 'Parte desconocida: «' . self::shorten( $word ) . '». Las partes son: ' . implode( ', ', array_keys( self::PARTS ) ) . '.' );
			$parts[ $key ] = true;
		}
		$ordered = array_values( array_intersect( array_keys( self::PARTS ), array_keys( $parts ) ) );
		if ( count( $ordered ) === count( self::PARTS ) ) return null;
		if ( in_array( 'plugins', $ordered, true ) ) $items['plugins'] = array();
		if ( in_array( 'themes', $ordered, true ) ) $items['themes'] = array();
		if ( in_array( 'uploads', $ordered, true ) ) $items['sites'] = array();
		if ( $environment && $items['sites'] && ! ( function_exists( 'is_multisite' ) && is_multisite() ) ) throw new InvalidArgumentException( 'Los medios de un sitio concreto sólo se eligen en una red.' );
		if ( $environment && $items['sites'] && function_exists( 'get_site_option' ) && get_site_option( 'ms_files_rewriting' ) ) throw new InvalidArgumentException( 'Esta red guarda los medios con el sistema antiguo (blogs.dir): elige «medios» entero, no los de un sitio.' );
		if ( ! $ordered && ! $items['plugins'] && ! $items['themes'] && ! $items['sites'] ) throw new InvalidArgumentException( 'Elige al menos una parte del respaldo para restaurar (o «Todo»).' );
		return array( 'parts' => $ordered, 'plugins' => $items['plugins'], 'themes' => $items['themes'], 'sites' => $items['sites'] );
	}
	/**
	 * La selección guardada en la especificación de un trabajo (null: todo). Se vuelve a validar su forma (nunca se confía
	 * en ella), no las reglas de esta red, que se comprobaron al encolar (revisión M4).
	 */
	public static function from_spec( array $spec ) {
		$saved = $spec['parts'] ?? null;
		if ( ! is_array( $saved ) ) return null;
		try {
			return self::normalize( array( 'parts' => (array) ( $saved['parts'] ?? array() ), 'plugins' => (array) ( $saved['plugins'] ?? array() ), 'themes' => (array) ( $saved['themes'] ?? array() ), 'sites' => (array) ( $saved['sites'] ?? array() ) ), false );
		} catch ( InvalidArgumentException $e ) {
			throw new Digitalisimo_Backups_Permanent_Error( 'La selección de partes del trabajo no es válida: ' . $e->getMessage() );
		}
	}
	/** La selección incluye la base de datos (todo, o la parte elegida). */
	public static function has_database( $selection ) { return null === $selection || in_array( 'database', (array) $selection['parts'], true ); }
	/** La selección incluye la parte entera $part. */
	public static function has( $selection, $part ) { return null === $selection || in_array( $part, (array) $selection['parts'], true ); }
	/** Alguna parte de archivos (entera o concreta). */
	public static function has_files( $selection ) {
		if ( null === $selection ) return true;
		return (bool) array_diff( (array) $selection['parts'], array( 'database' ) ) || $selection['plugins'] || $selection['themes'] || $selection['sites'];
	}
	private static function words( $value ) {
		if ( null === $value || '' === $value ) return array();
		$list = is_array( $value ) ? $value : explode( ',', (string) $value );
		$out = array();
		foreach ( $list as $word ) {
			if ( ! is_scalar( $word ) ) throw new InvalidArgumentException( 'La lista de partes no es válida.' );
			$word = trim( (string) $word );
			if ( '' !== $word ) $out[] = $word;
		}
		return $out;
	}
	/** Plugins o temas concretos: nombres de carpeta (o de archivo, un plugin de un solo archivo), sin rutas. */
	private static function items( $value, $kind ) {
		$out = array();
		foreach ( self::words( $value ) as $name ) {
			if ( strlen( $name ) > 190 || '.' === $name[0] || false !== strpbrk( $name, "/\\\0" ) || preg_match( '/[\x00-\x1f\x7f]/', $name ) ) throw new InvalidArgumentException( ( 'plugins' === $kind ? 'Plugin' : 'Tema' ) . ' no válido: «' . self::shorten( $name ) . '». Usa el nombre de su carpeta, sin rutas.' );
			$out[ $name ] = true;
		}
		if ( count( $out ) > self::MAX_ITEMS ) throw new InvalidArgumentException( 'Demasiados ' . ( 'plugins' === $kind ? 'plugins' : 'temas' ) . ' concretos (como mucho ' . self::MAX_ITEMS . '): elige la parte entera.' );
		$out = array_keys( $out );
		sort( $out, SORT_STRING );
		return array_map( 'strval', $out );
	}
	private static function sites( $value ) {
		$out = array();
		foreach ( self::words( $value ) as $id ) {
			if ( ! preg_match( '/^[1-9][0-9]{0,9}$/D', $id ) ) throw new InvalidArgumentException( 'Sitio de la red no válido: «' . self::shorten( $id ) . '» (el número del sitio).' );
			$out[ (int) $id ] = true;
		}
		if ( count( $out ) > self::MAX_ITEMS ) throw new InvalidArgumentException( 'Demasiados sitios concretos (como mucho ' . self::MAX_ITEMS . '): elige los medios enteros.' );
		$out = array_keys( $out );
		sort( $out );
		return $out;
	}
	private static function shorten( $text ) {
		$text = preg_replace( '/[\x00-\x1f\x7f]/', '?', (string) $text );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 60, 'UTF-8' ) : substr( $text, 0, 60 );
	}

	/* ------------------------------------------------------------------------------------------- Rutas */

	/**
	 * Parte de una ruta relativa a la raíz de WordPress, con las raíces de esta instalación (`restore_layout()`):
	 * array( parte, concreto ). El concreto es la carpeta (o el archivo) de un plugin o tema, el sitio de la red de un
	 * medio (1 fuera de `sites/`) o null. La raíz más larga gana (los medios dentro de wp-content son medios).
	 */
	public static function classify( $relative, array $layout ) {
		$relative = (string) $relative;
		$best = null;
		$length = -1;
		foreach ( array( 'plugins' => 'plugins', 'themes' => 'themes', 'mu-plugins' => 'mu', 'uploads' => 'uploads' ) as $part => $key ) {
			$base = (string) ( $layout[ $key ] ?? '' );
			if ( '' === $base || strlen( $base ) <= $length || 0 !== strpos( $relative, $base . '/' ) ) continue;
			$best = array( $part, $base );
			$length = strlen( $base );
		}
		if ( null !== $best ) {
			list( $part, $base ) = $best;
			$rest = substr( $relative, strlen( $base ) + 1 );
			if ( 'plugins' === $part || 'themes' === $part ) {
				$slash = strpos( $rest, '/' );
				return array( $part, false === $slash ? $rest : substr( $rest, 0, $slash ) );
			}
			if ( 'uploads' === $part ) return array( $part, preg_match( '#^sites/([1-9][0-9]*)/#', $rest, $m ) ? (int) $m[1] : 1 );
			return array( $part, null );
		}
		$content = (string) ( $layout['content'] ?? '' );
		if ( '' !== $content && 0 === strpos( $relative, $content . '/' ) ) return array( 'content', null );
		return array( 'core', null );
	}
	/** La ruta (archivo del respaldo o del sitio) entra en la selección. */
	public static function selected( $selection, $relative, array $layout ) {
		if ( null === $selection ) return true;
		list( $part, $item ) = self::classify( $relative, $layout );
		if ( in_array( $part, (array) $selection['parts'], true ) ) return true;
		if ( 'plugins' === $part ) return in_array( (string) $item, (array) $selection['plugins'], true );
		if ( 'themes' === $part ) return in_array( (string) $item, (array) $selection['themes'], true );
		if ( 'uploads' === $part ) return in_array( (int) $item, (array) $selection['sites'], true );
		return false;
	}
	/**
	 * Un archivo del sitio que el respaldo no tiene, dentro de lo que gestiona (H3), se retira: sólo si está en la
	 * selección y, en los medios sin la base de datos, sólo si PHP puede ejecutarlo. Los medios subidos después del
	 * respaldo son de la biblioteca de ahora (que no vuelve): se conservan; un `.php` en los medios nunca es un medio. En
	 * la vuelta a la copia previa ($rollback), lo que la copia no tiene lo trajo la restauración que falló: se retira todo
	 * lo elegido (revisión M3).
	 */
	public static function removable( $selection, $relative, array $layout, $rollback = false ) {
		if ( null === $selection ) return true;
		if ( ! self::selected( $selection, $relative, $layout ) ) return false;
		if ( $rollback || self::has_database( $selection ) ) return true;
		list( $part ) = self::classify( $relative, $layout );
		return 'uploads' !== $part || (bool) preg_match( '/\.(?:php[0-9]?|phtml|pht|phar|phps)$/i', (string) $relative );
	}
	/**
	 * El recorrido del sitio entra en esta carpeta: lleva a lo elegido o está dentro (las demás, por ejemplo los medios
	 * de un sitio enorme al restaurar un plugin, ni se recorren).
	 */
	public static function relevant_dir( $selection, $dir, array $layout ) {
		if ( null === $selection || '' === (string) $dir ) return true;
		$dir = (string) $dir;
		foreach ( self::roots( $selection, $layout ) as $root ) {
			list( $path, $without_sites ) = $root;
			if ( '' === $path ) continue;
			if ( $dir === $path || 0 === strpos( $path . '/', $dir . '/' ) ) return true;
			if ( 0 !== strpos( $dir . '/', $path . '/' ) ) continue;
			if ( $without_sites && ( $dir === $path . '/sites' || 0 === strpos( $dir, $path . '/sites/' ) ) ) continue;
			return true;
		}
		return false;
	}
	/** Carpetas (o archivos) elegidos relativos a la raíz: array( ruta, sin `sites/` ). */
	private static function roots( array $selection, array $layout ) {
		$roots = array();
		foreach ( (array) $selection['parts'] as $part ) {
			if ( 'core' === $part ) { $roots[] = array( 'wp-admin', false ); $roots[] = array( 'wp-includes', false ); }
			elseif ( 'plugins' === $part ) $roots[] = array( (string) $layout['plugins'], false );
			elseif ( 'themes' === $part ) $roots[] = array( (string) $layout['themes'], false );
			elseif ( 'mu-plugins' === $part ) $roots[] = array( (string) $layout['mu'], false );
			elseif ( 'uploads' === $part ) $roots[] = array( (string) $layout['uploads'], false );
			// El resto de wp-content no se recorre: como en la completa, ahí no se retira nada (no es una raíz gestionada).
		}
		foreach ( (array) $selection['plugins'] as $item ) if ( '' !== (string) $layout['plugins'] ) $roots[] = array( $layout['plugins'] . '/' . $item, false );
		foreach ( (array) $selection['themes'] as $item ) if ( '' !== (string) $layout['themes'] ) $roots[] = array( $layout['themes'] . '/' . $item, false );
		foreach ( (array) $selection['sites'] as $site ) if ( '' !== (string) $layout['uploads'] ) $roots[] = 1 === (int) $site ? array( (string) $layout['uploads'], true ) : array( $layout['uploads'] . '/sites/' . (int) $site, false );
		return $roots;
	}

	/* ------------------------------------------------------------------------------------------- Inventario */

	/**
	 * Lo que trae un ZIP de respaldo (por los nombres de sus entradas, sin leerlas): qué partes, qué plugins y temas (su
	 * carpeta o su archivo, sin los `index.php` de relleno) y, en una red, los sitios con medios.
	 */
	public static function inventory( $zip, array $layout ) {
		$out = array( 'parts' => array(), 'plugins' => array(), 'themes' => array(), 'sites' => array() );
		if ( ! $zip instanceof ZipArchive ) return $out;
		if ( false !== $zip->locateName( 'database.sql' ) ) $out['parts']['database'] = true;
		for ( $i = 0, $count = (int) $zip->numFiles; $i < $count; $i++ ) {
			$name = str_replace( '\\', '/', (string) $zip->getNameIndex( $i ) );
			if ( 0 !== strpos( $name, 'wordpress/' ) || '/' === substr( $name, -1 ) ) continue;
			$relative = substr( $name, 10 );
			if ( '' === $relative || 'wp-config.php' === $relative ) continue;
			list( $part, $item ) = self::classify( $relative, $layout );
			$out['parts'][ $part ] = true;
			// Plugins y temas concretos: carpetas y, en plugins, un plugin de un solo archivo (`.php`, salvo el index.php de
			// relleno); nunca un readme.txt o un .htaccess sueltos (revisión B5).
			if ( ( 'plugins' === $part || 'themes' === $part ) && '' !== (string) $item && 'index.php' !== $item ) {
				$folder = false !== strpos( substr( $relative, strlen( (string) $layout[ $part ] ) + 1 ), '/' );
				if ( $folder || ( 'plugins' === $part && '.php' === strtolower( substr( (string) $item, -4 ) ) ) ) $out[ $part ][ (string) $item ] = true;
			}
			if ( 'uploads' === $part ) $out['sites'][ (int) $item ] = true;
		}
		foreach ( array( 'plugins', 'themes', 'sites' ) as $key ) {
			$out[ $key ] = array_keys( $out[ $key ] );
			sort( $out[ $key ] );
		}
		$out['parts'] = array_values( array_intersect( array_keys( self::PARTS ), array_keys( $out['parts'] ) ) );
		return $out;
	}
	/**
	 * Lo elegido que el respaldo no trae (una parte entera, un plugin, un tema o los medios de un sitio que no tiene): frases.
	 * Elegir una parte que el respaldo no trae retiraría lo de ahora sin traer nada (revisión A2): se rechaza.
	 */
	public static function missing( $selection, array $inventory ) {
		if ( null === $selection ) return array();
		$out = array();
		foreach ( (array) $selection['parts'] as $part ) if ( ! in_array( $part, (array) ( $inventory['parts'] ?? array() ), true ) ) $out[] = 'la parte «' . self::PARTS[ $part ] . '»';
		foreach ( (array) $selection['plugins'] as $item ) if ( ! in_array( $item, $inventory['plugins'], true ) ) $out[] = 'el plugin «' . $item . '»';
		foreach ( (array) $selection['themes'] as $item ) if ( ! in_array( $item, $inventory['themes'], true ) ) $out[] = 'el tema «' . $item . '»';
		foreach ( (array) $selection['sites'] as $site ) if ( ! in_array( (int) $site, $inventory['sites'], true ) ) $out[] = 'medios del sitio ' . (int) $site;
		return $out;
	}
	/** Plugins y temas del sitio ahora (carpetas y plugins de un archivo) y cuáles están activos. */
	public static function live( array $layout ) {
		$out = array( 'plugins' => array(), 'themes' => array(), 'active_plugins' => array(), 'active_themes' => array() );
		foreach ( array( 'plugins', 'themes' ) as $kind ) {
			$base = (string) ( $layout[ $kind ] ?? '' );
			if ( '' === $base ) continue;
			$dir = rtrim( (string) $layout['root'], '/' ) . '/' . $base;
			foreach ( (array) @scandir( $dir ) as $name ) {
				$name = (string) $name;
				if ( '' === $name || '.' === $name[0] || 'index.php' === $name || is_link( $dir . '/' . $name ) ) continue;
				if ( is_dir( $dir . '/' . $name ) || ( 'plugins' === $kind && '.php' === strtolower( substr( $name, -4 ) ) ) ) $out[ $kind ][] = $name;
			}
			sort( $out[ $kind ] );
		}
		$active = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', array() ) : array();
		if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) $active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		foreach ( $active as $file ) if ( is_string( $file ) && '' !== $file ) $out['active_plugins'][ false === strpos( $file, '/' ) ? $file : strstr( $file, '/', true ) ] = true;
		$out['active_plugins'] = array_keys( $out['active_plugins'] );
		foreach ( array( 'get_stylesheet', 'get_template' ) as $function ) if ( function_exists( $function ) ) $out['active_themes'][] = (string) call_user_func( $function );
		$out['active_themes'] = array_values( array_unique( array_filter( $out['active_themes'] ) ) );
		return $out;
	}

	/* ------------------------------------------------------------------------------------------- Pantalla */

	/**
	 * Para la confirmación: qué trae el respaldo (partes, plugins, temas y sitios de la red), qué reemplaza la selección,
	 * qué no se toca y los avisos de coherencia. $zip abierto (o null si no se puede leer, un `.dgbk` sin descifrar).
	 */
	public static function preview( $zip, $parts, array $layout ) {
		$inventory = $zip instanceof ZipArchive ? self::inventory( $zip, $layout ) : null;
		$head = $zip instanceof ZipArchive ? (string) $zip->getFromName( 'manifest.json', 4096 ) : '';
		$describe = self::describe( $parts, $inventory, $inventory ? self::live( $layout ) : null, ! $zip instanceof ZipArchive || (bool) preg_match( '#"format"\s*:\s*"digitalisimo-backup\\\\?/v3"#', $head ) );
		return array(
			'available' => $inventory,
			'selection' => $parts,
			'label' => self::label( $parts ),
			'replaces' => $describe['replaces'],
			'untouched' => $describe['untouched'],
			'warnings' => $describe['warnings'],
			'missing' => $inventory ? self::missing( $parts, $inventory ) : array(),
			'coherence' => self::coherence( $parts ),
		);
	}
	/**
	 * «Qué restaurar» (restaurar por partes): plegado y con «Todo» marcado, así que el panel sigue siendo una frase, la
	 * confirmación y el botón. Dentro, cada parte con lo que reemplaza; con $items (el panel abierto de un ZIP que se
	 * puede leer), los plugins, temas y sitios de la red que trae el respaldo; si no, $link lleva a ese panel. Los avisos
	 * de coherencia (Digitalisimo_Backups_Restore_Parts::RULES) se pintan todos: el script deja a la vista sólo los que
	 * aplican a lo marcado (sin JavaScript se leen todos). El handler sólo mira las partes con «Todo» desmarcado.
	 */
	public static function field( $path, $items, $link, array $layout ) {
		$parts = __CLASS__;
		$inventory = null;
		$encrypted = '' !== (string) $path && Digitalisimo_Backups_Encrypted_File::is_encrypted( $path );
		if ( $items && '' !== (string) $path && ! $encrypted && class_exists( 'ZipArchive' ) && is_file( $path ) ) {
			$zip = new ZipArchive();
			if ( true === $zip->open( $path ) ) {
				$inventory = $parts::inventory( $zip, $layout );
				$zip->close();
			}
		}
		$out = '<details class="digitalisimo-backups-restore-parts" data-restore-parts' . ( $items && ! empty( $_GET['parts'] ) ? ' open' : '' ) . '><summary>Qué restaurar: <span data-restore-parts-label>todo</span></summary>';
		$out .= '<p><label><input type="checkbox" name="restore_all" value="1" checked data-restore-all> Todo (base de datos y archivos)</label><input type="hidden" name="restore_parts_shown" value="1"></p>';
		$out .= Digitalisimo_Backups::help_html( 'Los respaldos son siempre completos: aquí eliges qué vuelve. Desmarca «Todo» y marca las partes, o marca un plugin, tema o sitio concreto (sólo eso); lo que no marques no se toca. Antes se guarda la copia previa completa y, si algo falla, se vuelve a ella.' );
		$out .= '<ul class="digitalisimo-backups-parts-list">';
		foreach ( $parts::PARTS as $key => $name ) $out .= '<li><label><input type="checkbox" name="restore_parts[]" value="' . esc_attr( $key ) . '" checked data-restore-part> ' . esc_html( ucfirst( $name ) ) . '</label> <span class="description">' . esc_html( $parts::REPLACES[ $key ] ) . '</span></li>';
		$out .= '</ul>';
		if ( $inventory ) {
			$groups = array( 'plugins' => array( 'restore_plugins[]', 'Plugins concretos', 'plugin-items', 'Sólo se reemplaza la carpeta de cada plugin marcado; los demás no se tocan.' ), 'themes' => array( 'restore_themes[]', 'Temas concretos', 'theme-items', 'Sólo se reemplaza la carpeta de cada tema marcado; los demás no se tocan.' ) );
			if ( is_multisite() && ! get_site_option( 'ms_files_rewriting' ) ) $groups['sites'] = array( 'restore_sites[]', 'Medios de un sitio de la red', 'site-items', 'Sólo los medios de los sitios marcados (el principal: lo que no está en sites/).' );
			foreach ( $groups as $kind => $group ) {
				if ( empty( $inventory[ $kind ] ) ) continue;
				$whole = 'sites' === $kind ? 'uploads' : $kind;
				$out .= '<details class="digitalisimo-backups-parts-items"><summary>' . esc_html( $group[1] ) . ' (' . count( $inventory[ $kind ] ) . ')</summary><p class="description">' . esc_html( $group[3] ) . '</p><ul>';
				foreach ( $inventory[ $kind ] as $item ) $out .= '<li><label><input type="checkbox" name="' . esc_attr( $group[0] ) . '" value="' . esc_attr( (string) $item ) . '" data-restore-item="' . esc_attr( $group[2] ) . '" data-restore-whole="' . esc_attr( $whole ) . '"> ' . esc_html( 'sites' === $kind ? 'Sitio ' . $item : (string) $item ) . '</label></li>';
				$out .= '</ul></details>';
			}
		} elseif ( $encrypted ) {
			$out .= Digitalisimo_Backups::help_html( 'Respaldo cifrado: sus plugins y temas concretos se eligen por REST o WP-CLI (--plugins, --themes), que lo descifran.' );
		} elseif ( '' !== (string) $link ) {
			$out .= '<p><a href="' . esc_url( $link ) . '">Elegir plugins, temas o sitios concretos</a></p>';
		}
		$out .= '<ul class="digitalisimo-backups-coherence">';
		foreach ( $parts::RULES as $code => $rule ) $out .= '<li class="digitalisimo-backups-warning" data-coherence="' . esc_attr( $code ) . '" data-any="' . esc_attr( implode( ' ', $rule['any'] ) ) . '" data-none="' . esc_attr( implode( ' ', (array) ( $rule['none'] ?? array() ) ) ) . '" data-missing="' . esc_attr( implode( ' ', (array) ( $rule['missing'] ?? array() ) ) ) . '"><strong>Aviso:</strong> ' . esc_html( $rule['text'] ) . '</li>';
		$out .= '</ul></details>';
		return $out;
	}
	/**
	 * Lo que envió «Qué restaurar» (restaurar por partes) para el caso de uso: nada con «Todo» marcado o sin el campo (un
	 * formulario de antes); si no, las partes y lo concreto marcado (una lista vacía: no se eligió nada, y se dice).
	 */
	public static function from_request( array $post ) {
		if ( empty( $post['restore_parts_shown'] ) ) return array();
		$list = function( $key ) use ( $post ) {
			$value = $post[ $key ] ?? array();
			if ( ! is_array( $value ) ) return array();
			$out = array();
			foreach ( $value as $item ) if ( is_scalar( $item ) && '' !== (string) $item ) $out[] = (string) wp_unslash( (string) $item );
			return $out;
		};
		$items = array( 'plugins' => $list( 'restore_plugins' ), 'themes' => $list( 'restore_themes' ), 'sites' => $list( 'restore_sites' ) );
		// Con «Todo» marcado: todo, salvo que se marcara algo concreto, que empieza de cero (sólo eso), como hace el script
		// (revisión M1 y B1): nunca se restaura la base y lo demás por haber marcado un plugin.
		if ( ! empty( $post['restore_all'] ) ) return $items['plugins'] || $items['themes'] || $items['sites'] ? array( 'parts' => array() ) + $items : array();
		return array( 'parts' => $list( 'restore_parts' ) ) + $items;
	}
	/* ------------------------------------------------------------------------------------------- Motor */

	/** UpdraftPlus se restaura en una sola petición, sin la restauración por pasos: entero. */
	const UPDRAFT = 'La restauración se detuvo: un respaldo de UpdraftPlus se restaura entero; restaurar por partes es para los respaldos de Backups (también los importados de Backuply).';
	/**
	 * Antes de tocar nada: un sitio extraído de una red se restaura entero (sus reglas de usuarios, plugins y ajustes son
	 * de todo el paquete), y lo elegido (una parte entera, un plugin, un tema, los medios de un sitio) tiene que estar en
	 * el respaldo, salvo en la vuelta a la copia previa: allí lo que la copia no tiene es lo que trajo la restauración que
	 * falló, y se retira. Lanza Exception con el motivo.
	 */
	public static function check( $zip, $data, $selection, $rollback, array $layout ) {
		if ( null === $selection ) return;
		// Salvo el sitio convertido de un respaldo de red (`converted_from`): es el respaldo de quien restaura, por partes como cualquiera.
		if ( Digitalisimo_Backups_Site_Extract::SCOPE === ( $data['scope'] ?? '' ) && empty( $data['converted_from'] ) ) throw new Exception( 'Un sitio extraído de una red se restaura entero: no admite restaurar por partes. No se cambió nada del sitio.' );
		if ( $rollback ) return;
		$missing = self::missing( $selection, self::inventory( $zip, $layout ) );
		if ( $missing ) throw new Exception( 'El respaldo no tiene ' . self::list_text( $missing, 10 ) . ': elige lo que trae (la lista de plugins y temas del respaldo) o la parte entera. No se cambió nada del sitio.' );
	}
	/** La fase «base de datos» sin la base de datos: nada que importar (el mantenimiento empieza igual). */
	public static function skip_database() {
		return array( 'next' => 'files', 'checkpoint' => array( 'done' => time(), 'skipped' => true, 'statements' => 0, 'report' => array() ), 'message' => 'La base de datos no se restaura (no se eligió); preparando los archivos…' );
	}
	public static function switch_message( $with_database ) { return ( $with_database ? 'Base de datos y código cambiados' : 'Código cambiado' ) . '; moviendo los medios…'; }
	/** El mensaje del trabajo al terminar (la restauración o su vuelta a la copia previa), con la selección. */
	public static function finale_message( $rollback, $file, array $parts ) {
		$partial = ! empty( $parts ) && empty( $parts['all'] );
		if ( $rollback ) return 'Se volvió a la copia previa ' . $file . ': ' . ( $partial ? 'lo restaurado (' . (string) $parts['label'] . ') está' : 'el sitio está' ) . ' como antes de la restauración que falló.';
		return 'Restauración terminada desde ' . $file . ( $partial ? ' (por partes: ' . (string) $parts['label'] . ')' : '' ) . '.';
	}
	/** « (por partes: …)» para el estado de una restauración en curso, o nada. */
	public static function status_suffix( array $spec ) {
		try {
			$label = self::label( self::from_spec( $spec ) );
		} catch ( Throwable $e ) {
			return '';
		}
		return 'todo' === $label ? '' : ' (por partes: ' . $label . ')';
	}

	/* ------------------------------------------------------------------------------------------- Textos */

	/** «base de datos, plugins (akismet, hello.php) y medios del sitio 3». */
	public static function label( $selection ) {
		if ( null === $selection ) return 'todo';
		$names = array();
		foreach ( (array) $selection['parts'] as $part ) $names[] = self::PARTS[ $part ];
		if ( $selection['plugins'] ) $names[] = ( 1 === count( $selection['plugins'] ) ? 'el plugin ' : 'los plugins ' ) . self::list_text( $selection['plugins'], 8 );
		if ( $selection['themes'] ) $names[] = ( 1 === count( $selection['themes'] ) ? 'el tema ' : 'los temas ' ) . self::list_text( $selection['themes'], 8 );
		if ( $selection['sites'] ) $names[] = 'los medios ' . ( 1 === count( $selection['sites'] ) ? 'del sitio ' : 'de los sitios ' ) . self::list_text( $selection['sites'], 8 );
		return self::list_text( $names, 20 );
	}
	/** Las partes que no se tocan (enteras o con lo concreto que no se eligió). */
	public static function untouched( $selection ) {
		if ( null === $selection ) return array();
		$out = array();
		foreach ( self::PARTS as $part => $name ) {
			if ( in_array( $part, (array) $selection['parts'], true ) ) continue;
			if ( ( 'plugins' === $part && $selection['plugins'] ) || ( 'themes' === $part && $selection['themes'] ) || ( 'uploads' === $part && $selection['sites'] ) ) $out[] = 'los demás ' . ( 'uploads' === $part ? 'medios' : $name );
			else $out[] = $name;
		}
		return $out;
	}
	/**
	 * Qué reemplaza exactamente la selección, una frase por parte, con lo que se puede saber antes: con el inventario del
	 * respaldo y el del sitio, qué plugins y temas se retiran porque el respaldo no los tiene (y si están activos).
	 * Devuelve array( 'replaces' => frases, 'untouched' => nombres, 'warnings' => frases de lo que se retira activo ).
	 */
	public static function describe( $selection, $inventory = null, $live = null, $complete = true ) {
		$replaces = array();
		$warnings = array();
		$parts = null === $selection ? array_keys( self::PARTS ) : (array) $selection['parts'];
		foreach ( $parts as $part ) {
			$text = ucfirst( self::PARTS[ $part ] ) . ': ' . self::REPLACES[ $part ];
			if ( 'uploads' === $part ) $text .= self::has_database( $selection ) ? '; se retiran los que el respaldo no tiene' : '; se conservan los subidos después (salvo archivos ejecutables)';
			// Un respaldo anterior al manifiesto v3 no dice qué sobra: con él no se retira nada (revisión B3).
			if ( ! $complete && in_array( $part, array( 'plugins', 'themes', 'uploads', 'core', 'mu-plugins' ), true ) ) $text = preg_replace( '/[:;] se retiran los que el respaldo no tiene/u', '', $text ) . ' (respaldo anterior al formato v3: no se retira nada que el respaldo no traiga)';
			if ( $complete && ( 'plugins' === $part || 'themes' === $part ) && is_array( $inventory ) && is_array( $live ) ) {
				$gone = array_values( array_diff( (array) $live[ $part ], (array) $inventory[ $part ] ) );
				if ( $gone ) {
					$text .= ' (' . count( $gone ) . ': ' . self::list_text( $gone, 10 ) . ')';
					$active = array_values( array_intersect( $gone, (array) $live[ 'plugins' === $part ? 'active_plugins' : 'active_themes' ] ) );
					if ( $active ) $warnings[] = ( 'plugins' === $part ? 'Se retirarán plugins activos que el respaldo no tiene: ' : 'Se retirará el tema activo, que el respaldo no tiene: ' ) . self::list_text( $active, 10 ) . ( 'plugins' === $part ? '. WordPress los desactiva al no encontrarlos.' : '. WordPress pasará a otro tema.' );
				} elseif ( is_array( $inventory ) ) {
					$text .= ' (ninguno)';
				}
			}
			$replaces[] = $text . '.';
		}
		if ( null !== $selection ) {
			if ( $selection['plugins'] ) $replaces[] = ( 1 === count( $selection['plugins'] ) ? 'El plugin ' : 'Los plugins ' ) . self::list_text( $selection['plugins'], 20 ) . ': su carpeta (o su archivo) entera, como en el respaldo; los demás plugins no se tocan.';
			if ( $selection['themes'] ) $replaces[] = ( 1 === count( $selection['themes'] ) ? 'El tema ' : 'Los temas ' ) . self::list_text( $selection['themes'], 20 ) . ': su carpeta entera, como en el respaldo; los demás temas no se tocan.';
			if ( $selection['sites'] ) $replaces[] = 'Los medios ' . ( 1 === count( $selection['sites'] ) ? 'del sitio ' : 'de los sitios ' ) . self::list_text( $selection['sites'], 20 ) . ' de la red (el sitio principal: lo que no está en sites/)' . ( self::has_database( $selection ) ? '; se retiran los que el respaldo no tiene.' : '; se conservan los subidos después (salvo archivos ejecutables).' );
		}
		return array( 'replaces' => $replaces, 'untouched' => self::untouched( $selection ), 'warnings' => $warnings );
	}
	/** Los avisos de coherencia de la selección (RULES): array( array( code, text ) ). Nunca bloquean. */
	public static function coherence( $selection ) {
		if ( null === $selection ) return array();
		$on = array_fill_keys( (array) $selection['parts'], true );
		if ( $selection['plugins'] ) $on['plugin-items'] = true;
		if ( $selection['themes'] ) $on['theme-items'] = true;
		if ( $selection['sites'] ) $on['site-items'] = true;
		$out = array();
		foreach ( self::RULES as $code => $rule ) {
			if ( ! array_intersect_key( $on, array_flip( $rule['any'] ) ) ) continue;
			if ( isset( $rule['none'] ) && array_intersect_key( $on, array_flip( $rule['none'] ) ) ) continue;
			if ( isset( $rule['missing'] ) && ! array_diff( $rule['missing'], array_keys( $on ) ) ) continue;
			$out[] = array( 'code' => $code, 'text' => $rule['text'] );
		}
		return $out;
	}
	/**
	 * El informe de una restauración (pantalla, REST, WP-CLI y correo): qué partes se restauraron y cuáles no. Con todo,
	 * `all` y ninguna sin restaurar.
	 */
	public static function report( $selection ) {
		$restored = array();
		if ( null === $selection ) {
			foreach ( self::PARTS as $name ) $restored[] = $name;
			return array( 'all' => true, 'selection' => null, 'restored' => $restored, 'not_restored' => array(), 'label' => 'todo', 'text' => '', 'coherence' => array() );
		}
		$label = self::label( $selection );
		$untouched = self::untouched( $selection );
		return array(
			'all' => false,
			'selection' => $selection,
			'restored' => array_merge( array_map( function( $part ) { return self::PARTS[ $part ]; }, (array) $selection['parts'] ), $selection['plugins'] ? array( 'plugins: ' . implode( ', ', $selection['plugins'] ) ) : array(), $selection['themes'] ? array( 'temas: ' . implode( ', ', $selection['themes'] ) ) : array(), $selection['sites'] ? array( 'medios de los sitios: ' . implode( ', ', $selection['sites'] ) ) : array() ),
			'not_restored' => $untouched,
			'label' => $label,
			'text' => 'Restauración por partes: se restauró ' . $label . '; no se tocó ' . self::list_text( $untouched, 20 ) . '.',
			'coherence' => self::coherence( $selection ),
		);
	}
	/** «a, b y c» (con «y N más» si pasa de $max). */
	public static function list_text( array $items, $max = 10 ) {
		$items = array_values( array_map( 'strval', $items ) );
		$extra = count( $items ) - $max;
		if ( $extra > 0 ) $items = array_merge( array_slice( $items, 0, $max ), array( $extra . ' más' ) );
		if ( count( $items ) < 2 ) return implode( '', $items );
		return implode( ', ', array_slice( $items, 0, -1 ) ) . ' y ' . end( $items );
	}
}
