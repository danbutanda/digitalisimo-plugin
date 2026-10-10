<?php
/**
 * A/B de compatibilidad con Element Pack en WordPress Playground.
 *
 * Fase A (Element Pack activo) y fase B (sólo Elements) renderizan los mismos documentos con los
 * casos de tests/legacy/bdt-*.json en dos sitios de una red y guardan una firma de contenido:
 * textos visibles, enlaces e imágenes. Uso: ver tests/playground/README.md.
 */
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once WP_PLUGIN_DIR . '/digitalisimo-elements/modules/digitalisimo-legacy/class-migration.php';

$phase    = getenv( 'DIGI_PHASE' ) ?: 'A';
$out_dir  = '/wordpress/wp-content/digi-ab';
$fixtures = array();
foreach ( glob( '/wordpress/wp-content/digi-legacy-fixtures/bdt-*.json' ) as $file ) {
	$only = getenv( 'DIGI_ONLY' );
	$id   = basename( $file, '.json' );
	if ( $only && ! in_array( $id, explode( ',', $only ), true ) ) {
		continue;
	}
	$fixtures[ $id ] = json_decode( file_get_contents( $file ), true );
}

function digi_ab_signature( $html ) {
	$sig = array( 'text' => array(), 'links' => array(), 'images' => array() );
	if ( '' === trim( $html ) ) {
		return $sig;
	}
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	$xpath = new DOMXPath( $doc );
	foreach ( $xpath->query( '//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::svg) and not(ancestor::noscript) and not(ancestor::template)]' ) as $node ) {
		$text = trim( preg_replace( '/\s+/u', ' ', $node->nodeValue ) );
		if ( '' !== $text ) {
			$sig['text'][] = $text;
		}
	}
	foreach ( $xpath->query( '//a[@href]' ) as $a ) {
		$sig['links'][] = $a->getAttribute( 'href' ) . ( $a->getAttribute( 'target' ) ? ' →' . $a->getAttribute( 'target' ) : '' );
	}
	foreach ( $xpath->query( '//img' ) as $img ) {
		$src = $img->getAttribute( 'src' ) ?: $img->getAttribute( 'data-src' );
		$sig['images'][] = basename( (string) wp_parse_url( $src, PHP_URL_PATH ) ) . ' | alt=' . trim( $img->getAttribute( 'alt' ) );
	}
	return $sig;
}

$result = array( 'phase' => $phase, 'element_pack' => defined( 'BDTEP_VER' ), 'widgets' => array() );
foreach ( array( 1, 2 ) as $blog ) {
	switch_to_blog( $blog );
	update_option( 'elementor_element_cache_ttl', 'disable' );
	$registered = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
	foreach ( $fixtures as $widget => $cases ) {
		foreach ( $cases as $case => $settings ) {
			$expect_css = (array) ( $settings['_expect_css'] ?? array() );
			$known      = (array) ( $settings['_known'] ?? array() );
			unset( $settings['_expect_css'], $settings['_known'] );
			$slug = 'digi-ab-' . $widget . '-' . $case;
			$page = get_page_by_path( $slug );
			$id   = $page ? $page->ID : wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $slug, 'post_name' => $slug ) );
			if ( 'A' === $phase ) {
				$data = array( array( 'id' => 's' . substr( md5( $slug ), 0, 6 ), 'elType' => 'section', 'settings' => array(), 'elements' => array( array( 'id' => 'c' . substr( md5( $slug ), 0, 6 ), 'elType' => 'column', 'settings' => array( '_column_size' => 100 ), 'elements' => array(
					array( 'id' => 'w' . substr( md5( $slug ), 0, 6 ), 'elType' => 'widget', 'widgetType' => $widget, 'settings' => (object) $settings, 'elements' => array() ),
				) ) ) ) );
				update_post_meta( $id, '_elementor_edit_mode', 'builder' );
				update_post_meta( $id, '_elementor_template_type', 'wp-page' );
				update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
				update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			}
			$entry_extra = array();
			if ( 'C' === $phase ) {
				$entry_extra['migration'] = \Digitalisimo\Elements\Legacy\Migration::convert_document( $id );
			}
			if ( 'D' === $phase ) {
				$entry_extra['reverted'] = \Digitalisimo\Elements\Legacy\Migration::revert_document( $id );
				$entry_extra['data_md5'] = md5( (string) get_post_meta( $id, '_elementor_data', true ) );
			}
			if ( 'A' === $phase ) {
				$entry_extra['data_md5'] = md5( (string) get_post_meta( $id, '_elementor_data', true ) );
			}
			$GLOBALS['wp_query']     = new WP_Query( array( 'page_id' => $id ) );
			$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
			$GLOBALS['post']         = get_post( $id );
			setup_postdata( $GLOBALS['post'] );
			delete_post_meta( $id, '_elementor_element_cache' );
			$html  = \Elementor\Plugin::$instance->frontend->get_builder_content( $id, false );
			$entry = array( 'registered' => isset( $registered[ $widget ] ), 'signature' => digi_ab_signature( $html ), 'bytes' => strlen( $html ), 'known' => $known ) + $entry_extra;
			if ( in_array( $phase, array( 'B', 'C' ), true ) ) {
				$raw = \Elementor\Plugin::$instance->documents->get( $id, false )->get_elements_raw_data( null, true );
				$entry['editor_settings'] = array_keys( (array) ( $raw[0]['elements'][0]['elements'][0]['settings'] ?? array() ) );
				$entry['editor_widget']   = $raw[0]['elements'][0]['elements'][0]['widgetType'] ?? '';
				if ( $expect_css ) {
					$css = \Elementor\Core\Files\CSS\Post::create( $id );
					$css->update();
					$content = preg_replace( '/\s+/', '', (string) $css->get_content() );
					$entry['css_missing'] = array_values( array_filter( $expect_css, static function ( $fragment ) use ( $content ) {
						return false === strpos( $content, preg_replace( '/\s+/', '', $fragment ) );
					} ) );
				}
			}
			$result['widgets'][ $widget ][ $case ][ $blog ] = $entry;
			wp_reset_postdata();
		}
	}
	restore_current_blog();
}
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir );
}
file_put_contents( $out_dir . '/phase' . $phase . '.json', wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
