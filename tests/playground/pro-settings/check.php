<?php
// Un paso de tests/playground/pro-settings.sh (probe/step.txt): `render` (lo que se pinta y lo guardado), `run` (Migrar
// hasta terminar) o `switch` (de la versión publicada a la del repositorio, y una página nueva sin migrar). Anota en
// probe/steps.jsonl.
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$step = trim( (string) file_get_contents( '/wordpress/wp-content/probe/step.txt' ) );
$ids  = (array) get_option( 'ps_ids' );
$out  = array( 'step' => $step, 'active' => array_values( (array) get_option( 'active_plugins' ) ) );
$saved = static function ( $post_id ) {
	$data  = json_decode( (string) get_post_meta( (int) $post_id, '_elementor_data', true ), true );
	$found = array();
	$walk  = static function ( $els ) use ( &$walk, &$found ) {
		foreach ( (array) $els as $e ) {
			if ( isset( $e['widgetType'] ) ) {
				$found[ $e['id'] ] = array( 'type' => $e['widgetType'], 'settings' => $e['settings'] ?? array() );
			}
			$walk( $e['elements'] ?? array() );
		}
	};
	$walk( $data );
	return $found;
};
try {
	if ( 'switch' === $step ) {
		// La lista de plugins activos sin cargar la versión nueva en esta petición (redeclararía las clases de la publicada):
		// la siguiente petición carga sólo la del repositorio, con las opciones que dejó la publicada (el mismo plugin).
		$active = array_values( array_diff( (array) get_option( 'active_plugins' ), array( 'digitalisimo-migration-publicado/digitalisimo-migration.php', 'digitalisimo-elements-publicado/pro-elements.php' ) ) );
		$active = array_merge( $active, array( 'digitalisimo-elements/pro-elements.php', 'digitalisimo-migration/digitalisimo-migration.php' ) );
		sort( $active );
		update_option( 'active_plugins', $active );
		// Una página que nunca se migró, con la condición encendida y los inertes: la convierte la versión nueva.
		$w    = static function ( $type, $id, array $settings ) {
			return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
		};
		$rules        = array( array( '_id' => 'u9', 'display_condition_key' => 'browser' ) );
		$ids['nueva'] = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Nueva', 'post_name' => 'ps-nueva' ) );
		update_post_meta( $ids['nueva'], '_elementor_edit_mode', 'builder' );
		update_post_meta( $ids['nueva'], '_elementor_data', wp_slash( wp_json_encode( array( array( 'id' => 'c3', 'elType' => 'container', 'settings' => array(), 'elements' => array(
			$w( 'blockquote', 'n1', array( 'blockquote_content' => 'Nueva visible', 'content_width' => 'boxed', 'display_condition_list' => $rules ) ),
			$w( 'blockquote', 'n2', array( 'blockquote_content' => 'Nueva oculta', 'display_condition_enable' => 'yes', 'display_condition_list' => $rules ) ),
		) ) ) ) ) );
		update_option( 'ps_ids', $ids );
	} elseif ( 'run' === $step ) {
		\Digitalisimo\Elements\Legacy\Runner::load();
		$state = array();
		for ( $i = 0; $i < 30; $i++ ) {
			$state = \Digitalisimo\Elements\Legacy\Runner::run( true, true );
			if ( ! empty( $state['done'] ) || ! empty( $state['pause'] ) || ! empty( $state['busy'] ) ) {
				break;
			}
		}
		$out['state']  = array( 'done' => ! empty( $state['done'] ), 'counts' => $state['counts'] ?? null );
		$out['report'] = wp_strip_all_tags( (string) \Digitalisimo\Elements\Legacy\Runner::render( $state ) );
		$stored        = \Digitalisimo\Elements\Legacy\Report::stored();
		$out['codes']  = array();
		foreach ( (array) ( $stored['docs'] ?? array() ) as $doc => $list ) {
			foreach ( (array) ( $list['items'] ?? array() ) as $item ) {
				$out['codes'][] = $doc . ':' . $item['code'] . ':' . ( $item['detail']['widget'] ?? '' ) . ':' . ( $item['detail']['name'] ?? '' );
			}
		}
		foreach ( (array) ( $stored['site']['items'] ?? array() ) as $item ) {
			$out['codes'][] = 'site:' . $item['code'];
		}
	}
	if ( in_array( $step, array( 'render', 'run' ), true ) ) {
		foreach ( array( 'pagina', 'nueva' ) as $key ) {
			if ( empty( $ids[ $key ] ) ) {
				continue;
			}
			$out['saved'][ $key ] = $saved( $ids[ $key ] );
			if ( 'render' === $step && did_action( 'elementor/loaded' ) ) {
				$html = (string) \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( (int) $ids[ $key ], true );
				preg_match_all( '/data-id="([a-z0-9]+)"[^>]*data-widget_type="([a-z0-9_.-]+)"/', $html, $m, PREG_SET_ORDER );
				$out['painted'][ $key ] = array();
				foreach ( $m as $hit ) {
					$out['painted'][ $key ][ $hit[1] ] = $hit[2];
				}
				$out['text'][ $key ] = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );
			}
		}
	}
} catch ( \Throwable $e ) {
	$out['error'] = get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine();
}
file_put_contents( '/wordpress/wp-content/probe/steps.jsonl', wp_json_encode( $out ) . "\n", FILE_APPEND );
