<?php
namespace Digitalisimo\Elements\Services\Widgets;

use Digitalisimo\Elements\Services\Facebook;
use Digitalisimo\Elements\Services\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Botón de Facebook (`digitalisimo-facebook-button`): sustituye al «Facebook Button» de PRO
 * (`modules/social/widgets/facebook-button.php`; mapa `modules/digitalisimo-convert/maps/facebook-button.php`). Mismos
 * controles (nombre, orden, tipo, valor por defecto, opciones y condiciones) derivados de PRO Elements (GPL); el plugin
 * `fb-like` y sus `data-*` son los de PRO. Meta retiró este plugin para sitios de terceros: como en PRO, el panel lo avisa
 * y la página sólo lleva el `div` del plugin, vacío, sin fachada ni SDK (ninguna petición a Facebook).
 */
class Facebook_Button extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-facebook-button';
	}

	public function get_title() {
		return esc_html__( 'Botón de Facebook', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-facebook-like-box';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'facebook', 'social', 'me gusta', 'like', 'recomendar', 'botón', 'compartir' );
	}

	public function get_style_depends() {
		return Module::style_depends( $this->get_name() );
	}

	public function get_script_depends() {
		return Module::script_depends( $this->get_name() );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => esc_html__( 'Botón', 'pro-elements' ),
			)
		);

		Facebook::add_app_id_control( $this );

		$this->add_control(
			'type',
			array(
				'label'   => esc_html__( 'Tipo', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'like',
				'options' => array(
					'like'      => esc_html__( 'Me gusta', 'pro-elements' ),
					'recommend' => esc_html__( 'Recomendar', 'pro-elements' ),
				),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => esc_html__( 'Diseño', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'standard',
				'options' => array(
					'standard'     => esc_html__( 'Estándar', 'pro-elements' ),
					'button'       => esc_html__( 'Botón', 'pro-elements' ),
					'button_count' => esc_html__( 'Botón con contador', 'pro-elements' ),
					'box_count'    => esc_html__( 'Caja con contador', 'pro-elements' ),
				),
			)
		);

		$this->add_control(
			'size',
			array(
				'label'   => esc_html__( 'Tamaño', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'small',
				'options' => array(
					'small' => esc_html__( 'Pequeño', 'pro-elements' ),
					'large' => esc_html__( 'Grande', 'pro-elements' ),
				),
			)
		);

		$this->add_control(
			'color_scheme',
			array(
				'label'   => esc_html__( 'Esquema de color', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'light',
				'options' => array(
					'light' => esc_html__( 'Claro', 'pro-elements' ),
					'dark'  => esc_html__( 'Oscuro', 'pro-elements' ),
				),
			)
		);

		$this->add_control(
			'show_share',
			array(
				'label'     => esc_html__( 'Botón Compartir', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'default'   => '',
				'condition' => array(
					'type!' => 'follow',
				),
			)
		);

		$this->add_control(
			'show_faces',
			array(
				'label'   => esc_html__( 'Caras', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SWITCHER,
				'default' => '',
			)
		);

		$this->add_control(
			'url_type',
			array(
				'label'     => esc_html__( 'URL de destino', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => Facebook::url_type_options(),
				'default'   => Facebook::URL_TYPE_CURRENT_PAGE,
				'separator' => 'before',
				'condition' => array(
					'type' => array( 'like', 'recommend' ),
				),
			)
		);

		$this->add_control(
			'url_format',
			array(
				'label'     => esc_html__( 'Formato de la URL', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'options'   => Facebook::url_format_options(),
				'default'   => Facebook::URL_FORMAT_PLAIN,
				'condition' => array(
					'url_type' => Facebook::URL_TYPE_CURRENT_PAGE,
				),
			)
		);

		$this->add_control(
			'url',
			array(
				'label'       => esc_html__( 'Enlace', 'pro-elements' ),
				'placeholder' => esc_html__( 'https://tu-enlace.com', 'pro-elements' ),
				'label_block' => true,
				'condition'   => array(
					'type'     => array( 'like', 'recommend' ),
					'url_type' => Facebook::URL_TYPE_CUSTOM,
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings();
		$type     = (string) ( $settings['type'] ?? 'like' );
		$custom   = Facebook::URL_TYPE_CUSTOM === ( $settings['url_type'] ?? '' );
		if ( in_array( $type, array( 'like', 'recommend' ), true ) && $custom && ! filter_var( (string) ( $settings['url'] ?? '' ), FILTER_VALIDATE_URL ) ) {
			// Como PRO: el aviso sólo se ve en el editor; en la página no se pinta nada.
			if ( Facebook::editing() ) {
				echo esc_html( $this->get_title() . ': ' . __( 'introduce una URL válida.', 'pro-elements' ) );
			}
			return;
		}
		$attrs = array(
			'class'            => 'digi-fb__plugin fb-like',
			'data-layout'      => (string) ( $settings['layout'] ?? 'standard' ),
			'data-colorscheme' => (string) ( $settings['color_scheme'] ?? 'light' ),
			'data-size'        => (string) ( $settings['size'] ?? 'small' ),
			'data-show-faces'  => ! empty( $settings['show_faces'] ) ? 'true' : 'false',
			'data-href'        => $custom ? esc_url( (string) $settings['url'] ) : Facebook::permalink( $settings ),
			'data-share'       => ! empty( $settings['show_share'] ) ? 'true' : 'false',
			'data-action'      => $type,
		);
		// Meta retiró el plugin (`Facebook::DISCONTINUED`): como PRO, sólo su `div`, que queda vacío (1 px); sin fachada ni SDK.
		echo Facebook::plugin( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en plugin().
	}

	public function render_plain_content() {}
}
