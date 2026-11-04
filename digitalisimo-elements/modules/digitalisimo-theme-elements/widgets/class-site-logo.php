<?php
namespace Digitalisimo\Elements\Theme_Elements\Widgets;

use Digitalisimo\Elements\Theme_Elements\Module;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Image_Size;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Logotipo del sitio (`digitalisimo-site-logo`): sustituye al widget «Site Logo» de PRO
 * (`modules/theme-builder/widgets/site-logo.php`, mapa `modules/digitalisimo-convert/maps/theme-site-logo.php`). Como
 * PRO, hereda la Imagen del núcleo (`\Elementor\Widget_Image`: mismos controles y hoja `widget-image`) y repite lo que
 * PRO cambia: la imagen por defecto es la etiqueta dinámica `site-logo` (el logotipo del Personalizador, `custom_logo`),
 * con la vista previa de solo lectura del Theme Builder (`media-preview`) y el botón «Cambiar el logotipo» que abre la
 * identidad del sitio; tamaño `full` por defecto; enlace `site_url` (el inicio) por defecto, con las opciones Ninguno,
 * URL del sitio, URL personalizada y Archivo de medios (sólo éste abre el lightbox); leyenda sólo del adjunto (sin
 * leyenda personalizada).
 *
 * La imagen se pinta con `wp_get_attachment_image()` (`Group_Control_Image_Size`), con `srcset`, `loading` y `decoding`.
 *
 * Diferencia declarada con PRO (accesibilidad): si el enlace al inicio no tendría nombre (el logotipo sin texto
 * alternativo), lleva `aria-label` con el nombre del sitio. Con texto alternativo, el nombre es ese texto, como en PRO.
 * La hoja propia sólo añade el foco visible del enlace y el movimiento reducido.
 */
class Site_Logo extends \Elementor\Widget_Image {

	/** Tipo del control de vista previa del Theme Builder (si no está registrado, el de medios del núcleo). */
	const PREVIEW_CONTROL = 'media-preview';

	public function get_name() {
		return 'digitalisimo-site-logo';
	}

	public function get_title() {
		return esc_html__( 'Logotipo del sitio', 'pro-elements' );
	}

	public function get_icon() {
		return 'eicon-site-logo';
	}

	public function get_categories() {
		return array( 'theme-elements' );
	}

	public function get_keywords() {
		return array( 'sitio', 'logotipo', 'logo', 'marca', 'site', 'branding' );
	}

	public function get_style_depends(): array {
		return array_values( array_unique( array_merge( parent::get_style_depends(), Module::style_depends( $this->get_name() ) ) ) );
	}

	protected function register_controls() {
		parent::register_controls();

		$this->update_control(
			'section_image',
			array(
				'label' => esc_html__( 'Logotipo del sitio', 'pro-elements' ),
			)
		);

		$controls = \Elementor\Plugin::$instance->controls_manager;
		$this->update_control(
			'image',
			array(
				'label'   => esc_html__( 'Logotipo del sitio', 'pro-elements' ),
				'type'    => $controls->get_control( self::PREVIEW_CONTROL ) ? self::PREVIEW_CONTROL : Controls_Manager::MEDIA,
				'src'     => $this->get_site_logo(),
				'dynamic' => array(
					'default' => \Elementor\Plugin::$instance->dynamic_tags->tag_data_to_tag_text( null, 'site-logo' ),
				),
			),
			array(
				'recursive' => true,
			)
		);

		$this->update_control(
			'image_size',
			array(
				'separator' => 'before',
				'default'   => 'full',
			)
		);

		$this->update_control(
			'link_to',
			array(
				'options' => array(
					'none'     => esc_html__( 'Ninguno', 'pro-elements' ),
					'site_url' => esc_html__( 'URL del sitio', 'pro-elements' ),
					'custom'   => esc_html__( 'URL personalizada', 'pro-elements' ),
					'file'     => esc_html__( 'Archivo de medios', 'pro-elements' ),
				),
				'default' => 'site_url',
			),
			array(
				'recursive' => true,
			)
		);

		$this->update_control(
			'caption_source',
			array(
				'options' => array(
					'none'       => esc_html__( 'Ninguna', 'pro-elements' ),
					'attachment' => esc_html__( 'Leyenda del adjunto', 'pro-elements' ),
				),
			)
		);

		$this->remove_control( 'caption' );

		$this->add_control(
			'change_logo_cta',
			array(
				'type'        => Controls_Manager::BUTTON,
				'label_block' => true,
				'show_label'  => false,
				'button_type' => 'default elementor-button-center',
				'text'        => esc_html__( 'Cambiar el logotipo', 'pro-elements' ),
				'event'       => 'elementorProSiteLogo:change',
			),
			array(
				'position' => array(
					'of'   => 'image',
					'type' => 'control',
					'at'   => 'after',
				),
			)
		);
	}

	/**
	 * El mismo marcado que PRO (que repite el de la Imagen del núcleo con su propio enlace): `figure` sólo con leyenda,
	 * enlace según `link_to` (lightbox sólo con «Archivo de medios») e imagen por `Group_Control_Image_Size`.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( empty( $settings['image']['url'] ) ) {
			return;
		}

		$caption = $this->caption_text( $settings );
		$link    = $this->get_link_url( $settings );

		if ( $link ) {
			$this->add_link_attributes( 'link', $link );

			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				$this->add_render_attribute( 'link', 'class', 'elementor-clickable' );
			}

			if ( 'file' === $settings['link_to'] ) {
				$this->add_lightbox_data_attributes( 'link', $settings['image']['id'], $settings['open_lightbox'] );
			}

			if ( 'site_url' === $settings['link_to'] && '' === $this->image_alt( $settings ) ) {
				$this->add_render_attribute( 'link', 'aria-label', (string) get_bloginfo( 'name' ) );
			}
		}

		if ( null !== $caption ) {
			echo '<figure class="wp-caption">';
		}
		if ( $link ) {
			echo '<a ' . $this->get_render_attribute_string( 'link' ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atributos escapados por el núcleo.
		}
		Group_Control_Image_Size::print_attachment_image_html( $settings );
		if ( $link ) {
			echo '</a>';
		}
		if ( null !== $caption ) {
			echo '<figcaption class="widget-image-caption wp-caption-text">' . wp_kses_post( $caption ) . '</figcaption></figure>';
		}
	}

	/** Enlace según `link_to`, como PRO: ninguno, el personalizado, el inicio (`site-url`) o el archivo. */
	protected function get_link_url( $settings ) {
		switch ( $settings['link_to'] ?? 'site_url' ) {
			case 'none':
				return false;
			case 'custom':
				return ! empty( $settings['link']['url'] ) ? $settings['link'] : false;
			case 'site_url':
				return array( 'url' => \Elementor\Plugin::$instance->dynamic_tags->get_tag_data_content( null, 'site-url' ) ?? '' );
			default:
				return array( 'url' => $settings['image']['url'] );
		}
	}

	/** Leyenda (sólo la del adjunto) o `null` si no hay. */
	private function caption_text( array $settings ) {
		if ( empty( $settings['caption_source'] ) || 'none' === $settings['caption_source'] ) {
			return null;
		}
		return 'attachment' === $settings['caption_source'] ? (string) wp_get_attachment_caption( $settings['image']['id'] ?? 0 ) : '';
	}

	/** Texto alternativo que tendrá la imagen (el del adjunto, como `wp_get_attachment_image()`). */
	private function image_alt( array $settings ): string {
		$id = (int) ( $settings['image']['id'] ?? 0 );
		return $id ? trim( wp_strip_all_tags( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) ) : trim( (string) ( $settings['image']['alt'] ?? '' ) );
	}

	/** El logotipo de la etiqueta dinámica, o la imagen de muestra del núcleo. */
	private function get_site_logo(): string {
		$logo = \Elementor\Plugin::$instance->dynamic_tags->get_tag_data_content( null, 'site-logo' );
		return is_array( $logo ) && ! empty( $logo['url'] ) ? (string) $logo['url'] : Utils::get_placeholder_image_src();
	}
}
