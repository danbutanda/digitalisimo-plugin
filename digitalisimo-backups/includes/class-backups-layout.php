<?php
defined( 'ABSPATH' ) || exit;

/**
 * Carpetas que WordPress carga y que un respaldo completo debe contener.
 *
 * El respaldo recorre ABSPATH y no sigue enlaces simbólicos. Una carpeta definida fuera de ABSPATH, o alcanzada por un
 * enlace, quedaría fuera del ZIP: wp-content, plugins, mu-plugins, medios o un directorio de temas registrado. Esta
 * comprobación lo detecta antes de exportar nada y nombra la carpeta, quién la define y qué hacer. Así, un respaldo
 * nunca se presenta como completo sin ella.
 *
 * `wp-config.php` no pasa por aquí: el motor lo busca donde WordPress lo carga (ABSPATH o su carpeta superior), lo
 * guarda aunque sea un enlace y nunca lo escribe al restaurar.
 */
final class Digitalisimo_Backups_Layout {
	/** Carpetas de esta instalación: clave => array( ruta definida, nombre, quién la define ). */
	public static function folders() {
		$folders = array(
			'wp-content' => array( WP_CONTENT_DIR, 'wp-content', 'WP_CONTENT_DIR' ),
			'plugins' => array( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '', 'La carpeta de plugins', 'WP_PLUGIN_DIR' ),
			'mu-plugins' => array( defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : '', 'La carpeta de plugins obligatorios', 'WPMU_PLUGIN_DIR' ),
		);
		if ( function_exists( 'wp_upload_dir' ) ) {
			$uploads = wp_upload_dir( null, false );
			if ( is_array( $uploads ) && ! empty( $uploads['basedir'] ) ) $folders['uploads'] = array( (string) $uploads['basedir'], 'La carpeta de medios', 'UPLOADS o la opción upload_path' );
		}
		foreach ( array_values( (array) ( $GLOBALS['wp_theme_directories'] ?? array() ) ) as $index => $directory ) {
			$folders[ 'themes-' . $index ] = array( (string) $directory, 'Un directorio de temas', 'register_theme_directory()' );
		}
		return $folders;
	}
	/** Lanza el primer problema; un respaldo completo no empieza con una carpeta de WordPress fuera de su alcance. */
	public static function assert_complete() {
		$problems = self::problems();
		if ( $problems ) throw new Exception( $problems[0] );
	}
	/**
	 * Problemas que impiden un respaldo completo, en el orden de folders(). Una carpeta que no existe no tiene archivos
	 * que perder, salvo wp-content. Dentro de una carpeta externa ya anotada no se repite el aviso de sus subcarpetas.
	 */
	public static function problems( $abspath = null, $folders = null ) {
		$abspath = null === $abspath ? ABSPATH : (string) $abspath;
		$folders = null === $folders ? self::folders() : (array) $folders;
		$real_root = realpath( $abspath );
		if ( false === $real_root ) return array( 'No se pudo resolver la carpeta de WordPress (ABSPATH: ' . $abspath . ').' );
		$root = self::normalize( $real_root );
		$problems = array();
		$reported = array();
		foreach ( $folders as $key => $folder ) {
			list( $path, $label, $setting ) = array_pad( array_values( (array) $folder ), 3, '' );
			$path = (string) $path;
			if ( '' === $path ) continue;
			$real = realpath( $path );
			if ( false === $real ) {
				if ( 'wp-content' === $key ) $problems[] = 'No se pudo resolver wp-content (' . $path . ', definida por WP_CONTENT_DIR).';
				continue;
			}
			$real = self::normalize( $real );
			foreach ( $reported as $outside ) if ( self::within( $real, $outside ) ) continue 2;
			$link = self::link_inside( array( self::normalize( $abspath ), $root ), self::normalize( $path ) );
			if ( '' !== $link ) {
				$problems[] = $label . ' (' . $setting . ') pasa por el enlace simbólico ' . $link . ', que apunta a ' . self::normalize( (string) realpath( $link ) ) . '. El respaldo completo no sigue enlaces simbólicos, así que quedaría incompleto y no se crea. Para respaldar esta instalación, sustituye el enlace por la carpeta real dentro de ' . $root . '.';
				$reported[] = $real;
			} elseif ( ! self::within( $real, $root ) ) {
				$problems[] = $label . ' (' . $setting . ') está en ' . $real . ', fuera de la carpeta de WordPress (' . $root . '). El respaldo completo sólo recorre esa carpeta y todavía no incluye carpetas externas, así que quedaría incompleto y no se crea. Para respaldar esta instalación, mueve la carpeta dentro de ' . $root . ' y ajusta ' . $setting . '.';
				$reported[] = $real;
			}
		}
		return array_values( array_unique( $problems ) );
	}
	private static function normalize( $path ) {
		return rtrim( str_replace( '\\', '/', (string) $path ), '/' );
	}
	private static function within( $path, $root ) {
		return $path === $root || 0 === strpos( $path, $root . '/' );
	}
	/** Primer enlace simbólico entre la carpeta de WordPress y $path (sin contar la propia carpeta de WordPress). */
	private static function link_inside( array $roots, $path ) {
		foreach ( $roots as $root ) {
			if ( $path === $root || 0 !== strpos( $path, $root . '/' ) ) continue;
			$current = $root;
			foreach ( explode( '/', substr( $path, strlen( $root ) + 1 ) ) as $part ) {
				if ( '' === $part || '.' === $part ) continue;
				$current .= '/' . $part;
				if ( is_link( $current ) ) return $current;
			}
			return '';
		}
		return '';
	}
}
