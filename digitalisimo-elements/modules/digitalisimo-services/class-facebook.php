<?php
namespace Digitalisimo\Elements\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Piezas comunes de los cuatro widgets de Facebook propios (C9 de `docs/PRO_WIDGETS_OBJECTIVE.md`, contrato
 * `docs/pro/c9.md`): Botón, Comentarios, Publicación y Página de Facebook, que sustituyen a los de PRO
 * (`modules/social/widgets/facebook-*.php` y `modules/social/classes/facebook-sdk-manager.php`). Avisos del panel, enlace
 * permanente y App ID derivados de PRO Elements (GPL); la fachada, el marcado, la hoja y el script son propios.
 *
 * **Fachada** (como las de vídeo de la Fase 3, `modules/digitalisimo-front/class-facades.php`): la página no pide nada a
 * Facebook ni carga su SDK hasta que la persona pulsa. Antes se pinta un marcador propio con el nombre del servicio
 * (texto, sin logotipo ni enlace de rastreo) y un `<button>` con nombre accesible en español, del tamaño aproximado del
 * plugin final para no mover la página. El `div` del plugin, con las mismas clases y `data-*` que pintaba PRO, viaja
 * dentro de un `<template>` (inerte: no pide nada). Al pulsar, `assets/js/facebook.js` lo inserta, carga el SDK una sola
 * vez para toda la página (`connect.facebook.net/<idioma>/sdk.js`, `FB.init` con el App ID del sitio, versión `v2.10`,
 * sin `xfbml`), llama a `FB.XFBML.parse()` sobre ese widget y le pasa el foco. Sin JavaScript sólo queda el marcador.
 *
 * El App ID es el de la opción de PRO (`elementor_pro_facebook_app_id`, que PRO ya publica en `<meta property="fb:app_id">`):
 * es público y viaja en `data-digi-fb-app`. El idioma es el del sitio (`get_locale()`).
 */
final class Facebook {
	/** Opción del App ID (la misma de PRO: el ajuste de Integraciones sigue sirviendo). */
	const APP_OPTION = 'elementor_pro_facebook_app_id';

	const URL_TYPE_CURRENT_PAGE = 'current_page';
	const URL_TYPE_CUSTOM       = 'custom';
	const URL_FORMAT_PLAIN      = 'plain';
	const URL_FORMAT_PRETTY     = 'pretty';

	/** Widgets que Meta retiró (como en PRO): un aviso en lugar del del App ID. */
	const DISCONTINUED = array( 'digitalisimo-facebook-button', 'digitalisimo-facebook-comments' );

	public static function app_id(): string {
		return function_exists( 'get_option' ) ? (string) get_option( self::APP_OPTION, '' ) : '';
	}

	public static function lang(): string {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : 'es_ES';
		return preg_match( '/^[a-z]{2,3}_[A-Z]{2}$/', $locale ) ? $locale : 'es_ES';
	}

	/** Enlace a la pestaña Integraciones de los ajustes del constructor (donde vive el App ID), o ''. */
	private static function settings_url(): string {
		if ( class_exists( '\Elementor\Settings' ) && method_exists( '\Elementor\Settings', 'get_settings_tab_url' ) ) {
			return (string) \Elementor\Settings::get_settings_tab_url( 'integrations' );
		}
		return '';
	}

	/**
	 * Avisos del App ID, como `Facebook_SDK_Manager::add_app_id_control()` de PRO: mismos nombres, tipos y condiciones.
	 *
	 * @param \Elementor\Widget_Base $widget Widget en registro de controles.
	 */
	public static function add_app_id_control( $widget ): void {
		if ( in_array( $widget->get_name(), self::DISCONTINUED, true ) ) {
			$widget->add_control(
				'app_deprecated',
				array(
					'type'       => \Elementor\Controls_Manager::ALERT,
					'alert_type' => 'info',
					'content'    => esc_html__( 'Meta retiró este widget de Facebook y ya no muestra contenido. Quítalo de la página para que no quede un hueco.', 'pro-elements' ),
				)
			);
			return;
		}
		$url  = self::settings_url();
		$open = '' !== $url ? '<a href="' . esc_url( $url ) . '" target="_blank">' : '';
		$shut = '' !== $url ? '</a>' : '';
		if ( '' === self::app_id() ) {
			/* translators: 1: apertura del enlace a los ajustes, 2: cierre del enlace. */
			$content = sprintf( esc_html__( 'Configura el App ID de Facebook en los %1$sajustes de Integraciones%2$s.', 'pro-elements' ), $open, $shut );
			$type    = 'warning';
		} else {
			/* translators: 1: App ID, 2: apertura del enlace a los ajustes, 3: cierre del enlace. */
			$content = sprintf( esc_html__( 'Conectado a la aplicación de Facebook %1$s. %2$sCambiar la aplicación%3$s', 'pro-elements' ), esc_html( self::app_id() ), $open, $shut );
			$type    = 'info';
		}
		$widget->add_control(
			'app_id',
			array(
				'type'       => \Elementor\Controls_Manager::ALERT,
				'alert_type' => $type,
				'content'    => $content,
			)
		);
		$widget->add_control(
			'app_eu_message',
			array(
				'type'       => \Elementor\Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content'    => esc_html__( 'Para los visitantes de la UE, los widgets de Facebook sólo funcionan si iniciaron sesión en Facebook y aceptaron sus cookies.', 'pro-elements' ),
			)
		);
	}

	/** Opciones de «URL de destino» y «Formato de la URL» (las de PRO). */
	public static function url_type_options(): array {
		return array(
			self::URL_TYPE_CURRENT_PAGE => esc_html__( 'Página actual', 'pro-elements' ),
			self::URL_TYPE_CUSTOM       => esc_html__( 'Personalizada', 'pro-elements' ),
		);
	}

	public static function url_format_options(): array {
		return array(
			self::URL_FORMAT_PLAIN  => esc_html__( 'Enlace permanente simple', 'pro-elements' ),
			self::URL_FORMAT_PRETTY => esc_html__( 'Enlace permanente legible', 'pro-elements' ),
		);
	}

	/**
	 * Enlace de la entrada en curso, como `Facebook_SDK_Manager::get_permalink()` de PRO: el legible si se pide; si no, el
	 * simple (`?p=ID`), que no cambia al cambiar los enlaces permanentes y así no se pierden los comentarios.
	 */
	public static function permalink( array $settings = array() ): string {
		$post_id = function_exists( 'get_the_ID' ) ? get_the_ID() : 0;
		if ( isset( $settings['url_format'] ) && self::URL_FORMAT_PRETTY === $settings['url_format'] ) {
			return (string) get_permalink( $post_id );
		}
		return (string) add_query_arg( 'p', $post_id, home_url() );
	}

	/** ¿Se edita en el constructor? (los avisos de validación del botón sólo se ven ahí, como en PRO) */
	public static function editing(): bool {
		return class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && is_object( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
	}

	/**
	 * Fachada de un widget de Facebook.
	 *
	 * @param string $id    ID del elemento (para el `id` de la nota).
	 * @param string $kind  `button`, `comments`, `post`, `video`, `comment` o `page` (modificador de clase).
	 * @param array  $attrs Atributos del `div` del plugin (clase del servicio y `data-*`, los mismos que en PRO).
	 * @param string $label Texto visible y nombre accesible del botón.
	 * @param string $style Estilo en línea de la fachada (`--digi-fb-height` en la Página), ya saneado.
	 */
	public static function facade( string $id, string $kind, array $attrs, string $label, string $style = '' ): string {
		$note   = 'digi-fb-note-' . preg_replace( '/[^a-z0-9_-]/i', '', $id );
		$plugin = '<div';
		foreach ( $attrs as $name => $value ) {
			$plugin .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}
		$plugin .= '></div>';
		$out  = '<div class="digi-fb digi-fb--' . esc_attr( $kind ) . '" data-digi-fb data-digi-fb-lang="' . esc_attr( self::lang() ) . '" data-digi-fb-app="' . esc_attr( self::app_id() ) . '"' . ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>';
		$out .= '<button type="button" class="digi-fb__load" aria-describedby="' . esc_attr( $note ) . '"><span class="digi-fb__service" aria-hidden="true">Facebook</span><span class="digi-fb__label">' . esc_html( $label ) . '</span></button>';
		$out .= '<span class="digi-fb__note" id="' . esc_attr( $note ) . '">' . esc_html__( 'Al pulsar se carga contenido de Facebook.', 'pro-elements' ) . '</span>';
		$out .= '<template class="digi-fb__embed">' . $plugin . '</template>';
		return $out . '</div>';
	}
}
