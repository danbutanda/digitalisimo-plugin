<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/class-image-markup.php';

/**
 * Visor de documentos independiente: incrusta un PDF u otro archivo público en la página. Con las fachadas de
 * «Rendimiento» (`Front\Facades`) el documento no se carga hasta pulsar «Ver el documento aquí» (con la vista previa
 * de la primera página si es un PDF de la biblioteca que la tiene); «Abrir documento» sigue debajo.
 */
class Document_Viewer_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-document-viewer'; }
	public function get_title() { return 'Visor de documentos'; }
	public function get_icon() { return 'eicon-document-file'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'documento', 'pdf', 'visor', 'archivo' ); }
	public function get_style_depends() { return array_merge( array( 'digitalisimo-document-viewer' ), $this->facade_depends() ); }
	public function get_script_depends() { return $this->facade_depends(); }

	/** ¿Están aplicadas las fachadas en esta petición? */
	private static function facades() {
		return class_exists( '\\Digitalisimo\\Elements\\Front\\Facades' ) && \Digitalisimo\Elements\Front\Facades::active();
	}

	/** Hoja o script de la fachada si hay un documento válido. */
	private function facade_depends() {
		if ( ! method_exists( $this, 'is_type_instance' ) || $this->is_type_instance() || ! self::facades() ) { return array(); }
		$s    = (array) $this->get_settings_for_display();
		$file = isset( $s['file_source'] ) && is_array( $s['file_source'] ) ? $s['file_source'] : array();
		return \Digitalisimo\Elements\Front\Facades::depends( '' !== self::safe_source( $file['url'] ?? '' ) );
	}

	/** Vista previa de la primera página de un PDF de la biblioteca de este sitio, o ''. Sólo consulta si está en uploads. */
	private static function preview( $source ) {
		$uploads = function_exists( 'wp_get_upload_dir' ) ? wp_get_upload_dir() : array();
		$base    = set_url_scheme( (string) ( $uploads['baseurl'] ?? '' ), 'https' );
		if ( '' === $base || 0 !== strpos( set_url_scheme( $source, 'https' ), $base . '/' ) ) { return ''; }
		$id = attachment_url_to_postid( $source );
		return $id && 'application/pdf' === get_post_mime_type( $id ) ? (string) wp_get_attachment_image_url( $id, 'large' ) : '';
	}

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_content_layout', array( 'label' => 'Documento' ) );
		$this->add_control( 'file_source', array( 'label' => 'URL del documento', 'type' => $c::URL, 'dynamic' => array( 'active' => true ), 'label_block' => true, 'show_external' => false, 'placeholder' => 'https://ejemplo.com/archivo.pdf' ) );
		$this->add_control( 'viewer_type', array( 'label' => 'Visor', 'type' => $c::SELECT, 'default' => 'browser', 'options' => array( 'browser' => 'Navegador', 'google_docs' => 'Google Docs (archivo público)' ) ) );
		$this->add_control( 'frame_title', array( 'label' => 'Nombre accesible', 'type' => $c::TEXT, 'default' => 'Visor de documento', 'description' => 'Describe el documento para lectores de pantalla.' ) );
		$this->add_responsive_control( 'document_height', array( 'label' => 'Altura', 'type' => $c::SLIDER, 'size_units' => array( 'px' ), 'default' => array( 'size' => 800, 'unit' => 'px' ), 'range' => array( 'px' => array( 'min' => 200, 'max' => 1500 ) ), 'selectors' => array( '{{WRAPPER}} .digi-document-viewer__frame' => 'height: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	private static function safe_source( $value ) {
		$url = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		return $url;
	}

	private static function is_private_host( $host ) {
		$host = strtolower( trim( $host, '[]' ) );
		if ( 'localhost' === $host || preg_match( '/\.(?:localhost|local|test|internal)$/', $host ) ) { return true; }
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		return false;
	}

	private static function google_viewer_url( $source ) {
		$parts = wp_parse_url( $source );
		$host = strtolower( $parts['host'] ?? '' );
		if ( self::is_private_host( $host ) ) { return ''; }
		$path = $parts['path'] ?? '';
		if ( 'docs.google.com' === $host && preg_match( '#^/(document|presentation)/d/([a-zA-Z0-9_-]+)(?:/|$)#', $path, $matches ) ) {
			return 'https://docs.google.com/' . $matches[1] . '/d/' . $matches[2] . '/preview';
		}
		if ( 'docs.google.com' === $host && preg_match( '#^/spreadsheets/d/[a-zA-Z0-9_-]+(?:/|$)#', $path ) ) {
			return add_query_arg( array( 'widget' => 'true', 'headers' => 'false' ), $source );
		}
		return 'https://docs.google.com/gview?embedded=1&url=' . rawurlencode( $source );
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$file = isset( $settings['file_source'] ) && is_array( $settings['file_source'] ) ? $settings['file_source'] : array();
		$source = self::safe_source( $file['url'] ?? '' );
		if ( '' === $source ) { return; }
		$google = 'google_docs' === ( $settings['viewer_type'] ?? 'browser' );
		$frame_url = $google ? self::google_viewer_url( $source ) : $source;
		if ( '' === $frame_url ) { $frame_url = $source; }
		$title = trim( (string) ( $settings['frame_title'] ?? '' ) );
		if ( '' === $title ) { $title = 'Visor de documento'; }
		$facade = self::facades() ? \Digitalisimo\Elements\Front\Facades::markup(
			'document',
			array( 'src' => $frame_url, 'title' => $title, 'class' => 'digi-document-viewer__frame' ),
			array( 'name' => 'Ver el documento aquí: ' . $title, 'label' => 'Ver el documento aquí', 'image' => self::preview( $source ) )
		) : '';
		echo '<div class="digi-document-viewer">';
		echo '' !== $facade ? $facade : Image_Markup::preconnect( $frame_url ) . '<iframe class="digi-document-viewer__frame" src="' . esc_url( $frame_url ) . '" title="' . esc_attr( $title ) . '" loading="lazy"></iframe>';
		echo '<a class="digi-document-viewer__link" href="' . esc_url( $source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( 'Abrir documento' ) . '</a>';
		echo '</div>';
	}
}
