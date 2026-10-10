<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Código QR local con contenido visible y sin API externa. */
final class QR_Code_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-qr-code'; }
	public function get_title() { return 'Código QR'; }
	public function get_icon() { return 'eicon-barcode'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'qr', 'código', 'enlace' ); }
	public function get_style_depends() { return array( 'digitalisimo-qr-code' ); }
	public function get_script_depends() { return array( 'digitalisimo-qr-code' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_qr', array( 'label' => 'Código QR' ) );
		$this->add_control( 'site_link', array( 'label' => 'Usar URL de esta página', 'type' => $c::SWITCHER, 'default' => 'yes', 'return_value' => 'yes' ) );
		$this->add_control( 'text', array( 'label' => 'Contenido', 'type' => $c::TEXTAREA, 'dynamic' => array( 'active' => true ), 'condition' => array( 'site_link!' => 'yes' ) ) );
		$this->add_control( 'label', array( 'label' => 'Descripción accesible', 'type' => $c::TEXT, 'default' => 'Escanear código QR' ) );
		$this->add_control( 'size', array( 'label' => 'Tamaño (px)', 'type' => $c::NUMBER, 'default' => 200, 'min' => 64, 'max' => 512 ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_control( 'foreground', array( 'label' => 'Color del código', 'type' => $c::COLOR, 'default' => '#111111' ) );
		$this->add_control( 'background', array( 'label' => 'Color de fondo', 'type' => $c::COLOR, 'default' => '#ffffff' ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		if ( 'yes' === ( $s['site_link'] ?? '' ) ) {
			$queried_id = is_singular() ? get_queried_object_id() : 0;
			$content = $queried_id ? get_permalink( $queried_id ) : get_pagenum_link( max( 1, absint( get_query_var( 'paged' ) ) ) );
		} else {
			$content = trim( (string) ( $s['text'] ?? '' ) );
		}
		if ( ! is_string( $content ) || '' === $content ) { return; }
		$content = substr( $content, 0, 1000 );
		$label = trim( wp_strip_all_tags( (string) ( $s['label'] ?? '' ) ) );
		if ( '' === $label ) { $label = 'Escanear código QR'; }
		$size = max( 64, min( 512, absint( $s['size'] ?? 200 ) ) );
		$foreground = sanitize_hex_color( (string) ( $s['foreground'] ?? '#111111' ) ) ?: '#111111';
		$background = sanitize_hex_color( (string) ( $s['background'] ?? '#ffffff' ) ) ?: '#ffffff';
		$url = filter_var( $content, FILTER_VALIDATE_URL ) && in_array( strtolower( (string) wp_parse_url( $content, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true ) ? $content : '';
		echo '<figure class="digi-qr-code" data-digi-qr data-text="' . esc_attr( $content ) . '" data-size="' . (int) $size . '" data-fill="' . esc_attr( $foreground ) . '" data-background="' . esc_attr( $background ) . '">';
		echo '<span class="digi-qr-code__canvas" aria-hidden="true" style="width:' . (int) $size . 'px;height:' . (int) $size . 'px"></span>';
		echo '<figcaption class="digi-qr-code__caption">' . esc_html( $label ) . '</figcaption>';
		if ( $url ) { echo '<a class="digi-qr-code__fallback" href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>'; }
		else { echo '<span class="digi-qr-code__fallback">' . esc_html( $content ) . '</span>'; }
		echo '</figure>';
	}

	protected function content_template() {
		?>
		<# var content = settings.site_link === 'yes' ? '' : settings.text; var size = Math.max(64, Math.min(512, parseInt(settings.size, 10) || 200)); #>
		<# if (settings.site_link === 'yes') { #><p>El QR con la URL pública de esta página se genera en el sitio.</p><# } #>
		<# if (content) { #><figure class="digi-qr-code" data-digi-qr data-text="{{ content }}" data-size="{{ size }}" data-fill="{{ settings.foreground || '#111111' }}" data-background="{{ settings.background || '#ffffff' }}"><span class="digi-qr-code__canvas" aria-hidden="true" style="width:{{ size }}px;height:{{ size }}px"></span><figcaption class="digi-qr-code__caption">{{ settings.label || 'Escanear código QR' }}</figcaption><span class="digi-qr-code__fallback">{{ content }}</span></figure><# } #>
		<?php
	}
}
