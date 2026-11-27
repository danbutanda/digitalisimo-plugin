# WooCommerce en el MCP de DIGITALÍSIMO Elements

> **Acceso total, sin interruptores (decisión del dueño del 2026-11-27, Elements @NEXT_ELEMENTS@).** El servidor MCP
> está siempre activo (`Digitalisimo\Elements\Mcp\Access` fija `elementor_mcp_enabled` en 1) y todas sus herramientas
> leen y escriben. Se retiraron los modos de «WooCommerce en el MCP» (apagado, sólo lectura, escritura sólo en lo
> elegido, lectura y escritura) con su herencia de red, y la excepción del Asistente en «Sólo lectura»: decide sólo la
> capacidad de WordPress y WooCommerce del usuario conectado. Al actualizar, `Access::migrate()` borra en cada sitio (y
> en la red) las opciones `digitalisimo_elements_woo_mcp` y `digitalisimo_elements_woo_mcp_defaults`; ningún código las
> lee. La única opción es «Mostrar el prompt para vincular» («Rendimiento» → «MCP de este sitio»: dirección del
> servidor, configuración de Claude Code y Claude Desktop y dónde pegarla; ocultarla no apaga nada). Cada escritura de
> cualquier herramienta (constructor, tienda, SEO, Hosting, Tools, IA Tools, también por el proxy del editor) queda en
> la bitácora única `digitalisimo_mcp_write_log` (`Write_Log`: quién, vía, herramienta, acción, objeto sólo con IDs,
> resultado; anotada antes de ejecutar; 2000 entradas y 90 días), que se consulta con la herramienta de lectura
> `digitalisimo-mcp-write-log` (`manage_options`). Se conservan `dry_run`, `undo`, las anotaciones, las capacidades,
> el enmascarado de secretos y la bitácora propia de la tienda. Lo que sigue sobre modos es historia.

DIGITALÍSIMO Elements trae el servidor MCP del constructor (núcleo incluido): `POST /wp-json/elementor/mcp`, siempre
activo desde el 2026-11-27. Con WooCommerce activo, ese mismo servidor ofrece siete herramientas para administrar la
tienda del sitio y, con el plugin de Skydropx activo, una octava para sus envíos (sección «Skydropx»). El código vive en `digitalisimo-elements/modules/digitalisimo-woo/mcp/` y nunca se edita nada dentro
de `digitalisimo-elements/elementor/`.

## Cómo conectarse

1. Nada que activar: el MCP del sitio viene activo con lectura y escritura.
2. Copiar el bloque de «Rendimiento» → «MCP de este sitio» → «Mostrar el prompt para vincular».
3. **Crear una contraseña de aplicación** para el usuario que usará el asistente (Usuarios → Perfil → «Contraseñas de
   aplicación»). Las herramientas actúan con los permisos de ese usuario: un administrador o un gerente de tienda.
   WordPress sólo ofrece contraseñas de aplicación con HTTPS (o en un entorno `local`).
4. **Configurar el cliente MCP** con transporte HTTP hacia `https://<sitio>/wp-json/elementor/mcp` y la cabecera
   `Authorization: Basic base64(usuario:contraseña de aplicación)`. El cliente hace `initialize`, guarda la cabecera
   `Mcp-Session-Id` que devuelve y la envía en `tools/list` y `tools/call`.

En una red, cada sitio expone su propia tienda en su propio `/<sitio>/wp-json/elementor/mcp`; nada tiene alcance de red
salvo el modo, que la red define y cada sitio hereda o personaliza.

## Herramientas

Todas llevan `action` (enum) y parámetros cerrados (`additionalProperties: false`); ninguna usa la clave `post_id`.
`data` lleva los campos de la API REST v3 de WooCommerce y `query` sus filtros. `fields` recorta la respuesta a los
campos pedidos (siempre con `id`); en un lote, a los de cada elemento.

**Paginación.** Sólo se pagina lo que WooCommerce 11.2.1 pagina (`paged` en `Tools::RESOURCES`): productos,
variaciones, categorías, etiquetas, marcas, términos de atributo, clases de envío, reseñas, pedidos, reembolsos,
clientes, cupones y tasas de impuesto, con `page` y `per_page` (100 como mucho) y `total`/`total_pages`. Las demás listas
(pasarelas, zonas y sus ubicaciones y métodos, métodos de envío, grupos y opciones de ajustes, clases de impuesto,
atributos, notas de pedido, descargas) devuelven todo en una respuesta con `total`; `page` mayor que 1 se rechaza.

**Lotes.** `batch` acepta `data` `{"create":[…],"update":[…],"delete":[ids]}`, 100 elementos como mucho. Un parámetro
de la ruta dentro de un elemento (`group_id` en un lote de ajustes, `product_id` en uno de variaciones) se quita si
coincide con el de la ruta y, si no, el lote se rechaza: WooCommerce lo mezclaría sobre la URL y ganaría. WooCommerce
responde 200 aunque fallen elementos; la respuesta lleva `batch` (`done`, `failed`, `errors` con lista, posición, ID,
código y mensaje). Con algún fallo, `ok` es false y `partial` true; si no se aplicó ninguno, es un error.

| Herramienta (nombre MCP) | Recursos y acciones |
| --- | --- |
| `digitalisimo-woo/products` (`digitalisimo-woo-products`) | `product` y `variation` (con `product_id`): list, get, create, update, delete, batch; undo (`change_id`) |
| `digitalisimo-woo/catalog` | `category`, `tag`, `brand`, `shipping_class` (`term_id`), `attribute` (`attribute_id`), `attribute_term` (`attribute_id` y `term_id`), `review` (`review_id`): list, get, create, update, delete; undo (`change_id`) |
| `digitalisimo-woo/orders` | `order` (`order_id`): list, get, create, update, delete; `note`: list, get, create, delete; `refund`: list, get, create, delete |
| `digitalisimo-woo/customers` | `customer` (`customer_id`): list, get, create, update, delete; `download`: list |
| `digitalisimo-woo/coupons` | `coupon_id`: list, get, create, update, delete, batch; undo (`change_id`) |
| `digitalisimo-woo/store-settings` | `setting_group` (list), `setting` (`group_id`, `setting_id`: list, get, update, batch), `shipping_zone` (`zone_id`), `shipping_zone_location` (list y update, que reemplaza todas), `shipping_zone_method` (`zone_id`, `instance_id`), `shipping_method` (`method_id`: list, get), `tax_class` (`tax_class`: list, create, delete), `tax_rate` (`rate_id`, también batch), `payment_gateway` (`gateway_id`: list, get, update) |
| `digitalisimo-woo/reports` | sólo lectura: list, sales, top_sellers, orders_totals, products_totals, customers_totals, coupons_totals, reviews_totals, `log` (la bitácora) y `changes` (el registro de cambios) |
| `digitalisimo-woo/skydropx` (sólo con Skydropx) | lectura: status, audit_products, quote, shipments, get_shipment, label; escritura: update_products, set_tracking, resync (ver «Skydropx») |

**Ejecución.** Cada acción es una petición interna (`rest_do_request()`) a la API REST v3 de WooCommerce (`/wc/v3/…`)
como el usuario autenticado: WooCommerce valida cada campo, comprueba los permisos objeto a objeto, dispara sus ganchos
y cubre HPOS. La ruta y el método salen de una tabla cerrada (`Tools::RESOURCES`) con IDs enteros o claves validadas;
nunca vienen del cliente. Del cuerpo y de la consulta se quitan los parámetros de la ruta (el cuerpo les ganaría a la
URL), `force`, `api_refund` y todo lo que empieza por `_`. Nada de SQL propio ni de clases internas de WooCommerce.

**Una sola fuente.** WooCommerce trae abilities propias (`woocommerce/…`) y un servidor MCP experimental; no se usan ni
se exponen en el servidor de Elements. Las herramientas de tienda tampoco aparecen en la REST de abilities ni en el
servidor MCP genérico del adaptador.

**Permisos.** Cada herramienta (en `WP_Ability`, en `check_permission()` del núcleo con un `true` estricto y otra vez
al ejecutar) exige la capacidad que la REST de WooCommerce pide para ese recurso y esa acción (las de
`wc_rest_check_post_permissions`, `wc_rest_check_product_term_permissions`, `wc_rest_check_user_permissions` y
`wc_rest_check_manager_permissions`) y además un mínimo por herramienta (`edit_products`, `edit_shop_orders`,
`list_users`, `edit_shop_coupons`, `manage_woocommerce` o `view_woocommerce_reports`). Un editor o un suscriptor no
hace nada; tampoco leer reseñas, que WooCommerce deja a quien modera comentarios. La bitácora exige
`manage_woocommerce`.

**Anotaciones.** Son por herramienta: los informes son de sólo lectura (`readOnlyHint`); las otras seis tienen delete
(y los pedidos, reembolsos), así que son destructivas (`destructiveHint`). La descripción de cada una dice qué acciones
leen y cuáles escriben.

## Skydropx

Pedido del propietario (2026-11-19): que los clientes puedan modificar sus productos y sus envíos «a la perfección» con
Skydropx, en lo que le toca a WordPress; lo que está fuera (crear, pagar o cancelar envíos en la plataforma) se sigue
haciendo a mano en su panel. `digitalisimo-woo/skydropx` (`mcp/class-skydropx-tool.php`, la definición;
`mcp/class-skydropx.php`, la ejecución) sólo se registra cuando el plugin de Skydropx está cargado en el sitio
(`SKYDROPX_VERSION` o su clase `Skydropx`, sin tocar el disco); sin él no se carga ningún archivo de la herramienta. Se
probó con Skydropx 2.1.5 (la copia oficial de wordpress.org, anotada en `reference/OFFICIAL-SOURCES.txt`). Las mismas
reglas que las demás: entrada cerrada, modos (las escrituras sólo en «Lectura y escritura»), permiso también al ejecutar
y bitácora de cada escritura sin valores.

| Acción | Qué hace | Permiso |
| --- | --- | --- |
| `status` | vinculación (sí o no, con el método del plugin; nunca la clave), cotización activa, si la plataforma fijó la URL de cotización (sólo su host), zona «Skydropx» (posición, ubicaciones, método y las zonas que la preceden), unidades de la tienda y las que manda el plugin (kg y cm), dirección de origen y lo que falta, enlaces permanentes, webhooks hacia la plataforma (cuántos y sus temas, sin URL), metas de rastreo y piezas del plugin que falten; avisos de lo que impide cotizar | `edit_shop_orders` o `edit_products` |
| `audit_products` | productos publicados (no variables, agrupados ni externos) y variaciones activas que la cotización mandaría con peso o medidas vacíos o en cero, con la herencia del padre como la lee el plugin, y sin clase de envío si la tienda tiene clases (`checks` lo elige); omite virtuales y descargables (el plugin no los cotiza); paginado con totales y el recuento por campo; revisa hasta 5 000 | `edit_products` y `read_private_products` |
| `update_products` | peso, largo, ancho, alto y clase de envío de hasta 100 productos o variaciones, por los lotes de la REST (`products/batch` y `products/{id}/variations/batch`), como `products`, para que salten sus ganchos y webhooks; `weight_unit` (kg, g, lbs, oz) y `dimension_unit` (m, cm, mm, in, yd) convierten a las de la tienda con las funciones de WooCommerce; rechaza antes de escribir un valor en cero, negativo o no numérico, un producto que no existe, una variación de otro padre y una clase de envío desconocida (WooCommerce la cambiaría por «ninguna») | `edit_products` y `edit_others_products` |
| `quote` | cotización de prueba con el método del propio plugin (`calculate_shipping()`) para `address` y `cart`: la instancia de la zona que el carrito usaría con esa dirección (si esa zona no lo tiene, avisa que el cliente no vería las tarifas); tarifas resumidas de la más barata a la más cara; respeta la caché de 2 minutos del plugin (`cache:false` la salta) | `edit_shop_orders` o `edit_products` |
| `shipments` | pedidos de la REST con su rastreo (número, paquetería, URL y si el plugin muestra la guía), con `statuses`, `after`, `before` y `tracking_filter` (dentro de la página) | `edit_shop_orders` y `read_private_shop_orders` |
| `get_shipment` | un pedido: líneas de envío con su rastreo, si tiene guía (con el método del plugin), destino sin nombre ni calle y enlace al pedido | igual |
| `label` | un enlace a `admin-post.php?action=digitalisimo_woo_skydropx_label&order_id=…` y el del pedido | igual |
| `set_tracking` | número, paquetería y URL de un envío hecho a mano en las metas de la línea de envío que lee el plugin (`Tracking Number`, `Tracking Company`, `Tracking URL`, de sus constantes), conservando lo que no se manda; opcionalmente `order_status` y `customer_note` (WooCommerce avisa al cliente por correo) | `edit_shop_orders` |
| `resync` | guarda el pedido con su fecha de modificación al día: WooCommerce dispara `woocommerce_update_order` y entrega el webhook `order.updated` que la plataforma registró; avisa si no hay ninguno | igual |

**Cómo llega a WordPress.**
- **El rastreo** se escribe con el CRUD de WooCommerce (la línea de envío y un `save()` del pedido, que dispara sus
  webhooks), no con la REST: un `PUT` con `shipping_lines` recalcula los totales y los impuestos de un pedido ya cobrado.
  Un pedido sin línea de envío recibe una sin costo («Envío»), sin recalcular nada. El estado y la nota van por la REST.
- **La cotización** pone en `WC()->cart` y `WC()->session`, sólo durante la llamada, un carrito (`Skydropx_Cart`, que no
  llama al constructor de WooCommerce: nunca carga ni guarda el carrito persistente de nadie) y una sesión
  (`Skydropx_Session`, con el `get()`/`set()` de WooCommerce) cuyos datos, la caché del plugin, se guardan 2 minutos en
  un transitorio por usuario. El total que lee el plugin es el del carrito con o sin impuestos, según la tienda.
- **La guía.** El nonce del enlace de impresión del plugin va ligado a la sesión del navegador, así que no sirve
  generado desde el MCP (contraseña de aplicación): `label` devuelve un enlace propio que, abierto con la sesión de un
  administrador o gerente, comprueba el permiso y la guía y lleva al endpoint del plugin (`skydropx_print_label`) con un
  nonce de esa sesión; el plugin pide el PDF firmado a la plataforma y lo entrega. El manejador sólo se engancha en esa
  petición de `admin-post.php`; sin sesión lleva al acceso. Ni el PDF, ni la firma, ni la clave pasan por el modelo.

**Sólo lectura, a propósito.** La vinculación, las claves de la API, la URL de cotización que fija la plataforma y la
zona y el método que ella crea no se cambian desde aquí: son de la plataforma y cambiarlos rompería su sincronización.

**Otra versión del plugin.** Cada pieza (sus clases, métodos y constantes) se busca con
`class_exists`/`method_exists`/`defined`; las metas de rastreo se leen de sus constantes privadas y, si faltan, se usan
las de 2.1.5. `status` lista lo que falta y la acción que lo necesita lo dice (`digitalisimo_woo_mcp_skydropx_compat`);
las demás siguen.

**Límites.** `tracking_filter` filtra dentro de la página (los totales son los de la REST). `audit_products` revisa como
mucho 5 000 productos y variaciones (lo dice con `truncated`). Las tarifas son las que la plataforma da al plugin para
ese carrito de prueba (sin cupones). Una guía registrada a mano se ve como «Guía generada» en la caja del plugin, pero su
PDF sólo existe si la generó Skydropx.

## Modos (retirados el 2026-11-27)

Historia: «WooCommerce en el MCP», en la pantalla «Rendimiento» (sitio y red), sección propia con su formulario:

- **Apagado:** no se registra ninguna herramienta de tienda.
- **Sólo lectura** (predeterminado): list, get y los informes. create, update, delete y batch devuelven un error que
  dice dónde se cambia el modo.
- **Escritura sólo en lo elegido** (`custom`, opt-in desde la tanda B): lee todo y escribe sólo con las herramientas
  marcadas en sus casillas (productos, catálogo, pedidos, clientes, cupones, ajustes, Skydropx); con las demás, como en
  «Sólo lectura», con un error que dice qué falta marcar. Ver «Permiso por herramienta, simulación y deshacer».
- **Lectura y escritura:** todo, con la bitácora.

**El Asistente IA del escritorio** (IA Tools, caso plomerito 4 del 2026-11-26): «Sólo lectura» se pensó para los clientes
MCP externos. En el Asistente cada cambio lo confirma la persona en su tarjeta, así que, dentro de su contexto
(`Digitalisimo_AI_Assistant_Context`, que un cliente REST o MCP no puede abrir), `Executor` deja pasar en «Sólo lectura»
lo que el Asistente clasifica como cambio sencillo (`write`): crear o actualizar productos, variaciones y catálogo sin
metadatos, descargas ni publicar. Lo destructivo (borrar, lotes, pedidos, reembolsos, clientes, cupones, ajustes, envíos,
Skydropx) sigue sujeto al modo, y en el Asistente se niega ya en el permiso, antes de la tarjeta, con el mismo error que
dice el ajuste y dónde se cambia. «Apagado» sigue sin herramientas. Fuera del Asistente nada cambia.
`Executor::assistant_writes()` no se fía sólo de la clase que da el Asistente: lo comprueba por su cuenta (create o
update de producto, variación o catálogo, nunca reseñas, lotes ni borrar; sin metadatos ni descargas; sin cambiar el
estado de lo que existe; crear sólo en borrador o pendiente, porque la API v3 publica lo que llega sin estado). La ayuda
de la pantalla y la descripción de `products` y `catalog` lo dicen.

Paridad sitio/red como en «Rendimiento»: la red define el valor (sin «Heredar de la red») y cada sitio lo hereda o lo
personaliza (con «Heredar de la red»). Opciones `digitalisimo_elements_woo_mcp` (sitio, sin carga automática; sólo se lee con el MCP del sitio activo) y
`digitalisimo_elements_woo_mcp_defaults` (red). Sin WooCommerce (o con uno anterior a `Woo\Module::MIN_WC`) o con el
MCP del sitio apagado, no hay herramientas de tienda.

## Permiso por herramienta, simulación y deshacer (tanda B, 2026-11-26)

Pedido de la auditoría de digitalisimo.lat (2026-11-24, tanda B, B3): que un cliente MCP externo pueda escribir en la
tienda con un permiso propio por recurso en lugar de todo o nada, simular antes de escribir y deshacer donde se pueda.

**Lo que ya existía antes** (v518): los tres modos `off`/`read`/`write` por sitio y por red; en «Sólo lectura», el
Asistente IA del escritorio ya creaba o actualizaba productos, variaciones y catálogo con su tarjeta
(`Executor::assistant_writes()`); la bitácora (`Log`) de cada escritura, sin valores; los secretos enmascarados; el
permiso de WooCommerce por recurso y acción (capacidades) y el mínimo por herramienta. No había forma de dejar escribir
sólo en una parte de la tienda, ni simulación, ni deshacer: crear productos desde un cliente externo en «Sólo lectura»
obligaba a usar la REST de WooCommerce directamente.

**Permiso por herramienta.** El modo `custom` («Escritura sólo en lo elegido») guarda, junto al modo, la lista
`write_tools` (`Settings::WRITE_TOOLS`: `products`, `catalog`, `orders`, `customers`, `coupons`, `store-settings`,
`skydropx`). `Settings::can_write( $tool )` es la única regla: la usan `Executor` (también para el Asistente),
`Skydropx::run()`, las descripciones (`Tools::definition()` dice, herramienta por herramienta, si escribe) y la pantalla.
La lista y el modo se heredan juntos (`own` = `mode`): la red guarda los dos, y un sitio que personaliza guarda los dos;
volver a heredar conserva lo del sitio. Las casillas van en un grupo (`#digitalisimo-woo-mcp-tools`) que «Heredar de la
red» deshabilita con `data-group` (el script de herencia de «Rendimiento» lo admite desde esta versión); un campo oculto
`write_tools_sent` distingue «todas desmarcadas» de «grupo deshabilitado». Sin elegir `custom`, nada cambia.

**Simulación (`dry_run`).** Toda herramienta que escribe, salvo Skydropx, admite `dry_run: true` (y lo declara en
`meta.digitalisimo.dry_run`, que el Asistente de IA Tools usa para mostrar la simulación antes de confirmar algo
destructivo). Valida la entrada y exige la misma capacidad que la escritura; corre también en «Sólo lectura» (sólo lee) y
responde `allowed` (si este sitio dejaría hacerlo) con `reason` si no, y `would` (`Simulation`):
- update: `changes` (campo, valor actual y nuevo, sólo de lo enviado) y `unchanged`; en un mapa `settings` baja un nivel.
- create: los campos que se mandarían y lo que WooCommerce haría solo (publicar un producto sin `status`, devolver el
  dinero con `api_refund`).
- delete: el objeto (campos de identificación, sin datos personales) y si va a la papelera o se borra para siempre.
- batch: recuento por lista y la diferencia de cada update (un GET por elemento, 100 como mucho).
- undo: lo que se revertiría o, si no se desharía (conflicto, ya deshecho, sin deshacer), `allowed: false`, `would: null` y el motivo en `reason`, sin error.
Nunca hace `POST`, `PUT` ni `DELETE`, ni deja entrada en la bitácora. Un secreto sale como la máscara también aquí.
Skydropx no tiene simulación (sus acciones validan todo antes de escribir y responden qué hicieron): queda pendiente.

**Registro de cambios y deshacer** (`Changes`). Cada escritura hecha deja una entrada en
`digitalisimo_elements_woo_mcp_changes` (opción del sitio sin carga automática, últimas 50 de los últimos 30 días, 32 KB
por entrada como mucho) y la respuesta lleva `change` con su `id`, `undo` y, si no se puede deshacer, `reason`. La
bitácora sigue aparte y sin valores. Sólo se guardan valores de lo que se puede deshacer:
- update de `product`, `variation`, `category`, `tag`, `brand`, `attribute`, `attribute_term`, `shipping_class` o
  `coupon`: el valor de antes (una lectura previa, GET) y el de después de **las claves enviadas**;
- create de lo mismo: el ID y la fecha de modificación;
- delete a la papelera (sin `force`) de un producto o un cupón: el ID.
Lo demás queda con `undo: false` y el motivo, sin valores: lotes, pedidos, notas, reembolsos, clientes y reseñas (datos
personales o dinero), ajustes, envíos, impuestos y pasarelas (pueden llevar claves), borrados definitivos, cambios con
`meta_data` (WooCommerce combina los metadatos) o con los correos de un cupón (`email_restrictions`, `used_by`), y
updates de un producto o una variación que tocan `type`, `attributes` o `default_attributes` (de ellos dependen sus
variaciones: volver atrás no las quitaría ni las devolvería).

**El Asistente en «Sólo lectura».** Lo que escribe por su excepción queda en el registro igual, pero su respuesta dice
`undo: false` con el motivo: deshacer (que puede borrar) no entra en esa excepción. Lo deshace después quien tenga la
escritura de esa herramienta permitida.

`undo` (en `products`, `catalog` y `coupons`, con `change_id`; `dry_run` también) es una escritura: sujeta al modo, a la
capacidad de la acción inversa (editar para un update; borrar para un create o una papelera) y a la bitácora. Comprueba
antes que nadie lo cambió:
- update: cada clave debe seguir con el valor «después»; si no, no toca nada y dice qué campos cambiaron (409). En un
  producto, además, no debe tener variaciones creadas después del cambio (se guardan las que tenía).
- create: un producto o un cupón va a la papelera si no cambió desde que se creó; una variación (sin papelera) se borra
  sólo en ese caso; una categoría, etiqueta, marca, clase de envío o término, sólo si ningún producto ni variación lo
  usa en ningún estado (`get_objects_in_term()`: el `count` de la REST sólo cuenta los publicados, comprobado en
  WooCommerce 11.2.1, así que un borrador, un privado, un pendiente o uno en la papelera lo dejarían sin término) y no
  tiene hijos (`get_term_children()`); un atributo, sólo si no tiene términos.
- papelera: `wp_untrash_post()` con el estado que tenía (no el borrador que WordPress pone por defecto), si sigue en la
  papelera y el usuario puede borrarlo (`delete_post`).
Un cambio se deshace una vez; después la entrada queda marcada (`undone`) y sin valores. `reports` → `changes` lista el
registro sin valores (qué campos cambió cada entrada y si se puede deshacer), con `manage_woocommerce` como la bitácora.

**Qué configura el dueño.** Nada, si quiere seguir como estaba. Para que un cliente MCP externo cree o cambie productos
sin abrir toda la tienda: «Rendimiento» → «WooCommerce en el MCP» → «Escritura sólo en lo elegido» y marcar «Productos y
variaciones» (y lo que quiera).

## Guardas

- **Papelera.** `delete` manda a la papelera; `force: true` (borrado definitivo) sólo si el cliente lo pide. Donde
  WooCommerce no tiene papelera (variaciones, notas, reembolsos, términos, atributos, clientes, zonas, métodos,
  impuestos) un delete sin `force` se rechaza antes de llamar a WooCommerce. WooCommerce borra los elementos de un lote
  sin papelera: un `batch` con `delete` exige `force: true`.
- **Reembolsos.** `api_refund` es `false` por defecto: el reembolso se registra sin mover dinero. Sólo con
  `api_refund: true` de primer nivel se pide a la pasarela que devuelva el dinero (un `api_refund` dentro de `data` se
  ignora).
- **Secretos.** En pasarelas, métodos de envío y opciones de ajustes, todo campo de tipo `password` y todo campo cuyo ID
  nombra una contraseña (`password`, `passwd`, `passphrase`, `…_pass`), un secreto, un token, una firma, una sal, un
  HMAC, una credencial o una clave (`key`, `…_key`: `transaction_key`, `merchant_key`, `hash_key`, `webhook_key`…),
  salvo las claves públicas evidentes (`publishable_key`, `public_key`, `site_key`), sale como `••••••••` con
  `"masked": true`; nunca el valor ni parte de él. Es una heurística por nombre: un secreto guardado en un campo de texto
  con un nombre que no lo delata (p. ej. `client_id`, que suele ser público) sale tal cual. Escribirlo sí se permite; un
  valor vacío o la máscara conservan el guardado. Los `meta_data` de productos, pedidos y clientes no se enmascaran:
  salen como los devuelve WooCommerce.
- **Bitácora.** Cada escritura (también las rechazadas por el modo) queda en `digitalisimo_elements_woo_mcp_log`
  (opción del sitio sin carga automática, últimas 200): fecha UTC, ID del usuario, herramienta, acción, recurso, ID y
  padre de la ruta, elementos de un lote, resultado (`ok`, `partial`, `error` o `denied`), código de error y estado HTTP. Sin nombres, correos, direcciones,
  importes ni valores. Se consulta con `reports` → `log`.
- **Sin telemetría.** Ninguna herramienta emite eventos ni se conecta fuera del sitio.

## Límites conocidos

- Las anotaciones MCP son por herramienta, no por acción: las seis herramientas con delete se anuncian destructivas
  aunque `list` y `get` no lo sean.
- `data` no se cierra campo a campo: lo valida la API REST de WooCommerce con su propio esquema (sus mensajes de error
  llegan tal cual, en el idioma del sitio).
- Las respuestas de clientes y pedidos llevan los datos personales que WooCommerce devuelve; sólo la bitácora va sin
  ellos.
- Los informes son los de la API REST v3 (`reports/*`); los de WooCommerce Analytics (`wc-analytics`) no están.
- Webhooks, estado del sistema y herramientas de mantenimiento de WooCommerce no se exponen.

## Pruebas

- `tests/elements-woo-mcp.php` (en `validate.yml` y `release.yml`; PHP 7.4, 8.2 y 8.5): definiciones, tabla
  acción → ruta sin inyección (fuzz), modos con herencia, guardas, secretos, bitácora, permisos y registro; desde la
  tanda B, `custom` con herencia y la pantalla como un navegador (B3-permisos), `dry_run` (B3-dry_run) y el registro con
  deshacer, conflictos, retención y privacidad (B3-deshacer).
- `tests/playground/woo-mcp.sh` (manual, en la PC): Playground con WooCommerce 11.2.1 fijado y JSON-RPC real contra
  `/wp-json/elementor/mcp`, en un sitio y en una red.
- `tests/elements-woo-skydropx.php` (en `validate.yml` y `release.yml`; PHP 7.4, 8.2 y 8.5): registro sólo con Skydropx
  (y ningún archivo cargado sin él), definición, permisos, modos y bitácora, cada acción, unidades, lote con tope, metas
  del plugin, ningún secreto y, en un subproceso, un plugin de otra versión sin piezas.
- `tests/playground/skydropx-mcp.sh` (manual, en la PC): WooCommerce 11.2.1 y Skydropx 2.1.5 reales con la plataforma
  simulada por `pre_http_request`, JSON-RPC real y la pantalla del pedido y la guía abiertas con la sesión del
  administrador.
