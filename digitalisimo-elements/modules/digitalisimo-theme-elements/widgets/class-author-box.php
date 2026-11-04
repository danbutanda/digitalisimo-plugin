<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;
use Elementor\Controls_Manager;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Caja de autor (`digitalisimo-author-box`): sustituye al widget «Author Box» de PRO
 * (`modules/theme-elements/widgets/author-box.php`, mapa `modules/digitalisimo-convert/maps/author-box.php`). Repite sus
 * controles con el mismo nombre, tipo, valor por defecto, opciones y condiciones; el marcado, las clases (`digi-author-box…`)
 * y la hoja son propios, sin JavaScript.
 *
 * Mismos datos que PRO: con «Autor actual», `get_the_author_meta()` (nombre, web, biografía), `get_avatar_url()` con el
 * tamaño elegido y `get_author_posts_url()`; con «Personalizado», los campos del widget (biografía con `wpautop()`).
 *
 * El «Perfil ampliado» (`Author_Box_Extras`, que en PRO se inyecta desde fuera con filtros) va integrado: la misma
 * sección y los mismos ajustes `digitalisimo_author_*` (pasan sin cambios al convertir), con el usuario del sitio elegido
 * sustituyendo los datos escritos a mano y sus piezas en los mismos puntos (cabecera al inicio, nombre de usuario tras el
 * nombre, cifras y redes antes del botón), con la hoja `digitalisimo-author-box-extras` sólo cuando se usan.
 *
 * El botón conserva las clases del núcleo `elementor-button elementor-size-xs` (no son de PRO): así le llegan los estilos
 * de botón del kit del sitio igual que al de PRO, y la hoja propia repite encima el aspecto de PRO.
 *
 * Diferencias declaradas: textos alternativos en español («Imagen de %s»), avatar con ancho y alto (sin salto de
 * maquetación), una imagen de la biblioteca con `wp_get_attachment_image()` (srcset) en lugar de su URL mediana, el enlace a
 * la web del autor con `rel="noopener"` y el foco visible propio.
 */
class Author_Box extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-author-box';
	}

	public function get_title() {
		return esc_html__( 'Caja de autor', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-person';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'autor', 'author', 'usuario', 'user', 'perfil', 'profile', 'biografía', 'biography', 'avatar' );
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

	/**
	 * Condición de PRO para las opciones de la imagen, el nombre o la biografía: con el autor actual, su interruptor
	 * (`$show`) activo; con «Personalizado», el campo (`$field`) no vacío; y en las dos, los términos de `$extra`.
	 */
	private static function shown( string $show, string $field, array $extra = array() ): array {
		$branch = static function ( $source, $name, $operator, $value ) use ( $extra ) {
			return array(
				'relation' => 'and',
				'terms'    => array_merge(
					array(
						array( 'name' => 'source', 'operator' => '===', 'value' => $source ),
						array( 'name' => $name, 'operator' => $operator, 'value' => $value ),
					),
					$extra
				),
			);
		};
		return array(
			'relation' => 'or',
			'terms'    => array( $branch( 'current', $show, '===', 'yes' ), $branch( 'custom', $field, '!==', '' ) ),
		);
	}

	private static function image( array $extra = array() ): array {
		return self::shown( 'show_avatar', 'author_avatar[url]', $extra );
	}

	private static function show_hide( string $label, string $prefix, string $default ): array {
		return array(
			'label'        => $label,
			'type'         => Controls_Manager::SWITCHER,
			'prefix_class' => 'digi-author-box--' . $prefix . '-',
			'label_on'     => esc_html__( 'Mostrar', 'pro-elements' ),
			'label_off'    => esc_html__( 'Ocultar', 'pro-elements' ),
			'default'      => $default,
			'separator'    => 'before',
			'condition'    => array( 'source' => 'current' ),
			'render_type'  => 'template',
		);
	}

	private static function range( int $px, int $em ): array {
		return array(
			'px'  => array( 'max' => $px ),
			'em'  => array( 'max' => $em ),
			'rem' => array( 'max' => $em ),
		);
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_author_info', array( 'label' => esc_html__( 'Datos del autor', 'pro-elements' ) ) );

		$this->add_control(
			'source',
			array(
				'label'   => esc_html__( 'Origen', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'current',
				'options' => array(
					'current' => esc_html__( 'Autor actual', 'pro-elements' ),
					'custom'  => esc_html__( 'Personalizado', 'pro-elements' ),
				),
			)
		);

		$this->add_control( 'show_avatar', self::show_hide( esc_html__( 'Imagen de perfil', 'pro-elements' ), 'avatar', 'yes' ) );

		// El tamaño que pide `get_avatar_url()`.
		$this->add_control(
			'avatar_size',
			array(
				'label'     => esc_html__( 'Tamaño de la imagen', 'pro-elements' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 300,
				'condition' => array(
					'source'      => 'current',
					'show_avatar' => 'yes',
				),
			)
		);

		$this->add_control(
			'author_avatar',
			array(
				'label'     => esc_html__( 'Imagen de perfil', 'pro-elements' ),
				'type'      => Controls_Manager::MEDIA,
				'default'   => array( 'url' => Utils::get_placeholder_image_src() ),
				'condition' => array( 'source' => 'custom' ),
				'separator' => 'before',
				'dynamic'   => array( 'active' => true ),
			)
		);

		$this->add_control( 'show_name', self::show_hide( esc_html__( 'Nombre visible', 'pro-elements' ), 'name', 'yes' ) );

		$this->add_control(
			'author_name',
			array(
				'label'     => esc_html__( 'Nombre', 'pro-elements' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => esc_html__( 'John Doe', 'pro-elements' ),
				'condition' => array( 'source' => 'custom' ),
				'separator' => 'before',
				'dynamic'   => array( 'active' => true ),
				'ai'        => array( 'active' => false ),
			)
		);

		$this->add_control(
			'author_name_tag',
			array(
				'label'   => esc_html__( 'Etiqueta HTML', 'pro-elements' ),
				'type'    => Controls_Manager::SELECT,
				'options' => array(
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'div'  => 'div',
					'span' => 'span',
				),
				'default' => 'h4',
			)
		);

		$this->add_control(
			'link_to',
			array(
				'label'       => esc_html__( 'Enlace', 'pro-elements' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array(
					''              => esc_html__( 'Ninguno', 'pro-elements' ),
					'website'       => esc_html__( 'Sitio web', 'pro-elements' ),
					'posts_archive' => esc_html__( 'Archivo de entradas', 'pro-elements' ),
				),
				'condition'   => array( 'source' => 'current' ),
				'description' => esc_html__( 'Enlace del nombre y de la imagen del autor.', 'pro-elements' ),
			)
		);

		$this->add_control( 'show_biography', self::show_hide( esc_html__( 'Biografía', 'pro-elements' ), 'biography', 'yes' ) );
		$this->add_control( 'show_link', self::show_hide( esc_html__( 'Botón del archivo', 'pro-elements' ), 'link', 'no' ) );

		$this->add_control(
			'author_website',
			array(
				'label'       => esc_html__( 'Enlace', 'pro-elements' ),
				'type'        => Controls_Manager::URL,
				'condition'   => array( 'source' => 'custom' ),
				'description' => esc_html__( 'Enlace del nombre y de la imagen del autor.', 'pro-elements' ),
			)
		);

		$this->add_control(
			'author_bio',
			array(
				'label'     => esc_html__( 'Biografía', 'pro-elements' ),
				'type'      => Controls_Manager::TEXTAREA,
				'default'   => esc_html__( 'Lorem ipsum dolor sit amet consectetur adipiscing elit dolor', 'pro-elements' ),
				'rows'      => 3,
				'condition' => array( 'source' => 'custom' ),
				'separator' => 'before',
				'dynamic'   => array( 'active' => true ),
			)
		);

		$this->add_control(
			'posts_url',
			array(
				'label'     => esc_html__( 'Botón del archivo', 'pro-elements' ),
				'type'      => Controls_Manager::URL,
				'dynamic'   => array( 'active' => true ),
				'condition' => array( 'source' => 'custom' ),
			)
		);

		$this->add_control(
			'link_text',
			array(
				'label'   => esc_html__( 'Texto del botón', 'pro-elements' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'All Posts', 'pro-elements' ),
				'dynamic' => array( 'active' => true ),
				'ai'      => array( 'active' => false ),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'        => esc_html__( 'Disposición', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'left'  => array( 'title' => esc_html__( 'Izquierda', 'pro-elements' ), 'icon' => 'eicon-h-align-left' ),
					'above' => array( 'title' => esc_html__( 'Arriba', 'pro-elements' ), 'icon' => 'eicon-v-align-top' ),
					'right' => array( 'title' => esc_html__( 'Derecha', 'pro-elements' ), 'icon' => 'eicon-h-align-right' ),
				),
				'separator'    => 'before',
				'prefix_class' => 'digi-author-box--layout-image-',
			)
		);

		$this->add_control(
			'alignment',
			array(
				'label'        => esc_html__( 'Alineación', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'left'   => array( 'title' => esc_html__( 'Izquierda', 'pro-elements' ), 'icon' => 'eicon-text-align-left' ),
					'center' => array( 'title' => esc_html__( 'Centro', 'pro-elements' ), 'icon' => 'eicon-text-align-center' ),
					'right'  => array( 'title' => esc_html__( 'Derecha', 'pro-elements' ), 'icon' => 'eicon-text-align-right' ),
				),
				'prefix_class' => 'digi-author-box--align-',
			)
		);

		$this->end_controls_section();

		// «Perfil ampliado»: la misma sección que `Author_Box_Extras` añade a la caja de PRO tras «Datos del autor».
		if ( class_exists( '\Digitalisimo\Elements\Author_Box_Extras' ) ) {
			\Digitalisimo\Elements\Author_Box_Extras::register_controls( $this );
		}

		$this->start_controls_section(
			'section_image_style',
			array(
				'label'      => esc_html__( 'Imagen', 'pro-elements' ),
				'tab'        => Controls_Manager::TAB_STYLE,
				'conditions' => self::image(),
			)
		);

		$not_above = array( array( 'name' => 'layout', 'operator' => '!==', 'value' => 'above' ) );
		$bordered  = array( array( 'name' => 'image_border', 'operator' => '===', 'value' => 'yes' ) );

		$this->add_control(
			'image_vertical_align',
			array(
				'label'        => esc_html__( 'Alineación vertical', 'pro-elements' ),
				'type'         => Controls_Manager::CHOOSE,
				'options'      => array(
					'top'    => array( 'title' => esc_html__( 'Arriba', 'pro-elements' ), 'icon' => 'eicon-v-align-top' ),
					'middle' => array( 'title' => esc_html__( 'En medio', 'pro-elements' ), 'icon' => 'eicon-v-align-middle' ),
				),
				'prefix_class' => 'digi-author-box--image-valign-',
				'conditions'   => self::image( $not_above ),
			)
		);

		$this->add_responsive_control(
			'image_size',
			array(
				'label'      => esc_html__( 'Tamaño de la imagen', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => self::range( 200, 20 ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__avatar img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}' ),
				'conditions' => self::image(),
			)
		);

		$this->add_responsive_control(
			'image_gap',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => self::range( 100, 10 ),
				'selectors'  => array(
					'body.rtl {{WRAPPER}}.digi-author-box--layout-image-left .digi-author-box__avatar, body:not(.rtl) {{WRAPPER}}:not(.digi-author-box--layout-image-above) .digi-author-box__avatar' => 'margin-right: {{SIZE}}{{UNIT}}; margin-left: 0;',
					'body:not(.rtl) {{WRAPPER}}.digi-author-box--layout-image-right .digi-author-box__avatar, body.rtl {{WRAPPER}}:not(.digi-author-box--layout-image-above) .digi-author-box__avatar' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right:0;',
					'{{WRAPPER}}.digi-author-box--layout-image-above .digi-author-box__avatar' => 'margin-bottom: {{SIZE}}{{UNIT}}',
				),
				'conditions' => self::image(),
			)
		);

		$this->add_control(
			'image_border',
			array(
				'label'      => esc_html__( 'Borde', 'pro-elements' ),
				'type'       => Controls_Manager::SWITCHER,
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__avatar img' => 'border-style: solid' ),
				'conditions' => self::image(),
			)
		);

		$this->add_control(
			'image_border_color',
			array(
				'label'      => esc_html__( 'Color del borde', 'pro-elements' ),
				'type'       => Controls_Manager::COLOR,
				'default'    => '#000',
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__avatar img' => 'border-color: {{VALUE}}' ),
				'conditions' => self::image( $bordered ),
			)
		);

		$this->add_responsive_control(
			'image_border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => self::range( 20, 2 ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__avatar img' => 'border-width: {{SIZE}}{{UNIT}}' ),
				'conditions' => self::image( $bordered ),
			)
		);

		$this->add_control(
			'image_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__avatar img' => 'border-radius: {{SIZE}}{{UNIT}}' ),
				'conditions' => self::image(),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'           => 'input_box_shadow',
				'selector'       => '{{WRAPPER}} .digi-author-box__avatar img',
				'fields_options' => array( 'box_shadow_type' => array( 'separator' => 'default' ) ),
				'conditions'     => self::image(),
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_text_style',
			array(
				'label' => esc_html__( 'Autor', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$name = self::shown( 'show_name', 'author_name' );
		$bio  = self::shown( 'show_biography', 'author_bio' );

		$this->add_control(
			'heading_name_style',
			array(
				'label'      => esc_html__( 'Nombre', 'pro-elements' ),
				'type'       => Controls_Manager::HEADING,
				'separator'  => 'before',
				'conditions' => $name,
			)
		);

		$this->add_control(
			'name_color',
			array(
				'label'      => esc_html__( 'Color', 'pro-elements' ),
				'type'       => Controls_Manager::COLOR,
				'global'     => array( 'default' => Global_Colors::COLOR_SECONDARY ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__name' => 'color: {{VALUE}}' ),
				'conditions' => $name,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'       => 'name_typography',
				'selector'   => '{{WRAPPER}} .digi-author-box__name',
				'global'     => array( 'default' => Global_Typography::TYPOGRAPHY_PRIMARY ),
				'conditions' => $name,
			)
		);

		$this->add_responsive_control(
			'name_gap',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => self::range( 100, 10 ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__name' => 'margin-bottom: {{SIZE}}{{UNIT}}' ),
				'conditions' => $name,
			)
		);

		$this->add_control(
			'heading_bio_style',
			array(
				'label'      => esc_html__( 'Biografía', 'pro-elements' ),
				'type'       => Controls_Manager::HEADING,
				'separator'  => 'before',
				'conditions' => $bio,
			)
		);

		$this->add_control(
			'bio_color',
			array(
				'label'      => esc_html__( 'Color', 'pro-elements' ),
				'type'       => Controls_Manager::COLOR,
				'global'     => array( 'default' => Global_Colors::COLOR_TEXT ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__bio' => 'color: {{VALUE}}' ),
				'conditions' => $bio,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'       => 'bio_typography',
				'selector'   => '{{WRAPPER}} .digi-author-box__bio',
				'global'     => array( 'default' => Global_Typography::TYPOGRAPHY_TEXT ),
				'conditions' => $bio,
			)
		);

		$this->add_responsive_control(
			'bio_gap',
			array(
				'label'      => esc_html__( 'Separación', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'range'      => self::range( 100, 10 ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__bio' => 'margin-bottom: {{SIZE}}{{UNIT}}' ),
				'conditions' => $bio,
			)
		);

		$this->end_controls_section();

		$button = array( 'link_text!' => '' );

		$this->start_controls_section(
			'section_style_button',
			array(
				'label'     => esc_html__( 'Botón', 'pro-elements' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => $button,
			)
		);

		$this->start_controls_tabs( 'tabs_button_style' );

		$this->start_controls_tab(
			'tab_button_normal',
			array(
				'label'     => esc_html__( 'Normal', 'pro-elements' ),
				'condition' => $button,
			)
		);

		$this->add_control(
			'button_text_color',
			array(
				'label'     => esc_html__( 'Color del texto', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_SECONDARY ),
				'default'   => '',
				'selectors' => array( '{{WRAPPER}} .digi-author-box__button' => 'color: {{VALUE}}; border-color: {{VALUE}}' ),
				'condition' => $button,
			)
		);

		$this->add_control(
			'button_background_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-author-box__button' => 'background-color: {{VALUE}}' ),
				'condition' => $button,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'      => 'button_typography',
				'global'    => array( 'default' => Global_Typography::TYPOGRAPHY_ACCENT ),
				'selector'  => '{{WRAPPER}} .digi-author-box__button',
				'condition' => $button,
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'tab_button_hover',
			array(
				'label'     => esc_html__( 'Al pasar el cursor', 'pro-elements' ),
				'condition' => $button,
			)
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label'     => esc_html__( 'Color', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'global'    => array( 'default' => Global_Colors::COLOR_SECONDARY ),
				'selectors' => array( '{{WRAPPER}} .digi-author-box__button:hover' => 'border-color: {{VALUE}}; color: {{VALUE}};' ),
				'condition' => $button,
			)
		);

		$this->add_control(
			'button_background_hover_color',
			array(
				'label'     => esc_html__( 'Color de fondo', 'pro-elements' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .digi-author-box__button:hover' => 'background-color: {{VALUE}};' ),
				'condition' => $button,
			)
		);

		$this->add_control(
			'button_hover_transition_duration',
			array(
				'label'      => esc_html__( 'Duración de la transición', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 's', 'ms', 'custom' ),
				'default'    => array( 'unit' => 'ms' ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__button' => 'transition-duration: {{SIZE}}{{UNIT}}' ),
				'condition'  => $button,
			)
		);

		$this->add_control(
			'button_hover_animation',
			array(
				'label'     => esc_html__( 'Animación', 'pro-elements' ),
				'type'      => Controls_Manager::HOVER_ANIMATION,
				'condition' => $button,
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_control(
			'button_border_width',
			array(
				'label'      => esc_html__( 'Ancho del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'range'      => self::range( 20, 2 ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__button' => 'border-width: {{SIZE}}{{UNIT}};' ),
				'separator'  => 'before',
				'condition'  => $button,
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'range'      => self::range( 100, 10 ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__button' => 'border-radius: {{SIZE}}{{UNIT}};' ),
				'separator'  => 'after',
				'condition'  => $button,
			)
		);

		$this->add_control(
			'button_text_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array( '{{WRAPPER}} .digi-author-box__button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
				'separator'  => 'before',
				'condition'  => $button,
			)
		);

		$this->end_controls_section();
	}

	/** Usuario del sitio elegido en el «Perfil ampliado» (sólo con «Personalizado»), o `false`. */
	private static function chosen_user( array $settings ) {
		$id = 'custom' === ( $settings['source'] ?? 'current' ) ? absint( $settings['digitalisimo_author_user'] ?? 0 ) : 0;
		return $id && function_exists( 'get_userdata' ) ? get_userdata( $id ) : false;
	}

	/**
	 * Ajustes con los datos del usuario elegido en el «Perfil ampliado», como hace `Author_Box_Extras::before_render()` con
	 * la caja de PRO: su nombre, biografía, avatar y, si el botón no tiene enlace, su archivo de entradas.
	 */
	public static function with_chosen_user( array $settings ): array {
		$user = self::chosen_user( $settings );
		if ( ! $user ) {
			return $settings;
		}
		$name                       = trim( $user->first_name . ' ' . $user->last_name );
		$settings['author_name']    = '' !== $name ? $name : $user->display_name;
		$settings['author_bio']     = (string) $user->description;
		$settings['author_avatar']  = array( 'url' => (string) get_avatar_url( $user->ID, array( 'size' => 300 ) ) );
		if ( empty( $settings['posts_url']['url'] ) ) {
			$settings['posts_url'] = array( 'url' => (string) get_author_posts_url( $user->ID ) );
		}
		return $settings;
	}

	/** Datos del autor según el origen, de las mismas funciones que PRO. */
	private static function author( array $settings ): array {
		$author = array( 'avatar' => '', 'avatar_id' => 0, 'display_name' => '', 'website' => '', 'bio' => '', 'posts_url' => '' );
		if ( 'current' === $settings['source'] ) {
			$user_id                = get_the_author_meta( 'ID' );
			$author['avatar']       = (string) get_avatar_url( $user_id, array( 'size' => $settings['avatar_size'] ?? 300 ) );
			$author['display_name'] = (string) get_the_author_meta( 'display_name' );
			$author['website']      = (string) get_the_author_meta( 'user_url' );
			$author['bio']          = (string) get_the_author_meta( 'description' );
			$author['posts_url']    = (string) get_author_posts_url( $user_id );
		} elseif ( 'custom' === $settings['source'] ) {
			if ( ! empty( $settings['author_avatar']['url'] ) ) {
				$author['avatar']    = (string) $settings['author_avatar']['url'];
				$author['avatar_id'] = (int) ( $settings['author_avatar']['id'] ?? 0 );
			}
			$author['display_name'] = (string) ( $settings['author_name'] ?? '' );
			$author['website']      = (string) ( $settings['author_website']['url'] ?? '' );
			$author['bio']          = wpautop( (string) ( $settings['author_bio'] ?? '' ) );
			$author['posts_url']    = (string) ( $settings['posts_url']['url'] ?? '' );
		}
		return $author;
	}

	/** Imagen del autor: un adjunto de la biblioteca con `wp_get_attachment_image()`; si no, la URL (avatar) con medidas. */
	private static function avatar_html( array $author, array $settings ): string {
		$alt = '' !== $author['display_name']
			/* translators: %s: nombre de la persona. */
			? sprintf( __( 'Imagen de %s', 'pro-elements' ), wp_strip_all_tags( $author['display_name'] ) )
			: __( 'Imagen del autor', 'pro-elements' );
		if ( $author['avatar_id'] && function_exists( 'wp_get_attachment_image' ) ) {
			$html = (string) wp_get_attachment_image( $author['avatar_id'], 'medium', false, array( 'alt' => $alt, 'loading' => 'lazy', 'decoding' => 'async' ) );
			if ( '' !== $html ) {
				return $html;
			}
		}
		$size = 'current' === $settings['source'] ? max( 1, (int) ( $settings['avatar_size'] ?? 300 ) ) : 0;
		return '<img src="' . esc_url( $author['avatar'] ) . '" alt="' . esc_attr( $alt ) . '"' . ( $size ? ' width="' . $size . '" height="' . $size . '"' : '' ) . ' loading="lazy" decoding="async">';
	}

	/** Piezas del «Perfil ampliado» (cabecera, nombre de usuario y cuerpo), vacías si no se usa o no está cargado. */
	private static function extras( array $settings ): array {
		$parts = array( 'header' => '', 'username' => '', 'body' => '' );
		if ( class_exists( '\Digitalisimo\Elements\Author_Box_Extras' ) ) {
			$parts = array_merge( $parts, (array) \Digitalisimo\Elements\Author_Box_Extras::parts( $settings ) );
		}
		if ( '' !== $parts['header'] . $parts['username'] . $parts['body'] && function_exists( 'wp_enqueue_style' ) && defined( 'DIGITALISIMO_ELEMENTS_FILE' ) ) {
			$handle = \Digitalisimo\Elements\Author_Box_Extras::STYLE;
			if ( ! wp_style_is( $handle, 'registered' ) ) {
				wp_register_style( $handle, plugins_url( 'assets/css/author-box-extras.css', DIGITALISIMO_ELEMENTS_FILE ), array(), defined( 'DIGITALISIMO_ELEMENTS_VERSION' ) ? DIGITALISIMO_ELEMENTS_VERSION : false );
			}
			wp_enqueue_style( $handle );
		}
		return $parts;
	}

	protected function render() {
		$settings = self::with_chosen_user( (array) $this->get_settings_for_display() );
		$settings += array( 'source' => 'current', 'author_name_tag' => 'h4', 'link_to' => '', 'link_text' => '', 'show_avatar' => '', 'show_name' => '', 'show_biography' => '', 'show_link' => '' );
		$author   = self::author( $settings );
		$custom   = 'custom' === $settings['source'];
		$name_tag = Utils::validate_html_tag( $settings['author_name_tag'] );

		$print_avatar = ( ! $custom && 'yes' === $settings['show_avatar'] ) || ( $custom && '' !== $author['avatar'] );
		$print_name   = ( ! $custom && 'yes' === $settings['show_name'] ) || ( $custom && '' !== $author['display_name'] );
		$print_bio    = ( ! $custom && 'yes' === $settings['show_biography'] ) || ( $custom && '' !== trim( $author['bio'] ) );
		$print_link   = '' !== (string) $settings['link_text'] && ( ( ! $custom && 'yes' === $settings['show_link'] ) || ( $custom && '' !== $author['posts_url'] ) );

		// Enlace del nombre y de la imagen, con las mismas reglas que PRO.
		$link = '';
		if ( ! empty( $settings['link_to'] ) || $custom ) {
			if ( ( $custom || 'website' === $settings['link_to'] ) && '' !== $author['website'] ) {
				$external = $custom ? ! empty( $settings['author_website']['is_external'] ) : true;
				$link     = ' href="' . esc_url( $author['website'] ) . '"' . ( $external ? ' target="_blank" rel="noopener"' : '' );
			} elseif ( 'posts_archive' === $settings['link_to'] && '' !== $author['posts_url'] ) {
				$link = ' href="' . esc_url( $author['posts_url'] ) . '"';
			}
		}
		$tag = '' !== $link ? 'a' : 'div';

		$parts = self::extras( $settings );
		$html  = '<div class="digi-author-box' . ( '' !== $parts['header'] ? ' digi-author-box--header' : '' ) . '">' . $parts['header'];
		if ( $print_avatar ) {
			$html .= '<' . $tag . $link . ' class="digi-author-box__avatar">' . self::avatar_html( $author, $settings ) . '</' . $tag . '>';
		}
		$html .= '<div class="digi-author-box__text">';
		if ( $print_name ) {
			// El nombre sale de los datos del autor o de un campo con el HTML que permite el editor (como PRO).
			$html .= '<' . $tag . $link . '><' . $name_tag . ' class="digi-author-box__name">' . wp_kses_post( $author['display_name'] ) . '</' . $name_tag . '></' . $tag . '>';
		}
		$html .= $parts['username'];
		if ( $print_bio ) {
			$html .= '<div class="digi-author-box__bio">' . wp_kses_post( $author['bio'] ) . '</div>';
		}
		$html .= $parts['body'];
		if ( $print_link ) {
			$classes = 'digi-author-box__button elementor-button elementor-size-xs';
			if ( ! empty( $settings['button_hover_animation'] ) ) {
				$classes .= ' elementor-animation-' . sanitize_html_class( (string) $settings['button_hover_animation'] );
			}
			$html .= '<a class="' . esc_attr( $classes ) . '" href="' . esc_url( $author['posts_url'] ) . '">' . wp_kses_post( $settings['link_text'] ) . '</a>';
		}
		$html .= '</div></div>';
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cada parte se escapa al armarla.
	}

	/** Markdown como PRO: nombre en negrita, biografía y enlace al archivo. */
	public function render_markdown(): string {
		$settings = self::with_chosen_user( (array) $this->get_settings_for_display() );
		if ( 'custom' === ( $settings['source'] ?? 'current' ) ) {
			$name      = trim( wp_strip_all_tags( (string) ( $settings['author_name'] ?? '' ) ) );
			$bio       = trim( wp_strip_all_tags( (string) ( $settings['author_bio'] ?? '' ) ) );
			$posts_url = (string) ( $settings['posts_url']['url'] ?? '' );
		} else {
			$name      = trim( wp_strip_all_tags( (string) get_the_author_meta( 'display_name' ) ) );
			$bio       = trim( wp_strip_all_tags( (string) get_the_author_meta( 'description' ) ) );
			$posts_url = (string) get_author_posts_url( (int) get_the_author_meta( 'ID' ) );
		}
		$link_text = trim( wp_strip_all_tags( (string) ( $settings['link_text'] ?? '' ) ) );
		$lines     = array();
		if ( '' !== $name ) {
			$lines[] = '**' . $name . '**';
		}
		if ( '' !== $bio ) {
			$lines[] = $bio;
		}
		if ( '' !== $link_text && '' !== $posts_url ) {
			$lines[] = '[' . $link_text . '](' . esc_url( $posts_url ) . ')';
		}
		return implode( "\n\n", $lines );
	}

	public function render_plain_content() {}
}
