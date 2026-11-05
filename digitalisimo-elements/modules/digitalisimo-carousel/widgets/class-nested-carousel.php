<?php
namespace Digitalisimo\Elements\Carousel\Widgets;

use Digitalisimo\Elements\Carousel\Engine;
use Digitalisimo\Elements\Carousel\Module;
use Digitalisimo\Elements\Carousel\Nested_Controls;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Modules\NestedElements\Base\Widget_Nested_Base;
use Elementor\Modules\NestedElements\Controls\Control_Nested_Repeater;
use Elementor\Repeater;

defined( 'ABSPATH' ) || exit;

/**
 * Carrusel anidado (`digitalisimo-nested-carousel`, C1 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato `docs/pro/c1.md`
 * §2 «N»): sustituye al «Carrusel» anidado de PRO (`modules/nested-carousel/widgets/nested-carousel.php`, mapa
 * `modules/digitalisimo-convert/maps/nested-carousel.php`). Cada diapositiva es un contenedor hijo, como en PRO.
 *
 * - **Controles**: los 7 del widget de PRO (más `slide_title` en su repetidor), con el mismo nombre, valor por defecto y
 *   condiciones, y los del trait de carrusel de PRO a través de `Nested_Controls`, con los mismos parámetros. Estructura y
 *   controles derivados de PRO Elements (GPL); textos, selectores, marcado y hoja propios.
 * - **Marcado**: el del motor `digi-crs` (`Engine::open()`/`close()`), con los contenedores hijos directamente en la
 *   pista: cada uno recibe la clase `digi-crs__slide`, `role="group"`, `aria-roledescription` y «N de M»
 *   (`Engine::slide_attribute_array()`) por el filtro `elementor/frontend/container/should_render`, como el slider
 *   anidado de F7b; un contenedor anidado dentro del hijo no los recibe. PRO envolvía cada hijo en un `div.swiper-slide`:
 *   aquí el hijo es la diapositiva, así que el CSS personalizado `.swiper-slide > .e-con` deja de aplicarse (límite
 *   declarado en el mapa: las clases sí se traducen).
 * - **Editor**: `content_template()` repite el marcado de `Engine::open()` (los anidados se pintan en el navegador para
 *   que el editor coloque los hijos en la pista); `assets/js/nested-carousel-editor.js` registra el tipo, etiqueta cada
 *   hijo como diapositiva, muestra la que se elige en el panel y vuelve a pintar el widget tras añadir, duplicar, mover o
 *   borrar una diapositiva. Los repetidores mejorados del núcleo necesitan un contenedor con un nodo por fila: es
 *   `div.digi-ncarousel__items[hidden]`, sólo en el editor.
 * - **Recursos**: la hoja y el script del motor más `assets/css/nested-carousel.css` (valores por defecto del carrusel
 *   anidado de PRO); sin script propio en el front.
 */
class Nested_Carousel extends Widget_Nested_Base {
	use Nested_Controls;

	const NAME = 'digitalisimo-nested-carousel';

	/** Selector de baja especificidad de los contenedores-diapositiva del propio widget (no de uno anidado). */
	const SLIDES = ':where( {{WRAPPER}} > .digi-crs > .digi-crs__track, {{WRAPPER}} > .elementor-widget-container > .digi-crs > .digi-crs__track ) > .e-con';

	public function get_name() {
		return 'digitalisimo-nested-carousel';
	}

	public function get_title() {
		return esc_html__( 'Carrusel anidado', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-nested-carousel';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'carrusel', 'carousel', 'diapositivas', 'slides', 'anidado', 'nested', 'contenedores', 'galería', 'digitalisimo' );
	}

	/** Como en PRO: sólo con el experimento de contenedores. */
	public function show_in_panel() {
		return Module::feature( 'container' );
	}

	/** Como los anidados del núcleo: sin envoltorio interior con el marcado optimizado. */
	public function has_widget_inner_wrapper(): bool {
		return ! Module::feature( 'e_optimized_markup' );
	}

	public function get_style_depends(): array {
		return array( Engine::HANDLE, self::NAME );
	}

	public function get_script_depends(): array {
		return array( Engine::HANDLE );
	}

	/** Título de una diapositiva nueva (`%d` = número). */
	protected function get_default_children_title() {
		/* translators: %d: número de la diapositiva. */
		return esc_html__( 'Diapositiva n.º %d', 'pro-elements' );
	}

	protected function get_default_children_elements() {
		$out = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$out[] = array(
				'elType'   => 'container',
				'settings' => array( '_title' => sprintf( $this->get_default_children_title(), $i ) ),
			);
		}
		return $out;
	}

	protected function get_default_repeater_title_setting_key() {
		return 'slide_title';
	}

	/** Los contenedores hijos van directamente en la pista. */
	protected function get_default_children_placeholder_selector() {
		return '.digi-crs__track';
	}

	/**
	 * Repetidor mejorado del núcleo: al añadir o quitar una fila, el editor inserta o quita su nodo en
	 * `.digi-ncarousel__items` (`content_template_single_repeater_item()`); el script del editor vuelve a pintar después.
	 */
	protected function get_initial_config(): array {
		return array_merge(
			parent::get_initial_config(),
			array(
				'support_improved_repeaters' => true,
				'target_container'           => array( '.digi-ncarousel__items' ),
				'node'                       => 'span',
			)
		);
	}

	protected function register_controls() {
		$this->start_controls_section( 'section_slides', array( 'label' => esc_html__( 'Diapositivas', 'pro-elements' ) ) );

		$repeater = new Repeater();
		$repeater->add_control(
			'slide_title',
			array(
				'label'       => esc_html__( 'Título', 'pro-elements' ),
				'type'        => Controls_Manager::TEXT,
				'default'     => esc_html__( 'Título de la diapositiva', 'pro-elements' ),
				'placeholder' => esc_html__( 'Título de la diapositiva', 'pro-elements' ),
				'dynamic'     => array( 'active' => true ),
				'label_block' => true,
			)
		);

		$this->add_control(
			'carousel_name',
			array(
				'label'   => esc_html__( 'Nombre del carrusel', 'pro-elements' ),
				'type'    => Controls_Manager::TEXT,
				'default' => esc_html__( 'Carrusel', 'pro-elements' ),
			)
		);

		$defaults = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$defaults[] = array( 'slide_title' => sprintf( $this->get_default_children_title(), $i ) );
		}
		$this->add_control(
			'carousel_items',
			array(
				'label'              => esc_html__( 'Diapositivas', 'pro-elements' ),
				'type'               => Control_Nested_Repeater::CONTROL_TYPE,
				'fields'             => $repeater->get_controls(),
				'default'            => $defaults,
				'frontend_available' => true,
				'title_field'        => '{{{ slide_title }}}',
				'button_text'        => esc_html__( 'Añadir diapositiva', 'pro-elements' ),
			)
		);

		$this->add_carousel_layout_controls(
			array(
				'css_prefix'                       => 'e-n-carousel-',
				'slides_to_show_custom_settings'   => array(
					'separator'          => 'before',
					'tablet_default'     => '2',
					'mobile_default'     => '1',
					'frontend_available' => true,
					'render_type'        => 'template',
					'selectors'          => array(
						'{{WRAPPER}}' => '--digi-crs-view: {{VALUE}}',
					),
				),
				// PRO (Swiper) sólo aplica en escritorio el paso de escritorio: en los demás dispositivos, sin valor propio, 1.
				'slides_to_scroll_custom_settings' => array(
					'device_args' => array_fill_keys( array( 'widescreen', 'laptop', 'tablet_extra', 'tablet', 'mobile_extra', 'mobile' ), array( 'default' => '1' ) ),
				),
				'equal_height_custom_settings'     => array(
					'selectors' => array(
						'{{WRAPPER}}' => '--digi-crs-align: stretch;',
					),
				),
				'slides_on_display'                => 8,
			)
		);

		$this->end_controls_section();

		$this->add_carousel_settings_controls(
			array(
				'css_prefix'                   => 'e-n-carousel-',
				'autoplay_custom_settings'     => array(
					'description' => esc_html__( 'La reproducción automática no corre mientras editas: mírala en la vista previa. Con ella se muestra el botón «Pausar».', 'pro-elements' ),
				),
				'infinite_custom_settings'     => array(
					'description' => esc_html__( 'Al llegar al final vuelve al principio (y al revés). No corre mientras editas.', 'pro-elements' ),
				),
				'offset_sides_custom_settings' => array(
					'description' => esc_html__( 'Deja asomar las diapositivas vecinas a los lados.', 'pro-elements' ),
				),
			)
		);

		$this->add_carousel_navigation_controls( array( 'css_prefix' => 'e-n-carousel-' ) );

		$this->add_carousel_pagination_controls( array( 'css_prefix' => 'e-n-carousel-' ) );

		$this->start_controls_section(
			'section_slides_style',
			array(
				'label' => esc_html__( 'Diapositivas', 'pro-elements' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'image_spacing_custom',
			array(
				'label'              => esc_html__( 'Separación entre diapositivas', 'pro-elements' ),
				'type'               => Controls_Manager::SLIDER,
				'size_units'         => array( 'px' ),
				'range'              => array(
					'px' => array( 'max' => 400 ),
				),
				'default'            => array( 'size' => 10 ),
				'frontend_available' => true,
				'render_type'        => 'none',
				'selectors'          => array(
					'{{WRAPPER}}' => '--digi-crs-gap: {{SIZE}}{{UNIT}}',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Background::get_type(),
			array(
				'name'           => 'content_background',
				'types'          => array( 'classic', 'gradient' ),
				'exclude'        => array( 'image' ),
				'selector'       => self::SLIDES,
				'fields_options' => array(
					'color' => array( 'label' => esc_html__( 'Color de fondo', 'pro-elements' ) ),
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'           => 'content_border',
				'selector'       => self::SLIDES,
				'fields_options' => array(
					'color' => array( 'label' => esc_html__( 'Color del borde', 'pro-elements' ) ),
					'width' => array( 'label' => esc_html__( 'Ancho del borde', 'pro-elements' ) ),
				),
			)
		);

		$this->add_responsive_control(
			'border_radius',
			array(
				'label'      => esc_html__( 'Radio del borde', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'custom' ),
				'selectors'  => array(
					self::SLIDES => '--border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		// Las variables físicas del contenedor: el núcleo deriva de ellas las lógicas (también en RTL).
		$this->add_responsive_control(
			'content_padding',
			array(
				'label'      => esc_html__( 'Relleno', 'pro-elements' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%', 'em', 'rem', 'vw', 'custom' ),
				'selectors'  => array(
					self::SLIDES => '--padding-top: {{TOP}}{{UNIT}}; --padding-right: {{RIGHT}}{{UNIT}}; --padding-bottom: {{BOTTOM}}{{UNIT}}; --padding-left: {{LEFT}}{{UNIT}};',
				),
				'separator'  => 'before',
			)
		);

		$this->end_controls_section();

		$this->add_carousel_navigation_styling_controls(
			array(
				'css_prefix'                         => 'e-n-carousel-',
				'navigation_styling_custom_settings' => array(
					'condition' => array( 'arrows' => 'yes' ),
				),
			)
		);

		$this->add_carousel_pagination_style_controls( array( 'css_prefix' => 'e-n-carousel-' ) );
	}

	/** Diapositivas que se pintan: filas del repetidor con su contenedor hijo. */
	protected function slide_count( array $settings ): int {
		$rows = isset( $settings['carousel_items'] ) && is_array( $settings['carousel_items'] ) ? count( $settings['carousel_items'] ) : 0;
		return min( $rows, count( (array) $this->get_children() ) );
	}

	/** Argumentos de `Engine::open()` para unos ajustes y un número de diapositivas. */
	public function engine_args( array $settings, int $count ): array {
		$this->num_of_carousel_items = $count;
		$args                        = $this->carousel_args( $settings );
		$args['classes']             = array( 'digi-ncarousel' );
		return $args;
	}

	protected function render() {
		$settings = (array) $this->get_settings_for_display();
		$count    = $this->slide_count( $settings );
		echo Engine::open( $this->engine_args( $settings, $count ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Engine escapa.
		for ( $i = 0; $i < $count; $i++ ) {
			$this->print_child( $i, array( 'count' => $count ) );
		}
		echo Engine::close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constante.
	}

	/**
	 * Pinta el contenedor hijo `$index` con los atributos de diapositiva, por el mismo filtro que usa `nested-tabs` del
	 * núcleo (`elementor/modules/nested-tabs/widgets/nested-tabs.php`): sólo el hijo directo los recibe.
	 *
	 * @param int   $index         Posición del hijo.
	 * @param array $item_settings `count`: total de diapositivas que se pintan.
	 */
	public function print_child( $index, $item_settings = array() ) {
		$children = (array) $this->get_children();
		if ( ! isset( $children[ $index ] ) ) {
			return;
		}
		$child = $children[ $index ];
		$count = isset( $item_settings['count'] ) ? (int) $item_settings['count'] : count( $children );
		$add   = static function ( $should_render, $container = null ) use ( $child, $index, $count ) {
			if ( $container === $child && method_exists( $container, 'add_render_attribute' ) ) {
				$container->add_render_attribute( '_wrapper', Engine::slide_attribute_array( (int) $index, $count ) );
			}
			return $should_render;
		};
		add_filter( 'elementor/frontend/container/should_render', $add, 10, 2 );
		$child->print_element();
		remove_filter( 'elementor/frontend/container/should_render', $add, 10 );
	}

	/** Un nodo por fila en la lista oculta del editor (repetidor mejorado). */
	protected function content_template_single_repeater_item() {
		?>
		<span></span>
		<?php
	}

	/**
	 * Textos de las diapositivas para el script del editor (sin escapar: la plantilla los escapa), los mismos que pone
	 * `Engine::slide_attribute_array()` en el servidor.
	 */
	public static function editor_strings(): array {
		$slide = Engine::slide_attribute_array( 0, 1 );
		return array(
			'slide' => (string) $slide['aria-roledescription'],
			/* translators: 1: número de la diapositiva, 2: total. */
			'of'    => __( '%1$d de %2$d', 'pro-elements' ),
		);
	}

	/**
	 * Plantilla del editor construida con las piezas que devuelve el propio `Engine::open()` (raíz, pausa, flechas,
	 * paginación de cada tipo y pista), con cada atributo variable sustituido por su expresión: así el editor pinta
	 * siempre el marcado del servidor aunque el motor cambie. Las condiciones son las de `carousel_args()`.
	 *
	 * @return string Plantilla underscore (`<# #>`, `{{ }}`, `{{{ }}}`), sin las variables iniciales.
	 */
	public static function editor_markup(): string {
		$base = array(
			'label'               => '@L@',
			'count'               => 2,
			'autoplay'            => 4321,
			'loop'                => true,
			'hover'               => true,
			'interaction'         => true,
			'speed'               => 1234,
			'pagination'          => 'bullets',
			'pagination_position' => 'outside',
			'arrows'              => true,
			'arrows_position'     => 'outside',
			'prev'                => '@PREV@',
			'next'                => '@NEXT@',
			'dir'                 => 'rtl',
			'classes'             => array( 'digi-ncarousel' ),
		);
		$all = Engine::open( $base );
		$own = Engine::open( array( 'prev' => null, 'next' => null ) + $base );
		$pag = static function ( string $html ): string {
			return preg_match( '/class="[^"]*digi-crs__pagination[^"]*"[^>]*>(.*?)<\/div><button[^>]*digi-crs__next/s', $html, $m ) ? $m[1] : '';
		};
		$fraction = $pag( Engine::open( array( 'pagination' => 'fraction', 'count' => 7 ) + $base ) );
		$bar      = $pag( Engine::open( array( 'pagination' => 'progressbar' ) + $base ) );
		$button   = static function ( string $html, string $class ): string {
			return preg_match( '/<button[^>]*class="[^"]*' . $class . '[^"]*"[^>]*>.*?<\/button>/s', $html, $m ) ? $m[0] : '';
		};
		$inner = static function ( string $html ): string {
			return preg_match( '/^<button[^>]*>(.*)<\/button>$/s', $html, $m ) ? $m[1] : '';
		};

		// Raíz: cada atributo con su condición y su valor.
		preg_match( '/^<section ([^>]*)>/', $all, $root );
		preg_match_all( '/([a-zA-Z0-9_:.-]+)="([^"]*)"/', $root[1] ?? '', $attrs, PREG_SET_ORDER );
		$open = '<section';
		foreach ( $attrs as $attr ) {
			list( , $name, $value ) = $attr;
			switch ( $name ) {
				case 'class':
					$tokens = array();
					foreach ( preg_split( '/\s+/', $value ) as $token ) {
						$cond     = array( 'digi-crs--arrows-out' => 'digiArrowsOut', 'digi-crs--pag-out' => 'digiPagOut' )[ $token ] ?? '';
						$quoted   = "'" . preg_replace( '/[^A-Za-z0-9_-]/', '', $token ) . "'";
						$tokens[] = '' !== $cond ? $cond . ' ? ' . $quoted . " : ''" : $quoted;
					}
					$open .= ' class="{{ [ ' . implode( ', ', $tokens ) . " ].filter( Boolean ).join( ' ' ) }}\"";
					break;
				case 'aria-label':
					$open .= ' aria-label="{{ digiLabel }}"';
					break;
				case 'data-digi-autoplay':
					$open .= '<# if ( digiDelay ) { #> data-digi-autoplay="{{ digiDelay }}"<# } #>';
					break;
				case 'data-digi-hover':
					$open .= "<# if ( digiDelay && 'yes' === digiS.pause_on_hover ) { #> data-digi-hover=\"" . $value . '"<# } #>';
					break;
				case 'data-digi-interaction':
					$open .= "<# if ( digiDelay && 'yes' === digiS.pause_on_interaction ) { #> data-digi-interaction=\"" . $value . '"<# } #>';
					break;
				case 'data-digi-loop':
					$open .= "<# if ( 'yes' === digiS.infinite ) { #> data-digi-loop=\"" . $value . '"<# } #>';
					break;
				case 'data-digi-pagination':
					$open .= '<# if ( digiPag ) { #> data-digi-pagination="{{ digiPag }}"<# } #>';
					break;
				case 'style':
					// `--digi-crs-n` (número de diapositivas): el de la plantilla de base (2) pasa a la cuenta real.
					$open .= ' style="' . str_replace( array( '1234', '--digi-crs-n:2' ), array( '{{ digiSpeed }}', '--digi-crs-n:{{ digiCount }}' ), $value ) . '"';
					break;
				default:
					$open .= ' ' . $name . '="' . $value . '"';
			}
		}
		$open .= '>';

		// Puntos: el segundo, con su número como expresión; el primero lleva además lo que lo marca como actual.
		preg_match( '/<div[^>]*class="[^"]*digi-crs__pagination[^"]*"[^>]*>/', $all, $pag_open );
		preg_match_all( '/<button[^>]*class="[^"]*digi-crs__dot[^"]*"[^>]*><\/button>/', $all, $dots );
		$dot     = $dots[0][1] ?? '';
		$pattern = preg_match( '/data-digi-dot="([^"]*)"/', $pag_open[0] ?? '', $m ) ? $m[1] : '';
		$label   = esc_attr( sprintf( html_entity_decode( $pattern, ENT_QUOTES, 'UTF-8' ), 2 ) );
		$dot     = str_replace( 'aria-label="' . $label . '"', 'aria-label="' . str_replace( '%d', '{{ digiI }}', $pattern ) . '"', $dot );
		$current = isset( $dots[0][0] ) && preg_match( '/\s(aria-current="[^"]*")/', $dots[0][0], $c ) ? $c[1] : '';
		if ( '' !== $current ) {
			$dot = preg_replace( '/><\/button>$/', '<# if ( 1 === digiI ) { #> ' . $current . '<# } #>></button>', $dot );
		}

		// Pista.
		preg_match( '/<div[^>]*class="[^"]*digi-crs__track[^"]*"[^>]*>$/', $all, $track );
		$track = (string) ( $track[0] ?? '<div class="digi-crs__track">' );
		$track = preg_replace( '/ aria-live="[^"]*"/', ' aria-live="<# if ( digiDelay ) { #>off<# } else { #>polite<# } #>"', $track );
		$track = preg_replace( '/ dir="[^"]*"/', "<# if ( 'ltr' === digiS.direction || 'rtl' === digiS.direction ) { #> dir=\"{{ digiS.direction }}\"<# } #>", $track );

		$prev = $button( $all, 'digi-crs__prev' );
		$next = $button( $all, 'digi-crs__next' );
		return $open
			. '<# if ( digiMany && digiDelay ) { #>' . $button( $all, 'digi-crs__pause' ) . '<# } #>'
			. '<# if ( digiMany && digiArrows ) { #>' . str_replace( '@PREV@', '{{{ digiIcon( digiS.navigation_previous_icon, \'eicon-chevron-left\', ' . wp_json_encode( $inner( $button( $own, 'digi-crs__prev' ) ) ) . ' ) }}}', $prev ) . '<# } #>'
			. '<# if ( digiMany && digiPag ) { #>' . ( $pag_open[0] ?? '' )
			. "<# if ( 'bullets' === digiPag ) { for ( var digiI = 1; digiI <= digiCount; digiI++ ) { #>" . $dot . '<# } } '
			. "else if ( 'fraction' === digiPag ) { #>" . str_replace( '>7</span>', '>{{ digiCount }}</span>', $fraction ) . '<# } '
			. 'else { #>' . $bar . '<# } #></div><# } #>'
			. '<# if ( digiMany && digiArrows ) { #>' . str_replace( '@NEXT@', '{{{ digiIcon( digiS.navigation_next_icon, \'eicon-chevron-right\', ' . wp_json_encode( $inner( $button( $own, 'digi-crs__next' ) ) ) . ' ) }}}', $next ) . '<# } #>'
			. $track . '</div></section>';
	}

	/**
	 * Plantilla del editor: el marcado de `Engine::open()` (`editor_markup()`) con los mismos argumentos que
	 * `carousel_args()` (los contenedores hijos los coloca el editor en la pista), más la lista oculta del repetidor
	 * mejorado con los textos de las diapositivas.
	 */
	protected function content_template() {
		$t = self::editor_strings();
		?>
		<#
		var digiS = settings,
			digiItems = _.isArray( digiS.carousel_items ) ? digiS.carousel_items : [],
			digiCount = digiItems.length,
			digiMany = digiCount > 1,
			digiDelay = 'yes' === digiS.autoplay ? Math.max( 0, parseInt( undefined === digiS.autoplay_speed ? 5000 : digiS.autoplay_speed, 10 ) || 0 ) : 0,
			digiSpeed = Math.max( 0, parseInt( undefined === digiS.speed ? 500 : digiS.speed, 10 ) || 0 ),
			digiPag = _.contains( [ 'bullets', 'fraction', 'progressbar' ], digiS.pagination ) ? digiS.pagination : '',
			digiPagOut = ( 'bullets' === digiPag && 'outside' === ( undefined === digiS.dots_position ? 'outside' : digiS.dots_position ) ) || ( 'fraction' === digiPag && 'outside' === ( undefined === digiS.fraction_position ? 'outside' : digiS.fraction_position ) ),
			digiArrows = 'yes' === digiS.arrows,
			digiArrowsOut = digiArrows && 'outside' === digiS.arrows_position,
			digiLabel = _.isString( digiS.carousel_name ) && digiS.carousel_name.trim() ? digiS.carousel_name.trim() : '<?php echo esc_js( __( 'Carrusel', 'pro-elements' ) ); ?>',
			digiIcon = function( icon, fallback, own ) {
				if ( ! icon || ! _.isObject( icon ) || fallback === ( undefined === icon.value ? fallback : icon.value ) ) {
					return own;
				}
				if ( ! icon.value ) {
					return '';
				}
				var rendered = elementor.helpers.renderIcon( view, icon, { 'aria-hidden': true }, 'i', 'object' );
				return rendered && rendered.value ? rendered.value : '';
			};
		#>
		<?php echo self::editor_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado de Engine, ya escapado; las expresiones se escapan con {{ }}. ?>
		<div class="digi-ncarousel__items" data-digi-slide="<?php echo esc_attr( $t['slide'] ); ?>" data-digi-of="<?php echo esc_attr( $t['of'] ); ?>" hidden><# _.each( digiItems, function() { #><span></span><# } ); #></div>
		<?php
	}
}
