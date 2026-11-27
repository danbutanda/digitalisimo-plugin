<?php
defined( 'ABSPATH' ) || exit;

/**
 * Pantalla «Uso de IA» (docs/IA_USAGE.md): Digitalisimo → Uso de IA en cada sitio y en la red.
 *
 * - Sitio, en lenguaje llano: lo gastado en el mes y hoy (USD y, con tipo de cambio, MXN), el mes pasado, el tope,
 *   «¿En qué se gastó?» por función y el gasto por día. En «Detalle técnico»: tokens por función, modelo y usuario, las
 *   llamadas más caras y el CSV. Los ajustes (presupuesto, tipo de cambio, retención, precios) en su desplegable.
 * - Red: lo gastado en toda la red, cuánto gastó cada sitio y en qué se fue; en «Detalle técnico», por modelo, tokens y
 *   CSV por sitio; los mismos ajustes como valores de red. Cada sitio sólo ve lo suyo: sus consultas usan las tablas de
 *   su prefijo.
 * - Paridad: un solo dibujante de los ajustes (`settings_fields()`) y una sola validación
 *   (Digitalisimo_AI_Usage::sanitize_settings()) para las dos superficies. En una red, el presupuesto, los precios y
 *   la retención de un sitio los define el superadministrador (el administrador del sitio los ve sin poder cambiarlos);
 *   «Heredar de la red» existe sólo en el sitio.
 */
final class Digitalisimo_AI_Usage_Admin {
	const PAGE         = 'digitalisimo-ai-usage';
	const NETWORK_PAGE = 'digitalisimo-network-ai-usage';
	const SAVE         = 'digitalisimo_ai_usage_save';
	const CSV          = 'digitalisimo_ai_usage_csv';
	const NOTICE       = 'ia-usage-budget';
	const PRICES_VER   = 'digitalisimo_ai_usage_prices_ver';
	const REPRICED     = 'digitalisimo_ai_usage_repriced';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_' . self::CSV, array( __CLASS__, 'csv' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	public static function menu() {
		add_submenu_page( 'digitalisimo', 'Uso de IA', 'Uso de IA', 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}

	public static function network_menu() {
		add_submenu_page( 'digitalisimo-network', 'Uso de IA · Red', 'Uso de IA', 'manage_network_options', self::NETWORK_PAGE, array( __CLASS__, 'network_page' ) );
	}

	public static function assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sólo decide qué hoja cargar.
		if ( self::PAGE === $page || self::NETWORK_PAGE === $page ) {
			wp_enqueue_style( 'digitalisimo-ai-usage', DIGITALISIMO_CHATBOT_URL . 'assets/usage.css', function_exists( 'digitalisimo_ui_dependency' ) ? digitalisimo_ui_dependency() : array(), DIGITALISIMO_CHATBOT_VERSION );
		}
	}

	/** ¿Puede cambiar los ajustes de uso de esta superficie? En una red, sólo el superadministrador. */
	public static function can_edit( $network ) {
		if ( $network ) {
			return is_multisite() && current_user_can( 'manage_network_options' ) && is_super_admin();
		}
		return current_user_can( 'manage_options' ) && ( ! is_multisite() || is_super_admin() );
	}

	/* ---------------- Formato ---------------- */

	public static function usd( $value ) {
		if ( null === $value ) {
			return 'sin precio';
		}
		$value = (float) $value;
		return 'US$ ' . number_format( $value, abs( $value ) > 0 && abs( $value ) < 1 ? 4 : 2 );
	}

	public static function mxn( $value ) {
		$rate = self::rate();
		if ( null === $value || $rate <= 0 ) {
			return '—';
		}
		$mxn = (float) $value * $rate;
		return 'MX$ ' . number_format( $mxn, abs( $mxn ) > 0 && abs( $mxn ) < 1 ? 4 : 2 );
	}

	/** Tipo de cambio leído una vez al pintar una pantalla (null fuera de ella). */
	private static $rate = null;

	private static function rate() {
		return null !== self::$rate ? self::$rate : Digitalisimo_AI_Usage::mxn_rate();
	}

	private static function num( $n ) {
		return number_format( (float) $n, 0 );
	}

	/** Nombre de quien hizo la llamada: «Visitante» en el chatbot público, «Tarea automática» sin sesión. */
	public static function user_label( $user_id, $feature = '' ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return 'chatbot' === $feature || '' === $feature ? 'Visitante' : 'Tarea automática';
		}
		$user = get_userdata( $user_id );
		return $user ? (string) $user->display_name : 'Usuario #' . $user_id;
	}

	/** Rango pedido por GET (por omisión, el mes en curso). */
	private static function range() {
		$days = Digitalisimo_AI_Usage::days();
		$from = Digitalisimo_AI_Usage::date_or( isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', $days['month'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro de lectura.
		$to   = Digitalisimo_AI_Usage::date_or( isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '', $days['today'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro de lectura.
		return $from > $to ? array( $to, $from ) : array( $from, $to );
	}

	/* ---------------- Pantalla del sitio ---------------- */

	/**
	 * Lo que ve el dueño contesta «¿cuánto he gastado?»: el mes y hoy en dólares (y en pesos si hay tipo de cambio), en
	 * qué se fue (por función, en palabras) y el gasto por día. Los tokens, los modelos, las personas, las llamadas más
	 * caras y el CSV quedan en «Detalle técnico»; los ajustes, en su propio desplegable.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$saved = self::maybe_save( false );
		self::maybe_reprice();
		list( $from, $to ) = self::range();
		$summary = Digitalisimo_AI_Usage::summary( $from, $to );
		self::$rate = (float) $summary['mxn_rate'];
		$last    = self::last_month();
		$base    = admin_url( 'admin.php?page=' . self::PAGE );
		$label   = self::period_label( $from, $to );
		echo '<div class="wrap digitalisimo-ai-usage"><h1>Uso de IA</h1><hr class="wp-header-end">';
		if ( $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $saved ) . '</p></div>';
		}
		echo '<p class="digitalisimo-usage-lead">Cuánto te está costando la IA de este sitio con los proveedores (OpenAI, Anthropic, Google…): el chatbot, el Asistente IA, la creación de contenido, las imágenes y lo demás. Son cifras estimadas con los precios que publica cada proveedor.</p>';
		self::hero(
			'Gastado este mes',
			$summary['periods']['month'],
			$summary['periods']['today'],
			Digitalisimo_AI_Usage_Store::totals( $last[0], $last[1] ),
			$summary['budget']
		);
		self::unpriced_notice( $summary['unpriced'] );
		self::period_form( $base, $from, $to );
		self::features( '¿En qué se gastó? · ' . $label, $summary['by_feature'], $summary['periods']['range'] );
		self::day_chart( Digitalisimo_AI_Usage_Store::breakdown( 'day', $from, $to, 400 ) );
		echo '<details class="digitalisimo-usage-detail"><summary>Detalle técnico: tokens, modelos, personas y cada llamada</summary>';
		echo '<p class="description">Los proveedores cobran por «tokens», los pedazos de texto que la IA lee y escribe. Aquí está ese detalle de ' . esc_html( self::lower( $label ) ) . ', las llamadas más caras y la descarga en CSV.</p>';
		self::table( 'Por función', 'feature', $summary['by_feature'], $summary['periods']['range']['cost_usd'] );
		self::table( 'Por modelo', 'model', $summary['by_model'], $summary['periods']['range']['cost_usd'] );
		self::table( 'Por usuario', 'user_id', Digitalisimo_AI_Usage_Store::breakdown( 'user_id', $from, $to ), $summary['periods']['range']['cost_usd'] );
		self::top_calls( Digitalisimo_AI_Usage_Store::top( $from, $to, 20 ) );
		self::csv_links( 'site', $from, $to );
		echo '</details>';
		self::settings_form( false, (bool) $saved );
		echo '</div>';
		self::$rate = null;
	}

	/** El mes pasado: primer y último día (hora del sitio). */
	public static function last_month() {
		$days = Digitalisimo_AI_Usage::days();
		$end  = gmdate( 'Y-m-d', strtotime( $days['month'] . ' 00:00:00 UTC' ) - 86400 );
		return array( substr( $end, 0, 8 ) . '01', $end );
	}

	/** «26 de noviembre» (con el año si no es el de hoy). */
	public static function date_text( $day ) {
		return Digitalisimo_AI_Usage::long_date( $day );
	}

	/** Nombre del periodo elegido: «Este mes», «Hoy», «Últimos 7 días», «El mes pasado» o «Del … al …». */
	public static function period_label( $from, $to ) {
		$days = Digitalisimo_AI_Usage::days();
		$last = self::last_month();
		if ( $to === $days['today'] && $from === $days['month'] ) {
			return 'Este mes';
		}
		if ( $to === $days['today'] && $from === $days['today'] ) {
			return 'Hoy';
		}
		if ( $to === $days['today'] && $from === $days['week'] ) {
			return 'Últimos 7 días';
		}
		if ( array( $from, $to ) === $last ) {
			return 'El mes pasado';
		}
		return $from === $to ? 'El ' . self::date_text( $from ) : 'Del ' . self::date_text( $from ) . ' al ' . self::date_text( $to );
	}

	private static function lower( $label ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $label, 'UTF-8' ) : strtolower( $label );
	}

	/**
	 * La tarjeta principal: lo gastado en el mes (grande), hoy y el mes pasado, el tope si lo hay y de dónde salen los
	 * pesos. `$previous` y `$budget` null = sin «mes pasado» ni tope (la red).
	 */
	private static function hero( $title, array $month, array $today, $previous, $budget ) {
		$days = Digitalisimo_AI_Usage::days();
		$mxn  = self::plain_mxn( $month['cost_usd'] );
		echo '<div class="card digitalisimo-usage-hero"><div class="digitalisimo-usage-hero-main">';
		echo '<p class="digitalisimo-usage-kicker">' . esc_html( $title ) . ' <span>(del ' . esc_html( self::date_text( $days['month'] ) ) . ' a hoy)</span></p>';
		echo '<p class="digitalisimo-usage-big">' . esc_html( self::plain_usd( $month['cost_usd'] ) ) . '</p>';
		if ( '' !== $mxn ) {
			echo '<p class="digitalisimo-usage-big-mxn">unos ' . esc_html( $mxn ) . '</p>';
		}
		$line = 'Hoy: <strong>' . esc_html( self::plain_usd( $today['cost_usd'] ) ) . '</strong>' . ( '' !== self::plain_mxn( $today['cost_usd'] ) ? ' (unos ' . esc_html( self::plain_mxn( $today['cost_usd'] ) ) . ')' : '' );
		if ( is_array( $previous ) ) {
			$line .= ' · El mes pasado: <strong>' . esc_html( self::plain_usd( $previous['cost_usd'] ) ) . '</strong>' . ( '' !== self::plain_mxn( $previous['cost_usd'] ) ? ' (unos ' . esc_html( self::plain_mxn( $previous['cost_usd'] ) ) . ')' : '' );
		}
		echo '<p class="digitalisimo-usage-line">' . $line . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partes escapadas arriba.
		echo '<p class="description">' . ( $month['calls'] ? esc_html( self::num( $month['calls'] ) ) . ' consultas a la IA este mes' . ( $month['errors'] ? ' (' . esc_html( self::num( $month['errors'] ) ) . ' fallaron; las que fallan casi no cuestan)' : '' ) . '.' : 'Todavía no se ha usado la IA este mes.' ) . '</p>';
		echo '</div><div class="digitalisimo-usage-hero-side">';
		if ( is_array( $budget ) ) {
			self::budget_bar( $budget );
		}
		self::rate_line();
		echo '</div></div>';
	}

	/** Monto para el dueño: dos decimales; lo que no llega a un centavo, «menos de US$ 0.01». */
	public static function plain_usd( $value ) {
		$value = (float) $value;
		return $value > 0 && $value < 0.005 ? 'menos de US$ 0.01' : 'US$ ' . number_format( $value, 2 );
	}

	/** Lo mismo en pesos, o '' si el sitio no tiene tipo de cambio. */
	public static function plain_mxn( $value ) {
		$rate = self::rate();
		if ( $rate <= 0 || null === $value ) {
			return '';
		}
		$mxn = (float) $value * $rate;
		return $mxn > 0 && $mxn < 0.005 ? 'menos de MX$ 0.01' : 'MX$ ' . number_format( $mxn, 2 );
	}

	private static function rate_line() {
		$fx = Digitalisimo_AI_Usage::mxn_info();
		if ( $fx['rate'] > 0 ) {
			echo '<p class="description digitalisimo-usage-rate">Los pesos son aproximados: dólar a ' . esc_html( number_format( $fx['rate'], 2 ) ) . ', ' . esc_html( Digitalisimo_AI_Usage::fx_text( $fx ) ) . '.</p>';
			return;
		}
		echo '<p class="description digitalisimo-usage-rate">Los montos están en dólares, que es como cobran los proveedores. Para verlos también en pesos, escribe el tipo de cambio en Ajustes (abajo).</p>';
	}

	private static function period_form( $base, $from, $to ) {
		$days  = Digitalisimo_AI_Usage::days();
		$last  = self::last_month();
		$query = wp_parse_url( $base, PHP_URL_QUERY );
		parse_str( (string) $query, $hidden );
		echo '<form method="get" class="digitalisimo-usage-period"><span class="digitalisimo-usage-period-label">Ver:</span> ';
		$marked = false;
		foreach ( array( 'Este mes' => array( $days['month'], $days['today'] ), 'Hoy' => array( $days['today'], $days['today'] ), 'Últimos 7 días' => array( $days['week'], $days['today'] ), 'El mes pasado' => $last ) as $label => $span ) {
			$current = ! $marked && $span[0] === $from && $span[1] === $to; // El día 1, «Este mes» y «Hoy» son lo mismo: se marca uno.
			$marked  = $marked || $current;
			echo '<a class="button' . ( $current ? ' button-primary' : '' ) . '"' . ( $current ? ' aria-current="true"' : '' ) . ' href="' . esc_url( add_query_arg( array( 'from' => $span[0], 'to' => $span[1] ), $base ) ) . '">' . esc_html( $label ) . '</a> ';
		}
		foreach ( $hidden as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
		}
		echo '<span class="digitalisimo-usage-dates"><label>Desde <input type="date" name="from" value="' . esc_attr( $from ) . '"></label> <label>hasta <input type="date" name="to" value="' . esc_attr( $to ) . '"></label> <button class="button">Ver esas fechas</button></span>';
		echo '</form>';
	}

	/** Para qué sirve cada función, dicho para el dueño del sitio. */
	const HELP = array(
		'assistant'  => 'El chat del escritorio que te ayuda con el sitio, la tienda y las páginas.',
		'chatbot'    => 'Lo que el chat de tu página les contesta a tus visitantes.',
		'editorial'  => 'Las sugerencias de la IA al escribir una entrada o una página.',
		'content'    => 'Artículos y borradores que escribe la IA.',
		'seo'        => 'Las ideas y revisiones de DIGITALÍSIMO SEO.',
		'ecommerce'  => 'Textos de productos y de la tienda.',
		'automation' => 'Tareas automáticas que usan la IA.',
		'learning'   => 'Cuando el Asistente estudia tu sitio («Entrenar con mi sitio web») o repasa lo que le enseñaste.',
		'images'     => 'Imágenes creadas con IA.',
		'knowledge'  => 'Preparar y buscar en los documentos que le diste al chatbot.',
		'other'      => 'Otras llamadas hechas con las claves de IA Tools.',
	);

	/**
	 * «¿En qué se gastó?»: una fila por función, en palabras, con su gasto, su parte del total y cuántas consultas
	 * fueron. Filas con `feature` (sitio) o `key` (red).
	 */
	private static function features( $title, array $rows, array $total ) {
		echo '<h2>' . esc_html( $title ) . '</h2>';
		if ( ! $rows ) {
			echo '<p>No se usó la IA en estas fechas.</p>';
			return;
		}
		$pesos = self::rate() > 0;
		$sum   = (float) $total['cost_usd'];
		echo '<table class="widefat striped digitalisimo-usage-features"><thead><tr><th>En qué</th><th>Gasto</th>' . ( $pesos ? '<th>En pesos</th>' : '' ) . '<th>Parte del total</th><th>Consultas a la IA</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$feature = (string) ( $row['feature'] ?? ( $row['key'] ?? '' ) );
			$share   = $sum > 0 ? (float) $row['cost_usd'] / $sum * 100 : 0;
			$help    = self::HELP[ $feature ] ?? '';
			echo '<tr><td><strong>' . esc_html( Digitalisimo_AI_Usage::feature_label( $feature ) ) . '</strong>' . ( '' !== $help ? '<br><span class="description">' . esc_html( $help ) . '</span>' : '' ) . '</td>';
			echo '<td class="digitalisimo-usage-money">' . esc_html( self::plain_usd( $row['cost_usd'] ) ) . ( ! empty( $row['unpriced'] ) ? '<br><span class="description">y ' . esc_html( self::num( $row['unpriced'] ) ) . ' sin precio</span>' : '' ) . '</td>';
			echo $pesos ? '<td class="digitalisimo-usage-money">' . esc_html( self::plain_mxn( $row['cost_usd'] ) ) . '</td>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en la línea.
			echo '<td><span class="digitalisimo-usage-share"><span style="width:' . esc_attr( number_format( min( 100, $share ), 1, '.', '' ) ) . '%"></span></span> ' . esc_html( number_format( $share, 0 ) ) . ' %</td>';
			echo '<td>' . esc_html( self::num( $row['calls'] ) ) . '</td></tr>';
		}
		echo '</tbody><tfoot><tr><th>Total</th><th class="digitalisimo-usage-money">' . esc_html( self::plain_usd( $sum ) ) . '</th>' . ( $pesos ? '<th class="digitalisimo-usage-money">' . esc_html( self::plain_mxn( $sum ) ) . '</th>' : '' ) . '<th></th><th>' . esc_html( self::num( $total['calls'] ) ) . '</th></tr></tfoot></table>';
		echo '<p class="description">Una pregunta al Asistente IA puede ser varias consultas: la IA consulta el sitio y vuelve a preguntar antes de contestar.</p>';
	}

	private static function budget_bar( array $budget ) {
		if ( empty( $budget['enabled'] ) ) {
			echo '<p class="description">Sin tope mensual. Si quieres que te avise al llegar al 80 % y al 100 % de una cantidad (o que pause la IA), ponlo en Ajustes (abajo).</p>';
			return;
		}
		$percent = min( 100, (float) $budget['percent'] );
		$state   = $budget['percent'] >= 100 ? 'is-over' : ( $budget['percent'] >= 80 ? 'is-warning' : 'is-ok' );
		echo '<div class="digitalisimo-usage-budget ' . esc_attr( $state ) . '"><p><strong>Tope del mes: ' . esc_html( self::plain_usd( $budget['cap_usd'] ) ) . '</strong> · llevas el ' . esc_html( number_format( (float) $budget['percent'], 1 ) ) . ' %. Al llegarle: ' . esc_html( 'pause' === $budget['action'] ? 'se pausan las funciones de IA' : 'sólo te avisa' ) . '.</p>';
		echo '<div class="digitalisimo-usage-meter" role="img" aria-label="' . esc_attr( number_format( (float) $budget['percent'], 1 ) . ' % del tope' ) . '"><span style="width:' . esc_attr( number_format( $percent, 1, '.', '' ) ) . '%"></span></div>';
		if ( ! empty( $budget['paused'] ) ) {
			echo '<p><strong>' . esc_html( Digitalisimo_AI_Usage::pause_message() ) . '</strong></p>';
		}
		echo '</div>';
	}

	private static function unpriced_notice( array $unpriced ) {
		if ( ! $unpriced ) {
			return;
		}
		$list = array();
		foreach ( $unpriced as $key => $calls ) {
			list( $provider, $model ) = array_pad( explode( '|', (string) $key, 2 ), 2, '' );
			$list[] = ( '' !== $model ? $model : 'modelo desconocido' ) . ' (' . $provider . ', ' . self::num( $calls ) . ' llamadas)';
		}
		echo '<div class="notice notice-warning inline"><p><strong>Hay llamadas sin precio:</strong> usaron un modelo cuyo precio no conocemos (' . esc_html( implode( '; ', $list ) ) . '), así que el total real es algo mayor. Escribe su precio en Ajustes → Precios (abajo) y se sumará.</p></div>';
	}

	private static function day_chart( array $rows ) {
		echo '<h2>Gasto por día</h2>';
		if ( ! $rows ) {
			echo '<p>Sin consultas a la IA en estas fechas.</p>';
			return;
		}
		$max = 0.0;
		foreach ( $rows as $row ) {
			$max = max( $max, (float) $row['cost_usd'] );
		}
		echo '<div class="digitalisimo-usage-chart" role="img" aria-label="Gasto por día">';
		foreach ( $rows as $row ) {
			$height = $max > 0 ? max( 1, round( (float) $row['cost_usd'] / $max * 100, 1 ) ) : 1;
			$title  = self::date_text( $row['day'] ) . ': ' . self::plain_usd( $row['cost_usd'] ) . ' · ' . self::num( $row['calls'] ) . ' consultas';
			echo '<span class="digitalisimo-usage-bar" style="height:' . esc_attr( number_format( $height, 1, '.', '' ) ) . '%" title="' . esc_attr( $title ) . '"></span>';
		}
		$pesos = self::rate() > 0;
		echo '</div><details><summary>Ver como tabla</summary><table class="widefat striped"><thead><tr><th>Día</th><th>Gasto</th>' . ( $pesos ? '<th>En pesos</th>' : '' ) . '<th>Consultas</th><th>Tokens</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( self::date_text( $row['day'] ) ) . '</td><td>' . esc_html( self::usd( $row['cost_usd'] ) ) . '</td>' . ( $pesos ? '<td>' . esc_html( self::mxn( $row['cost_usd'] ) ) . '</td>' : '' ) . '<td>' . esc_html( self::num( $row['calls'] ) ) . '</td><td>' . esc_html( self::num( $row['tokens'] ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en la línea.
		}
		echo '</tbody></table></details>';
	}

	private static function table( $title, $by, array $rows, $total ) {
		echo '<h2>' . esc_html( $title ) . '</h2>';
		if ( ! $rows ) {
			echo '<p>Sin llamadas en el rango.</p>';
			return;
		}
		echo '<table class="widefat striped digitalisimo-usage-table"><thead><tr><th>' . esc_html( 'feature' === $by ? 'Función' : ( 'model' === $by ? 'Modelo' : 'Usuario' ) ) . '</th><th>Llamadas</th><th>Entrada</th><th>Salida</th><th>Caché leída</th><th>Caché escrita</th><th>Imágenes</th><th>USD</th><th>MXN</th><th>% del costo</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			if ( 'feature' === $by ) {
				$name = Digitalisimo_AI_Usage::feature_label( $row['feature'] );
			} elseif ( 'model' === $by ) {
				$name = ( '' !== $row['model'] ? $row['model'] : 'desconocido' ) . ' · ' . $row['provider'];
			} else {
				$name = self::user_label( $row['user_id'] );
			}
			$share = $total > 0 ? (float) $row['cost_usd'] / (float) $total * 100 : 0;
			$extra = $row['errors'] || $row['retries'] || $row['unpriced'] ? ' <span class="description">(' . esc_html( trim( ( $row['errors'] ? self::num( $row['errors'] ) . ' con error ' : '' ) . ( $row['retries'] ? self::num( $row['retries'] ) . ' reintentos ' : '' ) . ( $row['unpriced'] ? self::num( $row['unpriced'] ) . ' sin precio' : '' ) ) ) . ')</span>' : '';
			echo '<tr><td>' . esc_html( $name ) . $extra . '</td><td>' . esc_html( self::num( $row['calls'] ) ) . '</td><td>' . esc_html( self::num( $row['input_tokens'] ) ) . '</td><td>' . esc_html( self::num( $row['output_tokens'] ) ) . '</td><td>' . esc_html( self::num( $row['cache_read_tokens'] ) ) . '</td><td>' . esc_html( self::num( $row['cache_write_tokens'] ) ) . '</td><td>' . esc_html( self::num( $row['units'] ) ) . '</td><td>' . esc_html( self::usd( $row['cost_usd'] ) ) . '</td><td>' . esc_html( self::mxn( $row['cost_usd'] ) ) . '</td><td><span class="digitalisimo-usage-share"><span style="width:' . esc_attr( number_format( min( 100, $share ), 1, '.', '' ) ) . '%"></span></span> ' . esc_html( number_format( $share, 1 ) ) . ' %</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function top_calls( array $rows ) {
		echo '<h2>Llamadas más caras</h2>';
		if ( ! $rows ) {
			echo '<p>Sin llamadas con costo en el rango (el detalle se conserva según la retención).</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>Fecha (UTC)</th><th>Función</th><th>Usuario</th><th>Modelo</th><th>Entrada</th><th>Salida</th><th>Caché leída</th><th>Caché escrita</th><th>USD</th><th>MXN</th><th>Estado</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$state = $row['is_error'] ? 'Error HTTP ' . (int) $row['status'] : 'OK';
			if ( $row['is_retry'] ) {
				$state .= ' · reintento';
			}
			echo '<tr><td>' . esc_html( $row['created'] ) . '</td><td>' . esc_html( Digitalisimo_AI_Usage::feature_label( $row['feature'] ) ) . '</td><td>' . esc_html( self::user_label( $row['user_id'], $row['feature'] ) ) . '</td><td>' . esc_html( $row['model'] . ' · ' . $row['provider'] ) . '</td><td>' . esc_html( self::num( $row['input_tokens'] ) ) . '</td><td>' . esc_html( self::num( $row['output_tokens'] ) ) . '</td><td>' . esc_html( self::num( $row['cache_read_tokens'] ) ) . '</td><td>' . esc_html( self::num( $row['cache_write_tokens'] ) ) . '</td><td>' . esc_html( self::usd( null === $row['cost_usd'] ? null : (float) $row['cost_usd'] ) ) . '</td><td>' . esc_html( self::mxn( null === $row['cost_usd'] ? null : (float) $row['cost_usd'] ) ) . '</td><td>' . esc_html( $state ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function csv_links( $scope, $from, $to ) {
		$link = function ( $type ) use ( $scope, $from, $to ) {
			return wp_nonce_url( add_query_arg( array( 'action' => self::CSV, 'scope' => $scope, 'type' => $type, 'from' => $from, 'to' => $to ), admin_url( 'admin-post.php' ) ), self::CSV );
		};
		echo '<h2>Exportar</h2><p>';
		if ( 'network' === $scope ) {
			echo '<a class="button" href="' . esc_url( $link( 'sites' ) ) . '">CSV por sitio</a> ';
		} else {
			echo '<a class="button" href="' . esc_url( $link( 'daily' ) ) . '">CSV resumen por día</a> <a class="button" href="' . esc_url( $link( 'detail' ) ) . '">CSV detalle de llamadas</a> ';
		}
		echo '<span class="description">Del rango elegido. Sólo números y nombres de modelo y función: nunca texto de conversaciones ni claves.</span></p>';
	}

	/* ---------------- Pantalla de la red ---------------- */

	/**
	 * Toda la red: lo gastado en el mes y hoy, cuánto gastó cada sitio (de mayor a menor en el periodo elegido) y en
	 * qué se fue, en palabras. Modelos, tokens y CSV en «Detalle técnico»; los ajustes de la red, en su desplegable.
	 */
	public static function network_page() {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) || ! is_super_admin() ) {
			return;
		}
		$saved = self::maybe_save( true );
		list( $from, $to ) = self::range();
		$days  = Digitalisimo_AI_Usage::days() + array( 'from' => $from, 'to' => $to );
		$ids   = self::site_ids();
		$sites = Digitalisimo_AI_Usage_Store::network_sites( $ids, $days );
		$base  = network_admin_url( 'admin.php?page=' . self::NETWORK_PAGE );
		$label = self::period_label( $from, $to );
		self::$rate = Digitalisimo_AI_Usage::mxn_rate();
		echo '<div class="wrap digitalisimo-ai-usage"><h1>Uso de IA · Red</h1><hr class="wp-header-end">';
		if ( $saved ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $saved ) . '</p></div>';
		}
		echo '<p class="digitalisimo-usage-lead">Cuánto está costando la IA en cada sitio de la red y en toda la red, con los precios que publica cada proveedor. Cada sitio ve sólo lo suyo en su propio escritorio (Digitalisimo → Uso de IA).</p>';
		$total = array();
		foreach ( array( 'today', 'week', 'month', 'range' ) as $period ) {
			$total[ $period ] = array( 'cost_usd' => 0.0, 'calls' => 0, 'tokens' => 0, 'unpriced' => 0, 'errors' => 0 );
			foreach ( $sites as $site ) {
				foreach ( array( 'cost_usd', 'calls', 'tokens', 'unpriced' ) as $key ) {
					$total[ $period ][ $key ] += $site[ $period ][ $key ];
				}
			}
		}
		self::hero( 'Gastado este mes en toda la red', $total['month'], $total['today'], null, null );
		self::period_form( $base, $from, $to );
		echo '<h2>¿Cuánto gastó cada sitio? · ' . esc_html( $label ) . '</h2>';
		$pesos   = self::rate() > 0;
		$network = Digitalisimo_AI_Usage::network();
		$order   = $ids;
		usort(
			$order,
			function ( $a, $b ) use ( $sites ) {
				return (float) ( $sites[ $b ]['range']['cost_usd'] ?? 0 ) <=> (float) ( $sites[ $a ]['range']['cost_usd'] ?? 0 );
			}
		);
		echo '<table class="widefat striped digitalisimo-usage-sites"><thead><tr><th>Sitio</th><th>' . esc_html( $label ) . '</th>' . ( $pesos ? '<th>En pesos</th>' : '' ) . '<th>Este mes</th><th>Hoy</th><th>Tope del mes</th><th></th></tr></thead><tbody>';
		foreach ( $order as $blog_id ) {
			$site  = $sites[ $blog_id ] ?? null;
			$cap   = self::site_budget( $blog_id, $network );
			$month = $site ? $site['month']['cost_usd'] : 0.0;
			$note  = $cap ? self::plain_usd( $cap ) . ' (lleva el ' . number_format( $month / $cap * 100, 1 ) . ' %)' : 'Sin tope';
			$range = $site ? $site['range']['cost_usd'] : 0.0;
			echo '<tr><td><strong>' . esc_html( self::site_name( $blog_id ) ) . '</strong>' . ( $site && $site['range']['unpriced'] ? '<br><span class="description">y ' . esc_html( self::num( $site['range']['unpriced'] ) ) . ' consultas sin precio</span>' : '' ) . '</td>';
			echo '<td class="digitalisimo-usage-money">' . esc_html( self::plain_usd( $range ) ) . '</td>' . ( $pesos ? '<td class="digitalisimo-usage-money">' . esc_html( self::plain_mxn( $range ) ) . '</td>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en la línea.
			echo '<td class="digitalisimo-usage-money">' . esc_html( self::plain_usd( $month ) ) . '</td><td class="digitalisimo-usage-money">' . esc_html( self::plain_usd( $site ? $site['today']['cost_usd'] : 0 ) ) . '</td><td>' . esc_html( $note ) . '</td><td><a href="' . esc_url( get_admin_url( $blog_id, 'admin.php?page=' . self::PAGE . '&from=' . rawurlencode( $from ) . '&to=' . rawurlencode( $to ) ) ) . '">Ver en qué gastó</a></td></tr>';
		}
		echo '</tbody><tfoot><tr><th>Toda la red</th><th class="digitalisimo-usage-money">' . esc_html( self::plain_usd( $total['range']['cost_usd'] ) ) . '</th>' . ( $pesos ? '<th class="digitalisimo-usage-money">' . esc_html( self::plain_mxn( $total['range']['cost_usd'] ) ) . '</th>' : '' ) . '<th class="digitalisimo-usage-money">' . esc_html( self::plain_usd( $total['month']['cost_usd'] ) ) . '</th><th class="digitalisimo-usage-money">' . esc_html( self::plain_usd( $total['today']['cost_usd'] ) ) . '</th><th></th><th></th></tr></tfoot></table>';
		self::features( '¿En qué se gastó en toda la red? · ' . $label, Digitalisimo_AI_Usage_Store::network_breakdown( $ids, 'feature', $from, $to ), $total['range'] );
		echo '<details class="digitalisimo-usage-detail"><summary>Detalle técnico: modelos, tokens y CSV</summary>';
		echo '<h2>Por sitio</h2><table class="widefat striped"><thead><tr><th>Sitio</th><th>Hoy</th><th>7 días</th><th>Este mes</th><th>Rango</th><th>Llamadas (rango)</th><th>Tokens (rango)</th></tr></thead><tbody>';
		foreach ( $order as $blog_id ) {
			$site = $sites[ $blog_id ] ?? null;
			echo '<tr><td>' . esc_html( self::site_name( $blog_id ) ) . '</td>';
			foreach ( array( 'today', 'week', 'month', 'range' ) as $period ) {
				echo '<td>' . esc_html( $site ? self::usd( $site[ $period ]['cost_usd'] ) : '—' ) . ( $site && $site[ $period ]['unpriced'] ? ' <span class="description">+ ' . esc_html( self::num( $site[ $period ]['unpriced'] ) ) . ' sin precio</span>' : '' ) . '</td>';
			}
			echo '<td>' . esc_html( $site ? self::num( $site['range']['calls'] ) : '0' ) . '</td><td>' . esc_html( $site ? self::num( $site['range']['tokens'] ) : '0' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		foreach ( array( 'feature' => 'Toda la red por función', 'model' => 'Toda la red por modelo' ) as $by => $title ) {
			$rows = Digitalisimo_AI_Usage_Store::network_breakdown( $ids, $by, $from, $to );
			echo '<h2>' . esc_html( $title ) . '</h2>';
			if ( ! $rows ) {
				echo '<p>Sin llamadas en el rango.</p>';
				continue;
			}
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html( 'feature' === $by ? 'Función' : 'Modelo' ) . '</th><th>Llamadas</th><th>Tokens</th><th>USD</th><th>MXN</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$name = 'feature' === $by ? Digitalisimo_AI_Usage::feature_label( $row['key'] ) : str_replace( '|', ' · ', $row['key'] );
				echo '<tr><td>' . esc_html( $name ) . ( $row['unpriced'] ? ' <span class="description">(' . esc_html( self::num( $row['unpriced'] ) ) . ' sin precio)</span>' : '' ) . '</td><td>' . esc_html( self::num( $row['calls'] ) ) . '</td><td>' . esc_html( self::num( $row['tokens'] ) ) . '</td><td>' . esc_html( self::usd( $row['cost_usd'] ) ) . '</td><td>' . esc_html( self::mxn( $row['cost_usd'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		self::csv_links( 'network', $from, $to );
		echo '</details>';
		self::settings_form( true, (bool) $saved );
		echo '</div>';
		self::$rate = null;
	}

	private static function site_ids() {
		$ids = function_exists( 'get_sites' ) ? get_sites( array( 'fields' => 'ids', 'number' => 2000, 'deleted' => 0 ) ) : array( 1 );
		return array_map( 'intval', (array) $ids );
	}

	private static function site_name( $blog_id ) {
		$details = function_exists( 'get_blog_details' ) ? get_blog_details( (int) $blog_id ) : null;
		return $details ? trim( (string) $details->blogname ) . ' · ' . untrailingslashit( preg_replace( '#^https?://#', '', (string) $details->siteurl ) ) : 'Sitio #' . (int) $blog_id;
	}

	/** El tope efectivo de un sitio de la red (el suyo o, si hereda, el de la red), 0 sin tope. */
	private static function site_budget( $blog_id, array $network ) {
		$local = function_exists( 'get_blog_option' ) ? get_blog_option( (int) $blog_id, Digitalisimo_AI_Usage::SETTINGS, array() ) : array();
		$local = is_array( $local ) ? $local : array();
		$o     = ! array_key_exists( 'inherit_network', $local ) || ! empty( $local['inherit_network'] ) ? $network : wp_parse_args( $local, Digitalisimo_AI_Usage::defaults() );
		return ! empty( $o['budget_enabled'] ) ? (float) $o['budget_usd'] : 0.0;
	}

	/* ---------------- Ajustes (los mismos campos en el sitio y en la red) ---------------- */

	private static function settings_form( $network, $open = false ) {
		$editable = self::can_edit( $network );
		$inherits = ! $network && Digitalisimo_AI_Usage::inherits();
		$o        = $network ? Digitalisimo_AI_Usage::network() : Digitalisimo_AI_Usage::settings();
		echo '<details id="ajustes" class="digitalisimo-usage-settings-box"' . ( $open ? ' open' : '' ) . '><summary>Ajustes' . ( $network ? ' de la red' : '' ) . ': tope mensual, tipo de cambio y precios</summary><form method="post" class="digitalisimo-usage-settings">';
		wp_nonce_field( self::SAVE );
		if ( ! $editable ) {
			echo '<div class="notice notice-info inline"><p>En esta red, el presupuesto, los precios y la retención de cada sitio los define el superadministrador. Aquí se ven los valores vigentes.</p></div>';
		}
		if ( ! $network && is_multisite() ) {
			echo '<p><label><input type="hidden" name="usage[inherit_network]" value="0"><input type="checkbox" name="usage[inherit_network]" value="1" ' . checked( $inherits, true, false ) . ( $editable ? '' : ' disabled' ) . '> <strong>Heredar los ajustes de uso de la red</strong></label></p><p class="description">Desmarcada, este sitio usa su propio presupuesto, tipo de cambio, retención y correcciones de precios.</p>';
		}
		echo $inherits || ! $editable ? '<fieldset disabled>' : '<fieldset>';
		self::settings_fields( $o, $network );
		echo '</fieldset>';
		if ( $editable ) {
			submit_button( $network ? 'Guardar ajustes de uso de la red' : 'Guardar ajustes de uso', 'primary', self::SAVE );
		}
		echo '</form></details>';
	}

	/** Campos de ajustes: los mismos en el sitio y en la red (paridad). */
	public static function settings_fields( array $o, $network ) {
		echo '<h3>Presupuesto mensual</h3><p class="description">Opcional, apagado de fábrica. Cuenta desde el día 1 de cada mes (hora del sitio). Al 80 % avisa en el escritorio y por correo; al 100 % sólo avisa o pausa las funciones de IA' . ( $network ? ' de cada sitio que herede estos valores' : ' de este sitio' ) . '.</p><table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">Activar</th><td><label><input type="hidden" name="usage[budget_enabled]" value="0"><input type="checkbox" name="usage[budget_enabled]" value="1" ' . checked( ! empty( $o['budget_enabled'] ), true, false ) . '> Usar un tope mensual</label></td></tr>';
		echo '<tr><th scope="row"><label for="digitalisimo-usage-budget">Tope (USD)</label></th><td><input id="digitalisimo-usage-budget" type="number" min="0" step="0.01" name="usage[budget_usd]" value="' . esc_attr( (string) $o['budget_usd'] ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="digitalisimo-usage-action">Al llegar al 100 %</label></th><td><select id="digitalisimo-usage-action" name="usage[budget_action]"><option value="notify" ' . selected( $o['budget_action'], 'notify', false ) . '>Sólo avisar</option><option value="pause" ' . selected( $o['budget_action'], 'pause', false ) . '>Pausar las funciones de IA hasta el próximo mes</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="digitalisimo-usage-email">Correo del aviso</label></th><td><input id="digitalisimo-usage-email" type="email" name="usage[budget_email]" value="' . esc_attr( (string) $o['budget_email'] ) . '" placeholder="El del administrador del sitio"></td></tr>';
		echo '</tbody></table><h3>Moneda y registro</h3><table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="digitalisimo-usage-rate">Tipo de cambio (MXN por USD)</label></th><td><input id="digitalisimo-usage-rate" type="number" min="0" step="0.0001" name="usage[mxn_rate]" value="' . esc_attr( $o['mxn_rate'] ? (string) $o['mxn_rate'] : '' ) . '" placeholder="Ej. 18.50"><p class="description">Para ver los montos también en pesos. Vacío, se usa el último que guardó DIGITALÍSIMO Hosting al actualizar los precios de dominios (el FIX de Banxico), si lo hay; si no, sólo dólares.</p></td></tr>';
		echo '<tr><th scope="row"><label for="digitalisimo-usage-retention">Conservar el detalle (días)</label></th><td><input id="digitalisimo-usage-retention" type="number" min="7" max="3650" name="usage[retention_days]" value="' . esc_attr( (string) $o['retention_days'] ) . '"><p class="description">Cada llamada se guarda este tiempo; el resumen por día se conserva siempre.</p></td></tr>';
		echo '<tr><th scope="row">Al desinstalar</th><td><label><input type="hidden" name="usage[purge_on_uninstall]" value="0"><input type="checkbox" name="usage[purge_on_uninstall]" value="1" ' . checked( ! empty( $o['purge_on_uninstall'] ), true, false ) . '> Borrar el registro de uso al desinstalar IA Tools</label><p class="description">Desmarcado, el historial se conserva si se borra y se vuelve a instalar el plugin.</p></td></tr>';
		echo '</tbody></table>';
		self::price_fields( $network );
	}

	/** Tabla de precios editable: cada fila muestra el precio vigente; lo que cambia se guarda como corrección. */
	private static function price_fields( $network ) {
		$all = Digitalisimo_AI_Usage_Prices::all();
		echo '<h3 id="precios">Precios</h3><p class="description">USD por millón de tokens (la imagen, por imagen). De fábrica traen la fuente y la fecha en que se comprobaron; los precios cambian, así que puedes corregirlos o agregar un modelo en la última fila. Lo que dejes igual sigue al precio de fábrica' . ( $network ? '' : ( is_multisite() ? ' y al de la red' : '' ) ) . '. Al agregar el precio de un modelo, sus llamadas «sin precio» se calculan con él.</p>';
		echo '<div class="digitalisimo-usage-prices"><table class="widefat striped"><thead><tr><th>Modelo</th><th>Proveedor</th><th>Entrada</th><th>Salida</th><th>Caché leída</th><th>Caché escrita 5 min</th><th>Caché escrita 1 h</th><th>Imagen</th><th>Fuente</th><th>Quitar corrección</th></tr></thead><tbody>';
		$i = 0;
		foreach ( $all as $model => $row ) {
			self::price_row( $i++, $model, $row );
		}
		self::price_row( $i, '', array() );
		echo '</tbody></table></div>';
	}

	private static function price_row( $i, $model, array $row ) {
		$name = 'usage_prices[' . (int) $i . ']';
		echo '<tr><td>' . ( '' === $model ? '<input type="text" name="' . esc_attr( $name ) . '[model]" value="" placeholder="Nuevo modelo" size="18">' : '<code>' . esc_html( $model ) . '</code><input type="hidden" name="' . esc_attr( $name ) . '[model]" value="' . esc_attr( $model ) . '">' ) . '</td>';
		echo '<td><input type="text" size="8" name="' . esc_attr( $name ) . '[provider]" value="' . esc_attr( (string) ( $row['provider'] ?? '' ) ) . '"></td>';
		foreach ( Digitalisimo_AI_Usage_Prices::FIELDS as $field ) {
			echo '<td><input type="number" min="0" step="any" class="small-text" name="' . esc_attr( $name . '[' . $field . ']' ) . '" value="' . esc_attr( isset( $row[ $field ] ) ? (string) $row[ $field ] : '' ) . '"></td>';
		}
		$origin = (string) ( $row['origin'] ?? '' );
		$source = 'builtin' === $origin ? (string) ( $row['source'] ?? '' ) : ( '' !== $origin ? 'Corrección del administrador' : '' );
		$note   = trim( ( 'builtin' === $origin ? 'Comprobado ' . ( $row['checked'] ?? '' ) : ( '' !== $origin ? 'Guardado ' . ( $row['checked'] ?? '' ) : '' ) ) . ( ! empty( $row['note'] ) ? '. ' . $row['note'] : '' ) . ( ! empty( $row['long_over'] ) ? '. Precio mayor con más de ' . number_format( (float) $row['long_over'] ) . ' tokens de entrada.' : '' ) );
		echo '<td class="digitalisimo-usage-source">' . ( 0 === strpos( $source, 'http' ) ? '<a href="' . esc_url( $source ) . '" target="_blank" rel="noopener">' . esc_html( (string) wp_parse_url( $source, PHP_URL_HOST ) ) . '</a>' : esc_html( $source ) ) . ( '' !== $note ? '<br><span class="description">' . esc_html( $note ) . '</span>' : '' ) . '</td>';
		echo '<td>' . ( in_array( $origin, array( 'edited', 'custom' ), true ) ? '<label><input type="checkbox" name="' . esc_attr( $name ) . '[remove]" value="1"> Quitar</label>' : '' ) . '</td></tr>';
	}

	/** Guarda los ajustes si se envió el formulario de esta superficie; devuelve el aviso o ''. */
	private static function maybe_save( $network ) {
		if ( ! isset( $_POST[ self::SAVE ] ) || ! check_admin_referer( self::SAVE ) || ! self::can_edit( $network ) ) {
			return '';
		}
		$in     = isset( $_POST['usage'] ) && is_array( $_POST['usage'] ) ? wp_unslash( $_POST['usage'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se valida en sanitize_settings().
		$prices = isset( $_POST['usage_prices'] ) && is_array( $_POST['usage_prices'] ) ? wp_unslash( $_POST['usage_prices'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- se valida en sanitize_rows().
		if ( $network ) {
			update_site_option( Digitalisimo_AI_Usage::SETTINGS, Digitalisimo_AI_Usage::sanitize_settings( $in, Digitalisimo_AI_Usage::network() ) );
			if ( null !== $prices ) {
				update_site_option( Digitalisimo_AI_Usage_Prices::OPTION, self::price_diff( $prices, Digitalisimo_AI_Usage_Prices::builtin() ) );
				update_site_option( self::PRICES_VER, (string) microtime( true ) );
			}
			Digitalisimo_AI_Usage_Prices::reset();
			return 'Ajustes de uso de la red guardados.';
		}
		$local   = Digitalisimo_AI_Usage::local();
		$inherit = is_multisite() && ! empty( $in['inherit_network'] );
		if ( is_multisite() && $inherit ) {
			// Heredar guarda sólo eso: lo propio queda para cuando deje de heredar.
			$local['inherit_network'] = 1;
			update_option( Digitalisimo_AI_Usage::SETTINGS, $local, false );
			Digitalisimo_AI_Usage_Prices::reset();
			return 'Este sitio usa los ajustes de uso de la red.';
		}
		// Dejar de heredar parte de lo que se veía (los valores de la red; los campos deshabilitados no viajan).
		$base = Digitalisimo_AI_Usage::inherits() ? Digitalisimo_AI_Usage::network() : wp_parse_args( $local, Digitalisimo_AI_Usage::defaults() );
		$new  = Digitalisimo_AI_Usage::sanitize_settings( $in, $base );
		if ( is_multisite() ) {
			$new['inherit_network'] = 0;
		}
		update_option( Digitalisimo_AI_Usage::SETTINGS, $new, false );
		if ( null !== $prices ) {
			$inherited = Digitalisimo_AI_Usage_Prices::builtin();
			if ( is_multisite() ) {
				foreach ( Digitalisimo_AI_Usage_Prices::stored( true ) as $model => $row ) {
					$inherited[ $model ] = array_merge( $inherited[ $model ] ?? array(), $row );
				}
			}
			update_option( Digitalisimo_AI_Usage_Prices::OPTION, self::price_diff( $prices, $inherited ), false );
		}
		Digitalisimo_AI_Usage_Prices::reset();
		$fixed = Digitalisimo_AI_Usage_Store::reprice_unpriced();
		return 'Ajustes de uso guardados.' . ( $fixed ? sprintf( ' Se calculó el costo de %d llamadas que no tenían precio.', $fixed ) : '' );
	}

	/** Las filas que difieren de lo heredado (o que son modelos nuevos): eso es lo que se guarda. */
	public static function price_diff( array $rows, array $inherited ) {
		$out = array();
		foreach ( Digitalisimo_AI_Usage_Prices::sanitize_rows( $rows ) as $model => $row ) {
			$base    = $inherited[ $model ] ?? null;
			$differs = ! is_array( $base );
			foreach ( Digitalisimo_AI_Usage_Prices::FIELDS as $field ) {
				$was = isset( $base[ $field ] ) ? round( (float) $base[ $field ], 6 ) : null;
				$now = $row[ $field ] ?? null;
				if ( $was !== $now ) {
					$differs = true;
				}
			}
			if ( $differs ) {
				$out[ $model ] = $row;
			}
		}
		return $out;
	}

	/** Tras un cambio de precios en la red, cada sitio recalcula sus llamadas sin precio al abrir su pantalla. */
	private static function maybe_reprice() {
		if ( ! is_multisite() ) {
			return;
		}
		$ver = (string) get_site_option( self::PRICES_VER, '' );
		if ( '' !== $ver && get_option( self::REPRICED, '' ) !== $ver ) {
			Digitalisimo_AI_Usage_Store::reprice_unpriced();
			update_option( self::REPRICED, $ver, false );
		}
	}

	/* ---------------- CSV ---------------- */

	/** Una celda segura para hojas de cálculo (sin fórmulas). */
	public static function cell( $value ) {
		$value = (string) $value;
		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) && ! is_numeric( $value ) ? "'" . $value : $value;
	}

	public static function csv() {
		check_admin_referer( self::CSV );
		$scope = isset( $_GET['scope'] ) && 'network' === $_GET['scope'] ? 'network' : 'site';
		if ( 'network' === $scope ? ! ( is_multisite() && current_user_can( 'manage_network_options' ) && is_super_admin() ) : ! current_user_can( 'manage_options' ) ) {
			wp_die( 'No tienes permiso para exportar el uso de IA.', 403 );
		}
		list( $from, $to ) = self::range();
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'daily';
		$rows = self::csv_rows( $scope, $type, $from, $to );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="uso-ia-' . ( 'network' === $scope ? 'red' : 'sitio-' . (int) get_current_blog_id() ) . '-' . $type . '-' . $from . '_' . $to . '.csv"' );
		echo self::csv_text( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV, no HTML.
		exit;
	}

	/** Las filas (con encabezado) de un CSV: `daily` o `detail` de este sitio, o `sites` de la red. */
	public static function csv_rows( $scope, $type, $from, $to ) {
		$rate = Digitalisimo_AI_Usage::mxn_rate();
		$mxn  = function ( $usd ) use ( $rate ) {
			return null === $usd || $rate <= 0 ? '' : sprintf( '%.4F', (float) $usd * $rate );
		};
		$usd  = function ( $value ) {
			return null === $value ? 'sin precio' : sprintf( '%.8F', (float) $value );
		};
		if ( 'network' === $scope ) {
			$days  = Digitalisimo_AI_Usage::days() + array( 'from' => $from, 'to' => $to );
			$ids   = self::site_ids();
			$sites = Digitalisimo_AI_Usage_Store::network_sites( $ids, $days );
			$out   = array( array( 'blog_id', 'sitio', 'desde', 'hasta', 'llamadas', 'tokens', 'costo_usd', 'costo_mxn', 'llamadas_sin_precio', 'mes_usd' ) );
			foreach ( $ids as $blog_id ) {
				$site  = $sites[ $blog_id ] ?? array( 'range' => array( 'calls' => 0, 'tokens' => 0, 'cost_usd' => 0, 'unpriced' => 0 ), 'month' => array( 'cost_usd' => 0 ) );
				$out[] = array( $blog_id, self::site_name( $blog_id ), $from, $to, $site['range']['calls'], $site['range']['tokens'], $usd( $site['range']['cost_usd'] ), $mxn( $site['range']['cost_usd'] ), $site['range']['unpriced'], $usd( $site['month']['cost_usd'] ) );
			}
			return $out;
		}
		$blog = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;
		if ( 'detail' === $type ) {
			$out    = array( array( 'fecha_utc', 'dia_local', 'blog_id', 'funcion', 'usuario', 'proveedor', 'modelo', 'tokens_entrada', 'tokens_salida', 'cache_leida', 'cache_escrita', 'cache_escrita_1h', 'imagenes', 'costo_usd', 'costo_mxn', 'estado_http', 'error', 'reintento' ) );
			$offset = 0;
			do {
				$batch = Digitalisimo_AI_Usage_Store::details( $from, $to, 2000, $offset );
				foreach ( $batch as $row ) {
					$cost  = null === $row['cost_usd'] ? null : (float) $row['cost_usd'];
					$out[] = array( $row['created'], $row['day'], (int) $row['blog_id'], Digitalisimo_AI_Usage::feature_label( $row['feature'] ), self::user_label( $row['user_id'], $row['feature'] ), $row['provider'], $row['model'], (int) $row['input_tokens'], (int) $row['output_tokens'], (int) $row['cache_read_tokens'], (int) $row['cache_write_tokens'], (int) $row['cache_write_1h_tokens'], (int) $row['units'], $usd( $cost ), $mxn( $cost ), (int) $row['status'], $row['is_error'] ? 'sí' : 'no', $row['is_retry'] ? 'sí' : 'no' );
				}
				$offset += 2000;
			} while ( count( $batch ) === 2000 && $offset < 200000 );
			return $out;
		}
		global $wpdb;
		Digitalisimo_AI_Usage_Store::ensure();
		$daily = Digitalisimo_AI_Usage_Store::table( true );
		$rows  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$daily} WHERE day >= %s AND day <= %s ORDER BY day ASC, feature ASC, model ASC", $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabla propia.
		$out   = array( array( 'dia', 'blog_id', 'funcion', 'usuario', 'proveedor', 'modelo', 'llamadas', 'errores', 'reintentos', 'tokens_entrada', 'tokens_salida', 'cache_leida', 'cache_escrita', 'cache_escrita_1h', 'imagenes', 'costo_usd', 'costo_mxn', 'llamadas_sin_precio' ) );
		foreach ( $rows as $row ) {
			$out[] = array( $row['day'], $blog, Digitalisimo_AI_Usage::feature_label( $row['feature'] ), self::user_label( $row['user_id'], $row['feature'] ), $row['provider'], $row['model'], (int) $row['calls'], (int) $row['errors'], (int) $row['retries'], (int) $row['input_tokens'], (int) $row['output_tokens'], (int) $row['cache_read_tokens'], (int) $row['cache_write_tokens'], (int) $row['cache_write_1h_tokens'], (int) $row['units'], $usd( (float) $row['cost_usd'] ), $mxn( (float) $row['cost_usd'] ), (int) $row['unpriced'] );
		}
		return $out;
	}

	public static function csv_text( array $rows ) {
		$handle = fopen( 'php://temp', 'w+' );
		fwrite( $handle, "\xEF\xBB\xBF" ); // BOM: Excel abre los acentos bien.
		foreach ( $rows as $row ) {
			fputcsv( $handle, array_map( array( __CLASS__, 'cell' ), $row ), ',', '"', '\\' );
		}
		rewind( $handle );
		$text = (string) stream_get_contents( $handle );
		fclose( $handle );
		return $text;
	}

	/* ---------------- Aviso del presupuesto en el escritorio ---------------- */

	/** Id del aviso del presupuesto (lleva el sitio en una red: cerrarlo en un sitio no lo oculta en otro). */
	public static function notice_id() {
		return class_exists( 'Digitalisimo_Notices' ) ? Digitalisimo_Notices::site( self::NOTICE ) : self::NOTICE;
	}

	/**
	 * Aviso al 80 % y al 100 % del tope, por el ayudante común de avisos (la X de WordPress; queja del propietario del
	 * 2026-11-22): cerrado, no vuelve en el mes mientras siga igual (nivel, tope y qué pasa al llegarle); al pasar al
	 * 100 %, al cambiar el tope o en el mes siguiente, sí. Con la IA en pausa vuelve al día siguiente, como un error.
	 */
	public static function notice() {
		if ( ! current_user_can( 'manage_options' ) || ( function_exists( 'is_network_admin' ) && is_network_admin() ) ) {
			return;
		}
		$s = Digitalisimo_AI_Usage::settings();
		if ( empty( $s['budget_enabled'] ) ) {
			return;
		}
		$budget = Digitalisimo_AI_Usage::budget();
		if ( ! $budget['level'] ) {
			return;
		}
		$text = 100 === $budget['level']
			? ( $budget['paused'] ? Digitalisimo_AI_Usage::pause_message() : sprintf( 'este mes ya se gastó todo el tope de IA (%s de %s). Las funciones siguen activas porque el tope está en «sólo avisar».', self::plain_usd( $budget['spent'] ), self::plain_usd( $budget['cap'] ) ) )
			: sprintf( 'este mes ya se usó el %s %% del tope de IA (%s de %s).', number_format( $budget['percent'], 1 ), self::plain_usd( $budget['spent'] ), self::plain_usd( $budget['cap'] ) );
		$html = '<p><strong>Uso de IA:</strong> ' . esc_html( $text ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">Ver cuánto se ha gastado</a></p>';
		$type = 100 === $budget['level'] ? 'error' : 'warning';
		if ( class_exists( 'Digitalisimo_Notices' ) ) {
			Digitalisimo_Notices::show( self::notice_id(), $type, $html, array( 'hash' => array( substr( Digitalisimo_AI_Usage::today(), 0, 7 ), $budget['level'], $budget['paused'], $budget['cap'], $budget['action'] ), 'snooze' => $budget['paused'] ? DAY_IN_SECONDS : 0, 'cap' => 'manage_options' ) );
			return;
		}
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado arriba.
	}
}
