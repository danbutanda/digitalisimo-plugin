<?php
/**
 * F8 · Migrar de punta a punta con las clases reales de todos los frentes (V.16, frente A; el coordinador completa en
 * la oleada 2 lo que dependa de E, H e I). Incluye las secciones que salieron de `migration-dropped-extensions.php`
 * (`:263-405`) con el comportamiento de F8: el Addon y Prime Slider ya no son «de la familia sin desactivar» sino que se
 * retiran (el Addon con Element Pack, en una escritura; Prime Slider al final, sólo sin widgets suyos por convertir),
 * y los descartes se informan en el informe de F8. Más un sitio con Element Pack, el Addon y Prime Slider de punta a
 * punta, pulsar Migrar otra vez, la red con el Addon activado en toda la red, y `--revert`.
 *
 * Sin Elementor cargado (las conversiones usan sus valores guardados) y sin las constantes de Element Pack y Prime
 * Slider: cada petición corre «después» de desactivarlos, como la segunda petición real de la tarea.
 */
namespace Digitalisimo\Elements {
	/** Los widgets propios están cargados (la conversión lo exige). */
	final class Widget_Registry {}
}
namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	f8_load_migration();
	use Digitalisimo\Elements\Legacy\Aliases;
	use Digitalisimo\Elements\Legacy\Migration;
	use Digitalisimo\Elements\Legacy\Platform\Registry;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Retire;
	use Digitalisimo\Elements\Legacy\Runner;

	const ADDON = 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php';
	const EP    = 'bdthemes-element-pack/bdthemes-element-pack.php';
	const LITE  = 'bdthemes-element-pack-lite/bdthemes-element-pack-lite.php';
	const PSP   = 'bdthemes-prime-slider/bdthemes-prime-slider.php';
	const PSL   = 'bdthemes-prime-slider-lite/bdthemes-prime-slider.php';
	const ELE   = 'digitalisimo-elements/pro-elements.php';

	function e2e_reset( array $over = array() ) {
		f8_reset( $over );
		f8_wpdb();
		Runner::init();
		Registry::flush_cache();
		Aliases::set_map( array() );
		Report::reset_catalog();
		$GLOBALS['wp']['current_user'] = 1;
	}
	function e2e_family( $where = 'site' ) {
		f8_plugin( ELE, array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.170' ), 'network' === $where ? 'network' : 'site' );
		f8_plugin( ADDON, array( 'Name' => 'Element Pack Addon', 'Version' => '1.0.2', 'Author' => 'BdThemes' ), $where );
		f8_plugin( EP, array( 'Name' => 'Element Pack Pro', 'Version' => '10.0.1', 'Author' => 'BdThemes' ), $where );
		f8_plugin( PSP, array( 'Name' => 'Prime Slider Pro', 'Version' => '4.7.0', 'Author' => 'BdThemes' ), $where );
		f8_plugin( PSL, array( 'Name' => 'Prime Slider', 'Version' => '4.7.0', 'Author' => 'BdThemes' ), $where );
	}
	function e2e_el( $type, array $settings = array(), $id = 'w', array $children = array(), $el = 'widget' ) {
		$out = array( 'id' => $id, 'elType' => $el, 'settings' => $settings, 'elements' => $children );
		if ( 'widget' === $el ) {
			$out['widgetType'] = $type;
		}
		return $out;
	}
	function e2e_doc( array $elements, array $post = array(), array $meta = array() ) {
		return f8_post( $post + array( 'post_type' => 'page', 'post_title' => 'Página' ), array( '_elementor_data' => wp_json_encode( $elements ) ) + $meta );
	}
	/** Migrar hasta terminar (cada paso en una petición nueva). */
	function e2e_run( $start = true, $limit = 20 ) {
		$state = array();
		for ( $i = 0; $i < $limit; $i++ ) {
			f8_new_request();
			$state = Runner::site_step( $start && 0 === $i, 100 );
			if ( ! empty( $state['done'] ) || ! empty( $state['idle'] ) || ! empty( $state['wait_network'] ) ) {
				break;
			}
		}
		return $state;
	}
	function e2e_writes( $network = false ) {
		return array_values( array_map( static function ( $w ) { return $w[0]; }, array_filter( f8_writes(), static function ( $w ) use ( $network ) { return $w[1] === $network; } ) ) );
	}

	/* ================================================================ La familia: se reconoce y se retira (antes, KEPT) */
	e2e_reset();
	e2e_family();
	f8_plugin( 'bdthemes-element-pack-lite/bdthemes-element-pack-lite.php', array( 'Name' => 'Element Pack Lite - Addons for Elementor', 'Version' => '10.0.1' ) );
	f8_plugin( 'carpeta-copia/principal.php', array( 'Name' => 'Element Pack Addon', 'Version' => '1.0.1', 'Author' => 'BdThemes' ), 'site' );
	f8_plugin( 'otro/otro-addon.php', array( 'Name' => 'Otros Addons', 'Version' => '1.0' ), 'site' );
	$installed = Retire::installed();
	f8_check( isset( $installed[ ADDON ], $installed['carpeta-copia/principal.php'], $installed[ EP ], $installed[ LITE ], $installed[ PSP ], $installed[ PSL ] ) && ! isset( $installed['otro/otro-addon.php'], $installed[ ELE ] ), 'Se reconocen el Addon (también en otra carpeta, por su autor), Element Pack Pro y Lite y Prime Slider Pro y Lite; nunca un plugin ajeno ni DIGITALÍSIMO Elements.' );
	f8_check( 'Prime Slider Pro' === $installed[ PSP ]['label'] && 'Prime Slider' === $installed[ PSL ]['label'] && 'Element Pack Lite' === $installed[ LITE ]['label'], 'Cada uno con su etiqueta.' );
	f8_check( array() === Retire::kept_status(), 'Nada queda como «familia sin desactivar».' );
	$result = Retire::deactivate( false );
	f8_check( array( array( ADDON, 'carpeta-copia/principal.php', EP ) ) === e2e_writes(), 'Migrar desactiva los dos Addon y Element Pack en una sola escritura: ' . wp_json_encode( e2e_writes() ) );
	f8_check( in_array( PSP, get_option( 'active_plugins' ), true ) && in_array( PSL, get_option( 'active_plugins' ), true ), 'Prime Slider sigue activo hasta el final (se convierte activo).' );
	Retire::deactivate( false, true );
	f8_check( array( PSP, PSL ) === end( $GLOBALS['wp']['writes'] )[0], 'Y cae al final, Pro y Lite juntos.' );
	f8_check( ! array_filter( f8_writes(), static function ( $w ) { return in_array( 'otro/otro-addon.php', $w[0], true ); } ), 'Un plugin ajeno nunca se toca.' );

	/* ================================================================ Un sitio con Element Pack, el Addon y Prime Slider */
	e2e_reset();
	e2e_family();
	$extensions = e2e_el( '', array( 'section_sticky_on' => 'yes', 'element_pack_backdrop_filter' => 'yes', 'section_particles_on' => 'yes' ), 'c1', array(
		e2e_el( 'heading', array( 'title' => 'Hola', 'element_pack_widget_tooltip' => 'yes', 'element_pack_widget_tooltip_text' => 'Ayuda', 'ep_notation_active' => 'yes' ), 'h1' ),
		e2e_el( 'bdt-accordion', array( 'tabs' => array( array( '_id' => 't1', 'tab_title' => 'A' ) ), 'ep_display_conditions_enable' => 'yes', 'ep_display_conditions' => array() ), 'w1' ),
	), 'container' );
	$p_ext    = e2e_doc( array( $extensions ), array( 'post_title' => 'Efectos', 'post_status' => 'publish' ), array( '_elementor_page_settings' => array( 'ep_hash_link_enable' => 'yes', 'hide_title' => 'yes' ) ) );
	$p_ps     = e2e_doc( array( e2e_el( '', array(), 'c2', array( e2e_el( 'prime-slider-general', array( 'slides' => array( array( '_id' => 's1', 'title' => 'Uno' ) ) ), 'ps1' ) ), 'container' ) ), array( 'post_title' => 'Slider' ) );
	$p_plain  = e2e_doc( array( e2e_el( 'heading', array( 'title' => 'Nada heredado' ), 'n1' ) ) );
	$p_rev    = f8_post( array( 'post_type' => 'revision', 'post_parent' => $p_ext ), array( '_elementor_data' => wp_json_encode( array( $extensions ) ) ) );
	$p_old    = e2e_doc( array( e2e_el( '', array( 'section_sticky_on' => 'yes' ), 'o1', array(), 'section' ) ), array( 'post_title' => 'Migrada con 1.0.20' ), array( Migration::BACKUP => wp_json_encode( array( 'time' => 1, 'data' => '[]' ) ) ) );
	$state = e2e_run();
	f8_check( ! empty( $state['done'] ), 'Migrar termina: ' . wp_json_encode( array( $state['stage'] ?? '', $state['warnings'] ?? array() ) ) );
	f8_check( array( ADDON, EP ) === ( e2e_writes()[0] ?? null ), 'El grupo de Element Pack (Addon y Pro) cae en la primera escritura.' );
	f8_check( ! empty( $state['platform_prepared'] ) && ! empty( $state['ep_was_active'] ), 'La plataforma se preparó antes, con Element Pack activo.' );
	$data = (string) f8_meta( $p_ext, '_elementor_data' );
	f8_check( ! preg_match( '/"(ep_|element_pack_|bdt_|bdt-)/', $data ) && false !== strpos( $data, 'digitalisimo-accordion' ), 'Los efectos y el widget de Element Pack se convierten.' );
	f8_check( '' !== (string) f8_meta( $p_ext, Migration::BACKUP ) && is_array( Migration::f8_mark( $p_ext ) ), 'Con su copia original y la marca F8.' );
	$page = f8_meta( $p_ext, '_elementor_page_settings' );
	f8_check( is_array( $page ) && ! isset( $page['ep_hash_link_enable'] ) && 'yes' === $page['hide_title'] && is_array( json_decode( (string) f8_meta( $p_ext, Migration::BACKUP_PAGE ), true )['data'] ?? null ), 'La página pierde sus claves heredadas, con su copia.' );
	f8_check( false === strpos( (string) f8_meta( $p_rev, '_elementor_data' ), 'element_pack_' ), 'La revisión se convierte en su propio registro.' );
	f8_check( 'Nada heredado' === json_decode( (string) f8_meta( $p_plain, '_elementor_data' ), true )[0]['settings']['title'] && null === f8_meta( $p_plain, Migration::BACKUP ), 'Un documento sin nada heredado no se toca.' );
	$ps_data = (string) f8_meta( $p_ps, '_elementor_data' );
	$ps_left = Runner::prime_slider_left( $p_ps );
	if ( 0 === $ps_left[0] ) {
		f8_check( false === strpos( $ps_data, '"widgetType":"prime-slider-' ) || false !== strpos( $ps_data, 'digitalisimo-unconverted' ), 'El widget de Prime Slider se convierte (o queda en su marcador).' );
		f8_check( ! in_array( PSP, (array) get_option( 'active_plugins' ), true ) && ! in_array( PSL, (array) get_option( 'active_plugins' ), true ), 'Sin widgets suyos por convertir, Prime Slider se retira al final.' );
		f8_check( array( PSP, PSL ) === end( $GLOBALS['wp']['writes'] )[0], 'En una sola escritura, la última.' );
	} else {
		f8_check( in_array( PSP, (array) get_option( 'active_plugins' ), true ), 'Con un widget de Prime Slider sin convertir, Prime Slider se queda.' );
		f8_check( ! empty( Runner::report()['report']['late_kept'] ), 'Y el informe lo dice (core.late_kept).' );
		f8_check( Runner::has_pending( Runner::report() ), 'Pendiente de revisar.' );
	}
	// Documento migrado por 1.0.20: modo restringido (V.19.2 a, decisión del propietario).
	f8_check( false !== strpos( (string) f8_meta( $p_old, '_elementor_data' ), 'section_sticky_on' ), 'Un documento de 1.0.20 conserva sus restos (modo restringido).' );
	$report = Runner::report();
	$f8     = Report::stored();
	f8_check( isset( $report['report']['f8']['v'] ), 'El informe guardado es de F8.' );
	f8_check( $state['counts']['converted'] >= 2 && 1 === $state['counts']['revisions'] && $state['counts']['pages'] >= 1, 'Cuenta documentos, revisiones y páginas convertidos: ' . wp_json_encode( $state['counts'] ) );
	f8_check( false === f8_autoload( Report::OPTION ) && false === f8_autoload( Runner::REPORT ), 'El informe y su detalle no se cargan automáticamente.' );
	f8_check( isset( $f8['docs'][ $p_old ] ) && 1 <= Report::count_code( array( 'docs' => array( $p_old => $f8['docs'][ $p_old ] ) ), 'core.residual' ), 'Sus restos se informan (core.residual).' );
	f8_check( isset( $f8['docs'][ $p_ext ] ) && Report::count_code( array( 'docs' => array( $p_ext => $f8['docs'][ $p_ext ] ) ), 'ext.' ) >= 1, 'Los efectos de la página aparecen en el informe por documento.' );
	f8_check( Report::count_code( $f8, 'ext.dropped.' ) >= 1, 'Los descartes (partículas) se informan como «Sin equivalente».' );
	$html = Runner::render( $report );
	f8_check( str_contains( $html, 'Migración completada' ) && str_contains( $html, 'Efectos' ) && str_contains( $html, 'Sin equivalente' ), 'El informe pinta lo convertido, por documento y con título.' );
	f8_check( false === strpos( $html, 'Detectados y sin desactivar' ), 'Ya no hay avisos de «familia sin desactivar».' );
	$vendor = json_decode( (string) file_get_contents( __DIR__ . '/playground/brand-audit/vendors.json' ), true );
	$clean  = true;
	foreach ( (array) $vendor['hosts'] as $host ) {
		$clean = $clean && false === stripos( $html, $host ) && false === stripos( (string) f8_meta( $p_ext, '_elementor_data' ), $host ) && false === stripos( $ps_data, $host );
	}
	f8_check( $clean, 'Ni el informe ni lo convertido nombran dominios del proveedor.' );
	f8_check( false === strpos( $html, '<script' ), 'El informe no lleva código.' );
	f8_check( ! isset( $GLOBALS['wp']['options'][1][ Runner::JOB ] ) && ! isset( $GLOBALS['wp']['options'][1][ Runner::LOCK ] ), 'Sin tarea ni cerrojo al terminar.' );
	f8_check( ! array_filter( array_keys( $GLOBALS['wp']['options'][1] ), static function ( $k ) { return 0 === strpos( $k, Runner::IDS ); } ), 'Sin listas de IDs de etapa.' );

	// Pulsar Migrar otra vez: no reconvierte, y el informe sigue diciendo lo de la primera pasada.
	$before = array( f8_meta( $p_ext, '_elementor_data' ), f8_meta( $p_ext, Migration::BACKUP ) );
	$again  = e2e_run();
	f8_check( ! empty( $again['done'] ) && $before === array( f8_meta( $p_ext, '_elementor_data' ), f8_meta( $p_ext, Migration::BACKUP ) ), 'Volver a pulsar Migrar no toca lo ya convertido.' );
	$f8b = Report::stored();
	f8_check( isset( $f8b['docs'][ $p_ext ] ) && Report::count_code( $f8b, 'ext.' ) >= 1, 'Y el informe nuevo vuelve a listar lo convertido en la primera (relee las copias en la etapa report).' );

	// --revert: exige Element Pack activo; con él, devuelve datos y página y no toca lo editado.
	$refused = Runner::revert();
	f8_check( ! empty( $refused['refused'] ) && $before[0] === f8_meta( $p_ext, '_elementor_data' ), 'Sin Element Pack activo, --revert se niega (el sitio se quedaría sin cabecera).' );
	f8_plugin( EP, array( 'Name' => 'Element Pack Pro', 'Version' => '10.0.1', 'Author' => 'BdThemes' ), 'site' );
	$edited = e2e_doc( array( e2e_el( 'bdt-accordion', array( 'tabs' => array() ), 'e1' ) ), array( 'post_title' => 'Editada' ) );
	Migration::convert_document( $edited );
	update_post_meta( $edited, '_elementor_data', wp_slash( wp_json_encode( array( e2e_el( 'heading', array( 'title' => 'Editado a mano' ), 'x' ) ) ) ) );
	$rev = Runner::revert();
	f8_check( empty( $rev['refused'] ) && $rev['documents'] >= 1, 'Con Element Pack activo, --revert restaura: ' . wp_json_encode( $rev ) );
	f8_check( false !== strpos( (string) f8_meta( $p_ext, '_elementor_data' ), 'element_pack_widget_tooltip' ) && isset( f8_meta( $p_ext, '_elementor_page_settings' )['ep_hash_link_enable'] ), 'Datos y página vuelven a su original.' );
	f8_check( str_contains( (string) f8_meta( $edited, '_elementor_data' ), 'Editado a mano' ) && 1 <= $rev['skipped'], 'Lo editado después de migrar se omite (core.revert_skipped).' );
	$forced = Runner::revert( true );
	f8_check( str_contains( (string) f8_meta( $edited, '_elementor_data' ), 'bdt-accordion' ), 'Con --force, también.' );
	$writes_before = count( f8_writes() );
	Runner::revert( true );
	f8_check( count( f8_writes() ) === $writes_before && in_array( EP, get_option( 'active_plugins' ), true ) && ! in_array( ADDON, (array) get_option( 'active_plugins' ), true ), 'Restaurar no reactiva ni desactiva plugins (se reactivan a mano).' );
	f8_check( null === f8_meta( $p_ext, Migration::BACKUP ) && null === Migration::f8_mark( $p_ext ), 'Tras restaurar no quedan copias ni marca.' );

	/* ================================================================ La red: el Addon en toda la red, Prime Slider por sitio */
	e2e_reset( array( 'multisite' => true, 'sites' => 2 ) );
	f8_plugin( ELE, array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.170' ), 'network' );
	f8_plugin( ADDON, array( 'Name' => 'Element Pack Addon', 'Author' => 'BdThemes' ), 'network' );
	f8_plugin( EP, array( 'Name' => 'Element Pack Pro', 'Version' => '10.0.1', 'Author' => 'BdThemes' ), 'network' );
	f8_plugin( PSP, array( 'Name' => 'Prime Slider Pro', 'Version' => '4.7.0' ), 2 );
	switch_to_blog( 2 );
	$net_doc = e2e_doc( array( e2e_el( '', array( 'section_particles_on' => 'yes' ), 'r1', array(), 'container' ) ) );
	restore_current_blog();
	$job = Runner::network_start( true );
	$raw = Runner::network_state();
	f8_check( ! empty( $raw['barrier'] ) && array() === $raw['barrier_sites'] && ! empty( $raw['released'] ), 'Sin plantillas que preparar en ningún sitio, la barrera se libera al arrancar.' );
	f8_check( ! isset( $GLOBALS['wp']['site_options']['active_sitewide_plugins'][ ADDON ] ) && ! isset( $GLOBALS['wp']['site_options']['active_sitewide_plugins'][ EP ] ), 'El Addon y Element Pack caen en toda la red.' );
	f8_check( 1 === count( array_filter( f8_writes(), static function ( $w ) { return $w[1] && in_array( ADDON, $w[0], true ) && in_array( EP, $w[0], true ); } ) ), 'Juntos, en una escritura de red.' );
	foreach ( array( 1, 2 ) as $site ) {
		switch_to_blog( $site );
		e2e_run( false );
		restore_current_blog();
	}
	$summary = Runner::network_summary();
	f8_check( ! empty( $summary['done'] ) && 'done' === $summary['status'][1]['status'] && 'done' === $summary['status'][2]['status'], 'Los dos sitios terminan y la red también.' );
	f8_check( ! in_array( PSP, (array) $GLOBALS['wp']['options'][2]['active_plugins'], true ), 'El sitio 2 retira su Prime Slider al final.' );
	switch_to_blog( 2 );
	$f8_2 = Report::stored();
	restore_current_blog();
	f8_check( 1 <= Report::count_code( $f8_2, 'ext.dropped.' ) && isset( $f8_2['docs'][ $net_doc ] ), 'El sitio 2 informa sus descartes (partículas) en su propio informe.' );
	f8_check( isset( $summary['status'][2]['f8']['dropped'] ), 'La red lee las columnas del resumen guardado de cada sitio.' );
	$text = wp_strip_all_tags( Runner::render_network( $summary ) );
	f8_check( str_contains( $text, 'Migración de la red completada' ) && false === strpos( $text, 'Detectados y sin desactivar' ), 'La pantalla de red: completada, sin la «familia sin desactivar».' );
	ob_start();
	Migration::network_page();
	$table = wp_strip_all_tags( str_replace( '</td>', ' | ', ob_get_clean() ) );
	f8_check( str_contains( $table, 'Sin equivalente' ) && str_contains( $table, 'sin equivalente: ' ), 'La tabla de la red resume lo que no tiene equivalente por sitio, sin releer documentos.' );
	f8_check( 1 === $GLOBALS['wp']['blog'] && empty( $GLOBALS['wp']['stack'] ), 'Cada cambio de sitio se deshace.' );
	f8_check( ! isset( $GLOBALS['wp']['options'][1][ Runner::JOB ], $GLOBALS['wp']['options'][2][ Runner::JOB ] ) && ! isset( $GLOBALS['wp']['cron'][1][ Runner::NETWORK_TICK ] ), 'Sin tareas de sitio ni evento de red al terminar.' );
	f8_check( ! isset( $GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ] ), 'Ni cerrojo de red.' );
	f8_check( 1 === (int) Runner::network_state()['user'], 'La tarea de red recuerda quién la lanzó.' );

	/* ================================================================ Pantalla del sitio: un botón y el análisis por trozos */
	e2e_reset();
	e2e_family();
	e2e_doc( array( $extensions ), array( 'post_title' => 'Efectos' ) );
	ob_start();
	Migration::page();
	$screen = ob_get_clean();
	f8_check( 1 === substr_count( $screen, '<button' ), 'La herramienta sigue teniendo un solo botón.' );
	f8_check( str_contains( $screen, 'data-action="digitalisimo_migration_analyze"' ) && str_contains( $screen, 'Analizando…' ), 'El análisis previo se pide por trozos al abrirla.' );
	f8_check( str_contains( wp_strip_all_tags( $screen ), 'al final, cuando sus widgets estén convertidos' ) && str_contains( wp_strip_all_tags( $screen ), 'al empezar, en una sola escritura con su grupo' ), 'Dice qué plugins se retiran y cuándo.' );
	f8_check( str_contains( wp_strip_all_tags( $screen ), 'Element Pack con su Addon y, al final, Prime Slider' ), 'La explicación nombra el Addon y Prime Slider.' );
	$analysis = Migration::analyze_step( 0 );
	f8_check( $analysis['done'] && 1 === $analysis['total'] && Report::count_code( $analysis['report'], 'ext.' ) >= 1, 'El análisis previo describe los efectos del documento, sin escribir.' );
	f8_check( str_contains( Migration::analysis_html( $analysis ), 'Lo que hará Migrar' ), 'Y lo presenta como lo que hará Migrar.' );
	f8_check( null === get_option( Runner::JOB, null ) && null === get_option( Report::OPTION, null ), 'Analizar no crea tarea ni informe.' );
	f8_check( '' !== (string) f8_meta( array_keys( $GLOBALS['wp']['posts'][1] )[0], '_elementor_data' ) && null === f8_meta( array_keys( $GLOBALS['wp']['posts'][1] )[0], Migration::BACKUP ), 'Ni toca el documento.' );

	f8_done( 'DIGITALÍSIMO Migración: de punta a punta', 60 );
}
