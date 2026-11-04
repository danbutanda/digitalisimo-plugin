<?php
/**
 * Formulario de búsqueda (`digitalisimo-search-form`), el widget propio que sustituye al Search Form de PRO (C4 de
 * docs/PRO_WIDGETS_OBJECTIVE.md, contrato docs/pro/c4.md, frente H), y su mapa
 * `modules/digitalisimo-convert/maps/search-form.php`:
 * - recursos: su hoja (≤ 4 KB, sin el kit, `url()` ni clases del proveedor, sólo los colores por defecto de PRO, foco
 *   visible y movimiento reducido) y su script, que sólo cargan los widgets con la piel «Pantalla completa»;
 * - los controles de PRO cargados a la vez que los propios: mismo nombre, tipo, valor por defecto, opciones,
 *   condiciones, rangos y orden (`h_compare_widgets()`), con las diferencias declaradas;
 * - marcado de cada piel: `search` con `role="search"`, formulario GET a la portada con `s`, etiqueta, botón con nombre
 *   accesible, la lupa de la piel mínima como etiqueta, el diálogo modal de pantalla completa con sus botones, la flecha
 *   invertida en RTL, los ganchos de PRO para campos añadidos y el escape;
 * - conversión con `Converter::tree()` de ajustes reales (por defecto, cada piel, texto, flecha, globales, dinámicos,
 *   CSS personalizado con clases de PRO, responsivos): ningún `pro.unmapped`, cada clave cae en un control propio y lo
 *   convertido se pinta con los mismos textos.
 */
namespace {
	$GLOBALS['h_wp'] = array( 'search' => '' );
	function get_search_query( $escaped = true ) { return $GLOBALS['h_wp']['search']; }
	require __DIR__ . '/elements-theme-elements/stubs.php';
	require_once h_path( 'modules/theme-elements/widgets/search-form.php' );

	use Digitalisimo\Elements\Theme_Elements\Module;
	use Digitalisimo\Elements\Theme_Elements\Widgets\Search_Form;

	$pro = h_widget( \ElementorPro\Modules\ThemeElements\Widgets\Search_Form::class );
	$own = h_widget( Search_Form::class );
	$map = require h_path( 'modules/digitalisimo-convert/maps/search-form.php' );

	// 1. Registro y recursos.
	$css = h_assets( $own, 'Formulario de búsqueda', 'search-form', array( '#f1f2f3', '#3f444b', '#69727d', '#fff', '#33373d' ), true );
	foreach ( array( '.digi-search-form--skin-classic .digi-search-form__container:has(:focus-visible)', '.digi-search-form--skin-minimal:focus-within{outline:2px solid}', '.digi-search-form__toggle:focus-visible,.digi-search-form__close:focus-visible' ) as $rule ) {
		h_check( str_contains( $css, $rule ), "Foco visible: falta $rule" );
	}
	h_check( str_contains( $css, 'min-height:24px;min-width:24px' ) && str_contains( $css, '--digi-sf-toggle-size,33px' ) && str_contains( $css, 'min-height:50px' ), 'El aspa mide 24 × 24 px como mínimo; el conmutador, 33 px; el campo, 50 px, como PRO.' );
	h_check( (bool) preg_match( '/@media \(prefers-reduced-motion:reduce\)\{[^}]*transition:none\}dialog\.digi-search-form__container\[open\]\{animation:none\}\}/', $css ), 'Sin transiciones ni animación de apertura con movimiento reducido.' );
	$js = (string) file_get_contents( h_path( Module::WIDGETS['digitalisimo-search-form']['js'] ) );
	h_check( strlen( $js ) > 0 && strlen( $js ) <= 2048 && ! preg_match( '/jquery|\$\(|elementorFrontend|swiper/i', $js ), 'Script ≤ 2 KB, sin jQuery ni dependencias: ' . strlen( $js ) . ' B.' );
	h_check( str_contains( $js, 'showModal()' ) && str_contains( $js, "addEventListener('close'" ) && str_contains( $js, '}, true);' ) && str_contains( $js, 'toggle.focus()' ) && str_contains( $js, "setAttribute('aria-expanded'" ), 'El script abre el diálogo modal, escucha su cierre (Escape incluido) y devuelve el foco al botón.' );
	h_check( ! is_file( h_path( 'modules/digitalisimo-theme-elements/assets/js/search-form-editor.js' ) ), 'Sin script del editor: el script delegado sirve también para lo que el editor vuelve a pintar.' );
	$instance = static function ( $skin ) {
		return new class( $skin ) extends Search_Form {
			private $skin;
			public function __construct( $skin ) { $this->skin = $skin; }
			public function is_type_instance() { return false; }
			public function get_settings( $key = null ) { $s = array( 'skin' => $this->skin ); return null === $key ? $s : ( $s[ $key ] ?? null ); }
		};
	};
	h_check( array( 'digitalisimo-search-form' ) === $instance( 'full_screen' )->get_script_depends() && array() === $instance( 'classic' )->get_script_depends() && array() === $instance( 'minimal' )->get_script_depends(), 'El script sólo con la piel de pantalla completa (y siempre para el tipo del editor).' );

	// 2. Los controles de PRO, uno a uno.
	$skip = array(
		'placeholder.default' => true, // «Buscar...» en el idioma propio; el mapa completa el «Search...» de PRO.
		'button_text.default' => true, // «Buscar»; el mapa completa el «Search» de PRO.
	);
	$diff = h_compare_widgets( $pro, $own, $skip );
	h_check( array() === $diff, "Controles distintos de los de PRO:\n" . implode( "\n", $diff ) );
	h_check( 'Buscar...' === $own->controls['placeholder']['default'] && 'Buscar' === $own->controls['button_text']['default'], 'Textos por defecto propios en español.' );
	foreach ( $own->controls as $name => $control ) {
		foreach ( (array) ( $control['selectors'] ?? array() ) as $selector => $rule ) {
			h_check( ! str_contains( $selector . $rule, 'elementor' ) && ! str_contains( $rule, '--e-' ), "$name: selector o variable de PRO ($selector)." );
		}
		if ( isset( $control['prefix_class'] ) ) {
			h_check( 0 === strpos( $control['prefix_class'], 'digi-search-form--' ), "$name: prefijo de clase propio." );
		}
	}
	foreach ( array( 'skin' => '--skin-', 'button_type' => '--button-type-', 'icon' => '--icon-' ) as $name => $part ) {
		h_check( str_replace( 'elementor-search-form', 'digi-search-form', $pro->controls[ $name ]['prefix_class'] ) === $own->controls[ $name ]['prefix_class'], "$name: el mismo prefijo de clase que PRO con el nombre propio." );
	}

	// 3. Marcado.
	$GLOBALS['h_wp']['actions'] = array();
	$classic = h_render( $own );
	h_check( '<search role="search"><form class="digi-search-form" action="https://sitio.test/" method="get"><div class="digi-search-form__container"><label class="digi-search-form__label" for="digi-search-form-abc1234">Buscar</label><input id="digi-search-form-abc1234" placeholder="Buscar..." class="digi-search-form__input" type="search" name="s" value=""><button class="digi-search-form__submit" type="submit" aria-label="Buscar">' . Search_Form::ICONS['search'] . '</button></div></form></search>' === $classic, 'Clásica: búsqueda, formulario a la portada, etiqueta, campo y botón con nombre: ' . $classic );
	h_check( array( 'elementor_pro/search_form/before_input', 'elementor_pro/search_form/after_input' ) === $GLOBALS['h_wp']['actions'], 'Se disparan los ganchos de PRO para los complementos que añaden campos.' );
	$text = h_render( $own, array( 'button_type' => 'text', 'button_text' => 'Ir <script>x()</script><b onclick="y()">ya</b>' ) );
	h_check( str_contains( $text, '<button class="digi-search-form__submit" type="submit">Ir <b>ya</b></button>' ), 'Botón de texto: el texto (HTML permitido) es su nombre accesible: ' . $text );
	h_check( str_contains( h_render( $own, array( 'button_type' => 'text', 'button_text' => '' ) ), '<button class="digi-search-form__submit" type="submit" aria-label="Buscar"></button>' ), 'Botón de texto vacío: conserva el nombre «Buscar».' );
	h_check( str_contains( h_render( $own, array( 'icon' => 'arrow' ) ), Search_Form::ICONS['arrow'] ), 'Flecha en el botón.' );
	$GLOBALS['h_wp']['rtl'] = true;
	h_check( str_contains( h_render( $own, array( 'icon' => 'arrow' ) ), 'digi-search-form__svg--arrow digi-search-form__svg--rtl' ) && str_contains( $css, '.digi-search-form__svg--rtl{transform:scaleX(-1)}' ), 'En RTL la flecha apunta a la izquierda, como la de PRO.' );
	$GLOBALS['h_wp']['rtl'] = false;
	$minimal = h_render( $own, array( 'skin' => 'minimal' ) );
	h_check( str_contains( $minimal, '<div class="digi-search-form__container"><label class="digi-search-form__icon" for="digi-search-form-abc1234">' . Search_Form::ICONS['search'] . '<span class="digi-search-form__label">Buscar</span></label><input ' ) && 1 === substr_count( $minimal, '<label' ) && ! str_contains( $minimal, '<button' ), 'Mínima: la lupa es la etiqueta del campo (una sola), sin botón: ' . $minimal );
	$full = h_render( $own, array( 'skin' => 'full_screen' ) );
	h_check( str_contains( $full, '<form class="digi-search-form" action="https://sitio.test/" method="get"><button type="button" class="digi-search-form__toggle" aria-label="Buscar" aria-haspopup="dialog" aria-expanded="false" aria-controls="digi-search-form-abc1234-dialog">' . Search_Form::ICONS['search'] . '</button><dialog class="digi-search-form__container" id="digi-search-form-abc1234-dialog" aria-label="Buscar"><label class="digi-search-form__label" for="digi-search-form-abc1234">Buscar</label><input ' ) && str_contains( $full, '<button type="button" class="digi-search-form__close" aria-label="Cerrar la búsqueda">' . Search_Form::ICONS['close'] . '</button></dialog></form></search>' ) && ! str_contains( $full, 'digi-search-form__submit' ), 'Pantalla completa: botón que abre un diálogo con nombre, el campo y el botón de cierre: ' . $full );
	h_check( ! preg_match( '/role="button"|tabindex/', $classic . $minimal . $full ), 'Sin div con role="button" ni tabindex: botones reales.' );
	$GLOBALS['h_wp']['search'] = 'a"b<c>';
	$evil = h_render( $own, array( 'placeholder' => '"><script>x</script>' ) );
	h_check( str_contains( $evil, 'placeholder="&quot;&gt;&lt;script&gt;x&lt;/script&gt;"' ) && str_contains( $evil, 'value="a&quot;b&lt;c&gt;"' ) && ! str_contains( $evil, '<script' ), 'Texto de ejemplo y búsqueda escapados.' );
	$GLOBALS['h_wp']['search'] = '';
	foreach ( array( 'search', 'arrow', 'close' ) as $icon ) {
		h_check( str_contains( Search_Form::ICONS[ $icon ], 'aria-hidden="true" focusable="false"' ), "Icono $icon decorativo." );
	}

	// 4. Conversión con el mapa.
	h_check( 'search-form' === $map['source'] && 'digitalisimo-search-form' === $map['target'] && in_array( $map['status'], array( 'draft', 'ready' ), true ) && 'hide' === $map['panel'], 'Mapa del Search Form hacia el Formulario de búsqueda, oculto del panel al estar listo.' );
	$cases = array(
		'defaults'   => array(),
		'text'       => array( 'button_type' => 'text', 'button_text' => 'Encontrar', 'button_typography_typography' => 'custom', 'button_typography_font_weight' => '700', 'button_text_color' => '#111111', 'button_background_color' => '#eeeeee', 'button_text_color_hover' => '#222222', 'button_background_color_hover' => '#dddddd', 'button_width' => array( 'unit' => 'px', 'size' => 2.5 ) ),
		'arrow'      => array( 'icon' => 'arrow', 'size' => array( 'unit' => 'px', 'size' => 60 ), 'icon_size' => array( 'unit' => 'px', 'size' => 20 ), 'icon_size_mobile' => array( 'unit' => 'px', 'size' => 14 ), 'placeholder' => 'Busca aquí' ),
		'minimal'    => array( 'skin' => 'minimal', 'icon_size_minimal' => array( 'unit' => 'px', 'size' => 18 ), 'input_background_color' => '#ffffff', 'input_border_color' => '#cccccc', 'button_border_width' => array( 'top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'unit' => 'px', 'isLinked' => true ), 'border_radius' => array( 'unit' => 'px', 'size' => 20 ), 'input_box_shadow_box_shadow_type' => 'yes', 'input_box_shadow_box_shadow' => array( 'horizontal' => 0, 'vertical' => 2, 'blur' => 6, 'spread' => 0, 'color' => 'rgba(0,0,0,.2)' ) ),
		'full'       => array( 'skin' => 'full_screen', 'toggle_align' => 'end', 'toggle_size' => array( 'unit' => 'px', 'size' => 40 ), 'overlay_background_color' => 'rgba(0,0,30,.9)', 'toggle_color' => '#ff0000', 'toggle_background_color' => '#00ff00', 'toggle_color_hover' => '#0000ff', 'toggle_background_color_hover' => '#ffff00', 'toggle_icon_size' => array( 'unit' => 'px', 'size' => 60 ), 'toggle_border_width' => array( 'unit' => 'px', 'size' => 2 ), 'toggle_border_radius' => array( 'unit' => '%', 'size' => 50 ) ),
		'focus'      => array( 'input_text_color_focus' => '#000000', 'input_background_color_focus' => '#fafafa', 'input_border_color_focus' => '#333333', 'input_box_shadow_focus_box_shadow_type' => 'yes', 'input_typography_typography' => 'custom', 'input_typography_font_size' => array( 'unit' => 'px', 'size' => 18 ), 'input_typography_font_size_tablet' => array( 'unit' => 'px', 'size' => 16 ) ),
		'globals'    => array( 'input_text_color' => '', 'button_background_color' => '', '__globals__' => array( 'input_text_color' => 'globals/colors?id=accent', 'button_background_color' => 'globals/colors?id=primary', 'input_typography_typography' => 'globals/typography?id=secondary', 'button_typography_typography' => 'globals/typography?id=accent', '_background_color' => 'globals/colors?id=text' ) ),
		'dynamic'    => array( '__dynamic__' => array( 'placeholder' => '[elementor-tag id="a1" name="site-title" settings="%7B%7D"]' ) ),
		'css'        => array( 'custom_css' => "selector .elementor-search-form__container{border:1px solid red}\nselector.elementor-search-form--skin-classic .elementor-search-form__submit:hover{opacity:.8}\nselector .elementor-search-form__input::placeholder{color:#000}\n.elementor-widget-search-form .elementor-search-form__icon{opacity:1}\nselector .elementor-search-form__toggle .dialog-lightbox-close-button{color:red}\nselector.elementor-search-form--button-type-text.elementor-search-form--icon-arrow{x:y}", '_css_classes' => 'mi-busqueda', '_margin' => array( 'top' => '10', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px', 'isLinked' => false ) ),
		'responsive' => array( 'border_radius_tablet' => array( 'unit' => 'px', 'size' => 0 ), 'button_width_mobile' => array( 'unit' => 'px', 'size' => 1 ), 'icon_size_minimal_mobile' => array( 'unit' => 'px', 'size' => 12 ) ),
	);
	list( $out, $codes ) = h_convert( 'search-form', $own, $cases );
	h_check( array( 'css:pro.css:custom_css' ) === $codes, 'Ningún descarte ni aproximación; sólo el CSS convertido: ' . implode( ', ', $codes ) );
	$d = $out['defaults'];
	h_check( 'Search...' === $d['placeholder'] && 'Search' === $d['button_text'] && 'search-form' === $d['_digitalisimo_pro'], 'Un Search Form sin cambios conserva los textos que PRO pintaba.' );
	h_check( str_contains( h_render( $own, $d ), 'placeholder="Search..."' ), 'Lo convertido se pinta con el texto de ejemplo de PRO.' );
	h_check( str_contains( h_render( $own, $out['text'] ), '>Encontrar</button>' ) && '700' === $out['text']['button_typography_font_weight'] && 2.5 === $out['text']['button_width']['size'] && '#dddddd' === $out['text']['button_background_color_hover'], 'Botón de texto: texto, tipografía, colores y ancho.' );
	h_check( 'arrow' === $out['arrow']['icon'] && 14 === $out['arrow']['icon_size_mobile']['size'] && 60 === $out['arrow']['size']['size'] && str_contains( h_render( $own, $out['arrow'] ), 'placeholder="Busca aquí"' ), 'Flecha, tamaños por dispositivo y texto de ejemplo.' );
	h_check( 'minimal' === $out['minimal']['skin'] && 'yes' === $out['minimal']['input_box_shadow_box_shadow_type'] && 6 === $out['minimal']['input_box_shadow_box_shadow']['blur'] && '1' === $out['minimal']['button_border_width']['top'] && 20 === $out['minimal']['border_radius']['size'], 'Mínima: campo, bordes, radio y sombra.' );
	h_check( 'full_screen' === $out['full']['skin'] && 'end' === $out['full']['toggle_align'] && 50 === $out['full']['toggle_border_radius']['size'] && '#ffff00' === $out['full']['toggle_background_color_hover'] && 'rgba(0,0,30,.9)' === $out['full']['overlay_background_color'], 'Pantalla completa: conmutador y superposición.' );
	h_check( str_contains( h_render( $own, $out['full'] ), '<dialog class="digi-search-form__container"' ), 'Lo convertido con pantalla completa se pinta con el diálogo.' );
	h_check( '#333333' === $out['focus']['input_border_color_focus'] && 'yes' === $out['focus']['input_box_shadow_focus_box_shadow_type'] && 16 === $out['focus']['input_typography_font_size_tablet']['size'], 'Estado «Foco» y tipografía por dispositivo.' );
	h_check( array( 'input_text_color' => 'globals/colors?id=accent', 'button_background_color' => 'globals/colors?id=primary', 'input_typography_typography' => 'globals/typography?id=secondary', 'button_typography_typography' => 'globals/typography?id=accent', '_background_color' => 'globals/colors?id=text' ) === $out['globals']['__globals__'], 'Colores y tipografías globales siguen a su control.' );
	h_check( array( 'placeholder' ) === array_keys( $out['dynamic']['__dynamic__'] ), 'La etiqueta dinámica del texto de ejemplo sigue a su control.' );
	h_check( "selector .digi-search-form__container{border:1px solid red}\nselector.digi-search-form--skin-classic .digi-search-form__submit:hover{opacity:.8}\nselector .digi-search-form__input::placeholder{color:#000}\n.elementor-widget-digitalisimo-search-form .digi-search-form__icon{opacity:1}\nselector .digi-search-form__toggle .digi-search-form__close{color:red}\nselector.digi-search-form--button-type-text.digi-search-form--icon-arrow{x:y}" === $out['css']['custom_css'] && 'mi-busqueda' === $out['css']['_css_classes'], 'CSS personalizado con las clases propias: ' . $out['css']['custom_css'] );
	h_check( 0 === $out['responsive']['border_radius_tablet']['size'] && 1 === $out['responsive']['button_width_mobile']['size'] && 12 === $out['responsive']['icon_size_minimal_mobile']['size'], 'Valores por dispositivo.' );
	h_classes_exist( $map, $own, $classic . $minimal . $full );

	echo 'DIGITALÍSIMO Elements: Formulario de búsqueda (' . count( $own->controls ) . ' controles, ' . count( $own->groups ) . ' grupos, hoja de ' . strlen( $css ) . ' B, script de ' . strlen( $js ) . ' B) y su mapa desde el Search Form de PRO (' . count( $cases ) . " casos) validados.\n";
}
