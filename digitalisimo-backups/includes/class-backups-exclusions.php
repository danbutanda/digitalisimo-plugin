<?php
defined( 'ABSPATH' ) || exit;

/**
 * Exclusiones por defecto del respaldo completo (B8 de BRECHAS): lo que nunca es parte del sitio y, si entrara, lo haría
 * crecer sin límite.
 *
 * - Las carpetas de otros plugins de respaldo: en wp-content, Backuply, UpdraftPlus, All-in-One WP Migration, Backup
 *   Migration (`backup-migration` y `backup-migration-<aleatorio>`), Duplicator y WPvivid; en los medios, BackWPup
 *   (`uploads/backwpup-*`) y BackupBuddy (`uploads/backupbuddy_backups`).
 * - La caché de páginas de wp-content (`wp-content/cache`), que se regenera sola.
 * - Los registros (`*.log`, `error_log`) fuera del código: en la raíz de WordPress, en wp-content y en los medios
 *   (`uploads/wc-logs`…). Dentro de wp-admin, wp-includes, plugins, temas y mu-plugins un `.log` puede ser parte del
 *   código, así que entra.
 *
 * Una regla de carpeta sólo coincide con carpetas (nunca con un archivo como `object-cache.php`), y una regla de archivo
 * nunca excluye código (`.php`, `.phtml`, `.phar`…). El almacén privado de Backups y la base SQLite activa ya se omitían
 * antes. Las reglas aplicadas quedan en el manifiesto (`excluded`).
 *
 * El filtro `digitalisimo_backups_default_exclusions` sólo puede QUITAR reglas de esta lista (para incluir lo que
 * excluye: `add_filter( 'digitalisimo_backups_default_exclusions', '__return_empty_array' )`); lo que devuelva fuera de
 * ella se ignora. Así la restauración, que nunca retira nada de la lista fija aunque el respaldo no lo traiga, nunca
 * borra algo que el respaldo dejó fuera.
 */
final class Digitalisimo_Backups_Exclusions {
	/**
	 * Carpetas, relativas a wp-content; `*` al final: cualquier nombre que empiece así. Otros plugins de respaldo y la caché.
	 */
	const FOLDERS = array( 'backuply', 'updraft', 'ai1wm-backups', 'backup-migration', 'backup-migration-*', 'backups-dup-lite', 'backups-dup-pro', 'wpvividbackups', 'cache', 'uploads/backwpup-*', 'uploads/backupbuddy_backups' );
	/** Archivos fuera del código, en cualquier carpeta que no sea de código: `*.extensión` o un nombre exacto (sin mayúsculas). */
	const FILES = array( '*.log', 'error_log' );
	/** Lo que una regla de archivo nunca excluye: código que PHP puede ejecutar. */
	const CODE = '/\.(?:php[0-9]?|phtml|pht|phar|phps|inc)$/i';

	/**
	 * Reglas de este respaldo, relativas a la raíz de WordPress $root: `folders` (patrón relativo => true), `files`
	 * (patrones) y `code` (carpetas de código donde una regla de archivo no se aplica). Sólo las de la lista fija que el
	 * filtro no quitó. Sin wp-content dentro de la raíz no hay carpetas que excluir (ese caso ya se rechaza antes).
	 */
	public static function rules( $root, $content ) {
		$default = array( 'folders' => self::FOLDERS, 'files' => self::FILES );
		$chosen = function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_default_exclusions', $default ) : $default;
		$chosen = is_array( $chosen ) ? $chosen : array();
		// El filtro sólo reduce la lista: nada que no esté en ella (ni otra forma de escribirlo) cuenta.
		$folders = array_values( array_intersect( self::FOLDERS, array_filter( (array) ( $chosen['folders'] ?? array() ), 'is_string' ) ) );
		$files = array_values( array_intersect( self::FILES, array_filter( (array) ( $chosen['files'] ?? array() ), 'is_string' ) ) );
		$base = self::content_base( $root, $content );
		$out = array( 'folders' => array(), 'files' => $files, 'code' => self::code_roots( $root, $base ) );
		if ( null !== $base && '' !== $base ) foreach ( $folders as $folder ) $out['folders'][ $base . '/' . $folder ] = true;
		return $out;
	}
	/** La ruta relativa $relative (carpeta si $is_dir) queda fuera del respaldo con $rules. */
	public static function excluded( $relative, $is_dir, array $rules ) {
		$relative = trim( (string) $relative, '/' );
		if ( '' === $relative ) return false;
		foreach ( array_keys( (array) ( $rules['folders'] ?? array() ) ) as $folder ) if ( self::in_folder( $relative, (bool) $is_dir, $folder ) ) return true;
		if ( $is_dir ) return false;
		return self::file_excluded( $relative, (array) ( $rules['files'] ?? array() ), (array) ( $rules['code'] ?? array() ) );
	}
	/** Para el manifiesto: carpetas con barra final y patrones de archivo. */
	public static function declared( array $rules ) {
		$out = array();
		foreach ( array_keys( (array) ( $rules['folders'] ?? array() ) ) as $folder ) $out[] = $folder . '/';
		foreach ( (array) ( $rules['files'] ?? array() ) as $pattern ) $out[] = (string) $pattern;
		return $out;
	}
	/**
	 * La restauración conserva $relative (nunca lo retira por no estar en el respaldo): lo que excluye la lista fija, bajo
	 * wp-content o bajo la carpeta que define WP_CONTENT_DIR. Sin filtro: el filtro sólo puede quitar reglas, así que
	 * todo lo que un respaldo dejó fuera está aquí.
	 */
	public static function kept( $relative ) {
		$relative = trim( wp_normalize_path( (string) $relative ), '/' );
		if ( '' === $relative ) return false;
		$root = defined( 'ABSPATH' ) ? (string) ABSPATH : '';
		$bases = array( 'wp-content' );
		$own = defined( 'WP_CONTENT_DIR' ) ? self::content_base( $root, (string) WP_CONTENT_DIR ) : null;
		if ( null !== $own && '' !== $own ) $bases[] = $own;
		$code = array();
		foreach ( array_unique( $bases ) as $base ) {
			// Sin saber si es carpeta: conservar de más nunca daña el sitio.
			foreach ( self::FOLDERS as $folder ) if ( self::in_folder( $relative, true, $base . '/' . $folder ) ) return true;
			$code = array_merge( $code, self::code_roots( $root, $base ) );
		}
		return self::file_excluded( $relative, self::FILES, array_unique( $code ) );
	}

	/* ------------------------------------------------------------------ Ayudas */

	/** wp-content relativo a la raíz de WordPress, o null si está fuera. */
	private static function content_base( $root, $content ) {
		$root = rtrim( wp_normalize_path( (string) $root ), '/' ) . '/';
		$content = rtrim( wp_normalize_path( (string) $content ), '/' );
		$real_root = realpath( $root );
		$real_content = realpath( $content );
		if ( false !== $real_root && false !== $real_content ) {
			$root = rtrim( wp_normalize_path( $real_root ), '/' ) . '/';
			$content = rtrim( wp_normalize_path( $real_content ), '/' );
		}
		return 0 === strpos( $content . '/', $root ) ? substr( $content, strlen( $root ) ) : null;
	}
	/** Carpetas de código (relativas a la raíz) donde un `.log` puede ser parte de un plugin, un tema o el núcleo. */
	private static function code_roots( $root, $base ) {
		$out = array( 'wp-admin', 'wp-includes' );
		if ( null !== $base && '' !== $base ) foreach ( array( 'plugins', 'themes', 'mu-plugins' ) as $kind ) $out[] = $base . '/' . $kind;
		foreach ( array( 'WP_PLUGIN_DIR', 'WPMU_PLUGIN_DIR' ) as $constant ) {
			if ( ! defined( $constant ) ) continue;
			$relative = self::content_base( $root, (string) constant( $constant ) );
			if ( null !== $relative && '' !== $relative ) $out[] = $relative;
		}
		return array_values( array_unique( $out ) );
	}
	/**
	 * $relative es la carpeta $folder (patrón con `*` final opcional en su último nivel) o está dentro de ella. Si $relative
	 * tiene exactamente los niveles del patrón, sólo coincide si es una carpeta: una regla de carpeta nunca excluye un
	 * archivo con ese nombre.
	 */
	private static function in_folder( $relative, $is_dir, $folder ) {
		$pattern = explode( '/', trim( (string) $folder, '/' ) );
		$segments = explode( '/', (string) $relative );
		$levels = count( $pattern );
		if ( count( $segments ) < $levels || ( count( $segments ) === $levels && ! $is_dir ) ) return false;
		for ( $i = 0; $i < $levels; $i++ ) {
			$want = $pattern[ $i ];
			$have = $segments[ $i ];
			if ( $i === $levels - 1 && '*' === substr( $want, -1 ) ) {
				$prefix = substr( $want, 0, -1 );
				if ( 0 !== strpos( $have, $prefix ) || strlen( $have ) <= strlen( $prefix ) ) return false;
			} elseif ( $have !== $want ) {
				return false;
			}
		}
		return true;
	}
	/** Una regla de archivo, fuera de las carpetas de código y nunca sobre código. */
	private static function file_excluded( $relative, array $patterns, array $code ) {
		$name = strtolower( basename( (string) $relative ) );
		if ( preg_match( self::CODE, $name ) ) return false;
		foreach ( $code as $root ) if ( 0 === strpos( $relative . '/', rtrim( (string) $root, '/' ) . '/' ) ) return false;
		foreach ( $patterns as $pattern ) {
			$pattern = strtolower( (string) $pattern );
			if ( 0 === strpos( $pattern, '*.' ) ? strlen( $name ) > strlen( $pattern ) - 1 && substr( $name, -( strlen( $pattern ) - 1 ) ) === substr( $pattern, 1 ) : $name === $pattern ) return true;
		}
		return false;
	}
}
