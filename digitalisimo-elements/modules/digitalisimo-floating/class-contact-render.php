<?php
namespace Digitalisimo\Elements\Floating;

use Elementor\Core\Base\Providers\Social_Network_Provider;
use Elementor\Icons_Manager;
use Elementor\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Marcado de los Botones de contacto (`digitalisimo-contact-buttons`), una clase por presentación. Repite el de las
 * variantes de PRO (código GPL de PRO Elements, `modules/floating-buttons/classes/render/contact-buttons-var-*-render.php`)
 * y el de su base del núcleo (`contact-buttons-render-base.php` y `contact-buttons-core-render.php`), con estas
 * diferencias:
 *
 * - clases propias: `digi-cb` (bloque), `digi-cb--p<n>` (presentación) y `digi-cb__*`; los modificadores `has-*` y las
 *   animaciones del núcleo (`elementor-animation-*`) se conservan;
 * - el panel tiene un id propio por elemento (PRO repetía `e-contact-buttons__content-wrapper` en cada widget) y el botón
 *   que lo abre lo nombra en `aria-controls` y lleva `aria-expanded` (lo pone el script);
 * - el contenedor no es un diálogo (`role="dialog"` sin foco ni nombre en PRO);
 * - el aspa de cierre es un SVG propio (sin la fuente de iconos del núcleo);
 * - los nombres accesibles, en español, con el nombre legible de cada servicio;
 * - sin el texto del proveedor que la base del núcleo pinta sin PRO.
 */
class Contact_Render {

	/** @var string Presentación (número de la variante de PRO). */
	protected $presentation = '1';

	/** Clase del marcado de cada presentación. */
	public static function make( $widget ): Contact_Render {
		$presentation = \Digitalisimo\Elements\Floating\Widgets\Contact_Buttons::presentation_of( (array) $widget->get_settings_for_display() );
		$class        = __NAMESPACE__ . '\\Contact_Render_' . $presentation;
		$render       = class_exists( $class ) ? new $class( $widget ) : new Contact_Render_1( $widget );
		$render->presentation = $presentation;
		return $render;
	}

	/** Id del panel de este elemento. */
	protected function panel_id(): string {
		return 'digi-cb-' . $this->widget->get_id() . '-panel';
	}

	/** Aspa propia. */
	public static function close_svg(): string {
		return '<i class="digi-cb__close-icon" aria-hidden="true"><svg focusable="false" viewBox="0 0 24 24" width="1em" height="1em"><path d="M5.5 5.5l13 13M18.5 5.5l-13 13" stroke="currentColor" stroke-width="2.4" fill="none"/></svg></i>';
	}

	protected $widget;

	protected array $settings;



	public function __construct( $widget ) {
		$this->widget = $widget;
		$this->settings = $widget->get_settings_for_display();
	}

	protected function render_chat_button_icon(): void {
		$platform = $this->settings['chat_button_platform'] ?? '';

		$mapping = Social_Network_Provider::get_icon_mapping( $platform );
		$icon_lib = explode( ' ', $mapping )[0];
		$library = 'fab' === $icon_lib ? 'fa-brands' : 'fa-solid';
		Icons_Manager::render_icon(
			[
				'library' => $library,
				'value' => $mapping,
			],
			[ 'aria-hidden' => 'true' ]
		);
	}

	protected function render_chat_button(): void {
		$platform = $this->settings['chat_button_platform'] ?? '';
		$display_dot = $this->settings['chat_button_show_dot'] ?? '';
		$button_size = $this->settings['style_chat_button_size'];
		$hover_animation = $this->settings['style_button_color_hover_animation'];
		$entrance_animation = $this->settings['style_chat_button_animation'];
		$entrance_animation_duration = $this->settings['style_chat_button_animation_duration'];
		$entrance_animation_delay = $this->settings['style_chat_button_animation_delay'];
		$accessible_name = $this->settings['chat_aria_label'];

		$button_classnames = 'digi-cb__chat-button digi-cb__chat-button-shadow';

		if ( ! empty( $button_size ) ) {
			$button_classnames .= ' has-size-' . $button_size;
		}

		if ( ! empty( $hover_animation ) ) {
			$button_classnames .= ' elementor-animation-' . $hover_animation;
		}

		if ( ! empty( $entrance_animation ) && 'none' != $entrance_animation ) {
			$button_classnames .= ' has-entrance-animation';
		}

		if ( ! empty( $entrance_animation_delay ) ) {
			$button_classnames .= ' has-entrance-animation-delay';
		}

		if ( ! empty( $entrance_animation_duration ) ) {
			$button_classnames .= ' has-entrance-animation-duration-' . $entrance_animation_duration;
		}

		if ( 'yes' === $display_dot ) {
			$button_classnames .= ' has-dot';
		}

		$this->widget->add_render_attribute( 'button', [
			'class' => $button_classnames,
			'aria-controls' => $this->panel_id(),
			'aria-label' => sprintf(
				/* translators: %s: Accessible name. */
				esc_html__( 'Mostrar u ocultar %s', 'pro-elements' ),
				$accessible_name,
			),
			'type' => 'button',
		] );

		?>
		<div class="digi-cb__chat-button-container">
			<button <?php echo $this->widget->get_render_attribute_string( 'button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php
					$this->render_chat_button_icon();
				?>
			</button>
		</div>
		<?php
	}

	protected function render_close_button(): void {
		$accessible_name = $this->settings['chat_aria_label'];

		$this->widget->add_render_attribute( 'close-button', [
			'class' => 'digi-cb__close-button',
			'aria-controls' => $this->panel_id(),
			'aria-label' => sprintf(
				/* translators: %s: Accessible name. */
				esc_html__( 'Cerrar %s', 'pro-elements' ),
				$accessible_name,
			),
			'type' => 'button',
		] );

		?>
			<button <?php echo $this->widget->get_render_attribute_string( 'close-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php echo self::close_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		<?php
	}

	protected function render_top_bar(): void {
		$profile_image_value = $this->settings['top_bar_image'] ?? [];
		$has_profile_image = ! empty( $profile_image_value ) && ( ! empty( $profile_image_value['url'] || ! empty( $profile_image_value['id'] ) ) );
		$profile_image_size = $this->settings['style_top_bar_image_size'];
		$display_profile_dot = $this->settings['top_bar_show_dot'];

		$profile_image_classnames = 'digi-cb__profile-image';

		if ( ! empty( $profile_image_size ) ) {
			$profile_image_classnames .= ' has-size-' . $profile_image_size;
		}

		if ( 'yes' === $display_profile_dot ) {
			$profile_image_classnames .= ' has-dot';
		}

		$top_bar_title = $this->settings['top_bar_title'] ?? '';
		$top_bar_subtitle = $this->settings['top_bar_subtitle'] ?? '';

		$has_top_bar_title = ! empty( $top_bar_title );
		$has_top_bar_subtitle = ! empty( $top_bar_subtitle );

		$this->widget->add_render_attribute( 'profile-image', [
			'class' => $profile_image_classnames,
		] );
		?>
		<div class="digi-cb__top-bar">
			<?php $this->render_close_button(); ?>
			<div <?php echo $this->widget->get_render_attribute_string( 'profile-image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php if ( ! empty( $profile_image_value['id'] ) ) {
					echo wp_get_attachment_image( $profile_image_value['id'], 'medium', false, [
						'class' => 'digi-cb__profile-image-el',
					] );
				} else {
					$this->widget->add_render_attribute( 'profile-image-src', [
						'alt'   => '',
						'class' => 'digi-cb__profile-image-el',
						'src'   => esc_url( $profile_image_value['url'] ),
					] );
					?>
					<img <?php echo $this->widget->get_render_attribute_string( 'profile-image-src' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
				<?php } ?>
			</div>

			<div class="digi-cb__top-bar-details">
				<?php if ( $has_top_bar_title ) { ?>
					<p class="digi-cb__top-bar-title"><?php echo esc_html( $top_bar_title ); ?></p>
				<?php } ?>
				<?php if ( $has_top_bar_subtitle ) { ?>
					<p class="digi-cb__top-bar-subtitle"><?php echo esc_html( $top_bar_subtitle ); ?></p>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	protected function render_message_bubble_typing_animation(): void {
		$has_typing_animation = 'yes' === $this->settings['chat_button_show_animation'];
		?>
			<?php if ( $has_typing_animation ) { ?>
				<div class="digi-cb__dots-container">
					<span class="digi-cb__dot digi-cb__dot-1"></span>
					<span class="digi-cb__dot digi-cb__dot-2"></span>
					<span class="digi-cb__dot digi-cb__dot-3"></span>
				</div>
			<?php } ?>
		<?php
	}

	protected function render_message_bubble_container(): void {
		$message_bubble_name = $this->settings['message_bubble_name'] ?? '';
		$message_bubble_body = $this->settings['message_bubble_body'] ?? '';
		$has_message_bubble_name = ! empty( $message_bubble_name );
		$has_message_bubble_body = ! empty( $message_bubble_body );
		$time_format = $this->settings['chat_button_time_format'];
		?>
			<div class="digi-cb__bubble-container">
				<div class="digi-cb__bubble">
					<?php if ( $has_message_bubble_name ) { ?>
						<p class="digi-cb__message-bubble-name"><?php echo esc_html( $message_bubble_name ); ?></p>
					<?php } ?>
					<?php if ( $has_message_bubble_body ) { ?>
						<p class="digi-cb__message-bubble-body"><?php echo esc_html( $message_bubble_body ); ?></p>
					<?php } ?>
					<p class="digi-cb__message-bubble-time" data-time-format="<?php echo esc_attr( $time_format ); ?>"></p>
				</div>
			</div>
		<?php
	}

	protected function render_message_bubble_powered_by(): void {
		// Sin texto del proveedor (AGENTS.md: ningún rastro de otras empresas).
	}

	protected function render_message_bubble(): void {
		$message_bubble_classnames = 'digi-cb__message-bubble';
		$show_animation = $this->settings['chat_button_show_animation'] ?? false;
		$has_typing_animation = $show_animation && 'yes' === $show_animation;

		if ( $has_typing_animation ) {
			$message_bubble_classnames .= ' has-typing-animation';
		}

		$this->widget->add_render_attribute( 'message-bubble', [
			'class' => $message_bubble_classnames,
		] );
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'message-bubble' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
				$this->render_message_bubble_typing_animation();
				$this->render_message_bubble_container();
			?>
		</div>
		<?php
	}

	protected function render_contact_text(): void {
		$contact_cta_text = $this->settings['contact_cta_text'] ?? '';
		?>
			<?php if ( ! empty( $contact_cta_text ) ) { ?>
				<p class="digi-cb__contact-text"><?php echo esc_html( $contact_cta_text ); ?></p>
			<?php } ?>
		<?php
	}

	protected function render_contact_links(): void {
		$contact_icons = $this->settings['contact_repeater'] ?? [];
		$icons_size = $this->settings['style_contact_button_size'] ?? 'small';
		$hover_animation = $this->settings['style_contact_button_hover_animation'];
		?>
			<div class="digi-cb__contact-links">
				<?php
				foreach ( $contact_icons as $key => $icon ) {
					$icon_text_mapping = Social_Network_Provider::get_text_mapping( $icon['contact_icon_platform'] );
					$aria_label = sprintf(
						/* translators: %s: Platform name. */
						esc_html__( 'Abrir %s', 'pro-elements' ),
						$icon_text_mapping,
					);

					$link = [
						'platform' => $icon['contact_icon_platform'],
						'number' => $icon['contact_icon_number'] ?? '',
						'username' => $icon['contact_icon_username'] ?? '',
						'email_data' => [
							'contact_icon_mail' => $icon['contact_icon_mail'] ?? '',
							'contact_icon_mail_subject' => $icon['contact_icon_mail_subject'] ?? '',
							'contact_icon_mail_body' => $icon['contact_icon_mail_body'] ?? '',
						],
						'viber_action' => $icon['contact_icon_viber_action'] ?? '',
					];

					$formatted_link = $this->get_formatted_link( $link, 'contact_icon' );

					$icon_classnames = 'digi-cb__contact-icon-link has-size-' . $icons_size;

					if ( ! empty( $hover_animation ) ) {
						$icon_classnames .= ' elementor-animation-' . $hover_animation;
					}

					$this->widget->add_render_attribute( 'icon-link-' . $key, [
						'aria-label' => $aria_label,
						'class' => $icon_classnames,
						'href' => $formatted_link,
						'rel' => 'noopener noreferrer',
						'target' => '_blank',
					] );

					?>

					<a <?php echo $this->widget->get_render_attribute_string( 'icon-link-' . $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php
							$mapping = Social_Network_Provider::get_icon_mapping( $icon['contact_icon_platform'] );
							$icon_lib = explode( ' ', $mapping )[0];
							$library = 'fab' === $icon_lib ? 'fa-brands' : 'fa-solid';
							Icons_Manager::render_icon(
								[
									'library' => $library,
									'value' => $mapping,
								],
								[ 'aria-hidden' => 'true' ]
							);
						?>
					</a>
				<?php } ?>
			</div>
		<?php
	}

	protected function render_contact_section(): void {
		?>
		<div class="digi-cb__contact">
			<?php
				$this->render_contact_text();
				$this->render_contact_links();
			?>
		</div>
		<?php
	}

	protected function render_send_button(): void {
		$platform = $this->settings['chat_button_platform'] ?? '';
		$send_button_text = $this->settings['send_button_text'];
		$hover_animation = $this->settings['style_send_hover_animation'];
		$cta_classnames = 'digi-cb__send-cta';

		$link = [
			'platform' => $platform,
			'number' => $this->settings['chat_button_number'] ?? '',
			'username' => $this->settings['chat_button_username'] ?? '',
			'email_data' => [
				'chat_button_mail' => $this->settings['chat_button_mail'],
				'chat_button_mail_subject' => $this->settings['chat_button_mail_subject'] ?? '',
				'chat_button_mail_body' => $this->settings['chat_button_mail_body'] ?? '',
			],
			'viber_action' => $this->settings['chat_button_viber_action'],
		];

		$formatted_link = $this->get_formatted_link( $link, 'chat_button' );

		if ( ! empty( $hover_animation ) ) {
			$cta_classnames .= ' elementor-animation-' . $hover_animation;
		}

		$this->widget->add_render_attribute( 'formatted-cta', [
			'class' => $cta_classnames,
			'href' => $formatted_link,
			'rel' => 'noopener noreferrer',
			'target' => '_blank',
		] );

		?>
		<div class="digi-cb__send-button">
			<?php $this->render_message_bubble_powered_by(); ?>
			<div class="digi-cb__send-button-container">
				<?php if ( $send_button_text ) { ?>
					<a <?php echo $this->widget->get_render_attribute_string( 'formatted-cta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php
							$mapping = Social_Network_Provider::get_icon_mapping( $platform );
							$icon_lib = explode( ' ', $mapping )[0];
							$library = 'fab' === $icon_lib ? 'fa-brands' : 'fa-solid';
							Icons_Manager::render_icon(
								[
									'library' => $library,
									'value' => $mapping,
								],
								[ 'aria-hidden' => 'true' ]
							);
						?>
						<?php echo esc_html( $send_button_text ); ?>
					</a>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	protected function get_formatted_link( array $link, string $prefix ): string {

		// Ensure we clear the default link value if the matching type value is empty
		switch ( $link['platform'] ) {
			case Social_Network_Provider::EMAIL:
				$formatted_link = Social_Network_Provider::build_email_link( $link['email_data'], $prefix );
				break;
			case Social_Network_Provider::SMS:
				$formatted_link = ! empty( $link['number'] ) ? 'sms:' . $link['number'] : '';
				break;
			case Social_Network_Provider::MESSENGER:
				$formatted_link = ! empty( $link['username'] ) ?
					Social_Network_Provider::build_messenger_link( $link['username'] ) :
					'';
				break;
			case Social_Network_Provider::WHATSAPP:
				$formatted_link = ! empty( $link['number'] ) ? 'https://wa.me/' . $link['number'] : '';
				break;
			case Social_Network_Provider::VIBER:
				$formatted_link = Social_Network_Provider::build_viber_link( $link['viber_action'], $link['number'] );
				break;
			case Social_Network_Provider::SKYPE:
				$formatted_link = ! empty( $link['username'] ) ? 'skype:' . $link['username'] . '?chat' : '';
				break;
			case Social_Network_Provider::TELEPHONE:
				$formatted_link = ! empty( $link['number'] ) ? 'tel:' . $link['number'] : '';
				break;
			default:
				break;
		}

		return esc_html( $formatted_link );
	}

	protected function is_url_link( string $platform ): bool {
		return Social_Network_Provider::URL === $platform || Social_Network_Provider::WAZE === $platform;
	}

	protected function render_link_attributes( array $link, string $key ) {
		switch ( $link['platform'] ) {
			case Social_Network_Provider::WAZE:
				if ( empty( $link['location']['url'] ) ) {
					$link['location']['url'] = '#';
				}

				$this->widget->add_link_attributes( $key, $link['location'] );
				break;
			case Social_Network_Provider::URL:
				if ( empty( $link['url']['url'] ) ) {
					$link['url']['url'] = '#';
				}

				$this->widget->add_link_attributes( $key, $link['url'] );
				break;
			default:
				break;
		}
	}

	protected function build_layout_render_attribute(): void {
		$layout_classnames = 'digi-cb digi-cb--p' . $this->presentation;
		$platform = $this->settings['chat_button_platform'] ?? '';
		$border_radius = $this->settings['style_chat_box_corners'];
		$alignment_position_horizontal = $this->settings['advanced_horizontal_position'];
		$alignment_position_vertical = $this->settings['advanced_vertical_position'];
		$has_animations = ! empty( $this->settings['style_chat_box_exit_animation'] ) || ! empty( $this->settings['style_chat_box_entrance_animation'] );
		$custom_classes = $this->settings['advanced_custom_css_classes'] ?? '';

		$icon_name_mapping = Social_Network_Provider::get_name_mapping( $platform );

		if ( ! empty( $platform ) ) {
			$layout_classnames .= ' has-platform-' . $icon_name_mapping;
		}

		if ( ! empty( $border_radius ) ) {
			$layout_classnames .= ' has-corners-' . $border_radius;
		}

		if ( ! empty( $alignment_position_horizontal ) ) {
			$layout_classnames .= ' has-h-alignment-' . $alignment_position_horizontal;
		}

		if ( ! empty( $alignment_position_vertical ) ) {
			$layout_classnames .= ' has-v-alignment-' . $alignment_position_vertical;
		}

		if ( $has_animations ) {
			$layout_classnames .= ' has-animations';
		}

		if ( $custom_classes ) {
			$layout_classnames .= ' ' . $custom_classes;
		}

		$this->add_layout_render_attribute( $layout_classnames );
	}

	public function render(): void {
		$this->build_layout_render_attribute();
		$this->add_content_wrapper_render_attribute();

		$content_classnames = 'digi-cb__content';
		$animation_duration = $this->settings['style_chat_box_animation_duration'];

		if ( ! empty( $animation_duration ) ) {
			$content_classnames .= ' has-animation-duration-' . $animation_duration;
		}

		$this->widget->add_render_attribute( 'content', [
			'class' => $content_classnames,
		] );
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div <?php echo $this->widget->get_render_attribute_string( 'content-wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div <?php echo $this->widget->get_render_attribute_string( 'content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php
					$this->render_top_bar();
					$this->render_message_bubble();
					$this->render_send_button();
					?>
				</div>
			</div>
			<?php
			$this->render_chat_button();
			?>
		</div>
		<?php
	}

	protected function add_layout_render_attribute( $layout_classnames ) {
		$this->widget->add_render_attribute( 'layout', [
			'class' => $layout_classnames,
			'id' => $this->settings['advanced_custom_css_id'],
			'data-document-id' => get_the_ID(),
		] );
	}

	protected function add_content_wrapper_render_attribute() {
		$this->widget->add_render_attribute( 'content-wrapper', [
			'aria-hidden' => 'true',
			'aria-label' => __( 'Ventana de enlaces', 'pro-elements' ),
			'class' => 'digi-cb__content-wrapper hidden',
			'id' => $this->panel_id(),
		] );
	}

}

class Contact_Render_1 extends Contact_Render {

	protected function render_message_bubble(): void {
		$message_bubble_classnames = 'digi-cb__message-bubble';

		$this->widget->add_render_attribute( 'message-bubble', [
			'class' => $message_bubble_classnames,
		] );
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'message-bubble' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
				$this->render_message_bubble_container();
			?>
		</div>
		<?php
	}

	protected function render_chat_button_icon(): void {
		$custom_icon = $this->settings['chat_button_icon'] ?? '';

		Icons_Manager::render_icon( $custom_icon );
	}

	public function render(): void {
		$this->build_layout_render_attribute();
		$this->add_content_wrapper_render_attribute();

		$content_classnames = 'digi-cb__content';
		$animation_duration = $this->settings['style_chat_box_animation_duration'];

		if ( ! empty( $animation_duration ) ) {
			$content_classnames .= ' has-animation-duration-' . $animation_duration;
		}

		$this->widget->add_render_attribute( 'content', [
			'class' => $content_classnames,
		] );
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div <?php echo $this->widget->get_render_attribute_string( 'content-wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div <?php echo $this->widget->get_render_attribute_string( 'content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php
					$this->render_top_bar();
					$this->render_message_bubble();
					$this->render_contact_section();
					?>
				</div>
			</div>
			<?php
			$this->render_chat_button();
			?>
		</div>
		<?php
	}
}

class Contact_Render_3 extends Contact_Render {

	protected function render_chat_button_icon(): void {
		$custom_icon = $this->settings['chat_button_icon'] ?? '';

		Icons_Manager::render_icon( $custom_icon );
	}

	protected function render_chat_button(): void {
		$button_size = $this->settings['style_chat_button_size'];
		$hover_animation = $this->settings['style_button_color_hover_animation'];
		$entrance_animation = $this->settings['style_chat_button_animation'];
		$entrance_animation_duration = $this->settings['style_chat_button_animation_duration'];
		$entrance_animation_delay = $this->settings['style_chat_button_animation_delay'];
		$accessible_name = $this->settings['chat_aria_label'];

		$button_classnames = 'digi-cb__chat-button digi-cb__chat-button-shadow';

		if ( ! empty( $button_size ) ) {
			$button_classnames .= ' has-size-' . $button_size;
		}

		if ( ! empty( $hover_animation ) ) {
			$button_classnames .= ' elementor-animation-' . $hover_animation;
		}

		if ( ! empty( $entrance_animation ) && 'none' != $entrance_animation ) {
			$button_classnames .= ' has-entrance-animation';
		}

		if ( ! empty( $entrance_animation_delay ) ) {
			$button_classnames .= ' has-entrance-animation-delay';
		}

		if ( ! empty( $entrance_animation_duration ) ) {
			$button_classnames .= ' has-entrance-animation-duration-' . $entrance_animation_duration;
		}

		$this->widget->add_render_attribute( 'button-', [
			'class' => $button_classnames,
			'aria-controls' => $this->panel_id(),
			'type' => 'button',
			'aria-label' => sprintf(
				/* translators: 1: Accessible name */
				esc_attr__( 'Mostrar u ocultar %1$s', 'pro-elements' ),
				$accessible_name,
			),
			'aria-expanded' => 'true',
		] );

		?>
		<div class="digi-cb__chat-button-container">
			<button <?php echo $this->widget->get_render_attribute_string( 'button-' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php
					$this->render_chat_button_icon();
				?>
			</button>
		</div>
		<?php
	}

	protected function render_top_bar(): void {
		$top_bar_title = $this->settings['top_bar_title'] ?? '';
		$has_top_bar_title = ! empty( $top_bar_title );
		$accessible_name = $this->settings['chat_aria_label'];

		$this->widget->add_render_attribute( 'close-button', [
			'class' => 'digi-cb__close-button',
			'aria-controls' => $this->panel_id(),
			'aria-label' => sprintf(
				/* translators: 1: Accessible name */
				esc_attr__( 'Cerrar %1$s', 'pro-elements' ),
				$accessible_name,
			),
			'type' => 'button',
		] );

		?>
		<div class="digi-cb__top-bar">
			<button <?php echo $this->widget->get_render_attribute_string( 'close-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php echo self::close_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
			<div class="digi-cb__top-bar-details">
				<?php if ( $has_top_bar_title ) { ?>
					<p class="digi-cb__top-bar-title"><?php echo esc_html( $top_bar_title ); ?></p>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	protected function render_contact_section(): void {
		$contact_icons = $this->settings['contact_repeater'] ?? [];
		$icon_position = $this->settings['style_info_links_icon_position'];
		$has_dividers = $this->settings['style_info_links_dividers'];
		$hover_animation = $this->settings['style_info_links_hover_animation'];

		?>
		<div class="digi-cb__links">
			<?php
			foreach ( $contact_icons as $key => $icon ) {
				$link = [
					'platform' => $icon['contact_icon_platform'],
					'number' => $icon['contact_icon_number'] ?? '',
					'username' => $icon['contact_icon_username'] ?? '',
					'email_data' => [
						'contact_icon_mail' => $icon['contact_icon_mail'] ?? '',
						'contact_icon_mail_subject' => $icon['contact_icon_mail_subject'] ?? '',
						'contact_icon_mail_body' => $icon['contact_icon_mail_body'] ?? '',
					],
					'viber_action' => $icon['contact_icon_viber_action'] ?? '',
					'url' => $icon['contact_icon_url'] ?? '',
					'location' => $icon['contact_icon_waze'] ?? '',
				];

				$icon_tooltip = $icon['contact_tooltip'] ?? '';

				$icon_classnames = 'digi-cb__contact-icon-link';

				if ( ! empty( $icon_position ) ) {
					$icon_classnames .= ' has-icon-position-' . $icon_position;
				}

				if ( 'yes' == $has_dividers ) {
					$icon_classnames .= ' has-dividers';
				}

				if ( ! empty( $hover_animation ) ) {
					$icon_classnames .= ' elementor-animation-' . $hover_animation;
				}

				if ( $this->is_url_link( $icon['contact_icon_platform'] ) ) {
					$this->render_link_attributes( $link, 'icon-link-' . $key );

					$this->widget->add_render_attribute( 'icon-link-' . $key, [
						'class' => $icon_classnames,
					] );
				} else {
					$formatted_link = $this->get_formatted_link( $link, 'contact_icon' );

					$this->widget->add_render_attribute( 'icon-link-' . $key, [
						'aria-label' => esc_attr( Social_Network_Provider::get_text_mapping( $icon['contact_icon_platform'] ) ),
						'class' => $icon_classnames,
						'href' => $formatted_link,
						'rel' => 'noopener noreferrer',
						'target' => '_blank',
					] );
				}
				?>

				<a <?php echo $this->widget->get_render_attribute_string( 'icon-link-' . $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<span class="digi-cb__contact-icon-container">
						<?php
							$mapping = Social_Network_Provider::get_icon_mapping( $icon['contact_icon_platform'] );
							$icon_lib = explode( ' ', $mapping )[0];
							$library = 'fab' === $icon_lib ? 'fa-brands' : 'fa-solid';
							Icons_Manager::render_icon(
								[
									'library' => $library,
									'value' => $mapping,
								],
								[ 'aria-hidden' => 'true' ]
							);
						?>
					</span>
					<?php if ( ! empty( $icon_tooltip ) ) { ?>
						<span class="digi-cb__contact-tooltip"><?php echo esc_html( $icon_tooltip ); ?></span>
					<?php } ?>
					<?php if ( ! empty( $icon_description ) ) { ?>
						<span class="digi-cb__contact-description"><?php echo esc_html( $icon_description ); ?></span>
					<?php } ?>
				</a>
			<?php } ?>
		</div>
		<?php
	}

	protected function render_send_button_section(): void {
		$button_classnames = 'digi-cb__cta-button';
		$button_text = $this->settings['send_button_text'];
		$hover_animation = $this->settings['style_send_hover_animation'];

		if ( ! empty( $hover_animation ) ) {
			$button_classnames .= ' elementor-animation-' . $hover_animation;
		}

		$formatted_link = $this->settings['send_button_url']['url'];

		$this->widget->add_render_attribute( 'send-button', [
			'aria-label' => esc_attr( $button_text ),
			'class' => $button_classnames,
			'href' => esc_url( $formatted_link ),
			'rel' => 'noopener noreferrer',
			'target' => '_blank',
		] );
		?>
		<a <?php echo $this->widget->get_render_attribute_string( 'send-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php echo esc_html( $button_text ); ?>
		</a>
		<?php
	}

	protected function build_layout_render_attribute(): void {
		$layout_classnames = 'digi-cb digi-cb--p' . $this->presentation;
		$has_animations = ! empty( $this->settings['style_chat_box_exit_animation'] ) || ! empty( $this->settings['style_chat_box_entrance_animation'] );
		$alignment_position_horizontal = $this->settings['advanced_horizontal_position'];
		$alignment_position_vertical = $this->settings['advanced_vertical_position'];
		$border_radius = $this->settings['style_chat_box_corners'];

		$custom_classes = $this->settings['advanced_custom_css_classes'] ?? '';

		if ( ! empty( $alignment_position_horizontal ) ) {
			$layout_classnames .= ' has-h-alignment-' . $alignment_position_horizontal;
		}

		if ( ! empty( $alignment_position_vertical ) ) {
			$layout_classnames .= ' has-v-alignment-' . $alignment_position_vertical;
		}

		if ( ! empty( $border_radius ) ) {
			$layout_classnames .= ' has-corners-' . $border_radius;
		}

		if ( $has_animations ) {
			$layout_classnames .= ' has-animations';
		}

		if ( $custom_classes ) {
			$layout_classnames .= ' ' . $custom_classes;
		}

		$this->widget->add_render_attribute( 'layout', [
			'class' => $layout_classnames,
			'id'    => $this->settings['advanced_custom_css_id'],
		] );
	}

	public function render(): void {
		$this->build_layout_render_attribute();
		$this->add_content_wrapper_render_attribute();

		$content_classnames = 'digi-cb__content';

		$this->widget->add_render_attribute( 'content', [
			'class' => $content_classnames,
		] );
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div <?php echo $this->widget->get_render_attribute_string( 'content-wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div <?php echo $this->widget->get_render_attribute_string( 'content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php
					$this->render_top_bar();
					?>
					<div class="digi-cb__links-container">
						<?php
						$this->render_contact_section();
						$this->render_send_button_section();
						?>
					</div>
				</div>
			</div>
			<?php
				$this->render_chat_button();
			?>
		</div>
		<?php
	}
}

class Contact_Render_4 extends Contact_Render {

	protected function render_chat_button_icon(): void {
		$custom_icon = $this->settings['chat_button_icon'] ?? '';

		Icons_Manager::render_icon( $custom_icon );
	}

	protected function build_layout_render_attribute(): void {
		$layout_classnames = 'digi-cb digi-cb--p' . $this->presentation;
		$has_animations = ! empty( $this->settings['style_chat_box_exit_animation'] ) || ! empty( $this->settings['style_chat_box_entrance_animation'] );
		$alignment_position_horizontal = $this->settings['advanced_horizontal_position'];
		$alignment_position_vertical = $this->settings['advanced_vertical_position'];

		$custom_classes = $this->settings['advanced_custom_css_classes'] ?? '';

		if ( $has_animations ) {
			$layout_classnames .= ' has-animations';
		}

		if ( ! empty( $alignment_position_horizontal ) ) {
			$layout_classnames .= ' has-h-alignment-' . $alignment_position_horizontal;
		}

		if ( ! empty( $alignment_position_vertical ) ) {
			$layout_classnames .= ' has-v-alignment-' . $alignment_position_vertical;
		}

		if ( $custom_classes ) {
			$layout_classnames .= ' ' . $custom_classes;
		}

		$this->add_layout_render_attribute( $layout_classnames );
	}

	protected function render_close_button(): void {
		$button_size = $this->settings['style_chat_button_size'];
		$button_classnames = 'digi-cb__close-button digi-cb__chat-button-shadow';
		$accessible_name = $this->settings['chat_aria_label'];

		if ( ! empty( $button_size ) ) {
			$button_classnames .= ' has-size-' . $button_size;
		}

		$this->widget->add_render_attribute( 'close-button', [
			'class' => $button_classnames,
			'aria-controls' => $this->panel_id(),
			'aria-label' => sprintf(
				/* translators: 1: Accessible name */
				esc_attr__( 'Cerrar %1$s', 'pro-elements' ),
				$accessible_name,
			),
			'type' => 'button',
			'aria-expanded' => 'false',
		] );

		?>
			<button <?php echo $this->widget->get_render_attribute_string( 'close-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php echo self::close_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		<?php
	}

	protected function render_chat_button(): void {
		$platform = $this->settings['chat_button_platform'] ?? '';
		$button_size = $this->settings['style_chat_button_size'];
		$hover_animation = $this->settings['style_button_color_hover_animation'];
		$entrance_animation = $this->settings['style_chat_button_animation'];
		$entrance_animation_duration = $this->settings['style_chat_button_animation_duration'];
		$entrance_animation_delay = $this->settings['style_chat_button_animation_delay'];
		$accessible_name = $this->settings['chat_aria_label'];

		$button_classnames = 'digi-cb__chat-button digi-cb__chat-button-shadow';

		if ( ! empty( $button_size ) ) {
			$button_classnames .= ' has-size-' . $button_size;
		}

		if ( ! empty( $hover_animation ) ) {
			$button_classnames .= ' elementor-animation-' . $hover_animation;
		}

		if ( ! empty( $entrance_animation ) && 'none' != $entrance_animation ) {
			$button_classnames .= ' has-entrance-animation';
		}

		if ( ! empty( $entrance_animation_delay ) ) {
			$button_classnames .= ' has-entrance-animation-delay';
		}

		if ( ! empty( $entrance_animation_duration ) ) {
			$button_classnames .= ' has-entrance-animation-duration-' . $entrance_animation_duration;
		}

		$this->widget->add_render_attribute( 'button-', [
			'class' => $button_classnames,
			'aria-controls' => $this->panel_id(),
			'aria-label' => sprintf(
				/* translators: 1: Accessible name */
				esc_attr__( 'Mostrar u ocultar %1$s', 'pro-elements' ),
				$accessible_name,
			),
			'type' => 'button',
			'aria-expanded' => 'true',
		] );

		?>
		<div class="digi-cb__chat-button-container">
			<button <?php echo $this->widget->get_render_attribute_string( 'button-' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php
					$this->render_chat_button_icon();
				?>
			</button>
			<?php
				$this->render_close_button();
			?>
		</div>
		<?php
	}

	protected function render_contact_section(): void {
		$contact_icons = $this->settings['contact_repeater'] ?? [];
		$button_size = $this->settings['style_chat_button_size'];

		?>
		<div class="digi-cb__contact">
		<div class="digi-cb__contact-links">
				<?php
				foreach ( $contact_icons as $key => $icon ) {

					$link = [
						'platform' => $icon['contact_icon_platform'],
						'number' => $icon['contact_icon_number'] ?? '',
						'username' => $icon['contact_icon_username'] ?? '',
						'email_data' => [
							'contact_icon_mail' => $icon['contact_icon_mail'] ?? '',
							'contact_icon_mail_subject' => $icon['contact_icon_mail_subject'] ?? '',
							'contact_icon_mail_body' => $icon['contact_icon_mail_body'] ?? '',
						],
						'viber_action' => $icon['contact_icon_viber_action'] ?? '',
						'url' => $icon['contact_icon_url'] ?? '',
						'location' => $icon['contact_icon_waze'] ?? '',
					];

					$icon_classnames = 'digi-cb__contact-icon-link has-size-' . $button_size;
					$icon_tooltip = $icon['contact_tooltip'] ?? '';

					if ( $this->is_url_link( $icon['contact_icon_platform'] ) ) {
						$this->render_link_attributes( $link, 'icon-link-' . $key );

						$this->widget->add_render_attribute( 'icon-link-' . $key, [
							'class' => $icon_classnames,
						] );
					} else {
						$formatted_link = $this->get_formatted_link( $link, 'contact_icon' );

						$this->widget->add_render_attribute( 'icon-link-' . $key, [
							'aria-label' => esc_attr( Social_Network_Provider::get_text_mapping( $icon['contact_icon_platform'] ) ),
							'class' => $icon_classnames,
							'href' => $formatted_link,
							'rel' => 'noopener noreferrer',
							'target' => '_blank',
						] );
					}

					?>

					<a <?php echo $this->widget->get_render_attribute_string( 'icon-link-' . $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php if ( ! empty( $icon_tooltip ) ) { ?>
							<span class="digi-cb__contact-tooltip digi-cb__contact-box-shadow"><?php echo esc_html( $icon_tooltip ); ?></span>
						<?php } ?>
						<span class="digi-cb__contact-icon-container digi-cb__contact-box-shadow">
							<?php
								$mapping = Social_Network_Provider::get_icon_mapping( $icon['contact_icon_platform'] );
								$icon_lib = explode( ' ', $mapping )[0];
								$library = 'fab' === $icon_lib ? 'fa-brands' : 'fa-solid';
								Icons_Manager::render_icon(
									[
										'library' => $library,
										'value' => $mapping,
									],
									[ 'aria-hidden' => 'true' ]
								);
							?>
						</span>
					</a>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	public function render(): void {
		$this->build_layout_render_attribute();
		$this->add_content_wrapper_render_attribute();

		$content_classnames = 'digi-cb__content';
		$animation_duration = $this->settings['style_chat_box_animation_duration'];

		if ( ! empty( $animation_duration ) ) {
			$content_classnames .= ' has-animation-duration-' . $animation_duration;
		}

		$this->widget->add_render_attribute( 'content', [
			'class' => $content_classnames,
		] );
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div <?php echo $this->widget->get_render_attribute_string( 'content-wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div <?php echo $this->widget->get_render_attribute_string( 'content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php
						$this->render_contact_section();
					?>
				</div>
			</div>
			<?php
				$this->render_chat_button();
			?>
		</div>
		<?php
	}
}

class Contact_Render_5 extends Contact_Render {

	protected function build_layout_render_attribute(): void {
		$layout_classnames = 'digi-cb digi-cb--p' . $this->presentation;
		$platform = $this->settings['chat_button_platform'] ?? '';
		$alignment_position_horizontal = $this->settings['advanced_horizontal_position'];
		$alignment_position_vertical = $this->settings['advanced_vertical_position'];
		$has_animations = ! empty( $this->settings['style_chat_box_exit_animation'] ) || ! empty( $this->settings['style_chat_box_entrance_animation'] );
		$custom_classes = $this->settings['advanced_custom_css_classes'] ?? '';
		$icon_name_mapping = Social_Network_Provider::get_name_mapping( $platform );

		if ( ! empty( $alignment_position_horizontal ) ) {
			$layout_classnames .= ' has-h-alignment-' . $alignment_position_horizontal;
		}

		if ( ! empty( $alignment_position_vertical ) ) {
			$layout_classnames .= ' has-v-alignment-' . $alignment_position_vertical;
		}

		if ( $has_animations ) {
			$layout_classnames .= ' has-animations';
		}

		if ( $custom_classes ) {
			$layout_classnames .= ' ' . $custom_classes;
		}

		if ( ! empty( $platform ) ) {
			$layout_classnames .= ' has-platform-' . $icon_name_mapping;
		}

		$this->add_layout_render_attribute( $layout_classnames );
	}

	protected function render_chat_button(): void {
		$platform = $this->settings['chat_button_platform'] ?? '';
		$display_dot = $this->settings['chat_button_show_dot'] ?? '';
		$button_size = $this->settings['style_chat_button_size'];
		$hover_animation = $this->settings['style_button_color_hover_animation'];
		$accessible_name = $this->settings['chat_aria_label'];
		$icon_text_mapping = Social_Network_Provider::get_text_mapping( $platform );
		$aria_label = sprintf(
			/* translators: 1: Accessible name, 2: Platform name */
			esc_attr__( 'Abrir %2$s: %1$s', 'pro-elements' ),
			$accessible_name,
			$icon_text_mapping
		);

		$button_classnames = 'digi-cb__chat-button digi-cb__chat-button-shadow';

		if ( ! empty( $button_size ) ) {
			$button_classnames .= ' has-size-' . $button_size;
		}

		if ( ! empty( $hover_animation ) ) {
			$button_classnames .= ' elementor-animation-' . $hover_animation;
		}

		if ( 'yes' === $display_dot ) {
			$button_classnames .= ' has-dot';
		}

		$link = [
			'platform' => $platform,
			'number' => $this->settings['chat_button_number'] ?? '',
			'username' => $this->settings['chat_button_username'] ?? '',
			'email_data' => [
				'chat_button_mail' => $this->settings['chat_button_mail'] ?? '',
				'chat_button_mail_subject' => $this->settings['chat_button_mail_subject'] ?? '',
				'chat_button_mail_body' => $this->settings['chat_button_mail_body'] ?? '',
			],
			'viber_action' => $this->settings['chat_button_viber_action'] ?? '',
			'location' => $this->settings['chat_button_waze'] ?? '',
		];

		if ( $this->is_url_link( $platform ) ) {
			$this->render_link_attributes( $link, 'formatted-cta' );

			$this->widget->add_render_attribute( 'formatted-cta', [
				'class' => $button_classnames,
				'aria-label' => $aria_label,
			] );
		} else {
			$formatted_link = $this->get_formatted_link( $link, 'chat_button' );

			$this->widget->add_render_attribute( 'formatted-cta', [
				'class' => $button_classnames,
				'href' => $formatted_link,
				'rel' => 'noopener noreferrer',
				'target' => '_blank',
				'aria-label' => $aria_label,
			] );
		}

		?>
		<div class="digi-cb__chat-button-container">
			<a <?php echo $this->widget->get_render_attribute_string( 'formatted-cta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php
					$this->render_chat_button_icon();
				?>
			</a>
		</div>
		<?php
	}

	public function render(): void {
		$this->build_layout_render_attribute();
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
				$this->render_chat_button();
			?>
		</div>
		<?php
	}
}

class Contact_Render_6 extends Contact_Render {

	protected function build_layout_render_attribute(): void {
		$layout_classnames = 'digi-cb digi-cb--p' . $this->presentation;
		$custom_classes = $this->settings['advanced_custom_css_classes'] ?? '';
		$alignment_position_horizontal = $this->settings['advanced_horizontal_position'];
		$alignment_position_vertical = $this->settings['advanced_vertical_position'];

		if ( ! empty( $alignment_position_horizontal ) ) {
			$layout_classnames .= ' has-h-alignment-' . $alignment_position_horizontal;
		}

		if ( ! empty( $alignment_position_vertical ) ) {
			$layout_classnames .= ' has-v-alignment-' . $alignment_position_vertical;
		}

		if ( $custom_classes ) {
			$layout_classnames .= ' ' . $custom_classes;
		}

		$this->add_layout_render_attribute( $layout_classnames );
	}

	protected function render_contact_links(): void {
		$contact_icons = $this->settings['contact_repeater'] ?? [];
		$icons_size = $this->settings['style_contact_button_size'] ?? 'small';
		$hover_animation = $this->settings['style_contact_button_hover_animation'];
		$border_radius = $this->settings['style_contact_button_bar_corners'];
		$accessible_name = $this->settings['contact_aria_label'];

		$links_classnames = 'digi-cb__contact-links';

		if ( ! empty( $border_radius ) ) {
			$links_classnames .= ' has-corners-' . $border_radius;
		}

		$this->widget->add_render_attribute( 'contact-links', [
			'class' => $links_classnames,
		] );

		?>
			<div <?php echo $this->widget->get_render_attribute_string( 'contact-links' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php
				foreach ( $contact_icons as $key => $icon ) {
					$icon_text_mapping = Social_Network_Provider::get_text_mapping( $icon['contact_icon_platform'] );
					$aria_label = sprintf(
						/* translators: 1: Accessible name, 2: Platform name */
						esc_attr__( 'Abrir %2$s: %1$s', 'pro-elements' ),
						$accessible_name,
						$icon_text_mapping
					);

					$link = [
						'platform' => $icon['contact_icon_platform'],
						'number' => $icon['contact_icon_number'] ?? '',
						'username' => $icon['contact_icon_username'] ?? '',
						'email_data' => [
							'contact_icon_mail' => $icon['contact_icon_mail'] ?? '',
							'contact_icon_mail_subject' => $icon['contact_icon_mail_subject'] ?? '',
							'contact_icon_mail_body' => $icon['contact_icon_mail_body'] ?? '',
						],
						'viber_action' => $icon['contact_icon_viber_action'] ?? '',
						'location' => $icon['contact_icon_waze'] ?? '',
					];

					$icon_classnames = 'digi-cb__contact-icon-link has-size-' . $icons_size;

					if ( ! empty( $hover_animation ) ) {
						$icon_classnames .= ' elementor-animation-' . $hover_animation;
					}

					if ( $this->is_url_link( $icon['contact_icon_platform'] ) ) {
						$this->render_link_attributes( $link, 'icon-link-' . $key );

						$this->widget->add_render_attribute( 'icon-link-' . $key, [
							'class' => $icon_classnames,
							'aria-label' => $aria_label,
						] );
					} else {
						$formatted_link = $this->get_formatted_link( $link, 'contact_icon' );

						$this->widget->add_render_attribute( 'icon-link-' . $key, [
							'class' => $icon_classnames,
							'href' => $formatted_link,
							'rel' => 'noopener noreferrer',
							'target' => '_blank',
							'aria-label' => $aria_label,
						] );
					}

					?>

					<a <?php echo $this->widget->get_render_attribute_string( 'icon-link-' . $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php
							$mapping = Social_Network_Provider::get_icon_mapping( $icon['contact_icon_platform'] );
							$icon_lib = explode( ' ', $mapping )[0];
							$library = 'fab' === $icon_lib ? 'fa-brands' : 'fa-solid';
							Icons_Manager::render_icon(
								[
									'library' => $library,
									'value' => $mapping,
								],
								[ 'aria-hidden' => 'true' ]
							);
						?>
					</a>
				<?php } ?>
			</div>
		<?php
	}

	public function render(): void {
		$this->build_layout_render_attribute();
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
				$this->render_contact_links();
			?>
		</div>
		<?php
	}
}

class Contact_Render_7 extends Contact_Render {

	protected function build_layout_render_attribute(): void {
		$layout_classnames = 'digi-cb digi-cb--p' . $this->presentation;
		$platform = $this->settings['chat_button_platform'] ?? '';
		$alignment_position_horizontal = $this->settings['advanced_horizontal_position'];
		$alignment_position_vertical = $this->settings['advanced_vertical_position'];
		$custom_classes = $this->settings['advanced_custom_css_classes'] ?? '';
		$icon_name_mapping = Social_Network_Provider::get_name_mapping( $platform );
		$mobile_full_width = $this->settings['advanced_mobile_full_width'];

		if ( ! empty( $alignment_position_horizontal ) ) {
			$layout_classnames .= ' has-h-alignment-' . $alignment_position_horizontal;
		}

		if ( ! empty( $alignment_position_vertical ) ) {
			$layout_classnames .= ' has-v-alignment-' . $alignment_position_vertical;
		}

		if ( ! empty( $mobile_full_width ) ) {
			$layout_classnames .= ' has-mobile-full-width';
		}

		if ( $custom_classes ) {
			$layout_classnames .= ' ' . $custom_classes;
		}

		if ( ! empty( $platform ) ) {
			$layout_classnames .= ' has-platform-' . $icon_name_mapping;
		}

		$this->add_layout_render_attribute( $layout_classnames );
	}

	protected function get_platform_text(): string {
		$platform = $this->settings['chat_button_platform'];

		switch ( $platform ) {
			case Social_Network_Provider::EMAIL:
				$platform_text = $this->settings['chat_button_mail'];
				break;
			case Social_Network_Provider::SMS:
				$platform_text = $this->settings['chat_button_number'];
				break;
			case Social_Network_Provider::MESSENGER:
				$platform_text = $this->settings['chat_button_username'];
				break;
			case Social_Network_Provider::WHATSAPP:
				$platform_text = $this->settings['chat_button_number'];
				break;
			case Social_Network_Provider::VIBER:
				$platform_text = $this->settings['chat_button_number'];
				break;
			case Social_Network_Provider::SKYPE:
				$platform_text = $this->settings['chat_button_username'];
				break;
			case Social_Network_Provider::WAZE:
				$platform_text = $this->settings['chat_button_waze']['url'];
				break;
			case Social_Network_Provider::URL:
				$platform_text = $this->settings['chat_button_url']['url'];
				break;
			case Social_Network_Provider::TELEPHONE:
				$platform_text = $this->settings['chat_button_number'];
				break;
			default:
				break;
		}

		return $platform_text;
	}

	protected function get_chat_button_text(): string {
		if ( 'cta' == $this->settings['chat_button_display_text_select'] ) {
			$button_text = $this->settings['chat_button_display_text'];
		} else {
			$button_text = $this->get_platform_text();
		}

		return $button_text;
	}

	protected function get_aria_label(): string {
		$platform = $this->settings['chat_button_platform'] ?? '';
		$icon_text_mapping = Social_Network_Provider::get_text_mapping( $platform );
		$accessible_name = $this->settings['chat_aria_label'];

		if ( 'cta' == $this->settings['chat_button_display_text_select'] ) {
			return $this->settings['chat_button_display_text'];
		} else {
			return sprintf(
				/* translators: 1: Accessible name, 2: Platform name */
				esc_attr__( 'Abrir %2$s: %1$s', 'pro-elements' ),
				$accessible_name,
				$icon_text_mapping
			);
		}
	}

	protected function render_chat_button(): void {
		$platform = $this->settings['chat_button_platform'] ?? '';
		$hover_animation = $this->settings['style_button_color_hover_animation'];
		$chat_button_text = $this->get_chat_button_text();
		$aria_label = $this->get_aria_label();

		$button_classnames = 'digi-cb__chat-button';

		if ( ! empty( $hover_animation ) ) {
			$button_classnames .= ' elementor-animation-' . $hover_animation;
		}

		$link = [
			'platform' => $platform,
			'number' => $this->settings['chat_button_number'] ?? '',
			'username' => $this->settings['chat_button_username'] ?? '',
			'email_data' => [
				'chat_button_mail' => $this->settings['chat_button_mail'] ?? '',
				'chat_button_mail_subject' => $this->settings['chat_button_mail_subject'] ?? '',
				'chat_button_mail_body' => $this->settings['chat_button_mail_body'] ?? '',
			],
			'viber_action' => $this->settings['chat_button_viber_action'] ?? '',
			'location' => $this->settings['chat_button_waze'] ?? '',
			'url' => $this->settings['chat_button_url'] ?? '',
		];

		if ( $this->is_url_link( $platform ) ) {
			$this->render_link_attributes( $link, 'formatted-cta' );

			$this->widget->add_render_attribute( 'formatted-cta', [
				'class' => $button_classnames,
				'aria-label' => $aria_label,
			] );
		} else {
			$formatted_link = $this->get_formatted_link( $link, 'chat_button' );

			$this->widget->add_render_attribute( 'formatted-cta', [
				'class' => $button_classnames,
				'href' => $formatted_link,
				'rel' => 'noopener noreferrer',
				'target' => '_blank',
				'aria-label' => $aria_label,
			] );
		}

		?>
		<div class="digi-cb__chat-button-container">
			<a <?php echo $this->widget->get_render_attribute_string( 'formatted-cta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php if ( ! empty( $chat_button_text ) ) : ?>
					<span class="digi-cb__chat-button-text">
						<?php echo esc_html( $chat_button_text ); ?>
					</span>
				<?php endif; ?>
				<?php
					$this->render_chat_button_icon();
				?>
			</a>
		</div>
		<?php
	}

	public function render(): void {
		$this->build_layout_render_attribute();
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
				$this->render_chat_button();
			?>
		</div>
		<?php
	}
}

class Contact_Render_8 extends Contact_Render {

	protected function render_chat_button_icon(): void {
		$custom_icon = $this->settings['chat_button_icon'] ?? '';

		Icons_Manager::render_icon( $custom_icon );
	}

	protected function render_close_button(): void {
		$button_size = $this->settings['style_chat_button_size'];
		$button_classnames = 'digi-cb__close-button digi-cb__chat-button-shadow';
		$accessible_name = $this->settings['chat_aria_label'];

		if ( ! empty( $button_size ) ) {
			$button_classnames .= ' has-size-' . $button_size;
		}

		$this->widget->add_render_attribute( 'close-button', [
			'class' => $button_classnames,
			'aria-controls' => $this->panel_id(),
			'aria-label' => sprintf(
				/* translators: 1: Accessible name */
				esc_attr__( 'Cerrar %1$s', 'pro-elements' ),
				$accessible_name,
			),
			'type' => 'button',
			'aria-expanded' => 'false',
		] );

		?>
			<button <?php echo $this->widget->get_render_attribute_string( 'close-button' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php echo self::close_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</button>
		<?php
	}

	protected function render_chat_button(): void {
		$platform = $this->settings['chat_button_platform'] ?? '';
		$button_size = $this->settings['style_chat_button_size'];
		$hover_animation = $this->settings['style_button_color_hover_animation'];
		$entrance_animation = $this->settings['style_chat_button_animation'];
		$entrance_animation_duration = $this->settings['style_chat_button_animation_duration'];
		$entrance_animation_delay = $this->settings['style_chat_button_animation_delay'];
		$accessible_name = $this->settings['chat_aria_label'];

		$button_classnames = 'digi-cb__chat-button digi-cb__chat-button-shadow';

		if ( ! empty( $button_size ) ) {
			$button_classnames .= ' has-size-' . $button_size;
		}

		if ( ! empty( $hover_animation ) ) {
			$button_classnames .= ' elementor-animation-' . $hover_animation;
		}

		if ( ! empty( $entrance_animation ) && 'none' != $entrance_animation ) {
			$button_classnames .= ' has-entrance-animation';
		}

		if ( ! empty( $entrance_animation_delay ) ) {
			$button_classnames .= ' has-entrance-animation-delay';
		}

		if ( ! empty( $entrance_animation_duration ) ) {
			$button_classnames .= ' has-entrance-animation-duration-' . $entrance_animation_duration;
		}

		$this->widget->add_render_attribute( 'button-', [
			'class' => $button_classnames,
			'aria-controls' => $this->panel_id(),
			'aria-label' => sprintf(
				/* translators: 1: Accessible name */
				esc_attr__( 'Abrir %1$s', 'pro-elements' ),
				$accessible_name,
			),
			'type' => 'button',
			'aria-expanded' => 'true',
		] );

		?>
		<div class="digi-cb__chat-button-container">
			<button <?php echo $this->widget->get_render_attribute_string( 'button-' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php
					$this->render_chat_button_icon();
				?>
			</button>
			<?php
				$this->render_close_button();
			?>
		</div>
		<?php
	}

	protected function build_layout_render_attribute(): void {
		$layout_classnames = 'digi-cb digi-cb--p' . $this->presentation;
		$has_animations = ! empty( $this->settings['style_chat_box_exit_animation'] ) || ! empty( $this->settings['style_chat_box_entrance_animation'] );
		$border_radius = $this->settings['style_chat_box_corners'];
		$alignment_position_horizontal = $this->settings['advanced_horizontal_position'];
		$alignment_position_vertical = $this->settings['advanced_vertical_position'];

		$custom_classes = $this->settings['advanced_custom_css_classes'] ?? '';

		if ( ! empty( $border_radius ) ) {
			$layout_classnames .= ' has-corners-' . $border_radius;
		}

		if ( ! empty( $alignment_position_horizontal ) ) {
			$layout_classnames .= ' has-h-alignment-' . $alignment_position_horizontal;
		}

		if ( ! empty( $alignment_position_vertical ) ) {
			$layout_classnames .= ' has-v-alignment-' . $alignment_position_vertical;
		}

		if ( $has_animations ) {
			$layout_classnames .= ' has-animations';
		}

		if ( $custom_classes ) {
			$layout_classnames .= ' ' . $custom_classes;
		}

		$this->add_layout_render_attribute( $layout_classnames );
	}

	protected function render_top_bar(): void {
		$top_bar_title = $this->settings['top_bar_title'] ?? '';
		$top_bar_subtitle = $this->settings['top_bar_subtitle'] ?? '';

		$has_top_bar_title = ! empty( $top_bar_title );
		$has_top_bar_subtitle = ! empty( $top_bar_subtitle );
		?>
		<div class="digi-cb__top-bar">
			<div class="digi-cb__top-bar-details">
				<?php if ( $has_top_bar_title ) { ?>
					<p class="digi-cb__top-bar-title"><?php echo esc_html( $top_bar_title ); ?></p>
				<?php } ?>
				<?php if ( $has_top_bar_subtitle ) { ?>
					<p class="digi-cb__top-bar-subtitle"><?php echo esc_html( $top_bar_subtitle ); ?></p>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	protected function render_contact_section(): void {
		$contact_icons = $this->settings['contact_repeater'] ?? [];
		$button_size = $this->settings['style_resource_links_button_size'];
		$hover_animation = $this->settings['style_resource_links_hover_animation'];

		?>
		<div class="digi-cb__links-container">
			<div class="digi-cb__contact-links">
				<?php
				foreach ( $contact_icons as $key => $icon ) {
					$link = [
						'platform' => $icon['contact_icon_platform'],
						'number' => $icon['contact_icon_number'] ?? '',
						'username' => $icon['contact_icon_username'] ?? '',
						'email_data' => [
							'contact_icon_mail' => $icon['contact_icon_mail'] ?? '',
							'contact_icon_mail_subject' => $icon['contact_icon_mail_subject'] ?? '',
							'contact_icon_mail_body' => $icon['contact_icon_mail_body'] ?? '',
						],
						'viber_action' => $icon['contact_icon_viber_action'] ?? '',
						'url' => $icon['contact_icon_url'] ?? '',
						'location' => $icon['contact_icon_waze'] ?? '',
					];

					$icon_title = $icon['contact_title'] ?? '';
					$icon_description = $icon['contact_description'] ?? '';
					$icon_name_mapping = Social_Network_Provider::get_name_mapping( $icon['contact_icon_platform'] );
					$colors_type = $this->settings['style_resource_links_color_select'];

					$icon_classnames = 'digi-cb__contact-icon-link';

					if ( ! empty( $button_size ) ) {
						$icon_classnames .= ' has-size-' . $button_size;
					}

					if ( ! empty( $icon_name_mapping ) ) {
						$icon_classnames .= ' has-platform-' . $icon_name_mapping;
					}

					if ( ! empty( $colors_type ) ) {
						$icon_classnames .= ' has-colors-type-' . $colors_type;
					}

					if ( ! empty( $hover_animation ) ) {
						$icon_classnames .= ' elementor-animation-' . $hover_animation;
					}

					if ( $this->is_url_link( $icon['contact_icon_platform'] ) ) {
						$this->render_link_attributes( $link, 'icon-link-' . $key );

						$this->widget->add_render_attribute( 'icon-link-' . $key, [
							'class' => $icon_classnames,
						] );
					} else {
						$formatted_link = $this->get_formatted_link( $link, 'contact_icon' );

						$this->widget->add_render_attribute( 'icon-link-' . $key, [
							'aria-label' => esc_attr( Social_Network_Provider::get_text_mapping( $icon['contact_icon_platform'] ) ),
							'class' => $icon_classnames,
							'href' => $formatted_link,
							'rel' => 'noopener noreferrer',
							'target' => '_blank',
						] );
					}

					?>

					<a <?php echo $this->widget->get_render_attribute_string( 'icon-link-' . $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<span class="digi-cb__contact-icon-container">
							<?php
								$mapping = Social_Network_Provider::get_icon_mapping( $icon['contact_icon_platform'] );
								$icon_lib = explode( ' ', $mapping )[0];
								$library = 'fab' === $icon_lib ? 'fa-brands' : 'fa-solid';
								Icons_Manager::render_icon(
									[
										'library' => $library,
										'value' => $mapping,
									],
									[ 'aria-hidden' => 'true' ]
								);
							?>
						</span>
						<?php if ( ! empty( $icon_title ) ) { ?>
							<span class="digi-cb__contact-title"><?php echo esc_html( $icon_title ); ?></span>
						<?php } ?>
						<?php if ( ! empty( $icon_description ) ) { ?>
							<span class="digi-cb__contact-description"><?php echo esc_html( $icon_description ); ?></span>
						<?php } ?>
					</a>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	public function render(): void {
		$this->build_layout_render_attribute();
		$this->add_content_wrapper_render_attribute();

		$content_classnames = 'digi-cb__content';

		$this->widget->add_render_attribute( 'content', [
			'class' => $content_classnames,
		] );
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div <?php echo $this->widget->get_render_attribute_string( 'content-wrapper' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div <?php echo $this->widget->get_render_attribute_string( 'content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php
					$this->render_top_bar();
					$this->render_contact_section();
					?>
				</div>
			</div>
			<?php
				$this->render_chat_button();
			?>
		</div>
		<?php
	}
}

class Contact_Render_9 extends Contact_Render {

	protected function render_chat_button(): void {
		$platform = $this->settings['chat_button_platform'] ?? '';
		$button_size = $this->settings['style_chat_button_size'];
		$chat_button_text = $this->settings['chat_button_display_text'];

		$button_classnames = 'digi-cb__chat-button digi-cb__chat-button-drop-shadow';

		if ( ! empty( $button_size ) ) {
			$button_classnames .= ' has-size-' . $button_size;
		}

		$link = [
			'platform' => $platform,
			'number' => $this->settings['chat_button_number'] ?? '',
			'username' => $this->settings['chat_button_username'] ?? '',
			'email_data' => [
				'chat_button_mail' => $this->settings['chat_button_mail'] ?? '',
				'chat_button_mail_subject' => $this->settings['chat_button_mail_subject'] ?? '',
				'chat_button_mail_body' => $this->settings['chat_button_mail_body'] ?? '',
			],
			'viber_action' => $this->settings['chat_button_viber_action'] ?? '',
			'location' => $this->settings['chat_button_waze'] ?? '',
			'url' => $this->settings['chat_button_url'] ?? '',
		];

		if ( $this->is_url_link( $platform ) ) {
			$this->render_link_attributes( $link, 'formatted-cta' );

			$this->widget->add_render_attribute( 'formatted-cta', [
				'class' => $button_classnames,
			] );
		} else {
			$formatted_link = $this->get_formatted_link( $link, 'chat_button' );

			$this->widget->add_render_attribute( 'formatted-cta', [
				'class' => $button_classnames,
				'href' => $formatted_link,
				'rel' => 'noopener noreferrer',
				'target' => '_blank',
			] );
		}

		?>
		<div class="digi-cb__chat-button-container">
			<a <?php echo $this->widget->get_render_attribute_string( 'formatted-cta' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<span class="digi-cb__chat-button-icon-container"><?php
					$this->render_chat_button_icon();
				?></span>
				<span class="digi-cb__chat-button-text"><?php echo esc_html( $chat_button_text ); ?></span>
			</a>
		</div>
		<?php
	}

	protected function build_layout_render_attribute(): void {
		$layout_classnames = 'digi-cb digi-cb--p' . $this->presentation;
		$platform = $this->settings['chat_button_platform'] ?? '';
		$alignment_position_horizontal = $this->settings['advanced_horizontal_position'];
		$alignment_position_vertical = $this->settings['advanced_vertical_position'];
		$has_animations = ! empty( $this->settings['style_chat_box_exit_animation'] ) || ! empty( $this->settings['style_chat_box_entrance_animation'] );
		$custom_classes = $this->settings['advanced_custom_css_classes'] ?? '';
		$icon_name_mapping = Social_Network_Provider::get_name_mapping( $platform );

		if ( ! empty( $alignment_position_horizontal ) ) {
			$layout_classnames .= ' has-h-alignment-' . $alignment_position_horizontal;
		}

		if ( ! empty( $alignment_position_vertical ) ) {
			$layout_classnames .= ' has-v-alignment-' . $alignment_position_vertical;
		}

		if ( $has_animations ) {
			$layout_classnames .= ' has-animations';
		}

		if ( $custom_classes ) {
			$layout_classnames .= ' ' . $custom_classes;
		}

		if ( ! empty( $platform ) ) {
			$layout_classnames .= ' has-platform-' . $icon_name_mapping;
		}

		$this->add_layout_render_attribute( $layout_classnames );
	}

	public function render(): void {
		$this->build_layout_render_attribute();

		$content_classnames = 'digi-cb__content';

		$this->widget->add_render_attribute( 'content', [
			'class' => $content_classnames,
		] );
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
				$this->render_chat_button();
			?>
		</div>
		<?php
	}
}

class Contact_Render_10 extends Contact_Render {

	protected function build_layout_render_attribute(): void {
		$layout_classnames = 'digi-cb digi-cb--p' . $this->presentation;
		$alignment_position_horizontal = $this->settings['advanced_horizontal_position'];
		$alignment_position_vertical = $this->settings['advanced_vertical_position'];
		$custom_classes = $this->settings['advanced_custom_css_classes'] ?? '';

		if ( ! empty( $alignment_position_horizontal ) ) {
			$layout_classnames .= ' has-h-alignment-' . $alignment_position_horizontal;
		}

		if ( ! empty( $alignment_position_vertical ) ) {
			$layout_classnames .= ' has-v-alignment-' . $alignment_position_vertical;
		}

		if ( $custom_classes ) {
			$layout_classnames .= ' ' . $custom_classes;
		}

		$this->add_layout_render_attribute( $layout_classnames );
	}

	protected function render_contact_section(): void {
		$contact_icons = $this->settings['contact_repeater'] ?? [];
		$button_size = $this->settings['style_contact_button_size'];
		$button_corners = $this->settings['style_contact_corners'];

		?>
		<div class="digi-cb__links-container">
			<div class="digi-cb__contact-links">
				<?php
				foreach ( $contact_icons as $key => $icon ) {
					$link = [
						'platform' => $icon['contact_icon_platform'],
						'number' => $icon['contact_icon_number'] ?? '',
						'username' => $icon['contact_icon_username'] ?? '',
						'email_data' => [
							'contact_icon_mail' => $icon['contact_icon_mail'] ?? '',
							'contact_icon_mail_subject' => $icon['contact_icon_mail_subject'] ?? '',
							'contact_icon_mail_body' => $icon['contact_icon_mail_body'] ?? '',
						],
						'viber_action' => $icon['contact_icon_viber_action'] ?? '',
						'url' => $icon['contact_icon_url'] ?? '',
						'location' => $icon['contact_icon_waze'] ?? '',
					];

					$icon_title = $icon['contact_title'] ?? '';

					$icon_classnames = 'digi-cb__contact-icon-link digi-cb__contact-box-shadow';

					if ( ! empty( $button_size ) ) {
						$icon_classnames .= ' has-size-' . $button_size;
					}

					if ( ! empty( $button_corners ) ) {
						$icon_classnames .= ' has-corners-' . $button_corners;
					}

					if ( $this->is_url_link( $icon['contact_icon_platform'] ) ) {
						$this->render_link_attributes( $link, 'icon-link-' . $key );

						$this->widget->add_render_attribute( 'icon-link-' . $key, [
							'class' => $icon_classnames,
						] );
					} else {
						$formatted_link = $this->get_formatted_link( $link, 'contact_icon' );

						$this->widget->add_render_attribute( 'icon-link-' . $key, [
							'aria-label' => esc_attr( Social_Network_Provider::get_text_mapping( $icon['contact_icon_platform'] ) ),
							'class' => $icon_classnames,
							'href' => $formatted_link,
							'rel' => 'noopener noreferrer',
							'target' => '_blank',
						] );
					}

					?>

					<a <?php echo $this->widget->get_render_attribute_string( 'icon-link-' . $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php if ( ! empty( $icon_title ) ) { ?>
							<span class="digi-cb__contact-title"><?php echo esc_html( $icon_title ); ?></span>
						<?php } ?>
						<span class="digi-cb__contact-icon-container">
							<?php
								$mapping = Social_Network_Provider::get_icon_mapping( $icon['contact_icon_platform'] );
								$icon_lib = explode( ' ', $mapping )[0];
								$library = 'fab' === $icon_lib ? 'fa-brands' : 'fa-solid';
								Icons_Manager::render_icon(
									[
										'library' => $library,
										'value' => $mapping,
									],
									[ 'aria-hidden' => 'true' ]
								);
							?>
						</span>
					</a>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	public function render(): void {
		$this->build_layout_render_attribute();

		$this->widget->add_render_attribute( 'content', [
			'class' => 'digi-cb__content',
		] );
		?>
		<div <?php echo $this->widget->get_render_attribute_string( 'layout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php $this->render_contact_section(); ?>
		</div>
		<?php
	}
}
