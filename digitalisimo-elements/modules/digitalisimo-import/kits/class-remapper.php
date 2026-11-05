<?php
namespace Digitalisimo\Elements\Import\Kits;

use Digitalisimo\Elements\Import\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Remapeo de IDs y URL del kit (§9): traduce una sola vez cada documento creado por la sesión.
 *
 * No es un motor de render ni de condiciones: recorre lo que declara el registro (`References`) y los controles URL
 * de cada elemento, y cambia cada valor con los mapas de `Import_Context` (§9.1). Cada valor se traduce con una sola
 * búsqueda: un mapa solapado (`{5→12, 12→20}`) da 12, nunca 20, y un documento sólo se procesa una vez (CR-16).
 *
 * Regla de cierre (§5.5): una referencia sin destino nunca amplía quién ve algo.
 * - En una lista se quita sólo lo que no resuelve; un valor único se vacía.
 * - Si la lista queda vacía, o el valor único se vacía, y vacío significa «cualquiera» en ese control, se pone el
 *   valor de cierre que declara la referencia (`never`).
 * - Si la referencia decide quién ve algo (`gate`), el documento queda marcado para «revisar antes de publicar»
 *   (`Import_Context::flag_review()`): el runner final lo deja en borrador en los dos modos.
 * - Un ID nunca se deja sin traducir: en el sitio de destino apuntaría a otro contenido.
 *
 * Además reescribe `__globals__` con los mapas de colores y tipografías (§9.4) y, dentro de los marcadores de
 * elementos desconocidos, aplica también el remapeo nativo de cada elemento que sí existe (CV-19): el núcleo no
 * entra en un elemento que no puede instanciar.
 */
final class Remapper {
	const GLOBALS_KEY  = '__globals__';
	const MARKER_KEY   = 'digitalisimo_unconverted';
	const SESSION_META = '_elementor_import_session_id';
	const LIBRARY_TYPE = 'elementor_library';
	const REVIEW       = 'gate-unresolved';

	/** Carpeta de los archivos propios dentro de `uploads` (separadores y máscaras). */
	const FILES_FOLDER = 'digitalisimo-elements/';

	/** Prefijo de los separadores propios: el mapa `shapes` sólo traduce estos valores. */
	const SHAPE_PREFIX = 'digi-';

	/** Tipos de referencia que guardan IDs (o el slug de un menú) y siguen la regla de cierre. */
	const ID_KINDS = array( 'post', 'template', 'term', 'menu', 'menu_slug', 'user', 'attachment', 'element' );

	/** `templateID` de una instancia de widget global: está en el elemento, no en sus ajustes (ningún registro la cubre). */
	const GLOBAL_WIDGET_REF = array(
		'scope' => 'element',
		'type'  => 'global',
		'path'  => 'templateID',
		'kind'  => 'template',
		'gate'  => false,
		'never' => null,
	);

	/** Patrón de un tamaño intermedio de imagen: `foto-300x200.jpg`. */
	const SIZE_PATTERN = '/^(.+)-\d+x\d+(\.[A-Za-z0-9]+)$/';

	/** @var Import_Context */
	private $context;

	/** @var string[] `home`, `site` y `uploads` del sitio de destino. */
	private $target;

	/** @var callable[] */
	private $callbacks;

	/** @var array[]|null Pares `[ prefijo viejo, prefijo nuevo ]`, del más largo al más corto. */
	private $prefixes = null;

	/** @var string[]|null URL comparable vieja => URL nueva (medios). */
	private $media = null;

	/** @var self[] Remapeador de cada contexto (`spl_object_id`). */
	private static $instances = array();

	/** @var true[] Documentos ya remapeados en esta petición. */
	private $done = array();

	/** @var int Documento en curso (0 fuera de un documento). */
	private $post_id = 0;

	/** @var string Elemento en curso, para el informe. */
	private $element_id = '';

	/** @var string Tipo del elemento en curso. */
	private $element_type = '';

	/** @var true[] IDs de elemento del documento en curso (D20). */
	private $element_ids = array();

	/** @var true[] Avisos `note:*` ya emitidos por documento, elemento y código. */
	private $noted = array();

	/**
	 * @param Import_Context $context Contexto de la petición final: mapas, origen y avisos.
	 * @param array          $options {
	 *     Destino y búsquedas; por defecto, las de WordPress y del núcleo. Las pruebas las sustituyen.
	 *
	 *     @type string   $home               `home_url()`.
	 *     @type string   $site               `site_url()`.
	 *     @type string   $uploads            `wp_upload_dir()['baseurl']`.
	 *     @type callable $attachment_by_url  `( string $url ): int` adjunto importado de esa URL (`_elementor_source_image_hash`).
	 *     @type callable $attachment_url     `( int $id ): string`.
	 *     @type callable $post_type          `( int $id ): string`.
	 *     @type callable $permalink          `( int $id ): string`.
	 *     @type callable $exists             `( string $kind, int $id ): bool` (`user` o `post`).
	 *     @type callable $session_of         `( int $post_id ): string` sesión que creó el documento.
	 *     @type callable $known              `( array $element ): bool` ¿se puede instanciar?
	 *     @type callable $native             `( array $elements, array $replacements ): array` remapeo nativo.
	 *     @type callable $taxonomy_exists    `( string $name ): bool`.
	 *     @type callable $post_type_exists   `( string $name ): bool`.
	 * }
	 */
	public function __construct( Import_Context $context, array $options = array() ) {
		$this->context = $context;
		$uploads       = isset( $options['uploads'] ) ? (string) $options['uploads'] : self::uploads_url();
		$this->target  = array(
			'home'    => isset( $options['home'] ) ? (string) $options['home'] : ( function_exists( 'home_url' ) ? (string) home_url() : '' ),
			'site'    => isset( $options['site'] ) ? (string) $options['site'] : ( function_exists( 'site_url' ) ? (string) site_url() : '' ),
			'uploads' => $uploads,
		);
		$defaults        = array(
			'attachment_by_url' => array( __CLASS__, 'find_attachment_by_url' ),
			'attachment_url'    => function ( $id ) {
				$url = function_exists( 'wp_get_attachment_url' ) ? wp_get_attachment_url( $id ) : '';
				return is_string( $url ) ? $url : '';
			},
			'post_type'         => function ( $id ) {
				$type = function_exists( 'get_post_type' ) ? get_post_type( $id ) : '';
				return is_string( $type ) ? $type : '';
			},
			'permalink'         => function ( $id ) {
				$link = function_exists( 'get_permalink' ) ? get_permalink( $id ) : '';
				return is_string( $link ) ? $link : '';
			},
			'exists'            => function ( $kind, $id ) {
				if ( 'user' === $kind ) {
					return function_exists( 'get_userdata' ) && false !== get_userdata( $id );
				}
				return function_exists( 'get_post' ) && null !== get_post( $id );
			},
			'session_of'        => function ( $id ) {
				return function_exists( 'get_post_meta' ) ? (string) get_post_meta( $id, self::SESSION_META, true ) : '';
			},
			'known'             => array( __CLASS__, 'is_known_element' ),
			'native'            => array( __CLASS__, 'native_remap' ),
			'taxonomy_exists'   => function ( $name ) {
				return function_exists( 'taxonomy_exists' ) && taxonomy_exists( $name );
			},
			'post_type_exists'  => function ( $name ) {
				return function_exists( 'post_type_exists' ) && post_type_exists( $name );
			},
		);
		$this->callbacks = array();
		foreach ( $defaults as $name => $default ) {
			$this->callbacks[ $name ] = isset( $options[ $name ] ) && is_callable( $options[ $name ] ) ? $options[ $name ] : $default;
		}
	}

	// ── Documentos ──────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Remapea los datos de un documento que se va a guardar (`{ elements, settings }`), una sola vez por documento y
	 * contexto. Es la entrada de `Kits\Context::remap()` en `elementor/document/save/data` (prioridad 20, §5.4),
	 * que ya comprueba que el documento es de la sesión.
	 *
	 * @param array          $data    `{ elements, settings }`.
	 * @param Import_Context $context Contexto de la petición final.
	 * @param int            $post_id Documento (0: sin control de «una sola vez»).
	 */
	public static function apply( array $data, Import_Context $context, int $post_id = 0 ): array {
		$remapper = self::for_context( $context );
		if ( $post_id > 0 ) {
			if ( isset( $remapper->done[ $post_id ] ) ) {
				return $data;
			}
			$remapper->done[ $post_id ] = true;
		}
		if ( isset( $data['elements'] ) && is_array( $data['elements'] ) ) {
			$data['elements'] = $remapper->apply_elements( $data['elements'], $post_id );
		}
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$data['settings'] = $remapper->apply_settings( $data['settings'], '*', $post_id );
		}
		return $data;
	}

	/** Un remapeador por contexto (la memoria de «una sola vez» y de medios vive en él). */
	public static function for_context( Import_Context $context, array $options = array() ): self {
		$id = spl_object_id( $context );
		if ( ! isset( self::$instances[ $id ] ) || self::$instances[ $id ]->context !== $context ) {
			self::$instances[ $id ] = new self( $context, $options );
		}
		return self::$instances[ $id ];
	}

	/**
	 * Olvida los remapeadores por contexto.
	 *
	 * @internal Sólo para pruebas.
	 */
	public static function reset(): void {
		self::$instances = array();
	}

	/**
	 * Callback alternativo de `elementor/document/save/data` (prioridad 20) que hace él mismo las comprobaciones:
	 * sólo documentos creados por la sesión (`_elementor_import_session_id` y un ID posterior a `max_post_id`) y una
	 * sola vez por documento. Un segundo guardado, o un documento ajeno, no cambia (CV-12).
	 *
	 * @param array $data     `{ elements, settings }` que se van a guardar.
	 * @param mixed $document Documento del núcleo (`get_main_id()` y `get_name()`).
	 */
	public function save_data( $data, $document ) {
		if ( ! is_array( $data ) || ! is_object( $document ) || ! method_exists( $document, 'get_main_id' ) ) {
			return $data;
		}
		$id = (int) $document->get_main_id();
		if ( $id <= 0 || isset( $this->done[ $id ] ) || ! $this->belongs( $id ) ) {
			return $data;
		}
		$this->done[ $id ] = true;
		$type              = method_exists( $document, 'get_name' ) ? (string) $document->get_name() : '*';
		if ( isset( $data['elements'] ) && is_array( $data['elements'] ) ) {
			$data['elements'] = $this->apply_elements( $data['elements'], $id );
		}
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$data['settings'] = $this->apply_settings( $data['settings'], '' !== $type ? $type : '*', $id );
		}
		return $data;
	}

	/** ¿Lo creó esta sesión? */
	public function belongs( int $post_id ): bool {
		return $this->context->is_new_post( $post_id )
			&& (string) call_user_func( $this->callbacks['session_of'], $post_id ) === $this->context->session();
	}

	/** ¿Ya se remapeó ese documento en esta petición? */
	public function is_done( int $post_id ): bool {
		return isset( $this->done[ $post_id ] );
	}

	/**
	 * Remapea un árbol de elementos. Función pura respecto del sitio: sólo lee los mapas y escribe avisos.
	 *
	 * @param array $elements Árbol de `_elementor_data`.
	 * @param int   $post_id  Documento al que pertenece (0 si no es de un documento, como los valores por defecto).
	 */
	public function apply_elements( array $elements, int $post_id = 0 ): array {
		$previous          = array( $this->post_id, $this->element_ids );
		$this->post_id     = $post_id;
		$this->element_ids = array();
		$this->collect_ids( $elements );
		$elements = $this->walk( $elements );
		list( $this->post_id, $this->element_ids ) = $previous;
		return $elements;
	}

	/**
	 * Remapea los ajustes de un documento (`scope = document`) y sus `__globals__`.
	 *
	 * @param array  $settings Ajustes de página.
	 * @param string $type     Tipo de documento (`get_name()`), o `*`.
	 * @param int    $post_id  Documento.
	 */
	public function apply_settings( array $settings, string $type = '*', int $post_id = 0 ): array {
		$previous      = $this->post_id;
		$this->post_id = $post_id;
		$this->element_id   = '';
		$this->element_type = $type;
		foreach ( References::matching( 'document', $type ) as $ref ) {
			$settings = $this->apply_ref( $settings, $ref );
		}
		$settings      = $this->globals( $settings );
		$this->post_id = $previous;
		return $settings;
	}

	/**
	 * Remapea el valor de una meta (`scope = post_meta` o `term_meta`) con las referencias declaradas para su clave.
	 *
	 * @param string $scope     `post_meta` o `term_meta`.
	 * @param string $key       Clave de la meta.
	 * @param mixed  $value     Valor (ya sin serializar).
	 * @param int    $object_id Entrada o término, para el informe.
	 *
	 * @return mixed
	 */
	public function apply_meta( string $scope, string $key, $value, int $object_id = 0 ) {
		if ( ! in_array( $scope, References::META_SCOPES, true ) ) {
			return $value;
		}
		$previous           = $this->post_id;
		$this->post_id      = 'post_meta' === $scope ? $object_id : 0;
		$this->element_id   = '';
		$this->element_type = $key;
		foreach ( References::matching( $scope, $key ) as $ref ) {
			if ( '' === $ref['path'] ) {
				$value = $this->translate( $ref, $value, is_array( $value ) && Part_Context::is_list( $value ) );
			} elseif ( is_array( $value ) ) {
				$value = $this->apply_ref( $value, $ref );
			}
		}
		$this->post_id = $previous;
		return $value;
	}

	/**
	 * Sólo los `__globals__` de unos ajustes (valores por defecto de elementos, B1).
	 *
	 * @param array $settings Ajustes de un elemento.
	 */
	public function rewrite_globals( array $settings ): array {
		return $this->globals( $settings );
	}

	/**
	 * `__globals__` con los mapas dados: `globals/colors?id=<viejo>` → `globals/colors?id=<nuevo>`. Función pura.
	 *
	 * Recorre los repetidores, pero no la carga de un marcador, que se remapea como su propio árbol.
	 *
	 * @param array    $settings   Ajustes.
	 * @param string[] $colors     `_id` viejo => nuevo.
	 * @param string[] $typography `_id` viejo => nuevo.
	 */
	public static function globals_in( array $settings, array $colors, array $typography ): array {
		if ( ! $colors && ! $typography ) {
			return $settings;
		}
		foreach ( $settings as $key => $value ) {
			if ( self::MARKER_KEY === $key || ! is_array( $value ) ) {
				continue;
			}
			if ( self::GLOBALS_KEY === $key ) {
				foreach ( $value as $control => $global ) {
					if ( is_string( $global ) ) {
						$value[ $control ] = self::global_value( $global, $colors, $typography );
					}
				}
				$settings[ $key ] = $value;
				continue;
			}
			$settings[ $key ] = self::globals_in( $value, $colors, $typography );
		}
		return $settings;
	}

	// ── URL y CSS (§9.3) ────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * URL con el prefijo del origen (`uploads`, `site` o `home`) pasada al destino, con su ruta, consulta y
	 * fragmento. Lo que no empieza por un prefijo del origen no cambia.
	 */
	public function url( string $url ): string {
		foreach ( $this->prefixes() as $pair ) {
			$rest = self::after_prefix( $url, $pair[0] );
			if ( null !== $rest ) {
				return rtrim( $pair[1], '/' ) . $rest;
			}
		}
		return $url;
	}

	/**
	 * URL de un archivo bajo `uploads` del origen (`media-url`, D6): la del adjunto importado, o la del destino de un
	 * separador o una máscara. Si el archivo no vino en el paquete, se deja y se anota.
	 */
	public function media_url( string $url ): string {
		$new = $this->find_media( $url );
		if ( null !== $new ) {
			return $new;
		}
		$this->missing( $url );
		return $url;
	}

	/**
	 * Cada `url(…)` de un CSS (§9.3): un medio importado pasa a su URL nueva; un archivo propio de
	 * `uploads/digitalisimo-elements/`, a la de su destino; un tamaño intermedio de una imagen importada, a la URL
	 * completa (con aviso). Lo demás se deja y, si es del origen, se anota.
	 */
	public function css( string $css ): string {
		if ( false === stripos( $css, 'url(' ) ) {
			return $css;
		}
		return (string) preg_replace_callback(
			'/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i',
			function ( $match ) {
				$url = trim( $match[2] );
				if ( '' === $url || 0 === stripos( $url, 'data:' ) || null === self::split_url( $url ) ) {
					return $match[0];
				}
				$new = $this->find_media( $url );
				if ( null !== $new ) {
					return 'url(' . $match[1] . $new . $match[1] . ')';
				}
				if ( $this->is_origin( $url ) ) {
					$this->report( 'url.left', array( 'url' => $url ) );
				}
				return $match[0];
			},
			$css
		);
	}

	/**
	 * Enlaces de un texto enriquecido (`kind = html`, controles WYSIWYG): cada `href` y `src` con el prefijo del
	 * origen pasa al destino, como el WXR hace en `post_content` (§9.3); un archivo bajo `uploads` del origen, a su
	 * adjunto importado. El resto del HTML no se toca (kses ya actuó al guardar).
	 */
	public function html( string $html ): string {
		if ( false === stripos( $html, 'href' ) && false === stripos( $html, 'src' ) ) {
			return $html;
		}
		$uploads = $this->context->origin( 'uploads' );
		return (string) preg_replace_callback(
			'/\b(href|src)\s*=\s*(["\'])([^"\'<>]+)\2/i',
			function ( $match ) use ( $uploads ) {
				$url = trim( $match[3] );
				if ( null === self::split_url( $url ) || ! $this->is_origin( $url ) ) {
					return $match[0];
				}
				$new = '' !== $uploads && References::under( $url, $uploads ) ? $this->find_media( $url ) : null;
				$new = null !== $new ? $new : $this->url( $url );
				return $match[1] . '=' . $match[2] . $new . $match[2];
			},
			$html
		);
	}

	/**
	 * Valor de un control de medios (`{ id, url }`) o de una galería (lista de ellos), `kind = media`: si aún apunta
	 * al origen (el núcleo no lo importó, como en un repetidor), pasa al adjunto importado (por su URL y, si no, por
	 * su ID de origen) y a su URL. Lo que ya es de este sitio no cambia: así no se traduce dos veces un ID nuevo.
	 *
	 * @param mixed $value Valor del control.
	 * @param bool  $list  La ruta terminaba en `*`: es una lista.
	 *
	 * @return mixed
	 */
	private function media_value( $value, bool $list ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( $list || Part_Context::is_list( $value ) ) {
			foreach ( $value as $index => $item ) {
				if ( is_array( $item ) ) {
					$value[ $index ] = $this->media_value( $item, false );
				}
			}
			return $value;
		}
		$url = isset( $value['url'] ) && is_string( $value['url'] ) ? $value['url'] : '';
		if ( '' === $url || null === self::split_url( $url ) || ! $this->is_origin( $url ) ) {
			return $value;
		}
		$id = $this->context->mapped( 'attachment', self::strip_query( $url ) );
		if ( null === $id ) {
			$id = (int) call_user_func( $this->callbacks['attachment_by_url'], self::strip_query( $url ) );
		}
		if ( (int) $id <= 0 && isset( $value['id'] ) && ( is_int( $value['id'] ) || ( is_string( $value['id'] ) && ctype_digit( $value['id'] ) ) ) ) {
			$id = $this->context->mapped( 'attachment', (int) $value['id'] );
		}
		$new_url = $this->find_media( $url );
		if ( (int) $id > 0 ) {
			$value['id'] = isset( $value['id'] ) ? self::same_type( $value['id'], (int) $id ) : (int) $id;
			if ( null === $new_url ) {
				$attached = (string) call_user_func( $this->callbacks['attachment_url'], (int) $id );
				$new_url  = '' !== $attached ? $attached : null;
			}
		}
		if ( null !== $new_url ) {
			$value['url'] = $new_url;
		}
		if ( (int) $id <= 0 && null === $new_url ) {
			$this->missing( $url );
		}
		return $value;
	}

	// ── Theme Builder y Custom Code (§9.5) ──────────────────────────────────────────────────────────────────────

	/**
	 * Traduce condiciones del Theme Builder o de Custom Code (`include/singular/page/12` o `{ type, name,
	 * sub_name, sub_id }`), cerradas: si alguna queda sin destino, `conditions` es una lista vacía (la plantilla o el
	 * fragmento no se aplica en ninguna parte) y `unresolved` conserva las originales para el informe.
	 *
	 * Los `sub_id` se traducen según `sub_name`: por autor (`by_author`, `*_by_author`, `author`) son usuarios y
	 * nunca tienen destino; `child_of` y `any_child_of` son entradas; `in_*`, `in_*_children`, `child_of_*` y
	 * `any_child_of_*` son términos (CV-33); un nombre de taxonomía es un término y uno de tipo de entrada, una
	 * entrada. Un `sub_name` desconocido con `sub_id` no se puede traducir y cierra.
	 *
	 * @return array{conditions: array[], translated: array[], unresolved: string[]}
	 */
	public function theme_conditions( array $conditions ): array {
		$out        = array();
		$unresolved = array();
		foreach ( $conditions as $condition ) {
			$parts = self::parse_condition( $condition );
			if ( null === $parts ) {
				$unresolved[] = is_string( $condition ) ? $condition : Module::describe( $condition );
				continue;
			}
			if ( '' === $parts['sub_id'] ) {
				$out[] = $parts;
				continue;
			}
			$kind = $this->condition_kind( $parts['name'], $parts['sub_name'] );
			$new  = null;
			if ( 'post' === $kind || 'term' === $kind ) {
				$new = $this->map_id( $kind, $parts['sub_id'] );
			}
			if ( null === $new || '' === (string) $new ) {
				$unresolved[] = self::condition_string( $parts );
				continue;
			}
			$parts['sub_id'] = (string) $new;
			$out[]           = $parts;
		}
		return array(
			'conditions' => $unresolved ? array() : $out,
			'translated' => $out,
			'unresolved' => $unresolved,
		);
	}

	/** `{ type, name, sub_name, sub_id }` (en ese orden, como lo une `save_conditions()`), o null. */
	public static function parse_condition( $condition ): ?array {
		if ( is_string( $condition ) ) {
			$condition = array_pad( explode( '/', $condition, 4 ), 4, '' );
			$condition = array_combine( array( 'type', 'name', 'sub_name', 'sub_id' ), $condition );
		}
		if ( ! is_array( $condition ) ) {
			return null;
		}
		if ( array_key_exists( 0, $condition ) ) {
			$condition = array(
				'type'     => isset( $condition[0] ) ? $condition[0] : '',
				'name'     => isset( $condition[1] ) ? $condition[1] : '',
				'sub_name' => isset( $condition[2] ) ? $condition[2] : '',
				'sub_id'   => isset( $condition[3] ) ? $condition[3] : '',
			);
		}
		$parts = array();
		foreach ( array( 'type', 'name', 'sub_name', 'sub_id' ) as $key ) {
			$value = isset( $condition[ $key ] ) ? $condition[ $key ] : '';
			if ( ! is_scalar( $value ) ) {
				return null;
			}
			$parts[ $key ] = trim( (string) $value );
		}
		if ( ! in_array( $parts['type'], array( 'include', 'exclude' ), true ) || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $parts['name'] ) ) {
			return null;
		}
		if ( '' !== $parts['sub_name'] && 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $parts['sub_name'] ) ) {
			return null;
		}
		return $parts;
	}

	/** `include/singular/page/12`, sin barras finales vacías. */
	public static function condition_string( array $parts ): string {
		return rtrim( implode( '/', array( $parts['type'], $parts['name'], $parts['sub_name'], $parts['sub_id'] ) ), '/' );
	}

	// ── Valores ─────────────────────────────────────────────────────────────────────────────────────────────────

	/**
	 * Traduce el valor de una referencia ya normalizada.
	 *
	 * @param array $ref   Referencia (`References::normalize()`).
	 * @param mixed $value Valor encontrado en la ruta.
	 * @param bool  $list  La ruta terminaba en `*`: el valor es una lista.
	 *
	 * @return mixed El valor nuevo; si no tiene destino, el que fija la regla de cierre.
	 */
	public function translate( array $ref, $value, bool $list = false ) {
		$ref  = array_replace(
			array(
				'kind'  => '',
				'gate'  => false,
				'never' => null,
				'path'  => '',
			),
			$ref
		);
		$kind = References::kind_for( $ref, $value, $this->context->origin( 'uploads' ) );
		if ( in_array( $kind, self::ID_KINDS, true ) ) {
			return $this->id_value( $ref, $value, $list );
		}
		if ( '' !== References::note_code( $kind ) ) {
			if ( ! self::is_blank( $value ) ) {
				$this->note( References::note_code( $kind ), $ref );
			}
			return $value;
		}
		if ( '' !== References::map_name( $kind ) ) {
			return $this->map_value( $ref, References::map_name( $kind ), $value );
		}
		switch ( $kind ) {
			case 'url':
				return is_string( $value ) && '' !== $value ? $this->url_value( $value ) : $value;
			case 'media-url':
			case 'file':
				return is_string( $value ) && '' !== $value ? $this->media_url( $value ) : $value;
			case 'css':
				return is_string( $value ) ? $this->css( $value ) : $value;
			case 'html':
				return is_string( $value ) ? $this->html( $value ) : $value;
			case 'media':
				return $this->media_value( $value, $list );
			case 'conditions':
				return $this->conditions_value( $value );
		}
		Module::doing_it_wrong( __METHOD__, 'Tipo de referencia sin traducción: ' . Module::describe( $kind ) );
		if ( self::is_blank( $value ) ) {
			return $value;
		}
		$this->unresolved( $ref, array( $value ) );
		return null !== $ref['never'] ? $ref['never'] : '';
	}

	/**
	 * ID nuevo de un ID viejo, o null si no tiene destino.
	 *
	 * @param string     $kind `post`, `template`, `term`, `menu`, `menu_slug`, `attachment`, `element` o `user`.
	 * @param int|string $old  Valor viejo.
	 *
	 * @return int|string|null
	 */
	public function map_id( string $kind, $old ) {
		if ( is_float( $old ) && floor( $old ) === $old ) {
			$old = (int) $old;
		}
		if ( ! is_int( $old ) && ! is_string( $old ) ) {
			return null;
		}
		$c = $this->context;
		switch ( $kind ) {
			case 'post':
				return $c->mapped( 'post', $old, $c->mapped( 'template', $old ) );
			case 'template':
				$new = $c->mapped( 'template', $old );
				if ( null === $new ) {
					$new = $c->mapped( 'post', $old );
					if ( null !== $new && self::LIBRARY_TYPE !== call_user_func( $this->callbacks['post_type'], (int) $new ) ) {
						return null;
					}
				}
				return $new;
			case 'term':
				return $c->mapped( 'term', $old );
			case 'menu':
				return $c->mapped( 'menu', $old, $c->mapped( 'term', $old ) );
			case 'menu_slug':
				return $c->mapped( 'menu_slug', $old );
			case 'attachment':
				return $c->mapped( 'attachment', $old, $c->mapped( 'post', $old ) );
			case 'element':
				return is_string( $old ) && isset( $this->element_ids[ $old ] ) ? $old : null;
		}
		return null;
	}

	// ── Recorrido ───────────────────────────────────────────────────────────────────────────────────────────────

	private function walk( array $elements ): array {
		foreach ( $elements as $index => $element ) {
			if ( is_array( $element ) ) {
				$elements[ $index ] = $this->element( $element );
			}
		}
		return $elements;
	}

	private function element( array $element ): array {
		$type     = self::type_of( $element );
		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : null;
		if ( isset( $element['templateID'] ) && ! self::is_blank( $element['templateID'] ) ) {
			// Instancia de un widget global: su plantilla va fuera de los ajustes y el núcleo no la traduce. Sin
			// destino queda vacía, así que el widget no pinta nada (no hay quien vea más que en el origen).
			$this->element_id      = isset( $element['id'] ) && is_scalar( $element['id'] ) ? (string) $element['id'] : '';
			$this->element_type    = $type;
			$element['templateID'] = $this->translate( self::GLOBAL_WIDGET_REF, $element['templateID'] );
		}
		if ( null !== $settings ) {
			if ( Module::WIDGET === $type && isset( $settings[ self::MARKER_KEY ]['element'] ) && is_array( $settings[ self::MARKER_KEY ]['element'] ) ) {
				// La carga del marcador: primero el remapeo nativo de lo que existe, después el registro (CV-19).
				$payload = $this->native_walk( array( $settings[ self::MARKER_KEY ]['element'] ) );
				$payload = $this->element( $payload[0] );
				$settings[ self::MARKER_KEY ]['element'] = $payload;
			}
			$this->element_id   = isset( $element['id'] ) && is_scalar( $element['id'] ) ? (string) $element['id'] : '';
			$this->element_type = $type;
			foreach ( References::matching( 'element', '' !== $type ? $type : '*' ) as $ref ) {
				$settings = $this->apply_ref( $settings, $ref );
			}
			$settings            = $this->shaped_values( $settings, 0 );
			$element['settings'] = $this->globals( $settings );
		}
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$element['elements'] = $this->walk( $element['elements'] );
		}
		return $element;
	}

	/**
	 * Respaldo por la forma del valor para lo que el registro no cubre: los ajustes de un alias de widget fusionado
	 * conservan nombres de controles que su clase ya no declara, y un widget de un plugin ausente no tiene controles.
	 * Un `{ url, is_external | nofollow }` es un control URL y un `{ id, url }` un control de medios: si todavía
	 * apuntan al origen, pasan al destino con las mismas reglas (`url`, `media`). Lo ya traducido no cambia.
	 *
	 * @param array $node  Ajustes (o una parte).
	 * @param int   $depth Profundidad (los repetidores anidados no pasan de unos pocos niveles).
	 */
	private function shaped_values( array $node, int $depth ): array {
		if ( $depth > 8 ) {
			return $node;
		}
		if ( isset( $node['url'] ) && is_string( $node['url'] ) && '' !== $node['url'] && null !== self::split_url( $node['url'] ) && $this->is_origin( $node['url'] ) ) {
			$uploads = $this->context->origin( 'uploads' );
			if ( array_key_exists( 'id', $node ) && ! array_key_exists( 'is_external', $node ) && '' !== $uploads && References::under( $node['url'], $uploads ) ) {
				return $this->media_value( $node, false );
			}
			// Un enlace (`{ url }`, con o sin `is_external`/`nofollow`, como lo guarda el editor) o un `{ id, url }` que
			// no es un archivo: sólo la URL, con la regla de `url` (§9.3; un archivo de `uploads`, `media-url`).
			$node['url'] = $this->translate(
				array(
					'kind' => 'url',
					'path' => 'url',
				),
				$node['url']
			);
			return $node;
		}
		foreach ( $node as $key => $value ) {
			if ( is_array( $value ) && ! in_array( $key, array( self::GLOBALS_KEY, '__dynamic__', self::MARKER_KEY ), true ) ) {
				$node[ $key ] = $this->shaped_values( $value, $depth + 1 );
			}
		}
		return $node;
	}

	/** Aplica una referencia a unos ajustes, siguiendo su ruta. */
	private function apply_ref( array $settings, array $ref ): array {
		$segments = References::split_path( $ref['path'] );
		$list     = false;
		if ( $segments && '*' === end( $segments ) ) {
			array_pop( $segments );
			$list = true;
		}
		if ( ! $segments ) {
			return $settings;
		}
		return $this->walk_path( $settings, $segments, $ref, $list );
	}

	private function walk_path( array $node, array $segments, array $ref, bool $list ): array {
		$key = array_shift( $segments );
		if ( '*' === $key ) {
			foreach ( $node as $index => $item ) {
				if ( is_array( $item ) ) {
					$node[ $index ] = $segments ? $this->walk_path( $item, $segments, $ref, $list ) : $item;
				}
			}
			return $node;
		}
		if ( ! array_key_exists( $key, $node ) ) {
			return $node;
		}
		if ( $segments ) {
			if ( is_array( $node[ $key ] ) ) {
				$node[ $key ] = $this->walk_path( $node[ $key ], $segments, $ref, $list );
			}
			return $node;
		}
		if ( self::relevant( (string) $key, $node ) ) {
			$node[ $key ] = $this->translate( $ref, $node[ $key ], $list );
		}
		return $node;
	}

	/**
	 * En una fila de reglas de visibilidad, sólo cuenta el valor de la regla elegida: `rule_<clave>_value` con
	 * `rule_key = <clave>`, y `rule_custom_page_id` con la página especial. Lo demás son restos del editor.
	 */
	private static function relevant( string $key, array $row ): bool {
		if ( ! isset( $row['rule_key'] ) || ! is_string( $row['rule_key'] ) ) {
			return true;
		}
		if ( 'rule_custom_page_id' === $key ) {
			// Sin valor, la página especial es la de por defecto («home»): el ID no se usa.
			return 'static_page' === $row['rule_key'] && isset( $row['rule_static_page_value'] ) && 'custom' === $row['rule_static_page_value'];
		}
		if ( 1 === preg_match( '/^rule_(.+)_value$/', $key, $match ) ) {
			return $match[1] === $row['rule_key'];
		}
		return true;
	}

	/** Remapeo nativo nodo a nodo, sin repetir el de los hijos (cada nodo se traduce una vez). */
	private function native_walk( array $nodes ): array {
		$replacements = array(
			'post_ids' => $this->context->map( 'post' ) + $this->context->map( 'template' ),
			'term_ids' => $this->context->map( 'term' ),
		);
		return $this->native_nodes( $nodes, $replacements );
	}

	private function native_nodes( array $nodes, array $replacements ): array {
		foreach ( $nodes as $index => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$children         = isset( $node['elements'] ) && is_array( $node['elements'] ) ? $node['elements'] : array();
			$node['elements'] = array();
			if ( call_user_func( $this->callbacks['known'], $node ) ) {
				$done = call_user_func( $this->callbacks['native'], array( $node ), $replacements );
				if ( is_array( $done ) && isset( $done[0] ) && is_array( $done[0] ) ) {
					$node = $done[0];
				}
			}
			$node['elements'] = $this->native_nodes( $children, $replacements );
			$nodes[ $index ]  = $node;
		}
		return $nodes;
	}

	private function collect_ids( array $elements ): void {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['id'] ) && is_scalar( $element['id'] ) && '' !== (string) $element['id'] ) {
				$this->element_ids[ (string) $element['id'] ] = true;
			}
			if ( isset( $element['settings'][ self::MARKER_KEY ]['element'] ) && is_array( $element['settings'][ self::MARKER_KEY ]['element'] ) ) {
				$this->collect_ids( array( $element['settings'][ self::MARKER_KEY ]['element'] ) );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->collect_ids( $element['elements'] );
			}
		}
	}

	// ── Tipos de valor ──────────────────────────────────────────────────────────────────────────────────────────

	/** IDs: un valor, una lista, `{ id, label }` o `1,2,3`. */
	private function id_value( array $ref, $value, bool $list ) {
		if ( self::is_blank( $value ) ) {
			return $value;
		}
		$kind = (string) $ref['kind'];
		if ( is_string( $value ) && 1 === preg_match( '/^\s*\d+\s*(?:,\s*\d+\s*)+$/', $value ) ) {
			$items = array_map( 'trim', explode( ',', $value ) );
			$out   = $this->id_list( $ref, $items );
			return implode( ',', array_map( 'strval', $out ) );
		}
		if ( is_array( $value ) && ( $list || Part_Context::is_list( $value ) ) ) {
			return $this->id_list( $ref, $value );
		}
		if ( is_array( $value ) ) {
			if ( ! array_key_exists( 'id', $value ) || self::is_blank( $value['id'] ) ) {
				return $value;
			}
			$new = $this->map_id( $kind, $value['id'] );
			if ( null !== $new ) {
				$value['id'] = self::same_type( $value['id'], $new );
				return $value;
			}
			$this->unresolved( $ref, array( $value['id'] ) );
			$value['id'] = null !== $ref['never'] ? $this->never( $ref ) : '';
			return $value;
		}
		$new = $this->map_id( $kind, $value );
		if ( null !== $new ) {
			return self::same_type( $value, $new );
		}
		$this->unresolved( $ref, array( $value ) );
		return null !== $ref['never'] ? $this->never( $ref ) : '';
	}

	/** Una lista de IDs: se quita sólo lo que no resuelve (regla de cierre). */
	private function id_list( array $ref, array $items ): array {
		$out     = array();
		$removed = array();
		$sample  = null;
		foreach ( $items as $item ) {
			$old = is_array( $item ) ? ( isset( $item['id'] ) ? $item['id'] : null ) : $item;
			if ( self::is_blank( $old ) ) {
				continue;
			}
			$sample = null === $sample ? $item : $sample;
			$new    = $this->map_id( (string) $ref['kind'], $old );
			if ( null === $new ) {
				$removed[] = $old;
				continue;
			}
			$out[] = is_array( $item ) ? array_replace( $item, array( 'id' => self::same_type( $old, $new ) ) ) : self::same_type( $old, $new );
		}
		if ( $removed ) {
			$this->unresolved( $ref, $removed );
			if ( ! $out && null !== $ref['never'] ) {
				$never = $this->never( $ref );
				$out   = array( is_array( $sample ) ? array( 'id' => $never ) : $never );
			}
		}
		return $out;
	}

	/** `map:<nombre>`: lo que no está en el mapa no cambia (el mapa sólo cubre lo que gestiona K). */
	private function map_value( array $ref, string $name, $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $index => $item ) {
				if ( is_int( $item ) || is_string( $item ) ) {
					$value[ $index ] = $this->map_value( $ref, $name, $item );
				}
			}
			return $value;
		}
		if ( self::is_blank( $value ) || ( ! is_int( $value ) && ! is_string( $value ) ) ) {
			return $value;
		}
		if ( 'shapes' === $name && 0 !== strpos( (string) $value, self::SHAPE_PREFIX ) ) {
			return $value;
		}
		$new = $this->context->mapped( $name, $value );
		if ( null !== $new ) {
			return $new;
		}
		if ( ! empty( $ref['gate'] ) ) {
			$this->unresolved( $ref, array( $value ) );
			return null !== $ref['never'] ? $this->never( $ref ) : '';
		}
		$this->report( 'map.miss', array( 'map' => $name, 'value' => $value ) );
		return $value;
	}

	/** Una URL de un control: medios del paquete o prefijo del origen; avisa si la ruta del contenido cambió. */
	private function url_value( string $url ): string {
		$new = $this->url( $url );
		if ( $new !== $url ) {
			$this->route_check( $url );
		}
		return $new;
	}

	/** «La ruta cambió» (§9.3): sólo se informa. */
	private function route_check( string $url ): void {
		$routes = $this->context->map( 'routes' );
		$home   = $this->context->origin( 'home' );
		if ( ! $routes || '' === $home ) {
			return;
		}
		$rest = self::after_prefix( $url, $home );
		if ( null === $rest ) {
			return;
		}
		$route = self::route( $rest );
		if ( '' === $route || ! array_key_exists( $route, $routes ) ) {
			return;
		}
		$new_id = $this->map_id( 'post', $routes[ $route ] );
		if ( null === $new_id ) {
			return;
		}
		$link = (string) call_user_func( $this->callbacks['permalink'], (int) $new_id );
		$now  = '' === $link ? '' : self::after_prefix( $link, $this->target['home'] );
		$now  = null === $now ? '' : self::route( $now );
		if ( '' !== $now && $now !== $route ) {
			$this->report(
				'url.route-changed',
				array(
					'from' => $route,
					'to'   => $now,
				)
			);
		}
	}

	/** `e_display_conditions`: cada condición se resuelve con las referencias de `scope = condition`. */
	private function conditions_value( $value ) {
		$wrapped = is_array( $value ) && isset( $value[0] ) && is_string( $value[0] );
		$json    = $wrapped ? $value[0] : ( is_string( $value ) ? $value : null );
		if ( null === $json || '' === trim( $json ) ) {
			return $value;
		}
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) || ! $decoded ) {
			return $value;
		}
		$changed = $this->conditions_tree( $decoded );
		if ( $changed === $decoded ) {
			return $value;
		}
		$encoded = function_exists( 'wp_json_encode' ) ? wp_json_encode( $changed ) : json_encode( $changed );
		if ( ! is_string( $encoded ) ) {
			return $value;
		}
		if ( $wrapped ) {
			$value[0] = $encoded;
			return $value;
		}
		return $encoded;
	}

	/** Grupos (lista de listas) o una lista plana de condiciones `{ condition, … }`. */
	private function conditions_tree( array $tree ): array {
		foreach ( $tree as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( isset( $item['condition'] ) && is_string( $item['condition'] ) ) {
				foreach ( References::matching( 'condition', $item['condition'] ) as $ref ) {
					$item = $this->apply_ref( $item, $ref );
				}
				$tree[ $index ] = $item;
			} elseif ( Part_Context::is_list( $item ) ) {
				$tree[ $index ] = $this->conditions_tree( $item );
			}
		}
		return $tree;
	}

	private function globals( array $settings ): array {
		return self::globals_in( $settings, $this->context->map( 'global_color' ), $this->context->map( 'global_typography' ) );
	}

	private static function global_value( string $global, array $colors, array $typography ): string {
		if ( 1 !== preg_match( '/^globals\/(colors|typography)\?id=([^&#]+)$/', $global, $match ) ) {
			return $global;
		}
		$map = 'colors' === $match[1] ? $colors : $typography;
		if ( ! isset( $map[ $match[2] ] ) || ! is_string( $map[ $match[2] ] ) || '' === $map[ $match[2] ] ) {
			return $global;
		}
		return 'globals/' . $match[1] . '?id=' . $map[ $match[2] ];
	}

	// ── Medios ──────────────────────────────────────────────────────────────────────────────────────────────────

	/** URL nueva de un archivo del origen, o null si no viajó. */
	private function find_media( string $url ): ?string {
		$uploads = $this->context->origin( 'uploads' );
		if ( '' === $uploads ) {
			return null;
		}
		$rest = self::after_prefix( $url, $uploads );
		if ( null === $rest || '' === trim( $rest, '/' ) ) {
			return null;
		}
		$relative = ltrim( self::strip_query( $rest ), '/' );
		if ( 0 === strpos( $relative, self::FILES_FOLDER ) ) {
			$file = substr( $relative, strlen( self::FILES_FOLDER ) );
			$new  = $this->context->mapped( 'files', $file );
			return is_string( $new ) && '' !== $new && '' !== $this->target['uploads']
				? rtrim( $this->target['uploads'], '/' ) . '/' . self::FILES_FOLDER . $new
				: null;
		}
		$new = $this->imported_url( $url );
		if ( null !== $new ) {
			return $new;
		}
		// Un tamaño intermedio de una imagen importada pasa a la completa.
		$path = self::strip_query( $url );
		if ( 1 === preg_match( self::SIZE_PATTERN, $path, $match ) ) {
			$full = $this->imported_url( $match[1] . $match[2] );
			if ( null !== $full ) {
				$this->report( 'media.size', array( 'url' => $url ) );
				return $full;
			}
		}
		return null;
	}

	/** URL del adjunto importado desde esa URL exacta (mapas de la parte `media` o huella del núcleo). */
	private function imported_url( string $url ): ?string {
		$key   = self::comparable( self::strip_query( $url ) );
		$index = $this->media_index();
		if ( isset( $index[ $key ] ) ) {
			return $index[ $key ];
		}
		$id = $this->context->mapped( 'attachment', $url );
		if ( null === $id ) {
			$id = (int) call_user_func( $this->callbacks['attachment_by_url'], $url );
		}
		if ( (int) $id > 0 ) {
			$new = (string) call_user_func( $this->callbacks['attachment_url'], (int) $id );
			if ( '' !== $new ) {
				$this->media[ $key ] = $new;
				return $new;
			}
		}
		return null;
	}

	private function media_index(): array {
		if ( null === $this->media ) {
			$this->media = array();
			foreach ( $this->context->map( 'media_url' ) as $old => $new ) {
				if ( is_string( $old ) && is_string( $new ) && '' !== $new ) {
					$this->media[ self::comparable( $old ) ] = $new;
				}
			}
		}
		return $this->media;
	}

	/**
	 * Adjunto que el núcleo importó desde una URL (`_elementor_source_image_hash = sha1( url )`, en cualquier
	 * estado), o 0.
	 */
	public static function find_attachment_by_url( string $url ): int {
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_key'         => '_elementor_source_image_hash', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => sha1( $url ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return is_array( $ids ) && $ids ? (int) reset( $ids ) : 0;
	}

	// ── Cierre e informe ────────────────────────────────────────────────────────────────────────────────────────

	/** Valor de cierre de la referencia, comprobando que no sea un ID real. */
	private function never( array $ref ) {
		$never = $ref['never'];
		$kind  = 'user' === $ref['kind'] ? 'user' : 'post';
		if ( is_int( $never ) || ctype_digit( (string) $never ) ) {
			if ( call_user_func( $this->callbacks['exists'], $kind, (int) $never ) ) {
				$this->report(
					'never-exists',
					array(
						'kind'  => $kind,
						'value' => $never,
					),
					Part_Context::ERROR
				);
			}
		}
		return $never;
	}

	private function unresolved( array $ref, array $values ): void {
		$data = array(
			'post'    => $this->post_id,
			'element' => $this->element_id,
			'type'    => $this->element_type,
			'path'    => (string) $ref['path'],
			'kind'    => (string) $ref['kind'],
			'values'  => array_values( $values ),
		);
		$this->context->report( 'unresolved', $data, Part_Context::WARNING );
		if ( ! empty( $ref['gate'] ) && $this->post_id > 0 ) {
			$this->context->flag_review( $this->post_id, self::REVIEW, $data );
		}
	}

	private function note( string $code, array $ref ): void {
		$key = $this->post_id . '|' . $this->element_id . '|' . $code;
		if ( isset( $this->noted[ $key ] ) ) {
			return;
		}
		$this->noted[ $key ] = true;
		$this->context->report(
			'note:' . $code,
			array(
				'post'    => $this->post_id,
				'element' => $this->element_id,
				'type'    => $this->element_type,
				'path'    => (string) $ref['path'],
			)
		);
	}

	/** Anota un archivo que no viajó, una sola vez por documento, elemento y URL (el registro y la forma lo ven). */
	private function missing( string $url ): void {
		$key = 'media-url.missing|' . $this->post_id . '|' . $this->element_id . '|' . $url;
		if ( isset( $this->noted[ $key ] ) ) {
			return;
		}
		$this->noted[ $key ] = true;
		$this->report( 'media-url.missing', array( 'url' => $url ), Part_Context::WARNING );
	}

	private function report( string $code, array $data, string $level = Part_Context::INFO ): void {
		$this->context->report( $code, array( 'post' => $this->post_id ) + $data, $level );
	}

	// ── Utilidades ──────────────────────────────────────────────────────────────────────────────────────────────

	/** Pares de prefijos: los del mapa `url` (§9.1) o, si no hay, `uploads`, `site` y `home` del origen. */
	private function prefixes(): array {
		if ( null !== $this->prefixes ) {
			return $this->prefixes;
		}
		$pairs = array();
		foreach ( $this->context->map( 'url' ) as $old => $new ) {
			if ( is_string( $old ) && is_string( $new ) && null !== self::split_url( $old ) ) {
				$pairs[] = array( $old, $new );
			}
		}
		if ( ! $pairs ) {
			foreach ( array( 'uploads', 'site', 'home' ) as $key ) {
				$old = $this->context->origin( $key );
				if ( '' !== $old && '' !== $this->target[ $key ] && null !== self::split_url( $old ) ) {
					$pairs[] = array( $old, $this->target[ $key ] );
				}
			}
		}
		usort(
			$pairs,
			function ( $a, $b ) {
				return strlen( $b[0] ) - strlen( $a[0] );
			}
		);
		$this->prefixes = $pairs;
		return $pairs;
	}

	/** ¿Empieza por algún prefijo del origen? */
	private function is_origin( string $url ): bool {
		foreach ( array( 'uploads', 'site', 'home' ) as $key ) {
			$prefix = $this->context->origin( $key );
			if ( '' !== $prefix && null !== self::after_prefix( $url, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Lo que sigue a un prefijo de URL (ruta, consulta y fragmento), o null si la URL no empieza por él. Sin
	 * distinguir `http`/`https` ni mayúsculas del host; el prefijo debe acabar en un límite (`/`, `?`, `#` o el final).
	 */
	public static function after_prefix( string $url, string $prefix ): ?string {
		$u = self::split_url( $url );
		$p = self::split_url( $prefix );
		if ( null === $u || null === $p || strtolower( $u[0] ) !== strtolower( $p[0] ) ) {
			return null;
		}
		$base = rtrim( $p[1], '/' );
		if ( '' === $base ) {
			return $u[1];
		}
		if ( 0 !== strpos( $u[1], $base ) ) {
			return null;
		}
		$rest = (string) substr( $u[1], strlen( $base ) );
		if ( '' !== $rest && ! in_array( $rest[0], array( '/', '?', '#' ), true ) ) {
			return null;
		}
		return $rest;
	}

	/** `[ host, resto ]` de una URL absoluta (con o sin esquema), o null. */
	private static function split_url( string $url ): ?array {
		if ( 1 !== preg_match( '#^(?:https?:)?//([^/?\#\s]+)(.*)$#i', trim( $url ), $match ) ) {
			return null;
		}
		return array( $match[1], $match[2] );
	}

	/** Sin consulta ni fragmento. */
	private static function strip_query( string $url ): string {
		return (string) preg_replace( '/[?#].*$/s', '', $url );
	}

	/** `//host/ruta` con el host en minúsculas. */
	private static function comparable( string $url ): string {
		$split = self::split_url( $url );
		return null === $split ? $url : '//' . strtolower( $split[0] ) . $split[1];
	}

	/** Ruta normalizada de un permalink relativo: `/contacto/`. */
	private static function route( string $rest ): string {
		$path = self::strip_query( $rest );
		$path = '/' . trim( $path, '/' );
		return '/' === $path ? '/' : $path . '/';
	}

	private function condition_kind( string $name, string $sub_name ): ?string {
		if ( 'by_author' === $sub_name || 'author' === $sub_name || '_by_author' === substr( $sub_name, -10 ) ) {
			return 'user';
		}
		if ( 'child_of' === $sub_name || 'any_child_of' === $sub_name ) {
			return 'post';
		}
		if ( 1 === preg_match( '/^(?:in_|child_of_|any_child_of_)/', $sub_name ) ) {
			return 'term';
		}
		if ( call_user_func( $this->callbacks['taxonomy_exists'], $sub_name ) ) {
			return 'term';
		}
		if ( call_user_func( $this->callbacks['post_type_exists'], $sub_name ) ) {
			return 'post';
		}
		return null;
	}

	private static function type_of( array $element ): string {
		if ( isset( $element['elType'] ) && 'widget' === $element['elType'] ) {
			return isset( $element['widgetType'] ) && is_string( $element['widgetType'] ) ? $element['widgetType'] : '';
		}
		return isset( $element['elType'] ) && is_string( $element['elType'] ) ? $element['elType'] : '';
	}

	/** Vacío: nada que traducir. */
	private static function is_blank( $value ): bool {
		return null === $value || '' === $value || array() === $value || 0 === $value || '0' === $value || false === $value;
	}

	/** El ID nuevo con el tipo del viejo: una cadena sigue siendo cadena. */
	private static function same_type( $old, $new ) {
		if ( is_string( $old ) && is_int( $new ) ) {
			return (string) $new;
		}
		if ( is_int( $old ) && is_string( $new ) && ctype_digit( $new ) ) {
			return (int) $new;
		}
		return $new;
	}

	/** ¿El núcleo puede instanciar el elemento? */
	public static function is_known_element( array $element ): bool {
		if ( ! class_exists( '\Elementor\Plugin', false ) || empty( \Elementor\Plugin::$instance->elements_manager ) ) {
			return false;
		}
		// `get_element()` exige `elType` y un `widgetType` de texto: lo demás nunca es un elemento conocido.
		if ( ! isset( $element['elType'] ) || ! is_string( $element['elType'] ) || ( isset( $element['widgetType'] ) && ! is_string( $element['widgetType'] ) ) ) {
			return false;
		}
		try {
			return null !== \Elementor\Plugin::$instance->elements_manager->create_element_instance( $element );
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	/** Remapeo nativo de elementos (`on_import_update_dynamic_content` de cada uno y `__dynamic__`). */
	public static function native_remap( array $elements, array $replacements ): array {
		if ( ! class_exists( '\Elementor\Core\Base\Document', false ) ) {
			return $elements;
		}
		return \Elementor\Core\Base\Document::on_import_update_dynamic_content( $elements, $replacements );
	}

	private static function uploads_url(): string {
		if ( ! function_exists( 'wp_upload_dir' ) ) {
			return '';
		}
		$uploads = wp_upload_dir( null, false );
		return is_array( $uploads ) && empty( $uploads['error'] ) && ! empty( $uploads['baseurl'] ) ? (string) $uploads['baseurl'] : '';
	}
}
