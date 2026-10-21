<?php
/**
 * Kits (etapa K): WordPress y el núcleo del constructor en memoria, para las pruebas de las partes de K y del
 * remapeo (`tests/elements-kit-parts.php` y `tests/elements-kit-remap.php`).
 *
 * Cada función sólo se define si no existe: la prueba del contrato ya define algunas. El estado vive en
 * `$GLOBALS['wp']` y `kit_stub_reset()` lo vacía entre casos.
 */
namespace Elementor {
	if ( ! class_exists( 'Elementor\Plugin', false ) ) {
		class Plugin {
			public static $instance;
		}
	}
	if ( ! class_exists( 'Elementor\Utils', false ) ) {
		class Utils {
			public static function kses_post_deep( $value ) {
				if ( is_array( $value ) ) {
					return array_map( array( __CLASS__, 'kses_post_deep' ), $value );
				}
				return is_string( $value ) ? preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $value ) : $value;
			}
		}
	}
}

namespace Elementor\Core\Utils\Svg {
	if ( ! class_exists( 'Elementor\Core\Utils\Svg\Svg_Sanitizer', false ) ) {
		class Svg_Sanitizer {
			public function sanitize( $content ) {
				$start = strpos( $content, '<svg' );
				$end   = strrpos( $content, '</svg>' );
				if ( false === $start || false === $end ) {
					return false;
				}
				$content = substr( $content, $start, $end - $start + 6 );
				$content = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $content );
				return preg_replace( '/\son[a-z]+="[^"]*"/i', '', $content );
			}
		}
	}
}

namespace Elementor\Core\Files {
	if ( ! class_exists( 'Elementor\Core\Files\Uploads_Manager', false ) ) {
		class Uploads_Manager {
			public static $unfiltered = false;
			public static function are_unfiltered_uploads_enabled() {
				return self::$unfiltered;
			}
		}
	}
}

namespace Elementor\Core\Base {
	if ( ! class_exists( 'Elementor\Core\Base\Document', false ) ) {
		/** Remapeo nativo simulado: traduce `post_id` con `post_ids` y deja constancia de cada nodo visitado. */
		class Document {
			public static $calls = array();
			public static function on_import_update_dynamic_content( array $config, array $data, $controls = null ): array {
				foreach ( $config as &$element ) {
					self::$calls[] = $element['id'] ?? '';
					if ( isset( $element['settings']['post_id'] ) && isset( $data['post_ids'][ $element['settings']['post_id'] ] ) ) {
						$element['settings']['post_id'] = $data['post_ids'][ $element['settings']['post_id'] ];
					}
					$element['elements'] = static::on_import_update_dynamic_content( $element['elements'] ?? array(), $data );
				}
				return $config;
			}
		}
	}
}

namespace Elementor\Modules\KitElementsDefaults\Utils {
	if ( ! class_exists( 'Elementor\Modules\KitElementsDefaults\Utils\Settings_Sanitizer', false ) ) {
		/** Saneador nativo simulado: quita las claves que no son controles (salvo `__globals__`) y aplica kses. */
		class Settings_Sanitizer {
			private $type;
			private $settings;
			public function __construct( $elements_manager, array $widget_types = array() ) {
			}
			public function for( $type ) {
				$this->type = $type;
				return $this;
			}
			public function using( $settings ) {
				$this->settings = $settings;
				return $this;
			}
			public function remove_invalid_settings() {
				foreach ( array_keys( $this->settings ) as $key ) {
					if ( 0 === strpos( (string) $key, 'invalid_' ) ) {
						unset( $this->settings[ $key ] );
					}
				}
				return $this;
			}
			public function kses_deep() {
				$this->settings = \Elementor\Utils::kses_post_deep( $this->settings );
				return $this;
			}
			public function prepare_for_export( $document ) {
				$this->settings['_exported'] = 'yes';
				return $this;
			}
			public function prepare_for_import( $document ) {
				unset( $this->settings['_exported'] );
				return $this;
			}
			public function get() {
				return $this->settings;
			}
		}
	}
}

namespace ElementorPro\Modules\ThemeBuilder {
	if ( ! class_exists( 'ElementorPro\Modules\ThemeBuilder\Module', false ) ) {
		class Conditions_Stub {
			public $saved = array();
			/** Igual que el gestor real: `implode( '/', $condition )` de cada array. */
			public function save_conditions( $post_id, $conditions ) {
				$strings = array();
				foreach ( $conditions as $condition ) {
					unset( $condition['_id'] );
					$strings[] = rtrim( implode( '/', $condition ), '/' );
				}
				$this->saved[ $post_id ] = $strings;
				if ( $strings ) {
					update_post_meta( $post_id, '_elementor_conditions', $strings );
				} else {
					delete_post_meta( $post_id, '_elementor_conditions' );
				}
				return true;
			}
		}
		class Module {
			public static $manager;
			public static function instance() {
				return new self();
			}
			public function get_conditions_manager() {
				if ( null === self::$manager ) {
					self::$manager = new Conditions_Stub();
				}
				return self::$manager;
			}
		}
	}
}

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}

	$GLOBALS['wp'] = array();

	/** Vacía el estado en memoria. */
	function kit_stub_reset( string $uploads ): void {
		$GLOBALS['wp'] = array(
			'posts'     => array(),
			'meta'      => array(),
			'terms'     => array(),
			'termmeta'  => array(),
			'options'   => array(),
			'hooks'     => array(),
			'users'     => array( 1 => true ),
			'caps'      => array( 'manage_options' => true, 'unfiltered_html' => true ),
			'uploads'   => $uploads,
			'baseurl'   => 'https://b.test/wp-content/uploads',
			'home'      => 'https://b.test',
			'next'      => 1000,
			'next_term' => 500,
			'thumbs'    => array(),
			'wrong'     => array(),
		);
		if ( ! is_dir( $uploads ) ) {
			mkdir( $uploads, 0777, true );
		}
	}

	/** Crea una entrada y devuelve su ID. */
	function kit_stub_post( array $data ): int {
		$id                            = isset( $data['ID'] ) ? (int) $data['ID'] : ++$GLOBALS['wp']['next'];
		$GLOBALS['wp']['next']         = max( $GLOBALS['wp']['next'], $id );
		$GLOBALS['wp']['posts'][ $id ] = (object) array_merge(
			array(
				'ID'            => $id,
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_title'    => '',
				'post_content'  => '',
				'post_excerpt'  => '',
				'post_password' => '',
				'post_parent'   => 0,
				'guid'          => '',
			),
			$data,
			array( 'ID' => $id )
		);
		return $id;
	}

	/** Crea un término y devuelve su ID. */
	function kit_stub_term( array $data ): int {
		$id                            = isset( $data['term_id'] ) ? (int) $data['term_id'] : ++$GLOBALS['wp']['next_term'];
		$GLOBALS['wp']['next_term']    = max( $GLOBALS['wp']['next_term'], $id );
		$GLOBALS['wp']['terms'][ $id ] = (object) array_merge(
			array(
				'term_id'     => $id,
				'taxonomy'    => 'category',
				'slug'        => 't' . $id,
				'name'        => 'T' . $id,
				'description' => '',
			),
			$data,
			array( 'term_id' => $id )
		);
		return $id;
	}

	// Ganchos.
	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
			$GLOBALS['wp']['hooks'][ $hook ][ $priority ][] = $callback;
			return true;
		}
	}
	if ( ! function_exists( 'remove_filter' ) ) {
		function remove_filter( $hook, $callback, $priority = 10 ) {
			foreach ( $GLOBALS['wp']['hooks'][ $hook ][ $priority ] ?? array() as $i => $registered ) {
				if ( $registered === $callback ) {
					unset( $GLOBALS['wp']['hooks'][ $hook ][ $priority ][ $i ] );
					return true;
				}
			}
			return false;
		}
	}
	/** Los callbacks registrados con `add_filter()` de un gancho, por prioridad. */
	function kit_stub_filters( $hook ): array {
		$by = $GLOBALS['wp']['hooks'][ $hook ] ?? array();
		ksort( $by );
		$out = array();
		foreach ( $by as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$out[] = $callback;
			}
		}
		return $out;
	}
	/** Aplica los callbacks de `add_filter()` (la prueba del contrato tiene su propio `apply_filters`). */
	function kit_stub_apply( $hook, $value, ...$args ) {
		foreach ( kit_stub_filters( $hook ) as $callback ) {
			$value = call_user_func( $callback, $value, ...$args );
		}
		return $value;
	}
	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $hook, $value, ...$args ) {
			if ( isset( $GLOBALS['kit']['filters'][ $hook ] ) ) {
				$value = call_user_func( $GLOBALS['kit']['filters'][ $hook ], $value, ...$args );
			}
			return kit_stub_apply( $hook, $value, ...$args );
		}
	}
	if ( ! function_exists( 'add_action' ) ) {
		function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
			return add_filter( $hook, $callback, $priority, $args );
		}
	}
	if ( ! function_exists( 'remove_action' ) ) {
		function remove_action( $hook, $callback, $priority = 10 ) {
			return remove_filter( $hook, $callback, $priority );
		}
	}
	if ( ! function_exists( '__return_true' ) ) {
		function __return_true() {
			return true;
		}
	}
	if ( ! function_exists( '_doing_it_wrong' ) ) {
		function _doing_it_wrong( $function, $message, $version ) {
			$GLOBALS['wp']['wrong'][] = $function . ': ' . $message;
		}
	}
	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $value, $flags = 0 ) {
			return json_encode( $value, $flags );
		}
	}

	// URL y archivos.
	if ( ! function_exists( 'home_url' ) ) {
		function home_url() {
			return $GLOBALS['wp']['home'];
		}
	}
	if ( ! function_exists( 'site_url' ) ) {
		function site_url() {
			return $GLOBALS['wp']['home'];
		}
	}
	if ( ! function_exists( 'get_site_url' ) ) {
		function get_site_url() {
			return $GLOBALS['wp']['home'];
		}
	}
	if ( ! function_exists( 'wp_upload_dir' ) ) {
		function wp_upload_dir( $time = null, $create = true ) {
			return array(
				'basedir' => $GLOBALS['wp']['uploads'],
				'baseurl' => $GLOBALS['wp']['baseurl'],
				'error'   => false,
			);
		}
	}
	if ( ! function_exists( 'wp_parse_url' ) ) {
		function wp_parse_url( $url, $component = -1 ) {
			return parse_url( $url, $component );
		}
	}
	if ( ! function_exists( 'wp_mkdir_p' ) ) {
		function wp_mkdir_p( $dir ) {
			return is_dir( $dir ) || mkdir( $dir, 0777, true );
		}
	}
	if ( ! function_exists( 'get_allowed_mime_types' ) ) {
		function get_allowed_mime_types() {
			$types = array(
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'gif'      => 'image/gif',
				'webp'     => 'image/webp',
				'mp4'      => 'video/mp4',
				'mp3'      => 'audio/mpeg',
				'pdf'      => 'application/pdf',
				'csv'      => 'text/csv',
			);
			if ( ! empty( $GLOBALS['wp']['svg'] ) ) {
				$types['svg'] = 'image/svg+xml';
			}
			return $types;
		}
	}
	if ( ! function_exists( 'wp_check_filetype' ) ) {
		function wp_check_filetype( $filename, $mimes = null ) {
			$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
			foreach ( null === $mimes ? get_allowed_mime_types() : $mimes as $exts => $mime ) {
				if ( in_array( $ext, explode( '|', $exts ), true ) ) {
					return array(
						'ext'  => $ext,
						'type' => $mime,
					);
				}
			}
			return array(
				'ext'  => false,
				'type' => false,
			);
		}
	}
	if ( ! function_exists( 'sanitize_file_name' ) ) {
		function sanitize_file_name( $name ) {
			return preg_replace( '/[^A-Za-z0-9._-]/', '', $name );
		}
	}
	if ( ! function_exists( 'wp_upload_bits' ) ) {
		function wp_upload_bits( $name, $deprecated, $bits ) {
			$dir = $GLOBALS['wp']['uploads'] . '/2026/10';
			wp_mkdir_p( $dir );
			$file = $dir . '/' . $name;
			$i    = 1;
			while ( file_exists( $file ) ) {
				$file = $dir . '/' . pathinfo( $name, PATHINFO_FILENAME ) . '-' . ( ++$i ) . '.' . pathinfo( $name, PATHINFO_EXTENSION );
			}
			file_put_contents( $file, $bits );
			return array(
				'file'  => $file,
				'url'   => $GLOBALS['wp']['baseurl'] . '/2026/10/' . basename( $file ),
				'error' => false,
			);
		}
	}
	if ( ! function_exists( 'wp_insert_attachment' ) ) {
		function wp_insert_attachment( $data, $file ) {
			$id = kit_stub_post( array_merge( $data, array( 'post_type' => 'attachment', 'post_status' => 'inherit' ) ) );
			update_post_meta( $id, '_wp_attached_file', ltrim( substr( $file, strlen( $GLOBALS['wp']['uploads'] ) ), '/' ) );
			return $id;
		}
	}
	if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
		function wp_generate_attachment_metadata( $id, $file ) {
			return array( 'file' => basename( $file ) );
		}
	}
	if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
		function wp_update_attachment_metadata( $id, $data ) {
			return update_post_meta( $id, '_wp_attachment_metadata', $data );
		}
	}
	if ( ! function_exists( 'wp_get_attachment_url' ) ) {
		function wp_get_attachment_url( $id ) {
			$file = get_post_meta( $id, '_wp_attached_file', true );
			return is_string( $file ) && '' !== $file ? $GLOBALS['wp']['baseurl'] . '/' . $file : false;
		}
	}
	if ( ! function_exists( 'get_attached_file' ) ) {
		function get_attached_file( $id ) {
			$file = get_post_meta( $id, '_wp_attached_file', true );
			return is_string( $file ) && '' !== $file ? $GLOBALS['wp']['uploads'] . '/' . $file : false;
		}
	}
	if ( ! function_exists( 'attachment_url_to_postid' ) ) {
		function attachment_url_to_postid( $url ) {
			$base = $GLOBALS['wp']['baseurl'] . '/';
			if ( 0 !== strpos( $url, $base ) ) {
				return 0;
			}
			$path = substr( $url, strlen( $base ) );
			foreach ( $GLOBALS['wp']['posts'] as $id => $post ) {
				if ( 'attachment' === $post->post_type && get_post_meta( $id, '_wp_attached_file', true ) === $path ) {
					return $id;
				}
			}
			return 0;
		}
	}
	if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
		function get_post_thumbnail_id( $post ) {
			return (int) get_post_meta( is_object( $post ) ? $post->ID : $post, '_thumbnail_id', true );
		}
	}
	if ( ! function_exists( 'get_the_post_thumbnail_url' ) ) {
		function get_the_post_thumbnail_url( $post, $size = 'post-thumbnail' ) {
			$id = get_post_thumbnail_id( $post );
			if ( ! $id ) {
				return false;
			}
			$url = wp_get_attachment_url( $id );
			return 'full' === $size ? $url : preg_replace( '/(\.[a-z]+)$/', '-150x150$1', (string) $url );
		}
	}
	if ( ! function_exists( 'get_permalink' ) ) {
		function get_permalink( $post ) {
			$post = get_post( $post );
			return $post ? $GLOBALS['wp']['home'] . '/' . ( $post->post_name ?? ( 'p' . $post->ID ) ) . '/' : false;
		}
	}

	// Entradas.
	if ( ! function_exists( 'get_post' ) ) {
		function get_post( $id = null ) {
			if ( is_object( $id ) ) {
				return $id;
			}
			return $GLOBALS['wp']['posts'][ (int) $id ] ?? null;
		}
	}
	if ( ! function_exists( 'get_post_type' ) ) {
		function get_post_type( $id = null ) {
			$post = get_post( $id );
			return $post ? $post->post_type : false;
		}
	}
	if ( ! function_exists( 'get_post_status' ) ) {
		function get_post_status( $id = null ) {
			$post = get_post( $id );
			return $post ? $post->post_status : false;
		}
	}
	if ( ! function_exists( 'post_type_exists' ) ) {
		function post_type_exists( $type ) {
			return in_array( $type, $GLOBALS['wp']['post_types'] ?? array( 'post', 'page', 'attachment', 'elementor_library', 'product' ), true );
		}
	}
	if ( ! function_exists( 'taxonomy_exists' ) ) {
		function taxonomy_exists( $taxonomy ) {
			return in_array( $taxonomy, array( 'category', 'post_tag', 'product_cat', 'product_tag' ), true );
		}
	}
	if ( ! function_exists( 'get_userdata' ) ) {
		function get_userdata( $id ) {
			return isset( $GLOBALS['wp']['users'][ (int) $id ] ) ? (object) array( 'ID' => (int) $id ) : false;
		}
	}
	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( $cap ) {
			return ! empty( $GLOBALS['wp']['caps'][ $cap ] );
		}
	}
	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $name, $default = false ) {
			return $GLOBALS['wp']['options'][ $name ] ?? $default;
		}
	}
	if ( ! function_exists( 'is_serialized' ) ) {
		/** Como el de WordPress: `N;` o `<tipo>:` … terminado en `;` o `}`. */
		function is_serialized( $data ) {
			if ( ! is_string( $data ) ) {
				return false;
			}
			$data = trim( $data );
			if ( 'N;' === $data ) {
				return true;
			}
			if ( strlen( $data ) < 4 || ':' !== $data[1] || ! in_array( substr( $data, -1 ), array( ';', '}' ), true ) ) {
				return false;
			}
			return 1 === preg_match( '/^[aOsbidCE]:/', $data );
		}
	}

	// Metas (se guardan como valores PHP; sin clave se devuelven serializadas, como en WordPress).
	function kit_stub_meta_get( $store, $id, $key, $single ) {
		$all = $GLOBALS['wp'][ $store ][ (int) $id ] ?? array();
		if ( '' === $key ) {
			return array_map(
				function ( $values ) {
					return array_map(
						function ( $v ) {
							return is_array( $v ) || is_object( $v ) ? serialize( $v ) : $v;
						},
						$values
					);
				},
				$all
			);
		}
		if ( ! isset( $all[ $key ] ) ) {
			return $single ? '' : array();
		}
		return $single ? $all[ $key ][0] : $all[ $key ];
	}
	if ( ! function_exists( 'get_post_meta' ) ) {
		function get_post_meta( $id, $key = '', $single = false ) {
			return kit_stub_meta_get( 'meta', $id, $key, $single );
		}
	}
	if ( ! function_exists( 'update_post_meta' ) ) {
		function update_post_meta( $id, $key, $value ) {
			$GLOBALS['wp']['meta'][ (int) $id ][ $key ] = array( $value );
			return true;
		}
	}
	if ( ! function_exists( 'add_post_meta' ) ) {
		function add_post_meta( $id, $key, $value ) {
			$GLOBALS['wp']['meta'][ (int) $id ][ $key ][] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'delete_post_meta' ) ) {
		function delete_post_meta( $id, $key ) {
			unset( $GLOBALS['wp']['meta'][ (int) $id ][ $key ] );
			return true;
		}
	}
	if ( ! function_exists( 'metadata_exists' ) ) {
		function metadata_exists( $type, $id, $key ) {
			return isset( $GLOBALS['wp']['post' === $type ? 'meta' : 'termmeta'][ (int) $id ][ $key ] );
		}
	}

	// Términos.
	if ( ! function_exists( 'get_term' ) ) {
		function get_term( $id ) {
			return $GLOBALS['wp']['terms'][ (int) $id ] ?? null;
		}
	}
	if ( ! function_exists( 'get_term_meta' ) ) {
		function get_term_meta( $id, $key = '', $single = false ) {
			return kit_stub_meta_get( 'termmeta', $id, $key, $single );
		}
	}
	if ( ! function_exists( 'update_term_meta' ) ) {
		function update_term_meta( $id, $key, $value ) {
			$GLOBALS['wp']['termmeta'][ (int) $id ][ $key ] = array( $value );
			return true;
		}
	}
	if ( ! function_exists( 'add_term_meta' ) ) {
		function add_term_meta( $id, $key, $value ) {
			$GLOBALS['wp']['termmeta'][ (int) $id ][ $key ][] = $value;
			return true;
		}
	}
	if ( ! function_exists( 'delete_term_meta' ) ) {
		function delete_term_meta( $id, $key, $value = '' ) {
			if ( '' === $value ) {
				unset( $GLOBALS['wp']['termmeta'][ (int) $id ][ $key ] );
				return true;
			}
			$left = array_values(
				array_filter(
					$GLOBALS['wp']['termmeta'][ (int) $id ][ $key ] ?? array(),
					function ( $v ) use ( $value ) {
						return $v !== $value;
					}
				)
			);
			if ( $left ) {
				$GLOBALS['wp']['termmeta'][ (int) $id ][ $key ] = $left;
			} else {
				unset( $GLOBALS['wp']['termmeta'][ (int) $id ][ $key ] );
			}
			return true;
		}
	}
	if ( ! function_exists( 'get_taxonomies' ) ) {
		function get_taxonomies() {
			return array( 'category' => 'category', 'post_tag' => 'post_tag', 'product_cat' => 'product_cat' );
		}
	}
	if ( ! function_exists( 'get_object_taxonomies' ) ) {
		function get_object_taxonomies( $type ) {
			return 'post' === $type ? array( 'category', 'post_tag' ) : ( 'product' === $type ? array( 'product_cat' ) : array() );
		}
	}
	/** ¿Cumple una meta_query (`=`, `LIKE`, `EXISTS`, `NOT EXISTS`)? */
	function kit_stub_meta_match( string $store, int $id, array $clauses ): bool {
		foreach ( $clauses as $clause ) {
			if ( ! is_array( $clause ) ) {
				continue;
			}
			$values  = $GLOBALS['wp'][ $store ][ $id ][ $clause['key'] ] ?? null;
			$compare = $clause['compare'] ?? '=';
			if ( 'EXISTS' === $compare && null === $values ) {
				return false;
			}
			if ( 'NOT EXISTS' === $compare && null !== $values ) {
				return false;
			}
			if ( in_array( $compare, array( '=', 'LIKE' ), true ) ) {
				$hit = false;
				foreach ( (array) $values as $value ) {
					$text = is_array( $value ) ? serialize( $value ) : (string) $value;
					$hit  = $hit || ( 'LIKE' === $compare ? false !== strpos( $text, (string) $clause['value'] ) : $text === (string) $clause['value'] );
				}
				if ( ! $hit ) {
					return false;
				}
			}
		}
		return true;
	}
	if ( ! function_exists( 'get_terms' ) ) {
		function get_terms( $args = array() ) {
			$out = array();
			foreach ( $GLOBALS['wp']['terms'] as $id => $term ) {
				if ( isset( $args['taxonomy'] ) && ! in_array( $term->taxonomy, (array) $args['taxonomy'], true ) ) {
					continue;
				}
				if ( ! empty( $args['meta_query'] ) && ! kit_stub_meta_match( 'termmeta', $id, $args['meta_query'] ) ) {
					continue;
				}
				$out[] = 'ids' === ( $args['fields'] ?? '' ) ? $id : $term;
			}
			return $out;
		}
	}

	if ( ! class_exists( 'WP_Query', false ) ) {
		class WP_Query {
			public $posts       = array();
			public $found_posts = 0;
			public static $log  = array();
			public function __construct( $args = array() ) {
				self::$log[] = $args;
				$types       = $args['post_type'] ?? 'post';
				$statuses    = $args['post_status'] ?? 'publish';
				$meta        = $args['meta_query'] ?? array();
				if ( isset( $args['meta_key'] ) ) {
					$meta[] = array( 'key' => $args['meta_key'], 'value' => $args['meta_value'] ?? '', 'compare' => isset( $args['meta_value'] ) ? '=' : 'EXISTS' );
				}
				$found = array();
				foreach ( $GLOBALS['wp']['posts'] as $id => $post ) {
					if ( 'any' !== $types && ! in_array( $post->post_type, (array) $types, true ) ) {
						continue;
					}
					if ( 'any' === $statuses ? in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) : ! in_array( $post->post_status, (array) $statuses, true ) ) {
						continue;
					}
					if ( isset( $args['has_password'] ) && ( '' !== $post->post_password ) !== (bool) $args['has_password'] ) {
						continue;
					}
					if ( $meta && ! kit_stub_meta_match( 'meta', $id, $meta ) ) {
						continue;
					}
					$found[] = $id;
				}
				$this->found_posts = count( $found );
				$limit             = (int) ( $args['posts_per_page'] ?? 10 );
				if ( $limit >= 0 ) {
					$found = array_slice( $found, 0, $limit );
				}
				$this->posts = 'ids' === ( $args['fields'] ?? '' ) ? $found : array_map( 'get_post', $found );
			}
		}
	}
	if ( ! function_exists( 'get_posts' ) ) {
		function get_posts( $args = array() ) {
			$args += array( 'posts_per_page' => 5 );
			return ( new WP_Query( $args ) )->posts;
		}
	}

	/** Gestores del núcleo simulados: tipos registrados y documentos con `get_elements_data()`. */
	class Kit_Stub_Manager {
		public $types;
		public function __construct( array $types ) {
			$this->types = $types;
		}
		public function get_widget_types( $name = null ) {
			return null === $name ? $this->types : ( $this->types[ $name ] ?? null );
		}
		public function get_element_types( $name = null ) {
			return null === $name ? $this->types : ( $this->types[ $name ] ?? null );
		}
		public function create_element_instance( $data ) {
			$type = ( $data['elType'] ?? '' ) === 'widget' ? ( $data['widgetType'] ?? '' ) : ( $data['elType'] ?? '' );
			return isset( $this->types[ $type ] ) ? $this->types[ $type ] : null;
		}
	}
	class Kit_Stub_Controls {
		public $controls;
		public function __construct( array $controls = array() ) {
			$this->controls = $controls;
		}
		public function get_controls() {
			return $this->controls;
		}
	}
	class Kit_Stub_Document {
		public $id;
		public function __construct( $id ) {
			$this->id = (int) $id;
		}
		public function get_main_id() {
			return $this->id;
		}
		public function get_name() {
			return 'wp-page';
		}
		public function get_elements_data() {
			$raw = get_post_meta( $this->id, '_elementor_data', true );
			return is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		}
		public function get_json_meta( $key ) {
			$raw = get_post_meta( $this->id, $key, true );
			$raw = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : $raw;
			return is_array( $raw ) ? $raw : array();
		}
		public function update_json_meta( $key, $value ) {
			return update_post_meta( $this->id, $key, json_encode( $value ) );
		}
	}
	class Kit_Stub_Documents {
		public function get( $id ) {
			return get_post( $id ) ? new Kit_Stub_Document( $id ) : false;
		}
	}
	class Kit_Stub_Kits {
		public $id;
		public function __construct( $id ) {
			$this->id = $id;
		}
		public function get_active_kit() {
			return new Kit_Stub_Document( $this->id );
		}
		public function get_active_id() {
			return $this->id;
		}
	}
}
