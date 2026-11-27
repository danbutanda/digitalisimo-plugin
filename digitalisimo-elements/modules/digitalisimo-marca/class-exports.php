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
 *   WordPress) se aceptan los dos nombres.
 * - **Plantilla de página**: escribir `digitalisimo_canvas`, `digitalisimo_header_footer` o `digitalisimo_theme` en
 *   `_wp_page_template` guarda la del motor; así importar un archivo con el nombre nuevo deja la página igual.
 *
 * Pendiente (Fase 3): el ZIP de varias plantillas y los kits (sus archivos internos).
 */
final class Exports {
	const TEMPLATES = array( 'elementor_canvas', 'elementor_header_footer', 'elementor_theme' );

	public static function init(): void {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		add_action( 'wp_ajax_elementor_library_direct_actions', array( __CLASS__, 'template_export' ), 1 );
		add_action( 'export_wp', array( __CLASS__, 'wxr_start' ) );
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
		$xml = (string) preg_replace( '#(<wp:meta_key><!\[CDATA\[)_elementor_#', '$1' . Map::META_OUT, $xml );
		return (string) preg_replace_callback(
			'#(<wp:meta_key><!\[CDATA\[_wp_page_template\]\]></wp:meta_key>\s*<wp:meta_value><!\[CDATA\[)(elementor_[a-z_]+)#',
			static function ( $m ) {
				return $m[1] . self::template_out( $m[2] );
			},
			$xml
		);
	}

	/** Importador de WordPress: un WXR con los nombres propios (o los viejos) entra con los del motor. */
	public static function wxr_import( $post ) {
		if ( ! is_array( $post ) ) {
			return $post;
		}
		if ( isset( $post['post_type'] ) && is_string( $post['post_type'] ) ) {
			$post['post_type'] = Map::in( $post['post_type'] );
		}
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
