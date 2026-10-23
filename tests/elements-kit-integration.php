<?php
/**
 * Kits (etapa K, frente I): lo que une a los frentes y que la primera corrida de kit-roundtrip.sh mostró roto.
 *
 * Sin WordPress: tests/sources/kits/wp-stubs.php y los archivos reales de digitalisimo-elements/.
 *  1. `Module::init()` engancha a todos los frentes (marcador, biblioteca, pantalla «Kits», WP-CLI).
 *  2. Una sola fuente: huellas y procedencia en `Parts\Origin`, lista negra en `Export\Meta_Blacklist`, condiciones
 *     en `Remapper`, recuento de marcadores en `Preserve`; la búsqueda de procedencia ve la biblioteca de plantillas.
 *  3. Cada código que emiten K, los derivados de PRO y las partes y referencias de fuera de K (F7a: presets de estilo y
 *     condiciones) cae en una sección del informe (salvo los de diagnóstico).
 *  4. «Publicar páginas y plantillas»: `Session::publishable()` existe y publica lo visible, nunca el Theme Builder;
 *     una sección (sin ubicación) no es plantilla «que se aplica sola» (D8).
 *  5. Datos personales: el `author` del manifiesto sale vacío y el `<guid>` del WXR no lleva el login.
 *  6. Condiciones del Theme Builder con las claves en cualquier orden (`Normalizer::ordered_conditions()`).
 *  7. Template Kits: manda `type`; el editorial desconocido queda como tipo original (`original_type`, en `Finish`).
 *  8. Medios dentro del kit: fuentes y SVG con su MIME aunque WordPress no los conozca, archivos de un conjunto de
 *     iconos sin adjunto y sin salir de su carpeta, y textos enriquecidos y widgets de medios de WordPress remapeados.
 */

namespace Elementor {
	class Plugin {
		public static $instance;
		public $documents;
	}
}

namespace Elementor\Core\Frontend {
	/** Como el núcleo: fuera del editor y de REST, los controles de estilo quedan aparte salvo que se pidan. */
	class Performance {
		public static $use = false;
		public static function set_use_style_controls( bool $is_use ): void {
			self::$use = $is_use;
		}
		public static function is_use_style_controls(): bool {
			return self::$use;
		}
	}
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


namespace {
	require __DIR__ . '/sources/kits/wp-stubs.php';
	$E = __DIR__ . '/../digitalisimo-elements';
	$M = $E . '/modules/digitalisimo-import';
	require $M . '/class-module.php';
	\Digitalisimo\Elements\Import\Module::register_autoloader();

	if ( ! function_exists( 'wp_check_filetype' ) ) {
		/** Como WordPress: fuentes y SVG no están en la lista de tipos admitidos. */
		function wp_check_filetype( $file, $mimes = null ) {
			$known = array( 'png' => 'image/png', 'jpg' => 'image/jpeg', 'pdf' => 'application/pdf', 'css' => 'text/css' );
			$ext   = strtolower( (string) pathinfo( (string) $file, PATHINFO_EXTENSION ) );
			return isset( $known[ $ext ] ) ? array( 'ext' => $ext, 'type' => $known[ $ext ] ) : array( 'ext' => false, 'type' => false );
		}
	}
	if ( ! function_exists( 'attachment_url_to_postid' ) ) {
		function attachment_url_to_postid( $url ) {
			foreach ( $GLOBALS['kt']['posts'] as $post ) {
				if ( 'attachment' === $post['post_type'] && $url === $GLOBALS['kt']['home'] . '/wp-content/uploads/' . get_post_meta( $post['ID'], '_wp_attached_file', true ) ) {
					return $post['ID'];
				}
			}
			return 0;
		}
	}
	if ( ! function_exists( 'get_attached_file' ) ) {
		function get_attached_file( $id ) {
			$file = (string) get_post_meta( $id, '_wp_attached_file', true );
			return '' === $file ? false : $GLOBALS['kt']['uploads'] . '/' . $file;
		}
	}
	if ( ! function_exists( 'get_post_mime_type' ) ) {
		function get_post_mime_type( $id = null ) {
			return (string) ( $GLOBALS['kt']['posts'][ (int) $id ]['post_mime_type'] ?? '' );
		}
	}

	use Digitalisimo\Elements\Import\Kits\Context;
	use Digitalisimo\Elements\Import\Kits\Import_Context;
	use Digitalisimo\Elements\Import\Kits\Normalizer;
	use Digitalisimo\Elements\Import\Kits\Parts\Media;
	use Digitalisimo\Elements\Import\Kits\Parts\Origin;
	use Digitalisimo\Elements\Import\Kits\References;
	use Digitalisimo\Elements\Import\Kits\Remapper;
	use Digitalisimo\Elements\Import\Kits\Report;
	use Digitalisimo\Elements\Import\Kits\Session;
	use Digitalisimo\Elements\Import\Sources\Envato_Kit;

	$failures = array();
	$check    = static function ( $ok, $message ) use ( &$failures ) {
		if ( ! $ok ) {
			$failures[] = $message;
		}
	};
	$read = static function ( $file ) {
		return (string) file_get_contents( $file );
	};

	// 1. `Module::init()` engancha a todos los frentes.
	$module = $read( $M . '/class-module.php' );
	foreach ( array( "'Kits\\\\Preserve', 'boot'", "'Kits\\\\Preserve', 'register_widget'", "'Kits\\\\Library', 'intercept'", "'Kits\\\\Report_Page', 'init'", "'Kits\\\\Cli', 'init'" ) as $needle ) {
		$check( false !== strpos( $module, $needle ), "1: Module::init() engancha {$needle}." );
	}
	$check( 'digitalisimo_elements_kit_revert' === \Digitalisimo\Elements\Import\Module::ACTION_REVERT && \Digitalisimo\Elements\Import\Kits\Report_Page::ACTION_REVERT === \Digitalisimo\Elements\Import\Module::ACTION_REVERT, '1: la acción de revertir está declarada en Module y la pantalla usa ésa.' );
	$check( false === strpos( $module, 'K-TRACE' ), '1: sin instrumentación de diagnóstico en Module.' );

	// 2. Una sola fuente.
	kt_reset();
	$doc  = array( 'content' => array( array( 'id' => 'x', 'elType' => 'section' ) ), 'settings' => array( 'a' => 1 ), 'id' => 3, 'import_settings' => array( 'z' ) );
	$wxr  = array( 'post_title' => 'T', 'post_content' => 'C', 'status' => 'publish', 'postmeta' => array( array( 'key' => '_edit_lock', 'value' => '1' ), array( 'key' => 'k', 'value' => 'v' ) ) );
	$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'P', 'post_content' => 'texto' ) );
	$check( Session::document_hash( $doc ) === Origin::document_hash( $doc ) && Session::wxr_hash( $wxr ) === Origin::wxr_hash( $wxr ) && Session::local_hash( $page ) === Origin::local_hash( $page ), '2: Session expone las huellas de Parts\\Origin (una sola fórmula).' );
	$session = $read( $M . '/kits/class-session.php' );
	$check( false === strpos( $session, '$wpdb->esc_like( \'"kit";s:40:' ) && false === strpos( $session, 'Wxr_Guard::blacklisted' ), '2: Session no tiene una búsqueda de procedencia ni una lista negra propias.' );
	$guard = $read( $M . '/kits/class-wxr-guard.php' );
	$check( false === strpos( $guard, 'const BLACKLIST' ) && false !== strpos( $guard, 'Export\\Meta_Blacklist::blocks' ), '2: el WXR usa la lista negra de Export\\Meta_Blacklist.' );
	$finish = $read( $M . '/kits/runners/class-finish.php' );
	$check( false === strpos( $finish, 'function condition_kind' ) && false !== strpos( $finish, '( new Remapper( $context ) )->theme_conditions(' ), '2: Finish traduce condiciones sólo con Remapper::theme_conditions().' );
	$check( false === strpos( $finish, "p.post_status <> 'trash'" ) && false !== strpos( $finish, 'Preserve::recount_site()' ), '2: un solo recuento de marcadores (Preserve::recount_site()).' );
	$origin = $read( $M . '/kits/parts/class-origin.php' );
	$check( 1 === preg_match( "/'post_type'\s*=>\s*function_exists\( 'get_post_types' \)/", $origin ), '2: la búsqueda de procedencia incluye la biblioteca de plantillas (no `any`), así que una reimportación no la duplica (A8).' );
	$page_php = $read( $M . '/kits/class-report-page.php' );
	$check( false === strpos( $page_php, "self::has( 'Parts\\\\Origin'" ) && false !== strpos( $page_php, '$hash = Session::local_hash( $post_id );' ), '2: la pantalla «Kits» compara con una sola huella local.' );

	// 3. Cada código del informe tiene sección (salvo los de diagnóstico, que van a «Otros avisos»).
	$diagnostic = array( 'part.error', 'preserve.error', 'remap.error', 'runner.after', 'wxr.invalid', 'wxr.object', 'wxr.blacklist', 'ids.mismatch', 'part.version', 'invalid-code' );
	$sources    = array_merge(
		glob( $M . '/{,*/,*/*/}*.php', GLOB_BRACE ),
		glob( $E . '/modules/*/import-export-customization/*.php' ),
		glob( $E . '/modules/assets-manager/asset-types/*/import-export-customization/*.php' ),
		glob( $E . '/modules/assets-manager/asset-types/import-export/traits/*.php' ),
		glob( $E . '/modules/forms/actions/*.php' ),
		// Partes y referencias registradas fuera de K (F7a): presets de estilo y condiciones.
		glob( $E . '/modules/digitalisimo-design/{,*/}*.php', GLOB_BRACE ),
		glob( $E . '/modules/digitalisimo-conditions/*.php' ),
		// Partes y referencias de F4-Woo (WooCommerce visual): `kit/` y los demás submódulos.
		glob( $E . '/modules/digitalisimo-woo/{,*/}*.php', GLOB_BRACE )
	);
	$codes = array();
	foreach ( $sources as $file ) {
		if ( preg_match_all( "/(?:->report|::report|digitalisimo_report|->tally|::tally)\(\s*'([a-z0-9_:.-]+)'/", $read( $file ), $found ) ) {
			foreach ( $found[1] as $code ) {
				$codes[ $code ] = basename( $file );
			}
		}
	}
	$check( count( $codes ) > 50, '3: se encontraron los códigos del informe (' . count( $codes ) . ').' );
	foreach ( $codes as $code => $file ) {
		$section = Report::section_for( (string) $code );
		$check( 'other' !== $section || in_array( $code, $diagnostic, true ), "3: el código {$code} ({$file}) tiene sección en el informe." );
	}
	$check( 'requested' === Report::section_for( 'custom_code.skipped' ) && 'unresolved' === Report::section_for( 'theme-builder.closed' ) && 'media' === Report::section_for( 'assets.attachment_failed' ) && 'preserved' === Report::section_for( 'original-type' ) && 'drafts' === Report::section_for( 'origin.update' ), '3: secciones de los códigos de los derivados de PRO y de la procedencia.' );

	// 4. «Publicar páginas y plantillas» y D8.
	kt_reset();
	$posts = array(
		'page'    => wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'draft' ) ),
		'section' => wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'draft', 'meta_input' => array( '_elementor_template_type' => 'section' ) ) ),
		'header'  => wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'draft', 'meta_input' => array( '_elementor_template_type' => 'header' ) ) ),
		'snippet' => wp_insert_post( array( 'post_type' => 'elementor_snippet', 'post_status' => 'draft' ) ),
		'kit'     => wp_insert_post( array( 'post_type' => 'elementor_library', 'post_status' => 'draft', 'meta_input' => array( '_elementor_template_type' => 'kit' ) ) ),
		'public'  => wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish' ) ),
	);
	\Elementor\Plugin::$instance            = new \Elementor\Plugin();
	\Elementor\Plugin::$instance->documents = new class() {
		public function get_document_type( $type, $fallback = true ) {
			$map = array(
				'header'  => 'ElementorPro\\Modules\\ThemeBuilder\\Documents\\Header',
				'section' => 'ElementorPro\\Modules\\ThemeBuilder\\Documents\\Section',
			);
			return isset( $map[ $type ] ) ? $map[ $type ] : false;
		}
	};
	$check( method_exists( Session::class, 'publishable' ), '4: Session::publishable() existe (la pantalla «Kits» lo exige para ofrecer «Publicar»).' );
	$check( Session::publishable( $posts['page'] ) && Session::publishable( $posts['section'] ), '4: se publican las páginas y las secciones en borrador.' );
	$check( ! Session::publishable( $posts['header'] ) && ! Session::publishable( $posts['snippet'] ) && ! Session::publishable( $posts['kit'] ) && ! Session::publishable( $posts['public'] ), '4: nunca el Theme Builder, los fragmentos, el kit ni lo ya publicado.' );
	$check( Session::is_theme_template( 'header' ) && Session::is_theme_template( 'popup' ) && ! Session::is_theme_template( 'section' ) && ! Session::is_theme_template( 'loop-item' ), '4: D8: una sección (sin ubicación) conserva su estado; una cabecera y un popup, no.' );

	// 5. Datos personales.
	$hooks = array();
	foreach ( Context::export_hooks() as $hook ) {
		$hooks[ $hook[0] ] = $hook[1];
	}
	$check( isset( $hooks['get_the_author_display_name'] ) && '' === call_user_func( $hooks['get_the_author_display_name'], 'Daniel Persona' ), '5: el `author` del manifiesto sale vacío durante la exportación (CR-14).' );
	$check( false !== strpos( $read( $M . '/kits/export/class-wp-content.php' ), "getElementsByTagName( 'guid' )" ), '5: el `<guid>` del WXR (el login del autor) pasa a autor-<n>.' );

	// 6. Condiciones con las claves en cualquier orden.
	$ordered = Normalizer::ordered_conditions( array( array( 'name' => 'general', 'sub_id' => '', 'sub_name' => '', 'type' => 'include' ), 'exclude/singular/page/3', 7, array( 'x' => 1 ) ) );
	$check( array( array( 'type' => 'include', 'name' => 'general', 'sub_name' => '', 'sub_id' => '' ), 'exclude/singular/page/3' ) === $ordered, '6: condiciones en el orden del núcleo; lo que no es condición se descarta: ' . json_encode( $ordered ) );
	$check( 'include/general' === rtrim( implode( '/', $ordered[0] ), '/' ), '6: el documento de tema las une con implode() y queda include/general.' );

	// 7. Template Kits: manda `type`.
	$registered = static function ( $type ) {
		return in_array( $type, array( 'page', 'section', 'header', 'footer', 'single-page' ), true );
	};
	$check( array( 'page', '' ) === Envato_Kit::document_type( 'page', 'single-page', $registered ), '7: type registrado manda aunque el editorial también exista.' );
	$check( array( 'section', 'acme-bloque' ) === Envato_Kit::document_type( 'section', 'acme-bloque', $registered ), '7: editorial desconocido → tipo original.' );
	$check( array( 'section', 'acme-type' ) === Envato_Kit::document_type( 'acme-type', 'acme', $registered ), '7: type desconocido → section con ese tipo original.' );
	$check( array( 'header', '' ) === Envato_Kit::document_type( '', 'section-header', $registered ), '7: sin type, el editorial (también por su equivalencia).' );
	$check( false !== strpos( $finish, 'self::original_types( $data, $context );' ), '7: Finish anota `_digitalisimo_original_type` para cualquier runner (también el del Theme Builder).' );

	// 8. Medios dentro del kit.
	kt_reset();
	$up = $GLOBALS['kt']['uploads'];
	@mkdir( $up . '/elementor/custom-icons/set-a/css', 0777, true ); // phpcs:ignore
	file_put_contents( $up . '/fuente.woff2', 'wOF2' );
	file_put_contents( $up . '/figura.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>' );
	file_put_contents( $up . '/elementor/custom-icons/set-a/config.json', '{}' );
	file_put_contents( $up . '/elementor/custom-icons/set-a/css/set-a.css', '.a{}' );
	file_put_contents( $up . '/elementor/custom-icons/set-a/LICENSE.txt', 'x' );
	file_put_contents( $up . '/secreto.php', '<?php' );
	foreach ( array( 'fuente.woff2', 'figura.svg' ) as $name ) {
		wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_input' => array( '_wp_attached_file' => $name ) ) );
	}
	$uploads = 'https://b.test/wp-content/uploads';
	$font    = Media::local_file( $uploads . '/fuente.woff2', $uploads );
	$svg     = Media::local_file( $uploads . '/figura.svg', $uploads );
	$check( null !== $font && 'font/woff2' === $font['mime'] && null !== $svg && 'image/svg+xml' === $svg['mime'], '8: una fuente y un SVG del sitio viajan aunque WordPress no conozca su tipo.' );
	$css = Media::local_file( $uploads . '/elementor/custom-icons/set-a/css/set-a.css', $uploads );
	$cfg = Media::local_file( $uploads . '/elementor/custom-icons/set-a/config.json', $uploads );
	$check( null !== $css && 'css' === $css['ext'] && 0 === $css['id'] && null !== $cfg, '8: los archivos de un conjunto de iconos viajan sin ser adjuntos.' );
	$check( null === Media::local_file( $uploads . '/elementor/custom-icons/set-a/LICENSE.txt', $uploads ) && null === Media::local_file( $uploads . '/elementor/custom-icons/set-a/../../../secreto.php', $uploads ) && null === Media::local_file( $uploads . '/elementor/custom-icons/x.css', $uploads ), '8: nada fuera de la carpeta del conjunto ni con otra extensión.' );
	$check( 1 === preg_match( \Digitalisimo\Elements\Import\Module::MEDIA_FILE_PATTERN, str_repeat( 'a', 64 ) . '.css' ) && 1 === preg_match( \Digitalisimo\Elements\Import\Module::MEDIA_FILE_PATTERN, str_repeat( 'a', 64 ) . '.eot' ) && 0 === preg_match( \Digitalisimo\Elements\Import\Module::MEDIA_FILE_PATTERN, str_repeat( 'a', 64 ) . '.php' ), '8: el paquete admite css y eot (iconos), nunca php.' );
	$check( Media::is_icon_set_url( 'http://a.test/wp-content/uploads/elementor/custom-icons/set/x.css', 'http://a.test/wp-content/uploads' ) && ! Media::is_icon_set_url( 'http://a.test/wp-content/uploads/foto.png', 'http://a.test/wp-content/uploads' ), '8: la importación reconoce los archivos de iconos (no crea adjuntos con ellos).' );
	$icons_export = $read( $E . '/modules/assets-manager/asset-types/icons/import-export-customization/export-runner.php' );
	$check( false !== strpos( $icons_export, 'digitalisimo_set_files' ), '8: la exportación de iconos incluye los archivos de su carpeta.' );
	$check( false !== strpos( $read( $E . '/modules/assets-manager/asset-types/import-export/traits/external-attachment-trait.php' ), "'_elementor_source_image_hash', sha1( \$url )" ), '8: el adjunto de una fuente lleva la huella del núcleo (la parte media lo reutiliza).' );
	$check( false !== strpos( $read( $E . '/modules/assets-manager/asset-types/icons/import-export-customization/revert-runner.php' ), 'Custom_Icons::get_custom_icons_config();' ), '8: revertir rehace la caché de conjuntos de iconos (A10).' );

	// Remapeo de textos enriquecidos y de la URL de los widgets de medios de WordPress.
	kt_reset();
	$context = new Import_Context(
		array(
			'session'     => 'kitintegra01',
			'mode'        => 'add',
			'kit'         => str_repeat( 'b', 40 ),
			'max_post_id' => 0,
			'origin'      => array( 'home' => 'http://a.test', 'site' => 'http://a.test', 'uploads' => 'http://a.test/wp-content/uploads' ),
			'maps'        => array(
				'media_url' => array( 'http://a.test/wp-content/uploads/foto.png' => 'https://b.test/wp-content/uploads/foto-1.png' ),
				'url'       => array( 'http://a.test/wp-content/uploads' => 'https://b.test/wp-content/uploads', 'http://a.test' => 'https://b.test' ),
			),
		)
	);
	$context->begin_part( 'remap' );
	$remapper = new Remapper(
		$context,
		array(
			'home'              => 'https://b.test',
			'site'              => 'https://b.test',
			'uploads'           => 'https://b.test/wp-content/uploads',
			'attachment_by_url' => static function () {
				return 0;
			},
			'known'             => static function () {
				return false;
			},
		)
	);
	$html = $remapper->html( '<p>Ver <a href="http://a.test/hija/#x">la hija</a> y <a href="https://otro.test/">otro</a>.</p><img src="http://a.test/wp-content/uploads/foto.png">' );
	$check( false !== strpos( $html, 'href="https://b.test/hija/#x"' ) && false !== strpos( $html, 'href="https://otro.test/"' ) && false === strpos( $html, 'a.test' ), '8: los enlaces del origen en un texto enriquecido pasan al destino y los ajenos no cambian: ' . $html );
	$refs = References::derive( 'text-editor', array( 'editor' => array( 'name' => 'editor', 'type' => 'wysiwyg' ), 'link' => array( 'name' => 'link', 'type' => 'url' ) ) );
	$kinds = array_column( $refs, 'kind', 'path' );
	$check( array( 'editor' => 'html', 'link.url' => 'url' ) === $kinds, '8: los controles WYSIWYG se derivan como `html` y los URL como `url`: ' . json_encode( $kinds ) );
	$wp = array_column( References::matching( 'element', 'wp-widget-media_image' ), 'kind', 'path' );
	$check( isset( $wp['wp.url'], $wp['wp.attachment_id'] ) && 'url' === $wp['wp.url'], '8: el widget de imagen de WordPress traduce también su URL.' );
	// Controles de medios dentro de un repetidor (el núcleo no los importa): pasan al adjunto importado; uno que ya es
	// de este sitio no cambia (nunca se traduce dos veces un ID nuevo).
	$media_context = new Import_Context(
		array(
			'session'     => 'kitintegra02',
			'mode'        => 'add',
			'kit'         => str_repeat( 'c', 40 ),
			'max_post_id' => 0,
			'origin'      => array( 'home' => 'http://a.test', 'site' => 'http://a.test', 'uploads' => 'http://a.test/wp-content/uploads' ),
			'maps'        => array(
				'attachment' => array( 'http://a.test/wp-content/uploads/logo-a.png' => 501, 7 => 502 ),
				'media_url'  => array( 'http://a.test/wp-content/uploads/logo-a.png' => 'https://b.test/wp-content/uploads/logo-a.png' ),
				'url'        => array( 'http://a.test/wp-content/uploads' => 'https://b.test/wp-content/uploads', 'http://a.test' => 'https://b.test' ),
			),
		)
	);
	$media_context->begin_part( 'remap' );
	$media_remapper = new Remapper(
		$media_context,
		array(
			'home'              => 'https://b.test',
			'site'              => 'https://b.test',
			'uploads'           => 'https://b.test/wp-content/uploads',
			'attachment_by_url' => static function () {
				return 0;
			},
			'attachment_url'    => static function ( $id ) {
				return 'https://b.test/wp-content/uploads/adjunto-' . $id . '.png';
			},
			'known'             => static function () {
				return false;
			},
		)
	);
	$logos = $media_remapper->translate( array( 'kind' => 'media', 'path' => 'logo_list.*.image' ), array( 'id' => 6, 'url' => 'http://a.test/wp-content/uploads/logo-a.png' ) );
	$check( 501 === $logos['id'] && 'https://b.test/wp-content/uploads/logo-a.png' === $logos['url'], '8: un control de medios del origen pasa al adjunto importado: ' . json_encode( $logos ) );
	$by_id = $media_remapper->translate( array( 'kind' => 'media', 'path' => 'image' ), array( 'id' => '7', 'url' => 'http://a.test/wp-content/uploads/otro.png' ) );
	$check( '502' === $by_id['id'] && 'https://b.test/wp-content/uploads/adjunto-502.png' === $by_id['url'], '8: sin la URL en el mapa, por su ID de origen (y con el mismo tipo): ' . json_encode( $by_id ) );
	$local = array( 'id' => 7, 'url' => 'https://b.test/wp-content/uploads/ya-importada.png' );
	$check( $local === $media_remapper->translate( array( 'kind' => 'media', 'path' => 'image' ), $local ), '8: lo que ya es de este sitio no se traduce otra vez.' );
	$gallery = $media_remapper->translate( array( 'kind' => 'media', 'path' => 'gallery.*' ), array( array( 'id' => 6, 'url' => 'http://a.test/wp-content/uploads/logo-a.png' ) ), true );
	$check( 501 === $gallery[0]['id'], '8: una galería traduce cada imagen.' );
	$media_kinds = array_column( References::derive( 'logos', array( 'logo_list' => array( 'name' => 'logo_list', 'type' => 'repeater', 'fields' => array( 'image' => array( 'name' => 'image', 'type' => 'media' ) ) ), 'gallery' => array( 'name' => 'gallery', 'type' => 'gallery' ) ) ), 'kind', 'path' );
	$check( array( 'logo_list.*.image' => 'media', 'gallery.*' => 'media' ) === $media_kinds, '8: controles de medios y galerías derivados como `media`, también en repetidores: ' . json_encode( $media_kinds ) );
	// Respaldo por la forma: un alias con nombres de controles antiguos o un widget sin controles registrados.
	$shaped = $media_remapper->apply_elements(
		array(
			array(
				'id'         => 'f0000001',
				'elType'     => 'widget',
				'widgetType' => 'acme-sin-controles',
				'settings'   => array(
					'boton'  => array( 'url' => 'http://a.test/contacto/', 'is_external' => '', 'nofollow' => '' ),
					'solo'   => array( 'url' => 'http://a.test/digi-k-padre/' ),
					'items'  => array( array( '_id' => 'x1', 'foto' => array( 'id' => 6, 'url' => 'http://a.test/wp-content/uploads/logo-a.png' ) ) ),
					'fuera'  => array( 'url' => 'https://otro.test/', 'is_external' => 'on' ),
					'propia' => array( 'id' => 9, 'url' => 'https://b.test/wp-content/uploads/propia.png' ),
				),
				'elements'   => array(),
			),
		),
		300
	);
	$shaped_settings = $shaped[0]['settings'];
	$check( 'https://b.test/digi-k-padre/' === $shaped_settings['solo']['url'], '8: un enlace guardado sólo con `url` también pasa al destino.' );
	$check( 'https://b.test/contacto/' === $shaped_settings['boton']['url'] && 501 === $shaped_settings['items'][0]['foto']['id'] && 'https://otro.test/' === $shaped_settings['fuera']['url'] && 9 === $shaped_settings['propia']['id'], '8: enlaces y medios del origen se traducen por su forma aunque el widget no declare esos controles: ' . json_encode( $shaped_settings ) );
	$media_context->end_part();

	// Controles de consulta: los traduce K salvo lo que ya traduce el núcleo (por nombre o con su método propio).
	$query_controls = array(
		'post_query_posts_ids'          => array( 'name' => 'post_query_posts_ids', 'type' => 'query', 'autocomplete' => array( 'object' => 'post' ) ),
		'post_query_include_term_ids'   => array( 'name' => 'post_query_include_term_ids', 'type' => 'query', 'autocomplete' => array( 'object' => 'cpt_tax' ) ),
		'post_query_exclude_ids'        => array( 'name' => 'post_query_exclude_ids', 'type' => 'query', 'autocomplete' => array( 'object' => 'post' ) ),
		'post_taxonomy_posts_ids'       => array( 'name' => 'post_taxonomy_posts_ids', 'type' => 'query', 'autocomplete' => array( 'object' => 'tax' ) ),
		'post_query_include_authors'    => array( 'name' => 'post_query_include_authors', 'type' => 'query', 'autocomplete' => array( 'object' => 'author' ) ),
		'popup_action_popup_id'         => array( 'name' => 'popup_action_popup_id', 'type' => 'query', 'autocomplete' => array( 'object' => 'library_template' ) ),
		'template_id'                   => array( 'name' => 'template_id', 'type' => 'template_query', 'autocomplete' => array( 'object' => 'library_template' ) ),
		'raro'                          => array( 'name' => 'raro', 'type' => 'query', 'autocomplete' => array( 'object' => 'js' ) ),
	);
	$kinds_none     = array_column( References::derive( 'w', $query_controls, References::CORE_NONE ), 'kind', 'path' );
	$kinds_by_name  = array_column( References::derive( 'w', $query_controls, References::CORE_BY_NAME ), 'kind', 'path' );
	$kinds_override = array_column( References::derive( 'w', $query_controls, References::CORE_OVERRIDE ), 'kind', 'path' );
	$check( array( 'post_query_posts_ids' => 'post', 'post_query_include_term_ids' => 'term', 'post_query_exclude_ids' => 'post', 'post_taxonomy_posts_ids' => 'term', 'post_query_include_authors' => 'user', 'popup_action_popup_id' => 'template', 'template_id' => 'template' ) === $kinds_none, '8: sin traducción del núcleo, K traduce cada control de consulta según su objeto: ' . json_encode( $kinds_none ) );
	$check( ! isset( $kinds_by_name['post_query_include_term_ids'] ) && isset( $kinds_by_name['post_query_posts_ids'], $kinds_by_name['post_query_exclude_ids'], $kinds_by_name['template_id'] ), '8: con el rasgo de importación, lo que contiene `term_ids`/`post_ids` lo traduce el núcleo; `posts_ids`, `exclude_ids` y template_query, K: ' . json_encode( $kinds_by_name ) );
	$check( ! isset( $kinds_override['template_id'] ) && isset( $kinds_override['post_query_include_term_ids'] ), '8: con un método propio (Loop Grid), la plantilla la traduce el núcleo y el resto K: ' . json_encode( $kinds_override ) );

	// 9. Lo que la segunda y la tercera corrida mostraron.
	$context_hooks = array();
	foreach ( Context::hooks() as $hook ) {
		$context_hooks[ $hook[0] ] = $hook[1];
	}
	$check( isset( $context_hooks['created_term'] ), '9: los términos que crea el WXR llevan la sesión (revertir los borra, A10).' );
	$shown = \Digitalisimo\Elements\Import\Kits\Report::public_value( array( 'key' => '_elementor_edit_mode', 'type' => 'elementor_library', 'meta' => '_elementor_elements_default_values', 'otra' => 'elementor_pro_theme_builder_conditions', 'libre' => 'acme_objeto' ) );
	$check( false === stripos( json_encode( $shown ), 'elementor' ) && 'acme_objeto' === $shown['libre'] && 'plantilla' === $shown['type'], '9: el informe nombra con palabras propias las metas y los tipos internos del constructor: ' . json_encode( $shown, JSON_UNESCAPED_UNICODE ) );
	$check( 'datos internos del constructor' === \Digitalisimo\Elements\Import\Kits\Wxr_Guard::public_key( '_elementor_data' ) && 'acme_objeto' === \Digitalisimo\Elements\Import\Kits\Wxr_Guard::public_key( 'acme_objeto' ), '9: el informe no muestra el nombre interno de las metas del constructor.' );
	$dated = \Digitalisimo\Elements\Import\Kits\Wxr_Guard::processed( array( 'post_type' => 'attachment', 'post_status' => 'inherit' ), array( 'post_date' => '2026-01-15 10:00:00', 'post_date_gmt' => '0000-00-00 00:00:00' ) );
	$check( '2026-01-15 10:00:00' === ( $dated['post_date'] ?? '' ) && ! isset( $dated['post_date_gmt'] ), '9: la fecha de cada entrada del WXR se conserva (la nula no).' );
	$check( false !== strpos( $read( $M . '/kits/runners/class-site-settings.php' ), 'self::save_kit( $kit, $merged[\'settings\'], true );' ), '9: en «agregar» el kit queda sólo con las claves pedidas (sin viewport_md ni viewport_lg añadidos, D11).' );
	$check( 1 === preg_match( '/if \( ! \$defaults \) \{\s*\$id = self::kit_id\( \$kit \);/', $read( $M . '/kits/parts/class-element-defaults.php' ) ), '9: sin valores por defecto la meta del kit se borra (revertir no deja `[]`).' );
	$check( false !== strpos( $read( $E . '/modules/assets-manager/asset-types/fonts/import-export-customization/import-runner.php' ), "wp_set_object_terms( \$font_id, 'custom', Fonts_Manager::TAXONOMY );" ), '9: la fuente importada lleva su tipo.' );

	$check( 4 === count( array_intersect( array( '_elementor_version', '_elementor_pro_version', '_elementor_global_class_usage_indexed', '_elementor_global_class_usage_indexed_preview' ), \Digitalisimo\Elements\Import\Kits\Runners\Import_Hooks::KIT_METAS ) ), '9: el respaldo del kit incluye las metas que reescribe su guardado (revertir las deja como estaban).' );
	$check( false !== strpos( $read( $E . '/modules/assets-manager/asset-types/icons/import-export-customization/revert-runner.php' ), 'self::digitalisimo_tidy_folder();' ), '9: revertir no deja la carpeta de iconos vacía que creó la importación.' );

	// WP-CLI: el contexto pide todos los controles (los de estilo incluidos) y al terminar deja el valor anterior.
	kt_reset();
	\Elementor\Core\Frontend\Performance::$use = false;
	Context::activate( '' );
	$during = \Elementor\Core\Frontend\Performance::is_use_style_controls();
	Context::deactivate();
	$check( true === $during && false === \Elementor\Core\Frontend\Performance::is_use_style_controls(), '9: durante la importación el núcleo da también los controles de estilo (WP-CLI perdía valores por defecto y referencias).' );
	$check( 1 === preg_match( "/'customization' => array\(\s*'settings'\s*=> null,\s*'templates' => null,\s*'content'\s*=> null,/", $read( $M . '/kits/class-session.php' ) ), '9: la exportación por WP-CLI es «exportar todo», con las categorías y etiquetas de las entradas.' );

	// A13: tras 15 s de petición no se descarga otro medio (queda «no descargado (tiempo)»), aunque la red fuera rápida.
	kt_reset();
	\Digitalisimo\Elements\Import\Kits\Media_Proxy::reset( array() );
	\Digitalisimo\Elements\Import\Kits\Media_Proxy::set_request_start( microtime( true ) - 2 );
	$early = \Digitalisimo\Elements\Import\Kits\Media_Proxy::pre_http( false, array(), 'http://lento.test/wp-content/uploads/img-001.png' );
	\Digitalisimo\Elements\Import\Kits\Media_Proxy::set_request_start( microtime( true ) - 16 );
	$late = \Digitalisimo\Elements\Import\Kits\Media_Proxy::pre_http( false, array(), 'http://lento.test/wp-content/uploads/img-002.png' );
	$check( false === $early && $late instanceof WP_Error && 'digitalisimo_kit_time' === $late->get_error_code(), '9: con 15 s de petición, el medio siguiente queda «no descargado (tiempo)» (D13, A13).' );
	$args = \Digitalisimo\Elements\Import\Kits\Media_Proxy::http_args( array( 'timeout' => 30 ), 'http://lento.test/x.png' );
	$check( 1.0 === (float) $args['timeout'], '9: el tiempo de una descarga nunca pasa del que le queda a la petición.' );

	// Temporales del núcleo: sólo dentro de uploads/elementor/tmp/.
	kt_reset();
	$up = $GLOBALS['kt']['uploads'];
	@mkdir( $up . '/elementor/tmp/abc123/content/page', 0777, true ); // phpcs:ignore
	file_put_contents( $up . '/elementor/tmp/abc123/content/page/1.json', '{}' );
	@mkdir( $up . '/otra', 0777, true ); // phpcs:ignore
	file_put_contents( $up . '/otra/x.txt', 'x' );
	$check( ! Session::delete_extracted( 'otra' ) && is_file( $up . '/otra/x.txt' ), '9: fuera de uploads/elementor/tmp/ no se borra nada.' );
	$check( ! Session::delete_extracted( 'elementor/tmp' ) && is_dir( $up . '/elementor/tmp' ), '9: la carpeta tmp/ del núcleo no se borra entera.' );
	$check( ! Session::delete_extracted( 'elementor/tmp/abc123/../../../otra' ) && is_file( $up . '/otra/x.txt' ), '9: una ruta con `..` no sale de su carpeta.' );
	$check( Session::delete_extracted( 'elementor/tmp/abc123' ) && ! is_dir( $up . '/elementor/tmp/abc123' ), '9: la copia extraída de la sesión se borra al terminar.' );
	$token = Session::create_tmp();
	$check( is_dir( $up . '/digitalisimo-elements/tmp/' . $token['token'] ), '9: los temporales de K existen mientras dura la sesión.' );
	Session::delete_tmp( $token['token'] );
	$check( ! is_dir( $up . '/digitalisimo-elements/tmp' ) && ! is_dir( $up . '/digitalisimo-elements' ), '9: sin temporales pendientes, tmp/ (y la carpeta de Elements vacía) no se quedan en uploads.' );

	// Título y texto alternativo de los adjuntos.
	kt_reset();
	$attachment = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'logo-a.png', 'meta_input' => array( '_wp_attached_file' => 'logo-a.png' ) ) );
	$kept       = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_title' => 'Título de alguien', 'meta_input' => array( '_wp_attached_file' => 'otra.png' ) ) );
	$describe   = new \ReflectionMethod( Media::class, 'describe' );
	if ( PHP_VERSION_ID < 80100 ) {
		$describe->setAccessible( true );
	}
	$describe_context = new Import_Context( array( 'session' => 'kitintegra03', 'mode' => 'add', 'max_post_id' => 0 ) );
	$describe->invoke( null, $attachment, array( 'title' => 'Logo de A', 'alt' => 'Logotipo <b>A</b>' ), $describe_context );
	$describe->invoke( null, $kept, array( 'title' => 'Logo de A', 'alt' => 'Otro' ), $describe_context );
	$check( 'Logo de A' === get_post( $attachment )->post_title && 'Logotipo A' === get_post_meta( $attachment, '_wp_attachment_image_alt', true ), '9: el adjunto importado recupera su título y su texto alternativo (saneados).' );
	$check( 'Título de alguien' === get_post( $kept )->post_title, '9: un título que no es el nombre del archivo no se pisa.' );

	// A13: dentro de la importación no se guardan revisiones (cada documento se guarda dos veces) y el SVG toma sus
	// medidas del archivo local: sin ellas el núcleo las pedía por HTTP al propio sitio y la petición esperaba 10 s.
	kt_reset();
	$up = $GLOBALS['kt']['uploads'];
	file_put_contents( $up . '/figura.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 80"><rect width="10" height="10"/></svg>' );
	file_put_contents( $up . '/medida.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="64px" height="32" viewBox="0 0 120 80"></svg>' );
	file_put_contents( $up . '/rota.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120' );
	$svgs = array();
	foreach ( array( 'figura.svg', 'medida.svg', 'rota.svg', 'foto.png' ) as $file ) {
		$svgs[ $file ] = wp_insert_post( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_input' => array( '_wp_attached_file' => $file ) ) );
		$GLOBALS['kt']['posts'][ $svgs[ $file ] ]['post_mime_type'] = 'foto.png' === $file ? 'image/png' : 'image/svg+xml';
	}
	$check( array() === Context::svg_dimensions( array(), $svgs['figura.svg'] ), '9: fuera de la importación el SVG sigue el camino del núcleo.' );
	Context::activate( '' );
	$check( 0 === Context::no_revisions( 25 ), '9: durante la importación no se guardan revisiones (A13).' );
	$from_box  = Context::svg_dimensions( array( 'filesize' => 10 ), $svgs['figura.svg'] );
	$from_attr = Context::svg_dimensions( false, $svgs['medida.svg'] );
	$broken    = Context::svg_dimensions( array(), $svgs['rota.svg'] );
	$kept_svg  = Context::svg_dimensions( array( 'width' => 5, 'height' => 6 ), $svgs['medida.svg'] );
	$png       = Context::svg_dimensions( array(), $svgs['foto.png'] );
	Context::deactivate();
	$check( array( 'filesize' => 10, 'width' => 120, 'height' => 80 ) === $from_box && array( 'width' => 64, 'height' => 32 ) === $from_attr, '9: el SVG importado toma ancho y alto de sus atributos o de su viewBox, leídos del archivo local: ' . json_encode( array( $from_box, $from_attr ) ) );
	$check( array() === $broken && array( 'width' => 5, 'height' => 6 ) === $kept_svg && array() === $png, '9: un SVG ilegible, unas medidas ya presentes y una imagen que no es SVG quedan como estaban.' );
	$hooks_add = array();
	foreach ( Context::hooks() as $hook ) {
		$hooks_add[ $hook[0] ] = $hook[2];
	}
	$check( 99 === ( $hooks_add['wp_revisions_to_keep'] ?? 0 ) && ( $hooks_add['wp_update_attachment_metadata'] ?? 10 ) < 10, '9: el contexto quita las revisiones y mide el SVG antes que el núcleo (prioridad menor que 10).' );

	if ( $failures ) {
		// Una excepción y no `exit( 1 )`: php-wasm sale con 0 aunque el script termine con error.
		throw new \RuntimeException( 'elements-kit-integration: FALLA en ' . count( $failures ) . " comprobación(es)\n - " . implode( "\n - ", $failures ) );
	}
	echo 'elements-kit-integration: ok (' . count( $codes ) . " códigos del informe con sección)\n";
}

