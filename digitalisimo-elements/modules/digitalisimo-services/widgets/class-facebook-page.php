<?php
namespace Digitalisimo\Elements\Services\Widgets;

use Digitalisimo\Elements\Services\Facebook;
use Digitalisimo\Elements\Services\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Página de Facebook (`digitalisimo-facebook-page`): sustituye al «Facebook Page» de PRO
 * (`modules/social/widgets/facebook-page.php`; mapa `maps/facebook-page.php`). Mismos controles derivados de PRO
 * Elements (GPL); el plugin `fb-page` con los mismos `data-*` que PRO, detrás de la fachada de `Services\Facebook`, que
 * reserva el alto elegido (`height`) y hasta 500 px de ancho, como el plugin.
 */
class Facebook_Page extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-facebook-page';
	}

	public function get_title() {
		return esc_html__( 'Página de Facebook', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-fb-feed';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'facebook', 'social', 'insertar', 'embed', 'página', 'page' );
	}

	public function get_style_depends() {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends() {
		return Module::script_depends( $this->get_name() );
	}

	protected function is_dynamic_content(): bool {
		return false;
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Página', 'pro-elements' ),
			)
		);

		Facebook::add_app_id_control( $this );

		$this->add_control(
			'widget_exclusively_web',
			array(
				'type'       => \Elementor\Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content'    => esc_html__( 'Facebook sólo permite insertar páginas en la web: en los móviles no se muestran.', 'pro-elements' ),
			)
		);

		$this->add_control(
			'url',
			array(
				'label'       => esc_html__( 'Enlace', 'pro-elements' ),
				'placeholder' => 'https://www.facebook.com/your-page/',
				'default'     => '',
				'label_block' => true,
				'description' => esc_html__( 'Pega la URL de la página de Facebook.', 'pro-elements' ),
			)
		);

		$this->add_control(
			'tabs',
			array(
				'label'       => esc_html__( 'Diseño', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::SELECT2,
				'multiple'    => true,
				'label_block' => true,
				'default'     => array(
					'timeline',
				),
				'options'     => array(
					'timeline' => esc_html__( 'Biografía', 'pro-elements' ),
					'events'   => esc_html__( 'Eventos', 'pro-elements' ),
					'messages' => esc_html__( 'Mensajes', 'pro-elements' ),
				),
			)
		);

		$this->add_control(
			'small_header',
			array(
				'label'   => esc_html__( 'Cabecera pequeña', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'show_cover',
			array(
				'label'   => esc_html__( 'Foto de portada', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_facepile',
			array(
				'label'   => esc_html__( 'Fotos de perfil', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'show_cta',
			array(
				'label'   => esc_html__( 'Botón de llamada a la acción', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			)
		);

		$this->add_control(
			'height',
			array(
				'label'      => esc_html__( 'Alto', 'pro-elements' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem', 'custom' ),
				'default'    => array(
					'size' => 500,
				),
				'range'      => array(
					'px'  => array(
						'min' => 50,
						'max' => 1000,
					),
					'em'  => array(
						'min' => 5,
						'max' => 100,
					),
					'rem' => array(
						'min' => 5,
						'max' => 100,
					),
				),
			)
		);

		$this->end_controls_section();
	}

	/** Alto del plugin (`500px`), saneado: un número y una unidad conocida, o una medida CSS simple en «custom». */
	private static function height( $value ): string {
		$size = is_array( $value ) ? ( $value['size'] ?? 500 ) : 500;
		$unit = is_array( $value ) && ! empty( $value['unit'] ) ? (string) $value['unit'] : 'px';
		if ( 'custom' === $unit ) {
			$size = trim( (string) $size );
			return preg_match( '/^[0-9.]+(px|em|rem|vh|%)?$/', $size ) ? $size : '500px';
		}
		$unit = in_array( $unit, array( 'px', 'em', 'rem' ), true ) ? $unit : 'px';
		return ( is_numeric( $size ) ? (float) $size : 500 ) . $unit;
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( empty( $settings['url'] ) ) {
			echo esc_html( $this->get_title() . ': ' . __( 'introduce una URL válida.', 'pro-elements' ) );
			return;
		}
		$height = self::height( $settings['height'] ?? null );
		$attrs  = array(
			'class'              => 'digi-fb__plugin fb-page',
			'data-href'          => esc_url( (string) $settings['url'] ),
			'data-tabs'          => implode( ',', array_map( 'strval', (array) ( $settings['tabs'] ?? array() ) ) ),
			'data-height'        => $height,
			'data-width'         => '500px',
			'data-small-header'  => ! empty( $settings['small_header'] ) ? 'true' : 'false',
			'data-hide-cover'    => ! empty( $settings['show_cover'] ) ? 'false' : 'true',
			'data-show-facepile' => ! empty( $settings['show_facepile'] ) ? 'true' : 'false',
			'data-hide-cta'      => ! empty( $settings['show_cta'] ) ? 'false' : 'true',
			'style'              => 'min-height: 1px;height:' . $height,
		);
		echo Facebook::facade( (string) $this->get_id(), 'page', $attrs, __( 'Ver la página de Facebook', 'pro-elements' ), '--digi-fb-height:' . $height ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en facade().
	}

	public function render_plain_content() {}
}
