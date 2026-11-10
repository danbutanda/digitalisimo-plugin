<?php
/**
 * F8 · Informe de DIGITALÍSIMO Migración (V.14 C2, V.7, §3.G): catálogo (códigos únicos, prefijo de su archivo,
 * secciones válidas, textos sin dominios del proveedor, cada código escrito en el código está declarado), detalle sólo
 * con escalares conocidos, escape campo a campo con `<img src=x onerror=…>`, nombres privados, modo editor que descarta,
 * vuelco cada 20 documentos, topes, fusión sin duplicar, «Pendiente de revisar» como única sección que cuenta (y
 * `review_if_published`), columnas de red, CLI, regla de 1.0.20 para informes sin `f8` y el informe final del runner.
 */
namespace {
	require __DIR__ . '/fixtures/f8-wp.php';
	f8_load_migration( array( 'context', 'report' ) );
	use Digitalisimo\Elements\Legacy\Context;
	use Digitalisimo\Elements\Legacy\Report;

	$root   = dirname( __DIR__ ) . '/digitalisimo-migration/legacy/';
	$xss    = '<img src=x onerror=alert(1)>';
	$vendor = json_decode( (string) file_get_contents( __DIR__ . '/playground/brand-audit/vendors.json' ), true );

	/** Inyecta un catálogo de prueba (sin tocar los archivos). */
	function with_catalog( array $codes ) {
		$prop = new \ReflectionProperty( Report::class, 'catalog' );
		$prop->setAccessible( true ); // PHP 7.4 (el mínimo declarado) lo exige; desde 8.1 no hace nada.
		$prop->setValue( null, $codes );
	}

	/* ================================================================ Catálogo */
	Report::reset_catalog();
	$catalog = Report::catalog();
	f8_check( isset( $catalog['core.target_missing'], $catalog['core.late_kept'], $catalog['core.elements_old'] ), 'El catálogo carga los códigos del frente A.' );
	$prefixes = Report::CATALOGS;
	$seen     = array();
	$dupes    = array();
	foreach ( $prefixes as $name => $prefix ) {
		$file = $root . 'report/codes-' . $name . '.php';
		if ( ! is_file( $file ) ) {
			continue;
		}
		$text = (string) file_get_contents( $file );
		if ( false === strpos( $text, 'namespace Digitalisimo\\Elements\\Legacy;' ) ) {
			// Con WordPress siempre hay __() global; sin el espacio de nombres, un entorno de prueba sin __ no lo carga.
			echo 'Aviso: el catálogo ' . $name . " no declara `namespace Digitalisimo\\Elements\\Legacy;` (su __() de reserva).\n";
		}
		$codes = include $file;
		f8_check( is_array( $codes ) && $codes, 'El catálogo ' . $name . ' devuelve códigos.' );
		foreach ( $codes as $code => $spec ) {
			if ( isset( $seen[ $code ] ) ) {
				$dupes[] = $code;
			}
			$seen[ $code ] = $name;
			$ok_prefix     = 0 === strpos( $code, $prefix ) && ( 'ps' !== $name || 0 !== strpos( $code, 'ps.dyn.' ) );
			$ok_section    = in_array( $spec['section'] ?? '', Report::SECTIONS, true );
			$label         = (string) ( $spec['label'] ?? '' );
			$clean         = true;
			foreach ( (array) $vendor['hosts'] as $host ) {
				$clean = $clean && false === stripos( $label, $host );
			}
			f8_check( $ok_prefix && $ok_section && '' !== $label && $clean && 1 === preg_match( '/^(core|ext|ps|tb|plat|mk|pro)\.[a-z0-9_.]{1,80}$/', $code ), 'Código bien declarado (prefijo de su archivo, sección, texto sin dominios del proveedor): ' . $code . ' (' . $name . ')' );
		}
	}
	f8_check( ! $dupes, 'Ningún código está declarado dos veces: ' . implode( ', ', $dupes ) );
	f8_check( count( $seen ) === count( $catalog ), 'El catálogo cargado es la unión de los archivos.' );
	foreach ( $catalog as $code => $spec ) {
		if ( preg_match( '/\b(is|are|was|with|without|could|will|should|cannot)\b/i', $spec['label'] ) || ! preg_match( '/[áéíóúñ¿¡]|\b(el|la|los|las|de|del|sin|con|para|que|no|se|un|una|y|en|por|al|es)\b/iu', $spec['label'] ) ) {
			f8_check( false, 'El texto de ' . $code . ' no parece español: ' . $spec['label'] );
		}
	}
	f8_check( true, 'Los textos del catálogo están en español.' );
	// Cada código escrito literalmente en el código de Migración está declarado.
	$literal = array();
	$it      = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( '.php' !== substr( (string) $file, -4 ) || false !== strpos( (string) $file, '/report/' ) || false !== strpos( (string) $file, '/maps/' ) ) {
			continue;
		}
		$text = (string) file_get_contents( (string) $file );
		if ( preg_match_all( "/(?:Report::(?:add|site)\\(\\s*(?:[^,()]+(?:\\([^()]*\\))?\\s*,\\s*)?|::(?:site_code|doc_code)\\(\\s*(?:[^,()]+,\\s*)?|Report::site\\(\\s*\\$[a-z_]+\\s*\\)|array\\(\\s*)'((?:core|ext|ps|tb|plat|mk|pro)\\.[a-z0-9_.]+)'/", $text, $m ) ) {
			foreach ( $m[1] as $code ) {
				if ( '.' === substr( $code, -1 ) ) {
					continue; // Prefijo de un código compuesto (`ext.dropped.` . $slug).
				}
				$literal[ $code ] = basename( (string) $file );
			}
		}
	}
	$own     = array( 'class-runner.php', 'class-migration.php', 'class-native.php', 'class-retire.php', 'class-report.php', 'class-cli.php' );
	$missing = array();
	foreach ( $literal as $code => $file ) {
		if ( ! isset( $catalog[ $code ] ) && in_array( $file, $own, true ) && 0 === strpos( $code, 'core.' ) ) {
			$missing[] = $code . ' (' . $file . ')';
		}
	}
	f8_check( ! $missing, 'Cada código `core.` que escribe el frente A está en su catálogo: ' . implode( ', ', $missing ) );
	$others = array();
	foreach ( $literal as $code => $file ) {
		if ( ! isset( $catalog[ $code ] ) ) {
			$others[] = $code . ' (' . $file . ')';
		}
	}
	if ( $others ) {
		echo 'Aviso: códigos usados sin declarar en su catálogo (los integra el coordinador): ' . implode( ', ', $others ) . "\n";
	}
	f8_check( count( $literal ) >= 10, 'El código de Migración usa el informe: ' . count( $literal ) . ' códigos literales.' );

	/* ================================================================ Detalle y escape */
	f8_reset();
	with_catalog( array(
		'core.a'      => array( 'section' => 'converted', 'label' => 'Convertido A', 'review_if_published' => false, 'private' => false ),
		'core.b'      => array( 'section' => 'dropped', 'label' => 'Descartado <b>B</b>', 'review_if_published' => true, 'private' => false ),
		'core.secret' => array( 'section' => 'review', 'label' => 'Contraseña', 'review_if_published' => false, 'private' => true ),
	) );
	$doc   = f8_post( array( 'post_title' => $xss, 'post_status' => 'publish' ) );
	$draft = f8_post( array( 'post_title' => 'Borrador', 'post_status' => 'draft' ) );
	Report::clear();
	Report::begin( 'run' );
	Report::add( $doc, 'core.a', array( 'widget' => $xss, 'element' => $xss, 'name' => $xss, 'target' => $xss, 'type' => $xss, 'note' => $xss . str_repeat( 'x', 300 ), 'count' => '7abc', 'id' => '12', 'extra' => 'fuera', 'array' => array( 1 ), 'widget2' => 'no' ) );
	Report::site( 'core.a', array( 'name' => $xss, 'note' => $xss ) );
	Report::flush();
	$r    = Report::stored();
	$item = $r['docs'][ $doc ]['items'][0];
	f8_check( array( 'widget', 'element', 'count', 'name', 'target', 'id', 'type', 'note' ) === array_keys( $item['detail'] ), 'El detalle sólo guarda las claves conocidas, en su orden.' );
	f8_check( 7 === $item['detail']['count'] && 12 === $item['detail']['id'], '`count` e `id` son enteros.' );
	f8_check( false === strpos( $item['detail']['widget'], '<' ) && false === strpos( $item['detail']['note'], '<img' ), 'Las etiquetas se quitan al guardar.' );
	f8_check( mb_strlen( $item['detail']['note'] ) <= Report::NOTE_MAX, '`note` se recorta a 200 caracteres.' );
	Report::begin( 'run' );
	Report::add( $doc, 'core.a', array( 'widget' => array( 'x' ), 'name' => new \stdClass() ) );
	$raw = Report::take();
	f8_check( ! isset( $raw['docs'][ $doc ]['items'][1]['detail']['widget'] ), 'Un valor que no es escalar se descarta.' );
	// Valores con HTML que llegan al informe aunque sea por otra vía (un informe guardado manipulado): render() escapa.
	$forged = array( 'docs' => array( $doc => array( 'items' => array( array( 'code' => 'core.a', 'detail' => array( 'widget' => $xss, 'element' => $xss, 'name' => $xss, 'target' => $xss, 'type' => $xss, 'note' => $xss ) ) ), 'more' => 0 ) ), 'omitted' => 0, 'site' => array( 'items' => array( array( 'code' => 'core.a', 'detail' => array( 'name' => $xss, 'note' => $xss ) ) ), 'more' => 0 ) );
	$html   = Report::render( $forged, 'site' );
	f8_check( false === strpos( $html, '<img' ), 'Ningún campo sale sin escapar (detalle y título).' );
	f8_check( substr_count( $html, '&lt;img src=x onerror=alert(1)&gt;' ) >= 7, 'Cada campo (título, widget, elemento, nombre, destino, tipo y nota) aparece escapado: ' . substr_count( $html, '&lt;img' ) );
	foreach ( array( 'widget', 'elemento', 'nombre', 'destino', 'tipo' ) as $field ) {
		f8_check( str_contains( $html, $field . ': &lt;img src=x onerror=alert(1)&gt;' ), 'El campo «' . $field . '» se pinta escapado.' );
	}
	f8_check( str_contains( $html, '— &lt;img src=x onerror=alert(1)&gt;' ), 'La nota se pinta escapada.' );
	f8_check( str_contains( $html, '>&lt;img src=x onerror=alert(1)&gt;</a>' ), 'El título del documento se pinta escapado dentro de su enlace.' );
	f8_check( str_contains( $html, esc_html( 'Sitio' ) ) && str_contains( $html, 'nombre: &lt;img' ), 'Los elementos del sitio también se escapan.' );
	f8_check( str_contains( $html, 'Descartado' ) || str_contains( Report::render( array( 'docs' => array( $doc => array( 'items' => array( array( 'code' => 'core.b', 'detail' => array() ) ), 'more' => 0 ) ) ), 'site' ), 'Descartado &lt;b&gt;B&lt;/b&gt;' ), 'Las etiquetas del catálogo también se escapan.' );
	f8_check( str_contains( $html, 'href="https://s1.test/wp-admin/post.php?post=' . $doc . '&amp;action=edit"' ), 'El enlace lo construye el informe (get_edit_post_link) y va escapado.' );
	$cli = Report::render( $forged, 'cli' );
	f8_check( false === strpos( $cli, '<a ' ) && str_contains( $cli, '&lt;img' ), 'En WP-CLI no hay enlaces, y todo sigue escapado.' );
	$net = Report::render( $forged, 'network' );
	f8_check( str_contains( $net, 'Convertido: 2' ) && false === strpos( $net, 'img' ), 'En la red sólo van los recuentos por sección.' );

	// Nombres privados: un superadministrador en la red; quien gestiona el sitio fuera de ella.
	$secret = array( 'docs' => array(), 'omitted' => 0, 'site' => array( 'items' => array( array( 'code' => 'core.secret', 'detail' => array( 'name' => 'Ana Pérez', 'count' => 2 ) ) ), 'more' => 0 ) );
	f8_check( str_contains( Report::render( $secret, 'site' ), 'Ana Pérez' ), 'En un sitio individual, el administrador ve el nombre.' );
	f8_user( 2, array( 'caps' => array( 'editor' => true ) ) );
	wp_set_current_user( 2 );
	f8_check( ! str_contains( Report::render( $secret, 'site' ), 'Ana Pérez' ) && str_contains( Report::render( $secret, 'site' ), 'cantidad: 2' ), 'Quien no gestiona el sitio sólo ve el recuento.' );
	$GLOBALS['wp']['multisite'] = true;
	f8_user( 3, array( 'caps' => array( 'administrator' => true ), 'super' => false ) );
	wp_set_current_user( 3 );
	f8_check( ! str_contains( Report::render( $secret, 'site' ), 'Ana Pérez' ), 'En una red, el administrador del sitio no ve el nombre.' );
	wp_set_current_user( 1 );
	f8_check( str_contains( Report::render( $secret, 'site' ), 'Ana Pérez' ), 'El superadministrador sí.' );
	$GLOBALS['wp']['multisite'] = false;

	// Códigos que no casan con la forma se ignoran; uno con forma válida sin catálogo va a «Pendiente de revisar».
	Report::clear();
	Report::begin( 'run' );
	Report::add( $doc, 'evil.code' );
	Report::add( $doc, 'core.<b>' );
	Report::add( 0, 'core.a' );
	Report::site( 'core.desconocido' );
	$t = Report::take();
	f8_check( ! isset( $t['docs'][ $doc ] ) && ! isset( $t['docs'][0] ), 'Un código sin forma válida o sin documento no entra.' );
	f8_check( 'review' === Report::section_of( $t['site']['items'][0] ) && Report::pending( $t ), 'Un código fuera del catálogo se pinta en «Pendiente de revisar».' );

	/* ================================================================ Modo editor y análisis */
	Report::clear();
	Report::begin( 'editor' );
	Report::add( $doc, 'core.a' );
	Report::site( 'core.a' );
	Report::flush();
	f8_check( Report::is_empty( Report::take() ) && Report::is_empty( Report::stored() ), '`begin( editor )` descarta todo (el filtro de K es puro).' );
	Report::begin( 'run' );
	Context::begin( $doc, 'editor' );
	Report::add( $doc, 'core.a' );
	Context::end();
	f8_check( Report::is_empty( Report::take() ), 'Una conversión del editor (Context) tampoco escribe en el informe de la tarea.' );
	Report::begin( 'analysis' );
	Report::add( $doc, 'core.a' );
	Report::flush();
	f8_check( Report::is_empty( Report::stored() ) && ! Report::is_empty( Report::take() ), 'El análisis acumula en memoria y no escribe nada.' );

	/* ================================================================ Vuelco, topes y fusión */
	Report::clear();
	Report::begin( 'run' );
	for ( $i = 1; $i <= 19; $i++ ) {
		Report::add( 100 + $i, 'core.a', array( 'count' => 1 ) );
	}
	f8_check( null === get_option( Report::OPTION, null ), 'Con 19 documentos aún no se vuelca.' );
	Report::add( 120, 'core.a', array( 'count' => 1 ) );
	f8_check( 20 === count( (array) get_option( Report::OPTION )['docs'] ), 'Al documento 20 se vuelca solo.' );
	f8_check( false === f8_autoload( Report::OPTION ), 'Su opción no se carga automáticamente.' );
	Report::add( 121, 'core.a' );
	f8_check( 20 === count( (array) get_option( Report::OPTION )['docs'] ) && 21 === count( Report::take()['docs'] ), 'take() une lo guardado y la memoria sin escribir.' );
	Report::clear();
	f8_check( null === get_option( Report::OPTION, null ), 'clear() borra lo guardado (una tarea nueva).' );
	Report::begin( 'run' );
	for ( $i = 0; $i < Report::MAX_ITEMS + 5; $i++ ) {
		Report::add( $doc, 'core.a', array( 'id' => $i ) );
	}
	$t = Report::take();
	f8_check( Report::MAX_ITEMS === count( $t['docs'][ $doc ]['items'] ) && 5 === $t['docs'][ $doc ]['more'], 'Hasta 12 elementos por documento y el resto contado.' );
	for ( $i = 0; $i < Report::MAX_DOCS + 3; $i++ ) {
		Report::add( 5000 + $i, 'core.a' );
	}
	$t = Report::take();
	f8_check( Report::MAX_DOCS === count( $t['docs'] ) && 3 === $t['omitted'], 'Hasta 100 documentos y los demás contados.' );
	for ( $i = 0; $i < 70; $i++ ) {
		Report::site( 'core.a', array( 'id' => $i ) );
	}
	$t = Report::take();
	f8_check( Report::MAX_SITE_ITEMS === count( $t['site']['items'] ) && 10 === $t['site']['more'], 'El sitio tiene su propio tope (60), que la plataforma necesita.' );
	$a = array( 'docs' => array( 5 => array( 'items' => array( array( 'code' => 'core.a', 'detail' => array( 'id' => 1 ) ) ), 'more' => 2 ) ), 'omitted' => 1, 'site' => array( 'items' => array( array( 'code' => 'core.a', 'detail' => array() ) ), 'more' => 0 ) );
	$b = array( 'docs' => array( 5 => array( 'items' => array( array( 'code' => 'core.a', 'detail' => array( 'id' => 1 ) ), array( 'code' => 'core.b', 'detail' => array() ) ), 'more' => 1 ) ), 'omitted' => 0, 'site' => array( 'items' => array( array( 'code' => 'core.a', 'detail' => array() ) ), 'more' => 0 ) );
	$m = Report::merge( $a, $b );
	f8_check( 2 === count( $m['docs'][5]['items'] ) && 2 === $m['docs'][5]['more'] && 1 === $m['omitted'] && 1 === count( $m['site']['items'] ), 'Unir no cuenta dos veces lo mismo (firma = código + detalle) y conserva lo mayor de lo que no cupo.' );
	f8_check( Report::merge( $m, $m ) === Report::merge( $m, Report::none() ), 'Unir un informe consigo mismo no cambia nada.' );

	/* ================================================================ Pendiente: sólo «review» (y review_if_published) */
	Report::clear();
	Report::begin( 'run' );
	Report::add( $doc, 'core.a' );
	$t = Report::take();
	f8_check( ! Report::pending( $t ), 'Lo convertido no deja nada pendiente.' );
	Report::add( $draft, 'core.b' );
	$t = Report::take();
	f8_check( ! Report::pending( $t ) && 'dropped' === Report::section_of( $t['docs'][ $draft ]['items'][0] ), 'Un descarte en un borrador es información.' );
	Report::add( $doc, 'core.b' );
	$t = Report::take();
	f8_check( Report::pending( $t ) && 'review' === Report::section_of( $t['docs'][ $doc ]['items'][0] ), 'El mismo código en una página publicada pasa a «Pendiente de revisar» (review_if_published, C2-27).' );
	$summary = Report::summary( $t );
	f8_check( 1 === $summary['review'] && 1 === $summary['documents'] && true === $summary['pending'], 'El resumen cuenta por sección.' );
	$rev = f8_post( array( 'post_type' => 'revision', 'post_parent' => $doc ) );
	$html = Report::render( array( 'docs' => array( $rev => array( 'items' => array( array( 'code' => 'core.a', 'detail' => array() ) ), 'more' => 0 ) ), 'omitted' => 0, 'site' => array( 'items' => array(), 'more' => 0 ) ), 'site' );
	f8_check( str_contains( $html, 'revision.php?revision=' . $rev ) && str_contains( $html, 'Revisión de' ), 'Una revisión enlaza a sus revisiones, nunca al editor.' );
	f8_check( 3 === Report::count_code( array( 'docs' => array( 1 => array( 'items' => array( array( 'code' => 'core.a', 'detail' => array( 'count' => 2 ) ), array( 'code' => 'core.b', 'detail' => array() ) ) ) ) ), 'core.' ) && 2 === Report::count_code( array( 'docs' => array( 1 => array( 'items' => array( array( 'code' => 'core.a', 'detail' => array( 'count' => 2 ) ) ) ) ) ), 'core.a' ), 'count_code() cuenta por código o por prefijo.' );
	f8_check( '' === Report::render( Report::none(), 'site' ), 'Un informe vacío no pinta nada.' );
	$labels = Report::section_labels();
	f8_check( 'Pendiente de revisar' === $labels['review'] && 'Sin equivalente' === $labels['dropped'] && 5 === count( $labels ), 'Las cinco secciones, en español.' );
	Report::reset_catalog();

	/* ================================================================ Runner: informe guardado, regla anterior y red */
	f8_load_migration( array( 'translator', 'native', 'migration', 'retire', 'aliases', 'runner' ) );
	\Digitalisimo\Elements\Legacy\Aliases::set_map( array() );
	$runner = \Digitalisimo\Elements\Legacy\Runner::class;
	f8_check( ! $runner::has_pending( array( 'report' => array( 'plugins' => array() ), 'warnings' => array() ) ), 'Un informe de 1.0.20 sin nada usa la regla anterior: terminado.' );
	f8_check( $runner::has_pending( array( 'report' => array( 'unsupported' => array( 'bdt-x' => array() ) ) ) ), 'Con widgets sin adaptador, pendiente (regla anterior).' );
	f8_check( $runner::has_pending( array( 'report' => array( 'f8' => array( 'pending' => true ) ) ) ) && ! $runner::has_pending( array( 'report' => array( 'f8' => array( 'pending' => false ) ) ) ), 'Un informe de F8 queda pendiente sólo con «Pendiente de revisar».' );
	f8_check( ! $runner::has_pending( array( 'report' => array( 'f8' => array( 'pending' => false ), 'plugins' => array( 'ps' => array( 'late' => true, 'network' => true, 'site' => false ) ) ) ) ), 'Prime Slider activado en toda la red (lo retira la red al final) no deja el sitio pendiente.' );
	// Informe final con F8: el runner guarda el resumen y la pantalla pinta el detalle escapado.
	f8_reset();
	\Digitalisimo\Elements\Legacy\Platform\Registry::flush_cache();
	$page = f8_post( array( 'post_title' => $xss ) );
	Report::clear();
	Report::begin( 'run' );
	Report::add( $page, 'core.residual', array( 'element' => $xss ) );
	Report::flush();
	$state = $runner::fresh( true );
	$state['done']   = true;
	$state['stage']  = 'finish';
	$state['report'] = array( 'f8' => array( 'v' => 1 ) + Report::summary( Report::stored() ), 'plugins' => array() );
	$html  = $runner::render( $state );
	f8_check( str_contains( $html, 'Pendiente de revisar' ) && false === strpos( $html, '<img' ) && str_contains( $html, '&lt;img' ), 'El informe del sitio pinta el detalle de F8 escapado.' );
	f8_check( $runner::has_pending( $state ), 'Y queda pendiente por core.residual.' );
	// Columnas de la red, leídas del resumen guardado.
	$job = array( 'started' => 1, 'done' => true, 'finished' => 2, 'sites' => array( 1 ), 'skipped' => array(), 'deactivated' => array(), 'warnings' => array(), 'status' => array( 1 => array( 'status' => 'done', 'counts' => array( 'converted' => 2, 'copies' => 1 ), 'pending' => true, 'f8' => array( 'created' => 3, 'approx' => 1, 'review' => 1 ), 'name' => $xss, 'url' => 'https://s1.test/', 'tool' => 'https://s1.test/wp-admin/tools.php' ) ) );
	$net = $runner::render_network( $job );
	f8_check( str_contains( wp_strip_all_tags( $net ), 'creado: 3, con diferencias: 1, pendientes: 1' ) && str_contains( wp_strip_all_tags( $net ), 'plantillas: 1' ), 'La pantalla de red muestra las columnas de F8 de cada sitio.' );
	f8_check( false === strpos( $net, '<img' ), 'Y el nombre del sitio, escapado.' );

	f8_done( 'DIGITALÍSIMO Migración: informe de F8', 70 );
}
