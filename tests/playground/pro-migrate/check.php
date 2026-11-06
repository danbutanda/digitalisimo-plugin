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
	foreach ( array( 'page', 'header', 'revision', 'carousels', 'content', 'navigation', 'nav_header', 'single', 'loops', 'forms', 'product', 'shop', 'cart', 'shop_header' ) as $key ) {
		$out['types'][ $key ] = $types( (int) ( $ids[ $key ] ?? 0 ) );
	}
	// C6: lo que importa de cada formulario de la página de formularios (acciones, destinatarios y origen de PRO).
	$fdata = json_decode( (string) get_post_meta( (int) ( $ids['forms'] ?? 0 ), '_elementor_data', true ), true );
	foreach ( (array) ( $fdata[0]['elements'] ?? array() ) as $e ) {
		$s = (array) ( $e['settings'] ?? array() );
		$out['forms'][ $e['id'] ] = array_intersect_key( $s, array_flip( array( 'form_name', 'submit_actions', 'email_to', 'webhooks', 'redirect_to', 'mailchimp_list', 'button_text', 'user_label', '_digitalisimo_pro' ) ) ) + array( 'fields' => array_column( (array) ( $s['form_fields'] ?? array() ), 'custom_id' ) );
	}
	$out['backup'] = (bool) get_post_meta( (int) $ids['page'], '_digitalisimo_ep_backup', true );
	// C5: plantillas de los bucles convertidos (la de PRO se conserva; la del Dynamic Grid, la copia Loop Item de F8).
	$ldata = json_decode( (string) get_post_meta( (int) ( $ids['loops'] ?? 0 ), '_elementor_data', true ), true );
	$out['loops'] = array( 'loop_item' => (int) ( $ids['loop_item'] ?? 0 ), 'card_copy' => (int) get_post_meta( (int) ( $ids['card'] ?? 0 ), '_digitalisimo_loop_item', true ) );
	foreach ( (array) ( $ldata[0]['elements'] ?? array() ) as $e ) {
		$out['loops']['settings'][ $e['id'] ] = array_intersect_key( (array) ( $e['settings'] ?? array() ), array_flip( array( 'template_id', 'selected_element', '_skin', '_digitalisimo_pro', 'posts_per_page', 'columns' ) ) );
	}
	// Hijos del carrusel anidado (C1): sus contenedores deben conservarse con su id.
	$cdata = json_decode( (string) get_post_meta( (int) ( $ids['carousels'] ?? 0 ), '_elementor_data', true ), true );
	$out['nested_children'] = array_map( static function ( $e ) { return $e['id'] . ':' . $e['elType']; }, (array) ( $cdata[0]['elements'][0]['elements'] ?? array() ) );
	// Hijos del Mega Menu y del Off-Canvas (C3): sus contenedores deben conservarse con su id.
	$ndata = json_decode( (string) get_post_meta( (int) ( $ids['navigation'] ?? 0 ), '_elementor_data', true ), true );
	foreach ( (array) ( $ndata[0]['elements'] ?? array() ) as $e ) {
		if ( in_array( $e['id'] ?? '', array( 'v2', 'v3' ), true ) ) {
			$out['nav_children'][ $e['id'] ] = array_map( static function ( $c ) { return $c['id'] . ':' . $c['elType']; }, (array) ( $e['elements'] ?? array() ) );
		}
	}
} catch ( \Throwable $e ) {
	$out['error'] = get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}
file_put_contents( '/wordpress/wp-content/probe/steps.jsonl', wp_json_encode( $out ) . "\n", FILE_APPEND );
