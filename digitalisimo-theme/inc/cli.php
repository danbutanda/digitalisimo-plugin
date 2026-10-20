<?php
/** DIGITALÍSIMO: `wp digitalisimo-theme` (estado, importación y reversión del paso desde el tema anterior). */
defined( 'ABSPATH' ) || exit;

/**
 * Paso desde el tema anterior. En una red se ejecuta sitio por sitio con --url.
 */
class Digitalisimo_Theme_CLI {

	/**
	 * Muestra el registro y la bitácora del paso desde el tema anterior.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-theme status --url=https://sitio.example/
	 */
	public function status( $args, $assoc ) {
		$data = array(
			'site'     => home_url( '/' ),
			'theme'    => get_stylesheet(),
			'template' => get_template(),
			'version'  => DIGITALISIMO_THEME_VERSION,
			'parity'   => digitalisimo_theme_parity(),
			'record'   => digitalisimo_theme_switch_record(),
			'log'      => digitalisimo_theme_switch_log(),
		);
		WP_CLI::line( (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Repite la importación desde el tema anterior sin pisar lo que se haya editado en este tema.
	 *
	 * ## OPTIONS
	 *
	 * [--from=<stylesheet>]
	 * : Carpeta del tema del que importar. Por defecto, el tema anterior registrado.
	 *
	 * [--dry-run]
	 * : Muestra lo que guardaría, sin escribir nada ni avisar a DIGITALÍSIMO Elements.
	 *
	 * [--child-css]
	 * : Añade al CSS adicional el style.css del tema hijo (con sus url() relativos ya absolutos). Si usa @import no copia nada.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-theme import --url=https://sitio.example/
	 *     wp digitalisimo-theme import --from=hello-elementor-child --child-css --url=https://sitio.example/
	 */
	public function import( $args, $assoc ) {
		if ( Digitalisimo_Theme_Updater::URI !== (string) wp_get_theme( get_template() )->get( 'UpdateURI' ) ) {
			WP_CLI::error( 'El tema DIGITALÍSIMO no está activo en este sitio.' );
		}
		$record = digitalisimo_theme_switch_record();
		$from   = (string) ( $assoc['from'] ?? '' );
		if ( '' === $from ) {
			$from = (string) ( $record['last']['stylesheet'] ?? ( $record['origin']['stylesheet'] ?? '' ) );
		}
		if ( '' === $from ) {
			WP_CLI::error( 'No hay un tema anterior registrado: indica --from=<stylesheet>.' );
		}
		$old = wp_get_theme( $from );
		if ( ! $old->exists() ) {
			WP_CLI::error( sprintf( 'El tema %s no está instalado.', $from ) );
		}
		$result = digitalisimo_theme_import_previous(
			$old,
			array(
				'force'     => true,
				'dry_run'   => ! empty( $assoc['dry-run'] ),
				'child_css' => ! empty( $assoc['child-css'] ),
				'via'       => 'cli',
			)
		);
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::line( (string) wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		if ( $result['dry_run'] ) {
			WP_CLI::success( 'Simulación: no se escribió nada.' );
		} else {
			WP_CLI::success( sprintf( 'Importado desde %s (%s).', $from, $result['record']['state'] ?? 'done' ) );
		}
	}

	/**
	 * Vuelve al tema anterior registrado. No escribe ajustes de ese tema.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : No pide confirmación.
	 *
	 * [--force]
	 * : Sin DIGITALÍSIMO Elements activo, deja en borrador las plantillas de cabecera y pie generadas.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-theme revert --url=https://sitio.example/
	 */
	public function revert( $args, $assoc ) {
		$record = digitalisimo_theme_switch_record();
		$target = (string) ( $record['last']['stylesheet'] ?? '' );
		if ( '' === $target ) {
			WP_CLI::error( 'No hay un tema anterior registrado al que volver.' );
		}
		WP_CLI::confirm( sprintf( '¿Volver al tema %s en %s?', $target, home_url( '/' ) ), $assoc );
		$result = digitalisimo_theme_revert( array( 'force' => ! empty( $assoc['force'] ) ) );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( sprintf( 'El sitio vuelve a usar el tema %s.', $target ) );
	}
}

WP_CLI::add_command( 'digitalisimo-theme', 'Digitalisimo_Theme_CLI' );
