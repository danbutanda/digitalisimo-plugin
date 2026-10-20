<?php
/**
 * Paso de la cabecera y el pie dinámicos de Hello a plantillas del constructor de temas: el árbol reproduce los valores
 * por defecto y los ajustes del kit de Hello 3.5.1, las condiciones se guardan en el formato que espera el gestor real,
 * la huella se toma del JSON releído tras guardar, dos conversiones nunca crean dos plantillas, y revertir y volver
 * retira y restaura las plantillas sin pisar lo que el usuario editó.
 */
namespace Elementor {
	class Plugin { public static $instance; public $documents; public $widgets_manager; public $files_manager; }
	class Compatibility { public static $cleared = 0; public static function clear_3rd_party_cache() { self::$cleared++; } }
}
namespace ElementorPro\Modules\ThemeBuilder {
	class Module {
		private static $instance;
		public $conditions;
		public static function instance() { return self::$instance ? self::$instance : ( self::$instance = new self() ); }
		public function get_conditions_manager() { return $this->conditions ? $this->conditions : ( $this->conditions = new \Fake_Conditions() ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$GLOBALS['t'] = array( 'options' => array(), 'autoload' => array(), 'posts' => array(), 'meta' => array(), 'next' => 100, 'user' => 0, 'caps' => array( 1 => true, 2 => false ), 'template' => 'digitalisimo-theme', 'admin' => true, 'cron' => false, 'scheduled' => array(), 'locations' => array( 'menu-1' => 7, 'menu-2' => 8 ), 'menus' => array( 7 => 'principal', 8 => 'pie' ), 'logo' => false, 'name' => 'Mi sitio', 'tagline' => 'Un lema' );
	function check_hf( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
	function add_action() {} function add_filter() {}
	function get_template() { return $GLOBALS['t']['template']; }
	function is_admin() { return $GLOBALS['t']['admin']; }
	function wp_doing_cron() { return $GLOBALS['t']['cron']; }
	function wp_doing_ajax() { return false; }
	function get_option( $name, $default = false ) { return array_key_exists( $name, $GLOBALS['t']['options'] ) ? $GLOBALS['t']['options'][ $name ] : $default; }
	function update_option( $name, $value, $autoload = null ) { $GLOBALS['t']['options'][ $name ] = $value; $GLOBALS['t']['autoload'][ $name ] = $autoload; return true; }
	function wp_cache_delete() { return true; }
	function wp_next_scheduled( $hook ) { return isset( $GLOBALS['t']['scheduled'][ $hook ] ) ? 1 : false; }
	function wp_schedule_single_event( $time, $hook ) { $GLOBALS['t']['scheduled'][ $hook ] = ( $GLOBALS['t']['scheduled'][ $hook ] ?? 0 ) + 1; return true; }
	function get_current_user_id() { return $GLOBALS['t']['user']; }
	function wp_set_current_user( $id ) { $GLOBALS['t']['user'] = (int) $id; }
	function user_can( $user, $cap ) { return ! empty( $GLOBALS['t']['caps'][ (int) $user ] ); }
	function current_user_can( $cap ) { return user_can( $GLOBALS['t']['user'], $cap ); }
	function wp_generate_password( $length = 12, $special = true ) { return substr( str_repeat( md5( (string) mt_rand() ), 2 ), 0, $length ); }
	function get_current_blog_id() { return 1; }
	function is_multisite() { return false; }
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	class WP_Error { public $code; public function __construct( $code = '', $message = '' ) { $this->code = $code; } }
	function __( $text, $domain = 'default' ) { return $text; }
	function wp_kses_post( $html ) { return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html ); }
	function get_bloginfo( $what ) { return 'name' === $what ? $GLOBALS['t']['name'] : $GLOBALS['t']['tagline']; }
	function has_custom_logo() { return $GLOBALS['t']['logo']; }
	function get_nav_menu_locations() { return $GLOBALS['t']['locations']; }
	function wp_get_nav_menu_object( $id ) { return isset( $GLOBALS['t']['menus'][ $id ] ) ? (object) array( 'term_id' => $id, 'slug' => $GLOBALS['t']['menus'][ $id ] ) : false; }
	function wp_get_nav_menu_items( $id ) { return isset( $GLOBALS['t']['menus'][ $id ] ) ? array( (object) array( 'ID' => 1 ) ) : array(); }
	function wp_slash( $value ) { return $value; }
	function get_post( $id ) { return $GLOBALS['t']['posts'][ (int) $id ] ?? null; }
	function get_the_title( $post ) { return is_object( $post ) ? $post->post_title : ''; }
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['t']['meta'][ (int) $id ][ $key ] ?? ''; }
	function update_post_meta( $id, $key, $value ) { $GLOBALS['t']['meta'][ (int) $id ][ $key ] = $value; return true; }
	function wp_insert_post( array $data ) {
		$id = $GLOBALS['t']['next']++;
		$GLOBALS['t']['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => $data['post_type'] ?? 'post', 'post_status' => $data['post_status'] ?? 'draft', 'post_title' => $data['post_title'] ?? '', 'post_author' => (int) ( $data['post_author'] ?? 0 ) );
		foreach ( (array) ( $data['meta_input'] ?? array() ) as $key => $value ) { update_post_meta( $id, $key, $value ); }
		return $id;
	}
	function wp_update_post( array $data ) { $GLOBALS['t']['posts'][ (int) $data['ID'] ]->post_status = $data['post_status']; $GLOBALS['t']['updates'][] = array( (int) $data['ID'], $data['post_status'] ); return (int) $data['ID']; }
	function wp_trash_post( $id ) { $GLOBALS['t']['meta'][ $id ]['_wp_trash_meta_status'] = $GLOBALS['t']['posts'][ $id ]->post_status; $GLOBALS['t']['posts'][ $id ]->post_status = 'trash'; return true; }
	function wp_untrash_post( $id ) { $GLOBALS['t']['posts'][ $id ]->post_status = 'draft'; return true; } // WordPress restaura como borrador (wp_untrash_post_status).
	function get_posts( array $args ) {
		$out = array();
		foreach ( $GLOBALS['t']['posts'] as $post ) {
			if ( $post->post_type === $args['post_type'] && in_array( $post->post_status, (array) $args['post_status'], true ) && ( $GLOBALS['t']['meta'][ $post->ID ][ $args['meta_key'] ] ?? null ) === $args['meta_value'] ) {
				$out[] = $post;
			}
		}
		return array_slice( $out, 0, $args['posts_per_page'] );
	}

	/** Gestor de condiciones: el bucle de `save_conditions()` copiado de modules/theme-builder/classes/conditions-manager.php. */
	class Fake_Conditions {
		public $regenerated = 0;
		public function save_conditions( $post_id, $conditions ) {
			$conditions_to_save = array();
			foreach ( $conditions as $condition ) {
				unset( $condition['_id'] );
				$conditions_to_save[] = rtrim( implode( '/', $condition ), '/' );
			}
			update_post_meta( $post_id, '_elementor_conditions', $conditions_to_save );
			$this->regenerated++;
			return true;
		}
		public function get_cache() { return $this; }
		public function regenerate() { $this->regenerated++; }
	}
	/** Documento: save() exige un usuario que pueda editar y normaliza el árbol (como el real) antes de guardarlo. */
	class Fake_Document {
		public $id;
		public function __construct( $id ) { $this->id = $id; }
		public function get_main_id() { return $this->id; }
		public function save( $data ) {
			if ( ! current_user_can( 'edit_post' ) ) { return false; }
			if ( isset( $data['elements'] ) ) {
				$normalize = static function ( array $elements ) use ( &$normalize ) {
					$out = array();
					foreach ( $elements as $element ) {
						ksort( $element['settings'] );
						$element['elements'] = $normalize( $element['elements'] );
						$out[] = array( 'id' => $element['id'], 'elType' => $element['elType'] ) + $element;
					}
					return $out;
				};
				update_post_meta( $this->id, '_elementor_data', json_encode( $normalize( $data['elements'] ), JSON_PRETTY_PRINT ) );
			}
			return true;
		}
	}
	class Fake_Documents {
		public $created = array();
		public function create( $type, $post = array(), $meta = array() ) {
			$meta['_elementor_template_type'] = $type;
			$id = wp_insert_post( $post + array( 'post_type' => 'elementor_library', 'meta_input' => $meta ) );
			$this->created[] = array( $type, $post, $meta );
			$document = new Fake_Document( $id );
			$document->save( array() );
			return $document;
		}
	}
	class Fake_Widgets { public $missing = array(); public function get_widget_types( $type ) { return in_array( $type, $this->missing, true ) ? null : new stdClass(); } }
	class Fake_Files { public $cleared = 0; public function clear_cache() { $this->cleared++; } }
	/** $wpdb mínimo: sólo las cuatro consultas del cerrojo. */
	class Fake_Wpdb {
		public $options = 'wp_options';
		public $rows = array();
		public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
		public function esc_like( $text ) { return addcslashes( $text, '_%\\' ); }
		public function get_var( $query ) { return $this->rows[ $query[1][0] ] ?? null; }
		public function query( $query ) {
			list( $sql, $args ) = $query;
			if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) {
				if ( isset( $this->rows[ $args[0] ] ) ) { return 0; }
				$this->rows[ $args[0] ] = $args[1];
				return 1;
			}
			if ( 0 === strpos( $sql, 'UPDATE' ) ) {
				if ( ( $this->rows[ $args[1] ] ?? null ) !== $args[2] ) { return 0; }
				$this->rows[ $args[1] ] = $args[0];
				return 1;
			}
			if ( 0 === strpos( $sql, 'DELETE' ) ) {
				$suffix = stripslashes( substr( $args[1], 1 ) );
				if ( isset( $this->rows[ $args[0] ] ) && substr( $this->rows[ $args[0] ], -strlen( $suffix ) ) === $suffix ) { unset( $this->rows[ $args[0] ] ); return 1; }
				return 0;
			}
			throw new RuntimeException( 'Consulta inesperada: ' . $sql );
		}
	}
	$GLOBALS['wpdb'] = new Fake_Wpdb();
	\Elementor\Plugin::$instance = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->documents = new Fake_Documents();
	\Elementor\Plugin::$instance->widgets_manager = new Fake_Widgets();
	\Elementor\Plugin::$instance->files_manager = new Fake_Files();

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-theme/class-module.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-theme/class-hello-header-footer.php';
	use Digitalisimo\Elements\Hello_Header_Footer as HF;
	use Digitalisimo\Elements\Theme_Compat;
	use ElementorPro\Modules\ThemeBuilder\Module as TB;

	$widgets = static function ( array $tree ) {
		$found = array();
		$walk  = static function ( array $elements ) use ( &$walk, &$found ) {
			foreach ( $elements as $element ) {
				if ( 'widget' === $element['elType'] ) { $found[ $element['widgetType'] ] = $element['settings']; }
				$walk( $element['elements'] );
			}
		};
		$walk( $tree );
		return $found;
	};
	$ctx = array( 'has_logo' => true, 'site_name' => 'Mi sitio', 'tagline' => 'Un lema', 'menu' => 'principal', 'copyright' => 'All rights reserved', 'link' => array(), 'link_hover' => array(), 'reset_css' => true, 'theme_css' => true );

	// 1. Valores por defecto: logo (o título), lema y menú, en caja y en fila, con la escalera de anchos de Hello.
	$tree = HF::build( 'header', array(), $ctx );
	check_hf( 1 === count( $tree ) && 'container' === $tree[0]['elType'] && 'boxed' === $tree[0]['settings']['content_width'] && 'row' === $tree[0]['settings']['flex_direction'] && 'space-between' === $tree[0]['settings']['flex_justify_content'], 'Por defecto la cabecera es un contenedor en caja, en fila y repartido.' );
	$w = $widgets( $tree );
	check_hf( array( 'theme-site-logo', 'heading', 'nav-menu' ) === array_keys( $w ), 'Con logo, la cabecera lleva el logo, el lema y el menú: ' . implode( ',', array_keys( $w ) ) );
	check_hf( 'p' === $w['heading']['header_size'] && false !== strpos( $w['heading']['__dynamic__']['title'], 'name="site-tagline"' ) && 'none' === $w['nav-menu']['pointer'] && 'principal' === $w['nav-menu']['menu'] && 'tablet' === $w['nav-menu']['dropdown'], 'El lema es un párrafo con la etiqueta del lema y el menú no lleva puntero.' );
	check_hf( '#c36' === ( $w['nav-menu']['color_menu_item_active'] ?? '' ) && false !== strpos( $w['nav-menu']['custom_css'], 'elementor-item-active:hover,selector .elementor-nav-menu--main .elementor-item.elementor-item-active:focus{color:#336;' ), 'El enlace de la página actual conserva el color normal de Hello y el del ratón sólo al pasar por encima.' );
	check_hf( false !== strpos( $w['theme-site-logo']['__dynamic__']['image'], 'name="site-logo"' ) && 'site_url' === $w['theme-site-logo']['link_to'], 'El logo es el del sitio y enlaza al inicio.' );
	$css = $tree[0]['settings']['custom_css'];
	foreach ( array( 576 => 500, 768 => 600, 992 => 800, 1200 => 1140 ) as $min => $max ) {
		check_hf( false !== strpos( $css, '@media(min-width:' . $min . 'px){selector .e-con-inner{max-width:' . $max . 'px}}' ), "Falta el ancho {$max}px a partir de {$min}px." );
	}
	check_hf( 4 === substr_count( $css, '@media(min-width:' ) && false !== strpos( $css, 'selector{padding-block:0}' ) && false === strpos( $css, '>' ), 'El CSS del contenedor lleva los cuatro @media, anula el relleno de .site-header y no usa «>» (kses lo convertiría).' );
	$padding = $tree[0]['settings']['padding'];
	check_hf( 'custom' === $padding['unit'] && '1rem' === $padding['top'] && '1rem' === $padding['bottom'] && '0px' === $padding['left'] && '0px' === $padding['right'], 'Relleno de 1rem arriba y abajo y sin márgenes laterales.' );
	$branding = $tree[0]['elements'][0];
	check_hf( 'container' === $branding['elType'] && $branding['isInner'] && 'column' === $branding['settings']['flex_direction'] && 'full' === $branding['settings']['content_width'] && '0.5' === $branding['settings']['flex_gap']['row'], 'La marca va en una columna con el espacio de .5rem de Hello.' );
	$w = $widgets( HF::build( 'header', array(), array( 'has_logo' => false ) + $ctx ) );
	check_hf( isset( $w['theme-site-title'] ) && ! isset( $w['theme-site-logo'] ) && 'div' === $w['theme-site-title']['header_size'], 'Sin logo, el tipo por defecto es el título, en un div.' );
	$title = $w['theme-site-title'];
	check_hf( 2.5 === $title['typography_font_size']['size'] && 'rem' === $title['typography_font_size']['unit'] && '500' === $title['typography_font_weight'] && 1.2 === $title['typography_line_height']['size'] && '' === $title['__globals__']['typography_typography'], 'El título usa la tipografía explícita de .site-title (2.5rem, 500, 1.2), sin globales.' );
	check_hf( '#c36' === $title['title_color'] && '#336' === $title['title_hover_color'] && false !== strpos( $title['custom_css'], 'font-family:inherit' ), 'Sin color de enlace en el kit, el título toma el de reset.css y hereda la fuente del cuerpo.' );
	check_hf( false !== strpos( $title['__dynamic__']['title'], 'name="site-title"' ) && false !== strpos( $title['__dynamic__']['link'], 'name="site-url"' ) && 'rel|home' === $title['link']['custom_attributes'], 'El título es el del sitio y enlaza al inicio con rel="home".' );
	$fw = $widgets( HF::build( 'footer', array(), array( 'has_logo' => false ) + $ctx ) );
	check_hf( array( 'heading', 'nav-menu', 'text-editor' ) === array_keys( $fw ), 'En el pie el tipo por defecto es el logo: sin logo no hay marca, sólo lema, menú y copyright.' );
	check_hf( '<p>All rights reserved</p>' === $fw['text-editor']['editor'] && 'none' === $fw['nav-menu']['dropdown'] && 5 === $fw['nav-menu']['padding_vertical_menu_item']['size'] && false !== strpos( $fw['text-editor']['custom_css'], 'selector p{margin:0}' ), 'El pie lleva el copyright por defecto y el menú de pie de Hello (sin desplegable, 5px 15px).' );
	$fw = $widgets( HF::build( 'footer', array(), $ctx ) );
	check_hf( 1.5 === ( $fw['theme-site-title']['typography_font_size']['size'] ?? null ) || isset( $fw['theme-site-logo'] ), 'Con logo, el pie lleva el logo.' );
	$fw = $widgets( HF::build( 'footer', array( 'hello_footer_logo_type' => 'title' ), $ctx ) );
	check_hf( 1.5 === $fw['theme-site-title']['typography_font_size']['size'], 'El título del pie mide 1.5rem.' );

	// 2. Disposición: invertida, centrada y a todo lo ancho.
	$tree = HF::build( 'header', array( 'hello_header_layout' => 'inverted' ), $ctx );
	check_hf( 'row-reverse' === $tree[0]['settings']['flex_direction'] && 'selector{text-align:end}' === $tree[0]['elements'][0]['settings']['custom_css'], 'La disposición invertida es una fila al revés con la marca alineada al final.' );
	$tree = HF::build( 'header', array( 'hello_header_layout' => 'stacked', 'hello_header_gap' => array( 'unit' => 'px', 'size' => 30 ) ), $ctx );
	check_hf( 'column' === $tree[0]['settings']['flex_direction'] && 'center' === $tree[0]['settings']['flex_align_items'] && '0px' === $tree[0]['settings']['padding']['left'], 'La disposición centrada es una columna centrada y no aplica los márgenes laterales (Hello los oculta).' );
	$tree = HF::build( 'header', array( 'hello_header_width' => 'full-width', 'hello_header_gap' => array( 'unit' => 'px', 'size' => 30 ), 'hello_header_gap_mobile' => array( 'unit' => 'em ', 'size' => 1 ) ), $ctx );
	check_hf( 'full' === $tree[0]['settings']['content_width'] && false === strpos( $tree[0]['settings']['custom_css'], 'min-width' ) && '30px' === $tree[0]['settings']['padding']['left'] && '1em' === $tree[0]['settings']['padding_mobile']['right'], 'A todo lo ancho no hay escalera de anchos y los márgenes laterales son el relleno (también en móvil).' );
	$tree = HF::build( 'header', array( 'hello_header_custom_width' => array( 'unit' => 'px', 'size' => 900 ) ), $ctx );
	check_hf( 900 === $tree[0]['settings']['boxed_width']['size'] && false === strpos( $tree[0]['settings']['custom_css'], 'min-width' ), 'Un ancho propio de Hello sustituye a la escalera.' );
	$tree = HF::build( 'header', array(), array( 'theme_css' => false ) + $ctx );
	check_hf( false === strpos( $tree[0]['settings']['custom_css'], '@media' ), 'Si Hello no cargaba theme.css, no hay escalera de anchos.' );

	// 3. Menú: el slug de la ubicación; sin menú asignado no hay widget de menú.
	check_hf( ! isset( $widgets( HF::build( 'header', array(), array( 'menu' => '' ) + $ctx ) )['nav-menu'] ), 'Sin menú en la ubicación no se añade el widget de menú.' );
	$w = $widgets( HF::build( 'header', array( 'hello_header_menu_layout' => 'dropdown', 'hello_header_menu_dropdown' => 'mobile', 'hello_header_menu_display' => '' ), $ctx ) );
	check_hf( ! isset( $w['nav-menu'] ), 'Con el menú oculto no hay widget de menú.' );
	$w = $widgets( HF::build( 'header', array( 'hello_header_menu_layout' => 'dropdown', 'hello_header_menu_dropdown' => 'mobile' ), $ctx ) );
	check_hf( 'dropdown' === $w['nav-menu']['layout'] && 'mobile' === $w['nav-menu']['dropdown'], 'La disposición y el punto de corte del menú se copian.' );

	// 4. Clases: las de Hello.
	$tree = HF::build( 'header', array( 'hello_header_layout' => 'stacked', 'hello_header_width' => 'full-width', 'hello_header_menu_layout' => 'dropdown' ), $ctx );
	check_hf( 'site-header dynamic-header header-stacked header-full-width menu-dropdown-tablet menu-layout-dropdown' === $tree[0]['settings']['css_classes'], 'Clases de la cabecera: ' . $tree[0]['settings']['css_classes'] );
	$tree = HF::build( 'footer', array( 'hello_footer_layout' => 'inverted' ), $ctx );
	check_hf( 'site-footer dynamic-footer footer-inverted footer-has-copyright' === $tree[0]['settings']['css_classes'] && false !== strpos( $tree[0]['settings']['custom_css'], '@media(max-width:576px)' ), 'Clases del pie y su disposición móvil de Hello: ' . $tree[0]['settings']['css_classes'] );

	// 5. Ajustes del kit: sufijos _tablet y _mobile, globales, colores y copyright filtrado.
	$kit = array(
		'hello_header_logo_type' => 'title',
		'hello_header_title_typography_typography' => 'custom', 'hello_header_title_typography_font_family' => 'Roboto',
		'hello_header_title_typography_font_size_tablet' => array( 'unit' => 'px', 'size' => 30, 'sizes' => array() ),
		'hello_header_title_color' => '#111111',
		'hello_header_tagline_position' => 'after', 'hello_header_tagline_position_mobile' => 'below',
		'hello_header_tagline_gap_tablet' => array( 'unit' => 'em ', 'size' => 2 ),
		'hello_header_tagline_typography_typography' => 'custom', 'hello_header_tagline_typography_font_size_mobile' => array( 'unit' => 'px', 'size' => 12, 'sizes' => array() ),
		'hello_header_menu_typography_typography' => 'custom', 'hello_header_menu_typography_font_weight' => '700',
		'hello_header_background_background' => 'classic', 'hello_header_background_color' => '#fafafa',
		'__globals__' => array( 'hello_header_menu_color' => 'globals/colors?id=accent', 'hello_header_tagline_color' => '' ),
	);
	$tree = HF::build( 'header', $kit, $ctx );
	$w    = $widgets( $tree );
	check_hf( 'Roboto' === $w['theme-site-title']['typography_font_family'] && 30 === $w['theme-site-title']['typography_font_size_tablet']['size'] && 2.5 === $w['theme-site-title']['typography_font_size']['size'] && false === strpos( (string) ( $w['theme-site-title']['custom_css'] ?? '' ), 'font-family' ), 'La tipografía del título de Hello se copia encima de la explícita, con sus sufijos.' );
	check_hf( '#111111' === $w['theme-site-title']['title_color'] && '#111111' === $w['theme-site-title']['title_hover_color'], 'El color del título de Hello también vale al pasar el ratón, como en Hello.' );
	check_hf( 'row' === $tree[0]['elements'][0]['settings']['flex_direction'] && 'column' === $tree[0]['elements'][0]['settings']['flex_direction_mobile'] && 'em' === $tree[0]['elements'][0]['settings']['flex_gap_tablet']['unit'], 'La posición y el espacio del lema se copian por dispositivo.' );
	check_hf( 12 === $w['heading']['typography_font_size_mobile']['size'] && false !== strpos( $w['heading']['custom_css'], 'color:inherit' ) && false !== strpos( $w['heading']['custom_css'], 'font-size:inherit' ), 'El lema hereda del cuerpo lo que Hello no fijó.' );
	check_hf( 'globals/colors?id=accent' === $w['nav-menu']['__globals__']['color_menu_item'] && 'globals/colors?id=accent' === $w['nav-menu']['__globals__']['color_menu_item_hover'] && '700' === $w['nav-menu']['menu_typography_font_weight'] && false === strpos( $w['nav-menu']['custom_css'], 'font-weight' ), 'El color global y la tipografía del menú de Hello se copian.' );
	check_hf( 'globals/colors?id=accent' === ( $w['nav-menu']['__globals__']['color_menu_item_active'] ?? '' ) && false !== strpos( $w['nav-menu']['custom_css'], '{color:var(--e-global-color-accent);' ), 'Con un color global, el enlace activo usa el mismo global y su variable al pasar el ratón.' );
	check_hf( 'classic' === $tree[0]['settings']['background_background'] && '#fafafa' === $tree[0]['settings']['background_color'], 'El fondo de la cabecera se copia al contenedor.' );
	$w = $widgets( HF::build( 'header', array( 'hello_header_logo_width' => array( 'unit' => 'px', 'size' => 120 ), 'hello_header_logo_width_mobile' => array( 'unit' => '%', 'size' => 50 ) ), $ctx ) );
	check_hf( 120 === $w['theme-site-logo']['width']['size'] && 120 === $w['theme-site-logo']['space']['size'] && '%' === $w['theme-site-logo']['width_mobile']['unit'] && 50 === $w['theme-site-logo']['space_mobile']['size'], 'El ancho del logo se copia como ancho y ancho máximo, también en móvil.' );
	$w = $widgets( HF::build( 'header', array( 'hello_header_logo_type' => 'title' ), array( 'link' => array( 'global' => 'globals/colors?id=secondary' ), 'link_hover' => array( 'value' => '#000' ) ) + $ctx ) );
	check_hf( 'globals/colors?id=secondary' === $w['theme-site-title']['__globals__']['title_color'] && '#000' === $w['theme-site-title']['title_hover_color'] && ! isset( $w['theme-site-title']['title_color'] ), 'Con color de enlace en el kit se usa el del kit, no el de reset.css.' );
	$w = $widgets( HF::build( 'header', array( 'hello_header_logo_type' => 'title' ), array( 'reset_css' => false ) + $ctx ) );
	check_hf( ! isset( $w['theme-site-title']['title_color'] ), 'Si Hello no cargaba reset.css no se inventa el color de los enlaces.' );
	$fw = $widgets( HF::build( 'footer', array( 'hello_footer_copyright_text' => '© Sitio <script>alert(1)</script><b>2026</b>', 'hello_footer_copyright_color' => '#222', 'hello_footer_menu_color' => '#333' ), $ctx ) );
	check_hf( '<p>© Sitio <b>2026</b></p>' === $fw['text-editor']['editor'] && '#222' === $fw['text-editor']['text_color'] && false === strpos( $fw['text-editor']['custom_css'], 'color:inherit' ) && '#333' === $fw['nav-menu']['color_menu_item'], 'El copyright pasa por wp_kses_post y los colores del pie se copian.' );
	check_hf( ! isset( $widgets( HF::build( 'footer', array( 'hello_footer_copyright_text' => '' ), $ctx ) )['text-editor'] ), 'Un copyright vacío no se pinta, como en Hello.' );

	// 6. Todo oculto: la cabecera no genera nada (hello_get_header_display()); el pie, como en Hello, deja su contenedor
	// vacío (dynamic-footer.php no consulta hello_get_footer_display() e imprime footer.site-footer.dynamic-footer).
	check_hf( array() === HF::build( 'header', array( 'hello_header_logo_display' => '', 'hello_header_tagline_display' => '', 'hello_header_menu_display' => '' ), $ctx ), 'Con todo oculto la cabecera no genera nada.' );
	$empty = HF::build( 'footer', array( 'hello_footer_logo_display' => '', 'hello_footer_tagline_display' => '', 'hello_footer_menu_display' => '', 'hello_footer_copyright_display' => '' ), $ctx );
	check_hf( 1 === count( $empty ) && 'container' === $empty[0]['elType'] && array() === $empty[0]['elements'] && array() === $widgets( $empty ), 'Con todo oculto el pie es su contenedor vacío.' );
	check_hf( 'site-footer dynamic-footer' === $empty[0]['settings']['css_classes'] && '1rem' === $empty[0]['settings']['padding']['top'] && '1rem' === $empty[0]['settings']['padding']['bottom'], 'El pie vacío lleva las clases y el relleno de Hello (sin footer-has-copyright).' );
	$empty = HF::build( 'footer', array( 'hello_footer_logo_display' => '', 'hello_footer_tagline_display' => '', 'hello_footer_menu_display' => '', 'hello_footer_copyright_display' => '', 'hello_footer_background_background' => 'classic', 'hello_footer_background_color' => '#123456', 'hello_footer_width' => 'full-width' ), $ctx );
	check_hf( 'classic' === $empty[0]['settings']['background_background'] && '#123456' === $empty[0]['settings']['background_color'] && 'full' === $empty[0]['settings']['content_width'], 'El pie vacío conserva su fondo y su ancho, que en Hello seguían a la vista.' );
	check_hf( array() !== HF::build( 'footer', array( 'hello_footer_logo_display' => '', 'hello_footer_tagline_display' => '', 'hello_footer_menu_display' => '' ), $ctx ), 'Con el copyright visible el pie sí se genera.' );

	// Conversión: registro del tema tras pasar de Hello con cabecera y pie dinámicos.
	$record = array(
		'v' => 1, 'state' => 'partial', 'user' => 1,
		'origin' => array( 'family' => 'hello', 'stylesheet' => 'hello-elementor', 'template' => 'hello-elementor', 'version' => '3.5.1', 'child' => false ),
		'last' => array( 'stylesheet' => 'hello-elementor', 'template' => 'hello-elementor', 'family' => 'hello' ),
		'hello' => array( 'hello_style' => false, 'hello_theme' => false ),
		'header_footer' => array( 'experiment' => true, 'header' => 'dynamic', 'footer' => 'dynamic', 'kit_id' => 5, 'pending' => true, 'blocked' => '' ),
	);
	$GLOBALS['t']['options']['digitalisimo_theme_switch'] = $record;
	$GLOBALS['t']['options']['elementor_active_kit'] = 5;
	$GLOBALS['t']['meta'][5]['_elementor_page_settings'] = array( 'hello_header_layout' => 'inverted' );
	$GLOBALS['t']['user'] = 0;
	$GLOBALS['t']['admin'] = false;
	$GLOBALS['t']['cron'] = true;
	$generated = static function ( $part ) {
		$ids = array();
		foreach ( $GLOBALS['t']['posts'] as $post ) {
			if ( ( $GLOBALS['t']['meta'][ $post->ID ]['_digitalisimo_generated'] ?? '' ) === 'hello_' . $part ) { $ids[] = $post->ID; }
		}
		return $ids;
	};

	// 9b. Con el cerrojo tomado por otra petición, la conversión no escribe.
	$GLOBALS['wpdb']->rows['digitalisimo_theme_convert_lock'] = time() . '|otra';
	check_hf( false === HF::convert() && array() === $generated( 'header' ) && ! empty( get_option( 'digitalisimo_theme_switch' )['header_footer']['pending'] ), 'Con el cerrojo tomado el segundo no escribe y la tarea sigue pendiente.' );
	$GLOBALS['wpdb']->rows['digitalisimo_theme_convert_lock'] = ( time() - 400 ) . '|otra';

	// En cron no hay usuario: se usa el del registro (si aún puede editar el tema) y se devuelve al terminar.
	$files = \Elementor\Plugin::$instance->files_manager->cleared;
	check_hf( true === HF::convert(), 'La conversión en cron con el usuario del registro debe completarse.' );
	check_hf( $files + 1 === \Elementor\Plugin::$instance->files_manager->cleared, 'Tras crear las plantillas se limpia la caché del CSS (T8 al final de convert()).' );
	check_hf( 0 === $GLOBALS['t']['user'] && ! isset( $GLOBALS['wpdb']->rows['digitalisimo_theme_convert_lock'] ), 'Tras convertir se vuelve a no tener usuario y el cerrojo (robado por viejo) queda suelto.' );
	$after = get_option( 'digitalisimo_theme_switch' );
	$hid   = $after['generated']['header']['id'] ?? 0;
	$fid   = $after['generated']['footer']['id'] ?? 0;
	check_hf( $hid && $fid && false === $after['header_footer']['pending'] && '' === $after['header_footer']['blocked'] && true === $GLOBALS['t']['autoload']['digitalisimo_theme_switch'], 'El registro anota las dos plantillas, deja de estar pendiente y conserva la carga automática.' );
	check_hf( 'publish' === get_post( $hid )->post_status && 'Cabecera' === get_post( $hid )->post_title && 'Pie' === get_post( $fid )->post_title && 1 === get_post( $hid )->post_author && 'header' === get_post_meta( $hid, '_elementor_template_type' ), 'La cabecera se crea publicada, con su título y el autor del registro.' );
	check_hf( array( 'origin' => 'hello', 'version' => '3.5.1', 'hash' => $after['generated']['header']['hash'] ) === get_post_meta( $hid, '_digitalisimo_import_origin' ), 'La plantilla guarda su origen y su huella.' );
	$saved = json_decode( get_post_meta( $hid, '_elementor_data' ), true );
	check_hf( 'row-reverse' === $saved[0]['settings']['flex_direction'], 'La plantilla usa los ajustes guardados del kit.' );
	check_hf( 'v' === array_key_first( $after ) && 'partial' === $after['state'] && 'hello' === $after['origin']['family'] && 'dynamic' === $after['header_footer']['header'], 'Sólo se fusionan generated, pending y blocked: lo demás del registro queda igual.' );

	// 7. Condiciones en el formato que el bucle real de save_conditions convierte en include/general.
	check_hf( array( 'include/general' ) === get_post_meta( $hid, '_elementor_conditions' ) && array( 'include/general' ) === get_post_meta( $fid, '_elementor_conditions' ), 'Las condiciones deben quedar como include/general.' );

	// 8. Huella del JSON releído tras save(), que normaliza el árbol.
	$tree = HF::build( 'header', array( 'hello_header_layout' => 'inverted' ), array( 'has_logo' => false, 'site_name' => 'Mi sitio', 'tagline' => 'Un lema', 'menu' => 'principal', 'copyright' => 'All rights reserved', 'link' => array(), 'link_hover' => array(), 'reset_css' => true, 'theme_css' => true ) );
	check_hf( md5( get_post_meta( $hid, '_elementor_data' ) ) === $after['generated']['header']['hash'] && md5( json_encode( $tree ) ) !== $after['generated']['header']['hash'], 'La huella es la del JSON guardado, no la del árbol antes de guardar.' );

	// 9. Duplicados: una segunda conversión reutiliza las plantillas.
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['header_footer']['pending'] = true;
	$files = \Elementor\Plugin::$instance->files_manager->cleared;
	check_hf( true === HF::convert() && array( $hid ) === $generated( 'header' ) && array( $fid ) === $generated( 'footer' ), 'Dos conversiones seguidas crean una sola plantilla por parte.' );
	check_hf( $files === \Elementor\Plugin::$instance->files_manager->cleared, 'Sin plantillas nuevas ni publicadas, la conversión no vuelve a limpiar la caché.' );
	check_hf( 2 === count( \Elementor\Plugin::$instance->documents->created ), 'Sólo se crearon dos documentos.' );
	// Con la plantilla en borrador y sin cambios, se vuelve a publicar y se reutiliza.
	$GLOBALS['t']['posts'][ $hid ]->post_status = 'draft';
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['header_footer']['pending'] = true;
	$files = \Elementor\Plugin::$instance->files_manager->cleared;
	check_hf( true === HF::convert() && 'publish' === get_post( $hid )->post_status && array( $hid ) === $generated( 'header' ), 'Una plantilla generada sin cambios se publica de nuevo en lugar de duplicarse.' );
	check_hf( $files + 1 === \Elementor\Plugin::$instance->files_manager->cleared, 'Al volver a publicarla se limpia la caché del CSS.' );
	// Editada: se conserva tal cual, con un único aviso.
	$GLOBALS['t']['meta'][ $hid ]['_elementor_data'] .= ' ';
	$GLOBALS['t']['posts'][ $hid ]->post_status = 'draft';
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['header_footer']['pending'] = true;
	HF::convert();
	HF::convert();
	$codes = array_column( get_option( 'digitalisimo_theme_switch_log' )['warnings'], 'code' );
	check_hf( 'draft' === get_post( $hid )->post_status && array( $hid ) === $generated( 'header' ) && array( 'generated_edited' ) === $codes && false === $GLOBALS['t']['autoload']['digitalisimo_theme_switch_log'], 'Una plantilla editada no se publica ni se duplica, y se avisa una vez en la bitácora sin carga automática.' );
	$GLOBALS['t']['meta'][ $hid ]['_elementor_data'] = substr( $GLOBALS['t']['meta'][ $hid ]['_elementor_data'], 0, -1 );
	$GLOBALS['t']['posts'][ $hid ]->post_status = 'publish';

	// Un widget ausente bloquea la conversión con un único aviso aunque se reintente.
	$GLOBALS['t']['options']['digitalisimo_theme_switch_log'] = array( 'warnings' => array() );
	\Elementor\Plugin::$instance->widgets_manager->missing = array( 'nav-menu' );
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['header_footer']['pending'] = true;
	HF::convert();
	HF::convert();
	$state = get_option( 'digitalisimo_theme_switch' )['header_footer'];
	check_hf( 'widget:nav-menu' === $state['blocked'] && false === $state['pending'] && array( 'widget_missing' ) === array_column( get_option( 'digitalisimo_theme_switch_log' )['warnings'], 'code' ), 'Sin el widget de menú la conversión queda bloqueada con un solo aviso.' );
	\Elementor\Plugin::$instance->widgets_manager->missing = array();
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['header_footer']['blocked'] = '';

	// Sin un usuario que pueda editar el tema, la tarea queda pendiente.
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['user'] = 2;
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['header_footer']['pending'] = true;
	check_hf( false === HF::convert() && true === get_option( 'digitalisimo_theme_switch' )['header_footer']['pending'] && 0 === $GLOBALS['t']['user'], 'Con un usuario sin permisos no se convierte y sigue pendiente.' );
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['user'] = 1;
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['header_footer']['pending'] = false;

	// Otra versión de Hello: aviso de que se usaron los valores de 3.5.1.
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['origin']['version'] = '3.4.4';
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['header_footer']['pending'] = true;
	HF::convert();
	check_hf( in_array( 'hello_version', array_column( get_option( 'digitalisimo_theme_switch_log' )['warnings'], 'code' ), true ), 'Con otra versión de Hello se avisa.' );
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['origin']['version'] = '3.5.1';

	// 10. Reversión: sin cambios, a la papelera; editada, a borrador.
	$GLOBALS['t']['meta'][ $fid ]['_elementor_data'] .= ' ';
	$files = \Elementor\Plugin::$instance->files_manager->cleared;
	$third = \Elementor\Compatibility::$cleared;
	HF::reverted( get_option( 'digitalisimo_theme_switch' ) );
	$after = get_option( 'digitalisimo_theme_switch' );
	check_hf( 'trash' === get_post( $hid )->post_status && 'trash' === $after['generated']['header']['retired'], 'Al revertir, la cabecera sin cambios va a la papelera.' );
	check_hf( 'draft' === get_post( $fid )->post_status && 'draft' === $after['generated']['footer']['retired'], 'Al revertir, el pie editado pasa a borrador (nunca se borra).' );
	check_hf( $files + 1 === \Elementor\Plugin::$instance->files_manager->cleared && $third + 1 === \Elementor\Compatibility::$cleared, 'Al revertir se limpian las cachés (T8).' );

	// 11. Ida y vuelta: al volver al tema, las plantillas retiradas vuelven a publicarse (no a borrador) sin duplicados.
	$GLOBALS['t']['template'] = 'digitalisimo-theme';
	$GLOBALS['t']['updates'] = array();
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['header_footer']['pending'] = true;
	$GLOBALS['t']['admin'] = true;
	$GLOBALS['t']['cron'] = false;
	$GLOBALS['t']['user'] = 1;
	$files = \Elementor\Plugin::$instance->files_manager->cleared;
	HF::switched( get_option( 'digitalisimo_theme_switch' ) + array( 'restore' => true ), null );
	$after = get_option( 'digitalisimo_theme_switch' );
	check_hf( 'publish' === get_post( $hid )->post_status && 'publish' === get_post( $fid )->post_status && ! isset( $after['generated']['header']['retired'] ) && ! isset( $after['generated']['footer']['retired'] ), 'Al volver, las plantillas retiradas quedan publicadas.' );
	check_hf( $files + 2 === \Elementor\Plugin::$instance->files_manager->cleared, 'Al volver se limpia la caché al recibir el aviso y otra vez tras publicar las plantillas restauradas (T8).' );
	check_hf( in_array( array( $hid, 'publish' ), $GLOBALS['t']['updates'], true ), 'Tras wp_untrash_post (que deja borrador) se publica de forma explícita.' );
	check_hf( array( $hid ) === $generated( 'header' ) && array( $fid ) === $generated( 'footer' ) && false === $after['header_footer']['pending'], 'La ida y vuelta no duplica plantillas.' );
	// Una plantilla que el usuario borró del todo vuelve a generarse.
	HF::reverted( get_option( 'digitalisimo_theme_switch' ) );
	unset( $GLOBALS['t']['posts'][ $hid ], $GLOBALS['t']['meta'][ $hid ] );
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['header_footer']['pending'] = false;
	HF::switched( get_option( 'digitalisimo_theme_switch' ) + array( 'restore' => true ), null );
	$after = get_option( 'digitalisimo_theme_switch' );
	check_hf( 1 === count( $generated( 'header' ) ) && $hid !== $after['generated']['header']['id'] && 'publish' === get_post( $after['generated']['header']['id'] )->post_status && 'publish' === get_post( $fid )->post_status, 'Si la plantilla ya no existe, esa parte vuelve a convertirse.' );

	// 12. Dejar el tema desde Apariencia (left): la misma retirada que al revertir y el registro en reverted, una vez.
	$GLOBALS['t']['options']['digitalisimo_theme_switch']['state'] = 'done';
	$nid   = (int) get_option( 'digitalisimo_theme_switch' )['generated']['header']['id'];
	$files = \Elementor\Plugin::$instance->files_manager->cleared;
	check_hf( true === HF::left( get_option( 'digitalisimo_theme_switch' ) ), 'left() termina bien.' );
	$after = get_option( 'digitalisimo_theme_switch' );
	check_hf( 'trash' === get_post( $nid )->post_status && 'draft' === get_post( $fid )->post_status && 'trash' === $after['generated']['header']['retired'] && 'draft' === $after['generated']['footer']['retired'], 'Al dejar el tema: la cabecera sin cambios a la papelera y el pie editado a borrador.' );
	check_hf( 'reverted' === $after['state'] && $after['reverted'] > 0 && 'dynamic' === $after['header_footer']['header'] && true === $GLOBALS['t']['autoload']['digitalisimo_theme_switch'], 'El registro queda en reverted, sin perder lo demás y con carga automática.' );
	check_hf( $files + 1 === \Elementor\Plugin::$instance->files_manager->cleared && ! isset( $GLOBALS['wpdb']->rows['digitalisimo_theme_convert_lock'] ), 'Se limpian las cachés y el cerrojo queda suelto.' );
	$GLOBALS['t']['posts'][ $nid ]->post_status = 'publish';
	check_hf( true === HF::left( get_option( 'digitalisimo_theme_switch' ) ) && 'publish' === get_post( $nid )->post_status && $files + 1 === \Elementor\Plugin::$instance->files_manager->cleared, 'Con el registro ya en reverted, left() no vuelve a retirar nada (idempotente).' );
	$GLOBALS['wpdb']->rows['digitalisimo_theme_convert_lock'] = time() . '|otra';
	unset( $GLOBALS['t']['options']['digitalisimo_theme_switch']['reverted'] );
	check_hf( false === HF::left( get_option( 'digitalisimo_theme_switch' ) ) && 'publish' === get_post( $nid )->post_status && empty( get_option( 'digitalisimo_theme_switch' )['reverted'] ), 'Con el cerrojo de otra conversión, left() no retira ni marca nada (avisa en la bitácora).' );
	unset( $GLOBALS['wpdb']->rows['digitalisimo_theme_convert_lock'] );

	// Otro origen: nada.
	$before = $GLOBALS['t']['options'];
	HF::switched( array( 'origin' => array( 'family' => 'other' ), 'header_footer' => array( 'pending' => true ) ), null );
	check_hf( $before === $GLOBALS['t']['options'], 'Con otro tema de origen no se hace nada.' );

	echo "DIGITALÍSIMO Elements: cabecera y pie de Hello convertidos, sin duplicados y reversibles.\n";
}
