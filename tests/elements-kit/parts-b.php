<?php
/**
 * Kits (etapa K): secciones B1 a B8 de `tests/elements-kit-parts.php` (§13.1 «parts», puntos 2 a 8).
 *
 * Exportación propia (IDs de elemento, miniaturas, lista negra, WXR anónimo), las siete partes de K de ida y de
 * vuelta entre un sitio A y un sitio B en memoria, y su reversión. La incluye la prueba del contrato, después de sus
 * secciones: comparte `$check`, `$wrongs` y los stubs del contrato (`apply_filters`, `wp_upload_dir`…); el resto de
 * WordPress está en `wp-stubs.php`.
 */
namespace Elementor\App\Modules\ImportExportCustomization\Runners\Export {
	if ( ! class_exists( __NAMESPACE__ . '\Export_Runner_Base', false ) ) {
		/** Runners del núcleo simulados: exportan desde las entradas en memoria, con IDs de elemento nuevos. */
		abstract class Export_Runner_Base {
			abstract public function should_export( array $data );
			abstract public function export( array $data );
		}
		/** El exportador del núcleo: IDs nuevos y, en un marcador, el original (`on_export`). */
		function kit_core_tree( array $elements, bool $shuffle = false ): array {
			$out = array();
			foreach ( $elements as $element ) {
				if ( 'widget' === ( $element['elType'] ?? '' ) && 'digitalisimo-unconverted' === ( $element['widgetType'] ?? '' ) ) {
					$out[] = $element['settings']['digitalisimo_unconverted']['element'];
					continue;
				}
				$element['id']       = 'r' . substr( md5( $element['id'] . 'core' ), 0, 6 );
				$element['elements'] = kit_core_tree( $element['elements'] ?? array() );
				$out[]               = $element;
			}
			if ( $shuffle ) {
				$out[] = array( 'id' => 'rextra1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array(), 'elements' => array() );
			}
			return $out;
		}
		function kit_core_file( string $path, int $id, bool $shuffle = false ): array {
			return array(
				'path' => $path . $id,
				'data' => array(
					'content'  => kit_core_tree( json_decode( (string) get_post_meta( $id, '_elementor_data', true ), true ), $shuffle ),
					'settings' => array(),
					'metadata' => array(
						'_edit_lock'  => '1:1',
						'custom_meta' => 'se queda',
					),
				),
			);
		}
		class Templates extends Export_Runner_Base {
			public static $ids = array();
			public static function get_name(): string {
				return 'templates';
			}
			public function should_export( array $data ) {
				return true;
			}
			public function export( array $data ) {
				$files    = array();
				$manifest = array();
				foreach ( self::$ids as $id ) {
					$files[]         = kit_core_file( 'templates/', $id );
					$manifest[ $id ] = array(
						'title'      => 'Cabecera',
						'doc_type'   => 'header',
						'thumbnail'  => get_the_post_thumbnail_url( $id ),
						'location'   => 'header',
						'conditions' => array( array( 'type' => 'include', 'name' => 'general', 'sub_name' => '', 'sub_id' => '' ) ),
					);
				}
				return array(
					'files'    => $files,
					'manifest' => array( array( 'templates' => $manifest ) ),
				);
			}
		}
		class Elementor_Content extends Export_Runner_Base {
			public static $ids      = array();
			public static $mismatch = 0;
			public static $args     = null;
			public static $types    = null;
			public function __construct() {
			}
			public static function get_name(): string {
				return 'elementor-content';
			}
			public function should_export( array $data ) {
				return true;
			}
			public function export( array $data ) {
				self::$types = kit_stub_apply( 'elementor/import-export-customization/elementor-content/post-types/customization', array( 'page' ), null );
				self::$args  = kit_stub_apply( 'elementor/import-export-customization/export/elementor-content/query-args/customization', array( 'post_type' => 'page', 'post_status' => 'publish' ), 'page', null );
				$files       = array();
				$manifest    = array();
				foreach ( self::$ids as $id ) {
					$files[]         = kit_core_file( 'content/page/', $id, self::$mismatch === $id );
					$manifest[ $id ] = array(
						'title'     => 'Página ' . $id,
						'doc_type'  => 'wp-page',
						'thumbnail' => get_the_post_thumbnail_url( $id ),
						'url'       => get_permalink( $id ),
						'terms'     => array(),
					);
				}
				return array(
					'files'    => $files,
					'manifest' => array( array( 'content' => array( 'page' => $manifest ) ) ),
				);
			}
		}
		class Wp_Content extends Export_Runner_Base {
			public static $xml     = '';
			public static $posts   = array();
			public static $args    = null;
			public static $filters = array();
			public static function get_name(): string {
				return 'wp-content';
			}
			public function should_export( array $data ) {
				return true;
			}
			public function export( array $data ) {
				self::$args    = kit_stub_apply( 'elementor/import-export-customization/export/wp-content/query-args/customization', array( 'limit' => 20 ), 'post', null );
				self::$filters = array(
					'postmeta' => count( kit_stub_filters( 'wxr_export_skip_postmeta' ) ),
					'termmeta' => count( kit_stub_filters( 'wxr_export_skip_termmeta' ) ),
					'comments' => count( kit_stub_filters( 'wxr_export_skip_commentmeta' ) ),
				);
				return array(
					'files'    => array( array( 'path' => 'wp-content/post/post.xml', 'data' => self::$xml ) ),
					'manifest' => array( array( 'wp-content' => array( 'post' => self::$posts ) ) ),
				);
			}
		}
	}
}

namespace {
	use Digitalisimo\Elements\Import\Module;
	use Digitalisimo\Elements\Import\Kits\Parts;
	use Digitalisimo\Elements\Import\Kits\Part_Context;
	use Digitalisimo\Elements\Import\Kits\Export_Context;
	use Digitalisimo\Elements\Import\Kits\Import_Context;
	use Digitalisimo\Elements\Import\Kits\Revert_Context;
	use Digitalisimo\Elements\Import\Kits\Remapper;
	use Digitalisimo\Elements\Import\Kits\Export\Documents;
	use Digitalisimo\Elements\Import\Kits\Export\Meta_Blacklist;
	use Digitalisimo\Elements\Import\Kits\Export\Templates as Kit_Templates;
	use Digitalisimo\Elements\Import\Kits\Export\Elementor_Content as Kit_Content;
	use Digitalisimo\Elements\Import\Kits\Export\Wp_Content as Kit_Wp_Content;
	use Digitalisimo\Elements\Import\Kits\Parts\Origin;
	use Digitalisimo\Elements\Import\Kits\Parts\Element_Defaults;
	use Digitalisimo\Elements\Import\Kits\Parts\Kit_Settings;
	use Digitalisimo\Elements\Import\Kits\Parts\Files;
	use Digitalisimo\Elements\Import\Kits\Parts\Media;
	use Digitalisimo\Elements\Import\Kits\Parts\Theme_Builder;
	use Digitalisimo\Elements\Import\Kits\Parts\Term_Meta;
	use Elementor\App\Modules\ImportExportCustomization\Runners\Export as Core;

	require_once __DIR__ . '/wp-stubs.php';

	$b_tmp   = sys_get_temp_dir() . '/digi-kit-parts-b-' . bin2hex( random_bytes( 6 ) );
	$b_rmrf  = function ( $dir ) use ( &$b_rmrf ) {
		foreach ( is_dir( $dir ) && ! is_link( $dir ) ? scandir( $dir ) : array() as $name ) {
			if ( '.' !== $name && '..' !== $name ) {
				$path = $dir . '/' . $name;
				is_dir( $path ) && ! is_link( $path ) ? $b_rmrf( $path ) : unlink( $path );
			}
		}
		if ( is_dir( $dir ) ) {
			rmdir( $dir );
		}
	};
	$b_site  = function ( string $host, string $dir ) {
		kit_stub_reset( $dir );
		$GLOBALS['wp']['baseurl']  = 'https://' . $host . '/wp-content/uploads';
		$GLOBALS['wp']['home']     = 'https://' . $host;
		$GLOBALS['kit']['uploads'] = array(
			'basedir' => $dir,
			'baseurl' => $GLOBALS['wp']['baseurl'],
			'error'   => false,
		);
	};
	$b_put   = function ( string $dir, string $relative, string $bytes ) {
		wp_mkdir_p( dirname( $dir . '/' . $relative ) );
		file_put_contents( $dir . '/' . $relative, $bytes );
		return $dir . '/' . $relative;
	};
	$b_codes = function ( Part_Context $context, string $code ) {
		return array_values(
			array_filter(
				$context->get_report()['entries'],
				function ( $entry ) use ( $code ) {
					return $code === $entry['code'];
				}
			)
		);
	};
	$b_attachment = function ( int $id, string $file ) {
		kit_stub_post( array( 'ID' => $id, 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
		update_post_meta( $id, '_wp_attached_file', $file );
		return $id;
	};
	$b_widget = function ( string $id, string $type, array $settings, array $children = array() ) {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => $children );
	};
	$b_plugin = function ( int $kit ) {
		$types = array(
			'heading'               => new Kit_Stub_Controls( array( 'link' => array( 'name' => 'link', 'type' => 'url' ) ) ),
			'button'                => new Kit_Stub_Controls(),
			'digitalisimo-icon-box' => new Kit_Stub_Controls( array( 'link' => array( 'name' => 'link', 'type' => 'url' ) ) ),
			'container'             => new Kit_Stub_Controls(),
			'section'               => new Kit_Stub_Controls(),
			'column'                => new Kit_Stub_Controls(),
		);
		\Elementor\Plugin::$instance = (object) array(
			'widgets_manager'  => new Kit_Stub_Manager( $types ),
			'elements_manager' => new Kit_Stub_Manager( $types ),
			'documents'        => new Kit_Stub_Documents(),
			'kits_manager'     => new Kit_Stub_Kits( $kit ),
		);
	};
	$b_part = function ( string $key, Part_Context $context, callable $fn ) {
		$context->begin_part( $key );
		try {
			return $fn();
		} finally {
			$context->end_part();
		}
	};

	try {
		$check( array() === Parts::missing_builtin(), 'Las siete partes de K existen: ' . implode( ', ', Parts::missing_builtin() ) );
		foreach ( Parts::BUILTIN as $key => $class ) {
			$part = new $class();
			$check( $key === $part->key() && 1 === $part->version() && is_array( $part->kit_meta_keys() ), "La parte {$key} se describe con un constructor sin argumentos." );
		}
		$check( array( Module::KIT_META_UNCONVERTED_DEFAULTS ) === ( new Element_Defaults() )->kit_meta_keys(), 'Los valores por defecto desconocidos sobreviven si el núcleo crea un kit nuevo (meta_to_preserve).' );

		// ════ Sitio A: contenido ════════════════════════════════════════════════════════════════════════════════
		$a_dir = $b_tmp . '/uploads-a';
		$b_site( 'a.test', $a_dir );
		$GLOBALS['kit']['multisite'] = true;
		$GLOBALS['kit']['main']      = true;
		$up = 'https://a.test/wp-content/uploads';
		$b_put( $a_dir, '2026/01/foto.jpg', 'JPG-FOTO' );
		$b_put( $a_dir, '2026/01/foto-300x200.jpg', 'JPG-FOTO-PEQUENA' );
		$b_put( $a_dir, '2026/01/doc.pdf', '%PDF-doc' );
		$b_put( $a_dir, '2026/01/big.png', str_repeat( 'B', 200 ) );
		$b_put( $a_dir, '2026/01/cat.png', 'PNG-CAT' );
		$b_put( $a_dir, '2026/01/adj.png', 'PNG-ADJ' );
		$b_put( $a_dir, 'sites/3/x.png', 'PNG-OTRO-SITIO' );
		$b_put( $a_dir, 'woocommerce_uploads/x.pdf', '%PDF-privado' );
		$b_put( $a_dir, 'digitalisimo-elements/shapes/ola.svg', '<svg id="ola"><path d="M0"/></svg>' );
		$b_put( $a_dir, 'digitalisimo-elements/shapes/ola-negative.svg', '<svg id="ola-n"><path d="M1"/></svg>' );
		$b_put( $a_dir, 'digitalisimo-elements/shapes/sin-uso.svg', '<svg id="sin-uso"></svg>' );
		$b_put( $a_dir, 'digitalisimo-elements/masks/mask-3.svg', '<svg id="mask-3"></svg>' );
		$b_attachment( 201, '2026/01/foto.jpg' );
		$b_attachment( 202, 'sites/3/x.png' );
		$b_attachment( 203, 'woocommerce_uploads/x.pdf' );
		$b_attachment( 204, '2026/01/big.png' );
		$b_attachment( 206, '2026/01/doc.pdf' );
		$b_attachment( 207, '2026/01/cat.png' );
		$b_attachment( 305, '2026/01/adj.png' );

		$kit_a = kit_stub_post( array( 'ID' => 900, 'post_type' => 'elementor_library' ) );
		update_post_meta( $kit_a, '_elementor_elements_default_values', json_encode( array( 'heading' => array( 'title_color' => '#fff', 'invalid_x' => 1, '__globals__' => array( 'typography_typography' => 'globals/typography?id=secondary' ) ), 'acme-widget' => array( 'a' => 1 ) ) ) );
		update_post_meta( $kit_a, Module::KIT_META_UNCONVERTED_DEFAULTS, array( 'old-widget' => array( 'b' => 2 ) ) );
		update_post_meta( $kit_a, '_elementor_page_settings', array( 'site_logo' => array( 'url' => $up . '/2026/01/cat.png', 'id' => 207 ), 'body_background_image' => array( 'url' => $up . '/2026/01/foto.jpg', 'id' => 201 ) ) );
		$b_plugin( $kit_a );

		$marker   = $b_widget(
			'mrk0001',
			Module::WIDGET,
			array( 'digitalisimo_unconverted' => array( 'v' => 1, 'type' => 'widget/acme-hero', 'reason' => 'unknown', 'source' => 'kit', 'element' => $b_widget( 'acm0001', 'acme-hero', array( 'bg' => array( 'url' => $up . '/2026/01/doc.pdf', 'id' => 206 ) ) ) ) )
		);
		$template = array(
			array(
				'id'       => 'sec0001',
				'elType'   => 'section',
				'settings' => array( 'shape_divider_top' => 'digi-ola', 'shape_divider_bottom' => 'digi-falta' ),
				'elements' => array(
					array(
						'id'       => 'col0001',
						'elType'   => 'column',
						'settings' => array(),
						'elements' => array(
							$b_widget(
								'hdg0001',
								'heading',
								array(
									'image'      => array( 'url' => $up . '/2026/01/foto.jpg', 'id' => 201 ),
									'bg'         => array( 'url' => $up . '/sites/3/x.png', 'id' => 202 ),
									'file'       => array( 'url' => $up . '/woocommerce_uploads/x.pdf', 'id' => 203 ),
									'huge'       => array( 'url' => $up . '/2026/01/big.png', 'id' => 204 ),
									'outside'    => array( 'url' => 'https://otro.test/x.png', 'id' => 0 ),
									'custom_css' => 'selector{background:url(' . $up . '/2026/01/foto-300x200.jpg)} .m{mask-image:url("' . $up . '/digitalisimo-elements/masks/mask-3.svg")}',
								)
							),
							$b_widget( 'icb0001', 'digitalisimo-icon-box', array( 'link' => array( 'url' => $up . '/2026/01/doc.pdf' ) ) ),
							$marker,
						),
					),
				),
			),
		);
		$tpl = kit_stub_post( array( 'ID' => 101, 'post_type' => 'elementor_library' ) );
		update_post_meta( $tpl, '_elementor_data', json_encode( $template ) );
		update_post_meta( $tpl, '_elementor_conditions', array( 'include/general', 'include/singular/page/102', 'exclude/singular/by_author/1' ) );
		$page = kit_stub_post( array( 'ID' => 102, 'post_name' => 'contacto' ) );
		update_post_meta( $page, '_elementor_data', json_encode( array( array( 'id' => 'con0001', 'elType' => 'container', 'settings' => array( '__globals__' => array( 'background_color' => 'globals/colors?id=primary' ) ), 'elements' => array( $b_widget( 'hdg0002', 'heading', array( 'title' => 'Hola' ) ) ) ) ) ) );
		update_post_meta( $page, '_thumbnail_id', 201 );
		$other = kit_stub_post( array( 'ID' => 103 ) );
		update_post_meta( $other, '_elementor_data', json_encode( array( $b_widget( 'hdg0003', 'heading', array() ) ) ) );
		kit_stub_post( array( 'ID' => 104, 'post_status' => 'draft' ) );
		kit_stub_post( array( 'ID' => 105, 'post_password' => 'secreto' ) );
		foreach ( array( 104, 105 ) as $id ) {
			update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		}
		kit_stub_post( array( 'ID' => 301, 'post_type' => 'post' ) );
		$cat = kit_stub_term( array( 'term_id' => 40, 'taxonomy' => 'category', 'slug' => 'noticias' ) );
		add_term_meta( $cat, 'thumbnail_id', '207' );
		add_term_meta( $cat, 'color', '#f00' );
		add_term_meta( $cat, '_edit_lock', '1:1' );
		add_term_meta( $cat, 'objeto', 'O:8:"stdClass":1:{s:1:"a";i:1;}' );
		kit_stub_term( array( 'term_id' => 41, 'taxonomy' => 'category', 'slug' => 'vacia' ) );
		add_term_meta( 41, 'color', '#0ff' );
		kit_stub_term( array( 'term_id' => 42, 'taxonomy' => 'product_cat', 'slug' => 'de-otro-tipo' ) );
		add_term_meta( 42, 'color', '#0f0' );

		// ── B8. Exportación propia ─────────────────────────────────────────────────────────────────────────────
		$export = Export_Context::for_site(
			array(
				'include' => array( 'templates', 'content', 'settings' ),
				'limits'  => array( 'file' => 100, 'total' => 0 ),
			)
		);
		Core\Templates::$ids          = array( 101 );
		Core\Elementor_Content::$ids  = array( 102, 103 );
		Core\Elementor_Content::$mismatch = 103;
		Core\Wp_Content::$posts       = array( 301 => array( 'id' => 301 ), 302 => array( 'id' => 302 ), 303 => array( 'id' => 303 ), 304 => array( 'id' => 304 ), 305 => array( 'id' => 305 ) );
		$wxr_item = function ( $id, $status, $type = 'post', $password = '', $extra = '' ) {
			return '<item><title>Entrada ' . $id . '</title><dc:creator><![CDATA[autora]]></dc:creator><guid isPermaLink="false"><![CDATA[autora]]></guid><wp:post_id>' . $id . '</wp:post_id><wp:status><![CDATA[' . $status . ']]></wp:status><wp:post_type><![CDATA[' . $type . ']]></wp:post_type><wp:post_password><![CDATA[' . $password . ']]></wp:post_password>' . $extra . '</item>';
		};
		Core\Wp_Content::$xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n"
			. '<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/"><channel>'
			. '<wp:author><wp:author_id>1</wp:author_id><wp:author_login><![CDATA[autora]]></wp:author_login><wp:author_email><![CDATA[autora@example.com]]></wp:author_email><wp:author_display_name><![CDATA[Daniel Persona]]></wp:author_display_name><wp:author_first_name><![CDATA[Daniel]]></wp:author_first_name><wp:author_last_name><![CDATA[Persona]]></wp:author_last_name></wp:author>'
			. $wxr_item( 301, 'publish', 'post', '', '<wp:postmeta><wp:meta_key><![CDATA[_edit_lock]]></wp:meta_key><wp:meta_value><![CDATA[1:1]]></wp:meta_value></wp:postmeta><wp:postmeta><wp:meta_key><![CDATA[keep_me]]></wp:meta_key><wp:meta_value><![CDATA[sí]]></wp:meta_value></wp:postmeta><wp:comment><wp:comment_id>1</wp:comment_id><wp:comment_author_email><![CDATA[lector@example.com]]></wp:comment_author_email><wp:comment_author_IP><![CDATA[192.0.2.1]]></wp:comment_author_IP></wp:comment>' )
			. $wxr_item( 302, 'draft' )
			. $wxr_item( 303, 'private' )
			. $wxr_item( 304, 'publish', 'post', 'secreto' )
			. $wxr_item( 305, 'inherit', 'attachment' )
			. '</channel></rss>';

		$templates = ( new Kit_Templates( $export ) )->export( array() );
		$content   = ( new Kit_Content( $export ) )->export( array() );
		$wxr       = ( new Kit_Wp_Content( $export ) )->export( array() );

		$tpl_file = $templates['files'][0]['data'];
		$check( 'sec0001' === $tpl_file['content'][0]['id'] && 'col0001' === $tpl_file['content'][0]['elements'][0]['id'] && 'hdg0001' === $tpl_file['content'][0]['elements'][0]['elements'][0]['id'], 'IDs de elemento repuestos al exportar (D20).' );
		$check( 'acm0001' === $tpl_file['content'][0]['elements'][0]['elements'][2]['id'] && 'acme-hero' === $tpl_file['content'][0]['elements'][0]['elements'][2]['widgetType'], 'Un marcador se exporta como su original, con su ID.' );
		$check( array( 'custom_meta' => 'se queda' ) === $tpl_file['metadata'], '`metadata` sin la lista negra (§8.2).' );
		$check( array( array( 'type' => 'include', 'name' => 'general', 'sub_name' => '', 'sub_id' => '' ) ) === $templates['manifest'][0]['templates'][101]['conditions'], 'El `conditions` nativo del manifiesto no cambia (V-24).' );
		$by_path = array_column( $content['files'], 'data', 'path' );
		$check( 'con0001' === $by_path['content/page/102']['content'][0]['id'] && 'hdg0002' === $by_path['content/page/102']['content'][0]['elements'][0]['id'], 'IDs repuestos en el contenido.' );
		$check( 0 === strpos( $by_path['content/page/103']['content'][0]['id'], 'r' ), 'Si el árbol no coincide, el documento conserva los IDs nuevos…' );
		$check( 1 === count( $b_codes( $export, 'ids.mismatch' ) ) && 103 === $b_codes( $export, 'ids.mismatch' )[0]['data']['post'], '…y se informa.' );
		$check( $up . '/2026/01/foto.jpg' === $content['manifest'][0]['content']['page'][102]['thumbnail'], 'Miniatura en tamaño completo en el manifiesto (CC-23).' );
		$check( false === Core\Elementor_Content::$args['has_password'] && array( 'page' ) === Core\Elementor_Content::$types, 'El contenido protegido con contraseña no se exporta.' );
		$check( -1 === Core\Wp_Content::$args['limit'] && array( 'postmeta' => 1, 'termmeta' => 1, 'comments' => 1 ) === Core\Wp_Content::$filters, 'WXR sin tope de 20 por tipo (CC-13) y con la lista negra durante la exportación.' );
		$check( array() === kit_stub_filters( 'wxr_export_skip_postmeta' ) && array() === kit_stub_filters( Kit_Content::QUERY_FILTER ), 'Los filtros de la exportación se quitan al terminar.' );
		$xml = $wxr['files'][0]['data'];
		$check( false === strpos( $xml, 'autora' ) && false === strpos( $xml, 'Persona' ) && false === stripos( $xml, 'example.com' ) && false !== strpos( $xml, 'autor-1' ) && false !== strpos( $xml, 'Autor 1' ), 'WXR anónimo: sin login (tampoco en el `<guid>` que escribe el núcleo), nombres ni correos (CR-14).' );
		$check( false === strpos( $xml, '<wp:comment>' ) && false === strpos( $xml, '192.0.2.1' ), 'WXR sin comentarios.' );
		$check( false === strpos( $xml, '_edit_lock' ) && false !== strpos( $xml, 'keep_me' ), 'WXR sin la lista negra; lo demás pasa (sin lista blanca, V-20).' );
		$check( false === strpos( $xml, 'secreto' ) && false === strpos( $xml, '<wp:post_id>302<' ) && false === strpos( $xml, '<wp:post_id>303<' ) && false === strpos( $xml, '<wp:post_id>304<' ) && false !== strpos( $xml, '<wp:post_id>305<' ), 'WXR sólo con lo publicado y los adjuntos: sin borradores, privados ni protegidos.' );
		$check( array( 301, 305 ) === array_keys( $wxr['manifest'][0]['wp-content']['post'] ), 'El manifiesto tampoco nombra lo que no viajó.' );
		$check( null !== simplexml_load_string( $xml ), 'El WXR anonimizado sigue siendo XML válido.' );
		$check( null === Kit_Wp_Content::anonymize( '<!DOCTYPE x [<!ENTITY a "b">]><x/>' ) && null === Kit_Wp_Content::anonymize( 'no xml' ), 'Un WXR con DOCTYPE o inválido no se reescribe.' );
		$not = $export->get_not_exported();
		$check( 2 === $not['drafts'] && 1 === $not['private'] && 2 === $not['protected'] && 1 === $not['comments'], 'not_exported cuenta borradores, privados, protegidos y comentarios: ' . json_encode( $not ) );
		$check( array( 101 ) === $export->exported( 'template' ) && array( 102, 103, 301 ) === $export->exported( 'post' ) && array( 305 ) === $export->exported( 'attachment' ), 'Lo exportado queda anotado para las partes.' );
		$check( null === Documents::restore_ids( array( array( 'elType' => 'widget', 'widgetType' => 'a' ) ), array( array( 'elType' => 'widget', 'widgetType' => 'b' ) ) ) && null === Documents::restore_ids( array( 1 ), array() ), 'restore_ids(): otro tipo u otra longitud no coincide.' );

		// ── Partes: exportación ────────────────────────────────────────────────────────────────────────────────
		$GLOBALS['kit']['filters'][ Module::FILTER_THEME_BUILDER_FIELDS ] = function ( $fields, $id ) {
			$fields['prioridad'] = array(
				'export' => function ( $template ) {
					return 101 === $template ? 7 : null;
				},
				'import' => function ( $template, $value, $context ) {
					update_post_meta( $template, '_prioridad', $value );
				},
			);
			$fields['Mal Nombre'] = array();
			return $fields;
		};
		$out = array();
		foreach ( Parts::all() as $key => $part ) {
			$warned      = $wrongs(
				function () use ( $b_part, $key, $part, $export, &$out ) {
					$out[ $key ] = $b_part( $key, $export, function () use ( $part, $export ) {
						return $part->export( $export );
					} );
				}
			);
			$check( 'theme-builder' === $key ? 1 === count( $warned ) : array() === $warned, "La exportación de {$key} no deja avisos inesperados." );
		}
		$check( null === $out['origin'], 'origin no exporta datos: la huella del kit va en el manifiesto.' );

		// B1: valores por defecto.
		$defaults = $out['element-defaults'];
		$check( array( 'title_color' => '#fff', '__globals__' => array( 'typography_typography' => 'globals/typography?id=secondary' ), '_exported' => 'yes' ) === $defaults['defaults']['heading'], 'B1: los tipos conocidos pasan por el saneador nativo.' );
		$check( array( 'old-widget' => array( 'b' => 2 ), 'acme-widget' => array( 'a' => 1 ) ) === $defaults['unconverted'], 'B1: los tipos desconocidos se conservan y se reexportan.' );
		// Globales usados.
		$check( array( 'colors' => array( 'primary' ), 'typography' => array( 'secondary' ) ) === $out['kit-settings']['used'], 'kit-settings: los globales que usa el contenido y los valores por defecto.' );
		// B4 y B5: archivos.
		$check( array( 'masks/mask-3.svg', 'shapes/ola-negative.svg', 'shapes/ola.svg' ) === array_keys( $out['files'] ), 'B4/B5: sólo los separadores usados (con su negativo) y las máscaras del CSS: ' . implode( ', ', array_keys( $out['files'] ) ) );
		$check( hash( 'sha256', '<svg id="ola"><path d="M0"/></svg>' ) === $out['files']['shapes/ola.svg']['sha256'] && $export->has_file( 'files/shapes/ola.svg' ), 'Cada archivo con su huella y en el paquete.' );
		$check( 1 === count( $b_codes( $export, 'files.missing' ) ), 'Un separador usado que no existe se avisa.' );
		// D6: medios.
		$media = $out['media'];
		$check( array( $up . '/2026/01/adj.png', $up . '/2026/01/cat.png', $up . '/2026/01/doc.pdf', $up . '/2026/01/foto-300x200.jpg', $up . '/2026/01/foto.jpg' ) === array_keys( $media ), 'Medios incrustados: controles, URL derivadas, CSS, miniaturas, WXR, ajustes del kit y metas de término: ' . implode( ', ', array_keys( $media ) ) );
		$foto = $media[ $up . '/2026/01/foto.jpg' ];
		$check( hash( 'sha256', 'JPG-FOTO' ) . '.jpg' === $foto['file'] && 'image/jpeg' === $foto['mime'] && 8 === $foto['bytes'] && 201 === $foto['id'] && $export->has_file( 'media/' . $foto['file'] ), 'Cada medio: archivo por sha256, MIME, tamaño e ID de origen.' );
		$check( 1 === preg_match( Module::MEDIA_FILE_PATTERN, $foto['file'] ), 'El nombre cumple la forma de §5.2.' );
		$check( ! isset( $media[ $up . '/sites/3/x.png' ] ) && ! isset( $media[ $up . '/woocommerce_uploads/x.pdf' ] ), 'CR-2: nada de otro sitio de la red ni de woocommerce_uploads.' );
		$check( ! isset( $media[ $up . '/2026/01/big.png' ] ) && ! isset( $media['https://otro.test/x.png'] ) && ! isset( $media[ $up . '/digitalisimo-elements/masks/mask-3.svg' ] ), 'D6: lo que supera el tope y lo externo quedan como enlace; los archivos propios van en files.' );
		$counts = $export->get_report()['counts'];
		$check( 5 === $counts['media.embedded'] && 3 === $counts['media.linked'], 'Informe de medios: incrustados y enlazados: ' . json_encode( array( $counts['media.embedded'] ?? 0, $counts['media.linked'] ?? 0 ) ) );
		$check( in_array( $up . '/2026/01/big.png', $export->exported( 'media_url' ), true ), 'Las URL de medios quedan anotadas para el manifiesto.' );
		$link_ctx = Export_Context::for_site( array( 'media' => 'link', 'include' => array( 'content' ) ) );
		$link_ctx->add_exported( 'post', array( 102 ) );
		$check( null === ( new Media() )->export( $link_ctx ) && 1 === $link_ctx->get_report()['counts']['media.linked'], 'Con el formato «link» no se incrusta nada.' );
		// B3: Theme Builder.
		$check(
			array(
				'101' => array(
					'conditions' => array(
						array( 'type' => 'include', 'name' => 'general', 'sub_name' => '', 'sub_id' => '' ),
						array( 'type' => 'include', 'name' => 'singular', 'sub_name' => 'page', 'sub_id' => '102' ),
						array( 'type' => 'exclude', 'name' => 'singular', 'sub_name' => 'by_author', 'sub_id' => '1' ),
					),
					'fields'     => array( 'prioridad' => 7 ),
				),
			) === $out['theme-builder'],
			'B3: la lista completa de condiciones y los campos propios en theme-builder.json.'
		);
		// B18: metas de término.
		$check( array( '40', '41' ) === array_map( 'strval', array_keys( $out['term-meta'] ) ) && array( 'color' => array( '#f00' ), 'thumbnail_id' => array( '207' ) ) === $out['term-meta'][40]['meta'], 'term-meta: sólo términos de las taxonomías exportadas, sin la lista negra ni objetos.' );
		$check( 1 === count( $b_codes( $export, 'term-meta.object' ) ), 'Un valor serializado con objetos se omite y se anota.' );

		// ── Origen: las tres huellas ───────────────────────────────────────────────────────────────────────────
		$own = sha1( 'own' );
		$check( $own === Origin::fingerprint( array( 'digitalisimo' => array( 'kit' => $own ), 'site' => 'https://x.test', 'name' => 'n' ) ), 'Huella del kit: la de K.' );
		$check( sha1( 'https://a.test|mi-kit' ) === Origin::fingerprint( array( 'site' => 'https://a.test/', 'name' => 'mi-kit' ) ), 'Huella del kit: sha1( site|name ) sin barra final.' );
		$check( sha1( 'Kit|a.json,b.json' ) === Origin::fingerprint( array( 'manifest_version' => '1.0.2', 'title' => 'Kit', 'templates' => array( array( 'source' => 'templates/b.json' ), array( 'source' => 'a.json' ) ) ) ), 'Huella de un Template Kit de Envato: título y fuentes ordenadas.' );
		$doc_a = array( 'content' => array( array( 'id' => 'x', 'elType' => 'section' ) ), 'settings' => array( 'a' => 1 ), 'metadata' => array(), 'id' => 5, 'import_settings' => array( 'z' ) );
		$doc_b = array( 'settings' => array( 'a' => 1 ), 'metadata' => array(), 'content' => array( array( 'elType' => 'section', 'id' => 'x' ) ) );
		$check( Origin::document_hash( $doc_a ) === Origin::document_hash( $doc_b ) && Origin::document_hash( $doc_a ) !== Origin::document_hash( array( 'settings' => array( 'a' => 2 ) ) + $doc_b ), 'Huella de origen: sin `id` ni `import_settings`, sin depender del orden de claves.' );
		$wxr_post = array( 'post_title' => 'T', 'post_content' => 'C', 'status' => 'publish', 'postmeta' => array( array( 'key' => '_edit_lock', 'value' => '1' ), array( 'key' => 'k', 'value' => 'v' ) ) );
		$check( Origin::wxr_hash( $wxr_post ) === Origin::wxr_hash( array( 'postmeta' => array( array( 'key' => 'k', 'value' => 'v' ) ) ) + $wxr_post ), 'Huella del WXR: sin la lista negra.' );
		$session_class = 'Digitalisimo\Elements\Import\Kits\Session';
		if ( class_exists( $session_class ) ) {
			$check( $session_class::document_hash( $doc_a ) === Origin::document_hash( $doc_a ) && $session_class::wxr_hash( $wxr_post ) === Origin::wxr_hash( $wxr_post ), 'Origin y la sesión del importador usan la misma huella de origen.' );
			$check( $session_class::local_hash( 102 ) === Origin::local_hash( 102 ) && '' !== Origin::local_hash( 102 ), 'Origin y la sesión del importador usan la misma huella local.' );
		}
		$wxr_guard = 'Digitalisimo\Elements\Import\Kits\Wxr_Guard';
		if ( class_exists( $wxr_guard ) ) {
			// Una sola lista negra: el importador del WXR no guarda otra copia (integración de K).
			$check( ! defined( $wxr_guard . '::BLACKLIST' ) && ! defined( $wxr_guard . '::BLACKLIST_PREFIXES' ), 'El importador del WXR no tiene una lista negra propia: usa Export\\Meta_Blacklist.' );
			foreach ( array_merge( Meta_Blacklist::KEYS, array( '_digitalisimo_tools_network_x', 'thumbnail_id', 'keep_me' ) ) as $key ) {
				$check( $wxr_guard::blacklisted( $key ) === Meta_Blacklist::blocks( $key ), "La lista negra del importador es la de la exportación ({$key})." );
			}
		}
		$check( Meta_Blacklist::blocks( '_digitalisimo_tools_network_x' ) && Meta_Blacklist::blocks( '' ) && ! Meta_Blacklist::blocks( 'thumbnail_id' ), 'Lista negra: claves y prefijos.' );
		$check( array( false, null ) === Meta_Blacklist::value( 'a:1:{i:0;O:8:"stdClass":0:{}}' ) && array( true, array( 'a' => 1 ) ) === Meta_Blacklist::value( serialize( array( 'a' => 1 ) ) ) && array( true, 'b:0;x' ) === Meta_Blacklist::value( 'b:0;x' ), 'Un serializado con objetos nunca se reconstruye.' );

		// Copia el paquete a la carpeta preparada de B, como el normalizador.
		$package = array();
		foreach ( $export->files() as $path => $item ) {
			$package[ $path ] = file_get_contents( $item['source'] );
		}

		// ════ Sitio B: importación ══════════════════════════════════════════════════════════════════════════════
		$b_dir = $b_tmp . '/uploads-b';
		$b_site( 'b.test', $b_dir );
		$GLOBALS['kit']['multisite'] = false;
		$staged = $b_dir . '/' . Module::TMP_DIR . '/' . str_repeat( 'c', 64 ) . '/staged';
		foreach ( $package as $path => $bytes ) {
			$b_put( $staged, $path, $bytes );
		}
		$kit_b = kit_stub_post( array( 'ID' => 950, 'post_type' => 'elementor_library' ) );
		update_post_meta( $kit_b, '_elementor_elements_default_values', json_encode( array( 'heading' => array( 'title_color' => '#000' ) ) ) );
		update_post_meta( $kit_b, '_elementor_page_settings', array( 'custom_css' => 'body{background:url(' . $up . '/2026/01/foto.jpg)}' ) );
		$b_plugin( $kit_b );
		$b_put( $b_dir, 'digitalisimo-elements/shapes/ola.svg', '<svg id="ola-de-b"></svg>' );
		kit_stub_post( array( 'ID' => 1101, 'post_type' => 'elementor_library' ) );
		kit_stub_post( array( 'ID' => 1105, 'post_type' => 'elementor_library' ) );
		kit_stub_post( array( 'ID' => 1102, 'post_name' => 'contacto' ) );
		kit_stub_post( array( 'ID' => 600, 'post_type' => 'elementor_library' ) );
		foreach ( array( 1101, 1102, 1105 ) as $id ) {
			update_post_meta( $id, '_elementor_import_session_id', 'sesB' );
		}
		kit_stub_term( array( 'term_id' => 540, 'taxonomy' => 'category', 'slug' => 'noticias' ) );
		update_term_meta( 540, '_elementor_import_session_id', 'sesB' );
		kit_stub_term( array( 'term_id' => 541, 'taxonomy' => 'category', 'slug' => 'vacia' ) );
		add_term_meta( 541, 'color', '#000' );
		$GLOBALS['kit']['filters'][ Module::FILTER_THEME_BUILDER_FIELDS ] = function ( $fields, $id ) {
			$fields['prioridad'] = array(
				'import' => function ( $template, $value, $context ) {
					update_post_meta( $template, '_prioridad', $value );
				},
			);
			return $fields;
		};
		$kit_fp = sha1( 'https://a.test/wp|kit' );
		$new_context = function ( string $mode, string $session = 'sesB' ) use ( $kit_fp, $up, $staged ) {
			return new Import_Context(
				array(
					'session'     => $session,
					'mode'        => $mode,
					'kit'         => $kit_fp,
					'origin'      => array( 'home' => 'https://a.test', 'site' => 'https://a.test/wp', 'uploads' => $up ),
					'max_post_id' => 1000,
					'kit_id'      => 950,
					'staged_dir'  => $staged,
					'maps'        => array(
						'post'         => array( 101 => 1101, 102 => 1102, 105 => 600, 106 => 1105 ),
						'template'     => array( 101 => 1101, 105 => 600, 106 => 1105 ),
						'term'         => array( 40 => 540, 41 => 541 ),
						'global_color' => array( 'primary' => 'kabc123' ),
					),
				)
			);
		};
		$data = array();
		foreach ( $out as $key => $value ) {
			$data[ $key ] = json_decode( json_encode( $value ), true );
		}
		$data['element-defaults']['defaults']['button'] = array( 'color' => 'x', '__globals__' => array( 'button_background_color' => 'globals/colors?id=primary' ) );
		$data['theme-builder']['106'] = array( 'conditions' => array( array( 'type' => 'include', 'name' => 'singular', 'sub_name' => 'page', 'sub_id' => '102' ) ), 'fields' => array() );
		$data['theme-builder']['105'] = array( 'conditions' => array( array( 'type' => 'include', 'name' => 'general' ) ), 'fields' => array() );

		$add     = $new_context( 'add' );
		$summary = array();
		foreach ( Parts::all() as $key => $part ) {
			$summary[ $key ] = $b_part( $key, $add, function () use ( $part, $data, $key, $add ) {
				return $part->import( isset( $data[ $key ] ) ? $data[ $key ] : array(), 1, $add );
			} );
		}
		$stored = $add->stored_all();

		// B1 en «agregar»: se añaden los tipos que faltan y se conservan los del sitio; globales remapeados.
		$defaults_b = json_decode( get_post_meta( 950, '_elementor_elements_default_values', true ), true );
		$check( array( 'title_color' => '#000' ) === $defaults_b['heading'], 'B1 «agregar»: el tipo que el sitio ya tiene no cambia…' );
		$check( 1 === count( $b_codes( $add, 'element-defaults.kept' ) ), '…y se informa.' );
		$check( array( 'color' => 'x', '__globals__' => array( 'button_background_color' => 'globals/colors?id=kabc123' ) ) === $defaults_b['button'], 'B1: `__globals__` con el mapa de globales (CC-7).' );
		$check( array( 'old-widget' => array( 'b' => 2 ), 'acme-widget' => array( 'a' => 1 ) ) === get_post_meta( 950, Module::KIT_META_UNCONVERTED_DEFAULTS, true ), 'B1: los desconocidos van a `_digitalisimo_unconverted_defaults`.' );
		$check( 1 === count( $b_codes( $add, 'element-defaults.unconverted' ) ), 'B1: los desconocidos se informan.' );

		// B4: archivos con choque, destino construido por K y mapas.
		$sha_ola = hash( 'sha256', '<svg id="ola"><path d="M0"/></svg>' . "\n" . '<svg id="ola-n"><path d="M1"/></svg>' );
		$renamed = 'ola-k' . substr( hash( 'sha256', '<svg id="ola-n"><path d="M1"/></svg>' . "\n" . '<svg id="ola"><path d="M0"/></svg>' ), 0, 6 );
		$files_map = $add->map( 'files' );
		$check( 0 === strpos( $files_map['shapes/ola.svg'], 'shapes/ola-k' ) && $files_map['shapes/ola-negative.svg'] === str_replace( '.svg', '-negative.svg', $files_map['shapes/ola.svg'] ), 'B4: un choque da `<slug>-k<6 hex>`, también para su negativo: ' . json_encode( $files_map ) );
		$new_slug = substr( $files_map['shapes/ola.svg'], 7, -4 );
		$check( 1 === preg_match( '/^ola-k[0-9a-f]{6}$/', $new_slug ) && array( 'digi-ola' => 'digi-' . $new_slug ) === $add->map( 'shapes' ), 'B4: mapa `shapes` para el remapeo.' );
		$check( '<svg id="ola-de-b"></svg>' === file_get_contents( $b_dir . '/digitalisimo-elements/shapes/ola.svg' ) && is_file( $b_dir . '/digitalisimo-elements/shapes/' . $new_slug . '-negative.svg' ), 'B4: el archivo del sitio no se pisa.' );
		$check( 'masks/mask-3.svg' === $files_map['masks/mask-3.svg'] && is_file( $b_dir . '/digitalisimo-elements/masks/mask-3.svg' ), 'B5: la máscara se escribe con su nombre.' );
		$check( 3 === count( $stored['files']['written'] ) && 3 === $summary['files']['written'] && 1 === $summary['files']['renamed'], 'Lo escrito queda para la reversión.' );
		unset( $sha_ola, $renamed );

		// D6: medios desde el paquete, sin HTTP.
		$attachments = $add->map( 'attachment' );
		$new_foto    = $attachments[ $up . '/2026/01/foto.jpg' ];
		$check( $new_foto > 1000 && $new_foto === $attachments[201] && 'JPG-FOTO' === file_get_contents( get_attached_file( $new_foto ) ), 'Medios: adjunto creado desde el archivo preparado; mapa por URL y por ID de origen.' );
		$check( sha1( $up . '/2026/01/foto.jpg' ) === get_post_meta( $new_foto, '_elementor_source_image_hash', true ) && 'sesB' === get_post_meta( $new_foto, '_elementor_import_session_id', true ), 'Medios: con la huella del núcleo (dedupe) y la sesión (la reversión nativa lo borra).' );
		$check( 'attachment' === Origin::read( $new_foto )['type'] && $kit_fp === Origin::read( $new_foto )['kit'], 'Medios: con su procedencia (D7).' );
		$check( wp_get_attachment_url( $new_foto ) === $add->map( 'media_url' )[ $up . '/2026/01/foto.jpg' ] && 5 === $summary['media']['imported'], 'Medios: mapa `media_url` y resumen.' );

		// B19 en «nuevo»: el CSS del kit con los medios importados (aquí, «agregar»: no se toca).
		$check( 'body{background:url(' . $up . '/2026/01/foto.jpg)}' === get_post_meta( 950, '_elementor_page_settings', true )['custom_css'], 'kit-settings «agregar»: el CSS del kit no se toca.' );

		// B3: Theme Builder cerrado.
		$manager = \ElementorPro\Modules\ThemeBuilder\Module::$manager;
		$check( array() === $manager->saved[1101] && '' === get_post_meta( 1101, '_elementor_conditions', true ), 'B3: con una condición sin destino (por autor), la plantilla queda sin condiciones.' );
		$check( in_array( 1101, $add->review_ids(), true ) && 1 === count( $b_codes( $add, 'theme-builder.closed' ) ), 'B3: queda para «revisar antes de publicar» y el informe conserva la lista original.' );
		$closed = $b_codes( $add, 'theme-builder.closed' )[0]['data'];
		$check( array( 'include/general', 'include/singular/page/102', 'exclude/singular/by_author/1' ) === $closed['conditions'] && array( 'exclude/singular/by_author/1' ) === $closed['unresolved'], 'B3: lista original y lo que no resolvió.' );
		$check( array( 'include/singular/page/1102' ) === $manager->saved[1105], 'B3: si todas resuelven, se guardan traducidas.' );
		$check( ! isset( $manager->saved[600] ), 'B3: una plantilla reutilizada (D7) no se toca.' );
		$check( 7 === get_post_meta( 1101, '_prioridad', true ), 'B3: los campos propios se aplican con su callback.' );

		// B18: metas de término.
		$check( array( 'color' => array( '#f00' ), 'thumbnail_id' => array( (string) $attachments[207] ) ) === array_diff_key( $GLOBALS['wp']['termmeta'][540], array( '_elementor_import_session_id' => 1 ) ), 'term-meta: metas en el término que creó la sesión, con `thumbnail_id` → adjunto nuevo.' );
		$check( array( 'color' => array( '#000' ) ) === $GLOBALS['wp']['termmeta'][541] && 1 === $summary['term-meta']['kept'], 'term-meta: un término que ya era del sitio (reutilizado por su slug) no se toca.' );

		// Procedencia: reutilizar, actualización y conflicto, en cualquier estado.
		$origin_page = kit_stub_post( array( 'ID' => 1200, 'post_status' => 'draft', 'post_title' => 'Importada' ) );
		Origin::write( $origin_page, Origin::record( $kit_fp, 'page', 77, 'hash-1', 'sesB' ) );
		$check( array( 'status' => 'reuse', 'id' => 1200, 'previous' => 1200 ) === Origin::lookup( $kit_fp, 'page', 77, 'hash-1' ), 'origin: mismo origen y copia sin editar → reutilizar (también en borrador).' );
		$check( 'update' === Origin::lookup( $kit_fp, 'page', '77', 'hash-2' )['status'], 'origin: cambió el origen → actualización.' );
		$GLOBALS['wp']['posts'][1200]->post_status = 'private';
		$check( 1200 === Origin::existing( $kit_fp, 'page', 77, 'hash-1' ), 'origin: privado también se reutiliza.' );
		$GLOBALS['wp']['posts'][1200]->post_title = 'Editada a mano';
		Origin::reset();
		$check( 'conflict' === Origin::lookup( $kit_fp, 'page', 77, 'hash-1' )['status'] && null === Origin::existing( $kit_fp, 'page', 77, 'hash-1' ), 'origin: se editó la copia local → conflicto.' );
		$GLOBALS['wp']['posts'][1200]->post_status = 'trash';
		Origin::reset();
		$check( 'new' === Origin::lookup( $kit_fp, 'page', 77, 'hash-1' )['status'], 'origin: lo que está en la papelera no cuenta.' );
		$check( 'new' === Origin::lookup( sha1( 'otro kit' ), 'page', 77, 'hash-1' )['status'] && 'new' === Origin::lookup( 'mal', 'page', 77, 'x' )['status'], 'origin: otro kit u otra huella inválida → nuevo.' );
		$GLOBALS['wp']['posts'][1200]->post_status = 'publish';
		$GLOBALS['wp']['posts'][1200]->post_title  = 'Importada';
		Origin::reset();
		Origin::use_kit( $kit_fp );
		$doc_page = kit_stub_post( array( 'ID' => 1201, 'post_type' => 'elementor_library', 'post_title' => 'Plantilla' ) );
		Origin::write( $doc_page, Origin::record( $kit_fp, 'elementor_library', 88, Origin::document_hash( $doc_a ), 'sesB' ) );
		$check( 1201 === Origin::existing_document( null, 'elementor_library', 88, $doc_a ) && null === Origin::existing_document( null, 'elementor_library', 88, array( 'settings' => array( 'a' => 9 ) ) + $doc_a ), 'origin: el filtro de documentos existentes reutiliza el mismo documento y no uno que cambió.' );
		Origin::use_kit( 'no es una huella' );
		$check( null === Origin::existing_document( null, 'elementor_library', 88, $doc_a ), 'origin: sin importación en curso, el filtro no hace nada.' );
		Origin::use_kit( $kit_fp );
		$check( 9 === Origin::existing_document( 9, 'page', 77, array() ), 'origin: el filtro respeta un ID que ya traía.' );
		$term_origin = Origin::record( $kit_fp, 'category', 40, 'h', 'sesB' );
		Origin::write( 540, $term_origin, 'term' );
		$check( 540 === Origin::existing( $kit_fp, 'term:category', 40, 'h' ), 'origin: también para términos.' );
		Origin::reset();

		// Segunda importación del mismo kit: no crea adjuntos ni archivos nuevos (A8).
		$posts_before = count( $GLOBALS['wp']['posts'] );
		$files_before = count( glob( $b_dir . '/digitalisimo-elements/*/*.svg' ) );
		$again        = $new_context( 'add', 'sesC' );
		$b_part( 'files', $again, function () use ( $data, $again ) {
			return ( new Files() )->import( $data['files'], 1, $again );
		} );
		$media_again = $b_part( 'media', $again, function () use ( $data, $again ) {
			return ( new Media() )->import( $data['media'], 1, $again );
		} );
		$check( $posts_before === count( $GLOBALS['wp']['posts'] ) && 5 === $media_again['reused'] && $files_before === count( glob( $b_dir . '/digitalisimo-elements/*/*.svg' ) ), 'Reimportar: los medios y archivos se reutilizan, sin filas ni archivos nuevos.' );
		$check( $files_map === $again->map( 'files' ), 'Reimportar: el mismo slug derivado.' );

		// Un adjunto que ya trajo el WXR está en el mapa `attachment` del runner (sin la huella del núcleo): se reutiliza.
		$from_wxr = kit_stub_post( array( 'ID' => 1300, 'post_type' => 'attachment', 'post_status' => 'inherit' ) );
		update_post_meta( $from_wxr, '_wp_attached_file', '2026/10/adj-del-wxr.png' );
		$wxr_ctx = new Import_Context( array( 'session' => 'sesW', 'staged_dir' => $staged, 'origin' => array( 'uploads' => $up ), 'maps' => array( 'attachment' => array( $up . '/2026/01/adj.png' => $from_wxr ) ) ) );
		$posts_before = count( $GLOBALS['wp']['posts'] );
		$wxr_media    = $b_part( 'media', $wxr_ctx, function () use ( $data, $wxr_ctx, $up ) {
			return ( new Media() )->import( array( $up . '/2026/01/adj.png' => $data['media'][ $up . '/2026/01/adj.png' ] ), 1, $wxr_ctx );
		} );
		$check( 1 === $wxr_media['reused'] && $posts_before === count( $GLOBALS['wp']['posts'] ) && 'https://b.test/wp-content/uploads/2026/10/adj-del-wxr.png' === $wxr_ctx->map( 'media_url' )[ $up . '/2026/01/adj.png' ], 'media: lo que ya importó el WXR se reutiliza, sin duplicar el adjunto.' );

		// Entradas hostiles en las partes.
		$hostile = $new_context( 'add', 'sesD' );
		$bad_files = $b_part( 'files', $hostile, function () use ( $hostile ) {
			return ( new Files() )->import(
				array(
					'../x.svg'          => array( 'sha256' => str_repeat( '0', 64 ), 'bytes' => 1 ),
					'shapes/Ola.svg'    => array(),
					'masks/mask-3.svg'  => array( 'sha256' => 'no-es-un-sha' ),
					'masks/no-esta.svg' => array( 'sha256' => str_repeat( '0', 64 ) ),
				),
				1,
				$hostile
			);
		} );
		$check( 4 === $bad_files['failed'] && 0 === $bad_files['written'] && 1 === count( $b_codes( $hostile, 'files.hash' ) ) && 1 === count( $b_codes( $hostile, 'files.missing' ) ), 'files: claves inválidas, huellas inválidas y archivos que no están en la carpeta preparada se rechazan.' );
		// El normalizador guarda el SVG ya saneado: su sha256 deja de coincidir con el del kit y se acepta saneado.
		file_put_contents( $staged . '/files/masks/limpia.svg', '<svg id="limpia"></svg>' );
		$sanitized = $b_part( 'files', $hostile, function () use ( $hostile ) {
			return ( new Files() )->import( array( 'masks/limpia.svg' => array( 'sha256' => hash( 'sha256', '<svg id="limpia"><script>x</script></svg>' ) ) ), 1, $hostile );
		} );
		$check( 1 === $sanitized['written'] && '<svg id="limpia"></svg>' === file_get_contents( $b_dir . '/digitalisimo-elements/masks/limpia.svg' ) && 1 === count( $b_codes( $hostile, 'files.staged-sanitized' ) ), 'files: un SVG que el normalizador dejó saneado se acepta (y se vuelve a sanear).' );
		$sha     = hash( 'sha256', 'otra cosa' );
		$bad_med = $b_part( 'media', $hostile, function () use ( $hostile, $up, $sha ) {
			return ( new Media() )->import(
				array(
					$up . '/x.png'    => array( 'file' => '../../x.png' ),
					$up . '/y.png'    => array( 'file' => $sha . '.png' ),
					$up . '/logo.svg' => array( 'file' => hash( 'sha256', '<svg/>' ) . '.svg' ),
				),
				1,
				$hostile
			);
		} );
		$check( 3 === $bad_med['failed'] && 1 === count( $b_codes( $hostile, 'media.invalid' ) ) && 2 === count( $b_codes( $hostile, 'media.missing' ) ), 'media: un archivo con otra forma o que no viajó se rechaza.' );
		$fake = hash( 'sha256', 'PNG-ORIGINAL' );
		file_put_contents( $staged . '/media/' . $fake . '.png', 'PNG-CAMBIADO' );
		$tampered = $b_part( 'media', $hostile, function () use ( $hostile, $up, $fake ) {
			return ( new Media() )->import( array( $up . '/cambiada.png' => array( 'file' => $fake . '.png' ) ), 1, $hostile );
		} );
		$check( 1 === $tampered['failed'] && 1 === count( $b_codes( $hostile, 'media.hash' ) ), 'media: un archivo que no es el del kit (sha256) se rechaza.' );
		$svg_dir = $staged . '/media';
		file_put_contents( $svg_dir . '/' . hash( 'sha256', '<svg/>' ) . '.svg', '<svg/>' );
		$GLOBALS['wp']['svg'] = true;
		$svg = $b_part( 'media', $hostile, function () use ( $hostile, $up ) {
			return ( new Media() )->import( array( $up . '/logo.svg' => array( 'file' => hash( 'sha256', '<svg/>' ) . '.svg' ) ), 1, $hostile );
		} );
		$check( 1 === $svg['skipped'] && 1 === count( $b_codes( $hostile, 'media.svg-skipped' ) ), 'D15: con la subida sin filtrar apagada, un SVG de la biblioteca se omite y se informa.' );
		\Elementor\Core\Files\Uploads_Manager::$unfiltered = true;
		file_put_contents( $svg_dir . '/' . hash( 'sha256', '<svg onload="x()"><script>alert(1)</script></svg>' ) . '.svg', '<svg onload="x()"><script>alert(1)</script></svg>' );
		$svg_ok = $b_part( 'media', $hostile, function () use ( $hostile, $up ) {
			return ( new Media() )->import( array( $up . '/malo.svg' => array( 'file' => hash( 'sha256', '<svg onload="x()"><script>alert(1)</script></svg>' ) . '.svg' ) ), 1, $hostile );
		} );
		$svg_id = $hostile->map( 'attachment' )[ $up . '/malo.svg' ] ?? 0;
		$check( 1 === $svg_ok['imported'] && $svg_id > 0 && '<svg></svg>' === file_get_contents( get_attached_file( $svg_id ) ), 'Un SVG permitido se sanea antes de guardarse.' );
		\Elementor\Core\Files\Uploads_Manager::$unfiltered = false;
		unset( $GLOBALS['wp']['svg'] );

		// «Nuevo»: valores por defecto reemplazados y CSS del kit con los medios importados.
		$fresh = $new_context( 'fresh', 'sesF' );
		$fresh->add_map( 'media_url', $add->map( 'media_url' ) );
		$b_part( 'element-defaults', $fresh, function () use ( $data, $fresh ) {
			return ( new Element_Defaults() )->import( $data['element-defaults'], 1, $fresh );
		} );
		$fresh_defaults = json_decode( get_post_meta( 950, '_elementor_elements_default_values', true ), true );
		$check( array( 'heading', 'button' ) === array_keys( $fresh_defaults ) && array( 'title_color' => '#fff', '__globals__' => array( 'typography_typography' => 'globals/typography?id=secondary' ) ) === $fresh_defaults['heading'], 'B1 «nuevo»: reemplaza (y el saneador nativo quita lo de la exportación).' );
		$css = $b_part( 'kit-settings', $fresh, function () use ( $data, $fresh ) {
			return ( new Kit_Settings() )->import( $data['kit-settings'], 1, $fresh );
		} );
		$check( 1 === $css['css'] && 'body{background:url(' . wp_get_attachment_url( $new_foto ) . ')}' === get_post_meta( 950, '_elementor_page_settings', true )['custom_css'], 'kit-settings «nuevo»: el CSS del kit apunta a los medios importados (§9.3).' );
		$check( array( 'colors' => 1, 'colors_mapped' => 1, 'typography' => 1, 'typography_mapped' => 0 ) === array_diff_key( $css, array( 'css' => 1 ) ), 'kit-settings: resumen de globales usados y con destino.' );
		$check( array() === ( new Element_Defaults() )->import( array(), 1, $fresh ) && $fresh_defaults === json_decode( get_post_meta( 950, '_elementor_elements_default_values', true ), true ), 'B1: sin valores por defecto en el kit, los del sitio no cambian, tampoco en «nuevo».' );

		// ── Reversión ──────────────────────────────────────────────────────────────────────────────────────────
		$revert = new Revert_Context( array( 'session' => 'sesF', 'mode' => 'fresh', 'kit_id' => 950 ) );
		$fresh_stored = $fresh->stored_all();
		( new Kit_Settings() )->revert( $fresh_stored['kit-settings'], $revert );
		$check( 'body{background:url(' . $up . '/2026/01/foto.jpg)}' === get_post_meta( 950, '_elementor_page_settings', true )['custom_css'], 'kit-settings: revertir restaura el CSS si nadie lo cambió.' );
		( new Element_Defaults() )->revert( $fresh_stored['element-defaults'], $revert );
		$check( array( 'title_color' => '#000' ) === json_decode( get_post_meta( 950, '_elementor_elements_default_values', true ), true )['heading'], 'B1: revertir restaura los valores de antes si nadie los cambió.' );
		$revert_add = new Revert_Context( array( 'session' => 'sesB', 'kit_id' => 950 ) );
		$edited     = json_decode( get_post_meta( 950, '_elementor_elements_default_values', true ), true );
		$edited['heading']['title_color'] = '#123';
		update_post_meta( 950, '_elementor_elements_default_values', json_encode( $edited ) );
		( new Element_Defaults() )->revert( $stored['element-defaults'], $revert_add );
		$after_revert = json_decode( get_post_meta( 950, '_elementor_elements_default_values', true ), true );
		$check( array( 'title_color' => '#123' ) === $after_revert['heading'] && ! isset( $after_revert['button'] ), 'B1: con el kit editado, revertir sólo quita lo añadido.' );
		( new Term_Meta() )->revert( $stored['term-meta'], $revert_add );
		$check( ! isset( $GLOBALS['wp']['termmeta'][540]['color'] ) && ! isset( $GLOBALS['wp']['termmeta'][540]['thumbnail_id'] ) && isset( $GLOBALS['wp']['termmeta'][540]['_elementor_import_session_id'] ), 'term-meta: revertir quita lo añadido y nada más.' );
		$written = array_keys( $stored['files']['written'] );
		file_put_contents( $b_dir . '/digitalisimo-elements/masks/mask-3.svg', '<svg id="editada"></svg>' );
		( new Files() )->revert( $stored['files'], $revert_add );
		$check( ! is_file( $b_dir . '/digitalisimo-elements/' . $written[1] ) && is_file( $b_dir . '/digitalisimo-elements/masks/mask-3.svg' ) && is_file( $b_dir . '/digitalisimo-elements/shapes/ola.svg' ), 'files: revertir borra lo escrito sin cambios; lo editado y lo del sitio se quedan.' );
		$check( 1 === count( $b_codes( $revert_add, 'files.edited' ) ), 'files: lo editado se informa.' );
		( new Files() )->revert( array( 'written' => array( 'shapes/../../../x.svg' => 'x', 'masks/zz.svg' => 'x' ) ), $revert_add );
		update_post_meta( 1105, '_elementor_conditions', array( 'include/singular/page/1102' ) );
		( new Theme_Builder() )->revert( $stored['theme-builder'], $revert_add );
		$check( array() === $manager->saved[1105] && array() === $manager->saved[1101], 'B3: revertir restaura las condiciones de antes si nadie las cambió.' );
	} finally {
		unset( $GLOBALS['kit']['filters'][ Module::FILTER_THEME_BUILDER_FIELDS ] );
		\Elementor\Plugin::$instance = null;
		$GLOBALS['kit']['multisite']  = false;
		Parts::reset();
		$b_rmrf( $b_tmp );
	}
}
