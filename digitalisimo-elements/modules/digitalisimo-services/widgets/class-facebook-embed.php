<?php
namespace Digitalisimo\Elements\Services\Widgets;

use Digitalisimo\Elements\Services\Facebook;
use Digitalisimo\Elements\Services\Module;

defined( 'ABSPATH' ) || exit;

/**
 * Publicación de Facebook (`digitalisimo-facebook-embed`): sustituye al «Facebook Embed» de PRO
 * (`modules/social/widgets/facebook-embed.php`; mapa `maps/facebook-embed.php`). Mismos controles derivados de PRO
 * Elements (GPL): publicación, vídeo o comentario; los plugins `fb-post`, `fb-video` y `fb-comment-embed` con los
 * mismos `data-*` que PRO, detrás de la fachada de `Services\Facebook` (un vídeo reserva 16:9).
 */
class Facebook_Embed extends \Elementor\Widget_Base {

	public function get_name() {
		return 'digitalisimo-facebook-embed';
	}

	public function get_title() {
		return esc_html__( 'Publicación de Facebook', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-fb-embed';
	}

	public function get_categories() {
		return array( 'digitalisimo' );
	}

	public function get_keywords() {
		return array( 'facebook', 'social', 'insertar', 'embed', 'vídeo', 'publicación', 'comentario' );
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
				'label' => esc_html__( 'Insertar', 'pro-elements' ),
			)
		);

		Facebook::add_app_id_control( $this );

		$this->add_control(
			'widget_exclusively_web',
			array(
				'type'       => \Elementor\Controls_Manager::ALERT,
				'alert_type' => 'info',
				'content'    => esc_html__( 'Facebook sólo permite insertar publicaciones en la web: en los móviles no se muestran.', 'pro-elements' ),
			)
		);

		$this->add_control(
			'type',
			array(
				'label'   => esc_html__( 'Tipo', 'pro-elements' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'post',
				'options' => array(
					'post'    => esc_html__( 'Publicación', 'pro-elements' ),
					'video'   => esc_html__( 'Vídeo', 'pro-elements' ),
					'comment' => esc_html__( 'Comentario', 'pro-elements' ),
				),
			)
		);

		foreach ( array(
			'post'    => esc_html__( 'Pasa el puntero por la fecha junto a la publicación y copia la dirección del enlace.', 'pro-elements' ),
			'video'   => esc_html__( 'Pasa el puntero por la fecha junto al vídeo y copia la dirección del enlace.', 'pro-elements' ),
			'comment' => esc_html__( 'Pasa el puntero por la fecha junto al comentario y copia la dirección del enlace.', 'pro-elements' ),
		) as $kind => $description ) {
			$this->add_control(
				$kind . '_url',
				array(
					'label'       => esc_html__( 'URL', 'pro-elements' ),
					'default'     => '',
					'dynamic'     => array(
						'active' => true,
					),
					'label_block' => true,
					'condition'   => array(
						'type' => $kind,
					),
					'description' => $description,
				)
			);
		}

		$this->add_control(
			'include_parent',
			array(
				'label'       => esc_html__( 'Comentario principal', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => esc_html__( 'Incluye el comentario principal si la URL es una respuesta.', 'pro-elements' ),
				'condition'   => array(
					'type' => 'comment',
				),
			)
		);

		$this->add_control(
			'show_text',
			array(
				'label'       => esc_html__( 'Publicación completa', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => esc_html__( 'Muestra el texto completo de la publicación.', 'pro-elements' ),
				'condition'   => array(
					'type' => array( 'post', 'video' ),
				),
			)
		);

		$this->add_control(
			'video_allowfullscreen',
			array(
				'label'     => esc_html__( 'Permitir pantalla completa', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'default'   => '',
				'condition' => array(
					'type' => 'video',
				),
			)
		);

		$this->add_control(
			'video_autoplay',
			array(
				'label'     => esc_html__( 'Reproducción automática', 'pro-elements' ),
				'type'      => \Elementor\Controls_Manager::SWITCHER,
				'default'   => '',
				'condition' => array(
					'type' => 'video',
				),
			)
		);

		$this->add_control(
			'video_show_captions',
			array(
				'label'       => esc_html__( 'Subtítulos', 'pro-elements' ),
				'type'        => \Elementor\Controls_Manager::SWITCHER,
				'default'     => '',
				'description' => esc_html__( 'Muestra los subtítulos si los hay (sólo en escritorio).', 'pro-elements' ),
				'condition'   => array(
					'type' => 'video',
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$type     = (string) ( $settings['type'] ?? '' );
		if ( '' === $type ) {
			echo esc_html__( 'Elige el tipo de contenido que quieres insertar.', 'pro-elements' );
			return;
		}
		$url = (string) ( $settings[ $type . '_url' ] ?? '' );
		if ( ! in_array( $type, array( 'post', 'video', 'comment' ), true ) || '' === $url ) {
			echo esc_html__( 'Introduce una URL válida.', 'pro-elements' );
			return;
		}
		$attrs = array( 'style' => 'min-height: 1px' );
		switch ( $type ) {
			case 'comment':
				$attrs['class']               = 'digi-fb__plugin fb-comment-embed';
				$attrs['data-href']           = esc_url( $url );
				$attrs['data-include-parent'] = 'yes' === ( $settings['include_parent'] ?? '' ) ? 'true' : 'false';
				$label                        = __( 'Ver el comentario de Facebook', 'pro-elements' );
				break;
			case 'video':
				$attrs['class']                = 'digi-fb__plugin fb-video';
				$attrs['data-href']            = esc_url( $url );
				$attrs['data-show-text']       = 'yes' === ( $settings['show_text'] ?? '' ) ? 'true' : 'false';
				$attrs['data-allowfullscreen'] = 'yes' === ( $settings['video_allowfullscreen'] ?? '' ) ? 'true' : 'false';
				$attrs['data-autoplay']        = 'yes' === ( $settings['video_autoplay'] ?? '' ) ? 'true' : 'false';
				$attrs['data-show-captions']   = 'yes' === ( $settings['video_show_captions'] ?? '' ) ? 'true' : 'false';
				$label                         = __( 'Ver el vídeo de Facebook', 'pro-elements' );
				break;
			default:
				$attrs['class']          = 'digi-fb__plugin fb-post';
				$attrs['data-href']      = esc_url( $url );
				$attrs['data-show-text'] = 'yes' === ( $settings['show_text'] ?? '' ) ? 'true' : 'false';
				$label                   = __( 'Ver la publicación de Facebook', 'pro-elements' );
		}
		echo Facebook::facade( (string) $this->get_id(), $type, $attrs, $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapado en facade().
	}

	public function render_plain_content() {}
}
