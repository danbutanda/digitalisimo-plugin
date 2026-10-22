<?php
/**
 * Presupuesto del front de los widgets propios (docs/ELEMENTS_FRONT_OBJECTIVE.md): cada widget declara sólo recursos
 * propios registrados con `defer` y sin jQuery, pinta sus imágenes por Image_Markup (nunca <img> a mano ni `loading`
 * forzado), respeta el movimiento reducido si anima, difiere sus iframes con título, no imprime JSON-LD ni <script>, y
 * sus hojas y scripts caben en el tope. Las excepciones documentadas se listan abajo con su motivo.
 */
namespace {
	define( 'ABSPATH', __DIR__ );
	$root = realpath( __DIR__ . '/../digitalisimo-elements' );
	$dir  = $root . '/modules/digitalisimo-widgets/';
	function check_budget( $ok, $message ) { if ( ! $ok ) { throw new \RuntimeException( $message ); } }
	/** Cuerpo de un método, con sus llaves emparejadas (también los escritos en una línea). */
	function budget_method( $source, $name ) {
		$start = strpos( $source, 'function ' . $name . '()' );
		if ( false === $start ) { return ''; }
		$open = strpos( $source, '{', $start );
		$depth = 0;
		for ( $i = $open, $n = strlen( $source ); $i < $n; $i++ ) {
			if ( '{' === $source[ $i ] ) { $depth++; }
			elseif ( '}' === $source[ $i ] && 0 === --$depth ) { return substr( $source, $open, $i - $open + 1 ); }
		}
		return '';
	}
	// Excepciones documentadas: el Slider Optimizado decide la carga de sus imágenes y pesa más por su motor de bucle.
	$exceptions = array( 'digitalisimo-slider-optimizado' => array( 'img', 'loading', 'js' ) );
	$css_cap = 4096;
	$js_cap  = 8192;

	$registry = file_get_contents( $dir . 'class-registry.php' );
	check_budget( ! preg_match( "/wp_register_script\\([^;]*'jquery'/", $registry ), 'Ningún script propio depende de jQuery.' );
	preg_match_all( '/wp_register_script\(.*?\);/s', $registry, $scripts );
	foreach ( $scripts[0] as $registration ) { check_budget( str_contains( $registration, "'strategy' => 'defer'" ), 'Todo script propio se registra con defer: ' . substr( $registration, 0, 80 ) ); }
	foreach ( array( 'modules/digitalisimo-slider/class-module.php', 'modules/digitalisimo-widgets/class-figures.php', 'modules/digitalisimo-widgets/class-floating-effects.php' ) as $file ) {
		$source = file_get_contents( $root . '/' . $file );
		preg_match_all( '/wp_register_script\(.*?\);/s', $source, $found );
		foreach ( $found[0] as $registration ) { check_budget( str_contains( $registration, "'strategy' => 'defer'" ), $file . ': script sin defer.' ); }
	}
	// Rendimiento del front (F-front): todo script que registre el módulo va con defer y sin jQuery directo; el cargador
	// opt-in de Lottie y Prism (`lazy_libs`) tiene que estar entre ellos.
	$front_files = glob( $root . '/modules/digitalisimo-front/*.php' );
	$front_scripts = 0;
	foreach ( $front_files as $file ) {
		preg_match_all( '/wp_register_script\(.*?\);/s', file_get_contents( $file ), $found );
		foreach ( $found[0] as $registration ) {
			$front_scripts++;
			check_budget( str_contains( $registration, "'strategy' => 'defer'" ), basename( $file ) . ': script sin defer.' );
			check_budget( ! str_contains( $registration, "'jquery'" ), basename( $file ) . ': un script del front no depende de jQuery.' );
		}
	}
	check_budget( in_array( $root . '/modules/digitalisimo-front/class-lazy-libs.php', $front_files, true ) && $front_scripts >= 1, 'El cargador de modules/digitalisimo-front/ se revisa (' . $front_scripts . ' registros).' );
	// Capacidades de diseño (F7a): cada hoja del front del módulo está en su tope, sin url() salvo `data:`, y sólo se
	// registra; la encola el cargador del núcleo por la declaración `assets` de su control, en las páginas que lo usan.
	$design_styles = array(
		'digitalisimo-sticky-state' => array( 'class' => 'sticky/class-sticky-state.php', 'css' => 'sticky/assets/sticky-state.css', 'cap' => 512 ),
	);
	foreach ( $design_styles as $handle => $style ) {
		$css_path = $root . '/modules/digitalisimo-design/' . $style['css'];
		$class    = is_file( $root . '/modules/digitalisimo-design/' . $style['class'] ) ? (string) file_get_contents( $root . '/modules/digitalisimo-design/' . $style['class'] ) : '';
		check_budget( is_file( $css_path ) && filesize( $css_path ) <= min( $style['cap'], $css_cap ), $handle . ': la hoja pesa ' . ( is_file( $css_path ) ? filesize( $css_path ) : 0 ) . ' bytes, más de ' . $style['cap'] . '.' );
		check_budget( ! preg_match( '/url\((?!\s*["\']?data:)/i', (string) file_get_contents( $css_path ) ), $handle . ': sin url() en la hoja.' );
		check_budget( str_contains( $class, 'wp_register_style(' ) && str_contains( $class, "'assets'" ) && str_contains( $class, "'styles'" ) && ! str_contains( $class, 'wp_enqueue_style(' ), $handle . ': sólo se registra y se declara en `assets` del control.' );
		check_budget( ! str_contains( $class, 'wp_register_script(' ) && ! str_contains( $class, 'wp_enqueue_script(' ), $handle . ': 0 JS.' );
	}
	// Entradas del registro: nombre => array( 'file' => ..., 'class' => ..., 'css' => ... ) sin paréntesis anidados.
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*array\(([^()]*)\)/", $registry, $entries, PREG_SET_ORDER );
	$files = array();
	$css_files = array();
	foreach ( $entries as $m ) {
		if ( preg_match( "/'file'\s*=>\s*'([^']+)'/", $m[2], $f ) ) { $files[ $m[1] ] = $dir . $f[1]; }
		if ( preg_match( "/'css'\s*=>\s*'([^']+)'/", $m[2], $c ) ) { $css_files[ $m[1] ] = $root . '/' . $c[1]; }
	}
	$files['digitalisimo-slider-optimizado'] = $root . '/modules/digitalisimo-slider/class-widget.php';
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*'(assets\/css\/[^']+)'/", $registry, $variants, PREG_SET_ORDER );
	foreach ( $variants as $m ) { $css_files[ $m[1] ] = $root . '/' . $m[2]; }
	$css_files['digitalisimo-carousel-engine'] = $root . '/assets/css/carousel-engine.css';
	$css_files['digitalisimo-slider-optimizado'] = $root . '/assets/css/slider-optimizado.css';
	preg_match_all( "/wp_register_script\(\s*'(digitalisimo-[a-z0-9-]+)',\s*plugins_url\(\s*'([^']+)'/", $registry, $js_reg, PREG_SET_ORDER );
	$js_files = array();
	foreach ( $js_reg as $m ) { $js_files[ $m[1] ] = $root . '/' . $m[2]; }
	// Los scripts registrados en bucle (foreach sobre nombres) también cuentan.
	if ( preg_match( '/foreach \( array\( ((?:\'[a-z-]+\',?\s*)+) \) as \$script \)/', $registry, $loop ) ) {
		preg_match_all( "/'([a-z-]+)'/", $loop[1], $names );
		foreach ( $names[1] as $script ) { $js_files[ 'digitalisimo-' . $script ] = $root . '/assets/js/' . $script . '.js'; }
	}
	$js_files['digitalisimo-slider-optimizado'] = $root . '/assets/js/slider-optimizado.js';

	$count = 0;
	foreach ( $files as $name => $file ) {
		$source = file_get_contents( $file );
		if ( str_contains( $source, 'use Widget_Alias' ) ) { continue; }
		$count++;
		$exempt = $exceptions[ $name ] ?? array();
		// Sólo código de servidor: la plantilla del editor es JavaScript.
		$php = preg_replace( '/protected function content_template\(\).*$/s', '', $source );
		$render = $php;
		if ( ! in_array( 'img', $exempt, true ) ) {
			check_budget( ! preg_match( '/<img[\s>]/', $render ) && ! str_contains( $render, 'wp_get_attachment_image(' ), $name . ': las imágenes se pintan por Image_Markup, no con <img> a mano.' );
		}
		if ( ! in_array( 'loading', $exempt, true ) ) {
			check_budget( ! preg_match( '/loading="(lazy|eager)"|\'loading\'\s*=>|fetchpriority/', str_replace( 'loading="lazy"', '', preg_replace( '/<iframe[^>]*>/', '', $render ) ) ), $name . ': ninguna imagen fuerza su carga; lo decide WordPress.' );
		}
		check_budget( ! str_contains( $render, 'ld+json' ) && ! preg_match( '/<script(?!\s+type="text\/template")/', str_replace( '<noscript><style>', '', $render ) ), $name . ': sin JSON-LD ni <script> propios en la salida.' );
		preg_match_all( '/<iframe\s.*?<\/iframe>/s', $render, $iframes );
		foreach ( $iframes[0] as $iframe ) { check_budget( str_contains( $iframe, 'loading="lazy"' ) && str_contains( $iframe, 'title=' ), $name . ': todo iframe se difiere y lleva título.' ); }
		// Recursos declarados: sólo handles propios registrados.
		$sd = budget_method( $php, 'get_style_depends' );
		$jd = budget_method( $php, 'get_script_depends' );
		preg_match_all( "/'(digitalisimo-[a-z0-9-]*[a-z0-9])'/", $sd, $styles );
		preg_match_all( "/'(digitalisimo-[a-z0-9-]*[a-z0-9])'/", $jd, $js );
		check_budget( ! preg_match( "/'(?!digitalisimo-)[a-z][a-z0-9-]*'\s*[,)]/", preg_replace( '/\'[a-z_]+\'\s*=>|\$s\[\s*\'[a-z_]+\'\s*\]|self::[A-Z_]+|\'(yes|none|arrows|carousel|grid|rail|bar|a|metis)\'/', '', $sd . $jd ) ), $name . ': sólo recursos propios en get_style_depends y get_script_depends.' );
		$css_total = 0;
		foreach ( array_unique( $styles[1] ) as $handle ) {
			check_budget( isset( $css_files[ $handle ] ) && is_file( $css_files[ $handle ] ), $name . ': hoja no registrada ' . $handle );
			$css = file_get_contents( $css_files[ $handle ] );
			if ( preg_match( '/@keyframes|animation:|transition:/', $css ) ) { check_budget( str_contains( $css, 'prefers-reduced-motion' ) || str_contains( file_get_contents( $css_files[ $styles[1][0] ] ), 'prefers-reduced-motion' ), $name . ': la hoja ' . $handle . ' anima sin respetar el movimiento reducido.' ); }
			if ( 'digitalisimo-carousel-engine' !== $handle ) { $css_total = max( $css_total, filesize( $css_files[ $handle ] ) ); }
		}
		check_budget( $css_total <= $css_cap, $name . ': una hoja propia pesa ' . $css_total . ' bytes, más de ' . $css_cap . '.' );
		foreach ( array_unique( $js[1] ) as $handle ) {
			check_budget( isset( $js_files[ $handle ] ) && is_file( $js_files[ $handle ] ), $name . ': script no registrado ' . $handle );
			if ( ! in_array( 'js', $exempt, true ) && 'digitalisimo-qr-vendor' !== $handle ) { check_budget( filesize( $js_files[ $handle ] ) <= $js_cap, $name . ': el script ' . $handle . ' pesa más de ' . $js_cap . ' bytes.' ); }
		}
	}
	check_budget( $count >= 45, 'Deben revisarse los 45 widgets propios (' . $count . ').' );
	// El generador único: ALT de Medios, decoding y las decisiones de carga de WordPress; sizes sólo con srcset.
	$helper = file_get_contents( $dir . 'class-image-markup.php' );
	check_budget( str_contains( $helper, 'wp_get_attachment_image(' ) && str_contains( $helper, 'wp_get_loading_optimization_attributes' ) && str_contains( $helper, "'decoding'" ) && ! preg_match( "/'loading'\s*=>\s*'/", $helper ), 'Image_Markup delega la carga en WordPress y no la fuerza.' );
	echo "DIGITALÍSIMO Elements: presupuesto del front de los {$count} widgets propios en verde.\n";
}
