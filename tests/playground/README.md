# A/B de adaptadores de Element Pack

`legacy-ab.sh` comprueba en WordPress Playground (red Multisite con un subsitio) que una página guardada con un widget `bdt-*` se ve igual con Element Pack activo que sólo con DIGITALÍSIMO Elements. Necesita la copia local de referencia en `reference/bdthemes-element-pack/`, que no se versiona ni se publica.

```bash
bash tests/playground/legacy-ab.sh                  # todos los widgets con casos en tests/legacy/
bash tests/playground/legacy-ab.sh bdt-accordion    # uno o varios, separados por comas
```

Fases por widget, caso y sitio:

- **A**: Element Pack 9.9.1 activo con todos sus módulos encendidos; se guarda la firma de contenido (textos visibles, enlaces e imágenes) y el `_elementor_data` original.
- **B**: Element Pack desactivado en la red; la misma página se muestra con el adaptador. Se compara la firma, se verifica que el editor recibe los ajustes ya traducidos y que el CSS regenerado contiene los fragmentos de `_expect_css`.
- **C**: la herramienta de migración traduce el documento; la firma debe seguir igual.
- **E**: con DIGITALÍSIMO Migración desactivado, el documento convertido se pinta sólo con DIGITALÍSIMO Elements y no conserva identificadores heredados.
- **D**: se restaura la copia y el `_elementor_data` debe ser idéntico al original.

Cada `tests/legacy/bdt-*.json` es un objeto `caso → ajustes guardados`. `_expect_css` lista fragmentos de CSS esperados y `_known` documenta diferencias deliberadas por tipo (`text`, `links`, `images`), que se informan pero no fallan. La caché de elementos de Elementor se desactiva en la prueba: Element Pack no marca como dinámicos sus widgets.

Opciones de los casos (`tests/legacy/*.json`):

- `_widget`: tipo de widget del caso cuando el archivo no es `bdt-*` (extensiones `ext-*.json`).
- `_section`: ajustes de la sección que contiene el widget (figuras del Shape Builder en secciones).
- `_expect_html`: fragmentos que deben aparecer en el HTML de la fase B.
- `_acf`: valores de ACF que se guardan en la página del caso con `update_field()` (clave del campo => valor; requiere `EXTRA_PLUGINS=secure-custom-fields`).
- `BLOG=1` o `BLOG=2` limita las cuatro fases a un sitio, en su propio proceso: bbPress arrastra estado entre sitios y entre shortcodes de un mismo proceso, así que sus listas se validan sueltas y, si hace falta, por sitio.
- Fase B, guardado desde el editor: cada página se vuelve a guardar con `ajax_save()` a partir de lo que carga el editor y debe seguir pintando lo mismo con el widget heredado aún en el documento (`editor_resave`); después se restaura el `_elementor_data` original para las fases C y D.
- `_options`: opciones del sitio que se fijan sólo durante el caso y se restauran después (por ejemplo, la clave de Google Maps de Element Pack).
- `__TEMPLATE__` y `__POST__` en cualquier valor se sustituyen por el ID de la plantilla de Elementor y de la entrada de prueba que crea `legacy-setup.php` en cada sitio, junto con el menú «Principal».

Con `FAKE_PLUGINS=1`, `legacy-ab.sh` monta los plugins de `tests/playground/fake-plugins/` en las rutas que Element Pack exige a sus widgets de integración (Contact Form 7, Give, Charitable, EDD, WPForms…). Sólo registran el shortcode del plugin e imprimen sus atributos, así que la fase A y la B se comparan por el shortcode exacto que arma cada una. No se publican.

Con `EXTRA_PLUGINS=slug,…`, `legacy-ab.sh` instala esos plugins reales de wordpress.org y los activa en la red; `legacy-content.php` crea después su contenido de prueba en cada sitio y deja sus IDs como marcadores: `bbpress` (foro, tema, respuesta y etiqueta: `__BBP_FORUM__`, `__BBP_TOPIC__`, `__BBP_REPLY__`, `__BBP_TAG__`) `secure-custom-fields` (grupo «Digi A/B» en páginas: repetidor `field_digi_ab_faq` con `question`, `answer`, `photo` y `target`, galería `field_digi_ab_gallery` y dos adjuntos: `__ACF_IMAGE_1__`, `__ACF_IMAGE_2__`) y `woocommerce` (instala WooCommerce en cada sitio y crea cuatro productos con categoría, uno destacado y otro en oferta: `__WC_PRODUCT__`). Por ejemplo:

```bash
EXTRA_PLUGINS=woocommerce bash tests/playground/legacy-ab.sh bdt-wc-products,bdt-wc-add-to-cart
```

`migration-report.sh` comprueba en Playground Multisite la pantalla de red (Red → Ajustes → Migrar Element Pack), que sólo informa por sitio y enlaza a la herramienta de cada uno, y la del sitio, que explica el motivo de cada widget sin adaptador.

`form-submit.sh` envía en Playground Multisite (sin Element Pack) una página con `bdt-contact-form`, otra con `bdt-webhook-form` y otra con `bdt-mailchimp` por el AJAX de PRO Elements en cada sitio, y comprueba el correo (destinatario, asunto, `Reply-To`, mensaje), la petición al webhook (URL y campos) y la llamada a la API de Mailchimp (audiencia y clave que Element Pack guardaba en sus ajustes, correo y `FNAME`).

`interaction.sh` arranca Playground en modo servidor (sitio simple, porque Multisite no admite puertos personalizados) y, con Chrome sin interfaz (`puppeteer-core`, ruta en `PUPPETEER_PATH`), comprueba en una página por widget heredado que el acordeón abre su segundo elemento, las pestañas cambian de panel, el deslizador propio, el carrusel de logos, el carrusel de medios y el deslizador de diapositivas avanzan con la flecha, la marquesina se pausa, el comparador de imágenes responde al teclado, el menú vertical despliega su submenú, el botón de desplazamiento vuelve al inicio y el panel lateral se abre con su botón y se cierra con Escape; en cada página exige que no haya errores de JavaScript, recursos 404 ni widgets ocultos por una animación de entrada que no empieza. Los casos están en `interaction/cases.json` (`__SITE__` y `__TEMPLATE__` se sustituyen al crearlos).

## Núcleo de Elementor incluido

DIGITALÍSIMO Elements trae el núcleo de Elementor (`digitalisimo-elements/elementor/`). Por defecto los arneses instalan además el plugin Elementor de wordpress.org, que manda y deja inactivo el núcleo incluido. Con `CORE=bundled`, `legacy-ab.sh`, `interaction.sh`, `form-submit.sh` y `migration-report.sh` no lo instalan y todo corre sobre el núcleo incluido: es el escenario final de un sitio que ya retiró Elementor, Elementor Pro y Element Pack. En `legacy-ab.sh` Element Pack se activa en la fase A sobre ese mismo núcleo.

```bash
CORE=bundled FAKE_PLUGINS=1 EXTRA_PLUGINS=woocommerce,secure-custom-fields bash tests/playground/legacy-ab.sh
CORE=bundled bash tests/playground/interaction.sh
```

`interaction.sh` abre además el editor de Elementor como administrador y exige panel, vista previa y ninguna respuesta de error; un mu-plugin de prueba deja en `fatal.log` los errores fatales de cada petición, también las AJAX del editor.

`core-switch.sh` crea en una red páginas con widgets de Elementor, Pro y DIGITALÍSIMO bajo el plugin Elementor, lo borra (con sus traducciones) y comprueba que el núcleo incluido las pinta igual (texto, enlaces, imágenes y CSS), en español, con un kit por sitio, aceptando los complementos que exigen `elementor` y sirviendo desde el núcleo las rutas antiguas guardadas en el contenido. Cada sitio se pinta en su propia petición: Elementor guarda en caché los documentos por ID sin distinguir el sitio.

## Identidad propia del constructor

`brand-audit.sh` arranca un sitio en español sólo con DIGITALÍSIMO Elements y su núcleo incluido, y lo recorre en Chrome en español y en inglés:

- Todas las pantallas del menú del constructor, el escritorio, los plugins, las páginas y el editor de bloques.
- La app de kits.
- El editor del constructor: su pantalla de carga, su menú y la biblioteca de plantillas.
- El frontal, como administrador y como visitante.

Anota cada aparición visible de «Elementor» (textos, títulos, atributos accesibles, imágenes y logotipos, incluidos el icono del menú y los SVG de las barras superiores) y la meta `generator` del HTML público. Guarda capturas y el marcado de las barras en `$OUT` y falla si queda alguna. Los registros técnicos de «Información del sistema» (rutas y clases PHP) no cuentan: son identificadores.

Todos los arneses cargan `playground-cleanup.sh`: al salir borran las copias temporales del sitio (`node-playground-cli-site-*`, unos 400 MB) que Playground deja cuando su proceso termina por la fuerza, sin tocar las de otra prueba en marcha.

## Condiciones de visualización

`conditions.sh` crea páginas reales con los tres motores (Display Conditions nativo, reglas propias `digitalisimo_visibility_*` y reglas heredadas `ep_display_conditions_*`), en widgets y en contenedores con hijos, con «Mostrar» y «Ocultar», con reglas de sesión, tipo de contenido, página especial y URL, combinadas con el enlace del elemento y las figuras, dentro de plantillas incrustadas (acordeón propio y shortcode de plantilla), y las pinta como visitante, con sesión, otra vez como visitante y en la vista previa del editor, con la caché de elementos de Elementor desactivada y activada. Comprueba que lo oculto no aparece en el HTML y lo visible sí; que quien llena la caché primero (un administrador) no decide lo que ven los visitantes; y que quien llena la caché primero (un administrador) no decide lo que ven los visitantes. `probe-save.php`, en una segunda petición PHP (como el guardado AJAX real), guarda cada página desde el editor como administrador con la caché activa y comprueba que el primer visitante no ve lo que sólo él podía ver. `probe-cache.php` se ejecuta en un proceso PHP limpio por página (una petición de visitante con su consulta principal y `template_redirect`) y comprueba que la página con elementos condicionados pide quedar fuera de la caché de página antes de la salida (`DONOTCACHEPAGE` y, si el SAPI las expone, las cabeceras HTTP) y que una página sin condiciones no lo pide.

`legacy-repair.sh` reproduce el caso real de una portada estática con una regla heredada «ocultar en la página de inicio estática» y la opción `element_pack_elementor_extend` guardada sin la clave de Visibility Controls (activa por defecto en Element Pack): el motor heredado oculta el título en la portada y lo muestra en una interna, la conversión conserva el comportamiento, un documento convertido que perdió las reglas se repara desde su copia original (`Migration::repair_conditions`) y, con la extensión apagada expresamente, la conversión guarda las reglas desactivadas; un documento que sólo conserva un widget sin adaptador se cuenta aparte (`Migration::classify`) y no vuelve a entrar en los lotes.

