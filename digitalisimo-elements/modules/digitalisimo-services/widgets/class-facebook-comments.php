<?php
namespace Digitalisimo\Elements\Services\Widgets;

use Digitalisimo\Elements\Services\Facebook;
use Digitalisimo\Elements\Services\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Comentarios de Facebook (`digitalisimo-facebook-comments`): sustituye al «Facebook Comments» de PRO
 * (`modules/social/widgets/facebook-comments.php`; mapa `maps/facebook-comments.php`). Mismos controles derivados de PRO
 * Elements (GPL); el plugin `fb-comments` y sus `data-*` son los de PRO. Meta retiró este plugin para sitios de
 * terceros: como en PRO, el panel lo avisa y la página sólo lleva el `div` del plugin, vacío, sin fachada ni SDK.
 */
class Facebook_Comments extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-facebook-comments';
	}

	public function get_title() {
		return esc_html__( 'Comentarios de Facebook', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-facebook-comments';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'facebook', 'comentarios', 'comments', 'social' );
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
				'label' => esc_html__( 'Caja de comentarios', 'pro-elements' ),
			)
		);

		Facebook::add_app_id_control( $this );

		$this->add_control(
			'comments_number',
			array(
				'label'       => esc_html__( 'Número de comentarios', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 5,
				'max'         => 100,
				'default'     => '10',
				'description' => esc_html__( 'Mínimo: 5 comentarios.', 'pro-elements' ),
				'dynamic'     => array(
					'active' => true,
				),
			)
		);

		$this->add_control(
			'order_by',
			array(
				'label'   => esc_html__( 'Ordenar por', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'social',
				'options' => array(
					'social'       => esc_html__( 'Relevancia', 'pro-elements' ),
					'reverse_time' => esc_html__( 'Más recientes primero', 'pro-elements' ),
					'time'         => esc_html__( 'Más antiguos primero', 'pro-elements' ),
				),
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
					'url_type' => Facebook::URL_TYPE_CUSTOM,
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		if ( Facebook::URL_TYPE_CURRENT_PAGE === ( $settings['url_type'] ?? '' ) ) {
			$permalink = Facebook::permalink( $settings );
		} else {
			if ( ! filter_var( (string) ( $settings['url'] ?? '' ), FILTER_VALIDATE_URL ) ) {
				// Como PRO: el aviso se pinta también en la página.
				echo esc_html( $this->get_title() . ': ' . __( 'introduce una URL válida.', 'pro-elements' ) );
				return;
			}
			$permalink = esc_url( (string) $settings['url'] );
		}
		$attrs = array(
			'class'         => 'digi-fb__plugin fb-comments',
			'data-href'     => $permalink,
			'data-width'    => '100%',
			'data-numposts' => (string) ( $settings['comments_number'] ?? '10' ),
			'data-order-by' => (string) ( $settings['order_by'] ?? 'social' ),
			'style'         => 'min-height: 1px',
		);
		// Meta retiró el plugin (`Facebook::DISCONTINUED`): como PRO, sólo su `div`, que queda vacío (1 px); sin fachada ni SDK.
		echo Facebook::plugin( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en plugin().
	}

	public function render_plain_content() {}
}
