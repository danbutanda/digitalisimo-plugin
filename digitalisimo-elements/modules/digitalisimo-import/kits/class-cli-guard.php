<?php
namespace Digitalisimo\Elements\Import\Kits;

defined( 'ABSPATH' ) || exit;

/**
 * Guardas de WP-CLI del flujo heredado (§7.6, D4, CC-24, V-27, D19).
 *
 * `wp elementor kit` importa con el 2.0 y, **antes** de cualquier acción, descarga con `--sourceType=remote|library`
 * y cambia la opción de subidas con `--unfilteredFilesUpload=enable`. `wp elementor library import` acepta una URL y
 * trocea un kit. Con el núcleo incluido, `before_invoke` corta antes de que el comando haga nada y manda a
 * `wp digitalisimo-elements kit`. Con el plugin Elementor original activo no se instala (D19).
 */
final class Cli_Guard {
	const KIT_COMMANDS = array( 'import', 'export', 'revert' );

	/** Registra las guardas si corre WP-CLI con el núcleo incluido. */
	public static function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\WP_CLI' ) || ! Legacy_Guard::applies() ) {
			return;
		}
		foreach ( self::KIT_COMMANDS as $command ) {
			\WP_CLI::add_hook( 'before_invoke:elementor kit ' . $command, array( __CLASS__, 'kit_' . $command ) );
		}
		\WP_CLI::add_hook( 'before_invoke:elementor library import', array( __CLASS__, 'library_import' ) );
		\WP_CLI::add_hook( 'before_invoke:elementor library import-dir', array( __CLASS__, 'library_import_dir' ) );
	}

	public static function kit_import(): void {
		self::stop( 'import' );
	}

	public static function kit_export(): void {
		self::stop( 'export' );
	}

	public static function kit_revert(): void {
		self::stop( 'revert' );
	}

	/** Mensaje del comando heredado, que manda al propio. */
	public static function message( string $command ): string {
		return sprintf( 'Usa «wp digitalisimo-elements kit %s»: el comando heredado de kits está desactivado porque instalaba plugins, cambiaba el tema y descargaba kits por URL.', $command );
	}

	/** `elementor library import`: nunca una URL; un ZIP de kit va a `wp digitalisimo-elements kit import`. */
	public static function library_import(): void {
		$error = self::library_error( self::positional( 'import' ) );
		if ( '' !== $error ) {
			\WP_CLI::error( $error );
		}
	}

	/** `elementor library import-dir`: ningún ZIP de kit en la carpeta. */
	public static function library_import_dir(): void {
		$dir = self::positional( 'import-dir' );
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}
		foreach ( (array) glob( rtrim( $dir, '/\\' ) . '/*.zip' ) as $file ) {
			$error = self::library_error( (string) $file );
			if ( '' !== $error ) {
				\WP_CLI::error( $error );
			}
		}
	}

	/** Motivo por el que la biblioteca no debe importar ese argumento, o ''. */
	public static function library_error( string $file ): string {
		if ( '' === $file ) {
			return '';
		}
		if ( filter_var( $file, FILTER_VALIDATE_URL ) || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $file ) ) {
			return 'La biblioteca no importa plantillas desde una URL: descarga el archivo y revísalo antes de importarlo.';
		}
		if ( self::is_kit_zip( $file ) ) {
			return 'Este ZIP es un kit: impórtalo con «wp digitalisimo-elements kit import», que no lo trocea ni cambia el sitio sin permiso.';
		}
		return '';
	}

	/** ¿Es un ZIP con `manifest.json` en la raíz o en una sola subcarpeta? */
	public static function is_kit_zip( string $file ): bool {
		if ( ! is_file( $file ) || ! class_exists( '\ZipArchive' ) || 'zip' !== strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
			return false;
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file ) ) {
			return false;
		}
		$found = false;
		for ( $i = 0; $i < $zip->numFiles && $i < 10000; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$name = strtolower( (string) $zip->getNameIndex( $i ) );
			if ( 'manifest.json' === $name || preg_match( '#^[^/]+/manifest\.json$#', $name ) ) {
				$found = true;
				break;
			}
		}
		$zip->close();
		return $found;
	}

	/** Primer argumento posicional tras el subcomando (`import` o `import-dir`). */
	private static function positional( string $after ): string {
		if ( ! method_exists( '\WP_CLI', 'get_runner' ) ) {
			return '';
		}
		$arguments = (array) \WP_CLI::get_runner()->arguments;
		$index     = array_search( $after, $arguments, true );
		return false !== $index && isset( $arguments[ $index + 1 ] ) ? (string) $arguments[ $index + 1 ] : '';
	}

	private static function stop( string $command ): void {
		\WP_CLI::error( self::message( $command ) );
	}
}
