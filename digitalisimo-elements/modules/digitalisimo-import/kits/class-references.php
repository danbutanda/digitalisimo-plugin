<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Registro declarativo de referencias que el importador traduce (§5.5).
 *
 * No es un motor: dice dónde hay un ID, un menú o una URL del origen, y `Remapper` (frente C) los traduce una
 * sola vez por documento con los mapas de `Import_Context`. Se amplía con el filtro
 * `digitalisimo_elements_kit_references( array $refs )`, sin tocar el código de K.
 *
 * Cada entrada es un array con claves o posicional, en este orden:
 * - `scope`: `element`, `document`, `condition`, `post_meta` o `term_meta`;
 * - `type`: `widgetType` o `elType` (`*` = cualquiera); en `condition`, el nombre de la condición; en `*_meta`,
 *   la clave de la meta (sin `*`);
 * - `path`: claves separadas por punto, `*` recorre un array; vacío sólo en `*_meta` (el valor entero);
 * - `kind`: uno de `KINDS`, `note:<código>` o `map:<nombre>`;
 * - `gate` (opcional): decide quién ve algo o dónde se aplica; por defecto, sí en `scope = condition` y en
 *   `kind = conditions`;
 * - `never` (opcional): valor que nunca coincide, para un control en el que vacío significa «cualquiera» (regla de
 *   cierre). Lo fija quien declara la referencia y debe sobrevivir a `absint()` sin volverse un ID real.
 *
 * `kind = conditions` es un valor con condiciones de visualización en JSON (`e_display_conditions`): cada condición
 * se resuelve con las referencias de `scope = condition`.
 *
 * Además, `for_type()` deriva en tiempo de ejecución una referencia `url` por cada control URL del elemento (`html`
 * por cada texto enriquecido y `media` por cada control de medios o galería),
 * también dentro de repetidores (CC-6, CV-20); `kind_for()` la pasa a `media-url` si el valor está bajo
 * `uploads` del origen.
 */
final class References {
	const SCOPES      = array( 'element', 'document', 'condition', 'post_meta', 'term_meta' );
	const META_SCOPES = array( 'post_meta', 'term_meta' );

	/** `user`: los usuarios no viajan, así que nunca tienen destino. `element`: ID de elemento del mismo documento. */
	const KINDS = array( 'post', 'template', 'term', 'menu', 'menu_slug', 'user', 'attachment', 'url', 'media-url', 'css', 'html', 'media', 'file', 'element', 'conditions' );

	/** Prefijos de `kind` con sufijo: `note:<código>` y `map:<nombre>`. */
	const KIND_PREFIXES = array( 'note', 'map' );

	const FIELDS = array( 'scope', 'type', 'path', 'kind', 'gate', 'never' );

	const TYPE_PATTERN   = '/^(?:\*|[A-Za-z0-9_.:-]{1,191})$/';
	const PATH_PATTERN   = '/^(?:\*|[A-Za-z0-9_-]+)(?:\.(?:\*|[A-Za-z0-9_-]+))*$/';
	const SUFFIX_PATTERN = '/^[a-z0-9_-]{1,64}$/';

	/** Profundidad de repetidores anidados que recorre `derive()`. */
	const MAX_REPEATER_DEPTH = 3;

	/** @var array[]|null Ámbito => tipo => referencias. */
	private static $index = null;

	/** @var array[] Tipo => referencias derivadas de sus controles. */
	private static $derived = array();

	/**
	 * Referencias integradas y del filtro, válidas e indexadas por ámbito y tipo.
	 *
	 * No incluye las derivadas por tipo de control: se piden con `matching()` o `for_type()`.
	 *
	 * @return array[] `[ scope => [ type => [ ref, … ] ] ]`.
	 */
	public static function all(): array {
		if ( null !== self::$index ) {
			return self::$index;
		}
		$builtin = self::builtin();
		$entries = function_exists( 'apply_filters' ) ? apply_filters( Module::FILTER_REFERENCES, $builtin ) : $builtin;
		if ( ! is_array( $entries ) ) {
			Module::doing_it_wrong( __METHOD__, 'El filtro de referencias debe devolver un array.' );
			$entries = $builtin;
		}
		$index = array();
		foreach ( $entries as $entry ) {
			$ref = self::normalize( $entry );
			if ( null !== $ref ) {
				$index[ $ref['scope'] ][ $ref['type'] ][] = $ref;
			}
		}
		self::$index = $index;
		return self::$index;
	}

	/**
	 * Valor de cierre de los controles en los que vacío significa «cualquiera» (regla de cierre de §5.5).
	 *
	 * Un entero (sobrevive a `absint()` e `intval()` sin volverse 0, que vaciaría la regla, ni 1, el primer
	 * usuario), por debajo de 2^31 (cabe en un PHP de 32 bits y en el editor sin perder precisión) y muy por encima
	 * de cualquier ID real. El remapeo comprueba al usarlo que no existe y, si existiera, lo informa como error.
	 */
	const NEVER_ID = 2147483647;

	/**
	 * Referencias integradas de K (§9.2), comprobadas en cada archivo.
	 *
	 * Lo que ya traduce el núcleo no se declara: el widget Plantilla, el `template_id` de Loop Grid, Nav Menu, las
	 * etiquetas de popup, los controles `Query` de `On_Import_Trait`, la Query de los ajustes de documento y
	 * `__dynamic__`. Los controles URL se derivan por su tipo (`for_type()`), sin lista de nombres.
	 *
	 * Una ruta que termina en `*` es una lista: cada entrada es un ID o un `{ id, label }` del control de consulta.
	 * `never` sólo se declara donde vacío amplía quién ve algo (comprobado en el código de cada regla); donde una
	 * lista vacía ya no coincide con nada, vaciarla es lo más cerrado.
	 *
	 * @return array[]
	 */
	public static function builtin(): array {
		$never = self::NEVER_ID;
		$refs  = array(
			// Acordeón: plantilla guardada y de AnyWhere por elemento (E:modules/digitalisimo-widgets/class-accordion.php:29-30,108).
			array( 'element', 'digitalisimo-accordion', 'tabs.*.template_id', 'template' ),
			array( 'element', 'digitalisimo-accordion', 'tabs.*.anywhere_id', 'post' ),
			// Pestañas y su alias Alternador de contenido (class-fancy-tabs.php:66,118; class-content-switcher.php:8-27).
			array( 'element', 'digitalisimo-fancy-tabs', 'tabs.*.template_id', 'template' ),
			array( 'element', 'digitalisimo-content-switcher', 'tabs.*.template_id', 'template' ),
			// Panel lateral: plantilla y barra lateral de widgets, que no viaja (class-offcanvas.php:77-78,142).
			array( 'element', 'digitalisimo-offcanvas', 'template_id', 'template' ),
			array( 'element', 'digitalisimo-offcanvas', 'sidebar', 'note:sidebar' ),
			// Rasgo «plantilla en lugar del texto» del Deslizador (class-slides-templates.php:14,39-74).
			array( 'element', 'slides', 'slides.*.digitalisimo_template_id', 'template' ),
			// Navegación con iconos y su alias: ID del menú (class-icon-nav.php:51,110-125; class-icon-mobile-menu.php).
			array( 'element', 'digitalisimo-icon-nav', 'navbar', 'menu' ),
			array( 'element', 'digitalisimo-icon-mobile-menu', 'navbar', 'menu' ),
			// Menú vertical: guarda el slug del menú (class-vertical-menu.php:22-30,36,232).
			array( 'element', 'digitalisimo-vertical-menu', 'menu', 'menu_slug' ),
			// Caja de autor: usuario del sitio; vacío vuelve a los datos escritos a mano (class-author-box-extras.php:51,73).
			array( 'element', 'author-box', 'digitalisimo_author_user', 'user' ),
			// Reseñas: el Place ID viaja, la clave de Places no (class-google-reviews.php:19,32).
			array( 'element', 'digitalisimo-google-reviews', 'google_place_id', 'note:google_places' ),
			// Reglas de visibilidad (E:modules/digitalisimo-conditions/class-visibility.php:130-142; class-visibility-rules.php).
			// Entrada: una lista vacía hace `is_single( array() )`, cierto en cualquier entrada (rule_post).
			array( 'element', '*', 'digitalisimo_visibility_rules.*.rule_post_value', 'post', true, $never ),
			// Productos, categorías y etiquetas del carrito: una lista vacía ya no coincide (cart_ids).
			array( 'element', '*', 'digitalisimo_visibility_rules.*.rule_products_in_cart_value', 'post', true ),
			array( 'element', '*', 'digitalisimo_visibility_rules.*.rule_categories_in_cart_value', 'term', true ),
			array( 'element', '*', 'digitalisimo_visibility_rules.*.rule_tags_in_cart_value', 'term', true ),
			// Página por ID: 0 nunca coincide (rule_static_page).
			array( 'element', '*', 'digitalisimo_visibility_rules.*.rule_custom_page_id', 'post', true ),
			// Usuario: «Vacío: cualquier usuario con sesión» (rule_user); los usuarios no viajan.
			array( 'element', '*', 'digitalisimo_visibility_rules.*.rule_user_value', 'user', true, $never ),
			// Categoría de la página y del producto: vacío no cumple (rule_current_category_page, rule_single_product_category).
			array( 'element', '*', 'digitalisimo_visibility_rules.*.rule_current_category_page_value', 'term', true ),
			array( 'element', '*', 'digitalisimo_visibility_rules.*.rule_single_product_category_value', 'term', true ),
			// Condiciones de visualización: JSON por grupos (E:modules/display-conditions/module.php:204-214).
			array( 'element', '*', 'e_display_conditions', 'conditions' ),
			// Condiciones propias (E:modules/digitalisimo-conditions/class-conditions.php): `in_set()` no cumple con una lista vacía.
			array( 'condition', 'digitalisimo_user', 'users.*', 'user' ),
			array( 'condition', 'digitalisimo_specific_content', 'items.*', 'post' ),
			array( 'condition', 'digitalisimo_cart_products', 'products.*', 'post' ),
			array( 'condition', 'digitalisimo_customer_bought', 'products.*', 'post' ),
			array( 'condition', 'digitalisimo_cart_categories', 'categories.*', 'term' ),
			array( 'condition', 'digitalisimo_cart_tags', 'tags.*', 'term' ),
			array( 'condition', 'digitalisimo_product_category', 'categories.*', 'term' ),
			array( 'condition', 'digitalisimo_product_category_archive', 'categories.*', 'term' ),
			// Condiciones nativas (E:modules/display-conditions/conditions/): con la lista vacía cumplen siempre.
			array( 'condition', 'page_author', 'authors.*', 'user', true, $never ),
			array( 'condition', 'archive_of_authors', 'authors.*', 'user', true, $never ),
			array( 'condition', 'page_parent', 'pages.*', 'post', true, $never ),
			array( 'condition', 'in_categories', 'categories.*', 'term', true, $never ),
			array( 'condition', 'in_tags', 'tags.*', 'term', true, $never ),
			array( 'condition', 'archive_of_categories', 'categories.*', 'term', true, $never ),
			array( 'condition', 'archive_of_tags', 'tags.*', 'term', true, $never ),
			// Separadores propios `digi-*` (E:modules/digitalisimo-widgets/class-divider-shapes.php:15-16,48).
			array( 'element', 'section', 'shape_divider_top', 'map:shapes' ),
			array( 'element', 'section', 'shape_divider_bottom', 'map:shapes' ),
			array( 'element', 'container', 'shape_divider_top', 'map:shapes' ),
			array( 'element', 'container', 'shape_divider_bottom', 'map:shapes' ),
			// CSS personalizado del elemento y del documento: máscaras y medios en `url()` (M:legacy/class-masks.php:281,330).
			array( 'element', '*', 'custom_css', 'css' ),
			array( 'document', '*', 'custom_css', 'css' ),
			// Loop Grid: el núcleo sólo traduce `template_id`; las plantillas alternas no (E:modules/loop-builder/widgets/loop-grid.php:155-170,596-602).
			array( 'element', 'loop-grid', 'alternate_templates.*.template_id', 'template' ),
			// Loop Carousel: `Template_Query` no es `Query` y no tiene método propio (E:modules/loop-builder/widgets/base.php:119-123).
			array( 'element', 'loop-carousel', 'template_id', 'template' ),
			// Filtro de taxonomías: ID del Loop Grid del mismo documento (E:modules/loop-filter/widgets/taxonomy-filter.php:73-80).
			array( 'element', 'taxonomy-filter', 'selected_element', 'element' ),
			// Widgets de WordPress: `wp` guarda la instancia del widget (N:includes/widgets/wordpress.php:53,179,224).
			array( 'element', 'wp-widget-nav_menu', 'wp.nav_menu', 'menu' ),
			array( 'element', 'wp-widget-media_image', 'wp.attachment_id', 'attachment' ),
			array( 'element', 'wp-widget-media_video', 'wp.attachment_id', 'attachment' ),
			array( 'element', 'wp-widget-media_audio', 'wp.attachment_id', 'attachment' ),
			array( 'element', 'wp-widget-media_gallery', 'wp.ids', 'attachment' ),
			// La URL que guardan los widgets de medios de WordPress junto al ID (no es un control URL del constructor).
			array( 'element', 'wp-widget-media_image', 'wp.url', 'url' ),
			array( 'element', 'wp-widget-media_video', 'wp.url', 'url' ),
			array( 'element', 'wp-widget-media_audio', 'wp.url', 'url' ),
			// Formulario: destinatarios y webhooks no viajan (E:modules/forms/actions/email.php:317-330; webhook.php:33,69).
			array( 'element', 'form', 'form_fields', 'note:form' ),
			array( 'element', 'form', 'submit_actions', 'note:form' ),
			array( 'element', 'form', 'webhooks', 'note:form' ),
			// Origen ACF: viaja la clave del campo (E:modules/digitalisimo-widgets/class-acf-source.php:15-21,36).
			array( 'element', '*', 'digitalisimo_acf_field', 'note:acf' ),
			// Imagen de un término (categoría de producto): la parte `term-meta` la traduce y `media` la incrusta (B18).
			array( 'term_meta', 'thumbnail_id', '', 'attachment' ),
		);
		return $refs;
	}

	/**
	 * Referencias que se aplican a un ámbito y un tipo: las del tipo, las de `*` y, en `element`, las derivadas
	 * de sus controles URL. Si dos cubren la misma ruta, gana la del tipo, luego la de `*` y por último la derivada.
	 */
	public static function matching( string $scope, string $type ): array {
		$index = self::all();
		$refs  = array();
		$seen  = array();
		$types = '*' === $type ? array( '*' ) : array( $type, '*' );
		foreach ( $types as $candidate ) {
			foreach ( isset( $index[ $scope ][ $candidate ] ) ? $index[ $scope ][ $candidate ] : array() as $ref ) {
				if ( ! isset( $seen[ $ref['path'] ] ) ) {
					$seen[ $ref['path'] ] = true;
					$refs[]               = $ref;
				}
			}
		}
		if ( 'element' === $scope && '*' !== $type ) {
			foreach ( self::for_type( $type ) as $ref ) {
				if ( ! isset( $seen[ $ref['path'] ] ) ) {
					$seen[ $ref['path'] ] = true;
					$refs[]               = $ref;
				}
			}
		}
		return $refs;
	}

	/**
	 * Referencias `url` derivadas de los controles URL de un elemento (una vez por tipo y petición).
	 *
	 * @param string     $type     `widgetType` o `elType`.
	 * @param array|null $controls Controles del elemento; por defecto, los del elemento registrado. Con controles
	 *                             explícitos no se usa ni se llena la memoria de la petición.
	 */
	public static function for_type( string $type, ?array $controls = null ): array {
		if ( null !== $controls ) {
			return self::derive( $type, $controls );
		}
		if ( ! isset( self::$derived[ $type ] ) ) {
			$element                = self::element_of( $type );
			$controls               = null === $element ? array() : $element->get_controls();
			self::$derived[ $type ] = self::derive( $type, is_array( $controls ) ? $controls : array(), self::core_query_import( $element ) );
		}
		return self::$derived[ $type ];
	}

	/** Qué traduce el núcleo de los controles de consulta de un elemento (ver `derive()`). */
	const CORE_NONE     = 'none';
	const CORE_BY_NAME  = 'by-name';
	const CORE_OVERRIDE = 'override';

	/** Objeto de un control de consulta (`autocomplete.object`) → `kind`. */
	const QUERY_KINDS = array(
		'post'             => 'post',
		'attachment'       => 'attachment',
		'library_template' => 'template',
		'tax'              => 'term',
		'cpt_tax'          => 'term',
		'author'           => 'user',
		'user'             => 'user',
	);

	/**
	 * Cómo traduce el núcleo los controles de consulta de un elemento al importar: por su nombre (el rasgo de
	 * importación de PRO: sólo los que contienen `post_ids` o `term_ids`, o se llaman `post_id`, `template_id` o
	 * `popup`), con un método propio (Loop Grid, Template y Nav Menu: su plantilla o su menú) o nada.
	 *
	 * @param object|null $element Elemento registrado.
	 */
	public static function core_query_import( $element ): string {
		if ( ! is_object( $element ) || ! method_exists( $element, 'on_import_update_dynamic_content' ) ) {
			return self::CORE_NONE;
		}
		try {
			$file = (string) ( new \ReflectionMethod( $element, 'on_import_update_dynamic_content' ) )->getFileName();
			if ( method_exists( $element, 'on_import_replace_dynamic_content' ) ) {
				$replace = new \ReflectionMethod( $element, 'on_import_replace_dynamic_content' );
				if ( false === strpos( str_replace( '\\', '/', (string) $replace->getFileName() ), '/includes/base/' ) ) {
					return self::CORE_OVERRIDE;
				}
			}
		} catch ( \ReflectionException $error ) {
			return self::CORE_NONE;
		}
		$file = str_replace( '\\', '/', $file );
		if ( '/base/on-import-trait.php' === substr( $file, -strlen( '/base/on-import-trait.php' ) ) ) {
			return self::CORE_BY_NAME;
		}
		return false !== strpos( $file, '/includes/base/' ) ? self::CORE_NONE : self::CORE_OVERRIDE;
	}

	/**
	 * Función pura: referencias de una lista de controles (`get_controls()`), con repetidores anidados.
	 *
	 * - Un control URL guarda `{ url, is_external, nofollow, … }`: `url` en `<control>.url`.
	 * - Un texto enriquecido: `html`. Un control de medios o una galería: `media`.
	 * - Un control de consulta (`query`, y `template_query` para plantillas): su `kind` sale de
	 *   `autocomplete.object` (`QUERY_KINDS`), salvo lo que ya traduce el núcleo (`$core`): con `by-name`, los de tipo
	 *   `query` cuyo nombre contiene `post_ids` o `term_ids` o es `post_id`, `template_id` o `popup`; con `override`,
	 *   `template_id` y `menu`. Así nada se traduce dos veces.
	 *
	 * @param string   $type     `widgetType` o `elType`.
	 * @param array    $controls Controles del elemento.
	 * @param string   $core     `CORE_NONE`, `CORE_BY_NAME` o `CORE_OVERRIDE`.
	 */
	public static function derive( string $type, array $controls, string $core = self::CORE_NONE ): array {
		$paths = array();
		self::collect_urls( $controls, '', $paths, 0, $core );
		$refs = array();
		foreach ( $paths as $path => $kind ) {
			$refs[] = array(
				'scope'   => 'element',
				'type'    => $type,
				'path'    => (string) $path,
				'kind'    => true === $kind ? 'url' : (string) $kind,
				'gate'    => false,
				'never'   => null,
				'derived' => true,
			);
		}
		return $refs;
	}

	/**
	 * Comprueba una entrada y la devuelve con sus claves completas, o null (con `_doing_it_wrong()`).
	 *
	 * @param mixed $ref Entrada con claves o posicional.
	 *
	 * @return array|null `{ scope, type, path, kind, gate, never }`.
	 */
	public static function normalize( $ref ): ?array {
		if ( ! is_array( $ref ) ) {
			return self::invalid( 'no es un array', $ref );
		}
		if ( array_key_exists( 0, $ref ) ) {
			$named = array();
			foreach ( self::FIELDS as $position => $field ) {
				if ( array_key_exists( $position, $ref ) ) {
					$named[ $field ] = $ref[ $position ];
				}
			}
			$ref = $named;
		}
		$scope = isset( $ref['scope'] ) ? $ref['scope'] : null;
		$type  = isset( $ref['type'] ) ? $ref['type'] : null;
		$path  = isset( $ref['path'] ) ? $ref['path'] : '';
		$kind  = isset( $ref['kind'] ) ? $ref['kind'] : null;
		if ( ! is_string( $scope ) || ! in_array( $scope, self::SCOPES, true ) ) {
			return self::invalid( 'ámbito desconocido', $ref );
		}
		$meta = in_array( $scope, self::META_SCOPES, true );
		if ( ! is_string( $type ) || 1 !== preg_match( self::TYPE_PATTERN, $type ) || ( $meta && '*' === $type ) ) {
			return self::invalid( 'tipo inválido', $ref );
		}
		if ( ! is_string( $path ) || ( '' === $path ? ! $meta : 1 !== preg_match( self::PATH_PATTERN, $path ) ) ) {
			return self::invalid( 'ruta inválida', $ref );
		}
		if ( ! is_string( $kind ) || ! self::is_valid_kind( $kind ) ) {
			return self::invalid( 'tipo de referencia desconocido', $ref );
		}
		$never = isset( $ref['never'] ) ? $ref['never'] : null;
		if ( null !== $never && ! is_int( $never ) && ( ! is_string( $never ) || '' === $never ) ) {
			return self::invalid( 'valor de cierre inválido', $ref );
		}
		return array(
			'scope' => $scope,
			'type'  => $type,
			'path'  => $path,
			'kind'  => $kind,
			'gate'  => isset( $ref['gate'] ) ? (bool) $ref['gate'] : ( 'condition' === $scope || 'conditions' === $kind ),
			'never' => $never,
		);
	}

	/** ¿Es un `kind` conocido (`KINDS`, `note:<código>` o `map:<nombre>`)? */
	public static function is_valid_kind( string $kind ): bool {
		if ( in_array( $kind, self::KINDS, true ) ) {
			return true;
		}
		$colon = strpos( $kind, ':' );
		return false !== $colon
			&& in_array( substr( $kind, 0, $colon ), self::KIND_PREFIXES, true )
			&& 1 === preg_match( self::SUFFIX_PATTERN, substr( $kind, $colon + 1 ) );
	}

	/** Nombre del mapa de un `kind` `map:<nombre>`, o ''. */
	public static function map_name( string $kind ): string {
		return 0 === strpos( $kind, 'map:' ) && self::is_valid_kind( $kind ) ? substr( $kind, 4 ) : '';
	}

	/** Código de un `kind` `note:<código>`, o ''. */
	public static function note_code( string $kind ): string {
		return 0 === strpos( $kind, 'note:' ) && self::is_valid_kind( $kind ) ? substr( $kind, 5 ) : '';
	}

	/**
	 * `kind` efectivo para un valor: una referencia `url` cuyo valor está bajo `uploads` del origen es `media-url`.
	 *
	 * @param array  $ref     Referencia normalizada.
	 * @param mixed  $value   Valor encontrado en la ruta.
	 * @param string $uploads URL de `uploads` del origen (`Import_Context::origin( 'uploads' )`).
	 */
	public static function kind_for( array $ref, $value, string $uploads ): string {
		$kind = isset( $ref['kind'] ) ? (string) $ref['kind'] : '';
		if ( 'url' !== $kind || ! is_string( $value ) || '' === $uploads ) {
			return $kind;
		}
		return self::under( $value, $uploads ) ? 'media-url' : 'url';
	}

	/**
	 * ¿Está una URL absoluta bajo un prefijo? Sin distinguir `http`/`https` ni mayúsculas del host; la ruta sí las
	 * distingue. El prefijo solo (sin archivo) no cuenta.
	 */
	public static function under( string $url, string $prefix ): bool {
		$url    = self::comparable( $url );
		$prefix = self::comparable( $prefix );
		if ( '' === $url || '' === $prefix ) {
			return false;
		}
		return 0 === strpos( $url, rtrim( $prefix, '/' ) . '/' );
	}

	/** Segmentos de una ruta: `tabs.*.template_id` → `[ 'tabs', '*', 'template_id' ]`. */
	public static function split_path( string $path ): array {
		return '' === $path ? array() : explode( '.', $path );
	}

	/**
	 * Olvida el índice y las derivadas.
	 *
	 * @internal Sólo para pruebas.
	 */
	public static function reset(): void {
		self::$index   = null;
		self::$derived = array();
	}

	/** Widget o elemento registrado de un tipo, o null si el núcleo no está cargado o no existe. */
	private static function element_of( string $type ) {
		if ( '' === $type || '*' === $type || ! class_exists( '\Elementor\Plugin', false ) || empty( \Elementor\Plugin::$instance ) ) {
			return null;
		}
		$plugin  = \Elementor\Plugin::$instance;
		$element = null;
		if ( ! empty( $plugin->widgets_manager ) ) {
			$element = $plugin->widgets_manager->get_widget_types( $type );
		}
		if ( ! $element && ! empty( $plugin->elements_manager ) ) {
			$element = $plugin->elements_manager->get_element_types( $type );
		}
		return is_object( $element ) && method_exists( $element, 'get_controls' ) ? $element : null;
	}

	/** Rutas de los controles que se derivan por su tipo, dentro de repetidores con `.*.` (ver `derive()`). */
	private static function collect_urls( array $controls, string $prefix, array &$paths, int $depth, string $core = self::CORE_NONE ): void {
		foreach ( $controls as $key => $control ) {
			if ( ! is_array( $control ) ) {
				continue;
			}
			$name = isset( $control['name'] ) && is_string( $control['name'] ) ? $control['name'] : ( is_string( $key ) ? $key : '' );
			if ( 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $name ) ) {
				continue;
			}
			$type = isset( $control['type'] ) ? $control['type'] : '';
			if ( 'url' === $type ) {
				$paths[ $prefix . $name . '.url' ] = true;
			} elseif ( 'wysiwyg' === $type ) {
				// Texto enriquecido: sus enlaces e imágenes del origen (`html`).
				$paths[ $prefix . $name ] = 'html';
			} elseif ( 'media' === $type ) {
				// Control de medios `{ id, url }`: el núcleo no lo importa dentro de todos los repetidores (`media`).
				$paths[ $prefix . $name ] = 'media';
			} elseif ( 'gallery' === $type ) {
				$paths[ $prefix . $name . '.*' ] = 'media';
			} elseif ( 'query' === $type || 'template_query' === $type ) {
				$kind = self::query_kind( $control, $type, $name, $core );
				if ( '' !== $kind ) {
					$paths[ $prefix . $name ] = $kind;
				}
			} elseif ( 'repeater' === $type && $depth < self::MAX_REPEATER_DEPTH && ! empty( $control['fields'] ) && is_array( $control['fields'] ) ) {
				self::collect_urls( $control['fields'], $prefix . $name . '.*.', $paths, $depth + 1, $core );
			}
		}
	}

	/** `kind` de un control de consulta, o '' si no se traduce (objeto desconocido o lo traduce el núcleo). */
	private static function query_kind( array $control, string $type, string $name, string $core ): string {
		if ( self::CORE_OVERRIDE === $core && in_array( $name, array( 'template_id', 'menu' ), true ) ) {
			return '';
		}
		if ( self::CORE_BY_NAME === $core && 'query' === $type && ( false !== strpos( $name, 'post_ids' ) || false !== strpos( $name, 'term_ids' ) || in_array( $name, array( 'post_id', 'template_id', 'popup' ), true ) ) ) {
			return '';
		}
		$object = isset( $control['autocomplete']['object'] ) && is_string( $control['autocomplete']['object'] ) ? $control['autocomplete']['object'] : '';
		if ( '' === $object && 'template_query' === $type ) {
			$object = 'library_template';
		}
		return isset( self::QUERY_KINDS[ $object ] ) ? self::QUERY_KINDS[ $object ] : '';
	}

	/** `//host/ruta` con el host en minúsculas, o '' si no es una URL absoluta. */
	private static function comparable( string $url ): string {
		if ( 1 !== preg_match( '#^(?:https?:)?//([^/?\#]+)(.*)$#i', trim( $url ), $match ) ) {
			return '';
		}
		return '//' . strtolower( $match[1] ) . $match[2];
	}

	private static function invalid( string $reason, $ref ): ?array {
		Module::doing_it_wrong( 'Digitalisimo\Elements\Import\Kits\References::normalize', 'Referencia de kit inválida (' . $reason . '): ' . Module::describe( $ref ) );
		return null;
	}
}
