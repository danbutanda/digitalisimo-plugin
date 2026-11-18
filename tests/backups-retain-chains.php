<?php
/**
 * «Conservar» por cadenas en las automatizaciones incrementales, con su estimación (docs/BACKUPS.md, «Incrementales» →
 * «Retención»), sobre la instalación simulada de tests/lib/backups-env.php:
 *
 *   RC1 la estimación (Digitalisimo_Backups_Chain::estimate()) en los ejemplos del pedido (respaldo diario, un completo
 *       cada 6: «Conservar 4 respaldos» → entre 4 y 10 en 1 o 2 cadenas, con el aviso de los días con una sola cadena;
 *       «Conservar 2 cadenas» → entre 8 y 14, siempre una anterior completa), en los bordes (N = 1, cada respaldo
 *       completo, cadena cortada por días, sin programación, «Completo cada vez») y en el tope del historial de 100
 *       filas (N + M respaldos, N × (M + 1) con cadenas);
 *   RC2 retención con días simulados (diario, cada hora, semanal; un completo cada 6, cada 2, cada 60 o cada 3 días;
 *       N = 1, 2, 3; por respaldos y por cadenas) con la retención real (prune_policy()): nunca rompe una cadena, por
 *       cadenas guarda exactamente las N más recientes enteras, y lo que se guarda (respaldos y cadenas, mínimo y máximo)
 *       es exactamente lo que dice la estimación;
 *   RC3 un punto bloqueado de una cadena vieja sigue protegiendo a sus antecesores y no ocupa una de las N cadenas;
 *   RC4 en el remoto: la retención por cadenas anota para borrar las copias remotas de la cadena que sale (también las
 *       de los puntos «sólo en el remoto») y ninguna de las que se quedan;
 *   RC5 con el motor de trabajos real: una automatización guardada con «Conservar 2 cadenas» (formulario) respalda y
 *       retira cadenas enteras; una vieja sin unidad sigue contando respaldos; la especificación sólo lleva la unidad en
 *       el trabajo de una automatización incremental;
 *   RC6 la pantalla: «Programación» y las tarjetas de «Automatizaciones» pintan la unidad (sólo en una incremental), la
 *       estimación del servidor y la ayuda de las cadenas; guardar acota «Conservar» y lo avisa; un formulario sin la
 *       unidad la conserva; pasar a «Completo cada vez» la quita.
 *
 * Debe fallar con el código anterior (sin la unidad: la retención cuenta siempre respaldos y no hay estimación).
 */
require __DIR__ . '/lib/backups-env.php';
require __DIR__ . '/backups-incremental/helpers.php';
if ( ! function_exists( 'wp_create_nonce' ) ) { function wp_create_nonce( $action = '' ) { return 'nonce-' . $action; } }
if ( ! function_exists( 'wp_nonce_url' ) ) { function wp_nonce_url( $url, $action = '' ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . '_wpnonce=nonce-' . $action; } }
if ( ! function_exists( 'get_sites' ) ) { function get_sites() { return array(); } }
if ( ! function_exists( 'wp_timezone_string' ) ) { function wp_timezone_string() { return 'UTC'; } }
if ( ! function_exists( 'rest_url' ) ) { function rest_url( $path = '' ) { return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' ); } }
if ( ! function_exists( 'wp_get_scheduled_event' ) ) { function wp_get_scheduled_event( $hook, $args = array() ) { return false; } }
if ( ! function_exists( 'get_userdata' ) ) { function get_userdata( $id ) { return (object) array( 'user_email' => 'admin@example.test' ); } }
$B = 'Digitalisimo_Backups';
$C = 'Digitalisimo_Backups_Chain';
$failures = 0;

/* ------------------------------------------------------------------ Utilidades */

/** La estimación de una automatización incremental (por defecto: diaria, un completo cada 6 o cada 7 días, local). */
function rc_estimate( array $policy ) {
	return Digitalisimo_Backups_Chain::estimate( $policy + array( 'mode' => 'incremental', 'schedule' => 'daily', 'full_every' => 6, 'full_days' => 7, 'destination' => 'local' ) );
}
function rc_numbers( array $estimate ) {
	return array_intersect_key( $estimate, array_flip( array( 'min', 'max', 'chains_min', 'chains_max', 'previous_min', 'chain_length' ) ) );
}
function rc_history() { return (array) ( $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] ?? array() ); }
function rc_policy_rows( $policy ) { return array_values( array_filter( rc_history(), function( $row ) use ( $policy ) { return $policy === ( $row['policy'] ?? '' ); } ) ); }
/** Cadenas (id => filas, de la más reciente a la más antigua) de las filas de una automatización. */
function rc_chains( array $rows ) {
	$out = array();
	foreach ( $rows as $row ) { $chain = Digitalisimo_Backups_Chain::row_chain( $row ); $out[ $chain ? $chain['id'] : 'file:' . $row['file'] ][] = $row; }
	return $out;
}
/** Ninguna fila del historial se queda sin un eslabón de su cadena (fila y archivo en el almacén, o su copia remota). */
function rc_unbroken( $label ) {
	$history = rc_history();
	$named = array();
	foreach ( $history as $row ) $named[ (string) $row['file'] ] = $row;
	foreach ( $history as $row ) {
		foreach ( Digitalisimo_Backups_Chain::files( $row ) as $file ) {
			inc_check( isset( $named[ $file ] ), $label . ': la retención rompió la cadena de ' . $row['file'] . ' (falta la fila de ' . $file . ')' );
			$local = is_file( rtrim( bk_storage(), '/' ) . '/' . $file );
			inc_check( $local || 'remote-only' === ( $named[ $file ]['local'] ?? '' ) || ! empty( $named[ $file ]['remote_prune'] ), $label . ': la retención borró ' . $file . ', del que depende ' . $row['file'] );
		}
	}
}
/**
 * Una automatización simulada: cada `period` segundos un respaldo nuevo (base o incremental según Chain::plan(), como
 * «preflight»), con su fila y un archivo en el almacén, y después la retención real de su trabajo (prune_policy()).
 * `remote`: copia remota verificada de esta instalación; `remote_only`: además sin ZIP local. Llama a `$each` tras cada
 * retención con el número de respaldo.
 */
function rc_simulate( array $o, $each = null ) {
	$B = 'Digitalisimo_Backups';
	$C = 'Digitalisimo_Backups_Chain';
	$o += array( 'days' => 7, 'remote' => false, 'remote_only' => false, 'start' => 0, 'steps' => 0, 'late' => null );
	$dir = rtrim( bk_storage(), '/' );
	// Como run_policy(): la especificación lleva la frecuencia de la automatización (`period`, para el margen del corte por días).
	$schedule = (string) array_search( $o['period'], $C::PERIODS, true );
	$spec = array( 'policy' => $o['policy'], 'scope' => 'installation', 'incremental' => $C::spec_value( array( 'mode' => 'incremental', 'full_every' => $o['every'], 'full_days' => $o['days'], 'schedule' => $schedule ) ), 'security' => array( 'key_id' => '', 'encrypt' => false ) );
	$stamp = bk_call( $B, 'installation_stamp' );
	for ( $k = $o['start']; $k < $o['start'] + $o['steps']; $k++ ) {
		// La hora fija de cada ejecución y, con `late`, los segundos que WP-Cron o la cola tardan en arrancarla ese día.
		$now = 1767225600 + $k * $o['period'] + ( $o['late'] ? (int) call_user_func( $o['late'], $k ) : 0 );
		$plan = $C::plan( $spec, rc_history(), $now, function() { return true; }, function() { return true; } );
		$backup_id = wp_generate_uuid4();
		$file = sprintf( 'digitalisimo-backup-example-test-%s-%05d.zip', $o['policy'], $k );
		file_put_contents( $dir . '/' . $file, 'punto ' . $k . ' de ' . $o['policy'] );
		$sha = hash_file( 'sha256', $dir . '/' . $file );
		$row = array( 'date' => gmdate( 'Y-m-d H:i:s', $now ), 'file' => $file, 'size' => filesize( $dir . '/' . $file ), 'sha256' => $sha, 'destination' => 'local', 'scope' => 'installation', 'site_id' => 1, 'policy' => $o['policy'], 'type' => 'full', 'backup_type' => $plan['sequence'] > 0 ? 'incremental' : 'full', 'job' => 'sim-' . $k, 'installation' => $stamp,
			'chain' => array( 'v' => 1, 'id' => 'base' === $plan['mode'] ? $backup_id : $plan['id'], 'sequence' => $plan['sequence'], 'backup_id' => $backup_id, 'manifest_sha256' => hash( 'sha256', $file ), 'links' => $plan['links'], 'base_at' => $plan['base_at'], 'index' => array() ) );
		if ( $o['remote'] ) {
			$row = array_merge( $row, array( 'destination' => 'sftp', 'transfer' => 'ok', 'remote' => array( 'provider' => 'sftp', 'connection' => 'sftp', 'path' => '/respaldos/' . $file, 'size' => $row['size'], 'sha256' => $sha ) ) );
			if ( $o['remote_only'] && 1 === $plan['sequence'] % 2 ) { unlink( $dir . '/' . $file ); $row['local'] = 'remote-only'; }
		}
		$history = rc_history();
		array_unshift( $history, $row );
		$GLOBALS['bk_options'][ $B::HISTORY ] = $history;
		bk_call( $B, 'prune_policy', array( 'id' => $o['policy'], 'retain' => $o['retain'] ) + ( isset( $o['unit'] ) ? array( 'unit' => $o['unit'] ) : array() ) );
		if ( $each ) $each( $k, $plan );
	}
}
/** Retira las filas y archivos de una automatización simulada (cada caso empieza de cero). */
function rc_forget( $policy ) {
	foreach ( rc_policy_rows( $policy ) as $row ) @unlink( rtrim( bk_storage(), '/' ) . '/' . $row['file'] );
	$GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] = array_values( array_filter( rc_history(), function( $row ) use ( $policy ) { return $policy !== ( $row['policy'] ?? '' ); } ) );
}
/** Pinta una pestaña de la pantalla con $_GET. */
function rc_render( $tab, array $get = array() ) {
	$_GET = array( 'page' => 'digitalisimo-backups', 'tab' => $tab ) + $get;
	ob_start();
	try { bk_call( 'Digitalisimo_Backups', 'render', false ); } finally { $html = (string) ob_get_clean(); $_GET = array(); }
	return $html;
}

/* ------------------------------------------------------------------ RC1 estimación */

inc_case( 'RC1 estimación: los ejemplos del pedido con respaldo diario y un completo cada 6', function() {
	$four = rc_estimate( array( 'retain' => 4, 'retain_unit' => 'points' ) );
	inc_check( array( 'min' => 4, 'max' => 10, 'chains_min' => 1, 'chains_max' => 2, 'previous_min' => 0, 'chain_length' => 7 ) === rc_numbers( $four ), '«Conservar 4 respaldos»: ' . json_encode( $four ) );
	inc_check( 'Guardarás entre 4 y 10 respaldos en 1 o 2 cadenas. Hay días con una sola cadena: si su base se daña, no hay otra copia.' === $four['text'], 'Texto de «Conservar 4 respaldos» (local): ' . $four['text'] );
	$remote = rc_estimate( array( 'retain' => 4, 'destination' => 'sftp' ) );
	inc_check( 'Guardarás entre 4 y 10 respaldos en 1 o 2 cadenas. Hay días con una sola cadena: si su base se daña, sólo queda la copia remota.' === $remote['text'] && 'points' === $remote['unit'], 'Texto de «Conservar 4 respaldos» con destino remoto: ' . $remote['text'] );
	$two = rc_estimate( array( 'retain' => 2, 'retain_unit' => 'chains' ) );
	inc_check( array( 'min' => 8, 'max' => 14, 'chains_min' => 2, 'chains_max' => 2, 'previous_min' => 1, 'chain_length' => 7 ) === rc_numbers( $two ) && 'chains' === $two['unit'], '«Conservar 2 cadenas»: ' . json_encode( $two ) );
	inc_check( 'Guardarás entre 8 y 14 respaldos en 2 cadenas. Siempre queda al menos una cadena anterior completa.' === $two['text'], 'Texto de «Conservar 2 cadenas»: ' . $two['text'] );
	$three = rc_estimate( array( 'retain' => 3, 'retain_unit' => 'chains' ) );
	inc_check( array( 15, 21, 2 ) === array( $three['min'], $three['max'], $three['previous_min'] ) && false !== strpos( $three['text'], 'Siempre quedan al menos 2 cadenas anteriores completas.' ), '«Conservar 3 cadenas»: ' . json_encode( $three ) );
	$eight = rc_estimate( array( 'retain' => 8 ) );
	inc_check( array( 8, 14, 2, 2, 1 ) === array( $eight['min'], $eight['max'], $eight['chains_min'], $eight['chains_max'], $eight['previous_min'] ) && false !== strpos( $eight['text'], 'Siempre queda al menos una cadena anterior completa.' ), '«Conservar 8 respaldos» ya guarda siempre una cadena anterior: ' . json_encode( $eight ) );
} );

inc_case( 'RC1 estimación en los bordes: N = 1, cadenas cortadas por días, cada hora, semanal, sin programación y «Completo cada vez»', function() {
	$one = rc_estimate( array( 'retain' => 1, 'retain_unit' => 'chains' ) );
	inc_check( array( 1, 7, 1, 1, 0 ) === array( $one['min'], $one['max'], $one['chains_min'], $one['chains_max'], $one['previous_min'] ) && false !== strpos( $one['text'], 'Una sola cadena: si su base se daña, no hay otra copia.' ), '«Conservar 1 cadena»: ' . json_encode( $one ) );
	$point = rc_estimate( array( 'retain' => 1 ) );
	inc_check( array( 1, 7, 1, 1 ) === array( $point['min'], $point['max'], $point['chains_min'], $point['chains_max'] ) && false !== strpos( $point['text'], 'Una sola cadena' ), '«Conservar 1 respaldo»: ' . json_encode( $point ) );
	$hourly = rc_estimate( array( 'retain' => 4, 'schedule' => 'hourly' ) );
	inc_check( 7 === $hourly['chain_length'] && false !== strpos( $hourly['text'], 'Hay horas con una sola cadena' ), 'Cada hora la cadena es de 7 y el aviso habla de horas: ' . json_encode( $hourly ) );
	$cut = rc_estimate( array( 'retain' => 2, 'retain_unit' => 'chains', 'schedule' => 'hourly', 'full_every' => 30, 'full_days' => 1 ) );
	inc_check( array( 24, 25, 48 ) === array( $cut['chain_length'], $cut['min'], $cut['max'] ) && 'Guardarás entre 25 y 48 respaldos en 2 cadenas. Siempre queda al menos una cadena anterior completa.' === $cut['text'] && 'Con esta frecuencia la cadena se corta por días (un completo cada 1 día): 24 respaldos.' === $cut['detail'], 'Cada hora con un completo cada 30 o cada día, la cadena se corta a 24: ' . json_encode( $cut ) );
	// El tope sigue siendo el del número (N × (M + 1)): «Ejecutar ahora» también suma puntos a la cadena el mismo día.
	$capped = rc_estimate( array( 'retain' => 2, 'retain_unit' => 'chains', 'schedule' => 'hourly', 'full_every' => 60, 'full_days' => 1 ) );
	inc_check( 1 === $capped['retain'] && 24 === $capped['max'] && false !== strpos( $capped['text'], 'Una sola cadena: si su base se daña, no hay otra copia.' ) && '«Conservar» quedará en 1.' === substr( $capped['detail'], -strlen( '«Conservar» quedará en 1.' ) ), 'Con un completo cada 60 sólo cabe una cadena aunque la frecuencia la corte antes: ' . json_encode( $capped ) );
	$daily_cut = rc_estimate( array( 'retain' => 4, 'full_days' => 3 ) );
	inc_check( array( 3, 4, 6, 2, 2 ) === array( $daily_cut['chain_length'], $daily_cut['min'], $daily_cut['max'], $daily_cut['chains_min'], $daily_cut['chains_max'] ), 'Diario con un completo cada 3 días: ' . json_encode( $daily_cut ) );
	$weekly = rc_estimate( array( 'retain' => 4, 'schedule' => 'weekly' ) );
	inc_check( array( 1, 4, 4, 4, 4, 3 ) === array( $weekly['chain_length'], $weekly['min'], $weekly['max'], $weekly['chains_min'], $weekly['chains_max'], $weekly['previous_min'] ) && 'Con esta frecuencia cada respaldo sale completo (un completo cada 7 días).' === $weekly['detail'], 'Semanal con un completo cada 7 días: cada respaldo es completo: ' . json_encode( $weekly ) );
	$monthly = rc_estimate( array( 'retain' => 2, 'retain_unit' => 'chains', 'schedule' => 'monthly', 'full_days' => 90 ) );
	inc_check( 3 === $monthly['chain_length'] && 4 === $monthly['min'] && 6 === $monthly['max'], 'Mensual con un completo cada 90 días: 3 por cadena: ' . json_encode( $monthly ) );
	$manual = rc_estimate( array( 'retain' => 2, 'retain_unit' => 'chains', 'schedule' => 'disabled' ) );
	inc_check( 7 === $manual['chain_length'] && 'Cada cadena: un completo y 6 incrementales (7 respaldos).' === $manual['detail'], 'Sin programación la cadena sólo se corta por número: ' . json_encode( $manual ) );
	$full = Digitalisimo_Backups_Chain::estimate( array( 'mode' => 'full', 'retain' => 4, 'retain_unit' => 'chains', 'schedule' => 'daily' ) );
	inc_check( array( 'points', 4, 4, 4, 4 ) === array( $full['unit'], $full['min'], $full['max'], $full['chains_min'], $full['chains_max'] ) && 'Guardarás 4 respaldos completos.' === $full['text'], '«Completo cada vez»: la unidad no aplica y se guardan N: ' . json_encode( $full ) );
	inc_check( 'Guardarás 1 respaldo completo.' === Digitalisimo_Backups_Chain::estimate( array( 'retain' => 1 ) )['text'], 'Una automatización sin modo es «Completo cada vez».' );
	// Una automatización anterior, sin unidad, cuenta respaldos; una unidad desconocida también.
	inc_check( 'points' === Digitalisimo_Backups_Chain::retain_unit( array( 'mode' => 'incremental' ) ) && 'points' === Digitalisimo_Backups_Chain::retain_unit( array( 'mode' => 'incremental', 'retain_unit' => 'otra' ) ) && 'points' === Digitalisimo_Backups_Chain::retain_unit( array( 'mode' => 'full', 'retain_unit' => 'chains' ) ) && 'chains' === Digitalisimo_Backups_Chain::retain_unit( array( 'mode' => 'incremental', 'retain_unit' => 'chains' ) ), 'La unidad no se resuelve como se espera.' );
} );

inc_case( 'RC1 tope del historial: N + M respaldos o N × (M + 1) con cadenas caben en 100 filas, y la estimación lo dice', function() {
	$C = 'Digitalisimo_Backups_Chain';
	inc_check( array( 94, 14, 1, 50, 40, 33 ) === array( $C::retain_cap( 'points', 6 ), $C::retain_cap( 'chains', 6 ), $C::retain_cap( 'chains', 60 ), $C::retain_cap( 'chains', 1 ), $C::retain_cap( 'points', 60 ), $C::retain_cap( 'chains', 2 ) ), 'Topes: ' . json_encode( array( $C::retain_cap( 'points', 6 ), $C::retain_cap( 'chains', 6 ), $C::retain_cap( 'chains', 60 ), $C::retain_cap( 'chains', 1 ), $C::retain_cap( 'points', 60 ), $C::retain_cap( 'chains', 2 ) ) ) );
	foreach ( array( 1, 2, 6, 13, 30, 60 ) as $every ) {
		$cap = $C::retain_cap( 'chains', $every );
		inc_check( $cap * ( $every + 1 ) <= 100 && ( $cap + 1 ) * ( $every + 1 ) > 100 || 1 === $cap, 'El tope de cadenas con un completo cada ' . $every . ' no es el mayor que cabe: ' . $cap );
		$max = rc_estimate( array( 'retain' => 100, 'retain_unit' => 'chains', 'full_every' => $every, 'schedule' => 'disabled' ) );
		inc_check( $max['retain'] === $cap && $max['max'] <= 100 && false !== strpos( $max['detail'], '«Conservar» quedará en ' . $cap . '.' ) && false === strpos( $max['text'], 'quedará' ), 'La estimación no acota «Conservar 100 cadenas» con un completo cada ' . $every . ': ' . json_encode( $max ) );
		$points = rc_estimate( array( 'retain' => 100, 'full_every' => $every, 'schedule' => 'disabled' ) );
		inc_check( $points['retain'] === 100 - $every && $points['max'] <= 100, 'La estimación no acota «Conservar 100 respaldos» con un completo cada ' . $every . ': ' . json_encode( $points ) );
	}
	$fits = rc_estimate( array( 'retain' => 14, 'retain_unit' => 'chains' ) );
	inc_check( 98 === $fits['max'] && false === strpos( $fits['text'] . $fits['detail'], 'quedará' ), '«Conservar 14 cadenas» con un completo cada 6 cabe: ' . json_encode( $fits ) );
	$zero = rc_estimate( array( 'retain' => 0, 'retain_unit' => 'chains' ) );
	inc_check( 1 === $zero['retain'] && 1 === $zero['min'], '«Conservar 0» cuenta como 1.' );
} );

/* ------------------------------------------------------------------ RC2 retención con días simulados */

inc_case( 'RC2 retención con días simulados: nunca rompe una cadena y guarda exactamente lo que dice la estimación', function() {
	$configs = array(
		array( 'daily', 86400, 6, 7 ), array( 'daily', 86400, 2, 7 ), array( 'hourly', 3600, 6, 7 ), array( 'hourly', 3600, 2, 7 ),
		array( 'daily', 86400, 6, 3 ), array( 'weekly', 604800, 6, 7 ), array( 'hourly', 3600, 30, 1 ),
	);
	$checked = 0;
	foreach ( $configs as list( $schedule, $period, $every, $days ) ) {
		foreach ( array( 1, 2, 3 ) as $n ) {
			foreach ( array( 'points', 'chains' ) as $unit ) {
				$label = $schedule . ', un completo cada ' . $every . ' o ' . $days . ' días, «Conservar ' . $n . ' ' . ( 'chains' === $unit ? 'cadenas' : 'respaldos' ) . '»';
				$estimate = rc_estimate( array( 'retain' => $n, 'retain_unit' => $unit, 'schedule' => $schedule, 'full_every' => $every, 'full_days' => $days ) );
				$length = $estimate['chain_length'];
				$warm = ( $n + 2 ) * $length;
				$seen = array( 'min' => PHP_INT_MAX, 'max' => 0, 'chains_min' => PHP_INT_MAX, 'chains_max' => 0, 'previous_min' => PHP_INT_MAX );
				$policy = 'sim-' . $checked;
				try {
				rc_simulate( array( 'policy' => $policy, 'period' => $period, 'every' => $every, 'days' => $days, 'retain' => $n, 'unit' => $unit, 'steps' => $warm + 2 * $length ), function( $k, $plan ) use ( &$seen, $policy, $label, $warm, $length, $n, $unit ) {
					rc_unbroken( $label );
					$rows = rc_policy_rows( $policy );
					$chains = rc_chains( $rows );
					$ids = array_keys( $chains );
					// Toda cadena menos la actual está entera: L respaldos.
					foreach ( array_slice( $ids, 1 ) as $id ) inc_check( count( $chains[ $id ] ) === $length, $label . ': quedó una cadena anterior a medias (' . count( $chains[ $id ] ) . ' de ' . $length . ')' );
					if ( 'chains' === $unit && $k >= $n * $length ) inc_check( count( $chains ) === $n, $label . ': por cadenas se guardan ' . count( $chains ) . ' cadenas, no ' . $n );
					if ( $k < $warm ) return;
					$seen['min'] = min( $seen['min'], count( $rows ) );
					$seen['max'] = max( $seen['max'], count( $rows ) );
					$seen['chains_min'] = min( $seen['chains_min'], count( $chains ) );
					$seen['chains_max'] = max( $seen['chains_max'], count( $chains ) );
					$seen['previous_min'] = min( $seen['previous_min'], count( $chains ) - 1 );
				} );
				$expected = array_intersect_key( $estimate, $seen );
				ksort( $expected );
				ksort( $seen );
				inc_check( $expected === $seen, $label . ': la retención guardó ' . json_encode( $seen ) . ' y la estimación dice ' . json_encode( $expected ) );
				// En el almacén quedan exactamente los archivos de las filas.
				$files = array_map( 'basename', glob( rtrim( bk_storage(), '/' ) . '/digitalisimo-backup-example-test-' . $policy . '-*' ) );
				$named = array_column( rc_policy_rows( $policy ), 'file' );
				sort( $files );
				sort( $named );
				inc_check( $files === $named, $label . ': el almacén y el historial no coinciden.' );
				} finally {
					rc_forget( $policy );
				}
				$checked++;
			}
		}
	}
	inc_check( 42 === $checked, 'Se simularon ' . $checked . ' automatizaciones.' );
} );

inc_case( 'RC2 «Conservar 2 cadenas» diario con un completo cada 6: la cadena anterior sale entera el día que la tercera empieza', function() {
	$kept = array();
	rc_simulate( array( 'policy' => 'dos', 'period' => 86400, 'every' => 6, 'retain' => 2, 'unit' => 'chains', 'steps' => 22 ), function( $k ) use ( &$kept ) {
		$kept[ $k ] = count( rc_policy_rows( 'dos' ) );
	} );
	// Días 0–13: se acumulan las dos primeras cadenas (1..14); el día 14 empieza la tercera y la primera sale entera (8).
	inc_check( array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 8, 9, 10, 11, 12, 13, 14, 8 ) === array_values( $kept ), 'Respaldos guardados cada día: ' . json_encode( array_values( $kept ) ) );
	$points = array();
	rc_simulate( array( 'policy' => 'cuatro', 'period' => 86400, 'every' => 6, 'retain' => 4, 'steps' => 22 ), function( $k ) use ( &$points ) {
		$points[ $k ] = array( count( rc_policy_rows( 'cuatro' ) ), count( rc_chains( rc_policy_rows( 'cuatro' ) ) ) );
	} );
	// Sin unidad (una automatización anterior) cuenta respaldos: 4 y su cadena; casi toda la semana, una sola cadena.
	// Desde el día 10 (el 7 empieza la segunda cadena): 4 a 10 respaldos y, de cada 7 días, 4 con una sola cadena.
	inc_check( array( 4, 5, 6, 7, 8, 9, 10, 4, 5, 6, 7, 8 ) === array_column( array_slice( $points, 10 ), 0 ), 'Respaldos de «Conservar 4» (sin unidad): ' . json_encode( array_column( $points, 0 ) ) );
	inc_check( array( 1, 1, 1, 1, 2, 2, 2, 1, 1, 1, 1, 2 ) === array_column( array_slice( $points, 10 ), 1 ), 'Cadenas de «Conservar 4» (sin unidad): ' . json_encode( array_column( $points, 1 ) ) );
	rc_forget( 'dos' );
	rc_forget( 'cuatro' );
} );

inc_case( 'RC2 por cadenas, cada completo anterior a la cadena (sin bloque `chain`) cuenta como una cadena él solo', function() {
	$dir = rtrim( bk_storage(), '/' );
	$history = rc_history();
	foreach ( array( 0, 1, 2 ) as $i ) {
		$file = sprintf( 'digitalisimo-backup-example-test-antes-%05d.zip', $i );
		file_put_contents( $dir . '/' . $file, 'completo ' . $i );
		array_unshift( $history, array( 'date' => '2026-01-0' . ( $i + 1 ) . ' 00:00:00', 'file' => $file, 'sha256' => hash_file( 'sha256', $dir . '/' . $file ), 'destination' => 'local', 'scope' => 'installation', 'policy' => 'antes', 'type' => 'full', 'backup_type' => 'full', 'job' => 'antes-' . $i ) );
	}
	$GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] = $history;
	// La automatización pasa a incremental con «Conservar 2 cadenas»: su primer punto es una base nueva.
	rc_simulate( array( 'policy' => 'antes', 'period' => 86400, 'every' => 6, 'retain' => 2, 'unit' => 'chains', 'steps' => 1, 'start' => 10 ) );
	$files = array_column( rc_policy_rows( 'antes' ), 'file' );
	inc_check( array( 'digitalisimo-backup-example-test-antes-00010.zip', 'digitalisimo-backup-example-test-antes-00002.zip' ) === $files, 'Debían quedar la base nueva y el último completo de antes: ' . json_encode( $files ) );
	inc_check( ! is_file( $dir . '/digitalisimo-backup-example-test-antes-00000.zip' ) && ! is_file( $dir . '/digitalisimo-backup-example-test-antes-00001.zip' ) && is_file( $dir . '/digitalisimo-backup-example-test-antes-00002.zip' ), 'Los completos de antes no salieron uno a uno.' );
	rc_forget( 'antes' );
} );

/* ------------------------------------------------------------------ RC3 bloqueados */

inc_case( 'RC3 un punto bloqueado de una cadena que sale la protege hasta él y no ocupa una de las N cadenas', function() {
	$name = function( $k ) { return sprintf( 'digitalisimo-backup-example-test-bloq-%05d.zip', $k ); };
	$run = function( $start, $steps ) { rc_simulate( array( 'policy' => 'bloq', 'period' => 86400, 'every' => 2, 'retain' => 2, 'unit' => 'chains', 'steps' => $steps, 'start' => $start ) ); };
	$files = function() { return array_column( rc_policy_rows( 'bloq' ), 'file' ); };
	$run( 0, 3 );
	inc_check( array( $name( 2 ), $name( 1 ), $name( 0 ) ) === $files(), 'La primera cadena no está entera: ' . json_encode( $files() ) );
	// Bloqueado el incremental 1 de la primera cadena.
	foreach ( $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] as &$row ) if ( $name( 1 ) === $row['file'] ) $row['locked'] = true;
	unset( $row );
	// La segunda cadena (3, 4, 5) y la base de la tercera (6): la primera sale menos el bloqueado y su base.
	$run( 3, 4 );
	rc_unbroken( 'bloqueado' );
	inc_check( array( $name( 6 ), $name( 5 ), $name( 4 ), $name( 3 ), $name( 1 ), $name( 0 ) ) === $files(), 'Con «Conservar 2 cadenas» y un incremental bloqueado debían quedar la tercera y la segunda cadenas, el bloqueado y su base: ' . json_encode( $files() ) );
	// Lo que queda por el bloqueado no ocupa una de las 2: la segunda cadena sigue entera mientras la tercera crece.
	$run( 7, 1 );
	rc_unbroken( 'bloqueado, la tercera crece' );
	inc_check( array( $name( 7 ), $name( 6 ), $name( 5 ), $name( 4 ), $name( 3 ), $name( 1 ), $name( 0 ) ) === $files(), 'Lo bloqueado ocupó una de las 2 cadenas (la segunda debía seguir entera): ' . json_encode( $files() ) );
	// Desbloqueado, la siguiente retención retira lo que quedaba de la primera.
	foreach ( $GLOBALS['bk_options'][ Digitalisimo_Backups::HISTORY ] as &$row ) unset( $row['locked'] );
	unset( $row );
	$run( 8, 1 );
	rc_unbroken( 'desbloqueado' );
	inc_check( array( $name( 8 ), $name( 7 ), $name( 6 ), $name( 5 ), $name( 4 ), $name( 3 ) ) === $files(), 'Desbloqueado, lo que quedaba de la primera cadena no salió: ' . json_encode( $files() ) );
	rc_forget( 'bloq' );
} );

/* ------------------------------------------------------------------ RC4 remoto */

inc_case( 'RC4 en el remoto: la cadena que sale anota sus copias remotas para borrar (también las «sólo en el remoto») y las que se quedan, no', function() {
	rc_simulate( array( 'policy' => 'remota', 'period' => 86400, 'every' => 2, 'retain' => 1, 'unit' => 'chains', 'steps' => 3, 'remote' => true, 'remote_only' => true ) );
	foreach ( rc_policy_rows( 'remota' ) as $row ) inc_check( empty( $row['remote_prune'] ), 'Una copia remota de la cadena que se queda quedó para borrar: ' . $row['file'] );
	$old = array_column( rc_policy_rows( 'remota' ), 'file' );
	rc_simulate( array( 'policy' => 'remota', 'period' => 86400, 'every' => 2, 'retain' => 1, 'unit' => 'chains', 'steps' => 1, 'start' => 3, 'remote' => true, 'remote_only' => true ) );
	rc_unbroken( 'remota' );
	$rows = array();
	foreach ( rc_policy_rows( 'remota' ) as $row ) $rows[ $row['file'] ] = $row;
	foreach ( $old as $file ) {
		inc_check( isset( $rows[ $file ] ) && 'pending' === ( $rows[ $file ]['remote_prune']['state'] ?? '' ), 'La copia remota de ' . $file . ' (cadena que sale) no quedó para borrar: ' . json_encode( $rows[ $file ] ?? null ) );
		inc_check( ! is_file( rtrim( bk_storage(), '/' ) . '/' . $file ), 'El ZIP local de ' . $file . ' (cadena que sale) sigue en el almacén.' );
	}
	$new = sprintf( 'digitalisimo-backup-example-test-remota-%05d.zip', 3 );
	inc_check( empty( $rows[ $new ]['remote_prune'] ) && is_file( rtrim( bk_storage(), '/' ) . '/' . $new ), 'La base de la cadena nueva quedó para borrar.' );
	// Con «Conservar 2 cadenas» la anterior se queda con sus copias remotas.
	rc_forget( 'remota' );
	rc_simulate( array( 'policy' => 'remota2', 'period' => 86400, 'every' => 2, 'retain' => 2, 'unit' => 'chains', 'steps' => 6, 'remote' => true ) );
	inc_check( 6 === count( rc_policy_rows( 'remota2' ) ) && ! array_filter( rc_policy_rows( 'remota2' ), function( $row ) { return ! empty( $row['remote_prune'] ); } ), 'Con «Conservar 2 cadenas» se anotó para borrar una copia remota de una cadena que se queda.' );
	rc_simulate( array( 'policy' => 'remota2', 'period' => 86400, 'every' => 2, 'retain' => 2, 'unit' => 'chains', 'steps' => 1, 'start' => 6, 'remote' => true ) );
	$pending = array_values( array_map( function( $row ) { return $row['file']; }, array_filter( rc_policy_rows( 'remota2' ), function( $row ) { return ! empty( $row['remote_prune'] ); } ) ) );
	sort( $pending );
	inc_check( array( sprintf( 'digitalisimo-backup-example-test-remota2-%05d.zip', 0 ), sprintf( 'digitalisimo-backup-example-test-remota2-%05d.zip', 1 ), sprintf( 'digitalisimo-backup-example-test-remota2-%05d.zip', 2 ) ) === $pending, 'Al empezar la tercera cadena sólo la primera debía quedar para borrar en el remoto: ' . json_encode( $pending ) );
	rc_forget( 'remota2' );
} );

/* ------------------------------------------------------------------ RC5 motor real */

inc_case( 'RC5 motor real: «Conservar 2 cadenas» guardada por el formulario respalda y retira cadenas enteras', function() use ( $B, $C ) {
	list( $ok, $message ) = bk_post( array( $B, 'create_automation' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_CREATE_AUTOMATION, 'digitalisimo_backup_automation' => array( 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 2, 'retain_unit' => 'chains', 'mode' => 'incremental', 'full_every' => 2, 'full_days' => 7 ) ) );
	inc_check( $ok, 'No se creó la automatización: ' . $message );
	$policy = null;
	foreach ( $B::policies() as $item ) if ( 'chains' === ( $item['retain_unit'] ?? '' ) ) $policy = $item;
	inc_check( $policy && 2 === (int) $policy['retain'] && 'incremental' === $policy['mode'], 'La automatización no guardó «Conservar 2 cadenas»: ' . json_encode( $B::policies() ) );
	$made = array();
	for ( $i = 0; $i < 7; $i++ ) {
		inc_put( 'wp-content/uploads/2026/11/cadenas.txt', 'vuelta ' . $i );
		$queued = $B::run_policy( $policy['id'], 0, 'test' );
		inc_check( is_array( $queued ) && ! empty( $queued['job']['id'] ), 'La automatización no se encoló: ' . json_encode( $queued ) );
		inc_check( 'chains' === ( $queued['job']['spec']['retain_unit'] ?? '' ), 'El trabajo de la automatización no lleva «Conservar 2 cadenas»: ' . json_encode( $queued['job']['spec'] ) );
		for ( $t = 0; $t < 400; $t++ ) {
			$job = Digitalisimo_Backups_Jobs::get( $queued['job']['id'] );
			if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
			Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
		}
		inc_check( 'success' === $job['status'], 'El respaldo ' . $i . ' no terminó: ' . json_encode( $job['error'] ) );
		$made[] = basename( (string) $job['spec']['file'] );
		$rows = rc_policy_rows( $policy['id'] );
		$expected = array( 1, 2, 3, 4, 5, 6, 4 )[ $i ];
		inc_check( $expected === count( $rows ), 'Tras el respaldo ' . $i . ' debían quedar ' . $expected . ' (dos cadenas enteras de 3): ' . json_encode( array_column( $rows, 'file' ) ) );
		foreach ( $rows as $row ) foreach ( $C::files( $row ) as $file ) inc_check( is_file( rtrim( bk_storage(), '/' ) . '/' . $file ), 'La retención rompió la cadena de ' . $row['file'] );
	}
	// El séptimo empieza la tercera cadena: la primera (respaldos 0, 1 y 2) sale entera; quedan la segunda y la nueva.
	foreach ( array( 0, 1, 2 ) as $gone ) inc_check( ! is_file( rtrim( bk_storage(), '/' ) . '/' . $made[ $gone ] ) && ! inc_row( $made[ $gone ] ), 'Quedó un respaldo de la primera cadena: ' . $made[ $gone ] );
	foreach ( array( 3, 4, 5, 6 ) as $kept ) inc_check( is_file( rtrim( bk_storage(), '/' ) . '/' . $made[ $kept ] ) && inc_row( $made[ $kept ] ), 'Falta un respaldo de las dos cadenas que se conservan: ' . $made[ $kept ] );
	$named = array();
	foreach ( rc_history() as $row ) { $chain = $C::row_chain( $row ); if ( $chain && ! empty( $chain['index']['file'] ) ) $named[] = $chain['index']['file']; }
	foreach ( glob( rtrim( bk_storage(), '/' ) . '/chains/*' ) as $index ) inc_check( in_array( basename( $index ), $named, true ), 'Quedó un índice lateral huérfano: ' . basename( $index ) );
	$listed = array_values( array_filter( $B::service_automations(), function( $row ) use ( $policy ) { return $row['id'] === $policy['id']; } ) );
	inc_check( 'chains' === $listed[0]['retain_unit'] && 'Guardarás entre 4 y 6 respaldos en 2 cadenas. Siempre queda al menos una cadena anterior completa.' === $listed[0]['retain_estimate']['text'], 'La lista de automatizaciones no dice la unidad ni la estimación: ' . json_encode( $listed[0] ) );
} );

inc_case( 'RC5 una automatización incremental anterior (sin unidad) sigue contando respaldos; la unidad sólo viaja en su trabajo', function() use ( $B ) {
	$policies = $B::policies();
	$policies[] = array( 'id' => 'vieja-inc', 'label' => '', 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 2, 'enabled' => true, 'time' => '', 'remote_only' => false, 'mode' => 'incremental', 'full_every' => 2, 'full_days' => 7 );
	update_option( $B::POLICIES, $policies, false );
	$listed = array_values( array_filter( $B::service_automations(), function( $row ) { return 'vieja-inc' === $row['id']; } ) );
	inc_check( 'points' === $listed[0]['retain_unit'] && 'Guardarás entre 2 y 4 respaldos en 1 o 2 cadenas. Hay días con una sola cadena: si su base se daña, no hay otra copia.' === $listed[0]['retain_estimate']['text'], 'La automatización vieja no cuenta respaldos: ' . json_encode( $listed[0] ) );
	$made = array();
	for ( $i = 0; $i < 4; $i++ ) {
		inc_put( 'wp-content/uploads/2026/11/vieja.txt', 'vuelta ' . $i );
		$queued = $B::run_policy( 'vieja-inc', 0, 'test' );
		inc_check( ! isset( $queued['job']['spec']['retain_unit'] ), 'El trabajo de una automatización vieja lleva una unidad.' );
		for ( $t = 0; $t < 400; $t++ ) {
			$job = Digitalisimo_Backups_Jobs::get( $queued['job']['id'] );
			if ( Digitalisimo_Backups_Job_Store::is_terminal( $job ) ) break;
			Digitalisimo_Backups_Jobs::tick( PHP_INT_MAX );
		}
		$made[] = basename( (string) $job['spec']['file'] );
	}
	// Respaldos 0 (base), 1, 2 | 3 (base): con «Conservar 2» quedan el 3 y el 2, y el 1 y el 0, de los que depende el 2.
	$kept = array_column( rc_policy_rows( 'vieja-inc' ), 'file' );
	inc_check( array( $made[3], $made[2], $made[1], $made[0] ) === $kept, 'Una automatización sin unidad no contó respaldos: ' . json_encode( $kept ) );
	// La especificación: sólo el trabajo de una automatización (retención «policy») incremental lleva la unidad.
	$J = 'Digitalisimo_Backups_Jobs';
	inc_check( 'chains' === ( $J::spec( array( 'policy' => 'x', 'retention' => 'policy', 'retain_unit' => 'chains', 'incremental' => array( 'full_every' => 2 ) ) )['retain_unit'] ?? '' ), 'La especificación de una incremental no lleva la unidad.' );
	inc_check( ! isset( $J::spec( array( 'policy' => 'x', 'retention' => 'policy', 'retain_unit' => 'chains' ) )['retain_unit'] ), 'Una automatización completa lleva la unidad en su trabajo.' );
	inc_check( ! isset( $J::spec( array( 'policy' => 'manual', 'retention' => 'global', 'retain_unit' => 'chains', 'incremental' => array( 'full_every' => 2 ) ) )['retain_unit'] ), 'Un respaldo manual (retención global) lleva la unidad.' );
	inc_check( ! isset( $J::spec( array( 'policy' => 'x', 'retention' => 'policy', 'retain_unit' => 'otra', 'incremental' => array( 'full_every' => 2 ) ) )['retain_unit'] ), 'Una unidad desconocida viaja en el trabajo.' );
} );

/* ------------------------------------------------------------------ RC6 pantalla */

inc_case( 'RC6 pantalla: «Programación» y las tarjetas pintan la unidad, la estimación del servidor y la ayuda de las cadenas', function() use ( $B ) {
	$saved = get_option( $B::POLICIES );
	try {
		update_option( $B::POLICIES, array( array( 'id' => 'principal', 'label' => '', 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 2, 'retain_unit' => 'chains', 'enabled' => true, 'time' => '', 'remote_only' => false, 'mode' => 'incremental', 'full_every' => 6, 'full_days' => 7 ), array( 'id' => 'completa', 'label' => '', 'type' => 'full', 'schedule' => 'weekly', 'destination' => 'local', 'retain' => 3, 'enabled' => true, 'time' => '', 'remote_only' => false ) ), false );
		$settings = rc_render( 'settings' );
		inc_check( preg_match( '#<form method="post"[^>]*class="digitalisimo-backups-schedule">(.*?)</form>#s', $settings, $form ), 'No se pintó «Programación».' );
		inc_check( preg_match( '#<input id="digitalisimo-backups-retain" type="number" min="1" max="100" name="digitalisimo_backup_automation\[retain\]" value="2"> <select class="digitalisimo-backups-retain-unit" name="digitalisimo_backup_automation\[retain_unit\]" aria-label="Qué cuenta «Conservar»" data-retain-unit><option value="points">respaldos</option><option value="chains" selected="selected">cadenas</option></select><span data-retain-noun hidden>respaldos</span>#', $form[1] ), '«Programación» no pinta «Conservar 2 cadenas»: ' . $form[1] );
		inc_check( false !== strpos( $form[1], '<p class="description digitalisimo-backups-retain-estimate" data-retain-estimate aria-live="polite">Guardarás entre 8 y 14 respaldos en 2 cadenas. Siempre queda al menos una cadena anterior completa.</p>' ), '«Programación» no pinta la estimación del servidor.' );
		inc_check( preg_match( '#<div data-retain-chains-help><p class="description" data-retain-detail>Cada cadena: un completo y 6 incrementales \(7 respaldos\)\.</p><p class="description">Una cadena es un completo y los incrementales que dependen de él: si su completo se daña, ninguno de sus puntos se restaura\.</p><details class="digitalisimo-backups-help"><summary>Más</summary>.*?«Conservar 2 cadenas» o más deja siempre una cadena anterior entera como plan B#s', $form[1] ), 'Falta la ayuda de las cadenas: ' . $form[1] );
		$tools = rc_render( 'tools', array( 'open' => 'automations' ) );
		inc_check( 3 === substr_count( $tools, 'data-retain-estimate' ) && false !== strpos( $tools, 'Guardarás 3 respaldos completos.' ) && false !== strpos( $tools, 'Guardarás entre 8 y 14 respaldos en 2 cadenas.' ), 'Las tarjetas de las automatizaciones no pintan su estimación.' );
		// En «Crear automatización» (completo por defecto) la unidad y la ayuda están ocultas y se ve «respaldos».
		inc_check( preg_match( '#<h3>Crear automatización</h3>.*?<select class="digitalisimo-backups-retain-unit" name="digitalisimo_backup_automation\[retain_unit\]" aria-label="Qué cuenta «Conservar»" data-retain-unit hidden>.*?<span data-retain-noun>respaldos</span>.*?data-retain-estimate aria-live="polite">Guardarás 4 respaldos completos\.</p><div data-retain-chains-help hidden>#s', $tools ), '«Crear automatización» no oculta la unidad de un completo.' );
		// En las tarjetas, «Conservar» va después del modo y justo antes de su estimación (no entre la frecuencia y la hora).
		inc_check( preg_match( '#name="digitalisimo_backup_automation\[full_days\]"[^>]*> días</label></p>.*?<p class="description">Cada incremental es un punto.*?<p><label>Conservar<br><input type="number"[^>]*value="2"> <select[^>]*data-retain-unit>.*?</label></p><p class="description digitalisimo-backups-retain-estimate"#s', $tools ), '«Conservar» no está junto a su estimación en la tarjeta.' );
		// La estimación a la vista es corta aunque la cadena se corte por días; el porqué va en la ayuda.
		$policies = get_option( $B::POLICIES );
		$policies[] = array( 'id' => 'horaria', 'label' => '', 'type' => 'full', 'schedule' => 'hourly', 'destination' => 'sftp', 'retain' => 2, 'retain_unit' => 'chains', 'enabled' => true, 'time' => '', 'remote_only' => false, 'mode' => 'incremental', 'full_every' => 30, 'full_days' => 1 );
		update_option( $B::POLICIES, $policies, false );
		$estimate = Digitalisimo_Backups_Chain::estimate( end( $policies ) );
		$tools = rc_render( 'tools', array( 'open' => 'automations' ) );
		inc_check( mb_strlen( $estimate['text'], 'UTF-8' ) < 140 && false !== strpos( $tools, 'data-retain-estimate aria-live="polite">' . esc_html( $estimate['text'] ) . '</p><div data-retain-chains-help><p class="description" data-retain-detail>Con esta frecuencia la cadena se corta por días (un completo cada 1 día): 24 respaldos.</p>' ), 'La estimación de una cadena cortada por días no es corta con su detalle en la ayuda: ' . json_encode( $estimate ) );
		// Con la principal completa, «Programación» no enseña la unidad ni la ayuda.
		update_option( $B::POLICIES, array( array( 'id' => 'monthly-full', 'label' => '', 'type' => 'full', 'schedule' => 'monthly', 'destination' => 'local', 'retain' => 4, 'enabled' => true ) ), false );
		$plain = rc_render( 'settings' );
		inc_check( false !== strpos( $plain, 'data-retain-unit hidden>' ) && false !== strpos( $plain, '<span data-retain-noun>respaldos</span>' ) && false !== strpos( $plain, '<div data-retain-chains-help hidden>' ) && false !== strpos( $plain, '>Guardarás 4 respaldos completos.</p>' ), 'Con «Completo cada vez», «Programación» enseña la unidad.' );
	} finally {
		update_option( $B::POLICIES, $saved, false );
	}
} );

inc_case( 'RC6 guardar: acota «Conservar» por unidad y lo avisa; sin el campo conserva la unidad; «Completo cada vez» la quita', function() use ( $B ) {
	$saved = get_option( $B::POLICIES );
	try {
		update_option( $B::POLICIES, array( array( 'id' => 'principal', 'label' => '', 'type' => 'full', 'schedule' => 'daily', 'destination' => 'local', 'retain' => 4, 'enabled' => true, 'time' => '', 'remote_only' => false, 'mode' => 'incremental', 'full_every' => 6, 'full_days' => 7 ) ), false );
		$save = function( array $fields ) use ( $B ) {
			list( $ok, $message ) = bk_post( array( $B, 'update_automation' ), array( '_wpnonce' => 'nonce-' . $B::ACTION_UPDATE_AUTOMATION, 'policy_id' => 'principal', 'digitalisimo_backup_automation' => $fields + array( 'type' => 'full', 'enabled' => '1', 'schedule' => 'daily', 'destination' => 'local' ) ) );
			inc_check( $ok, 'No se guardó la automatización: ' . $message );
			return array( $B::policy( 'principal' ), $message );
		};
		list( $policy, $message ) = $save( array( 'retain' => '30', 'retain_unit' => 'chains', 'mode' => 'incremental', 'full_every' => '6', 'full_days' => '7' ) );
		inc_check( 14 === $policy['retain'] && 'chains' === $policy['retain_unit'] && false !== strpos( $message, '«Conservar» quedó en 14 cadenas: con un completo cada 6 incrementales, sus cadenas caben así en el historial.' ), '«Conservar 30 cadenas» con un completo cada 6 no quedó en 14 con su aviso: ' . json_encode( $policy ) . ' · ' . $message );
		list( $policy, $message ) = $save( array( 'retain' => '5', 'retain_unit' => 'chains', 'mode' => 'incremental', 'full_every' => '60', 'full_days' => '90' ) );
		inc_check( 1 === $policy['retain'] && false !== strpos( $message, '«Conservar» quedó en 1 cadena:' ), 'Con un completo cada 60 sólo cabe una cadena: ' . json_encode( $policy ) . ' · ' . $message );
		list( $policy, $message ) = $save( array( 'retain' => '99', 'retain_unit' => 'points', 'mode' => 'incremental', 'full_every' => '6', 'full_days' => '7' ) );
		inc_check( 94 === $policy['retain'] && 'points' === $policy['retain_unit'] && false !== strpos( $message, '«Conservar» quedó en 94: con un completo cada 6 incrementales, sus puntos y su cadena caben así en el historial.' ), '«Conservar 99 respaldos» no quedó en 94: ' . json_encode( $policy ) . ' · ' . $message );
		list( $policy ) = $save( array( 'retain' => '3', 'retain_unit' => 'chains', 'mode' => 'incremental', 'full_every' => '6', 'full_days' => '7' ) );
		// Un formulario sin la unidad (anterior, o «Programación» sin JavaScript) la conserva, como el modo.
		list( $policy ) = $save( array( 'retain' => '2' ) );
		inc_check( 'chains' === $policy['retain_unit'] && 'incremental' === $policy['mode'] && 2 === $policy['retain'], 'Guardar sin la unidad la perdió: ' . json_encode( $policy ) );
		// Una unidad desconocida cuenta respaldos.
		list( $policy ) = $save( array( 'retain' => '2', 'retain_unit' => 'semanas', 'mode' => 'incremental' ) );
		inc_check( 'points' === $policy['retain_unit'], 'Una unidad desconocida no cuenta respaldos: ' . json_encode( $policy ) );
		// «Completo cada vez» no guarda la unidad (todo respaldo es completo) y su trabajo no la lleva.
		list( $policy ) = $save( array( 'retain' => '3', 'retain_unit' => 'chains', 'mode' => 'full' ) );
		inc_check( ! isset( $policy['retain_unit'] ) && 'points' === Digitalisimo_Backups_Chain::retain_unit( $policy ), '«Completo cada vez» guardó la unidad: ' . json_encode( $policy ) );
	} finally {
		update_option( $B::POLICIES, $saved, false );
	}
} );

bk_cleanup();
echo $failures ? "FALLA: «Conservar» por cadenas con {$failures} caso(s) fallido(s).\n" : "DIGITALÍSIMO Backups: «Conservar» por respaldos o por cadenas enteras, con su estimación, en la retención local y remota y en la pantalla.\n";
exit( $failures ? 1 : 0 );
