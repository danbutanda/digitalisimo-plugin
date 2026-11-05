<?php
namespace Digitalisimo\Elements\Conditions;

use Digitalisimo\Elements\Display_Conditions;

defined( 'ABSPATH' ) || exit;

/**
 * Grupos de condiciones de un elemento en sus dos formatos guardados, sin evaluarlos.
 *
 * - v3: `e_display_conditions` = `[ "<JSON de grupos O de condiciones Y>" ]` (o la cadena sola). El formato antiguo,
 *   una lista plana de condiciones, es un solo grupo, como en `get_converted_conditions()` de PRO.
 * - v4: el prop `display-conditions` = `{ $$type: display-conditions, value: [ { $$type: condition-group, value: [
 *   { $$type: string, value: "<JSON de una condición>" } ] } ] }`, el que escriben el control atómico y
 *   `transformV3ToV4Conditions()` del editor. Una entrada que no se entiende se conserva tal cual (`RAW`).
 *
 * Un grupo es una lista de condiciones (arrays con `condition`). Escribir sólo cambia el ajuste pedido.
 */
final class Condition_Groups {
	const V3  = 'e_display_conditions';
	const V4  = 'display-conditions';
	const RAW = '__digitalisimo_raw';

	/** @return array[]|null Grupos del ajuste, o `null` si no hay ninguno o no se entiende. */
	public static function read( array $settings, string $key ): ?array {
		return self::V4 === $key ? self::read_v4( $settings ) : self::read_v3( $settings );
	}

	/** Ajustes con los grupos escritos en el formato del ajuste; sin grupos, el valor vacío de cada formato. */
	public static function write( array $settings, string $key, array $groups ): array {
		$groups = array_values( $groups );
		if ( self::V4 === $key ) {
			$value = array();
			foreach ( $groups as $group ) {
				$items = array();
				foreach ( (array) $group as $condition ) {
					$items[] = is_array( $condition ) && array_key_exists( self::RAW, $condition )
						? $condition[ self::RAW ]
						: array(
							'$$type' => 'string',
							'value'  => self::json( $condition ),
						);
				}
				$value[] = array(
					'$$type' => 'condition-group',
					'value'  => $items,
				);
			}
			$prop                  = isset( $settings[ self::V4 ] ) && is_array( $settings[ self::V4 ] ) ? $settings[ self::V4 ] : array( '$$type' => self::V4 );
			$prop['value']         = $value;
			$settings[ self::V4 ] = $value ? $prop : null;
			return $settings;
		}
		$json                  = $groups ? self::json( $groups ) : '';
		$raw                   = isset( $settings[ self::V3 ] ) ? $settings[ self::V3 ] : array();
		$settings[ self::V3 ] = is_string( $raw ) ? $json : ( '' === $json ? '' : array( $json ) );
		return $settings;
	}

	/** Identificadores de condición de una lista de grupos. */
	public static function condition_ids( array $groups ): array {
		$ids = array();
		foreach ( $groups as $group ) {
			foreach ( (array) $group as $condition ) {
				if ( is_array( $condition ) && isset( $condition['condition'] ) && is_string( $condition['condition'] ) ) {
					$ids[] = $condition['condition'];
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** JSON como el del editor (`JSON.stringify`): sin escapar barras ni caracteres no ASCII. */
	public static function json( $value ): string {
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		$json  = function_exists( 'wp_json_encode' ) ? wp_json_encode( $value, $flags ) : json_encode( $value, $flags );
		return is_string( $json ) ? $json : '[]';
	}

	private static function read_v3( array $settings ): ?array {
		$raw = isset( $settings[ self::V3 ] ) ? $settings[ self::V3 ] : null;
		if ( is_array( $raw ) ) {
			$raw = isset( $raw[0] ) && is_string( $raw[0] ) ? $raw[0] : '';
		}
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || ! $decoded ) {
			return null;
		}
		$flat = true;
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['condition'] ) ) {
				$flat = false;
				break;
			}
		}
		$groups = $flat ? array( $decoded ) : $decoded;
		$out    = array();
		foreach ( $groups as $group ) {
			$out[] = is_array( $group ) ? array_values( $group ) : array();
		}
		return $out;
	}

	private static function read_v4( array $settings ): ?array {
		$prop = isset( $settings[ self::V4 ] ) ? $settings[ self::V4 ] : null;
		if ( ! is_array( $prop ) || empty( $prop['value'] ) || ! is_array( $prop['value'] ) ) {
			return null;
		}
		$out = array();
		foreach ( $prop['value'] as $group ) {
			$items = is_array( $group ) && isset( $group['value'] ) && is_array( $group['value'] ) ? $group['value'] : array();
			$list  = array();
			foreach ( $items as $item ) {
				$condition = is_array( $item ) && isset( $item['value'] ) && is_string( $item['value'] ) ? json_decode( $item['value'], true ) : null;
				$list[]    = is_array( $condition ) ? $condition : array( self::RAW => $item );
			}
			$out[] = $list;
		}
		return $out;
	}
}

/**
 * Reglas de cierre de las condiciones de F7a (V.4.1 y V.4.2), sin otro motor.
 *
 * - **Centinela.** Al guardar (`elementor/document/save/data`), cada grupo Y que contiene una condición de F7a
 *   (`Display_Conditions::MORE`) recibe al final `SENTINEL`, una página especial que 4.3.0.163 no conoce y evalúa como
 *   falsa: si el sitio vuelve a esa versión, o un kit llega a ella, lo condicionado no se ve. Desde 4.3.0.164
 *   `Special_Page_Condition::check()` la da por cumplida. En el editor (`elementor/document/load/data`, sólo en modo
 *   de edición) se retira para que la interfaz no la muestre. Igual en el prop v4. K la conserva tal cual: la página
 *   especial no tiene referencia de kit.
 * - **Cookie.** Añadir o cambiar una condición `digitalisimo_cookie` exige `unfiltered_html` (en una red, el
 *   superadministrador), comprobado en el servidor. Sin el permiso, un elemento que ya existía recupera sus
 *   condiciones guardadas, y en uno nuevo cada condición de cookie que no estaba en el documento se queda sin nombre,
 *   que nunca se cumple: nunca se quita una condición, porque quitarla ampliaría quién ve el elemento.
 * - **Editor.** Las condiciones de F7a no se ofrecen en el control v4, la de cookie no se ofrece a quien no puede
 *   guardarla, cada condición de F7a recuerda que no es una protección y una condición variable en una cabecera o un
 *   pie avisa de que saca de la caché de página todo lo que la muestra.
 */
final class Conditions_Guard {
	const SENTINEL_CONDITION = 'digitalisimo_special_page';
	const SENTINEL_PAGE      = 'digitalisimo_f7a';
	const COOKIE_CONDITION   = 'digitalisimo_cookie';

	/** Widget marcador de K: conserva un original que no se toca hasta que vuelve a su tipo. */
	const MARKER_WIDGET = 'digitalisimo-unconverted';

	/** Aviso común de F7a (3.4.1). */
	const NOTICE = 'Decide qué se muestra, no quién puede leerlo: el contenido oculto sigue en el código de la página para otros canales y una cookie la puede cambiar el visitante.';

	/** Aviso de una condición variable en una cabecera o un pie (R-M7). */
	const LOCATION_NOTICE = 'Esta condición cambia según el visitante y está en una cabecera o un pie: todas las páginas que los muestran dejarán de guardarse en la caché de página.';

	/** Ayuda de «Visitó una página» (V.4.3 y V.14): sin consentimiento no hay visitas que recordar. */
	const VISIT_NOTICE = '«Visitó una página» sólo recuerda visitas con el consentimiento de preferencias del visitante (WP Consent API) o si «Rendimiento» → «Privacidad» lo permite sin gestor de consentimiento. Si no, «visitó» nunca se cumple y «no visitó», siempre.';

	/** Pantalla «Rendimiento» de Elements (`Front\Settings_Page::PAGE`), sección «Privacidad». */
	const PRIVACY_PAGE = 'admin.php?page=digitalisimo-elements-front#digitalisimo-front-privacy';

	/** Tipos de documento del Theme Builder que se imprimen en todo el sitio. */
	const SITE_LOCATIONS = array( 'header', 'footer' );

	/**
	 * Fragmentos de nombre de cookie que nunca se comparan, sin distinguir mayúsculas y después de normalizar el
	 * nombre como PHP: credenciales y sesiones de WordPress, WooCommerce, comentarios y cualquier otro plugin.
	 */
	const DENIED = array(
		'wordpress_', 'wp-postpass_', 'wp_postpass_', 'wp-settings-', 'wp_settings_', 'wp_woocommerce_session_', 'woocommerce_', 'comment_author_', 'phpsessid',
		'sess', 'auth', 'token', 'login', 'pass', 'secret', 'csrf', 'nonce', 'key', 'hash',
	);

	const COOKIE_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

	private static $initialized = false;

	public static function init() {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;
		add_filter( 'elementor/document/save/data', array( __CLASS__, 'on_save' ), 10, 2 );
		add_filter( 'elementor/document/load/data', array( __CLASS__, 'on_load' ), 10, 2 );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'editor_script' ) );
	}

	/** Condición centinela que se añade al final de cada grupo con una condición de F7a. */
	public static function sentinel(): array {
		return array(
			'condition'  => self::SENTINEL_CONDITION,
			'comparator' => 'is_one_of',
			'pages'      => array( self::SENTINEL_PAGE ),
		);
	}

	public static function is_sentinel( $condition ): bool {
		return is_array( $condition )
			&& isset( $condition['condition'], $condition['pages'] )
			&& self::SENTINEL_CONDITION === $condition['condition']
			&& array( self::SENTINEL_PAGE ) === array_values( (array) $condition['pages'] );
	}

	public static function is_more( $condition ): bool {
		return is_array( $condition ) && isset( $condition['condition'] ) && in_array( $condition['condition'], Display_Conditions::MORE, true );
	}

	/** Nombre de cookie normalizado como PHP (`.` y espacio pasan a `_`), o '' si no es válido o está prohibido. */
	public static function cookie_name( $raw ): string {
		$name = str_replace( array( '.', ' ' ), '_', trim( is_scalar( $raw ) ? (string) $raw : '' ) );
		if ( ! preg_match( self::COOKIE_PATTERN, $name ) || self::cookie_denied( $name ) ) {
			return '';
		}
		return $name;
	}

	/** ¿Nombre prohibido? La lista sólo se amplía (`digitalisimo_elements_cookie_condition_denied`), nunca se acorta. */
	public static function cookie_denied( string $name ): bool {
		$extra  = function_exists( 'apply_filters' ) ? (array) apply_filters( 'digitalisimo_elements_cookie_condition_denied', array(), $name ) : array();
		$lower  = strtolower( str_replace( array( '.', ' ' ), '_', $name ) );
		foreach ( array_merge( self::DENIED, $extra ) as $fragment ) {
			$fragment = strtolower( str_replace( array( '.', ' ' ), '_', trim( is_scalar( $fragment ) ? (string) $fragment : '' ) ) );
			if ( '' !== $fragment && false !== strpos( $lower, $fragment ) ) {
				return true;
			}
		}
		return false;
	}

	/** ¿Puede este usuario añadir o cambiar una condición de cookie? `unfiltered_html` y, en una red, superadministrador. */
	public static function can_configure_cookies(): bool {
		if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'unfiltered_html' ) ) {
			return false;
		}
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			return function_exists( 'is_super_admin' ) && is_super_admin();
		}
		return true;
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Guardado y carga.

	/**
	 * `elementor/document/save/data`: guarda de la cookie y centinela, en v3 y en v4. Un documento sin condiciones de
	 * F7a sale idéntico.
	 *
	 * @param mixed $data     `{ elements, settings }` del guardado.
	 * @param mixed $document Documento que se guarda.
	 */
	public static function on_save( $data, $document = null ) {
		if ( ! is_array( $data ) || empty( $data['elements'] ) || ! is_array( $data['elements'] ) ) {
			return $data;
		}
		$previous         = self::can_configure_cookies() ? null : self::previous( $document );
		$data['elements'] = self::walk_save( $data['elements'], $previous );
		return $data;
	}

	/**
	 * `elementor/document/load/data`: retira el centinela sólo en el modo de edición, para que la interfaz no lo
	 * muestre. El front también aplica este filtro y ahí no se toca nada.
	 *
	 * @param mixed $elements Lista de elementos.
	 * @param mixed $document Documento que se carga.
	 */
	public static function on_load( $elements, $document = null ) {
		if ( ! is_array( $elements ) || ! $elements || ! self::in_edit_mode() ) {
			return $elements;
		}
		return self::strip( $elements );
	}

	/** Lista de elementos sin el centinela (lo que ve el editor). Pública para las pruebas. */
	public static function strip( array $elements ): array {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) || self::is_marker( $element ) ) {
				continue;
			}
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				foreach ( array( Condition_Groups::V3, Condition_Groups::V4 ) as $key ) {
					if ( ! self::mentions( $element['settings'], $key, array( self::SENTINEL_PAGE ) ) ) {
						continue;
					}
					$groups = Condition_Groups::read( $element['settings'], $key );
					if ( null === $groups ) {
						continue;
					}
					$changed = false;
					$out     = array();
					foreach ( $groups as $group ) {
						$kept = array();
						foreach ( $group as $condition ) {
							if ( self::is_sentinel( $condition ) ) {
								$changed = true;
								continue;
							}
							$kept[] = $condition;
						}
						if ( $kept || count( $kept ) === count( $group ) ) {
							$out[] = $kept;
						}
					}
					if ( $changed ) {
						$element['settings'] = Condition_Groups::write( $element['settings'], $key, $out );
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::strip( $element['elements'] );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/**
	 * Guardado recursivo. `$previous` es `null` si quien guarda puede configurar cookies; si no, lo que el documento
	 * tenía guardado (`elements`: ajustes de condiciones por ID de elemento; `cookies`: firmas de sus condiciones de
	 * cookie).
	 */
	private static function walk_save( array $elements, ?array $previous ): array {
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) || self::is_marker( $element ) ) {
				continue;
			}
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$settings = $element['settings'];
				if ( null !== $previous ) {
					$settings = self::guard_cookies( $settings, isset( $element['id'] ) ? (string) $element['id'] : '', $previous );
				}
				$element['settings'] = self::with_sentinel( $settings );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::walk_save( $element['elements'], $previous );
			}
			$elements[ $index ] = $element;
		}
		return $elements;
	}

	/** Ajustes con el centinela al final de cada grupo que tiene una condición de F7a y aún no lo lleva. */
	public static function with_sentinel( array $settings ): array {
		foreach ( array( Condition_Groups::V3, Condition_Groups::V4 ) as $key ) {
			if ( ! self::mentions( $settings, $key, Display_Conditions::MORE ) ) {
				continue;
			}
			$groups = Condition_Groups::read( $settings, $key );
			if ( null === $groups ) {
				continue;
			}
			$changed = false;
			foreach ( $groups as $g => $group ) {
				$more     = false;
				$sentinel = false;
				foreach ( $group as $condition ) {
					$more     = $more || self::is_more( $condition );
					$sentinel = $sentinel || self::is_sentinel( $condition );
				}
				if ( $more && ! $sentinel ) {
					$groups[ $g ][] = self::sentinel();
					$changed        = true;
				}
			}
			if ( $changed ) {
				$settings = Condition_Groups::write( $settings, $key, $groups );
			}
		}
		return $settings;
	}

	/**
	 * Sin permiso para configurar cookies: si alguna condición de cookie del elemento no estaba ya en el documento,
	 * el elemento que existía recupera sus condiciones guardadas y uno nuevo deja esas condiciones sin nombre.
	 */
	private static function guard_cookies( array $settings, string $id, array $previous ): array {
		$foreign = false;
		foreach ( array( Condition_Groups::V3, Condition_Groups::V4 ) as $key ) {
			if ( ! self::mentions( $settings, $key, array( self::COOKIE_CONDITION ) ) ) {
				continue;
			}
			foreach ( (array) Condition_Groups::read( $settings, $key ) as $group ) {
				foreach ( $group as $condition ) {
					if ( self::is_cookie( $condition ) && ! isset( $previous['cookies'][ self::cookie_signature( $condition ) ] ) ) {
						$foreign = true;
					}
				}
			}
		}
		if ( ! $foreign ) {
			return $settings;
		}
		if ( '' !== $id && isset( $previous['elements'][ $id ] ) ) {
			foreach ( array( Condition_Groups::V3, Condition_Groups::V4 ) as $key ) {
				if ( array_key_exists( $key, $previous['elements'][ $id ] ) ) {
					$settings[ $key ] = $previous['elements'][ $id ][ $key ];
				} else {
					unset( $settings[ $key ] );
				}
			}
			return $settings;
		}
		foreach ( array( Condition_Groups::V3, Condition_Groups::V4 ) as $key ) {
			$groups = Condition_Groups::read( $settings, $key );
			if ( null === $groups ) {
				continue;
			}
			$changed = false;
			foreach ( $groups as $g => $group ) {
				foreach ( $group as $c => $condition ) {
					if ( self::is_cookie( $condition ) && ! isset( $previous['cookies'][ self::cookie_signature( $condition ) ] ) ) {
						$groups[ $g ][ $c ]['name'] = '';
						$changed                    = true;
					}
				}
			}
			if ( $changed ) {
				$settings = Condition_Groups::write( $settings, $key, $groups );
			}
		}
		return $settings;
	}

	private static function is_cookie( $condition ): bool {
		return is_array( $condition ) && isset( $condition['condition'] ) && self::COOKIE_CONDITION === $condition['condition'];
	}

	/** Firma de una condición de cookie: nombre normalizado, comparador y valor. */
	public static function cookie_signature( array $condition ): string {
		$name = str_replace( array( '.', ' ' ), '_', trim( isset( $condition['name'] ) && is_scalar( $condition['name'] ) ? (string) $condition['name'] : '' ) );
		$comp = isset( $condition['comparator'] ) && is_scalar( $condition['comparator'] ) ? (string) $condition['comparator'] : '';
		$val  = isset( $condition['value'] ) && is_scalar( $condition['value'] ) ? (string) $condition['value'] : '';
		return $name . "\0" . $comp . "\0" . $val;
	}

	/** Lo que el documento tenía guardado: el del contenido y, si se guarda un autoguardado, también el suyo. */
	private static function previous( $document ): array {
		$previous = array(
			'elements' => array(),
			'cookies'  => array(),
		);
		$ids = array();
		if ( is_object( $document ) && method_exists( $document, 'get_main_id' ) ) {
			$ids[] = (int) $document->get_main_id();
		}
		if ( is_object( $document ) && method_exists( $document, 'get_post' ) ) {
			$post = $document->get_post();
			if ( is_object( $post ) && isset( $post->ID ) ) {
				$ids[] = (int) $post->ID;
			}
		}
		foreach ( array_values( array_unique( array_filter( $ids ) ) ) as $post_id ) {
			$raw      = function_exists( 'get_post_meta' ) ? get_post_meta( $post_id, '_elementor_data', true ) : '';
			$elements = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : null );
			if ( is_array( $elements ) ) {
				self::collect_previous( $elements, $previous );
			}
		}
		return $previous;
	}

	private static function collect_previous( array $elements, array &$previous ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) || self::is_marker( $element ) ) {
				continue;
			}
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
			if ( isset( $element['id'] ) && is_scalar( $element['id'] ) ) {
				$previous['elements'][ (string) $element['id'] ] = array_intersect_key( $settings, array( Condition_Groups::V3 => 1, Condition_Groups::V4 => 1 ) );
			}
			foreach ( array( Condition_Groups::V3, Condition_Groups::V4 ) as $key ) {
				foreach ( (array) Condition_Groups::read( $settings, $key ) as $group ) {
					foreach ( $group as $condition ) {
						if ( self::is_cookie( $condition ) ) {
							$previous['cookies'][ self::cookie_signature( $condition ) ] = true;
						}
					}
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::collect_previous( $element['elements'], $previous );
			}
		}
	}

	/** ¿Aparece alguno de estos textos en el ajuste? Evita decodificar el JSON de elementos que no los tienen. */
	private static function mentions( array $settings, string $key, array $needles ): bool {
		if ( empty( $settings[ $key ] ) ) {
			return false;
		}
		$raw = $settings[ $key ];
		$raw = is_string( $raw ) ? $raw : ( Condition_Groups::V3 === $key && is_array( $raw ) && isset( $raw[0] ) && is_string( $raw[0] ) ? $raw[0] : Condition_Groups::json( $raw ) );
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $raw, (string) $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private static function is_marker( array $element ): bool {
		return isset( $element['widgetType'] ) && self::MARKER_WIDGET === $element['widgetType'];
	}

	/**
	 * ¿Se cargan los datos para el editor? El modo de edición del núcleo (pantalla del editor, `get_document_config`
	 * y acciones de plantillas) o una petición `elementor_ajax` del editor, como insertar una plantilla de la
	 * biblioteca. Nunca en el front, que también aplica `elementor/document/load/data`.
	 */
	private static function in_edit_mode(): bool {
		if ( ! function_exists( 'is_admin' ) || ! is_admin() ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide si se oculta el centinela.
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_REQUEST['action'] ) && 'elementor_ajax' === $_REQUEST['action'] ) {
			return true;
		}
		if ( ! class_exists( '\\Elementor\\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) || ! is_object( \Elementor\Plugin::$instance ) ) {
			return false;
		}
		$editor = isset( \Elementor\Plugin::$instance->editor ) ? \Elementor\Plugin::$instance->editor : null;
		return is_object( $editor ) && method_exists( $editor, 'is_edit_mode' ) && $editor->is_edit_mode();
	}

	// -----------------------------------------------------------------------------------------------------------------
	// Editor.

	/** Datos del script del editor. */
	public static function editor_data(): array {
		return array(
			'more'      => array_values( Display_Conditions::MORE ),
			'hide3'     => self::can_configure_cookies() ? array() : array( self::COOKIE_CONDITION ),
			'cacheable' => Visitor_Content::cacheable_conditions(),
			'locations' => self::SITE_LOCATIONS,
			'notice'    => self::NOTICE,
			'location'  => self::LOCATION_NOTICE,
			'visit'     => self::VISIT_NOTICE,
			'privacy'   => function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) && function_exists( 'admin_url' ) ? admin_url( self::PRIVACY_PAGE ) : '',
			'open'      => 'Abrir «Privacidad»',
		);
	}

	/**
	 * Delante del script del modal de condiciones (que comparten v3 y v4): filtra lo que se ofrece y avisa al cerrar.
	 * El control v4 abre el modal sin `conditionsConfig` y sin `rootElement`; el de v3, con `rootElement`.
	 */
	public static function editor_script(): void {
		if ( ! function_exists( 'wp_script_is' ) || ! function_exists( 'wp_add_inline_script' ) || ! wp_script_is( 'e-display-conditions', 'registered' ) ) {
			return;
		}
		wp_add_inline_script( 'e-display-conditions', 'window.digitalisimoConditions=' . Condition_Groups::json( self::editor_data() ) . ';' . self::EDITOR_JS, 'before' );
	}

	const EDITOR_JS = <<<'JS'
(function(w){var D=w.digitalisimoConditions||{},shown={};
function cfg(hide){var b=w.elementor&&w.elementor.config&&w.elementor.config.displayConditions;if(!b||!hide||!hide.length)return null;var c=b.conditions||{},g=b.groups||{},by={},flat=[];Object.keys(g).forEach(function(k){var keys=Object.keys(c).filter(function(n){return c[n]&&c[n].group===k&&hide.indexOf(n)<0;});if(keys.length){by[k]=keys;flat.push({key:k,label:g[k].label,isGroup:true});keys.forEach(function(n){flat.push({key:n,label:c[n].label,isGroup:false});});}});return Object.assign({},b,{conditionsByGroup:by,flattenedConditionOptions:flat});}
function sel(){try{var s=w.elementor.selection.getElements();return s&&s[0]||null;}catch(e){return null;}}
function atomic(c){try{return!!(c&&w.elementor.helpers.isAtomicWidget(c.model));}catch(e){return false;}}
function ids(c){var out=[];function walk(v){if(!v)return;if(typeof v==='string'){try{walk(JSON.parse(v));}catch(e){}return;}if(Array.isArray(v)){v.forEach(walk);return;}if(typeof v==='object'){if(typeof v.condition==='string'){out.push(v.condition);return;}if(v.value!==undefined)walk(v.value);}}try{walk(c.settings.get('e_display_conditions'));walk(c.settings.get('display-conditions'));}catch(e){}return out;}
w.addEventListener('elementor/display-conditions/open',function(e){var d=e.detail||{},p=d.props;if(!p||p.conditionsConfig)return;var v4=!d.rootElement||atomic(sel()),x=cfg(v4?D.more:D.hide3);if(x)p.conditionsConfig=x;},true);
w.addEventListener('elementor/display-conditions/close',function(){setTimeout(function(){var c=sel();if(!c)return;var l=ids(c),m=[],t='';try{t=w.elementor.documents.getCurrent().config.type;}catch(e){}
if(l.some(function(i){return(D.more||[]).indexOf(i)>=0;}))m.push(D.notice);
if((D.locations||[]).indexOf(t)>=0&&l.some(function(i){return(D.cacheable||[]).indexOf(i)<0;}))m.push(D.location);
var visit=l.indexOf('digitalisimo_page_visit')>=0;if(visit)m.push(D.visit);
var key=(c.id||'')+'|'+m.join('|');if(!m.length||shown[key])return;shown[key]=1;var o={message:m.join(' ')};if(visit&&D.privacy)o.buttons=[{name:'digitalisimo_privacy',text:D.open,callback:function(){w.open(D.privacy,'_blank');}}];try{w.elementor.notifications.showToast(o);}catch(e){}},50);});
})(window);
JS;
}
