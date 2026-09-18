<?php
defined( 'ABSPATH' ) || exit;

/**
 * Perfiles oficiales del sitio, una red por campo.
 *
 * Antes era un único textarea con una URL por línea: no se sabía qué red
 * faltaba ni si una dirección estaba mal puesta. Ahora cada red tiene su campo
 * y queda un textarea sólo para perfiles que no encajan en la lista.
 *
 * El resultado sigue siendo la lista plana que consume `sameAs` del schema, así
 * que lo publicado no cambia de forma.
 */
class Digitalisimo_Integrations_Social_Profiles {
	const KEY    = 'seo_social_profiles';
	const LEGACY = 'seo_knowledge_urls';

	/** Redes admitidas: etiqueta, ejemplo y dominios con los que se reconoce una URL. */
	public static function networks() {
		return array(
			'facebook'        => array( 'Facebook', 'https://facebook.com/tumarca', array( 'facebook.com', 'fb.com', 'fb.me' ) ),
			'instagram'       => array( 'Instagram', 'https://instagram.com/tumarca', array( 'instagram.com' ) ),
			'x'               => array( 'X (Twitter)', 'https://x.com/tumarca', array( 'x.com', 'twitter.com' ) ),
			'linkedin'        => array( 'LinkedIn', 'https://linkedin.com/company/tumarca', array( 'linkedin.com' ) ),
			'youtube'         => array( 'YouTube', 'https://youtube.com/@tumarca', array( 'youtube.com', 'youtu.be' ) ),
			'tiktok'          => array( 'TikTok', 'https://tiktok.com/@tumarca', array( 'tiktok.com' ) ),
			'whatsapp'        => array( 'WhatsApp', 'https://wa.me/521XXXXXXXXXX', array( 'wa.me', 'whatsapp.com' ) ),
			'pinterest'       => array( 'Pinterest', 'https://pinterest.com/tumarca', array( 'pinterest.com', 'pin.it' ) ),
			'threads'         => array( 'Threads', 'https://threads.net/@tumarca', array( 'threads.net', 'threads.com' ) ),
			'google_business' => array( 'Perfil de Google Business', 'https://g.page/tumarca', array( 'g.page', 'business.google.com', 'maps.app.goo.gl' ) ),
		);
	}

	/** Valor efectivo de cada red, resolviendo contenido → sitio → red → default. */
	public static function values() {
		$stored = Digitalisimo_Integrations_SEO_Resolver::option( self::KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$values = array();
		foreach ( self::networks() as $id => $unused ) $values[ $id ] = isset( $stored[ $id ] ) ? (string) $stored[ $id ] : '';

		// Nada configurado todavía: se prellena desde el textarea antiguo para no
		// obligar a reescribir a mano lo que ya estaba puesto.
		if ( ! array_filter( $values ) ) {
			foreach ( self::classify( self::legacy_lines() )['known'] as $id => $url ) $values[ $id ] = $url;
		}
		return $values;
	}

	/** Perfiles sueltos que no pertenecen a ninguna red de la lista. */
	public static function extras() {
		return self::classify( self::legacy_lines() )['extra'];
	}

	/** Lista plana para `sameAs`, sin huecos ni repeticiones. */
	public static function all_urls() {
		$urls = array_merge( array_values( array_filter( self::values() ) ), self::extras() );
		return array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
	}

	/** ¿Hay al menos un perfil declarado? Lo usa el diagnóstico de entidades. */
	public static function configured() {
		return (bool) self::all_urls();
	}

	private static function legacy_lines() {
		$raw = (string) Digitalisimo_Integrations_SEO_Resolver::option( self::LEGACY, '' );
		return array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
	}

	/** Reparte una lista de URLs entre redes reconocidas y sobrantes. */
	private static function classify( $lines ) {
		$known = array();
		$extra = array();
		foreach ( $lines as $line ) {
			$host = strtolower( (string) wp_parse_url( $line, PHP_URL_HOST ) );
			$host = preg_replace( '/^www\./', '', $host );
			$id   = '';
			foreach ( self::networks() as $network => $meta ) {
				if ( in_array( $host, $meta[2], true ) ) { $id = $network; break; }
			}
			// La primera URL de cada red gana; el resto se conserva como perfil suelto.
			if ( $id && ! isset( $known[ $id ] ) ) $known[ $id ] = $line;
			else $extra[] = $line;
		}
		return array( 'known' => $known, 'extra' => $extra );
	}

	/**
	 * Normaliza el envío del formulario.
	 *
	 * @param mixed $input   Valor recibido para la clave.
	 * @param array $current Valor almacenado, que se conserva si no llega nada.
	 */
	public static function sanitize( $input, $current = array() ) {
		if ( ! is_array( $input ) ) return is_array( $current ) ? $current : array();
		$output = array();
		foreach ( self::networks() as $id => $unused ) {
			$url = esc_url_raw( trim( (string) ( $input[ $id ] ?? '' ) ) );
			if ( $url ) $output[ $id ] = $url;
		}
		return $output;
	}

	/**
	 * Limpia el textarea de perfiles sueltos.
	 *
	 * Quita las URLs que ya tienen campo propio: de lo contrario `sameAs` las
	 * publicaría dos veces y el prellenado volvería a dispararse.
	 */
	public static function sanitize_extras( $input ) {
		$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $input ) ) );
		return implode( "\n", self::classify( $lines )['extra'] );
	}

	/** Campos del formulario por sitio, con su herencia de red. */
	public static function site_fields() {
		$option   = Digitalisimo_Integrations_Settings::OPTION;
		$values   = self::values();
		$inherit  = is_multisite() && Digitalisimo_Integrations_SEO_Resolver::inherits_network( self::KEY );
		$disabled = $inherit ? ' disabled' : '';

		echo '<tr><th scope="row">' . esc_html__( 'Perfiles oficiales', 'digitalisimo-integrations' ) . '</th><td>';
		echo '<p class="description">' . esc_html__( 'Una red por campo. Se publican como sameAs en el schema de la organización; deja vacías las que no uses.', 'digitalisimo-integrations' ) . '</p>';
		echo '<div class="digitalisimo-social" id="digitalisimo-social-group">';
		foreach ( self::networks() as $id => $meta ) {
			$field = 'digitalisimo_social_' . $id;
			echo '<p class="digitalisimo-social__row"><label for="' . esc_attr( $field ) . '">' . esc_html( $meta[0] ) . '</label>';
			echo '<input class="regular-text digitalisimo-inheritable" type="url" id="' . esc_attr( $field ) . '" name="' . esc_attr( $option ) . '[' . esc_attr( self::KEY ) . '][' . esc_attr( $id ) . ']" value="' . esc_attr( $values[ $id ] ) . '" placeholder="' . esc_attr( $meta[1] ) . '"' . $disabled . '></p>';
		}
		echo '</div>';
		if ( is_multisite() ) {
			echo '<p><input type="hidden" name="' . esc_attr( $option ) . '[network_inherit][' . esc_attr( self::KEY ) . ']" value="0"><label><input class="digitalisimo-network-inherit" data-group="#digitalisimo-social-group" type="checkbox" name="' . esc_attr( $option ) . '[network_inherit][' . esc_attr( self::KEY ) . ']" value="1" ' . checked( $inherit, true, false ) . '> ' . esc_html__( 'Heredar de la red', 'digitalisimo-integrations' ) . '</label></p>';
		}
		echo '</td></tr>';
	}

	/** Campos de la configuración de red. */
	public static function network_fields() {
		$all    = (array) get_site_option( Digitalisimo_Integrations_Settings::OPTION, array() );
		$stored = is_array( $all[ self::KEY ] ?? null ) ? $all[ self::KEY ] : array();

		echo '<tr><th scope="row">' . esc_html__( 'Perfiles oficiales', 'digitalisimo-integrations' ) . '</th><td>';
		echo '<p class="description">' . esc_html__( 'Valores de red: los sitios los heredan salvo que definan los suyos.', 'digitalisimo-integrations' ) . '</p>';
		echo '<div class="digitalisimo-social">';
		foreach ( self::networks() as $id => $meta ) {
			$field = 'network_social_' . $id;
			echo '<p class="digitalisimo-social__row"><label for="' . esc_attr( $field ) . '">' . esc_html( $meta[0] ) . '</label>';
			echo '<input class="regular-text" type="url" id="' . esc_attr( $field ) . '" name="digitalisimo_network[' . esc_attr( self::KEY ) . '][' . esc_attr( $id ) . ']" value="' . esc_attr( (string) ( $stored[ $id ] ?? '' ) ) . '" placeholder="' . esc_attr( $meta[1] ) . '"></p>';
		}
		echo '</div></td></tr>';
	}
}
