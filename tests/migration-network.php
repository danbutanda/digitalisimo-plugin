<?php
/**
 * F8 · Barrera de red de DIGITALÍSIMO Migración (V.6, V.4, C16): sitios de la barrera (archivados y «nada que
 * preparar» fuera), listo y bloqueado, plazo relativo y tope absoluto, cerrojo de red (dos liberaciones a la vez y robo
 * de uno abandonado), WP-Cron del sitio principal, network_finish() con Prime Slider al final, petición anterior a la
 * liberación, liberación desde el paso de un sitio (como mucho cada minuto) y las cuatro combinaciones del Addon y Pro
 * en sitio y red. WordPress de f8-wp.php; Translator y Migration simulados, los pasos de plataforma de G y H con
 * dobles de su firma; Retire, Runner, Report, Registry, Aliases y Dropped reales.
 */
namespace Elementor {
	class Plugin { public static $instance; }
}
namespace ElementorPro\Modules\ThemeBuilder {
	/** El Theme Builder de DIGITALÍSIMO Elements está cargado (V.5.3, condición 5). */
	class Module {}
}
namespace Digitalisimo\Elements {
	final class Widget_Registry {}
}
namespace Digitalisimo\Elements\Legacy {
	final class Translator {
		public static function element_pack_active() { return ! empty( $GLOBALS['wp']['ep_loaded'][ get_current_blog_id() ] ); }
		public static function is_legacy_id( $id ) { return 1 === preg_match( '/^(bdt-[a-z0-9-]+|fooevents-calendar|lightbox)$/', (string) $id ); }
		public static function is_prime_slider_id( $id ) { return 1 === preg_match( '/^prime-slider-[a-z0-9-]+$/', (string) $id ); }
		public static function is_known_id( $id ) { return self::is_legacy_id( $id ) || self::is_prime_slider_id( $id ); }
		public static function prime_slider_active() { return false; }
	}
	final class Migration {
		const BACKUP        = '_digitalisimo_ep_backup';
		const PAGE          = 'digitalisimo-ep-migration';
		const REPAIRED      = 'digitalisimo_ep_conditions_repaired';
		const REPAIR_CUTOFF = 'digitalisimo_ep_conditions_repair_cutoff';
		const REPAIR_BATCH  = 25;
		public static function recover_candidates() { return array(); }
		public static function overwritten_by( $r ) { return null; }
		public static function repair_step( $l ) { update_option( self::REPAIRED, '1' ); return array( 'documents' => 0, 'elements' => 0, 'remaining' => 0, 'done' => true ); }
		public static function documents() { return array(); }
		public static function all_documents() { return array(); }
		public static function documents_with( array $n ) { return array_values( (array) ( $GLOBALS['wp']['ps_docs'][ get_current_blog_id() ] ?? array() ) ); }
		public static function legacy_revisions() { return array(); }
		public static function convert_document( $id ) { $GLOBALS['wp']['converted'][ get_current_blog_id() ][] = $id; return array( 'native' => 1, 'page' => false ); }
		public static function classify() { return array( 'convertible' => array(), 'blocked' => array() ); }
		public static function inventory() { return array(); }
		public static function reason( $t ) { return ''; }
		public static function figures() { return array( 'elements' => 0, 'documents' => 0, 'missing' => array() ); }
		public static function clear_css( $id ) {}
		public static function backups() { return array(); }
		public static function page_backups() { return array(); }
		public static function f8_mark( $id ) { return null; }
		public static function marker_type( array $m ) { return ''; }
	}
}
namespace Digitalisimo\Elements\Legacy\Platform {
	/** Doble del frente G: una cabecera por sitio con plantillas, que puede quedar sin copia publicable (bloquea). */
	final class Theme_Builder {
		public static function prepare( array &$cursor, float $deadline ): bool {
			$blog = get_current_blog_id();
			if ( empty( $GLOBALS['wp']['tb_sites'][ $blog ] ) ) {
				return true;
			}
			if ( ! Registry::get( 'tb', '12' ) ) {
				$id = f8_post( array( 'post_type' => 'elementor_library', 'post_status' => 'draft', 'post_title' => 'Cabecera' ) );
				Registry::put( 'tb', '12', array( 'dest' => $id, 'state' => 'draft', 'extra' => array( 'blocks' => ! empty( $GLOBALS['wp']['tb_blocks'][ $blog ] ) ) ) );
			}
			return true;
		}
	}
	/** Doble del frente G: anota quién permite publicar, por sitio. */
	final class Publisher {
		public static function allow( bool $on ): void { $GLOBALS['wp']['allowed'][ get_current_blog_id() ] = $on; }
		public static function sync(): void {}
	}
}
namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	define( 'DIGITALISIMO_ELEMENTS_FILE', WP_PLUGIN_DIR . '/digitalisimo-elements/pro-elements.php' );
	define( 'DIGITALISIMO_MIGRATION_VERSION', '1.0.21' );
	$legacy = __DIR__ . '/../digitalisimo-migration/legacy/';
	foreach ( array( 'context', 'report', 'retire', 'aliases', 'runner' ) as $file ) {
		require_once $legacy . 'class-' . $file . '.php';
	}
	require_once $legacy . 'platform/class-registry.php';
	use Digitalisimo\Elements\Legacy\Aliases;
	use Digitalisimo\Elements\Legacy\Platform\Registry;
	use Digitalisimo\Elements\Legacy\Report;
	use Digitalisimo\Elements\Legacy\Retire;
	use Digitalisimo\Elements\Legacy\Runner;

	const EP    = 'bdthemes-element-pack/bdthemes-element-pack.php';
	const ADDON = 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php';
	const PS    = 'bdthemes-prime-slider/bdthemes-prime-slider.php';
	const EPRO  = 'elementor-pro/elementor-pro.php';
	const PROE  = 'pro-elements/pro-elements.php';
	const ELE   = 'digitalisimo-elements/pro-elements.php';

	/** Red de 4 sitios: el 4 archivado; el 3 sin nada que preparar; Element Pack en toda la red. */
	function net( array $extra = array() ) {
		f8_reset( array( 'multisite' => true, 'sites' => 4 ) );
		f8_wpdb();
		Runner::init();
		Registry::flush_cache();
		Aliases::set_map( array() );
		f8_site( 4, array( 'archived' => 1 ) );
		f8_plugin( ELE, array( 'Name' => 'DIGITALÍSIMO Elements', 'Version' => '4.3.0.170' ), 'network' );
		f8_plugin( EP, array( 'Name' => 'Element Pack Pro', 'Version' => '10.0.1', 'Author' => 'BdThemes' ), 'network' );
		f8_plugin( PROE, array( 'Name' => 'PRO Elements', 'Version' => '3.30' ), 'network' );
		f8_plugin( EPRO, array( 'Name' => 'Elementor Pro', 'Version' => '3.30' ), 2 );
		foreach ( array( 1, 2 ) as $site ) {
			switch_to_blog( $site );
			update_option( '_bdthemes_builder_header__12', 'yes' );
			restore_current_blog();
		}
		$GLOBALS['wp']['tb_sites']  = array( 1 => true, 2 => true );
		$GLOBALS['wp']['tb_blocks'] = array();
		$GLOBALS['wp']['ep_loaded'] = array( 1 => true, 2 => true, 3 => true, 4 => true );
		$GLOBALS['wp']['allowed']   = array();
		$GLOBALS['wp']['converted'] = array();
		foreach ( $extra as $key => $value ) {
			$GLOBALS['wp'][ $key ] = $value;
		}
	}

	/** Una petición del sitio: hasta `$n` pasos o hasta que termine o espere a la red. */
	function step( $site, $n = 6 ) {
		switch_to_blog( $site );
		try {
			$state = array();
			for ( $i = 0; $i < $n; $i++ ) {
				f8_new_request();
				$state = Runner::site_step( false, 100 );
				if ( ! empty( $state['done'] ) || ! empty( $state['wait_network'] ) || ! empty( $state['idle'] ) ) {
					break;
				}
			}
			return $state;
		} finally {
			restore_current_blog();
		}
	}

	function job_state( $site ) {
		return $GLOBALS['wp']['options'][ $site ][ Runner::JOB ] ?? null;
	}

	/** Escrituras de red (deactivate_plugins con $network) que incluyen un archivo. */
	function network_writes( $file ) {
		return count( array_filter( f8_writes(), static function ( $w ) use ( $file ) {
			return $w[1] && in_array( $file, $w[0], true );
		} ) );
	}

	function active_network( $file ) {
		return isset( $GLOBALS['wp']['site_options']['active_sitewide_plugins'][ $file ] );
	}

	/* ================================================================ Arranque */
	net();
	$job = Runner::network_start( true );
	$raw = Runner::network_state();
	f8_check( ! empty( $raw['barrier'] ), 'Con Element Pack activado en toda la red y quien puede retirarlo, hay barrera.' );
	f8_check( array( 1, 2, 3 ) === array_map( 'intval', $raw['sites'] ), 'Los sitios de la tarea: los activos con DIGITALÍSIMO Elements.' );
	f8_check( array( 4 ) === $raw['excluded'], 'El sitio archivado queda fuera y listado.' );
	f8_check( array( 1, 2 ) === $raw['barrier_sites'], 'El sitio 3, sin nada que preparar, no retiene la barrera.' );
	f8_check( ! active_network( PROE ) && 1 === network_writes( PROE ), 'PRO Elements (toda la red) cae al inicio, en una escritura.' );
	f8_check( ! in_array( EPRO, (array) $GLOBALS['wp']['options'][2]['active_plugins'], true ), 'Elementor Pro del sitio 2 cae al inicio (sin él, los adaptadores no tienen de qué heredar).' );
	f8_check( active_network( EP ) && 0 === network_writes( EP ), 'Element Pack sigue activo en toda la red hasta la liberación.' );
	f8_check( empty( $raw['released'] ) && empty( $raw['done'] ), 'La barrera empieza cerrada.' );
	f8_check( isset( $GLOBALS['wp']['cron'][1][ Runner::NETWORK_TICK ] ), 'El evento de red queda en el WP-Cron del sitio principal.' );
	f8_check( ! isset( $GLOBALS['wp']['cron'][2][ Runner::NETWORK_TICK ] ), 'Y sólo en el sitio principal.' );
	f8_check( ! isset( $GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ] ), 'El cerrojo de red se suelta.' );
	f8_check( ! isset( $GLOBALS['wp']['options'][4][ Runner::JOB ] ) && isset( $GLOBALS['wp']['options'][3][ Runner::JOB ] ), 'El archivado no recibe tarea; los demás sí.' );
	f8_check( 1 === (int) job_state( 2 )['user'] && (int) $raw['started'] === (int) job_state( 2 )['network'], 'Cada tarea sabe quién lanzó la red y a qué tarea de red pertenece.' );
	f8_check( str_contains( wp_strip_all_tags( Runner::render_network( $job ) ), 'Archivados, marcados como spam o borrados' ), 'La pantalla de red lista el archivado.' );

	/* ================================================================ Espera de un sitio */
	$s1 = step( 1 );
	f8_check( 'deactivate' === $s1['stage'] && ! empty( $s1['wait_network'] ) && ! empty( $s1['pause'] ), 'El sitio 1 prepara y espera en `deactivate` (wait_network).' );
	f8_check( ! empty( $s1['barrier_ready'] ) && empty( $s1['barrier_blocked'] ), 'Con copia publicable, está listo.' );
	f8_check( true === ( $GLOBALS['wp']['allowed'][1] ?? null ), 'Antes de esperar permite publicar sus copias (sólo se publican sin Element Pack cargado).' );
	f8_check( 0 === (int) $s1['stage_attempts'] && 0 === (int) $s1['pauses'], 'Esperar no cuenta como intento ni como pausa.' );
	f8_check( true === Runner::site_response( $s1 )['wait_network'], 'La respuesta del paso dice wait_network: la pantalla deja de conducirlo.' );
	f8_check( active_network( EP ), 'Element Pack sigue activo en la red.' );
	for ( $i = 0; $i < 5; $i++ ) {
		$again = step( 1, 1 );
	}
	f8_check( 'deactivate' === $again['stage'] && ! empty( $again['wait_network'] ) && ! in_array( true, array_map( static function ( $w ) { return str_contains( $w, 'se saltó' ); }, (array) $again['warnings'] ), true ), 'Cinco sondeos más siguen esperando: la etapa nunca se salta por esperar.' );
	f8_check( str_contains( Runner::render( $again ), 'Esperando a que los demás sitios' ), 'El avance del sitio explica la espera.' );

	/* ================================================================ Liberación: no hasta que todos estén listos */
	$GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['checked'] = 0;
	$rel = Runner::network_release();
	f8_check( empty( $rel['released'] ) && active_network( EP ), 'Con el sitio 2 sin preparar, la red no libera.' );
	f8_check( $rel['checked'] > 0, 'Anota cuándo comprobó.' );
	$summary = Runner::network_summary();
	f8_check( ! empty( $summary['status'][1]['waiting'] ) && empty( $summary['status'][2]['waiting'] ), 'El resumen distingue al que espera.' );
	$resp = Runner::network_response( $summary );
	f8_check( 1 === $resp['waiting'] && $resp['barrier'] && ! $resp['released'], 'La respuesta de red cuenta los que esperan.' );
	$drive = array_column( $resp['sites'], 'waiting', 'id' );
	f8_check( true === $drive[1] && false === $drive[2], 'Y marca qué sitios esperan (la pantalla no los conduce).' );
	// El sitio 3 (nada que preparar) también espera, pero no retiene.
	$s3 = step( 3 );
	f8_check( ! empty( $s3['wait_network'] ) && 'deactivate' === $s3['stage'], 'El sitio sin nada que preparar espera para convertir después, sin Element Pack.' );

	/* ================================================================ Cerrojo de red */
	// La última comprobación fue hace más de un minuto: el paso de un sitio que espera puede liberar.
	$GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['checked'] = time() - 61;
	$s2 = step( 2 );
	f8_check( ! empty( $s2['wait_network'] ) && ! empty( $s2['barrier_ready'] ), 'El sitio 2 llega a la barrera listo.' );
	// Si el paso del sitio 2 ya liberó (lo autoriza la red, como mucho cada minuto), se repite con el reloj atrás.
	$net = Runner::network_state();
	f8_check( ! empty( $net['released'] ), 'El paso de un sitio que espera libera la barrera cuando todos están listos.' );
	f8_check( 1 === network_writes( EP ) && ! active_network( EP ), 'Element Pack cae en toda la red, en una escritura.' );
	// Idempotencia y cerrojo: otra liberación no vuelve a escribir.
	$before = count( f8_writes() );
	Runner::network_release();
	Runner::network_release();
	f8_check( count( f8_writes() ) === $before, 'Liberar dos veces no escribe dos veces.' );

	// Dos liberaciones a la vez: con el cerrojo tomado por otra petición, ésta no hace nada.
	net();
	Runner::network_start( true );
	step( 1 );
	step( 3 );
	switch_to_blog( 2 );
	f8_new_request();
	$GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['checked'] = time(); // el sitio no libera por su cuenta.
	for ( $i = 0; $i < 6; $i++ ) {
		f8_new_request();
		$s = Runner::site_step( false, 100 );
		if ( ! empty( $s['wait_network'] ) ) {
			break;
		}
	}
	restore_current_blog();
	f8_check( empty( Runner::network_state()['released'] ), 'Con la última comprobación reciente, el sitio que espera no libera (como mucho cada minuto).' );
	f8_check( Runner::network_lock(), 'Otra petición toma el cerrojo de red.' );
	$held = $GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ];
	f8_check( str_contains( $GLOBALS['wpdb']->queries[ count( $GLOBALS['wpdb']->queries ) - 1 ], 'INSERT IGNORE INTO wp_options' ), 'El cerrojo es un INSERT IGNORE en `options` del sitio principal.' );
	// Simula una segunda petición: sin token propio, no puede tomarlo.
	$token = ( new \ReflectionProperty( Runner::class, 'network_token' ) );
	$token->setAccessible( true ); // PHP 7.4 (el mínimo declarado) lo exige; desde 8.1 no hace nada.
	$mine  = $token->getValue();
	$token->setValue( null, '' );
	$blocked_rel = Runner::network_release();
	f8_check( empty( $blocked_rel['released'] ) && active_network( EP ), 'Con el cerrojo de otra petición, la liberación no hace nada.' );
	f8_check( $held === $GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ], 'Y no toca el cerrojo ajeno.' );
	$token->setValue( null, $mine );
	Runner::network_unlock();
	f8_check( ! isset( $GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ] ), 'El dueño lo suelta.' );
	$ok = Runner::network_release();
	f8_check( ! empty( $ok['released'] ) && 1 === network_writes( EP ), 'Sin cerrojo ajeno, libera una sola vez.' );
	// Cerrojo abandonado: se roba pasados 300 s.
	$GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ] = ( time() - Runner::NETWORK_STALE - 5 ) . '|muerto';
	f8_check( Runner::network_lock(), 'Un cerrojo de red abandonado se roba.' );
	Runner::network_unlock();
	$GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ] = time() . '|vivo';
	f8_check( ! Runner::network_lock(), 'Uno vivo, no.' );
	unset( $GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ] );

	/* ================================================================ Petición anterior a la liberación */
	$released = (float) Runner::network_state()['released'];
	switch_to_blog( 1 );
	// Arrancó después de lo que la red desactivó al inicio, pero antes de la liberación.
	$_SERVER['REQUEST_TIME_FLOAT'] = ( (float) Runner::state()['deactivated_at'] + $released ) / 2;
	$old = Runner::site_step( false, 100 );
	restore_current_blog();
	f8_check( 'deactivate' === $old['stage'] && ! empty( $old['pause'] ) && (float) $old['deactivated_at'] === $released, 'Una petición que arrancó antes de la liberación fija deactivated_at y espera (V.3.2).' );
	f8_check( 0 === (int) $old['pauses'] && 0 === (int) $old['stage_attempts'], 'Sin sumar pausas ni intentos.' );
	$GLOBALS['wp']['ep_loaded'] = array();
	$done1 = step( 1, 12 );
	f8_check( ! empty( $done1['done'] ), 'La siguiente petición, ya sin Element Pack, sigue hasta el final.' );
	f8_check( empty( $done1['wait_network'] ) && 0 === (int) $done1['pauses'], 'Sin espera ni pausas pendientes.' );
	switch_to_blog( 1 );
	$copy = Registry::get( 'tb', '12' );
	restore_current_blog();
	f8_check( in_array( (int) $copy['dest'], (array) ( $GLOBALS['wp']['converted'][1] ?? array() ), true ), 'La copia registrada del sitio 1 se convirtió antes que nada.' );

	/* ================================================================ network_finish: Prime Slider al final */
	f8_plugin( PS, array( 'Name' => 'Prime Slider Pro', 'Version' => '4.7.0' ), 'network' );
	step( 2, 12 );
	f8_check( empty( Runner::network_state()['done'] ), 'Con el sitio 3 sin terminar, la red no termina.' );
	f8_check( active_network( PS ), 'Ni retira Prime Slider.' );
	step( 3, 12 );
	$summary = Runner::network_summary();
	f8_check( ! empty( $summary['done'] ) && ! empty( Runner::network_state()['done'] ), 'Con todos terminados, la red termina (network_finish bajo el cerrojo).' );
	f8_check( ! active_network( PS ) && 1 === network_writes( PS ), 'Prime Slider de la red cae al final, en una escritura.' );
	f8_check( ! isset( $GLOBALS['wp']['cron'][1][ Runner::NETWORK_TICK ] ), 'El evento de red se retira al terminar.' );
	f8_check( ! isset( $GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ] ), 'Y el cerrojo queda libre.' );
	$text = wp_strip_all_tags( Runner::render_network( $summary ) );
	f8_check( str_contains( $text, 'Migración de la red completada' ), 'La pantalla de red lo dice.' );

	// Con un sitio que conserva widgets de Prime Slider, se queda.
	net();
	f8_plugin( PS, array( 'Name' => 'Prime Slider Pro', 'Version' => '4.7.0' ), 'network' );
	$GLOBALS['wp']['ep_loaded'] = array();
	unset( $GLOBALS['wp']['site_options']['active_sitewide_plugins'][ EP ] );
	$GLOBALS['wp']['ps_docs'] = array( 2 => array( 50 ) );
	switch_to_blog( 2 );
	f8_post( array( 'ID' => 50 ), array( '_elementor_data' => json_encode( array( array( 'id' => 'p', 'elType' => 'widget', 'widgetType' => 'prime-slider-general', 'settings' => array(), 'elements' => array() ) ) ) ) );
	restore_current_blog();
	$job = Runner::network_start( true );
	f8_check( empty( Runner::network_state()['barrier'] ), 'Sin Element Pack en la red no hay barrera.' );
	foreach ( array( 1, 2, 3 ) as $site ) {
		step( $site, 12 );
	}
	$summary = Runner::network_summary();
	f8_check( ! empty( $summary['done'] ) && active_network( PS ), 'Un sitio con widgets de Prime Slider sin convertir retiene Prime Slider en la red.' );
	f8_check( ! empty( $summary['status'][2]['late_kept'] ), 'El resumen lo atribuye a ese sitio.' );
	f8_check( str_contains( implode( ' ', (array) Runner::network_state()['warnings'] ), 'Prime Slider sigue activo en toda la red' ), 'Y lo avisa.' );
	switch_to_blog( 2 );
	$r2 = Runner::report();
	restore_current_blog();
	f8_check( ! empty( $r2['report']['late_kept'] ) && ! empty( $r2['report']['f8']['pending'] ), 'El informe del sitio queda pendiente (core.late_kept).' );

	/* ================================================================ Plazo relativo y tope absoluto */
	net();
	Runner::network_start( true );
	step( 1 );
	step( 3 );
	// El sitio 2 nunca avanza (dominio propio sin WP-Cron): 900 s sin avance de nadie.
	$GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['started'] = time() - 1000;
	foreach ( array( 1, 2, 3 ) as $site ) {
		if ( isset( $GLOBALS['wp']['options'][ $site ][ Runner::JOB ] ) ) {
			$GLOBALS['wp']['options'][ $site ][ Runner::JOB ]['updated'] = time() - 950;
			$GLOBALS['wp']['options'][ $site ][ Runner::JOB ]['network'] = $GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['started'];
		}
	}
	$rel = Runner::network_release();
	f8_check( ! empty( $rel['released'] ) && array( 2 ) === $rel['unprepared'], 'Tras 900 s sin avance, se libera y se lista el sitio sin preparar.' );
	f8_check( str_contains( wp_strip_all_tags( Runner::render_network( Runner::network_summary() ) ), 'al vencer el plazo' ), 'La pantalla de red lo dice.' );
	// Con avance reciente de alguien, el plazo relativo no vence…
	net();
	Runner::network_start( true );
	step( 1 );
	$GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['started'] = time() - 1000;
	foreach ( array( 1, 3 ) as $site ) {
		if ( isset( $GLOBALS['wp']['options'][ $site ][ Runner::JOB ] ) ) {
			$GLOBALS['wp']['options'][ $site ][ Runner::JOB ]['network'] = $GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['started'];
		}
	}
	$GLOBALS['wp']['options'][2][ Runner::JOB ]['network']  = $GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['started'];
	$GLOBALS['wp']['options'][2][ Runner::JOB ]['updated']  = time() - 10;
	$rel = Runner::network_release();
	f8_check( empty( $rel['released'] ), 'Con un avance hace 10 s, el plazo relativo no vence.' );
	// …pero el tope absoluto sí (filtro digitalisimo_migration_barrier_max).
	add_filter( 'digitalisimo_migration_barrier_max', static function () { return 500; } );
	$rel = Runner::network_release();
	f8_check( ! empty( $rel['released'] ) && in_array( 2, $rel['unprepared'], true ), 'El tope absoluto libera aunque alguien siga avanzando.' );
	remove_all_filters( 'digitalisimo_migration_barrier_max' );
	// El plazo relativo también es filtrable.
	net();
	Runner::network_start( true );
	add_filter( 'digitalisimo_migration_barrier_idle', static function () { return 0; } );
	$rel = Runner::network_release();
	f8_check( ! empty( $rel['released'] ), 'Con digitalisimo_migration_barrier_idle a 0, vence en seguida.' );
	remove_all_filters( 'digitalisimo_migration_barrier_idle' );

	/* ================================================================ Sitio bloqueado (V.19.1 a, decisión del propietario) */
	net( array( 'tb_blocks' => array( 2 => true ) ) );
	Runner::network_start( true );
	$GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['checked'] = time(); // los sitios no liberan por su cuenta.
	$s2 = step( 2 );
	f8_check( ! empty( $s2['wait_network'] ) && 'tb.blocked_retire' === $s2['barrier_blocked'] && empty( $s2['barrier_ready'] ), 'Una cabecera visible sin copia publicable bloquea el sitio.' );
	f8_check( empty( $GLOBALS['wp']['allowed'][2] ), 'Y no permite publicar.' );
	$rel = Runner::network_release();
	f8_check( empty( $rel['released'] ) && empty( $rel['done'] ) && array( 2 ) === $rel['blocked'] && active_network( EP ), 'Con un bloqueado y otro sitio aún preparando, la red espera sin liberar.' );
	step( 1 );
	$rel = Runner::network_release();
	f8_check( ! empty( $rel['done'] ) && ! empty( $rel['stopped'] ) && empty( $rel['released'] ), 'En cuanto sólo queda el bloqueado, la red se detiene sin liberar (no espera al plazo).' );
	f8_check( active_network( EP ) && 0 === network_writes( EP ), 'Element Pack sigue en la red: ningún visitante pierde la cabecera.' );
	$html = Runner::render_network( Runner::network_summary() );
	$text = wp_strip_all_tags( $html );
	f8_check( str_contains( $text, 'se detuvo' ) && str_contains( $text, 'sin copia que pueda publicarse' ), 'La pantalla de red explica por qué.' );
	f8_check( str_contains( $html, 'href="https://s2.test/wp-admin/tools.php?page=digitalisimo-ep-migration"' ), 'Y enlaza la herramienta del sitio bloqueado, cuyo informe nombra la plantilla.' );
	// Corregido el sitio, volver a pulsar sigue: la barrera se libera.
	$GLOBALS['wp']['tb_blocks'] = array();
	switch_to_blog( 2 );
	\Digitalisimo\Elements\Legacy\Platform\Registry::put( 'tb', '12', array( 'dest' => (int) \Digitalisimo\Elements\Legacy\Platform\Registry::get( 'tb', '12' )['dest'], 'state' => 'draft', 'extra' => array( 'blocks' => false ) ) );
	restore_current_blog();
	$again = Runner::network_start( true );
	$raw   = Runner::network_state();
	f8_check( empty( $raw['done'] ) && ! empty( $raw['barrier'] ) && array( 1, 2 ) === $raw['barrier_sites'], 'Volver a pulsar Migrar toda la red arranca otra vez la barrera.' );
	f8_check( (int) $raw['started'] === (int) job_state( 2 )['network'], 'Las tareas de los sitios pasan a la tarea de red nueva.' );
	$GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['checked'] = time();
	step( 1 );
	$s2 = step( 2 );
	f8_check( ! empty( $s2['barrier_ready'] ) && empty( $s2['barrier_blocked'] ), 'Resuelta la plantilla, el sitio queda listo.' );
	$rel = Runner::network_release();
	f8_check( ! empty( $rel['released'] ) && ! active_network( EP ), 'Y la red libera.' );

	// Al vencer el plazo con un bloqueado (y otro sin preparar), también se detiene sin liberar.
	net( array( 'tb_blocks' => array( 2 => true ) ) );
	Runner::network_start( true );
	$GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['checked'] = time();
	step( 2 );
	$GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['started'] = time() - 5000;
	foreach ( array( 1, 2, 3 ) as $site ) {
		$GLOBALS['wp']['options'][ $site ][ Runner::JOB ]['updated'] = time() - 5000;
		$GLOBALS['wp']['options'][ $site ][ Runner::JOB ]['network'] = $GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['started'];
	}
	$rel = Runner::network_release();
	f8_check( ! empty( $rel['done'] ) && ! empty( $rel['stopped'] ) && empty( $rel['released'] ) && active_network( EP ), 'Al vencer el plazo con un bloqueado, la red se detiene sin liberar.' );
	switch_to_blog( 2 );
	$state2 = Runner::state();
	restore_current_blog();
	$after = step( 2, 3 );
	f8_check( empty( $after['wait_network'] ), 'Con la red detenida, el sitio deja de esperar.' );
	f8_check( empty( $after['done'] ) || str_contains( implode( ' ', (array) $after['warnings'] ), 'Element Pack sigue activo' ), 'Y sigue como estaba, con Element Pack.' );
	f8_check( is_array( $state2 ), 'La tarea del sitio bloqueado se conserva.' );

	/* ================================================================ WP-Cron del sitio principal */
	net();
	Runner::network_start( true );
	step( 1 );
	step( 2 );
	step( 3 );
	// Ninguna pantalla abierta: el evento del sitio principal libera.
	$GLOBALS['wp']['site_options'][ Runner::NETWORK_JOB ]['checked'] = time();
	if ( empty( Runner::network_state()['released'] ) ) {
		f8_cron_fire( Runner::NETWORK_TICK, 1 );
	}
	f8_check( ! empty( Runner::network_state()['released'] ) && ! active_network( EP ), 'El WP-Cron del sitio principal libera la barrera sin pantalla.' );
	f8_check( isset( $GLOBALS['wp']['cron'][1][ Runner::NETWORK_TICK ] ), 'Y se reprograma mientras la red no termina.' );
	$GLOBALS['wp']['ep_loaded'] = array();
	foreach ( array( 1, 2, 3 ) as $site ) {
		step( $site, 12 );
	}
	f8_cron_fire( Runner::NETWORK_TICK, 1 );
	f8_check( ! empty( Runner::network_state()['done'] ) && ! isset( $GLOBALS['wp']['cron'][1][ Runner::NETWORK_TICK ] ), 'Con todos terminados, el evento cierra la red y no se reprograma.' );
	// El evento fuera de una red no hace nada.
	$GLOBALS['wp']['multisite'] = false;
	Runner::network_tick();
	f8_check( ! empty( Runner::network_state()['done'] ), 'Fuera de una red, el evento no hace nada.' );
	$GLOBALS['wp']['multisite'] = true;

	/* ================================================================ Escritura de `done` bajo el cerrojo */
	net();
	$GLOBALS['wp']['ep_loaded'] = array();
	unset( $GLOBALS['wp']['site_options']['active_sitewide_plugins'][ EP ] );
	Runner::network_start( true );
	foreach ( array( 1, 2, 3 ) as $site ) {
		step( $site, 12 );
	}
	$GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ] = time() . '|ajeno';
	$summary = Runner::network_summary();
	f8_check( empty( Runner::network_state()['done'] ) && ! empty( $summary['all_done'] ), 'Con el cerrojo de red ajeno, el resumen no escribe `done`.' );
	unset( $GLOBALS['wp']['options'][1][ Runner::NETWORK_LOCK ] );
	Runner::network_summary();
	f8_check( ! empty( Runner::network_state()['done'] ), 'Sin él, sí.' );

	/* ================================================================ Addon y Pro en sitio y red (V.4) */
	function addon_case( $addon, $pro ) {
		f8_reset( array( 'multisite' => true, 'sites' => 2 ) );
		f8_wpdb();
		f8_plugin( ELE, array( 'Name' => 'DIGITALÍSIMO Elements' ), 'network' );
		f8_plugin( ADDON, array( 'Name' => 'Element Pack Addon', 'Version' => '1.0.2', 'Author' => 'BdThemes' ), 'network' === $addon ? 'network' : 1 );
		f8_plugin( EP, array( 'Name' => 'Element Pack Pro', 'Version' => '10.0.1', 'Author' => 'BdThemes' ), 'network' === $pro ? 'network' : 1 );
	}
	function site_write_files() {
		$out = array();
		foreach ( f8_writes() as $w ) {
			if ( ! $w[1] ) {
				$out[] = $w[0];
			}
		}
		return $out;
	}
	// 1. Addon y Pro en el sitio: los dos en una escritura.
	addon_case( 'site', 'site' );
	$r = Retire::deactivate( false );
	f8_check( array( array( ADDON, EP ) ) === site_write_files(), 'Addon y Pro del sitio caen juntos en una escritura, el Addon primero.' );
	f8_check( ! $r['codes'], 'Sin avisos de ámbito.' );
	// 2. Addon y Pro en la red: el sitio no toca nada; la red, los dos en una escritura.
	addon_case( 'network', 'network' );
	$r = Retire::deactivate( false );
	f8_check( ! f8_writes() && ! $r['deactivated'], 'El sitio no desactiva lo activado en toda la red.' );
	$r = Retire::deactivate( true, false, array( 'pro', 'core' ) );
	f8_check( 1 === count( f8_writes() ) && array( ADDON, EP ) === f8_writes()[0][0] && f8_writes()[0][1], 'La red los desactiva juntos, en una escritura.' );
	// 3. Addon en la red y Pro en el sitio: el sitio no toca el grupo; la red quita el Addon; después, el sitio, Pro.
	addon_case( 'network', 'site' );
	$r = Retire::deactivate( false );
	f8_check( ! f8_writes() && in_array( 'element-pack', $r['kept'], true ), 'El sitio no quita Pro mientras el Addon siga en la red.' );
	f8_check( 'core.addon_scope' === ( $r['codes'][0][0] ?? '' ), 'Y lo informa (core.addon_scope).' );
	$r = Retire::deactivate( true, false, array( 'pro', 'core' ) );
	f8_check( array( array( ADDON ), true, 1 ) === f8_writes()[0], 'La red quita el Addon.' );
	$r = Retire::deactivate( false );
	f8_check( array( EP ) === site_write_files()[0] && ! in_array( EP, (array) get_option( 'active_plugins' ), true ), 'Después, el sitio quita su Pro.' );
	// 4. Addon en el sitio y Pro en la red: el sitio quita su Addon; la red, Pro (si ningún sitio conserva el Addon).
	addon_case( 'site', 'network' );
	$r = Retire::deactivate( false );
	f8_check( array( array( ADDON ) ) === site_write_files() && active_network( EP ), 'El sitio quita su Addon; Pro sigue en la red.' );
	f8_check( 'core.addon_scope_info' === ( $r['codes'][0][0] ?? '' ), 'Informado (core.addon_scope_info).' );
	$r = Retire::deactivate( true, false, array( 'pro', 'core' ) );
	f8_check( ! active_network( EP ), 'La red quita Pro: ningún sitio conserva el Addon.' );
	addon_case( 'site', 'network' );
	f8_plugin( ADDON, array( 'Name' => 'Element Pack Addon', 'Version' => '1.0.2', 'Author' => 'BdThemes' ), 2 );
	switch_to_blog( 1 );
	Retire::deactivate( false );
	restore_current_blog();
	$r = Retire::deactivate( true, false, array( 'pro', 'core' ) );
	f8_check( active_network( EP ) && 'core.addon_scope' === ( $r['codes'][0][0] ?? '' ), 'Con el Addon aún activo en otro sitio, Pro no cae en la red (el Addon nunca queda sin Pro).' );
	// Barrera con el Addon en la red y Pro por sitio: Pro del sitio se queda hasta la liberación.
	f8_reset( array( 'multisite' => true, 'sites' => 2 ) );
	f8_wpdb();
	Registry::flush_cache();
	f8_plugin( ELE, array( 'Name' => 'DIGITALÍSIMO Elements' ), 'network' );
	f8_plugin( ADDON, array( 'Name' => 'Element Pack Addon', 'Author' => 'BdThemes' ), 'network' );
	f8_plugin( EP, array( 'Name' => 'Element Pack Pro', 'Author' => 'BdThemes' ), 1 );
	f8_plugin( EP, array( 'Name' => 'Element Pack Pro', 'Author' => 'BdThemes' ), 2 );
	foreach ( array( 1, 2 ) as $site ) {
		switch_to_blog( $site );
		update_option( '_bdthemes_builder_header__12', 'yes' );
		restore_current_blog();
	}
	$GLOBALS['wp']['tb_sites']  = array( 1 => true, 2 => true );
	$GLOBALS['wp']['ep_loaded'] = array( 1 => true, 2 => true );
	Runner::network_start( true );
	f8_check( ! empty( Runner::network_state()['barrier'] ), 'El Addon activado en toda la red también crea la barrera.' );
	$a = step( 1 );
	f8_check( ! empty( $a['wait_network'] ) && in_array( EP, (array) $GLOBALS['wp']['options'][1]['active_plugins'], true ), 'Mientras espera, Pro del sitio se queda (el Addon de la red lo necesita).' );
	step( 2 );
	$rel = Runner::network_state();
	if ( empty( $rel['released'] ) ) {
		$rel = Runner::network_release();
	}
	f8_check( ! empty( $rel['released'] ) && ! active_network( ADDON ), 'Al liberar, la red quita el Addon.' );
	$GLOBALS['wp']['ep_loaded'] = array();
	$a = step( 1, 12 );
	f8_check( ! in_array( EP, (array) $GLOBALS['wp']['options'][1]['active_plugins'], true ), 'Y después el sitio quita su Pro.' );
	$addon_writes = array_filter( f8_writes(), static function ( $w ) { return in_array( ADDON, $w[0], true ); } );
	f8_check( 1 === count( $addon_writes ), 'El Addon cae una sola vez.' );
	// Un plugin que parece el Addon y no se reconoce bloquea el grupo (fallar cerrado).
	f8_reset( array( 'multisite' => false ) );
	f8_plugin( EP, array( 'Name' => 'Element Pack Pro', 'Author' => 'BdThemes' ), 'site' );
	f8_plugin( 'carpeta-copia/principal.php', array( 'Name' => 'Element Pack Addon', 'Author' => 'Otro' ), 'site' );
	$r = Retire::deactivate( false );
	f8_check( ! f8_writes() && 'core.addon_unknown' === ( $r['codes'][0][0] ?? '' ), 'Un Addon que no se reconoce (otra carpeta y otro autor) impide desactivar Element Pack.' );
	f8_check( array() === Retire::kept_status(), 'Nada queda «sin desactivar» por regla (KEPT vacío).' );

	/* ================================================================ Versión de Elements (al final: la constante no se puede quitar) */
	net();
	define( 'DIGITALISIMO_ELEMENTS_VERSION', '4.3.0.169' );
	$job = Runner::network_start( true );
	f8_check( ! empty( $job['error'] ) && str_contains( $job['error'], '4.3.0.170' ) && null === Runner::network_state(), 'Con Elements 4.3.0.169, Migrar toda la red no crea tarea y lo dice.' );
	f8_check( ! isset( $GLOBALS['wp']['options'][1][ Runner::JOB ] ) && active_network( EP ), 'Ni toca sitios ni plugins.' );
	f8_check( '' !== (string) Runner::network_response( $job )['error'], 'La respuesta de red lleva el error.' );

	f8_done( 'DIGITALÍSIMO Migración: barrera de red', 90 );
}
