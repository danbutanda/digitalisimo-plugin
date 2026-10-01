<?php
defined( 'ABSPATH' ) || exit;

/** Google Tag, GA4 y GTM opt-in, con preflight de duplicados por sitio. */
class Digitalisimo_Integrations_Performance_Tracking {
	private static $gtm_printed = '';
	/** Claves públicas de configuración; nunca se muestran tokens ni opciones completas de Site Kit. */
	private static function site_kit_fields() {
		return array(
			'googlesitekit_analytics-4_settings' => array( 'title' => 'Analytics 4', 'fields' => array( 'accountID' => 'Cuenta', 'propertyID' => 'Propiedad', 'webDataStreamID' => 'Flujo web', 'measurementID' => 'ID de medición GA4', 'googleTagID' => 'Google Tag ID', 'googleTagAccountID' => 'Cuenta de Google Tag', 'googleTagContainerID' => 'Contenedor de Google Tag', 'adsConversionID' => 'Conversión de Ads (anterior)', 'useSnippet' => 'Fragmento de Analytics', 'trackingDisabled' => 'Tracking desactivado para' ) ),
			'googlesitekit_tagmanager_settings' => array( 'title' => 'Tag Manager', 'fields' => array( 'accountID' => 'Cuenta', 'containerID' => 'Contenedor web', 'ampContainerID' => 'Contenedor AMP', 'internalContainerID' => 'Contenedor interno', 'internalAMPContainerID' => 'Contenedor AMP interno', 'useSnippet' => 'Fragmento de Tag Manager' ) ),
			'googlesitekit_ads_settings' => array( 'title' => 'Google Ads', 'fields' => array( 'conversionID' => 'ID de conversión', 'paxConversionID' => 'ID de conversión PAX', 'customerID' => 'Cliente', 'extCustomerID' => 'Cliente externo' ) ),
			'googlesitekit_adsense_settings' => array( 'title' => 'AdSense', 'fields' => array( 'accountID' => 'Cuenta', 'clientID' => 'Cliente', 'useSnippet' => 'Fragmento de AdSense' ) ),
			'googlesitekit_search-console_settings' => array( 'title' => 'Search Console', 'fields' => array( 'propertyID' => 'Propiedad verificada' ) ),
		);
	}

	/** Lee únicamente opciones del sitio actual y campos conocidos que no contienen credenciales. */
	public static function site_kit_inventory() {
		$result = array();
		foreach ( self::site_kit_fields() as $option => $module ) {
			$settings = get_option( $option, array() );
			if ( ! is_array( $settings ) || ! $settings ) continue;
			$values = array();
			foreach ( $module['fields'] as $key => $label ) {
				if ( ! array_key_exists( $key, $settings ) ) continue;
				$value = $settings[ $key ];
				if ( 'useSnippet' === $key ) $value = $value ? 'Sí' : 'No';
				elseif ( 'trackingDisabled' === $key ) $value = is_array( $value ) ? implode( ', ', array_intersect( $value, array( 'loggedinUsers' ) ) ) : '';
				elseif ( ! is_scalar( $value ) ) continue;
				else $value = trim( (string) $value );
				if ( '' !== $value ) $values[ $label ] = $value;
			}
			if ( $values ) $result[ $module['title'] ] = $values;
		}
		return $result;
	}

	/** IDs del sitio actual admitidos por Google Tracking; nunca mezcla datos de otros sitios. */
	public static function site_kit_ids() {
		$analytics = (array) get_option( 'googlesitekit_analytics-4_settings', array() );
		$manager = (array) get_option( 'googlesitekit_tagmanager_settings', array() );
		$google_tag = trim( (string) ( $analytics['googleTagID'] ?? '' ) );
		return array(
			'perf_gt_id' => self::sanitize_id( $google_tag, 'gt' ),
			'perf_ga4_id' => self::sanitize_id( $analytics['measurementID'] ?? '', 'ga4' ) ?: self::sanitize_id( $google_tag, 'ga4' ),
			'perf_gtm_id' => self::sanitize_id( $manager['containerID'] ?? '', 'gtm' ),
		);
	}

	/** El selector de sitio de la consola de red sólo determina el inventario visible. */
	public static function render_site_kit( $network = false ) {
		$site_id = $network ? absint( $_GET['site_id'] ?? get_current_blog_id() ) : get_current_blog_id();
		if ( $network && ! get_site( $site_id ) ) $site_id = get_current_blog_id();
		$switched = $site_id !== get_current_blog_id();
		if ( $switched ) switch_to_blog( $site_id );
		try {
			$active = Digitalisimo_Integrations_Performance_Migration::site_kit_active();
			$inventory = self::site_kit_inventory();
			echo '<h2>Valores detectados de Google Site Kit</h2><p>Sitio: <code>' . esc_html( home_url( '/' ) ) . '</code> · Plugin: ' . ( $active ? 'activo' : 'inactivo' ) . '. Los IDs válidos llenan los campos vacíos de este sitio al activar y guardar Google Tracking. Los defaults de red no reciben IDs de un subsitio.</p>';
			$ids = self::site_kit_ids();
			if ( array_filter( $ids ) ) {
				echo '<table class="form-table"><tbody>';
				foreach ( array( 'perf_gt_id' => 'Google Tag ID', 'perf_ga4_id' => 'GA4 ID', 'perf_gtm_id' => 'GTM ID' ) as $key => $label ) {
					echo '<tr><th scope="row">' . esc_html( $label ) . ' de este sitio</th><td><input class="regular-text" type="text" readonly value="' . esc_attr( $ids[ $key ] ) . '"></td></tr>';
				}
				echo '</tbody></table>';
			}
			if ( ! $inventory ) { echo '<p>No hay valores de Site Kit guardados para este sitio.</p>'; return; }
			echo '<table class="widefat striped"><thead><tr><th>Módulo</th><th>Valor</th><th>Detectado</th></tr></thead><tbody>';
			foreach ( $inventory as $module => $values ) foreach ( $values as $label => $value ) echo '<tr><td>' . esc_html( $module ) . '</td><td>' . esc_html( $label ) . '</td><td><code>' . esc_html( $value ) . '</code></td></tr>';
			echo '</tbody></table><p>Site Kit puede cargar etiquetas según la configuración de cada módulo. Mientras esté activo, Digitalísimo bloquea su propia salida de tracking para evitar duplicados.</p>';
		} finally { if ( $switched ) restore_current_blog(); }
	}

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'head' ), 6 );
		add_action( 'wp_body_open', array( __CLASS__, 'body' ), 10 );
	}

	public static function sanitize_id( $value, $kind ) {
		$value = strtoupper( trim( (string) $value ) );
		$patterns = array( 'gt' => '/^GT-[A-Z0-9]{4,30}$/', 'ga4' => '/^G-[A-Z0-9]{4,30}$/', 'gtm' => '/^GTM-[A-Z0-9]{4,30}$/' );
		return isset( $patterns[ $kind ] ) && preg_match( $patterns[ $kind ], $value ) ? $value : '';
	}

	public static function sanitize_mode( $value ) {
		return in_array( $value, array( 'normal', 'domcontentloaded', 'load', 'interaction', 'delay' ), true ) ? $value : 'normal';
	}

	public static function sanitize_seconds( $value ) {
		return min( 120, max( 1, absint( $value ) ) );
	}

	private static function config() {
		$option = array( 'gt' => 'perf_gt_id', 'ga4' => 'perf_ga4_id', 'gtm' => 'perf_gtm_id' );
		$detected = self::site_kit_ids();
		$ids = array();
		foreach ( $option as $kind => $key ) {
			$ids[ $kind ] = self::sanitize_id( Digitalisimo_Integrations_SEO_Resolver::option( $key ), $kind );
			if ( ! $ids[ $kind ] ) $ids[ $kind ] = $detected[ $key ];
		}
		return $ids;
	}

	private static function queued_google_script() {
		$scripts = wp_scripts();
		foreach ( (array) $scripts->queue as $handle ) {
			$src = (string) ( $scripts->registered[ $handle ]->src ?? '' );
			if ( preg_match( '#(?:googletagmanager\.com/(?:gtag/js|gtm\.js)|google-analytics\.com/)#i', $src ) ) return true;
		}
		return false;
	}

	public static function ready() {
		if ( ! Digitalisimo_Integrations_Performance_Manager::frontend_safe() || ! Digitalisimo_Integrations_SEO_Resolver::option( 'perf_tracking_enabled' ) ) return false;
		$ids = array_filter( self::config() );
		if ( ! $ids || Digitalisimo_Integrations_Performance_Migration::tracking_conflict( $ids ) || self::queued_google_script() ) return false;
		return true;
	}

	public static function head() {
		if ( ! self::ready() ) return;
		$ids = self::config();
		$mode = self::sanitize_mode( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_tracking_mode' ) );
		$delay = self::sanitize_seconds( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_tracking_delay' ) );
		$fallback = self::sanitize_seconds( Digitalisimo_Integrations_SEO_Resolver::option( 'perf_tracking_fallback' ) );
		self::$gtm_printed = $ids['gtm'];
		$config = wp_json_encode( array( 'gt' => $ids['gt'], 'ga4' => $ids['ga4'], 'gtm' => $ids['gtm'], 'mode' => $mode, 'delay' => $delay, 'fallback' => $fallback ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		echo '<script id="digitalisimo-performance-tracking">(function(w,d,c){';
		echo 'if(w.__digitalisimoTrackingReady)return;w.__digitalisimoTrackingReady=1;w.dataLayer=w.dataLayer||[];';
		echo 'if(c.gt||c.ga4){w.gtag=w.gtag||function(){w.dataLayer.push(arguments)};w.gtag("js",new Date());if(c.gt)w.gtag("config",c.gt);if(c.ga4&&c.ga4!==c.gt)w.gtag("config",c.ga4)}';
		echo 'if(c.gtm)w.dataLayer.push({"gtm.start":Date.now(),event:"gtm.js"});';
		echo 'var loaded={};function inject(url,key){if(loaded[key])return;loaded[key]=1;var s=d.createElement("script");s.async=true;s.src=url;d.head.appendChild(s)}';
		echo 'function start(){var id=c.gt||c.ga4;if(id)inject("https://www.googletagmanager.com/gtag/js?id="+encodeURIComponent(id),"gtag");if(c.gtm)inject("https://www.googletagmanager.com/gtm.js?id="+encodeURIComponent(c.gtm),"gtm")}';
		echo 'function onLoad(fn){if(d.readyState==="complete")fn();else w.addEventListener("load",fn,{once:true})}';
		echo 'if(c.mode==="normal")start();else if(c.mode==="domcontentloaded"){if(d.readyState==="loading")d.addEventListener("DOMContentLoaded",start,{once:true});else start()}else if(c.mode==="load")onLoad(start);else if(c.mode==="delay")onLoad(function(){w.setTimeout(start,c.delay*1000)});else{var events=["scroll","touchstart","keydown","click"];var trigger=function(){events.forEach(function(e){w.removeEventListener(e,trigger)});start()};events.forEach(function(e){w.addEventListener(e,trigger,{once:true,passive:true})});onLoad(function(){w.setTimeout(start,c.fallback*1000)})}';
		echo '})(window,document,' . $config . ');</script>' . "\n";
	}

	public static function body() {
		if ( ! self::$gtm_printed ) return;
		echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( self::$gtm_printed ) . '" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>' . "\n";
	}
}
