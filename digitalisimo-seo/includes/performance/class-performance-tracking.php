<?php
defined( 'ABSPATH' ) || exit;

/** Google Tag, GA4 y GTM opt-in, con preflight de duplicados por sitio. */
class Digitalisimo_Integrations_Performance_Tracking {
	private static $gtm_printed = '';

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
		$ids = array();
		foreach ( $option as $kind => $key ) $ids[ $kind ] = self::sanitize_id( Digitalisimo_Integrations_SEO_Resolver::option( $key ), $kind );
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
