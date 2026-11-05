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
	// Rasgos de F8 (§5): la información emergente, el distintivo y el contador del slider anidado, cada uno en su tope, sin
	// url() ni jQuery, con movimiento reducido si anima, y sólo registrados: los encola el cargador del núcleo por la
	// declaración `assets` de su control, en las páginas que los usan (la vista previa del editor encola el contador).
	$f8_assets = array(
		'digitalisimo-tooltip'               => array( 'class' => 'modules/digitalisimo-widgets/class-tooltip.php', 'css' => array( 'modules/digitalisimo-widgets/assets/css/tooltip.css', 1536 ), 'js' => array( 'modules/digitalisimo-widgets/assets/js/tooltip.js', 1024 ) ),
		'digitalisimo-badge'                 => array( 'class' => 'modules/digitalisimo-widgets/class-badge.php', 'css' => array( 'modules/digitalisimo-widgets/assets/css/badge.css', 1024 ), 'js' => null ),
		'digitalisimo-nested-slider-counter' => array( 'class' => 'modules/digitalisimo-f7b/nested/class-slider-widget.php', 'register' => 'modules/digitalisimo-f7b/nested/class-module.php', 'css' => array( 'modules/digitalisimo-f7b/assets/css/nested-slider-counter.css', 256 ), 'js' => array( 'modules/digitalisimo-f7b/assets/js/nested-slider-counter.js', 512 ) ),
	);
	foreach ( $f8_assets as $handle => $asset ) {
		$class    = (string) file_get_contents( $root . '/' . $asset['class'] );
		$register = isset( $asset['register'] ) ? (string) file_get_contents( $root . '/' . $asset['register'] ) : $class;
		$sheet    = (string) file_get_contents( $root . '/' . $asset['css'][0] );
		check_budget( '' !== $sheet && strlen( $sheet ) <= $asset['css'][1], $handle . ': la hoja pesa ' . strlen( $sheet ) . ' B, más de ' . $asset['css'][1] . ' (§5).' );
		check_budget( ! preg_match( '/url\(/i', $sheet ), $handle . ': sin url() en la hoja.' );
		if ( preg_match( '/@keyframes|animation:|transition:/', $sheet ) ) { check_budget( str_contains( $sheet, 'prefers-reduced-motion' ), $handle . ': la hoja anima sin respetar el movimiento reducido.' ); }
		check_budget( str_contains( $class, "'assets'" ) && str_contains( $class, "'styles'" ) && ( null === $asset['js'] || str_contains( $class, "'scripts'" ) ), $handle . ': se declara en `assets` de su control.' );
		$outside = preg_replace( '/function preview_counter_(style|script)\(\).*?\n\t\}/s', '', $register . $class );
		check_budget( ! preg_match( '/wp_enqueue_(style|script)\(\s*(self::COUNTER_HANDLE|self::HANDLE|\'' . preg_quote( $handle, '/' ) . '\')/', $outside ), $handle . ': sólo se registra (lo encola el núcleo), salvo en la vista previa del editor.' );
		if ( null === $asset['js'] ) {
			check_budget( ! str_contains( $class, 'wp_register_script(' ) && ! is_file( $root . '/' . str_replace( '.css', '.js', str_replace( '/css/', '/js/', $asset['css'][0] ) ) ), $handle . ': 0 JS.' );
			continue;
		}
		$script = (string) file_get_contents( $root . '/' . $asset['js'][0] );
		check_budget( '' !== $script && strlen( $script ) <= $asset['js'][1] && ! preg_match( '/jQuery|\$\(/', $script ), $handle . ': el script pesa ' . strlen( $script ) . ' B (tope ' . $asset['js'][1] . ') y no usa jQuery.' );
		preg_match_all( '/wp_register_script\(.*?\);/s', $register, $found );
		$mine = array_filter( $found[0], function ( $r ) use ( $handle ) { return str_contains( $r, 'COUNTER_HANDLE' ) || str_contains( $r, 'self::HANDLE' ) || str_contains( $r, "'" . $handle . "'" ); } );
		check_budget( 1 === count( $mine ) && str_contains( reset( $mine ), "'strategy' => 'defer'" ) && ! str_contains( reset( $mine ), "'jquery'" ), $handle . ': el script se registra con defer y sin jQuery.' );
	}
	check_budget( 3066 === filesize( $root . '/modules/digitalisimo-f7b/assets/css/nested-slider.css' ) && 5119 === filesize( $root . '/modules/digitalisimo-f7b/assets/js/nested-slider.js' ), 'El contador no cambia nested-slider.css ni nested-slider.js (3 066 B y 5 119 B, §5).' );
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

	// WooCommerce visual (F4-Woo, V.9): los siete widgets de modules/digitalisimo-woo/ con las mismas reglas. Sus
	// recursos los registra Woo\Assets (`digitalisimo-woo-<capacidad>`, con defer) y sólo pueden depender de los
	// handles de WooCommerce y del núcleo que admite Assets::FOREIGN (§4.2).
	$woo_dir      = $root . '/modules/digitalisimo-woo/';
	$woo_registry = file_get_contents( $woo_dir . 'class-registry.php' );
	$woo_assets   = file_get_contents( $woo_dir . 'class-assets.php' );
	preg_match( "/const FOREIGN = array\(([^)]*)\)/", $woo_assets, $foreign_list );
	preg_match_all( "/'([a-z-]+)'/", $foreign_list[1] ?? '', $foreign );
	$foreign = $foreign[1];
	check_budget( array( 'wc-cart-fragments', 'wc-add-to-cart-variation', 'elementor-frontend' ) === $foreign, 'Assets::FOREIGN sólo admite las dependencias de §4.2.' );
	preg_match( "/const SCRIPT_DEPS = array\((.*?)\n\t\);/s", $woo_assets, $deps_list );
	preg_match_all( "/'([a-z-]+)'\s*\)/", $deps_list[1] ?? '', $deps );
	foreach ( $deps[1] as $dep ) { check_budget( in_array( $dep, $foreign, true ) && 'jquery' !== $dep, 'Un script de F4-Woo depende de ' . $dep . ', que no está en Assets::FOREIGN.' ); }
	preg_match_all( '/wp_register_script\(.*?\);/s', $woo_assets, $woo_scripts );
	check_budget( count( $woo_scripts[0] ) >= 1, 'Woo\Assets registra los scripts de F4-Woo.' );
	foreach ( $woo_scripts[0] as $registration ) { check_budget( str_contains( $registration, "'strategy' => 'defer'" ), 'Woo\Assets: script sin defer.' ); }
	preg_match_all( "/'(digitalisimo-woo-[a-z0-9-]+)'\s*=>\s*array\(([^()]*)\)/", $woo_registry, $woo_entries, PREG_SET_ORDER );
	$woo_count = 0;
	foreach ( $woo_entries as $m ) {
		if ( ! preg_match( "/'file'\s*=>\s*'([^']+)'/", $m[2], $f ) ) { continue; }
		if ( preg_match( '#^(single|store)/#', $f[1] ) ) { continue; } // C7: abajo, con sus reglas.
		$name   = $m[1];
		$source = (string) file_get_contents( $woo_dir . $f[1] );
		$woo_count++;
		check_budget( ! str_contains( $source, 'function content_template' ), $name . ': sin plantilla del editor (contrato de Woo\Widget).' );
		check_budget( ! preg_match( '/<img[\s>]/', $source ) && ! str_contains( $source, 'wp_get_attachment_image(' ), $name . ': las imágenes se pintan por Image_Markup, no con <img> a mano.' );
		check_budget( ! preg_match( '/loading="(lazy|eager)"|\'loading\'\s*=>|fetchpriority/', $source ), $name . ': ninguna imagen fuerza su carga; lo decide WordPress.' );
		check_budget( ! str_contains( $source, 'ld+json' ) && ! preg_match( '/<script(?!\s+type="text\/template")/', $source ), $name . ': sin JSON-LD ni <script> propios en la salida.' );
		$declared = budget_method( $source, 'get_style_depends' ) . budget_method( $source, 'get_script_depends' );
		// Los filtros heredan sus recursos de una base propia de su carpeta (`class Price_Widget extends Filter_Widget`).
		if ( '' === $declared && preg_match( '/class \w+ extends (\w+)/', $source, $parent ) && 'Widget' !== $parent[1] ) {
			$parent_file = dirname( $woo_dir . $f[1] ) . '/class-' . strtolower( str_replace( '_', '-', $parent[1] ) ) . '.php';
			$parent_src  = is_file( $parent_file ) ? (string) file_get_contents( $parent_file ) : '';
			$declared    = budget_method( $parent_src, 'get_style_depends' ) . budget_method( $parent_src, 'get_script_depends' );
		}
		// Sólo las cadenas de los array( … ) que devuelven (no los nombres de métodos o de ajustes que consultan).
		preg_match_all( '/array\(([^()]*)\)/', $declared, $lists );
		preg_match_all( "/'([a-z][a-z0-9_-]*)'/", implode( ',', $lists[1] ), $handles );
		check_budget( count( $handles[1] ) >= 1, $name . ': declara sus recursos.' );
		foreach ( array_unique( $handles[1] ) as $handle ) {
			if ( in_array( $handle, $foreign, true ) ) { continue; }
			check_budget( 0 === strpos( $handle, 'digitalisimo-woo-' ), $name . ': sólo recursos propios o de Assets::FOREIGN (' . $handle . ').' );
			$base = substr( $handle, strlen( 'digitalisimo-woo-' ) );
			$css  = $root . '/assets/css/' . $base . '.css';
			$js   = $root . '/assets/js/' . $base . '.js';
			check_budget( is_file( $css ) || is_file( $js ), $name . ': recurso sin archivo ' . $handle );
			if ( is_file( $css ) ) {
				check_budget( filesize( $css ) <= $css_cap, $name . ': la hoja ' . $handle . ' pesa ' . filesize( $css ) . ' bytes, más de ' . $css_cap . '.' );
				check_budget( ! preg_match( '/url\((?!\s*["\']?data:)/i', (string) file_get_contents( $css ) ), $name . ': sin url() en la hoja ' . $handle . '.' );
				$sheet = (string) file_get_contents( $css );
				if ( preg_match( '/@keyframes|animation:|transition:/', $sheet ) ) { check_budget( str_contains( $sheet, 'prefers-reduced-motion' ), $name . ': la hoja ' . $handle . ' anima sin respetar prefers-reduced-motion.' ); }
			}
			if ( is_file( $js ) ) {
				check_budget( filesize( $js ) <= $js_cap, $name . ': el script ' . $handle . ' pesa ' . filesize( $js ) . ' bytes, más de ' . $js_cap . '.' );
			}
		}
	}
	check_budget( 7 === $woo_count, 'Deben revisarse los siete widgets de F4-Woo (' . $woo_count . ').' );
	$count += $woo_count;
	// C7 (programa «sin widgets de PRO», docs/pro/c7.md): los widgets de WooCommerce propios que sustituyen a los de PRO
	// (`single/` y `store/`). Sus recursos están en su carpeta y los registra `Registry::register_assets()` con el nombre
	// del widget (con defer); pueden heredar el Encabezado, el Botón o la Imagen del núcleo (con su plantilla del editor).
	// Excepción declarada (decisión del coordinador de C7): carrito, pago, cuenta, resumen y carrito del menú llevan una
	// segunda hoja (`<widget>-2.css`, registrada por `Store\Base`), cada una en su tope de 4 KB, porque las de PRO pesan de
	// 11 a 36 KB. Además de Assets::FOREIGN, sólo pueden pedir los handles de WooCommerce que ya pedía el widget de PRO.
	$c7_foreign = array_merge( $foreign, array( 'wc-cart', 'wc-checkout', 'wc-password-strength-meter', 'selectWoo', 'select2' ) );
	$c7_count   = 0;
	$c7_second  = array();
	foreach ( $woo_entries as $m ) {
		if ( ! preg_match( "#'file'\s*=>\s*'((?:single|store)/[^']+)'#", $m[2], $f ) || ! preg_match( "/'css'\s*=>\s*'([^']+)'/", $m[2], $c ) || ! preg_match( "/'js'\s*=>\s*'([^']+)'/", $m[2], $j ) ) { continue; }
		$name   = $m[1];
		$source = (string) file_get_contents( $woo_dir . $f[1] );
		$c7_count++;
		check_budget( ! str_contains( $source, 'ld+json' ) && ! preg_match( '/<script(?!\s+type="text\/template")/', $source ), $name . ': sin JSON-LD ni <script> propios en la salida.' );
		check_budget( ! preg_match( '/loading="(lazy|eager)"|\'loading\'\s*=>|fetchpriority/', $source ), $name . ': ninguna imagen fuerza su carga; lo decide WordPress (o WooCommerce).' );
		check_budget( 0 === strpos( $c[1], 'modules/digitalisimo-woo/' ) && 0 === strpos( $j[1], 'modules/digitalisimo-woo/' ), $name . ': recursos en su carpeta del módulo.' );
		check_budget( ! preg_match( '/pro-elements-handlers|elementor-pro-frontend|\bswiper\b|\'jquery\'/i', budget_method( $source, 'get_style_depends' ) . budget_method( $source, 'get_script_depends' ) ), $name . ': sin paquetes de PRO, Swiper ni jQuery.' );
		// Handles literales que declara (también los de constantes `WC_SCRIPTS`/`WC_STYLES`): propios, del núcleo o admitidos.
		$declared = budget_method( $source, 'get_style_depends' ) . budget_method( $source, 'get_script_depends' );
		preg_match_all( '/const WC_[A-Z]+\s*=\s*array\(([^()]*)\)/', $source, $consts );
		preg_match_all( '/(?:array\(|\[)([^()\[\]]*)(?:\)|\])/', $declared, $lists );
		preg_match_all( "/'([a-zA-Z][a-zA-Z0-9_-]*)'/", implode( ',', array_merge( $lists[1], $consts[1] ) ), $handles );
		foreach ( array_unique( $handles[1] ) as $handle ) {
			check_budget( in_array( $handle, $c7_foreign, true ) || 0 === strpos( $handle, 'digitalisimo-woo-' ), $name . ': recurso ajeno no admitido (' . $handle . ').' );
		}
		$sheets = array( $c[1] );
		$second = preg_replace( '/\.css$/', '-2.css', $c[1] );
		if ( is_file( $root . '/' . $second ) ) {
			$sheets[]    = $second;
			$c7_second[] = $name;
		}
		// La regla de movimiento reducido puede vivir en la primera hoja: la segunda depende de ella y siempre van juntas.
		$pair = '';
		foreach ( $sheets as $file ) { $pair .= is_file( $root . '/' . $file ) ? (string) file_get_contents( $root . '/' . $file ) : ''; }
		foreach ( $sheets as $file ) {
			if ( ! is_file( $root . '/' . $file ) ) { continue; }
			$sheet = (string) file_get_contents( $root . '/' . $file );
			check_budget( strlen( $sheet ) <= $css_cap, $name . ': la hoja ' . basename( $file ) . ' pesa ' . strlen( $sheet ) . ' bytes, más de ' . $css_cap . '.' );
			check_budget( ! preg_match( '/url\((?!\s*["\']?data:)/i', $sheet ), $name . ': sin url() en la hoja ' . basename( $file ) . '.' );
			check_budget( ! str_contains( $sheet, '--e-global-' ), $name . ': la hoja ' . basename( $file ) . ' no usa la paleta del kit como valor propio.' );
			if ( preg_match( '/@keyframes|animation:|transition:/', $sheet ) ) { check_budget( str_contains( $pair, 'prefers-reduced-motion' ), $name . ': la hoja ' . basename( $file ) . ' anima sin respetar prefers-reduced-motion.' ); }
		}
		if ( is_file( $root . '/' . $j[1] ) ) {
			$script = (string) file_get_contents( $root . '/' . $j[1] );
			check_budget( strlen( $script ) <= $js_cap, $name . ': el script pesa ' . strlen( $script ) . ' bytes, más de ' . $js_cap . '.' );
			check_budget( ! preg_match( '/\bjQuery\s*\(|\$\(\s*[\'"]/', $script ), $name . ': el script no usa jQuery.' );
		}
	}
	check_budget( 30 === $c7_count, 'Deben revisarse los 30 widgets de WooCommerce de C7 (' . $c7_count . ').' );
	sort( $c7_second );
	check_budget( array( 'digitalisimo-woo-cart', 'digitalisimo-woo-checkout', 'digitalisimo-woo-menu-cart', 'digitalisimo-woo-my-account', 'digitalisimo-woo-purchase-summary' ) === $c7_second, 'Sólo carrito, pago, cuenta, resumen y carrito del menú llevan segunda hoja (excepción declarada): ' . implode( ', ', $c7_second ) . '.' );
	$count += $c7_count;
	// F7b (frente F): el slider anidado ligero de modules/digitalisimo-f7b/nested/ con las mismas reglas; sus recursos los
	// registra Nested\Module (`digitalisimo-nested-slider`, con defer y sin dependencias) y el widget sólo declara los suyos.
	$f7b_dir      = $root . '/modules/digitalisimo-f7b/';
	$f7b_registry = is_file( $f7b_dir . 'nested/class-module.php' ) ? (string) file_get_contents( $f7b_dir . 'nested/class-module.php' ) : '';
	preg_match_all( '/wp_register_script\(.*?\);/s', $f7b_registry, $f7b_scripts );
	foreach ( $f7b_scripts[0] as $registration ) { check_budget( str_contains( $registration, "'strategy' => 'defer'" ) && ! str_contains( $registration, "'jquery'" ), 'Nested\Module: script sin defer o con jQuery.' ); }
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*array\(([^()]*)\)/", $f7b_registry, $f7b_entries, PREG_SET_ORDER );
	$f7b_count = 0;
	foreach ( $f7b_entries as $m ) {
		if ( ! preg_match( "/'file'\s*=>\s*'([^']+)'/", $m[2], $f ) || ! preg_match( "/'css'\s*=>\s*'([^']+)'/", $m[2], $c ) || ! preg_match( "/'js'\s*=>\s*'([^']+)'/", $m[2], $j ) ) { continue; }
		$name   = $m[1];
		$source = (string) file_get_contents( $f7b_dir . $f[1] );
		$php    = preg_replace( '/protected function content_template\(\).*$/s', '', $source );
		$f7b_count++;
		check_budget( ! preg_match( '/<img[\s>]/', $php ) && ! str_contains( $php, 'wp_get_attachment_image(' ), $name . ': sin <img> a mano.' );
		check_budget( ! preg_match( '/loading="(lazy|eager)"|\'loading\'\s*=>|fetchpriority/', $php ), $name . ': ninguna imagen fuerza su carga.' );
		check_budget( ! str_contains( $php, 'ld+json' ) && ! preg_match( '/<script(?!\s+type="text\/template")/', $php ), $name . ': sin JSON-LD ni <script> propios en la salida.' );
		$declared = budget_method( $php, 'get_style_depends' ) . budget_method( $php, 'get_script_depends' );
		preg_match_all( "/'([a-z][a-z0-9_-]*)'/", $declared, $handles );
		check_budget( array( $name ) === array_values( array_unique( $handles[1] ) ), $name . ': declara sólo su hoja y su script.' );
		check_budget( filesize( $root . '/' . $c[1] ) <= 4096 && filesize( $root . '/' . $j[1] ) <= 6144, $name . ': hoja ≤ 4 KB y script ≤ 6 KB (V.13).' );
		check_budget( ! preg_match( '/url\((?!\s*["\']?data:)/i', (string) file_get_contents( $root . '/' . $c[1] ) ) && str_contains( (string) file_get_contents( $root . '/' . $c[1] ), 'prefers-reduced-motion' ), $name . ': sin url() y con movimiento reducido.' );
	}
	check_budget( 1 === $f7b_count, 'Debe revisarse el slider anidado de F7b (' . $f7b_count . ').' );
	$count += $f7b_count;
	// Filtros ampliados (F7b, V.5): los cuatro widgets de modules/digitalisimo-filters/ con las mismas reglas. Heredan
	// sus recursos de la base de su carpeta: la hoja propia (`digitalisimo-filters`) y el coordinador, la hoja y los
	// formularios de F4-Woo (`digitalisimo-woo-filters`, `digitalisimo-woo-filters-form`), que registra Filters\Module.
	$filters_dir      = $root . '/modules/digitalisimo-filters/';
	$filters_registry = file_get_contents( $filters_dir . 'class-registry.php' );
	$filters_module   = file_get_contents( $filters_dir . 'class-module.php' );
	preg_match_all( '/wp_register_script\(.*?\);/s', $filters_module, $filters_scripts );
	check_budget( count( $filters_scripts[0] ) >= 2, 'Filters\Module registra el coordinador y los formularios.' );
	foreach ( $filters_scripts[0] as $registration ) { check_budget( str_contains( $registration, "'strategy' => 'defer'" ) && ! str_contains( $registration, "'jquery'" ), 'Filters\Module: script sin defer o con jQuery.' ); }
	$filters_files = array( 'digitalisimo-filters' => 'modules/digitalisimo-filters/assets/f7b-filters.css', 'digitalisimo-woo-filters' => 'assets/css/filters.css', 'digitalisimo-woo-filters.js' => 'assets/js/filters.js', 'digitalisimo-woo-filters-form' => 'assets/js/filters-form.js' );
	preg_match_all( "/'(digitalisimo-loop-[a-z0-9-]+)'\s*=>\s*array\(([^()]*)\)/", $filters_registry, $filters_entries, PREG_SET_ORDER );
	$filters_count = 0;
	foreach ( $filters_entries as $m ) {
		if ( ! preg_match( "/'file'\s*=>\s*'([^']+)'/", $m[2], $f ) ) { continue; }
		$name   = $m[1];
		$source = (string) file_get_contents( $filters_dir . $f[1] );
		$filters_count++;
		check_budget( ! preg_match( '/<img[\s>]/', $source ) && ! str_contains( $source, 'wp_get_attachment_image(' ), $name . ': sin <img> a mano.' );
		check_budget( ! str_contains( $source, 'ld+json' ) && ! preg_match( '/<script(?!\s+type="text\/template")/', $source ), $name . ': sin JSON-LD ni <script> propios en la salida.' );
		$declared = budget_method( $source, 'get_style_depends' ) . budget_method( $source, 'get_script_depends' );
		if ( '' === $declared && preg_match( '/class \w+ extends (\w+)/', $source, $parent ) ) {
			$parent_src = (string) file_get_contents( $filters_dir . 'class-' . strtolower( str_replace( '_', '-', $parent[1] ) ) . '.php' );
			$declared   = budget_method( $parent_src, 'get_style_depends' ) . budget_method( $parent_src, 'get_script_depends' );
		}
		preg_match_all( '/array\(([^()]*)\)/', $declared, $lists );
		preg_match_all( "/'([a-z][a-z0-9_-]*)'/", implode( ',', $lists[1] ), $handles );
		check_budget( array( 'digitalisimo-woo-filters', 'digitalisimo-filters', 'digitalisimo-woo-filters-form' ) === array_values( array_unique( $handles[1] ) ), $name . ': sólo la hoja propia y los recursos de F4-Woo.' );
	}
	foreach ( $filters_files as $handle => $file ) {
		$path = $root . '/' . $file;
		$cap  = 'digitalisimo-filters' === $handle ? 2048 : ( 'digitalisimo-woo-filters-form' === $handle ? 6144 : ( str_ends_with( $file, '.css' ) ? $css_cap : $js_cap ) );
		check_budget( is_file( $path ) && filesize( $path ) <= $cap, $file . ' pesa ' . ( is_file( $path ) ? filesize( $path ) : 0 ) . ' B, más de ' . $cap . '.' );
		if ( str_ends_with( $file, '.css' ) ) { check_budget( ! preg_match( '/url\((?!\s*["\']?data:)/i', (string) file_get_contents( $path ) ), $file . ': sin url().' ); }
	}
	check_budget( 4 === $filters_count, 'Deben revisarse los cuatro filtros ampliados (' . $filters_count . ').' );
	$count += $filters_count;
	// Carruseles propios (programa «sin widgets de PRO», C1, docs/pro/c1.md): los cinco widgets de
	// modules/digitalisimo-carousel/ sobre el motor `digi-crs`. Sus recursos los registra Carousel\Module (con defer y sin
	// dependencias): el motor (hoja ≤ 4 KB, script ≤ 6 KB) y la hoja de cada widget (≤ 4 KB), con script sólo donde hace
	// falta. Nada de Swiper ni de jQuery. Excepción documentada: la «Carga diferida» de los carruseles de PRO
	// (`lazyload`) es una opción que el usuario elige, y la base la respeta con `loading="lazy"`.
	$crs_dir    = $root . '/modules/digitalisimo-carousel/';
	$crs_module = (string) file_get_contents( $crs_dir . 'class-module.php' );
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*array\(\s*'file'\s*=>\s*'([^']+)'\s*,\s*'class'\s*=>\s*'[^']+'\s*,\s*'css'\s*=>\s*'([^']+)'\s*,\s*'js'\s*=>\s*'([^']+)'/", $crs_module, $crs_entries, PREG_SET_ORDER );
	check_budget( 5 === count( $crs_entries ), 'Carousel\\Module::WIDGETS declara los cinco carruseles (' . count( $crs_entries ) . ').' );
	preg_match_all( '/wp_register_script\(.*?\);/s', $crs_module, $crs_scripts );
	foreach ( $crs_scripts[0] as $registration ) { check_budget( str_contains( $registration, "'strategy' => 'defer'" ) && ! str_contains( $registration, "'jquery'" ), 'Carousel\\Module: script sin defer o con jQuery.' ); }
	foreach ( array( 'modules/digitalisimo-carousel/assets/css/carousel.css' => 4096, 'modules/digitalisimo-carousel/assets/js/carousel.js' => 6144 ) as $file => $cap ) {
		$path = $root . '/' . $file;
		check_budget( is_file( $path ) && filesize( $path ) <= $cap, $file . ' pesa ' . ( is_file( $path ) ? filesize( $path ) : 0 ) . ' B, más de ' . $cap . '.' );
	}
	check_budget( str_contains( (string) file_get_contents( $root . '/modules/digitalisimo-carousel/assets/css/carousel.css' ), 'prefers-reduced-motion' ) || str_contains( (string) file_get_contents( $root . '/modules/digitalisimo-carousel/assets/js/carousel.js' ), 'prefers-reduced-motion' ), 'El motor respeta el movimiento reducido.' );
	$crs_count = 0;
	foreach ( $crs_entries as $m ) {
		$name   = $m[1];
		$source = (string) file_get_contents( $crs_dir . $m[2] );
		check_budget( '' !== $source, $name . ': falta ' . $m[2] );
		$crs_count++;
		// Ni el handle ni la clase de Swiper ni jQuery (las clases `.elementor-swiper-button` del lightbox del núcleo sí).
		check_budget( ! preg_match( '/[\'"]swiper[\'"]|new\s+Swiper|jquery/i', preg_replace( '/^\s*(\*|\/\/).*$/m', '', $source ) ), $name . ': ni Swiper ni jQuery en el código.' );
		check_budget( ! str_contains( $source, 'ld+json' ) && ! preg_match( '/<script(?!\s+type="text\/template")/', $source ), $name . ': sin JSON-LD ni <script> propios en la salida.' );
		foreach ( array( 3 => 4096, 4 => 2048 ) as $i => $cap ) {
			$path = $root . '/' . $m[ $i ];
			if ( ! is_file( $path ) ) { check_budget( 4 === $i, $name . ': falta su hoja ' . $m[ $i ] ); continue; }
			check_budget( filesize( $path ) <= $cap, $m[ $i ] . ' pesa ' . filesize( $path ) . ' B, más de ' . $cap . '.' );
			$text = (string) file_get_contents( $path );
			if ( 3 === $i ) {
				check_budget( ! preg_match( '/url\((?!\s*["\']?data:)/i', $text ), $m[ $i ] . ': sin url().' );
				check_budget( ! preg_match( '/@keyframes|animation:|transition:/', $text ) || str_contains( $text, 'prefers-reduced-motion' ), $m[ $i ] . ': anima sin respetar el movimiento reducido.' );
			} else {
				check_budget( ! preg_match( '/jQuery|\$\(/', $text ), $m[ $i ] . ': sin jQuery.' );
			}
		}
		$declared = budget_method( $source, 'get_style_depends' ) . budget_method( $source, 'get_script_depends' );
		preg_match_all( "/'([a-z][a-z0-9_-]*)'/", $declared, $handles );
		foreach ( $handles[1] as $handle ) { check_budget( str_starts_with( $handle, 'digitalisimo-' ), $name . ': sólo recursos propios (' . $handle . ').' ); }
	}
	check_budget( 5 === $crs_count, 'Deben revisarse los cinco carruseles propios (' . $crs_count . ').' );
	$count += $crs_count;
	// Contenido propio (programa «sin widgets de PRO», C2, docs/pro/c2.md): los catorce widgets de
	// modules/digitalisimo-content/. Content\Module registra la hoja (≤ 4 KB) y, sólo si existe, el script (≤ 6 KB, defer,
	// sin dependencias) de cada uno; el script del editor (`<nombre>-editor.js`) sólo en el editor. Las librerías que pesan
	// más (Prism del Código resaltado, lottie-web de la Animación Lottie) no se declaran como dependencia: su script las pide
	// al acercarse el widget a la pantalla, nunca en la carga inicial. Excepciones documentadas: los Botones para compartir
	// declaran además las hojas Font Awesome del núcleo (`elementor-icons-fa-*`, como PRO), y la Lista de videos pinta con
	// `<img>` escapado la miniatura o la portada que sólo es una URL local (sin adjunto) detrás de la fachada.
	$content_dir    = $root . '/modules/digitalisimo-content/';
	$content_module = (string) file_get_contents( $content_dir . 'class-module.php' );
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*array\(\s*'file'\s*=>\s*'([^']+)'\s*,\s*'class'\s*=>\s*'[^']+'\s*,\s*'css'\s*=>\s*'([^']+)'\s*,\s*'js'\s*=>\s*'([^']+)'/", $content_module, $content_entries, PREG_SET_ORDER );
	check_budget( 14 === count( $content_entries ), 'Content\\Module::WIDGETS declara los catorce widgets de C2 (' . count( $content_entries ) . ').' );
	preg_match_all( '/wp_(?:register|enqueue)_script\(.*?\);/s', $content_module, $content_scripts );
	check_budget( count( $content_scripts[0] ) >= 2, 'Content\\Module registra los scripts del sitio y del editor.' );
	foreach ( $content_scripts[0] as $registration ) { check_budget( str_contains( $registration, "'strategy' => 'defer'" ) && ! str_contains( $registration, "'jquery'" ), 'Content\\Module: script sin defer o con jQuery.' ); }
	$content_count = 0;
	foreach ( $content_entries as $m ) {
		$name   = $m[1];
		$source = (string) file_get_contents( $content_dir . $m[2] );
		check_budget( '' !== $source, $name . ': falta ' . $m[2] );
		$content_count++;
		$code = preg_replace( '/^\s*(\*|\/\/|\/\*).*$/m', '', $source );
		check_budget( ! preg_match( '/[\'"]swiper[\'"]|new\s+Swiper|jquery|elementor-pro-frontend|pro-elements-handlers/i', $code ), $name . ': ni Swiper, ni jQuery, ni los paquetes de PRO.' );
		check_budget( ! str_contains( $source, 'ld+json' ) && ! preg_match( '/<script(?!\s+type="text\/template")/', $source ) && ! preg_match( '/<iframe/i', $code ), $name . ': sin JSON-LD, <script> ni <iframe> propios en la salida.' );
		check_budget( ! preg_match( '/<img[\s>]/', $code ) || 'digitalisimo-video-playlist' === $name, $name . ': sin <img> a mano (Image_Markup o wp_get_attachment_image()).' );
		foreach ( array( 3 => 4096, 4 => 6144 ) as $i => $cap ) {
			$path = $root . '/' . $m[ $i ];
			if ( ! is_file( $path ) ) { check_budget( 4 === $i, $name . ': falta su hoja ' . $m[ $i ] ); continue; }
			check_budget( filesize( $path ) <= $cap, $m[ $i ] . ' pesa ' . filesize( $path ) . ' B, más de ' . $cap . '.' );
			$text = (string) file_get_contents( $path );
			if ( 3 === $i ) {
				check_budget( ! preg_match( '/url\((?!\s*["\']?data:)/i', $text ) && ! str_contains( $text, '--e-global-' ), $m[ $i ] . ': sin url() ni la paleta global.' );
				check_budget( ! preg_match( '/@keyframes|animation:|transition:/', $text ) || str_contains( $text, 'prefers-reduced-motion' ), $m[ $i ] . ': anima sin respetar el movimiento reducido.' );
			} else {
				$js = preg_replace( '#/\*.*?\*/#s', '', $text );
				check_budget( ! preg_match( '/\bjQuery\s*[(.]|\$\(|\bSwiper\b|elementorProFrontend/', $js ), $m[ $i ] . ': sin jQuery, Swiper ni PRO.' );
				check_budget( ! preg_match( '#https?://(?!www\.w3\.org/)#', $js ), $m[ $i ] . ': ninguna dirección de terceros en el script.' );
			}
		}
		$declared = budget_method( $source, 'get_style_depends' ) . budget_method( $source, 'get_script_depends' );
		preg_match_all( "/'([a-z][a-z0-9_-]*)'/", $declared, $handles );
		// Un handle siempre lleva guion; las demás cadenas del método son claves de ajustes.
		foreach ( preg_grep( '/-/', $handles[1] ) as $handle ) { check_budget( str_starts_with( $handle, 'digitalisimo-' ) || ( 'digitalisimo-share-buttons' === $name && str_starts_with( $handle, 'elementor-icons-fa-' ) ), $name . ': sólo recursos propios (' . $handle . ').' ); }
	}
	foreach ( glob( $content_dir . 'assets/js/*-editor.js' ) as $editor ) {
		check_budget( filesize( $editor ) <= 6144 && ! preg_match( '/\bjQuery\s*[(.]|\$\(/', (string) file_get_contents( $editor ) ), basename( $editor ) . ': script del editor ≤ 6 KB y sin jQuery.' );
	}
	// Las librerías grandes se piden al acercarse (IntersectionObserver), nunca como dependencia registrada.
	foreach ( array( 'code-highlight', 'lottie' ) as $lazy ) {
		$text = (string) file_get_contents( $content_dir . 'assets/js/' . $lazy . '.js' );
		check_budget( str_contains( $text, 'IntersectionObserver' ), $lazy . '.js: la librería se carga al acercarse a la pantalla.' );
	}
	check_budget( 14 === $content_count, 'Deben revisarse los catorce widgets de contenido propios (' . $content_count . ').' );
	$count += $content_count;
	// Elementos de tema propios (programa «sin widgets de PRO», C4, docs/pro/c4.md): los quince widgets de
	// modules/digitalisimo-theme-elements/ con las mismas reglas. Sus recursos los registra Theme_Elements\Module (con
	// defer y sin dependencias): hoja ≤ 4 KB por widget y script sólo donde hace falta (el buscador, ≤ 2 KB, sólo con la
	// pantalla completa o en el editor). Excepciones documentadas: los títulos, el logotipo y la imagen destacada heredan
	// la hoja del widget del núcleo del que derivan, la misma que cargaba PRO (`parent::get_style_depends()`); la
	// Información de la entrada pide las hojas de Font Awesome del núcleo sólo con iconos de fuente, como PRO; la Caja de
	// autor encola `digitalisimo-author-box-extras` sólo cuando se usa el Perfil ampliado.
	$te_dir    = $root . '/modules/digitalisimo-theme-elements/';
	$te_module = (string) file_get_contents( $te_dir . 'class-module.php' );
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*array\(\s*'file'\s*=>\s*'([^']+)'\s*,\s*'class'\s*=>\s*'[^']+'\s*,\s*'css'\s*=>\s*'([^']+)'\s*,\s*'js'\s*=>\s*'([^']+)'/", $te_module, $te_entries, PREG_SET_ORDER );
	check_budget( 15 === count( $te_entries ), 'Theme_Elements\\Module::WIDGETS declara los quince elementos de tema (' . count( $te_entries ) . ').' );
	preg_match_all( '/wp_register_script\(.*?\);/s', $te_module, $te_scripts );
	foreach ( $te_scripts[0] as $registration ) { check_budget( str_contains( $registration, "'strategy' => 'defer'" ) && ! str_contains( $registration, "'jquery'" ), 'Theme_Elements\\Module: script sin defer o con jQuery.' ); }
	$te_count   = 0;
	$te_scripts = 0;
	foreach ( $te_entries as $m ) {
		$name   = $m[1];
		$source = (string) file_get_contents( $te_dir . $m[2] );
		check_budget( '' !== $source, $name . ': falta ' . $m[2] );
		$te_count++;
		$code = preg_replace( '/^\s*(\*|\/\/).*$/m', '', $source );
		check_budget( ! preg_match( '/[\'"]swiper[\'"]|new\s+Swiper|jquery|elementor-pro-frontend|pro-elements-handlers/i', $code ), $name . ': ni Swiper, ni jQuery, ni los paquetes de PRO.' );
		check_budget( ! str_contains( $source, 'ld+json' ) && ! preg_match( '/<script(?!\s+type="text\/template")/', $source ) && ! preg_match( '/itemscope|<title[\s>]|<meta\s|rel=["\']canonical/i', $code ), $name . ': sin JSON-LD, <script>, microdatos ni señales SEO propias en la salida.' );
		foreach ( array( 3 => 4096, 4 => 2048 ) as $i => $cap ) {
			$path = $root . '/' . $m[ $i ];
			if ( ! is_file( $path ) ) { continue; }
			check_budget( filesize( $path ) <= $cap, $m[ $i ] . ' pesa ' . filesize( $path ) . ' B, más de ' . $cap . '.' );
			$text = (string) file_get_contents( $path );
			if ( 3 === $i ) {
				check_budget( ! preg_match( '/url\((?!\s*["\']?data:)/i', $text ) && ! str_contains( $text, '--e-global-' ), $m[ $i ] . ': sin url() ni la paleta del kit.' );
				check_budget( ! preg_match( '/@keyframes|animation:|transition:/', $text ) || str_contains( $text, 'prefers-reduced-motion' ), $m[ $i ] . ': anima sin respetar el movimiento reducido.' );
			} else {
				$te_scripts++;
				check_budget( ! preg_match( '/jQuery|\$\(/', $text ), $m[ $i ] . ': sin jQuery.' );
			}
		}
		$declared = budget_method( $source, 'get_style_depends' ) . budget_method( $source, 'get_script_depends' );
		preg_match_all( "/'([a-z][a-z0-9_-]*)'/", preg_replace( "/'(full_screen|skin|classic|e_font_icon_svg|is_type_instance|get_settings)'/", '', $declared ), $handles );
		foreach ( $handles[1] as $handle ) { check_budget( str_starts_with( $handle, 'digitalisimo-' ) || in_array( $handle, array( 'elementor-icons-fa-regular', 'elementor-icons-fa-solid' ), true ), $name . ': sólo recursos propios o del núcleo (' . $handle . ').' ); }
	}
	check_budget( 15 === $te_count && 1 === $te_scripts, 'Deben revisarse los quince elementos de tema, con un solo script (' . $te_count . ', ' . $te_scripts . ').' );
	$count += $te_count;
	// Navegación y búsqueda propias (programa «sin widgets de PRO», C3, docs/pro/c3.md): los cuatro widgets de
	// modules/digitalisimo-navigation/. Navigation\Module registra la hoja (≤ 4 KB) y, sólo si existe, el script (≤ 6 KB,
	// defer, sin dependencias) de cada uno; el script del editor (`<nombre>-editor.js`) sólo en el editor. El Menú de
	// WordPress y el Menú con submenús de contenido reparten su estilo en más hojas (`SHEETS` de su clase: desplegable,
	// elementos y familia del puntero), cada una ≤ 4 KB y sólo encolada si los ajustes la usan. Sin jQuery ni SmartMenus.
	$nav_dir    = $root . '/modules/digitalisimo-navigation/';
	$nav_module = (string) file_get_contents( $nav_dir . 'class-module.php' );
	preg_match_all( "/'(digitalisimo-[a-z0-9-]+)'\s*=>\s*array\(\s*'file'\s*=>\s*'([^']+)'\s*,\s*'class'\s*=>\s*'[^']+'\s*,\s*'css'\s*=>\s*'([^']+)'\s*,\s*'js'\s*=>\s*'([^']+)'/", $nav_module, $nav_entries, PREG_SET_ORDER );
	check_budget( 4 === count( $nav_entries ), 'Navigation\\Module::WIDGETS declara los cuatro widgets de C3 (' . count( $nav_entries ) . ').' );
	preg_match_all( '/wp_(?:register|enqueue)_script\(.*?\);/s', $nav_module, $nav_scripts );
	check_budget( count( $nav_scripts[0] ) >= 2, 'Navigation\\Module registra los scripts del sitio y del editor.' );
	foreach ( $nav_scripts[0] as $registration ) { check_budget( str_contains( $registration, "'strategy' => 'defer'" ) && ! str_contains( $registration, "'jquery'" ), 'Navigation\\Module: script sin defer o con jQuery.' ); }
	$nav_count  = 0;
	$nav_sheets = 0;
	foreach ( $nav_entries as $m ) {
		$name   = $m[1];
		$source = (string) file_get_contents( $nav_dir . $m[2] );
		check_budget( '' !== $source, $name . ': falta ' . $m[2] );
		$nav_count++;
		$code = preg_replace( '/^\s*(\*|\/\/|\/\*).*$/m', '', $source );
		check_budget( ! preg_match( '/[\'"]swiper[\'"]|new\s+Swiper|jquery|smartmenus|elementor-pro-frontend|pro-elements-handlers/i', $code ), $name . ': ni Swiper, ni jQuery, ni SmartMenus, ni los paquetes de PRO.' );
		check_budget( ! str_contains( $source, 'ld+json' ) && ! preg_match( '/<script(?!\s+type="text\/template")/', $source ) && ! preg_match( '/<iframe/i', $code ), $name . ': sin JSON-LD, <script> ni <iframe> propios en la salida.' );
		check_budget( ! preg_match( '/<img[\s>]/', $code ), $name . ': sin <img> a mano.' );
		// La hoja principal y las de `SHEETS` de la clase, con el mismo presupuesto.
		$css = array( $m[3] );
		if ( preg_match( '/const\s+SHEETS\s*=\s*array\((.*?)\);/s', $source, $sheets ) ) {
			preg_match_all( "/'digitalisimo-[a-z0-9-]+'\s*=>\s*'([^']+\.css)'/", $sheets[1], $extra );
			$css         = array_merge( $css, $extra[1] );
			$nav_sheets += count( $extra[1] );
		}
		foreach ( array_merge( array_fill_keys( $css, 4096 ), array( $m[4] => 6144 ) ) as $file => $cap ) {
			$path = $root . '/' . $file;
			if ( ! is_file( $path ) ) { check_budget( $file === $m[4], $name . ': falta su hoja ' . $file ); continue; }
			check_budget( filesize( $path ) <= $cap, $file . ' pesa ' . filesize( $path ) . ' B, más de ' . $cap . '.' );
			$text = (string) file_get_contents( $path );
			if ( 4096 === $cap ) {
				check_budget( ! preg_match( '/url\((?!\s*["\']?data:)/i', $text ) && ! str_contains( $text, '--e-global-' ), $file . ': sin url() ni la paleta global.' );
				check_budget( ! preg_match( '/@keyframes|animation:|transition:/', $text ) || str_contains( $text, 'prefers-reduced-motion' ), $file . ': anima sin respetar el movimiento reducido.' );
			} else {
				$js = preg_replace( '#/\*.*?\*/#s', '', $text );
				check_budget( ! preg_match( '/\bjQuery\s*[(.]|\$\(|\bSwiper\b|elementorProFrontend|smartmenus/i', $js ), $file . ': sin jQuery, Swiper, SmartMenus ni PRO.' );
				check_budget( ! preg_match( '#https?://(?!www\.w3\.org/)#', $js ), $file . ': ninguna dirección de terceros en el script.' );
			}
		}
		$declared = budget_method( $source, 'get_style_depends' ) . budget_method( $source, 'get_script_depends' );
		preg_match_all( "/'([a-z][a-z0-9_-]*)'/", $declared, $handles );
		foreach ( preg_grep( '/-/', $handles[1] ) as $handle ) { check_budget( str_starts_with( $handle, 'digitalisimo-' ), $name . ': sólo recursos propios (' . $handle . ').' ); }
	}
	check_budget( 7 === $nav_sheets, 'Las hojas adicionales del Menú de WordPress (5) y del Menú con submenús de contenido (2) entran en el presupuesto (' . $nav_sheets . ').' );
	foreach ( glob( $nav_dir . 'assets/js/*-editor.js' ) as $editor ) {
		check_budget( filesize( $editor ) <= 6144 && ! preg_match( '/\bjQuery\s*[(.]|\$\(/', (string) file_get_contents( $editor ) ), basename( $editor ) . ': script del editor ≤ 6 KB y sin jQuery.' );
	}
	// Resultados en vivo de la Búsqueda: la REST del propio sitio, sin peticiones a terceros.
	check_budget( ! preg_match( '#https?://#', preg_replace( '/^\s*(\*|\/\/).*$/m', '', (string) file_get_contents( $nav_dir . 'class-search-rest.php' ) ) ), 'Search_Rest: sin direcciones de terceros.' );
	check_budget( 4 === $nav_count, 'Deben revisarse los cuatro widgets de navegación propios (' . $nav_count . ').' );
	$count += $nav_count;
	check_budget( $count >= 126, 'Deben revisarse los 126 widgets propios (' . $count . ').' );
	// El generador único: ALT de Medios, decoding y las decisiones de carga de WordPress; sizes sólo con srcset.
	$helper = file_get_contents( $dir . 'class-image-markup.php' );
	check_budget( str_contains( $helper, 'wp_get_attachment_image(' ) && str_contains( $helper, 'wp_get_loading_optimization_attributes' ) && str_contains( $helper, "'decoding'" ) && ! preg_match( "/'loading'\s*=>\s*'/", $helper ), 'Image_Markup delega la carga en WordPress y no la fuerza.' );
	echo "DIGITALÍSIMO Elements: presupuesto del front de los {$count} widgets propios en verde.\n";
}
