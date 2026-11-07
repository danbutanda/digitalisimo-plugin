<?php
/**
 * Widget global de PRO en DIGITALÍSIMO Migración y en Elements (C9 de docs/PRO_WIDGETS_OBJECTIVE.md, contrato
 * docs/pro/c9.md): `Convert\Globals` (pura) y su uso en `legacy/class-pro.php`.
 *
 * Desde la corrección de fidelidad de C9, «Migrar» pasa cada instancia con plantilla válida al widget global propio
 * (`digitalisimo-global`, `Globals::link()`), que sigue leyendo su plantilla: editarla cambia todas las páginas, como en
 * PRO. La sustitución por el contenido (`Globals::expand()`/`tree()`) queda para una conversión explícita y se sigue
 * probando aquí (sección 1).
 *
 * Cubre: la sustitución de cada instancia (`widgetType: global` con `templateID`) por el elemento guardado en su plantilla
 * de widget (mismo id de la instancia, ajustes de la plantilla, ids nuevos y estables para los hijos, clase
 * `elementor-global-<id>`, `_digitalisimo_global`); las guardas de PRO (contenido que no es un widget, otra instancia
 * global, plantilla en la papelera o de otro tipo) y el bucle; la cadena con los mapas listos (un Blockquote de PRO
 * guardado como widget global acaba en la Cita propia en la misma pasada); la etapa `pro` de Migrar (documentos con
 * widgets globales, informe `pro.global`/`pro.global_missing`, inventario «se convierte a digitalisimo-global»), la plantilla
 * intacta, la idempotencia y `--revert`.
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
	use Digitalisimo\Elements\Convert\Registry;
	use Digitalisimo\Elements\Convert\Globals;
	use Digitalisimo\Elements\Convert\Converter;

	function quote_map(): array {
		return array(
			'source' => 'blockquote',
			'target' => 'digitalisimo-blockquote',
			'status' => 'ready',
			'keep'   => array( 'blockquote_content', 'author_name' ),
		);
	}
	/** Plantilla de widget global de la biblioteca con ese elemento. */
	function global_template( array $element, array $post = array(), string $type = 'widget' ): int {
		return doc( array( $element ), $post + array( 'post_type' => 'elementor_library', 'post_title' => 'Global' ), array( '_elementor_template_type' => $type, '_elementor_template_widget_type' => (string) ( $element['widgetType'] ?? '' ) ) );
	}
	/** Instancia guardada por PRO: tipo `global`, plantilla y una copia de los ajustes (que PRO no usa al pintar). */
	function instance( int $template, string $id, array $snapshot = array() ): array {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => 'global', 'templateID' => $template, 'settings' => $snapshot, 'elements' => array() );
	}

	/* ================================================================ 1. Convert\Globals (pura) */
	$heading = w( 'heading', array( 'title' => 'Hola', 'header_size' => 'h3', '_css_classes' => 'mia' ), 'tpl1' );
	$codes   = null;
	$out     = Globals::expand( instance( 12, 'aaa1111', array( 'title' => 'copia vieja' ) ) + array( 'isInner' => true ), array( $heading ), $codes );
	$check( is_array( $out ) && 'aaa1111' === $out['id'] && 'heading' === $out['widgetType'] && 'widget' === $out['elType'] && true === $out['isInner'], 'La instancia pasa a ser el widget de la plantilla con el id de la instancia: ' . wp_json_encode( $out ) );
	$check( 'Hola' === $out['settings']['title'] && 'h3' === $out['settings']['header_size'] && 'mia elementor-global-12' === $out['settings']['_css_classes'] && 12 === $out['settings']['_digitalisimo_global'], 'Ajustes de la plantilla (no la copia de la instancia), su clase de PRO y la plantilla de origen.' );
	$check( ! isset( $out['templateID'] ) && ! isset( $out['originalWidgetType'] ) && 1 === count( $codes ) && 'pro.global' === $codes[0]['code'] && 'converted' === $codes[0]['section'], 'Sin templateID y con su código del informe.' );
	$nested  = array( 'id' => 'tpl2', 'elType' => 'widget', 'widgetType' => 'nested-tabs', 'settings' => array(), 'elements' => array( box( 'container', array( w( 'heading', array(), 'h1' ) ), 'c1' ) ) );
	$a       = Globals::expand( instance( 13, 'one' ), array( $nested ) );
	$b       = Globals::expand( instance( 13, 'two' ), array( $nested ) );
	$check( $a['elements'][0]['id'] !== $b['elements'][0]['id'] && $a['elements'][0]['id'] === Globals::expand( instance( 13, 'one' ), array( $nested ) )['elements'][0]['id'] && 7 === strlen( $a['elements'][0]['elements'][0]['id'] ), 'Hijos con ids nuevos, estables y distintos por instancia.' );
	$check( null === Globals::expand( instance( 14, 'x' ), array( instance( 9, 'y' ) ) ) && null === Globals::expand( instance( 14, 'x' ), array( box( 'container', array(), 'c' ) ) ) && null === Globals::expand( instance( 14, 'x' ), null ) && null === Globals::expand( instance( 0, 'x' ), array( $heading ) ) && null === Globals::expand( w( 'heading' ), array( $heading ) ), 'Guardas de PRO: otra instancia global, un contenedor, sin datos, sin plantilla o sin ser instancia: nada.' );
	$log  = null;
	$tree = Globals::tree(
		array( box( 'container', array( instance( 20, 'i1' ), instance( 21, 'i2' ), instance( 22, 'i3' ) ), 'k' ) ),
		static function ( $id ) use ( $heading ) {
			return 20 === $id ? array( $heading ) : ( 22 === $id ? array( instance( 22, 'self' ) ) : null );
		},
		$log
	);
	$check( 'heading' === $tree[0]['elements'][0]['widgetType'] && 'global' === $tree[0]['elements'][1]['widgetType'] && 'global' === $tree[0]['elements'][2]['widgetType'] && 3 === count( $log ) && array( true, false, false ) === array_column( $log, 'expanded' ) && 'pro.global_missing' === $log[1]['codes'][0]['code'], 'Árbol: la que tiene plantilla se sustituye; sin plantilla o con una que se contiene a sí misma, se queda (y se informa).' );
	$check( 3 === Globals::count( array( box( 'container', array( instance( 20, 'i1' ), box( 'container', array( instance( 21, 'i2' ), instance( 22, 'i3' ) ), 'z' ) ), 'k' ) ) ), 'Recuento a cualquier profundidad.' );
	// Cadena: el contenido es un widget de PRO con mapa listo → Converter lo convierte después.
	Registry::reset();
	Registry::set( 'blockquote', quote_map() );
	$expanded = Globals::tree( array( instance( 30, 'q1' ) ), static function () {
		return array( w( 'blockquote', array( 'blockquote_content' => 'Dentro' ), 'orig' ) );
	} );
	$conv     = Converter::tree( $expanded, $clog );
	$check( 'digitalisimo-blockquote' === $conv[0]['widgetType'] && 'q1' === $conv[0]['id'] && 'Dentro' === $conv[0]['settings']['blockquote_content'] && 30 === $conv[0]['settings']['_digitalisimo_global'] && 1 === count( $clog ), 'Un Blockquote guardado como widget global acaba en la Cita, con el id de la instancia.' );

	/* ================================================================ 2. Migración: etapa pro, informe, inventario y --revert */
	fresh();
	Registry::reset();
	Registry::set( 'blockquote', quote_map() );
	$check( Pro::globals(), 'Con el núcleo de Elements de C9, Migración sustituye los widgets globales.' );
	$t_heading = global_template( w( 'heading', array( 'title' => 'Título global' ), 'th' ) );
	$t_quote   = global_template( w( 'blockquote', array( 'blockquote_content' => 'Cita global', 'author_name' => 'Ana' ), 'tq' ) );
	$t_trash   = global_template( w( 'heading', array( 'title' => 'Borrada' ), 'tt' ), array( 'post_status' => 'trash' ) );
	$t_section = global_template( w( 'heading', array( 'title' => 'No es widget' ), 'ts' ), array(), 'section' );
	$tpl_raw   = (string) get_metadata( 'post', $t_quote, '_elementor_data', true );
	$page      = doc( array( box( 'container', array( instance( $t_heading, 'g1', array( 'title' => 'copia' ) ), instance( $t_quote, 'g2' ), instance( $t_heading, 'g3' ), instance( $t_trash, 'g4' ), instance( $t_section, 'g5' ), instance( 99999, 'g6' ) ), 'k1' ) ), array( 'post_title' => 'Con globales' ) );
	$orphan    = doc( array( instance( 99998, 'o1' ) ), array( 'post_title' => 'Sólo huérfana' ) );
	$original  = (string) get_metadata( 'post', $page, '_elementor_data', true );
	$check( in_array( $page, Pro::documents(), true ) && in_array( $orphan, Pro::documents(), true ), 'Los documentos con widgets globales están entre los que convierte la etapa pro.' );
	$inventory = Pro::inventory();
	$rows      = array_column( $inventory['widgets'], null, 'widget' );
	$check( 7 === $rows['global']['elements'] && 'digitalisimo-global' === $rows['global']['target'] && str_contains( $rows['global']['status'], 'digitalisimo-global' ), 'Inventario: 7 instancias globales que pasan al widget global propio: ' . wp_json_encode( $rows['global'] ?? null ) );
	$result = Pro::convert_document( $page );
	$data   = tree( $page );
	$check( 3 === $result['converted'] && 'digitalisimo-global' === find( $data, 'g1' )['widgetType'] && $t_heading === find( $data, 'g1' )['templateID'] && array( 'title' => 'copia' ) === find( $data, 'g1' )['settings'] && 'digitalisimo-global' === find( $data, 'g3' )['widgetType'] && $t_heading === find( $data, 'g3' )['templateID'], 'Las dos instancias del encabezado global pasan al widget global propio, vinculadas a su plantilla (mismo id, templateID y ajustes): ' . wp_json_encode( $result ) );
	$check( 'digitalisimo-global' === find( $data, 'g2' )['widgetType'] && $t_quote === find( $data, 'g2' )['templateID'], 'La instancia de la Cita global sigue vinculada a su plantilla (la plantilla la convierte la etapa, como cualquier documento de la biblioteca).' );
	$check( 'global' === find( $data, 'g4' )['widgetType'] && 'global' === find( $data, 'g5' )['widgetType'] && 'global' === find( $data, 'g6' )['widgetType'], 'Sin plantilla válida (papelera, de sección, inexistente) las instancias se quedan como estaban.' );
	$check( $tpl_raw === get_metadata( 'post', $t_quote, '_elementor_data', true ) && 'publish' === get_post( $t_quote )->post_status, 'La plantilla global no se borra ni cambia.' );
	$codes = doc_codes( $page );
	$check( in_array( 'pro.global:global:templateID', $codes, true ) && in_array( 'pro.converted:global:', $codes, true ) && ! in_array( 'pro.converted:blockquote:', $codes, true ) && 3 === count( array_filter( $codes, static function ( $c ) { return 0 === strpos( $c, 'pro.global_missing:global:' ); } ) ), 'Informe: pro.global, pro.converted y una fila pro.global_missing por plantilla que falta: ' . wp_json_encode( $codes ) );
	$again = Pro::convert_document( $page );
	$check( 0 === $again['converted'] && wp_json_encode( $data ) === wp_json_encode( tree( $page ) ), 'Una segunda pasada no repite nada.' );
	$check( array( 'converted' => 0, 'blocked' => array() ) === Pro::convert_document( $orphan ) && in_array( 'pro.global_missing:global:99998', doc_codes( $orphan ), true ), 'Un documento con una instancia sin plantilla no se escribe, pero el informe lo dice.' );
	$rev = Runner::revert();
	$check( empty( $rev['refused'] ) && $original === get_metadata( 'post', $page, '_elementor_data', true ), '--revert devuelve las instancias globales: ' . wp_json_encode( $rev ) );
	$check( $tpl_raw === get_metadata( 'post', $t_quote, '_elementor_data', true ), 'Y la plantilla sigue igual.' );
	// Migrar completo.
	fresh();
	Registry::reset();
	Registry::set( 'blockquote', quote_map() );
	$t   = global_template( w( 'heading', array( 'title' => 'Uno' ), 'th' ) );
	$pg  = doc( array( box( 'container', array( instance( $t, 'm1' ) ), 'k' ) ), array( 'post_title' => 'Migrar' ) );
	$tq  = global_template( w( 'blockquote', array( 'blockquote_content' => 'Cita global', 'author_name' => 'Ana' ), 'tq' ) );
	$pg2 = doc( array( instance( $tq, 'm2' ) ), array( 'post_title' => 'Otra página' ) );
	$pg3 = doc( array( instance( $tq, 'm3' ) ), array( 'post_title' => 'Y otra' ) );
	$st  = run_pro_stage();
	$check( 'retire_late' === $st['stage'] && 4 === (int) $st['counts']['pro'] && 'digitalisimo-global' === find( tree( $pg ), 'm1' )['widgetType'] && $t === find( tree( $pg ), 'm1' )['templateID'], 'La etapa pro de Migrar pasa el widget global al propio, vinculado a su plantilla: ' . wp_json_encode( $st['counts'] ) );
	$check( 'digitalisimo-blockquote' === tree( $tq )[0]['widgetType'] && 'Cita global' === tree( $tq )[0]['settings']['blockquote_content'] && 'digitalisimo-global' === find( tree( $pg2 ), 'm2' )['widgetType'] && $tq === find( tree( $pg3 ), 'm3' )['templateID'], 'La plantilla con una Cita de PRO se convierte en la misma pasada y las dos páginas siguen apuntando a ella.' );
	// El vínculo: editar la plantilla después de Migrar no toca las páginas, que la leen al pintar (lo pinta
	// Digitalisimo_Global, como el widget global de PRO: tests/elements-pro-global-link.php).
	$before = array( tree( $pg2 ), tree( $pg3 ) );
	update_metadata( 'post', $tq, '_elementor_data', wp_slash( wp_json_encode( array( w( 'digitalisimo-blockquote', array( 'blockquote_content' => 'Cita editada' ), 'tq' ) ) ) ) );
	run_pro_stage();
	$check( array( tree( $pg2 ), tree( $pg3 ) ) === $before && 'Cita editada' === tree( $tq )[0]['settings']['blockquote_content'], 'Editar la plantilla después de Migrar no copia nada en las páginas: siguen vinculadas.' );
	$check( ! in_array( $pg, Pro::documents(), true ) && ! in_array( $pg2, Pro::documents(), true ), 'Y los documentos ya no quedan por convertir.' );
	// Otra pasada de Migrar (etapa `report`) describe la copia original de lo ya vinculado, aunque no lleve la marca del
	// traductor ni `_digitalisimo_global`: el informe final sigue contando sus instancias globales.
	Report::clear();
	Report::begin( 'run' );
	Runner::describe_backup( $pg );
	$check( in_array( 'pro.global:global:templateID', doc_codes( $pg ), true ), 'La etapa report sigue informando de la instancia vinculada al widget global propio: ' . wp_json_encode( doc_codes( $pg ) ) );
	$catalog = require dirname( __DIR__ ) . '/digitalisimo-migration/legacy/report/codes-pro.php';
	$check( 'converted' === $catalog['pro.global']['section'] && 'review' === $catalog['pro.global_missing']['section'], 'Catálogo: pro.global en «convertido» y pro.global_missing en «por revisar».' );
	$guard = '/bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i';
	$check( ! preg_match( $guard, (string) file_get_contents( dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-convert/class-globals.php' ) ), 'Globals no nombra lo prohibido (guarda de build-packages.sh).' );

	f8_done( 'Widget global de PRO en DIGITALÍSIMO Migración y Convert\\Globals', 20 );
}
