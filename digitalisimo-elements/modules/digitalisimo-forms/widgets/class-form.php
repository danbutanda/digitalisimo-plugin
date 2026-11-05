<?php
namespace Digitalisimo\Elements\Forms\Widgets;

use Digitalisimo\Elements\Forms\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Formulario (`digitalisimo-form`): sustituye al widget «Form» de PRO (`modules/forms/widgets/form.php`, mapa
 * `modules/digitalisimo-convert/maps/form.php`; contrato `docs/pro/c6.md`). Tiene un control por cada control de aquél
 * (también los de cada campo del repetidor y los de los pasos y mensajes) con el mismo nombre, tipo, valor por defecto,
 * opciones, condición y selectores; controles derivados de PRO Elements (GPL), con textos en español del dominio
 * `pro-elements`.
 *
 * **Una sola tubería de envío.** El formulario envía a `admin-ajax.php` con la acción `elementor_pro_forms_send_form` y
 * los mismos parámetros que el de PRO (`post_id`, `form_id`, `queried_id`, `referer_title`, `form_fields[…]`,
 * `referrer`), así que el manejador del constructor (`modules/forms/classes/ajax-handler.php`) lo encuentra por su `id`,
 * crea esta misma clase por su `widgetType` y corre la validación de los campos, la de F7b, el honeypot, reCAPTCHA, los
 * envíos guardados (`e_submissions`) y las acciones registradas (`actions_registrar`) exactamente igual: este widget no
 * guarda, no valida ni envía nada por su cuenta. Sus controles de acciones los registra cada acción
 * (`register_settings_section( $this )`), y los de los tipos de campo (fecha, subida, pasos, F7b…), que PRO engancha a
 * `elementor/element/form/section_form_fields/before_section_end`, llegan por el reenvío de `Module::forward_form_hooks()`.
 * `on_export()` deja que cada acción quite sus secretos de un kit, como PRO.
 *
 * Marcado con el vocabulario de formularios del núcleo (`elementor-form`, `elementor-field-group`, `elementor-column`,
 * `elementor-col-*`, `elementor-field`, `elementor-field-textual`, `elementor-size-*`, `elementor-button`,
 * `e-form__buttons`…), que estiliza `frontend.min.css` del núcleo y que usan los tipos de campo compartidos y el script de
 * campos condicionales de F7b; las mismas claves de atributos (`field-group<n>`, `input<n>`, `label<n>`…) para los filtros
 * `elementor_pro/forms/render/item*` y `render_field/<tipo>`. Propio:
 * - los pasos se pintan en el servidor (indicadores, barra de progreso con `role="progressbar"`, botones y región viva que
 *   anuncia el paso), no los arma el script;
 * - radios y casillas en un grupo con nombre (`role="radiogroup"`/`group`); cada mensaje de error de un campo con `id`,
 *   enlazado con `aria-describedby` y `aria-invalid`; mensajes del formulario con `role="alert"`;
 * - `assets/js/form.js` (`defer`, sin jQuery): envío con `fetch` y `FormData`, pasos, redirección, popup, reCAPTCHA,
 *   selector de fecha y hora del núcleo, tamaño máximo de archivos; un envío en curso bloquea otro (sin duplicados);
 * - `assets/css/form.css`: lo que la hoja `widget-form` de PRO añadía al núcleo (pasos, campo HTML, oculto, flecha del
 *   desplegable…), foco visible y objetivos de 24 px.
 * Sin plantilla JS del editor: el editor pinta lo mismo que el servidor.
 */
class Form extends \Elementor\Widget_Base {
	/** Tipo del control del repetidor de campos que registra el módulo de formularios del constructor. */
	const FIELDS_CONTROL = 'form-fields-repeater';

	/** Acción AJAX del manejador de envíos del constructor (la misma del formulario de PRO). */
	const SUBMIT_ACTION = 'elementor_pro_forms_send_form';

	const FORMS_MODULE = 'ElementorPro\\Modules\\Forms\\Module';

	public function get_name() {
		return 'digitalisimo-form';
	}

	public function get_title() {
		return esc_html__( 'Formulario', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-form-horizontal';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'formulario', 'contacto', 'campos', 'enviar', 'form', 'correo', 'webhook', 'recaptcha', 'pasos', 'suscripción' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	/** Como PRO: sin envoltorio interior con el marcado optimizado del núcleo. */
	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	/**
	 * Acciones registradas en el módulo de formularios del constructor (correo, webhook, envíos guardados, listas…). Sin
	 * el módulo, ninguna: el widget no tiene acciones propias.
	 *
	 * @return object[]
	 */
	public static function actions(): array {
		$module = self::FORMS_MODULE;
		if ( ! class_exists( $module ) || ! method_exists( $module, 'instance' ) ) {
			return array();
		}
		try {
			$instance = $module::instance();
			$actions  = isset( $instance->actions_registrar ) && is_object( $instance->actions_registrar ) ? $instance->actions_registrar->get() : array();
		} catch ( \Throwable $error ) {
			return array();
		}
		return is_array( $actions ) ? $actions : array();
	}

	/** Textos por defecto de los mensajes personalizados (las claves son las del manejador de envíos). */
	public static function default_messages(): array {
		return array(
			'success'        => esc_html__( 'El formulario se envió correctamente.', 'pro-elements' ),
			'error'          => esc_html__( 'No se pudo enviar el formulario por un error.', 'pro-elements' ),
			'required_field' => esc_html__( 'Este campo es obligatorio.', 'pro-elements' ),
			'invalid_form'   => esc_html__( 'No se pudo enviar el formulario: hay datos no válidos.', 'pro-elements' ),
			'server_error'   => esc_html__( 'No se pudo enviar el formulario por un error del servidor.', 'pro-elements' ),
		);
	}

	public static function get_button_sizes() {
		return array(
			'xs' => esc_html__( 'Extra pequeño', 'pro-elements' ),
			'sm' => esc_html__( 'Pequeño', 'pro-elements' ),
			'md' => esc_html__( 'Mediano', 'pro-elements' ),
			'lg' => esc_html__( 'Grande', 'pro-elements' ),
			'xl' => esc_html__( 'Extra grande', 'pro-elements' ),
		);
	}

	/**
	 * Exportación a un kit: cada acción registrada quita sus secretos (URL de webhooks, claves de API), como hace
	 * `Form_Base::on_export()` con el formulario de PRO.
	 */
	public function on_export( $element ) {
		foreach ( self::actions() as $action ) {
			if ( ! is_object( $action ) || ! method_exists( $action, 'on_export' ) ) {
				continue;
			}
			$new = $action->on_export( $element );
			if ( null !== $new ) {
				$element = $new;
			}
		}
		return $element;
	}

	/** Nombre del campo en el envío (el que lee el manejador: `form_fields[<id>]`). */
	public function get_attribute_name( $item ) {
		return "form_fields[{$item['custom_id']}]";
	}

	/** `id` del control de un campo (el que buscan los errores del servidor: `form-field-<id>`). */
	public function get_attribute_id( $item ) {
		return 'form-field-' . esc_attr( $item['custom_id'] );
	}

	protected function register_controls() {
		$repeater = new Repeater();

		$field_types = [
			'text' => esc_html__( 'Texto', 'pro-elements' ),
			'email' => esc_html__( 'Correo electrónico', 'pro-elements' ),
			'textarea' => esc_html__( 'Área de texto', 'pro-elements' ),
			'url' => esc_html__( 'URL', 'pro-elements' ),
			'tel' => esc_html__( 'Teléfono', 'pro-elements' ),
			'radio' => esc_html__( 'Opciones (radio)', 'pro-elements' ),
			'select' => esc_html__( 'Desplegable', 'pro-elements' ),
			'checkbox' => esc_html__( 'Casillas', 'pro-elements' ),
			'acceptance' => esc_html__( 'Aceptación', 'pro-elements' ),
			'number' => esc_html__( 'Número', 'pro-elements' ),
			'date' => esc_html__( 'Fecha', 'pro-elements' ),
			'time' => esc_html__( 'Hora', 'pro-elements' ),
			'upload' => esc_html__( 'Subir archivo', 'pro-elements' ),
			'password' => esc_html__( 'Contraseña', 'pro-elements' ),
			'html' => esc_html__( 'HTML', 'pro-elements' ),
			'hidden' => esc_html__( 'Oculto', 'pro-elements' ),
		];

		/**
		 * Forms field types.
		 *
		 * Filters the list of field types displayed in the form `field_type` control.
		 *
		 * This hook allows developers to alter the list of displayed field types. For
		 * example, removing the 'upload' field type from the list of fields types will
		 * prevent uploading files using Elementor forms.
		 *
		 * @since 1.0.0
		 *
		 * @param array $field_types Field types.
		 */
		$field_types = apply_filters( 'elementor_pro/forms/field_types', $field_types );

		$repeater->start_controls_tabs( 'form_fields_tabs' );

		$repeater->start_controls_tab( 'form_fields_content_tab', [
			'label' => esc_html__( 'Contenido', 'pro-elements' ),
		] );

		$repeater->add_control(
			'field_type',
			[
				'label' => esc_html__( 'Tipo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => $field_types,
				'default' => 'text',
			]
		);

		$repeater->add_control(
			'field_label',
			[
				'label' => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$repeater->add_control(
			'placeholder',
			[
				'label' => esc_html__( 'Texto de ejemplo', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'operator' => 'in',
							'value' => [
								'tel',
								'text',
								'email',
								'textarea',
								'number',
								'url',
								'password',
							],
						],
					],
				],
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$repeater->add_control(
			'required',
			[
				'label' => esc_html__( 'Obligatorio', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'return_value' => 'true',
				'default' => '',
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'operator' => '!in',
							'value' => [
								'checkbox',
								'recaptcha',
								'recaptcha_v3',
								'hidden',
								'html',
								'step',
							],
						],
					],
				],
			]
		);

		$repeater->add_control(
			'field_options',
			[
				'label' => esc_html__( 'Opciones', 'pro-elements' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => '',
				'description' => esc_html__( 'Escribe cada opción en una línea. Para separar la etiqueta del valor, usa una barra vertical («|»). Por ejemplo: Nombre|nombre', 'pro-elements' ),
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'operator' => 'in',
							'value' => [
								'select',
								'checkbox',
								'radio',
							],
						],
					],
				],
			]
		);

		$repeater->add_control(
			'allow_multiple',
			[
				'label' => esc_html__( 'Selección múltiple', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'return_value' => 'true',
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'value' => 'select',
						],
					],
				],
			]
		);

		$repeater->add_control(
			'select_size',
			[
				'label' => esc_html__( 'Filas', 'pro-elements' ),
				'type' => Controls_Manager::NUMBER,
				'min' => 2,
				'step' => 1,
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'value' => 'select',
						],
						[
							'name' => 'allow_multiple',
							'value' => 'true',
						],
					],
				],
			]
		);

		$repeater->add_control(
			'inline_list',
			[
				'label' => esc_html__( 'Lista en línea', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'return_value' => 'elementor-subgroup-inline',
				'default' => '',
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'operator' => 'in',
							'value' => [
								'checkbox',
								'radio',
							],
						],
					],
				],
			]
		);

		$repeater->add_control(
			'field_html',
			[
				'label' => esc_html__( 'HTML', 'pro-elements' ),
				'type' => Controls_Manager::TEXTAREA,
				'dynamic' => [
					'active' => true,
				],
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'value' => 'html',
						],
					],
				],
			]
		);

		$repeater->add_responsive_control(
			'width',
			[
				'label' => esc_html__( 'Ancho de la columna', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Predeterminado', 'pro-elements' ),
					'100' => '100%',
					'80' => '80%',
					'75' => '75%',
					'70' => '70%',
					'66' => '66%',
					'60' => '60%',
					'50' => '50%',
					'40' => '40%',
					'33' => '33%',
					'30' => '30%',
					'25' => '25%',
					'20' => '20%',
				],
				'default' => '100',
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'operator' => '!in',
							'value' => [
								'hidden',
								'recaptcha',
								'recaptcha_v3',
								'step',
							],
						],
					],
				],
			]
		);

		$repeater->add_control(
			'rows',
			[
				'label' => esc_html__( 'Filas', 'pro-elements' ),
				'type' => Controls_Manager::NUMBER,
				'default' => 4,
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'value' => 'textarea',
						],
					],
				],
			]
		);

		$repeater->add_control(
			'recaptcha_size', [
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'normal',
				'options' => [
					'normal' => esc_html__( 'Normal', 'pro-elements' ),
					'compact' => esc_html__( 'Compacto', 'pro-elements' ),
				],
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'value' => 'recaptcha',
						],
					],
				],
			]
		);

		$repeater->add_control(
			'recaptcha_style',
			[
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'light',
				'options' => [
					'light' => esc_html__( 'Claro', 'pro-elements' ),
					'dark' => esc_html__( 'Oscuro', 'pro-elements' ),
				],
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'value' => 'recaptcha',
						],
					],
				],
			]
		);

		$repeater->add_control(
			'recaptcha_badge', [
				'label' => esc_html__( 'Distintivo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'bottomright',
				'options' => [
					'bottomright' => esc_html__( 'Abajo a la derecha', 'pro-elements' ),
					'bottomleft' => esc_html__( 'Abajo a la izquierda', 'pro-elements' ),
					'inline' => esc_html__( 'En línea', 'pro-elements' ),
				],
				'description' => esc_html__( 'Para ver el distintivo de validación, cambia a la vista previa', 'pro-elements' ),
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'value' => 'recaptcha_v3',
						],
					],
				],
			]
		);

		$repeater->add_control(
			'css_classes',
			[
				'label' => esc_html__( 'Clases CSS', 'pro-elements' ),
				'type' => Controls_Manager::HIDDEN,
				'default' => '',
				'title' => esc_html__( 'Añade tu clase SIN el punto. Por ejemplo: mi-clase', 'pro-elements' ),
			]
		);

		$repeater->end_controls_tab();

		$repeater->start_controls_tab(
			'form_fields_advanced_tab',
			[
				'label' => esc_html__( 'Avanzado', 'pro-elements' ),
				'condition' => [
					'field_type!' => 'html',
				],
			]
		);

		$repeater->add_control(
			'field_value',
			[
				'label' => esc_html__( 'Valor predeterminado', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'conditions' => [
					'terms' => [
						[
							'name' => 'field_type',
							'operator' => 'in',
							'value' => [
								'text',
								'email',
								'textarea',
								'url',
								'tel',
								'radio',
								'select',
								'number',
								'date',
								'time',
								'hidden',
							],
						],
					],
				],
			]
		);

		$repeater->add_control(
			'custom_id',
			[
				'label' => esc_html__( 'ID', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'description' => sprintf(
					esc_html__( 'Asegúrate de que el ID sea único y no se use en otro lugar de la página. Admite %1$sA-z 0-9%2$s y guion bajo, sin espacios.', 'pro-elements' ),
					'<code>',
					'</code>'
				),
				'render_type' => 'none',
				'required' => true,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
			]
		);

		$shortcode_template = '{{ view.container.settings.get( \'custom_id\' ) }}';
		$repeater->add_control(
			'shortcode',
			[
				'label' => esc_html__( 'Código corto', 'pro-elements' ),
				'type' => Controls_Manager::RAW_HTML,
				'classes' => 'forms-field-shortcode',
				'raw' => '<input class="elementor-form-field-shortcode" value=\'[field id="' . $shortcode_template . '"]\' readonly />',
			]
		);

		$repeater->end_controls_tab();

		$repeater->end_controls_tabs();

		$this->start_controls_section(
			'section_form_fields',
			[
				'label' => esc_html__( 'Campos del formulario', 'pro-elements' ),
			]
		);

		$this->add_control(
			'form_name',
			[
				'label' => esc_html__( 'Nombre del formulario', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Formulario nuevo', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'form_fields',
			[
				'type' => self::FIELDS_CONTROL,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'custom_id' => 'name',
						'field_type' => 'text',
						'field_label' => esc_html__( 'Nombre', 'pro-elements' ),
						'placeholder' => esc_html__( 'Nombre', 'pro-elements' ),
						'width' => '100',
						'dynamic' => [
							'active' => true,
						],
					],
					[
						'custom_id' => 'email',
						'field_type' => 'email',
						'required' => 'true',
						'field_label' => esc_html__( 'Correo electrónico', 'pro-elements' ),
						'placeholder' => esc_html__( 'Correo electrónico', 'pro-elements' ),
						'width' => '100',
					],
					[
						'custom_id' => 'message',
						'field_type' => 'textarea',
						'field_label' => esc_html__( 'Mensaje', 'pro-elements' ),
						'placeholder' => esc_html__( 'Mensaje', 'pro-elements' ),
						'width' => '100',
					],
				],
				'title_field' => '{{{ field_label }}}',
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
				'separator' => 'before',
			]
		);

		$this->add_control(
			'show_labels',
			[
				'label' => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'return_value' => 'true',
				'default' => 'true',
				'separator' => 'before',
			]
		);

		$this->add_control(
			'mark_required',
			[
				'label' => esc_html__( 'Marca de obligatorio', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'label_on' => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default' => '',
				'condition' => [
					'show_labels!' => '',
				],
			]
		);

		$this->add_control(
			'label_position',
			[
				'label' => esc_html__( 'Posición de la etiqueta', 'pro-elements' ),
				'type' => Controls_Manager::HIDDEN,
				'options' => [
					'above' => esc_html__( 'Encima', 'pro-elements' ),
					'inline' => esc_html__( 'En línea', 'pro-elements' ),
				],
				'default' => 'above',
				'condition' => [
					'show_labels!' => '',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_buttons',
			[
				'label' => esc_html__( 'Botones', 'pro-elements' ),
			]
		);

		$this->add_control(
			'button_size',
			[
				'label' => esc_html__( 'Tamaño', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'default' => 'sm',
				'options' => self::get_button_sizes(),
			]
		);

		$this->add_responsive_control(
			'button_width',
			[
				'label' => esc_html__( 'Ancho de la columna', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'Predeterminado', 'pro-elements' ),
					'100' => '100%',
					'80' => '80%',
					'75' => '75%',
					'70' => '70%',
					'66' => '66%',
					'60' => '60%',
					'50' => '50%',
					'40' => '40%',
					'33' => '33%',
					'30' => '30%',
					'25' => '25%',
					'20' => '20%',
				],
				'default' => '100',
				'frontend_available' => true,
			]
		);

		$this->add_control(
			'heading_steps_buttons',
			[
				'label' => esc_html__( 'Botones de los pasos', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'step_next_label',
			[
				'label' => esc_html__( 'Siguiente', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'frontend_available' => true,
				'render_type' => 'none',
				'default' => esc_html__( 'Siguiente', 'pro-elements' ),
				'placeholder' => esc_html__( 'Siguiente', 'pro-elements' ),
			]
		);

		$this->add_control(
			'step_previous_label',
			[
				'label' => esc_html__( 'Anterior', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
				'frontend_available' => true,
				'render_type' => 'none',
				'default' => esc_html__( 'Anterior', 'pro-elements' ),
				'placeholder' => esc_html__( 'Anterior', 'pro-elements' ),
			]
		);

		$this->add_control(
			'heading_submit_button',
			[
				'label' => esc_html__( 'Botón de envío', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'button_text',
			[
				'label' => esc_html__( 'Enviar', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => esc_html__( 'Enviar', 'pro-elements' ),
				'placeholder' => esc_html__( 'Enviar', 'pro-elements' ),
				'dynamic' => [
					'active' => true,
				],
				'ai' => [
					'active' => false,
				],
			]
		);

		$this->add_control(
			'selected_button_icon',
			[
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'type' => Controls_Manager::ICONS,
				'skin' => 'inline',
				'label_block' => false,
			]
		);

		$start = is_rtl() ? 'right' : 'left';
		$end = is_rtl() ? 'left' : 'right';

		$this->add_control(
			'button_icon_align',
			[
				'label' => esc_html__( 'Posición del icono', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'default' => is_rtl() ? 'row-reverse' : 'row',
				'options' => [
					'row' => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => "eicon-h-align-{$start}",
					],
					'row-reverse' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => "eicon-h-align-{$end}",
					],
				],
				'selectors_dictionary' => [
					'left' => is_rtl() ? 'row-reverse' : 'row',
					'right' => is_rtl() ? 'row' : 'row-reverse',
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-button-content-wrapper' => 'flex-direction: {{VALUE}};',
				],
				'condition' => [
					'button_text!' => '',
					'selected_button_icon[value]!' => '',
				],
			]
		);

		$this->add_control(
			'button_icon_indent',
			[
				'label' => esc_html__( 'Espacio del icono', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'condition' => [
					'button_text!' => '',
					'selected_button_icon[value]!' => '',
				],
				'selectors' => [
					'{{WRAPPER}} .elementor-button span' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'button_css_id',
			[
				'label' => esc_html__( 'ID del botón', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'ai' => [
					'active' => false,
				],
				'title' => esc_html__( 'Añade tu ID SIN la almohadilla. Por ejemplo: mi-id', 'pro-elements' ),
				'description' => sprintf(
					esc_html__( 'Asegúrate de que el ID sea único y no se use en otro lugar de la página. Admite %1$sA-z 0-9%2$s y guion bajo, sin espacios.', 'pro-elements' ),
					'<code>',
					'</code>'
				),
				'separator' => 'before',
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_integration',
			[
				'label' => esc_html__( 'Acciones tras el envío', 'pro-elements' ),
			]
		);

		$actions = self::actions();

		$actions_options = [];

		foreach ( $actions as $action ) {
			$actions_options[ $action->get_name() ] = $action->get_label();
		}

		$default_submit_actions = [ 'email' ];

		/**
		 * Default submit actions.
		 *
		 * Filters the list of submit actions pre deffined by Elementor forms.
		 *
		 * By default, only one submit action is set by Elementor forms, an 'email'
		 * action. This hook allows developers to alter those submit action.
		 *
		 * @param array $default_submit_actions A list of default submit actions.
		 */
		$default_submit_actions = apply_filters( 'elementor_pro/forms/default_submit_actions', $default_submit_actions );

		$this->add_control(
			'submit_actions',
			[
				'label' => esc_html__( 'Añadir acción', 'pro-elements' ),
				'type' => Controls_Manager::SELECT2,
				'multiple' => true,
				'options' => $actions_options,
				'render_type' => 'none',
				'label_block' => true,
				'default' => $default_submit_actions,
				'description' => esc_html__( 'Añade las acciones que se harán cuando un visitante envíe el formulario (por ejemplo, enviar un aviso por correo). Al elegir una acción, sus ajustes aparecen debajo.', 'pro-elements' ),
			]
		);

		$this->end_controls_section();

		foreach ( $actions as $action ) {
			$action->register_settings_section( $this );
		}

		// Steps settings.
		$this->start_controls_section(
			'section_steps_settings',
			[
				'label' => esc_html__( 'Ajustes de los pasos', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'step_type',
			[
				'label' => esc_html__( 'Tipo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'frontend_available' => true,
				'render_type' => 'none',
				'options' => [
					'none' => esc_html__( 'Ninguno', 'pro-elements' ),
					'text' => esc_html__( 'Texto', 'pro-elements' ),
					'icon' => esc_html__( 'Icono', 'pro-elements' ),
					'number' => esc_html__( 'Número', 'pro-elements' ),
					'progress_bar' => esc_html__( 'Barra de progreso', 'pro-elements' ),
					'number_text' => esc_html__( 'Número y texto', 'pro-elements' ),
					'icon_text' => esc_html__( 'Icono y texto', 'pro-elements' ),
				],
				'default' => 'number_text',
			]
		);

		$this->add_control(
			'step_icon_shape',
			[
				'label' => esc_html__( 'Forma', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'frontend_available' => true,
				'render_type' => 'none',
				'options' => [
					'circle' => esc_html__( 'Círculo', 'pro-elements' ),
					'square' => esc_html__( 'Cuadrado', 'pro-elements' ),
					'rounded' => esc_html__( 'Redondeado', 'pro-elements' ),
					'none' => esc_html__( 'Ninguna', 'pro-elements' ),
				],
				'default' => 'circle',
				'conditions' => [
					'terms' => [
						[
							'name' => 'step_type',
							'operator' => '!in',
							'value' => [
								'progress_bar',
								'text',
							],
						],
					],
				],
			]
		);

		$repeater->add_control(
			'display_percentage',
			[
				'label' => esc_html__( 'Mostrar el porcentaje', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'frontend_available' => true,
				'render_type' => 'none',
				'return_value' => 'true',
				'default' => '',
				'condition' => [
					'step_type' => 'progress_bar',
				],
			]
		);

		// End of steps settings.
		$this->end_controls_section();

		$this->start_controls_section(
			'section_form_options',
			[
				'label' => esc_html__( 'Opciones adicionales', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'form_id',
			[
				'label' => esc_html__( 'ID del formulario', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'ai' => [
					'active' => false,
				],
				'placeholder' => 'new_form_id',
				'description' => sprintf(
					esc_html__( 'Asegúrate de que el ID sea único y no se use en otro lugar de la página. Admite %1$sA-z 0-9%2$s y guion bajo, sin espacios.', 'pro-elements' ),
					'<code>',
					'</code>'
				),
				'separator' => 'after',
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'form_validation',
			[
				'label' => esc_html__( 'Validación del formulario', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => [
					'' => esc_html__( 'La del navegador', 'pro-elements' ),
					'custom' => esc_html__( 'Personalizada', 'pro-elements' ),
				],
				'default' => '',
			]
		);

		$this->add_control(
			'custom_messages',
			[
				'label' => esc_html__( 'Mensajes personalizados', 'pro-elements' ),
				'type' => Controls_Manager::SWITCHER,
				'default' => '',
				'separator' => 'before',
				'render_type' => 'none',
			]
		);

		$default_messages = self::default_messages();

		$this->add_control(
			'success_message',
			[
				'label' => esc_html__( 'Mensaje de éxito', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => $default_messages[ 'success' ],
				'placeholder' => $default_messages[ 'success' ],
				'label_block' => true,
				'condition' => [
					'custom_messages!' => '',
				],
				'render_type' => 'none',
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'error_message',
			[
				'label' => esc_html__( 'Error del formulario', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => $default_messages[ 'error' ],
				'placeholder' => $default_messages[ 'error' ],
				'label_block' => true,
				'condition' => [
					'custom_messages!' => '',
				],
				'render_type' => 'none',
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'server_message',
			[
				'label' => esc_html__( 'Error del servidor', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => $default_messages[ 'server_error' ],
				'placeholder' => $default_messages[ 'server_error' ],
				'label_block' => true,
				'condition' => [
					'custom_messages!' => '',
				],
				'render_type' => 'none',
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'invalid_message',
			[
				'label' => esc_html__( 'Formulario no válido', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => $default_messages[ 'invalid_form' ],
				'placeholder' => $default_messages[ 'invalid_form' ],
				'label_block' => true,
				'condition' => [
					'custom_messages!' => '',
				],
				'render_type' => 'none',
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->add_control(
			'required_field_message',
			[
				'label' => esc_html__( 'Campo obligatorio', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => $default_messages[ 'required_field' ],
				'placeholder' => $default_messages[ 'required_field' ],
				'label_block' => true,
				'condition' => [
					'custom_messages!' => '',
					'form_validation' => 'custom',
				],
				'render_type' => 'none',
				'dynamic' => [
					'active' => true,
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_form_style',
			[
				'label' => esc_html__( 'Formulario', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'column_gap',
			[
				'label' => esc_html__( 'Espacio entre columnas', 'pro-elements' ),
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
					'{{WRAPPER}} .elementor-field-group' => 'padding-right: calc( {{SIZE}}{{UNIT}}/2 ); padding-left: calc( {{SIZE}}{{UNIT}}/2 );',
					'{{WRAPPER}} .elementor-form-fields-wrapper' => 'margin-left: calc( -{{SIZE}}{{UNIT}}/2 ); margin-right: calc( -{{SIZE}}{{UNIT}}/2 );',
				],
			]
		);

		$this->add_control(
			'row_gap',
			[
				'label' => esc_html__( 'Espacio entre filas', 'pro-elements' ),
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
					'{{WRAPPER}} .elementor-field-group.recaptcha_v3-bottomleft, {{WRAPPER}} .elementor-field-group.recaptcha_v3-bottomright' => 'margin-bottom: 0;',
					'{{WRAPPER}} .elementor-form-fields-wrapper' => 'margin-bottom: -{{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'heading_label',
			[
				'label' => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
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
					'body.rtl {{WRAPPER}} .elementor-labels-inline .elementor-field-group > label' => 'padding-left: {{SIZE}}{{UNIT}};',
					// for the label position = inline option
					'body:not(.rtl) {{WRAPPER}} .elementor-labels-inline .elementor-field-group > label' => 'padding-right: {{SIZE}}{{UNIT}};',
					// for the label position = inline option
					'body {{WRAPPER}} .elementor-labels-above .elementor-field-group > label' => 'padding-bottom: {{SIZE}}{{UNIT}};',
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
					'{{WRAPPER}} .elementor-field-group > label, {{WRAPPER}} .elementor-field-subgroup label' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);

		$this->add_control(
			'mark_required_color',
			[
				'label' => esc_html__( 'Color de la marca', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .elementor-mark-required .elementor-field-label:after' => 'color: {{COLOR}};',
				],
				'condition' => [
					'mark_required' => 'yes',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'label_typography',
				'selector' => '{{WRAPPER}} .elementor-field-group > label',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);

		$this->add_control(
			'heading_html',
			[
				'label' => esc_html__( 'Campo HTML', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'html_spacing',
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
					'{{WRAPPER}} .elementor-field-type-html' => 'padding-bottom: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'html_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-field-type-html' => 'color: {{VALUE}};',
				],
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'html_typography',
				'selector' => '{{WRAPPER}} .elementor-field-type-html',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_field_style',
			[
				'label' => esc_html__( 'Campo', 'pro-elements' ),
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
				'label' => esc_html__( 'Botones', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'button_align',
			[
				'label' => esc_html__( 'Posición', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start' => [
						'title' => esc_html__( 'Izquierda', 'pro-elements' ),
						'icon' => 'eicon-h-align-left',
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-h-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Derecha', 'pro-elements' ),
						'icon' => 'eicon-h-align-right',
					],
					'stretch' => [
						'title' => esc_html__( 'Estirado', 'pro-elements' ),
						'icon' => 'eicon-h-align-stretch',
					],
				],
				'default' => 'stretch',
				'prefix_class' => 'elementor%s-button-align-',
			]
		);

		$this->add_responsive_control(
			'button_content_align',
			[
				'label' => esc_html__( 'Alineación', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'start'    => [
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon' => "eicon-text-align-{$start}",
					],
					'center' => [
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-text-align-center',
					],
					'end' => [
						'title' => esc_html__( 'Final', 'pro-elements' ),
						'icon' => "eicon-text-align-{$end}",
					],
					'space-between' => [
						'title' => esc_html__( 'Espacio entre ellos', 'pro-elements' ),
						'icon' => 'eicon-text-align-justify',
					],
				],
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .elementor-button span' => 'justify-content: {{VALUE}};',
				],
				'condition' => [ 'button_align' => 'stretch' ],
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
			Group_Control_Border::get_type(), [
				'name' => 'button_border',
				'selector' => '{{WRAPPER}} .elementor-button',
				'exclude' => [
					'color',
				],
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
			'heading_next_submit_button',
			[
				'label' => esc_html__( 'Botón siguiente y de envío', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'button_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_ACCENT,
				],
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"] svg *' => 'fill: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'button_border_border!' => '',
				],
			]
		);

		$this->add_control(
			'heading_previous_button',
			[
				'label' => esc_html__( 'Botón anterior', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'previous_button_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_ACCENT,
				],
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'previous_button_text_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'previous_button_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'button_border_border!' => '',
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
			'heading_next_submit_button_hover',
			[
				'label' => esc_html__( 'Botón siguiente y de envío', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'button_background_hover_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next:hover' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_hover_color',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next:hover' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]:hover' => 'color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]:hover svg *' => 'fill: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_hover_border_color',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next:hover' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .elementor-button[type="submit"]:hover' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'button_border_border!' => '',
				],
			]
		);

		$this->add_control(
			'heading_previous_button_hover',
			[
				'label' => esc_html__( 'Botón anterior', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'previous_button_background_color_hover',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'previous_button_text_color_hover',
			[
				'label' => esc_html__( 'Color del texto', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'previous_button_border_color_hover',
			[
				'label' => esc_html__( 'Color del borde', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous:hover' => 'border-color: {{VALUE}};',
				],
				'condition' => [
					'button_border_border!' => '',
				],
			]
		);

		$this->add_control(
			'hover_transition_duration',
			[
				'label' => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 's', 'ms', 'custom' ],
				'default' => [
					'unit' => 'ms',
				],
				'selectors' => [
					'{{WRAPPER}} .e-form__buttons__wrapper__button-previous' => 'transition-duration: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .e-form__buttons__wrapper__button-next' => 'transition-duration: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-button[type="submit"] svg *' => 'transition-duration: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .elementor-button[type="submit"]' => 'transition-duration: {{SIZE}}{{UNIT}};',
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

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'button_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'selectors' => [
					'{{WRAPPER}} .elementor-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
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

		$this->end_controls_section();

		$this->start_controls_section(
			'section_messages_style',
			[
				'label' => esc_html__( 'Mensajes', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'message_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				],
				'selector' => '{{WRAPPER}} .elementor-message',
			]
		);

		$this->add_control(
			'success_message_color',
			[
				'label' => esc_html__( 'Color del mensaje de éxito', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-message.elementor-message-success' => 'color: {{COLOR}};',
				],
			]
		);

		$this->add_control(
			'error_message_color',
			[
				'label' => esc_html__( 'Color del mensaje de error', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-message.elementor-message-danger' => 'color: {{COLOR}};',
				],
			]
		);

		$this->add_control(
			'inline_message_color',
			[
				'label' => esc_html__( 'Color del mensaje del campo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .elementor-message.elementor-help-inline' => 'color: {{COLOR}};',
				],
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_steps_style',
			[
				'label' => esc_html__( 'Pasos', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'steps_typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				],
				'selector' => '{{WRAPPER}} .e-form__indicators__indicator, {{WRAPPER}} .e-form__indicators__indicator__label',
				'conditions' => [
					'terms' => [
						[
							'name' => 'step_type',
							'operator' => '!in',
							'value' => [
								'icon',
								'progress_bar',
							],
						],
					],
				],
			]
		);

		$this->add_responsive_control(
			'steps_gap',
			[
				'label' => esc_html__( 'Espaciado', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'default' => [
					'size' => 20,
				],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicators-spacing: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_responsive_control(
			'steps_icon_size',
			[
				'label' => esc_html__( 'Tamaño del icono', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'custom' ],
				'default' => [
					'size' => 15,
				],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'conditions' => [
					'terms' => [
						[
							'name' => 'step_type',
							'operator' => 'in',
							'value' => [
								'icon',
								'icon_text',
							],
						],
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-icon-size: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_responsive_control(
			'steps_padding',
			[
				'label' => esc_html__( 'Relleno', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'default' => [
					'size' => 30,
				],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-padding: {{SIZE}}{{UNIT}}',
				],
				'conditions' => [
					'terms' => [
						[
							'name' => 'step_type',
							'operator' => '!in',
							'value' => [
								'text',
								'progress_bar',
							],
						],
					],
				],
			]
		);

		$this->start_controls_tabs( 'steps_state', [
			'condition' => [
				'step_type!' => 'progress_bar',
			],
		] );

		$this->start_controls_tab(
			'tab_steps_state_inactive',
			[
				'label' => esc_html__( 'Inactivo', 'pro-elements' ),
			]
		);

		$this->add_control(
			'step_inactive_primary_color',
			[
				'label' => esc_html__( 'Color principal', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-inactive-primary-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'step_inactive_secondary_color',
			[
				'label' => esc_html__( 'Color secundario', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-inactive-secondary-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_steps_state_active',
			[
				'label' => esc_html__( 'Activo', 'pro-elements' ),
			]
		);

		$this->add_control(
			'step_active_primary_color',
			[
				'label' => esc_html__( 'Color principal', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_ACCENT,
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-active-primary-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'step_active_secondary_color',
			[
				'label' => esc_html__( 'Color secundario', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-active-secondary-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_steps_state_completed',
			[
				'label' => esc_html__( 'Completado', 'pro-elements' ),
			]
		);

		$this->add_control(
			'step_completed_primary_color',
			[
				'label' => esc_html__( 'Color principal', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_ACCENT,
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-completed-primary-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'step_completed_secondary_color',
			[
				'label' => esc_html__( 'Color secundario', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'default' => '#ffffff',
				'condition' => [
					'step_icon_shape!' => 'none',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-completed-secondary-color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'step_divider_width',
			[
				'label' => esc_html__( 'Ancho del divisor', 'pro-elements' ),
				'separator' => 'before',
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'vw', 'custom' ],
				'default' => [
					'size' => 1,
				],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'condition' => [
					'step_type!' => 'progress_bar',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-divider-width: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_responsive_control(
			'step_divider_gap',
			[
				'label' => esc_html__( 'Espacio del divisor', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'default' => [
					'size' => 10,
				],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'condition' => [
					'step_type!' => 'progress_bar',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-divider-gap: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'step_progress_bar_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_ACCENT,
				],
				'condition' => [
					'step_type' => 'progress_bar',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-progress-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'step_progress_bar_background_color',
			[
				'label' => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'condition' => [
					'step_type' => 'progress_bar',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-progress-background-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'step_progress_bar_height',
			[
				'label' => esc_html__( 'Alto', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', 'em', 'rem', 'vh', 'custom' ],
				'default' => [
					'size' => 20,
				],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'condition' => [
					'step_type' => 'progress_bar',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-progress-height: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'step_progress_bar_border_radius',
			[
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
				'default' => [
					'size' => 0,
				],
				'range' => [
					'px' => [
						'max' => 100,
					],
					'em' => [
						'max' => 10,
					],
					'rem' => [
						'max' => 10,
					],
				],
				'condition' => [
					'step_type' => 'progress_bar',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-progress-border-radius: {{SIZE}}{{UNIT}}',
				],
			]
		);

		$this->add_control(
			'step_progress_bar_percentage_heading',
			[
				'label' => esc_html__( 'Porcentaje', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'step_type' => 'progress_bar',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'step_progress_bar_percentage__typography',
				'global' => [
					'default' => Global_Typography::TYPOGRAPHY_ACCENT,
				],
				'selector' => '{{WRAPPER}} .e-form__indicators__indicator__progress__meter',
				'condition' => [
					'step_type' => 'progress_bar',
				],
			]
		);

		$this->add_control(
			'step_progress_bar_percentage_color',
			[
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => [
					'default' => Global_Colors::COLOR_TEXT,
				],
				'condition' => [
					'step_type' => 'progress_bar',
				],
				'selectors' => [
					'{{WRAPPER}}' => '--e-form-steps-indicator-progress-meter-color: {{VALUE}};',
				],
			]
		);

		// End of steps style.
		$this->end_controls_section();

	}

	/** ¿Se está editando en el editor del constructor? */
	private static function editing(): bool {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$editor = isset( \Elementor\Plugin::$instance->editor ) ? \Elementor\Plugin::$instance->editor : null;
		return is_object( $editor ) && method_exists( $editor, 'is_edit_mode' ) && $editor->is_edit_mode();
	}

	/** Documento principal que guarda el formulario (el `post_id` que el manejador usa para encontrarlo). */
	private static function current_post_id(): int {
		$plugin = class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
		$documents = is_object( $plugin ) && isset( $plugin->documents ) ? $plugin->documents : null;
		if ( is_object( $documents ) && method_exists( $documents, 'get_current' ) && $documents->get_current() ) {
			return (int) $documents->get_current()->get_main_id();
		}
		return (int) get_the_ID();
	}

	/** Atributos de un campo: las mismas claves y clases que el formulario de PRO (`Form_Base::form_fields_render_attributes()`). */
	protected function form_fields_render_attributes( $i, $instance, $item ) {
		$this->add_render_attribute(
			array(
				'field-group' . $i => array(
					'class' => array(
						'elementor-field-type-' . $item['field_type'],
						'elementor-field-group',
						'elementor-column',
						'elementor-field-group-' . $item['custom_id'],
					),
				),
				'input' . $i       => array(
					'type'  => $item['field_type'],
					'name'  => $this->get_attribute_name( $item ),
					'id'    => $this->get_attribute_id( $item ),
					'class' => array(
						'elementor-field',
						'elementor-size-' . $item['input_size'],
						empty( $item['css_classes'] ) ? '' : esc_attr( $item['css_classes'] ),
					),
				),
				'label' . $i       => array(
					'for'   => $this->get_attribute_id( $item ),
					'class' => 'elementor-field-label',
				),
			)
		);
		if ( empty( $item['width'] ) ) {
			$item['width'] = '100';
		}
		$this->add_render_attribute( 'field-group' . $i, 'class', 'elementor-col-' . $item['width'] );
		if ( ! empty( $item['width_tablet'] ) ) {
			$this->add_render_attribute( 'field-group' . $i, 'class', 'elementor-md-' . $item['width_tablet'] );
		}
		if ( ! empty( $item['allow_multiple'] ) ) {
			$this->add_render_attribute( 'field-group' . $i, 'class', 'elementor-field-type-' . $item['field_type'] . '-multiple' );
		}
		if ( ! empty( $item['width_mobile'] ) ) {
			$this->add_render_attribute( 'field-group' . $i, 'class', 'elementor-sm-' . $item['width_mobile'] );
		}
		if ( isset( $item['placeholder'] ) && '' !== (string) $item['placeholder'] ) {
			$this->add_render_attribute( 'input' . $i, 'placeholder', $item['placeholder'] );
		}
		if ( ! empty( $item['field_value'] ) ) {
			$this->add_render_attribute( 'input' . $i, 'value', $item['field_value'] );
		}
		if ( empty( $instance['show_labels'] ) ) {
			$this->add_render_attribute( 'label' . $i, 'class', 'elementor-screen-only' );
		}
		if ( ! empty( $item['required'] ) ) {
			$class = 'elementor-field-required';
			if ( ! empty( $instance['mark_required'] ) ) {
				$class .= ' elementor-mark-required';
			}
			$this->add_render_attribute( 'field-group' . $i, 'class', $class );
			$this->add_render_attribute( 'input' . $i, 'required', 'required' );
		}
	}

	protected function make_textarea_field( $item, $item_index ) {
		$this->add_render_attribute(
			'textarea' . $item_index,
			array(
				'class' => array( 'elementor-field-textual', 'elementor-field', esc_attr( $item['css_classes'] ), 'elementor-size-' . $item['input_size'] ),
				'name'  => $this->get_attribute_name( $item ),
				'id'    => $this->get_attribute_id( $item ),
				'rows'  => $item['rows'],
			)
		);
		if ( $item['placeholder'] ) {
			$this->add_render_attribute( 'textarea' . $item_index, 'placeholder', $item['placeholder'] );
		}
		if ( $item['required'] ) {
			$this->add_render_attribute( 'textarea' . $item_index, 'required', 'required' );
		}
		$value = empty( $item['field_value'] ) ? '' : $item['field_value'];
		return '<textarea ' . $this->get_render_attribute_string( 'textarea' . $item_index ) . '>' . esc_textarea( $value ) . '</textarea>';
	}

	protected function make_select_field( $item, $i ) {
		$this->add_render_attribute(
			array(
				'select-wrapper' . $i => array(
					'class' => array( 'elementor-field', 'elementor-select-wrapper', 'remove-before', esc_attr( $item['css_classes'] ) ),
				),
				'select' . $i         => array(
					'name'  => $this->get_attribute_name( $item ) . ( ! empty( $item['allow_multiple'] ) ? '[]' : '' ),
					'id'    => $this->get_attribute_id( $item ),
					'class' => array( 'elementor-field-textual', 'elementor-size-' . $item['input_size'] ),
				),
			)
		);
		if ( $item['required'] ) {
			$this->add_render_attribute( 'select' . $i, 'required', 'required' );
		}
		if ( $item['allow_multiple'] ) {
			$this->add_render_attribute( 'select' . $i, 'multiple' );
			if ( ! empty( $item['select_size'] ) ) {
				$this->add_render_attribute( 'select' . $i, 'size', $item['select_size'] );
			}
		}
		$options = preg_split( "/\\r\\n|\\r|\\n/", (string) $item['field_options'] );
		if ( ! $options ) {
			return '';
		}
		$html  = '<div ' . $this->get_render_attribute_string( 'select-wrapper' . $i ) . '><div class="select-caret-down-wrapper">';
		if ( ! $item['allow_multiple'] ) {
			ob_start();
			Icons_Manager::render_icon( array( 'library' => 'eicons', 'value' => 'eicon-caret-down', 'position' => 'right' ), array( 'aria-hidden' => 'true' ) );
			$html .= ob_get_clean();
		}
		$html  .= '</div><select ' . $this->get_render_attribute_string( 'select' . $i ) . '>';
		$chosen = empty( $item['field_value'] ) ? array() : explode( ',', (string) $item['field_value'] );
		foreach ( $options as $option ) {
			$label = $option;
			$value = $option;
			if ( false !== strpos( $option, '|' ) ) {
				list( $label, $value ) = explode( '|', $option );
			}
			$html .= '<option value="' . esc_attr( $value ) . '"' . ( in_array( esc_attr( $value ), $chosen, true ) ? ' selected="selected"' : '' ) . '>' . esc_html( $label ) . '</option>';
		}
		return $html . '</select></div>';
	}

	/**
	 * Radios y casillas: las mismas opciones, nombres e `id` que PRO, dentro de un grupo con nombre (la etiqueta del campo,
	 * por `aria-labelledby`), porque una etiqueta `for` no puede nombrar varias opciones.
	 */
	protected function make_radio_checkbox_field( $item, $item_index, $type ) {
		$options = preg_split( "/\\r\\n|\\r|\\n/", (string) $item['field_options'] );
		if ( ! $options ) {
			return '';
		}
		$group = 'radio' === $type ? 'radiogroup' : 'group';
		$html  = '<div class="elementor-field-subgroup ' . esc_attr( $item['css_classes'] ) . ' ' . esc_attr( $item['inline_list'] ) . '" role="' . $group . '"';
		if ( ! empty( $item['field_label'] ) ) {
			$html .= ' aria-labelledby="' . esc_attr( $this->get_attribute_id( $item ) ) . '-label"';
		}
		$html .= '>';
		foreach ( $options as $key => $option ) {
			$element_id = esc_attr( $item['custom_id'] ) . $key;
			$html_id    = $this->get_attribute_id( $item ) . '-' . $key;
			$label      = $option;
			$value      = $option;
			if ( false !== strpos( $option, '|' ) ) {
				list( $label, $value ) = explode( '|', $option );
			}
			$this->add_render_attribute(
				$element_id,
				array(
					'type'  => $type,
					'value' => $value,
					'id'    => $html_id,
					'name'  => $this->get_attribute_name( $item ) . ( ( 'checkbox' === $type && count( $options ) > 1 ) ? '[]' : '' ),
				)
			);
			if ( ! empty( $item['field_value'] ) && $value === $item['field_value'] ) {
				$this->add_render_attribute( $element_id, 'checked', 'checked' );
			}
			if ( $item['required'] && 'radio' === $type ) {
				$this->add_render_attribute( $element_id, 'required', 'required' );
			}
			$html .= '<span class="elementor-field-option"><input ' . $this->get_render_attribute_string( $element_id ) . '> <label for="' . esc_attr( $html_id ) . '">' . wp_kses_post( $label ) . '</label></span>';
		}
		return $html . '</div>';
	}

	/** Icono del botón (con la migración de Font Awesome 4, como PRO). */
	private function render_icon_with_fallback( $settings ) {
		$migrated = isset( $settings['__fa4_migrated']['selected_button_icon'] );
		$is_new   = empty( $settings['button_icon'] ) && Icons_Manager::is_migration_allowed();
		if ( $is_new || $migrated ) {
			Icons_Manager::render_icon( $settings['selected_button_icon'], array( 'aria-hidden' => 'true' ) );
		} else {
			echo '<i class="' . esc_attr( $settings['button_icon'] ) . '" aria-hidden="true"></i>';
		}
	}

	/** Un campo con su grupo, su etiqueta y su control (mismos filtros y acciones que PRO). */
	private function field_html( array $instance, $item_index, array $item ): string {
		$item['input_size'] = $instance['input_size'];
		$this->form_fields_render_attributes( $item_index, $instance, $item );
		$field_type = $item['field_type'];
		/** Filtros de PRO: el mismo `$item`, índice y widget (los usan los tipos de campo, honeypot, reCAPTCHA y F7b). */
		$item = apply_filters( 'elementor_pro/forms/render/item', $item, $item_index, $this );
		$item = apply_filters( "elementor_pro/forms/render/item/{$field_type}", $item, $item_index, $this );
		$type = (string) $item['field_type'];
		ob_start();
		echo '<div ' . $this->get_render_attribute_string( 'field-group' . $item_index ) . '>';
		if ( ! in_array( $type, array( 'hidden', 'html', 'step' ), true ) && ! empty( $item['field_label'] ) ) {
			if ( in_array( $type, array( 'radio', 'checkbox' ), true ) ) {
				// Sin `for`: nombra el grupo de opciones por `aria-labelledby`.
				$this->remove_render_attribute( 'label' . $item_index, 'for' );
				$this->add_render_attribute( 'label' . $item_index, 'id', $this->get_attribute_id( $item ) . '-label' );
			}
			echo '<label ' . $this->get_render_attribute_string( 'label' . $item_index ) . '>' . $item['field_label'] . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- la etiqueta del campo, como PRO.
		}
		switch ( $type ) {
			case 'html':
				echo do_shortcode( $item['field_html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- el HTML del campo, como PRO.
				break;
			case 'textarea':
				echo $this->make_textarea_field( $item, $item_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'select':
				echo $this->make_select_field( $item, $item_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'radio':
			case 'checkbox':
				echo $this->make_radio_checkbox_field( $item, $item_index, $type ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'text':
			case 'email':
			case 'url':
			case 'password':
			case 'hidden':
			case 'search':
				$this->add_render_attribute( 'input' . $item_index, 'class', 'elementor-field-textual' );
				echo '<input size="1" ' . $this->get_render_attribute_string( 'input' . $item_index ) . '>';
				break;
			case 'step':
				break;
			default:
				/** Acción de PRO: los tipos de campo registrados (fecha, hora, número, subida, aceptación, reCAPTCHA, honeypot…). */
				do_action( "elementor_pro/forms/render_field/{$type}", $item, $item_index, $this );
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	/** Clases de la columna del botón por dispositivo (`elementor-md-*`, `elementor-sm-*`). */
	private static function responsive_widths( array $instance ): array {
		$out = array();
		if ( ! empty( $instance['button_width_tablet'] ) ) {
			$out[] = 'elementor-md-' . $instance['button_width_tablet'];
		}
		if ( ! empty( $instance['button_width_mobile'] ) ) {
			$out[] = 'elementor-sm-' . $instance['button_width_mobile'];
		}
		return $out;
	}

	/** Indicadores de los pasos (el mismo marcado que armaba el script de PRO, pintado en el servidor). */
	private function step_indicators( array $instance, array $steps ): string {
		$type  = (string) $instance['step_type'];
		$count = count( $steps );
		if ( 'none' === $type || ! $count ) {
			return '';
		}
		$html = '<div class="e-form__indicators e-form__indicators--type-' . esc_attr( $type ) . '"';
		if ( 'progress_bar' === $type ) {
			$value = (int) floor( 100 / $count );
			/* translators: 1: paso actual, 2: total de pasos. */
			$text = sprintf( esc_html__( 'Paso %1$d de %2$d', 'pro-elements' ), 1, $count );
			return $html . '><div class="e-form__indicators__indicator__progress" role="progressbar" aria-label="' . esc_attr__( 'Progreso del formulario', 'pro-elements' ) . '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $value . '" aria-valuetext="' . esc_attr( $text ) . '"><div class="e-form__indicators__indicator__progress__meter">' . $value . '%</div></div></div>';
		}
		$html .= ' aria-hidden="true">';
		$shape = 'e-form__indicators__indicator--shape-' . preg_replace( '/[^a-z]/', '', (string) $instance['step_icon_shape'] );
		foreach ( $steps as $k => $step ) {
			if ( $k ) {
				$html .= '<div class="e-form__indicators__indicator__separator"></div>';
			}
			$html .= '<div class="e-form__indicators__indicator e-form__indicators__indicator--state-' . ( $k ? 'inactive' : 'active' ) . '">';
			if ( false !== strpos( $type, 'icon' ) ) {
				ob_start();
				if ( ! empty( $step['selected_icon']['value'] ) ) {
					Icons_Manager::render_icon( $step['selected_icon'], array( 'aria-hidden' => 'true' ) );
				}
				$html .= '<div class="e-form__indicators__indicator__icon ' . $shape . '">' . ob_get_clean() . '</div>';
			}
			if ( false !== strpos( $type, 'number' ) ) {
				$html .= '<div class="e-form__indicators__indicator__number ' . $shape . '">' . ( $k + 1 ) . '</div>';
			}
			if ( false !== strpos( $type, 'text' ) ) {
				$html .= '<span class="e-form__indicators__indicator__label">' . esc_html( (string) ( $step['field_label'] ?? '' ) ) . '</span>';
			}
			$html .= '</div>';
		}
		return $html . '</div>';
	}

	/** Botón «anterior» o «siguiente» de un paso (el texto del paso o el del widget). */
	private function step_button( array $instance, array $step, string $direction, string $size ): string {
		$own   = 'next' === $direction ? ( $step['next_button'] ?? '' ) : ( $step['previous_button'] ?? '' );
		$label = '' !== (string) $own ? (string) $own : (string) ( $instance[ 'step_' . $direction . '_label' ] ?? '' );
		$class = 'elementor-button ' . $size . ' e-form__buttons__wrapper__button e-form__buttons__wrapper__button-' . $direction;
		return '<div class="elementor-field-group e-form__buttons__wrapper elementor-field-type-' . $direction . '"><button type="button" class="' . esc_attr( trim( preg_replace( '/\s+/', ' ', $class ) ) ) . '" data-direction="' . $direction . '">' . esc_html( $label ) . '</button></div>';
	}

	/** Botón de envío (con su grupo); `$wrapper` son las clases del grupo. */
	private function submit_html( array $instance, array $wrapper, bool $in_step ): string {
		if ( $in_step ) {
			$this->add_render_attribute( 'button', 'class', 'e-form__buttons__wrapper__button' );
		}
		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', array_filter( $wrapper ) ) ) . '"><button ' . $this->get_render_attribute_string( 'button' ) . '><span ' . $this->get_render_attribute_string( 'button-content-wrapper' ) . '>';
		if ( ! empty( $instance['button_icon'] ) || ! empty( $instance['selected_button_icon']['value'] ) ) {
			echo '<span ' . $this->get_render_attribute_string( 'button-icon' ) . '>';
			$this->render_icon_with_fallback( $instance );
			if ( empty( $instance['button_text'] ) ) {
				echo '<span class="elementor-screen-only">' . esc_html__( 'Enviar', 'pro-elements' ) . '</span>';
			}
			echo '</span>';
		}
		if ( ! empty( $instance['button_text'] ) ) {
			echo '<span ' . $this->get_render_attribute_string( 'button-text' ) . '>' . wp_kses_post( $instance['button_text'] ) . '</span>';
		}
		echo '</span></button></div>';
		return (string) ob_get_clean();
	}

	protected function render() {
		$instance = $this->get_settings_for_display();
		$fields   = is_array( $instance['form_fields'] ?? null ) ? array_values( $instance['form_fields'] ) : array();
		if ( ! self::editing() ) {
			/** Acción de PRO antes de pintar el formulario en el front. */
			do_action( 'elementor-pro/forms/pre_render', $instance, $this );
		}
		$this->add_render_attribute(
			array(
				'wrapper'                => array( 'class' => array( 'elementor-form-fields-wrapper', 'elementor-labels-' . $instance['label_position'] ) ),
				'button'                 => array( 'class' => 'elementor-button', 'type' => 'submit' ),
				'button-content-wrapper' => array( 'class' => 'elementor-button-content-wrapper' ),
				'button-icon'            => array( 'class' => 'elementor-button-icon' ),
				'button-text'            => array( 'class' => 'elementor-button-text' ),
			)
		);
		$width = empty( $instance['button_width'] ) ? '100' : (string) $instance['button_width'];
		$size  = empty( $instance['button_size'] ) ? '' : 'elementor-size-' . $instance['button_size'];
		if ( '' !== $size ) {
			$this->add_render_attribute( 'button', 'class', $size );
		}
		if ( ! empty( $instance['button_type'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-button-' . $instance['button_type'] );
		}
		if ( ! empty( $instance['button_hover_animation'] ) ) {
			$this->add_render_attribute( 'button', 'class', 'elementor-animation-' . $instance['button_hover_animation'] );
		}
		if ( ! empty( $instance['button_css_id'] ) ) {
			$this->add_render_attribute( 'button', 'id', $instance['button_css_id'] );
		}
		$messages = self::default_messages();
		$server   = ! empty( $instance['custom_messages'] ) && ! empty( $instance['server_message'] ) ? (string) $instance['server_message'] : $messages['server_error'];
		$this->add_render_attribute(
			'form',
			array(
				'class'          => 'elementor-form',
				'method'         => 'post',
				'data-digi-form' => self::SUBMIT_ACTION,
				'data-digi-ajax' => admin_url( 'admin-ajax.php' ),
				'data-digi-error' => $server,
			)
		);
		if ( ! empty( $instance['form_id'] ) ) {
			$this->add_render_attribute( 'form', 'id', $instance['form_id'] );
		}
		if ( ! empty( $instance['form_name'] ) ) {
			$this->add_render_attribute( 'form', 'name', $instance['form_name'] );
			$this->add_render_attribute( 'form', 'aria-label', $instance['form_name'] );
		}
		if ( 'custom' === $instance['form_validation'] ) {
			$this->add_render_attribute( 'form', 'novalidate' );
		}
		$referer_title = trim( wp_title( '', false ) );
		if ( ! $referer_title && is_home() ) {
			$referer_title = get_option( 'blogname' );
		}

		// Pasos: cada campo «step» abre un grupo con los campos que le siguen (como el script de PRO, en el servidor).
		$steps = array();
		foreach ( $fields as $index => $item ) {
			if ( 'step' === ( $item['field_type'] ?? '' ) ) {
				$steps[ $index ] = $item;
			}
		}
		$total  = count( $steps );
		$submit = array_merge( array( 'elementor-field-group', 'elementor-column', 'elementor-field-type-submit', 'elementor-col-' . $width, 'e-form__buttons' ), self::responsive_widths( $instance ) );
		if ( $total ) {
			$this->add_render_attribute( 'form', 'data-digi-steps', (string) $total );
		}

		echo '<form ' . $this->get_render_attribute_string( 'form' ) . '>';
		echo '<input type="hidden" name="post_id" value="' . esc_attr( (string) self::current_post_id() ) . '"/>';
		echo '<input type="hidden" name="form_id" value="' . esc_attr( $this->get_id() ) . '"/>';
		echo '<input type="hidden" name="referer_title" value="' . esc_attr( $referer_title ) . '" />';
		if ( is_singular() ) {
			// `queried_id` puede ser distinto de `post_id` en una plantilla del Theme Builder.
			echo '<input type="hidden" name="queried_id" value="' . esc_attr( (string) get_the_ID() ) . '"/>';
		}
		if ( $total ) {
			echo $this->step_indicators( $instance, array_values( $steps ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado al armarlo.
		}
		echo '<div ' . $this->get_render_attribute_string( 'wrapper' ) . '>';
		$step = -1;
		foreach ( $fields as $item_index => $item ) {
			$html = $this->field_html( $instance, $item_index, $item );
			if ( ! isset( $steps[ $item_index ] ) ) {
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado al armarlo.
				continue;
			}
			if ( $step >= 0 ) {
				echo $this->step_close( $instance, $steps, $step, $size, $submit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			++$step;
			// El grupo del paso: sus clases de campo pasan a `e-form__step` (como el script de PRO) y su contenido es el paso.
			$classes = array_diff( explode( ' ', (string) preg_replace( '/^<div class="([^"]*)".*/s', '$1', $html ) ), array( 'elementor-field-group', 'elementor-column', '' ) );
			$label   = (string) ( $item['field_label'] ?? '' );
			/* translators: 1: número del paso, 2: total de pasos. */
			$name = sprintf( esc_html__( 'Paso %1$d de %2$d', 'pro-elements' ), $step + 1, $total ) . ( '' !== $label ? ': ' . $label : '' );
			echo '<div class="' . esc_attr( implode( ' ', array_merge( $classes, array( 'e-form__step' ) ) ) ) . '" role="group" aria-label="' . esc_attr( $name ) . '" data-digi-step="' . esc_attr( (string) $step ) . '"' . ( $step ? ' hidden' : '' ) . '>';
		}
		if ( $step >= 0 ) {
			echo $this->step_close( $instance, $steps, $step, $size, $submit ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( $total < 2 ) {
			echo $this->submit_html( $instance, $submit, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
		if ( $total ) {
			echo '<div class="elementor-screen-only" aria-live="polite" data-digi-status></div>';
		}
		echo '</form>';
	}

	/** Cierra un paso con sus botones: «anterior» desde el segundo, «siguiente» o, en el último, el de envío. */
	private function step_close( array $instance, array $steps, int $step, string $size, array $submit ): string {
		$total = count( $steps );
		$item  = array_values( $steps )[ $step ];
		$html  = '<div class="' . esc_attr( implode( ' ', array_merge( array( 'e-form__buttons', 'elementor-column', 'elementor-col-' . ( $instance['button_width'] ?? '' ) ), self::responsive_widths( $instance ) ) ) ) . '">';
		if ( $step ) {
			$html .= $this->step_button( $instance, $item, 'previous', $size );
		}
		if ( $step < $total - 1 ) {
			$html .= $this->step_button( $instance, $item, 'next', $size );
		} elseif ( $total > 1 ) {
			$wrapper = array_diff( $submit, array( 'elementor-column', 'e-form__buttons', 'elementor-col-' . ( empty( $instance['button_width'] ) ? '100' : $instance['button_width'] ) ) );
			$wrapper = array_values( array_filter( $wrapper, static function ( $class ) {
				return ! preg_match( '/^elementor-(sm|md)-\d+$/', $class );
			} ) );
			$wrapper[] = 'e-form__buttons__wrapper';
			$html     .= $this->submit_html( $instance, $wrapper, true );
		}
		return $html . '</div></div>';
	}

	public function render_plain_content() {}
}
