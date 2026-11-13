# Kit de ventas de DIGITALÍSIMO Elements

DIGITALÍSIMO Elements trae el kit de ventas de DIGITALÍSIMO: ocho plantillas de página para un embudo de venta, su guía
de venta y tres herramientas del servidor MCP del núcleo incluido (`POST /wp-json/elementor/mcp`, apagado por defecto
en cada sitio) para que un asistente cree páginas **en borrador** con ellas. Encargo del operador (2026-11-13, vía la
sesión del cerebro): «Transfórmalo a plantilla digitalisimo-elements para que nuestro MCP pueda generarlas».

El código vive en `digitalisimo-elements/modules/digitalisimo-sales/` (cargado desde `pro-elements.php`) y nunca se
edita nada dentro de `digitalisimo-elements/elementor/`.

## De dónde sale cada cosa

| Pieza | Fuente |
| --- | --- |
| Las ocho plantillas | Kit de ventas **v3 genérico** (`OneDrive: DIGITALISIMO/Plantillas/Landing Page Ventas/Kit de ventas DIGITALISIMO v3/`, generador con `--generico`, `PERSONA_GENERICA`), probado por la sesión del cerebro (Elements `acdf3254`: 8 plantillas importadas y 16 vistas limpias). La versión con la persona de DIGITALÍSIMO (`PERSONA_DIGITALISIMO`) es sólo para los sitios propios y **no entra en Elements**: el generador del repositorio sólo trae la genérica y `tests/elements-sales-kit.mjs` falla si «Daniel» aparece en una plantilla, el manifiesto o la guía. |
| El orden de la página y la guía (`guide.md`) | Cerebro, `oficio/ventas-en-linea.md` en el commit **`06ff42f`** (sobre todo «El orden de una página de venta», 26 bloques; también las reglas, la tabla por caso y las fórmulas), sin nombres de otras empresas. |
| El generador y el validador | `scripts/sales-kit/` (`kit_lib.py`, `kit_paginas.py`, `kit_validar.py`): el generador del kit v3 adaptado (abajo). El commit `6f009884` guarda la copia tal cual del generador v2, antes de adaptarlo. |

Cambiar el kit es cambiar el generador y volver a escribir las plantillas:
`python3 -I scripts/sales-kit/kit_paginas.py` (escribe `templates/sections/`, `templates/containers/` y
`templates/manifest.json`) y `python3 -I scripts/sales-kit/kit_validar.py`. Nadie edita a mano una plantilla
generada: la CI corre el generador con `--check`.

## Las plantillas

| Id | Plantilla | Para qué |
| --- | --- | --- |
| `venta-completa` | 01 Página de venta completa | La carta de venta larga: los 26 bloques de la guía en su orden (32 secciones), con `#oferta` y `#historias` |
| `venta-corta` | 02 Página de venta corta | Promesa, beneficios, quién está detrás, para quién es, testimonios, oferta, garantía y preguntas |
| `captacion` | 03 Captación | Registro a una clase, un reto o un recurso, con formulario (guarda, avisa por correo y lleva a `/gracias-registro/`) |
| `gracias-registro` | 04 Gracias por registrarte | Los tres pasos después del registro |
| `carrito` | 05 Carrito | Formulario y forma de pago junto a la oferta, con el extra de un clic (lleva a `/pago/`) |
| `oferta-unica` | 06 Oferta única después de la compra | Un complemento, sí o no |
| `gracias-compra` | 07 Gracias por tu compra | Bienvenida, acceso y soporte |
| `prueba-social` | 08 Bloques de prueba social | Siete bloques para copiar a otra página |

Lo que va entre `[corchetes]` se llena (un placeholder es un tramo de primer nivel: los de dentro de otro son su
fórmula). Los enlaces de los botones son rutas del propio sitio (`/carrito/`, `/pago/`, `/gracias-registro/`,
`/pago-complemento/`, `/gracias-compra/`, `/acceso/` y los avisos legales), que se crean aparte; el manifiesto dice a
qué página espera cada una.

### Dos variantes, una sola fuente

El generador arma cada página una vez, con secciones y columnas, y de ahí escribe:

- `templates/sections/`: secciones y columnas clásicas, para los sitios sin el experimento de contenedores (los
  instalados antes de 3.16 que no lo encendieron);
- `templates/containers/`: contenedores flexibles (`a_contenedores()`): cada sección es un contenedor en caja con el
  mismo ancho, en fila si tenía varias columnas (que en celular se apilan, `flex_direction_mobile: column`, como las
  columnas de una sección) y cada columna un contenedor hijo con su ancho en % (100 % en celular), su fondo, relleno,
  borde, sombra y alineación vertical; una sección de una sola columna sin estilo es un solo contenedor. Los IDs se
  conservan entre variantes.

`create-page` elige sola: contenedores si el sitio tiene activo el experimento de contenedores (lo normal en Elements
4.3), secciones si no. Así la pregunta 169 del cerebro (contenedores o secciones) deja de bloquear. El validador exige
que las dos variantes lleven los mismos widgets, en el mismo orden, con los mismos ajustes e IDs.

### Lo que se adaptó del kit v3

Todo lo demás es el kit v3 genérico tal cual (el validador lo compara al generarlo):

- **Marcas** (abajo): «PayPal», «Stripe», «Zoom / YouTube» y cualquier otra marca pasan a placeholders neutros
  (`[otro medio de pago]`, `[u otra pasarela de pago]`, `[plataforma de la sesión]`).
- **Video:** el enlace de muestra a un video de otro servicio (`…watch?v=VIDEO_ID`) pasa a `[enlace del video]`: sin un
  enlace válido el reproductor no carga nada y la portada de muestra queda a la vista. Se pega en el editor.
- **Grupo y redes:** el botón del grupo lleva `#[enlace del grupo]` (sin enlace a otro sitio) y los iconos sociales
  (con enlaces y logotipos de otras empresas) pasan a un texto neutro que dice dónde agregarlos.
- **Cierre:** los placeholders de la fecha de cierre son propios (`[día de cierre]`, `[mes de cierre]`,
  `[hora de cierre]`) y los contadores llevan una fecha ya pasada (`2000-01-01 00:00`) en lugar de una futura inventada:
  sin la fecha real sólo muestran su mensaje de cierre. No llevan un placeholder porque el widget del contador hace
  `new DateTime()` con su fecha y un texto lo hace fallar (lo encontró el ensayo: el ZIP del kit con un placeholder ahí
  terminaba en un error fatal al importar). La barra de cierre de la 01 y el bloque de urgencia del carrito se marcan
  como urgencia en el manifiesto.
- **Bloques:** cada sección de primer nivel pertenece a un bloque con clave (`promesa`, `oferta`, `pie`…), en el orden
  de la guía.
- **Títulos con resalte:** el kit pintaba parte de un título de otro color con `<span style="color:…">`. El núcleo le
  quita todos los atributos al título que guarda quien no es administrador (`modules/content-sanitizer/` y
  `Widget_Heading::sanitize()`), y quien crea las páginas es una editora (`edit_pages`): el resalte salía sin color.
  Ahora el título lleva `<span>` sin atributos y el color va en el CSS propio del widget (Avanzado → CSS personalizado,
  `selector span{color:…}`), que el núcleo no toca. El validador rechaza un título con atributos o un resalte sin su
  color, y el ensayo pinta en Chrome las páginas que crea una editora (y una sin `unfiltered_html`).

## Marca

Regla de `AGENTS.md`: ningún texto propio visible nombra ni enlaza a otras empresas. Los nombres que el kit conserva
son **excepciones declaradas** en `tests/playground/brand-audit/marcas.json`, cada una acotada a sus archivos y a su
campo o texto, con su motivo y su decisión; una aparición fuera de ellas, o una excepción sin uso, hace fallar
`kit_validar.py` (en la CI desde `tests/elements-sales-kit.mjs`):

- **OXXO, SPEI y Mercado Pago** — excepción explícita del operador (2026-11-13): fijos en las preguntas frecuentes de
  pago y en la línea «Pago 100 % seguro» del resumen de la oferta (01 y 02) y del carrito (05), porque en los sitios de
  México es como paga el comprador. Sólo ahí.
- **Meta y Google** — sólo en el aviso legal del pie («Este sitio no forma parte de Meta Platforms, Inc. ni de Google, y
  no está respaldado por ellos»), que piden las políticas de anuncios a las páginas a las que llevan.
- **WhatsApp** — **pendiente de confirmar por el operador** (no es excepción suya: lo pide el brief del kit). Acotado
  al llamado a la acción y a sus textos y campos concretos: los botones («Hablar por WhatsApp», «Pregúntanos por
  WhatsApp», el del grupo de la clase) y su icono, las frases que invitan a escribir por ese canal, el campo del número
  en los formularios de la 03 y la 05, el nombre de sus bloques en el Navegador, su mención en la guía y el manifiesto y
  la entrada `whatsapp` de `create-page`. Cada excepción de WhatsApp lleva `frases`: cada aparición del nombre debe
  estar dentro de una de ellas, así que «Síguenos en WhatsApp» en un título, o en un botón con otro texto, falla.

La lista vigilada (`nombres`) es amplia: pagos (Visa, Mastercard, American Express, Clip, Kueski, Coppel, PayPal,
Stripe…), tiendas y cursos (Amazon, Shopify, Hotmart…), redes y mensajería (Facebook, Instagram, TikTok, X/Twitter,
LinkedIn, Discord, Telegram, Spotify…) y servicios (Calendly, Gmail, Notion, Zoom…); `tests/elements-sales-kit.mjs`
falla si una de las que pidió el operador deja de vigilarse.

Esos nombres viven sólo en las constantes de `scripts/sales-kit/kit_lib.py` (`MEDIOS_PAGO`, `MEDIOS_PAGO_FAQ`,
`PASARELA`, `PAGO_EFECTIVO`, `AVISO_PLATAFORMAS`, `PLATAFORMA_SESION`, `VIDEO_URL`): fijar o quitar uno es cambiar una
línea (más su excepción en `marcas.json`). La guía, el manifiesto y los textos de las herramientas no nombran ninguna
otra empresa salvo WhatsApp en sus frases (los textos de las herramientas los revisa `tests/elements-sales-mcp.php`). Las plantillas no llevan dominios de los plugins de origen (`vendors.json`) ni el nombre
del proveedor del núcleo.

## Herramientas MCP

En la categoría propia `digitalisimo-sales` («Páginas de venta»), con el mismo patrón que las de tienda
(`docs/ELEMENTS_MCP_WOO.md`): se añaden al registro de abilities del núcleo (o, sin él, con `wp_register_ability()` y
el filtro `elementor/mcp/server/tools`), sólo con el MCP del sitio activo, fuera del proxy del editor, de la REST de
abilities y del servidor genérico. En el cliente se llaman `digitalisimo-sales-list-templates`,
`digitalisimo-sales-create-page` y `digitalisimo-sales-guide`. Todas exigen `edit_pages` (en `WP_Ability` con un
`true` estricto y otra vez al ejecutar); `publish_pages` nunca se consulta. Entrada cerrada
(`additionalProperties: false`, nunca `post_id`).

| Herramienta | Entrada | Devuelve |
| --- | --- | --- |
| `list-templates` (lectura) | `template` (opcional) | Las ocho plantillas (para qué, cuándo, qué mide, secciones, widgets, contador, formulario, enlaces internos y bloques en orden), el embudo, la tabla por caso y la variante del sitio. Con `template`, además sus placeholders: texto exacto, apariciones y tipo (`texto`, `precio`, `dato_real`, `cierre` o `enlace`) |
| `create-page` (escritura, no destructiva) | `template`, `title` (obligatorios), `replacements`, `omit_blocks`, `whatsapp`, `close_date` | El ID, `status: draft`, la variante, las URL de edición y de vista previa, lo llenado, lo ignorado, los placeholders pendientes, los datos reales que hay que comprobar, la urgencia quitada, los bloques quitados, las anclas que quedaron sin destino y las páginas del sitio que faltan |
| `guide` (lectura) | `section` (opcional: `reglas`, `plantillas`, `casos`, `orden`, `formulas`, `paginas`, `antes-de-publicar`) | La guía en Markdown, entera o una parte |

### `create-page`

- **Borrador siempre.** `wp_insert_post()` con `draft` y el guardado normal del documento del constructor: el lienzo
  con `Document::update_settings()` y los elementos con `Document::save()` (el del editor: elementos, versión y el CSS
  de la página borrado para que el siguiente render lo genere con estos elementos; el arreglo del CSS de las escrituras del MCP del núcleo va en otra rama,
  `elements/mcp-css`, y no se duplica). Si algo la cambia de estado al guardar, vuelve a borrador; si el guardado falla,
  la página se borra. Publicar es de una persona.
- **Sin avisos de PHP al guardar como editora.** El saneador del núcleo (`modules/content-sanitizer/`, para quien no
  tiene `manage_options`) recorre los datos del guardado y avisaba dos veces: por los ajustes pasados a `save()`
  (`includes/db.php`, `foreach` sobre una cadena; por eso el lienzo va aparte) y por cada sección, columna o contenedor
  (`module.php`, `widgetType` sin definir). Durante su guardado, `Pages` les pone un `widgetType` vacío antes del
  saneador (prioridad 9 de `elementor/document/save/data`) y lo quita después (prioridad 11); el núcleo no lo guardaría
  de todos modos (guarda los datos de cada elemento sin claves de más).
- **`replacements`:** objeto «[placeholder]» → texto (todas sus apariciones) o lista de textos: uno por aparición en la
  plantilla entera, en el orden y con el total que da `list-templates`, también con `omit_blocks`. Se llena antes de
  quitar bloques, así que ningún valor se corre a otro bloque (13 nombres sin «funciona para ti»: los 3 de ese bloque
  se descartan y los demás quedan en el suyo); los que caen en un bloque quitado, los que sobran y un valor único que
  sólo iba en un bloque quitado se informan en `ignored` (con sus posiciones). Si faltan, las demás apariciones quedan
  pendientes. Una clave sin corchetes se acepta; una que no está en la plantilla o un
  valor vacío se ignoran y se informan. Cada valor se sanea según su campo: texto plano (`sanitize_text_field()`) o
  HTML (`wp_kses_post()` en el editor de texto, el título, el contenido del acordeón y la lista con iconos); dentro de
  un enlace de WhatsApp, el mensaje se llena como texto y se codifica.
- **Nada se inventa** (D-0023, D-0024): la herramienta no genera texto; un placeholder sin dato se queda y se informa.
  Los de tipo `dato_real` que se llenan se devuelven en `real_data` para que una persona los compruebe. La descripción
  le dice al modelo que sólo use datos del usuario.
- **Ni HTML con atributos ni enlaces:** un valor sólo lleva texto y `strong`, `em`, `b`, `i` y `br` sin atributos;
  ningún `<a>`, `<img>`, `<form>`, `<span style>`… (los enlaces sólo existen en la plantilla).
- **Ninguna URL de otro sitio** en un valor, leída como la lee un navegador (entidades decodificadas, `\` como `/`, sin
  tabuladores ni saltos de línea): un esquema pegado a su contenido (`https://otro…`, `http:otro`, `javascript:…`,
  `data:…`, `vbscript:…`, `file:…`), `//otro…` o `www.otro…`. Sólo `wa.me` y el propio sitio. Un punto no basta: «Curso
  de JavaScript: de cero», «ASP.NET», «Socket.io», «hoy.Es tu momento», un dominio suelto en un texto o un correo pasan
  (no son enlaces: un valor no puede llevar uno).
  Los placeholders de tipo `enlace` (video, grupo) no se llenan por aquí: se ponen en el editor.
- **Precios en MXN:** un placeholder de tipo `precio` (detrás de `$`) sólo acepta una cifra (`1,997`, `1997.50`).
- **`whatsapp`:** sólo dígitos (8 a 15, con la clave del país); cambia el número de todos los botones
  (`https://wa.me/<dígitos>`). Sin él, queda el de muestra y se informa.
- **`close_date`** («AAAA-MM-DD HH:MM» en la hora del sitio), en las plantillas con contador o con textos de cierre
  (01, 02 y 05; `close_date: true` en `list-templates`): una fecha válida, futura y a menos de dos años; llena los
  contadores que haya y los placeholders de cierre (la 02 no tiene contador, pero su oferta dice «terminan el [día de
  cierre] a las [hora de cierre]»). Los de cierre no se llenan
  con `replacements`. **Sin `close_date`** se quitan los bloques de urgencia del manifiesto (la barra de cierre de la
  01 y el contador del carrito), como pide el LEEME del kit; los textos que nombran el cierre quedan pendientes.
- **`omit_blocks`:** claves de bloques que no aplican; se quitan enteros y lo demás queda en el orden de la guía (se
  puede quitar un bloque, pero no cambiar el orden). El pie, con los avisos legales, no se quita. Si un botón llevaba a
  un ancla quitada (`#oferta`, `#historias`), se informa.
- **IDs:** todos los de los elementos y de los elementos de listas se regeneran (siete hexadecimales, sin repetirse y
  sin repetir uno de la plantilla).
- **Variante:** la que diga el experimento de contenedores del sitio en ese momento.

## Aviso al importar un ZIP sin la carga de archivos sin filtrar

«Plantillas → Plantillas guardadas → Importar plantillas» con un ZIP extrae los JSON y valida cada uno como archivo sin
filtrar: sin «Habilitar cargas de archivos sin filtrar» (`elementor_unfiltered_files_upload`), el núcleo no importa
ninguno, deja avisos de PHP (`includes/template-library/sources/local.php`, `$extracted_files['files']` sin definir) y
vuelve a la lista sin decir nada. `Sales\Import_Notice` (sólo en la administración) se engancha antes que el núcleo
(`wp_ajax_elementor_library_direct_actions`, prioridad 1, con el mismo nonce y el mismo permiso) y, si la importación
daría cero, vuelve a la biblioteca con un aviso que dice qué falta y dónde se enciende (Configuración → Avanzado, con
enlace para quien puede cambiarla): sin la opción, sin permiso de subir JSON (también con un JSON suelto, donde el
núcleo termina en blanco), sin las extensiones DOM y SimpleXML, o si otro código la apaga. Con la opción encendida, o
con un JSON suelto, no hace nada. El editor del constructor ya pregunta antes de importar.

## Pruebas

- `tests/elements-sales-kit.mjs` (en `validate.yml` y `release.yml`; necesita `python3`): el generador con `--check`,
  el validador sobre las dos variantes, veinte plantillas rotas que el validador debe rechazar (marcas, también las de
  la lista ampliada y WhatsApp fuera del llamado a la acción, enlaces, video, IDs, widgets, paridad, filas, contador,
  relleno, proveedor, la persona propia, títulos con atributos o sin el color de su resalte, y bloques), las marcas
  vigiladas y sus excepciones acotadas, las marcas sólo en las constantes del generador, la ausencia de la persona
  propia en el módulo y que el ensayo de Playground no lleve fechas de cierre fijas.
- `tests/elements-sales-mcp.php` (en `validate.yml` y `release.yml`; PHP 7.4, 8.2 y 8.5): definiciones cerradas,
  catálogo y variantes, guía, placeholders, armado (IDs, reemplazos, saneado, URLs, precios, WhatsApp, cierre,
  urgencia, bloques), borrador siempre, permisos, registro con y sin el registro del núcleo, el aviso al importar y la
  revisión adversarial (R1–R8: enlaces en HTML, listas con bloques quitados, títulos que guarda una editora, falsos
  positivos de URL, cierre de la 02 y avisos del saneador del núcleo, con un doble de ese saneador). Falla con el código
  anterior: el módulo no existe.
- `tests/playground/sales-kit.sh` (manual, en la PC): Playground con JSON-RPC real y Chrome (ver su cabecera); pinta
  también las páginas que crean una editora y una editora sin `unfiltered_html`, comprueba el color de cada resalte de
  título y que guardar como editora no deje avisos de PHP. La sonda ve los avisos de las peticiones REST (el MCP es
  una), donde el núcleo pone su propio manejador de errores. La fecha de cierre del ensayo se calcula desde hoy.

## Límites conocidos

- La herramienta no puede saber si un testimonio o una cifra son reales: lo pide la descripción y lo devuelve en
  `real_data` para que una persona lo compruebe antes de publicar.
- Videos, grupo y redes se ponen en el editor: ningún enlace a otro sitio entra por el MCP.
- Las páginas a las que llevan los botones (`/carrito/`, `/pago/`…) no se crean solas; `create-page` dice cuáles faltan.
- `omit_blocks` quita bloques, no los recorta por dentro (p. ej. menos bonus): eso se hace en el editor.
- El aviso de importación cubre la importación de la administración; la de WP-CLI (`wp elementor library import`)
  sigue como en el núcleo.
- La variante de contenedores es una conversión del generador, no un diseño hecho a mano: se valida igual que la de
  secciones (mismos widgets, CSS cargado, sin desborde) y conviene revisar sus capturas.
