<?php
/**
 * Widgets de PRO en DIGITALÍSIMO Migración (docs/PRO_WIDGETS_OBJECTIVE.md, C0, frente C): `legacy/class-pro.php`.
 *
 * Cubre:
 * - sin el núcleo de conversión de DIGITALÍSIMO Elements: el análisis pide actualizarlo y nada se convierte (ni la
 *   etapa, ni convert_document(), ni los documentos por convertir);
 * - el inventario: widgets de PRO a cualquier profundidad (sección/columna y contenedores anidados), en páginas,
 *   cabeceras, popups y Loop Items, con su familia, sus documentos y su tipo; revisiones y autoguardados aparte;
 *   por lotes con cursor (varias vueltas dan lo mismo que una), con huella que lo rehace al cambiar los documentos;
 * - la conversión con un mapa inyectado como listo (`Registry::set( 'blockquote', … )`): mismo `id`, anidados, sólo lo
 *   que tiene mapa, copia del original con la marca F8 dentro, huella al día cuando la migración ya tocó el documento,
 *   copias de ≤ 1.0.20 sin marca nueva, cachés del documento, idempotencia, revisiones intactas, destino sin registrar
 *   (falla cerrado con `pro.target_missing`) e informe con su sección (`pro.converted`, `pro.dropped`, `pro.approx`,
 *   `pro.unmapped`, `pro.css`);
 * - la etapa `pro` del runner (entre `aliases` y `retire_late`, por lotes, con su recuento) dentro de Migrar completo, y
 *   `--revert`, que devuelve lo convertido y omite lo editado después;
 * - el análisis («Lo que hará Migrar» y la tabla «Widgets de PRO»), la celda de la pantalla de red y el catálogo `pro.`;
 * - C1 (frente K): la etapa `pro` va después de las conversiones de F8, y un Expo de Prime Slider que F8 lleva al
 *   Carrusel multimedia de PRO acaba en `digitalisimo-media-carousel` en la misma pasada de Migrar (mapa de prueba
 *   listo y, si existe, el borrador real), con la copia del original y `--revert`; las máscaras de Element Pack de los
 *   carruseles propios (`Masks::TARGETS`) coinciden con las clases de sus mapas;
 * - C2 (frente D, regla 6): qué orígenes de F8 llegan a los widgets de PRO de C2, y cada uno de Element Pack acaba en el
 *   widget propio en la misma pasada de Migrar (con el mapa de C2 del repositorio, sin claves sin declarar); la Cuenta
 *   regresiva con ajustes reales (fecha, etiquetas, mensaje, la cuenta en bucle como perpetua, CSS) y `--revert`.
 *
 * Se ejecuta con php-wasm (`node …/php-wasm-cli tests/migration-pro.php`): sale con 0 aunque haya un error fatal, así
 * que se juzga por la línea final.
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

	/** Un sitio con widgets de PRO en todos los sitios donde pueden estar. */
	function site(): array {
		$quote = array(
			'blockquote_content' => 'Citar es <b>recordar</b>',
			'author_name'        => 'Ana',
			'blockquote_skin'    => 'quotation',
			'tweet_button'       => '',
			'mystery_key'        => 'x',
			'custom_css'         => 'selector .elementor-blockquote__content { color: red; }',
			'_margin'            => array( 'top' => '10' ),
		);
		$ids = array();
		// Página: sección/columna y contenedores anidados, con un widget de PRO sin mapa al lado.
		$ids['page'] = doc(
			array(
				box( 'section', array( box( 'column', array( w( 'blockquote', $quote, 'q1' ), w( 'nav-menu', array( 'menu' => 'principal' ), 'n1' ) ), 'c1' ) ), 's1' ),
				box( 'container', array( box( 'container', array( w( 'blockquote', array( 'blockquote_content' => 'Anidada' ), 'q2' ) ), 'k2' ) ), 'k1' ),
			),
			array( 'post_title' => 'Inicio' )
		);
		$ids['header'] = doc( array( box( 'container', array( w( 'nav-menu', array(), 'n2' ) ), 'h1' ) ), array( 'post_type' => 'elementor_library', 'post_title' => 'Cabecera' ), array( '_elementor_template_type' => 'header' ) );
		$ids['popup']  = doc( array( box( 'container', array( w( 'form', array( 'form_name' => 'Contacto' ), 'f1' ) ), 'p1' ) ), array( 'post_type' => 'elementor_library', 'post_title' => 'Popup' ), array( '_elementor_template_type' => 'popup' ) );
		$ids['loop']   = doc( array( box( 'container', array( w( 'blockquote', array( 'blockquote_content' => 'En bucle' ), 'q3' ) ), 'l1' ) ), array( 'post_type' => 'elementor_library', 'post_title' => 'Elemento de bucle' ), array( '_elementor_template_type' => 'loop-item' ) );
		$ids['plain']  = doc( array( box( 'container', array( w( 'heading', array( 'title' => 'Sin PRO' ), 'h9' ) ), 'x1' ) ), array( 'post_title' => 'Normal' ) );
		$ids['empty']  = doc( array(), array( 'post_title' => 'Vacía' ) );
		// Revisión y autoguardado de la página: se cuentan aparte y nunca se convierten.
		$ids['revision'] = doc( array( w( 'blockquote', array( 'blockquote_content' => 'Vieja' ), 'q1' ) ), array( 'post_type' => 'revision', 'post_parent' => $ids['page'], 'post_name' => $ids['page'] . '-revision-v1' ) );
		$ids['autosave'] = doc( array( w( 'blockquote', array(), 'q1' ), w( 'blockquote', array(), 'q2' ) ), array( 'post_type' => 'revision', 'post_parent' => $ids['page'], 'post_name' => $ids['page'] . '-autosave-v1' ) );
		return $ids;
	}

	/** Mapa del piloto, inyectado como listo (el de verdad lo publica el frente B). */
	function pilot_map(): array {
		return array(
			'source'   => 'blockquote',
			'target'   => 'digitalisimo-blockquote',
			'status'   => 'ready',
			'panel'    => 'hide',
			'defaults' => array( 'blockquote_skin' => 'border', 'tweet_button' => 'yes' ),
			'keep'     => array( 'blockquote_content', 'author_name' ),
			'rename'   => array( 'blockquote_skin' => 'skin' ),
			'approx'   => array( 'blockquote_skin' => 'Las presentaciones del widget propio no son idénticas.' ),
			'drop'     => array( 'tweet_button' => 'El widget propio no comparte en redes.' ),
			'classes'  => array( '.elementor-blockquote__content' => '.digi-blockquote__content' ),
		);
	}

	/* ================================================================ 1. Sin el núcleo de conversión de Elements */
	fresh();
	$ids = site();
	$raw = get_metadata( 'post', $ids['page'], '_elementor_data', true );
	$check( ! Pro::available() && array() === Pro::ready() && array() === Pro::documents(), 'Sin el núcleo de Elements no hay nada que convertir.' );
	$check( array( 'converted' => 0, 'blocked' => array() ) === Pro::convert_document( $ids['page'] ) && $raw === get_metadata( 'post', $ids['page'], '_elementor_data', true ) && null === f8_meta( $ids['page'], Migration::BACKUP ), 'convert_document() no toca nada sin el núcleo.' );
	$inventory = Pro::inventory();
	$check( false === $inventory['available'] && array() === $inventory['widgets'], 'El inventario dice que no está disponible.' );
	$html = Pro::table_html( $inventory );
	$check( false !== strpos( $html, 'actualizar DIGITALÍSIMO Elements' ) && false !== strpos( $html, 'Widgets de PRO' ), 'El análisis pide actualizar DIGITALÍSIMO Elements: ' . $html );
	$check( 'Actualiza DIGITALÍSIMO Elements' === Pro::network_cell( null ), 'La pantalla de red también.' );
	$check( Pro::current( Pro::step( null ) ), 'El inventario sin núcleo está «completo» (la pantalla no lo pide en bucle).' );
	$state = run_pro_stage();
	$check( 'retire_late' === $state['stage'] && 0 === (int) $state['counts']['pro'] && $raw === get_metadata( 'post', $ids['page'], '_elementor_data', true ), 'La etapa pro pasa a retire_late sin tocar nada.' );

	/* ================================================================ El núcleo de Elements (autocarga acotada a Convert\) */
	require_once dirname( __DIR__ ) . '/digitalisimo-elements/modules/digitalisimo-convert/class-module.php';
	\Digitalisimo\Elements\Convert\Module::init();
	use Digitalisimo\Elements\Convert\Registry;
	Registry::reset();
	Pro::reset();
	$check( Pro::available(), 'Con el núcleo de Elements, Pro está disponible.' );

	/* ================================================================ 2. Inventario */
	fresh();
	Registry::set( 'blockquote', pilot_map() );
	$ids       = site();
	$inventory = Pro::inventory();
	$rows      = array_column( $inventory['widgets'], null, 'widget' );
	$check( true === $inventory['available'] && 8 === $inventory['scanned'], 'Revisa los 8 documentos con _elementor_data (también revisiones): ' . $inventory['scanned'] );
	$check( 3 === $rows['blockquote']['elements'] && 2 === $rows['blockquote']['documents'], 'Blockquote: 3 elementos (anidados y en el Loop Item) en 2 documentos: ' . wp_json_encode( $rows['blockquote'] ) );
	$check( 3 === $rows['blockquote']['revision_elements'] && 2 === $rows['blockquote']['revisions'], 'Revisión y autoguardado aparte: 3 elementos en 2 revisiones.' );
	$check( 2 === $rows['nav-menu']['elements'] && 2 === $rows['nav-menu']['documents'] && 1 === $rows['form']['elements'] && 0 === $rows['form']['revisions'], 'El menú en la página y en la cabecera; el formulario en el popup.' );
	$check( 4 === $inventory['documents'] && 2 === $inventory['revisions'], 'Documentos con widgets de PRO: 4 (página, cabecera, popup, Loop Item); revisiones: 2.' );
	$check( array( 'header' => 1, 'loop-item' => 1, 'page' => 1, 'popup' => 1 ) == $inventory['where'], 'Por tipo: plantillas por su tipo, entradas por el suyo: ' . wp_json_encode( $inventory['where'] ) );
	$check( 'C2' === $rows['blockquote']['family'] && 'C3' === $rows['nav-menu']['family'] && 'C6' === $rows['form']['family'], 'Cada widget con su familia (Catalog::family()).' );
	$check( 'digitalisimo-blockquote' === $rows['blockquote']['target'] && 'Se convierte a digitalisimo-blockquote' === $rows['blockquote']['status'], 'Con mapa listo: «Se convierte a <widget propio>».' );
	$check( '' === $rows['nav-menu']['target'] && 'Pendiente: familia C3' === $rows['nav-menu']['status'], 'Sin mapa: «Pendiente: familia Cn».' );
	$check( array( 'C2', 'C3', 'C6' ) === array_keys( $inventory['families'] ) && 3 === $inventory['families']['C2']['elements'] && 2 === $inventory['families']['C2']['documents'] && 'Contenido' === $inventory['families']['C2']['label'], 'Familias de más a menos usadas, con su nombre.' );
	$check( array( 'C2', 'C3', 'C6' ) === array_column( $inventory['widgets'], 'family' ), 'Filas por familia.' );
	// Lo que haría Migrar: el informe previsto, sin escribir nada.
	$expected = array( 'pro.approx:blockquote:blockquote_skin', 'pro.converted:blockquote:', 'pro.css:blockquote:custom_css', 'pro.dropped:blockquote:tweet_button', 'pro.unmapped:blockquote:mystery_key' );
	$check( $expected === doc_codes( $ids['page'], $inventory['report'] ), 'Informe previsto de la página: ' . wp_json_encode( doc_codes( $ids['page'], $inventory['report'] ) ) );
	$check( array( 'pro.converted:blockquote:' ) === doc_codes( $ids['loop'], $inventory['report'] ) && ! doc_codes( $ids['revision'], $inventory['report'] ) && ! doc_codes( $ids['header'], $inventory['report'] ), 'Sólo lo que se convierte; nada de revisiones ni de widgets sin mapa.' );
	$check( null === f8_meta( $ids['page'], Migration::BACKUP ) && Report::is_empty( Report::stored() ) && 'run' === Report::mode(), 'El inventario no escribe nada (ni copias ni informe) y devuelve el informe a su modo.' );
	// Por lotes: el recorrido nunca pide todo de una vez y varias vueltas cortas dan lo mismo que una.
	for ( $i = 0; $i < 230; $i++ ) {
		doc( array( w( 'heading', array( 'title' => 'Relleno ' . $i ), 'r' . $i ) ), array( 'post_title' => 'Relleno' ) );
	}
	$last = doc( array( w( 'countdown', array(), 'cd' ) ), array( 'post_title' => 'Al final' ) );
	$GLOBALS['wpdb']->inventory_rows = array();
	$GLOBALS['wpdb']->queries        = array();
	$state = Pro::step( null, 0, 60 );
	$check( empty( $state['done'] ) && 100 === $state['processed'] && 239 === $state['total'], 'Una vuelta con tope de 60 documentos termina su lote de 100 y se detiene: ' . $state['processed'] . ' de ' . $state['total'] );
	$turns = 1;
	while ( empty( $state['done'] ) && $turns < 20 ) {
		$state = Pro::step( $state, 0, 60 );
		++$turns;
	}
	$sliced = Pro::describe( $state );
	$check( ! empty( $state['done'] ) && 239 === $sliced['scanned'] && $turns >= 3, 'Termina en varias vueltas: ' . $turns );
	$check( max( $GLOBALS['wpdb']->inventory_rows ) <= Pro::INVENTORY_BATCH && false !== strpos( implode( "\n", $GLOBALS['wpdb']->queries ), 'LIMIT 100' ), 'Cada consulta trae como mucho un lote (LIMIT 100).' );
	$whole = Pro::inventory();
	$check( $whole['widgets'] === $sliced['widgets'] && $whole['families'] === $sliced['families'] && isset( array_column( $whole['widgets'], null, 'widget' )['countdown'] ), 'Por trozos o entero, el mismo inventario (también el último documento).' );
	$check( Pro::current( $state ), 'Un inventario completo sigue al día mientras no cambie nada.' );
	update_metadata( 'post', $last, '_elementor_data', wp_slash( wp_json_encode( array( w( 'countdown', array(), 'cd' ), w( 'lottie', array(), 'lt' ) ) ) ) );
	$check( ! Pro::current( $state ), 'Al cambiar un documento, la huella cambia y el inventario se rehace.' );
	$again = Pro::step( $state, 0, 0 );
	$check( 1 === array_column( Pro::describe( $again )['widgets'], null, 'widget' )['lottie']['elements'], 'El inventario rehecho ve el cambio.' );

	/* ================================================================ 3. Conversión de un documento */
	fresh();
	Registry::set( 'blockquote', pilot_map() );
	$ids = site();
	update_metadata( 'post', $ids['page'], '_elementor_css', array( 'status' => 'file' ) );
	update_metadata( 'post', $ids['page'], '_elementor_page_settings', array( 'hide_title' => 'yes' ) );
	$original = (string) get_metadata( 'post', $ids['page'], '_elementor_data', true );
	$check( array( $ids['page'], $ids['loop'] ) === Pro::documents(), 'Documentos por convertir: los que tienen un widget con mapa listo, sin revisiones ni los que sólo tienen widgets pendientes.' );
	$check( array( $ids['loop'] ) === Pro::documents( $ids['page'] ) && array( $ids['page'] ) === Pro::documents( 0, 1 ), 'Con cursor y límite, como los alias.' );
	$result = Pro::convert_document( $ids['page'] );
	$after  = tree( $ids['page'] );
	$q1     = find( $after, 'q1' );
	$q2     = find( $after, 'q2' );
	$check( 2 === $result['converted'] && array() === $result['blocked'], 'Convierte los dos Blockquote de la página: ' . wp_json_encode( $result ) );
	$check( $q1 && 'digitalisimo-blockquote' === $q1['widgetType'] && 'widget' === $q1['elType'] && 'Citar es <b>recordar</b>' === $q1['settings']['blockquote_content'] && 'Ana' === $q1['settings']['author_name'] && 'quotation' === $q1['settings']['skin'], 'Mismo id y contenido; ajustes traducidos por el mapa.' );
	$check( ! isset( $q1['settings']['mystery_key'] ) && ! isset( $q1['settings']['tweet_button'] ) && array( 'top' => '10' ) === $q1['settings']['_margin'] && 'blockquote' === $q1['settings']['_digitalisimo_pro'], 'Lo no declarado y lo descartado no pasan; lo común sí; queda la marca del traductor.' );
	$check( false !== strpos( $q1['settings']['custom_css'], '.digi-blockquote__content' ) && false === strpos( $q1['settings']['custom_css'], 'elementor-blockquote' ), 'El CSS personalizado del elemento pasa a las clases del widget propio.' );
	$check( $q2 && 'digitalisimo-blockquote' === $q2['widgetType'] && 'k2' === find( $after, 'k2' )['id'], 'También dentro de contenedores anidados.' );
	$check( 'nav-menu' === find( $after, 'n1' )['widgetType'] && array( 'menu' => 'principal' ) === find( $after, 'n1' )['settings'], 'El widget de PRO sin mapa queda tal cual.' );
	$backup = json_decode( (string) f8_meta( $ids['page'], Migration::BACKUP ), true );
	$mark   = Migration::f8_mark( $ids['page'] );
	$check( is_array( $backup ) && $original === $backup['data'] && is_array( $backup['f8'] ?? null ), 'Copia del original con la marca F8 dentro (una escritura menos, como Migration).' );
	$check( is_array( $mark ) && md5( (string) get_metadata( 'post', $ids['page'], '_elementor_data', true ) ) === $mark['hash'] && 0 === (int) $mark['restricted'] && 1 === (int) $mark['v'], 'La marca F8 lleva la huella de lo escrito y no es de modo restringido.' );
	$check( false === Migration::restricted( $ids['page'] ), 'El documento no pasa por migrado con una versión antigua.' );
	$check( null === f8_meta( $ids['page'], '_elementor_css' ) && array( 'hide_title' => 'yes' ) === f8_meta( $ids['page'], '_elementor_page_settings' ), 'Vacía la caché de CSS del documento y no toca sus ajustes de página.' );
	$codes = doc_codes( $ids['page'] );
	$check( $expected === $codes, 'Informe del documento, con el widget y la clave: ' . wp_json_encode( $codes ) );
	$catalog = Report::catalog();
	$sections = array();
	foreach ( (array) Report::stored()['docs'][ $ids['page'] ]['items'] as $item ) {
		$sections[ $item['code'] ] = Report::section_of( $item );
	}
	$check( array( 'pro.approx' => 'approx', 'pro.converted' => 'converted', 'pro.css' => 'converted', 'pro.dropped' => 'dropped', 'pro.unmapped' => 'review' ) == $sections, 'Cada código en su sección: ' . wp_json_encode( $sections ) );
	$converted = null;
	foreach ( (array) Report::stored()['docs'][ $ids['page'] ]['items'] as $item ) {
		if ( 'pro.converted' === $item['code'] ) {
			$converted = $item['detail'];
		}
	}
	$check( array( 'count' => 2, 'target' => 'digitalisimo-blockquote', 'widget' => 'blockquote' ) == $converted, 'pro.converted agrupado por widget, con su destino y recuento.' );
	$check( Report::pending( Report::stored() ), 'Una clave sin declarar deja el sitio con «Pendiente de revisar».' );
	// Idempotencia.
	$written = (string) get_metadata( 'post', $ids['page'], '_elementor_data', true );
	$copy    = (string) f8_meta( $ids['page'], Migration::BACKUP );
	Report::clear();
	Report::begin( 'run' );
	$check( 0 === Pro::convert_document( $ids['page'] )['converted'] && $written === get_metadata( 'post', $ids['page'], '_elementor_data', true ) && $copy === f8_meta( $ids['page'], Migration::BACKUP ) && ! doc_codes( $ids['page'] ), 'Una segunda vez no cambia nada ni repite el informe.' );
	$check( ! in_array( $ids['page'], Pro::documents(), true ), 'Ya no figura entre los documentos por convertir.' );
	// Revisiones: nunca se convierten.
	$rev_raw = (string) get_metadata( 'post', $ids['revision'], '_elementor_data', true );
	$check( 0 === Pro::convert_document( $ids['revision'] )['converted'] && $rev_raw === get_metadata( 'post', $ids['revision'], '_elementor_data', true ) && null === f8_meta( $ids['revision'], Migration::BACKUP ), 'Una revisión no se convierte (el widget de PRO sigue registrado y se ve).' );
	// Documento ya tocado por la migración (copia con su marca F8 en la meta): la copia no cambia; la huella, sí.
	$ep_raw  = wp_json_encode( array( w( 'bdt-accordion', array(), 'e1' ) ) );
	$touched = doc( array( w( 'blockquote', array( 'blockquote_content' => 'Tras F8' ), 'q7' ) ), array( 'post_title' => 'Migrada' ) );
	add_metadata( 'post', $touched, Migration::BACKUP, wp_slash( wp_json_encode( array( 'time' => 1000, 'data' => $ep_raw ) ) ) );
	update_metadata( 'post', $touched, Migration::F8_MARK, wp_slash( wp_json_encode( array( 'v' => 1, 'time' => 1000, 'hash' => md5( (string) get_metadata( 'post', $touched, '_elementor_data', true ) ), 'page_hash' => '', 'restricted' => 0 ) ) ) );
	Pro::convert_document( $touched );
	$mark = Migration::f8_mark( $touched );
	$check( $ep_raw === json_decode( (string) f8_meta( $touched, Migration::BACKUP ), true )['data'] && md5( (string) get_metadata( 'post', $touched, '_elementor_data', true ) ) === $mark['hash'] && 1000 === (int) $mark['time'], 'Ya migrado: conserva la copia original (de antes de Element Pack) y pone la huella al día sin cambiar la fecha.' );
	// Copia de ≤ 1.0.20 sin marca: no se le crea una (seguiría siendo de modo restringido).
	$old = doc( array( w( 'blockquote', array(), 'q8' ) ), array( 'post_title' => 'Antigua' ) );
	add_metadata( 'post', $old, Migration::BACKUP, wp_slash( wp_json_encode( array( 'time' => 900, 'data' => $ep_raw ) ) ) );
	Pro::convert_document( $old );
	$check( 'digitalisimo-blockquote' === tree( $old )[0]['widgetType'] && null === Migration::f8_mark( $old ) && Migration::restricted( $old ), 'Migrado con ≤ 1.0.20: se convierte y sigue en modo restringido.' );
	// Destino sin registrar: falla cerrado.
	manager( array( 'heading' ) );
	$loop_raw = (string) get_metadata( 'post', $ids['loop'], '_elementor_data', true );
	$check( array() === Pro::ready() && 'digitalisimo-blockquote' === ( Pro::missing_targets()['blockquote'] ?? '' ) && ! array_diff_key( Pro::missing_targets(), Registry::all() ) && array() === Pro::documents(), 'Sin el widget propio registrado, nada está listo ni por convertir.' );
	Report::clear();
	Report::begin( 'run' );
	$blocked = Pro::convert_document( $ids['loop'] );
	$check( array( 'blockquote' ) === $blocked['blocked'] && 0 === $blocked['converted'] && $loop_raw === get_metadata( 'post', $ids['loop'], '_elementor_data', true ) && null === f8_meta( $ids['loop'], Migration::BACKUP ), 'convert_document() no escribe nada si falta el destino.' );
	$check( array( 'pro.target_missing:blockquote:' ) === doc_codes( $ids['loop'] ) && 'review' === Report::catalog()['pro.target_missing']['section'], 'Y lo informa en «Pendiente de revisar» (pro.target_missing).' );
	$check( 'Pendiente: digitalisimo-blockquote no está registrado en este sitio' === Pro::status( 'blockquote', '', 'C2' ), 'El análisis dice que el destino no está registrado.' );
	manager( array( 'heading', 'digitalisimo-blockquote' ) );
	$check( array( 'blockquote' => 'digitalisimo-blockquote' ) === Pro::ready(), 'Con el widget propio registrado, vuelve a estar listo.' );
	manager( null );

	/* ================================================================ 4. La etapa pro y Migrar completo, y --revert */
	$stages = Runner::STAGES;
	$check( array_search( 'pro', $stages, true ) === array_search( 'aliases', $stages, true ) + 1 && array_search( 'retire_late', $stages, true ) === array_search( 'pro', $stages, true ) + 1 && in_array( 'pro', Runner::LOOP_STAGES, true ), 'La etapa pro va entre aliases y retire_late y recorre documentos.' );
	fresh();
	Registry::set( 'blockquote', pilot_map() );
	$ids      = site();
	$original = (string) get_metadata( 'post', $ids['page'], '_elementor_data', true );
	$loop_raw = (string) get_metadata( 'post', $ids['loop'], '_elementor_data', true );
	$state    = run_pro_stage();
	$check( 'retire_late' === $state['stage'] && 2 === $state['counts']['pro'] && 3 === $state['counts']['pro_elements'], 'La etapa convierte 2 documentos (3 widgets) y pasa a retire_late: ' . wp_json_encode( array( $state['counts']['pro'], $state['counts']['pro_elements'] ) ) );
	$check( 'digitalisimo-blockquote' === find( tree( $ids['loop'] ), 'q3' )['widgetType'] && 'blockquote' === tree( $ids['revision'] )[0]['widgetType'], 'El Loop Item convertido; la revisión intacta.' );
	// Por lotes: con más documentos que un lote, la etapa avanza con su cursor.
	fresh();
	Registry::set( 'blockquote', pilot_map() );
	$many = array();
	for ( $i = 0; $i < Runner::BATCH + 5; $i++ ) {
		$many[] = doc( array( w( 'blockquote', array( 'blockquote_content' => 'n' . $i ), 'b' . $i ) ), array( 'post_title' => 'Lote ' . $i ) );
	}
	$state = run_pro_stage();
	$left  = array_filter( $many, static function ( $id ) {
		return 'blockquote' === tree( $id )[0]['widgetType'];
	} );
	$check( 'retire_late' === $state['stage'] && Runner::BATCH + 5 === $state['counts']['pro'] && ! $left, 'Más de un lote: los ' . ( Runner::BATCH + 5 ) . ' documentos convertidos.' );

	// Migrar de punta a punta (sin Element Pack): la etapa corre dentro de la tarea y el informe lo dice.
	fresh();
	Registry::set( 'blockquote', pilot_map() );
	f8_plugin( 'digitalisimo-elements/pro-elements.php', array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.171' ), 'site' );
	$ids      = site();
	$original = (string) get_metadata( 'post', $ids['page'], '_elementor_data', true );
	$loop_raw = (string) get_metadata( 'post', $ids['loop'], '_elementor_data', true );
	$state    = migrate();
	$check( ! empty( $state['done'] ) && 2 === (int) $state['counts']['pro'] && 3 === (int) $state['counts']['pro_elements'], 'Migrar termina con 2 documentos y 3 widgets de PRO convertidos: ' . wp_json_encode( $state['counts'] ) );
	$check( 0 === (int) ( $state['report']['pro_left'] ?? -1 ), 'El informe no deja documentos de PRO por convertir.' );
	$html = Runner::render( $state );
	$check( false !== strpos( $html, 'Documentos con widgets de PRO convertidos a widgets propios: 2 (3 widgets).' ) && false !== strpos( $html, 'Pendiente de revisar' ), 'El informe cuenta lo convertido y deja la clave sin declarar en «Pendiente de revisar».' );
	$check( Runner::has_pending( $state ), 'Con una clave sin declarar, el sitio queda con asuntos por revisar.' );
	$check( in_array( 'pro.converted', array_column( (array) Report::stored()['docs'][ $ids['page'] ]['items'], 'code' ), true ), 'El informe guardado de F8 tiene los códigos pro.' );
	// Volver a pulsar Migrar: nada cambia y el informe sigue diciendo lo convertido (relee la copia).
	$written = (string) get_metadata( 'post', $ids['page'], '_elementor_data', true );
	// La pasada anterior fue antes (en la prueba, el mismo segundo): su copia lleva una marca con fecha anterior.
	foreach ( array( $ids['page'], $ids['loop'] ) as $id ) {
		$backup             = json_decode( (string) f8_meta( $id, Migration::BACKUP ), true );
		$backup['f8']['time'] = (int) $backup['f8']['time'] - 60;
		update_metadata( 'post', $id, Migration::BACKUP, wp_slash( wp_json_encode( $backup ) ) );
	}
	$state2  = migrate();
	$check( ! empty( $state2['done'] ) && 0 === (int) $state2['counts']['pro'] && $written === get_metadata( 'post', $ids['page'], '_elementor_data', true ), 'Volver a pulsar Migrar no reconvierte.' );
	$check( in_array( 'pro.converted:blockquote:', doc_codes( $ids['page'] ), true ), 'Y el informe nuevo vuelve a listar lo convertido en la primera pasada.' );
	// Un documento editado después de migrar.
	$edited = doc( array( w( 'blockquote', array( 'blockquote_content' => 'Editable' ), 'q9' ) ), array( 'post_title' => 'Editada' ) );
	$edited_raw = (string) get_metadata( 'post', $edited, '_elementor_data', true );
	Pro::convert_document( $edited );
	update_metadata( 'post', $edited, '_elementor_data', wp_slash( wp_json_encode( array( w( 'heading', array( 'title' => 'Editado a mano' ), 'x' ) ) ) ) );
	$rev = Runner::revert();
	$check( empty( $rev['refused'] ) && $rev['documents'] >= 2, '--revert restaura lo convertido: ' . wp_json_encode( array( $rev['documents'], $rev['skipped'] ) ) );
	$check( $original === get_metadata( 'post', $ids['page'], '_elementor_data', true ) && $loop_raw === get_metadata( 'post', $ids['loop'], '_elementor_data', true ), 'La página y el Loop Item vuelven a su widget de PRO original.' );
	$check( null === f8_meta( $ids['page'], Migration::BACKUP ) && null === Migration::f8_mark( $ids['page'] ), 'Sin copia ni marca tras restaurar.' );
	$check( str_contains( (string) get_metadata( 'post', $edited, '_elementor_data', true ), 'Editado a mano' ) && $rev['skipped'] >= 1, 'Lo editado después de migrar se omite.' );
	Runner::revert( true );
	$check( $edited_raw === get_metadata( 'post', $edited, '_elementor_data', true ), 'Con --force, también vuelve.' );

	/* ================================================================ 5. Análisis, pantalla de red y catálogo */
	fresh();
	Registry::set( 'blockquote', pilot_map() );
	$ids    = site();
	$result = Migration::analyze_step( 0 );
	$check( ! empty( $result['done'] ) && is_array( $result['pro'] ) && ! empty( $result['pro']['done'] ), 'El análisis entero (WP-CLI) incluye el inventario de PRO.' );
	$html = Migration::analysis_html( $result );
	$check( false !== strpos( $html, '<h3>Widgets de PRO</h3>' ) && false !== strpos( $html, 'Se convierte a digitalisimo-blockquote' ) && false !== strpos( $html, 'Pendiente: familia C3' ) && false !== strpos( $html, '<code>nav-menu</code>' ), 'La pantalla muestra la tabla con su estado.' );
	$check( false !== strpos( $html, 'Lo que hará Migrar' ) && false !== strpos( $html, 'Pendiente de revisar' ), '«Lo que hará Migrar» incluye los códigos de PRO.' );
	$check( false !== strpos( $html, 'C2 Contenido: 3 elementos en 2 documentos' ) && false !== strpos( $html, 'header: 1' ), 'Con el resumen por familia y por tipo de documento.' );
	$cache = get_option( Migration::ANALYSIS );
	$check( Pro::current( $cache['pro'] ?? null ), 'El inventario se guarda en la caché del análisis y sigue al día.' );
	$cell = Pro::network_cell( $cache );
	$check( '2 documentos por convertir; en uso: 6 elementos de 3 widgets (3 pendientes de su familia)' === $cell, 'Celda de la red: ' . $cell );
	// Por trozos, como la pantalla: la primera respuesta puede no estar completa, pero termina.
	delete_option( Migration::ANALYSIS );
	$turns = 0;
	do {
		$result = Migration::analyze_step( 0.000001 );
		++$turns;
	} while ( empty( $result['done'] ) && $turns < 50 );
	$check( ! empty( $result['done'] ) && false !== strpos( Migration::analysis_html( $result ), 'Widgets de PRO' ), 'Por trozos también termina: ' . $turns . ' vueltas.' );
	$check( false !== strpos( Migration::analysis_html( array( 'done' => false, 'pro_stage' => true, 'pro' => array( 'processed' => 3, 'total' => 9 ) ) ), 'Contando los widgets de PRO… (3 de 9 documentos)' ), 'Mientras cuenta, la pantalla dice el avance.' );
	// Escape campo a campo.
	$xss  = '<img src=x onerror=alert(1)>';
	$html = Pro::table_html( array( 'available' => true, 'scanned' => 1, 'documents' => 1, 'revisions' => 0, 'widgets' => array( array( 'widget' => $xss, 'family' => 'C2', 'family_label' => $xss, 'elements' => 1, 'documents' => 1, 'revision_elements' => 0, 'revisions' => 0, 'target' => '', 'status' => $xss ) ), 'families' => array( 'C2' => array( 'label' => $xss, 'elements' => 1, 'documents' => 1 ) ), 'where' => array( $xss => 1 ) ) );
	$check( false === strpos( $html, '<img' ), 'La tabla escapa cada campo.' );
	// Catálogo.
	Report::reset_catalog();
	$catalog = Report::catalog();
	$codes   = include dirname( __DIR__ ) . '/digitalisimo-migration/legacy/report/codes-pro.php';
	$check( 'pro.' === Report::CATALOGS['pro'], 'El informe declara el catálogo pro con su prefijo.' );
	$mine = array_keys( $codes );
	sort( $mine );
	$check( array( 'pro.approx', 'pro.converted', 'pro.css', 'pro.dropped', 'pro.target_missing', 'pro.unmapped' ) === $mine, 'Catálogo pro: ' . implode( ', ', $mine ) );
	foreach ( $codes as $code => $spec ) {
		$check( isset( $catalog[ $code ] ) && $catalog[ $code ]['section'] === $spec['section'], 'El informe carga ' . $code . ' con su sección.' );
	}
	$check( 'dropped' === $catalog['pro.dropped']['section'] && 'approx' === $catalog['pro.approx']['section'] && 'review' === $catalog['pro.unmapped']['section'] && 'converted' === $catalog['pro.css']['section'], 'Las secciones coinciden con las del traductor de Elements.' );

	/* ================================================================ 6. La cadena F8 → PRO → widget propio (C1, frente K) */
	// La etapa `pro` corre después de todas las conversiones de F8 (Element Pack, Prime Slider, sus revisiones, los
	// marcadores de K y la plataforma), así que lo que F8 lleva a un carrusel de PRO acaba en el propio en la misma pasada.
	$at = array_flip( Runner::STAGES );
	$check( $at['platform_convert'] < $at['pro'] && $at['convert'] < $at['pro'] && $at['revisions'] < $at['pro'] && $at['markers'] < $at['pro'] && $at['platform'] < $at['pro'], 'La etapa pro va después de las conversiones de F8 (platform_convert, convert, revisions, markers, platform).' );
	/** Mapa de prueba del Carrusel multimedia, listo (el de verdad lo publica el frente M de C1 tras su A/B). */
	function chain_map(): array {
		return array(
			'source'    => 'media-carousel',
			'target'    => 'digitalisimo-media-carousel',
			'status'    => 'ready',
			'panel'     => 'hide',
			'defaults'  => array( 'skin' => 'carousel', 'effect' => 'slide', 'loop' => 'yes', 'autoplay' => 'yes', 'autoplay_speed' => 5000, 'speed' => 500 ),
			'keep'      => array( 'skin', 'effect', 'autoplay', 'loop', 'pause_on_hover', 'show_arrows', 'pagination', 'autoplay_speed', 'speed', 'slides_per_view' ),
			'repeaters' => array( 'slides' => array( 'target' => 'slides', 'keep' => array( 'type', 'image', 'image_link_to_type', 'image_link_to', 'video' ) ) ),
			'classes'   => array( '.elementor-carousel-image' => '.digi-media-carousel__image' ),
		);
	}
	/** Un Expo de Prime Slider sólo con imágenes: F8 lo lleva al Carrusel multimedia de PRO (familia H). */
	function expo_doc(): int {
		$rows = array(
			array( '_id' => 'x1', 'expo_image' => array( 'id' => 11, 'url' => 'https://sitio.test/wp-content/uploads/a.jpg' ), 'expo_title' => '' ),
			array( '_id' => 'x2', 'expo_image' => array( 'id' => 12, 'url' => 'https://sitio.test/wp-content/uploads/b.jpg' ), 'expo_title' => '' ),
		);
		return doc( array( box( 'container', array( w( 'prime-slider-expo-slider', array( 'expo_slides' => $rows, 'expo_loop' => 'yes' ), 'ps1' ) ), 'pc1' ) ), array( 'post_title' => 'Expo' ) );
	}
	// Sin mapa listo del Carrusel multimedia: F8 deja el de PRO (registrado, se ve igual) y la etapa pro no lo toca.
	fresh();
	f8_plugin( 'digitalisimo-elements/pro-elements.php', array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.172' ), 'site' );
	Registry::set( 'media-carousel', array( 'status' => 'draft' ) + chain_map() );
	$expo  = expo_doc();
	$state = migrate();
	$check( ! empty( $state['done'] ) && 1 === (int) $state['counts']['converted'] && 0 === (int) $state['counts']['pro'] && 'media-carousel' === find( tree( $expo ), 'ps1' )['widgetType'], 'Con el mapa en borrador, el Expo queda como Carrusel multimedia de PRO: ' . wp_json_encode( array( $state['counts']['converted'], $state['counts']['pro'], find( tree( $expo ), 'ps1' )['widgetType'] ) ) );
	// Con el mapa listo: Prime Slider → media-carousel (F8) → digitalisimo-media-carousel (pro), en una sola pasada.
	fresh();
	f8_plugin( 'digitalisimo-elements/pro-elements.php', array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.172' ), 'site' );
	Registry::set( 'media-carousel', chain_map() );
	$expo     = expo_doc();
	$original = (string) get_metadata( 'post', $expo, '_elementor_data', true );
	$state    = migrate();
	$el       = find( tree( $expo ), 'ps1' );
	$check( ! empty( $state['done'] ) && 1 === (int) $state['counts']['converted'] && 1 === (int) $state['counts']['pro'] && 1 === (int) $state['counts']['pro_elements'], 'Migrar convierte el Expo con F8 y después con la etapa pro en la misma pasada: ' . wp_json_encode( array( $state['counts']['converted'], $state['counts']['pro'], $state['counts']['pro_elements'] ) ) );
	$check( is_array( $el ) && 'digitalisimo-media-carousel' === $el['widgetType'] && 'pc1' === tree( $expo )[0]['id'], 'El Expo acaba en el Carrusel multimedia propio, con el mismo id y en su contenedor: ' . wp_json_encode( $el['widgetType'] ?? null ) );
	$check( 'media-carousel' === ( $el['settings']['_digitalisimo_pro'] ?? '' ) && 2 === count( (array) ( $el['settings']['slides'] ?? array() ) ) && 11 === (int) ( $el['settings']['slides'][0]['image']['id'] ?? 0 ) && 'yes' === ( $el['settings']['loop'] ?? '' ), 'Con la marca del traductor, sus dos imágenes y el bucle: ' . wp_json_encode( $el['settings'] ?? null ) );
	$codes = doc_codes( $expo );
	$check( in_array( 'pro.converted:media-carousel:', $codes, true ) && ! preg_grep( '/^pro\.unmapped:/', $codes ), 'El informe de F8 tiene pro.converted del Carrusel multimedia, sin claves sin declarar: ' . wp_json_encode( $codes ) );
	$check( false === strpos( (string) get_metadata( 'post', $expo, '_elementor_data', true ), 'prime-slider' ) && 0 === Runner::prime_slider_left( $expo )[0], 'No queda nada de Prime Slider en el documento.' );
	$backup = json_decode( (string) f8_meta( $expo, Migration::BACKUP ), true );
	$check( is_array( $backup ) && $original === (string) ( $backup['data'] ?? '' ), 'La copia guarda el Expo original (antes de F8), no el paso intermedio de PRO.' );
	Runner::revert( true );
	$check( $original === get_metadata( 'post', $expo, '_elementor_data', true ), '--revert devuelve el Expo de Prime Slider original.' );
	// El borrador real del frente M, si ya existe: la misma cadena sin claves sin declarar.
	Registry::reset();
	$real = Registry::map( 'media-carousel', true );
	if ( null !== $real ) {
		fresh();
		f8_plugin( 'digitalisimo-elements/pro-elements.php', array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.172' ), 'site' );
		Registry::set( 'media-carousel', array( 'status' => 'ready' ) + $real );
		$expo  = expo_doc();
		$state = migrate();
		$codes = doc_codes( $expo );
		$check( 'digitalisimo-media-carousel' === find( tree( $expo ), 'ps1' )['widgetType'] && ! preg_grep( '/^pro\.unmapped:/', $codes ), 'Con el borrador real de maps/media-carousel.php, el Expo llega al Carrusel multimedia propio sin claves sin declarar: ' . wp_json_encode( $codes ) );
	}

	// Máscaras: la imagen que enmascaraba Element Pack en cada widget propio de C1, la misma a la que el mapa lleva la
	// clase del widget de PRO (la máscara que F8 escribe para PRO sigue apuntando a su imagen tras la etapa pro).
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/legacy/class-masks.php';
	$masks = \Digitalisimo\Elements\Legacy\Masks::TARGETS;
	$check( '.digi-media-carousel__image' === ( $masks['digitalisimo-media-carousel'] ?? '' ) && '.digi-review__image img' === ( $masks['digitalisimo-reviews'] ?? '' ) && '.digi-testimonial__image img' === ( $masks['digitalisimo-testimonial-carousel'] ?? '' ), 'Masks::TARGETS tiene la imagen de los carruseles propios.' );
	$mask_settings = array( 'image_mask_popover' => 'yes', 'image_mask_shape' => 'custom', 'image_mask_shape_custom' => array( 'url' => 'https://sitio.test/wp-content/uploads/figura.svg' ) );
	$pro_css       = \Digitalisimo\Elements\Legacy\Masks::widget_css( 'media-carousel', $mask_settings );
	$own_css       = \Digitalisimo\Elements\Legacy\Masks::widget_css( 'digitalisimo-media-carousel', $mask_settings );
	$translated    = \Digitalisimo\Elements\Convert\Translator::translate( array( 'custom_css' => $pro_css ), chain_map() )[0]['custom_css'] ?? '';
	$check( '' !== $own_css && false !== strpos( $own_css, 'selector .digi-media-carousel__image' ) && $own_css === $translated, 'La máscara escrita para el Carrusel multimedia de PRO, traducida por el mapa, es la del propio: ' . $translated );
	// Cada par PRO ↔ propio de TARGETS coincide con las clases del mapa de C1 en cuanto existe (aunque sea en borrador).
	foreach ( array( 'media-carousel' => 'digitalisimo-media-carousel', 'reviews' => 'digitalisimo-reviews' ) as $source => $target ) {
		$map = Registry::map( $source, true );
		if ( null === $map || empty( $map['classes'] ) ) {
			continue;
		}
		$css = \Digitalisimo\Elements\Convert\Translator::translate( array( 'custom_css' => 'selector ' . $masks[ $source ] . '{}' ), array( 'status' => 'ready' ) + $map )[0]['custom_css'] ?? '';
		$check( 'selector ' . $masks[ $target ] . '{}' === $css, "La imagen enmascarada de {$source} pasa a la de {$target} con las clases de su mapa: {$css}" );
	}

	/* ================================================================ 7. La cadena F8 → PRO de C2 → widget propio (C2, frente D) */
	// Regla 6 del programa: lo que F8 lleva de Element Pack o Prime Slider a un widget de PRO de C2 acaba en el widget
	// propio en la misma pasada de Migrar, en cuanto el mapa de C2 está listo. Primero, qué orígenes de F8 llegan a cada
	// widget de PRO de C2 (un mapa nuevo de F8 hacia uno de ellos obliga a revisar esta lista y su caso).
	$c2      = array( 'animated-headline', 'call-to-action', 'code-highlight', 'countdown', 'flip-box', 'gallery', 'hotspot', 'lottie', 'price-list', 'price-table', 'progress-tracker', 'share-buttons', 'table-of-contents', 'video-playlist' );
	$legacy  = dirname( __DIR__ ) . '/digitalisimo-migration/legacy/';
	$sources = array();
	foreach ( array_merge( glob( $legacy . 'maps/*.php' ) ?: array(), glob( $legacy . 'prime-slider/maps/*.php' ) ?: array() ) as $file ) {
		if ( false !== strpos( basename( $file ), '.styles.' ) ) {
			continue;
		}
		preg_match_all( "/'(target|fallback)'\s*=>\s*'([a-z0-9-]+)'/", (string) file_get_contents( $file ), $m, PREG_SET_ORDER );
		foreach ( $m as $hit ) {
			if ( in_array( $hit[2], $c2, true ) ) {
				$sources[ $hit[2] ][] = basename( $file, '.php' );
			}
		}
	}
	ksort( $sources );
	$expected = array(
		'code-highlight'    => array( 'bdt-source-code' ),
		'countdown'         => array( 'bdt-countdown' ),
		'flip-box'          => array( 'bdt-flip-box' ),
		'hotspot'           => array( 'bdt-marker', 'prime-slider-woohotspot' ),
		'lottie'            => array( 'bdt-lottie-icon-box', 'bdt-lottie-image' ),
		'price-list'        => array( 'bdt-price-list' ),
		'price-table'       => array( 'bdt-price-table' ),
		'share-buttons'     => array( 'bdt-social-share' ),
		'table-of-contents' => array( 'bdt-table-of-content' ),
	);
	$check( $expected === $sources, 'Orígenes de F8 hacia los widgets de PRO de C2 (Prime Slider: el respaldo `hotspot` de Woo HotSpot; sin el slider anidado deja Puntos de interés de PRO): ' . wp_json_encode( $sources ) );

	/**
	 * Gestor de widgets de prueba con controles: F8 sólo deja en el widget de PRO las claves que son controles suyos
	 * (`Native::target_controls()`), como en WordPress. Los controles salen del archivo del widget de PRO (y del widget
	 * Imagen del núcleo para Puntos de interés, que lo extiende); cada grupo, con las subclaves de los grupos del núcleo.
	 */
	class C2_Manager {
		public $controls = array();
		public function get_widget_types( $name = null ) {
			if ( null === $name ) {
				return array();
			}
			if ( ! isset( $this->controls[ $name ] ) ) {
				return null;
			}
			return new class( $this->controls[ $name ] ) {
				private $list;
				public function __construct( array $list ) { $this->list = $list; }
				public function get_controls() { return $this->list; }
				public function has_widget_inner_wrapper() { return true; }
			};
		}
	}
	function c2_controls( string $type ): array {
		$elements = dirname( __DIR__ ) . '/digitalisimo-elements/';
		$files    = array( 'code-highlight' => 'code-highlight/widgets/code-highlight.php', 'countdown' => 'countdown/widgets/countdown.php', 'flip-box' => 'flip-box/widgets/flip-box.php', 'hotspot' => 'hotspot/widgets/hotspot.php', 'lottie' => 'lottie/widgets/lottie.php', 'price-list' => 'pricing/widgets/price-list.php', 'price-table' => 'pricing/widgets/price-table.php', 'share-buttons' => 'share-buttons/widgets/share-buttons.php', 'table-of-contents' => 'table-of-contents/widgets/table-of-contents.php' );
		$php      = (string) file_get_contents( $elements . 'modules/' . $files[ $type ] );
		if ( preg_match( '/\bextends\s+Widget_Image\b/', $php ) ) {
			$php .= (string) file_get_contents( $elements . 'elementor/includes/widgets/image.php' );
		}
		$subs  = array( 'typography', 'font_family', 'font_size', 'font_weight', 'text_transform', 'font_style', 'text_decoration', 'line_height', 'letter_spacing', 'word_spacing', 'border', 'width', 'color', 'box_shadow_type', 'box_shadow', 'box_shadow_position', 'text_shadow_type', 'text_shadow', 'text_stroke_type', 'text_stroke', 'stroke_color', 'background', 'color_stop', 'color_b', 'color_b_stop', 'gradient_type', 'gradient_angle', 'gradient_position', 'image', 'position', 'attachment', 'repeat', 'size', 'bg_width', 'xpos', 'ypos', 'css_filter', 'blur', 'brightness', 'contrast', 'saturate', 'hue', 'custom_dimension' );
		// Controles de `$this` (el widget) o de `$repeater` (los campos del repetidor), con sus grupos y dispositivos.
		$names = static function ( string $owner ) use ( $php, $subs ): array {
			preg_match_all( '/\$' . $owner . "->add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $php, $plain );
			preg_match_all( '/\$' . $owner . "->add_group_control\([^;]*?'name'\s*=>\s*'([a-z0-9_]+)'/s", $php, $groups );
			$list = $plain[1];
			foreach ( $groups[1] as $group ) {
				foreach ( $subs as $sub ) {
					$list[] = $group . '_' . $sub;
				}
			}
			$out = array();
			foreach ( array_unique( $list ) as $name ) {
				foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
					$out[ $name . $device ] = array( 'name' => $name . $device );
				}
			}
			return $out;
		};
		// Más los controles comunes de todo widget que no empiezan por `_` (CSS personalizado, visibilidad).
		$out = $names( 'this' ) + array( 'custom_css' => array( 'name' => 'custom_css' ), 'hide_desktop' => array( 'name' => 'hide_desktop' ), 'hide_tablet' => array( 'name' => 'hide_tablet' ), 'hide_mobile' => array( 'name' => 'hide_mobile' ) );
		preg_match_all( "/\\\$this->add_control\(\s*'([a-z0-9_]+)',\s*\[(?:(?!->add_).)*?Controls_Manager::REPEATER/s", $php, $repeaters );
		foreach ( $repeaters[1] as $name ) {
			$out[ $name ] = array( 'name' => $name, 'type' => 'repeater', 'fields' => array_values( $names( 'repeater' ) ) );
		}
		return $out;
	}
	// Después, cada origen de Element Pack por la misma pasada de Migrar: F8 lo lleva al widget de PRO y la etapa `pro`,
	// con el mapa de C2 (el del repositorio; si aún está en borrador, como listo), al propio, sin claves sin declarar.
	$chained = 0;
	$pending = array();
	foreach ( $expected as $pro_type => $origins ) {
		Registry::reset();
		$map = Registry::map( $pro_type, true );
		foreach ( $origins as $origin ) {
			if ( 0 === strpos( $origin, 'prime-slider-' ) ) {
				continue; // Woo HotSpot: con el slider anidado no queda ningún widget de PRO; su respaldo es el de bdt-marker.
			}
			fresh();
			f8_plugin( 'digitalisimo-elements/pro-elements.php', array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.174' ), 'site' );
			$gestor                      = new C2_Manager();
			$gestor->controls[ $pro_type ] = c2_controls( $pro_type );
			if ( null !== $map ) {
				$gestor->controls[ (string) $map['target'] ] = array();
				Registry::set( $pro_type, array( 'status' => 'ready' ) + $map );
			}
			\Elementor\Plugin::$instance = (object) array( 'widgets_manager' => $gestor );
			Pro::reset();
			$doc   = doc( array( box( 'container', array( w( $origin, array(), 'ep1' ) ), 'ec1' ) ), array( 'post_title' => $origin ) );
			$state = migrate();
			$el    = find( tree( $doc ), 'ep1' );
			$codes = doc_codes( $doc );
			if ( null === $map ) {
				$pending[] = $pro_type;
				$check( ! empty( $state['done'] ) && is_array( $el ) && $pro_type === $el['widgetType'], "Sin mapa de C2, {$origin} queda en {$pro_type} de PRO: " . wp_json_encode( $el['widgetType'] ?? null ) );
				continue;
			}
			$check( ! empty( $state['done'] ) && 1 === (int) $state['counts']['converted'] && 1 === (int) $state['counts']['pro'], "Migrar convierte {$origin} con F8 y después con la etapa pro en la misma pasada: " . wp_json_encode( array( $state['counts']['converted'] ?? null, $state['counts']['pro'] ?? null ) ) );
			$check( is_array( $el ) && $map['target'] === $el['widgetType'] && $pro_type === ( $el['settings']['_digitalisimo_pro'] ?? '' ) && 'ec1' === tree( $doc )[0]['id'], "{$origin} acaba en {$map['target']} con el mismo id, en su contenedor: " . wp_json_encode( $el['widgetType'] ?? null ) );
			$check( in_array( "pro.converted:{$pro_type}:", $codes, true ) && ! preg_grep( '/^pro\.unmapped:/', $codes ), "{$origin} → {$pro_type} → {$map['target']}: sin claves sin declarar en el mapa de C2: " . wp_json_encode( preg_grep( '/^pro\./', $codes ) ) );
			$check( false === strpos( (string) get_metadata( 'post', $doc, '_elementor_data', true ), '"bdt-' ), "No queda nada de Element Pack en el documento de {$origin}." );
			++$chained;
		}
	}
	// La Cuenta regresiva, con ajustes reales de Element Pack: fecha, etiquetas, mensaje al terminar y la cuenta en bucle.
	fresh();
	f8_plugin( 'digitalisimo-elements/pro-elements.php', array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.174' ), 'site' );
	Registry::reset();
	$gestor                                     = new C2_Manager();
	$gestor->controls['countdown']              = c2_controls( 'countdown' );
	$gestor->controls['digitalisimo-countdown'] = array();
	\Elementor\Plugin::$instance                = (object) array( 'widgets_manager' => $gestor );
	Pro::reset();
	$ep = array(
		'due_date' => '2031-02-03 04:05', 'show_seconds' => '', 'label_days' => 'Jours', 'end_action_type' => 'message', 'end_message' => '¡Se acabó!',
		'number_background_color' => '#123456', 'columns' => '3', 'custom_css' => "selector .bdt-countdown-number{color:red}\nselector .elementor-countdown-digits{color:blue}",
	);
	$loop = array( 'loop_time' => 'yes', 'loop_hours' => '5', 'end_action_type' => 'url', 'end_redirect_link' => array( 'url' => 'https://destino.test/' ) );
	$doc   = doc( array( box( 'container', array( w( 'bdt-countdown', $ep, 'cd1' ), w( 'bdt-countdown', $loop, 'cd2' ) ), 'cc1' ) ), array( 'post_title' => 'Cuenta' ) );
	$orig  = (string) get_metadata( 'post', $doc, '_elementor_data', true );
	$state = migrate();
	$one   = find( tree( $doc ), 'cd1' );
	$two   = find( tree( $doc ), 'cd2' );
	$codes = doc_codes( $doc );
	$check( 'digitalisimo-countdown' === ( $one['widgetType'] ?? '' ) && 'digitalisimo-countdown' === ( $two['widgetType'] ?? '' ) && 1 === (int) $state['counts']['pro'] && 2 === (int) $state['counts']['pro_elements'], 'Las dos cuentas de Element Pack llegan a la Cuenta regresiva propia: ' . wp_json_encode( array( $one['widgetType'] ?? null, $two['widgetType'] ?? null, $state['counts'] ) ) );
	$s1 = (array) ( $one['settings'] ?? array() );
	$s2 = (array) ( $two['settings'] ?? array() );
	$check( 'due_date' === $s1['countdown_type'] && '2031-02-03 04:05' === $s1['due_date'] && '' === $s1['show_seconds'] && 'Jours' === $s1['label_days'] && array( 'message' ) === $s1['expire_actions'] && '¡Se acabó!' === $s1['message_after_expire'] && ! isset( $s1['columns'] ), 'Fecha, partes, etiquetas y mensaje al terminar pasan a la Cuenta regresiva; lo que sólo tenía Element Pack no llega: ' . wp_json_encode( $s1 ) );
	$check( 'evergreen' === $s2['countdown_type'] && 5 === (int) $s2['evergreen_counter_hours'] && array( 'redirect' ) === $s2['expire_actions'] && 'https://destino.test/' === ( $s2['expire_redirect_url']['url'] ?? '' ), 'La cuenta en bucle pasa a temporizador perpetuo de 5 h que redirige al terminar: ' . wp_json_encode( $s2 ) );
	// F8 deja el CSS personalizado tal cual (las `classes` de sus mapas no lo reescriben); la etapa pro pasa las clases de PRO.
	$check( "selector .bdt-countdown-number{color:red}\nselector .digi-countdown__digits{color:blue}" === (string) ( $s1['custom_css'] ?? '' ), 'El CSS personalizado llega con las clases de PRO pasadas a las propias (las de Element Pack, tal cual): ' . wp_json_encode( $s1['custom_css'] ?? null ) );
	$check( array( 'pro.converted:countdown:', 'pro.css:countdown:custom_css' ) === array_values( preg_grep( '/^pro\./', $codes ) ), 'Informe: la conversión y el CSS convertido, ningún pro.unmapped: ' . wp_json_encode( preg_grep( '/^pro\./', $codes ) ) );
	Runner::revert( true );
	$check( $orig === get_metadata( 'post', $doc, '_elementor_data', true ), '--revert devuelve las cuentas de Element Pack originales.' );
	manager( null );
	Registry::reset();
	$check( $chained + count( $pending ) === 10, 'Cadena F8 → C2 → propio comprobada en ' . $chained . ' orígenes' . ( $pending ? '; sin mapa de C2 todavía: ' . implode( ', ', $pending ) : '' ) . '.' );

	// Regla 6, C6 (docs/pro/c6.md): los formularios y el acceso que F8 lleva de Element Pack al Form y al Login de PRO
	// acaban en el Formulario y el Inicio de sesión propios en la misma pasada de Migrar, con las acciones (correo,
	// webhook, Mailchimp) y sus destinatarios intactos. Primero, qué orígenes de F8 llegan a cada uno.
	$c6      = array( 'form', 'login' );
	$sources = array();
	foreach ( array_merge( glob( $legacy . 'maps/*.php' ) ?: array(), glob( $legacy . 'prime-slider/maps/*.php' ) ?: array() ) as $file ) {
		if ( false !== strpos( basename( $file ), '.styles.' ) ) {
			continue;
		}
		preg_match_all( "/'(target|fallback)'\s*=>\s*'([a-z0-9-]+)'/", (string) file_get_contents( $file ), $m, PREG_SET_ORDER );
		foreach ( $m as $hit ) {
			if ( in_array( $hit[2], $c6, true ) ) {
				$sources[ $hit[2] ][] = basename( $file, '.php' );
			}
		}
	}
	ksort( $sources );
	$expected = array(
		'form'  => array( 'bdt-contact-form', 'bdt-mailchimp', 'bdt-webhook-form' ),
		'login' => array( 'bdt-user-login' ),
	);
	$check( $expected === $sources, 'Orígenes de F8 hacia el Form y el Login de PRO: ' . wp_json_encode( $sources ) );
	/**
	 * Controles del Form y del Login de PRO para el gestor de prueba: los de su archivo (con grupos y dispositivos, como
	 * `c2_controls()`) y, en el Form, los de las acciones de envío, que en WordPress registra cada acción en el widget (la
	 * lista `keep` del mapa de C6, que `elements-pro-form.php` comprueba contra el widget).
	 */
	function c6_controls( string $type, array $map ): array {
		$php   = (string) file_get_contents( dirname( __DIR__ ) . '/digitalisimo-elements/modules/forms/widgets/' . $type . '.php' );
		$subs  = array( 'typography', 'font_family', 'font_size', 'font_weight', 'text_transform', 'font_style', 'text_decoration', 'line_height', 'letter_spacing', 'word_spacing', 'border', 'width', 'color', 'box_shadow_type', 'box_shadow', 'box_shadow_position', 'background', 'color_b', 'gradient_type', 'gradient_angle', 'image', 'position', 'size' );
		preg_match_all( "/\\\$this->add_(?:responsive_)?control\(\s*'([a-z0-9_]+)'/", $php, $plain );
		preg_match_all( "/\\\$this->add_group_control\([^;]*?'name'\s*=>\s*'([a-z0-9_]+)'/s", $php, $groups );
		$list = array_merge( $plain[1], 'form' === $type ? (array) $map['keep'] : array() );
		foreach ( $groups[1] as $group ) {
			foreach ( $subs as $sub ) {
				$list[] = $group . '_' . $sub;
			}
		}
		$out = array( 'custom_css' => array( 'name' => 'custom_css' ) );
		foreach ( array_unique( $list ) as $name ) {
			foreach ( array( '', '_tablet', '_mobile' ) as $device ) {
				$out[ $name . $device ] = array( 'name' => $name . $device );
			}
		}
		if ( 'form' === $type ) {
			$fields = array();
			foreach ( (array) $map['repeaters']['form_fields']['keep'] as $field ) {
				$fields[] = array( 'name' => $field );
			}
			$out['form_fields'] = array( 'name' => 'form_fields', 'type' => 'form-fields-repeater', 'fields' => $fields );
		}
		return $out;
	}
	$c6_chained = 0;
	$settings   = array(
		'bdt-contact-form' => array( 'show_subject' => 'yes', 'show_message' => 'yes', 'redirect_after_submit' => 'yes', 'redirect_url' => array( 'url' => 'https://sitio.test/gracias/' ) ),
		'bdt-mailchimp'    => array(),
		'bdt-webhook-form' => array( 'webhook_url' => 'https://receptor.test/hook' ),
		'bdt-user-login'   => array(),
	);
	foreach ( $expected as $pro_type => $origins ) {
		foreach ( $origins as $origin ) {
			Registry::reset();
			$map = Registry::map( $pro_type, true );
			$check( is_array( $map ), "Mapa de C6 para {$pro_type}." );
			fresh();
			f8_plugin( 'digitalisimo-elements/pro-elements.php', array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.174' ), 'site' );
			update_option( 'element_pack_api_settings', array( 'mailchimp_api_key' => 'abc-us1', 'mailchimp_list_id' => 'L1', 'contact_form_email' => 'ventas@sitio.test' ) );
			$gestor                                      = new C2_Manager();
			$gestor->controls[ $pro_type ]               = c6_controls( $pro_type, $map );
			$gestor->controls[ (string) $map['target'] ] = array();
			Registry::set( $pro_type, array( 'status' => 'ready' ) + $map );
			\Elementor\Plugin::$instance = (object) array( 'widgets_manager' => $gestor );
			Pro::reset();
			$doc   = doc( array( box( 'container', array( w( $origin, $settings[ $origin ], 'ef1' ) ), 'efc' ) ), array( 'post_title' => $origin ) );
			$orig  = (string) get_metadata( 'post', $doc, '_elementor_data', true );
			$state = migrate();
			$el    = find( tree( $doc ), 'ef1' );
			$codes = doc_codes( $doc );
			$s     = (array) ( $el['settings'] ?? array() );
			$check( ! empty( $state['done'] ) && 1 === (int) $state['counts']['converted'] && 1 === (int) $state['counts']['pro'], "Migrar convierte {$origin} con F8 y después con la etapa pro en la misma pasada: " . wp_json_encode( $state['counts'] ?? null ) );
			$check( is_array( $el ) && $map['target'] === $el['widgetType'] && $pro_type === ( $s['_digitalisimo_pro'] ?? '' ) && 'efc' === tree( $doc )[0]['id'], "{$origin} acaba en {$map['target']} con el mismo id (el form_id de los envíos): " . wp_json_encode( $el['widgetType'] ?? null ) );
			$check( in_array( "pro.converted:{$pro_type}:", $codes, true ) && ! preg_grep( '/^pro\.unmapped:/', $codes ), "{$origin} → {$pro_type} → {$map['target']}: sin claves sin declarar en el mapa de C6: " . wp_json_encode( preg_grep( '/^pro\./', $codes ) ) );
			$check( false === strpos( (string) get_metadata( 'post', $doc, '_elementor_data', true ), '"bdt-' ), "No queda nada de Element Pack en el documento de {$origin}." );
			if ( 'bdt-contact-form' === $origin ) {
				$check( in_array( 'email', (array) $s['submit_actions'], true ) && in_array( 'redirect', (array) $s['submit_actions'], true ) && 'https://sitio.test/gracias/' === ( $s['redirect_to'] ?? '' ) && array( 'name', 'email', 'subject', 'message' ) === array_column( (array) $s['form_fields'], 'custom_id' ) && 'ventas@sitio.test' === ( $s['email_to'] ?? '' ), 'Contacto: campos, correo al mismo destinatario y redirección llegan al Formulario propio: ' . wp_json_encode( array( $s['submit_actions'] ?? null, $s['email_to'] ?? null, $s['redirect_to'] ?? null ) ) );
			} elseif ( 'bdt-mailchimp' === $origin ) {
				$check( in_array( 'mailchimp', (array) $s['submit_actions'], true ) && 'L1' === ( $s['mailchimp_list'] ?? '' ) && ! empty( $s['mailchimp_fields_map'] ), 'Mailchimp: la acción, la audiencia y el mapa de campos llegan al Formulario propio: ' . wp_json_encode( array( $s['submit_actions'] ?? null, $s['mailchimp_list'] ?? null ) ) );
			} elseif ( 'bdt-webhook-form' === $origin ) {
				$check( in_array( 'webhook', (array) $s['submit_actions'], true ) && 'https://receptor.test/hook' === ( $s['webhooks'] ?? '' ), 'Webhook: la acción y su URL llegan al Formulario propio: ' . wp_json_encode( array( $s['submit_actions'] ?? null, $s['webhooks'] ?? null ) ) );
			}
			Runner::revert( true );
			$check( $orig === get_metadata( 'post', $doc, '_elementor_data', true ), "--revert devuelve {$origin} de Element Pack." );
			++$c6_chained;
		}
	}
	manager( null );
	Registry::reset();
	$check( 4 === $c6_chained, 'Cadena F8 → C6 → propio comprobada en ' . $c6_chained . ' orígenes.' );

	f8_done( 'Widgets de PRO en DIGITALÍSIMO Migración', 100 );
}
