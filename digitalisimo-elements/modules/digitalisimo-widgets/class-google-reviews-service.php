<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Ajustes por sitio/red y consulta diferida a Google Places sin almacenar reseñas. */
final class Google_Reviews_Service {
	private const KEY = 'digitalisimo_elements_google_places_key';
	private const ACTION = 'digitalisimo_elements_google_reviews';

	public static function init() {
		add_action( 'admin_menu', array( self::class, 'site_menu' ) );
		add_action( 'network_admin_menu', array( self::class, 'network_menu' ) );
		add_action( 'admin_post_digitalisimo_elements_google_reviews_save', array( self::class, 'save' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( self::class, 'ajax' ) );
	}

	public static function site_menu() {
		add_options_page( 'Google Reviews · DIGITALÍSIMO Elements', 'Google Reviews · DIGITALÍSIMO', 'manage_options', 'digitalisimo-elements-google-reviews', array( self::class, 'settings_page' ) );
	}

	public static function network_menu() {
		add_submenu_page( 'settings.php', 'Google Reviews · DIGITALÍSIMO Elements', 'Google Reviews · DIGITALÍSIMO', 'manage_network_options', 'digitalisimo-elements-google-reviews', array( self::class, 'settings_page' ) );
	}

	private static function configured_key( $network ) {
		$value = $network ? get_site_option( self::KEY, '' ) : get_option( self::KEY, '' );
		return is_string( $value ) ? trim( $value ) : '';
	}

	public static function api_key() {
		$site = self::configured_key( false );
		if ( '' !== $site ) { return $site; }
		return is_multisite() ? self::configured_key( true ) : '';
	}

	public static function settings_page() {
		$network = is_network_admin();
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) ) { wp_die( 'No tienes permisos para configurar esta clave.' ); }
		$stored = self::configured_key( $network );
		$inherited = ! $network && is_multisite() && '' === $stored && '' !== self::configured_key( true );
		echo '<div class="wrap"><h1>Google Reviews · DIGITALÍSIMO Elements</h1><p>Configura una clave de Places API (New) restringida a tu servidor. La clave se usa sólo en el servidor y no se muestra en el HTML público.</p>';
		if ( isset( $_GET['updated'] ) ) { echo '<div class="notice notice-success"><p>Configuración guardada.</p></div>'; }
		echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post">';
		wp_nonce_field( 'digitalisimo_elements_google_reviews_save_' . ( $network ? 'network' : 'site' ) );
		echo '<input type="hidden" name="action" value="digitalisimo_elements_google_reviews_save"><input type="hidden" name="scope" value="' . ( $network ? 'network' : 'site' ) . '">';
		echo '<table class="form-table"><tr><th scope="row"><label for="digi-google-key">Clave API de Google Places</label></th><td><input id="digi-google-key" name="google_places_key" type="password" value="" autocomplete="new-password" class="regular-text" placeholder="' . ( $stored ? 'Clave guardada' : ( $inherited ? 'Heredada de la red' : 'Sin configurar' ) ) . '"><p class="description">Deja el campo vacío para conservar la clave actual. En Multisite, un sitio sin clave propia hereda la de la red.</p><label><input type="checkbox" name="remove_key" value="1"> Eliminar la clave de este ámbito</label></td></tr></table>';
		submit_button( 'Guardar clave' );
		echo '</form></div>';
	}

	public static function save() {
		$network = 'network' === sanitize_key( $_POST['scope'] ?? '' );
		if ( ! current_user_can( $network ? 'manage_network_options' : 'manage_options' ) ) { wp_die( 'No tienes permisos para guardar esta clave.' ); }
		check_admin_referer( 'digitalisimo_elements_google_reviews_save_' . ( $network ? 'network' : 'site' ) );
		if ( ! empty( $_POST['remove_key'] ) ) {
			$network ? delete_site_option( self::KEY ) : delete_option( self::KEY );
		} else {
			$value = trim( sanitize_text_field( wp_unslash( $_POST['google_places_key'] ?? '' ) ) );
			if ( '' !== $value ) { $network ? update_site_option( self::KEY, $value ) : update_option( self::KEY, $value, false ); }
		}
		$url = $network ? network_admin_url( 'settings.php?page=digitalisimo-elements-google-reviews&updated=1' ) : admin_url( 'options-general.php?page=digitalisimo-elements-google-reviews&updated=1' );
		wp_safe_redirect( $url );
		exit;
	}

	public static function valid_place_id( $place_id ) {
		return is_string( $place_id ) && 1 === preg_match( '/^[A-Za-z0-9_:\-]{1,200}$/D', $place_id );
	}

	public static function signature( $place_id ) {
		return hash_hmac( 'sha256', get_current_blog_id() . '|' . $place_id, wp_salt( 'auth' ) );
	}

	public static function maps_url( $place_id ) {
		return 'https://www.google.com/maps/search/?api=1&query=Google&query_place_id=' . rawurlencode( $place_id );
	}

	public static function fetch( $place_id, $language = '' ) {
		$key = self::api_key();
		if ( '' === $key || ! self::valid_place_id( $place_id ) ) { return array(); }
		$url = 'https://places.googleapis.com/v1/places/' . rawurlencode( $place_id );
		$headers = array( 'X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => 'id,displayName,rating,userRatingCount,reviews,googleMapsUri,attributions' );
		$language = str_replace( '_', '-', (string) $language );
		if ( 1 === preg_match( '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8}){0,2}$/D', $language ) ) { $url = add_query_arg( 'languageCode', $language, $url ); }
		$response = wp_remote_get( $url, array( 'headers' => $headers, 'timeout' => 5, 'redirection' => 0 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { return array(); }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) && ( $data['id'] ?? '' ) === $place_id ? $data : array();
	}

	public static function review_markup( $data, $limit = 5 ) {
		$reviews = is_array( $data['reviews'] ?? null ) ? array_slice( $data['reviews'], 0, max( 1, min( 5, absint( $limit ) ) ) ) : array();
		if ( ! $reviews ) { return ''; }
		$place = trim( wp_strip_all_tags( (string) ( $data['displayName']['text'] ?? '' ) ) );
		$maps = esc_url( $data['googleMapsUri'] ?? self::maps_url( (string) ( $data['id'] ?? '' ) ) );
		$out = '<div class="digi-google-reviews__heading"><strong>' . esc_html( $place ) . '</strong>';
		if ( isset( $data['rating'], $data['userRatingCount'] ) ) { $out .= '<span>' . esc_html( number_format_i18n( (float) $data['rating'], 1 ) ) . '/5 · ' . esc_html( number_format_i18n( absint( $data['userRatingCount'] ) ) ) . ' opiniones</span>'; }
		$out .= '</div><ul class="digi-google-reviews__list">';
		foreach ( $reviews as $review ) {
			if ( ! is_array( $review ) ) { continue; }
			$author = is_array( $review['authorAttribution'] ?? null ) ? $review['authorAttribution'] : array();
			$name = trim( wp_strip_all_tags( (string) ( $author['displayName'] ?? '' ) ) );
			$photo = esc_url( $author['photoUri'] ?? '' );
			$profile = esc_url( $author['uri'] ?? '' );
			$link = esc_url( $review['googleMapsUri'] ?? $maps );
			$text = trim( wp_strip_all_tags( (string) ( $review['text']['text'] ?? $review['originalText']['text'] ?? '' ) ) );
			$rating = max( 0, min( 5, (float) ( $review['rating'] ?? 0 ) ) );
			$out .= '<li class="digi-google-reviews__item"><div class="digi-google-reviews__author">';
			if ( $photo ) { $out .= '<img src="' . $photo . '" alt="" width="40" height="40" loading="lazy">'; }
			if ( $name ) { $out .= $profile ? '<a href="' . $profile . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . '</a>' : '<span>' . esc_html( $name ) . '</span>'; }
			$out .= '</div><span class="digi-google-reviews__rating" aria-label="' . esc_attr( 'Calificación: ' . $rating . ' de 5' ) . '">' . esc_html( number_format_i18n( $rating, 1 ) ) . ' ★</span>';
			if ( $text ) { $out .= '<p>' . esc_html( $text ) . '</p>'; }
			$published = trim( wp_strip_all_tags( (string) ( $review['relativePublishTimeDescription'] ?? '' ) ) );
			if ( $published ) { $out .= '<small>' . esc_html( $published ) . '</small>'; }
			$visit = is_array( $review['visitDate'] ?? null ) ? $review['visitDate'] : array();
			if ( ! empty( $visit['year'] ) && ! empty( $visit['month'] ) ) { $out .= '<small> · Visita: ' . esc_html( sprintf( '%02d/%04d', absint( $visit['month'] ), absint( $visit['year'] ) ) ) . '</small>'; }
			if ( $link ) { $out .= '<a href="' . $link . '" target="_blank" rel="noopener noreferrer">Ver reseña en Google Maps</a>'; }
			$out .= '</li>';
		}
		$out .= '</ul><p class="digi-google-reviews__source">Fuente: <a href="' . $maps . '" target="_blank" rel="noopener noreferrer" translate="no">Google Maps</a>. Las reseñas disponibles (máximo 5) las ordena Google por relevancia; aquí no se filtran ni reordenan. <a href="https://support.google.com/contributionpolicy/answer/7422880" target="_blank" rel="noopener noreferrer">Política de reseñas</a>.</p>';
		foreach ( (array) ( $data['attributions'] ?? array() ) as $attribution ) {
			if ( ! is_array( $attribution ) || empty( $attribution['provider'] ) ) { continue; }
			$provider = esc_html( wp_strip_all_tags( (string) $attribution['provider'] ) );
			$provider_url = esc_url( $attribution['providerUri'] ?? '' );
			$out .= '<p class="digi-google-reviews__source">Datos: ' . ( $provider_url ? '<a href="' . $provider_url . '" target="_blank" rel="noopener noreferrer">' . $provider . '</a>' : $provider ) . '</p>';
		}
		return $out;
	}

	public static function ajax() {
		$place_id = sanitize_text_field( wp_unslash( $_POST['place_id'] ?? '' ) );
		$signature = sanitize_text_field( wp_unslash( $_POST['signature'] ?? '' ) );
		$limit = absint( $_POST['limit'] ?? 5 );
		if ( ! self::valid_place_id( $place_id ) || ! hash_equals( self::signature( $place_id ), $signature ) ) { wp_send_json_error( array( 'message' => 'Solicitud no válida.' ), 403 ); }
		$data = self::fetch( $place_id, get_locale() );
		$markup = self::review_markup( $data, $limit );
		if ( '' === $markup ) { wp_send_json_error( array( 'message' => 'No hay reseñas disponibles en este momento.' ), 502 ); }
		wp_send_json_success( array( 'html' => $markup ) );
	}
}
