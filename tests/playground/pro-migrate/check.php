<?php
// Un paso de la prueba: `inventory`, `run` (Migrar hasta terminar o pausar) o `revert`. Anota en probe/steps.jsonl.
require '/wordpress/wp-load.php';
$step = (string) file_get_contents( '/wordpress/wp-content/probe/step.txt' );
$ids  = (array) get_option( 'pm_ids' );
$out  = array( 'step' => $step );
$types = static function ( $post_id ) {
	$data = json_decode( (string) get_post_meta( $post_id, '_elementor_data', true ), true );
	$found = array();
	$walk = static function ( $els ) use ( &$walk, &$found ) {
		foreach ( (array) $els as $e ) {
			if ( isset( $e['widgetType'] ) ) {
				$found[ $e['id'] ] = $e['widgetType'] . ( isset( $e['settings']['custom_css'] ) ? ' css=' . $e['settings']['custom_css'] : '' );
			}
			$walk( $e['elements'] ?? array() );
		}
	};
	$walk( $data );
	return $found;
};
try {
	\Digitalisimo\Elements\Legacy\Runner::load();
	if ( 'inventory' === $step ) {
		$out['inventory'] = \Digitalisimo\Elements\Legacy\Pro::inventory();
	} elseif ( 'run' === $step ) {
		\Digitalisimo\Elements\Legacy\Runner::load();
		$state = array();
		for ( $i = 0; $i < 30; $i++ ) {
			$state = \Digitalisimo\Elements\Legacy\Runner::run( true, true );
			if ( ! empty( $state['done'] ) || ! empty( $state['pause'] ) || ! empty( $state['busy'] ) ) {
				break;
			}
		}
		$out['state'] = array( 'done' => ! empty( $state['done'] ), 'pause' => ! empty( $state['pause'] ), 'counts' => $state['counts'] ?? null );
		$out['report'] = wp_strip_all_tags( (string) \Digitalisimo\Elements\Legacy\Runner::render( $state ) );
	} elseif ( 'revert' === $step ) {
		\Digitalisimo\Elements\Legacy\Runner::load();
		$out['revert'] = \Digitalisimo\Elements\Legacy\Runner::revert( false );
	}
	foreach ( array( 'page', 'header', 'revision', 'carousels', 'content', 'single' ) as $key ) {
		$out['types'][ $key ] = $types( (int) ( $ids[ $key ] ?? 0 ) );
	}
	$out['backup'] = (bool) get_post_meta( (int) $ids['page'], '_digitalisimo_ep_backup', true );
	// Hijos del carrusel anidado (C1): sus contenedores deben conservarse con su id.
	$cdata = json_decode( (string) get_post_meta( (int) ( $ids['carousels'] ?? 0 ), '_elementor_data', true ), true );
	$out['nested_children'] = array_map( static function ( $e ) { return $e['id'] . ':' . $e['elType']; }, (array) ( $cdata[0]['elements'][0]['elements'] ?? array() ) );
} catch ( \Throwable $e ) {
	$out['error'] = get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}
file_put_contents( '/wordpress/wp-content/probe/steps.jsonl', wp_json_encode( $out ) . "\n", FILE_APPEND );
