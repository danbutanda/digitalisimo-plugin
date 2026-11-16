<?php
defined( 'ABSPATH' ) || exit;

// El panel de avance de la pantalla Backups, el mismo para un respaldo y para una restauración (docs/BACKUPS.md,
// «Pantalla»): a todo lo ancho, con el porcentaje, la fase en lenguaje claro y los tramos de la barra, el paso dentro de la
// fase, el tiempo transcurrido y, al terminar, el resultado. Aquí sólo están su marcado y el trabajo que sigue al cargar la
// página; lo pinta y lo hace avanzar assets/manual-progress.js con Digitalisimo_Backups_Jobs::screen_view(). Sólo lo carga
// la pantalla (render()) y, tras su paso, el tick por testigo de quien no gestiona Backups (su vista mínima): no ocupa
// memoria en las peticiones que restauran.
final class Digitalisimo_Backups_Progress {
	// Minutos durante los que, al cargar la pantalla, el panel enseña el resultado de la última restauración (como
	// FINISHED_ANSWER del tick por testigo): quien perdió la sesión con ella y vuelve a entrar lo ve.
	const RECENT = 900;

	// El trabajo que sigue el panel al cargar: la restauración en curso (o la reconstrucción de un punto incremental, o la
	// vuelta a la copia previa); si no, en «Respaldos», el respaldo manual en curso; si no, la última restauración que
	// terminó hace menos de RECENT segundos. Nunca la de una migración (o la que la deshace): la sigue «Migrar».
	public static function initial( $home ) {
		try {
			$jobs = Digitalisimo_Backups_Jobs::all();
		} catch ( Throwable $e ) {
			return null;
		}
		$active = null;
		$manual = null;
		$recent = null;
		foreach ( $jobs as $job ) {
			$restore = Digitalisimo_Backups_Jobs::restore_like( $job );
			if ( ! empty( $job['spec']['aside'] ) ) continue;
			$terminal = Digitalisimo_Backups_Job_Store::is_terminal( $job );
			if ( $restore && ! $terminal ) $active = $job;
			elseif ( $restore && time() - (int) ( $job['finished_at'] ?? 0 ) < self::RECENT && ( ! $recent || (int) $job['finished_at'] >= (int) $recent['finished_at'] ) ) $recent = $job;
			elseif ( ! $restore && ! $terminal && $home && 'manual' === ( $job['spec']['policy'] ?? '' ) && empty( $job['spec']['inline'] ) && 'backup' === ( $job['kind'] ?? 'backup' ) ) $manual = $job;
		}
		return $active ?: ( $manual ?: $recent );
	}

	// El marcado del panel (oculto: lo enseña el script) con el trabajo que sigue al cargar en data-view. En «Respaldos», bajo
	// «Respaldar ahora»; en las demás pestañas, al principio (al enviar «Restaurar un archivo suelto» enseña que empieza).
	// Devuelve el identificador del trabajo que sigue (la tarjeta «Trabajos» no lo repite) o '' si no sigue ninguno.
	public static function panel( $network, $home ) {
		$job = self::initial( $home );
		$view = $job ? Digitalisimo_Backups_Jobs::screen_view( $job ) : null;
		$screen = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-backups' ) : admin_url( 'admin.php?page=digitalisimo-backups' );
		$login = function_exists( 'wp_login_url' ) ? wp_login_url( $screen ) : $screen;
		$id = 'digitalisimo-backups-progress';
		echo '<div id="digitalisimo-backups-manual-progress" class="digitalisimo-backups-manual-progress digitalisimo-backups-progress' . ( $home ? '' : ' digitalisimo-backups-card' ) . '" data-state="idle" role="region" aria-labelledby="' . $id . '-title"' . ( $view ? ' data-view="' . esc_attr( (string) wp_json_encode( $view ) ) . '"' : '' ) . ' data-login="' . esc_url( $login ) . '" hidden>';
		echo '<div class="' . $id . '-head"><h2 id="' . $id . '-title" class="' . $id . '-title">Avance</h2><span id="' . $id . '-percent" class="' . $id . '-percent" aria-hidden="true">0 %</span></div>';
		echo '<p id="' . $id . '-what" class="' . $id . '-what"></p>';
		echo '<div id="digitalisimo-backups-manual-bar" class="' . $id . '-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-labelledby="' . $id . '-title"><span id="' . $id . '-fill" class="' . $id . '-fill"></span><span id="' . $id . '-ticks" class="' . $id . '-ticks" aria-hidden="true"></span></div>';
		echo '<ol id="' . $id . '-phases" class="' . $id . '-phases" aria-label="Fases"></ol>';
		echo '<p id="digitalisimo-backups-manual-message" class="' . $id . '-message" role="status" aria-live="polite"></p><p id="digitalisimo-backups-manual-detail" class="' . $id . '-detail"></p>';
		echo '<p id="' . $id . '-time" class="' . $id . '-time"></p><p id="' . $id . '-note" class="' . $id . '-note" role="status" aria-live="polite"></p>';
		// Las regiones vivas (nota y resultado) están siempre en la página, vacías: el CSS las pliega (revisión B7).
		echo '<div id="' . $id . '-result" class="' . $id . '-result" aria-live="polite"></div>';
		echo '<div class="' . $id . '-actions"><button type="button" class="button" id="' . $id . '-cancel" hidden>Cancelar</button> <button type="button" class="button button-primary" id="' . $id . '-reload" hidden>Ver la lista actualizada</button> <a class="button" id="' . $id . '-safety" href="' . esc_url( $screen ) . '" hidden>Volver a la copia previa</a> <a class="button button-primary" id="' . $id . '-login" href="' . esc_url( $login ) . '" hidden>Iniciar sesión</a> ';
		// «Volver a la copia previa» de un sitio que quedó a medio cambiar: la misma acción que la franja de avisos.
		echo '<form id="' . $id . '-rollback" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" hidden>';
		wp_nonce_field( Digitalisimo_Backups::ACTION_RESTORE );
		echo '<input type="hidden" name="action" value="' . esc_attr( Digitalisimo_Backups::ACTION_RESTORE ) . '"><input type="hidden" name="rollback_retry" value="1">' . ( $network ? '<input type="hidden" name="digitalisimo_backups_network" value="1">' : '' ) . '<button class="button button-primary">Volver a la copia previa</button></form></div>';
		echo '<details class="digitalisimo-backups-help" id="' . $id . '-help" hidden><summary>Qué la hace avanzar</summary><p class="description" data-folded="1">' . esc_html( 'Con esta pantalla abierta avanza sin pausa. Si la cierras, la siguen WP-Cron (las visitas al sitio en mantenimiento lo despiertan) o el cron del sistema: ' . Digitalisimo_Backups_Restore_Maintenance::system_cron_line() . '. En un sitio sin visitas ni cron, se detiene hasta que vuelvas aquí o ejecutes wp digitalisimo-backups jobs run.' ) . '</p></details>';
		echo '</div>';
		return $job ? (string) $job['id'] : '';
	}

	// Lo que recibe del tick por testigo quien no gestiona Backups (revisión M4): de esa restauración, el estado, la fase, el
	// %, el paso (sin rutas del servidor), las partes, los tramos y el tiempo; si es una vuelta a la copia previa, de cuál.
	// Terminada, sólo un texto general (C4): sin el error, el informe ni el nombre del respaldo.
	public static function token_view( array $job ) {
		$full = Digitalisimo_Backups_Jobs::screen_view( $job );
		$terminal = ! empty( $full['terminal'] );
		$texts = array( 'success' => 'Restauración terminada. Entra al escritorio para ver el resultado.', 'failed' => 'La restauración no terminó bien. Entra al escritorio para ver el detalle y la copia previa.', 'cancelled' => 'La restauración se canceló. Entra al escritorio para ver el detalle.' );
		$view = array_intersect_key( $full, array_flip( array( 'id', 'kind', 'status', 'status_label', 'phase', 'phase_label', 'percent', 'terminal', 'running', 'retry_in', 'retry_label', 'origin_label', 'created_label', 'track', 'elapsed', 'parts', 'rollback_of', 'followed_by', 'midway', 'from_point', 'aside' ) ) );
		$view['can_cancel'] = false;
		$view['destination_label'] = $terminal ? '' : (string) $full['destination_label'];
		$view['message'] = $terminal ? '' : self::public_text( $full['message'], $view['destination_label'] );
		$view['detail'] = $terminal ? ( $texts[ (string) $job['status'] ] ?? 'La restauración terminó. Entra al escritorio para ver el detalle.' ) : self::public_text( $full['detail'], $view['destination_label'] );
		return $view;
	}
	// Un texto del motor sin rutas del servidor ni nombres de archivo (salvo $allow, el del respaldo que se restaura): el
	// almacén se nombra y cualquier otra ruta (con alguna barra) o nombre de archivo queda en «…».
	private static function public_text( $text, $allow = '' ) {
		$text = (string) $text;
		try {
			$store = rtrim( (string) Digitalisimo_Backups::storage_directory(), '/\\' );
		} catch ( Throwable $e ) {
			$store = '';
		}
		if ( strlen( $store ) > 1 ) $text = (string) preg_replace( '#' . preg_quote( $store, '#' ) . '(?:[/\\\\][^\s\'"«»<>,;()]*)?#u', 'el almacén privado', $text );
		$clean = preg_replace_callback( '#[^\s\'"«»<>,;()]*(?:[/\\\\][^\s\'"«»<>,;()]*)+|[\w.-]+\.(?:zip|dgbk|sql|gz|php|json|log|txt|htaccess)\b#u', function( $m ) use ( $allow ) { return '' !== $allow && $m[0] === $allow ? $m[0] : '…'; }, $text );
		return null === $clean ? '' : $clean;
	}
}
