<?php
/**
 * F-front, frente C: los widgets propios funcionan con los iconos en línea (SVG). Con el experimento activo el núcleo
 * pinta cada icono como `<svg>` sin tamaño ni color y deja de cargar Font Awesome y eicons, así que:
 *
 * 1. En cada widget propio que pinta iconos, cada combinación de hojas que puede declarar cubre `svg` con
 *    `fill: currentColor`, y toda regla que apunta a un `i` tiene su gemela con `svg`.
 * 2. Ningún widget propio pinta iconos de fuente a mano: ni `<i class="fa…">`, ni `eicon-`, ni Font Awesome. Quedan
 *    fuera `get_icon()` (el icono del panel, V17) y las definiciones de controles (iconos de las opciones del editor y
 *    valores por defecto del control de iconos, que el núcleo pinta en línea).
 */
namespace {
	$root    = realpath( __DIR__ . '/../digitalisimo-elements' );
	$widgets = $root . '/modules/digitalisimo-widgets/';
	$errors  = array();
	function icons_svg_method( $source, $name ) {
		if ( ! preg_match( '/function\s+' . $name . '\s*\(\s*\)[^{]*/', $source, $m, PREG_OFFSET_CAPTURE ) ) { return ''; }
		$open  = strpos( $source, '{', $m[0][1] );
		$depth = 0;
		for ( $i = $open, $n = strlen( $source ); $i < $n; $i++ ) {
			if ( '{' === $source[ $i ] ) { $depth++; }
			elseif ( '}' === $source[ $i ] && 0 === --$depth ) { return substr( $source, $open, $i - $open + 1 ); }
		}
		return '';
	}
	function icons_svg_selector( $selector ) { return preg_replace( '/\s*([>+~])\s*/', '$1', trim( preg_replace( '/\s+/', ' ', $selector ) ) ); }
	/** Reglas de una hoja: lista de [selectores normalizados, declaraciones]; las de @media también cuentan. */
	function icons_svg_rules( $css ) {
		preg_match_all( '/([^{}]+)\{([^{}]*)\}/', preg_replace( '#/\*.*?\*/#s', '', $css ), $found, PREG_SET_ORDER );
		$rules = array();
		foreach ( $found as $rule ) { $rules[] = array( array_map( 'icons_svg_selector', explode( ',', $rule[1] ) ), $rule[2] ); }
		return $rules;
	}

	// Hojas de los widgets: las del registro (principal y variantes) y las que un archivo registra con self::STYLE.
	$registry = file_get_contents( $widgets . 'class-registry.php' );
	$sheets   = array();
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*array\(([^()]*)\)/", $registry, $entries, PREG_SET_ORDER );
	foreach ( $entries as $entry ) { if ( preg_match( "/'css'\s*=>\s*'([^']+)'/", $entry[2], $css ) ) { $sheets[ $entry[1] ] = $root . '/' . $css[1]; } }
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*'(assets\/css\/[^']+)'/", $registry, $variants, PREG_SET_ORDER );
	foreach ( $variants as $variant ) { $sheets[ $variant[1] ] = $root . '/' . $variant[2]; }

	$files = array_merge( glob( $widgets . '*.php' ), array( $root . '/modules/digitalisimo-slider/class-widget.php', $root . '/modules/digitalisimo-import/class-unconverted-widget.php' ) );
	$icon_widgets = 0;
	foreach ( $files as $file ) {
		$name   = basename( $file );
		$source = file_get_contents( $file );
		if ( false !== strpos( $source, 'use Widget_Alias' ) ) { continue; }

		// 2. Sin iconos de fuente a mano.
		$code = preg_replace( '#/\*.*?\*/#s', '', $source );
		$code = preg_replace( '#^\s*//[^\n]*#m', '', $code );
		$icon = icons_svg_method( $code, 'get_icon' );
		if ( '' !== $icon ) { $code = str_replace( $icon, '{}', $code ); }
		$code = preg_replace( "/'icon'\s*=>\s*'eicon-[a-z0-9-]+'/", '', $code );
		$code = preg_replace( "/'library'\s*=>\s*'[a-z0-9-]+'/", '', $code );
		$code = preg_replace( "/'value'\s*=>\s*'(?:fa[srlbdt]?\s+)?fa-[a-z0-9-]+'/", '', $code );
		foreach ( array(
			'<i class="fa…">'        => '/<i\b[^>]*\bclass\s*=\s*\\\\?["\'][^"\']*\b(?:fa[srlbdt]?|fa-[a-z0-9-]+|eicons?|eicon-[a-z0-9-]+)\b/',
			'eicon-'                 => '/\beicon-[a-z0-9-]*/',
			'clase fa-*'             => '/\bfa-[a-z0-9-]+/',
			'clases fa/fas/far/fab'  => '/["\'\s]fa[srlbdt]?\s+fa-/',
			'Font Awesome'           => '/font[\s-]?awesome/i',
			'hoja de iconos de fuente' => '/elementor-icons(?:-fa-|-shared-)|[\'"]font-awesome[\'"]/',
		) as $what => $regex ) {
			if ( preg_match( $regex, $code, $hit ) ) { $errors[] = $name . ': pinta un icono de fuente a mano (' . $what . ': «' . $hit[0] . '»).'; }
		}

		// 1. Hojas que cubren los iconos en línea.
		if ( ! preg_match( '/Icons_Manager::(?:render_icon|try_get_icon_html|get_icon_html)|elementor\.helpers\.renderIcon/', $source ) ) { continue; }
		$icon_widgets++;
		$sets = array();
		preg_match_all( "/array\(\s*((?:'digitalisimo-[a-z0-9-]+'\s*,?\s*)+)\)/", icons_svg_method( $source, 'get_style_depends' ), $lists );
		foreach ( $lists[1] as $list ) { preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'/", $list, $handles ); $sets[] = $handles[1]; }
		if ( preg_match( "/const STYLE\s*=\s*'(digitalisimo-[a-z0-9-]+)'/", $source, $style ) && preg_match( "/wp_register_style\(\s*self::STYLE,\s*plugins_url\(\s*'([^']+)'/", $source, $own ) ) {
			$sheets[ $style[1] ] = $root . '/' . $own[1];
			$sets[] = array( $style[1] );
		}
		if ( ! $sets ) { $errors[] = $name . ': pinta iconos y no declara ninguna hoja propia.'; continue; }
		foreach ( $sets as $set ) {
			$rules = array();
			foreach ( $set as $handle ) {
				if ( ! isset( $sheets[ $handle ] ) || ! is_file( $sheets[ $handle ] ) ) { $errors[] = $name . ': hoja no registrada ' . $handle . '.'; continue 2; }
				$rules = array_merge( $rules, icons_svg_rules( file_get_contents( $sheets[ $handle ] ) ) );
			}
			$label = implode( ' + ', $set );
			$all   = array();
			$fill  = false;
			foreach ( $rules as $rule ) {
				$all = array_merge( $all, $rule[0] );
				foreach ( $rule[0] as $selector ) {
					if ( preg_match( '/(?:^|[\s>+~])svg(?=$|[\s>+~:.\[#])/', $selector ) && preg_match( '/(?:^|;)\s*fill\s*:\s*currentColor\b/i', $rule[1] ) ) { $fill = true; }
				}
			}
			if ( ! $fill ) { $errors[] = $name . ' (' . $label . '): ninguna regla da «fill: currentColor» a los iconos svg.'; }
			foreach ( array_unique( $all ) as $selector ) {
				if ( preg_match( '/(?:^|[\s>+~])i(?=$|[\s>+~:.\[#])/', $selector ) ) {
					$twin = preg_replace( '/(^|[\s>+~])i(?=$|[\s>+~:.\[#])/', '$1svg', $selector );
					if ( ! in_array( $twin, $all, true ) ) { $errors[] = $name . ' (' . $label . '): «' . $selector . '» no tiene su gemela «' . $twin . '».'; }
				}
			}
		}
	}
	if ( $icon_widgets < 16 ) { $errors[] = 'Deben revisarse los 16 widgets propios que pintan iconos (' . $icon_widgets . ').'; }
	if ( $errors ) { throw new RuntimeException( "Widgets propios sin soporte completo de iconos en línea:\n- " . implode( "\n- ", $errors ) ); }
	echo "DIGITALÍSIMO Elements: los {$icon_widgets} widgets propios con iconos cubren svg con currentColor y ninguno pinta iconos de fuente a mano.\n";
}
