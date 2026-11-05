<?php
namespace Digitalisimo\Elements\Navigation\Widgets;

use Digitalisimo\Elements\Navigation\Module;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Panel fuera de lienzo (`digitalisimo-nested-off-canvas`, C3 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato
 * `docs/pro/c3.md`, frente F): sustituye al «Off-Canvas» anidado de PRO (`modules/off-canvas/widgets/off-canvas.php`,
 * mapa `modules/digitalisimo-convert/maps/off-canvas.php`). Su contenido es un contenedor hijo, como en PRO.
 *
 * - **Controles**: los 19 controles y grupos del widget de PRO, con el mismo nombre, tipo, valor por defecto,
 *   condiciones y sección (estructura derivada de PRO Elements, GPL); textos, selectores, marcado y recursos propios.
 * - **Marcado**: `div.digi-noc` (o la etiqueta elegida) con `role="dialog"`, `aria-modal`, el nombre del panel, cerrado
 *   (`aria-hidden="true"`, `inert`) y con los manejadores de sus hijos en espera (`data-delay-child-handlers`, del
 *   núcleo) hasta la primera apertura. El `id` es el de PRO (`off-canvas-<id>`, con el filtro de los bucles), así los
 *   enlaces de acción existentes (`#elementor-action:action=off_canvas:open|close|toggle&settings={id, displayMode}`)
 *   lo siguen abriendo. `data-digi-noc` lleva lo que el script necesita (animaciones por dispositivo y cierres).
 * - **Script** `assets/js/nested-off-canvas.js` (`defer`, sin dependencias): registra las tres acciones de URL en el
 *   núcleo (sin pisar las que ya existan) y escucha el evento de ventana con el que se abren, cierra y alternan; diálogo
 *   con foco atrapado, Escape, clic en la capa, foco devuelto al disparador, `aria-expanded` en los disparadores,
 *   bloqueo del desplazamiento y las animaciones de entrada y salida del núcleo (`prefers-reduced-motion` las apaga).
 * - **Editor**: `content_template()` repite el marcado del servidor; `assets/js/nested-off-canvas-editor.js` registra el
 *   tipo anidado, abre el panel al editar su sección «Diseño», sigue el «Modo de edición» y añade los paneles propios a
 *   la lista de la etiqueta dinámica «Off-Canvas».
 */
class Nested_Off_Canvas extends Widget_Nested_Base {
	const NAME = 'digitalisimo-nested-off-canvas';

	/** Filtro del `id` que aplican los bucles de PRO (un panel por elemento del bucle) y su etiqueta dinámica. */
	const ID_FILTER = 'elementor-pro/off-canvas/id';

	public function get_name() {
		return self::NAME;
	}

	public function get_title() {
		return esc_html__( 'Panel fuera de lienzo', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-off-canvas';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'panel', 'lateral', 'fuera de lienzo', 'off', 'canvas', 'cajón', 'drawer', 'menú', 'digitalisimo' );
	}

	/** Como en PRO: sólo con el experimento de contenedores. */
	public function show_in_panel() {
		return Module::feature( 'container' );
	}

	/** Como en PRO: sin envoltorio interior con el marcado optimizado. */
	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( self::NAME );
	}

	public function get_script_depends(): array {
		return Module::script_depends( self::NAME );
	}

	protected function get_default_children_elements() {
		return array(
			array(
				'elType'   => 'container',
				'settings' => array( 'content_width' => 'full' ),
			),
		);
	}

	protected function get_default_repeater_title_setting_key() {
		return '';
	}

	/** El contenedor hijo va dentro del contenido del panel. */
	protected function get_default_children_placeholder_selector() {
		return '.digi-noc__content';
	}

	/** Como en PRO: dentro de un bucle, el contenido depende del elemento. */
	protected function is_dynamic_content(): bool {
		global $wp_query;
		return isset( $wp_query->is_loop_widget );
	}

	protected function register_controls() {
		$this->register_layout_section();
		$this->register_settings_section();
		$this->register_background_controls();
		$this->register_overlay_controls();
	}

	protected function register_layout_section() {
		$this->start_controls_section( 'section_layout', array( 'label' => esc_html__( 'Diseño', 'pro-elements' ) ) );

		$this->add_control(
			'learn_more_alert',
			array(
				'type'       => Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content'    => esc_html__( 'Para abrir el panel, añade un enlace o un botón a la página y elige en su enlace la etiqueta dinámica «Off-Canvas» con este panel.', 'pro-elements' ),
			)
		);

		$this->add_control(
			'editing_mode',
			array(
				'label'            => esc_html__( 'Modo de edición', 'pro-elements' ),
				'type'             => Controls_Manager::SWITCHER,
				'label_on'         => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'        => esc_html__( 'No', 'pro-elements' ),
				'return_value'     => 'yes',
				'default'          => 'yes',
				'editor_available' => true,
				'render_type'      => 'ui',
				'separator'        => 'after',
			)
		);

		$this->add_control(
			'off_canvas_name',
			array(
				'label'   => esc_html__( 'Nombre del panel', 'pro-elements' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Panel fuera de lienzo', 'pro-elements' ),
			)
		);

		$this->add_responsive_control(
			'horizontal_position',
			array(
				'label'     => esc_html__( 'Posición horizontal', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center'     => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-h-align-center',
					),
					'flex-end'   => array(
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-noc-justify: {{VALUE}}',
				),
			)
		);

		$this->add_responsive_control(
			'vertical_position',
			array(
				'label'     => esc_html__( 'Posición vertical', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon'  => 'eicon-v-align-top',
					),
					'center'     => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-v-align-middle',
					),
					'flex-end'   => array(
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon'  => 'eicon-v-align-bottom',
					),
				),
				'selectors' => array(
					'{{WRAPPER}}' => '--digi-noc-align: {{VALUE}}',
				),
			)
		);

		$this->add_responsive_control(
			'width',
			array(
				'label'      => esc_html__( 'Ancho', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 500 ),
					'%'   => array( 'max' => 50 ),
					'em'  => array(
						'min' => 0,
						'max' => 5,
					),
					'rem' => array(
						'min' => 0,
						'max' => 5,
					),
					'vw'  => array( 'max' => 50 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-noc-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'height',
			array(
				'label'                => esc_html__( 'Alto', 'pro-elements' ),
				'type'                 => Controls_Manager::SELECT,
				'options'              => array(
					'fit-content' => esc_html__( 'Ajustado al contenido', 'pro-elements' ),
					'custom'      => esc_html__( 'Personalizado', 'pro-elements' ),
				),
				'selectors_dictionary' => array(
					'fit-content' => '--digi-noc-height: fit-content; --digi-noc-overflow: initial;',
					'custom'      => '--digi-noc-height: 100vh; --digi-noc-overflow: auto;',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
				'default'              => 'custom',
			)
		);

		$this->add_responsive_control(
			'custom_height',
			array(
				'label'      => esc_html__( 'Alto personalizado', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vh', 'custom' ),
				'range'      => array(
					'px'  => array( 'max' => 1000 ),
					'em'  => array( 'min' => 0 ),
					'rem' => array( 'min' => 0 ),
				),
				'condition'  => array( 'height' => 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-noc-height: {{SIZE}}{{UNIT}};',
				),
				'default'    => array( 'unit' => 'vh' ),
			)
		);

		$this->end_controls_section();
	}

	protected function register_settings_section() {
		$this->start_controls_section( 'section_settings', array( 'label' => esc_html__( 'Ajustes', 'pro-elements' ) ) );

		$this->add_control(
			'animation',
			array(
				'label' => esc_html__( 'Animación', 'pro-elements' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_responsive_control(
			'entrance_animation',
			array(
				'label'              => esc_html__( 'Entrada', 'pro-elements' ),
				'type'               => Controls_Manager::ANIMATION,
				'frontend_available' => true,
			)
		);

		$this->add_responsive_control(
			'exit_animation',
			array(
				'label'              => esc_html__( 'Salida', 'pro-elements' ),
				'type'               => Controls_Manager::EXIT_ANIMATION,
				'frontend_available' => true,
			)
		);

		$this->add_responsive_control(
			'offcanvas_animation_duration',
			array(
				'label'      => esc_html__( 'Duración de la animación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array(
					'unit' => 's',
					'size' => 1.5,
				),
				'range'      => array(
					's'  => array( 'max' => 5 ),
					'ms' => array( 'max' => 5000 ),
				),
				'selectors'  => array(
					'{{WRAPPER}}' => '--digi-noc-duration: {{SIZE}}{{UNIT}}',
				),
				'conditions' => array(
					'relation' => 'or',
					'terms'    => array(
						array(
							'name'     => 'entrance_animation',
							'operator' => '!==',
							'value'    => '',
						),
						array(
							'name'     => 'exit_animation',
							'operator' => '!==',
							'value'    => '',
						),
					),
				),
			)
		);

		$this->add_control(
			'interactions',
			array(
				'label'     => esc_html__( 'Interacciones', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'is_not_close_on_overlay',
			array(
				'label'              => esc_html__( 'No cerrar al pulsar la capa', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_on'           => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'          => esc_html__( 'No', 'pro-elements' ),
				'return_value'       => 'yes',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'is_not_close_on_esc_overlay',
			array(
				'label'              => esc_html__( 'No cerrar con la tecla Escape', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_on'           => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'          => esc_html__( 'No', 'pro-elements' ),
				'return_value'       => 'yes',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'prevent_scroll',
			array(
				'label'              => esc_html__( 'Bloquear el desplazamiento de la página', 'pro-elements' ),
				'type'               => Controls_Manager::SWITCHER,
				'label_on'           => esc_html__( 'Sí', 'pro-elements' ),
				'label_off'          => esc_html__( 'No', 'pro-elements' ),
				'return_value'       => 'yes',
				'frontend_available' => true,
			)
		);

		$this->add_control(
			'wrapper_html_tag',
			array(
				'label'     => esc_html__( 'Etiqueta HTML', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'div',
				'options'   => array(
					'div'     => 'div',
					'main'    => 'main',
					'article' => 'article',
					'header'  => 'header',
					'footer'  => 'footer',
					'section' => 'section',
					'aside'   => 'aside',
					'nav'     => 'nav',
				),
				'separator' => 'before',
			)
		);

		$this->end_controls_section();
	}

	protected function register_background_controls() {
		$this->start_controls_section(
			'section_background',
			array(
				'label' => esc_html__( 'Fondo', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'background',
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => '{{WRAPPER}} .digi-noc__content',
				'fields_options' => array(
					'color' => array( 'label' => esc_html__( 'Fondo', 'pro-elements' ) ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'border',
				'selector' => '{{WRAPPER}} .digi-noc__content',
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-noc__content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'selector' => '{{WRAPPER}} .digi-noc__content',
			)
		);

		$this->end_controls_section();
	}

	protected function register_overlay_controls() {
		$this->start_controls_section(
			'section_overlay',
			array(
				'label' => esc_html__( 'Capa', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'has_overlay',
			array(
				'label'                => esc_html__( 'Capa', 'pro-elements' ),
				'type'                 => Controls_Manager::SWITCHER,
				'label_on'             => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off'            => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value'         => 'yes',
				'default'              => 'yes',
				'selectors_dictionary' => array(
					'yes' => '1',
					''    => '0',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '--digi-noc-overlay: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'overlay_background',
				'types'          => array( 'classic', 'gradient' ),
				'selector'       => '{{WRAPPER}} .digi-noc__overlay',
				'fields_options' => array(
					'background' => array( 'default' => 'classic' ),
					'color'      => array( 'default' => 'rgba(0,0,0,.8)' ),
				),
				'condition'      => array( 'has_overlay' => 'yes' ),
			)
		);

		$this->end_controls_section();
	}

	/** Dispositivos con valor propio en los ajustes, para leer un control responsivo en el navegador. */
	const DEVICES = array( '', 'widescreen', 'laptop', 'tablet_extra', 'tablet', 'mobile_extra', 'mobile' );

	/**
	 * Lo que el script necesita, en `data-digi-noc`: animación de entrada (`in`) y de salida (`out`) por dispositivo
	 * (`''` es escritorio; sólo los que tienen valor) y los cierres (`o`: capa, `e`: Escape) y el bloqueo (`s`).
	 */
	public static function script_config( array $settings ): array {
		$out = array();
		foreach ( array( 'in' => 'entrance_animation', 'out' => 'exit_animation' ) as $key => $control ) {
			$values = array();
			foreach ( self::DEVICES as $device ) {
				$value = $settings[ '' === $device ? $control : $control . '_' . $device ] ?? '';
				if ( is_string( $value ) && '' !== $value && preg_match( '/^[A-Za-z0-9_-]{1,40}$/', $value ) ) {
					$values[ $device ] = $value;
				}
			}
			$out[ $key ] = (object) $values;
		}
		$out['o'] = 'yes' !== ( $settings['is_not_close_on_overlay'] ?? '' );
		$out['e'] = 'yes' !== ( $settings['is_not_close_on_esc_overlay'] ?? '' );
		$out['s'] = 'yes' === ( $settings['prevent_scroll'] ?? '' );
		return $out;
	}

	/** `id` del panel: el de PRO (`off-canvas-<id>`), con el filtro de los bucles. */
	public function panel_id(): string {
		$id = function_exists( 'apply_filters' ) ? apply_filters( self::ID_FILTER, $this->get_id() ) : $this->get_id();
		return 'off-canvas-' . preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $id );
	}

	protected function render() {
		$settings = (array) $this->get_settings_for_display();
		$tag      = Utils::validate_html_tag( (string) ( $settings['wrapper_html_tag'] ?? 'div' ) );
		$name     = (string) ( $settings['off_canvas_name'] ?? '' );
		$config   = function_exists( 'wp_json_encode' ) ? wp_json_encode( self::script_config( $settings ) ) : json_encode( self::script_config( $settings ) );
		printf(
			'<%1$s id="%2$s" class="digi-noc" role="dialog" aria-modal="true" aria-label="%3$s" aria-hidden="true" inert data-delay-child-handlers="true" data-digi-noc="%4$s"><div class="digi-noc__overlay"></div><div class="digi-noc__main"><div class="digi-noc__content">',
			$tag, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validate_html_tag.
			esc_attr( $this->panel_id() ),
			esc_attr( '' !== trim( $name ) ? $name : __( 'Panel fuera de lienzo', 'pro-elements' ) ),
			esc_attr( (string) $config )
		);
		foreach ( (array) $this->get_children() as $child ) {
			$child->print_element();
		}
		echo '</div></div></' . $tag . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validate_html_tag.
	}

	/**
	 * Plantilla del editor: el marcado del servidor; abierto mientras «Modo de edición» esté activo (salvo al copiar el
	 * lienzo, `isBuffering`). El icono vacío del núcleo marca el sitio del widget en el lienzo.
	 */
	protected function content_template() {
		?>
		<#
		var digiTag = elementor.helpers.validateHTMLTag( settings.wrapper_html_tag ),
			digiClosed = elementor.previewView.isBuffering || 'yes' !== settings.editing_mode,
			digiName = _.isString( settings.off_canvas_name ) && settings.off_canvas_name.trim() ? settings.off_canvas_name : '<?php echo esc_js( __( 'Panel fuera de lienzo', 'pro-elements' ) ); ?>',
			digiDevices = <?php echo wp_json_encode( self::DEVICES ); ?>,
			digiConfig = { o: 'yes' !== settings.is_not_close_on_overlay, e: 'yes' !== settings.is_not_close_on_esc_overlay, s: 'yes' === settings.prevent_scroll };
		_.each( { 'in': 'entrance_animation', 'out': 'exit_animation' }, function( control, key ) {
			digiConfig[ key ] = {};
			_.each( digiDevices, function( device ) {
				var value = settings[ '' === device ? control : control + '_' + device ];
				if ( _.isString( value ) && /^[A-Za-z0-9_-]{1,40}$/.test( value ) ) {
					digiConfig[ key ][ device ] = value;
				}
			} );
		} );
		view.addRenderAttribute( 'digiNoc', {
			'id': 'off-canvas-' + view.getID(),
			'class': 'digi-noc',
			'role': 'dialog',
			'aria-modal': 'true',
			'aria-label': digiName,
			'aria-hidden': digiClosed ? 'true' : 'false',
			'data-digi-noc': JSON.stringify( digiConfig )
		} );
		if ( digiClosed ) {
			view.addRenderAttribute( 'digiNoc', { 'inert': '', 'data-delay-child-handlers': 'true' } );
		}
		#>
		<div class="elementor-element elementor-widget-empty"><i class="elementor-widget-empty-icon eicon-off-canvas" aria-hidden="true"></i></div>
		<{{{ digiTag }}} {{{ view.getRenderAttributeString( 'digiNoc' ) }}}><div class="digi-noc__overlay"></div><div class="digi-noc__main"><div class="digi-noc__content"></div></div></{{{ digiTag }}}>
		<?php
	}
}
