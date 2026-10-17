<?php
/** Element Pack Pro 9.9.1 `bdt-profile-card` → `author-box`. */
defined( 'ABSPATH' ) || exit;

$placeholder_url = class_exists( '\Elementor\Utils' ) ? \Elementor\Utils::get_placeholder_image_src() : '';

return array(
	'target'    => 'author-box',
	// Clases de Element Pack más usadas en sus selectores: .bdt-profile-card, .bdt-profile-card-share-link, .bdt-profile-card-button, .bdt-button, .bdt-profile-card-inner, .bdt-profile-card-name-info, .bdt-profile-card-pro, .bdt-profile-card-image, .bdt-profile-card-header, .bdt-profile-card-settings, .bdt-name, .bdt-profile-card-bio, .bdt-profile-card-status, .bdt-dropdown
	'classes'   => array(
		'.bdt-profile-card .bdt-profile-card-button .bdt-button' => '.elementor-author-box__button',
		'.bdt-profile-card .bdt-profile-card-image img' => '.elementor-author-box__avatar img',
		'.bdt-profile-card .bdt-profile-card-bio' => '.elementor-author-box__bio',
		'.bdt-profile-card .bdt-profile-card-name' => '.elementor-author-box__name',
		'.bdt-profile-card'                       => '.elementor-author-box',
	),
	'defaults'  => array(
		'profile' => 'custom',
		'blog_user_id' => '1',
		'alignment' => 'center',
		'profile_badge_text' => 'Pro',
		'show_badge' => 'yes',
		'show_user_menu' => 'yes',
		'show_image' => 'yes',
		'show_name' => 'yes',
		'show_username' => 'yes',
		'show_text' => 'yes',
		'show_status' => 'yes',
		'show_button' => 'yes',
		'show_social_icon' => 'yes',
		'profile_image' => array(
			'url' => $placeholder_url,
		),
		'profile_name' => 'Adam Smith',
		'profile_username' => '@adamsmith',
		'profile_content' => 'Hello, My name is Adam Smith ! I am Web Developer.',
		'profile_posts' => 'Posts',
		'profile_posts_number' => '213',
		'profile_followers' => 'Followers',
		'profile_followers_number' => '423',
		'profile_following' => 'Following',
		'profile_following_number' => '213',
		'profile_button_text' => 'Follow',
		'follow_link' => array(
			'url' => '#',
		),
		'blog_posts' => 'Posts',
		'blog_post_comments' => 'Comments',
		'blog_button_text' => 'Follow',
		'dropdown_position' => 'bottom-right',
		'dropdown_mode' => 'hover',
		'profile_card_image_width' => array(
			'unit' => 'px',
		),
		'profile_card_image_width_tablet' => array(
			'unit' => 'px',
		),
		'profile_card_image_width_mobile' => array(
			'unit' => 'px',
		),
		'profile_card_name_color' => '',
		'profile_card_username_color' => '',
		'profile_card_text_color' => '',
		'profile_card_stat_color' => '',
		'profile_card_label_color' => '',
		'social_icon_tooltip' => 'yes',
	),
	'repeaters' => array(
		'social_link_list' => array(
			'defaults'     => array(
				'social_link_title' => 'Facebook',
				'social_icon' => array(
					'value' => 'fab fa-facebook-f',
					'library' => 'fa-brands',
				),
			),
			'default_rows' => array( array(
					'social_icon_link' => array(
						'url' => '',
					),
					'social_icon' => array(
						'value' => 'fab fa-facebook-f',
						'library' => 'fa-brands',
					),
					'social_link_title' => 'Facebook',
				), array(
					'social_icon_link' => array(
						'url' => '',
					),
					'social_icon' => array(
						'value' => 'fab fa-x-twitter',
						'library' => 'fa-brands',
					),
					'social_link_title' => 'X',
				), array(
					'social_icon_link' => array(
						'url' => '',
					),
					'social_icon' => array(
						'value' => 'fab fa-instagram',
						'library' => 'fa-brands',
					),
					'social_link_title' => 'Instagram',
				) ),
		),
		'custom_navs' => array(
			'defaults'     => array(
				'custom_nav_title' => 'Title',
				'custom_nav_link' => array(
					'url' => '#',
				),
			),
			'default_rows' => array( array(
					'custom_nav_title' => 'Billing',
					'icon' => array(
						'value' => 'fas fa-dollar-sign',
						'library' => 'fa-solid',
					),
				), array(
					'custom_nav_title' => 'Settings',
					'icon' => array(
						'value' => 'fas fa-cog',
						'library' => 'fa-solid',
					),
				), array(
					'custom_nav_title' => 'Support',
					'icon' => array(
						'value' => 'fas fa-life-ring',
						'library' => 'fa-solid',
					),
				) ),
		),
	),
	// Distintivo, nombre de usuario, cifras, redes y menú pasan al perfil ampliado propio de la Caja de autor;
	// el perfil «blog» muestra al usuario elegido con sus datos al día.
	'filter'    => static function ( array $out ) use ( $placeholder_url ) {
		$custom   = 'custom' === ( $out['profile'] ?? 'custom' );
		$text     = static function ( $value ) {
			return trim( wp_strip_all_tags( (string) $value ) );
		};
		$out['source'] = 'custom';
		$out['digitalisimo_author_user'] = $custom ? '' : (string) absint( $out['blog_user_id'] ?? 0 );
		if ( $custom ) {
			$out['author_name']   = (string) ( $out['profile_name'] ?? '' );
			$out['author_bio']    = (string) ( $out['profile_content'] ?? '' );
			$out['author_avatar'] = is_array( $out['profile_image'] ?? null ) && ! empty( $out['profile_image']['url'] ) ? $out['profile_image'] : array( 'url' => $placeholder_url );
			$out['link_text']     = (string) ( $out['profile_button_text'] ?? '' );
			$out['posts_url']     = $out['follow_link'] ?? array();
		} else {
			$out['link_text'] = (string) ( $out['blog_button_text'] ?? '' );
			$out['posts_url'] = array( 'url' => '' );
		}
		$out['show_avatar']    = 'yes' === ( $out['show_image'] ?? 'yes' ) ? 'yes' : '';
		$out['show_name']      = 'yes' === ( $out['show_name'] ?? 'yes' ) ? 'yes' : '';
		$out['show_biography'] = 'yes' === ( $out['show_text'] ?? 'yes' ) ? 'yes' : '';
		$out['show_link']      = 'yes' === ( $out['show_button'] ?? 'yes' ) ? 'yes' : 'no';
		$out['digitalisimo_author_show_username'] = 'yes' === ( $out['show_username'] ?? 'yes' ) ? 'yes' : '';
		$out['digitalisimo_author_username']      = $custom ? $text( $out['profile_username'] ?? '' ) : '';
		$out['digitalisimo_author_badge']         = 'yes' === ( $out['show_badge'] ?? 'yes' ) ? $text( $out['profile_badge_text'] ?? '' ) : '';
		$out['digitalisimo_author_stats']         = array();
		if ( 'yes' === ( $out['show_status'] ?? 'yes' ) ) {
			$stats = $custom
				? array( array( 'profile_posts_number', 'profile_posts' ), array( 'profile_followers_number', 'profile_followers' ), array( 'profile_following_number', 'profile_following' ) )
				: array( array( 'posts', 'blog_posts' ), array( 'comments', 'blog_post_comments' ) );
			foreach ( $stats as $stat ) {
				$out['digitalisimo_author_stats'][] = $custom
					? array( 'stat_source' => 'custom', 'stat_value' => $text( $out[ $stat[0] ] ?? '' ), 'stat_label' => $text( $out[ $stat[1] ] ?? '' ) )
					: array( 'stat_source' => $stat[0], 'stat_value' => '', 'stat_label' => $text( $out[ $stat[1] ] ?? '' ) );
			}
		}
		$out['digitalisimo_author_social'] = array();
		if ( 'yes' === ( $out['show_social_icon'] ?? 'yes' ) ) {
			foreach ( is_array( $out['social_link_list'] ?? null ) ? $out['social_link_list'] : array() as $row ) {
				$link = is_array( $row['social_icon_link'] ?? null ) && ! empty( $row['social_icon_link']['url'] ) ? $row['social_icon_link'] : array( 'url' => (string) ( $row['social_link'] ?? '' ), 'is_external' => 'on' );
				$icon = is_array( $row['social_icon'] ?? null ) && ! empty( $row['social_icon']['value'] ) ? $row['social_icon'] : array( 'value' => '', 'library' => '' );
				if ( '' !== (string) ( $link['url'] ?? '' ) ) {
					$out['digitalisimo_author_social'][] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'social_icon' => $icon, 'social_label' => $text( $row['social_link_title'] ?? '' ), 'social_link' => $link );
				}
			}
		}
		$out['digitalisimo_author_menu'] = array();
		if ( 'yes' === ( $out['show_user_menu'] ?? 'yes' ) ) {
			foreach ( is_array( $out['custom_navs'] ?? null ) ? $out['custom_navs'] : array() as $row ) {
				$out['digitalisimo_author_menu'][] = array( '_id' => (string) ( $row['_id'] ?? '' ), 'menu_label' => $text( $row['custom_nav_title'] ?? '' ), 'menu_link' => is_array( $row['custom_nav_link'] ?? null ) ? $row['custom_nav_link'] : array( 'url' => '' ) );
			}
		}
		foreach ( array_keys( $out ) as $key ) {
			if ( preg_match( '/^(profile|blog_|follow_link|show_badge|show_user_menu|show_image|show_username|show_text|show_status|show_button|show_social_icon|social_link_list|custom_navs|dropdown_)/', $key ) ) {
				unset( $out[ $key ] );
			}
		}
		return $out;
	},
);
