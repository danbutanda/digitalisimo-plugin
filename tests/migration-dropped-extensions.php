<?php
/**
 * Extensiones de Element Pack sin equivalente (DIGITALÍSIMO Migración 1.0.21, F8 · frente C).
 *
 * Desde F8 sólo quedan 10 descartes, aprobados por el propietario, y 18 extensiones se convierten
 * (`docs/migration-map.json → extensions` pasa de 12 a 30). `Dropped` las encuentra por documento (también en la copia
 * original y en la de los ajustes de la página, `_digitalisimo_ep_backup_page`), las anota en el informe de F8 con
 * `ext.dropped.*` y anota `ext.ambiguous` las claves sin prefijo sin ninguna señal de Element Pack. Un interruptor
 * atómico apagado (`value: false`) no cuenta. Las secciones de punta a punta y de red de 1.0.20 pasaron a
 * `tests/migration-end-to-end.php` (frente A). Con el WordPress mínimo de `tests/fixtures/f8-wp.php`.
 */
namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	f8_load_migration();
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/legacy/effects/class-dispatcher.php';
	require_once dirname( __DIR__ ) . '/digitalisimo-migration/legacy/class-dropped.php';

	use Digitalisimo\Elements\Legacy\Dropped;
	use Digitalisimo\Elements\Legacy\Effects\Dispatcher;
	use Digitalisimo\Elements\Legacy\Migration;
	use Digitalisimo\Elements\Legacy\Native;
	use Digitalisimo\Elements\Legacy\Report;

	function dx_widget( $type, array $settings, $id ) {
		return array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
	}

	function dx_short( array $items ) {
		return array_map( static function ( $item ) {
			return $item['ext'] . '|' . $item['key'] . '|' . $item['el'] . '|' . $item['id'];
		}, $items );
	}

	function dx_codes( array $report ) {
		$out = array();
		foreach ( (array) ( $report['docs'] ?? array() ) as $post_id => $doc ) {
			foreach ( (array) ( $doc['items'] ?? array() ) as $item ) {
				$out[] = $post_id . ':' . $item['code'] . ':' . ( $item['detail']['element'] ?? '' ) . ':' . ( $item['detail']['name'] ?? '' );
			}
		}
		return $out;
	}

	/* -------- La tabla: 10 descartes, una sola fuente con las 18 que se convierten -------- */

	$extensions = Dropped::extensions();
	f8_check( 10 === count( $extensions ), 'Quedan los 10 descartes aprobados por el propietario (eran 28): ' . count( $extensions ) );
	f8_check( array( 'background-image-parallax', 'particles', 'cursor-effects', 'ripple-effects', 'tile-scroll', 'hash-link', 'confetti-effects', 'sound-effects', 'content-protector', 'adblock-detector' ) === array_keys( $extensions ), 'Son los de la decisión 1 de F8.' );
	$keys = Dispatcher::keys();
	foreach ( $extensions as $ext => $spec ) {
		f8_check( 'dropped' === $keys[ $ext ]['treatment'] && $spec['keys'] === $keys[ $ext ]['switch'] && $spec['label'] === $keys[ $ext ]['label'], 'Leídos de effects/keys.php (una sola fuente): ' . $ext );
		f8_check( '' !== $spec['label'] && false === stripos( $spec['label'], 'element pack' ), 'Nombre visible propio: ' . $ext );
		foreach ( $spec['keys'] as $key ) {
			f8_check( ! preg_match( Native::ELEMENT_KEYS, $key ), 'Una clave que la conversión traslada no se da por perdida: ' . $key );
		}
	}
	f8_check( array( 'hash-link', 'content-protector', 'adblock-detector' ) === array_keys( array_filter( $extensions, static function ( $spec ) {
		return $spec['page'];
	} ) ) && array( 'particles' ) === array_keys( array_filter( $extensions, static function ( $spec ) {
		return $spec['bare'];
	} ) ), 'Tres de página y una sin prefijo (partículas).' );
	$map     = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/docs/migration-map.json' ), true );
	$treated = (array) ( $map['extensions'] ?? array() );
	f8_check( 30 === count( $treated ), 'docs/migration-map.json → extensions pasa de 12 a 30: ' . count( $treated ) );
	f8_check( ! array_intersect( array_keys( $treated ), array_keys( $extensions ) ), 'Ninguna extensión tratada se lista como descartada: ' . implode( ', ', array_intersect( array_keys( $treated ), array_keys( $extensions ) ) ) );
	f8_check( 40 === count( $treated ) + count( $extensions ) - 0 && array() === array_diff( array_keys( $keys ), array_keys( $treated ), array_keys( $extensions ) ), 'Las 28 de keys.php están en una de las dos listas (30 tratadas + 10 descartadas = 40 extensiones).' );
	foreach ( $keys as $slug => $spec ) {
		if ( 'dropped' === $spec['treatment'] ) {
			continue;
		}
		$entry = $treated[ $slug ] ?? array();
		f8_check( ( $entry['treatment'] ?? '' ) === $spec['treatment'] && ( $entry['target'] ?? '' ) === $spec['target'] && ( $entry['losses'] ?? null ) === array_values( (array) $spec['losses'] ) && 'MIGRAR' === ( $entry['decision'] ?? '' ), 'migration-map.json refleja keys.php: ' . $slug );
	}
	f8_check( 'ADAPTAR' === $map['entries']['bdt-mega-menu']['decision'] && 'adaptador publicado' === $map['entries']['bdt-mega-menu']['legacy_documents'] && ! array_key_exists( 'bdt-mega-menu', (array) include dirname( __DIR__ ) . '/digitalisimo-migration/legacy/unsupported.php' ), 'Mega Menu: adaptado (V.11) y fuera de unsupported.php (regenerado).' );
	f8_check( false === strpos( Dropped::INTRO, 'todavía no' ) && false !== strpos( Dropped::INTRO, 'copia original' ), 'El texto común ya no promete una conversión futura.' );

	/* -------- Detección pura, por elemento y en los ajustes de la página -------- */

	$doc = array(
		array( 'id' => 'c1', 'elType' => 'container', 'settings' => array(
			'section_particles_on'             => 'yes',
			'element_pack_cursor_effects_show' => 'yes',
			'section_sticky_on'                => 'yes',
			'element_pack_backdrop_filter'     => 'yes',
			'ep_widget_cf_confetti'            => '',
			'element_pack_tile_scroll_show'    => 'no',
		), 'elements' => array(
			dx_widget( 'heading', array( 'title' => 'Hola', 'element_pack_widget_tooltip' => 'yes', 'ep_threed_text_active' => 'yes', 'ep_sound_effects_active' => 'yes' ), 'h1' ),
			dx_widget( 'image', array( 'ep_background_image_parallax_on' => 'yes', 'ep_image_hover_effects_on' => 'yes' ), 'i1' ),
			dx_widget( 'button', array( 'ep_ripple_enable' => 'yes', 'ep_widget_cf_confetti' => 'yes' ), 'b1' ),
			array( 'id' => 'v4', 'elType' => 'widget', 'widgetType' => 'e-heading', 'settings' => array( 'ep_widget_cf_confetti' => array( '$$type' => 'boolean', 'value' => false ), 'ep_sound_effects_active' => array( '$$type' => 'boolean', 'value' => true ) ), 'elements' => array() ),
		) ),
	);
	$found = dx_short( Dropped::scan_elements( $doc ) );
	f8_check( array(
		'particles|section_particles_on|container|c1',
		'cursor-effects|element_pack_cursor_effects_show|container|c1',
		'sound-effects|ep_sound_effects_active|heading|h1',
		'background-image-parallax|ep_background_image_parallax_on|image|i1',
		'ripple-effects|ep_ripple_enable|button|b1',
		'confetti-effects|ep_widget_cf_confetti|button|b1',
		'sound-effects|ep_sound_effects_active|e-heading|v4',
	) === $found, 'Cada descarte usado, con su clave, su elemento y su ID, en el orden del documento; nada apagado, vacío, «no», atómico apagado ni convertible: ' . implode( ' ; ', $found ) );
	f8_check( ! in_array( 'confetti-effects|ep_widget_cf_confetti|e-heading|v4', $found, true ), 'Un interruptor atómico apagado ({"$$type":"boolean","value":false}) no cuenta como usado.' );
	$no_signal = dx_short( Dropped::scan_elements( array( array( 'id' => 'p1', 'elType' => 'section', 'settings' => array( 'section_particles_on' => 'yes' ), 'elements' => array() ) ), false ) );
	$own_signal = dx_short( Dropped::scan_elements( array( array( 'id' => 'p2', 'elType' => 'section', 'settings' => array( 'section_particles_on' => 'yes', 'ep_bg_expand_enable' => '' ), 'elements' => array() ) ), false ) );
	f8_check( array() === $no_signal && array( 'particles|section_particles_on|section|p2' ) === $own_signal, 'Sin señal de Element Pack, la clave sin prefijo de las partículas sólo cuenta si el elemento tiene otra con prefijo (C2-18).' );
	$page = Dropped::scan_page( array( 'ep_custom_footer_script' => '<script>x()</script>', 'ep_hash_link_enable' => 'yes', 'ep_grid_line_enable' => 'yes', 'ep_content_protector_enable' => '', 'ep_adblock_detector_enable' => 'yes', 'hide_title' => 'yes' ) );
	f8_check( array( 'hash-link|ep_hash_link_enable||', 'adblock-detector|ep_adblock_detector_enable||' ) === dx_short( $page ), 'Ajustes de la página: sólo los descartes (grid-line y el JavaScript propio ahora se convierten).' );
	$ambiguous = dx_short( Dropped::scan_ambiguous( $doc ) );
	f8_check( array() === $ambiguous, 'Un elemento con claves con prefijo no es ambiguo.' );
	$ambiguous = dx_short( Dropped::scan_ambiguous( array( array( 'id' => 'a1', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes', 'section_parallax_on' => 'yes', 'section_equal_height_on' => '', 'section_parallax_elements' => array( array( '_id' => 'x' ) ) ), 'elements' => array( dx_widget( 'image', array( 'hover_effects' => '2' ), 'a2' ) ) ) ) ) );
	f8_check( array( 'background-parallax|section_parallax_on|section|a1', 'image-parallax|section_parallax_elements|section|a1', 'section-sticky|section_sticky_on|section|a1' ) === array_values( array_intersect( array( 'background-parallax|section_parallax_on|section|a1', 'image-parallax|section_parallax_elements|section|a1', 'section-sticky|section_sticky_on|section|a1' ), $ambiguous ) ) && 3 === count( $ambiguous ), 'Interruptores sin prefijo de las que se convierten, sin señal en el elemento: ambiguos (las genéricas de image-hover, nunca): ' . implode( ' ; ', $ambiguous ) );

	/* -------- Documentos del sitio: datos, ajustes de página y sus copias -------- */

	f8_reset();
	Dispatcher::force_signal( null );
	$p10 = f8_post( array( 'ID' => 10, 'post_type' => 'page', 'post_title' => 'Página diez' ), array( '_elementor_data' => wp_json_encode( $doc ) ) );
	$p11 = f8_post( array( 'ID' => 11, 'post_type' => 'page' ), array( '_elementor_data' => '[]', '_elementor_page_settings' => array( 'ep_content_protector_enable' => 'yes' ) ) );
	// Ajustes de la página ya convertidos: sólo su copia (JSON codificado una vez, `data` como objeto).
	$p12 = f8_post( array( 'ID' => 12, 'post_type' => 'page' ), array( '_elementor_data' => '[]', '_elementor_page_settings' => array( 'hide_title' => 'yes' ), '_digitalisimo_ep_backup_page' => wp_json_encode( array( 'time' => 1, 'data' => array( 'ep_hash_link_enable' => 'yes', 'ep_grid_line_enable' => 'yes' ) ) ) ) );
	// Documento ya convertido: el descarte sólo queda en la copia original (JSON dentro de JSON).
	$p13 = f8_post( array( 'ID' => 13, 'post_type' => 'page' ), array( '_elementor_data' => wp_json_encode( array( dx_widget( 'button', array( 'text' => 'x' ), 'b9' ) ) ), '_digitalisimo_ep_backup' => wp_json_encode( array( 'time' => 1, 'data' => wp_json_encode( array( dx_widget( 'button', array( 'ep_ripple_enable' => 'yes' ), 'b9' ) ) ) ) ) ) );
	// Revisión: nunca se lista.
	$p14 = f8_post( array( 'ID' => 14, 'post_type' => 'revision', 'post_parent' => 10 ), array( '_elementor_data' => wp_json_encode( $doc ) ) );
	// Sólo una clave sin prefijo de las que se convierten, sin señal: se lista para ext.ambiguous.
	$p15 = f8_post( array( 'ID' => 15, 'post_type' => 'page' ), array( '_elementor_data' => wp_json_encode( array( array( 'id' => 'hd', 'elType' => 'section', 'settings' => array( 'section_sticky_on' => 'yes' ), 'elements' => array() ) ) ) ) );
	// Nada que ver.
	$p16 = f8_post( array( 'ID' => 16, 'post_type' => 'page' ), array( '_elementor_data' => wp_json_encode( array( dx_widget( 'heading', array( 'title' => 'nada' ), 'n1' ) ) ) ) );
	$GLOBALS['wpdb']->queries = array();
	$docs = Dropped::documents();
	f8_check( array( 10, 11, 12, 13, 15 ) === $docs, 'Documentos con descartes en los datos, en los ajustes de la página, en la copia de la página y en la copia original, y los ambiguos; ni revisiones ni lo demás: ' . implode( ', ', $docs ) );
	f8_check( array( 12, 13 ) === Dropped::documents( 11, 2 ) && array( 15 ) === Dropped::documents( 13 ), 'Por trozos: después de un ID y con límite.' );
	$sql = implode( "\n", array_map( static function ( $q ) {
		return is_array( $q ) ? (string) ( $q['sql'] ?? $q[0] ?? '' ) : (string) $q;
	}, (array) $GLOBALS['wpdb']->queries ) );
	f8_check( '' !== $sql && false === stripos( $sql, 'SELECT DISTINCT' ) && false !== strpos( $sql, 'EXISTS' ), 'Sin SELECT DISTINCT sobre tablas enteras.' );
	f8_check( array( 'hash-link|ep_hash_link_enable||' ) === dx_short( Dropped::scan_post( 12 ) ), 'La copia de los ajustes de la página se sigue leyendo tras convertirlos (C1-17).' );
	f8_check( array( 'ripple-effects|ep_ripple_enable|button|b9' ) === dx_short( Dropped::scan_post( 13 ) ), 'La copia original del documento, también.' );
	f8_check( 7 === count( Dropped::scan_post( 10 ) ) && array( 'content-protector|ep_content_protector_enable||' ) === dx_short( Dropped::scan_post( 11 ) ), 'Y los datos y ajustes de hoy (el documento 10 tiene señal: sus claves con prefijo).' );
	// Un marcador de K (`digitalisimo-unconverted`) con un widget de Element Pack dentro: su carga se lee.
	$marker = array( 'id' => 'mk1', 'elType' => 'widget', 'widgetType' => 'digitalisimo-unconverted', 'settings' => array( 'digitalisimo_unconverted' => array( 'v' => 1, 'type' => 'widget/bdt-accordion', 'reason' => 'unknown', 'source' => 'kit', 'element' => dx_widget( 'bdt-accordion', array( 'ep_widget_cf_confetti' => 'yes' ), 'in1' ) ) ), 'elements' => array() );
	f8_post( array( 'ID' => 17, 'post_type' => 'page' ), array( '_elementor_data' => wp_json_encode( array( $marker ) ) ) );
	f8_check( in_array( 17, Dropped::documents(), true ) && array( 'confetti-effects|ep_widget_cf_confetti|bdt-accordion|in1' ) === dx_short( Dropped::scan_post( 17 ) ), 'Un marcador de K con un descarte dentro se encuentra y se informa con el widget original (V.12).' );

	/* -------- Informe de F8: ext.dropped.* y ext.ambiguous -------- */

	Report::begin( 'analysis' );
	$total = 0;
	foreach ( $docs as $post_id ) {
		$total += Dropped::report( $post_id );
	}
	$report = Report::take();
	$codes  = dx_codes( $report );
	f8_check( 11 === $total && in_array( '10:ext.dropped.particles:container #c1:section_particles_on', $codes, true ) && in_array( '11:ext.dropped.content_protector:Ajustes de la página:ep_content_protector_enable', $codes, true ) && in_array( '12:ext.dropped.hash_link:Ajustes de la página:ep_hash_link_enable', $codes, true ) && in_array( '13:ext.dropped.ripple_effects:button #b9:ep_ripple_enable', $codes, true ), 'Cada descarte va al informe con su código, su elemento y su clave: ' . implode( ' ; ', $codes ) );
	f8_check( in_array( '15:ext.ambiguous:section #hd:section_sticky_on', $codes, true ) && ! preg_grep( '/^10:ext\.ambiguous/', $codes ), 'Sin señal de Element Pack, la clave sin prefijo se anota como ambigua (y no se toca); con señal, no.' );
	$catalog = Report::catalog();
	foreach ( $report['docs'] as $doc_items ) {
		foreach ( $doc_items['items'] as $item ) {
			f8_check( isset( $catalog[ $item['code'] ] ) && in_array( $catalog[ $item['code'] ]['section'], array( 'dropped' ), true ), 'Código del catálogo y en «Sin equivalente» (información): ' . $item['code'] );
		}
	}
	f8_check( ! Report::pending( $report ), 'Los descartes y los ambiguos son información: no dejan el sitio con asuntos pendientes.' );
	Report::begin( 'analysis' );
	Dropped::report( 10 );
	Dropped::report( 10 );
	f8_check( 7 === count( Report::take()['docs'][10]['items'] ), 'Anotar dos veces el mismo documento no cuenta dos veces (firma = código + detalle).' );
	f8_plugin( 'bdthemes-element-pack/bdthemes-element-pack.php', array( 'Name' => 'Element Pack Pro', 'Version' => '10.0.1' ) );
	Dispatcher::force_signal( null );
	f8_check( array() === array_filter( Dropped::report_items( 15 ), static function ( $item ) {
		return 'ext.ambiguous' === $item[0];
	} ), 'Con Element Pack instalado, nada es ambiguo.' );

	/* -------- Conversión real: los descartes se retiran y siguen en el informe por la copia -------- */

	f8_reset();
	Dispatcher::force_signal( true );
	list( $converted ) = Migration::convert_data( $doc, 10 );
	// El elemento v4 no se toca (F8-D26): se comprueba aparte y el resto no debe conservar ninguna clave de Element Pack.
	$v4    = array_values( array_filter( $converted[0]['elements'], static function ( $el ) { return 'v4' === ( $el['id'] ?? '' ); } ) )[0] ?? array();
	$plain = $converted;
	$plain[0]['elements'] = array_values( array_filter( $plain[0]['elements'], static function ( $el ) { return 'v4' !== ( $el['id'] ?? '' ); } ) );
	$after = wp_json_encode( $plain );
	f8_check( ! preg_match( '/"(ep_|element_pack_|bdt_)/', $after ) && false === strpos( $after, 'section_particles_on' ) && false === strpos( $after, 'section_sticky_on' ), 'La conversión retira las claves de los descartes (también la sin prefijo de las partículas) y convierte las demás.' );
	f8_check( isset( $v4['settings']['ep_sound_effects_active'] ), 'Un elemento v4 conserva sus claves de Element Pack: se informa y no se toca (F8-D26).' );
	f8_check( 'top' === ( $converted[0]['settings']['sticky'] ?? '' ) && 'yes' === ( $converted[0]['elements'][0]['settings']['digitalisimo_tooltip'] ?? '' ), 'Lo que tiene equivalente se convierte (fijo, información emergente).' );
	$atomic = Dispatcher::element( $doc[0]['elements'][3], 10 );
	f8_check( $doc[0]['elements'][3] === $atomic, 'Las extensiones no tocan un elemento atómico (se informa con ext.v4).' );
	f8_check( array() === array_values( array_diff( dx_short( Dropped::scan_elements( $converted ) ), array( 'sound-effects|ep_sound_effects_active|e-heading|v4' ) ) ), 'Tras convertir no queda ningún descarte a la vista (salvo, si Native lo deja, el elemento atómico): lo demás se lee de la copia.' );
	f8_post( array( 'ID' => 20, 'post_type' => 'page' ), array( '_elementor_data' => wp_json_encode( $converted ), '_digitalisimo_ep_backup' => wp_json_encode( array( 'time' => 1, 'data' => wp_json_encode( $doc ) ) ) ) );
	f8_check( 7 === count( Dropped::scan_post( 20 ) ), 'Volver a pulsar Migrar no borra nada del informe: la copia original sigue diciendo lo que había.' );
	f8_check( array() === Dropped::uncovered( 20 ), 'Nada fuera de la copia.' );
	f8_post( array( 'ID' => 21, 'post_type' => 'page' ), array(
		'_digitalisimo_ep_backup' => wp_json_encode( array( 'time' => 1, 'data' => wp_json_encode( array( dx_widget( 'heading', array( 'ep_sound_effects_active' => 'yes' ), 'h9' ) ) ) ) ),
		'_elementor_data'         => wp_json_encode( array( dx_widget( 'heading', array( 'ep_sound_effects_active' => 'yes' ), 'h9' ), dx_widget( 'heading', array( 'ep_widget_cf_confetti' => 'yes' ), 'n9' ) ) ),
	) );
	f8_check( array( 'confetti-effects|ep_widget_cf_confetti|heading|n9' ) === dx_short( Dropped::uncovered( 21 ) ), 'Con una copia de una conversión anterior, sólo lo añadido después: ' . implode( ', ', dx_short( Dropped::uncovered( 21 ) ) ) );

	/* -------- Conjuntos: sin repetir, con topes por documento y por informe -------- */

	$set = Dropped::add( Dropped::none(), 10, Dropped::scan_elements( $doc ) );
	$set = Dropped::add( $set, 10, Dropped::scan_elements( $converted ) );
	f8_check( 7 === count( $set['docs'][10]['items'] ) && 0 === $set['docs'][10]['more'], 'Lo anotado antes y lo que queda después se unen sin repetir.' );
	$many = array();
	for ( $i = 0; $i < Dropped::MAX_ITEMS + 3; $i++ ) {
		$many[] = array( 'id' => 't' . $i, 'elType' => 'section', 'settings' => array( 'section_particles_on' => 'yes' ), 'elements' => array() );
	}
	$big = Dropped::add( Dropped::none(), 5, Dropped::scan_elements( $many ) );
	f8_check( Dropped::MAX_ITEMS === count( $big['docs'][5]['items'] ) && 3 === $big['docs'][5]['more'] && array( 'documents' => 1, 'settings' => Dropped::MAX_ITEMS + 3 ) === Dropped::totals( $big ), 'Un documento lista hasta el tope y cuenta el resto.' );
	$capped = Dropped::none();
	for ( $i = 1; $i <= Dropped::MAX_DOCUMENTS + 2; $i++ ) {
		$capped = Dropped::add( $capped, $i, $page );
	}
	f8_check( Dropped::MAX_DOCUMENTS === count( $capped['docs'] ) && 2 === $capped['omitted'] && Dropped::MAX_DOCUMENTS + 2 === Dropped::totals( $capped )['documents'], 'El informe lista hasta el tope de documentos y cuenta los demás.' );
	$merged = Dropped::merge( $big, Dropped::add( Dropped::none(), 5, Dropped::scan_elements( array_slice( $many, 0, 2 ) ) ) );
	f8_check( Dropped::MAX_ITEMS === count( $merged['docs'][5]['items'] ) && 3 === $merged['docs'][5]['more'], 'Unir dos pasadas no cuenta dos veces lo mismo.' );
	f8_check( 3 === Dropped::add( $big, 5, Dropped::scan_elements( $many ) )['docs'][5]['more'], 'Reintentar el mismo documento (una petición cortada) no suma otra vez lo que no cupo.' );
	$cursor = array( 'id' => 'cx', 'elType' => 'container', 'settings' => array( 'element_pack_cursor_effects_show' => 'yes' ), 'elements' => array() );
	$full   = Dropped::add( Dropped::none(), 6, Dropped::scan_elements( array_merge( array_slice( array_merge( $many, $many ), 0, 14 ), array( $cursor ) ) ) );
	$full   = Dropped::merge( $full, Dropped::add( Dropped::none(), 6, Dropped::scan_elements( array( $cursor ) ) ) );
	f8_check( 15 === Dropped::totals( $full )['settings'], 'Lo que no cupo en la lista y vuelve a aparecer al unir no se cuenta dos veces: ' . Dropped::totals( $full )['settings'] );
	f8_check( 'efectos heredados sin equivalente: 15 en 1 documentos' === Dropped::summary( $full ) && '' === Dropped::summary( Dropped::none() ), 'Resumen de una línea para la red y la terminal.' );

	/* -------- El Addon y Prime Slider ya no son «plugins que no se desactivan» -------- */

	f8_reset();
	f8_plugin( 'bdthemes-prime-slider/bdthemes-prime-slider.php', array( 'Name' => 'Prime Slider Pro', 'Version' => '4.7.0' ), 'site' );
	f8_check( array() === Dropped::kept() && ! Dropped::kept_pending( array( 'site' => true ) ) && ! Dropped::kept_hidden( array( 'elements' => 3 ) ), 'kept() queda vacío: Migrar retira y convierte la familia (frente A y E).' );
	f8_check( array( 'pending' => array(), 'info' => array(), 'idle' => array() ) === Dropped::kept_messages( array( array( 'label' => 'Prime Slider', 'slug' => 'prime-slider', 'site' => true ) ) ) && '' === Dropped::kept_notice( array( 'Prime Slider (activo)' ), 'Fin.' ) && '' === Dropped::idle_message( array( 'x' ) ), 'Sin avisos de «todavía no».' );
	f8_check( 'Sin equivalente' === Report::section_labels()['dropped'] && false !== strpos( Dropped::render( Dropped::describe( Dropped::add( Dropped::none(), 10, array( array( 'ext' => 'particles', 'key' => 'section_particles_on', 'el' => '<b>x</b>', 'id' => '"><script>' ) ) ) ), 0, Dropped::INTRO ), '&lt;b&gt;x&lt;/b&gt;' ), 'La tabla anterior sigue escapando (informes guardados por 1.0.20).' );
	$GLOBALS['wpdb']->queries = array();
	f8_post( array( 'ID' => 30, 'post_type' => 'page' ), array( '_elementor_data' => wp_json_encode( array( dx_widget( 'prime-slider-general', array(), 'ps' ) ) ) ) );
	$slider = Dropped::prime_slider();
	$sql    = implode( "\n", array_map( static function ( $q ) {
		return is_array( $q ) ? (string) ( $q['sql'] ?? $q[0] ?? '' ) : (string) $q;
	}, (array) $GLOBALS['wpdb']->queries ) );
	f8_check( 1 === $slider['elements'] && false === stripos( $sql, 'DISTINCT' ), 'El recuento de Prime Slider, también sin SELECT DISTINCT.' );

	f8_done( 'DIGITALÍSIMO Migración: extensiones de Element Pack sin equivalente (10 descartes) informadas por documento', 60 );
}
