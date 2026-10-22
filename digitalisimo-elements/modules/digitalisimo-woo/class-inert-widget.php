<?php
namespace Digitalisimo\Elements\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Sustituto oculto de un widget F4-Woo que no sigue el contrato de `Woo\Widget` (o cuyo archivo falta), para `Inert`.
 *
 * Tiene el mismo nombre que el widget, ningún control y ninguna salida salvo el aviso del editor. Basta para que el
 * núcleo lo instancie y guarde sus ajustes tal cual (`Element_Base::get_data_for_save()` no descarta ajustes sin
 * control). El nombre llega en los argumentos por defecto de la instancia de registro y, en las instancias completas,
 * en su propio `widgetType`.
 */
final class Inert_Widget extends \Elementor\Widget_Base {
	const ARG_NAME  = 'digitalisimo_woo_name';
	const ARG_TITLE = 'digitalisimo_woo_title';

	public function get_name() {
		$type = $this->get_data( 'widgetType' );
		if ( is_string( $type ) && '' !== $type ) {
			return $type;
		}
		$name = $this->get_default_args( self::ARG_NAME );
		return is_string( $name ) && '' !== $name ? $name : 'digitalisimo-woo-inert';
	}

	public function get_title() {
		$title = $this->get_default_args( self::ARG_TITLE );
		return is_string( $title ) && '' !== $title ? $title : Registry::title( $this->get_name() );
	}

	public function get_icon() {
		return 'eicon-woocommerce';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function show_in_panel() {
		return false;
	}

	protected function register_controls() {}

	protected function render() {
		echo Inert::notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- texto propio escapado.
	}
}
