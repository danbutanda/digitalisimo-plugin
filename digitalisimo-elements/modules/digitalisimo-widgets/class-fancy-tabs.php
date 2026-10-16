<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-widget-alias.php';

/**
 * Pestañas accesibles (navegación por teclado, panel inicial visible y contenido legible sin JavaScript).
 * Cada pestaña lleva icono o imagen, título, subtítulo y botón, y su panel muestra texto, una plantilla
 * publicada o un elemento de la página por su ID. Dos estilos: tarjetas con icono en rejilla o botones en
 * línea. Reúne Pestañas destacadas y Alternador de contenido, que sigue registrado como alias oculto de
 * éste (misma clase PHP, mismo nombre y mismas clases CSS) para el contenido ya guardado.
 */
class Fancy_Tabs_Widget extends \Elementor\Widget_Base {
	use Legacy_Classes;

	const CSS_PREFIX = 'digi-fancy-tabs';
	const MAX_TABS   = 12;

	private static $fallback_printed = false;
	private static $rendering_templates = array();
	private static $template_options = array();

	public function get_name() { return 'digitalisimo-fancy-tabs'; }
	public function get_title() { return 'Pestañas'; }
	public function get_icon() { return 'eicon-tabs'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'pestañas', 'tabs', 'iconos', 'alternador', 'switcher', 'contenido' ); }
	public function get_style_depends() { return array( 'digitalisimo-fancy-tabs' ); }
	public function get_script_depends() { return array( 'digitalisimo-content-switcher' ); }

	private static function template_options() {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() || ! function_exists( 'post_type_exists' ) || ! post_type_exists( 'elementor_library' ) ) { return array(); }
		$key = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1;
		if ( ! isset( self::$template_options[ $key ] ) ) {
			self::$template_options[ $key ] = array();
			foreach ( get_posts( array( 'post_type' => 'elementor_library', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true ) ) as $post ) {
				self::$template_options[ $key ][ $post->ID ] = get_the_title( $post->ID );
			}
		}
		return self::$template_options[ $key ];
	}

	/** Plantilla publicada de la biblioteca, sin repetirse dentro de sí misma. */
	private static function template_content( $template_id ) {
		$id = absint( $template_id );
		if ( ! $id || 'elementor_library' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || isset( self::$rendering_templates[ $id ] ) || ! class_exists( '\\Elementor\\Plugin' ) ) { return ''; }
		self::$rendering_templates[ $id ] = true;
		try { return (string) \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $id, true ); }
		finally { unset( self::$rendering_templates[ $id ] ); }
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_tabs', array( 'label' => 'Pestañas' ) );
		$this->add_control( 'style', array( 'label' => 'Estilo', 'type' => $c::SELECT, 'default' => 'cards', 'options' => array( 'cards' => 'Tarjetas con icono', 'switcher' => 'Botones en línea' ) ) );
		$repeater = new \Elementor\Repeater();
		$repeater->add_control( 'tab_title', array( 'label' => 'Título', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ), 'label_block' => true, 'default' => 'Pestaña' ) );
		$repeater->add_control( 'tab_sub_title', array( 'label' => 'Subtítulo', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'icon_type', array( 'label' => 'Icono', 'type' => $c::SELECT, 'default' => 'icon', 'options' => array( 'icon' => 'Icono', 'image' => 'Imagen', 'none' => 'Ninguno' ) ) );
		$repeater->add_control( 'selected_icon', array( 'label' => 'Icono', 'type' => $c::ICONS, 'condition' => array( 'icon_type' => 'icon' ) ) );
		$repeater->add_control( 'image', array( 'label' => 'Imagen', 'type' => $c::MEDIA, 'dynamic' => array( 'active' => true ), 'condition' => array( 'icon_type' => 'image' ) ) );
		$repeater->add_control( 'content_type', array( 'label' => 'Contenido del panel', 'type' => $c::SELECT, 'default' => 'content', 'options' => array( 'content' => 'Texto', 'template' => 'Plantilla guardada', 'link_section' => 'Sección o widget de la página' ) ) );
		$repeater->add_control( 'tab_content', array( 'label' => 'Contenido', 'type' => $c::WYSIWYG, 'dynamic' => array( 'active' => true ), 'condition' => array( 'content_type' => 'content' ) ) );
		$repeater->add_control( 'template_id', array( 'label' => 'Plantilla publicada', 'type' => $c::SELECT2, 'options' => self::template_options(), 'condition' => array( 'content_type' => 'template' ) ) );
		$repeater->add_control( 'link_target', array( 'label' => 'ID CSS del elemento', 'type' => $c::TEXT, 'placeholder' => 'mi-seccion', 'description' => 'El elemento con ese ID se muestra sólo con esta pestaña activa. Sin JavaScript todos permanecen visibles.', 'condition' => array( 'content_type' => 'link_section' ) ) );
		$repeater->add_control( 'tabs_button', array( 'label' => 'Botón', 'type' => $c::TEXT, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'button_link', array( 'label' => 'Enlace del botón', 'type' => $c::URL, 'dynamic' => array( 'active' => true ) ) );
		$repeater->add_control( 'active', array( 'label' => 'Activa al cargar', 'type' => $c::SWITCHER, 'return_value' => 'yes' ) );
		$this->add_control( 'tabs', array( 'label' => 'Pestañas', 'type' => $c::REPEATER, 'fields' => $repeater->get_controls(), 'default' => array( array( 'tab_title' => 'Primera', 'tab_content' => 'Contenido de la primera pestaña.' ), array( 'tab_title' => 'Segunda', 'tab_content' => 'Contenido de la segunda pestaña.' ) ), 'title_field' => '{{{ tab_title }}}' ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Columnas de pestañas', 'type' => $c::SELECT, 'default' => '2', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => array_combine( range( 1, 6 ), range( 1, 6 ) ), 'condition' => array( 'style' => 'cards' ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-tabs__tabs' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'active_color', array( 'label' => 'Color activo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-tabs__tab[aria-selected="true"]' => 'color:{{VALUE}};' ) ) );
		$this->add_control( 'active_background', array( 'label' => 'Fondo activo', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-fancy-tabs__tab[aria-selected="true"]' => 'background-color:{{VALUE}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => 'Separación', 'type' => $c::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 80 ) ), 'selectors' => array( '{{WRAPPER}} .digi-fancy-tabs__tabs' => 'gap:{{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	private static function target( array $item ) {
		return 'link_section' === ( $item['content_type'] ?? 'content' ) ? sanitize_html_class( ltrim( trim( (string) ( $item['link_target'] ?? '' ) ), '#' ) ) : '';
	}

	/** Pestañas con título, hasta doce. */
	public static function tabs( array $settings ) {
		$tabs = array_values( array_filter( is_array( $settings['tabs'] ?? null ) ? $settings['tabs'] : array(), static function ( $item ) { return is_array( $item ) && '' !== trim( wp_strip_all_tags( (string) ( $item['tab_title'] ?? '' ) ) ); } ) );
		return array_slice( $tabs, 0, self::MAX_TABS );
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$tabs = self::tabs( $s );
		if ( ! $tabs ) { return; }
		$style    = 'switcher' === ( $s['style'] ?? 'cards' ) ? 'switcher' : 'cards';
		$selected = 0;
		foreach ( $tabs as $index => $item ) { if ( 'yes' === ( $item['active'] ?? '' ) ) { $selected = $index; break; } }
		$id = 'digi-fancy-tabs-' . sanitize_html_class( (string) $this->get_id() );
		echo '<div class="' . esc_attr( $this->cls( '', array( $style ) ) ) . '" data-digi-content-switcher data-digi-selected="' . esc_attr( (string) $selected ) . '"><div class="' . esc_attr( $this->cls( 'tabs' ) ) . '" role="tablist" aria-label="Pestañas">';
		foreach ( $tabs as $index => $item ) {
			$active = $selected === $index;
			echo '<button class="' . esc_attr( $this->cls( 'tab' ) ) . '" type="button" id="' . esc_attr( $id . '-tab-' . $index ) . '" role="tab" aria-controls="' . esc_attr( $id . '-panel-' . $index ) . '" aria-selected="' . ( $active ? 'true' : 'false' ) . '" tabindex="' . ( $active ? '0' : '-1' ) . '">';
			$type = $item['icon_type'] ?? 'icon';
			if ( 'icon' === $type && ! empty( $item['selected_icon']['value'] ) ) { echo '<span class="' . esc_attr( $this->cls( 'icon' ) ) . '" aria-hidden="true">'; \Elementor\Icons_Manager::render_icon( $item['selected_icon'], array( 'aria-hidden' => 'true' ) ); echo '</span>'; }
			elseif ( 'image' === $type && ! empty( $item['image']['url'] ) ) {
				$image = $item['image']; $image_id = absint( $image['id'] ?? 0 );
				$markup = $image_id ? wp_get_attachment_image( $image_id, 'thumbnail', false, array( 'class' => $this->cls( 'icon' ), 'alt' => '' ) ) : '';
				if ( ! $markup ) { $markup = '<img class="' . esc_attr( $this->cls( 'icon' ) ) . '" src="' . esc_url( $image['url'] ) . '" alt="">'; }
				echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- imagen de WordPress o escapada.
			}
			echo '<span class="' . esc_attr( $this->cls( 'label' ) ) . '"><strong>' . esc_html( wp_strip_all_tags( $item['tab_title'] ) ) . '</strong>';
			$subtitle = trim( wp_strip_all_tags( (string) ( $item['tab_sub_title'] ?? '' ) ) );
			if ( $subtitle ) { echo '<span>' . esc_html( $subtitle ) . '</span>'; }
			echo '</span></button>';
		}
		echo '</div><div class="' . esc_attr( $this->cls( 'panels' ) ) . '">';
		foreach ( $tabs as $index => $item ) {
			$type   = $item['content_type'] ?? 'content';
			$target = self::target( $item );
			echo '<div class="' . esc_attr( $this->cls( 'panel' ) ) . '" id="' . esc_attr( $id . '-panel-' . $index ) . '" role="tabpanel" aria-labelledby="' . esc_attr( $id . '-tab-' . $index ) . '" tabindex="0"' . ( '' !== $target ? ' data-digi-target="' . esc_attr( $target ) . '"' : '' ) . ( $selected !== $index ? ' hidden' : '' ) . '>';
			if ( 'template' === $type ) { echo self::template_content( $item['template_id'] ?? 0 ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor renderiza la plantilla publicada.
			elseif ( 'link_section' !== $type && ! empty( $item['tab_content'] ) ) { echo wp_kses_post( method_exists( $this, 'parse_text_editor' ) ? $this->parse_text_editor( (string) $item['tab_content'] ) : (string) $item['tab_content'] ); }
			$button = trim( wp_strip_all_tags( (string) ( $item['tabs_button'] ?? '' ) ) );
			$link   = is_array( $item['button_link'] ?? null ) ? $item['button_link'] : array();
			if ( $button && ! empty( $link['url'] ) ) { $key = 'fancy_tab_button_' . $index; $this->add_link_attributes( $key, $link ); if ( ! empty( $link['is_external'] ) ) { $this->add_render_attribute( $key, 'rel', array( 'noopener', 'noreferrer' ) ); } echo '<a class="' . esc_attr( $this->cls( 'button' ) ) . '" ' . $this->get_render_attribute_string( $key ) . '>' . esc_html( $button ) . '</a>'; }
			echo '</div>';
		}
		echo '</div></div>';
		if ( ! self::$fallback_printed ) { echo '<noscript><style>.digi-fancy-tabs__panel[hidden]{display:block!important}</style></noscript>'; self::$fallback_printed = true; }
	}

	protected function content_template() {
		?>
		<# <?php echo $this->cls_template_js(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON de clases fijas. ?>
		var tabs = ( settings.tabs || [] ).filter( function ( item ) { return item && String( item.tab_title || '' ).trim(); } ).slice( 0, 12 );
		var style = settings.style === 'switcher' ? 'switcher' : 'cards';
		var id = 'digi-fancy-tabs-' + view.model.id; var selected = 0; _.find( tabs, function ( item, index ) { if ( item.active === 'yes' ) { selected = index; return true; } } );
		var linkRel = function ( link ) { return [ link && link.nofollow ? 'nofollow' : '', link && link.is_external ? 'noopener noreferrer' : '' ].filter( Boolean ).join( ' ' ); }; #>
		<# if ( tabs.length ) { #><div class="{{ cls( '', [style] ) }}" data-digi-content-switcher data-digi-selected="{{ selected }}"><div class="{{ cls( 'tabs' ) }}" role="tablist" aria-label="Pestañas"><# _.each( tabs, function ( item, index ) { var icon = elementor.helpers.renderIcon( view, item.selected_icon, { 'aria-hidden': true }, 'i', 'object' ); #><button class="{{ cls( 'tab' ) }}" type="button" id="{{ id }}-tab-{{ index }}" role="tab" aria-controls="{{ id }}-panel-{{ index }}" aria-selected="{{ index === selected ? 'true' : 'false' }}" tabindex="{{ index === selected ? '0' : '-1' }}"><# if ( item.icon_type !== 'image' && item.icon_type !== 'none' && icon && icon.rendered ) { #><span class="{{ cls( 'icon' ) }}" aria-hidden="true">{{{ icon.value }}}</span><# } else if ( item.icon_type === 'image' && item.image && item.image.url ) { #><img class="{{ cls( 'icon' ) }}" src="{{ item.image.url }}" alt=""><# } #><span class="{{ cls( 'label' ) }}"><strong>{{ item.tab_title }}</strong><# if ( item.tab_sub_title ) { #><span>{{ item.tab_sub_title }}</span><# } #></span></button><# } ); #></div><div class="{{ cls( 'panels' ) }}"><# _.each( tabs, function ( item, index ) { var link = item.button_link || {}; var rel = linkRel( link ); var type = item.content_type || 'content'; #><div class="{{ cls( 'panel' ) }}" id="{{ id }}-panel-{{ index }}" role="tabpanel" aria-labelledby="{{ id }}-tab-{{ index }}" tabindex="0"<# if ( type === 'link_section' && item.link_target ) { #> data-digi-target="{{ String( item.link_target ).replace( /^#/, '' ) }}"<# } #><# if ( index !== selected ) { #> hidden<# } #>><# if ( type === 'template' ) { #><p>Plantilla guardada #{{ item.template_id }}</p><# } else if ( type === 'link_section' ) { #><p>Muestra el elemento #{{ item.link_target }} de la página.</p><# } else { #>{{{ item.tab_content || '' }}}<# } #><# if ( item.tabs_button && link.url ) { #><a class="{{ cls( 'button' ) }}" href="{{ link.url }}"<# if ( link.is_external ) { #> target="_blank"<# } #><# if ( rel ) { #> rel="{{ rel }}"<# } #>>{{ item.tabs_button }}</a><# } #></div><# } ); #></div></div><# } #>
		<?php
	}
}
