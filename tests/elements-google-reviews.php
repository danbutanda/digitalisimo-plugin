<?php
namespace Elementor {
	class Widget_Base {
		public $settings = array();
		public function get_settings_for_display() { return $this->settings; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	function check_reviews( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	function get_option( $name, $default = '' ) { return $GLOBALS['review_site_key'] ?? $default; }
	function get_site_option( $name, $default = '' ) { return $GLOBALS['review_network_key'] ?? $default; }
	function is_multisite() { return $GLOBALS['review_multisite'] ?? false; }
	function get_current_blog_id() { return $GLOBALS['review_blog_id'] ?? 1; }
	function wp_salt( $scheme ) { return 'test-secret'; }
	function add_query_arg( $key, $value, $url ) { return $url . '?' . rawurlencode( $key ) . '=' . rawurlencode( $value ); }
	function wp_remote_get( $url, $args ) { $GLOBALS['review_request'] = compact( 'url', 'args' ); return array( 'code' => 200, 'body' => json_encode( $GLOBALS['review_response'] ) ); }
	function is_wp_error( $value ) { return false; }
	function wp_remote_retrieve_response_code( $response ) { return $response['code']; }
	function wp_remote_retrieve_body( $response ) { return $response['body']; }
	function wp_strip_all_tags( $value ) { return strip_tags( (string) $value ); }
	function absint( $value ) { return abs( (int) $value ); }
	function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $value ) { return esc_html( $value ); }
	function esc_url( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function number_format_i18n( $value, $decimals = 0 ) { return number_format( $value, $decimals, '.', ',' ); }
	function current_user_can( $cap ) { return false; }
	function admin_url( $path ) { return 'https://site.test/wp-admin/' . $path; }
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-google-reviews-service.php';
	require __DIR__ . '/../digitalisimo-elements/modules/digitalisimo-widgets/class-google-reviews.php';
	$service = \Digitalisimo\Elements\Google_Reviews_Service::class;
	$site = 'site-secret'; $network = 'network-secret';
	$GLOBALS['review_site_key'] = $site; $GLOBALS['review_network_key'] = $network; $GLOBALS['review_multisite'] = true;
	check_reviews( $site === $service::api_key(), 'La clave del sitio debe prevalecer.' );
	$GLOBALS['review_site_key'] = '';
	check_reviews( $network === $service::api_key(), 'Debe heredar la clave de red.' );
	$GLOBALS['review_multisite'] = false;
	check_reviews( '' === $service::api_key(), 'Un sitio individual no debe leer opciones de red.' );
	$GLOBALS['review_multisite'] = true;
	$GLOBALS['review_response'] = array( 'id' => 'ChIJTest', 'reviews' => array( array( 'authorAttribution' => array( 'displayName' => 'Ana <script>x</script>', 'uri' => 'https://maps.google.com/ana' ), 'rating' => 5, 'text' => array( 'text' => 'Excelente <script>x</script>' ), 'googleMapsUri' => 'https://maps.google.com/review/1', 'relativePublishTimeDescription' => 'hace un mes', 'visitDate' => array( 'month' => 5, 'year' => 2026 ) ) ), 'attributions' => array( array( 'provider' => 'Proveedor', 'providerUri' => 'https://example.test/' ) ) );
	$data = $service::fetch( 'ChIJTest', 'es_MX' );
	check_reviews( 'ChIJTest' === $data['id'] && str_contains( $GLOBALS['review_request']['url'], 'languageCode=es-MX' ), 'Debe consultar Places para el lugar y el idioma solicitados.' );
	check_reviews( $network === $GLOBALS['review_request']['args']['headers']['X-Goog-Api-Key'] && str_contains( $GLOBALS['review_request']['args']['headers']['X-Goog-FieldMask'], 'reviews' ), 'La consulta usa la clave heredada y los campos de reseñas.' );
	$html = $service::review_markup( $data );
	check_reviews( str_contains( $html, 'Google Maps' ) && str_contains( $html, '05/2026' ) && str_contains( $html, 'https://maps.google.com/review/1' ), 'Debe atribuir Maps, mostrar fecha de visita y enlazar la reseña individual.' );
	check_reviews( ! str_contains( $html, '<script>' ) && str_contains( $html, '&lt;script&gt;' ) === false, 'El texto de terceros debe sanearse.' );
	$GLOBALS['review_request'] = null;
	check_reviews( array() === $service::fetch( '../../secret' ) && null === $GLOBALS['review_request'], 'Un Place ID inválido no debe consultar la API.' );
	$signature = $service::signature( 'ChIJTest' );
	$GLOBALS['review_blog_id'] = 2;
	check_reviews( ! hash_equals( $signature, $service::signature( 'ChIJTest' ) ), 'La firma pública debe aislar cada sitio de la red.' );
	$widget = new \Digitalisimo\Elements\Google_Reviews_Widget();
	$widget->settings = array( 'google_place_id' => 'ChIJTest', 'heading' => 'Opiniones' );
	ob_start(); ( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget ); $widget_html = ob_get_clean();
	check_reviews( str_contains( $widget_html, 'data-digi-google-reviews' ) && str_contains( $widget_html, 'https://site.test/wp-admin/admin-ajax.php' ) && ! str_contains( $widget_html, $network ), 'El widget carga diferido desde el sitio actual y no expone la clave.' );
	echo "DIGITALÍSIMO Elements: reseñas de Google aisladas y atribuidas.\n";
}
