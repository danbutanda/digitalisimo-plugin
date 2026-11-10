<?php
defined( 'ABSPATH' ) || exit;

/**
 * Exclusiones por defecto del respaldo completo (B8 de BRECHAS): lo que nunca es parte del sitio y, si entrara, lo haría
 * crecer sin límite.
 *
 * - Las carpetas de otros plugins de respaldo en wp-content (Backuply, UpdraftPlus, All-in-One WP Migration, Backup
 *   Migration, Duplicator): el primer respaldo de un sitio que viene de Backuply arrastraría todos sus `.tar.gz`.
 * - La caché de páginas de wp-content (`wp-content/cache`), que se regenera sola.
 * - Los registros (`*.log`, `error_log`) en cualquier carpeta.
 *
 * El almacén privado de Backups y la base SQLite activa ya se omitían antes. Las reglas que se aplicaron quedan en el
 * manifiesto (`excluded`), y el filtro `digitalisimo_backups_default_exclusions` permite incluirlas
 * (`add_filter( 'digitalisimo_backups_default_exclusions', '__return_empty_array' )`) o reducir la lista.
 *
 * La restauración nunca retira nada de esto (aunque el respaldo no lo traiga): siempre con la lista fija, también si el
 * filtro la cambió, porque conservar de más nunca daña el sitio y retirar los respaldos de otro plugin sí.
 */
final class Digitalisimo_Backups_Exclusions {
	/** Carpetas de wp-content que no entran: otros plugins de respaldo y la caché de páginas. */
	const FOLDERS = array( 'backuply', 'updraft', 'ai1wm-backups', 'backup-migration', 'backups-dup-lite', 'backups-dup-pro', 'cache' );
	/** Archivos que no entran, en cualquier carpeta: `*.extensión` o un nombre exacto (sin distinguir mayúsculas). */
	const FILES = array( '*.log', 'error_log' );

	/**
	 * Reglas de este respaldo, relativas a la raíz de WordPress $root (con barra final): `folders` (ruta relativa => true) y
	 * `files` (patrones). Sin wp-content dentro de la raíz no hay carpetas que excluir (ese caso ya se rechaza antes).
	 */
	public static function rules( $root, $content ) {
		$default = array( 'folders' => self::FOLDERS, 'files' => self::FILES );
		$chosen = function_exists( 'apply_filters' ) ? apply_filters( 'digitalisimo_backups_default_exclusions', $default ) : $default;
		$chosen = is_array( $chosen ) ? $chosen : array();
		$root = rtrim( wp_normalize_path( (string) $root ), '/' ) . '/';
		$content = rtrim( wp_normalize_path( (string) $content ), '/' );
		$base = 0 === strpos( $content . '/', $root ) ? substr( $content, strlen( $root ) ) : null;
		$out = array( 'folders' => array(), 'files' => array() );
		// Sólo nombres simples: una regla nunca sale de wp-content ni excluye una carpeta de código.
		if ( null !== $base && '' !== $base ) foreach ( (array) ( $chosen['folders'] ?? array() ) as $folder ) if ( is_string( $folder ) && preg_match( '/^[A-Za-z0-9._-]+$/', $folder ) && ! in_array( $folder, array( '.', '..', 'plugins', 'themes', 'mu-plugins', 'uploads' ), true ) ) $out['folders'][ $base . '/' . $folder ] = true;
		foreach ( (array) ( $chosen['files'] ?? array() ) as $pattern ) if ( is_string( $pattern ) && preg_match( '/^(?:\*\.[A-Za-z0-9]+|[A-Za-z0-9._-]+)$/', $pattern ) && ! in_array( strtolower( $pattern ), array( 'wp-config.php', 'index.php', '.htaccess' ), true ) ) $out['files'][] = strtolower( $pattern );
		$out['files'] = array_values( array_unique( $out['files'] ) );
		return $out;
	}
	/** La ruta relativa $relative (carpeta si $is_dir) queda fuera del respaldo con $rules. */
	public static function excluded( $relative, $is_dir, array $rules ) {
		$relative = trim( (string) $relative, '/' );
		if ( '' === $relative ) return false;
		foreach ( array_keys( (array) ( $rules['folders'] ?? array() ) ) as $folder ) if ( $relative === $folder || 0 === strpos( $relative . '/', $folder . '/' ) ) return true;
		if ( $is_dir ) return false;
		return self::file_matches( $relative, (array) ( $rules['files'] ?? array() ) );
	}
	/** Para el manifiesto: carpetas con barra final y patrones de archivo. */
	public static function declared( array $rules ) {
		$out = array();
		foreach ( array_keys( (array) ( $rules['folders'] ?? array() ) ) as $folder ) $out[] = $folder . '/';
		foreach ( (array) ( $rules['files'] ?? array() ) as $pattern ) $out[] = (string) $pattern;
		return $out;
	}
	/**
	 * La restauración conserva $relative (nunca lo retira por no estar en el respaldo): las carpetas de la lista fija bajo
	 * cualquier wp-content y los registros. Sin filtro: conservar de más es inofensivo.
	 */
	public static function kept( $relative ) {
		$relative = trim( wp_normalize_path( (string) $relative ), '/' );
		if ( '' === $relative ) return false;
		$content = defined( 'WP_CONTENT_DIR' ) ? basename( wp_normalize_path( (string) WP_CONTENT_DIR ) ) : 'wp-content';
		foreach ( array_unique( array( 'wp-content', $content ) ) as $base ) foreach ( self::FOLDERS as $folder ) if ( $relative === $base . '/' . $folder || 0 === strpos( $relative . '/', $base . '/' . $folder . '/' ) ) return true;
		return self::file_matches( $relative, self::FILES );
	}
	private static function file_matches( $relative, array $patterns ) {
		$name = strtolower( basename( (string) $relative ) );
		foreach ( $patterns as $pattern ) {
			$pattern = strtolower( (string) $pattern );
			if ( 0 === strpos( $pattern, '*.' ) ? substr( $name, -( strlen( $pattern ) - 1 ) ) === substr( $pattern, 1 ) && strlen( $name ) > strlen( $pattern ) - 1 : $name === $pattern ) return true;
		}
		return false;
	}
}
