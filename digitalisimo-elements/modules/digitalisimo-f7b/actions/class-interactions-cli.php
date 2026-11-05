<?php
namespace Digitalisimo\Elements\F7b\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * `wp digitalisimo-elements interactions …`: estado e inventario de las interacciones PRO del sitio (F7b, frente B).
 *
 * En una red, cada sitio con `--url=`. «play» hace lo mismo que el botón del aviso; el inventario lo escribe el
 * recorrido por lotes (`F7b\Scan`).
 */
final class Interactions_Cli {
	/**
	 * Muestra el estado y los documentos con interacciones que sólo reproduce PRO.
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-elements interactions status
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 */
	public function status( $args = array(), $assoc_args = array() ): void {
		$inventory = Interactions_State::inventory();
		$labels    = array(
			Interactions_State::PENDING => 'en pausa',
			Interactions_State::CLEAR   => 'se reproducen (no había ninguna guardada)',
			Interactions_State::ACK     => 'se reproducen (autorizadas)',
		);
		\WP_CLI::log( 'Estado: ' . $labels[ Interactions_State::state() ] . '.' );
		if ( ! $inventory['done'] ) {
			\WP_CLI::log( 'El inventario aún no ha terminado.' );
			return;
		}
		\WP_CLI::log( sprintf( 'Documentos con interacciones: %d.', max( count( $inventory['docs'] ), $inventory['total'] ) ) );
		foreach ( $inventory['docs'] as $id ) {
			\WP_CLI::log( sprintf( '- %d: %s', $id, get_the_title( $id ) ) );
		}
	}

	/**
	 * Reproduce las interacciones guardadas en este sitio (el botón del aviso).
	 *
	 * ## EXAMPLES
	 *
	 *     wp digitalisimo-elements interactions play
	 *
	 * @param array $args       Argumentos.
	 * @param array $assoc_args Opciones.
	 */
	public function play( $args = array(), $assoc_args = array() ): void {
		if ( ! Interactions_State::set_state( Interactions_State::ACK ) ) {
			\WP_CLI::error( 'No se pudo guardar el estado.' );
		}
		\WP_CLI::success( 'Las interacciones guardadas se reproducen en este sitio.' );
	}
}
