<?php
/**
 * Kits (etapa K): remapeo de IDs y URL (§9 y §13.1 «remap»).
 *
 * Cada fila de las referencias integradas (§9.2), `__globals__`, URL, medios y CSS (§9.3), la regla de cierre
 * (§5.5, A14), «una sola vez» por documento (CR-16, CV-12), el remapeo dentro de un marcador (CV-19), las condiciones
 * del Theme Builder (§9.5) y un `kind` sin traducción.
 */
namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	require __DIR__ . '/elements-kit/wp-stubs.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import/class-module.php';

	use Digitalisimo\Elements\Import\Module;
	use Digitalisimo\Elements\Import\Kits\Import_Context;
	use Digitalisimo\Elements\Import\Kits\Part_Context;
	use Digitalisimo\Elements\Import\Kits\References;
	use Digitalisimo\Elements\Import\Kits\Remapper;
	use Elementor\Core\Base\Document as Core_Document;

	Module::register_autoloader();

	$check = function ( $ok, $message ) {
		if ( ! $ok ) {
			throw new RuntimeException( $message );
		}
	};

	$tmp = sys_get_temp_dir() . '/digi-kit-remap-' . bin2hex( random_bytes( 6 ) );
	kit_stub_reset( $tmp . '/uploads' );

	// Tipos registrados: sus controles URL se derivan por tipo (§5.5).
	$url  = array( 'type' => 'url' );
	$text = array( 'type' => 'text' );
	$registered = array(
		'heading'                       => new Kit_Stub_Controls( array( 'title' => array( 'name' => 'title' ) + $text, 'link' => array( 'name' => 'link' ) + $url ) ),
		'digitalisimo-icon-box'         => new Kit_Stub_Controls(
			array(
				'link'  => array( 'name' => 'link' ) + $url,
				'items' => array(
					'name'   => 'items',
					'type'   => 'repeater',
					'fields' => array( array( 'name' => 'item_link' ) + $url, array( 'name' => 'label' ) + $text ),
				),
			)
		),
		'digitalisimo-accordion'        => new Kit_Stub_Controls(),
		'digitalisimo-fancy-tabs'       => new Kit_Stub_Controls(),
		'digitalisimo-content-switcher' => new Kit_Stub_Controls(),
		'digitalisimo-offcanvas'        => new Kit_Stub_Controls(),
		'slides'                        => new Kit_Stub_Controls(),
		'digitalisimo-icon-nav'         => new Kit_Stub_Controls(),
		'digitalisimo-icon-mobile-menu' => new Kit_Stub_Controls(),
		'digitalisimo-vertical-menu'    => new Kit_Stub_Controls(),
		'author-box'                    => new Kit_Stub_Controls(),
		'digitalisimo-google-reviews'   => new Kit_Stub_Controls(),
		'loop-grid'                     => new Kit_Stub_Controls(),
		'loop-carousel'                 => new Kit_Stub_Controls(),
		'taxonomy-filter'               => new Kit_Stub_Controls(),
		'wp-widget-nav_menu'            => new Kit_Stub_Controls(),
		'wp-widget-media_image'         => new Kit_Stub_Controls(),
		'wp-widget-media_video'         => new Kit_Stub_Controls(),
		'wp-widget-media_audio'         => new Kit_Stub_Controls(),
		'wp-widget-media_gallery'       => new Kit_Stub_Controls(),
		'form'                          => new Kit_Stub_Controls(),
		Module::WIDGET                  => new Kit_Stub_Controls( array( 'digitalisimo_unconverted' => array( 'name' => 'digitalisimo_unconverted', 'type' => 'hidden' ) ) ),
		'container'                     => new Kit_Stub_Controls(),
		'section'                       => new Kit_Stub_Controls(),
		'column'                        => new Kit_Stub_Controls(),
	);
	\Elementor\Plugin::$instance = (object) array(
		'widgets_manager'  => new Kit_Stub_Manager( $registered ),
		'elements_manager' => new Kit_Stub_Manager( $registered ),
	);

	// Destino: la página 12 tiene otro slug que en el origen; el adjunto 1100 es la foto importada.
	kit_stub_post( array( 'ID' => 12, 'post_name' => 'contacto-2' ) );
	kit_stub_post( array( 'ID' => 20, 'post_name' => 'otra' ) );
	kit_stub_post( array( 'ID' => 1030, 'post_type' => 'elementor_library' ) );
	kit_stub_post( array( 'ID' => 1031, 'post_type' => 'elementor_library' ) );
	kit_stub_post( array( 'ID' => 1032, 'post_type' => 'page' ) );
	$photo = kit_stub_post( array( 'ID' => 1100, 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
	update_post_meta( $photo, '_wp_attached_file', '2026/10/foto.jpg' );
	$hashed = kit_stub_post( array( 'ID' => 1101, 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
	update_post_meta( $hashed, '_wp_attached_file', '2026/10/fondo.png' );
	update_post_meta( $hashed, '_elementor_source_image_hash', sha1( 'https://a.test/wp-content/uploads/2026/01/fondo.png' ) );

	$up      = 'https://a.test/wp-content/uploads';
	$context = function ( array $maps = array(), string $mode = 'add' ) use ( $up ) {
		return new Import_Context(
			array(
				'session'     => 'sesion-1',
				'mode'        => $mode,
				'kit'         => sha1( 'kit' ),
				'origin'      => array(
					'home'    => 'https://a.test',
					'site'    => 'https://a.test',
					'uploads' => $up,
				),
				'max_post_id' => 1000,
				'maps'        => $maps + array(
					'post'              => array( 5 => 12, 12 => 20, 7 => 1007, 33 => 1032 ),
					'template'          => array( 30 => 1030, 31 => 1031 ),
					'term'              => array( 40 => 540, 41 => 541 ),
					'menu'              => array( 50 => 550 ),
					'menu_slug'         => array( 'principal' => 'principal-2' ),
					'global_color'      => array( 'primary' => 'kabc123', 'c0ffee1' => 'k111111' ),
					'global_typography' => array( 'primary' => 'k222222' ),
					'attachment'        => array( $up . '/2026/01/foto.jpg' => 1100, 60 => 1100 ),
					'media_url'         => array( $up . '/2026/01/doc.pdf' => 'https://b.test/wp-content/uploads/2026/10/doc.pdf' ),
					'files'             => array( 'masks/mask-3.svg' => 'masks/mask-3.svg', 'shapes/ola.svg' => 'shapes/ola-k1a2b3c.svg' ),
					'shapes'            => array( 'digi-ola' => 'digi-ola-k1a2b3c' ),
					'routes'            => array( '/contacto/' => 5 ),
				),
			)
		);
	};
	$never = References::NEVER_ID;
	$codes = function ( Import_Context $ctx, string $code ) {
		return array_values(
			array_filter(
				$ctx->get_report()['entries'],
				function ( $entry ) use ( $code ) {
					return $code === $entry['code'];
				}
			)
		);
	};
	$widget = function ( string $type, array $settings, string $id = '' ) {
		static $n = 0;
		return array(
			'id'         => '' !== $id ? $id : 'w' . str_pad( (string) ++$n, 6, '0', STR_PAD_LEFT ),
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	};

	try {
		// ── 1. Cada fila de §9.2 ────────────────────────────────────────────────────────────────────────────────
		$rows = array(
			'accordion'  => $widget( 'digitalisimo-accordion', array( 'tabs' => array( array( 'template_id' => '30', 'anywhere_id' => 5 ), array( 'template_id' => 99 ) ), 'digitalisimo_acf_field' => 'field_6512bd43' ) ),
			'tabs'       => $widget( 'digitalisimo-fancy-tabs', array( 'tabs' => array( array( 'template_id' => 31 ) ) ) ),
			'switcher'   => $widget( 'digitalisimo-content-switcher', array( 'tabs' => array( array( 'template_id' => 30 ) ) ) ),
			'offcanvas'  => $widget( 'digitalisimo-offcanvas', array( 'template_id' => '31', 'sidebar' => 'sidebar-1' ) ),
			'slides'     => $widget( 'slides', array( 'slides' => array( array( 'digitalisimo_template_id' => '30' ), array( 'heading' => 'sin plantilla' ) ) ) ),
			'icon_nav'   => $widget( 'digitalisimo-icon-nav', array( 'navbar' => '50' ) ),
			'icon_menu'  => $widget( 'digitalisimo-icon-mobile-menu', array( 'navbar' => 50 ) ),
			'vertical'   => $widget( 'digitalisimo-vertical-menu', array( 'menu' => 'principal' ) ),
			'author'     => $widget( 'author-box', array( 'digitalisimo_author_user' => '3' ) ),
			'reviews'    => $widget( 'digitalisimo-google-reviews', array( 'google_place_id' => 'ChIJ-test-place' ) ),
			'loop_grid'  => $widget( 'loop-grid', array( 'template_id' => 30, 'alternate_templates' => array( array( 'template_id' => 31 ), array( 'template_id' => '30' ) ) ) ),
			'carousel'   => $widget( 'loop-carousel', array( 'template_id' => 31 ) ),
			'filter_ok'  => $widget( 'taxonomy-filter', array( 'selected_element' => 'g000001' ) ),
			'filter_bad' => $widget( 'taxonomy-filter', array( 'selected_element' => 'zz99999' ) ),
			'nav_menu'   => $widget( 'wp-widget-nav_menu', array( 'wp' => array( 'nav_menu' => '50', 'title' => 'Menú' ) ) ),
			'image'      => $widget( 'wp-widget-media_image', array( 'wp' => array( 'attachment_id' => 60 ) ) ),
			'video'      => $widget( 'wp-widget-media_video', array( 'wp' => array( 'attachment_id' => 61 ) ) ),
			'gallery'    => $widget( 'wp-widget-media_gallery', array( 'wp' => array( 'ids' => '60,61' ) ) ),
			'form'       => $widget( 'form', array( 'form_fields' => array( array( 'field_type' => 'email' ) ) ) ),
			'icon_box'   => $widget(
				'digitalisimo-icon-box',
				array(
					'link'  => array( 'url' => 'https://a.test/contacto/?x=1#y', 'is_external' => '' ),
					'items' => array(
						array( 'item_link' => array( 'url' => $up . '/2026/01/doc.pdf' ) ),
						array( 'item_link' => array( 'url' => $up . '/2026/01/falta.pdf' ) ),
						array( 'item_link' => array( 'url' => 'https://otro.test/x' ) ),
						array( 'item_link' => array( 'url' => '/relativa/' ) ),
					),
				)
			),
		);
		$grid      = $widget( 'loop-grid', array(), 'g000001' );
		$visible   = $widget(
			'heading',
			array(
				'digitalisimo_visibility_enable' => 'yes',
				'digitalisimo_visibility_rules'  => array(
					array( 'rule_key' => 'post', 'rule_post_value' => array( array( 'id' => 5, 'label' => 'Contacto' ), array( 'id' => 99, 'label' => 'Borrada' ) ) ),
					array( 'rule_key' => 'user', 'rule_user_value' => array( 3 ) ),
					array( 'rule_key' => 'user', 'rule_user_value' => '' ),
					array( 'rule_key' => 'role', 'rule_role_value' => 'editor', 'rule_user_value' => array( 3 ) ),
					array( 'rule_key' => 'products_in_cart', 'rule_products_in_cart_value' => array( 7 ) ),
					array( 'rule_key' => 'categories_in_cart', 'rule_categories_in_cart_value' => array( '40', '77' ) ),
					array( 'rule_key' => 'tags_in_cart', 'rule_tags_in_cart_value' => array( 78 ) ),
					array( 'rule_key' => 'static_page', 'rule_static_page_value' => 'custom', 'rule_custom_page_id' => '5' ),
					array( 'rule_key' => 'static_page', 'rule_static_page_value' => 'home', 'rule_custom_page_id' => '10' ),
					array( 'rule_key' => 'current_category_page', 'rule_current_category_page_value' => array( 41 ) ),
					array( 'rule_key' => 'single_product_category', 'rule_single_product_category_value' => array( 40 ) ),
				),
				'e_display_conditions'           => array(
					json_encode(
						array(
							array(
								array( 'condition' => 'page_author', 'comparator' => 'is', 'authors' => array( array( 'id' => 3, 'label' => 'Ana' ) ) ),
								array( 'condition' => 'in_categories', 'comparator' => 'is', 'categories' => array( array( 'id' => 40 ), array( 'id' => 79 ) ) ),
							),
							array( array( 'condition' => 'digitalisimo_specific_content', 'comparator' => 'is_one_of', 'items' => array( array( 'id' => 5 ), array( 'id' => 98 ) ) ) ),
							array( array( 'condition' => 'login_status', 'comparator' => 'is', 'status' => 'logged_in' ) ),
						)
					),
				),
				'__globals__'                    => array( 'title_color' => 'globals/colors?id=primary', 'typography_typography' => 'globals/typography?id=primary', 'other' => 'globals/colors?id=secondary' ),
				'list'                           => array( array( '__globals__' => array( 'color' => 'globals/colors?id=c0ffee1' ) ) ),
				'custom_css'                     => 'selector{-webkit-mask-image:url("' . $up . '/digitalisimo-elements/masks/mask-3.svg")} .a{background:url(' . $up . '/2026/01/foto-300x200.jpg)} .b{background:url(\'' . $up . '/2026/01/otra.png\')} .c{background:url(https://cdn.otro.test/x.png)} .d{background:url(' . $up . '/2026/01/fondo.png)}',
			),
			'h000001'
		);
		$section = array(
			'id'       => 's000001',
			'elType'   => 'section',
			'settings' => array( 'shape_divider_top' => 'digi-ola', 'shape_divider_bottom' => 'waves' ),
			'elements' => array( array( 'id' => 'c000001', 'elType' => 'column', 'settings' => array(), 'elements' => array_merge( array_values( $rows ), array( $grid, $visible ) ) ) ),
		);
		$container = array( 'id' => 'k000001', 'elType' => 'container', 'settings' => array( 'shape_divider_top' => 'digi-mar' ), 'elements' => array() );
		$ctx       = $context();
		$out       = Remapper::apply(
			array(
				'elements' => array( $section, $container ),
				'settings' => array( 'custom_css' => 'body{background:url(' . $up . '/2026/01/foto.jpg)}', '__globals__' => array( 'body_color' => 'globals/colors?id=primary' ) ),
			),
			$ctx,
			1500
		);
		$by        = array();
		foreach ( $out['elements'][0]['elements'][0]['elements'] as $element ) {
			$by[ $element['id'] ] = $element['settings'];
		}
		$s = function ( $name ) use ( $rows, $by ) {
			return $by[ $rows[ $name ]['id'] ];
		};

		$check( 1030 === (int) $s( 'accordion' )['tabs'][0]['template_id'] && '1030' === $s( 'accordion' )['tabs'][0]['template_id'], 'Acordeón: plantilla traducida y conserva el tipo cadena.' );
		$check( 12 === $s( 'accordion' )['tabs'][0]['anywhere_id'], 'Acordeón: AnyWhere con el mapa de entradas.' );
		$check( '' === $s( 'accordion' )['tabs'][1]['template_id'], 'Acordeón: una plantilla sin destino se vacía, nunca queda el ID del origen.' );
		$check( 1031 === $s( 'tabs' )['tabs'][0]['template_id'] && 1030 === $s( 'switcher' )['tabs'][0]['template_id'], 'Pestañas y su alias Alternador.' );
		$check( '1031' === $s( 'offcanvas' )['template_id'] && 'sidebar-1' === $s( 'offcanvas' )['sidebar'], 'Panel lateral: plantilla traducida; la barra lateral no cambia.' );
		$check( '1030' === $s( 'slides' )['slides'][0]['digitalisimo_template_id'] && ! isset( $s( 'slides' )['slides'][1]['digitalisimo_template_id'] ), 'Rasgo de plantilla del Deslizador.' );
		$check( '550' === $s( 'icon_nav' )['navbar'] && 550 === $s( 'icon_menu' )['navbar'], 'Navegación con iconos y su alias: ID del menú.' );
		$check( 'principal-2' === $s( 'vertical' )['menu'], 'Menú vertical: slug del menú.' );
		$check( '' === $s( 'author' )['digitalisimo_author_user'], 'Caja de autor: los usuarios no viajan.' );
		$check( 'ChIJ-test-place' === $s( 'reviews' )['google_place_id'], 'Reseñas: el Place ID viaja tal cual.' );
		$check( 30 === $s( 'loop_grid' )['template_id'], 'Loop Grid: `template_id` lo traduce el núcleo; K no lo duplica.' );
		$check( 1031 === $s( 'loop_grid' )['alternate_templates'][0]['template_id'] && '1030' === $s( 'loop_grid' )['alternate_templates'][1]['template_id'], 'Loop Grid: plantillas alternas (CC-9).' );
		$check( 1031 === $s( 'carousel' )['template_id'], 'Loop Carousel: `template_id` (V-33).' );
		$check( 'g000001' === $s( 'filter_ok' )['selected_element'] && '' === $s( 'filter_bad' )['selected_element'], 'Filtro de taxonomías: el elemento existe en el documento (D20); si no, se vacía.' );
		$check( '550' === $s( 'nav_menu' )['wp']['nav_menu'] && 'Menú' === $s( 'nav_menu' )['wp']['title'], 'Widget de menú de WordPress.' );
		$check( 1100 === $s( 'image' )['wp']['attachment_id'] && '' === $s( 'video' )['wp']['attachment_id'], 'Widgets de medios: ID del adjunto; sin destino, vacío.' );
		$check( '1100' === $s( 'gallery' )['wp']['ids'], 'Galería de WordPress: lista `1,2` traducida y sin lo que no resuelve.' );
		$icon = $s( 'icon_box' );
		$check( 'https://b.test/contacto/?x=1#y' === $icon['link']['url'], 'URL derivada del tipo de control: prefijo del origen → destino, con consulta y fragmento.' );
		$check( 'https://b.test/wp-content/uploads/2026/10/doc.pdf' === $icon['items'][0]['item_link']['url'], 'URL de un archivo bajo uploads dentro de un repetidor: media-url al adjunto importado.' );
		$check( $up . '/2026/01/falta.pdf' === $icon['items'][1]['item_link']['url'] && 'https://otro.test/x' === $icon['items'][2]['item_link']['url'] && '/relativa/' === $icon['items'][3]['item_link']['url'], 'Un archivo que no viajó, una URL externa y una relativa no cambian.' );
		$check( 1 === count( $codes( $ctx, 'media-url.missing' ) ), 'El archivo que no viajó se anota.' );
		$route = $codes( $ctx, 'url.route-changed' );
		$check( 1 === count( $route ) && '/contacto/' === $route[0]['data']['from'] && '/contacto-2/' === $route[0]['data']['to'], 'Si la ruta del destino cambió, se informa (§9.3).' );
		foreach ( array( 'note:sidebar', 'note:google_places', 'note:form', 'note:acf' ) as $note ) {
			$check( 1 === count( $codes( $ctx, $note ) ), "Configurar después: {$note}." );
		}

		// Reglas de visibilidad y condiciones (gate).
		$rules = $by['h000001']['digitalisimo_visibility_rules'];
		$check( array( array( 'id' => 12, 'label' => 'Contacto' ) ) === $rules[0]['rule_post_value'], 'Lista parcial: se quita sólo lo que no resuelve.' );
		$check( array( $never ) === $rules[1]['rule_user_value'], 'Usuario: sin destino nunca queda vacío (vacío = cualquier usuario con sesión): valor de cierre.' );
		$check( '' === $rules[2]['rule_user_value'], 'Un valor vacío no se toca.' );
		$check( array( 3 ) === $rules[3]['rule_user_value'], 'Un resto del editor de otra regla no cuenta.' );
		$check( array( 1007 ) === $rules[4]['rule_products_in_cart_value'] && array( '540' ) === $rules[5]['rule_categories_in_cart_value'], 'Carrito: productos y categorías.' );
		$check( array() === $rules[6]['rule_tags_in_cart_value'], 'Etiquetas del carrito: lista vacía, que ya no coincide con nada.' );
		$check( '12' === $rules[7]['rule_custom_page_id'] && '10' === $rules[8]['rule_custom_page_id'], 'Página por ID: sólo con la página especial «custom».' );
		$check( array( 541 ) === $rules[9]['rule_current_category_page_value'] && array( 540 ) === $rules[10]['rule_single_product_category_value'], 'Categorías de la página y del producto.' );
		$conditions = json_decode( $by['h000001']['e_display_conditions'][0], true );
		$check( array( array( 'id' => $never ) ) === $conditions[0][0]['authors'], 'Autor de la página (nativa): vacío cumpliría siempre; queda el valor de cierre.' );
		$check( array( array( 'id' => 540 ) ) === $conditions[0][1]['categories'] && array( array( 'id' => 12 ) ) === $conditions[1][0]['items'], 'Condiciones con términos y entradas.' );
		$check( 'logged_in' === $conditions[2][0]['status'], 'Una condición sin referencias no cambia.' );
		$review = $ctx->review();
		$check( array( 1500 ) === $ctx->review_ids() && count( $review[1500] ) >= 5, 'Una referencia `gate` sin destino deja el documento para «revisar antes de publicar» (A14).' );
		$check( Remapper::REVIEW === $review[1500][0]['reason'], 'Motivo de la revisión.' );

		// Separadores, CSS y globales.
		$check( 'digi-ola-k1a2b3c' === $out['elements'][0]['settings']['shape_divider_top'] && 'waves' === $out['elements'][0]['settings']['shape_divider_bottom'], 'Separadores: `digi-*` con el mapa `shapes`; los nativos no cambian.' );
		$check( 'digi-mar' === $out['elements'][1]['settings']['shape_divider_top'] && 1 === count( $codes( $ctx, 'map.miss' ) ), 'Un `digi-*` que no viajó no cambia y se anota.' );
		$css = $by['h000001']['custom_css'];
		$check( false !== strpos( $css, 'url("https://b.test/wp-content/uploads/digitalisimo-elements/masks/mask-3.svg")' ), 'CSS: máscara propia → URL del destino.' );
		$check( false !== strpos( $css, 'url(https://b.test/wp-content/uploads/2026/10/foto.jpg)' ) && 1 === count( $codes( $ctx, 'media.size' ) ), 'CSS: un tamaño intermedio de una imagen importada pasa a la completa, con aviso.' );
		$check( false !== strpos( $css, "url('" . $up . "/2026/01/otra.png')" ) && false !== strpos( $css, 'url(https://cdn.otro.test/x.png)' ), 'CSS: lo que no viajó y lo externo se dejan.' );
		$check( false !== strpos( $css, 'url(https://b.test/wp-content/uploads/2026/10/fondo.png)' ), 'CSS: un medio importado por el núcleo se encuentra por su huella.' );
		$check( 1 === count( $codes( $ctx, 'url.left' ) ), 'CSS: sólo la URL del origen que queda se anota.' );
		$check( 'body{background:url(https://b.test/wp-content/uploads/2026/10/foto.jpg)}' === $out['settings']['custom_css'], 'CSS del documento.' );
		$globals = $by['h000001']['__globals__'];
		$check( 'globals/colors?id=kabc123' === $globals['title_color'] && 'globals/typography?id=k222222' === $globals['typography_typography'] && 'globals/colors?id=secondary' === $globals['other'], '`__globals__` de color y tipografía (§9.4).' );
		$check( 'globals/colors?id=k111111' === $by['h000001']['list'][0]['__globals__']['color'] && 'globals/colors?id=kabc123' === $out['settings']['__globals__']['body_color'], '`__globals__` en repetidores y en los ajustes del documento.' );
		$check( array( 'title' => 'x', '__globals__' => array( 'c' => 'globals/colors?id=kabc123' ) ) === Remapper::for_context( $ctx )->rewrite_globals( array( 'title' => 'x', '__globals__' => array( 'c' => 'globals/colors?id=primary' ) ) ), '`__globals__` de los valores por defecto (B1).' );

		// ── 2. Una sola vez (CR-16, CV-12) ──────────────────────────────────────────────────────────────────────
		$once = $context();
		$doc  = array( 'elements' => array( $widget( 'digitalisimo-offcanvas', array( 'template_id' => 30 ) ), $widget( 'digitalisimo-accordion', array( 'tabs' => array( array( 'anywhere_id' => 5 ) ) ) ) ) );
		$first = Remapper::apply( $doc, $once, 1600 );
		$check( 12 === $first['elements'][1]['settings']['tabs'][0]['anywhere_id'], 'Mapa solapado {5→12, 12→20} aplicado una vez: 12.' );
		$again = Remapper::apply( $first, $once, 1600 );
		$check( $first === $again, 'Un segundo guardado del mismo documento no remapea.' );
		kit_stub_post( array( 'ID' => 1601, 'post_type' => 'page' ) );
		kit_stub_post( array( 'ID' => 1602, 'post_type' => 'page' ) );
		update_post_meta( 1601, '_elementor_import_session_id', 'sesion-1' );
		update_post_meta( 1602, '_elementor_import_session_id', 'otra-sesion' );
		update_post_meta( 500, '_elementor_import_session_id', 'sesion-1' );
		$hook = new Remapper( $once );
		$check( $doc === $hook->save_data( $doc, new Kit_Stub_Document( 1602 ) ) && $doc === $hook->save_data( $doc, new Kit_Stub_Document( 500 ) ), 'Un documento ajeno a la sesión (otra sesión, o anterior a la importación) no cambia.' );
		$mine = $hook->save_data( $doc, new Kit_Stub_Document( 1601 ) );
		$check( 1030 === $mine['elements'][0]['settings']['template_id'] && $mine === $hook->save_data( $mine, new Kit_Stub_Document( 1601 ) ) && $hook->is_done( 1601 ), 'El de la sesión se remapea una vez.' );

		// ── 3. Dentro del marcador (CV-19) ──────────────────────────────────────────────────────────────────────
		Core_Document::$calls = array();
		$inner  = array(
			'id'       => 'u000001',
			'elType'   => 'container',
			'settings' => array(),
			'elements' => array(
				array(
					'id'         => 'u000002',
					'elType'     => 'widget',
					'widgetType' => 'acme-hero',
					'settings'   => array( 'post_id' => 5 ),
					'elements'   => array(
						$widget( 'heading', array( 'post_id' => 5 ), 'u000003' ),
						$widget( 'digitalisimo-accordion', array( 'tabs' => array( array( 'template_id' => 30 ) ) ), 'u000004' ),
					),
				),
			),
		);
		$marker = $widget(
			Module::WIDGET,
			array(
				'digitalisimo_unconverted' => array(
					'v'       => 1,
					'type'    => 'container/',
					'reason'  => 'unknown',
					'source'  => 'kit',
					'element' => $inner,
				),
			),
			'm000001'
		);
		$res     = Remapper::apply( array( 'elements' => array( $marker ) ), $context(), 1700 );
		$payload = $res['elements'][0]['settings']['digitalisimo_unconverted']['element'];
		$hero    = $payload['elements'][0];
		$check( 12 === $hero['elements'][0]['settings']['post_id'], 'Marcador: un hijo conocido de un elemento desconocido recibe el remapeo nativo.' );
		$check( 5 === $hero['settings']['post_id'], 'Marcador: el núcleo no remapea lo que no puede instanciar.' );
		$check( 1030 === $hero['elements'][1]['settings']['tabs'][0]['template_id'], 'Marcador: el registro también recorre la carga.' );
		$check( array( 'u000001', 'u000003', 'u000004' ) === Core_Document::$calls, 'Marcador: cada nodo conocido pasa una sola vez por el remapeo nativo: ' . implode( ',', Core_Document::$calls ) );

		// ── 4. Theme Builder y Custom Code (§9.5) ───────────────────────────────────────────────────────────────
		$tb  = Remapper::for_context( $context() );
		$all = $tb->theme_conditions( array( 'include/singular/page/5', 'include/archive/category/40', 'include/singular/child_of/33', 'include/singular/in_category_children/41', array( 'type' => 'exclude', 'name' => 'singular', 'sub_name' => 'any_child_of_category', 'sub_id' => '40' ), 'include/general' ) );
		$check( array() === $all['unresolved'] && array( 'include/singular/page/12', 'include/archive/category/540', 'include/singular/child_of/1032', 'include/singular/in_category_children/541', 'exclude/singular/any_child_of_category/540', 'include/general' ) === array_map( array( Remapper::class, 'condition_string' ), $all['conditions'] ), 'Condiciones traducidas: entradas, términos, child_of e in_*_children (CV-33).' );
		$check( array( 'type', 'name', 'sub_name', 'sub_id' ) === array_keys( $all['conditions'][0] ), 'El orden de claves es el que une `save_conditions()` (FR-2).' );
		foreach ( array( 'exclude/singular/by_author/3', 'include/singular/post_by_author/3', 'include/archive/author/3', 'include/singular/page/404', 'include/singular/acme/7', 'nada/x' ) as $bad ) {
			$closed = $tb->theme_conditions( array( 'include/general', $bad ) );
			$check( array() === $closed['conditions'] && 1 === count( $closed['unresolved'] ), "Sin destino ({$bad}): la plantilla queda sin condiciones y se conserva la original." );
		}

		// ── 5. Regla de cierre: el valor de cierre ──────────────────────────────────────────────────────────────
		$check( $never === absint_like( $never ) && $never === intval( (string) $never ) && $never > 1 && $never < 2147483648, 'El valor de cierre sobrevive a absint() e intval() sin volverse 0 ni 1.' );
		$GLOBALS['wp']['users'][ $never ] = true;
		$exists                          = $context();
		Remapper::apply( array( 'elements' => array( $widget( 'heading', array( 'digitalisimo_visibility_rules' => array( array( 'rule_key' => 'user', 'rule_user_value' => array( 4 ) ) ) ) ) ) ), $exists, 1800 );
		$check( 1 === count( $codes( $exists, 'never-exists' ) ) && $exists->has_errors(), 'Si el valor de cierre fuera un ID real, se informa como error.' );
		unset( $GLOBALS['wp']['users'][ $never ] );

		// ── 6. Metas (scope term_meta y post_meta) ──────────────────────────────────────────────────────────────
		$meta = Remapper::for_context( $context() );
		$check( 1100 === $meta->apply_meta( 'term_meta', 'thumbnail_id', 60, 540 ) && '' === $meta->apply_meta( 'term_meta', 'thumbnail_id', '62', 540 ) && 'x' === $meta->apply_meta( 'term_meta', 'otra', 'x' ), 'Meta de término `thumbnail_id` → adjunto; sin destino, vacía.' );

		// ── 7. Un `kind` sin traducción → _doing_it_wrong ───────────────────────────────────────────────────────
		$before = count( $GLOBALS['wp']['wrong'] );
		$bad    = $meta->translate( array( 'kind' => 'pagina', 'path' => 'x', 'gate' => false, 'never' => null ), 5 );
		$check( '' === $bad && count( $GLOBALS['wp']['wrong'] ) === $before + 1 && false !== strpos( end( $GLOBALS['wp']['wrong'] ), 'sin traducción' ), 'Un tipo de referencia desconocido avisa con _doing_it_wrong y no deja el valor.' );

		// ── 8. URL y prefijos ───────────────────────────────────────────────────────────────────────────────────
		$check( '/x?y#z' === Remapper::after_prefix( 'HTTP://A.TEST/x?y#z', 'https://a.test' ) && null === Remapper::after_prefix( 'https://a.testing/x', 'https://a.test' ) && '' === Remapper::after_prefix( 'https://a.test/sub', 'https://a.test/sub/' ), 'Prefijos sin distinguir esquema ni mayúsculas del host, con límite de segmento.' );
		$sub = new Remapper(
			new Import_Context(
				array(
					'session' => 'x',
					'origin'  => array( 'home' => 'https://a.test/sub', 'uploads' => 'https://a.test/sub/wp-content/uploads' ),
					'maps'    => array( 'url' => array( 'https://a.test/sub' => 'https://b.test/otra', 'https://a.test/sub/wp-content/uploads' => 'https://b.test/otra/wp-content/uploads' ) ),
				)
			),
			array( 'home' => 'https://b.test/otra' )
		);
		$check( 'https://b.test/otra/pagina/' === $sub->url( 'https://a.test/sub/pagina/' ) && 'https://a.test/subasta/' === $sub->url( 'https://a.test/subasta/' ), 'El mapa `url` del runner manda; un prefijo sólo vale en un límite de segmento.' );

		echo "DIGITALÍSIMO Elements: remapeo de kits validado (referencias integradas, URL, medios, CSS, globales, cierre, una sola vez, marcadores y Theme Builder).\n";
	} finally {
		$rmrf = function ( $dir ) use ( &$rmrf ) {
			foreach ( is_dir( $dir ) && ! is_link( $dir ) ? scandir( $dir ) : array() as $name ) {
				if ( '.' !== $name && '..' !== $name ) {
					$path = $dir . '/' . $name;
					is_dir( $path ) && ! is_link( $path ) ? $rmrf( $path ) : unlink( $path );
				}
			}
			if ( is_dir( $dir ) ) {
				rmdir( $dir );
			}
		};
		$rmrf( $tmp );
	}

	/** `absint()` de WordPress. */
	function absint_like( $value ) {
		return abs( (int) $value );
	}
}
