# Migrar un sitio o una red a DIGITALÍSIMO Elements

Guía operativa para dejar de usar Elementor, Elementor Pro, PRO Elements y Element Pack Pro sin que cambie lo que ven los visitantes. Complementa el [objetivo de migración](ELEMENT_PACK_MIGRATION_OBJECTIVE.md) (qué se adaptó y cómo se validó), la [tabla de decisiones](element-pack-decisions.md) (qué pasa con cada widget de Element Pack) y [ELEMENTOR_CORE.md](ELEMENTOR_CORE.md) (el núcleo de Elementor incluido en DIGITALÍSIMO Elements).

DIGITALÍSIMO Elements no contiene ni nombra nada de Element Pack. Toda la compatibilidad y la migración están en **DIGITALÍSIMO Migración** (`digitalisimo-migration`), un plugin que sólo se instala mientras dura la migración.

## Un solo paso: Migrar

1. Instala y activa DIGITALÍSIMO Elements y DIGITALÍSIMO Migración (en una red, los dos en toda la red). Haz un respaldo.
2. Pulsa **Migrar**:
   - En un sitio: **Herramientas → Migrar a DIGITALÍSIMO Elements → Migrar**.
   - En una red: **Red → Ajustes → Migrar a DIGITALÍSIMO Elements → Migrar toda la red**. La pantalla dice que modifica todos los sitios de la red.
   - Desde la terminal: `wp digitalisimo-migration --run --url=https://sitio/` o `wp digitalisimo-migration --run --network`.
3. Lee el informe que queda en la misma pantalla. Lo que la migración no puede resolver sola aparece en «Pendiente de revisar», con el enlace a cada página.
4. Cuando hayas comprobado el sitio, borra desde Plugins los que el informe da por listos para borrarse y, al final, DIGITALÍSIMO Migración.

No hay más botones ni pasos. Si cierras la pantalla, la migración sigue con el WP-Cron del sitio y la pantalla la retoma al volver. Pulsar Migrar otra vez es seguro: sólo hace lo que quedó pendiente.

## Qué hace Migrar en cada sitio

La migración es una tarea por etapas que avanza en peticiones cortas (`legacy/class-runner.php`). Un cerrojo atómico, propio de cada petición y renovado con cada documento, impide que dos pestañas, el WP-Cron o la terminal la ejecuten a la vez. La continuación queda programada en el WP-Cron antes de cada paso, así que una petición cortada no detiene la migración. Un proceso que arrancó antes de que la migración desactivara plugins (y por eso aún los tiene cargados) se pausa sin trabajar:

1. **Desactiva los plugins sustituidos** en el sitio: Element Pack (Pro o Lite), Elementor Pro, PRO Elements y Elementor. Elementor sólo se desactiva si el núcleo incluido en DIGITALÍSIMO Elements puede sustituirlo; si el plugin Elementor era más nuevo que el núcleo incluido, el informe lo dice. Nada se borra: los archivos siguen ahí, y Element Pack debe seguir instalado mientras dura la conversión, porque se leen algunos de sus ajustes (destinatario del formulario de contacto, clave y audiencia de Mailchimp, extensión de visibilidad del sitio) y se copian de su carpeta las figuras de máscaras y separadores. La petición que desactiva termina ahí: la siguiente ya corre sin esos plugins, con el núcleo incluido y los widgets propios cargados.
2. **Recupera conversiones antiguas**: páginas sobrescritas por conversiones de Migración 1.0.13 o anterior (desde su última revisión guardada o su copia original), revisión a revisión y sólo en las páginas que guardan una copia de aquellas conversiones; y, una sola vez por sitio, reglas de visibilidad perdidas con Migración 1.0.10 o anterior (desde la copia original; los documentos que convierte esta migración nunca cuentan).
3. **Convierte el contenido de Element Pack** de cada documento al formato propio (`Native`), con copia del original en `_digitalisimo_ep_backup`:
   - Deja cada widget en su destino propio (DIGITALÍSIMO Elements o PRO Elements) sólo con ajustes que ese widget conoce. Los estilos que sólo tenía Element Pack pasan al CSS personalizado del elemento.
   - Pasa a rasgos propios lo que el adaptador resolvía en tiempo de ejecución: categorías y etiquetas en Entradas, partes y número de productos en WooCommerce, origen ACF, diapositivas de plantilla, plantillas del Dynamic Grid y Carousel (se duplican una sola vez como «Loop Item» para el Loop nativo).
   - En cualquier elemento, pasa las reglas de visibilidad, las figuras decorativas y el enlace del elemento a sus ajustes propios, y los efectos de las extensiones (filtro de fondo, degradado de texto, sombra de imagen, flotación, anotación) al CSS personalizado. Los contadores de visitas y sesiones de esas reglas empiezan de cero. Las reglas se conservan siempre; si el sitio tenía Visibility Controls apagada expresamente, quedan guardadas pero desactivadas.
   - Copia, saneados, los SVG de las máscaras y separadores de Element Pack que el sitio usa a sus propios archivos (`uploads/digitalisimo-elements/`, en una red cada sitio los suyos). La máscara nativa pasa a «Personalizada» con esa copia, «Image Masking» pasa al CSS personalizado del elemento y el separador pasa al separador propio del sitio (`digi-mezquita`, `digi-navidad`…). Si una figura ya no tiene archivo, se retira para que la imagen siga visible. La máscara de la imagen atómica del editor v4 (`e-image`) no se traslada.
4. **Convierte las revisiones y los autoguardados** con contenido de Element Pack, cada uno en su propio registro y con su propia copia: restaurar una revisión después de borrar Element Pack ya no devuelve widgets que dejaron de existir. Se deja sin convertir la revisión que delata una página sobrescrita que no pudo recuperarse, para que esa página siga listada hasta que alguien la revise y la guarde.
5. **Actualiza los widgets fusionados**. Los widgets propios que DIGITALÍSIMO Elements fusionó en otro (Carrusel y Cuadrícula de marcas y de logotipos en Logotipos, Botón creativo en Botón, Menú móvil en Navegación con iconos, Alternador de contenido en Pestañas) siguen existiendo como alias ocultos. Migrar pasa cada uno al widget fusionado con la misma traducción de ajustes que el alias aplica al pintarse, y reescribe en el CSS personalizado del propio elemento las clases de antes (`.digi-brand-carousel__slide`) y la del envoltorio (`elementor-widget-digitalisimo-brand-carousel`) con las del fusionado. Si otro CSS, marcado o código del sitio nombra esas clases, el envoltorio del alias o el del widget fusionado (CSS adicional y estilos globales del tema activo, hojas, scripts y plantillas PHP del tema y de los mu-plugins, fragmentos de Simple Custom CSS and JS o WPCode, bloques y plantillas del editor de sitio, otros elementos, ajustes de página o del kit, opciones de plugins como el CSS personalizado de DIGITALÍSIMO SEO), ese tipo de alias no se convierte en el sitio: se sigue viendo igual y el informe dice dónde está ese código. Tampoco se convierte el alternador cuyo CSS propio alcanzaría a las plantillas de sus paneles, ni un CSS propio que alcance a elementos hermanos. Cambia ese código a las clases nuevas y vuelve a pulsar Migrar.
6. **Vacía las cachés** de Elementor (CSS generado, HTML de los elementos y recursos de cada página) y deja el informe.

Un documento, o una etapa sin documentos, cuya conversión tumba la petición dos veces se salta y queda listado; los demás siguen, y pulsar Migrar otra vez lo reintenta. Tras migrar, cada documento convertido no contiene ningún identificador de Element Pack ni de un alias y se pinta sólo con DIGITALÍSIMO Elements.

### Lo que queda para una persona

- **Widgets de Element Pack sin equivalente**: dejan de mostrarse sin Element Pack (criptomonedas, redes sociales con API retirada, controles remotos de carruseles…). El informe lista cada uno con su motivo, su alternativa y las páginas que lo usan; sustitúyelos en el editor o acepta que desaparezcan.
- **Páginas sobrescritas sin origen** del que recuperarlas: revísalas en el editor y guárdalas.
- **Alias que se quedan** por CSS ajeno que nombra sus clases (ver la etapa 5).
- **Plugins que siguen activos**, con el motivo (activado en toda la red cuando se migró sólo un sitio, núcleo incluido no disponible, usuario sin permiso para desactivar plugins). Element Pack no se da por borrable mientras quede contenido suyo por convertir ni, en una red, hasta que el resumen de la red lo diga, porque la conversión lee sus ajustes y copia sus figuras.

Requisitos que la migración no puede cubrir: los plugins de los que dependen los widgets siguen activos (WooCommerce, Secure Custom Fields o ACF, bbPress, BdThemes Testimonials, Give, Charitable, EDD, FooEvents, los formularios de terceros…), porque la migración reproduce el widget, no el plugin que le daba los datos; y los widgets de PRO Elements que sirven de destino no están desactivados en Elementor → Ajustes → Funciones.

## En una red

Cada petición de WordPress carga los plugins del sitio que la atiende, así que convertir un sitio desde otra petición podría hacerse sin sus plugins (WooCommerce activo sólo allí, por ejemplo). Por eso **Migrar toda la red**:

1. Si DIGITALÍSIMO Elements está activado en toda la red (si no, algún sitio se quedaría sin constructor; el informe lo explica y no toca nada de la red): desactiva en cada sitio Elementor Pro y PRO Elements, sin los que DIGITALÍSIMO Elements no carga los widgets de los que heredan los adaptadores, y después desactiva en toda la red lo activado en toda la red. Sólo los sitios de la red en curso.
2. Encola la migración de cada sitio con DIGITALÍSIMO Elements, autorizada a desactivar sus plugins por quien la lanzó. Los sitios sin DIGITALÍSIMO Elements no se tocan y se listan.
3. La pantalla conduce cada sitio, uno a uno, con peticiones a la administración de ese sitio: cada uno se migra con sus propios plugins. En una red con subdominios, el origen de la administración de red puede leer esas respuestas sólo para la acción de la migración. Un sitio que el navegador no alcanza (dominio propio) completa su migración con su WP-Cron o al abrir su herramienta, y la pantalla de red lo sigue mostrando hasta que termina.
4. Al terminar, el resumen dice qué plugins ya no están activos en ningún sitio y pueden borrarse desde Red → Plugins (Element Pack, sólo si ningún sitio tiene contenido suyo por convertir).

La herramienta de cada sitio sigue disponible y hace lo mismo sólo en ese sitio.

## Restaurar

`wp digitalisimo-migration --revert --url=https://sitio/` devuelve cada documento a su copia original (`_digitalisimo_ep_backup`), también las revisiones convertidas. Reactiva Element Pack (o deja DIGITALÍSIMO Migración activo, que pinta los widgets heredados) para que esos documentos se vean. Si algo del núcleo falla, reactivar el plugin Elementor hace que vuelva a mandar él sin perder nada.

## Validación disponible en el repositorio

- `bash tests/playground/migrate.sh`: red de Playground con Elementor en toda la red y Element Pack en cada sitio; firma cada página, pulsa en Chrome el único botón de la pantalla de red y comprueba que cada sitio queda sin esos plugins, sobre el núcleo incluido, con el contenido de Element Pack, sus revisiones y los alias convertidos, con las mismas páginas (en los alias, el mismo estilo calculado en cada elemento) y con el alias que el CSS del sitio impide convertir intacto y explicado en el informe.
- `php tests/migration-runner.php`: plugins que se retiran, conversión de alias (clases, ajustes, CSS que la impide), etapas, pausa, cerrojo, reintentos, informe, red y origen permitido.
- `bash tests/playground/legacy-ab.sh` (con `FAKE_PLUGINS=1 EXTRA_PLUGINS=woocommerce,secure-custom-fields`, y `EXTRA_PLUGINS=bbpress` aparte): compara cada widget de Element Pack activo y sin él, en el sitio principal y un subsitio, el guardado desde el editor, la conversión, el documento convertido sin DIGITALÍSIMO Migración y la restauración.
- `bash tests/playground/interaction.sh`: widgets con JavaScript en Chrome real. `bash tests/playground/form-submit.sh`: envío real de los formularios heredados. `bash tests/playground/migration-report.sh`: pantallas de red y de sitio.
