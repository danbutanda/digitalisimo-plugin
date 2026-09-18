<?php
defined( 'ABSPATH' ) || exit;

/**
 * Campo de archivo compartido por los módulos Digitalisimo.
 *
 * Sustituye a los inputs de URL escritos a mano: permite elegir de la
 * biblioteca de medios, arrastrar un archivo sobre el campo o pegar una URL
 * externa. El valor sigue guardándose como una URL en la misma clave, de modo
 * que ni la sanitización ni el consumo en el frontend cambian.
 *
 * Se declara una sola vez aunque haya varios módulos activos.
 */
if ( ! class_exists( 'Digitalisimo_Media_Field' ) ) {
	final class Digitalisimo_Media_Field {
		const HANDLE = 'digitalisimo-media-field';

		private static $base    = '';
		private static $version = '';

		/**
		 * Registra los assets. Lo llaman todos los módulos, pero sólo el primero
		 * queda registrado: el script es idéntico en los cinco.
		 *
		 * @param string $url     URL base del módulo que aporta los assets.
		 * @param string $version Versión del módulo, para cachear el script.
		 */
		public static function boot( $url, $version ) {
			if ( self::$base ) return;
			self::$base    = $url;
			self::$version = $version;
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
			add_action( 'network_admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		}

		/**
		 * Sólo donde hay campos de archivo: wp_enqueue_media() es pesado y no debe
		 * cargarse en todo el escritorio.
		 */
		public static function assets( $hook = '' ) {
			$page = sanitize_key( $_GET['page'] ?? '' );
			$editing = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
			if ( 0 !== strpos( $page, 'digitalisimo' ) && ! $editing ) return;
			$can_upload = current_user_can( 'upload_files' );
			if ( $can_upload ) wp_enqueue_media();
			wp_enqueue_style( self::HANDLE, self::$base . 'assets/media-field.css', array(), self::$version );
			wp_enqueue_script( self::HANDLE, self::$base . 'assets/media-field.js', array(), self::$version, true );
			wp_localize_script(
				self::HANDLE,
				'digitalisimoMediaField',
				array(
					'uploadUrl'   => admin_url( 'admin-ajax.php' ),
					// Sin permiso de subida no se entrega nonce: el arrastre queda inerte.
					'uploadNonce' => $can_upload ? wp_create_nonce( 'media-form' ) : '',
					'i18n'        => array(
						'choose'    => __( 'Elegir archivo', 'digitalisimo' ),
						'use'       => __( 'Usar este archivo', 'digitalisimo' ),
						'uploading' => __( 'Subiendo…', 'digitalisimo' ),
						'failed'    => __( 'No se pudo subir el archivo.', 'digitalisimo' ),
						'noUpload'  => __( 'Tu cuenta no tiene permiso para subir archivos.', 'digitalisimo' ),
					),
				)
			);
		}

		/**
		 * Marca del campo.
		 *
		 * @param string $id       Identificador del input.
		 * @param string $name     Atributo name del input.
		 * @param string $value    URL almacenada.
		 * @param bool   $disabled Campo heredado de la red.
		 * @param string $classes  Clases extra del input (las usa el formulario por sitio).
		 */
		public static function render( $id, $name, $value, $disabled = false, $classes = '' ) {
			$value      = (string) $value;
			$can_upload = current_user_can( 'upload_files' );
			$hint       = $can_upload
				? __( 'Arrastra un archivo aquí, elígelo de la biblioteca o pega una URL.', 'digitalisimo' )
				: __( 'Elige un archivo ya subido o pega una URL.', 'digitalisimo' );

			echo '<div class="digitalisimo-media' . ( $disabled ? ' is-locked' : '' ) . '">';
			echo '<div class="digitalisimo-media__drop">';
			echo '<img class="digitalisimo-media__preview" alt="" hidden>';
			echo '<div class="digitalisimo-media__body">';
			echo '<p class="digitalisimo-media__hint">' . esc_html( $hint ) . '</p>';
			echo '<button type="button" class="button digitalisimo-media__pick"' . disabled( $disabled, true, false ) . '>' . esc_html__( 'Elegir de la biblioteca', 'digitalisimo' ) . '</button> ';
			echo '<button type="button" class="button-link digitalisimo-media__clear" hidden>' . esc_html__( 'Quitar', 'digitalisimo' ) . '</button>';
			echo '<span class="digitalisimo-media__status"></span>';
			echo '</div></div>';
			echo '<input class="regular-text digitalisimo-media__input ' . esc_attr( $classes ) . '" type="url" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="https://"' . disabled( $disabled, true, false ) . '>';
			echo '</div>';
		}
	}
}
