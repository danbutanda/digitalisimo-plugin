<?php
namespace Digitalisimo\Elements\Content\Widgets;

use Digitalisimo\Elements\Content\Cards;
use Digitalisimo\Elements\Content\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Image_Size;
use Elementor\Group_Control_Text_Stroke;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Lista de precios (`digitalisimo-price-list`): sustituye al widget «Price List» de PRO
 * (`modules/pricing/widgets/price-list.php`, mapa `modules/digitalisimo-convert/maps/price-list.php`). Tiene un control
 * por cada control de aquél (también los del repetidor), con el mismo nombre, los mismos valores por defecto y las
 * mismas condiciones; el marcado, las clases y la hoja (`assets/css/price-list.css`, sin JavaScript) son propios.
 * Controles derivados de PRO Elements (GPL); textos en español con el dominio `pro-elements`.
 *
 * - Cada elemento: imagen (con el tamaño del grupo `image_size`, `srcset` y las decisiones de carga de WordPress),
 *   título y precio unidos por el separador, y descripción. Con enlace, todo el elemento es el enlace (como PRO). La
 *   imagen de un elemento con título es decorativa (`alt=""`): el título está al lado y PRO lo repetía como texto
 *   alternativo, así que el enlace se anunciaba con el título dos veces; sin título, lleva el texto de Medios.
 * - El espacio de la imagen usa propiedades lógicas (también en RTL), sin depender de la clase `rtl` del `body`.
 * - Sin plantilla JS del editor: el editor pinta lo mismo que el servidor.
 */
class Price_List extends \Elementor\Widget_Base {
	public function get_name() {
		return 'digitalisimo-price-list';
	}

	public function get_title() {
		return esc_html__( 'Lista de precios', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-price-list';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'lista de precios', 'price list', 'precios', 'menú', 'carta', 'producto', 'imagen' );
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

	protected function register_controls() {

		$this->start_controls_section(
			'section_list',
			array(
				'label' => esc_html__( 'Lista', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			)
		);

		$repeater = new Repeater();

		$repeater->add_control(
			'price',
			array(
				'label' => esc_html__( 'Precio', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'dynamic' => array(
					'active' => true,
				),
				'ai' => array(
					'active' => false,
				),
			)
		);

		$repeater->add_control(
			'title',
			array(
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::TEXT,
				'default' => '',
				'label_block' => 'true',
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'item_description',
			array(
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
				'type' => Controls_Manager::TEXTAREA,
				'default' => '',
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'image',
			array(
				'label' => esc_html__( 'Imagen', 'pro-elements' ),
				'type' => Controls_Manager::MEDIA,
				'default' => array(),
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$repeater->add_control(
			'link',
			array(
				'label' => esc_html__( 'Enlace', 'pro-elements' ),
				'type' => Controls_Manager::URL,
				'default' => array( 'url' => '#' ),
				'dynamic' => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'price_list',
			array(
				'label' => esc_html__( 'Elementos de la lista', 'pro-elements' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => array(
					array(
						'title' => esc_html__( 'Primer elemento de la lista', 'pro-elements' ),
						'item_description' => esc_html__( 'Escribe aquí una descripción breve que acompañe al título.', 'pro-elements' ),
						'price' => '$20',
						'link' => array( 'url' => '#' ),
					),
					array(
						'title' => esc_html__( 'Segundo elemento de la lista', 'pro-elements' ),
						'item_description' => esc_html__( 'Escribe aquí una descripción breve que acompañe al título.', 'pro-elements' ),
						'price' => '$9',
						'link' => array( 'url' => '#' ),
					),
					array(
						'title' => esc_html__( 'Tercer elemento de la lista', 'pro-elements' ),
						'item_description' => esc_html__( 'Escribe aquí una descripción breve que acompañe al título.', 'pro-elements' ),
						'price' => '$32',
						'link' => array( 'url' => '#' ),
					),
				),
				'title_field' => '{{{ title }}}',
			)
		);

		$this->add_control(
			'title_tag',
			array(
				'label' => esc_html__( 'Etiqueta HTML del título', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
					'div' => 'div',
					'span' => 'span',
					'p' => 'p',
				),
				'default' => 'span',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'description_tag',
			array(
				'label' => esc_html__( 'Etiqueta HTML de la descripción', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
					'div' => 'div',
					'span' => 'span',
					'p' => 'p',
				),
				'default' => 'p',
			)
		);

		$this->add_group_control(
			Group_Control_Image_Size::get_type(),
			array(
				'name' => 'image_size',
				'default' => 'thumbnail',
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_list_style',
			array(
				'label' => esc_html__( 'Lista', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'vertical_align',
			array(
				'label' => esc_html__( 'Alineación vertical', 'pro-elements' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => array(
					'top' => array(
						'title' => esc_html__( 'Arriba', 'pro-elements' ),
						'icon' => 'eicon-v-align-top',
					),
					'center' => array(
						'title' => esc_html__( 'Centro', 'pro-elements' ),
						'icon' => 'eicon-v-align-middle',
					),
					'bottom' => array(
						'title' => esc_html__( 'Abajo', 'pro-elements' ),
						'icon' => 'eicon-v-align-bottom',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__item' => 'align-items: {{VALUE}};',
				),
				'selectors_dictionary' => array(
					'top' => 'flex-start',
					'bottom' => 'flex-end',
				),
				'default' => 'top',
			)
		);

		$this->add_responsive_control(
			'row_gap',
			array(
				'label' => esc_html__( 'Espacio entre elementos', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'separator' => 'before',
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 50,
					),
					'em' => array(
						'max' => 5,
					),
					'rem' => array(
						'max' => 5,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list li:not(:last-child)' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
				'default' => array(
					'size' => 20,
				),
			)
		);

		$this->add_responsive_control(
			'image_spacing',
			array(
				'label' => esc_html__( 'Espacio de la imagen', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 100,
					),
					'em' => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__image' => 'padding-inline-end: calc({{SIZE}}{{UNIT}}/2);',
					'{{WRAPPER}} .digi-price-list__image + .digi-price-list__text' => 'padding-inline-start: calc({{SIZE}}{{UNIT}}/2);',
				),
				'default' => array(
					'size' => 20,
				),
			)
		);

		$this->add_responsive_control(
			'content_spacing',
			array(
				'label' => esc_html__( 'Espacio del contenido', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 100,
					),
					'em' => array(
						'max' => 10,
					),
					'rem' => array(
						'max' => 10,
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__header' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_content_style',
			array(
				'label' => esc_html__( 'Contenido', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'heading__title',
			array(
				'label' => esc_html__( 'Título', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__header' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'heading_typography',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .digi-price-list__header',
			)
		);

		$this->add_group_control(
			Group_Control_Text_Stroke::get_type(),
			array(
				'name' => 'text_stroke',
				'selector' => '{{WRAPPER}} .digi-price-list__header',
			)
		);

		$this->add_control(
			'price_title',
			array(
				'label' => esc_html__( 'Precio', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'price_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_PRIMARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__price' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'price_typography',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_PRIMARY,
				),
				'selector' => '{{WRAPPER}} .digi-price-list__price',
			)
		);

		$this->add_control(
			'heading_item_description',
			array(
				'label' => esc_html__( 'Descripción', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'description_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__description' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name' => 'description_typography',
				'global' => array(
					'default' => Global_Typography::TYPOGRAPHY_TEXT,
				),
				'selector' => '{{WRAPPER}} .digi-price-list__description',
			)
		);

		$this->add_control(
			'heading_separator',
			array(
				'label' => esc_html__( 'Separador', 'pro-elements' ),
				'type' => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'separator_style',
			array(
				'label' => esc_html__( 'Estilo', 'pro-elements' ),
				'type' => Controls_Manager::SELECT,
				'options' => array(
					'none' => esc_html__( 'Ninguno', 'pro-elements' ),
					'solid' => esc_html__( 'Continuo', 'pro-elements' ),
					'dotted' => esc_html__( 'Punteado', 'pro-elements' ),
					'dashed' => esc_html__( 'Discontinuo', 'pro-elements' ),
					'double' => esc_html__( 'Doble', 'pro-elements' ),
				),
				'default' => 'dotted',
				'render_type' => 'template',
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__separator' => 'border-bottom-style: {{VALUE}}',
				),
			)
		);

		$this->add_control(
			'separator_weight',
			array(
				'label' => esc_html__( 'Grosor', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 10,
					),
					'em' => array(
						'max' => 1,
					),
					'rem' => array(
						'max' => 1,
					),
				),
				'condition' => array(
					'separator_style!' => 'none',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__separator' => 'border-bottom-width: {{SIZE}}{{UNIT}}',
				),
				'default' => array(
					'size' => 2,
				),
			)
		);

		$this->add_control(
			'separator_color',
			array(
				'label' => esc_html__( 'Color', 'pro-elements' ),
				'type' => Controls_Manager::COLOR,
				'global' => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__separator' => 'border-bottom-color: {{VALUE}};',
				),
				'condition' => array(
					'separator_style!' => 'none',
				),
			)
		);

		$this->add_control(
			'separator_spacing',
			array(
				'label' => esc_html__( 'Espacio', 'pro-elements' ),
				'type' => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range' => array(
					'px' => array(
						'max' => 40,
					),
					'em' => array(
						'max' => 4,
					),
					'rem' => array(
						'max' => 4,
					),
				),
				'condition' => array(
					'separator_style!' => 'none',
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__separator' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_image_style',
			array(
				'label' => esc_html__( 'Imagen', 'pro-elements' ),
				'tab' => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'border_radius',
			array(
				'label' => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors' => array(
					'{{WRAPPER}} .digi-price-list__image img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
		}

	protected function render() {
		$settings  = $this->get_settings_for_display();
		$items     = is_array( $settings['price_list'] ?? null ) ? $settings['price_list'] : array();
		$title_tag = Cards::tag( $settings['title_tag'] ?? 'span', 'span' );
		$desc_tag  = Cards::tag( $settings['description_tag'] ?? 'p', 'p' );
		$separator = 'none' !== ( $settings['separator_style'] ?? 'dotted' );
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos con Cards::attributes(), textos con wp_kses_post() e imagen de Image_Markup.
		echo '<ul class="digi-price-list">';
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$title = (string) ( $item['title'] ?? '' );
			$price = (string) ( $item['price'] ?? '' );
			$desc  = (string) ( $item['item_description'] ?? '' );
			if ( '' === $title && '' === $price && '' === $desc ) {
				continue;
			}
			$repeater = 'elementor-repeater-item-' . sanitize_html_class( (string) ( $item['_id'] ?? '' ) );
			$link     = Cards::link( $item['link'] ?? array() );
			if ( $link ) {
				echo '<li class="' . $repeater . '"><a ' . Cards::attributes( array( 'class' => 'digi-price-list__item' ) + $link ) . '>';
			} else {
				echo '<li class="digi-price-list__item ' . $repeater . '">';
			}
			$image = (array) ( $item['image'] ?? array() );
			if ( '' !== (string) ( $image['url'] ?? '' ) ) {
				$img = Cards::image( $image, $settings, 'image_size', array( 'decorative' => '' !== Cards::plain( $title ) ) );
				if ( '' !== $img ) {
					echo '<div class="digi-price-list__image">' . $img . '</div>';
				}
			}
			echo '<div class="digi-price-list__text">';
			if ( '' !== $title || '' !== $price ) {
				echo '<div class="digi-price-list__header">';
				if ( '' !== $title ) {
					echo '<' . $title_tag . ' class="digi-price-list__title">' . wp_kses_post( $title ) . '</' . $title_tag . '>';
				}
				if ( $separator ) {
					echo '<span class="digi-price-list__separator"></span>';
				}
				if ( '' !== $price ) {
					echo '<span class="digi-price-list__price">' . wp_kses_post( $price ) . '</span>';
				}
				echo '</div>';
			}
			if ( '' !== $desc ) {
				echo '<' . $desc_tag . ' class="digi-price-list__description">' . wp_kses_post( $desc ) . '</' . $desc_tag . '>';
			}
			echo '</div>';
			echo $link ? '</a></li>' : '</li>';
		}
		echo '</ul>';
		// phpcs:enable
	}

	/** Markdown como el de PRO: un elemento por línea con su enlace, su precio y su descripción. */
	public function render_markdown(): string {
		$settings = $this->get_settings_for_display();
		$lines    = array();
		foreach ( (array) ( $settings['price_list'] ?? array() ) as $item ) {
			$title = Cards::plain( $item['title'] ?? '' );
			$price = Cards::plain( $item['price'] ?? '' );
			$desc  = Cards::plain( $item['item_description'] ?? '' );
			$url   = (string) ( $item['link']['url'] ?? '' );
			if ( '' === $title && '' === $price && '' === $desc ) {
				continue;
			}
			$label   = '**' . $title . '**';
			$label   = '' !== $url ? '[' . $label . '](' . esc_url( $url ) . ')' : $label;
			$lines[] = '- ' . $label . ( '' !== $price ? ' — *' . $price . '*' : '' );
			if ( '' !== $desc ) {
				$lines[] = '  ' . $desc;
			}
		}
		return implode( "\n", $lines );
	}
}
