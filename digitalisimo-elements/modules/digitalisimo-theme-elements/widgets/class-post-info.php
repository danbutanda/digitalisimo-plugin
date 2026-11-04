<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Información de la entrada (`digitalisimo-post-info`): sustituye al widget «Post Info» de PRO
 * (`modules/theme-elements/widgets/post-info.php`, mapa `modules/digitalisimo-convert/maps/post-info.php`). Repite sus
 * controles con el mismo nombre, valor por defecto, opciones y condiciones (también los del repetidor `icon_list`); el
 * marcado, las clases y la hoja son propios, sin script y sin la hoja de lista de iconos del núcleo.
 *
 * Mismo dato que PRO, de las mismas funciones de WordPress: autor (`get_the_author_meta()`, su archivo y su avatar de
 * 96 px), fecha y hora (`get_the_time()` con los mismos formatos, «Predeterminado» = `F j, Y` y `g:i a`, como PRO) y el
 * archivo del día, comentarios (sólo con los comentarios abiertos, `get_comments_number()` y `get_comments_link()`),
 * términos (`wp_get_post_terms()` de la taxonomía elegida) y texto personalizado con su enlace.
 *
 * Marcado: `<ul class="digi-post-info">` (con `--inline` en la disposición en línea) y un `<li>` por dato con
 * `elementor-repeater-item-<id>` (la clase que el núcleo usa para `{{CURRENT_ITEM}}`), enlace opcional, icono o avatar
 * (`digi-post-info__icon`) y texto (`digi-post-info__text`, con `--type-<tipo>` como la clase de PRO). Iconos de
 * `Icons_Manager`, decorativos.
 *
 * Diferencias declaradas con PRO: sin microdatos (`itemprop` sin `itemscope` no describía nada; las señales de schema son
 * de DIGITALÍSIMO SEO); `<time>` con `datetime`; el texto «Antes» se escapa una vez (PRO lo escapaba dos veces y pintaba
 * `&amp;` donde se escribió `&`); el texto alternativo del avatar y los textos de comentarios por defecto, en español
 * (el mapa conserva los de PRO en lo convertido); foco visible propio; la alineación «Inicio» y «Fin» es la de PRO (sólo
 * `text-align`: con la hoja actual del núcleo PRO no movía los elementos) y «Centro» los centra, como PRO; el divisor en
 * línea usa el borde lógico de inicio (PRO mezclaba el izquierdo y el lógico en RTL).
 */
class Post_Info extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-post-info';
	}

	public function get_title() {
		return esc_html__( 'Información de la entrada', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-post-info';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'información', 'entrada', 'fecha', 'hora', 'autor', 'taxonomía', 'comentarios', 'términos', 'avatar', 'post', 'info', 'date', 'time', 'author', 'meta' );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	/**
	 * Su hoja y, como PRO, las de Font Awesome regular y sólida que usan los iconos por defecto, pero sólo cuando los
	 * iconos se pintan con la fuente (sin el experimento de iconos en línea del núcleo).
	 */
	public function get_style_depends(): array {
		$deps = Module::style_depends( $this->get_name() );
		if ( class_exists( Icons_Manager::class ) && method_exists( Icons_Manager::class, 'is_font_icon_inline_svg' ) && ! Icons_Manager::is_font_icon_inline_svg() && Icons_Manager::is_migration_allowed() ) {
			$deps[] = 'elementor-icons-fa-regular';
			$deps[] = 'elementor-icons-fa-solid';
		}
		return $deps;
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_icon',
			array(
				'label' => esc_html__( 'Metadatos', 'pro-elements' ),
			)
		);

		$this->add_control(
			'view',
			array(
				'label'       => esc_html__( 'Disposición', 'pro-elements' ),
				'type'        => Controls_Manager::CHOOSE,
				'default'     => 'inline',
				'options'     => array(
					'traditional' => array(
						'title' => esc_html__( 'Predeterminada', 'pro-elements' ),
						'icon'  => 'eicon-editor-list-ul',
					),
					'inline'      => array(
						'title' => esc_html__( 'En línea', 'pro-elements' ),
						'icon'  => 'eicon-ellipsis-h',
					),
				),
				'render_type' => 'template',
				'classes'     => 'elementor-control-start-end',
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'type',
			array(
				'label'   => esc_html__( 'Tipo', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'date',
				'options' => array(
					'author'   => esc_html__( 'Autor', 'pro-elements' ),
					'date'     => esc_html__( 'Fecha', 'pro-elements' ),
					'time'     => esc_html__( 'Hora', 'pro-elements' ),
					'comments' => esc_html__( 'Comentarios', 'pro-elements' ),
					'terms'    => esc_html__( 'Términos', 'pro-elements' ),
					'custom'   => esc_html__( 'Personalizado', 'pro-elements' ),
				),
			)
		);

		$repeater->add_control(
			'date_format',
			array(
				'label'     => esc_html__( 'Formato de fecha', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'default',
				'options'   => array(
					'default' => esc_html__( 'Predeterminado', 'pro-elements' ),
					'0'       => esc_html_x( '6 de marzo de 2018 (F j, Y)', 'Date Format', 'pro-elements' ),
					'1'       => '2018-03-06 (Y-m-d)',
					'2'       => '03/06/2018 (m/d/Y)',
					'3'       => '06/03/2018 (d/m/Y)',
					'custom'  => esc_html__( 'Personalizado', 'pro-elements' ),
				),
				'condition' => array(
					'type' => 'date',
				),
			)
		);

		$repeater->add_control(
			'custom_date_format',
			array(
				'label'       => esc_html__( 'Formato de fecha personalizado', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'F j, Y',
				'condition'   => array(
					'type'        => 'date',
					'date_format' => 'custom',
				),
				'description' => sprintf(
					/* translators: %s: letras de formato de fecha permitidas. */
					esc_html__( 'Usa las letras: %s', 'pro-elements' ),
					'l D d j S F m M n Y y'
				),
				'ai'          => array(
					'active' => false,
				),
			)
		);

		$repeater->add_control(
			'time_format',
			array(
				'label'     => esc_html__( 'Formato de hora', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'default',
				'options'   => array(
					'default' => esc_html__( 'Predeterminado', 'pro-elements' ),
					'0'       => '3:31 pm (g:i a)',
					'1'       => '3:31 PM (g:i A)',
					'2'       => '15:31 (H:i)',
					'custom'  => esc_html__( 'Personalizado', 'pro-elements' ),
				),
				'condition' => array(
					'type' => 'time',
				),
			)
		);
		$repeater->add_control(
			'custom_time_format',
			array(
				'label'       => esc_html__( 'Formato de hora personalizado', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => 'g:i a',
				'placeholder' => 'g:i a',
				'condition'   => array(
					'type'        => 'time',
					'time_format' => 'custom',
				),
				'description' => sprintf(
					/* translators: %s: letras de formato de hora permitidas. */
					esc_html__( 'Usa las letras: %s', 'pro-elements' ),
					'g G H i a A'
				),
				'ai'          => array(
					'active' => false,
				),
			)
		);

		$repeater->add_control(
			'taxonomy',
			array(
				'label'       => esc_html__( 'Taxonomía', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT2,
				'label_block' => true,
				'default'     => array(),
				'options'     => $this->get_taxonomies(),
				'condition'   => array(
					'type' => 'terms',
				),
			)
		);

		$repeater->add_control(
			'text_prefix',
			array(
				'label'     => esc_html__( 'Antes', 'pro-elements' ),
				'type'      => Controls_Manager::TEXT,
				'condition' => array(
					'type!' => 'custom',
				),
				'dynamic'   => array(
					'active' => true,
				),
				'ai'        => array(
					'active' => false,
				),
			)
		);

		$repeater->add_control(
			'show_avatar',
			array(
				'label'     => esc_html__( 'Avatar', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'condition' => array(
					'type' => 'author',
				),
			)
		);

		$repeater->add_responsive_control(
			'avatar_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					'{{WRAPPER}} {{CURRENT_ITEM}} .digi-post-info__icon' => 'width: {{SIZE}}{{UNIT}}',
				),
				'condition'  => array(
					'show_avatar' => 'yes',
				),
			)
		);

		$repeater->add_control(
			'comments_custom_strings',
			array(
				'label'     => esc_html__( 'Formato personalizado', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => false,
				'condition' => array(
					'type' => 'comments',
				),
			)
		);

		$repeater->add_control(
			'string_no_comments',
			array(
				'label'       => esc_html__( 'Sin comentarios', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Sin comentarios', 'pro-elements' ),
				'condition'   => array(
					'comments_custom_strings' => 'yes',
					'type'                    => 'comments',
				),
			)
		);

		$repeater->add_control(
			'string_one_comment',
			array(
				'label'       => esc_html__( 'Un comentario', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html__( 'Un comentario', 'pro-elements' ),
				'condition'   => array(
					'comments_custom_strings' => 'yes',
					'type'                    => 'comments',
				),
			)
		);

		$repeater->add_control(
			'string_comments',
			array(
				'label'       => esc_html__( 'Comentarios', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'placeholder' => esc_html(
					/* translators: %s: número de comentarios. */
					__( '%s comentarios', 'pro-elements' )
				),
				'condition'   => array(
					'comments_custom_strings' => 'yes',
					'type'                    => 'comments',
				),
			)
		);

		$repeater->add_control(
			'custom_text',
			array(
				'label'       => esc_html__( 'Personalizado', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'dynamic'     => array(
					'active' => true,
				),
				'label_block' => true,
				'condition'   => array(
					'type' => 'custom',
				),
			)
		);

		$repeater->add_control(
			'link',
			array(
				'label'     => esc_html__( 'Enlace', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'default'   => 'yes',
				'condition' => array(
					'type!' => 'time',
				),
			)
		);

		$repeater->add_control(
			'custom_url',
			array(
				'label'     => esc_html__( 'URL personalizada', 'pro-elements' ),
				'type'      => Controls_Manager::URL,
				'dynamic'   => array(
					'active' => true,
				),
				'condition' => array(
					'type' => 'custom',
				),
			)
		);

		$repeater->add_control(
			'show_icon',
			array(
				'label'     => esc_html__( 'Icono', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'none'    => esc_html__( 'Ninguno', 'pro-elements' ),
					'default' => esc_html__( 'Predeterminado', 'pro-elements' ),
					'custom'  => esc_html__( 'Personalizado', 'pro-elements' ),
				),
				'default'   => 'default',
				'condition' => array(
					'show_avatar!' => 'yes',
				),
			)
		);

		$repeater->add_control(
			'selected_icon',
			array(
				'label'            => esc_html__( 'Elegir icono', 'pro-elements' ),
				'type'             => Controls_Manager::ICONS,
				'fa4compatibility' => 'icon',
				'condition'        => array(
					'show_icon'    => 'custom',
					'show_avatar!' => 'yes',
				),
			)
		);

		$this->add_control(
			'icon_list',
			array(
				'label'       => '',
				'type'        => Controls_Manager::REPEATER,
				'fields'      => $repeater->get_controls(),
				'default'     => array(
					array(
						'type'          => 'author',
						'selected_icon' => array(
							'value'   => 'far fa-user-circle',
							'library' => 'fa-regular',
						),
					),
					array(
						'type'          => 'date',
						'selected_icon' => array(
							'value'   => 'fas fa-calendar',
							'library' => 'fa-solid',
						),
					),
					array(
						'type'          => 'time',
						'selected_icon' => array(
							'value'   => 'far fa-clock',
							'library' => 'fa-regular',
						),
					),
					array(
						'type'          => 'comments',
						'selected_icon' => array(
							'value'   => 'far fa-comment-dots',
							'library' => 'fa-regular',
						),
					),
				),
				'title_field' => '{{{ elementor.helpers.renderIcon( this, selected_icon, {}, "i", "panel" ) || \'<i class="{{ icon }}" aria-hidden="true"></i>\' }}} <span style="text-transform: capitalize;">{{{ type }}}</span>',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_icon_list',
			array(
				'label' => esc_html__( 'Lista', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'space_between',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
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
				'selectors'  => array(
					'{{WRAPPER}} .digi-post-info:not(.digi-post-info--inline) .digi-post-info__item:not(:last-child)' => 'padding-bottom: calc({{SIZE}}{{UNIT}}/2)',
					'{{WRAPPER}} .digi-post-info:not(.digi-post-info--inline) .digi-post-info__item:not(:first-child)' => 'margin-top: calc({{SIZE}}{{UNIT}}/2)',
					'{{WRAPPER}} .digi-post-info--inline .digi-post-info__item' => 'margin-right: calc({{SIZE}}{{UNIT}}/2); margin-left: calc({{SIZE}}{{UNIT}}/2)',
					'{{WRAPPER}} .digi-post-info--inline' => 'margin-right: calc(-{{SIZE}}{{UNIT}}/2); margin-left: calc(-{{SIZE}}{{UNIT}}/2)',
					'{{WRAPPER}} .digi-post-info--inline .digi-post-info__item:after' => 'inset-inline-end: calc(-{{SIZE}}{{UNIT}}/2)',
				),
			)
		);

		$this->add_responsive_control(
			'icon_align',
			array(
				'label'                => esc_html__( 'Alineación', 'pro-elements' ),
				'type'                 => Controls_Manager::CHOOSE,
				'options'              => array(
					'left'   => array(
						'title' => esc_html__( 'Inicio', 'pro-elements' ),
						'icon'  => 'eicon-h-align-left',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon'  => 'eicon-h-align-center',
					),
					'right'  => array(
						'title' => esc_html__( 'Fin', 'pro-elements' ),
						'icon'  => 'eicon-h-align-right',
					),
				),
				'prefix_class'         => 'digi-post-info%s--align-',
				// Cada valor fija las dos propiedades: un dispositivo nunca hereda las de otro.
				'selectors_dictionary' => array(
					'left'   => 'text-align: left; --digi-pi-justify: flex-start',
					'center' => 'text-align: center; --digi-pi-justify: center',
					'right'  => 'text-align: right; --digi-pi-justify: flex-start',
				),
				'selectors'            => array(
					'{{WRAPPER}}' => '{{VALUE}}',
				),
			)
		);

		$this->add_control(
			'divider',
			array(
				'label'     => esc_html__( 'Divisor', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_off' => esc_html__( 'No', 'pro-elements' ),
				'label_on'  => esc_html__( 'Sí', 'pro-elements' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-post-info__item:not(:last-child):after' => 'content: ""',
				),
				'separator' => 'before',
			)
		);

		$this->add_control(
			'divider_style',
			array(
				'label'     => esc_html__( 'Estilo', 'pro-elements' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array(
					'solid'  => esc_html__( 'Continuo', 'pro-elements' ),
					'double' => esc_html__( 'Doble', 'pro-elements' ),
					'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
					'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
				),
				'default'   => 'solid',
				'condition' => array(
					'divider' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-post-info:not(.digi-post-info--inline) .digi-post-info__item:not(:last-child):after' => 'border-top-style: {{VALUE}};',
					'{{WRAPPER}} .digi-post-info--inline .digi-post-info__item:not(:last-child):after' => 'border-inline-start-style: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'divider_weight',
			array(
				'label'     => esc_html__( 'Grosor', 'pro-elements' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => array(
					'size' => 1,
				),
				'range'     => array(
					'px'  => array(
						'min' => 1,
						'max' => 20,
					),
					'em'  => array(
						'max' => 2,
					),
					'rem' => array(
						'max' => 2,
					),
				),
				'condition' => array(
					'divider' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-post-info:not(.digi-post-info--inline) .digi-post-info__item:not(:last-child):after' => 'border-top-width: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-post-info--inline .digi-post-info__item:not(:last-child):after' => 'border-inline-start-width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'divider_width',
			array(
				'label'      => esc_html__( 'Ancho', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'default'    => array(
					'unit' => '%',
				),
				'range'      => array(
					'px'  => array(
						'min' => 1,
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
					'%'   => array(
						'min' => 1,
						'max' => 100,
					),
				),
				'condition'  => array(
					'divider' => 'yes',
					'view!'   => 'inline',
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-post-info__item:not(:last-child):after' => 'width: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'divider_height',
			array(
				'label'      => esc_html__( 'Altura', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'vh', 'custom' ),
				'default'    => array(
					'unit' => '%',
				),
				'range'      => array(
					'px'  => array(
						'min' => 1,
						'max' => 100,
					),
					'em'  => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
					'%'   => array(
						'min' => 1,
						'max' => 100,
					),
				),
				'condition'  => array(
					'divider' => 'yes',
					'view'    => 'inline',
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-post-info__item:not(:last-child):after' => 'height: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_control(
			'divider_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ddd',
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'condition' => array(
					'divider' => 'yes',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-post-info__item:not(:last-child):after' => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_icon_style',
			array(
				'label' => esc_html__( 'Icono', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'icon_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '',
				'selectors' => array(
					'{{WRAPPER}} .digi-post-info__icon i' => 'color: {{VALUE}};',
					'{{WRAPPER}} .digi-post-info__icon svg' => 'fill: {{VALUE}};',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
			)
		);

		$this->add_responsive_control(
			'icon_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array(
					'size' => 14,
				),
				'range'      => array(
					'px'  => array(
						'min' => 6,
					),
					'em'  => array(
						'max' => 0.6,
					),
					'rem' => array(
						'max' => 0.6,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-post-info__icon' => 'width: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .digi-post-info__icon i' => 'font-size: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .digi-post-info__icon svg' => '--digi-pi-icon: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_text_style',
			array(
				'label' => esc_html__( 'Texto', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'text_indent',
			array(
				'label'      => esc_html__( 'Sangría', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'max' => 50,
					),
					'em'  => array(
						'max' => 5,
					),
					'rem' => array(
						'max' => 5,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-post-info__text' => 'padding-inline-start: {{SIZE}}{{UNIT}}',
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
					'{{WRAPPER}} .digi-post-info__text, {{WRAPPER}} .digi-post-info__text a' => 'color: {{VALUE}}',
				),
				'global'    => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'icon_typography',
				'selector' => '{{WRAPPER}} .digi-post-info__item',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
			)
		);

		$this->end_controls_section();
	}

	/** Taxonomías que se muestran en los menús, como PRO (`show_in_nav_menus`). */
	protected function get_taxonomies(): array {
		$options = array(
			'' => esc_html__( 'Elegir', 'pro-elements' ),
		);
		if ( ! function_exists( 'get_taxonomies' ) ) {
			return $options;
		}
		foreach ( get_taxonomies( array( 'show_in_nav_menus' => true ), 'objects' ) as $taxonomy ) {
			$options[ $taxonomy->name ] = $taxonomy->label;
		}
		return $options;
	}

	/** Textos por defecto de los comentarios (en el idioma del sitio, con el dominio de los widgets propios). */
	public static function comment_strings(): array {
		return array(
			'string_no_comments' => esc_html__( 'Sin comentarios', 'pro-elements' ),
			'string_one_comment' => esc_html__( 'Un comentario', 'pro-elements' ),
			/* translators: %s: número de comentarios. */
			'string_comments'    => esc_html( __( '%s comentarios', 'pro-elements' ) ),
		);
	}

	/**
	 * Datos de una fila con las mismas funciones y reglas que PRO: texto, enlace, icono por defecto del tipo, avatar y
	 * lista de términos.
	 */
	protected function get_meta_data( array $item ): array {
		$data = array();
		$type = (string) ( $item['type'] ?? 'date' );
		$link = 'yes' === ( $item['link'] ?? '' );
		switch ( $type ) {
			case 'author':
				$data['text']          = get_the_author_meta( 'display_name' );
				$data['icon']          = 'fa fa-user-circle-o';
				$data['selected_icon'] = array(
					'value'   => 'far fa-user-circle',
					'library' => 'fa-regular',
				);
				if ( $link ) {
					$data['url'] = array( 'url' => get_author_posts_url( get_the_author_meta( 'ID' ) ) );
				}
				if ( 'yes' === ( $item['show_avatar'] ?? '' ) ) {
					$data['image'] = get_avatar_url( get_the_author_meta( 'ID' ), 96 );
				}
				break;

			case 'date':
				$custom  = empty( $item['custom_date_format'] ) ? 'F j, Y' : $item['custom_date_format'];
				$formats = array(
					'default' => 'F j, Y',
					'0'       => 'F j, Y',
					'1'       => 'Y-m-d',
					'2'       => 'm/d/Y',
					'3'       => 'd/m/Y',
					'custom'  => $custom,
				);
				$format                = $formats[ (string) ( $item['date_format'] ?? 'default' ) ] ?? 'F j, Y';
				$data['text']          = get_the_time( $format );
				$data['datetime']      = get_the_time( 'c' );
				$data['icon']          = 'fa fa-calendar';
				$data['selected_icon'] = array(
					'value'   => 'fas fa-calendar',
					'library' => 'fa-solid',
				);
				if ( $link ) {
					$data['url'] = array( 'url' => get_day_link( get_post_time( 'Y' ), get_post_time( 'm' ), get_post_time( 'j' ) ) );
				}
				break;

			case 'time':
				$custom  = empty( $item['custom_time_format'] ) ? 'g:i a' : $item['custom_time_format'];
				$formats = array(
					'default' => 'g:i a',
					'0'       => 'g:i a',
					'1'       => 'g:i A',
					'2'       => 'H:i',
					'custom'  => $custom,
				);
				$format                = $formats[ (string) ( $item['time_format'] ?? 'default' ) ] ?? 'g:i a';
				$data['text']          = get_the_time( $format );
				$data['datetime']      = get_the_time( 'c' );
				$data['icon']          = 'fa fa-clock-o';
				$data['selected_icon'] = array(
					'value'   => 'far fa-clock',
					'library' => 'fa-regular',
				);
				break;

			case 'comments':
				if ( comments_open() ) {
					$strings = self::comment_strings();
					if ( 'yes' === ( $item['comments_custom_strings'] ?? '' ) ) {
						foreach ( array_keys( $strings ) as $key ) {
							if ( ! empty( $item[ $key ] ) ) {
								$strings[ $key ] = (string) $item[ $key ];
							}
						}
					}
					$count = (int) get_comments_number();
					if ( 0 === $count ) {
						$data['text'] = $strings['string_no_comments'];
					} else {
						// Como PRO: el texto elegido decide el singular y el plural, con el número en `%s`.
						$data['text'] = sprintf( 1 === $count ? $strings['string_one_comment'] : $strings['string_comments'], $count );
					}
					if ( $link ) {
						$data['url'] = array( 'url' => get_comments_link() );
					}
					$data['icon']          = 'fa fa-commenting-o';
					$data['selected_icon'] = array(
						'value'   => 'far fa-comment-dots',
						'library' => 'fa-regular',
					);
				}
				break;

			case 'terms':
				$data['icon']          = 'fa fa-tags';
				$data['selected_icon'] = array(
					'value'   => 'fas fa-tags',
					'library' => 'fa-solid',
				);
				$taxonomy = $item['taxonomy'] ?? array();
				$terms    = ( is_string( $taxonomy ) && '' !== $taxonomy ) || ( is_array( $taxonomy ) && $taxonomy ) ? wp_get_post_terms( get_the_ID(), $taxonomy ) : array();
				foreach ( is_array( $terms ) ? $terms : array() as $term ) {
					$data['terms_list'][ $term->term_id ]['text'] = $term->name;
					if ( $link ) {
						$url = get_term_link( $term );
						$data['terms_list'][ $term->term_id ]['url'] = is_string( $url ) ? $url : '';
					}
				}
				break;

			case 'custom':
				$data['text']          = $item['custom_text'] ?? '';
				$data['icon']          = 'fa fa-info-circle';
				$data['selected_icon'] = array(
					'value'   => 'far fa-tags',
					'library' => 'fa-regular',
				);
				if ( $link && ! empty( $item['custom_url'] ) ) {
					$data['url'] = $item['custom_url'];
				}
				break;
		}

		$data['type'] = $type;
		if ( ! empty( $item['text_prefix'] ) ) {
			$data['text_prefix'] = (string) $item['text_prefix'];
		}
		return $data;
	}

	/** Icono o avatar de la fila, con las mismas reglas que PRO (icono por defecto, personalizado o ninguno). */
	protected function render_icon_or_image( array $data, array $item, string $key ): string {
		$show = (string) ( $item['show_icon'] ?? 'default' );
		if ( 'custom' === $show && ! empty( $item['selected_icon'] ) ) {
			$data['selected_icon'] = $item['selected_icon'];
		} elseif ( 'none' === $show ) {
			$data['selected_icon'] = array();
		}
		$show_icon = 'none' !== $show;
		if ( empty( $data['image'] ) && ( ! $show_icon || ( empty( $data['selected_icon'] ) && empty( $data['icon'] ) ) ) ) {
			return '';
		}
		$inner = '';
		if ( ! empty( $data['image'] ) ) {
			$this->add_render_attribute(
				'image_' . $key,
				array(
					'class'    => 'digi-post-info__avatar',
					'src'      => $data['image'],
					/* translators: %s: nombre de la persona. */
					'alt'      => sprintf( esc_attr__( 'Imagen de %s', 'pro-elements' ), wp_strip_all_tags( (string) ( $data['text'] ?? '' ) ) ),
					'width'    => '96',
					'height'   => '96',
					'loading'  => 'lazy',
					'decoding' => 'async',
				)
			);
			$inner = '<img ' . $this->get_render_attribute_string( 'image_' . $key ) . '>';
		} elseif ( empty( $item['icon'] ) || isset( $item['__fa4_migrated']['selected_icon'] ) ) {
			$inner = ! empty( $data['selected_icon']['value'] ) ? (string) Icons_Manager::try_get_icon_html( $data['selected_icon'], array( 'aria-hidden' => 'true' ) ) : '';
		} else {
			// Una fila guardada con el icono de Font Awesome 4 y sin migrar, como PRO.
			$inner = '<i class="' . esc_attr( (string) $item['icon'] ) . '" aria-hidden="true"></i>';
		}
		return '<span class="digi-post-info__icon">' . $inner . '</span>';
	}

	/** Texto de la fila: prefijo, términos enlazados o el dato (fecha y hora en `<time>`). */
	protected function render_text( array $data ): string {
		$out = '<span class="digi-post-info__text digi-post-info__text--type-' . esc_attr( $data['type'] ) . '">';
		if ( ! empty( $data['text_prefix'] ) ) {
			$out .= '<span class="digi-post-info__prefix">' . esc_html( $data['text_prefix'] ) . '</span> ';
		}
		if ( ! empty( $data['terms_list'] ) ) {
			$terms = array();
			foreach ( $data['terms_list'] as $term ) {
				$terms[] = ! empty( $term['url'] )
					? '<a href="' . esc_url( $term['url'] ) . '" class="digi-post-info__term">' . esc_html( $term['text'] ) . '</a>'
					: '<span class="digi-post-info__term">' . esc_html( $term['text'] ) . '</span>';
			}
			$out .= '<span class="digi-post-info__terms">' . implode( ', ', $terms ) . '</span>';
		} else {
			$text = wp_kses(
				(string) $data['text'],
				array(
					'a' => array(
						'href'  => array(),
						'title' => array(),
						'rel'   => array(),
					),
				)
			);
			if ( 'date' === $data['type'] || 'time' === $data['type'] ) {
				$text = '<time' . ( ! empty( $data['datetime'] ) ? ' datetime="' . esc_attr( $data['datetime'] ) . '"' : '' ) . '>' . $text . '</time>';
			}
			$out .= $text;
		}
		return $out . '</span>';
	}

	/** Una fila: nada si no hay dato (los comentarios cerrados, unos términos vacíos). */
	protected function render_item( array $item, string $view ): string {
		$data = $this->get_meta_data( $item );
		if ( empty( $data['text'] ) && empty( $data['terms_list'] ) ) {
			return '';
		}
		$key = (string) ( $item['_id'] ?? md5( serialize( $item ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		$this->add_render_attribute( 'item_' . $key, 'class', array( 'digi-post-info__item', 'elementor-repeater-item-' . $key ) );
		$inner = $this->render_icon_or_image( $data, $item, $key ) . $this->render_text( $data );
		if ( ! empty( $data['url']['url'] ) ) {
			$this->add_render_attribute( 'link_' . $key, 'class', 'digi-post-info__link' );
			$this->add_link_attributes( 'link_' . $key, $data['url'] );
			$inner = '<a ' . $this->get_render_attribute_string( 'link_' . $key ) . '>' . $inner . '</a>';
		}
		return '<li ' . $this->get_render_attribute_string( 'item_' . $key ) . '>' . $inner . '</li>';
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$view     = (string) ( $settings['view'] ?? 'inline' );
		$items    = '';
		foreach ( is_array( $settings['icon_list'] ?? null ) ? $settings['icon_list'] : array() as $item ) {
			if ( is_array( $item ) ) {
				$items .= $this->render_item( $item, $view );
			}
		}
		if ( '' === $items ) {
			return;
		}
		echo '<ul class="digi-post-info' . ( 'inline' === $view ? ' digi-post-info--inline' : '' ) . '">' . $items . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapó al armarla.
	}

	/** Como PRO: una lista con un renglón por dato (los términos separados por comas) y su enlace. */
	public function render_markdown(): string {
		$settings = $this->get_settings_for_display();
		$lines    = array();
		foreach ( is_array( $settings['icon_list'] ?? null ) ? $settings['icon_list'] : array() as $item ) {
			$data = $this->get_meta_data( (array) $item );
			$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) ( $data['text'] ?? '' ) ) ) );
			$url  = (string) ( $data['url']['url'] ?? '' );
			if ( ! empty( $data['terms_list'] ) ) {
				$text = implode( ', ', array_filter( array_column( $data['terms_list'], 'text' ) ) );
			}
			if ( '' === $text ) {
				continue;
			}
			$lines[] = '' !== $url ? '- [' . $text . '](' . esc_url( $url ) . ')' : '- ' . $text;
		}
		return implode( "\n", $lines );
	}
}
