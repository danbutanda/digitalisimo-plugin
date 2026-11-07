<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;

defined( 'ABSPATH' ) || exit;

/**
 * Cuenta regresiva (`digitalisimo-countdown`): sustituye al widget «Countdown» de PRO (`modules/countdown/widgets/countdown.php`,
 * mapa `modules/digitalisimo-convert/maps/countdown.php`). Repite sus controles con el mismo nombre, valor por defecto y
 * condiciones; el marcado, las clases, la hoja y el script son propios (sin jQuery ni los paquetes de PRO).
 *
 * - Fecha límite: el servidor la convierte a una marca de tiempo con la zona horaria del sitio, como PRO
 *   (`data-digi-date`); «Temporizador perpetuo» guarda su vencimiento en el navegador del visitante con las mismas claves
 *   que PRO (`<id del elemento>-evergreen_due_date` y `-evergreen_interval`), así un visitante conserva su plazo al
 *   convertir el widget (el `id` no cambia).
 * - Acciones al terminar (`data-digi-actions`, sólo fuera del editor, como PRO): ocultar la cuenta, redirigir (sólo a
 *   `http`/`https`) y mostrar el mensaje, que se pinta oculto (`hidden`). El elemento emite `digi-countdown-expire` y,
 *   si la página tiene jQuery, el `countdown_expire` de jQuery que emitía PRO, para el código propio del sitio.
 * - Los dígitos se pintan ya en el servidor (sin cajas vacías ni salto de diseño); el script los pone al día cada segundo.
 *   La cuenta tiene `role="timer"` (región viva apagada: no se anuncia cada segundo).
 *
 * Diferencias declaradas con PRO: los dígitos iniciales los pinta el servidor (PRO los dejaba vacíos hasta su script), el
 * mensaje oculto usa `hidden` y con la cuenta ya vencida al cargar las acciones corren una sola vez (PRO las repetía cada
 * segundo); las etiquetas por defecto de lo nuevo son textos propios en español (en lo convertido de PRO, las de PRO).
 */
class Countdown extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-countdown';
	}

	public function get_title() {
		return esc_html__( 'Cuenta regresiva', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-countdown';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'cuenta regresiva', 'countdown', 'temporizador', 'timer', 'fecha', 'tiempo', 'número', 'perpetuo', 'evergreen' );
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	/** Zona horaria del sitio, para la descripción de la fecha (la del núcleo si existe). */
	private static function timezone_label(): string {
		if ( class_exists( '\Elementor\Utils' ) && method_exists( '\Elementor\Utils', 'get_timezone_string' ) ) {
			return (string) \Elementor\Utils::get_timezone_string();
		}
		return function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : 'UTC';
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_countdown',
			array(
				'label' => esc_html__( 'Cuenta regresiva', 'pro-elements' ),
			)
		);

		$this->add_control(
			'countdown_type',
			array(
				'label'   => esc_html__( 'Tipo', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'due_date'  => esc_html__( 'Fecha límite', 'pro-elements' ),
					'evergreen' => esc_html__( 'Temporizador perpetuo', 'pro-elements' ),
				),
				'default' => 'due_date',
			)
		);

		$this->add_control(
			'due_date',
			array(
				'label'       => esc_html__( 'Fecha límite', 'pro-elements' ),
				'type'        => Controls_Manager::DATE_TIME,
				'default'     => gmdate( 'Y-m-d H:i', strtotime( '+1 month' ) + ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ),
				/* translators: %s: zona horaria. */
				'description' => sprintf( esc_html__( 'Fecha según la zona horaria del sitio: %s.', 'pro-elements' ), esc_html( self::timezone_label() ) ),
				'condition'   => array(
					'countdown_type' => 'due_date',
				),
				'dynamic'     => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'evergreen_counter_hours',
			array(
				'label'       => esc_html__( 'Horas', 'pro-elements' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 47,
				'placeholder' => esc_html__( 'Horas', 'pro-elements' ),
				'condition'   => array(
					'countdown_type' => 'evergreen',
				),
				'dynamic'     => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'evergreen_counter_minutes',
			array(
				'label'       => esc_html__( 'Minutos', 'pro-elements' ),
				'type'        => Controls_Manager::NUMBER,
				'default'     => 59,
				'placeholder' => esc_html__( 'Minutos', 'pro-elements' ),
				'condition'   => array(
					'countdown_type' => 'evergreen',
				),
				'dynamic'     => array(
					'active' => true,
				),
			)
		);

		foreach ( array(
			'show_days'    => esc_html__( 'Días', 'pro-elements' ),
			'show_hours'   => esc_html__( 'Horas', 'pro-elements' ),
			'show_minutes' => esc_html__( 'Minutos', 'pro-elements' ),
			'show_seconds' => esc_html__( 'Segundos', 'pro-elements' ),
		) as $name => $label ) {
			$this->add_control(
				$name,
				array(
					'label'     => $label,
					'type'      => Controls_Manager::SWITCHER,
					'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
					'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
					'default'   => 'yes',
				)
			);
		}

		$this->add_control(
			'show_labels',
			array(
				'label'     => esc_html__( 'Mostrar etiquetas', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'custom_labels',
			array(
				'label'     => esc_html__( 'Etiquetas personalizadas', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array(
					'show_labels!' => '',
				),
			)
		);

		foreach ( self::default_labels() as $name => $label ) {
			$this->add_control(
				$name,
				array(
					'label'       => $label,
					'type'        => Controls_Manager::TEXT,
					'default'     => $label,
					'placeholder' => $label,
					'condition'   => array(
						'show_labels!'   => '',
						'custom_labels!' => '',
						'show_' . substr( $name, 6 ) => 'yes',
					),
					'dynamic'     => array(
						'active' => true,
					),
					'ai'          => array(
						'active' => false,
					),
				)
			);
		}

		$this->add_control(
			'expire_actions',
			array(
				'label'       => esc_html__( 'Acciones al terminar', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT2,
				'options'     => array(
					'redirect' => esc_html__( 'Redirigir', 'pro-elements' ),
					'hide'     => esc_html__( 'Ocultar', 'pro-elements' ),
					'message'  => esc_html__( 'Mostrar un mensaje', 'pro-elements' ),
				),
				'label_block' => true,
				'separator'   => 'before',
				'render_type' => 'none',
				'multiple'    => true,
			)
		);

		$this->add_control(
			'message_after_expire',
			array(
				'label'     => esc_html__( 'Mensaje', 'pro-elements' ),
				'type'      => Controls_Manager::TEXTAREA,
				'separator' => 'before',
				'dynamic'   => array(
					'active' => true,
				),
				'condition' => array(
					'expire_actions' => 'message',
				),
			)
		);

		$this->add_control(
			'expire_redirect_url',
			array(
				'label'     => esc_html__( 'URL de destino', 'pro-elements' ),
				'type'      => Controls_Manager::URL,
				'separator' => 'before',
				'options'   => false,
				'dynamic'   => array(
					'active' => true,
				),
				'condition' => array(
					'expire_actions' => 'redirect',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_countdown_style',
			array(
				'label' => esc_html__( 'Cuenta regresiva', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_container',
			array(
				'label' => esc_html__( 'Contenedor', 'pro-elements' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'label_display',
			array(
				'label'        => esc_html__( 'Disposición', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'block'  => array(
						'title' => esc_html__( 'En bloque', 'pro-elements' ),
						'icon'  => 'eicon-grow',
					),
					'inline' => array(
						'title' => esc_html__( 'En línea', 'pro-elements' ),
						'icon'  => 'eicon-shrink',
					),
				),
				'default'      => 'block',
				'prefix_class' => 'digi-countdown--label-',
			)
		);

		$this->add_responsive_control(
			'container_width',
			array(
				'label'          => esc_html__( 'Ancho del contenedor', 'pro-elements' ),
				'type'           => Controls_Manager::SLIDER,
				'size_units'     => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'        => array(
					'unit' => '%',
					'size' => 100,
				),
				'tablet_default' => array(
					'unit' => '%',
				),
				'mobile_default' => array(
					'unit' => '%',
				),
				'range'          => array(
					'px'  => array(
						'max' => 2000,
					),
					'em'  => array(
						'max' => 200,
					),
					'rem' => array(
						'max' => 200,
					),
				),
				'selectors'      => array(
					'{{WRAPPER}} .digi-countdown' => 'max-width: {{SIZE}}{{UNIT}};',
				),
				'condition'      => array(
					'label_display' => 'block',
				),
			)
		);

		$this->add_control(
			'boxes_alignment',
			array(
				'label'     => esc_html__( 'Alineación', 'pro-elements' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => self::start_end_options(),
				'classes'   => 'elementor-control-start-end',
				'selectors' => array(
					'{{WRAPPER}} .digi-countdown' => 'text-align: {{VALUE}};',
				),
				'condition' => array(
					'label_display' => 'inline',
				),
			)
		);

		$this->add_responsive_control(
			'box_spacing',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array(
					'size' => 10,
				),
				'range'      => array(
					'px'  => array(
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				// Los mismos márgenes que PRO (la mitad a cada lado entre cajas), con propiedades lógicas en lugar de
				// `body.rtl`: en un sitio de derecha a izquierda la caja sigue la dirección del texto.
				'selectors'  => array(
					'{{WRAPPER}} .digi-countdown__item:not(:first-of-type)' => 'margin-inline-start: calc( {{SIZE}}{{UNIT}}/2 );',
					'{{WRAPPER}} .digi-countdown__item:not(:last-of-type)'  => 'margin-inline-end: calc( {{SIZE}}{{UNIT}}/2 );',
				),
			)
		);

		$this->add_control(
			'heading_boxes',
			array(
				'label'     => esc_html__( 'Cajas', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_responsive_control(
			'box_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-countdown__item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'box_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-countdown__item' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'box_border',
				'selector' => '{{WRAPPER}} .digi-countdown__item',
			)
		);

		$this->add_control(
			'box_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-countdown__item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'box_shadow',
				'selector' => '{{WRAPPER}} .digi-countdown__item',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_content_style',
			array(
				'label' => esc_html__( 'Contenido', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading_digits',
			array(
				'label' => esc_html__( 'Dígitos', 'pro-elements' ),
				'type'  => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'digits_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-countdown__digits' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'digits_typography',
				'selector' => '{{WRAPPER}} .digi-countdown__digits',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'digits_text_shadow',
				'selector' => '{{WRAPPER}} .digi-countdown__digits',
			)
		);

		$this->add_control(
			'heading_label',
			array(
				'label'     => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => array(
					'show_labels!' => '',
				),
			)
		);

		$this->add_control(
			'label_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-countdown__label' => 'color: {{VALUE}};',
				),
				'condition' => array(
					'show_labels!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'label_typography',
				'selector'  => '{{WRAPPER}} .digi-countdown__label',
				'global'    => array(
					'default' => Global_Typography::TYPOGRAPHY_SECONDARY,
				),
				'condition' => array(
					'show_labels!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'      => 'label_text_shadow',
				'selector'  => '{{WRAPPER}} .digi-countdown__label',
				'condition' => array(
					'show_labels!' => '',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name'      => 'text_stroke',
				'selector'  => '{{WRAPPER}} .digi-countdown__label',
				'condition' => array(
					'show_labels!' => '',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_expire_message_style',
			array(
				'label'     => esc_html__( 'Mensaje', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'expire_actions' => 'message',
				),
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => self::start_end_options(),
				'classes'              => 'elementor-control-start-end',
				'selectors_dictionary' => array(
					'left'  => is_rtl() ? 'end' : 'start',
					'right' => is_rtl() ? 'start' : 'end',
				),
				'selectors'            => array(
					'{{WRAPPER}} .digi-countdown__message' => 'text-align: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-countdown__message' => 'color: {{VALUE}};',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'selector' => '{{WRAPPER}} .digi-countdown__message',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Shadow::get_type(),
			array(
				'name'     => 'message_text_shadow',
				'selector' => '{{WRAPPER}} .digi-countdown__message',
			)
		);

		$this->add_responsive_control(
			'message_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} .digi-countdown__message' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/** Opciones Inicio / Centro / Fin (las de PRO). */
	private static function start_end_options(): array {
		return array(
			'start'  => array(
				'title' => esc_html__( 'Inicio', 'pro-elements' ),
				'icon'  => 'eicon-text-align-left',
			),
			'center' => array(
				'title' => esc_html__( 'Centro', 'pro-elements' ),
				'icon'  => 'eicon-text-align-center',
			),
			'end'    => array(
				'title' => esc_html__( 'Fin', 'pro-elements' ),
				'icon'  => 'eicon-text-align-right',
			),
		);
	}

	/** Etiquetas por defecto (sin «Etiquetas personalizadas»), por control. */
	public static function default_labels(): array {
		return array(
			'label_days'    => esc_html__( 'Días', 'pro-elements' ),
			'label_hours'   => esc_html__( 'Horas', 'pro-elements' ),
			'label_minutes' => esc_html__( 'Minutos', 'pro-elements' ),
			'label_seconds' => esc_html__( 'Segundos', 'pro-elements' ),
		);
	}

	/**
	 * Etiquetas por defecto de lo convertido de PRO (la marca del traductor, `Translator::MARKER`, que dejan «Migrar» y los
	 * kits, también en lo ya migrado): los textos de PRO con su msgid y su dominio, o la traducción del paquete de idioma
	 * de PRO, en el idioma del front, como `get_default_countdown_labels()`. Lo nuevo sigue con las propias.
	 */
	public static function pro_labels(): array {
		return array(
			'label_days'    => esc_html__( 'Days', 'elementor-pro' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- mismo texto y dominio que PRO en lo convertido.
			'label_hours'   => esc_html__( 'Hours', 'elementor-pro' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- ídem.
			'label_minutes' => esc_html__( 'Minutes', 'elementor-pro' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- ídem.
			'label_seconds' => esc_html__( 'Seconds', 'elementor-pro' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- ídem.
		);
	}

	/** Segundos del temporizador perpetuo (horas y minutos), como PRO. */
	public static function evergreen_interval( array $s ): int {
		$hours   = empty( $s['evergreen_counter_hours'] ) ? 0 : (int) $s['evergreen_counter_hours'] * HOUR_IN_SECONDS;
		$minutes = empty( $s['evergreen_counter_minutes'] ) ? 0 : (int) $s['evergreen_counter_minutes'] * MINUTE_IN_SECONDS;
		return max( 0, $hours + $minutes );
	}

	/** Marca de tiempo de la fecha límite en la zona horaria del sitio (0 si no es una fecha). */
	public static function due_timestamp( $due_date ): int {
		$due_date = is_scalar( $due_date ) ? trim( (string) $due_date ) : '';
		if ( '' === $due_date ) {
			return 0;
		}
		try {
			$zone = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
			return ( new \DateTime( $due_date, $zone ) )->getTimestamp();
		} catch ( \Throwable $error ) {
			return 0;
		}
	}

	/** Días, horas, minutos y segundos de una cuenta en segundos, con las mismas reglas que el script (todo 0 al vencer). */
	public static function parts( int $remaining ): array {
		if ( $remaining <= 0 ) {
			return array( 'days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0 );
		}
		return array(
			'days'    => intdiv( $remaining, DAY_IN_SECONDS ),
			'hours'   => intdiv( $remaining % DAY_IN_SECONDS, HOUR_IN_SECONDS ),
			'minutes' => intdiv( $remaining % HOUR_IN_SECONDS, MINUTE_IN_SECONDS ),
			'seconds' => $remaining % MINUTE_IN_SECONDS,
		);
	}

	/**
	 * Acciones al terminar, saneadas como PRO: tipos conocidos, y la redirección sólo con una URL `http`/`https` válida
	 * (si no, la acción se omite).
	 */
	public static function actions( array $s ): array {
		$out = array();
		foreach ( (array) ( $s['expire_actions'] ?? array() ) as $type ) {
			if ( ! in_array( $type, array( 'redirect', 'hide', 'message' ), true ) ) {
				continue;
			}
			$action = array( 'type' => $type );
			if ( 'redirect' === $type ) {
				$url = trim( (string) ( $s['expire_redirect_url']['url'] ?? '' ) );
				if ( '' === $url || preg_match( '/\bjavascript\b/i', $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) || ! preg_match( '#^https?://#i', $url ) ) {
					continue;
				}
				$action['redirect_url'] = esc_url_raw( $url );
			}
			$out[] = $action;
		}
		return $out;
	}

	/** ¿Se está pintando en el editor? (allí no se envían las acciones, como en PRO). */
	private static function edit_mode(): bool {
		$plugin = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::$instance : null;
		return is_object( $plugin ) && isset( $plugin->editor ) && is_object( $plugin->editor ) && method_exists( $plugin->editor, 'is_edit_mode' ) && $plugin->editor->is_edit_mode();
	}

	protected function render() {
		$s         = $this->get_settings_for_display();
		$evergreen = 'evergreen' === ( $s['countdown_type'] ?? 'due_date' );
		$attrs     = array( 'class' => 'digi-countdown', 'role' => 'timer' );
		if ( $evergreen ) {
			$interval                         = self::evergreen_interval( $s );
			$attrs['data-digi-evergreen']     = (string) $interval;
			$attrs['data-digi-date']          = '0';
			$remaining                        = $interval;
		} else {
			$due                     = self::due_timestamp( $s['due_date'] ?? '' );
			$attrs['data-digi-date'] = (string) $due;
			$remaining               = $due - time();
		}
		$actions = self::edit_mode() ? array() : self::actions( $s );
		if ( $actions ) {
			$attrs['data-digi-actions'] = (string) wp_json_encode( $actions );
		}
		$parts  = self::parts( $remaining );
		$labels = '' !== (string) $this->get_settings( '_digitalisimo_pro' ) ? self::pro_labels() : self::default_labels();
		$items  = '';
		foreach ( array( 'days', 'hours', 'minutes', 'seconds' ) as $part ) {
			if ( empty( $s[ 'show_' . $part ] ) ) {
				continue;
			}
			$value  = (string) $parts[ $part ];
			$items .= '<div class="digi-countdown__item"><span class="digi-countdown__digits digi-countdown__' . $part . '">' . ( 1 === strlen( $value ) ? '0' . $value : $value ) . '</span>';
			if ( ! empty( $s['show_labels'] ) ) {
				$label  = ! empty( $s['custom_labels'] ) ? (string) ( $s[ 'label_' . $part ] ?? '' ) : $labels[ 'label_' . $part ];
				$items .= ' <span class="digi-countdown__label">' . wp_kses_post( $label ) . '</span>';
			}
			$items .= '</div>';
		}
		$html = '';
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
		}
		echo '<div' . $html . '>' . $items . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados y etiquetas con wp_kses_post().
		if ( in_array( 'message', array_column( $actions, 'type' ), true ) ) {
			echo '<div class="digi-countdown__message" hidden>' . esc_html( (string) ( $s['message_after_expire'] ?? '' ) ) . '</div>';
		}
	}

	public function render_markdown(): string {
		$s     = $this->get_settings_for_display();
		$lines = array();
		if ( 'evergreen' === ( $s['countdown_type'] ?? 'due_date' ) ) {
			$lines[] = '- **' . esc_html__( 'Tipo', 'pro-elements' ) . ':** ' . esc_html__( 'Temporizador perpetuo', 'pro-elements' );
			/* translators: 1: horas, 2: minutos. */
			$lines[] = '- **' . esc_html__( 'Intervalo', 'pro-elements' ) . ':** ' . sprintf( esc_html__( '%1$d horas, %2$d minutos', 'pro-elements' ), (int) ( $s['evergreen_counter_hours'] ?? 0 ), (int) ( $s['evergreen_counter_minutes'] ?? 0 ) );
			return implode( "\n", $lines );
		}
		$lines[] = '- **' . esc_html__( 'Tipo', 'pro-elements' ) . ':** ' . esc_html__( 'Fecha límite', 'pro-elements' );
		$due     = self::due_timestamp( $s['due_date'] ?? '' );
		if ( $due && function_exists( 'wp_date' ) ) {
			$lines[] = '- **' . esc_html__( 'Termina', 'pro-elements' ) . ':** ' . wp_date( 'Y-m-d H:i', $due ) . ' (' . self::timezone_label() . ')';
		}
		if ( $due && time() >= $due && in_array( 'message', (array) ( $s['expire_actions'] ?? array() ), true ) ) {
			$message = trim( wp_strip_all_tags( (string) ( $s['message_after_expire'] ?? '' ) ) );
			if ( '' !== $message ) {
				$lines[] = '- **' . esc_html__( 'Mensaje', 'pro-elements' ) . ':** ' . $message;
			}
		}
		return implode( "\n", $lines );
	}
}
