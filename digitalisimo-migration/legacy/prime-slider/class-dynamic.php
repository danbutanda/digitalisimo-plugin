<?php
namespace Digitalisimo\Elements\Legacy\Prime_Slider;

defined( 'ABSPATH' ) || exit;

/**
 * Prime Slider dinámico (§3.E.4, V.10, frente F): las familias E, F, G y L.
 *
 * `Converter::element()` (E) es el único punto de entrada (C15): rellena los valores por defecto, limpia el rastro del
 * proveedor y resuelve las imágenes de demostración, y llama a `build()` si la familia es de F. `build()` devuelve el
 * resultado completo (elemento, fragmento con los hermanos de redes y «Scroll Down», o null) y lo informa con los
 * códigos `ps.dyn.*` (`report/codes-ps-dynamic.php`).
 *
 * | Familia | Widgets | Destino | Respaldo |
 * |---|---|---|---|
 * | E · entradas UIkit | blog, flogia, fluent | `loop-carousel` (1 por vista) + Loop Item generado | `posts` en rejilla |
 * | F · entradas Swiper | mercury, storker, knily (1); pacific, rubix, vertex, fiestar, marble, material (columnas) | `loop-carousel` + Loop Item | `posts` en rejilla |
 * | G · WooCommerce | woocommerce, woolamp, woostand | `loop-carousel` de productos (1 por vista) + Loop Item de producto | `woocommerce-products` en carrusel |
 * | | woocircle, wooexpand | `woocommerce-products` en carrusel con sus partes | — |
 * | | woohotspot | slider anidado (`Builder::nested_slider()` de E) con 3 contenedores, cada uno con un `hotspot` | fragmento con 3 `hotspot` |
 * | L · eventos | event-calendar | con The Events Calendar, `loop-carousel` sobre `tribe_events` + Loop Item (`source = post`) | `posts` en rejilla; sin The Events Calendar, descarte declarado (marcador) |
 *
 * Los mapas (`maps/prime-slider-<id>.php`, 19, de F) declaran `family`, `decision`, `target`, `fallback` y `reason`
 * (lo que lee la prueba de decisiones de E) y, en `dynamic`, lo que lee esta clase: tipo de construcción, motor,
 * claves de navegación, partes del Loop Item, alturas, columnas, pérdidas declaradas y valores por defecto de respaldo
 * (por si el relleno de `Defaults` no los trajo).
 *
 * Pureza (V.7): con `Context::pure()` nunca se crea un Loop Item ni un medio. Si el destino lo necesitaría y no existe
 * ya, `build()` devuelve null (el marcador queda para la etapa `markers`); si el sitio no puede pintar el destino, el
 * respaldo es el mismo que daría la tarea, que no tiene efectos.
 */
final class Dynamic {
	const FAMILIES = array( 'E', 'F', 'G', 'L' );

	/** Los 19 widgets de F (V.10.7). */
	const IDS = array( 'blog', 'flogia', 'fluent', 'mercury', 'storker', 'knily', 'pacific', 'rubix', 'vertex', 'fiestar', 'marble', 'material', 'woocommerce', 'woolamp', 'woostand', 'woocircle', 'wooexpand', 'woohotspot', 'event-calendar' );

	const PREFIX  = 'prime-slider-';
	const BUILDER = '\\Digitalisimo\\Elements\\Legacy\\Prime_Slider\\Builder';
	const MAPS    = '\\Digitalisimo\\Elements\\Legacy\\Prime_Slider\\Maps';
	const NATIVE  = '\\Digitalisimo\\Elements\\Legacy\\Native';
	const CONTEXT = '\\Digitalisimo\\Elements\\Legacy\\Context';
	const REPORT  = '\\Digitalisimo\\Elements\\Legacy\\Report';
	const NESTED  = '\\Digitalisimo\\Elements\\F7b\\Nested\\Module';
	const SANITIZER = '\\Digitalisimo\\Elements\\Legacy\\Prime_Slider\\Sanitizer';

	/** Dispositivos de los controles responsivos del destino (los que no tiene Prime Slider heredan de uno cercano). */
	const DEVICES = array( '' => '', '_widescreen' => '', '_laptop' => '', '_tablet_extra' => '', '_tablet' => '_tablet', '_mobile_extra' => '_tablet', '_mobile' => '_mobile' );

	/** Lista blanca de claves comunes (V.10.2), si `Builder::common()` de E no está. */
	const COMMON = '/^(_margin|_padding|_z_index|_element_width|_element_custom_width|_flex_|_position$|_offset_|_background_|_border_|_box_shadow_|_animation|animation_duration$|_transform_|hide_(desktop|tablet|mobile)$|_attributes$|_mask_|motion_fx_|sticky|custom_css$)/';

	/** Claves de contenido o de disposición que se traducen (no cuentan como estilo sin equivalente). */
	const CONTENT = '/^(item_gap|item_height|slider_height|slider_item_height|viewport_height|slider_min_height|slider_size_ratio|enable_height|columns|overlay_color|content_alignment|content_align|thumbnail_size|primary_thumbnail_size|duration|offset)(_|$)|_toggle$/';

	/**
	 * Rasgos propios de DIGITALÍSIMO Elements que el widget de Prime Slider pudo tener (se registran en todos los
	 * widgets): siguen en el principal. Las extensiones de Element Pack no: las añade y convierte `Converter` (E) con
	 * `Native::element()` sobre el principal (§3.E.0.5), y sólo si el principal no las trae ya.
	 */
	const CARRY = '/^digitalisimo_(visibility_|figures|element_link|tooltip|badge|preset)/';

	/** Claves de estilo (para contar lo que no tiene equivalente). */
	const STYLE = '/(color|typography|_border|radius|padding|margin|shadow|spacing|_size$|_size_|width|height|background|opacity|_gap|offset|text_stroke|blur|glassmorphism)/';

	/** Iconos por red, si la fila de Prime Slider no trae icono (lista de texto). */
	const NETWORK_ICONS = array(
		'facebook'  => 'fab fa-facebook-f',
		'twitter'   => 'fab fa-x-twitter',
		'x'         => 'fab fa-x-twitter',
		'instagram' => 'fab fa-instagram',
		'linkedin'  => 'fab fa-linkedin-in',
		'youtube'   => 'fab fa-youtube',
		'pinterest' => 'fab fa-pinterest',
		'tiktok'    => 'fab fa-tiktok',
		'github'    => 'fab fa-github',
		'dribbble'  => 'fab fa-dribbble',
		'behance'   => 'fab fa-behance',
		'vimeo'     => 'fab fa-vimeo-v',
	);

	/** @var array|null Entorno forzado (pruebas): `ready`, `tags`, `features`, `woocommerce`, `events`. */
	private static $env = null;

	/** @var array<string, array|null> */
	private static $maps = array();

	/* ---------------------------------------------------------------- Entrada (C15) */

	/**
	 * Construye el destino de un widget de las familias E, F, G o L.
	 *
	 * @param string $id       ID corto (`blog`) o `widgetType` (`prime-slider-blog`).
	 * @param array  $filled   Ajustes rellenados, limpios y con las imágenes resueltas (E).
	 * @param array  $element  Elemento original (su `id` y sus ajustes guardados: claves comunes y recuento de estilos).
	 * @param int    $post_id  Documento en conversión (informe).
	 * @return array|null Elemento, fragmento (`elType` = `__fragment`) o null (queda o se envuelve en marcador).
	 */
	public static function build( string $id, array $filled, array $element, int $post_id ): ?array {
		$id  = self::short( $id );
		$map = '' === $id ? null : self::map( $id );
		if ( ! $map || ! in_array( $map['family'] ?? '', self::FAMILIES, true ) || 'DESCARTAR' === ( $map['decision'] ?? '' ) ) {
			return null;
		}
		$cfg   = (array) ( $map['dynamic'] ?? array() );
		$s     = self::with_defaults( $filled, (array) ( $cfg['defaults'] ?? array() ) );
		$cfg   = self::skin( $cfg, $s );
		$codes = array();
		switch ( (string) ( $cfg['kind'] ?? '' ) ) {
			case 'posts':
				$main = self::posts( $id, $s, $cfg, $element, $codes );
				break;
			case 'products':
				$main = self::products( $id, $s, $cfg, $element, $codes );
				break;
			case 'wc':
				$main = self::wc_widget( $s, $cfg, $element, $codes, 'ps.dyn.products_carousel' );
				break;
			case 'hotspot':
				$main = self::hotspot( $s, $cfg, $element, $codes, $post_id );
				break;
			case 'events':
				$main = self::events( $id, $s, $cfg, $element, $codes );
				break;
			default:
				$main = null;
		}
		if ( 'pending' === $main ) {
			// Modo puro y el Loop Item todavía no existe: el marcador queda para la etapa `markers`, sin informe.
			return null;
		}
		$widget = self::PREFIX . $id;
		if ( null === $main ) {
			self::report( $post_id, $codes, $widget, $element );
			return null;
		}
		$out = self::finish( $main, $s, $cfg, $element, $codes );
		self::losses( $s, $cfg, $element, $codes );
		self::report( $post_id, $codes, $widget, $element );
		return $out;
	}

	/**
	 * Estado del destino de un widget en este sitio, sin escribir nada: `target` (el destino de su mapa), `fallback`
	 * (su respaldo), `declared` (descarte declarado: `event-calendar` sin The Events Calendar; no bloquea
	 * `retire_late`) o `blocked` (sin destino ni respaldo: queda en marcador y en «Pendiente de revisar»).
	 *
	 * Lo pueden leer `Converter` (E), `Markers::classify()` (I) y la retirada tardía (A) para distinguir un null de
	 * `build()`. No cuenta la pureza: dice lo que haría la tarea.
	 */
	public static function state( string $id ): string {
		$id  = self::short( $id );
		$map = '' === $id ? null : self::map( $id );
		if ( ! $map ) {
			return 'blocked';
		}
		$cfg = (array) ( $map['dynamic'] ?? array() );
		switch ( (string) ( $cfg['kind'] ?? '' ) ) {
			case 'events':
				if ( false === self::events_active() ) {
					return 'declared';
				}
				return self::loop_possible( 'tribe_events', self::parts( 'post', self::with_defaults( array(), (array) ( $cfg['defaults'] ?? array() ) ), $cfg ) ) ? 'target' : ( false !== self::ready( 'posts' ) ? 'fallback' : 'blocked' );
			case 'posts':
				return self::loop_possible( 'post', self::parts( 'post', self::with_defaults( array(), (array) ( $cfg['defaults'] ?? array() ) ), $cfg ) ) ? 'target' : ( false !== self::ready( 'posts' ) ? 'fallback' : 'blocked' );
			case 'products':
				if ( false === self::woocommerce() ) {
					return 'blocked';
				}
				return self::loop_possible( 'product', self::parts( 'product', self::with_defaults( array(), (array) ( $cfg['defaults'] ?? array() ) ), $cfg ) ) ? 'target' : ( false !== self::ready( 'woocommerce-products' ) ? 'fallback' : 'blocked' );
			case 'wc':
				return false !== self::woocommerce() && false !== self::ready( 'woocommerce-products' ) ? 'target' : 'blocked';
			case 'hotspot':
				if ( false === self::ready( 'hotspot' ) ) {
					return 'blocked';
				}
				return self::nested_possible() ? 'target' : 'fallback';
		}
		return 'blocked';
	}

	/**
	 * Análisis previo de un widget de F (sin escribir nada), por `state()`: el código principal que dejaría la tarea.
	 * Para `Converter::describe()` (E) y el análisis por trozos (A).
	 *
	 * @return array<string, int>
	 */
	public static function describe( string $id ): array {
		$map  = self::map( $id );
		$kind = (string) ( $map['dynamic']['kind'] ?? '' );
		switch ( self::state( $id ) ) {
			case 'target':
				$code = array( 'posts' => 'ps.dyn.loop', 'products' => 'ps.dyn.loop', 'wc' => 'ps.dyn.products_carousel', 'hotspot' => 'ps.dyn.hotspot', 'events' => 'ps.dyn.events' )[ $kind ] ?? 'ps.dyn.loop';
				break;
			case 'fallback':
				$code = array( 'posts' => 'ps.dyn.fallback_posts', 'events' => 'ps.dyn.fallback_posts', 'products' => 'ps.dyn.fallback_products', 'hotspot' => 'ps.dyn.hotspot_fragment' )[ $kind ] ?? 'ps.dyn.blocked';
				break;
			case 'declared':
				$code = 'ps.dyn.events_unavailable';
				break;
			default:
				$code = in_array( $kind, array( 'products', 'wc' ), true ) && ! self::woocommerce() ? 'ps.dyn.no_woocommerce' : 'ps.dyn.blocked';
		}
		return array( $code => 1 );
	}

	/** Mapa de un widget de F (por `Maps::get()` de E si existe; si no, el archivo). */
	public static function map( string $id ): ?array {
		$id = self::short( $id );
		if ( '' === $id || ! in_array( $id, self::IDS, true ) ) {
			return null;
		}
		if ( ! array_key_exists( $id, self::$maps ) ) {
			$maps = self::MAPS;
			if ( class_exists( $maps ) && method_exists( $maps, 'get' ) ) {
				self::$maps[ $id ] = $maps::get( $id );
			} else {
				$file              = __DIR__ . '/maps/' . self::PREFIX . $id . '.php';
				$map               = is_file( $file ) ? require $file : null;
				self::$maps[ $id ] = is_array( $map ) ? $map + array( 'id' => self::PREFIX . $id ) : null;
			}
		}
		return self::$maps[ $id ];
	}

	/** `prime-slider-blog` y `blog` → `blog` ('' si no tiene forma de ID). */
	public static function short( string $id ): string {
		$id = strtolower( trim( $id ) );
		if ( 0 === strpos( $id, self::PREFIX ) ) {
			$id = substr( $id, strlen( self::PREFIX ) );
		}
		return 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id ) ? $id : '';
	}

	/* ---------------------------------------------------------------- Entorno */

	/**
	 * ¿Está registrado el widget de destino? `Native::target_ready()` (A) si existe; si no, el gestor de widgets de
	 * Elementor. null sin Elementor cargado (pruebas): cuenta como disponible (V.10.2).
	 */
	public static function ready( string $widget ): ?bool {
		if ( is_array( self::$env ) && array_key_exists( $widget, (array) ( self::$env['ready'] ?? array() ) ) ) {
			return self::$env['ready'][ $widget ];
		}
		$native = self::NATIVE;
		if ( class_exists( $native, false ) && method_exists( $native, 'target_ready' ) ) {
			return $native::target_ready( $widget );
		}
		$plugin = self::elementor();
		if ( ! $plugin || ! isset( $plugin->widgets_manager ) ) {
			return null;
		}
		return (bool) $plugin->widgets_manager->get_widget_types( $widget );
	}

	/** Experimentos y tipos del constructor: `container`, `loop-item` (documento), `nested` (slider anidado de F7b). */
	public static function feature( string $name ): ?bool {
		if ( is_array( self::$env ) && array_key_exists( $name, (array) ( self::$env['features'] ?? array() ) ) ) {
			return self::$env['features'][ $name ];
		}
		if ( 'nested' === $name ) {
			$nested = self::NESTED;
			if ( class_exists( $nested, false ) && method_exists( $nested, 'ready' ) ) {
				return (bool) $nested::ready();
			}
			return self::elementor() ? false : null;
		}
		$plugin = self::elementor();
		if ( ! $plugin ) {
			return null;
		}
		if ( 'container' === $name ) {
			return isset( $plugin->experiments ) && (bool) $plugin->experiments->is_feature_active( 'container' );
		}
		if ( 'loop-item' === $name ) {
			return isset( $plugin->documents ) && false !== $plugin->documents->get_document_type( 'loop-item', false );
		}
		return null;
	}

	/** ¿Etiqueta dinámica registrada? null sin Elementor cargado. */
	public static function tag( string $name ): ?bool {
		if ( is_array( self::$env ) && array_key_exists( $name, (array) ( self::$env['tags'] ?? array() ) ) ) {
			return self::$env['tags'][ $name ];
		}
		$plugin = self::elementor();
		if ( ! $plugin || ! isset( $plugin->dynamic_tags ) ) {
			return null;
		}
		return null !== $plugin->dynamic_tags->get_tag_info( $name );
	}

	/** ¿WooCommerce activo? */
	public static function woocommerce(): bool {
		if ( is_array( self::$env ) && array_key_exists( 'woocommerce', self::$env ) ) {
			return (bool) self::$env['woocommerce'];
		}
		return class_exists( 'WooCommerce', false ) || function_exists( 'WC' );
	}

	/** ¿The Events Calendar activo (su tipo de entrada registrado)? */
	public static function events_active(): bool {
		if ( is_array( self::$env ) && array_key_exists( 'events', self::$env ) ) {
			return (bool) self::$env['events'];
		}
		return function_exists( 'post_type_exists' ) && post_type_exists( 'tribe_events' );
	}

	/** Fuerza el entorno (pruebas); null lo devuelve a la detección real. */
	public static function set_env( ?array $env ): void {
		self::$env  = $env;
		self::$maps = array();
	}

	/* ---------------------------------------------------------------- Familias E, F y L: entradas */

	/** @return array|string|null elemento, 'pending' (modo puro sin Loop Item) o null. */
	private static function posts( string $id, array $s, array $cfg, array $element, array &$codes ) {
		$parts = self::parts( 'post', $s, $cfg );
		$loop  = self::loop( 'post', $parts, $codes );
		if ( 'pending' === $loop ) {
			return 'pending';
		}
		if ( $loop ) {
			list( $query, $query_codes ) = Query::posts( $s, 'post_query_' );
			$codes                       = array_merge( $codes, $query_codes );
			$codes[]                     = array( 'ps.dyn.loop', array( 'target' => 'loop-carousel', 'id' => $loop ) );
			return self::loop_carousel( 'post', $loop, Query::limit( $s, (int) ( $cfg['limit'] ?? 5 ) ), $query, $s, $cfg, $element, $codes );
		}
		return self::posts_grid( $s, $cfg, $element, $codes, 'posts' );
	}

	/** @return array|string|null */
	private static function events( string $id, array $s, array $cfg, array $element, array &$codes ) {
		if ( ! self::events_active() ) {
			$codes[] = array( 'ps.dyn.events_unavailable', array() );
			return null;
		}
		$codes[] = array( 'ps.dyn.loss.event_dates', array() );
		$parts   = self::parts( 'post', $s, $cfg );
		$loop    = self::loop( 'tribe_events', $parts, $codes );
		if ( 'pending' === $loop ) {
			return 'pending';
		}
		if ( $loop ) {
			list( $query, $query_codes ) = Query::events( $s, 'post_query_' );
			$codes                       = array_merge( $codes, $query_codes );
			$codes[]                     = array( 'ps.dyn.events', array( 'target' => 'loop-carousel', 'id' => $loop ) );
			return self::loop_carousel( 'post', $loop, Query::limit( $s, 6, 'limit' ), $query, $s, $cfg, $element, $codes );
		}
		return self::posts_grid( $s, $cfg, $element, $codes, 'events' );
	}

	/** Rejilla clásica del widget Entradas (respaldo sin Loop Item posible). */
	private static function posts_grid( array $s, array $cfg, array $element, array &$codes, string $query_kind ): ?array {
		if ( false === self::ready( 'posts' ) ) {
			$codes[] = array( 'ps.dyn.blocked', array( 'target' => 'posts' ) );
			return null;
		}
		$parts   = self::parts( 'post', $s, $cfg );
		$columns = self::columns( $s, $cfg );
		$limit   = 'events' === $query_kind ? Query::limit( $s, 6, 'limit' ) : Query::limit( $s, (int) ( $cfg['limit'] ?? 5 ) );
		list( $query, $query_codes ) = 'events' === $query_kind ? Query::events( $s, 'posts_' ) : Query::posts( $s, 'posts_' );
		$codes   = array_merge( $codes, $query_codes );
		$set     = array(
			'_skin'                  => 'classic',
			'classic_posts_per_page' => $limit,
			'classic_columns'        => (string) $columns[''],
			'classic_columns_tablet' => (string) $columns['_tablet'],
			'classic_columns_mobile' => (string) $columns['_mobile'],
			'classic_thumbnail'      => 'top',
			'classic_masonry'        => '',
			'classic_show_title'     => '' !== $parts['title'] ? 'yes' : '',
			'classic_title_tag'      => '' !== $parts['title'] ? $parts['title'] : 'h3',
			'classic_meta_data'      => $parts['meta'],
			'classic_meta_separator' => '|',
			'classic_show_excerpt'   => $parts['excerpt'] >= 0 ? 'yes' : '',
			'classic_excerpt_length' => $parts['excerpt'] > 0 ? $parts['excerpt'] : 25,
			'classic_show_read_more' => '' !== $parts['button'] ? 'yes' : '',
			'classic_read_more_text' => '' !== $parts['button'] ? $parts['button'] : '',
			'pagination_type'        => '',
		);
		if ( '' !== $parts['terms'] ) {
			$set['digitalisimo_post_terms'] = array( $parts['terms'] );
		}
		$codes[] = array( 'ps.dyn.fallback_posts', array( 'target' => 'posts' ) );
		return self::widget( self::element_id( $element ), 'posts', $set + $query );
	}

	/* ---------------------------------------------------------------- Familia G: productos */

	/** @return array|string|null */
	private static function products( string $id, array $s, array $cfg, array $element, array &$codes ) {
		if ( ! self::woocommerce() ) {
			$codes[] = array( 'ps.dyn.no_woocommerce', array() );
			return null;
		}
		$parts = self::parts( 'product', $s, $cfg );
		$loop  = self::loop( 'product', $parts, $codes );
		if ( 'pending' === $loop ) {
			return 'pending';
		}
		if ( $loop ) {
			list( $query, $query_codes ) = Query::products( $s, 'product_query_' );
			$codes                       = array_merge( $codes, $query_codes );
			$codes[]                     = array( 'ps.dyn.loop', array( 'target' => 'loop-carousel', 'id' => $loop ) );
			return self::loop_carousel( 'product', $loop, Query::limit( $s, (int) ( $cfg['limit'] ?? 3 ) ), $query, $s, $cfg, $element, $codes );
		}
		return self::wc_widget( $s, $cfg, $element, $codes, 'ps.dyn.fallback_products' );
	}

	/** `woocommerce-products` en carrusel, con las partes del producto (woocircle, wooexpand y respaldo de G). */
	private static function wc_widget( array $s, array $cfg, array $element, array &$codes, string $code ): ?array {
		if ( ! self::woocommerce() ) {
			$codes[] = array( 'ps.dyn.no_woocommerce', array() );
			return null;
		}
		if ( false === self::ready( 'woocommerce-products' ) ) {
			$codes[] = array( 'ps.dyn.blocked', array( 'target' => 'woocommerce-products' ) );
			return null;
		}
		$p       = (array) ( $cfg['parts'] ?? array() );
		$limit   = Query::limit( $s, (int) ( $cfg['limit'] ?? 3 ) );
		$columns = self::columns( $s, $cfg );
		$on      = static function ( $key ) use ( $s ) {
			return is_string( $key ) && '' !== $key && self::truthy( $s[ $key ] ?? '' );
		};
		$parts = array( 'image' );
		$title = self::title_tag( $s, $p );
		if ( '' !== $title ) {
			$parts[] = 'title';
		}
		if ( isset( $p['excerpt'][0] ) && $on( $p['excerpt'][0] ) ) {
			$parts[] = 'excerpt';
		}
		foreach ( array( 'category' => 'terms', 'rating' => 'rating', 'price' => 'price', 'cart' => 'cart', 'badge' => 'badge' ) as $part => $key ) {
			if ( isset( $p[ $key ] ) && $on( $p[ $key ] ) ) {
				$parts[] = $part;
			}
		}
		$nav = self::navigation( $s, $cfg );
		list( $query, $query_codes ) = Query::products( $s, 'query_' );
		$codes = array_merge( $codes, $query_codes );
		$set   = array(
			'columns'                        => (string) $columns[''],
			'columns_tablet'                 => (string) $columns['_tablet'],
			'columns_mobile'                 => (string) $columns['_mobile'],
			'rows'                           => (int) ceil( $limit / max( 1, $columns[''] ) ),
			'paginate'                       => '',
			'show_onsale_flash'              => in_array( 'badge', $parts, true ) ? 'yes' : '',
			'digitalisimo_wc_custom'         => 'yes',
			'digitalisimo_wc_parts'          => $parts,
			'digitalisimo_wc_title_tag'      => '' !== $title ? $title : 'h2',
			'digitalisimo_wc_excerpt_length' => max( 1, self::excerpt_words( $s, $p, 10 ) ),
			'digitalisimo_products_limit'    => $limit,
			'digitalisimo_wc_layout'         => 'carousel',
			'digitalisimo_wc_navigation'     => $nav['arrows'] ? 'yes' : '',
		);
		if ( ! $nav['arrows'] && $limit > $columns[''] ) {
			// El carrusel propio sólo tiene flechas: se ponen para no dejarlo sin navegación (WCAG 2.1.1).
			$set['digitalisimo_wc_navigation'] = 'yes';
			$codes[]                           = array( $nav['dots'] ? 'ps.dyn.loss.dots' : 'ps.dyn.nav_none', array() );
		} elseif ( $nav['dots'] ) {
			$codes[] = array( 'ps.dyn.loss.dots', array() );
		}
		if ( 'ps.dyn.fallback_products' === $code && in_array( 'cart', $parts, true ) ) {
			// El botón del bucle de WooCommerce no lleva el selector de cantidad del formulario del producto.
			$codes[] = array( 'ps.dyn.loss.product_form', array() );
		}
		if ( self::autoplay( $s, $cfg )['on'] ) {
			$codes[] = array( 'ps.dyn.loss.autoplay', array() );
		}
		if ( 'h1' === $title ) {
			$codes[] = array( 'ps.dyn.h1', array() );
		}
		$codes[] = array( $code, array( 'target' => 'woocommerce-products' ) );
		return self::widget( self::element_id( $element ), 'woocommerce-products', $set + $query );
	}

	/* ---------------------------------------------------------------- woohotspot */

	/**
	 * Tres diapositivas fijas (`image`/`markers`, `two_image`/`two_markers`, `three_image`/`three_markers`) → un
	 * `hotspot` por diapositiva: imagen, puntos (`hotspot_offset_x/_y` ← `marker_x/_y_position`) y globo con el título,
	 * el texto, el precio y el enlace. Con el slider anidado y `Builder` de E, dentro del slider; si no, en un fragmento.
	 */
	private static function hotspot( array $s, array $cfg, array $element, array &$codes, int $post_id ): ?array {
		if ( false === self::ready( 'hotspot' ) ) {
			$codes[] = array( 'ps.dyn.blocked', array( 'target' => 'hotspot' ) );
			return null;
		}
		$base    = self::element_id( $element );
		$spots   = array();
		$missing = 0;
		foreach ( array( '' => 'image', 'two_' => 'two_image', 'three_' => 'three_image' ) as $prefix => $image_key ) {
			$image = self::media( $s[ $image_key ] ?? null );
			if ( '' === $image['url'] ) {
				++$missing;
			}
			$rows = array();
			foreach ( (array) ( $s[ $prefix . 'markers' ] ?? array() ) as $index => $marker ) {
				if ( is_array( $marker ) ) {
					$rows[] = self::hotspot_row( $marker, $s, substr( md5( $base . ':' . $prefix . ':' . $index ), 0, 7 ) );
				}
			}
			$settings = array(
				'image'      => $image,
				'image_size' => self::image_size( $s['thumbnail_size'] ?? 'full' ),
				'hotspot'    => $rows,
			);
			$spots[] = self::widget( substr( md5( $base . ':hotspot:' . $prefix ), 0, 7 ), 'hotspot', $settings );
		}
		if ( $missing ) {
			$codes[] = array( 'ps.dyn.hotspot_no_image', array( 'count' => $missing ) );
		}
		$codes[] = array( 'ps.dyn.loss.tooltip', array() );
		$builder = self::BUILDER;
		if ( self::nested_possible() && class_exists( $builder ) && method_exists( $builder, 'nested_slider' ) && method_exists( $builder, 'container' ) ) {
			$children = array();
			foreach ( $spots as $spot ) {
				$children[] = $builder::container( array(), array( $spot ) );
			}
			$slider = $builder::nested_slider( self::nested_settings( $s, count( $children ), $post_id, $codes ), $children );
			if ( is_array( $slider ) && $slider ) {
				$slider['id'] = $base;
				$codes[]      = array( 'ps.dyn.hotspot', array( 'target' => 'digitalisimo-nested-slider', 'count' => count( $children ) ) );
				return $slider;
			}
		}
		$codes[] = array( 'ps.dyn.hotspot_fragment', array( 'target' => 'hotspot', 'count' => count( $spots ) ) );
		$spots[0]['id'] = $base;
		return array( 'elType' => '__fragment', 'elements' => $spots );
	}

	/** Un punto de woohotspot → una fila del repetidor `hotspot` de PRO. */
	private static function hotspot_row( array $m, array $s, string $id ): array {
		$type = (string) ( $m['select_type'] ?? 'icon' );
		$icon = is_array( $m['marker_select_icon'] ?? null ) && is_string( $m['marker_select_icon']['value'] ?? null ) && '' !== $m['marker_select_icon']['value'] ? array( 'value' => (string) $m['marker_select_icon']['value'], 'library' => (string) ( $m['marker_select_icon']['library'] ?? '' ) ) : array( 'value' => '', 'library' => '' );
		$row  = array(
			'_id'                     => is_string( $m['_id'] ?? null ) && 1 === preg_match( '/^[a-z0-9]{1,16}$/i', $m['_id'] ) ? $m['_id'] : $id,
			'hotspot_label'           => 'text' === $type ? self::plain( $m['text'] ?? '' ) : '',
			'hotspot_icon'            => 'icon' === $type ? $icon : array( 'value' => '', 'library' => '' ),
			'hotspot_horizontal'      => 'left',
			'hotspot_vertical'        => 'top',
			'hotspot_tooltip_content' => self::tooltip( $m, $s ),
		);
		foreach ( array( 'x' => 'marker_x_position', 'y' => 'marker_y_position' ) as $axis => $key ) {
			foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
				$value = $m[ $key . $device ] ?? null;
				if ( is_array( $value ) && is_numeric( $value['size'] ?? null ) ) {
					$row[ 'hotspot_offset_' . $axis . $device ] = array( 'unit' => '%', 'size' => max( 0, min( 100, 0 + $value['size'] ) ), 'sizes' => array() );
				}
			}
		}
		$place = strtok( (string) ( $m['marker_tooltip_placement'] ?? 'top' ), '-' );
		if ( in_array( $place, array( 'right', 'bottom', 'left' ), true ) ) {
			$row['hotspot_tooltip_position'] = 'yes';
			$row['hotspot_position']         = $place;
		}
		return $row;
	}

	/** Globo de un punto: título, texto, precio y enlace (sólo con texto y enlace de verdad), todo escapado. */
	private static function tooltip( array $m, array $s ): string {
		$html  = '';
		$tag   = in_array( (string) ( $s['title_tag'] ?? 'h3' ), array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), true ) ? (string) ( $s['title_tag'] ?? 'h3' ) : 'h3';
		$title = self::plain( $m['product_title'] ?? '' );
		if ( '' !== $title && self::truthy( $s['show_title'] ?? 'yes' ) ) {
			$html .= '<' . $tag . '>' . self::esc( $title ) . '</' . $tag . '>';
		}
		$text = is_scalar( $m['product_text'] ?? null ) ? trim( (string) $m['product_text'] ) : '';
		if ( '' !== $text && self::truthy( $s['show_text'] ?? 'yes' ) ) {
			$html .= function_exists( 'wp_kses_post' ) ? wp_kses_post( $text ) : '<p>' . self::esc( strip_tags( $text ) ) . '</p>';
		}
		$price = self::plain( $m['product_price'] ?? '' );
		if ( '' !== $price && self::truthy( $s['show_price'] ?? 'yes' ) ) {
			$html .= '<p>' . self::esc( $price ) . '</p>';
		}
		$label = self::plain( $m['product_link_text'] ?? '' );
		$link  = is_array( $m['product_link'] ?? null ) ? (array) $m['product_link'] : array( 'url' => $m['product_link'] ?? '' );
		$url   = self::safe_url( $link['url'] ?? '' );
		if ( '' !== $label && '' !== $url && self::truthy( $s['show_button'] ?? 'yes' ) ) {
			$external = 'on' === ( $link['is_external'] ?? '' ) ? ' target="_blank" rel="noopener"' : '';
			$html    .= '<p><a href="' . ( function_exists( 'esc_url' ) ? esc_url( $url ) : self::esc( $url ) ) . '"' . $external . '>' . self::esc( $label ) . '</a></p>';
		}
		return $html;
	}

	/**
	 * Ajustes del slider anidado para woohotspot (la tabla de §3.E.2 con sus claves de Swiper): transición, velocidad,
	 * reproducción automática acotada a 2 000–20 000 ms, pausa, bucle, navegación y nombre accesible.
	 */
	private static function nested_settings( array $s, int $count, int $post_id, array &$codes ): array {
		$auto    = self::autoplay( $s, array( 'engine' => 'swiper' ) );
		$effects = array( 'slide' => 'slide', 'fade' => 'fade', 'cube' => 'slide', 'flip' => 'slide', 'coverflow' => 'slide', 'creative' => 'fade', 'cards' => 'fade', 'tinder' => 'fade', 'shutters' => 'fade', 'slicer' => 'fade', 'gl' => 'fade' );
		$out     = array( 'effect' => $effects[ $auto['effect'] ] ?? 'slide' );
		if ( ! in_array( $auto['effect'], array( 'slide', 'fade' ), true ) ) {
			$codes[] = array( 'ps.dyn.loss.effect', array( 'type' => $auto['effect'] ) );
		}
		if ( 'slide' !== $out['effect'] && null !== $auto['speed'] ) {
			$out['speed'] = max( 0, min( 3000, $auto['speed'] ) );
		}
		$out['autoplay']       = $auto['on'] ? 'yes' : '';
		$out['autoplay_delay'] = max( 2000, min( 20000, $auto['delay'] ) );
		$out['pause_on_hover'] = $auto['pause'] ? 'yes' : '';
		$out['loop']           = $auto['loop'] ? 'yes' : '';
		$nav                   = self::navigation( $s, array( 'nav' => array( 'arrows' => 'show_navigation_arrows', 'dots' => 'show_navigation_dots' ) ) );
		$out['navigation']     = $nav['arrows'] && $nav['dots'] ? 'both' : ( $nav['arrows'] ? 'arrows' : 'dots' );
		if ( ! $nav['arrows'] && ! $nav['dots'] && $count > 1 ) {
			$codes[] = array( 'ps.dyn.nav_none', array() );
		}
		$title = $post_id && function_exists( 'get_the_title' ) ? trim( self::plain( get_the_title( $post_id ) ) ) : '';
		if ( '' !== $title ) {
			$out['carousel_label'] = function_exists( 'mb_substr' ) ? mb_substr( $title, 0, 80, 'UTF-8' ) : substr( $title, 0, 80 );
		}
		return $out;
	}

	/* ---------------------------------------------------------------- Loop Carousel */

	/**
	 * Loop Item para estas partes: ID, 0 (no se puede: respaldo) o 'pending' (modo puro y el sitio podría crearlo).
	 *
	 * @return int|string
	 */
	private static function loop( string $source, array $parts, array &$codes ) {
		if ( ! self::loop_possible( $source, $parts ) ) {
			return 0;
		}
		$id = Loop_Item::ensure( $source, $parts );
		if ( $id ) {
			return $id;
		}
		if ( self::pure() ) {
			return 'pending';
		}
		$codes[] = array( 'ps.dyn.loop_missing', array() );
		return 0;
	}

	private static function loop_possible( string $source, array $parts ): bool {
		return false !== self::ready( 'loop-carousel' ) && Loop_Item::ready( $source, $parts );
	}

	private static function nested_possible(): bool {
		return false !== self::feature( 'nested' ) && false !== self::ready( 'digitalisimo-nested-slider' );
	}

	/** El Loop Carousel con su plantilla, su consulta y los ajustes de carrusel del origen. */
	private static function loop_carousel( string $skin, int $loop, int $limit, array $query, array $s, array $cfg, array $element, array &$codes ): array {
		$set = array(
			'_skin'          => $skin,
			'template_id'    => (string) $loop,
			'posts_per_page' => $limit,
		) + self::carousel( $s, $cfg, $codes ) + $query;
		$parts = self::parts( $skin, $s, $cfg );
		if ( 'h1' === $parts['title'] ) {
			$codes[] = array( 'ps.dyn.h1', array() );
		}
		return self::widget( self::element_id( $element ), 'loop-carousel', $set );
	}

	/** Ajustes de carrusel del Loop Carousel: diapositivas por vista, paso, separación, reproducción, bucle y navegación. */
	private static function carousel( array $s, array $cfg, array &$codes ): array {
		$out     = array();
		$columns = self::columns( $s, $cfg );
		foreach ( self::DEVICES as $device => $from ) {
			$out[ 'slides_to_show' . $device ] = (string) $columns[ $from ];
		}
		foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
			$scroll = $s[ 'slides_to_scroll' . $device ] ?? ( '' === $device ? 1 : null );
			if ( is_numeric( $scroll ) && (int) $scroll >= 1 ) {
				$out[ 'slides_to_scroll' . $device ] = (string) min( 8, (int) $scroll );
			}
		}
		$gap = (array) ( $cfg['gap'] ?? array( 0, 0, 0 ) );
		foreach ( array( '' => 0, '_tablet' => 1, '_mobile' => 2 ) as $device => $index ) {
			$value = $s[ 'item_gap' . $device ] ?? null;
			$size  = is_array( $value ) && is_numeric( $value['size'] ?? null ) ? (int) $value['size'] : (int) ( $gap[ $index ] ?? 0 );
			$out[ 'image_spacing_custom' . $device ] = array( 'unit' => 'px', 'size' => max( 0, min( 400, $size ) ), 'sizes' => array() );
		}
		$auto                   = self::autoplay( $s, $cfg );
		$out['autoplay']        = $auto['on'] ? 'yes' : '';
		$out['autoplay_speed']  = $auto['delay'];
		$out['pause_on_hover']  = $auto['pause'] ? 'yes' : '';
		$out['infinite']        = $auto['loop'] ? 'yes' : '';
		if ( null !== $auto['speed'] ) {
			$out['speed'] = $auto['speed'];
		}
		$nav = self::navigation( $s, $cfg );
		if ( ! $nav['arrows'] && ! $nav['dots'] ) {
			// Sin flechas ni puntos no habría otra forma de cambiar de diapositiva que arrastrar (WCAG 2.1.1, 2.5.7).
			$nav['dots'] = true;
			$codes[]     = array( 'ps.dyn.nav_none', array() );
		}
		$out['arrows']     = $nav['arrows'] ? 'yes' : '';
		$out['pagination'] = $nav['dots'] ? 'bullets' : '';
		if ( 'slide' !== $auto['effect'] ) {
			$codes[] = array( 'ps.dyn.loss.effect', array( 'type' => $auto['effect'] ) );
		}
		return $out;
	}

	/**
	 * Reproducción automática, pausa, bucle, velocidad y efecto según el motor del origen: UIkit (`autoplay` no vacío,
	 * `autoplay_interval`, `pause_on_hover`, `finite` = «Loop», animación `fade` fija de `render_slideshows_settings()`)
	 * o Swiper (`autoplay` = yes, `autoplay_speed`, `pauseonhover`, `loop`, `speed.size`, `swiper_effect`/`effect`/
	 * `transition`).
	 */
	private static function autoplay( array $s, array $cfg ): array {
		if ( 'uikit' === ( $cfg['engine'] ?? 'swiper' ) ) {
			$delay = (int) ( $s['autoplay_interval'] ?? 7000 );
			return array(
				'on'     => self::truthy( $s['autoplay'] ?? 'yes' ),
				'delay'  => $delay > 0 ? $delay : 7000,
				'pause'  => 'yes' === ( $s['pause_on_hover'] ?? '' ),
				'loop'   => self::truthy( $s['finite'] ?? 'yes' ),
				'speed'  => null,
				'effect' => 'fade',
			);
		}
		$speed  = $s['speed'] ?? null;
		$speed  = is_array( $speed ) ? ( $speed['size'] ?? null ) : $speed;
		$effect = '';
		foreach ( array( 'swiper_effect', 'effect', 'transition' ) as $key ) {
			if ( isset( $s[ $key ] ) && is_string( $s[ $key ] ) && '' !== $s[ $key ] ) {
				$effect = $s[ $key ];
				break;
			}
		}
		$effect = '' === $effect ? (string) ( $cfg['effect'] ?? 'slide' ) : $effect;
		$delay  = (int) ( $s['autoplay_speed'] ?? 5000 );
		return array(
			'on'     => 'yes' === ( $s['autoplay'] ?? '' ),
			'delay'  => $delay > 0 ? $delay : 5000,
			'pause'  => 'yes' === ( $s['pauseonhover'] ?? '' ),
			'loop'   => 'yes' === ( $s['loop'] ?? '' ),
			'speed'  => is_numeric( $speed ) && (int) $speed > 0 ? min( 10000, (int) $speed ) : null,
			'effect' => 1 === preg_match( '/^[a-z0-9_-]{1,20}$/', $effect ) ? $effect : 'slide',
		);
	}

	/**
	 * Flechas y puntos visibles en el origen. `nav` del mapa: `arrows` y `dots` (clave del interruptor o valor fijo),
	 * `both` (un interruptor para los dos) o `select` (un desplegable `both`/`arrows`/`dots`/`none`).
	 */
	private static function navigation( array $s, array $cfg ): array {
		$nav = (array) ( $cfg['nav'] ?? array() );
		if ( isset( $nav['select'] ) ) {
			$value = (string) ( $s[ $nav['select'] ] ?? 'arrows' );
			return array( 'arrows' => in_array( $value, array( 'both', 'arrows' ), true ), 'dots' => in_array( $value, array( 'both', 'dots' ), true ) );
		}
		if ( isset( $nav['both'] ) ) {
			$on = self::truthy( $s[ $nav['both'] ] ?? 'yes' );
			return array( 'arrows' => $on, 'dots' => $on );
		}
		$get = static function ( $rule ) use ( $s ) {
			if ( is_bool( $rule ) ) {
				return $rule;
			}
			return is_string( $rule ) && self::truthy( $s[ $rule ] ?? '' );
		};
		return array( 'arrows' => $get( $nav['arrows'] ?? false ), 'dots' => $get( $nav['dots'] ?? false ) );
	}

	/** Columnas por dispositivo (`''`, `_tablet`, `_mobile`), de 1 a 8. 1 por vista en los héroes. */
	private static function columns( array $s, array $cfg ): array {
		if ( 'columns' !== ( $cfg['per_view'] ?? 1 ) ) {
			$fixed = (array) ( $cfg['per_view_fixed'] ?? array( 1, 1, 1 ) );
			return array( '' => self::clamp( $fixed[0] ?? 1 ), '_tablet' => self::clamp( $fixed[1] ?? 1 ), '_mobile' => self::clamp( $fixed[2] ?? 1 ) );
		}
		$defaults = (array) ( $cfg['columns'] ?? array( 3, 2, 1 ) );
		$out      = array();
		foreach ( array( '' => 0, '_tablet' => 1, '_mobile' => 2 ) as $device => $index ) {
			$value          = $s[ 'columns' . $device ] ?? $defaults[ $index ] ?? 1;
			$out[ $device ] = self::clamp( is_numeric( $value ) ? (int) $value : (int) ( $defaults[ $index ] ?? 1 ) );
		}
		return $out;
	}

	/* ---------------------------------------------------------------- Partes del Loop Item */

	/**
	 * Partes del Loop Item a partir de los ajustes y del mapa (`parts`): título y su etiqueta, extracto y sus
	 * palabras, términos, metadatos, botón, precio y carrito, alineación, alturas, superposición y colores.
	 */
	private static function parts( string $source, array $s, array $cfg ): array {
		$p       = (array) ( $cfg['parts'] ?? array() );
		$product = 'product' === $source;
		$on      = static function ( $key ) use ( $s ) {
			return is_string( $key ) && '' !== $key && self::truthy( $s[ $key ] ?? '' );
		};
		$parts = array(
			'title'   => self::title_tag( $s, $p ),
			'excerpt' => isset( $p['excerpt'][0] ) && $on( $p['excerpt'][0] ) ? self::excerpt_words( $s, $p, 0 ) : -1,
			'terms'   => isset( $p['terms'] ) && $on( $p['terms'] ) ? ( $product ? 'product_cat' : ( 'event' === ( $p['taxonomy'] ?? '' ) ? 'tribe_events_cat' : 'category' ) ) : '',
			'meta'    => array(),
			'button'  => '',
			'price'   => $product && isset( $p['price'] ) && $on( $p['price'] ),
			'cart'    => $product && isset( $p['cart'] ) && $on( $p['cart'] ),
		);
		if ( isset( $p['meta'] ) && is_array( $p['meta'] ) && ( empty( $p['meta']['switch'] ) || $on( $p['meta']['switch'] ) ) ) {
			foreach ( array( 'author', 'date', 'comments' ) as $type ) {
				$keys = (array) ( $p['meta'][ $type ] ?? array() );
				foreach ( $keys as $key ) {
					if ( $on( $key ) ) {
						$parts['meta'][] = $type;
						break;
					}
				}
			}
		}
		if ( isset( $p['button'][0] ) && ( true === $p['button'][0] || $on( $p['button'][0] ) ) ) {
			$text = $s[ $p['button'][1] ?? '' ] ?? ( $p['button'][2] ?? '' );
			$parts['button'] = self::plain( self::text( $text ) );
			if ( isset( $p['button_link'] ) ) {
				$link                = $s[ $p['button_link'] ] ?? array();
				$parts['button_url'] = self::safe_url( is_array( $link ) ? ( $link['url'] ?? '' ) : $link );
				if ( '' === $parts['button_url'] ) {
					// Botón de un enlace fijo sin enlace: no se pinta (como el botón de las diapositivas).
					$parts['button'] = '';
				}
			}
		}
		$align = $s['content_alignment'] ?? ( $s['content_align'] ?? '' );
		if ( is_string( $align ) && in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			$parts['align'] = $align;
		}
		$parts += self::heights( $s, $cfg );
		$overlay = (string) ( $s['overlay'] ?? ( $cfg['overlay'] ?? 'background' ) );
		if ( 'none' !== $overlay ) {
			// Sin color propio, el de la hoja del origen (rgba(0,0,0,.5)); con él, tal cual.
			$color            = is_string( $s['overlay_color'] ?? null ) ? trim( $s['overlay_color'] ) : '';
			$parts['overlay'] = '' === $color ? array( 'color' => '#000000', 'opacity' => 0.5 ) : array( 'color' => $color, 'opacity' => 1 );
		}
		foreach ( (array) ( $cfg['colors'] ?? array() ) as $name => $keys ) {
			foreach ( (array) $keys as $key ) {
				if ( isset( $s[ $key ] ) && is_string( $s[ $key ] ) && '' !== $s[ $key ] ) {
					$parts['colors'][ $name ] = $s[ $key ];
					break;
				}
			}
		}
		return Loop_Item::normalize( 'product' === $source ? 'product' : 'post', $parts );
	}

	/** Alturas: UIkit (`enable_height` + `viewport_height`, o proporción `W/H` con su mínimo) o la clave de altura del mapa. */
	private static function heights( array $s, array $cfg ): array {
		$out = array( 'height' => array() );
		if ( 'ratio' === ( $cfg['height'] ?? '' ) ) {
			if ( self::truthy( $s['enable_height'] ?? '' ) && is_numeric( $s['viewport_height']['size'] ?? null ) ) {
				foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
					$value = $s[ 'viewport_height' . $device ] ?? null;
					if ( is_array( $value ) && is_numeric( $value['size'] ?? null ) ) {
						$out['height'][ $device ] = array( 'unit' => (string) ( $value['unit'] ?? 'vh' ), 'size' => 0 + $value['size'] );
					}
				}
				return $out;
			}
			$ratio = $s['slider_size_ratio'] ?? array();
			$w     = is_array( $ratio ) && is_numeric( $ratio['width'] ?? null ) ? (int) $ratio['width'] : 0;
			$h     = is_array( $ratio ) && is_numeric( $ratio['height'] ?? null ) ? (int) $ratio['height'] : 0;
			$out['ratio'] = $w > 0 && $h > 0 && $w < 1000 && $h < 1000 ? $w . '/' . $h : '16/9';
			$min          = $s['slider_min_height']['size'] ?? null;
			$out['height'][''] = array( 'unit' => 'px', 'size' => is_numeric( $min ) && (float) $min > 0 ? 0 + $min : (int) ( $cfg['min_height'] ?? 440 ) );
			return $out;
		}
		$key = (string) ( $cfg['height'] ?? '' );
		if ( '' !== $key ) {
			foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
				$value = $s[ $key . $device ] ?? null;
				if ( is_array( $value ) && is_numeric( $value['size'] ?? null ) && (float) $value['size'] > 0 ) {
					$out['height'][ $device ] = array( 'unit' => (string) ( $value['unit'] ?? 'px' ) ?: 'px', 'size' => 0 + $value['size'] );
				}
			}
		}
		if ( ! isset( $out['height'][''] ) && ! empty( $cfg['min_height'] ) ) {
			$out['height'][''] = array( 'unit' => 'px', 'size' => (int) $cfg['min_height'] );
		}
		return $out;
	}

	/** Etiqueta del título ('' si no se muestra). */
	private static function title_tag( array $s, array $p ): string {
		if ( ! isset( $p['title'][0] ) || ! self::truthy( $s[ $p['title'][0] ] ?? '' ) ) {
			return '';
		}
		$tag = (string) ( $s[ $p['title'][1] ?? '' ] ?? ( $p['title'][2] ?? 'h3' ) );
		return in_array( $tag, Loop_Item::TAGS, true ) ? $tag : 'h3';
	}

	/** Palabras del extracto (0 = sin límite). */
	private static function excerpt_words( array $s, array $p, int $default ): int {
		$value = $s[ $p['excerpt'][1] ?? '' ] ?? null;
		$value = is_array( $value ) ? ( $value['size'] ?? null ) : $value;
		return is_numeric( $value ) && (int) $value > 0 ? min( 500, (int) $value ) : $default;
	}

	/* ---------------------------------------------------------------- Resultado */

	/** Claves comunes en el elemento principal y hermanos (redes y «Scroll Down»): elemento o fragmento. */
	private static function finish( array $main, array $s, array $cfg, array $element, array &$codes ) {
		$original = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		if ( '__fragment' === ( $main['elType'] ?? '' ) ) {
			if ( isset( $main['elements'][0]['settings'] ) ) {
				$main['elements'][0]['settings'] = self::merge( self::common( $original, true, $codes ), self::carry( $original ), (array) $main['elements'][0]['settings'] );
			}
			return $main;
		}
		$main['settings'] = self::merge( self::common( $original, true, $codes ), self::carry( $original ), (array) ( $main['settings'] ?? array() ) );
		$siblings         = array();
		if ( ! empty( $cfg['social'] ) ) {
			$social = self::social( $s, $element, $codes );
			if ( $social ) {
				$siblings[] = $social;
			}
		}
		if ( ! empty( $cfg['scroll'] ) ) {
			$scroll = self::scroll( $s, $element, $codes );
			if ( $scroll ) {
				$siblings[] = $scroll;
			}
		}
		if ( ! $siblings ) {
			return $main;
		}
		return array( 'elType' => '__fragment', 'elements' => array_merge( array( $main ), $siblings ) );
	}

	/** Claves comunes del widget origen (`Builder::common()` de E o la misma lista blanca). */
	private static function common( array $from, bool $main, array &$codes ): array {
		$builder = self::BUILDER;
		$out     = class_exists( $builder ) && method_exists( $builder, 'common' ) ? (array) $builder::common( $from, $main ) : self::whitelist( $from, $main );
		if ( isset( $out['custom_css'] ) && is_string( $out['custom_css'] ) && 1 === preg_match( '/(^|[^a-z0-9_-])(bdt-|prime-slider)/i', $out['custom_css'] ) ) {
			$codes[] = array( 'ps.dyn.review.custom_css', array() );
		}
		return $out;
	}

	/** La lista blanca de V.10.2, si `Builder::common()` de E no está. */
	private static function whitelist( array $from, bool $main ): array {
		$out = array();
		foreach ( $from as $key => $value ) {
			$key = (string) $key;
			if ( 1 === preg_match( self::COMMON, $key ) || ( $main && in_array( $key, array( '_element_id', '_css_classes' ), true ) ) ) {
				$out[ $key ] = $value;
			}
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			if ( isset( $from[ $bag ] ) && is_array( $from[ $bag ] ) ) {
				$kept = array();
				foreach ( $from[ $bag ] as $key => $value ) {
					if ( 1 === preg_match( self::COMMON, (string) $key ) ) {
						$kept[ $key ] = $value;
					}
				}
				if ( $kept ) {
					$out[ $bag ] = $kept;
				}
			}
		}
		return $out;
	}

	/** Rasgos propios del elemento origen (`CARRY`), con sus entradas de `__globals__`/`__dynamic__`. */
	private static function carry( array $from ): array {
		$match = static function ( string $key ): bool {
			return 1 === preg_match( self::CARRY, $key );
		};
		$out = array();
		foreach ( $from as $key => $value ) {
			if ( '__globals__' !== $key && '__dynamic__' !== $key && $match( (string) $key ) ) {
				$out[ $key ] = $value;
			}
		}
		foreach ( array( '__globals__', '__dynamic__' ) as $bag ) {
			foreach ( (array) ( $from[ $bag ] ?? array() ) as $key => $value ) {
				if ( $match( (string) $key ) ) {
					$out[ $bag ][ $key ] = $value;
				}
			}
		}
		return $out;
	}

	/** Une ajustes (el último gana) con `__globals__` y `__dynamic__` unidos clave a clave. */
	private static function merge( array ...$parts ): array {
		$out = array();
		foreach ( $parts as $part ) {
			foreach ( $part as $key => $value ) {
				if ( ( '__globals__' === $key || '__dynamic__' === $key ) && is_array( $value ) ) {
					$out[ $key ] = array_merge( (array) ( $out[ $key ] ?? array() ), $value );
				} else {
					$out[ $key ] = $value;
				}
			}
		}
		return $out;
	}

	/** Redes sociales de Prime Slider → `social-icons` hermano, sólo con filas que no sean del proveedor. */
	private static function social( array $s, array $element, array &$codes ): ?array {
		if ( ! self::truthy( $s['show_social_icon'] ?? 'yes' ) ) {
			return null;
		}
		$rows = array();
		foreach ( (array) ( $s['social_link_list'] ?? array() ) as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$link = is_array( $row['social_icon_link'] ?? null ) ? $row['social_icon_link'] : array( 'url' => $row['social_icon_link'] ?? '' );
			$url  = self::safe_url( $link['url'] ?? '' );
			if ( '' === $url || self::vendor_profile( $url ) ) {
				continue;
			}
			$icon = $row['social_icon'] ?? null;
			if ( ! is_array( $icon ) || ! is_string( $icon['value'] ?? null ) || '' === $icon['value'] ) {
				$icon = self::network_icon( $url );
			}
			$rows[] = array(
				'_id'         => substr( md5( self::element_id( $element ) . ':social:' . $index ), 0, 7 ),
				'social_icon' => array( 'value' => (string) $icon['value'], 'library' => (string) ( $icon['library'] ?? '' ) ),
				'link'        => array( 'url' => $url, 'is_external' => 'on', 'nofollow' => '', 'custom_attributes' => '' ),
			);
		}
		if ( ! $rows ) {
			return null;
		}
		if ( false === self::ready( 'social-icons' ) ) {
			$codes[] = array( 'ps.dyn.loss.social', array( 'count' => count( $rows ) ) );
			return null;
		}
		$codes[] = array( 'ps.dyn.social', array( 'count' => count( $rows ) ) );
		return self::widget( substr( md5( self::element_id( $element ) . ':social' ), 0, 7 ), 'social-icons', array( 'social_icon_list' => $rows ) );
	}

	/** «Scroll Down» → `digitalisimo-scroll-button` hermano, sólo si estaba visible y su destino no es el de ejemplo. */
	private static function scroll( array $s, array $element, array &$codes ): ?array {
		if ( ! self::truthy( $s['show_scroll_button'] ?? 'yes' ) ) {
			return null;
		}
		$section = preg_replace( '/[^A-Za-z0-9_-]/', '', ltrim( trim( (string) ( $s['section_id'] ?? '' ) ), '#' ) );
		if ( '' === $section || 'my-header' === $section ) {
			return null;
		}
		if ( false === self::ready( 'digitalisimo-scroll-button' ) ) {
			$codes[] = array( 'ps.dyn.loss.scroll', array() );
			return null;
		}
		$set = array(
			'scroll_button_text' => self::plain( self::text( $s['scroll_button_text'] ?? '' ) ),
			'section_id'         => $section,
		);
		foreach ( array( 'duration' => array( 0, 5000 ), 'offset' => array( -200, 200 ) ) as $key => $range ) {
			if ( is_numeric( $s[ $key ]['size'] ?? null ) ) {
				$set[ $key ] = array( 'unit' => 'px', 'size' => max( $range[0], min( $range[1], (int) $s[ $key ]['size'] ) ), 'sizes' => array() );
			}
		}
		$codes[] = array( 'ps.dyn.scroll', array() );
		return self::widget( substr( md5( self::element_id( $element ) . ':scroll' ), 0, 7 ), 'digitalisimo-scroll-button', $set );
	}

	/**
	 * Configuración de la *skin* del origen (`_skin`): `skins.<skin>` del mapa sustituye `parts.*`, `nav.*` y añade
	 * `loss.*` (Lite: `general`, `isolate` y `blog` tienen skins que ocultan controles por condición).
	 */
	private static function skin( array $cfg, array $s ): array {
		$skin = $s['_skin'] ?? '';
		if ( ! is_string( $skin ) || '' === $skin || ! isset( $cfg['skins'][ $skin ] ) || ! is_array( $cfg['skins'][ $skin ] ) ) {
			return $cfg;
		}
		foreach ( array( 'parts', 'nav', 'loss' ) as $part ) {
			if ( isset( $cfg['skins'][ $skin ][ $part ] ) && is_array( $cfg['skins'][ $skin ][ $part ] ) ) {
				$cfg[ $part ] = array_replace( (array) ( $cfg[ $part ] ?? array() ), $cfg['skins'][ $skin ][ $part ] );
			}
		}
		return $cfg;
	}

	/**
	 * Pérdidas declaradas del mapa (`loss`: código → `true` siempre, la clave de un interruptor encendido o `!clave`
	 * apagado) y el recuento de ajustes de estilo sin equivalente.
	 */
	private static function losses( array $s, array $cfg, array $element, array &$codes ): void {
		foreach ( (array) ( $cfg['loss'] ?? array() ) as $code => $rule ) {
			$hit = true === $rule;
			if ( is_string( $rule ) && '' !== $rule ) {
				$hit = '!' === $rule[0] ? ! self::truthy( $s[ substr( $rule, 1 ) ] ?? '' ) : self::truthy( $s[ $rule ] ?? '' );
			}
			if ( $hit ) {
				$codes[] = array( (string) $code, array() );
			}
		}
		$original = is_array( $element['settings'] ?? null ) ? $element['settings'] : array();
		$carried  = self::carry( $original );
		$used     = array_flip( (array) ( $cfg['consumed'] ?? array() ) );
		foreach ( (array) ( $cfg['colors'] ?? array() ) as $keys ) {
			foreach ( (array) $keys as $key ) {
				$used[ $key ] = true;
			}
		}
		$count = 0;
		foreach ( $original as $key => $value ) {
			$key = (string) $key;
			if ( '' === $key || '_' === $key[0] || isset( $used[ $key ] ) || 1 === preg_match( self::COMMON, $key ) || 1 !== preg_match( self::STYLE, $key ) ) {
				continue;
			}
			if ( 1 === preg_match( self::CONTENT, $key ) || isset( $carried[ $key ] ) ) {
				continue;
			}
			if ( '' === $value || null === $value || array() === $value ) {
				continue;
			}
			++$count;
		}
		if ( $count ) {
			$codes[] = array( 'ps.dyn.loss.styles', array( 'count' => $count ) );
		}
	}

	/** Informa los códigos del documento (el informe descarta todo en el editor). */
	private static function report( int $post_id, array $codes, string $widget, array $element ): void {
		$report = self::REPORT;
		if ( self::pure() || ! class_exists( $report, false ) || ! method_exists( $report, 'add' ) ) {
			return;
		}
		$element_id = self::element_id( $element );
		foreach ( $codes as $code ) {
			if ( ! is_array( $code ) || ! is_string( $code[0] ?? null ) ) {
				continue;
			}
			$detail = (array) ( $code[1] ?? array() );
			$report::add( $post_id, $code[0], $detail + array( 'widget' => $widget, 'element' => $element_id ) );
		}
	}

	/* ---------------------------------------------------------------- Auxiliares */

	/** Ajustes con los valores por defecto del mapa donde falten (sólo las claves que lee esta clase). */
	private static function with_defaults( array $settings, array $defaults ): array {
		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				$settings[ $key ] = self::text_default( $value );
			} elseif ( is_array( $value ) && is_array( $settings[ $key ] ) && ! isset( $value['__t'] ) && $value && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
				$settings[ $key ] = array_merge( $value, $settings[ $key ] );
			}
		}
		return $settings;
	}

	/** `array( '__t' => 'Read More', 'd' => '<dominio>' )` → el texto traducido con el dominio del plugin (o literal). */
	private static function text_default( $value ) {
		if ( is_array( $value ) && isset( $value['__t'] ) ) {
			$text   = (string) $value['__t'];
			$domain = (string) ( $value['d'] ?? '' );
			return function_exists( 'translate' ) && '' !== $domain ? (string) translate( $text, $domain ) : $text;
		}
		return $value;
	}

	private static function text( $value ): string {
		$value = self::text_default( $value );
		return is_scalar( $value ) ? (string) $value : '';
	}

	/** Texto plano corto. */
	private static function plain( $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value ) : strip_tags( $value );
		return trim( (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value ) );
	}

	private static function esc( string $text ): string {
		return function_exists( 'esc_html' ) ? esc_html( $text ) : htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}

	/** URL de un enlace del origen que puede quedar: http(s), relativa o ancla; nunca `#` solo ni `javascript:`. */
	private static function safe_url( $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( '' === $value || '#' === $value || 1 !== preg_match( '#^(https?://|/(?!/)|\#[A-Za-z]|mailto:|tel:)[^\s"<>]*$#i', $value ) ) {
			return '';
		}
		return $value;
	}

	/** ¿Perfil del proveedor? (`Sanitizer::vendor_profile()` de E, o la misma regla). */
	private static function vendor_profile( string $url ): bool {
		$sanitizer = self::SANITIZER;
		if ( class_exists( $sanitizer ) && method_exists( $sanitizer, 'vendor_profile' ) ) {
			return (bool) $sanitizer::vendor_profile( $url );
		}
		return 1 === preg_match( '#^(?:https?:)?//(?:[a-z0-9-]+\.)*(?:bdthemes\.com|bdthemes\.co|elementpack\.pro|primeslider\.pro)(?:[/?\#:]|$)#i', $url )
			|| 1 === preg_match( '#^(?:https?:)?//(?:[a-z0-9-]+\.)*[a-z]+\.com/(?:company/|in/|@)?bdthemes(?:[/?\#._-]|$)#i', $url );
	}

	/** Icono de una red a partir del dominio del enlace (o un enlace genérico). */
	private static function network_icon( string $url ): array {
		$host = strtolower( (string) ( function_exists( 'wp_parse_url' ) ? wp_parse_url( $url, PHP_URL_HOST ) : parse_url( $url, PHP_URL_HOST ) ) );
		foreach ( self::NETWORK_ICONS as $network => $icon ) {
			if ( 1 === preg_match( '/(^|\.)' . preg_quote( $network, '/' ) . '\.com$/', $host ) ) {
				return array( 'value' => $icon, 'library' => 'fa-brands' );
			}
		}
		return array( 'value' => 'fas fa-link', 'library' => 'fa-solid' );
	}

	/** Imagen `{id, url}` de un valor MEDIA (vacía si no hay URL). */
	private static function media( $value ): array {
		if ( ! is_array( $value ) || ! is_string( $value['url'] ?? null ) || '' === trim( $value['url'] ) ) {
			return array( 'url' => '', 'id' => '' );
		}
		return array( 'url' => (string) $value['url'], 'id' => is_numeric( $value['id'] ?? null ) ? (int) $value['id'] : '' );
	}

	private static function image_size( $value ): string {
		$value = is_scalar( $value ) ? (string) $value : 'full';
		return 1 === preg_match( '/^[a-z0-9_-]{1,40}$/', $value ) ? $value : 'full';
	}

	/** Interruptor encendido: «yes» o cualquier valor no vacío que no sea «no» (como el `if ( $settings[…] )` del origen). */
	private static function truthy( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		return is_scalar( $value ) && '' !== (string) $value && 'no' !== (string) $value && '0' !== (string) $value;
	}

	private static function clamp( $value ): int {
		return max( 1, min( 8, (int) $value ) );
	}

	/** ID del elemento origen (se conserva en el principal) o uno nuevo si no es válido. */
	private static function element_id( array $element ): string {
		$id = $element['id'] ?? '';
		return is_string( $id ) && 1 === preg_match( '/^[a-z0-9]{1,16}$/i', $id ) ? $id : substr( md5( (string) json_encode( $element ) ), 0, 7 );
	}

	private static function widget( string $id, string $type, array $settings ): array {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'settings'   => $settings,
			'elements'   => array(),
			'widgetType' => $type,
		);
	}

	private static function pure(): bool {
		$context = self::CONTEXT;
		return class_exists( $context, false ) && $context::pure();
	}

	/** Instancia de Elementor, si está cargado. */
	private static function elementor() {
		return class_exists( '\\Elementor\\Plugin', false ) && isset( \Elementor\Plugin::$instance ) ? \Elementor\Plugin::$instance : null;
	}
}
