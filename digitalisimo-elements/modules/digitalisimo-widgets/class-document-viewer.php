<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Visor de documentos independiente; referencia funcional: Element Pack Pro 9.9.1 (GPLv3). */
class Document_Viewer_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-document-viewer'; }
	public function get_title() { return 'Visor de documentos'; }
	public function get_icon() { return 'eicon-document-file'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'documento', 'pdf', 'visor', 'archivo' ); }
	public function get_style_depends() { return array( 'digitalisimo-document-viewer' ); }
	public function get_script_depends() { return array(); }

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
		echo '<div class="digi-document-viewer">';
		echo '<iframe class="digi-document-viewer__frame" src="' . esc_url( $frame_url ) . '" title="' . esc_attr( $title ) . '" loading="lazy"></iframe>';
		echo '<a class="digi-document-viewer__link" href="' . esc_url( $source ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( 'Abrir documento' ) . '</a>';
		echo '</div>';
	}
}
