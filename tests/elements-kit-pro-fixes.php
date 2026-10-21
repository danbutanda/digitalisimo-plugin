<?php
/**
 * Kits (etapa K, §13.1 «pro-fixes»): las once correcciones de §7.8 en los derivados de PRO y en archivos propios.
 *
 * Sin WordPress: tests/sources/kits/wp-stubs.php y los archivos reales de digitalisimo-elements/ (derivados de PRO y
 * núcleo incluido). Lo que necesita el constructor completo se sustituye por stubs mínimos (documentos, módulos).
 *  #1 Fuentes: font_face del kit nunca se copia; se regenera desde los archivos locales; duplicados por procedencia.
 *  #2 Adjuntos externos: se descargan por la API HTTP (el proxy de K) y quedan locales; si falla, se omite e informa.
 *  #3 Iconos: icon_set_path del kit se ignora; un conjunto sin archivos locales se omite; nunca URL externas.
 *  #4 Custom_Icons: rmdir sólo dentro de uploads/elementor/custom-icons.
 *  #5 Custom Code: exportación e importación simétricas (ubicación, prioridad, código, reglas y condiciones);
 *     siempre en borrador; sin unfiltered_html no se importa; duplicados por procedencia.
 *  #6 Theme Builder y #7 widget global: reutilizan lo que el mismo kit ya importó (digitalisimo_elements_kit_existing_document).
 *  #8 Webhook: on_export() quita la URL (también dinámica).
 *  #9 Delegado de ajustes de PRO: nunca llama a import_theme() ni a import_experiments().
 * #10 is_high_tier() → true y plan_type = expert sólo en la App.
 * #11 Duplicador: la copia no lleva la sesión del importador, la procedencia ni la caché de elementos.
 */

namespace Elementor {
	class Plugin {
		public static $instance;
		public $documents;
		public $app;
		public $templates_manager;
	}
}

namespace ElementorPro {
	/** Sólo lo que usan los importadores de PRO: el constructor activo. */
	class Plugin {
		public static function elementor() {
			return \Elementor\Plugin::$instance;
		}
	}
}

namespace ElementorPro\Modules\CustomCode {
	class Module {
		const CPT = 'elementor_snippet';
	}
}

namespace ElementorPro\Modules\GlobalWidget {
	class Module {
		const TEMPLATE_TYPE = 'widget';
	}
}

namespace {
	require __DIR__ . '/sources/kits/wp-stubs.php';
	$E = __DIR__ . '/../digitalisimo-elements';

	// ── Funciones de medios y HTTP que usan los derivados ───────────────────────────────────────────────────────
	$GLOBALS['kt_remote'] = array();
	$GLOBALS['kt_http']   = array();
	class WP_Query {
		public $posts = array();
		public function __construct( $args = array() ) { $this->posts = get_posts( $args ); }
		public function have_posts() { return (bool) $this->posts; }
	}
	function get_the_title( $id = 0 ) { $post = get_post( $id ); return $post ? $post->post_title : ''; }
	/** Términos de un objeto: sólo lo que comprueba la prueba (el tipo de fuente). */
	function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) { $GLOBALS['kt_object_terms'][ (int) $object_id ][ $taxonomy ] = (array) $terms; return array(); }
	function wp_safe_remote_get( $url, $args = array() ) {
		$GLOBALS['kt_http'][] = $url;
		$pre = apply_filters( 'pre_http_request', false, $args, $url );
		if ( false !== $pre ) {
			return $pre;
		}
		if ( ! isset( $GLOBALS['kt_remote'][ $url ] ) ) {
			return new WP_Error( 'http_request_failed', 'sin red en la prueba' );
		}
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => $GLOBALS['kt_remote'][ $url ], 'headers' => array() );
	}
	function wp_remote_retrieve_response_code( $response ) { return is_array( $response ) ? (int) $response['response']['code'] : ''; }
	function wp_remote_retrieve_body( $response ) { return is_array( $response ) ? (string) $response['body'] : ''; }
	function sanitize_file_name( $name ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $name ); }
	function wp_basename( $path, $suffix = '' ) { return basename( (string) $path, $suffix ); }
	function wp_normalize_path( $path ) { return str_replace( '\\', '/', (string) $path ); }
	function wp_upload_bits( $name, $deprecated, $bits ) {
		$file = $GLOBALS['kt']['uploads'] . '/' . $name;
		file_put_contents( $file, $bits );
		return array( 'file' => $file, 'url' => home_url( '/wp-content/uploads/' . $name ), 'error' => false );
	}
	function wp_check_filetype( $file, $mimes = null ) {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		$map = array( 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'css' => 'text/css', 'json' => 'application/json', 'svg' => 'image/svg+xml', 'png' => 'image/png' );
		return array( 'ext' => $ext, 'type' => $map[ $ext ] ?? false );
	}
	function wp_insert_attachment( $args, $file = false, $parent = 0, $wp_error = false ) {
		$id = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_parent' => $parent ) + $args );
		update_post_meta( $id, '_wp_attached_file', $file );
		return $id;
	}
	function wp_delete_file( $file ) { @unlink( $file ); } // phpcs:ignore
	function get_attached_file( $id ) { return (string) get_post_meta( $id, '_wp_attached_file', true ); }
	function wp_get_attachment_url( $id ) {
		$file = get_attached_file( $id );
		return '' === $file ? false : home_url( '/wp-content/uploads/' . ltrim( substr( $file, strlen( $GLOBALS['kt']['uploads'] ) ), '/' ) );
	}
	function get_attached_media( $type, $parent ) {
		return array_values( array_filter( get_posts( array( 'post_type' => 'attachment' ) ), static function ( $p ) use ( $parent ) { return (int) $p->post_parent === (int) $parent; } ) );
	}
	function wp_delete_attachment( $id, $force = false ) { unset( $GLOBALS['kt']['posts'][ (int) $id ] ); return true; }
	function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['kt']['posts'][ (int) $id ], $GLOBALS['kt']['meta'][ (int) $id ] ); return true; }
	function get_post_type_object( $type ) { return post_type_exists( $type ) ? (object) array( 'name' => $type, 'show_ui' => true, 'cap' => (object) array( 'create_posts' => 'edit_posts' ) ) : null; }
	function get_object_taxonomies( $type ) { return array(); }
	// WP_Filesystem directo, sólo dentro de los temporales de la prueba.
	$GLOBALS['wp_filesystem'] = new class() {
		public function rmdir( $path, $recursive = false ) { kt_rmdir( rtrim( $path, '/' ) ); return ! is_dir( $path ); }
		public function is_dir( $path ) { return is_dir( $path ); }
		public function mkdir( $path ) { return wp_mkdir_p( $path ); }
		public function put_contents( $file, $content ) { return false !== file_put_contents( $file, $content ); }
		public function move( $from, $to ) { return rename( $from, $to ); }
	};

	$documents = new class() {
		public $created = array();
		public function create( $type, $args = array(), $meta = array() ) {
			$id              = wp_insert_post( array( 'post_type' => $args['post_type'] ?? 'elementor_library', 'post_status' => $args['post_status'] ?? 'publish', 'post_title' => $args['post_title'] ?? '' ) );
			$this->created[] = array( $type, $id );
			return new class( $id ) {
				private $id;
				public function __construct( $id ) { $this->id = $id; }
				public function import( $data ) {}
				public function get_main_id() { return $this->id; }
			};
		}
		public function get_document_types( $args = array() ) { return array( 'header' => true ); }
	};
	\Elementor\Plugin::$instance                    = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->documents         = $documents;
	\Elementor\Plugin::$instance->templates_manager = null;

	require $E . '/modules/digitalisimo-import/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();
	use Digitalisimo\Elements\Import\Module;
	use Digitalisimo\Elements\Import\Kits\Context;
	use Digitalisimo\Elements\Import\Kits\Session;

	$session_id = 'kpro000000001';
	$activate   = static function ( array $extra = array() ) use ( $session_id ) {
		Context::deactivate();
		Session::forget( $session_id );
		update_option( Session::option( $session_id ), array_replace( array( 'v' => 1, 'mode' => Module::MODE_ADD, 'user' => 1, 'max_post_id' => 0, 'origin' => array( 'kit' => sha1( 'kit-pro' ), 'home' => 'http://demo-autor.test', 'site' => 'http://demo-autor.test', 'uploads' => 'http://demo-autor.test/wp-content/uploads' ), 'report' => array( 'entries' => array(), 'counts' => array(), 'dropped' => 0 ) ), $extra ) );
		Context::activate( $session_id );
	};
	$report_codes = static function () {
		$notes = Context::notes();
		return array_keys( $notes['report']['counts'] ?? array() );
	};
	$extracted = static function ( array $files ) {
		$dir = $GLOBALS['kt']['root'] . '/kit-' . substr( md5( serialize( $files ) . mt_rand() ), 0, 6 ) . '/';
		mkdir( $dir, 0777, true );
		foreach ( $files as $name => $content ) {
			file_put_contents( $dir . $name, json_encode( $content ) );
		}
		return $dir;
	};

	// ── #1 y #2 Fuentes ─────────────────────────────────────────────────────────────────────────────────────────
	$font_url  = 'http://demo-autor.test/wp-content/uploads/acme-sans.woff2';
	$GLOBALS['kt_remote'][ $font_url ] = "wOF2\0\1\0\0fuente-sintética";
	$font_kit  = array( array(
		'ID'          => 901,
		'post_title'  => "Acme Sans</style><script>alert(1)</script>",
		'post_status' => 'publish',
		'font_files'  => array( array( 'font_weight' => '400', 'font_style' => 'normal', 'woff2' => array( 'id' => '902', 'url' => $font_url ), 'ttf' => array( 'id' => '903', 'url' => 'http://demo-autor.test/wp-content/uploads/sin-archivo.ttf' ) ) ),
		'font_face'   => "@font-face{font-family:'Acme Sans';src:url('{$font_url}')}</style><script>document.title='hostil'</script><style>",
		'attachments' => array( array( 'id' => 902, 'title' => 'acme-sans', 'url' => $font_url ), array( 'id' => 903, 'title' => 'sin-archivo', 'url' => 'http://demo-autor.test/wp-content/uploads/sin-archivo.ttf' ) ),
	) );
	$runner_class = '\ElementorPro\Modules\AssetsManager\AssetTypes\Fonts\ImportExportCustomization\Import_Runner';
	kt_check( class_exists( $runner_class ), 'Falta el runner de importación de fuentes de PRO.' );
	$activate();
	$dir    = $extracted( array( 'custom-fonts.json' => $font_kit ) );
	$runner = new $runner_class();
	$runner->import( array( 'session_id' => $session_id, 'include' => array( 'settings' ), 'customization' => array(), 'extracted_directory_path' => $dir ), array() );
	$fonts = get_posts( array( 'post_type' => 'elementor_font', 'post_status' => 'any' ) );
	kt_check( 1 === count( $fonts ), '#1: la fuente del kit se crea una vez.' );
	$font  = $fonts[0];
	$face  = (string) get_post_meta( $font->ID, 'elementor_font_face', true );
	$files = get_post_meta( $font->ID, 'elementor_font_files', true );
	kt_check( false === stripos( $face, '<script' ) && false === stripos( $face, '</style' ) && false === stripos( $font->post_title, '<' ), '#1: font_face y el título del kit nunca se copian con HTML (XSS almacenado).' );
	kt_check( false === strpos( $face, 'demo-autor.test' ) && false !== strpos( $face, 'https://b.test/wp-content/uploads/' ), '#1/#2: font_face se regenera con la URL del adjunto local, nunca la del autor.' );
	kt_check( is_array( $files ) && 1 === count( $files ) && isset( $files[0]['woff2']['id'] ) && ! isset( $files[0]['ttf'] ), '#2: sólo viajan los archivos que se pudieron traer como adjuntos locales; el que falló se omite.' );
	kt_check( 'attachment' === get_post_type( (int) $files[0]['woff2']['id'] ) && is_file( get_attached_file( (int) $files[0]['woff2']['id'] ) ), '#2: el archivo de la fuente es un adjunto local del sitio.' );
	kt_check( in_array( 'assets.attachment_failed', $report_codes(), true ), '#2: el archivo que no se pudo descargar queda en el informe.' );
	kt_check( array( 'custom' ) === ( $GLOBALS['kt_object_terms'][ (int) $font->ID ]['elementor_font_type'] ?? null ), '#1: la fuente importada lleva su tipo (`elementor_font_type = custom`): sin él, el gestor no la lista ni la carga.' );
	// Duplicado por procedencia (D7, CC-5): si el mismo kit ya la importó, no se crea otra (en cualquier estado).
	add_filter( 'digitalisimo_elements_kit_existing_document', $reuse = static function ( $id, $type ) use ( $font ) { return 'elementor_font' === $type ? (int) $font->ID : $id; }, 50, 2 );
	wp_update_post( array( 'ID' => $font->ID, 'post_status' => 'draft' ) );
	( new $runner_class() )->import( array( 'session_id' => $session_id, 'include' => array( 'settings' ), 'customization' => array(), 'extracted_directory_path' => $dir ), array() );
	kt_check( 1 === count( get_posts( array( 'post_type' => 'elementor_font', 'post_status' => 'any' ) ) ), '#1: una fuente ya importada por el mismo kit (aunque esté en borrador) no se duplica.' );
	remove_filter( 'digitalisimo_elements_kit_existing_document', $reuse, 50 );
	Context::deactivate();

	// ── #3 Iconos ───────────────────────────────────────────────────────────────────────────────────────────────
	$icons_class = '\ElementorPro\Modules\AssetsManager\AssetTypes\Icons\ImportExportCustomization\Import_Runner';
	kt_check( class_exists( $icons_class ), 'Falta el runner de importación de iconos de PRO.' );
	$activate();
	$GLOBALS['kt_http'] = array();
	$dir                = $extracted( array( 'custom-icons.json' => array( array(
		'ID'              => 911,
		'post_title'      => 'acme-iconos',
		'post_status'     => 'publish',
		'icon_set_config' => json_encode( array( 'name' => 'acme-iconos', 'url' => 'http://cdn.demo-autor.test/acme.css', 'enqueue' => array( 'http://cdn.demo-autor.test/extra.css' ), 'fetchJson' => 'http://cdn.demo-autor.test/icons.js', 'prefix' => 'acme-', 'icons' => array( 'uno' ) ) ),
		'icon_set_path'   => '/etc/',
		'attachments'     => array(),
	) ) ) );
	( new $icons_class() )->import( array( 'session_id' => $session_id, 'include' => array( 'settings' ), 'customization' => array(), 'extracted_directory_path' => $dir ), array() );
	$sets = get_posts( array( 'post_type' => 'elementor_icons', 'post_status' => 'any' ) );
	$all  = json_encode( array( $GLOBALS['kt']['meta'], $GLOBALS['kt']['options'] ) );
	kt_check( array() === $sets, '#3: un conjunto sin archivos locales se omite (no queda una entrada a medias).' );
	kt_check( false === strpos( $all, 'cdn.demo-autor.test' ) && false === strpos( $all, '"\/etc\/"' ), '#3: nunca se guardan url, enqueue ni fetchJson externos, ni la ruta icon_set_path del kit (CR-8).' );
	kt_check( in_array( 'icons.skipped', $report_codes(), true ), '#3: el conjunto omitido queda en el informe.' );
	kt_check( ! array_filter( $GLOBALS['kt_http'], static function ( $u ) { return false !== strpos( $u, 'cdn.demo-autor.test' ); } ), '#3: las URL de la configuración del kit ni siquiera se piden.' );
	Context::deactivate();

	// ── #4 Custom_Icons::is_icon_set_dir ────────────────────────────────────────────────────────────────────────
	$icons_root = $GLOBALS['kt']['uploads'] . '/elementor/custom-icons';
	mkdir( $icons_root . '/mi-set', 0777, true );
	mkdir( $GLOBALS['kt']['root'] . '/ajena', 0777, true );
	$custom_icons = '\ElementorPro\Modules\AssetsManager\AssetTypes\Icons\Custom_Icons';
	kt_check( method_exists( $custom_icons, 'is_icon_set_dir' ), '#4: falta Custom_Icons::is_icon_set_dir().' );
	kt_check( $custom_icons::is_icon_set_dir( $icons_root . '/mi-set' ), '#4: una carpeta de conjunto dentro de custom-icons se puede borrar.' );
	foreach ( array( $icons_root, $icons_root . '/', $icons_root . '/mi-set/../..', $GLOBALS['kt']['root'] . '/ajena', '/etc', '/', '' ) as $bad ) {
		kt_check( ! $custom_icons::is_icon_set_dir( $bad ), "#4: «{$bad}» nunca se borra (fuera de uploads/elementor/custom-icons o su raíz)." );
	}
	if ( function_exists( 'symlink' ) && @symlink( $GLOBALS['kt']['root'] . '/ajena', $icons_root . '/enlace' ) ) { // phpcs:ignore
		kt_check( ! $custom_icons::is_icon_set_dir( $icons_root . '/enlace' ), '#4: un enlace dentro de custom-icons que sale de ella no se borra.' );
	}
	$source = (string) file_get_contents( $E . '/modules/assets-manager/asset-types/icons/custom-icons.php' );
	kt_check( 1 === preg_match( '/is_dir\(\s*\$icon_set_dir\s*\)\s*&&\s*self::is_icon_set_dir\(\s*\$icon_set_dir\s*\)/', $source ), '#4: el borrado del conjunto (rmdir) pasa por is_icon_set_dir().' );

	// ── #5 Custom Code ──────────────────────────────────────────────────────────────────────────────────────────
	kt_reset();
	\Elementor\Plugin::$instance->documents = $documents;
	$export_class = '\ElementorPro\Modules\CustomCode\ImportExportCustomization\Export_Runner';
	$import_class = '\ElementorPro\Modules\CustomCode\ImportExportCustomization\Import_Runner';
	$snippet      = wp_insert_post( array( 'post_type' => 'elementor_snippet', 'post_status' => 'publish', 'post_title' => 'Analítica' ) );
	$fields       = array(
		'_elementor_location'        => 'elementor_body_end',
		'_elementor_priority'        => '7',
		'_elementor_code'            => '<script>console.log("k")</script>',
		'_elementor_extra_options'   => array( 'ensure_jquery' ),
		'_elementor_conditions'      => array( 'include/singular/page/12' ),
		'_elementor_device_mode'     => array( 'desktop' ),
		'_elementor_user_roles'      => array( 'administrator' ),
		'_elementor_site_languages'  => array( 'es_MX' ),
		'_elementor_url_conditions'  => array( array( 'url' => '/tienda' ) ),
		'_elementor_date_conditions' => array( 'from' => '2026-01-01' ),
		'_elementor_time_conditions' => array( 'from' => '08:00' ),
	);
	foreach ( $fields as $key => $value ) {
		update_post_meta( $snippet, $key, $value );
	}
	$prepare = new ReflectionMethod( $export_class, 'prepare_snippet_data' );
	$data    = $prepare->invoke( new $export_class(), get_post( $snippet ) );
	foreach ( array( 'location' => 'elementor_body_end', 'priority' => '7', 'code' => '<script>console.log("k")</script>', 'conditions' => array( 'include/singular/page/12' ), 'device_mode' => array( 'desktop' ), 'user_roles' => array( 'administrator' ), 'site_languages' => array( 'es_MX' ), 'url_conditions' => array( array( 'url' => '/tienda' ) ), 'date_conditions' => array( 'from' => '2026-01-01' ), 'time_conditions' => array( 'from' => '08:00' ) ) as $key => $want ) {
		kt_check( isset( $data[ $key ] ) && $want === $data[ $key ], "#5: la exportación de Custom Code lleva «{$key}» (CC-3)." );
	}
	$activate( array( 'mode' => Module::MODE_FRESH ) );
	$dir = $extracted( array( 'custom-code.json' => array( $data, array( 'ID' => 99, 'post_title' => 'Antiguo', 'post_status' => 'publish', 'post_content' => '<!-- kit antiguo -->', 'location' => 'en-cualquier-parte', 'priority' => '999' ) ) ) );
	( new $import_class() )->import( array( 'session_id' => $session_id, 'include' => array( 'settings' ), 'customization' => array(), 'extracted_directory_path' => $dir ), array() );
	$imported = array_values( array_filter( get_posts( array( 'post_type' => 'elementor_snippet', 'post_status' => 'any' ) ), static function ( $p ) use ( $snippet ) { return $p->ID !== $snippet; } ) );
	kt_check( 2 === count( $imported ), '#5: los fragmentos del kit se importan.' );
	$new = $imported[0];
	kt_check( 'draft' === $new->post_status && 'draft' === $imported[1]->post_status, '#5: un fragmento importado queda siempre en borrador, también en «nuevo» (D8).' );
	foreach ( array( '_elementor_location' => 'elementor_body_end', '_elementor_priority' => '7', '_elementor_code' => '<script>console.log("k")</script>', '_elementor_device_mode' => array( 'desktop' ), '_elementor_user_roles' => array( 'administrator' ), '_elementor_site_languages' => array( 'es_MX' ) ) as $key => $want ) {
		kt_check( $want === get_post_meta( $new->ID, $key, true ), "#5: la importación de Custom Code guarda «{$key}» (simétrica con la exportación)." );
	}
	kt_check( '' === get_post_meta( $new->ID, '_elementor_conditions', true ) && array( 'include/singular/page/12' ) === get_post_meta( $new->ID, '_digitalisimo_import_conditions', true ), '#5: las condiciones quedan pendientes y se traducen cerradas al final (§9.5), nunca con los IDs del origen.' );
	kt_check( 'elementor_head' === get_post_meta( $imported[1]->ID, '_elementor_location', true ) && '10' === get_post_meta( $imported[1]->ID, '_elementor_priority', true ) && '<!-- kit antiguo -->' === get_post_meta( $imported[1]->ID, '_elementor_code', true ), '#5: una ubicación desconocida y una prioridad fuera de rango se normalizan; el código de un kit antiguo viene de post_content.' );
	Context::deactivate();
	$before = count( get_posts( array( 'post_type' => 'elementor_snippet', 'post_status' => 'any' ) ) );
	$GLOBALS['kt']['caps'][1] = array_values( array_diff( $GLOBALS['kt']['caps'][1], array( 'unfiltered_html' ) ) );
	$activate();
	( new $import_class() )->import( array( 'session_id' => $session_id, 'include' => array( 'settings' ), 'customization' => array(), 'extracted_directory_path' => $dir ), array() );
	kt_check( $before === count( get_posts( array( 'post_type' => 'elementor_snippet', 'post_status' => 'any' ) ) ) && in_array( 'custom_code.skipped', $report_codes(), true ), '#5: sin unfiltered_html no se importa ningún fragmento y queda en el informe (A9).' );
	Context::deactivate();
	$GLOBALS['kt']['caps'][1][] = 'unfiltered_html';
	add_filter( 'digitalisimo_elements_kit_existing_document', $reuse = static function ( $id, $type ) use ( $new ) { return 'elementor_snippet' === $type ? (int) $new->ID : $id; }, 50, 2 );
	$activate();
	( new $import_class() )->import( array( 'session_id' => $session_id, 'include' => array( 'settings' ), 'customization' => array(), 'extracted_directory_path' => $dir ), array() );
	kt_check( $before === count( get_posts( array( 'post_type' => 'elementor_snippet', 'post_status' => 'any' ) ) ), '#5: un fragmento ya importado por el mismo kit no se duplica (por procedencia, no por título publicado).' );
	remove_filter( 'digitalisimo_elements_kit_existing_document', $reuse, 50 );
	Context::deactivate();

	// ── #6 y #7 Theme Builder y widget global ───────────────────────────────────────────────────────────────────
	foreach ( array( array( '\ElementorPro\Modules\ThemeBuilder\ImportExportCustomization\Import', 'import_theme_builder_template', 'header' ), array( '\ElementorPro\Modules\GlobalWidget\ImportExportCustomization\Import', 'import_global_widget', 'widget' ) ) as list( $class, $method, $type ) ) {
		kt_check( class_exists( $class ), "Falta {$class}." );
		$reflection = new ReflectionMethod( $class, $method );
		$instance   = ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
		$documents->created = array();
		add_filter( 'digitalisimo_elements_kit_existing_document', $reuse = static function ( $id, $doc_type, $old ) use ( $type ) { return $type === $doc_type && '41' === (string) $old ? 777 : $id; }, 50, 3 );
		$id = $reflection->invoke( $instance, '41', array( 'doc_type' => $type, 'title' => 'Del kit' ), array( 'content' => array() ), array( 'session_id' => $session_id ) );
		kt_check( 777 === $id && array() === $documents->created, "#6/#7 ({$type}): lo que el mismo kit ya importó se reutiliza sin crear otro documento." );
		$id = $reflection->invoke( $instance, '42', array( 'doc_type' => $type, 'title' => 'Otro' ), array( 'content' => array() ), array( 'session_id' => $session_id ) );
		kt_check( $id > 0 && 1 === count( $documents->created ), "#6/#7 ({$type}): lo nuevo se crea como antes." );
		remove_filter( 'digitalisimo_elements_kit_existing_document', $reuse, 50 );
	}

	// ── #8 Webhook ──────────────────────────────────────────────────────────────────────────────────────────────
	$webhook = ( new ReflectionClass( '\ElementorPro\Modules\Forms\Actions\Webhook' ) )->newInstanceWithoutConstructor();
	$out     = $webhook->on_export( array( 'id' => 'a1b2c3d', 'settings' => array( 'webhooks' => 'https://hooks.example.com/t0k3n', 'email_to' => 'x@example.com', '__dynamic__' => array( 'webhooks' => '[elementor-tag id="1" name="url"]', 'title' => 't' ) ) ) );
	kt_check( is_array( $out ) && ! isset( $out['settings']['webhooks'] ) && ! isset( $out['settings']['__dynamic__']['webhooks'] ) && 't' === $out['settings']['__dynamic__']['title'] && 'a1b2c3d' === $out['id'], '#8: on_export() quita la URL del webhook (también la dinámica) y conserva lo demás (CC-19).' );
	$plain = array( 'id' => 'b', 'settings' => array( 'form_name' => 'x' ) );
	kt_check( $plain === $webhook->on_export( $plain ), '#8: un formulario sin webhook no cambia.' );

	// ── #9 Delegado de ajustes ──────────────────────────────────────────────────────────────────────────────────
	$delegate = (string) file_get_contents( $E . '/core/app/modules/import-export-customization/runners/import/site-settings.php' );
	$code     = preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $delegate );
	kt_check( ! preg_match( '/->\s*import_(?:theme|experiments)\s*\(/', $code ), '#9: el delegado de ajustes de PRO nunca llama a import_theme() ni a import_experiments() (V-31, CR-6).' );

	// ── #10 Personalización fina ────────────────────────────────────────────────────────────────────────────────
	kt_check( true === \ElementorPro\Core\App\Modules\ImportExportCustomization\Utils::is_high_tier(), '#10: is_high_tier() → true (D3).' );
	$plugin = (string) file_get_contents( $E . '/plugin.php' );
	kt_check( false === strpos( (string) file_get_contents( $E . '/core/app/modules/import-export-customization/utils.php' ), 'License\\API' ), '#10: is_high_tier() no depende de la licencia del proveedor.' );
	kt_check( 1 === preg_match( "/function add_kit_customization_plan_to_app_settings[^{]*\{(?:(?!\n\t\}).)*is_current\(\)(?:(?!\n\t\}).)*\['plan_type'\]\s*=\s*'expert'/s", $plugin ), "#10: plan_type = 'expert' sólo cuando la App es la pantalla actual." );

	// ── #11 Duplicador ──────────────────────────────────────────────────────────────────────────────────────────
	require $E . '/digitalisimo-duplicator.php';
	kt_reset();
	\Elementor\Plugin::$instance->documents = $documents;
	$original = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Importada' ) );
	foreach ( array( '_elementor_data' => '[]', '_elementor_import_session_id' => 'sesion', '_digitalisimo_import_origin' => array( 'kit' => 'x' ), '_elementor_element_cache' => 'cache', 'propia' => 'sí' ) as $key => $value ) {
		update_post_meta( $original, $key, $value );
	}
	$copy = Digitalisimo_Elements_Duplicator::duplicate( get_post( $original ) );
	kt_check( is_int( $copy ) && $copy > 0, '#11: el duplicador crea la copia.' );
	kt_check( '' === get_post_meta( $copy, '_elementor_import_session_id', true ) && '' === get_post_meta( $copy, '_digitalisimo_import_origin', true ) && '' === get_post_meta( $copy, '_elementor_element_cache', true ), '#11: la copia no lleva la sesión del importador, la procedencia ni la caché de elementos (CC-18).' );
	kt_check( 'sí' === get_post_meta( $copy, 'propia', true ) && '[]' === get_post_meta( $copy, '_elementor_data', true ), '#11: lo demás se copia.' );

	kt_rmdir( $GLOBALS['kt']['root'] );
	echo "Kits: las once correcciones de §7.8 validadas (fuentes, adjuntos, iconos, rmdir, Custom Code, Theme Builder, widget global, webhook, delegado, personalización y duplicador).\n";
}
