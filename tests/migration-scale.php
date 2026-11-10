<?php
/**
 * F8 · Escala (V.3.5, C2-17): 5 000 documentos sintéticos con un presupuesto de 2 s por petición. La lista de IDs de cada
 * etapa se calcula una vez (la consulta LIKE corre una vez por etapa) y se guarda en trozos sin carga automática;
 * ninguna petición decodifica más documentos de los que procesa; el informe de F8 vive en su opción, con topes, y la
 * tarea no crece con el número de documentos; el análisis previo avanza por trozos dentro de su plazo.
 */
namespace Digitalisimo\Elements {
	final class Widget_Registry {}
}
namespace {
	/** Lecturas y escrituras de `_elementor_data` por petición (el resto, como el doble común). */
	function get_metadata( $type, $id, $key = '', $single = false ) {
		$store = 'user' === $type ? ( $GLOBALS['wp']['user_meta'][ (int) $id ] ?? array() ) : ( $GLOBALS['wp']['meta'][ $GLOBALS['wp']['blog'] ][ (int) $id ] ?? array() );
		if ( '_elementor_data' === $key ) {
			$GLOBALS['scale']['reads'][ (int) $id ] = true;
			$GLOBALS['scale']['total'][ (int) $id ] = (int) ( $GLOBALS['scale']['total'][ (int) $id ] ?? 0 ) + 1;
			// Lecturas fuera de las etapas que recorren documentos (finish, platform…): las que no procesan nada.
			$job   = $GLOBALS['wp']['options'][ $GLOBALS['wp']['blog'] ]['digitalisimo_migration_job'] ?? array();
			$stage = (string) ( $job['stage'] ?? '' );
			if ( '' !== $stage && ! in_array( $stage, \Digitalisimo\Elements\Legacy\Runner::LOOP_STAGES, true ) ) {
				$GLOBALS['scale']['outside'][ $stage ] = (int) ( $GLOBALS['scale']['outside'][ $stage ] ?? 0 ) + 1;
			}
		}
		if ( '' === $key ) {
			return array_map( static function ( $v ) { return array( $v ); }, $store );
		}
		if ( ! array_key_exists( $key, $store ) ) {
			return $single ? '' : array();
		}
		return $single ? $store[ $key ] : array( $store[ $key ] );
	}
	function update_metadata( $type, $id, $key, $value, $prev = '' ) {
		if ( '_elementor_data' === $key ) {
			$GLOBALS['scale']['writes'][ (int) $id ] = true;
		}
		$value = wp_unslash( $value );
		if ( 'user' === $type ) {
			$GLOBALS['wp']['user_meta'][ (int) $id ][ $key ] = $value;
		} else {
			$GLOBALS['wp']['meta'][ $GLOBALS['wp']['blog'] ][ (int) $id ][ $key ] = $value;
		}
		return true;
	}
	require __DIR__ . '/fixtures/f8-wp.php';
	f8_load_migration();
	use Digitalisimo\Elements\Legacy\Aliases;
	use Digitalisimo\Elements\Legacy\Migration;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Runner;

	const DOCS   = 5000;
	const BUDGET = 2;

	/** $wpdb que cuenta las búsquedas LIKE de documentos, por etapa. */
	/**
	 * $wpdb que cuenta las búsquedas LIKE de documentos, por etapa, y mide su tiempo: la búsqueda en memoria del doble
	 * es mucho más lenta que la de MySQL, así que el presupuesto se mide sin ella.
	 */
	final class Scale_Wpdb extends F8_Wpdb {
		public $likes = array();
		public $time  = 0.0;
		public function get_col( $query, $x = 0 ) {
			$sql = (string) strstr( (string) $query . "\0", "\0", true );
			if ( preg_match( "/meta_key = '_elementor_data' AND p\\.post_type (<>|=) 'revision'/", $sql, $m ) ) {
				$stage = (string) ( Runner::state()['stage'] ?? 'none' );
				$key   = $stage . ( '=' === $m[1] ? ':revisiones' : '' );
				$this->likes[ $key ] = (int) ( $this->likes[ $key ] ?? 0 ) + 1;
			}
			$t0 = microtime( true );
			try {
				return parent::get_col( $query, $x );
			} finally {
				$this->time += microtime( true ) - $t0;
			}
		}
		public function get_results( $query, $output = 'OBJECT' ) {
			$t0 = microtime( true );
			try {
				return parent::get_results( $query, $output );
			} finally {
				$this->time += microtime( true ) - $t0;
			}
		}
	}
	f8_reset();
	$GLOBALS['wpdb'] = new Scale_Wpdb();
	Aliases::set_map( array() );
	$GLOBALS['scale'] = array( 'reads' => array(), 'writes' => array(), 'total' => array(), 'outside' => array() );
	$json = wp_json_encode( array( array( 'id' => 'c', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'h', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => array( 'title' => 'Hola', 'ep_notation_active' => 'yes', 'ep_notation_type' => 'underline' ), 'elements' => array() ) ) ) ) );
	for ( $i = 1; $i <= DOCS; $i++ ) {
		f8_post( array( 'ID' => 10000 + $i, 'post_type' => 'page', 'post_title' => 'Página ' . $i ), array( '_elementor_data' => $json ) );
	}
	f8_check( DOCS === count( $GLOBALS['wp']['posts'][1] ), '5 000 documentos sintéticos con contenido de Element Pack.' );

	/* ================================================================ Migrar en peticiones de 2 s */
	$requests      = 0;
	$slowest       = 0.0;
	$slowest_stage = '';
	$excess   = array();
	$job_max  = 0;
	$state    = array();
	for ( $i = 0; $i < 400; $i++ ) {
		f8_new_request();
		$GLOBALS['scale']['reads']  = array();
		$GLOBALS['scale']['writes'] = array();
		$t0    = microtime( true );
		$db0   = $GLOBALS['wpdb']->time;
		$stage = (string) ( Runner::state()['stage'] ?? 'prepare' );
		$state = Runner::site_step( 0 === $i, BUDGET );
		$took  = microtime( true ) - $t0 - ( $GLOBALS['wpdb']->time - $db0 );
		++$requests;
		if ( $took > $slowest ) {
			$slowest       = $took;
			$slowest_stage = $stage;
		}
		$job_max = max( $job_max, strlen( serialize( get_option( Runner::JOB, array() ) ) ) );
		// Una petición sólo decodifica los documentos que procesan sus etapas: fuera de ellas (cierre, informe final,
		// pasos de plataforma) no lee ninguno, sea cual sea el número de documentos.
		foreach ( (array) ( $GLOBALS['scale']['outside'] ?? array() ) as $where => $count ) {
			if ( $count > 2 ) {
				$excess[] = sprintf( 'petición %d (%s): %d documentos leídos sin procesarlos', $requests, $where, $count );
			}
		}
		$GLOBALS['scale']['outside'] = array();
		if ( ! empty( $state['done'] ) ) {
			break;
		}
	}
	f8_check( ! empty( $state['done'] ), 'Migrar termina con 5 000 documentos: ' . $requests . ' peticiones.' );
	f8_check( DOCS === (int) $state['counts']['converted'], 'Convierte los 5 000: ' . (int) $state['counts']['converted'] );
	// En una máquina rápida los 5 000 documentos sintéticos caben en un solo presupuesto: entonces basta con que ninguna
	// petición lo haya agotado. Si alguna lo agotó, la conversión tuvo que repartirse en varias peticiones.
	f8_check( $requests > 2 || $slowest < BUDGET, sprintf( 'En varias peticiones, o todo dentro del presupuesto (el presupuesto se respeta): %d peticiones, la más lenta %.2f s.', $requests, $slowest ) );
	f8_check( $slowest < BUDGET + 1.5, sprintf( 'Ninguna petición se pasa de su presupuesto más un documento y el cierre (sin la búsqueda en memoria del doble): %.2f s en %s.', $slowest, $slowest_stage ) );
	f8_check( ! $excess, 'Ninguna petición decodifica más documentos de los que procesa: ' . implode( '; ', array_slice( $excess, 0, 3 ) ) );
	f8_check( max( $GLOBALS['scale']['total'] ) <= 6, 'Cada documento se lee unas pocas veces en toda la migración (convertir, informe): ' . max( $GLOBALS['scale']['total'] ) );
	f8_check( 1 === (int) ( $GLOBALS['wpdb']->likes['convert'] ?? 0 ), 'La búsqueda de documentos corre una vez en la etapa de conversión: ' . wp_json_encode( $GLOBALS['wpdb']->likes ) );
	f8_check( 1 === (int) ( $GLOBALS['wpdb']->likes['convert:revisiones'] ?? 0 ), 'Y la de revisiones, una vez.' );
	f8_check( $job_max < 65536, 'La tarea guardada no crece con los documentos: ' . $job_max . ' bytes.' );
	f8_check( ! array_filter( array_keys( $GLOBALS['wp']['options'][1] ), static function ( $k ) { return 0 === strpos( $k, Runner::IDS ); } ), 'Las listas de IDs se borran al cerrar cada etapa.' );
	$report = Report::stored();
	f8_check( count( $report['docs'] ) <= Report::MAX_DOCS, 'El informe de F8 lista como mucho 100 documentos.' );
	f8_check( false === f8_autoload( Report::OPTION ) && strlen( serialize( $report ) ) < 262144, 'Y vive en su opción, sin carga automática y acotado: ' . strlen( serialize( $report ) ) . ' bytes.' );
	$left = 0;
	foreach ( $GLOBALS['wp']['meta'][1] as $meta ) {
		$left += false !== strpos( (string) ( $meta['_elementor_data'] ?? '' ), '"ep_' ) ? 1 : 0;
	}
	f8_check( 0 === $left, 'No queda ningún documento con claves heredadas.' );

	/* ================================================================ La lista de la etapa: en trozos, sin carga automática */
	f8_reset();
	$GLOBALS['wpdb'] = new Scale_Wpdb();
	for ( $i = 1; $i <= 6001; $i++ ) {
		$GLOBALS['wp']['posts'][1][ $i ] = f8_post_defaults( array( 'ID' => $i ) );
		$GLOBALS['wp']['meta'][1][ $i ]  = array( '_elementor_data' => $json );
	}
	$GLOBALS['wp']['options'][1][ Runner::JOB ] = array_merge( Runner::fresh( true ), array( 'stage' => 'convert', 'platform_prepared' => true ) );
	f8_new_request();
	Runner::site_step( false, 0.5 );
	// Se mira lo que la etapa ESCRIBIÓ, no lo que queda: el presupuesto mínimo es 1 s y con PHP nativo los 6 001
	// documentos pueden convertirse en ese paso; entonces la etapa se cierra y borra sus listas (la comprobación de arriba).
	$writes = $GLOBALS['wp']['f8_last_write'][1] ?? array();
	$meta   = $writes[ Runner::IDS . 'convert' ]['value'] ?? array();
	f8_check( 2 === (int) ( $meta['chunks'] ?? 0 ) && 6001 === (int) ( $meta['total'] ?? 0 ), 'La lista de 6 001 IDs va en trozos de 5 000.' );
	f8_check( 5000 === count( (array) ( $writes[ Runner::IDS . 'convert_0' ]['value'] ?? array() ) ) && 1001 === count( (array) ( $writes[ Runner::IDS . 'convert_1' ]['value'] ?? array() ) ) && false === ( $writes[ Runner::IDS . 'convert_0' ]['autoload'] ?? null ) && false === ( $writes[ Runner::IDS . 'convert_1' ]['autoload'] ?? null ), 'Sin carga automática.' );

	/* ================================================================ Análisis previo por trozos */
	f8_reset();
	$GLOBALS['wpdb'] = new Scale_Wpdb();
	for ( $i = 1; $i <= 2000; $i++ ) {
		$GLOBALS['wp']['posts'][1][ $i ] = f8_post_defaults( array( 'ID' => $i ) );
		$GLOBALS['wp']['meta'][1][ $i ]  = array( '_elementor_data' => $json );
	}
	$calls = 0;
	$worst = 0.0;
	do {
		$t0     = microtime( true );
		$db0    = $GLOBALS['wpdb']->time;
		$result = Migration::analyze_step( 0.5 );
		$worst  = max( $worst, microtime( true ) - $t0 - ( $GLOBALS['wpdb']->time - $db0 ) );
		++$calls;
	} while ( empty( $result['done'] ) && $calls < 200 );
	f8_check( ! empty( $result['done'] ) && 2000 === $result['processed'] && $calls > 1, 'El análisis de 2 000 documentos avanza por trozos: ' . $calls . ' peticiones.' );
	f8_check( $worst < 1.5, sprintf( 'Cada trozo cabe en su plazo (sin la búsqueda en memoria): %.2f s.', $worst ) );
	f8_check( null === get_option( Runner::JOB, null ) && null === get_option( Report::OPTION, null ), 'Analizar no crea tarea ni informe.' );

	f8_done( 'DIGITALÍSIMO Migración: escala', 15 );
}
