<?php
namespace Digitalisimo\Elements\Import;

use Digitalisimo\Elements\Import\Kits\Preserve;

defined( 'ABSPATH' ) || exit;

/**
 * Widget oculto `digitalisimo-unconverted` (D9, §10.3): guarda un elemento que este sitio no sabe pintar.
 *
 * Lo crea `Kits\Preserve::wrap()` al importar un kit o una plantilla, con el original completo y el motivo en el
 * ajuste `digitalisimo_unconverted`. No aparece en el panel, no pinta nada en la página (presupuesto 0/0: ni CSS ni
 * JS), avisa en el editor con una tarjeta y, al exportar, devuelve el original con su ID. Sólo se registra si el
 * sitio tiene marcadores o se está importando (CR-21): esta clase sólo se carga entonces, porque extiende el
 * widget base del núcleo.
 */
class Unconverted_Widget extends \Elementor\Widget_Base {
	public function get_name() {
		return Module::WIDGET;
	}

	public function get_title() {
		return 'Contenido conservado';
	}

	public function get_icon() {
		return 'eicon-warning';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array();
	}

	public function show_in_panel() {
		return false;
	}

	public function get_style_depends() {
		return array();
	}

	public function get_script_depends() {
		return array();
	}

	protected function register_controls() {
		$hidden = defined( '\\Elementor\\Controls_Manager::HIDDEN' ) ? \Elementor\Controls_Manager::HIDDEN : 'hidden';
		$raw    = defined( '\\Elementor\\Controls_Manager::RAW_HTML' ) ? \Elementor\Controls_Manager::RAW_HTML : 'raw_html';
		$this->start_controls_section( 'section_digitalisimo_unconverted', array( 'label' => 'Contenido conservado' ) );
		$this->add_control(
			Preserve::SETTING,
			array(
				'type'    => $hidden,
				'default' => array(),
			)
		);
		$this->add_control(
			'digitalisimo_unconverted_notice',
			array(
				'type' => $raw,
				'raw'  => esc_html( 'Este elemento venía en un kit o una plantilla y este sitio no sabe pintarlo. Se conserva tal cual, sin mostrarse en la página. Si su tipo vuelve a estar disponible, aparecerá solo; si lo borras, se pierde.' ),
			)
		);
		$this->end_controls_section();
	}

	/** No pinta nada: sin contenido, el núcleo ni siquiera imprime el envoltorio del widget. */
	protected function render() {}

	/** Tarjeta del editor. Todo lo que viene del kit se imprime escapado (`{{ }}`), nunca crudo. */
	protected function content_template() {
		?>
		<# var digiInfo = ( settings && settings.<?php echo esc_js( Preserve::SETTING ); ?> ) || {}; var digiComponent = '<?php echo esc_js( Preserve::REASON_COMPONENT ); ?>' === digiInfo.reason; #>
		<div class="digitalisimo-unconverted" role="note" style="padding:12px 16px;border:1px dashed currentColor;border-radius:4px;font-size:13px;line-height:1.5;opacity:.85">
			<strong>Contenido conservado sin mostrar</strong><br>
			<span>Tipo: {{ digiInfo.type || '—' }}</span><br>
			<# if ( digiComponent ) { #>
				<span>Componente v4: el núcleo aún no los admite en kits. Se conserva, pero no se pinta.</span>
			<# } else { #>
				<span>Este sitio no tiene ese tipo de elemento. Se conserva tal cual y volverá solo cuando el tipo exista.</span>
			<# } #>
		</div>
		<?php
	}

	/**
	 * Al exportar (kit o biblioteca) viaja el original, con su ID: otro sitio que sí lo tenga lo recibe intacto.
	 *
	 * @param array $element_data Datos del marcador.
	 *
	 * @return array
	 */
	public function on_export( $element_data ) {
		$original = Preserve::payload( is_array( $element_data ) ? $element_data : array() );
		return null !== $original ? $original : $element_data;
	}
}
