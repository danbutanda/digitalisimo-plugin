<?php
/**
 * CSS del sitio que nombra clases de los widgets de PRO en DIGITALÍSIMO Migración (corrección de fidelidad de C5 y C6,
 * docs/pro/c5.md y docs/pro/c6.md): la etapa `pro` usa `Convert\Site_Css` de Elements.
 *
 * Cubre, con los mapas reales de Entradas y del Formulario: en un documento convertido, el CSS personalizado de otro
 * elemento (un contenedor) y el de la página ganan la traducción de sus reglas (la original no cambia); al terminar la
 * etapa, el del kit activo y el CSS adicional del Personalizador; el informe (`pro.site_css`); una segunda pasada no
 * duplica nada; `--revert` devuelve el documento (con el CSS de sus elementos) y quita sólo las reglas añadidas del CSS
 * de la página, del kit y del Personalizador, sin pisar lo que se editó después. Con el código anterior esas reglas
 * dejaban de aplicarse tras «Migrar» y nada lo decía.
 *
 * Se ejecuta con php-wasm: sale con 0 aunque haya un error fatal, así que se juzga por la línea final.
 */

namespace Elementor {
	if ( ! class_exists( Plugin::class, false ) ) {
		/** Sin el constructor: `$instance` nulo (destinos «desconocidos», que cuentan como registrados). */
		class Plugin {
			public static $instance;
		}
	}
}

namespace Digitalisimo\Elements {
	/** Los widgets propios están cargados (la conversión de Migrar lo exige). */
	final class Widget_Registry {}
}

namespace {
	require __DIR__ . '/fixtures/f8-wp.php';

	/**
	 * $wpdb en memoria con las dos consultas propias del inventario: la de filas por lotes (con el tipo de entrada y el
	 * de plantilla) y la de la huella. Lo demás, como el de A.
	 */
	class Pro_Wpdb extends F8_Wpdb {
		public $inventory_rows = array();

		private function parts( $query ) {
			$pos = strpos( (string) $query, "\0" );
			return false === $pos ? array( (string) $query, array() ) : array( substr( $query, 0, $pos ), (array) unserialize( substr( $query, $pos + 1 ) ) );
		}

		/** Documentos con `_elementor_data` del sitio en curso, por id. */
		private function documents() {
			$blog = (int) $GLOBALS['wp']['blog'];
			$out  = array();
			foreach ( $GLOBALS['wp']['meta'][ $blog ] ?? array() as $id => $meta ) {
				if ( array_key_exists( '_elementor_data', $meta ) && isset( $GLOBALS['wp']['posts'][ $blog ][ $id ] ) ) {
					$out[ (int) $id ] = $meta;
				}
			}
			ksort( $out );
			return $out;
		}

		public function get_results( $query, $output = 'OBJECT' ) {
			list( $sql, $args ) = $this->parts( $query );
			if ( false === strpos( $sql, 'AS template_type' ) ) {
				return parent::get_results( $query, $output );
			}
			$this->queries[] = $sql;
			preg_match( '/LIMIT\s+(\d+)/', $sql, $m );
			$limit = isset( $m[1] ) ? (int) $m[1] : PHP_INT_MAX;
			$blog  = (int) $GLOBALS['wp']['blog'];
			$rows  = array();
			foreach ( $this->documents() as $id => $meta ) {
				if ( $id <= (int) $args[0] ) {
					continue;
				}
				if ( count( $rows ) >= $limit ) {
					break;
				}
				$rows[] = (object) array( 'post_id' => $id, 'post_type' => $GLOBALS['wp']['posts'][ $blog ][ $id ]['post_type'], 'template_type' => $meta['_elementor_template_type'] ?? null, 'meta_value' => (string) $meta['_elementor_data'] );
			}
			$this->inventory_rows[] = count( $rows );
			return $rows;
		}

		public function get_row( $query, $output = 'OBJECT', $y = 0 ) {
			list( $sql ) = $this->parts( $query );
			if ( false === strpos( $sql, 'AS last_id' ) ) {
				return parent::get_row( $query, $output, $y );
			}
			$this->queries[] = $sql;
			$docs     = $this->documents();
			$modified = '';
			foreach ( array_keys( $docs ) as $id ) {
				$modified = max( $modified, (string) $GLOBALS['wp']['posts'][ (int) $GLOBALS['wp']['blog'] ][ $id ]['post_modified_gmt'] );
			}
			// Sin meta_id en el doble: una huella del contenido hace su papel (cambia con cada escritura).
			return (object) array( 'n' => count( $docs ), 'last_id' => md5( serialize( array_column( $docs, '_elementor_data' ) ) ), 'modified' => $modified );
		}
	}

	f8_wpdb( 'Pro_Wpdb' );
	f8_load_migration( array( 'translator', 'context', 'report', 'native', 'migration', 'retire', 'aliases', 'runner', 'pro' ) );

	use Digitalisimo\Elements\Legacy\Context;
	use Digitalisimo\Elements\Legacy\Migration;
	use Digitalisimo\Elements\Legacy\Pro;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Runner;
	use Digitalisimo\Elements\Legacy\Aliases;

	$check = 'f8_check';

	/* -------------------------------------------------------------- Ayudas */

	function w( string $type, array $settings = array(), string $id = 'w1' ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}
	function box( string $type, array $children, string $id ): array {
		return array( 'id' => $id, 'elType' => $type, 'settings' => array(), 'elements' => $children, 'isInner' => false );
	}
	/** Un documento con esos elementos tal cual (raíz), del tipo indicado. */
	function doc( array $elements, array $post = array(), array $meta = array() ): int {
		return f8_post( $post + array( 'post_type' => 'page', 'post_title' => 'Página' ), array( '_elementor_data' => wp_json_encode( $elements ) ) + $meta );
	}
	function tree( int $id ): array {
		$out = json_decode( (string) get_metadata( 'post', $id, '_elementor_data', true ), true );
		return is_array( $out ) ? $out : array();
	}
	function find( array $elements, string $id ): ?array {
		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}
			if ( (string) ( $el['id'] ?? '' ) === $id ) {
				return $el;
			}
			$hit = find( (array) ( $el['elements'] ?? array() ), $id );
			if ( $hit ) {
				return $hit;
			}
		}
		return null;
	}
	/** Códigos guardados por el informe para un documento: `código:widget:clave`. */
	function doc_codes( int $id, ?array $report = null ): array {
		if ( null === $report ) {
			Report::flush();
			$report = Report::stored();
		}
		$out = array();
		foreach ( (array) ( $report['docs'][ $id ]['items'] ?? array() ) as $item ) {
			$out[] = $item['code'] . ':' . (string) ( $item['detail']['widget'] ?? '' ) . ':' . (string) ( $item['detail']['name'] ?? '' );
		}
		sort( $out );
		return $out;
	}
	class Test_Manager {
		public $known;
		public function __construct( array $known ) {
			$this->known = $known;
		}
		public function get_widget_types( $name = null ) {
			return null === $name ? array() : ( in_array( $name, $this->known, true ) ? new stdClass() : null );
		}
	}
	function manager( ?array $known ): void {
		\Elementor\Plugin::$instance = null === $known ? null : (object) array( 'widgets_manager' => new Test_Manager( $known ) );
		Pro::reset();
	}
	function fresh(): void {
		f8_reset();
		f8_wpdb( 'Pro_Wpdb' );
		Runner::init();
		Aliases::set_map( array() );
		Report::reset_catalog();
		Report::clear();
		Report::begin( 'run' );
		Context::reset();
		manager( null );
	}
	/** La etapa `pro` del runner, como en una petición, hasta que pasa a la siguiente. */
	function run_pro_stage( array $state = array() ): array {
		$state          = $state + Runner::fresh( true, 0, 1 );
		$state['stage'] = 'pro';
		$stage          = new ReflectionMethod( Runner::class, 'stage_pro' );
		if ( PHP_VERSION_ID < 80100 ) {
			$stage->setAccessible( true );
		}
		for ( $i = 0; $i < 50 && 'pro' === $state['stage']; $i++ ) {
			$state = $stage->invoke( null, $state, microtime( true ) + 30 );
		}
		return $state;
	}
	/** Migrar hasta terminar (cada paso en una petición nueva). */
	function migrate( $limit = 30 ): array {
		$state = array();
		for ( $i = 0; $i < $limit; $i++ ) {
			f8_new_request();
			$state = Runner::site_step( 0 === $i, 100, true );
			if ( ! empty( $state['done'] ) || ! empty( $state['idle'] ) ) {
				break;
			}
		}
		return $state;
	}


	require_once dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();

	/** El CSS adicional del Personalizador del tema activo (un solo tema en la prueba). */
	function wp_get_custom_css_post( $stylesheet = '' ) {
		return isset( $GLOBALS['customizer_css'] ) ? (object) array( 'ID' => 900, 'post_content' => $GLOBALS['customizer_css'], 'post_content_filtered' => $GLOBALS['customizer_src'] ?? '', 'post_name' => 'tema' ) : null;
	}
	// Como WordPress: sin `preprocessed`, la fuente preprocesada (Sass/LESS de un plugin) se vacía.
	function wp_update_custom_css_post( $css, $args = array() ) {
		$GLOBALS['customizer_css'] = (string) $css;
		$GLOBALS['customizer_src'] = (string) ( $args['preprocessed'] ?? '' );
		return wp_get_custom_css_post();
	}

	$check( Pro::site_css_available(), 'Con el núcleo de Elements que traduce el CSS del sitio, Migración lo usa.' );
	fresh();
	$page_css = '.elementor-widget-form .elementor-field-group input{border:0}';
	$box_css  = 'selector .elementor-widget-posts .elementor-post__title{color:red}';
	$kit_css  = '.elementor-post__title a{color:blue}';
	$css_add  = "@media (max-width:767px){.elementor-widget-posts .elementor-pagination{gap:0}}";
	$posts    = w( 'posts', array( 'posts_per_page' => 3 ), 'p1' );
	$page     = doc( array( array( 'id' => 'k1', 'elType' => 'container', 'settings' => array( 'custom_css' => $box_css ), 'elements' => array( $posts ), 'isInner' => false ) ), array( 'post_title' => 'Con Entradas' ), array( '_elementor_page_settings' => array( 'custom_css' => $page_css, 'hide_title' => 'yes' ) ) );
	$kit      = f8_post( array( 'post_type' => 'elementor_library', 'post_title' => 'Kit' ), array( '_elementor_template_type' => 'kit', '_elementor_page_settings' => array( 'custom_css' => $kit_css, 'body_background_color' => '#fff' ) ) );
	update_option( 'elementor_active_kit', $kit );
	$GLOBALS['customizer_css'] = $css_add;
	$GLOBALS['customizer_src'] = '$gap: 0; ' . $css_add;
	$original = (string) get_metadata( 'post', $page, '_elementor_data', true );

	$st   = run_pro_stage();
	$data = tree( $page );
	$check( 'retire_late' === $st['stage'] && 'digitalisimo-posts' === find( $data, 'p1' )['widgetType'], 'La etapa pro convierte las Entradas: ' . wp_json_encode( $st['counts'] ) );
	$box = (string) find( $data, 'k1' )['settings']['custom_css'];
	$check( str_starts_with( $box, $box_css . '/*digitalisimo-pro*/selector .elementor-widget-digitalisimo-posts .digi-posts__title{color:red}' ), 'El CSS del contenedor gana la regla traducida detrás de la original: ' . $box );
	$ps = get_metadata( 'post', $page, '_elementor_page_settings', true );
	$check( str_starts_with( (string) $ps['custom_css'], $page_css . '/*digitalisimo-pro*/.elementor-widget-digitalisimo-form .elementor-field-group input{border:0}' ) && 'yes' === $ps['hide_title'], 'El CSS de la página, igual (y el resto de sus ajustes intactos): ' . wp_json_encode( $ps ) );
	$ks = get_metadata( 'post', $kit, '_elementor_page_settings', true );
	$check( str_contains( (string) $ks['custom_css'], '/*digitalisimo-pro*/.digi-posts__title a{color:blue}' ) && str_starts_with( (string) $ks['custom_css'], $kit_css ) && '#fff' === $ks['body_background_color'], 'Al terminar la etapa, el CSS del kit activo: ' . wp_json_encode( $ks ) );
	$check( str_contains( $GLOBALS['customizer_css'], '@media (max-width:767px){.elementor-widget-posts .elementor-pagination{gap:0}/*digitalisimo-pro*/.elementor-widget-digitalisimo-posts .digi-loop__pagination{gap:0}}' ), 'Y el CSS adicional del Personalizador: ' . $GLOBALS['customizer_css'] );
	$check( '$gap: 0; ' . $css_add === $GLOBALS['customizer_src'], 'La fuente preprocesada del CSS adicional (Sass/LESS de un plugin) no se vacía: ' . $GLOBALS['customizer_src'] );
	$codes = doc_codes( $page );
	$check( in_array( 'pro.site_css::custom_css', $codes, true ) && in_array( 'pro.site_css::_elementor_page_settings.custom_css', $codes, true ) && in_array( 'pro.site_css::kit.custom_css', doc_codes( $kit ), true ) && in_array( 'pro.site_css::customizer.custom_css', doc_codes( 900 ), true ), 'Informe: pro.site_css por cada lugar: ' . wp_json_encode( $codes ) );
	$catalog = require dirname( __DIR__ ) . '/digitalisimo-migration/legacy/report/codes-pro.php';
	$check( 'converted' === ( $catalog['pro.site_css']['section'] ?? '' ), 'Catálogo: pro.site_css en «convertido».' );

	// Una segunda pasada (otra vez Migrar) no duplica nada.
	$kit_after  = (string) get_metadata( 'post', $kit, '_elementor_page_settings', true )['custom_css'];
	$cust_after = $GLOBALS['customizer_css'];
	run_pro_stage();
	$check( $kit_after === get_metadata( 'post', $kit, '_elementor_page_settings', true )['custom_css'] && $cust_after === $GLOBALS['customizer_css'] && 1 === substr_count( $cust_after, 'digitalisimo-pro' ), 'Otra pasada no duplica las reglas del kit ni del Personalizador.' );

	// Lo editado después de Migrar se conserva al revertir.
	$ks                = get_metadata( 'post', $kit, '_elementor_page_settings', true );
	$ks['custom_css'] .= "\n.mia{margin:0}";
	update_metadata( 'post', $kit, '_elementor_page_settings', $ks );
	$rev = Runner::revert();
	$check( empty( $rev['refused'] ) && $original === get_metadata( 'post', $page, '_elementor_data', true ), '--revert devuelve el documento con el CSS original de sus elementos: ' . wp_json_encode( $rev ) );
	$check( $page_css === get_metadata( 'post', $page, '_elementor_page_settings', true )['custom_css'], '--revert quita la regla añadida al CSS de la página.' );
	$check( $kit_css . "\n.mia{margin:0}" === get_metadata( 'post', $kit, '_elementor_page_settings', true )['custom_css'] && $css_add === $GLOBALS['customizer_css'] && '$gap: 0; ' . $css_add === $GLOBALS['customizer_src'], '--revert quita sólo las reglas añadidas del kit y del Personalizador (con su fuente preprocesada intacta); lo editado después se queda.' );

	f8_done( 'CSS del sitio con clases de PRO en DIGITALÍSIMO Migración', 13 );
}
