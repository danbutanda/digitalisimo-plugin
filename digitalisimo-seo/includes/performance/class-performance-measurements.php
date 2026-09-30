<?php
defined( 'ABSPATH' ) || exit;

/** Comparativas manuales, aisladas por sitio de la red. */
class Digitalisimo_Integrations_Performance_Measurements {
	const OPTION = 'digitalisimo_performance_measurements';
	private static function metrics() { return array( 'score' => 'Performance (0–100)', 'fcp' => 'FCP (s)', 'lcp' => 'LCP (s)', 'tbt' => 'TBT (ms)', 'cls' => 'CLS' ); }

	public static function init() {
		add_action( 'admin_post_digitalisimo_performance_measurement', array( __CLASS__, 'save' ) );
	}

	public static function render( $network = false ) {
		if ( $network ? ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) return;
		$url = esc_url_raw( (string) wp_unslash( $_GET['measurement_url'] ?? ( $network ? network_home_url( '/' ) : home_url( '/' ) ) ) );
		$site_id = Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( $url, $network );
		$records = array();
		if ( $site_id ) {
			$switched = $network && $site_id !== get_current_blog_id();
			if ( $switched ) switch_to_blog( $site_id );
			try { $records = (array) get_option( self::OPTION, array() ); }
			finally { if ( $switched ) restore_current_blog(); }
		}
		$current = $records[ md5( $url ) ] ?? array();
		echo '<h2>Medición manual antes/después</h2><p>Guarda valores de PageSpeed o una prueba comparable. No se consulta ninguna API externa. Usa la misma URL, dispositivo y condiciones para comparar.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'digitalisimo_performance_measurement' );
		echo '<input type="hidden" name="action" value="digitalisimo_performance_measurement"><input type="hidden" name="network_context" value="' . ( $network ? '1' : '0' ) . '">';
		echo '<p><label>URL evaluada <input class="regular-text" type="url" name="url" value="' . esc_attr( $url ) . '" required></label></p>';
		echo '<table class="widefat striped"><thead><tr><th>Métrica</th><th>Antes</th><th>Después</th></tr></thead><tbody>';
		foreach ( self::metrics() as $key => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th>';
			foreach ( array( 'before', 'after' ) as $period ) echo '<td><input type="number" step="0.001" min="0" ' . ( 'score' === $key ? 'max="100" ' : '' ) . 'name="values[' . esc_attr( $period ) . '][' . esc_attr( $key ) . ']" value="' . esc_attr( $current[ $period ][ $key ] ?? '' ) . '"></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		submit_button( 'Guardar comparativa', 'secondary' );
		echo '</form>';
		if ( ! $site_id ) echo '<p class="notice notice-error">La URL no pertenece al sitio actual o a esta red.</p>';
		elseif ( $current ) echo '<p>Última actualización: ' . esc_html( $current['updated'] ?? '' ) . '. Estos valores fueron ingresados manualmente.</p>';
	}

	public static function save() {
		$network = ! empty( $_POST['network_context'] );
		if ( $network ? ! is_multisite() || ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado.' );
		check_admin_referer( 'digitalisimo_performance_measurement' );
		$url = esc_url_raw( trim( (string) wp_unslash( $_POST['url'] ?? '' ) ) );
		$site_id = Digitalisimo_Integrations_Asset_Diagnostics::site_for_url( $url, $network );
		if ( ! $site_id ) wp_die( 'La URL no pertenece al sitio permitido.' );
		$input = (array) wp_unslash( $_POST['values'] ?? array() );
		$value = array_merge( array( 'url' => $url, 'updated' => current_time( 'mysql' ) ), self::sanitize_metrics( $input ) );
		$switched = $network && $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try {
			$records = (array) get_option( self::OPTION, array() );
			$records[ md5( $url ) ] = $value;
			update_option( self::OPTION, array_slice( $records, -25, null, true ), false );
		} finally { if ( $switched ) restore_current_blog(); }
		$target = $network ? network_admin_url( 'admin.php?page=digitalisimo-network-seo&tab=performance' ) : admin_url( 'admin.php?page=digitalisimo-seo-performance' );
		wp_safe_redirect( add_query_arg( 'measurement_url', $url, $target ) );
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
