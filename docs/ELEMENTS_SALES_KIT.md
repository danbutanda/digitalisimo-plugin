# Kit de ventas de DIGITALÍSIMO Elements

DIGITALÍSIMO Elements trae el kit de ventas de DIGITALÍSIMO: ocho plantillas de página para un embudo de venta, su guía
de venta y tres herramientas del servidor MCP del núcleo incluido (`POST /wp-json/elementor/mcp`, apagado por defecto
en cada sitio) para que un asistente cree páginas **en borrador** con ellas. Encargo del operador (2026-11-13, vía la
sesión del cerebro): «Transfórmalo a plantilla digitalisimo-elements para que nuestro MCP pueda generarlas». Después
(2026-11-13, también vía el cerebro) llegaron la v4 del kit y cinco decisiones del operador: WhatsApp es excepción de
marca declarada; vuelven los iconos de redes con su logo en la 04 y la 07; el aviso de Meta y Google va en el pie de
toda plantilla con pie; el contador lleva una fecha vencida mientras no se pone la real; y `create-page` no deja quitar
la promesa, la oferta, la garantía, las preguntas frecuentes, WhatsApp ni el pie.

El código vive en `digitalisimo-elements/modules/digitalisimo-sales/` (cargado desde `pro-elements.php`) y nunca se
edita nada dentro de `digitalisimo-elements/elementor/`.

## De dónde sale cada cosa

| Pieza | Fuente |
| --- | --- |
| Las ocho plantillas | Kit de ventas **v4 genérico** (`OneDrive: DIGITALISIMO/Plantillas/Landing Page Ventas/Kit de ventas DIGITALISIMO v4/`, generador con `--generico`, `PERSONA_GENERICA`), con las correcciones del operador del 2026-11-13 (la portada en una pantalla y sin video, el título corto grande y la frase chica, el precio sólo en la oferta, la barra fija con el logo del evento). Hasta Elements 4.3.0.186 era el v3 genérico. La versión con la persona de DIGITALÍSIMO (`PERSONA_DIGITALISIMO`) es sólo para los sitios propios y **no entra en Elements**: el generador del repositorio sólo trae la genérica y `tests/elements-sales-kit.mjs` falla si «Daniel» aparece en una plantilla, el manifiesto o la guía. |
| El orden de la página y la guía (`guide.md`) | Cerebro, `oficio/ventas-en-linea.md` en el commit **`06ff42f`** (sobre todo «El orden de una página de venta», 26 bloques; también las reglas, la tabla por caso y las fórmulas) más la v4 de su copia de trabajo del 2026-11-13, aún sin commit («La portada de una landing de DIGITALÍSIMO», «El precio se revela tarde»), y el `LEEME.md` del kit v4; sin nombres de otras empresas. |
| El generador y el validador | `scripts/sales-kit/` (`kit_lib.py`, `kit_paginas.py`, `kit_validar.py`): el generador del kit v4 fusionado sobre la adaptación del v3 (abajo). El commit `6f009884` guarda la copia tal cual del generador v2, antes de adaptarlo. |

Cambiar el kit es cambiar el generador y volver a escribir las plantillas:
`python3 -I scripts/sales-kit/kit_paginas.py` (escribe `templates/sections/`, `templates/containers/` y
`templates/manifest.json`) y `python3 -I scripts/sales-kit/kit_validar.py`. Nadie edita a mano una plantilla
generada: la CI corre el generador con `--check`.

## Las plantillas

| Id | Plantilla | Para qué |
| --- | --- | --- |
| `venta-completa` | 01 Página de venta completa | La carta de venta larga: los 26 bloques de la guía en su orden (31 secciones), con `#oferta` y `#historias` |
| `venta-corta` | 02 Página de venta corta | Con un cierre real, la barra fija de cierre; la portada, beneficios, quién está detrás, para quién es, testimonios, oferta, garantía y preguntas |
| `captacion` | 03 Captación | La portada y, justo después (`#registro`), el registro a una clase, un reto o un recurso, con formulario (guarda, avisa por correo y lleva a `/gracias-registro/`) |
| `gracias-registro` | 04 Gracias por registrarte | Los tres pasos después del registro y los iconos de las redes del sitio |
| `carrito` | 05 Carrito | Formulario y forma de pago junto a la oferta, con el extra de un clic (lleva a `/pago/`) |
| `oferta-unica` | 06 Oferta única después de la compra | Un complemento, sí o no |
| `gracias-compra` | 07 Gracias por tu compra | Bienvenida, acceso, soporte y los iconos de las redes del sitio |
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

### La v4 del kit

El generador del repositorio era el del kit v3 adaptado (variantes, manifiesto, `--check`, placeholders que llena
`create-page`, `urgente()`, la fecha de muestra, el resalte de los títulos). La v4 se trajo con una fusión de tres vías
(`git merge-file --diff3 -L repo -L v3 -L v4`, el v3 genérico de OneDrive como base): `kit_validar.py` no cambió,
`kit_lib.py` dio 2 conflictos y `kit_paginas.py` 5. Gana el repositorio en el resalte de los títulos, en el contador
(fecha vencida que `create-page` cambia por `close_date`) y en los placeholders de cierre; de la v4 viene lo demás:

- **La portada** (01, 02 y 03) cabe en la primera pantalla, en escritorio y en celular, y va sin video: el logo, la
  promesa grande (h1, con su resalte), el antetítulo o el evento chico debajo y un botón. En la 03, la cabecera de la
  v4 llevaba el formulario al lado, que en un celular no cabe en una pantalla: el formulario va justo después
  (bloque `registro`, ancla `#registro`) y el botón de la portada lleva ahí.
- **Encabezados:** en cada sección, el título corto va grande y la frase de venta chica, debajo (`cabecera()`).
- **El precio se revela tarde:** sólo en la oferta (y en el cierre de la 01, que la repite); el carrito y la oferta
  única son la oferta. Los bonus van sin precio (su valor está en el resumen de la oferta) y la sección del valor total
  de los bonus desaparece (la 01 pasa de 32 a 31 secciones). «Cuánto vale para ti» pierde sus cifras con `$` (la v4
  decía «[un cliente de $X al mes], en un año son $[Y]»): el cálculo queda en palabras.
- **La barra de cierre** de la 01 queda fija arriba (`sticky` en escritorio, tableta y celular), con el logo del
  evento (oculto en celular), el texto y el botón en una fila en celular (`tam_movil`: en contenedores, fila sin saltos
  de línea) y el mensaje corto «Inscripción cerrada.». El efecto «fijo» deja en su lugar una copia invisible
  (`.elementor-sticky__spacer`), que el ensayo no cuenta como widgets pintados. Lleva el margen de las anclas
  (`sticky_anchor_link_offset`, 130 px): el núcleo sólo da `scroll-margin-top` a las anclas que quedan bajo un elemento
  fijo con ese ajuste, y sin él la barra tapaba el título de «La oferta» o de «Lo avala la comunidad» al llegar por un
  botón (revisión M1). Sus columnas van sin separación y sin relleno arriba y abajo, así que mide lo mismo con secciones
  que con contenedores (antes, 30 px más con secciones). La 02 lleva la misma barra, opcional (texto «Esta oferta
  termina…» y botón «¡Lo quiero!»), para el caso «Evento de venta de temporada»; las dos sólo aparecen con
  `close_date` (revisión B3).

`kit_validar.py` comprueba estas reglas en las dos variantes: portada con logo, h1, antetítulo y un botón a un bloque
de la página, sin video y primera (sólo la barra va antes); ningún precio fuera de los bloques que pueden llevarlo
(`price` en el manifiesto); bonus sin precio ni valor; barra fija con el logo, el contador y el botón y marcada como
urgencia. La salida genérica de la v4 (`plantillas-genericas/`) y la variante de secciones del repositorio sólo
difieren en las adaptaciones del repositorio (abajo y en el informe de la rama).

### Lo que se adaptó del kit

Todo lo demás es el kit v4 genérico tal cual (el validador lo compara al generarlo):

- **Marcas** (abajo): «PayPal», «Stripe», «Zoom / YouTube» y cualquier otra marca pasan a placeholders neutros
  (`[otro medio de pago]`, `[u otra pasarela de pago]`, `[plataforma de la sesión]`).
- **Video:** el enlace de muestra a un video de otro servicio (`…watch?v=VIDEO_ID`) pasa a `[enlace del video]`: sin un
  enlace válido el reproductor no carga nada y la portada de muestra queda a la vista. Se pega en el editor.
- **Grupo:** el botón del grupo lleva `#[enlace del grupo]` (sin enlace a otro sitio).
- **Redes:** los iconos de redes de la 04 y la 07 conservan su logo (decisión del operador del 2026-11-13; de
  4.3.0.186 a esta versión fueron un texto neutro), pero los de perfil no enlazan a las redes: cada uno lleva el
  placeholder `#[enlace de tu perfil]` (tipo `enlace`: se pone en el editor), que abre en la misma pestaña para que,
  si se publica sin cambiar, no abra otra con la misma página. El de WhatsApp sí lleva a `wa.me`, con el número del
  sitio, como los botones del llamado a la acción.
- **Cierre:** los placeholders de la fecha de cierre son propios (`[día de cierre]`, `[mes de cierre]`,
  `[hora de cierre]`) y los contadores llevan una fecha ya pasada (`2000-01-01 00:00`) en lugar de una futura inventada:
  sin la fecha real sólo muestran su mensaje de cierre. No llevan un placeholder porque el widget del contador hace
  `new DateTime()` con su fecha y un texto lo hace fallar (lo encontró el ensayo: el ZIP del kit con un placeholder ahí
  terminaba en un error fatal al importar). La barra de cierre de la 01 y el bloque de urgencia del carrito se marcan
  como urgencia en el manifiesto.
- **Bloques:** cada sección de primer nivel pertenece a un bloque con clave (`promesa`, `oferta`, `pie`…), en el orden
  de la guía.
- **Títulos con resalte:** el kit pintaba parte de un título de otro color con `<span style="color:…">` (la v4 lo
  pasa a `selector .elementor-heading-title span { color: … }`; aquí sigue la forma del repositorio). El núcleo le
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
- **WhatsApp** — excepción explícita del operador (2026-11-13, decisión 1; hasta 4.3.0.186 estaba pendiente de
  confirmar). Acotada al llamado a la acción y a sus textos y campos concretos: los botones («Hablar por WhatsApp»,
  «Pregúntanos por WhatsApp», el del grupo de la clase) y su icono, las frases que invitan a escribir por ese canal, el
  campo del número en los formularios de la 03 y la 05, el nombre de sus bloques en el Navegador, su mención en la guía
  y el manifiesto (también entre los bloques que no se quitan) y la entrada `whatsapp` de `create-page`. Cada excepción
  de WhatsApp lleva `frases`: cada aparición del nombre debe estar dentro de una de ellas, así que «Síguenos en
  WhatsApp» en un título, o en un botón con otro texto, falla.
- **Los logos de las redes** (Facebook, Instagram, TikTok, YouTube y WhatsApp) — excepción explícita del operador
  (2026-11-13, decisión 2): sólo el valor del icono del widget de iconos sociales (`fab fa-facebook-f`…), sólo en la 04
  y la 07. Para cumplir también la regla de no enlazar a otras empresas, los enlaces de esos iconos son de muestra del
  propio sitio (`#[enlace de tu perfil]`, sin abrir otra pestaña), que el dueño cambia en el editor por sus perfiles,
  y el de WhatsApp lleva a `wa.me` con el número del sitio (la excepción del llamado a la acción). El validador rechaza
  iconos en otra plantilla, otra red, un icono de perfil que enlace a la red o abra otra pestaña con el enlace de
  muestra, y el nombre de una red en cualquier otro texto. `create-page` sigue sin aceptar URLs de otros sitios: no hay una entrada para los
  perfiles (abriría la puerta a enlaces de otros sitios por el MCP; ver «Límites»), y su `review` dice que faltan.

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
| `list-templates` (lectura) | `template` (opcional) | Las ocho plantillas (para qué, cuándo, qué mide, secciones, widgets, contador, formulario, enlaces internos y bloques en orden, con los que no se quitan: `required` y `required_for`), el embudo, la tabla por caso, lo que no se quita (`required`) y la variante del sitio. Con `template`, además sus placeholders: texto exacto, apariciones y tipo (`texto`, `precio`, `dato_real`, `cierre` o `enlace`) |
| `create-page` (escritura, no destructiva) | `template` y `title` (o `product_id` con `offer`: la landing de un producto de la tienda), `replacements`, `omit_blocks`, `whatsapp`, `close_date`, `finish` (por defecto, sí) | El ID, `status: draft`, la variante, las URL de edición y de vista previa, lo llenado, lo ignorado, los placeholders pendientes, los datos reales que hay que comprobar, la urgencia quitada, los bloques quitados, las anclas que quedaron sin destino, los iconos de redes sin enlace (`social_pending`), las páginas del sitio que faltan y la revisión (`review`); terminada: qué se llenó y de dónde (`filled_from`), lo que falta con su bloque y campo (`pending`), lo quitado (`removed`), los enlaces reales (`links`) y, con `product_id`, el producto, la oferta y el enlace de compra |
| `guide` (lectura) | `section` (opcional: `reglas`, `plantillas`, `casos`, `orden`, `formulas`, `paginas`, `antes-de-publicar`) | La guía en Markdown, entera o una parte |

### `create-page`

- **La landing de un producto de la tienda (`product_id`, 2026-11-23).** Caso real de plomerito: la promo de Black
  Friday de un purificador ($10,000 a $7,500, «de hoy a fin de mes») salió como la plantilla, con 36 placeholders sin
  llenar, el WhatsApp y el logo de muestra y enlaces a páginas que no existen. Con `product_id` y `offer` (`price`,
  `regular_price`, `ends`, `label`), `Product` lee el producto (nombre, descripciones, imágenes, precios, atributos,
  peso y medidas, reseñas aprobadas, fin de la rebaja, si se puede comprar) y arma la venta corta (02) como landing de
  producto, por los IDs de sus widgets (iguales en las dos variantes; si la plantilla cambia, un error lo dice): la
  portada con el nombre y la etiqueta, los beneficios (las listas de la descripción), «Conócelo» (la descripción y la
  galería en un carrusel), características (atributos, peso, medidas) y «Además», las reseñas aprobadas (si no hay,
  sin testimonios), la oferta con el precio normal tachado, el de oferta, el ahorro y el botón que agrega el producto
  al carrito y lleva a pagar (o a la ficha, si tiene opciones o está agotado), el fin real con la barra fija y la
  cuenta regresiva, la garantía que dice el producto (o la política de reembolso del sitio; si no hay, `pending`) y
  preguntas frecuentes de compra, pago, envío y cierre con lo que la tienda tiene activo. Sin bloques de curso. Si la
  tienda cobra otro precio que la landing, `review` lo dice (se pone el precio rebajado en el producto antes de
  publicar); el producto no se toca.
- **Revisión adversarial (2026-11-23).** El nombre del producto no pasa por la regla del precio (el mensaje de WhatsApp
  se escribe directo: «Bomba 0.50 HP» no es un precio); los medios de pago se dicen «Formas de pago», sin «en línea»;
  el WhatsApp del sitio cuenta cada origen una vez, busca la cabecera, el pie, el botón flotante y los menús en su
  propia consulta (no los tapan las páginas recientes), toma Joinchat y Click to Chat sólo con su plugin activo y avisa
  en `review` si hay más de un número (`whatsapp_candidates`); con variaciones, el tachado y el ahorro sólo son de la
  misma variación («Desde» si los precios difieren); la pregunta de envíos sólo con métodos que llevan el producto (sin
  recoger en tienda) y si el producto se envía, y dice «gratis» con envío gratis; la hora del cierre se nombra con la
  zona real del sitio; sólo reseñas de 4 o 5 estrellas sin las respuestas de la tienda; un producto con contraseña se
  rechaza; una oración que niega la garantía no es una garantía. Segunda revisión: la zona del sitio sólo se nombra en
  los textos del cierre (los que llevan `[… de cierre]`; la hora de un evento de la 03 y la 04 la escribe el usuario y
  se queda «del centro de México»); un envío gratis con mínimo o cupón se dice con su condición y cada método de cada
  zona cuenta por separado; la negación de la garantía debe ir sobre la garantía («ningún tipo de garantía»,
  «garantía: ninguna»/«-»; no «envío sin costo y garantía de 12 meses»); variaciones empatadas, la de ID menor;
  WhatsApp sin páginas con contraseña, y una plantilla guardada sin condiciones de visualización pesa menos que la
  cabecera o el pie en uso; un enlace que se pone en el editor quita su botón, no el bloque.
- **Regenerar en el mismo borrador (`post_id`).** Cada borrador del kit lleva la meta `_digitalisimo_sales_kit` (sitio
  de la red y plantilla). Con `post_id`, `create-page` arma de nuevo y guarda en ESE borrador (mismo ID, sigue en
  borrador), sólo si lo creó el kit en este sitio, el usuario puede editarlo (`edit_post`) y está en borrador; una
  página publicada, programada, privada, en revisión, de otro sitio o que no creó el kit da error y no se toca. El
  núcleo marca la página como cambiada por el MCP. Los encabezados sueltos de la descripción («Beneficios»,
  «Características:», «Detalles:», un `<h3>`) no se copian como beneficio ni como párrafo.
- **Terminada (`finish`, por defecto).** `Finisher` termina cualquier página del kit: `[Marca]` y `[año]` con los datos
  del sitio; las rutas del kit (`/aviso-de-privacidad/`, `/terminos-y-condiciones/`, `/politica-de-reembolso/`,
  `/carrito/`, `/pago/`…) a la página real que el sitio declara o tiene con esa dirección (`Site::read()`), y si no la
  tiene, sin el enlace (y sin el botón); las imágenes de muestra del logo (portada, barra, pie) con el logo del sitio,
  y las demás imágenes de muestra fuera; sin WhatsApp, el botón a la página de contacto real (o fuera) y los textos sin
  mandar a WhatsApp; un bloque opcional con un dato que falta, fuera entero; en uno obligatorio, fuera el elemento de la
  lista o el widget, y lo que falta en `pending` con el bloque y el campo; los botones a un ancla que ya no está, fuera.
  Ningún `[placeholder]` queda a la vista. `finish: false` deja la plantilla como antes, para llenarla a mano.
- **WhatsApp del sitio.** Sin `whatsapp`, `Site::read()` busca el número que el sitio ya publica: los ajustes de los
  botones de chat comunes, los menús, las plantillas del constructor (cabecera, pie, botón flotante) y las páginas
  publicadas (enlaces `wa.me`, `api.whatsapp.com/send?phone=` y el número del botón flotante); gana el de más peso
  (cabecera, pie y menús antes que una entrada). La respuesta dice de dónde salió.

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
- **Nada se inventa** (D-0023, D-0024): la herramienta no inventa texto; usa los datos que dio el usuario o que están
  en el sitio (tienda, páginas, ajustes), y lo que no tiene dato se quita (o, con `finish: false`, se queda y se
  informa). Los de tipo `dato_real` que se llenan se devuelven en `real_data` para que una persona los compruebe. La
  descripción le prohíbe al modelo inventar testimonios, reseñas, cifras de clientes o urgencia.
- **Ni HTML con atributos ni enlaces:** un valor sólo lleva texto y `strong`, `em`, `b`, `i` y `br` sin atributos;
  ningún `<a>`, `<img>`, `<form>`, `<span style>`… (los enlaces sólo existen en la plantilla).
- **Ninguna URL de otro sitio** en un valor, leída como la lee un navegador (entidades decodificadas, `\` como `/`, sin
  tabuladores ni saltos de línea): un esquema pegado a su contenido (`https://otro…`, `http:otro`, `javascript:…`,
  `data:…`, `vbscript:…`, `file:…`), `//otro…` o `www.otro…`. Sólo `wa.me` y el propio sitio. Un punto no basta: «Curso
  de JavaScript: de cero», «ASP.NET», «Socket.io», «hoy.Es tu momento», un dominio suelto en un texto o un correo pasan
  (no son enlaces: un valor no puede llevar uno).
  Los placeholders de tipo `enlace` (video, grupo) no se llenan por aquí: se ponen en el editor.
- **Precios en MXN:** un placeholder de tipo `precio` (detrás de `$`) sólo acepta una cifra (`1,997`, `1997.50`).
- **El precio, sólo en la oferta** (revisión M2): un valor con `$`, `MXN`, «peso(s)», una cifra con centavos
  (`1997.50`, `49,90`) o una cifra justo después de «precio» o «cuesta» se rechaza
  (`digitalisimo_sales_price_outside_offer`, con el bloque en el mensaje) si cae en un bloque sin `price_values`. Lo
  admiten la oferta y el cierre de la 01 (que la repite), los caminos y «Cuánto vale para ti» (el ancla habla en pesos a
  propósito), la oferta de la 02 y el carrito y la oferta única enteros; `list-templates` lo dice con `price` en cada
  bloque. En una lista se mira por aparición (la de un bloque quitado no cuenta). Una cifra suelta con separador de
  miles («1,250 alumnos») no cuenta como precio: puede ser un dato real de otro tipo.
- **`whatsapp`:** sólo dígitos (8 a 15, con la clave del país); cambia el número de todos los botones
  (`https://wa.me/<dígitos>`). Sin él, el que el sitio ya publica; si no hay, terminada, el botón va a contacto o se
  quita (con `finish: false`, queda el de muestra y se informa).
- **`close_date`** («AAAA-MM-DD HH:MM» en la hora del sitio), en las plantillas con contador o con textos de cierre
  (01, 02 y 05; `close_date: true` en `list-templates`): una fecha válida, futura y a menos de dos años; llena los
  contadores y los placeholders de cierre. Los de cierre no se llenan con `replacements`. **Sin `close_date`** se
  quitan los bloques de urgencia del manifiesto (las barras de cierre de la 01 y la 02 y el contador del carrito), como
  pide el LEEME del kit; los textos que nombran el cierre quedan pendientes. Con la barra, `review` avisa que el logo
  del evento sigue siendo la imagen de muestra (`event_logo`, revisión B4).
- **`omit_blocks`:** claves de bloques que no aplican; se quitan enteros y lo demás queda en el orden de la guía (se
  puede quitar un bloque, pero no cambiar el orden). **No se quitan** (decisión 5 del operador, 2026-11-13) los bloques
  que llevan la promesa, la oferta, la garantía, las preguntas frecuentes, WhatsApp o el pie, en las plantillas que los
  tienen, ni (decisiones del operador sobre la revisión, 2026-11-15) el formulario y el último enlace a WhatsApp
  (`required: true` y `required_for` en el manifiesto y en `list-templates`; `required_kinds` los nombra): en la 01,
  `promesa`, `oferta`, `garantia`, `preguntas-frecuentes`, `whatsapp` y `pie`; en la 02, `promesa`, `oferta`,
  `garantia-y-preguntas` y `pie`; en la 03, `promesa`, `registro` (el formulario: una captación sin formulario no capta)
  y `pie`; en la 04, `mientras-tanto` (su único enlace a WhatsApp, el icono) y `pie`; en la 05, `encabezado`,
  `formulario`, `dudas-de-pago` y `pie`; en la 06, `aviso-y-video`, `complemento` y `pie`; en la 07, `soporte` y `pie`;
  la 08 no tiene ninguno. El validador reconoce WhatsApp por los enlaces `wa.me`: si una plantilla tiene uno, un bloque
  obligatorio por WhatsApp lo lleva. Pedir uno da un solo error que nombra todos los obligatorios pedidos, qué lleva cada uno y cuáles no se
  quitan en esa plantilla. Las pruebas sociales (testimonios, historias, capturas, objeciones) siguen siendo
  opcionales. Si un botón llevaba a un ancla quitada (`#historias`), se informa.
- **Redes:** en la 04 y la 07, los cuatro iconos de perfil llevan `#[enlace de tu perfil]` (un placeholder de tipo
  `enlace`, que no se llena por aquí, y abre en la misma pestaña) y el de WhatsApp el número de `whatsapp`;
  `social_pending` cuenta los que siguen con el enlace de muestra y `review` dice que faltan y dónde se ponen.
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
  el validador sobre las dos variantes, cuarenta y nueve plantillas y manifiestos rotos que el validador debe rechazar (marcas, también las de
  la lista ampliada y WhatsApp fuera del llamado a la acción, enlaces, video, IDs, widgets, paridad, filas, contador,
  relleno, proveedor, la persona propia, títulos con atributos o sin el color de su resalte, y bloques; y de la v4: una
  portada con video, sin botón o sin el antetítulo, un botón de portada sin destino, precios fuera de la oferta, un
  bonus con su valor, una barra de cierre que no es fija, sin logo o que no se acomoda en celular, iconos de redes en
  otra plantilla, que enlazan a la red, de otra red o el nombre de una red fuera de ellos; y de la revisión: una barra sin
  el margen de las anclas o que no mide lo mismo en las dos variantes, el formulario o el último enlace a WhatsApp que
  se podrían quitar, un bloque «de WhatsApp» sin enlace a WhatsApp, un icono de perfil de muestra que abre otra pestaña
  y una oferta donde create-page no dejaría poner el precio), las marcas vigiladas y sus
  excepciones acotadas (WhatsApp y los logos de las redes, decisión del operador), las marcas sólo en las constantes
  del generador, la ausencia de la persona propia en el módulo y que el ensayo de Playground no lleve fechas de cierre
  fijas.
- `tests/elements-sales-mcp.php` (en `validate.yml` y `release.yml`; PHP 7.4, 8.2 y 8.5): definiciones cerradas,
  catálogo y variantes, guía, placeholders, armado (IDs, reemplazos, saneado, URLs, precios, WhatsApp, cierre,
  urgencia, bloques), borrador siempre, permisos, registro con y sin el registro del núcleo, el aviso al importar y la
  revisión adversarial (R1–R8: enlaces en HTML, listas con bloques quitados, títulos que guarda una editora, falsos
  positivos de URL, cierre de la 02 y avisos del saneador del núcleo, con un doble de ese saneador); y las cinco
  decisiones del operador: WhatsApp como excepción declarada, los iconos de redes de la 04 y la 07 (con su logo, sin
  enlazar a la red, `social_pending` y la revisión), el aviso de Meta y Google en el pie de toda plantilla con pie, la
  fecha vencida de los contadores y los bloques obligatorios (cada uno da error, las pruebas sociales se quitan), más el
  precio sólo en los bloques que pueden llevarlo; y los arreglos de la revisión: el candado del precio en lo que llena
  create-page (por bloque y por aparición), el formulario de la 03 y el último enlace a WhatsApp de cada plantilla, la
  barra de la 02, el aviso del logo de muestra, los perfiles en la misma pestaña y las marcas en todas las cadenas de
  texto del PHP del módulo (no sólo en las definiciones de las herramientas).
- `tests/elements-sales-product.php` (en `validate.yml` y `release.yml`; PHP 7.4, 8.2 y 8.5): la landing del producto
  del caso (nombre, descripción con lista, tres imágenes, $10,000 a $7,500 hasta fin de mes) en las dos variantes sin
  ningún placeholder, con el nombre, los precios (sólo en la oferta), las imágenes, el botón al carrito, el WhatsApp
  del pie del sitio, el logo y sólo enlaces a páginas que existen; sin WhatsApp en el sitio (a contacto o sin botón),
  sin oferta (la rebaja de la tienda), con una reseña real, sin garantía (pendiente, sin inventarla), agotado, los
  candados de la oferta, las ocho plantillas terminadas sin datos y `finish: false`.
- `tests/playground/sales-product.sh` (manual, en la PC): Playground con la tienda (la versión fijada) y Elements, el
  producto del caso, `create-page` con `product_id` por el MCP real, el HTML de la página sin corchetes de
  placeholder y capturas en Chrome a 1 280 y 390 px.
- `tests/playground/sales-kit.sh` (manual, en la PC): Playground con JSON-RPC real y Chrome (ver su cabecera); crea
  las ocho en las dos variantes como administrador y como editora y las pinta a 1 280 × 800 y 390 × 844 px, también
  las de una editora sin `unfiltered_html`; comprueba el color de cada resalte de título, que guardar como editora no
  deje avisos de PHP, que la portada de 01, 02 y 03 quepa en la primera pantalla con su botón visible sin desplazar,
  que la barra de cierre quede fija al desplazar (con el logo en escritorio y sin él en celular), los iconos de redes
  de la 04 y la 07 y que los bloques obligatorios no se quiten; como visitante (sin la barra de administración), un clic
  en el botón de la portada, en el de la barra y en «Ver más historias» deja el título del bloque al que llevan a la
  vista, bajo la barra fija, a 1 280 × 800 y 390 × 844, y la barra mide lo mismo con contenedores que con secciones. Los widgets pintados se cuentan sin la copia que deja la
  barra fija (`.elementor-sticky__spacer`). La sonda ve los avisos de las peticiones REST (el MCP es una), donde el
  núcleo pone su propio manejador de errores. La fecha de cierre del ensayo se calcula desde hoy.

## Límites conocidos

- La herramienta no puede saber si un testimonio o una cifra son reales: lo pide la descripción y lo devuelve en
  `real_data` para que una persona lo compruebe antes de publicar.
- Videos, grupo y los perfiles de los iconos de redes se ponen en el editor: ningún enlace a otro sitio entra por el
  MCP. Una entrada para los perfiles del sitio en `create-page` abriría esa puerta (habría que aceptar dominios de
  otras empresas): queda como pregunta para el operador.
- La portada se mide en Chrome con la barra de administración (la vista previa pide sesión): un visitante tiene más
  alto. Con textos reales muy largos, la portada puede dejar de caber: se revisa en la vista previa antes de publicar.
- Las páginas a las que llevan los botones (`/carrito/`, `/pago/`…) no se crean solas: terminada, el enlace va a la
  real o se quita, y `create-page` dice cuáles faltan.
- La landing de producto no cambia el precio del producto: si la tienda cobra otro, lo avisa (`review`).
- Los beneficios salen de las listas de la descripción del producto: sin listas, no hay bloque de beneficios.
- `omit_blocks` quita bloques, no los recorta por dentro (p. ej. menos bonus): eso se hace en el editor.
- El aviso de importación cubre la importación de la administración; la de WP-CLI (`wp elementor library import`)
  sigue como en el núcleo.
- La variante de contenedores es una conversión del generador, no un diseño hecho a mano: se valida igual que la de
  secciones (mismos widgets, CSS cargado, sin desborde) y conviene revisar sus capturas.
