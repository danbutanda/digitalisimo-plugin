<?php
namespace Digitalisimo\Elements\Woo\Quick_View;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-trigger.php';
require_once __DIR__ . '/class-render.php';
require_once __DIR__ . '/class-template.php';

/**
 * Widget «Vista rápida» (`digitalisimo-woo-quick-view`): el botón que abre el diálogo (V.5).
 *
 * - **Contenido:** «Diseño propio» (por defecto: imagen y galería, título, valoración, precio, extracto, compra,
 *   referencia y categorías, «Ver la ficha completa») o «Plantilla de producto» (opt-in, `digitalisimo_template_id`,
 *   que la puerta de PRO recorre: la página carga los paquetes de PRO si la plantilla los necesita; el editor lo avisa).
 * - **Ancho del diálogo:** el control escribe `--digi-qv-width` en el widget y el script lo copia al diálogo al abrir
 *   (el diálogo vive fuera del widget, V-I9). Igual con el fondo, el color, el radio y el relleno.
 * - Sin WooCommerce, `Woo\Inert` registra esta misma clase oculta: `register_controls()` no llama a WooCommerce.
 */
class Widget extends \Digitalisimo\Elements\Woo\Widget {
	public function get_name() { return 'digitalisimo-woo-quick-view'; }
	public function get_title() { return 'Vista rápida'; }
	public function get_icon() { return 'eicon-preview-medium'; }
	public function get_keywords() { return array( 'vista rápida', 'producto', 'diálogo', 'modal', 'woocommerce' ); }
	public function get_style_depends() { return array( 'digitalisimo-woo-quick-view' ); }
	public function get_script_depends() { return array( 'digitalisimo-woo-quick-view' ); }

	protected function register_controls() {
		$c        = '\\Elementor\\Controls_Manager';
		$query    = class_exists( '\\ElementorPro\\Modules\\QueryControl\\Module' ) ? \ElementorPro\Modules\QueryControl\Module::QUERY_CONTROL_ID : $c::NUMBER;
		$template = class_exists( '\\ElementorPro\\Modules\\QueryControl\\Controls\\Template_Query' ) ? \ElementorPro\Modules\QueryControl\Controls\Template_Query::CONTROL_ID : $query;
		$own      = array( 'content_mode' => 'own' );

		$this->start_controls_section( 'section_quick_view', array( 'label' => 'Vista rápida' ) );
		$this->add_control( 'product_source', array( 'label' => 'Producto', 'type' => $c::SELECT, 'default' => 'current', 'options' => array( 'current' => 'Actual (el del bucle o la ficha)', 'custom' => 'Uno concreto' ) ) );
		$this->add_control( 'product_id', array( 'label' => 'Producto concreto', 'type' => $query, 'label_block' => true, 'options' => array(), 'autocomplete' => array( 'object' => 'post', 'query' => array( 'post_type' => array( 'product' ), 'post_status' => array( 'publish' ) ) ), 'condition' => array( 'product_source' => 'custom' ) ) );
		$this->add_control( 'button_text', array( 'label' => 'Texto del botón', 'type' => $c::TEXT, 'default' => Trigger::DEFAULT_TEXT, 'description' => 'El nombre accesible añade el nombre del producto.' ) );
		$this->add_control( 'button_icon', array( 'label' => 'Icono', 'type' => $c::ICONS, 'skin' => 'inline', 'label_block' => false ) );
		$this->add_control( 'content_mode', array( 'label' => 'Contenido', 'type' => $c::SELECT, 'default' => 'own', 'separator' => 'before', 'options' => array( 'own' => 'Diseño propio', 'template' => 'Plantilla de producto' ) ) );
		$this->add_control(
			'digitalisimo_template_id',
			array(
				'label'        => 'Plantilla',
				'type'         => $template,
				'label_block'  => true,
				'options'      => array(),
				'autocomplete' => array(
					'object' => 'library_template',
					'query'  => array( 'post_status' => array( 'publish' ), 'meta_query' => array( array( 'key' => '_elementor_template_type', 'value' => Template::TYPE ) ) ),
				),
				'actions'      => array( 'edit' => array( 'visible' => true ) ),
				'description'  => 'Una plantilla de «Producto individual» de la biblioteca. Sin plantilla, se usa el diseño propio.',
				'condition'    => array( 'content_mode' => 'template' ),
			)
		);
		$this->add_control( 'template_cost', array( 'type' => $c::RAW_HTML, 'raw' => esc_html__( 'Este modo carga los scripts avanzados del constructor (unos 372 KB) en cada página con el botón, también en los archivos de la tienda.', 'pro-elements' ), 'content_classes' => 'elementor-panel-alert elementor-panel-alert-warning', 'condition' => array( 'content_mode' => 'template' ) ) );
		$this->add_control( 'template_cost_note', array( 'type' => $c::RAW_HTML, 'raw' => esc_html__( 'Si vuelves al diseño propio, vacía también «Plantilla»: mientras tenga una, la página sigue cargando esos scripts.', 'pro-elements' ), 'content_classes' => 'elementor-descriptor', 'condition' => array( 'content_mode' => 'own', 'digitalisimo_template_id!' => '' ) ) );
		$this->add_control( 'qv_parts', array( 'label' => 'Partes', 'type' => $c::SELECT2, 'multiple' => true, 'label_block' => true, 'default' => Render::DEFAULT_PARTS, 'options' => array( 'image' => 'Imagen', 'gallery' => 'Galería (miniaturas)', 'title' => 'Título', 'rating' => 'Valoración', 'price' => 'Precio', 'excerpt' => 'Descripción corta', 'cart' => 'Compra', 'meta' => 'Referencia y categorías', 'link' => '«Ver la ficha completa»' ), 'condition' => $own ) );
		$this->add_control( 'qv_title_tag', array( 'label' => 'Etiqueta del título', 'type' => $c::SELECT, 'default' => 'h2', 'options' => array_combine( Render::TITLE_TAGS, Render::TITLE_TAGS ), 'condition' => $own ) );
		$this->add_control( 'qv_image_size', array( 'label' => 'Tamaño de la imagen', 'type' => $c::SELECT, 'default' => 'woocommerce_single', 'options' => array( 'woocommerce_single' => 'Imagen de la ficha', 'large' => 'Grande', 'medium_large' => 'Mediana grande', 'medium' => 'Mediana', 'full' => 'Original' ), 'condition' => $own ) );
		$this->add_control( 'qv_link_text', array( 'label' => 'Texto del enlace a la ficha', 'type' => $c::TEXT, 'default' => Render::LINK_TEXT, 'condition' => $own ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_button_style', array( 'label' => 'Botón', 'tab' => $c::TAB_STYLE ) );
		if ( class_exists( '\\Elementor\\Group_Control_Typography' ) ) {
			$this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array( 'name' => 'button_typography', 'selector' => '{{WRAPPER}} .digi-qv__open' ) );
		}
		$this->add_control( 'button_color', array( 'label' => 'Color', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-qv__open' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'button_background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-qv__open' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'button_padding', array( 'label' => 'Relleno', 'type' => $c::DIMENSIONS, 'size_units' => array( 'px', 'em', 'rem' ), 'selectors' => array( '{{WRAPPER}} .digi-qv__open' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'button_radius', array( 'label' => 'Radio del borde', 'type' => $c::SLIDER, 'size_units' => array( 'px', '%', 'em' ), 'selectors' => array( '{{WRAPPER}} .digi-qv__open' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_dialog_style', array( 'label' => 'Diálogo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'dialog_width', array( 'label' => 'Ancho', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'rem', 'vw' ), 'range' => array( 'px' => array( 'min' => 320, 'max' => 1600 ), 'vw' => array( 'min' => 30, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}}' => '--digi-qv-width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'dialog_background', array( 'label' => 'Fondo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}}' => '--digi-qv-bg: {{VALUE}};' ) ) );
		$this->add_control( 'dialog_color', array( 'label' => 'Color del texto', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}}' => '--digi-qv-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'dialog_padding', array( 'label' => 'Relleno', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'rem' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}}' => '--digi-qv-padding: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'dialog_radius', array( 'label' => 'Radio del borde', 'type' => $c::SLIDER, 'size_units' => array( 'px', 'rem' ), 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}}' => '--digi-qv-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	private function editing(): bool {
		if ( ! class_exists( '\\Elementor\\Plugin' ) || empty( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$plugin = \Elementor\Plugin::$instance;
		return ( isset( $plugin->editor ) && $plugin->editor->is_edit_mode() ) || ( isset( $plugin->preview ) && $plugin->preview->is_preview_mode() );
	}

	protected function render_widget() {
		$s  = (array) $this->get_settings_for_display();
		$id = 'custom' === ( $s['product_source'] ?? 'current' ) ? (int) ( $s['product_id'] ?? 0 ) : Trigger::current_product();
		$icon = '';
		if ( ! empty( $s['button_icon']['value'] ) && class_exists( '\\Elementor\\Icons_Manager' ) ) {
			ob_start();
			\Elementor\Icons_Manager::render_icon( $s['button_icon'], array( 'aria-hidden' => 'true' ) );
			$icon = (string) ob_get_clean();
		}
		$html = $id > 0 ? Trigger::markup(
			$id,
			array(
				'post'     => Trigger::document_id(),
				'widget'   => (string) $this->get_id(),
				'text'     => (string) ( $s['button_text'] ?? '' ),
				'icon'     => $icon,
				'template' => 'template' === ( $s['content_mode'] ?? 'own' ) && ! empty( $s['digitalisimo_template_id'] ),
			)
		) : '';
		if ( '' === $html && $this->editing() ) {
			echo '<p class="digi-qv__notice">La vista rápida aparece con un producto publicado: el del bucle, el de la ficha o el que elijas.</p>';
			return;
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en Trigger::markup().
	}
}
