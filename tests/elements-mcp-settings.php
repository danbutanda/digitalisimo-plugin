<?php
/**
 * Los ajustes de un documento se combinan al escribirlos por el MCP (`modules/digitalisimo-mcp/class-settings-merge.php`,
 * `Settings_Merge`), con la herramienta real del núcleo (`elementor/update-page-settings`: `abstract-ability.php`,
 * `ability-definition.php` y `update-settings-ability.php` de `digitalisimo-elements/elementor/`, sin tocarlos):
 * - kit con colores, tipografías, `__globals__`, botones, formularios y puntos de corte: actualizar sólo `custom_css`
 *   deja intacto todo lo demás y el CSS del kit conserva `--e-global-color-*` y `--e-global-typography-*`;
 * - un solo global en `__globals__` conserva los otros; una página con diseño, fondo y CSS conserva el resto;
 * - `null` borra una clave (también un global, y la última clave borra la meta); la respuesta dice qué cambió;
 * - ninguna opción de WordPress ni de WooCommerce cambia al actualizar parcialmente el kit, aunque la petición traiga
 *   `post_status: publish` (con la que hoy corren los `on_save` de las pestañas), salvo la clave que se mande;
 * - en una publicada, el autoguardado con los cambios pendientes del MCP no se borra (el núcleo borra el más reciente al
 *   guardar ajustes) y recibe lo pedido (lo promueve `publish-document`); sin `unfiltered_html`, kses no reescribe las
 *   claves que no vinieron; `settings: {}` no guarda nada;
 * - otra herramienta del MCP que guarda `settings` parciales (`manage-component`, también por el proxy del editor)
 *   combina, y al publicar un componente no reescribe sus props con la copia vieja de sus ajustes; fuera del MCP nada
 *   cambia; la descripción y el esquema lo dicen.
 * - `post_name` (el slug) va a la entrada con `wp_update_post()` y `page_template` en null, nunca a la meta (N1–N3).
 *
 * Sin WordPress: dobles mínimos de sus funciones (ganchos con prioridad, metas, opciones, `pre_update_option_*` y
 * `pre_set_theme_mod_*` como en el núcleo de WordPress) y del guardado del núcleo de Elementor (`Document::save()`,
 * `Page\Manager::save_settings()`, `Kit::save()` con las pestañas de diseño, identidad del sitio y WooCommerce). Las
 * líneas de esos archivos que los dobles reproducen se comprueban en su código. El CSS real del kit se comprueba en
 * Playground (`tests/playground/elements-mcp-settings.sh`); aquí, las variables que el kit genera de su meta.
 * Falla con el código anterior: el kit pierde su diseño (no existe `class-settings-merge.php`).
 */
namespace Elementor\MCP\Composer\Admin {
	class McpSettingsController {
		public static function is_enabled(): bool {
			return true;
		}
	}
}

namespace Elementor\Modules\Mcp\Utils {
	class Editor_Sync_State {
		public static function set_mcp_mutation( $id ): void {
			$GLOBALS['ms']['mutations'][] = $id;
		}
	}
	class Mcp_V4_Gate {
		public static function is_available( string $id ) {
			return true;
		}
	}
}

namespace Elementor\Modules\Mcp\Abilities\Utils {
	class Document_Mutation_Links {
		public static function preview_schema_property(): array {
			return array( 'type' => 'string' );
		}
		public static function llm_instructions_schema_property(): array {
			return array( 'type' => 'string' );
		}
		public static function for_document( $document, ?string $message = null ): array {
			return array( 'preview_url' => 'http://ensayo.test/?p=' . $document->get_post()->ID );
		}
	}
}

namespace Elementor {
	class Plugin {
		public static $instance;
	}
	/** `Utils::kses_post_deep()`: en el doble, sólo lo que importa aquí (wp_kses convierte un `>` suelto en `&gt;`). */
	class Utils {
		public static function kses_post_deep( $value ) {
			return is_array( $value ) ? array_map( array( __CLASS__, 'kses_post_deep' ), $value ) : ( is_string( $value ) ? str_replace( '>', '&gt;', $value ) : $value );
		}
	}
}

namespace Elementor\Core\Base {
	/** `Document::save()` de 4.3.5 en lo que toca a los ajustes (filtro, permiso, `save_settings()`, `after_save`). */
	class Document {
		public $post;
		public $editable = true;
		public function __construct( int $id ) {
			$this->post = (object) array( 'ID' => $id );
		}
		public function get_post() {
			return $this->post;
		}
		public function get_main_id() {
			return $this->post->ID;
		}
		public function is_editable_by_current_user() {
			return $this->editable;
		}
		public function save( $data ) {
			$data = apply_filters( 'elementor/document/save/data', $data, $this );
			if ( ! $this->is_editable_by_current_user() ) {
				return false;
			}
			if ( ! current_user_can( 'unfiltered_html' ) ) {
				$data = \Elementor\Utils::kses_post_deep( $data );
			}
			$GLOBALS['ms']['saved'][] = $data;
			if ( ! empty( $data['settings'] ) ) {
				$this->save_settings( $data['settings'] );
			}
			do_action( 'elementor/document/after_save', $this, $data );
			return true;
		}
		/**
		 * `Page\Manager::ajax_before_save_settings()` (el título y, con la entrada publicada, el borrado de su autoguardado
		 * más reciente de cualquier usuario) y `save_settings()` → `save_settings_to_db()`.
		 */
		protected function save_settings( $settings ) {
			if ( ! empty( $settings['post_title'] ) ) {
				$GLOBALS['ms']['titles'][ $this->post->ID ] = $settings['post_title'];
			}
			if ( 'publish' === ( $GLOBALS['ms']['status'][ $this->post->ID ] ?? '' ) ) {
				$autosave = wp_get_post_autosave( $this->post->ID );
				if ( $autosave ) {
					wp_delete_post_revision( $autosave->ID );
				}
			}
			foreach ( array( 'id', 'post_title', 'post_status', 'template', 'post_excerpt', 'post_featured_image', 'menu_order', 'comment_status' ) as $special ) {
				unset( $settings[ $special ] );
			}
			if ( ! empty( $settings ) ) {
				update_metadata( 'post', $this->post->ID, '_elementor_page_settings', wp_slash( $settings ) );
			} else {
				delete_metadata( 'post', $this->post->ID, '_elementor_page_settings' );
			}
		}
	}
}

namespace Elementor\Core\Kits\Documents {
	/** `Kit::save()`: `before_save` de las pestañas, guardado, `on_save` de las pestañas, CSS del sitio regenerado. */
	class Kit extends \Elementor\Core\Base\Document {
		public function save( $data ) {
			$data  = self::layout_before_save( $data );
			$saved = parent::save( $data );
			if ( ! $saved ) {
				return false;
			}
			self::identity_on_save( $data );
			self::woo_on_save( $data );
			$GLOBALS['ms']['kit_css'][ $this->post->ID ] = ms_kit_css( $this->post->ID );
			return $saved;
		}
		/** `Settings_Layout::before_save()`: los puntos de corte viejos salen de lo recibido (o del valor por defecto). */
		private static function layout_before_save( array $data ) {
			if ( empty( $data['settings'] ) ) {
				return $data;
			}
			$data['settings']['viewport_md'] = empty( $data['settings']['viewport_mobile'] ) ? 767 + 1 : $data['settings']['viewport_mobile'] + 1;
			$data['settings']['viewport_lg'] = empty( $data['settings']['viewport_tablet'] ) ? 1024 + 1 : $data['settings']['viewport_tablet'] + 1;
			return $data;
		}
		/** `Settings_Site_Identity::on_save()`. */
		private static function identity_on_save( $data ) {
			if ( ! isset( $data['settings']['post_status'] ) || 'publish' !== $data['settings']['post_status'] ) {
				return;
			}
			if ( isset( $data['settings']['site_name'] ) ) {
				update_option( 'blogname', $data['settings']['site_name'] );
			}
			if ( isset( $data['settings']['site_description'] ) ) {
				update_option( 'blogdescription', $data['settings']['site_description'] );
			}
			if ( isset( $data['settings']['site_logo'] ) ) {
				set_theme_mod( 'custom_logo', $data['settings']['site_logo']['id'] );
			}
			if ( isset( $data['settings']['site_favicon'] ) ) {
				update_option( 'site_icon', $data['settings']['site_favicon']['id'] );
			}
		}
		/** `Settings_Woocommerce::on_save()` (modules/woocommerce/settings/settings-woocommerce.php). */
		private static function woo_on_save( $data ) {
			if ( ! isset( $data['settings']['post_status'] ) || 'publish' !== $data['settings']['post_status'] ) {
				return;
			}
			$map = array(
				'woocommerce_cart_page_id'             => 'woocommerce_cart_page_id',
				'woocommerce_checkout_page_id'         => 'woocommerce_checkout_page_id',
				'woocommerce_myaccount_page_id'        => 'woocommerce_myaccount_page_id',
				'woocommerce_terms_page_id'            => 'woocommerce_terms_page_id',
				'woocommerce_purchase_summary_page_id' => 'elementor_woocommerce_purchase_summary_page_id',
				'woocommerce_shop_page_id'             => 'woocommerce_shop_page_id',
			);
			foreach ( $map as $ec_key => $wc_key ) {
				if ( array_key_exists( $ec_key, $data['settings'] ) ) {
					update_option( $wc_key, $data['settings'][ $ec_key ] ? $data['settings'][ $ec_key ] : '' );
				}
			}
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	error_reporting( E_ALL );
	set_error_handler(
		static function ( $severity, $message, $file, $line ) {
			fwrite( STDERR, "PHP $severity: $message en $file:$line\n" );
			$GLOBALS['ms_php_errors'] = ( $GLOBALS['ms_php_errors'] ?? 0 ) + 1;
			return true;
		}
	);

	$GLOBALS['ms_failures'] = 0;
	$GLOBALS['ms_checks']   = 0;
	function check( $ok, string $message ) {
		++$GLOBALS['ms_checks'];
		if ( $ok ) {
			echo "ok · $message\n";
		} else {
			++$GLOBALS['ms_failures'];
			echo "FALLA · $message\n";
		}
	}

	class WP_Error {
		public $code;
		public $message;
		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_code() {
			return $this->code;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
	class WP_Http {
		const BAD_REQUEST           = 400;
		const FORBIDDEN             = 403;
		const NOT_FOUND             = 404;
		const INTERNAL_SERVER_ERROR = 500;
	}
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
	function __( $text, $domain = '' ) {
		return $text;
	}
	function absint( $n ) {
		return abs( (int) $n );
	}
	function current_user_can( $cap ) {
		return 'unfiltered_html' === $cap ? ! empty( $GLOBALS['ms']['unfiltered'] ) : true;
	}
	function do_action( $hook, ...$args ) {
		apply_filters( $hook, ...( $args ? $args : array( null ) ) );
	}
	function get_current_user_id() {
		return 7;
	}
	function wp_slash( $value ) {
		return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value );
	}
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
	}

	// Ganchos con prioridad, como WordPress (PHP_INT_MIN y PHP_INT_MAX incluidos).
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['ms_hooks'][ $hook ][ $priority ][] = array( $callback, $args );
		return true;
	}
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return add_filter( $hook, $callback, $priority, $args );
	}
	function remove_filter( $hook, $callback, $priority = 10 ) {
		foreach ( $GLOBALS['ms_hooks'][ $hook ][ $priority ] ?? array() as $i => $entry ) {
			if ( $entry[0] === $callback ) {
				unset( $GLOBALS['ms_hooks'][ $hook ][ $priority ][ $i ] );
				return true;
			}
		}
		return false;
	}
	function has_filter( $hook ) {
		foreach ( $GLOBALS['ms_hooks'][ $hook ] ?? array() as $list ) {
			if ( $list ) {
				return true;
			}
		}
		return false;
	}
	function apply_filters( $hook, $value, ...$args ) {
		$all = $GLOBALS['ms_hooks'][ $hook ] ?? array();
		ksort( $all );
		foreach ( $all as $list ) {
			foreach ( $list as $entry ) {
				$value = call_user_func_array( $entry[0], array_slice( array_merge( array( $value ), $args ), 0, $entry[1] ) );
			}
		}
		return $value;
	}

	// Metas (también de revisiones: `get/update/delete_metadata`, que no redirigen a la entrada).
	function get_metadata( $type, $id, $key, $single = false ) {
		$value = $GLOBALS['ms']['meta'][ $id ][ $key ] ?? '';
		return $single ? $value : ( '' === $value ? array() : array( $value ) );
	}
	function update_metadata( $type, $id, $key, $value ) {
		$GLOBALS['ms']['meta'][ $id ][ $key ] = wp_unslash( $value );
		return true;
	}
	function delete_metadata( $type, $id, $key ) {
		unset( $GLOBALS['ms']['meta'][ $id ][ $key ] );
		return true;
	}
	/** Autoguardados: `$GLOBALS['ms']['autosaves'][ entrada ][ usuario ] = revisión`; sin usuario, el más reciente. */
	function wp_get_post_autosave( $id, $user = 0 ) {
		$list     = $GLOBALS['ms']['autosaves'][ $id ] ?? array();
		$autosave = $user ? ( $list[ $user ] ?? 0 ) : ( $list ? max( $list ) : 0 );
		return $autosave ? ms_revision( $id, $autosave ) : false;
	}
	function ms_revision( $parent, $id ) {
		return (object) array( 'ID' => $id, 'post_type' => 'revision', 'post_parent' => $parent, 'post_name' => $parent . '-autosave-v1' );
	}
	function wp_is_post_autosave( $post ) {
		return is_object( $post ) && false !== strpos( (string) $post->post_name, '-autosave-' ) ? (int) $post->post_parent : false;
	}
	/** `get_posts()` de los autoguardados de una entrada (por su nombre `<id>-autosave-v1`). */
	function get_posts( $args ) {
		$id  = (int) $args['post_parent'];
		$out = array();
		foreach ( $GLOBALS['ms']['autosaves'][ $id ] ?? array() as $rev ) {
			if ( 'revision' === $args['post_type'] && $id . '-autosave-v1' === $args['name'] ) {
				$out[] = ms_revision( $id, $rev );
			}
		}
		return $out;
	}
	/** `wp_delete_post_revision()` → `wp_delete_post()` con su filtro `pre_delete_post`. */
	function wp_delete_post_revision( $rev ) {
		foreach ( $GLOBALS['ms']['autosaves'] as $parent => $list ) {
			$user = array_search( $rev, $list, true );
			if ( false === $user ) {
				continue;
			}
			$check = apply_filters( 'pre_delete_post', null, ms_revision( $parent, $rev ), false );
			if ( null !== $check ) {
				return $check;
			}
			unset( $GLOBALS['ms']['autosaves'][ $parent ][ $user ], $GLOBALS['ms']['meta'][ $rev ] );
			return true;
		}
		return false;
	}

	// Opciones y mods del tema con sus filtros previos, como `update_option()` y `set_theme_mod()` de WordPress.
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['ms']['options'] ) ? $GLOBALS['ms']['options'][ $name ] : $default;
	}
	function update_option( $name, $value ) {
		$old   = get_option( $name );
		$value = apply_filters( "pre_update_option_{$name}", $value, $old, $name );
		if ( $value === $old ) {
			return false;
		}
		$GLOBALS['ms']['options'][ $name ] = $value;
		return true;
	}
	function set_theme_mod( $name, $value ) {
		$old = $GLOBALS['ms']['theme_mods'][ $name ] ?? false;
		$GLOBALS['ms']['theme_mods'][ $name ] = apply_filters( "pre_set_theme_mod_{$name}", $value, $old );
		return true;
	}

	/** Registro de la Abilities API: `wp_register_ability_args` y el `execute_callback` resultante. */
	function wp_register_ability( $id, $args ) {
		$GLOBALS['ms_abilities'][ $id ] = apply_filters( 'wp_register_ability_args', $args, $id );
	}
	function ms_run( $id, $input ) {
		return call_user_func( $GLOBALS['ms_abilities'][ $id ]['execute_callback'], $input );
	}

	/** Variables globales del CSS del kit (`Kit` → `Global_CSS`) a partir de su meta guardada. */
	function ms_kit_css( int $id ): string {
		$s   = $GLOBALS['ms']['meta'][ $id ]['_elementor_page_settings'] ?? array();
		$css = '.elementor-kit-' . $id . '{';
		foreach ( array_merge( (array) ( $s['system_colors'] ?? array() ), (array) ( $s['custom_colors'] ?? array() ) ) as $c ) {
			$css .= '--e-global-color-' . $c['_id'] . ':' . $c['color'] . ';';
		}
		foreach ( array_merge( (array) ( $s['system_typography'] ?? array() ), (array) ( $s['custom_typography'] ?? array() ) ) as $t ) {
			$css .= '--e-global-typography-' . $t['_id'] . '-font-family:"' . $t['typography_font_family'] . '";';
		}
		return $css . '}' . ( $s['custom_css'] ?? '' );
	}

	class Ms_Documents {
		public function get( $id ) {
			return $GLOBALS['ms']['docs'][ (int) $id ] ?? null;
		}
	}

	const KIT  = 5;
	const PAGE = 20;
	const COMP = 30;

	function ms_kit_settings(): array {
		return array(
			'system_colors'           => array(
				array( '_id' => 'primary', 'title' => 'Principal', 'color' => '#1A2B3C' ),
				array( '_id' => 'secondary', 'title' => 'Secundario', 'color' => '#445566' ),
				array( '_id' => 'text', 'title' => 'Texto', 'color' => '#111111' ),
				array( '_id' => 'accent', 'title' => 'Acento', 'color' => '#FF6600' ),
			),
			'custom_colors'           => array( array( '_id' => 'a1b2c3d', 'title' => 'Marca', 'color' => '#00AA88' ) ),
			'system_typography'       => array(
				array( '_id' => 'primary', 'title' => 'Principal', 'typography_typography' => 'custom', 'typography_font_family' => 'Montserrat', 'typography_font_weight' => '700' ),
				array( '_id' => 'text', 'title' => 'Texto', 'typography_typography' => 'custom', 'typography_font_family' => 'Open Sans', 'typography_font_weight' => '400' ),
			),
			'custom_typography'       => array( array( '_id' => 'f0e1d2c', 'title' => 'Titulares', 'typography_typography' => 'custom', 'typography_font_family' => 'Playfair Display' ) ),
			'__globals__'             => array(
				'button_text_color'       => 'globals/colors?id=secondary',
				'button_background_color' => 'globals/colors?id=accent',
				'body_typography'         => 'globals/typography?id=text',
			),
			'button_typography_typography' => 'custom',
			'button_border_radius'    => array( 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true ),
			'button_padding'          => array( 'unit' => 'px', 'top' => '14', 'right' => '28', 'bottom' => '14', 'left' => '28', 'isLinked' => false ),
			'form_field_border_color' => '#CCCCCC',
			'form_field_background_color' => '#FAFAFA',
			'body_background_background' => 'classic',
			'body_background_color'   => '#FFFFFF',
			'container_width'         => array( 'unit' => 'px', 'size' => 1200 ),
			'viewport_mobile'         => 600,
			'viewport_tablet'         => 900,
			'viewport_md'             => 601,
			'viewport_lg'             => 901,
			'site_name'               => 'Nombre viejo guardado en el kit',
			'site_description'        => 'Descripción vieja',
			'site_logo'               => array( 'id' => 99, 'url' => 'http://ensayo.test/logo-viejo.png' ),
			'site_favicon'            => array( 'id' => 98, 'url' => 'http://ensayo.test/icono-viejo.png' ),
			'woocommerce_cart_page_id' => 11,
			'woocommerce_checkout_page_id' => 12,
			'woocommerce_myaccount_page_id' => 13,
			'woocommerce_terms_page_id' => 14,
			'woocommerce_purchase_summary_page_id' => 15,
			'woocommerce_shop_page_id' => 16,
			'custom_css'              => '.viejo{color:red}',
		);
	}
	function ms_page_settings(): array {
		return array(
			'hide_title'                   => '',
			'padding'                      => array( 'unit' => 'px', 'top' => '40', 'right' => '0', 'bottom' => '40', 'left' => '0', 'isLinked' => false ),
			'background_background'        => 'classic',
			'background_color'             => '#F4F4F4',
			'background_image'             => array( 'id' => 77, 'url' => 'http://ensayo.test/fondo.jpg' ),
			'custom_css'                   => 'selector .titulo > span { color: "azul"; }',
			'__globals__'                  => array( 'background_color' => 'globals/colors?id=primary' ),
		);
	}
	function ms_options(): array {
		return array(
			'blogname'                                       => 'Sitio de ensayo',
			'blogdescription'                                => 'Otro lema',
			'site_icon'                                      => 41,
			'woocommerce_cart_page_id'                       => 101,
			'woocommerce_checkout_page_id'                   => 102,
			'woocommerce_myaccount_page_id'                  => 103,
			'woocommerce_terms_page_id'                      => 104,
			'elementor_woocommerce_purchase_summary_page_id' => 105,
			'woocommerce_shop_page_id'                       => 106,
		);
	}
	function ms_reset(): void {
		$kit  = new \Elementor\Core\Kits\Documents\Kit( KIT );
		$page = new \Elementor\Core\Base\Document( PAGE );
		$comp = new \Elementor\Core\Base\Document( COMP );
		$GLOBALS['ms'] = array(
			'docs'       => array( KIT => $kit, PAGE => $page, COMP => $comp ),
			'meta'       => array(
				KIT  => array( '_elementor_page_settings' => ms_kit_settings() ),
				PAGE => array( '_elementor_page_settings' => ms_page_settings() ),
				COMP => array(
					'_elementor_page_settings'               => array( 'overridable_props' => array( 'props' => array( 'viejo' ) ), 'variants' => array( 'v1' ), 'custom_css' => '.c{}' ),
					'_elementor_component_overridable_props' => array( 'props' => array( 'publicado' ) ),
				),
			),
			'options'    => ms_options(),
			'status'     => array( KIT => 'publish', PAGE => 'publish', COMP => 'draft' ),
			'unfiltered' => true,
			'theme_mods' => array( 'custom_logo' => 42 ),
			'autosaves'  => array(),
			'saved'      => array(),
			'kit_css'    => array(),
			'mutations'  => array(),
			'titles'     => array(),
		);
		\Elementor\Plugin::$instance = (object) array( 'documents' => new Ms_Documents() );
	}
	/** `sanitize_title()` en lo que importa aquí: minúsculas, guiones y nada más. */
	function sanitize_title( $title ) {
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $title ) ), '-' );
	}
	/** `wp_update_post()`: anota lo recibido y aplica el slug a la entrada. */
	function wp_update_post( $postarr, $wp_error = false ) {
		$GLOBALS['ms']['updates'][] = $postarr;
		if ( ! empty( $GLOBALS['ms']['slug_fail'] ) ) {
			return new WP_Error( 'db_update_error', 'No se pudo actualizar la entrada en la base de datos.' );
		}
		if ( isset( $postarr['post_name'] ) ) {
			$GLOBALS['ms']['slugs'][ (int) $postarr['ID'] ] = $postarr['post_name'];
		}
		return (int) $postarr['ID'];
	}
	function get_post_field( $field, $id, $context = 'display' ) {
		return 'post_name' === $field ? ( $GLOBALS['ms']['slugs'][ (int) $id ] ?? 'slug-viejo' ) : '';
	}
	function ms_meta( int $id ) {
		return $GLOBALS['ms']['meta'][ $id ]['_elementor_page_settings'] ?? null;
	}
	function ms_without( array $settings, array $keys ): array {
		return array_diff_key( $settings, array_flip( $keys ) );
	}

	$root = dirname( __DIR__ ) . '/digitalisimo-elements';
	$core = $root . '/elementor/modules/mcp/abilities';
	require $core . '/ability-definition.php';
	require $core . '/abstract-ability.php';
	require $core . '/update-settings-ability.php';
	$merge = $root . '/modules/digitalisimo-mcp/class-settings-merge.php';
	$fixed = file_exists( $merge );
	if ( $fixed ) {
		require $merge;
		\Digitalisimo\Elements\Mcp\Settings_Merge::init();
	}
	check( $fixed, 'existe modules/digitalisimo-mcp/class-settings-merge.php' );

	// Lo que los dobles reproducen sigue igual en el núcleo incluido y en PRO.
	$src = static function ( string $path ) use ( $root ) {
		return (string) file_get_contents( $root . '/' . $path );
	};
	check( false !== strpos( $src( 'elementor/modules/mcp/abilities/update-settings-ability.php' ), '$document->save( [ \'settings\' => $settings ] )' ), 'núcleo: update-page-settings guarda lo recibido con Document::save()' );
	check( false !== strpos( $src( 'elementor/core/settings/page/manager.php' ), "update_metadata( 'post', \$id, self::META_KEY, wp_slash( \$settings ) );" ), 'núcleo: Page\Manager guarda los ajustes tal cual (reemplazo) en la meta' );
	check( false !== strpos( $src( 'elementor/core/base/document.php' ), "if ( ! empty( \$data['settings'] ) ) {" ), 'núcleo: unos ajustes vacíos no se guardan' );
	$identity = $src( 'elementor/core/kits/documents/tabs/settings-site-identity.php' );
	check( false !== strpos( $identity, "Document::STATUS_PUBLISH !== \$data['settings']['post_status']" ) && false !== strpos( $identity, "update_option( 'blogname'" ) && false !== strpos( $identity, "set_theme_mod( 'custom_logo'" ) && false !== strpos( $identity, "update_option( 'site_icon'" ), 'núcleo: la identidad del sitio escribe opciones sólo con post_status publish' );
	$woo = $src( 'modules/woocommerce/settings/settings-woocommerce.php' );
	check( false !== strpos( $woo, "'woocommerce_purchase_summary_page_id' => 'elementor_woocommerce_purchase_summary_page_id'" ) && false !== strpos( $woo, "update_option( \$wc_key, \$value );" ), 'PRO: la pestaña de WooCommerce escribe sus seis opciones de páginas' );
	check( false !== strpos( $src( 'elementor/core/kits/documents/tabs/settings-layout.php' ), "\$data['settings'][ \$prefix . 'md' ] = empty( \$data['settings'][ \$mobile_breakpoint_key ] )" ), 'núcleo: los puntos de corte viejos se recalculan de lo recibido' );

	// `Components\Module::set_component_overridable_props()`: el after_save copia overridable_props a su meta propia.
	add_action(
		'elementor/document/after_save',
		static function ( $document, $data ) {
			if ( COMP === $document->get_post()->ID && isset( $data['settings']['overridable_props'] ) ) {
				$GLOBALS['ms']['meta'][ COMP ]['_elementor_component_overridable_props'] = $data['settings']['overridable_props'];
			}
		},
		10,
		2
	);

	// Registro real de la herramienta del núcleo, con el filtro de la Abilities API.
	ms_reset();
	( new \Elementor\Modules\Mcp\Abilities\Update_Settings_Ability() )->register();
	$args = $GLOBALS['ms_abilities']['elementor/update-page-settings'];
	check( false !== strpos( $args['description'], 'null' ) && false !== strpos( $args['description'], '__globals__' ) && false !== strpos( $args['description'], 'every other saved setting is kept' ), 'la descripción dice que se combina, que null borra y cómo va __globals__' );
	check( false !== strpos( $args['input_schema']['properties']['settings']['description'] ?? '', 'a key with null is deleted' ), 'el esquema de settings documenta el borrado con null' );
	check( isset( $args['output_schema']['properties']['changed_keys'], $args['output_schema']['properties']['removed_keys'] ), 'el esquema de salida declara changed_keys y removed_keys' );
	check( array( 'post_id', 'settings' ) === $args['input_schema']['required'], 'la entrada sigue exigiendo post_id y settings' );

	// K1: kit completo, sólo custom_css.
	ms_reset();
	$before = ms_meta( KIT );
	$r      = ms_run( 'elementor/update-page-settings', array( 'post_id' => KIT, 'settings' => array( 'custom_css' => '.nuevo{color:blue}' ) ) );
	$after  = ms_meta( KIT );
	check( is_array( $r ) && true === $r['success'], 'K1: la herramienta responde success' );
	check( is_array( $after ) && '.nuevo{color:blue}' === $after['custom_css'], 'K1: custom_css cambió' );
	check( is_array( $after ) && ms_without( $before, array( 'custom_css' ) ) === ms_without( $after, array( 'custom_css' ) ), 'K1: colores, tipografías, __globals__, botones, formularios, puntos de corte, identidad y WooCommerce del kit quedan intactos' );
	$css = $GLOBALS['ms']['kit_css'][ KIT ] ?? '';
	check( false !== strpos( $css, '--e-global-color-primary:#1A2B3C' ) && false !== strpos( $css, '--e-global-color-accent:#FF6600' ) && false !== strpos( $css, '--e-global-color-a1b2c3d:#00AA88' ), 'K1: el CSS del kit conserva --e-global-color-*' );
	check( false !== strpos( $css, '--e-global-typography-primary-font-family:"Montserrat"' ) && false !== strpos( $css, '--e-global-typography-f0e1d2c-font-family:"Playfair Display"' ), 'K1: el CSS del kit conserva --e-global-typography-*' );
	check( is_array( $r ) && array( 'custom_css' ) === ( $r['changed_keys'] ?? null ) && array() === ( $r['removed_keys'] ?? null ), 'K1: la respuesta dice que sólo cambió custom_css' );
	check( 'http://ensayo.test/?p=5' === ( $r['preview_url'] ?? '' ) && array( KIT ) === $GLOBALS['ms']['mutations'], 'K1: la respuesta y la marca de mutación del núcleo siguen ahí' );
	check( ms_options() === $GLOBALS['ms']['options'] && 42 === $GLOBALS['ms']['theme_mods']['custom_logo'], 'K1: ninguna opción de WordPress ni de WooCommerce cambió' );

	// K2: un solo global.
	ms_reset();
	$before = ms_meta( KIT );
	$r      = ms_run( 'elementor/update-page-settings', array( 'post_id' => KIT, 'settings' => array( '__globals__' => array( 'button_background_color' => 'globals/colors?id=primary' ) ) ) );
	$after  = ms_meta( KIT );
	check( is_array( $after ) && array( 'button_text_color' => 'globals/colors?id=secondary', 'button_background_color' => 'globals/colors?id=primary', 'body_typography' => 'globals/typography?id=text' ) === ( $after['__globals__'] ?? null ), 'K2: un global en __globals__ cambia y los otros quedan' );
	check( is_array( $after ) && ms_without( $before, array( '__globals__' ) ) === ms_without( $after, array( '__globals__' ) ), 'K2: el resto del kit queda intacto' );
	check( is_array( $r ) && array( '__globals__.button_background_color' ) === ( $r['changed_keys'] ?? null ), 'K2: la respuesta dice __globals__.button_background_color' );

	// K3: null borra una clave y un global; la respuesta lo dice.
	ms_reset();
	$r     = ms_run( 'elementor/update-page-settings', array( 'post_id' => KIT, 'settings' => array( 'custom_css' => null, '__globals__' => array( 'button_text_color' => null ) ) ) );
	$after = ms_meta( KIT );
	check( is_array( $after ) && ! array_key_exists( 'custom_css', $after ) && isset( $after['system_colors'] ), 'K3: custom_css con null se borra y lo demás queda' );
	check( is_array( $after ) && array( 'button_background_color' => 'globals/colors?id=accent', 'body_typography' => 'globals/typography?id=text' ) === ( $after['__globals__'] ?? null ), 'K3: un global con null se desvincula y los otros quedan' );
	$sorted = static function ( $list ) {
		$list = (array) $list;
		sort( $list );
		return $list;
	};
	check( is_array( $r ) && array( '__globals__.button_text_color', 'custom_css' ) === $sorted( $r['changed_keys'] ?? null ) && array( '__globals__.button_text_color', 'custom_css' ) === $sorted( $r['removed_keys'] ?? null ), 'K3: changed_keys y removed_keys nombran lo borrado' );

	// K4: el kit con post_status publish (los on_save de las pestañas corren) no toca opciones que no vinieron.
	ms_reset();
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => KIT, 'settings' => array( 'post_status' => 'publish', 'custom_css' => '.x{}' ) ) );
	check( is_array( $r ) && $r['success'] && ms_options() === $GLOBALS['ms']['options'], 'K4: con post_status publish, ni el nombre, la descripción, el ícono ni las seis páginas de WooCommerce cambian' );
	check( 42 === $GLOBALS['ms']['theme_mods']['custom_logo'], 'K4: el logo del tema no cambia' );
	check( is_array( $r ) && array( 'post_status' ) === ( $r['post_fields'] ?? null ) && array( 'custom_css' ) === ( $r['changed_keys'] ?? null ), 'K4: post_status va en post_fields, no en changed_keys' );
	ms_reset();
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => KIT, 'settings' => array( 'post_status' => 'publish', 'site_name' => 'Nombre nuevo', 'woocommerce_cart_page_id' => 201 ) ) );
	$expected = array_merge( ms_options(), array( 'blogname' => 'Nombre nuevo', 'woocommerce_cart_page_id' => 201 ) );
	check( $expected === $GLOBALS['ms']['options'] && 42 === $GLOBALS['ms']['theme_mods']['custom_logo'], 'K4: lo que sí viene (site_name, la página del carrito) se escribe, y nada más' );
	check( ! has_filter( 'pre_update_option_blogdescription' ) && ! has_filter( 'pre_set_theme_mod_custom_logo' ), 'K4: la protección de las opciones se retira al terminar' );
	ms_reset();
	update_option( 'blogdescription', 'Cambio fuera del MCP' );
	check( 'Cambio fuera del MCP' === get_option( 'blogdescription' ), 'K4: fuera de la herramienta las opciones se escriben como siempre' );

	// P0: una meta escrita a mano o copiada de otro sitio con campos de la entrada (post_title, post_status, template)
	// no los vuelve a aplicar en cada llamada: sólo se aplica lo que viene en la petición.
	ms_reset();
	$stray = ms_meta( PAGE );
	$stray = array_merge( is_array( $stray ) ? $stray : array(), array( 'post_title' => 'Título de otro sitio', 'post_status' => 'draft', 'template' => 'elementor_canvas' ) );
	$GLOBALS['ms']['meta'][ PAGE ]['_elementor_page_settings'] = $stray;
	$title_before = $GLOBALS['ms']['titles'][ PAGE ] ?? null;
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'hide_title' => 'yes' ) ) );
	check( is_array( $r ) && $r['success'] && $title_before === ( $GLOBALS['ms']['titles'][ PAGE ] ?? null ), 'P0: un post_title suelto en la meta no se vuelve a aplicar a la entrada' );
	check( is_array( $r ) && array() === ( $r['post_fields'] ?? null ), 'P0: post_fields queda vacío si la petición no trae campos de la entrada' );

	// P1: página con diseño, fondo y CSS; actualizar una clave conserva el resto.
	ms_reset();
	$before = ms_meta( PAGE );
	$r      = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'hide_title' => 'yes' ) ) );
	$after  = ms_meta( PAGE );
	check( is_array( $after ) && 'yes' === $after['hide_title'] && ms_without( $before, array( 'hide_title' ) ) === ms_without( $after, array( 'hide_title' ) ), 'P1: hide_title cambia; relleno, fondo, imagen, CSS (con comillas y >) y __globals__ quedan' );
	check( is_array( $r ) && array( 'hide_title' ) === ( $r['changed_keys'] ?? null ), 'P1: la respuesta dice hide_title' );
	ms_reset();
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'post_title' => 'Título nuevo', 'background_color' => '#F4F4F4' ) ) );
	check( is_array( $r ) && ms_page_settings() === ms_meta( PAGE ) && array() === $r['changed_keys'] && array( 'post_title' ) === $r['post_fields'] && 'Título nuevo' === ( $GLOBALS['ms']['titles'][ PAGE ] ?? '' ), 'P1: un valor igual no cuenta como cambio y el título va a la entrada' );

	// N1–N3: post_name (el slug) va a la entrada, nunca a la meta, y sin tocar la plantilla (incidente digitalisimo.lat,
	// 2026-11-23). Con el código anterior se guardaba como ajuste y el slug no cambiaba.
	ms_reset();
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'post_name' => 'Black Friday 2026' ) ) );
	$upd = $GLOBALS['ms']['updates'][0] ?? array();
	check( is_array( $r ) && ! empty( $r['success'] ) && 'black-friday-2026' === ( $GLOBALS['ms']['slugs'][ PAGE ] ?? '' ) && 'black-friday-2026' === ( $r['post_name'] ?? '' ), 'N1: post_name cambia el slug de la entrada y la respuesta da el final' );
	check( PAGE === ( $upd['ID'] ?? 0 ) && array_key_exists( 'page_template', $upd ) && null === $upd['page_template'] && 1 === count( $GLOBALS['ms']['updates'] ), 'N1: un solo wp_update_post, con page_template null (no revalida ni reescribe la plantilla)' );
	check( ms_page_settings() === ms_meta( PAGE ) && array( 'post_name' ) === ( $r['post_fields'] ?? null ) && array() === ( $r['changed_keys'] ?? null ), 'N1: post_name no se guarda como ajuste: la meta queda igual y va en post_fields' );
	ms_reset();
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'post_name' => ' ¡¡ ', 'hide_title' => 'yes' ) ) );
	check( is_wp_error( $r ) && 'invalid_post_name' === $r->get_error_code() && ms_page_settings() === ms_meta( PAGE ) && empty( $GLOBALS['ms']['updates'] ), 'N2: un slug vacío se rechaza sin guardar nada' );
	ms_reset();
	$GLOBALS['ms']['meta'][ PAGE ]['_elementor_page_settings']['post_name'] = 'basura-de-antes';
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'hide_title' => 'yes' ) ) );
	check( is_array( $r ) && ! array_key_exists( 'post_name', (array) ms_meta( PAGE ) ) && empty( $GLOBALS['ms']['updates'] ), 'N3: un post_name guardado antes como ajuste se limpia y no se aplica a la entrada' );

	// N4: un slug numérico (llega como número en el JSON) vale (revisión del 2026-11-25).
	ms_reset();
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'post_name' => 2026 ) ) );
	check( is_array( $r ) && '2026' === ( $r['post_name'] ?? '' ) && '2026' === ( $GLOBALS['ms']['slugs'][ PAGE ] ?? '' ), 'N4: un slug numérico (2026) se acepta' );
	// N5: si el slug no se puede cambiar pero los ajustes ya se guardaron, el error lo dice claro.
	ms_reset();
	$GLOBALS['ms']['slug_fail'] = true;
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'post_name' => 'nuevo', 'hide_title' => 'yes' ) ) );
	check( is_wp_error( $r ) && 'post_name_not_changed' === $r->get_error_code() && 0 === strpos( $r->get_error_message(), 'Ajustes guardados; el slug no se pudo cambiar: No se pudo actualizar' ), 'N5: «Ajustes guardados; el slug no se pudo cambiar: …» con el motivo' );
	check( 'yes' === ( ms_meta( PAGE )['hide_title'] ?? '' ), 'N5: y los ajustes sí quedaron guardados' );

	// P2: borrar la última clave borra la meta (el núcleo no guarda ajustes vacíos).
	ms_reset();
	$GLOBALS['ms']['meta'][ PAGE ]['_elementor_page_settings'] = array( 'custom_css' => '.solo{}' );
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'custom_css' => null ) ) );
	check( is_array( $r ) && $r['success'] && null === ms_meta( PAGE ) && array( 'custom_css' ) === $r['removed_keys'], 'P2: con null en la única clave, la meta se borra' );

	// P3: en una publicada, el núcleo borra el autoguardado más reciente al guardar ajustes; con los cambios pendientes
	// del MCP (build-composition / manage-elements) dentro, no se borra y recibe lo pedido.
	ms_reset();
	$GLOBALS['ms']['autosaves'][ PAGE ][7]                       = 21;
	$GLOBALS['ms']['meta'][21]['_elementor_data']                = 'elementos pendientes';
	$GLOBALS['ms']['meta'][21]['_elementor_page_settings'] = array_merge( ms_page_settings(), array( 'background_color' => '#000000', 'custom_css' => '.pendiente{}', 'hide_title' => 'no' ) );
	ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'hide_title' => 'yes', 'custom_css' => null ) ) );
	$pending = ms_meta( 21 );
	check( 21 === ( $GLOBALS['ms']['autosaves'][ PAGE ][7] ?? 0 ) && 'elementos pendientes' === ( $GLOBALS['ms']['meta'][21]['_elementor_data'] ?? '' ), 'P3: el autoguardado con los elementos pendientes no se borra' );
	check( is_array( $pending ) && 'yes' === $pending['hide_title'] && ! array_key_exists( 'custom_css', $pending ) && '#000000' === $pending['background_color'], 'P3: el autoguardado recibe hide_title y el borrado, y conserva su fondo pendiente' );
	check( ! has_filter( 'pre_delete_post' ), 'P3: la protección de los autoguardados se retira al terminar' );
	wp_delete_post_revision( 21 );
	check( ! isset( $GLOBALS['ms']['autosaves'][ PAGE ][7] ), 'P3: fuera de la herramienta los autoguardados se borran como siempre' );
	// P4: un valor igual al de la entrada pero distinto del pendiente también llega al autoguardado.
	ms_reset();
	$GLOBALS['ms']['autosaves'][ PAGE ][7]                       = 21;
	$GLOBALS['ms']['meta'][21]['_elementor_page_settings'] = array_merge( ms_page_settings(), array( 'background_color' => '#000000' ) );
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'background_color' => '#F4F4F4' ) ) );
	check( is_array( $r ) && array() === ( $r['changed_keys'] ?? null ) && '#F4F4F4' === ( ms_meta( 21 )['background_color'] ?? '' ), 'P4: lo pedido llega al autoguardado aunque la entrada ya lo tuviera' );

	// K5: sin unfiltered_html, kses del núcleo no toca las claves que no vinieron.
	ms_reset();
	$GLOBALS['ms']['unfiltered'] = false;
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'hide_title' => 'yes' ) ) );
	check( is_array( $r ) && 'selector .titulo > span { color: "azul"; }' === ( ms_meta( PAGE )['custom_css'] ?? '' ) && array( 'hide_title' ) === ( $r['changed_keys'] ?? null ), 'K5: sin unfiltered_html, el CSS guardado (con >) no se reescribe y sólo cambia hide_title' );
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'custom_css' => '.a > .b{}' ) ) );
	check( '.a &gt; .b{}' === ( ms_meta( PAGE )['custom_css'] ?? '' ), 'K5: lo que sí viene pasa por kses como en el núcleo' );

	// E2: settings vacío no vuelve a guardar nada.
	ms_reset();
	$GLOBALS['ms']['autosaves'][ PAGE ][7] = 21;
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array() ) );
	check( is_array( $r ) && $r['success'] && array() === ( $r['changed_keys'] ?? null ) && ms_page_settings() === ms_meta( PAGE ) && isset( $GLOBALS['ms']['autosaves'][ PAGE ][7] ), 'E2: settings {} responde sin cambios ni borrados' );

	// E1: errores del núcleo intactos.
	ms_reset();
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => 999, 'settings' => array( 'a' => 1 ) ) );
	check( $r instanceof WP_Error && 'document_not_found' === $r->get_error_code(), 'E1: documento inexistente → el error del núcleo' );
	$GLOBALS['ms']['docs'][ PAGE ]->editable = false;
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE, 'settings' => array( 'hide_title' => 'yes' ) ) );
	check( $r instanceof WP_Error && 'rest_cannot_edit' === $r->get_error_code() && ms_page_settings() === ms_meta( PAGE ), 'E1: sin permiso → el error del núcleo y nada cambia' );
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => PAGE ) );
	check( $r instanceof WP_Error && 'invalid_settings' === $r->get_error_code(), 'E1: sin settings → el error del núcleo' );

	// C1: otra herramienta del MCP que guarda settings parciales (manage-component manda sólo overridable_props).
	ms_reset();
	wp_register_ability(
		'elementor/manage-component',
		array(
			'execute_callback' => static function ( $input ) {
				return $GLOBALS['ms']['docs'][ COMP ]->save( array( 'elements' => array(), 'settings' => $input['settings'] ) );
			},
		)
	);
	ms_run( 'elementor/manage-component', array( 'settings' => array( 'overridable_props' => array( 'props' => array( 'nuevo' ) ) ) ) );
	check( array( 'overridable_props' => array( 'props' => array( 'nuevo' ) ), 'custom_css' => '.c{}' ) === ms_meta( COMP ), 'C1: manage-component cambia overridable_props y conserva custom_css' );
	// Publicar (Components_Repository::copy_autosave_data_to_main_component_document_and_publish): sólo estado y título.
	ms_reset();
	ms_run( 'elementor/manage-component', array( 'settings' => array( 'post_status' => 'publish', 'post_title' => 'Componente' ) ) );
	check( array( 'props' => array( 'publicado' ) ) === $GLOBALS['ms']['meta'][ COMP ]['_elementor_component_overridable_props'], 'C1: publicar no reescribe las props del componente con la copia vieja de sus ajustes' );
	check( array( 'custom_css' => '.c{}' ) === ms_meta( COMP ), 'C1: publicar conserva los demás ajustes (antes se borraban)' );
	$GLOBALS['ms']['docs'][ COMP ]->save( array( 'settings' => array( 'custom_css' => '.fuera{}' ) ) );
	check( array( 'custom_css' => '.fuera{}' ) === ms_meta( COMP ), 'C1: fuera del MCP, Document::save() sigue como en el núcleo' );
	check( $fixed && array() === \Digitalisimo\Elements\Mcp\Settings_Merge::state()['stack'], 'C1: el ámbito de la herramienta se cierra' );
	// C2: por el proxy del editor (sin el execute_callback registrado) también se combina.
	ms_reset();
	$proxy = new class() {
		public function get_route() {
			return '/elementor/v1/mcp-proxy';
		}
		public function get_method() {
			return 'POST';
		}
	};
	apply_filters( 'rest_request_before_callbacks', null, array(), $proxy );
	$GLOBALS['ms']['docs'][ COMP ]->save( array( 'settings' => array( 'overridable_props' => array( 'props' => array( 'proxy' ) ) ) ) );
	apply_filters( 'rest_request_after_callbacks', null, array(), $proxy );
	check( array( 'overridable_props' => array( 'props' => array( 'proxy' ) ), 'custom_css' => '.c{}' ) === ms_meta( COMP ), 'C2: por el proxy, manage-component también combina' );
	check( $fixed && 0 === \Digitalisimo\Elements\Mcp\Settings_Merge::state()['proxy'], 'C2: el ámbito del proxy se cierra' );
	// Un \Error que escapa del proxy no llega a after_rest: la siguiente petición REST cierra el ámbito.
	ms_reset();
	apply_filters( 'rest_request_before_callbacks', null, array(), $proxy );
	$other = new class() {
		public function get_route() {
			return '/wp/v2/pages';
		}
		public function get_method() {
			return 'POST';
		}
	};
	apply_filters( 'rest_request_before_callbacks', null, array(), $other );
	$GLOBALS['ms']['docs'][ COMP ]->save( array( 'settings' => array( 'custom_css' => '.otra{}' ) ) );
	check( array( 'custom_css' => '.otra{}' ) === ms_meta( COMP ), 'C2: tras un proxy que no cerró, otra petición REST ya no combina' );
	// update-page-settings sobre un componente: las copias overridable_props y variants no se informan como borradas.
	ms_reset();
	$r = ms_run( 'elementor/update-page-settings', array( 'post_id' => COMP, 'settings' => array( 'custom_css' => '.nuevo{}' ) ) );
	check( is_array( $r ) && array( 'custom_css' ) === ( $r['changed_keys'] ?? null ) && array() === ( $r['removed_keys'] ?? null ) && array( 'props' => array( 'publicado' ) ) === $GLOBALS['ms']['meta'][ COMP ]['_elementor_component_overridable_props'], 'C3: en un componente, update-page-settings no reescribe sus props ni dice que las borró' );

	// Unitarias de la combinación.
	if ( $fixed ) {
		$m = '\\Digitalisimo\\Elements\\Mcp\\Settings_Merge';
		check( array( 'a' => 1, 'b' => array( 'x' => 1 ) ) === $m::merge( array( 'a' => 1, 'b' => array( 'x' => 0, 'y' => 2 ) ), array( 'b' => array( 'x' => 1 ) ) ), 'merge: un valor anidado (no __globals__) se reemplaza entero' );
		check( array( 'a' => 1 ) === $m::merge( array( 'a' => 1, '__globals__' => array( 'k' => 'g' ) ), array( '__globals__' => array( 'k' => null ) ) ), 'merge: __globals__ vacío se quita' );
		check( array( 'a' => null ) === $m::merge( array( 'a' => 1 ), array( 'a' => null ), false ), 'merge sin borrado: null se guarda como null' );
	}

	echo "\n{$GLOBALS['ms_checks']} comprobaciones, {$GLOBALS['ms_failures']} fallas" . ( empty( $GLOBALS['ms_php_errors'] ) ? '' : ", {$GLOBALS['ms_php_errors']} avisos de PHP" ) . "\n";
	exit( $GLOBALS['ms_failures'] || ! empty( $GLOBALS['ms_php_errors'] ) ? 1 : 0 );
}
