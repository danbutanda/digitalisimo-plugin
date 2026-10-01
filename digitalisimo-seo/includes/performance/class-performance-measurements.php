<?php
defined( 'ABSPATH' ) || exit;

/** Comparativas manuales, aisladas por sitio de la red. */
class Digitalisimo_Integrations_Performance_Measurements {
	const OPTION = 'digitalisimo_performance_measurements';
	private static function metrics() { return array( 'score' => 'Puntuación Performance (0–100)', 'fcp' => 'FCP (segundos)', 'lcp' => 'LCP (segundos)', 'tbt' => 'TBT (milisegundos)', 'cls' => 'CLS (sin unidad)' ); }

	public static function init() {
		add_action( 'admin_post_digitalisimo_performance_measurement', array( __CLASS__, 'save' ) );
	}

	public static function render( $network = false ) {
		if ( $network ? ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) return;
		$selected_site = $network ? absint( $_GET['site_id'] ?? get_current_blog_id() ) : get_current_blog_id();
		if ( $network && ! get_site( $selected_site ) ) $selected_site = get_current_blog_id();
		$last_result = get_transient( 'digitalisimo_perf_result_' . get_current_user_id() );
		$last_url = Digitalisimo_Integrations_Asset_Diagnostics::capture_belongs_to_site( $last_result, $selected_site ) ? $last_result['url'] : '';
		$url = esc_url_raw( (string) wp_unslash( $_GET['performance_url'] ?? $_GET['measurement_url'] ?? ( $last_url ?: get_home_url( $selected_site, '/' ) ) ) );
		$site_id = Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( $url, $network );
		$records = array();
		if ( $site_id ) {
			$switched = $network && $site_id !== get_current_blog_id();
			if ( $switched ) switch_to_blog( $site_id );
			try { $records = (array) get_option( self::OPTION, array() ); }
			finally { if ( $switched ) restore_current_blog(); }
		}
		$current = $records[ md5( $url ) ] ?? array();
		echo '<h2>Mediciones externas · antes y después</h2><p>Esta pestaña registra resultados de PageSpeed o Lighthouse que obtuviste por separado. El diagnóstico de recursos está en «Estado» y no calcula estas métricas. La comparación es opcional.</p>';
		if ( ! $site_id ) { echo '<p class="notice notice-error">Elige arriba una URL de este sitio o de esta red para registrar una comparación.</p>'; return; }
		echo '<p><strong>URL de esta comparación:</strong> <code>' . esc_html( $url ) . '</code></p>';
		if ( $current ) echo '<p>Última actualización: ' . esc_html( $current['updated'] ?? '' ) . '. Estos valores fueron ingresados manualmente.</p>';
		echo '<details><summary>Registrar resultados externos (opcional)</summary><p>Usa la misma URL, el mismo dispositivo y condiciones similares en ambas pruebas. Puedes llenar sólo las métricas que tengas: «Antes» es previo al cambio y «Después» es posterior.</p><p>FCP indica cuándo aparece el primer contenido; LCP, el contenido principal; TBT, el tiempo de bloqueo de JavaScript; CLS, los saltos visuales. Copia los números de tu informe, sin escribir unidades.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'digitalisimo_performance_measurement' );
		echo '<input type="hidden" name="action" value="digitalisimo_performance_measurement"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '">';
		echo '<input type="hidden" name="url" value="' . esc_attr( $url ) . '">';
		echo '<table class="widefat striped"><thead><tr><th>Métrica</th><th>Antes</th><th>Después</th></tr></thead><tbody>';
		foreach ( self::metrics() as $key => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th>';
			foreach ( array( 'before', 'after' ) as $period ) echo '<td><input type="number" step="0.001" min="0" ' . ( 'score' === $key ? 'max="100" ' : '' ) . 'name="values[' . esc_attr( $period ) . '][' . esc_attr( $key ) . ']" value="' . esc_attr( $current[ $period ][ $key ] ?? '' ) . '"></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		submit_button( 'Guardar resultados externos', 'secondary' );
		echo '</form></details>';
	}

	public static function save() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		check_admin_referer( 'digitalisimo_performance_measurement' );
		$url = esc_url_raw( trim( (string) wp_unslash( $_POST['url'] ?? '' ) ) );
		$site_id = Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( $url, $network );
		if ( ! $site_id ) wp_die( 'La URL no pertenece al sitio permitido.' );
		$input = (array) wp_unslash( $_POST['values'] ?? array() );
		$metrics = self::sanitize_metrics( $input );
		if ( ! $metrics['before'] && ! $metrics['after'] ) wp_die( 'Escribe al menos una métrica antes de guardar.' );
		$value = array_merge( array( 'url' => $url, 'updated' => current_time( 'mysql' ) ), $metrics );
		$switched = $network && $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try {
			$records = (array) get_option( self::OPTION, array() );
			$records[ md5( $url ) ] = $value;
			update_option( self::OPTION, array_slice( $records, -25, null, true ), false );
		} finally { if ( $switched ) restore_current_blog(); }
		$target = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-performance&section=measurements' ) : admin_url( 'admin.php?page=digitalisimo-performance&section=measurements' );
		$args = array( 'performance_url' => $url );
		if ( $network ) $args['site_id'] = $site_id;
		wp_safe_redirect( add_query_arg( $args, $target ) );
		exit;
	}

	/** Rechaza valores negativos, no finitos y puntuaciones fuera de 0–100. */
	public static function sanitize_metrics( $input ) {
		$output = array( 'before' => array(), 'after' => array() );
		foreach ( array( 'before', 'after' ) as $period ) {
			$values = isset( $input[ $period ] ) && is_array( $input[ $period ] ) ? $input[ $period ] : array();
			foreach ( self::metrics() as $key => $unused ) {
				$raw = isset( $values[ $key ] ) && is_scalar( $values[ $key ] ) ? trim( (string) $values[ $key ] ) : '';
				if ( '' === $raw ) continue;
				if ( ! is_numeric( $raw ) || ! is_finite( (float) $raw ) || (float) $raw < 0 || ( 'score' === $key && (float) $raw > 100 ) ) wp_die( 'Una métrica contiene un valor inválido.' );
				$output[ $period ][ $key ] = round( (float) $raw, 3 );
			}
		}
		return $output;
	}
}
