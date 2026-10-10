<?php
/** Element Pack Pro 9.9.1 `bdt-breadcrumbs` → `digitalisimo-breadcrumbs`. */
defined( 'ABSPATH' ) || exit;

return array(
	'target'   => 'digitalisimo-breadcrumbs',
	'classes'  => array(
		'.bdt-ep-breadcrumbs-wrapper .bdt-ep-breadcrumb>:nth-child(n+2):not(.bdt-first-column)::before' => '.digi-breadcrumbs__separator',
		'.bdt-ep-breadcrumb>:nth-child(n+2):not(.bdt-first-column)::before' => '.digi-breadcrumbs__separator',
		'.bdt-ep-breadcrumb>:last-child>span' => '.digi-breadcrumbs__item:last-child > span',
		'.bdt-ep-breadcrumb>*>*'               => '.digi-breadcrumbs__item > :not(.digi-breadcrumbs__separator)',
		'.bdt-ep-breadcrumbs-wrapper'          => '.digi-breadcrumbs',
		'.bdt-ep-breadcrumb'                   => '.digi-breadcrumbs__list',
	),
	'defaults' => array( 'breadcrumbs_separator' => '/', 'change_text' => '' ),
	'filter'   => static function ( array $out ) {
		$presets = array( '/' => 'slash', '›' => 'chevron', '·' => 'dot', '–' => 'dash' );
		$sep     = trim( (string) ( $out['breadcrumbs_separator'] ?? '/' ) );
		if ( isset( $presets[ $sep ] ) ) {
			$out['separator'] = $presets[ $sep ];
		} else {
			$out['separator']        = 'custom';
			$out['separator_custom'] = '' === $sep ? '/' : $sep;
		}
		// Sin texto propio Element Pack mostraba el nombre del sitio: se usa la etiqueta dinámica,
		// que sigue al nombre si cambia después de migrar. También se mostraba en la portada.
		$custom = 'yes' === ( $out['change_text'] ?? '' ) ? trim( (string) ( $out['home_page_text'] ?? '' ) ) : '';
		if ( '' !== $custom ) {
			$out['home_text'] = $custom;
		} else {
			$out['home_text']                  = get_bloginfo( 'name' );
			$out['__dynamic__']['home_text'] = '[elementor-tag id="digihome" name="site-title" settings="%7B%7D"]';
		}
		$out['show_home_only'] = 'yes';
		unset( $out['breadcrumbs_separator'], $out['change_text'], $out['home_page_text'], $out['home_icon'] );
		return $out;
	},
);
