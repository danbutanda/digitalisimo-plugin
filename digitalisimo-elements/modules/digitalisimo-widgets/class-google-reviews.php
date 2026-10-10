<?php
namespace Digitalisimo\Elements;

defined( 'ABSPATH' ) || exit;

/** Reseñas reales de Places, consultadas después de que el widget entra en pantalla. */
class Google_Reviews_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'digitalisimo-google-reviews'; }
	public function get_title() { return 'Reseñas de Google'; }
	public function get_icon() { return 'eicon-review'; }
	public function get_categories() { return array( 'digitalisimo' ); }
	public function get_keywords() { return array( 'google', 'maps', 'reseñas', 'opiniones' ); }
	public function get_style_depends() { return array( 'digitalisimo-google-reviews' ); }
	public function get_script_depends() { return array( 'digitalisimo-google-reviews' ); }

	protected function register_controls() {
		$c = '\\Elementor\\Controls_Manager';
		$this->start_controls_section( 'section_reviews', array( 'label' => 'Reseñas de Google Maps' ) );
		$this->add_control( 'google_place_id', array( 'label' => 'Place ID', 'type' => $c::TEXT, 'label_block' => true, 'description' => 'Usa el ID del lugar de Google Maps. La clave de Places API (New) se configura en Ajustes → Google Reviews · DIGITALÍSIMO.' ) );
		$this->add_control( 'max_reviews', array( 'label' => 'Reseñas visibles', 'type' => $c::NUMBER, 'default' => 5, 'min' => 1, 'max' => 5 ) );
		$this->add_control( 'heading', array( 'label' => 'Encabezado', 'type' => $c::TEXT, 'default' => 'Reseñas de Google', 'dynamic' => array( 'active' => true ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'section_reviews_style', array( 'label' => 'Estilo', 'tab' => $c::TAB_STYLE ) );
		$this->add_responsive_control( 'columns', array( 'label' => 'Columnas', 'type' => $c::SELECT, 'default' => '2', 'tablet_default' => '2', 'mobile_default' => '1', 'options' => array( '1' => '1', '2' => '2', '3' => '3' ), 'selectors' => array( '{{WRAPPER}} .digi-google-reviews__list' => 'grid-template-columns:repeat({{VALUE}},minmax(0,1fr));' ) ) );
		$this->add_control( 'card_background', array( 'label' => 'Fondo de tarjeta', 'type' => $c::COLOR, 'selectors' => array( '{{WRAPPER}} .digi-google-reviews__item' => 'background-color:{{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$place_id = trim( (string) ( $s['google_place_id'] ?? '' ) );
		if ( ! Google_Reviews_Service::valid_place_id( $place_id ) ) {
			if ( current_user_can( 'manage_options' ) ) { echo '<p>Indica un Place ID válido para mostrar las reseñas.</p>'; }
			return;
		}
		$heading = trim( wp_strip_all_tags( (string) ( $s['heading'] ?? '' ) ) );
		$limit = max( 1, min( 5, absint( $s['max_reviews'] ?? 5 ) ) );
		$url = Google_Reviews_Service::maps_url( $place_id );
		echo '<section class="digi-google-reviews" aria-label="Reseñas de Google Maps"';
		if ( Google_Reviews_Service::api_key() ) {
			echo ' data-digi-google-reviews data-endpoint="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-place-id="' . esc_attr( $place_id ) . '" data-signature="' . esc_attr( Google_Reviews_Service::signature( $place_id ) ) . '" data-limit="' . (int) $limit . '"';
		}
		echo '>';
		if ( $heading ) { echo '<h3 class="digi-google-reviews__title">' . esc_html( $heading ) . '</h3>'; }
		echo '<div class="digi-google-reviews__results" aria-live="polite"></div><p class="digi-google-reviews__fallback"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">Ver reseñas en Google Maps</a></p>';
		if ( ! Google_Reviews_Service::api_key() && current_user_can( 'manage_options' ) ) { echo '<p>Configura la clave de Places API (New) en Ajustes → Google Reviews · DIGITALÍSIMO para mostrar las tarjetas.</p>'; }
		echo '</section>';
	}

	protected function content_template() {
		?>
		<# if ( settings.google_place_id ) { #><section class="digi-google-reviews" aria-label="Reseñas de Google Maps"><# if ( settings.heading ) { #><h3 class="digi-google-reviews__title">{{ settings.heading }}</h3><# } #><p>Las reseñas se cargan en la vista pública cuando este bloque aparece en pantalla.</p><p class="digi-google-reviews__fallback"><a href="https://www.google.com/maps/search/?api=1&query=Google&query_place_id={{ settings.google_place_id }}" target="_blank" rel="noopener noreferrer">Ver reseñas en Google Maps</a></p></section><# } #>
		<?php
	}
}
