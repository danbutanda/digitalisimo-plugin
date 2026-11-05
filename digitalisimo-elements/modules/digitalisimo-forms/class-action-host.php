<?php
namespace Digitalisimo\Elements\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Anfitrión de los controles de las acciones de envío para el Formulario propio.
 *
 * Cada acción registrada en el módulo de formularios del constructor añade sus controles con
 * `register_settings_section( $widget )`, y las integraciones con listas de correo (Mailchimp, Drip, ActiveCampaign,
 * GetResponse, ConvertKit, MailerLite, MailPoet) lo terminan con `Integration_Base::register_fields_map_control( Form
 * $form )`, que exige una instancia del widget de PRO. Este objeto la satisface y **reenvía cada llamada de registro al
 * Formulario propio**: los controles, sus valores por defecto y sus condiciones quedan en el widget propio exactamente
 * como en el de PRO, sin reescribir ninguna acción. No pinta nada, no guarda estado propio y sólo se crea mientras el
 * Formulario registra sus controles (`Widgets\Form::register_controls()`).
 *
 * Lo que no se reenvía: `add_group_control()`, `add_responsive_control()` y `update_responsive_control()` son finales en
 * el núcleo, pero terminan en `add_control()`/`update_control()`, que sí se reenvían; los «popover» (`start_popover()`,
 * finales) no los usa ninguna acción.
 */
final class Action_Host extends \ElementorPro\Modules\Forms\Widgets\Form {
	/** @var \Elementor\Controls_Stack|null */
	private $host = null;

	/** Anfitrión de `$host` (un tipo, sin ajustes: no registra controles propios al crearse). */
	public static function wrap( \Elementor\Controls_Stack $host ): self {
		$proxy       = new self();
		$proxy->host = $host;
		return $proxy;
	}

	public function get_name() {
		return null === $this->host ? '' : $this->host->get_name();
	}

	public function get_unique_name() {
		return $this->host->get_unique_name();
	}

	public function get_id() {
		return $this->host->get_id();
	}

	public function get_current_section() {
		return $this->host->get_current_section();
	}

	public function get_current_tab() {
		return $this->host->get_current_tab();
	}

	public function get_controls( $control_id = null ) {
		return $this->host->get_controls( $control_id );
	}

	public function add_control( $id, array $args, $options = array() ) {
		return $this->host->add_control( $id, $args, $options );
	}

	public function remove_control( $control_id ) {
		return $this->host->remove_control( $control_id );
	}

	public function update_control( $control_id, array $args, array $options = array() ) {
		return $this->host->update_control( $control_id, $args, $options );
	}

	public function start_controls_section( $section_id, array $args = array() ) {
		$this->host->start_controls_section( $section_id, $args );
	}

	public function end_controls_section() {
		$this->host->end_controls_section();
	}

	public function start_controls_tabs( $tabs_id, array $args = array() ) {
		$this->host->start_controls_tabs( $tabs_id, $args );
	}

	public function end_controls_tabs() {
		$this->host->end_controls_tabs();
	}

	public function start_controls_tab( $tab_id, $args ) {
		$this->host->start_controls_tab( $tab_id, $args );
	}

	public function end_controls_tab() {
		$this->host->end_controls_tab();
	}

	public function get_settings_for_display( $setting_key = null ) {
		return $this->host->get_settings_for_display( $setting_key );
	}

	/** Nunca se pinta ni se registra en el panel. */
	public function show_in_panel() {
		return false;
	}

	protected function register_controls() {}

	protected function render() {}
}
