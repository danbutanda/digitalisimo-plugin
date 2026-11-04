<?php
/**
 * Post Info de PRO → Información de la entrada (`digitalisimo-post-info`,
 * modules/digitalisimo-theme-elements/widgets/class-post-info.php).
 *
 * El widget propio repite los controles del de PRO (también los campos del repetidor `icon_list`) con el mismo nombre,
 * los mismos valores y las mismas condiciones, así que el mapa es `keep` y `groups`. `icon` es el icono de Font Awesome 4
 * que guardaban las versiones antiguas en cada fila (`fa4compatibility`), que el widget propio lee igual.
 *
 * Textos de los comentarios: PRO los pinta, si la fila no tiene un formato propio, con sus textos por defecto en el idioma
 * del sitio («No Comments», «One Comment», «%s Comments»), que no se guardan; el widget propio tiene los suyos en español.
 * Para que lo convertido diga lo mismo, cada fila de comentarios llega con «Formato personalizado» y los textos de PRO en
 * los que el sitio dejó vacíos (también en las filas por defecto, que por eso se completan). Diferencias sin clave
 * propia (sin microdatos, `<time>` con `datetime`, texto alternativo del avatar en español, foco visible): cabecera del
 * widget. Formato: cabecera de `class-translator.php`.
 */
defined( 'ABSPATH' ) || exit;

$digi_pi_rows = array(
	array( 'type' => 'author', 'selected_icon' => array( 'value' => 'far fa-user-circle', 'library' => 'fa-regular' ) ),
	array( 'type' => 'date', 'selected_icon' => array( 'value' => 'fas fa-calendar', 'library' => 'fa-solid' ) ),
	array( 'type' => 'time', 'selected_icon' => array( 'value' => 'far fa-clock', 'library' => 'fa-regular' ) ),
	array( 'type' => 'comments', 'selected_icon' => array( 'value' => 'far fa-comment-dots', 'library' => 'fa-regular' ) ),
);

$digi_pi_classes = array(
	'.elementor-widget-post-info'           => '.elementor-widget-digitalisimo-post-info',
	'.elementor-icon-list-items'            => '.digi-post-info',
	'.elementor-post-info'                  => '.digi-post-info',
	'.elementor-inline-items'               => '.digi-post-info--inline',
	'.elementor-icon-list-item'             => '.digi-post-info__item',
	'.elementor-inline-item'                => '.digi-post-info__item',
	'.elementor-icon-list-icon'             => '.digi-post-info__icon',
	'.elementor-avatar'                     => '.digi-post-info__avatar',
	'.elementor-icon-list-text'             => '.digi-post-info__text',
	'.elementor-post-info__item'            => '.digi-post-info__text',
	'.elementor-post-info__item-prefix'     => '.digi-post-info__prefix',
	'.elementor-post-info__terms-list'      => '.digi-post-info__terms',
	'.elementor-post-info__terms-list-item' => '.digi-post-info__term',
	'.elementor-align-left'                 => '.digi-post-info--align-left',
	'.elementor-align-center'               => '.digi-post-info--align-center',
	'.elementor-align-right'                => '.digi-post-info--align-right',
);
foreach ( array( 'author', 'date', 'time', 'comments', 'terms', 'custom' ) as $digi_pi_type ) {
	$digi_pi_classes[ '.elementor-post-info__item--type-' . $digi_pi_type ] = '.digi-post-info__text--type-' . $digi_pi_type;
}

return array(
	'source'    => 'post-info',
	'target'    => 'digitalisimo-post-info',
	'status'    => 'draft',
	'panel'     => 'hide',
	'keep'      => array(
		'view',
		'space_between',
		'icon_align',
		'divider',
		'divider_style',
		'divider_weight',
		'divider_width',
		'divider_height',
		'divider_color',
		'icon_color',
		'icon_size',
		'text_indent',
		'text_color',
	),
	'groups'    => array(
		'icon_typography' => 'icon_typography',
	),
	'repeaters' => array(
		'icon_list' => array(
			'target'       => 'icon_list',
			'default_rows' => $digi_pi_rows,
			'keep'         => array( 'type', 'date_format', 'custom_date_format', 'time_format', 'custom_time_format', 'taxonomy', 'text_prefix', 'show_avatar', 'avatar_size', 'comments_custom_strings', 'string_no_comments', 'string_one_comment', 'string_comments', 'custom_text', 'link', 'custom_url', 'show_icon', 'selected_icon', 'icon' ),
		),
	),
	// Textos de los comentarios de PRO en las filas que no los fijaban (ver la cabecera).
	'filter'    => static function ( array $out, array $source ): array {
		$pro = array(
			'string_no_comments' => function_exists( 'esc_html__' ) ? esc_html__( 'No Comments', 'elementor-pro' ) : 'No Comments', // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
			'string_one_comment' => function_exists( 'esc_html__' ) ? esc_html__( 'One Comment', 'elementor-pro' ) : 'One Comment', // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
			/* translators: %s: Number of comments. */
			'string_comments'    => function_exists( '__' ) ? esc_html( __( '%s Comments', 'elementor-pro' ) ) : '%s Comments', // phpcs:ignore WordPress.WP.I18n -- mismo texto que PRO.
		);
		foreach ( is_array( $out['icon_list'] ?? null ) ? $out['icon_list'] : array() as $index => $row ) {
			if ( ! is_array( $row ) || 'comments' !== ( $row['type'] ?? 'date' ) ) {
				continue;
			}
			$custom = 'yes' === ( $row['comments_custom_strings'] ?? '' );
			foreach ( $pro as $key => $text ) {
				if ( ! $custom || empty( $row[ $key ] ) ) {
					$row[ $key ] = $text;
				}
			}
			$row['comments_custom_strings'] = 'yes';
			$out['icon_list'][ $index ]     = $row;
		}
		return $out;
	},
	'classes'   => $digi_pi_classes,
);
