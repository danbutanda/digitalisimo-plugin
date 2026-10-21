<?php
/**
 * Kits (etapa K, §13.1 «session»): sesión, contexto, proxy de medios, WXR y guardas del importador (frente A).
 *
 * Sin WordPress ni el constructor: tests/sources/kits/wp-stubs.php (ganchos con prioridades, opciones, transitorios,
 * entradas, metas y una carpeta de subidas real en un temporal). Comprueba:
 *  1. D1: un sitio con una entrada publicada sin el constructor no es vacío (CC-12); «nuevo» se rechaza con contenido,
 *     plantillas, portada o menús; Envato siempre en «agregar».
 *  2. Context::activate() registra exactamente los filtros de §5.4 (más los declarados aquí con su motivo) y
 *     deactivate() los quita; en «nuevo» no se protege la portada.
 *  3. Ruta REST con otra capitalización y barra final (CV-7, V-22); process-media → 403 siempre (V-23).
 *  4. Estados de D8 en los dos modos; ID ≤ max_post_id no cambia; elementor_snippet siempre en borrador.
 *  5. Portada sin cambio en «agregar» (runner de contenido y WXR usan update_option).
 *  6. Proxy (CR-20, CC-2): bytes del paquete; con `stream`, archivo escrito con content-length exacto; fuera del
 *     origen o con una pila ajena no responde; wordpress.org y el proveedor bloqueados; presupuesto y tiempo → error.
 *  7. WXR (V-21, V-20, CC-6, CV-1): objetos fuera, lista negra, prefijo reescrito y deduplicación en la 2.ª pasada.
 *  8. rest_request_before_callbacks: file_url → 403, source=cloud → 403, sin manage_options ningún cambio.
 *  9. Guardas heredadas: AJAX y admin-post, WP-CLI before_invoke antes de descargar, library import; con el plugin
 *     original no se instalan (D19).
 * 10. Temporales: staged_rel relativo; nada se borra fuera de tmp/; token de otro usuario, caducado o usado → null.
 */

namespace Elementor {
	class Plugin {
		public static $instance;
		public $documents;
	}
}

namespace Elementor\Core {
	/** Tipos de documento del núcleo y del Theme Builder que usa la regla de D8. */
	class Documents_Stub {
		public function get_document_type( $type, $fallback = true ) {
			$map = array(
				'header'       => '\ElementorPro\Modules\ThemeBuilder\Documents\Header',
				'footer'       => '\ElementorPro\Modules\ThemeBuilder\Documents\Header',
				'single-post'  => '\ElementorPro\Modules\ThemeBuilder\Documents\Header',
				'archive'      => '\ElementorPro\Modules\ThemeBuilder\Documents\Header',
				'product'      => '\ElementorPro\Modules\ThemeBuilder\Documents\Header',
				// En el constructor real la sección también es del grupo Theme Builder, pero sin ubicación propia.
				'section'      => '\ElementorPro\Modules\ThemeBuilder\Documents\Section',
				'wp-page'      => '\Elementor\Core\Section_Doc',
			);
			return isset( $map[ $type ] ) ? ltrim( $map[ $type ], '\\' ) : ( $fallback ? 'Elementor\Core\Section_Doc' : false );
		}
	}
	class Section_Doc {}
}

namespace ElementorPro\Modules\ThemeBuilder\Documents {
	abstract class Theme_Document {
		const LOCATION = '';
		public static function get_property( $key ) {
			return 'location' === $key ? static::LOCATION : null;
		}
	}
	class Header extends Theme_Document {
		const LOCATION = 'header';
	}
	class Section extends Theme_Document {}
}

namespace Elementor\TemplateLibrary\Classes {
	/** El importador de imágenes del núcleo (el proxy sólo le responde a él y a los demás de su lista). */
	class Import_Images {
		public static function fetch( $url, array $args = array() ) {
			return apply_filters( 'pre_http_request', false, $args, $url );
		}
	}
}

namespace Otro_Plugin {
	/** Un plugin ajeno que pide la misma URL durante la importación: el proxy no le responde. */
	class Cliente {
		public static function fetch( $url ) {
			return apply_filters( 'pre_http_request', false, array(), $url );
		}
	}
}

namespace {
	require __DIR__ . '/sources/kits/wp-stubs.php';
	require __DIR__ . '/../digitalisimo-elements/digitalisimo-vendor-free.php';

	/** El cargador del núcleo: con el núcleo incluido (por defecto) o con el plugin Elementor original (D19). */
	class Digitalisimo_Elements_Core {
		public static function bundled() {
			return ! empty( $GLOBALS['kt']['bundled'] );
		}
	}

	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-import/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();

	use Digitalisimo\Elements\Import\Module;
	use Digitalisimo\Elements\Import\Kits\Context;
	use Digitalisimo\Elements\Import\Kits\Session;
	use Digitalisimo\Elements\Import\Kits\Media_Proxy;
	use Digitalisimo\Elements\Import\Kits\Wxr_Guard;
	use Digitalisimo\Elements\Import\Kits\Legacy_Guard;
	use Digitalisimo\Elements\Import\Kits\Cli_Guard;

	foreach ( array( Context::class, Session::class, Media_Proxy::class, Wxr_Guard::class, Legacy_Guard::class, Cli_Guard::class ) as $class ) {
		kt_check( class_exists( $class ), "Falta {$class} (frente A)." );
	}

	$session_id = 'k' . str_repeat( '0', 12 ) . '1';
	$fresh_site = static function () use ( $session_id ) {
		Context::deactivate();
		Session::forget( $session_id );
		kt_reset( array( 'bundled' => true ) );
		\Elementor\Plugin::$instance            = new \Elementor\Plugin();
		\Elementor\Plugin::$instance->documents = new \Elementor\Core\Documents_Stub();
		if ( class_exists( '\Digitalisimo\Elements\Import\Kits\Parts\Origin' ) && method_exists( '\Digitalisimo\Elements\Import\Kits\Parts\Origin', 'reset' ) ) {
			\Digitalisimo\Elements\Import\Kits\Parts\Origin::reset();
		}
	};
	$record     = static function ( array $extra = array() ) {
		return array_replace(
			array(
				'v'           => 1,
				'mode'        => Module::MODE_ADD,
				'user'        => 1,
				'time'        => time(),
				'max_post_id' => 10,
				'origin'      => array( 'format' => '3.0', 'kit' => sha1( 'kit-de-prueba' ), 'home' => 'http://origen.test', 'site' => 'http://origen.test', 'uploads' => 'http://origen.test/wp-content/uploads' ),
				'report'      => array( 'entries' => array(), 'counts' => array(), 'dropped' => 0 ),
			),
			$extra
		);
	};
	$post = static function ( $type, $name, $status = 'publish', $id = null ) {
		return wp_insert_post( array( 'post_type' => $type, 'post_name' => $name, 'post_title' => $name, 'post_status' => $status ) + ( null !== $id ? array( 'import_id' => $id ) : array() ) );
	};

	// ── 1. D1: sitio vacío y modo ────────────────────────────────────────────────────────────────────────────────
	$fresh_site();
	$post( 'post', 'hello-world', 'publish', 1 );
	$post( 'page', 'sample-page', 'publish', 2 );
	$post( 'page', 'privacy-policy', 'draft', 3 );
	$kit = $post( 'elementor_library', 'default-kit', 'publish' );
	update_post_meta( $kit, '_elementor_template_type', 'kit' );
	$empty = Session::site_is_empty();
	kt_check( true === $empty['empty'], 'D1: un sitio con sólo lo que crea WordPress y el kit es un sitio vacío: ' . implode( ' ', $empty['reasons'] ) );
	kt_check( Module::MODE_FRESH === Session::decide_mode( Module::MODE_FRESH )['mode'], 'D1: «nuevo» pedido en un sitio vacío se acepta.' );
	kt_check( Module::MODE_ADD === Session::decide_mode( Module::MODE_ADD )['mode'], 'D1: sin pedirlo, siempre «agregar».' );
	$envato = Session::decide_mode( Module::MODE_FRESH, \Digitalisimo\Elements\Import\Kits\Normalizer::FORMAT_ENVATO );
	kt_check( Module::MODE_ADD === $envato['mode'] && '' !== $envato['error'], 'D1: un Template Kit de Envato entra siempre en «agregar», con el motivo.' );
	$cases = array(
		'entrada publicada sin el constructor (CC-12)' => static function () use ( $post ) { $post( 'post', 'blog-normal', 'publish' ); },
		'entrada en borrador'                           => static function () use ( $post ) { $post( 'post', 'borrador', 'draft' ); },
		'producto'                                      => static function () use ( $post ) { $post( 'product', 'producto', 'private' ); },
		'plantilla de la biblioteca'                    => static function () use ( $post ) { $id = $post( 'elementor_library', 'seccion', 'draft' ); update_post_meta( $id, '_elementor_template_type', 'section' ); },
		'portada estática'                              => static function () { update_option( 'show_on_front', 'page' ); },
		'menú'                                          => static function () { $GLOBALS['kt']['menus'] = array( (object) array( 'term_id' => 5, 'slug' => 'principal' ) ); },
		'página por defecto con ID alto'                => static function () use ( $post ) { $post( 'page', 'sample-page', 'publish', 40 ); },
	);
	foreach ( $cases as $label => $make ) {
		$fresh_site();
		$post( 'post', 'hello-world', 'publish', 1 );
		$make();
		$state    = Session::site_is_empty();
		$decision = Session::decide_mode( Module::MODE_FRESH );
		kt_check( false === $state['empty'] && $state['reasons'], "D1: con {$label} el sitio no es vacío." );
		kt_check( Module::MODE_ADD === $decision['mode'] && '' !== $decision['error'], "D1: «nuevo» se rechaza con {$label}, con el motivo." );
	}

	// ── 2. Ganchos del contexto (§5.4) ──────────────────────────────────────────────────────────────────────────
	$fresh_site();
	$spec = array(
		array( 'pre_http_request', 1 ),
		array( 'http_request_args', 10 ),
		array( 'import_attachment_size_limit', 10 ),
		array( 'elementor/import/get_default_settings_conflicts', 99 ),
		array( 'wp_insert_post_data', 99 ),
		array( 'wp_import_posts', 10 ),
		array( 'wp_import_post_data_raw', 10 ),
		array( 'wp_import_post_data_processed', 10 ),
		array( 'wp_import_post_meta', 10 ),
		array( 'wp_import_term_meta', 10 ),
		array( 'wp_import_post_comments', 10 ),
		array( 'wp_import_insert_post', 10 ),
		array( 'add_attachment', 10 ),
		array( 'elementor/document/save/data', 5 ),
		array( 'elementor/document/save/data', 20 ),
	);
	$add_only = array( array( 'pre_update_option_page_on_front', 10 ), array( 'pre_update_option_show_on_front', 10 ) );
	// Ganchos que el frente A añadió a §5.4, cada uno con su motivo. Uno nuevo sin declarar aquí hace fallar la prueba.
	$declared = array(
		'http_api_debug|10'                       => 'mide el tiempo y los bytes de cada descarga (presupuesto de D13, A13)',
		'wp_update_nav_menu_item|10'              => 'procedencia de los elementos de menú del WXR (D7, CV-1)',
		'import_end|10'                           => 'olvida la entrada del WXR en curso al terminar',
		'elementor/import-export-customization/import/wp-content/query-args/customization|20' => 'pasa al importador los pares de lo reutilizado (los menús se importan al final)',
		Module::FILTER_EXISTING_DOCUMENT . '|5'   => 'puente de procedencia para los derivados de PRO (§7.8 #1, #3, #5, #6 y #7)',
		'created_term|10'                          => 'los términos que crea el WXR llevan la sesión: «Revertir» los borra (A10)',
		'wp_revisions_to_keep|99'                  => 'sin revisiones durante la importación: cada documento se guarda dos veces (A13)',
		'wp_update_attachment_metadata|5'          => 'medidas del SVG leídas del archivo local, no por HTTP al propio sitio (A13)',
	);
	$key_of   = static function ( array $hook ) {
		return $hook[0] . '|' . $hook[1];
	};
	$expected = array_merge( array_map( $key_of, array_merge( $spec, $add_only ) ), array_keys( $declared ) );
	$got      = array_map( static function ( $hook ) { return $hook[0] . '|' . $hook[2]; }, Context::hooks( Module::MODE_ADD ) );
	sort( $expected );
	sort( $got );
	kt_check( $expected === $got, "§5.4: los ganchos del contexto «agregar» no coinciden.\n  Faltan: " . implode( ', ', array_diff( $expected, $got ) ) . "\n  Sobran (declararlos con su motivo): " . implode( ', ', array_diff( $got, $expected ) ) );
	$fresh_hooks = array_map( static function ( $hook ) { return $hook[0] . '|' . $hook[2]; }, Context::hooks( Module::MODE_FRESH ) );
	kt_check( ! array_intersect( array_map( $key_of, $add_only ), $fresh_hooks ), '§5.4: en «nuevo» la portada no se protege (la pidió el propietario).' );
	update_option( Session::option( $session_id ), $record() );
	Context::activate( $session_id );
	kt_check( Context::active() && $session_id === Context::session(), 'Context::activate() deja el contexto activo con su sesión.' );
	foreach ( Context::hooks( Module::MODE_ADD ) as $hook ) {
		kt_check( $hook[2] === has_filter( $hook[0], $hook[1] ), "Context::activate() debe registrar {$hook[0]} con prioridad {$hook[2]}." );
	}
	Context::deactivate();
	foreach ( Context::hooks( Module::MODE_ADD ) as $hook ) {
		kt_check( false === has_filter( $hook[0], $hook[1] ), "Context::deactivate() debe quitar {$hook[0]}." );
	}
	kt_check( ! Context::active(), 'Context::deactivate() deja el contexto inactivo.' );

	// ── 3. Rutas REST y process-media ───────────────────────────────────────────────────────────────────────────
	$routes = array(
		'/elementor/v1/import-export-customization/upload'               => 'upload',
		'/Elementor/V1/Import-Export-Customization/Upload/'              => 'upload',
		'/ELEMENTOR/V1/IMPORT-EXPORT-CUSTOMIZATION/IMPORT-RUNNER'        => 'import-runner',
		'/elementor/v1/import-export-customization/import/'              => 'import',
		'/elementor/v1/import-export-customization/revert'               => 'revert',
		'/elementor/v1/import-export-customization/Process-Media'        => 'process-media',
		'/elementor/v1/import-export-customization/upload-otra'          => '',
		'/elementor/v1/import-export/upload'                             => '',
		'/wp/v2/pages'                                                   => '',
	);
	foreach ( $routes as $route => $operation ) {
		kt_check( $operation === Context::operation( $route ), "Ruta REST {$route} → «{$operation}» (CV-7). ?rest_route= y /wp-json/ llegan con la misma ruta." );
	}
	foreach ( array( 1, 2 ) as $user ) {
		wp_set_current_user( $user );
		$res = Context::rest_before( null, array(), new WP_REST_Request( 'POST', '/elementor/v1/import-export-customization/PROCESS-MEDIA/' ) );
		kt_check( is_wp_error( $res ) && 403 === ( $res->get_error_data()['status'] ?? 0 ) && preg_match( '/[áéíóúñ]|medios/u', $res->get_error_message() ), 'process-media responde 403 siempre, también a un editor, con un mensaje en español (V-23).' );
	}
	wp_set_current_user( 1 );

	// ── 4. Estados de D8 ────────────────────────────────────────────────────────────────────────────────────────
	$status_of = static function ( $type, $doc_type, $status = 'publish', $id = 0 ) {
		$postarr = array( 'post_type' => $type, 'post_status' => $status ) + ( $id ? array( 'ID' => $id ) : array() ) + ( '' !== $doc_type ? array( 'meta_input' => array( '_elementor_template_type' => $doc_type ) ) : array() );
		$data    = apply_filters( 'wp_insert_post_data', array( 'post_type' => $type, 'post_status' => $status ), $postarr, $postarr, (bool) $id );
		return $data['post_status'];
	};
	$fresh_site();
	update_option( Session::option( $session_id ), $record() );
	Context::activate( $session_id );
	$rules = array(
		array( 'page', 'wp-page', 'draft', 'página visible' ),
		array( 'post', '', 'draft', 'entrada visible' ),
		array( 'product', '', 'draft', 'producto' ),
		array( 'e-landing-page', 'landing-page', 'draft', 'landing' ),
		array( 'elementor_library', 'header', 'draft', 'cabecera del Theme Builder' ),
		array( 'elementor_library', 'single-post', 'draft', 'plantilla de entrada' ),
		array( 'elementor_library', 'popup', 'draft', 'popup' ),
		array( 'elementor_library', 'section', 'publish', 'sección (sólo se ve incrustada)' ),
		array( 'elementor_library', 'loop-item', 'publish', 'elemento de bucle' ),
		array( 'e-floating-buttons', 'floating-buttons', 'draft', 'botón flotante' ),
		array( 'elementor_snippet', '', 'draft', 'fragmento de Custom Code' ),
		array( 'elementor_font', '', 'publish', 'fuente propia (sólo se carga publicada)' ),
		array( 'elementor_icons', '', 'publish', 'iconos propios (sólo se cargan publicados)' ),
		array( 'nav_menu_item', '', 'publish', 'elemento de menú' ),
		array( 'product_variation', '', 'publish', 'variación' ),
		array( 'wp_block', '', 'publish', 'bloque reutilizable' ),
	);
	foreach ( $rules as list( $type, $doc_type, $want, $label ) ) {
		kt_check( $want === $status_of( $type, $doc_type ), "D8 «agregar»: {$label} → {$want}." );
	}
	kt_check( 'inherit' === $status_of( 'attachment', '', 'inherit' ), 'D8: un adjunto conserva inherit.' );
	kt_check( 'publish' === $status_of( 'page', 'wp-page', 'publish', 7 ), 'D8: una entrada con ID ≤ max_post_id (anterior a la sesión) no cambia de estado.' );
	kt_check( 'draft' === $status_of( 'page', 'wp-page', 'publish', 11 ), 'D8: una entrada con ID > max_post_id es de la sesión.' );
	Context::deactivate();
	update_option( Session::option( $session_id ), $record( array( 'mode' => Module::MODE_FRESH ) ) );
	Session::forget( $session_id );
	Context::activate( $session_id );
	kt_check( 'publish' === $status_of( 'page', 'wp-page' ) && 'publish' === $status_of( 'elementor_library', 'header' ), 'D8 «nuevo»: lo visible conserva su estado.' );
	kt_check( 'draft' === $status_of( 'elementor_snippet', '' ), 'D8: elementor_snippet queda en borrador también en «nuevo».' );
	Context::deactivate();
	kt_check( 'publish' === $status_of( 'page', 'wp-page' ), 'D8: fuera del contexto nada cambia de estado.' );

	// ── 5. Portada en «agregar» ─────────────────────────────────────────────────────────────────────────────────
	$fresh_site();
	update_option( 'show_on_front', 'posts' );
	update_option( 'page_on_front', 0 );
	update_option( Session::option( $session_id ), $record() );
	Context::activate( $session_id );
	update_option( 'page_on_front', 42 );   // Runner de contenido (IEC:runners/import/elementor-content.php).
	update_option( 'show_on_front', 'page' ); // WXR (wp-import.php:715).
	kt_check( 0 === (int) get_option( 'page_on_front' ) && 'posts' === get_option( 'show_on_front' ), 'A6: en «agregar» la portada no cambia (runner de contenido y WXR).' );
	Context::deactivate();
	$saved = Session::get( $session_id );
	Session::forget( $session_id );
	$saved = Session::get( $session_id );
	kt_check( ! empty( $saved['front'] ), 'A6: la sesión anota que el kit traía portada (se aplica con «nuevo» o a mano).' );
	update_option( Session::option( $session_id ), $record( array( 'mode' => Module::MODE_FRESH ) ) );
	Session::forget( $session_id );
	Context::activate( $session_id );
	update_option( 'page_on_front', 42 );
	kt_check( 42 === (int) get_option( 'page_on_front' ), 'A6: en «nuevo» la portada del kit se aplica.' );
	Context::deactivate();

	// ── 6. Proxy de medios ──────────────────────────────────────────────────────────────────────────────────────
	$fresh_site();
	$tmp     = Session::create_tmp();
	$staged  = $tmp['dir'] . 'staged';
	$bytes   = str_repeat( 'PNG-del-kit', 50 );
	$sha     = hash( 'sha256', $bytes );
	$url     = 'http://origen.test/wp-content/uploads/foto.png';
	mkdir( $staged . '/media', 0777, true );
	file_put_contents( $staged . '/media/' . $sha . '.png', $bytes );
	file_put_contents( $staged . '/media.json', json_encode( array(
		$url                                  => array( 'file' => $sha . '.png', 'sha256' => $sha, 'mime' => 'image/png', 'bytes' => strlen( $bytes ) ),
		'http://ajeno.test/uploads/foto.png' => array( 'file' => $sha . '.png', 'sha256' => $sha, 'mime' => 'image/png', 'bytes' => strlen( $bytes ) ),
	) ) );
	$proxy_record = $record( array( 'token' => $tmp['token'], 'staged_rel' => Module::TMP_DIR . '/' . $tmp['token'] . '/staged' ) );
	update_option( Session::option( $session_id ), $proxy_record );
	Context::activate( $session_id );
	$res = \Elementor\TemplateLibrary\Classes\Import_Images::fetch( $url );
	kt_check( is_array( $res ) && 200 === $res['response']['code'] && $bytes === $res['body'] && (string) strlen( $bytes ) === $res['headers']['content-length'], 'Proxy: una URL del paquete sin flujo responde 200 con sus bytes.' );
	$target = $GLOBALS['kt']['root'] . '/descarga.tmp';
	$res    = \Elementor\TemplateLibrary\Classes\Import_Images::fetch( $url, array( 'stream' => true, 'filename' => $target ) );
	kt_check( is_array( $res ) && is_file( $target ) && filesize( $target ) === (int) $res['headers']['content-length'] && $target === $res['filename'] && '' === $res['body'], 'Proxy con stream: el archivo queda escrito en filename con el tamaño de content-length (CC-2, V-19).' );
	kt_check( false === \Elementor\TemplateLibrary\Classes\Import_Images::fetch( 'http://ajeno.test/uploads/foto.png' ), 'Proxy: una URL fuera de origin.uploads/home no se sirve aunque esté en media.json.' );
	kt_check( false === \Otro_Plugin\Cliente::fetch( $url ), 'Proxy: una pila sin el importador no recibe el medio (CR-20).' );
	foreach ( array( 'https://downloads.wordpress.org/plugin/x.zip', 'https://api.wordpress.org/plugins/info/1.0/', 'https://wordpress.org/', 'https://my.elementor.com/api/v1/kits', 'https://assets.elementor.com/x.png' ) as $blocked ) {
		$res = \Otro_Plugin\Cliente::fetch( $blocked );
		kt_check( is_wp_error( $res ) && 'digitalisimo_kit_blocked' === $res->get_error_code(), "Proxy: {$blocked} se bloquea durante la sesión." );
	}
	$GLOBALS['kt']['bundled'] = false;
	$res = \Otro_Plugin\Cliente::fetch( 'https://my.elementor.com/api/v1/kits' );
	kt_check( is_wp_error( $res ) && 'digitalisimo_kit_blocked' === $res->get_error_code(), 'Proxy: el proveedor también se bloquea con el plugin Elementor original (CORE=plugin, V-32).' );
	$GLOBALS['kt']['bundled'] = true;
	Context::deactivate();
	kt_check( false === \Otro_Plugin\Cliente::fetch( 'https://downloads.wordpress.org/x.zip' ), 'Proxy: fuera de la sesión no se toca ninguna petición.' );
	$limits = \Digitalisimo\Elements\Import\Kits\Zip_Guard::limits();
	update_option( Session::option( $session_id ), $proxy_record + array( 'downloaded' => $limits['session_download'] ) );
	Session::forget( $session_id );
	Context::activate( $session_id );
	$res = \Otro_Plugin\Cliente::fetch( 'http://demo-autor.test/wp-content/uploads/otra.png' );
	kt_check( is_wp_error( $res ) && 'digitalisimo_kit_budget' === $res->get_error_code(), 'Proxy: con el presupuesto de descargas agotado → WP_Error (D13).' );
	Context::deactivate();
	update_option( Session::option( $session_id ), $proxy_record );
	Session::forget( $session_id );
	Context::activate( $session_id );
	// 20 s de red ya gastados en esta petición (D13: request_network), sin esperar de verdad.
	$seconds = new ReflectionProperty( Media_Proxy::class, 'seconds' );
	$seconds->setValue( null, (float) $limits['request_network'] );
	$res = \Otro_Plugin\Cliente::fetch( 'http://demo-autor.test/wp-content/uploads/otra.png' );
	kt_check( is_wp_error( $res ) && 'digitalisimo_kit_time' === $res->get_error_code() && false !== strpos( $res->get_error_message(), 'tiempo' ), 'Proxy: con el tiempo de red agotado → «no descargado (tiempo)» (CV-23, A13).' );
	$args = apply_filters( 'http_request_args', array( 'timeout' => 300, 'redirection' => 10 ), 'http://demo-autor.test/x.png' );
	kt_check( $args['timeout'] <= 20 && $args['redirection'] <= 3 && $args['limit_response_size'] > 0, 'http_request_args: timeout ≤ 20, redirection ≤ 3 y tope de tamaño (el WXR pide 300 s).' );
	Context::deactivate();

	// ── 7. WXR ──────────────────────────────────────────────────────────────────────────────────────────────────
	$fresh_site();
	update_option( Session::option( $session_id ), $record() );
	Context::activate( $session_id );
	$menu_item = array(
		'post_id'   => 812,
		'post_type' => 'nav_menu_item',
		'post_title' => 'Clases hostiles',
		'status'    => 'publish',
		'postmeta'  => array(
			array( 'key' => '_menu_item_classes', 'value' => 'a:1:{i:0;O:8:"stdClass":0:{}}' ),
			array( 'key' => '_menu_item_url', 'value' => 'http://origen.test/blog/?a=1#b' ),
			array( 'key' => '_elementor_data', 'value' => '[{"elType":"widget","widgetType":"html"}]' ),
			array( 'key' => '_elementor_edit_mode', 'value' => 'builder' ),
			array( 'key' => '_digitalisimo_tools_network_template', 'value' => '1' ),
			array( 'key' => '_menu_item_type', 'value' => 'custom' ),
			array( 'key' => 'ajena_lista', 'value' => 'a:1:{i:0;s:3:"uno";}' ),
		),
		'comments'  => array( array( 'comment_author' => 'Lectora', 'comment_author_email' => 'lectora@example.com', 'comment_author_IP' => '192.0.2.10', 'comment_content' => 'Hola' ) ),
	);
	$clean = apply_filters( 'wp_import_post_data_raw', $menu_item );
	$keys  = array_column( $clean['postmeta'], 'value', 'key' );
	kt_check( ! isset( $keys['_menu_item_classes'] ), 'WXR: un objeto serializado en _menu_item_classes se quita (V-21: sólo lo ve wp_import_post_data_raw).' );
	kt_check( ! isset( $keys['_elementor_data'] ) && ! isset( $keys['_elementor_edit_mode'] ) && ! isset( $keys['_digitalisimo_tools_network_template'] ), 'WXR: la lista negra de §8.2 quita _elementor_data y las metas de red de Tools (V-20).' );
	kt_check( isset( $keys['_menu_item_type'] ) && 'a:1:{i:0;s:3:"uno";}' === $keys['ajena_lista'], 'WXR: todo lo demás pasa, sin lista blanca (los arrays serializados sin objetos también).' );
	kt_check( 'https://b.test/blog/?a=1#b' === $keys['_menu_item_url'], 'WXR: _menu_item_url con el prefijo del origen pasa a home_url() conservando consulta y fragmento (CC-6).' );
	kt_check( '' === $clean['comments'][0]['comment_author_email'] && '' === $clean['comments'][0]['comment_author_IP'], 'WXR: los comentarios llegan sin correo ni IP (CR-14).' );
	$processed = apply_filters( 'wp_import_post_data_processed', array( 'post_type' => 'post', 'post_status' => 'publish', 'post_content' => '<a href="http://origen.test/blog/">blog</a> <a href="http://origen.testigo/x">otro</a>' ), $menu_item );
	kt_check( false !== strpos( $processed['post_content'], 'href="https://b.test/blog/"' ) && false !== strpos( $processed['post_content'], 'origen.testigo' ), 'WXR: el prefijo home del origen en post_content pasa a home_url() sin tocar otros hosts.' );
	kt_check( 'draft' === $processed['post_status'], 'WXR: en «agregar» una entrada visible queda en borrador (D8).' );
	$meta = apply_filters( 'wp_import_post_meta', array( array( 'key' => 'x', 'value' => 'O:8:"stdClass":0:{}' ), array( 'key' => 'y', 'value' => 'z' ) ), 1, array() );
	kt_check( array( 'y' ) === array_column( $meta, 'key' ), 'WXR: segunda comprobación de objetos en wp_import_post_meta.' );
	$tmeta = apply_filters( 'wp_import_term_meta', array( array( 'key' => '_elementor_conditions', 'value' => 'a:0:{}' ), array( 'key' => 'color', 'value' => '#000' ) ), 1, array() );
	kt_check( array( 'color' ) === array_column( $tmeta, 'key' ), 'WXR: la lista negra también en las metas de término.' );
	// Deduplicación (CV-1): la primera pasada inserta; la segunda, con el mismo kit e ID de origen sin cambios, no.
	$wxr_post = array( 'post_id' => 801, 'post_type' => 'post', 'post_title' => 'Noticia', 'post_content' => '<p>x</p>', 'status' => 'publish', 'postmeta' => array( array( 'key' => 'acme', 'value' => '1' ) ) );
	$first    = apply_filters( 'wp_import_posts', array( $wxr_post ) );
	kt_check( 1 === count( $first ), 'WXR: la primera pasada conserva la entrada.' );
	$raw = apply_filters( 'wp_import_post_data_raw', $wxr_post );
	$new = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'Noticia', 'post_status' => 'draft' ) );
	do_action( 'wp_import_insert_post', $new, 801, array(), $raw );
	kt_check( is_array( get_post_meta( $new, Module::META_ORIGIN, true ) ), 'WXR: lo que crea el WXR lleva _digitalisimo_import_origin (CV-1).' );
	Context::deactivate();
	Session::forget( $session_id );
	if ( class_exists( '\Digitalisimo\Elements\Import\Kits\Parts\Origin' ) && method_exists( '\Digitalisimo\Elements\Import\Kits\Parts\Origin', 'reset' ) ) {
		\Digitalisimo\Elements\Import\Kits\Parts\Origin::reset();
	}
	Context::activate( $session_id );
	$second = apply_filters( 'wp_import_posts', array( $wxr_post ) );
	kt_check( 0 === count( $second ), 'WXR: la segunda pasada del mismo kit no inserta (CV-1, A8).' );
	kt_check( array( 801 => $new ) === Wxr_Guard::pairs(), 'WXR: el par viejo → nuevo de lo reutilizado queda para el mapa.' );
	Context::deactivate();

	// ── 8. rest_request_before_callbacks ────────────────────────────────────────────────────────────────────────
	$fresh_site();
	$upload = static function ( array $params, array $files = array() ) {
		$request = new WP_REST_Request( 'POST', '/elementor/v1/import-export-customization/upload' );
		foreach ( $params as $k => $v ) {
			$request->set_param( $k, $v );
		}
		$request->set_file_params( $files );
		return Context::rest_before( null, array(), $request );
	};
	$res = $upload( array( 'file_url' => 'https://kits.test/kit.zip' ) );
	kt_check( is_wp_error( $res ) && 403 === $res->get_error_data()['status'] && false !== strpos( $res->get_error_message(), 'ZIP de este equipo' ), 'Subida: file_url → 403 «Los kits se importan desde un ZIP de este equipo.»' );
	$res = $upload( array( 'source' => 'cloud', 'kit_id' => '1' ) );
	kt_check( is_wp_error( $res ) && 403 === $res->get_error_data()['status'], 'Subida: source = cloud → 403.' );
	kt_check( ! Context::active(), 'Subida rechazada: el contexto no queda activo.' );
	wp_set_current_user( 2 );
	$options_before = $GLOBALS['kt']['options'];
	$res            = $upload( array( 'file_url' => 'https://kits.test/kit.zip' ) );
	$req            = new WP_REST_Request( 'POST', '/elementor/v1/import-export-customization/import' );
	$req->set_param( 'session', $session_id );
	$res2 = Context::rest_before( null, array(), $req );
	kt_check( null === $res && null === $res2 && ! Context::active() && $options_before === $GLOBALS['kt']['options'], 'Sin manage_options, K no toca nada y la ruta devuelve su 403 (V-3).' );
	wp_set_current_user( 1 );

	// ── 9. Guardas heredadas (D4, D19) ──────────────────────────────────────────────────────────────────────────
	$fresh_site();
	$GLOBALS['kt']['ajax'] = true;
	foreach ( Legacy_Guard::AJAX as $action ) {
		$_POST['action'] = $action;
		$exit            = kt_exit_of( array( Legacy_Guard::class, 'ajax' ) );
		kt_check( $exit && 409 === $exit->status && false !== strpos( (string) ( $exit->data['message'] ?? '' ), 'Herramientas' ), "AJAX {$action} → 409 con el mensaje que manda a Herramientas." );
	}
	$_POST['action'] = 'heartbeat';
	kt_check( null === kt_exit_of( array( Legacy_Guard::class, 'ajax' ) ), 'Otro AJAX no se toca.' );
	unset( $_POST['action'] );
	$GLOBALS['kt']['ajax'] = false;
	$exit = kt_exit_of( array( Legacy_Guard::class, 'revert' ) );
	kt_check( $exit && 409 === $exit->status, 'admin_post_elementor_revert_kit → wp_die 409 con enlace.' );
	$threw = false;
	try {
		Legacy_Guard::abort();
	} catch ( \Error $e ) {
		$threw = true;
	}
	kt_check( $threw, 'elementor/import-export/import-kit (2.0) se corta con \Error, el único que captura su AJAX (V-4).' );
	if ( ! defined( 'WP_CLI' ) ) {
		define( 'WP_CLI', true );
	}
	WP_CLI::$hooks = array();
	Cli_Guard::register();
	foreach ( Cli_Guard::KIT_COMMANDS as $command ) {
		update_option( 'elementor_unfiltered_files_upload', '' );
		$exit = kt_exit_of( static function () use ( $command ) { WP_CLI::invoke( 'elementor kit ' . $command, array( 'elementor', 'kit', $command, 'https://kits.test/kit.zip', '--sourceType=remote', '--unfilteredFilesUpload=enable' ) ); } );
		kt_check( $exit && false !== strpos( $exit->getMessage(), 'wp digitalisimo-elements kit ' . $command ), "wp elementor kit {$command} corta en before_invoke con el comando propio (V-27)." );
		kt_check( '' === get_option( 'elementor_unfiltered_files_upload' ), "wp elementor kit {$command}: la opción de subida no cambió antes de cortar." );
	}
	$plain = kt_zip( $GLOBALS['kt']['root'] . '/plantilla.zip', array( 'plantilla.json' => array( 'content' => array() ) ) );
	$kitzip = kt_zip( $GLOBALS['kt']['root'] . '/kit.zip', array( 'kit/manifest.json' => array( 'version' => '3.0' ), 'kit/templates/1.json' => array( 'content' => array() ) ) );
	$exit   = kt_exit_of( static function () { WP_CLI::invoke( 'elementor library import', array( 'elementor', 'library', 'import', 'https://kits.test/x.zip' ) ); } );
	kt_check( $exit && false !== strpos( $exit->getMessage(), 'URL' ), 'wp elementor library import con una URL → error (CC-24).' );
	$exit = kt_exit_of( static function () use ( $kitzip ) { WP_CLI::invoke( 'elementor library import', array( 'elementor', 'library', 'import', $kitzip ) ); } );
	kt_check( $exit && false !== strpos( $exit->getMessage(), 'wp digitalisimo-elements kit import' ), 'wp elementor library import con un kit → manda a wp digitalisimo-elements kit import.' );
	kt_check( null === kt_exit_of( static function () use ( $plain ) { WP_CLI::invoke( 'elementor library import', array( 'elementor', 'library', 'import', $plain ) ); } ), 'wp elementor library import con una plantilla suelta sigue su curso.' );
	$GLOBALS['kt']['bundled'] = false;
	WP_CLI::$hooks             = array();
	$GLOBALS['kt']['hooks']    = array();
	Legacy_Guard::register();
	Cli_Guard::register();
	kt_check( ! Legacy_Guard::applies() && array() === WP_CLI::$hooks && ! has_action( 'admin_init' ) && ! has_action( 'elementor/import-export/import-kit' ), 'D19: con el plugin Elementor original las guardas del flujo 2.0 no se instalan.' );
	$GLOBALS['kt']['bundled'] = true;
	Legacy_Guard::register();
	kt_check( 9 === has_action( 'admin_init', array( Legacy_Guard::class, 'ajax' ) ) && 1 === has_action( 'admin_post_elementor_revert_kit', array( Legacy_Guard::class, 'revert' ) ), 'Con el núcleo incluido las guardas heredadas se instalan con sus prioridades (§7.6).' );

	// ── 10. Temporales y token ──────────────────────────────────────────────────────────────────────────────────
	$fresh_site();
	$tmp = Session::create_tmp();
	kt_check( 1 === preg_match( '/^[0-9a-f]{64}$/', $tmp['token'] ) && is_file( $tmp['dir'] . 'index.php' ) && false !== strpos( (string) file_get_contents( $tmp['dir'] . '.htaccess' ), 'Require all denied' ), 'Temporales: tmp/<64 hex>/ con index.php y .htaccess que niega el acceso (§5.3).' );
	$rel = Module::TMP_DIR . '/' . $tmp['token'] . '/staged';
	mkdir( $tmp['dir'] . 'staged' );
	kt_check( 0 !== strpos( $rel, '/' ) && realpath( $tmp['dir'] . 'staged' ) . '/' === Session::staged_dir( array( 'staged_rel' => $rel ) ), 'staged_rel es relativo a uploads y resuelve la carpeta del sitio actual (§5.3: «Clonar sitio»).' );
	foreach ( array( '/etc', '../../../../etc', Module::TMP_DIR . '/' . $tmp['token'] . '/../../..', Module::TMP_DIR . '/' . str_repeat( 'a', 64 ) . '/staged' ) as $bad ) {
		kt_check( null === Session::staged_dir( array( 'staged_rel' => $bad ) ), "staged_rel hostil o inexistente «{$bad}» → null." );
	}
	$outside = $GLOBALS['kt']['root'] . '/fuera';
	mkdir( $outside );
	file_put_contents( $outside . '/importante.txt', 'no borrar' );
	if ( function_exists( 'symlink' ) && @symlink( $outside, $tmp['dir'] . 'enlace' ) ) { // phpcs:ignore
		Session::delete_tmp( $tmp['token'] );
		kt_check( is_file( $outside . '/importante.txt' ), 'Limpieza: un enlace simbólico dentro de tmp/ no arrastra lo de fuera.' );
		@unlink( $tmp['dir'] . 'enlace' ); // phpcs:ignore -- el guardián nunca extrae enlaces; aquí sólo se prueba que no se siguen.
	}
	kt_check( Session::delete_tmp( $tmp['token'] ) && ! is_dir( $tmp['dir'] ), 'delete_tmp borra la carpeta del token.' );
	foreach ( array( '../fuera', '/etc', 'xyz', str_repeat( 'g', 64 ) ) as $bad ) {
		kt_check( false === Session::delete_tmp( $bad ) && is_file( $outside . '/importante.txt' ), "Limpieza con un token hostil «{$bad}» → no borra nada fuera de tmp/." );
	}
	Session::cleanup( 'no-existe' );
	kt_check( is_file( $outside . '/importante.txt' ), 'Limpieza de una sesión inexistente no borra nada.' );
	$token = str_repeat( 'ab', 32 );
	Session::remember_token( array( 'token' => $token, 'user' => 1, 'mode' => Module::MODE_FRESH ) );
	wp_set_current_user( 2 );
	kt_check( null === Session::take_token( $token ), 'Token de otro usuario → rechazo (CV-16).' );
	wp_set_current_user( 1 );
	$taken = Session::take_token( $token );
	kt_check( is_array( $taken ) && Module::MODE_FRESH === $taken['mode'], 'El token guarda el modo y lo entrega a su usuario.' );
	kt_check( null === Session::take_token( $token ), 'Token ya usado → rechazo (un solo uso).' );
	$token2 = str_repeat( 'cd', 32 );
	Session::remember_token( array( 'token' => $token2, 'user' => 1 ) );
	kt_expire_transient( Session::TOKEN_PREFIX . $token2 );
	kt_check( null === Session::take_token( $token2 ), 'Token caducado → rechazo.' );
	kt_check( null === Session::take_token( 'ab' ), 'Token con otra forma → rechazo.' );
	$res = Session::kit_result( array( 'kit_id' => $token, 'source' => Module::SOURCE ) );
	kt_check( is_wp_error( $res ), 'elementor/import/kit/result/digitalisimo con un token usado → error.' );
	wp_set_current_user( 2 );
	kt_check( is_wp_error( Session::kit_result( array( 'kit_id' => $token2 ) ) ), 'elementor/import/kit/result/digitalisimo sin manage_options → error.' );
	wp_set_current_user( 1 );
	Session::schedule_cleanup( $session_id );
	$event = wp_next_scheduled( Module::CLEANUP_EVENT, array( $session_id ) );
	kt_check( $event && $event > time() + 23 * HOUR_IN_SECONDS, 'La limpieza se programa a 24 h con el ID de sesión como argumento, nunca una ruta.' );

	kt_rmdir( $GLOBALS['kt']['root'] );
	echo "Kits: sesión, contexto, proxy, WXR y guardas validados (D1, D8, §5.4, CV-1, CV-7, CV-16, CV-23, CV-27, V-3, V-21, V-23, D19).\n";
}
