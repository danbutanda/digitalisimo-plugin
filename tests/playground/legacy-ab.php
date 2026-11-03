<?php
/**
 * A/B de compatibilidad con Element Pack en WordPress Playground.
 *
 * Fase A (Element Pack activo) y fase B (sólo Elements) renderizan los mismos documentos con los
 * casos de tests/legacy/bdt-*.json en dos sitios de una red y guardan una firma de contenido:
 * textos visibles, enlaces e imágenes. Uso: ver tests/playground/README.md.
 *
 * Se lanza por HTTP desde legacy-ab-run.mjs (`?phase=A&token=…&only=…&blog=…`, con el token de `token.txt`; F8, P10:
 * entre fases el navegador mide el estilo computado de las partes mapeadas) o, como antes, desde un paso `runPHP` con
 * `DIGI_PHASE`, `DIGI_ONLY` y `DIGI_BLOG`. Con la lista de widgets de la copia montada (`ep-widgets.txt`, la deja
 * ep-lite-pin.sh), sólo se prueban los casos de lo que esa copia registra: con Element Pack Lite 10.0.1, sus widgets y
 * sus extensiones.
 */
// Por HTTP sólo con el token de la corrida (la carpeta de resultados se monta en digi-ab).
$digi_ab_http = isset( $_SERVER['REQUEST_METHOD'], $_GET['phase'] ); // php-wasm declara la SAPI «cli» también en las peticiones HTTP.
if ( $digi_ab_http ) {
	$digi_ab_token = trim( (string) @file_get_contents( '/wordpress/wp-content/digi-ab/token.txt' ) );
	if ( '' === $digi_ab_token || ! hash_equals( $digi_ab_token, (string) ( $_GET['token'] ?? '' ) ) ) {
		http_response_code( 403 );
		exit;
	}
	foreach ( array( 'phase' => 'DIGI_PHASE', 'only' => 'DIGI_ONLY', 'blog' => 'DIGI_BLOG' ) as $digi_ab_key => $digi_ab_env ) {
		putenv( $digi_ab_env . '=' . preg_replace( '/[^A-Za-z0-9,_-]/', '', (string) ( $_GET[ $digi_ab_key ] ?? '' ) ) );
	}
	@set_time_limit( 0 );
}
// Todas las combinaciones se renderizan en un solo proceso: con Element Pack activo no bastan 256 MB.
ini_set( 'memory_limit', '2048M' );
// Como index.php en una visita real: algunos plugins (bbPress) sólo cargan sus plantillas con temas activos.
define( 'WP_USE_THEMES', true );
// Fase E: documentos ya convertidos, pintados con DIGITALÍSIMO Migración desactivado (paso previo del blueprint).
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( in_array( getenv( 'DIGI_PHASE' ), array( 'C', 'D' ), true ) ) {
	require_once WP_PLUGIN_DIR . '/digitalisimo-migration/legacy/class-migration.php';
}

$phase    = getenv( 'DIGI_PHASE' ) ?: 'A';
register_shutdown_function( static function () use ( $phase ) {
	$error = error_get_last();
	if ( $error && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		file_put_contents( '/wordpress/wp-content/digi-ab/fatal-' . $phase . '.txt', print_r( $error, true ) );
	}
} );
$out_dir  = '/wordpress/wp-content/digi-ab';
$fixtures = array();
// Lo que registra la copia montada de Element Pack (ep-lite-pin.sh): widgets `bdt-*` y carpetas de módulos. Sin las
// listas, todos los casos, como antes.
$ep_widgets = is_file( $out_dir . '/ep-widgets.txt' ) ? array_filter( array_map( 'trim', file( $out_dir . '/ep-widgets.txt' ) ) ) : null;
$ep_modules = is_file( $out_dir . '/ep-modules.txt' ) ? array_filter( array_map( 'trim', file( $out_dir . '/ep-modules.txt' ) ) ) : null;
$skipped    = array();
foreach ( array_merge( glob( '/wordpress/wp-content/digi-legacy-fixtures/bdt-*.json' ), glob( '/wordpress/wp-content/digi-legacy-fixtures/fooevents-*.json' ), glob( '/wordpress/wp-content/digi-legacy-fixtures/lightbox.json' ), glob( '/wordpress/wp-content/digi-legacy-fixtures/ext-*.json' ) ) as $file ) {
	$only = getenv( 'DIGI_ONLY' );
	$id   = basename( $file, '.json' );
	if ( $only && ! in_array( $id, explode( ',', $only ), true ) ) {
		continue;
	}
	if ( null !== $ep_widgets ) {
		$module = 0 === strpos( $id, 'ext-' ) ? substr( $id, 4 ) : $id;
		$known  = 0 === strpos( $id, 'bdt-' ) ? in_array( $id, $ep_widgets, true ) : in_array( $module, (array) $ep_modules, true );
		if ( ! $known ) {
			$skipped[] = $id;
			continue;
		}
	}
	$fixtures[ $id ] = json_decode( file_get_contents( $file ), true );
}

require_once __DIR__ . '/legacy-signature.php';

$result = array( 'phase' => $phase, 'element_pack' => defined( 'BDTEP_VER' ), 'element_pack_version' => defined( 'BDTEP_VER' ) ? BDTEP_VER : '', 'skipped' => $skipped, 'widgets' => array() );
// DIGI_BLOG limita la ejecución a un sitio: bbPress arrastra estado entre sitios dentro del mismo proceso.
$blogs = (int) getenv( 'DIGI_BLOG' ) > 0 ? array( (int) getenv( 'DIGI_BLOG' ) ) : array( 1, 2 );
foreach ( $blogs as $blog ) {
	switch_to_blog( $blog );
	update_option( 'elementor_element_cache_ttl', 'disable' );
	$registered = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
	foreach ( $fixtures as $widget => $cases ) {
		foreach ( $cases as $case => $settings ) {
			$expect_css  = (array) ( $settings['_expect_css'] ?? array() );
			$expect_html = (array) ( $settings['_expect_html'] ?? array() );
			// Casos de F8 (llevan `_expect_codes`: extensiones que la migración convierte, sin adaptador en el front).
			// `_expect_css` es el CSS que pinta Element Pack (fase A) y `_expect_css_converted`, el del documento
			// convertido (fases C y E); la fase B no tiene nada que comprobar (frente C, informe «I, J y K»).
			$f8_case = array_key_exists( '_expect_codes', $settings );
			if ( $f8_case ) {
				$expect_css = in_array( $phase, array( 'C', 'E' ), true ) ? (array) ( $settings['_expect_css_converted'] ?? array() ) : ( 'A' === $phase ? $expect_css : array() );
			}
			// Tras convertir, los nombres heredados pasan a los propios: la fixture puede declarar qué buscar entonces.
			if ( 'E' === $phase && ! $f8_case ) {
				$expect_css  = (array) ( $settings['_expect_css_converted'] ?? $expect_css );
				$expect_html = (array) ( $settings['_expect_html_converted'] ?? $expect_html );
			} elseif ( 'E' === $phase ) {
				$expect_html = (array) ( $settings['_expect_html_converted'] ?? $expect_html );
			}
			// Ajustes de la página del caso (extensiones de página: grid-line, custom-js…), como `_elementor_page_settings`.
			$page_settings = (array) ( $settings['_page'] ?? array() );
			$section     = (array) ( $settings['_section'] ?? array() );
			$known      = (array) ( $settings['_known'] ?? array() );
			$type       = (string) ( $settings['_widget'] ?? $widget );
			// Opciones del sitio sólo durante este caso (claves de API de Element Pack…).
			$options    = (array) ( $settings['_options'] ?? array() );
			$previous   = array();
			foreach ( $options as $option => $value ) {
				$previous[ $option ] = get_option( $option, null );
				update_option( $option, $value );
			}
			unset( $settings['_expect_css'], $settings['_expect_html'], $settings['_expect_css_converted'], $settings['_expect_html_converted'], $settings['_known'], $settings['_widget'], $settings['_section'], $settings['_options'], $settings['_page'], $settings['_expect_json'], $settings['_expect_codes'] );
			// Marcadores «__X__»: IDs del contenido de prueba de este sitio.
			$template = get_page_by_path( 'digi-ab-template', OBJECT, 'elementor_library' );
			$sample   = get_page_by_path( 'digi-ab-post-1', OBJECT, 'post' );
			$markers  = array( '__TEMPLATE__' => $template ? (string) $template->ID : '', '__POST__' => $sample ? (string) $sample->ID : '' ) + (array) get_option( 'digi_ab_markers', array() );
			array_walk_recursive( $settings, static function ( &$value ) use ( $markers ) {
				if ( is_string( $value ) && isset( $markers[ $value ] ) ) {
					$value = $markers[ $value ];
				}
			} );
			$acf_values = (array) ( $settings['_acf'] ?? array() );
			unset( $settings['_acf'] );
			$slug = 'digi-ab-' . $widget . '-' . $case;
			$page = get_page_by_path( $slug );
			$id   = $page ? $page->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
			// Valores de ACF de la página del caso (clave del campo => valor), con la API de ACF.
			if ( 'A' === $phase && function_exists( 'update_field' ) ) {
				foreach ( $acf_values as $field => $value ) {
					update_field( $field, $value, $id );
				}
			}
			if ( 'A' === $phase ) {
				$data = array( array( 'id' => 's' . substr( md5( $slug ), 0, 6 ), 'elType' => 'section', 'settings' => (object) $section, 'elements' => array( array( 'id' => 'c' . substr( md5( $slug ), 0, 6 ), 'elType' => 'column', 'settings' => array( '_column_size' => 100 ), 'elements' => array(
					array( 'id' => 'w' . substr( md5( $slug ), 0, 6 ), 'elType' => 'widget', 'widgetType' => $type, 'settings' => (object) $settings, 'elements' => array() ),
				) ) ) ) );
				update_post_meta( $id, '_elementor_edit_mode', 'builder' );
				update_post_meta( $id, '_elementor_template_type', 'wp-page' );
				update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
				update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
				if ( $page_settings ) {
					update_post_meta( $id, '_elementor_page_settings', wp_slash( $page_settings ) );
				} else {
					delete_post_meta( $id, '_elementor_page_settings' );
				}
			}
			$entry_extra = array();
			if ( 'C' === $phase ) {
				// `Native::$errors` ya no existe en 1.0.21: sólo se lee si la versión montada lo tiene.
				$has_errors = property_exists( '\\Digitalisimo\\Elements\\Legacy\\Native', 'errors' );
				if ( $has_errors ) {
					\Digitalisimo\Elements\Legacy\Native::$errors = array();
				}
				$entry_extra['migration'] = \Digitalisimo\Elements\Legacy\Migration::convert_document( $id );
				$entry_extra['native_errors'] = $has_errors ? \Digitalisimo\Elements\Legacy\Native::$errors : array();
				$entry_extra['converted'] = json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true );
			}
			if ( 'D' === $phase ) {
				$entry_extra['reverted'] = \Digitalisimo\Elements\Legacy\Migration::revert_document( $id );
				$entry_extra['data_md5'] = md5( (string) get_post_meta( $id, '_elementor_data', true ) );
				$entry_extra['page_md5'] = md5( maybe_serialize( get_post_meta( $id, '_elementor_page_settings', true ) ) );
			}
			if ( 'A' === $phase ) {
				$entry_extra['data_md5'] = md5( (string) get_post_meta( $id, '_elementor_data', true ) );
				$entry_extra['page_md5'] = md5( maybe_serialize( get_post_meta( $id, '_elementor_page_settings', true ) ) );
			}
			if ( 'E' === $phase ) {
				// Tras convertir no debe quedar ningún identificador heredado en el documento.
				$saved = (string) get_post_meta( $id, '_elementor_data', true );
				// Los ajustes de la página también (extensiones de página de F8).
				$saved .= wp_json_encode( get_post_meta( $id, '_elementor_page_settings', true ) );
				preg_match_all( '/"widgetType":"(?:bdt-[a-z0-9-]+|fooevents-calendar|lightbox)"|"(?:ep_|element_pack_|bdt_|_digitalisimo_legacy|image_mask_)[a-z0-9_]*"|"_mask_shape":"shape-\d+"|"shape_divider_(?:top|bottom)":"ep-[a-z0-9-]+"/', $saved, $left );
				$entry_extra['legacy_left'] = array_values( array_unique( $left[0] ) );
			}
			$GLOBALS['wp_query']     = new WP_Query( array( 'page_id' => $id ) );
			$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
			$GLOBALS['post']         = get_post( $id );
			setup_postdata( $GLOBALS['post'] );
			delete_post_meta( $id, '_elementor_element_cache' );
			$html  = \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
			$entry = array( 'registered' => isset( $registered[ $type ] ), 'signature' => digi_ab_signature( $html ), 'bytes' => strlen( $html ), 'known' => $known, 'page_id' => (int) $id, 'url' => (string) get_permalink( $id ) ) + $entry_extra;
			// Fase A de un caso de F8: el CSS que pinta Element Pack (con su extensión cargada) debe tener `_expect_css`.
			if ( 'A' === $phase && $f8_case && $expect_css ) {
				try {
					// Documento recién escrito: se vuelve a leer sin la caché antes de generar su CSS (como en la fase B).
					\Elementor\Plugin::$instance->documents->get( $id, false );
					$css = \Elementor\Core\Files\CSS\Post::create( $id );
					$css->update();
					$content = preg_replace( '/\s+/', '', (string) $css->get_content() );
					$entry['css_missing'] = array_values( array_filter( $expect_css, static function ( $fragment ) use ( $content ) {
						return false === strpos( $content, preg_replace( '/\s+/', '', $fragment ) );
					} ) );
				} catch ( \Throwable $e ) {
					$entry['css_error'] = $e->getMessage();
				}
			}
			if ( in_array( $phase, array( 'B', 'C', 'E' ), true ) ) {
				$raw = \Elementor\Plugin::$instance->documents->get( $id, false )->get_elements_raw_data( null, true );
				$entry['editor_settings'] = array_keys( (array) ( $raw[0]['elements'][0]['elements'][0]['settings'] ?? array() ) );
				$entry['editor_widget']   = $raw[0]['elements'][0]['elements'][0]['widgetType'] ?? '';
				// El envoltorio se presenta como el widget de destino para su CSS y sus scripts.
				$target = class_exists( '\\Digitalisimo\\Elements\\Legacy\\Translator' ) && \Digitalisimo\Elements\Legacy\Translator::is_legacy_id( $type ) ? \Digitalisimo\Elements\Legacy\Translator::target( $type ) : '';
				// Sin contenido Elementor no pinta el envoltorio: no hay nada que comprobar.
				if ( '' !== $target && 'B' === $phase && false !== strpos( $html, 'elementor-element-w' . substr( md5( $slug ), 0, 6 ) ) ) {
					$entry['wrapper_ok'] = false !== strpos( $html, 'data-widget_type="' . $target . '.' ) && false !== strpos( $html, 'elementor-widget-' . $target );
				}
				// Guardado desde el editor: lo que el editor carga se vuelve a guardar y la página debe seguir igual,
				// con el widget heredado aún en el documento (la migración es otra acción, siempre explícita).
				// Se guarda sobre la misma página y después se restaura su `_elementor_data` original, para que las
				// fases C y D sigan partiendo del documento tal y como lo dejó Element Pack.
				if ( 'B' === $phase && $entry['registered'] ) {
					wp_set_current_user( 1 );
					$original = (string) get_post_meta( $id, '_elementor_data', true );
					// El guardado del editor reescribe también los ajustes de la página (aquí, vacíos): se conservan
					// los del caso para las fases C, E y D (extensiones de página de F8).
					$original_page = get_post_meta( $id, '_elementor_page_settings', true );
					try {
						$document = \Elementor\Plugin::$instance->documents->get( $id, false );
						\Elementor\Plugin::$instance->documents->ajax_save( array( 'editor_post_id' => $id, 'status' => 'publish', 'elements' => $document->get_elements_raw_data(), 'settings' => array() ) );
						// Sólo el guardado necesita sesión: la página se vuelve a pintar como visitante, igual que antes.
						wp_set_current_user( 0 );
						delete_post_meta( $id, '_elementor_element_cache' );
						$saved_html = \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
						$same       = digi_ab_signature( $saved_html ) === $entry['signature'];
						$kept       = false !== strpos( (string) get_post_meta( $id, '_elementor_data', true ), '"widgetType":"' . $type . '"' );
						$entry['editor_resave'] = array( 'ok' => $same && $kept, 'same_output' => $same, 'widget_kept' => $kept );
					} catch ( \Throwable $e ) {
						$entry['editor_resave'] = array( 'ok' => false, 'error' => $e->getMessage() );
					}
					update_post_meta( $id, '_elementor_data', wp_slash( $original ) );
					if ( is_array( $original_page ) && $original_page ) {
						update_post_meta( $id, '_elementor_page_settings', wp_slash( $original_page ) );
					} else {
						delete_post_meta( $id, '_elementor_page_settings' );
					}
					delete_post_meta( $id, '_elementor_element_cache' );
					delete_post_meta( $id, '_elementor_css' );
					// Las páginas se comparan como visitante: formularios de acceso y de bbPress cambian con sesión.
					wp_set_current_user( 0 );
				}
				if ( $expect_html ) {
					$entry['html_missing'] = array_values( array_filter( $expect_html, static function ( $fragment ) use ( $html ) {
						return false === strpos( $html, $fragment );
					} ) );
				}
				if ( $expect_css ) {
					$css = \Elementor\Core\Files\CSS\Post::create( $id );
					$css->update();
					$content = preg_replace( '/\s+/', '', (string) $css->get_content() );
					$entry['css_missing'] = array_values( array_filter( $expect_css, static function ( $fragment ) use ( $content ) {
						return false === strpos( $content, preg_replace( '/\s+/', '', $fragment ) );
					} ) );
				}
			}
			$result['widgets'][ $widget ][ $case ][ $blog ] = $entry;
			foreach ( $previous as $option => $value ) {
				null === $value ? delete_option( $option ) : update_option( $option, $value );
			}
			wp_reset_postdata();
		}
	}
	restore_current_blog();
}
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir );
}
file_put_contents( $out_dir . '/phase' . $phase . '.json', wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
