<?php
/**
 * CSS del sitio que nombra clases de los listados y formularios de PRO en DIGITALÍSIMO Migración (corrección de
 * fidelidad de C5 y C6, docs/pro/c5.md y docs/pro/c6.md). Lo hacen las etapas `pro` y `pro_css` con el traductor del CSS
 * «desde fuera» de Elements (`Converter::external_css()`/`tree_css()`, corrección de C3–C4; su caso con un mapa de
 * prueba está en `migration-pro.php`, sección 4b).
 *
 * Cubre, con los mapas reales de Entradas y del Formulario: en un documento convertido, el CSS personalizado de otro
 * elemento (un contenedor) y el de la página ganan la copia de sus reglas con las clases propias (la original no
 * cambia); al terminar, el del kit activo y el CSS adicional del Personalizador, sin tocar su fuente preprocesada
 * (Sass/LESS de un plugin); el informe (`pro.css_external`); una segunda pasada no duplica nada; `--revert` devuelve el
 * documento (con el CSS de sus elementos), la página y el Personalizador, conserva el kit editado después de Migrar
 * (`core.revert_skipped`) y, con `--force`, también lo devuelve. Con el código anterior esas reglas dejaban de
 * aplicarse tras «Migrar» y nada lo decía.
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
	/** Las etapas `pro` y `pro_css` del runner, como en una petición, hasta que pasan a la siguiente. */
	function run_pro_stage( array $state = array() ): array {
		$state          = $state + Runner::fresh( true, 0, 1 );
		$state['stage'] = 'pro';
		foreach ( array( 'pro', 'pro_css' ) as $name ) {
			$stage = new ReflectionMethod( Runner::class, 'stage_' . $name );
			if ( PHP_VERSION_ID < 80100 ) {
				$stage->setAccessible( true );
			}
			for ( $i = 0; $i < 50 && $name === $state['stage']; $i++ ) {
				$state = $stage->invoke( null, $state, microtime( true ) + 30 );
			}
		}
		return $state;
	}
	/** Códigos del informe del sitio: `código:name`. */
	function site_codes(): array {
		Report::flush();
		$out = array();
		foreach ( (array) ( Report::stored()['site']['items'] ?? array() ) as $item ) {
			$out[] = $item['code'] . ':' . (string) ( $item['detail']['name'] ?? '' );
		}
		sort( $out );
		return $out;
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

	/** El CSS adicional del Personalizador del tema activo (un solo tema en la prueba): la entrada `custom_css`. */
	function wp_get_custom_css_post( $stylesheet = '' ) {
		foreach ( $GLOBALS['wp']['posts'][ $GLOBALS['wp']['blog'] ] ?? array() as $row ) {
			if ( 'custom_css' === $row['post_type'] ) {
				return (object) $row;
			}
		}
		return null;
	}

	$check( (bool) Pro::class_sets(), 'Con el núcleo de Elements que copia el CSS que nombra los widgets de PRO, Migración lo usa.' );
	fresh();
	$page_css = '.elementor-widget-form .elementor-field-group input{border:0}';
	$box_css  = 'selector .elementor-widget-posts .elementor-post__title{color:red}';
	$kit_css  = '.elementor-post__title a{color:blue}';
	$css_add  = "@media (max-width:767px){.elementor-widget-posts .elementor-pagination{gap:0}}";
	$css_src  = '$gap: 0; ' . $css_add;
	$posts    = w( 'posts', array( 'posts_per_page' => 3 ), 'p1' );
	$page     = doc( array( array( 'id' => 'k1', 'elType' => 'container', 'settings' => array( 'custom_css' => $box_css ), 'elements' => array( $posts ), 'isInner' => false ) ), array( 'post_title' => 'Con Entradas' ), array( '_elementor_page_settings' => array( 'custom_css' => $page_css, 'hide_title' => 'yes' ) ) );
	$kit      = f8_post( array( 'post_type' => 'elementor_library', 'post_title' => 'Kit' ), array( '_elementor_template_type' => 'kit', '_elementor_page_settings' => array( 'custom_css' => $kit_css, 'body_background_color' => '#fff' ) ) );
	update_option( 'elementor_active_kit', $kit );
	$cust     = f8_post( array( 'post_type' => 'custom_css', 'post_name' => 'tema', 'post_content' => $css_add, 'post_content_filtered' => $css_src ) );
	$original = (string) get_metadata( 'post', $page, '_elementor_data', true );

	$st   = run_pro_stage();
	$data = tree( $page );
	$check( 'retire_late' === $st['stage'] && 'digitalisimo-posts' === find( $data, 'p1' )['widgetType'], 'Las etapas pro y pro_css convierten las Entradas: ' . wp_json_encode( $st['counts'] ) );
	$box = (string) find( $data, 'k1' )['settings']['custom_css'];
	$check( str_starts_with( $box, $box_css ) && str_contains( $box, 'selector .elementor-widget-digitalisimo-posts .digi-posts__title{color:red}' ), 'El CSS del contenedor gana la copia con las clases propias detrás de la original: ' . $box );
	$ps = get_metadata( 'post', $page, '_elementor_page_settings', true );
	$check( $page_css . '.elementor-widget-digitalisimo-form .elementor-field-group input{border:0}' === (string) $ps['custom_css'] && 'yes' === $ps['hide_title'], 'El CSS de la página, igual (y el resto de sus ajustes intactos): ' . wp_json_encode( $ps ) );
	$ks = get_metadata( 'post', $kit, '_elementor_page_settings', true );
	$check( str_contains( (string) $ks['custom_css'], '.digi-posts__title a{color:blue}' ) && str_starts_with( (string) $ks['custom_css'], $kit_css ) && '#fff' === $ks['body_background_color'], 'Al terminar, el CSS del kit activo: ' . wp_json_encode( $ks ) );
	$check( 1 === preg_match( '#^@media \(max-width:767px\)\{\.elementor-widget-posts \.elementor-pagination\{gap:0\}[^@]*\.elementor-widget-digitalisimo-posts \.digi-loop__pagination\{gap:0\}[^@]*\}$#', get_post( $cust )->post_content ), 'Y el CSS adicional del Personalizador, dentro de su @media: ' . get_post( $cust )->post_content );
	$check( $css_src === get_post( $cust )->post_content_filtered, 'La fuente preprocesada del CSS adicional (Sass/LESS de un plugin) no cambia: ' . get_post( $cust )->post_content_filtered );
	$codes = doc_codes( $page );
	// El kit, con `_elementor_page_settings`, lo recorre la etapa `pro_css` como un documento más (o, si no, su final).
	$check( in_array( 'pro.css_external::', $codes, true ) && ( in_array( 'pro.css_external::', doc_codes( $kit ), true ) || in_array( 'pro.css_external:kit', site_codes(), true ) ) && in_array( 'pro.css_external:customizer', site_codes(), true ), 'Informe: pro.css_external en el documento, el kit y el Personalizador: ' . wp_json_encode( array( $codes, doc_codes( $kit ), site_codes() ) ) );
	$catalog = require dirname( __DIR__ ) . '/digitalisimo-migration/legacy/report/codes-pro.php';
	$check( 'converted' === ( $catalog['pro.css_external']['section'] ?? '' ) && ! isset( $catalog['pro.site_css'] ), 'Catálogo: pro.css_external en «convertido» (una sola regla, sin un código paralelo).' );

	// Una segunda pasada (otra vez Migrar) no duplica nada.
	$kit_after  = (string) get_metadata( 'post', $kit, '_elementor_page_settings', true )['custom_css'];
	$cust_after = get_post( $cust )->post_content;
	$box_after  = $box;
	$again      = run_pro_stage();
	$check( $kit_after === get_metadata( 'post', $kit, '_elementor_page_settings', true )['custom_css'] && $cust_after === get_post( $cust )->post_content && $box_after === (string) find( tree( $page ), 'k1' )['settings']['custom_css'] && 0 === (int) $again['counts']['pro_css'], 'Otra pasada no duplica las copias del contenedor, del kit ni del Personalizador: ' . wp_json_encode( $again['counts'] ) );

	// --revert: lo editado después de Migrar (aquí, el kit) se conserva y se informa; con --force, también vuelve.
	$ks                = get_metadata( 'post', $kit, '_elementor_page_settings', true );
	$ks['custom_css'] .= "\n.mia{margin:0}";
	update_metadata( 'post', $kit, '_elementor_page_settings', $ks );
	$rev = Runner::revert();
	$check( empty( $rev['refused'] ) && $original === get_metadata( 'post', $page, '_elementor_data', true ), '--revert devuelve el documento con el CSS original de sus elementos: ' . wp_json_encode( $rev ) );
	$check( $page_css === get_metadata( 'post', $page, '_elementor_page_settings', true )['custom_css'] && $css_add === get_post( $cust )->post_content && $css_src === get_post( $cust )->post_content_filtered, '--revert devuelve el CSS de la página y del Personalizador (con su fuente preprocesada intacta).' );
	$check( str_contains( get_metadata( 'post', $kit, '_elementor_page_settings', true )['custom_css'], '.mia{margin:0}' ) && in_array( array( 'core.revert_skipped', array( 'id' => $kit ) ), $rev['codes'], true ), 'El CSS del kit editado después de Migrar se conserva y se informa.' );
	Runner::revert( true );
	$check( $kit_css === get_metadata( 'post', $kit, '_elementor_page_settings', true )['custom_css'] && null === f8_meta( $kit, Pro::CSS_BACKUP ), 'Con --force, el del kit también vuelve.' );

	f8_done( 'CSS del sitio con clases de PRO de listados y formularios en DIGITALÍSIMO Migración', 14 );
}
