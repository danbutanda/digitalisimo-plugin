<?php
defined( 'ABSPATH' ) || exit;

/**
 * Texto antes y después del contenido en el RSS (RM16) y alt de los avatares (RM11) de
 * docs/SEO_RANKMATH_OBJECTIVE.md.
 *
 * El texto del feed es del administrador (HTML permitido en entradas) y admite las variables de
 * los títulos más cuatro enlaces propios del feed. El alt del avatar sólo se pone donde falta y
 * sólo con el nombre público: nunca el correo.
 */
class Digitalisimo_Integrations_SEO_Feed {
	/** Variables que sólo existen en el feed: se sustituyen por un enlace. */
	const LINKS = array( 'post_link', 'site_link', 'sitedesc_link', 'author_link' );

	public static function init() {
		add_filter( 'the_content_feed', array( __CLASS__, 'content' ), 20 );
		add_filter( 'the_excerpt_rss', array( __CLASS__, 'content' ), 20 );
		add_filter( 'get_avatar', array( __CLASS__, 'avatar' ), 10, 6 );
	}

	private static function o( $key, $default = '' ) { return Digitalisimo_Integrations_SEO_Resolver::option( $key, $default ); }

	public static function content( $content ) {
		$post = get_post();
		if ( ! $post ) return $content;
		$before = self::render( (string) self::o( 'seo_rss_before', '' ), $post );
		$after  = self::render( (string) self::o( 'seo_rss_after', '' ), $post );
		return ( '' !== $before ? '<p>' . $before . '</p>' : '' ) . $content . ( '' !== $after ? '<p>' . $after . '</p>' : '' );
	}

	/** Plantilla del administrador ya sanitizada → HTML del feed para un contenido. */
	public static function render( $template, $post ) {
		$template = trim( (string) $template );
		if ( '' === $template ) return '';
		$links = array();
		$text  = preg_replace_callback( '/%(' . implode( '|', self::LINKS ) . ')%/i', function( $m ) use ( $post, &$links ) {
			$links[] = self::link( strtolower( $m[1] ), $post );
			return "\x01" . ( count( $links ) - 1 ) . "\x01";
		}, $template );
		$text = Digitalisimo_Integrations_SEO_Variables::replace( $text, array( 'id' => $post->ID, 'title' => get_the_title( $post ) ) );
		return preg_replace_callback( "/\x01(\d+)\x01/", function( $m ) use ( $links ) { return $links[ (int) $m[1] ] ?? ''; }, $text );
	}

	private static function link( $name, $post ) {
		$site = (string) self::o( 'seo_site_name', '' ) ?: get_bloginfo( 'name' );
		switch ( $name ) {
			case 'post_link':
				return '<a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( wp_strip_all_tags( get_the_title( $post ) ) ) . '</a>';
			case 'site_link':
				return '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $site ) . '</a>';
			case 'sitedesc_link':
				$desc = get_bloginfo( 'description' );
				return '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $site . ( '' !== $desc ? ' - ' . $desc : '' ) ) . '</a>';
			case 'author_link':
				$author = get_userdata( (int) $post->post_author );
				return $author ? '<a href="' . esc_url( get_author_posts_url( $author->ID ) ) . '">' . esc_html( wp_strip_all_tags( $author->display_name ) ) . '</a>' : '';
		}
		return '';
	}

	/** Texto del feed: HTML de entradas, como mucho 2000 caracteres. */
	public static function sanitize( $value ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		return trim( wp_kses_post( function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 2000 ) : substr( $value, 0, 2000 ) ) );
	}

	public static function avatar( $html, $id_or_email = null, $size = 0, $default = '', $alt = '', $args = array() ) {
		if ( '' !== (string) $alt || ! self::o( 'seo_avatar_alt', 0 ) || ! is_string( $html ) || '' === $html ) return $html;
		$name = self::avatar_name( $id_or_email );
		return '' === $name ? $html : self::with_alt( $html, $name );
	}

	/** Pone alt en el primer <img> si no lo tiene o está vacío. */
	public static function with_alt( $html, $name ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) return $html;
		$tags = new WP_HTML_Tag_Processor( $html );
		if ( ! $tags->next_tag( 'img' ) ) return $html;
		if ( '' !== trim( (string) $tags->get_attribute( 'alt' ) ) ) return $html;
		$tags->set_attribute( 'alt', $name );
		return $tags->get_updated_html();
	}

	/** Nombre público de quien tiene el avatar; nunca el correo. */
	public static function avatar_name( $who ) {
		$user = null;
		if ( $who instanceof WP_User ) $user = $who;
		elseif ( is_numeric( $who ) ) $user = get_userdata( (int) $who );
		elseif ( $who instanceof WP_Post ) $user = get_userdata( (int) $who->post_author );
		elseif ( $who instanceof WP_Comment ) {
			if ( (int) $who->user_id ) $user = get_userdata( (int) $who->user_id );
			if ( ! $user ) return trim( wp_strip_all_tags( (string) $who->comment_author ) );
		} elseif ( is_string( $who ) && is_email( $who ) ) $user = get_user_by( 'email', $who );
		return $user ? trim( wp_strip_all_tags( (string) $user->display_name ) ) : '';
	}
}
