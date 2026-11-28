<?php
namespace Digitalisimo\Elements\Marca;

defined( 'ABSPATH' ) || exit;

/**
 * Exportaciones que el cliente descarga, con la marca propia en vigor (DECISIONES: «se renombran al salir y aceptan los
 * dos nombres al entrar»). El contenido del cliente no cambia; sólo los nombres internos que salen en el archivo.
 *
 * - **Plantilla suelta** (Plantillas → Exportar, `elementor_library_direct_actions` con `export_template`): el archivo
 *   sale como `dg-<id>-<fecha>.json` y la plantilla de página (`elementor_canvas`…) con su nombre propio
 *   (`digitalisimo_canvas`…). Se arma con los mismos métodos del núcleo (por reflexión, sin editarlo), con sus permisos y
 *   su nonce.
 * - **WXR de WordPress** (Herramientas → Exportar): el tipo `elementor_library`, sus taxonomías y las metas
 *   `_elementor_*` salen como `digitalisimo_library`, `digitalisimo_library_type` y `_dg_*`. Al importar (importador de
 *   WordPress) se aceptan los dos nombres, pero sólo si el archivo trae el tipo o una taxonomía con nombre propio
 *   (`digitalisimo_library`…) o con la marca en vigor (N-2 de la segunda revisión): con todo apagado, un WXR cualquiera
 *   entra como siempre y una meta ajena `_dg_data` de otro plugin no se convierte en `_elementor_data`.
 * - **Plantilla de página**: escribir `digitalisimo_canvas`, `digitalisimo_header_footer` o `digitalisimo_theme` en
 *   `_wp_page_template` guarda la del motor; así importar un archivo con el nombre nuevo deja la página igual.
 *
 * Pendiente (Fase 3): el ZIP de varias plantillas y los kits (sus archivos internos).
 */
final class Exports {
	const TEMPLATES = array( 'elementor_canvas', 'elementor_header_footer', 'elementor_theme' );

	/** @var bool ¿El WXR que se está importando trae nombres propios? (lo decide `wxr_scan()` en `import_start`) */
	private static $branded_file = false;

	public static function init(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		add_action( 'wp_ajax_elementor_library_direct_actions', array( __CLASS__, 'template_export' ), 1 );
		add_action( 'export_wp', array( __CLASS__, 'wxr_start' ) );
		add_action( 'import_start', array( __CLASS__, 'wxr_scan' ) );
		add_action( 'import_end', array( __CLASS__, 'wxr_scan_end' ) );
		add_filter( 'wp_import_post_data_raw', array( __CLASS__, 'wxr_import' ) );
		add_filter( 'update_post_metadata', array( __CLASS__, 'page_template' ), 10, 5 );
		add_filter( 'add_post_metadata', array( __CLASS__, 'page_template' ), 10, 5 );
	}

	/** `elementor_canvas` → `digitalisimo_canvas` (y las otras dos plantillas del motor). */
	public static function template_out( $value ) {
		return is_string( $value ) && in_array( $value, self::TEMPLATES, true ) ? 'digitalisimo_' . substr( $value, 10 ) : $value;
	}

	public static function template_in( $value ) {
		return is_string( $value ) && in_array( 'elementor_' . substr( $value, 13 ), self::TEMPLATES, true ) && 0 === strpos( $value, 'digitalisimo_' ) ? 'elementor_' . substr( $value, 13 ) : $value;
	}

	/** El JSON de una plantilla exportada con los nombres propios (sin tocar `content`, que es del cliente). */
	public static function template_json( string $json ): string {
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			return $json;
		}
		if ( isset( $data['page_settings'] ) && is_array( $data['page_settings'] ) ) {
			foreach ( $data['page_settings'] as $k => $v ) {
				$data['page_settings'][ $k ] = self::template_out( $v );
			}
		}
		$out = function_exists( 'wp_json_encode' ) ? wp_json_encode( $data ) : json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- sin WordPress (pruebas).
		return is_string( $out ) ? $out : $json;
	}

	/** Nombre del archivo: `elementor-12-2026-11-27.json` → `dg-12-2026-11-27.json`. */
	public static function file_name( string $name ): string {
		return (string) preg_replace( '/elementor/i', Transform::P, $name );
	}

	/**
	 * Plantilla suelta con la marca en vigor: los mismos pasos que `Manager::handle_direct_actions()` y
	 * `Source_Local::export_template()` (permisos, nonce, datos), con el nombre y la plantilla de página propios.
	 */
	public static function template_export(): void {
		$action = isset( $_REQUEST['library_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['library_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- el nonce se comprueba abajo, como en el núcleo.
		if ( 'export_template' !== $action || ! Toggle::active() || ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\Elementor\User' ) ) {
			return;
		}
		$source = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );
		$ajax   = \Elementor\Plugin::$instance->common ? \Elementor\Plugin::$instance->common->get_component( 'ajax' ) : null;
		if ( ! $source || ! $ajax || ! \Elementor\User::is_current_user_can_edit_post_type( 'elementor_library' ) || ! $ajax->verify_request_nonce() ) {
			return; // El núcleo responde como siempre.
		}
		$id = isset( $_REQUEST['template_id'] ) ? (int) $_REQUEST['template_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		try {
			$check = new \ReflectionMethod( $source, 'validate_template_export_permissions' );
			$check->setAccessible( true );
			$prepare = new \ReflectionMethod( $source, 'prepare_template_export' );
			$prepare->setAccessible( true );
			if ( is_wp_error( $check->invoke( $source, $id ) ) ) {
				return;
			}
			$file = $prepare->invoke( $source, $id );
		} catch ( \Throwable $e ) {
			return;
		}
		if ( ! is_array( $file ) || empty( $file['content'] ) ) {
			return;
		}
		$content = self::template_json( (string) $file['content'] );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . self::file_name( (string) $file['name'] ) );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . strlen( $content ) );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- archivo JSON descargable, como el del núcleo.
		die;
	}

	/** Herramientas → Exportar: el WXR pasa por `wxr()` con la marca en vigor. */
	public static function wxr_start(): void {
		if ( Toggle::active() ) {
			ob_start( array( __CLASS__, 'wxr' ) );
		}
	}

	/** Los nombres internos del WXR con nombre propio (tipo, taxonomías, metas y plantillas de página). */
	public static function wxr( $xml ) {
		if ( ! is_string( $xml ) || false === stripos( $xml, 'elementor' ) ) {
			return $xml;
		}
		$types = Map::types();
		$xml   = (string) preg_replace_callback(
			'#(<wp:post_type><!\[CDATA\[|<wp:term_taxonomy><!\[CDATA\[|<wp:category_nicename>|\sdomain=")(elementor_[a-z0-9_]+)#',
			static function ( $m ) use ( $types ) {
				return $m[1] . ( $types[ $m[2] ] ?? $m[2] );
			},
			$xml
		);
		$xml = (string) preg_replace_callback(
			'#(<wp:meta_key><!\[CDATA\[)_elementor_([a-z0-9_]+)#',
			static function ( $m ) {
				return Map::engine_meta( $m[2] ) ? $m[1] . Map::META_OUT . $m[2] : $m[0];
			},
			$xml
		);
		return (string) preg_replace_callback(
			'#(<wp:meta_key><!\[CDATA\[_wp_page_template\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[)(elementor_[a-z_]+)#',
			static function ( $m ) {
				return $m[1] . self::template_out( $m[2] );
			},
			$xml
		);
	}

	/**
	 * `import_start` del importador de WordPress (ya leyó el archivo en `$GLOBALS['wp_import']->posts`): ¿trae el tipo o
	 * una taxonomía del motor con su nombre propio? Sólo un WXR exportado con la marca en vigor los trae.
	 *
	 * @param array|null $posts Entradas del archivo (las del importador si es null).
	 */
	public static function wxr_scan( $posts = null ): void {
		if ( ! is_array( $posts ) ) {
			$importer = $GLOBALS['wp_import'] ?? null;
			$posts    = is_object( $importer ) && isset( $importer->posts ) && is_array( $importer->posts ) ? $importer->posts : array();
		}
		self::$branded_file = false;
		foreach ( $posts as $post ) {
			if ( is_array( $post ) && self::branded_post( $post ) ) {
				self::$branded_file = true;
				return;
			}
		}
	}

	/** `import_end`: el siguiente archivo se vuelve a mirar. */
	public static function wxr_scan_end(): void {
		self::$branded_file = false;
	}

	/** ¿La entrada lleva el tipo o una taxonomía del motor con nombre propio (`digitalisimo_library`…)? */
	private static function branded_post( array $post ): bool {
		$names = array( $post['post_type'] ?? '' );
		foreach ( (array) ( $post['terms'] ?? array() ) as $term ) {
			$names[] = is_array( $term ) ? ( $term['domain'] ?? '' ) : '';
		}
		foreach ( $names as $name ) {
			if ( is_string( $name ) && 0 === strpos( $name, 'digitalisimo_' ) && Map::in( $name ) !== $name ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Importador de WordPress: un WXR con los nombres propios (o los viejos) entra con los del motor. Sólo si el archivo
	 * los trae (`wxr_scan()`, o la propia entrada) o con la marca en vigor; si no, la entrada pasa sin tocar.
	 */
	public static function wxr_import( $post ) {
		if ( ! is_array( $post ) ) {
			return $post;
		}
		if ( ! self::$branded_file && ! Map::active() && ! self::branded_post( $post ) ) {
			return $post;
		}
		if ( isset( $post['post_type'] ) && is_string( $post['post_type'] ) ) {
			$post['post_type'] = Map::in( $post['post_type'] );
		}
		// Map::in() sólo devuelve al motor lo que la salida produce: los tipos y taxonomías registrados del motor y sus
		// metas (Map::METAS). Una meta ajena `_dg_<x>` entra igual, con la marca encendida o apagada.
		foreach ( array( 'terms', 'postmeta' ) as $list ) {
			if ( empty( $post[ $list ] ) || ! is_array( $post[ $list ] ) ) {
				continue;
			}
			foreach ( $post[ $list ] as $i => $item ) {
				if ( isset( $item['domain'] ) && is_string( $item['domain'] ) ) {
					$post[ $list ][ $i ]['domain'] = Map::in( $item['domain'] );
				}
				if ( isset( $item['key'] ) && is_string( $item['key'] ) ) {
					$post[ $list ][ $i ]['key'] = Map::in( $item['key'] );
					if ( '_wp_page_template' === $post[ $list ][ $i ]['key'] && isset( $item['value'] ) ) {
						$post[ $list ][ $i ]['value'] = self::template_in( $item['value'] );
					}
				}
			}
		}
		return $post;
	}

	/** `_wp_page_template` = `digitalisimo_canvas` → se guarda `elementor_canvas`. */
	public static function page_template( $check, $object_id, $meta_key, $meta_value, $prev = null ) {
		if ( '_wp_page_template' !== $meta_key || ! is_string( $meta_value ) || self::template_in( $meta_value ) === $meta_value ) {
			return $check;
		}
		$fn = 'add_post_metadata' === current_filter() ? 'add_post_meta' : 'update_post_meta';
		return (bool) $fn( (int) $object_id, $meta_key, self::template_in( $meta_value ) );
	}
}
