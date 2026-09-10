<?php
defined( 'ABSPATH' ) || exit;

/** Administración avanzada de crawlers IA; reutiliza el registro y las opciones de SEO AI. */
class Digitalisimo_Integrations_SEO_AI_Crawler_Tools {
	const OPTION = 'digitalisimo_integrations_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 31 );
		if ( is_multisite() ) add_action( 'network_admin_menu', array( __CLASS__, 'network_menu' ), 31 );
	}
	public static function menu() { add_submenu_page( 'digitalisimo', 'SEO AI · Rastreadores', 'SEO AI · Rastreadores', 'manage_options', 'digitalisimo-seo-ai-crawlers', array( __CLASS__, 'page' ) ); }
	public static function network_menu() { add_submenu_page( 'digitalisimo-network', 'SEO AI · Rastreadores', 'SEO AI · Rastreadores', 'manage_network_options', 'digitalisimo-network-seo-ai-crawlers', array( __CLASS__, 'page' ) ); }
	private static function option( $network ) { return $network ? (array) get_site_option( self::OPTION, array() ) : (array) get_option( self::OPTION, array() ); }
	private static function effective( $key, $default = '' ) { return Digitalisimo_Integrations_Settings::get( $key, $default ); }
	private static function presets() { return array( 'visibility' => 'Máxima visibilidad', 'no_training' => 'Visibilidad sin entrenamiento', 'restricted' => 'Restringido', 'custom' => 'Personalizado' ); }
	private static function preset_rules( $preset ) {
		$rules = array();
		foreach ( Digitalisimo_Integrations_SEO_AI::crawlers() as $id => $crawler ) {
			$category = $crawler['category'] ?? '';
			$rules[ $id ] = 'restricted' === $preset ? 'disallow' : ( 'no_training' === $preset && 'Training' === $category ? 'disallow' : 'allow' );
		}
		return $rules;
	}
	private static function save( $network ) {
		if ( ! check_admin_referer( 'digitalisimo_seo_ai_crawlers' ) ) return;
		$option = self::option( $network );
		$preset = sanitize_key( wp_unslash( $_POST['crawler_preset'] ?? 'custom' ) );
		$rules = (array) wp_unslash( $_POST['crawler_rules'] ?? array() );
		if ( 'custom' !== $preset ) $rules = self::preset_rules( $preset );
		$valid = array(); foreach ( $rules as $id => $rule ) if ( in_array( $rule, array( 'allow', 'disallow', 'inherit' ), true ) ) $valid[ sanitize_key( $id ) ] = $rule;
		$option['seo_ai_crawler_rules'] = wp_json_encode( $valid ); $option['seo_ai_crawler_preset'] = $preset;
		$name = sanitize_text_field( wp_unslash( $_POST['custom_name'] ?? '' ) ); $ua = sanitize_text_field( wp_unslash( $_POST['custom_ua'] ?? '' ) );
		if ( $name && $ua ) { $custom = json_decode( (string) ( $option['seo_ai_crawlers'] ?? '' ), true ); if ( ! is_array( $custom ) ) $custom = array(); $id = sanitize_key( $name ); $custom[ $id ] = array( 'name' => $name, 'ua' => $ua, 'provider' => sanitize_text_field( wp_unslash( $_POST['custom_provider'] ?? '' ) ), 'category' => sanitize_text_field( wp_unslash( $_POST['custom_category'] ?? 'Custom' ) ), 'purpose' => sanitize_text_field( wp_unslash( $_POST['custom_purpose'] ?? '' ) ), 'default' => sanitize_key( wp_unslash( $_POST['custom_rule'] ?? 'disallow' ) ), 'doc' => esc_url_raw( wp_unslash( $_POST['custom_doc'] ?? '' ) ) ); $option['seo_ai_crawlers'] = wp_json_encode( $custom ); }
		if ( $network ) update_site_option( self::OPTION, $option ); else { update_option( self::OPTION, $option, false ); if ( is_multisite() ) { $inherit = (array) get_option( 'digitalisimo_seo_network_inherit', array() ); $inherit['seo_ai_crawler_rules'] = empty( $_POST['crawler_inherit'] ) ? 0 : 1; $inherit['seo_ai_crawler_preset'] = empty( $_POST['crawler_inherit'] ) ? 0 : 1; update_option( 'digitalisimo_seo_network_inherit', $inherit, false ); } }
	}
	public static function page() {
		$network = is_network_admin(); if ( $network ? ! current_user_can( 'manage_network_options' ) : ! current_user_can( 'manage_options' ) ) return;
		if ( isset( $_POST['save_crawlers'] ) ) self::save( $network );
		$rules = (array) json_decode( (string) self::effective( 'seo_ai_crawler_rules' ), true ); $preset = self::effective( 'seo_ai_crawler_preset', 'custom' ); $inherits = is_multisite() && Digitalisimo_Integrations_SEO_Resolver::inherits_network( 'seo_ai_crawler_rules' );
		echo '<div class="wrap"><h1>SEO AI · Rastreadores</h1><p>Controla por separado descubrimiento/búsqueda y entrenamiento. Estas reglas no sustituyen <code>noindex</code>.</p>';
		if ( file_exists( ABSPATH . 'robots.txt' ) ) echo '<div class="notice notice-warning"><p><strong>robots.txt físico detectado.</strong> Puede prevalecer sobre las reglas dinámicas de WordPress. Revísalo manualmente; Digitalisimo no lo modifica.</p></div>';
		echo '<form method="post">'; wp_nonce_field( 'digitalisimo_seo_ai_crawlers' ); if ( is_multisite() && ! $network ) echo '<p class="notice notice-info inline"><label><input type="hidden" name="crawler_inherit" value="0"><input type="checkbox" name="crawler_inherit" value="1" ' . checked( $inherits, true, false ) . '> <strong>Heredar reglas de crawlers de la red</strong></label><br><span class="description">Los cambios de red se reflejan dinámicamente mientras esta opción esté activa.</span></p>'; echo '<h2>Preset de acceso</h2><select name="crawler_preset">'; foreach ( self::presets() as $id => $label ) echo '<option value="' . esc_attr( $id ) . '" ' . selected( $preset, $id, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select><p class="description">Al guardar un preset se muestran y aplican las reglas resultantes.</p><h2>Reglas efectivas</h2><table class="widefat striped"><thead><tr><th>Rastreador</th><th>Tipo</th><th>Regla</th><th>Origen</th></tr></thead><tbody>';
		foreach ( Digitalisimo_Integrations_SEO_AI::crawlers() as $id => $crawler ) { $stored = $rules[ $id ] ?? ( $crawler['default'] ?? 'disallow' ); $origin = ''; $rule = Digitalisimo_Integrations_SEO_AI::crawler_rule( $id, $crawler, $origin ); echo '<tr><td><strong>' . esc_html( $crawler['name'] ) . '</strong><br><small>' . esc_html( $crawler['provider'] ) . '</small></td><td>' . esc_html( $crawler['category'] ) . '</td><td><select name="crawler_rules[' . esc_attr( $id ) . ']"><option value="allow" ' . selected( $stored, 'allow', false ) . '>Permitir</option><option value="disallow" ' . selected( $stored, 'disallow', false ) . '>Bloquear</option><option value="inherit" ' . selected( $stored, 'inherit', false ) . '>Heredar</option></select><br><small>Valor efectivo: ' . esc_html( 'allow' === $rule ? 'Permitir' : 'Bloquear' ) . '</small></td><td>' . esc_html( $network ? 'Configuración de red' : $origin ) . '</td></tr>'; }
		echo '</tbody></table><h2>Añadir rastreador personalizado</h2><p><input name="custom_name" placeholder="Nombre"> <input name="custom_ua" placeholder="User-Agent"> <input name="custom_provider" placeholder="Proveedor"> <select name="custom_category"><option>Custom</option><option>Search / Citation</option><option>Training</option><option>User-triggered Fetcher</option><option>General Search</option></select> <input name="custom_purpose" placeholder="Propósito"> <select name="custom_rule"><option value="disallow">Bloquear</option><option value="allow">Permitir</option></select> <input name="custom_doc" placeholder="URL documentación"></p>'; submit_button( 'Guardar rastreadores', 'primary', 'save_crawlers' ); echo '</form></div>';
	}
}
