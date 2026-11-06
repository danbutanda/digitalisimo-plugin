<?php
namespace Digitalisimo\Elements\Forms\Widgets;

use Digitalisimo\Elements\Forms\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Inicio de sesión (`digitalisimo-login`): sustituye al widget «Login» de PRO (`modules/forms/widgets/login.php`, mapa
 * `modules/digitalisimo-convert/maps/login.php`; contrato `docs/pro/c6.md`). Tiene un control por cada control de aquél
 * con el mismo nombre, tipo, valor por defecto, opciones, condición y selectores; controles derivados de PRO Elements
 * (GPL), con textos en español del dominio `pro-elements`.
 *
 * - Mismo destino que PRO: el formulario se envía a `wp-login.php` (`site_url( …, 'login_post' )`) con `log`, `pwd`,
 *   `rememberme`, `wp-submit` y `redirect_to` (la URL elegida o la página actual); enlaces de contraseña perdida
 *   (`wp_lostpassword_url()`) y de registro (`wp_registration_url()`, sólo si el sitio admite registros), y con la sesión
 *   iniciada, el mismo aviso con el nombre público del usuario y el enlace para cerrar sesión (nada más del usuario).
 * - Marcado con el vocabulario de formularios del núcleo (`elementor-form`, `elementor-field-group`, `elementor-field`,
 *   `elementor-size-*`, `elementor-button`…, que estiliza `frontend.min.css` del núcleo), así que los selectores de los
 *   controles y el CSS personalizado son los de PRO. Propio: cada campo con su etiqueta asociada y `autocomplete`, el
 *   botón con nombre accesible aunque no tenga texto, foco visible y objetivos de 24 px (`assets/css/login.css`).
 * - Sin JavaScript ni jQuery. Sin plantilla JS del editor: el editor pinta lo mismo que el servidor.
 */
class Login extends \Elementor\Widget_Base {
	public function get_name() {
		return 'digitalisimo-login';
	}

	public function get_title() {
		return esc_html__( 'Inicio de sesión', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-lock-user';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'inicio de sesión', 'acceso', 'acceder', 'login', 'usuario', 'contraseña', 'formulario' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	/** Como PRO: sin envoltorio interior con el marcado optimizado del núcleo. */
	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	/** El enlace de registro sólo existe si el sitio (o la red) admite registros, como en PRO. */
	public static function registration_enabled(): bool {
		return is_multisite()
			? in_array( get_site_option( 'registration' ), array( 'all', 'user' ), true )
			: (bool) get_option( 'users_can_register' );
	}

	protected function register_controls() {
		$optimized_markup = Module::feature( 'e_optimized_markup' );
		$widget_container_selector = $optimized_markup ? '' : ' .elementor-widget-container';

		$this->start_controls_section(
			'section_fields_content',
			[
				'label' => esc_html__( 'Campos del formulario', 'pro-elements' ),
			]
		);

		$this->add_control(
			'show_labels',
			[
				'label' => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
			]
		);

		$this->add_control(
			'input_size',
			[
				'label' => esc_html__( 'Tamaño de los campos', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'xs' => esc_html__( 'Extra pequeño', 'pro-elements' ),
					'sm' => esc_html__( 'Pequeño', 'pro-elements' ),
					'md' => esc_html__( 'Mediano', 'pro-elements' ),
					'lg' => esc_html__( 'Grande', 'pro-elements' ),
					'xl' => esc_html__( 'Extra grande', 'pro-elements' ),
				],
				'default' => 'sm',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_button_content',
			[
				'label' => esc_html__( 'Botón', 'pro-elements' ),
			]
		);

		$this->add_control(
			'button_text',
			[
				'label' => esc_html__( 'Texto', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Acceder', 'pro-elements' ),
			]
		);

		$this->add_control(
			'button_size',
			[
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'xs' => esc_html__( 'Extra pequeño', 'pro-elements' ),
					'sm' => esc_html__( 'Pequeño', 'pro-elements' ),
					'md' => esc_html__( 'Mediano', 'pro-elements' ),
					'lg' => esc_html__( 'Grande', 'pro-elements' ),
					'xl' => esc_html__( 'Extra grande', 'pro-elements' ),
				],
				'default' => 'sm',
			]
		);

		$this->add_responsive_control(
			'align',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-text-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-text-align-right',
					],
					'stretch' => [
						'title' => esc_html__( 'Justificado', 'pro-elements' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'prefix_class' => 'elementor%s-button-align-',
				'default' => '',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_login_content',
			[
				'label' => esc_html__( 'Opciones adicionales', 'pro-elements' ),
			]
		);

		$this->add_control(
			'redirect_after_login',
			[
				'label' => esc_html__( 'Redirigir al acceder', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
			]
		);

		$this->add_control(
			'redirect_url',
			[
				'type' => Controls_Manager::URL,
				'show_label' => false,
				'options' => false,
				'description' => esc_html__( 'Nota: por seguridad, aquí SÓLO puedes usar el dominio de este sitio.', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
				'condition' => [
					'redirect_after_login' => 'yes',
				],
			]
		);

		$this->add_control(
			'redirect_after_logout',
			[
				'label' => esc_html__( 'Redirigir al cerrar sesión', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on' => esc_html__( 'Sí', 'pro-elements' ),
			]
		);

		$this->add_control(
			'redirect_logout_url',
			[
				'type' => Controls_Manager::URL,
				'show_label' => false,
				'options' => false,
				'description' => esc_html__( 'Nota: por seguridad, aquí SÓLO puedes usar el dominio de este sitio.', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
				'condition' => [
					'redirect_after_logout' => 'yes',
				],
			]
		);

		$this->add_control(
			'show_lost_password',
			[
				'label' => esc_html__( '¿Olvidaste tu contraseña?', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
			]
		);

		if ( self::registration_enabled() ) {
			$this->add_control(
				'show_register',
				[
					'label' => esc_html__( 'Registrarse', 'pro-elements' ),
					'type' => Controls_Manager::SWITCHER,
					'default' => 'yes',
					'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
					'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				]
			);
		}

		$this->add_control(
			'show_remember_me',
			[
				'label' => esc_html__( 'Recordarme', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
			]
		);

		$this->add_control(
			'show_logged_in_message',
			[
				'label' => esc_html__( 'Mensaje de sesión iniciada', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => 'yes',
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
			]
		);

		$this->add_control(
			'custom_labels',
			[
				'label' => esc_html__( 'Etiquetas personalizadas', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
			]
		);

		$this->add_control(
			'user_label',
			[
				'label' => esc_html__( 'Etiqueta del usuario', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Nombre de usuario o correo electrónico', 'pro-elements' ),
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'name' => 'show_labels',
							'operator' => '===',
							'value' => 'yes',
						],
						[
							'name' => 'custom_labels',
							'operator' => '===',
							'value' => 'yes',
						],
					],
				],
			]
		);

		$this->add_control(
			'user_placeholder',
			[
				'label' => esc_html__( 'Texto de ejemplo del usuario', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Nombre de usuario o correo electrónico', 'pro-elements' ),
				'condition' => [
					'custom_labels' => 'yes',
				],
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
			]
		);

		$this->add_control(
			'password_label',
			[
				'label' => esc_html__( 'Etiqueta de la contraseña', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'default' => esc_html__( 'Contraseña', 'pro-elements' ),
				'conditions' => [
					'relation' => 'or',
					'terms' => [
						[
							'name' => 'show_labels',
							'operator' => '===',
							'value' => 'yes',
						],
						[
							'name' => 'custom_labels',
							'operator' => '===',
							'value' => 'yes',
						],
					],
				],
			]
		);

		$this->add_control(
			'password_placeholder',
			[
				'label' => esc_html__( 'Texto de ejemplo de la contraseña', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Contraseña', 'pro-elements' ),
				'condition' => [
					'custom_labels' => 'yes',
				],
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			[
				'label' => esc_html__( 'Formulario', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'row_gap',
			[
				'label' => esc_html__( 'Separación entre filas', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'default' => [
					'size' => 10,
				],
				'range' => [
					'px' => [
						'max' => 60,
					],
					'em' => [
						'max' => 6,
					],
					'rem' => [
						'max' => 6,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group' => 'margin-bottom: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-form-fields-wrapper' => 'margin-bottom: -{{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'links_color',
			[
				'label' => esc_html__( 'Color de los enlaces', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group > a' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);

		$this->add_control(
			'links_hover_color',
			[
				'label' => esc_html__( 'Color de los enlaces al pasar el cursor', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group > a:hover' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_ACCENT,
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_labels',
			[
				'label' => esc_html__( 'Etiqueta', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_labels!' => '',
				],
			]
		);

		$this->add_control(
			'label_spacing',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'default' => [
					'size' => 0,
				],
				'range' => [
					'px' => [
						'max' => 60,
					],
					'em' => [
						'max' => 6,
					],
					'rem' => [
						'max' => 6,
					],
				],
				'selectors' => [
					'body {{WRAPPER}} .elementor-field-group > label' => 'padding-bottom: {{SIZE}}{{UNIT}};',
					// for the label position = above option
				],
			]
		);

		$this->add_control(
			'label_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-form-fields-wrapper label' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'label_typography',
				'selector' => '{{WRAPPER}} .elementor-form-fields-wrapper label',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_field_style',
			[
				'label' => esc_html__( 'Campos', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'field_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-field' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'field_typography',
				'selector' => '{{WRAPPER}} .elementor-field-group .elementor-field, {{WRAPPER}} .elementor-field-subgroup label',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);

		$this->add_control(
			'field_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-field:not(.elementor-select-wrapper)' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'background-color: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'field_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-field:not(.elementor-select-wrapper)' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper::before' => 'color: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_control(
			'field_border_width',
			[
				'label' => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'placeholder' => '1',
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-field:not(.elementor-select-wrapper)' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'field_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-field-group .elementor-field:not(.elementor-select-wrapper)' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
					'{{WRAPPER}} .elementor-field-group .elementor-select-wrapper select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_button_style',
			[
				'label' => esc_html__( 'Botón', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->start_controls_tabs( 'tabs_button_style' );

		$this->start_controls_tab(
			'tab_button_normal',
			[
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'button_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				],
				'selector' => '{{WRAPPER}} .elementor-button',
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'button_background',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => '{{WRAPPER}} .elementor-button',
				'fields_options' => [
					'background' => [
						'default' => 'classic',
					],
					'color' => [
						'global' => [
							'default' => Global_Colors::COLOR_ACCENT,
						],
					],
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(), [
				'name' => 'button_border',
				'selector' => '{{WRAPPER}} .elementor-button',
				'separator' => 'before',
			]
		);

		$this->add_control(
			'button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'button_text_padding',
			[
				'label' => esc_html__( 'Relleno del texto', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			[
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
			]
		);

		$this->add_control(
			'button_hover_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			[
				'name' => 'button_background_hover',
				'types' => [ 'classic', 'gradient' ],
				'exclude' => [ 'image' ],
				'selector' => '{{WRAPPER}} .elementor-button:hover',
				'fields_options' => [
					'background' => [
						'default' => 'classic',
					],
				],
			]
		);

		$this->add_control(
			'button_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-button:hover' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'button_border_border!' => '',
				],
			]
		);

		$this->add_control(
			'button_hover_animation',
			[
				'label' => esc_html__( 'Animación', 'pro-elements' ),
				'type' => Controls_Manager::HOVER_ANIMATION,
			]
		);

		$this->add_control(
			'button_hover_transition_duration',
			[
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'default' => [
					'unit' => 'ms',
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'transition-duration: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_message',
			[
				'label' => esc_html__( 'Mensaje de sesión iniciada', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_logged_in_message' => 'yes',
				],
			]
		);

		$this->add_control(
			'message_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					"{{WRAPPER}}{$widget_container_selector} .elementor-login__logged-in-message" => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'condition' => [
					'show_logged_in_message' => 'yes',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'message_typography',
				'selector' => "{{WRAPPER}}{$widget_container_selector} .elementor-login__logged-in-message",
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
				'condition' => [
					'show_logged_in_message' => 'yes',
				],
			]
		);

		$this->end_controls_section();

	}

	/** ¿Está el editor abierto? Fuera de Elementor, no. */
	private static function editing(): bool {
		$plugin = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
		return is_object( $plugin ) && isset( $plugin->editor ) && is_object( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode();
	}

	/** Atributos de los campos y del botón (los mismos de PRO, más `autocomplete`). */
	private function form_fields_render_attributes( array $settings ): void {
		$id   = $this->get_id();
		$size = 'elementor-size-' . ( $settings['input_size'] ?? 'sm' );
		if ( ! empty( $settings['button_size'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-size-' . $settings['button_size'] );
		}
		if ( ! empty( $settings['button_hover_animation'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-animation-' . $settings['button_hover_animation'] );
		}
		$this->add_render_attribute(
			array(
				'wrapper'        => array( 'class' => array( 'elementor-form-fields-wrapper' ) ),
				'field-group'    => array( 'class' => array( 'elementor-field-type-text', 'elementor-field-group', 'elementor-column', 'elementor-col-100', 'elementor-field-required' ) ),
				'submit-group'   => array( 'class' => array( 'elementor-field-group', 'elementor-column', 'elementor-field-type-submit', 'elementor-col-100' ) ),
				'button'         => array(
					'class' => array( 'elementor-button' ),
					'name'  => 'wp-submit',
				),
				'user_label'     => array(
					'for'   => 'user-' . $id,
					'class' => 'elementor-field-label',
				),
				'user_input'     => array(
					'size'         => '1',
					'type'         => 'text',
					'name'         => 'log',
					'id'           => 'user-' . $id,
					'autocomplete' => 'username',
					'placeholder'  => (string) ( $settings['user_placeholder'] ?? '' ),
					'class'        => array( 'elementor-field', 'elementor-field-textual', $size ),
				),
				'password_label' => array(
					'for'   => 'password-' . $id,
					'class' => 'elementor-field-label',
				),
				'password_input' => array(
					'size'         => '1',
					'type'         => 'password',
					'name'         => 'pwd',
					'id'           => 'password-' . $id,
					'autocomplete' => 'current-password',
					'placeholder'  => (string) ( $settings['password_placeholder'] ?? '' ),
					'class'        => array( 'elementor-field', 'elementor-field-textual', $size ),
				),
			)
		);
		if ( empty( $settings['show_labels'] ) ) {
			$this->add_render_attribute( 'user_label', 'class', 'elementor-screen-only' );
			$this->add_render_attribute( 'password_label', 'class', 'elementor-screen-only' );
		}
	}

	/** URL de redirección de un control URL, o la de respaldo. */
	private static function url( $value, string $fallback ): string {
		$url = is_array( $value ) && isset( $value['url'] ) && is_string( $value['url'] ) ? trim( $value['url'] ) : '';
		return '' === $url ? $fallback : $url;
	}

	protected function render() {
		$settings        = $this->get_settings_for_display();
		$current_url     = remove_query_arg( 'fake_arg' );
		$redirect_url    = 'yes' === ( $settings['redirect_after_login'] ?? '' ) ? self::url( $settings['redirect_url'] ?? null, $current_url ) : $current_url;
		$logout_redirect = 'yes' === ( $settings['redirect_after_logout'] ?? '' ) ? self::url( $settings['redirect_logout_url'] ?? null, $current_url ) : $current_url;

		if ( is_user_logged_in() && ! self::editing() ) {
			if ( 'yes' === ( $settings['show_logged_in_message'] ?? '' ) ) {
				$current_user = wp_get_current_user();
				echo '<div class="elementor-login elementor-login__logged-in-message">' . sprintf(
					/* translators: 1: nombre público del usuario, 2: apertura del enlace, 3: cierre del enlace. */
					esc_html__( 'Has iniciado sesión como %1$s (%2$sCerrar sesión%3$s)', 'pro-elements' ),
					wp_kses_post( $current_user->display_name ),
					sprintf( '<a href="%s" target="_blank" rel="noopener">', esc_url( wp_logout_url( $logout_redirect ) ) ),
					'</a>'
				) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partes escapadas arriba, como PRO.
			}
			return;
		}

		$this->form_fields_render_attributes( $settings );
		$id                 = $this->get_id();
		$button_text        = (string) ( $settings['button_text'] ?? '' );
		$show_lost_password = 'yes' === ( $settings['show_lost_password'] ?? '' );
		$show_register      = self::registration_enabled() && 'yes' === ( $settings['show_register'] ?? '' );
		?>
		<form class="elementor-login elementor-form" method="post" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" aria-label="<?php echo esc_attr__( 'Formulario de acceso', 'pro-elements' ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_url ); ?>">
			<div <?php $this->print_render_attribute_string( 'wrapper' ); ?>>
				<div <?php $this->print_render_attribute_string( 'field-group' ); ?>>
					<label <?php $this->print_render_attribute_string( 'user_label' ); ?>><?php echo wp_kses_post( (string) ( $settings['user_label'] ?? '' ) ); ?></label>
					<input <?php $this->print_render_attribute_string( 'user_input' ); ?>>
				</div>
				<div <?php $this->print_render_attribute_string( 'field-group' ); ?>>
					<label <?php $this->print_render_attribute_string( 'password_label' ); ?>><?php echo wp_kses_post( (string) ( $settings['password_label'] ?? '' ) ); ?></label>
					<input <?php $this->print_render_attribute_string( 'password_input' ); ?>>
				</div>
				<?php if ( 'yes' === ( $settings['show_remember_me'] ?? '' ) ) : ?>
				<div class="elementor-field-type-checkbox elementor-field-group elementor-column elementor-col-100 elementor-remember-me">
					<label for="elementor-login-remember-me-<?php echo esc_attr( $id ); ?>"><input type="checkbox" id="elementor-login-remember-me-<?php echo esc_attr( $id ); ?>" name="rememberme" value="forever"> <?php echo esc_html__( 'Recordarme', 'pro-elements' ); ?></label>
				</div>
				<?php endif; ?>
				<div <?php $this->print_render_attribute_string( 'submit-group' ); ?>>
					<button type="submit" <?php $this->print_render_attribute_string( 'button' ); ?>>
						<?php if ( '' !== $button_text ) : ?>
						<span class="elementor-button-text"><?php echo wp_kses_post( $button_text ); ?></span>
						<?php else : ?>
						<span class="elementor-screen-only"><?php echo esc_html__( 'Acceder', 'pro-elements' ); ?></span>
						<?php endif; ?>
					</button>
				</div>
				<?php if ( $show_lost_password || $show_register ) : ?>
				<div class="elementor-field-group elementor-column elementor-col-100">
					<?php if ( $show_lost_password ) : ?>
					<a class="elementor-lost-password" href="<?php echo esc_url( wp_lostpassword_url( $redirect_url ) ); ?>"><?php echo esc_html__( '¿Olvidaste tu contraseña?', 'pro-elements' ); ?></a>
					<?php endif; ?>
					<?php if ( $show_register ) : ?>
						<?php if ( $show_lost_password ) : ?>
					<span class="elementor-login-separator" aria-hidden="true"> | </span>
						<?php endif; ?>
					<a class="elementor-register" href="<?php echo esc_url( wp_registration_url() ); ?>"><?php echo esc_html__( 'Registrarse', 'pro-elements' ); ?></a>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
		</form>
		<?php
	}

	public function render_plain_content() {}

	/** Resumen en Markdown (como PRO): campos y botón, sin datos del usuario. */
	public function render_markdown(): string {
		$settings = $this->get_settings_for_display();
		$plain    = static function ( $value ) {
			return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $value ) ) );
		};
		$parts  = array( '## ' . esc_html__( 'Formulario de acceso', 'pro-elements' ) );
		$fields = array();
		if ( '' !== $plain( $settings['user_label'] ?? '' ) ) {
			$fields[] = '- **' . $plain( $settings['user_label'] ) . '** (text)';
		}
		if ( '' !== $plain( $settings['password_label'] ?? '' ) ) {
			$fields[] = '- **' . $plain( $settings['password_label'] ) . '** (password)';
		}
		if ( 'yes' === ( $settings['show_remember_me'] ?? '' ) ) {
			$fields[] = '- **' . esc_html__( 'Recordarme', 'pro-elements' ) . '** (checkbox)';
		}
		if ( $fields ) {
			$parts[] = implode( "\n", $fields );
		}
		if ( '' !== $plain( $settings['button_text'] ?? '' ) ) {
			$parts[] = '[' . $plain( $settings['button_text'] ) . ']';
		}
		return implode( "\n\n", $parts );
	}
}
