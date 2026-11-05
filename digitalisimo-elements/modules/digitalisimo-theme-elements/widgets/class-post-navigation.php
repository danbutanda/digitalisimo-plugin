<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Navegación entre entradas (`digitalisimo-post-navigation`): sustituye al widget «Post Navigation» de PRO
 * (`modules/theme-elements/widgets/post-navigation.php`, mapa `modules/digitalisimo-convert/maps/post-navigation.php`).
 * Repite sus controles con el mismo nombre, valor por defecto y condiciones (también la taxonomía por tipo de entrada,
 * `<tipo>_taxonomy`, armada con los mismos tipos públicos que PRO); el marcado, las clases y la hoja son propios, sin
 * script.
 *
 * Mismo dato que PRO: los enlaces salen de `get_previous_post_link()`/`get_next_post_link()` (los mismos filtros de
 * WordPress que `previous_post_link()`), con «En el mismo término» y la taxonomía elegida para el tipo de la entrada.
 *
 * Marcado: `<nav aria-label="Navegación entre entradas">` con un enlace a cada lado (`rel="prev"`/`"next"`) y, con
 * «Bordes», el separador vertical entre los dos. Las flechas son decorativas (`aria-hidden`, iconos de `Icons_Manager`);
 * PRO añadía dentro del enlace un texto oculto «Prev»/«Next» que se leía antes de la etiqueta. Aquí ese texto oculto
 * («Entrada anterior»/«Entrada siguiente») sólo aparece si no se muestran ni la etiqueta ni el título, para que el
 * enlace nunca quede sin nombre.
 *
 * Aspecto por defecto el de la hoja de PRO 4.3: flechas de 30 px en #d5d8dc, etiqueta en mayúsculas al 80 %, título al
 * 70 %, separador de 1 px en #d5d8dc. En PRO 4.3 los bordes superior e inferior de «Bordes» no se pintan (su regla pide
 * una clase que el marcado no tiene): aquí tampoco, y el color y el tamaño de los bordes llegan a las mismas propiedades.
 */
class Post_Navigation extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-post-navigation';
	}

	public function get_title() {
		return esc_html__( 'Navegación entre entradas', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-post-navigation';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'navegación', 'entradas', 'anterior', 'siguiente', 'enlaces', 'post', 'navigation', 'menu', 'links' );
	}

	public function get_style_depends(): array {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends(): array {
		return Module::script_depends( $this->get_name() );
	}

	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	/** Iconos recomendados en el selector, los mismos que PRO. */
	private function get_recommended_icons( $direction ): array {
		return array(
			'fa-regular' => array(
				"arrow-alt-circle-$direction",
				"caret-square-$direction",
			),
			'fa-solid'   => array(
				"angle-double-$direction",
				"angle-$direction",
				"arrow-alt-circle-$direction",
				"arrow-circle-$direction",
				"arrow-$direction",
				"caret-$direction",
				"caret-square-$direction",
				"chevron-circle-$direction",
				"chevron-$direction",
				"long-arrow-alt-$direction",
			),
		);
	}

	/**
	 * Tipos de entrada públicos con alguna taxonomía y sus taxonomías: los mismos que PRO (`ElementorPro\Core\Utils`,
	 * que sigue en Elements), o los de WordPress si esa clase faltara.
	 *
	 * @return array{0: array<string,string>, 1: array<string, array<string,string>>}
	 */
	public static function post_types(): array {
		$options    = array();
		$taxonomies = array();
		$utils      = '\ElementorPro\Core\Utils';
		$types      = class_exists( $utils ) ? $utils::get_public_post_types() : array();
		if ( ! class_exists( $utils ) && function_exists( 'get_post_types' ) ) {
			foreach ( get_post_types( array( 'show_in_nav_menus' => true ), 'objects' ) as $name => $object ) {
				$types[ $name ] = $object->label;
			}
		}
		foreach ( $types as $post_type => $label ) {
			$found = class_exists( $utils ) ? $utils::get_taxonomies( array( 'object_type' => $post_type ), false ) : ( function_exists( 'get_object_taxonomies' ) ? get_object_taxonomies( $post_type, 'objects' ) : array() );
			if ( empty( $found ) ) {
				continue;
			}
			$options[ $post_type ]    = $label;
			$taxonomies[ $post_type ] = array();
			foreach ( $found as $taxonomy ) {
				$taxonomies[ $post_type ][ $taxonomy->name ] = $taxonomy->label;
			}
		}
		return array( $options, $taxonomies );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_post_navigation_content',
			array(
				'label' => esc_html__( 'Navegación entre entradas', 'pro-elements' ),
			)
		);

		$this->add_control(
			'show_label',
			array(
				'label'     => esc_html__( 'Etiqueta', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
			)
		);

		$this->add_control(
			'prev_label',
			array(
				'label'     => esc_html__( 'Etiqueta de la anterior', 'pro-elements' ),
				'type'      => Controls_Manager::TEXT,
				'dynamic'   => array(
					'active' => true,
				),
				'ai'        => array(
					'active' => false,
				),
				'default'   => esc_html__( 'Anterior', 'pro-elements' ),
				'condition' => array(
					'show_label' => 'yes',
				),
			)
		);

		$this->add_control(
			'next_label',
			array(
				'label'     => esc_html__( 'Etiqueta de la siguiente', 'pro-elements' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'Siguiente', 'pro-elements' ),
				'condition' => array(
					'show_label' => 'yes',
				),
				'ai'        => array(
					'active' => false,
				),
				'dynamic'   => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'show_arrow',
			array(
				'label'     => esc_html__( 'Flechas', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		if ( is_rtl() ) {
			$previous_direction = 'right';
			$next_direction     = 'left';
		} else {
			$previous_direction = 'left';
			$next_direction     = 'right';
		}

		$this->add_control(
			'arrow_previous_icon',
			array(
				'label'       => esc_html__( 'Icono de la anterior', 'pro-elements' ),
				'type'        => Controls_Manager::ICONS,
				'skin'        => 'inline',
				'label_block' => false,
				'default'     => array(
					'value'   => "fas fa-angle-$previous_direction",
					'library' => 'fa-solid',
				),
				'recommended' => $this->get_recommended_icons( $previous_direction ),
				'condition'   => array(
					'show_arrow' => 'yes',
				),
			)
		);

		$this->add_control(
			'arrow_next_icon',
			array(
				'label'       => esc_html__( 'Icono de la siguiente', 'pro-elements' ),
				'type'        => Controls_Manager::ICONS,
				'skin'        => 'inline',
				'label_block' => false,
				'default'     => array(
					'value'   => "fas fa-angle-$next_direction",
					'library' => 'fa-solid',
				),
				'recommended' => $this->get_recommended_icons( $next_direction ),
				'condition'   => array(
					'show_arrow' => 'yes',
				),
			)
		);

		$this->add_control(
			'show_title',
			array(
				'label'     => esc_html__( 'Título de la entrada', 'pro-elements' ),
				'type'      => Controls_Manager::SWITCHER,
				'label_on'  => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off' => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'   => 'yes',
				'separator' => 'before',
			)
		);

		$this->add_control(
			'show_borders',
			array(
				'label'        => esc_html__( 'Bordes', 'pro-elements' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => esc_html__( 'Mostrar', 'pro-elements' ),
				'label_off'    => esc_html__( 'Ocultar', 'pro-elements' ),
				'default'      => 'yes',
				'prefix_class' => 'digi-post-navigation--borders-',
				'separator'    => 'before',
			)
		);

		list( $post_type_options, $post_type_taxonomies ) = self::post_types();

		$this->add_control(
			'in_same_term',
			array(
				'label'       => esc_html__( 'En el mismo término', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT2,
				'options'     => $post_type_options,
				'default'     => '',
				'multiple'    => true,
				'label_block' => true,
				'description' => esc_html__( 'La entrada anterior y la siguiente deben compartir un término de la taxonomía elegida con la entrada actual; se elige una taxonomía por cada tipo de entrada.', 'pro-elements' ),
				'separator'   => 'before',
			)
		);

		foreach ( $post_type_options as $post_type => $post_type_label ) {
			$this->add_control(
				$post_type . '_taxonomy',
				array(
					/* translators: %s: nombre del tipo de entrada. */
					'label'     => sprintf( esc_html__( 'Taxonomía de %s', 'pro-elements' ), $post_type_label ),
					'type'      => Controls_Manager::SELECT,
					'options'   => $post_type_taxonomies[ $post_type ],
					'default'   => '',
					'condition' => array(
						'in_same_term' => $post_type,
					),
				)
			);
		}

		$this->end_controls_section();

		$this->start_controls_section(
			'label_style',
			array(
				'label'     => esc_html__( 'Etiqueta', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_label' => 'yes',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_label_style' );

		$this->start_controls_tab(
			'label_color_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'label_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_TEXT,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-post-navigation__label' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'label_color_hover',
			array(
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
			)
		);

		$this->add_control(
			'label_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-post-navigation__label:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'label_hover_color_transition_duration',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array(
					'unit' => 'ms',
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-post-navigation__label' => 'transition-duration: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'label_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_SECONDARY,
				),
				'selector' => '{{WRAPPER}} .digi-post-navigation__label',
				'exclude'  => array( 'line_height' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'title_style',
			array(
				'label'     => esc_html__( 'Título de la entrada', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_title' => 'yes',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_post_navigation_style' );

		$this->start_controls_tab(
			'tab_color_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'text_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array(
					'default' => Global_Colors::COLOR_SECONDARY,
				),
				'selectors' => array(
					'{{WRAPPER}} .digi-post-navigation__title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_color_hover',
			array(
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
			)
		);

		$this->add_control(
			'hover_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-post-navigation__title:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'hover_color_transition_duration',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array(
					'unit' => 'ms',
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-post-navigation__title' => 'transition-duration: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'title_typography',
				'global'   => array(
					'default' => Global_Typography::TYPOGRAPHY_SECONDARY,
				),
				'selector' => '{{WRAPPER}} .digi-post-navigation__title',
				'exclude'  => array( 'line_height' ),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'arrow_style',
			array(
				'label'     => esc_html__( 'Flecha', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_arrow' => 'yes',
				),
			)
		);

		$this->start_controls_tabs( 'tabs_post_navigation_arrow_style' );

		$this->start_controls_tab(
			'arrow_color_normal',
			array(
				'label' => esc_html__( 'Normal', 'pro-elements' ),
			)
		);

		$this->add_control(
			'arrow_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-post-navigation__arrow' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'arrow_color_hover',
			array(
				'label' => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
			)
		);

		$this->add_control(
			'arrow_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-post-navigation__arrow:hover' => 'color: {{VALUE}}; fill: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'arrow_hover_color_transition_duration',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array(
					'unit' => 'ms',
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-post-navigation__arrow' => 'transition-duration: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'arrow_size',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => array(
					'px'  => array(
						'min' => 1,
						'max' => 300,
					),
					'em'  => array(
						'max' => 30,
					),
					'rem' => array(
						'max' => 30,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-post-navigation__arrow' => 'font-size: {{SIZE}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->add_control(
			'arrow_padding',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
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
					'{{WRAPPER}} .digi-post-navigation__link a' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'borders_section_style',
			array(
				'label'     => esc_html__( 'Bordes', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => array(
					'show_borders!' => '',
				),
			)
		);

		$this->add_control(
			'sep_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .digi-post-navigation__separator' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .digi-post-navigation' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'borders_width',
			array(
				'label'      => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
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
				),
				'selectors'  => array(
					'{{WRAPPER}} .digi-post-navigation__separator' => 'width: {{SIZE}}{{UNIT}}',
					'{{WRAPPER}} .digi-post-navigation' => 'border-block-width: {{SIZE}}{{UNIT}};',
					'{{WRAPPER}} .digi-post-navigation__next.digi-post-navigation__link' => 'width: calc(50% - ({{SIZE}}{{UNIT}} / 2))',
					'{{WRAPPER}} .digi-post-navigation__prev.digi-post-navigation__link' => 'width: calc(50% - ({{SIZE}}{{UNIT}} / 2))',
				),
			)
		);

		$this->add_control(
			'borders_spacing',
			array(
				'label'      => esc_html__( 'Espaciado', 'pro-elements' ),
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
					'{{WRAPPER}} .digi-post-navigation' => 'padding: {{SIZE}}{{UNIT}} 0;',
				),
			)
		);

		$this->end_controls_section();
	}

	/** Icono decorativo de una flecha ('' si el icono está vacío). */
	private static function icon( $icon ): string {
		if ( ! is_array( $icon ) || empty( $icon['value'] ) || ! class_exists( Icons_Manager::class ) ) {
			return '';
		}
		ob_start();
		Icons_Manager::render_icon( $icon, array( 'aria-hidden' => 'true' ) );
		return (string) ob_get_clean();
	}

	/**
	 * «En el mismo término» y su taxonomía para el tipo de la entrada actual, como PRO.
	 *
	 * @return array{0: bool, 1: string}
	 */
	public static function same_term( array $settings ): array {
		$in_same_term = false;
		$taxonomy     = 'category';
		$post_type    = get_post_type( get_queried_object_id() );
		if ( ! empty( $settings['in_same_term'] ) && is_array( $settings['in_same_term'] ) && in_array( $post_type, $settings['in_same_term'] ) ) { // phpcs:ignore WordPress.PHP.StrictInArray -- como PRO.
			if ( isset( $settings[ $post_type . '_taxonomy' ] ) ) {
				$in_same_term = true;
				$taxonomy     = $settings[ $post_type . '_taxonomy' ];
			}
		}
		return array( $in_same_term, (string) $taxonomy );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$parts = array();
		foreach ( array( 'prev' => 'arrow_previous_icon', 'next' => 'arrow_next_icon' ) as $side => $icon_key ) {
			$label = '';
			$title = '';
			$arrow = '';
			if ( 'yes' === ( $settings['show_label'] ?? '' ) ) {
				$label = '<span class="digi-post-navigation__label digi-post-navigation__label--' . $side . '">' . wp_kses_post( (string) ( $settings[ $side . '_label' ] ?? '' ) ) . '</span>';
			}
			if ( 'yes' === ( $settings['show_title'] ?? '' ) ) {
				$title = '<span class="digi-post-navigation__title digi-post-navigation__title--' . $side . '">%title</span>';
			}
			if ( 'yes' === ( $settings['show_arrow'] ?? '' ) ) {
				$arrow = '<span class="digi-post-navigation__arrow digi-post-navigation__arrow--' . $side . '" aria-hidden="true">' . self::icon( $settings[ $icon_key ] ?? array() ) . '</span>';
			}
			// Sin etiqueta ni título, el enlace conserva un nombre accesible.
			$hidden = '' === $label && '' === $title ? '<span class="digi-post-navigation__sr">' . ( 'prev' === $side ? esc_html__( 'Entrada anterior', 'pro-elements' ) : esc_html__( 'Entrada siguiente', 'pro-elements' ) ) . '</span>' : '';
			$text   = '<span class="digi-post-navigation__text digi-post-navigation__text--' . $side . '">' . $label . $title . $hidden . '</span>';
			$parts[ $side ] = 'prev' === $side ? $arrow . $text : $text . $arrow;
		}

		list( $in_same_term, $taxonomy ) = self::same_term( $settings );

		echo '<nav class="digi-post-navigation" aria-label="' . esc_attr__( 'Navegación entre entradas', 'pro-elements' ) . '">';
		echo '<div class="digi-post-navigation__prev digi-post-navigation__link">';
		echo get_previous_post_link( '%link', $parts['prev'], $in_same_term, '', $taxonomy ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- enlace de WordPress con el marcado propio escapado arriba.
		echo '</div>';
		if ( 'yes' === ( $settings['show_borders'] ?? '' ) ) {
			echo '<div class="digi-post-navigation__separator-wrapper"><div class="digi-post-navigation__separator"></div></div>';
		}
		echo '<div class="digi-post-navigation__next digi-post-navigation__link">';
		echo get_next_post_link( '%link', $parts['next'], $in_same_term, '', $taxonomy ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ídem.
		echo '</div>';
		echo '</nav>';
	}

	/** Markdown: un enlace por lado, «Etiqueta: título», como PRO. */
	public function render_markdown(): string {
		$settings   = $this->get_settings_for_display();
		$prev_label = self::link_text( $settings['prev_label'] ?? '' );
		$next_label = self::link_text( $settings['next_label'] ?? '' );
		if ( '' === $prev_label ) {
			$prev_label = esc_html__( 'Anterior', 'pro-elements' );
		}
		if ( '' === $next_label ) {
			$next_label = esc_html__( 'Siguiente', 'pro-elements' );
		}
		$lines = array();
		foreach ( array( array( get_previous_post(), $prev_label ), array( get_next_post(), $next_label ) ) as $pair ) {
			if ( $pair[0] ) {
				$lines[] = '[' . $pair[1] . ': ' . self::link_text( get_the_title( $pair[0] ) ) . '](' . esc_url( get_permalink( $pair[0] ) ) . ')';
			}
		}
		return implode( "\n", $lines );
	}

	private static function link_text( $value ): string {
		$text = trim( wp_strip_all_tags( (string) $value ) );
		if ( '' === $text ) {
			return '';
		}
		return str_replace( array( '[', ']' ), array( '\\[', '\\]' ), (string) preg_replace( '/\s+/', ' ', $text ) );
	}

	public function render_plain_content() {}
}
