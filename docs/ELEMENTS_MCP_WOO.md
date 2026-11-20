# WooCommerce en el MCP de DIGITALÍSIMO Elements

DIGITALÍSIMO Elements trae el servidor MCP del constructor (núcleo incluido): `POST /wp-json/elementor/mcp`, apagado
por defecto en cada sitio. Con WooCommerce activo, ese mismo servidor ofrece siete herramientas para administrar la
tienda del sitio y, con el plugin de Skydropx activo, una octava para sus envíos (sección «Skydropx»). El código vive en `digitalisimo-elements/modules/digitalisimo-woo/mcp/` y nunca se edita nada dentro
de `digitalisimo-elements/elementor/`.

## Cómo conectarse

1. **Activar el MCP del sitio** en la pantalla «MCP» del menú de DIGITALÍSIMO Elements (guarda la opción
   `elementor_mcp_enabled` del sitio; viene apagada).
2. **Elegir el modo** «WooCommerce en el MCP» en la pantalla «Rendimiento» de DIGITALÍSIMO Elements, sección
   «WooCommerce en el MCP» (ver «Modos»). Por defecto, sólo lectura.
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
| `digitalisimo-woo/products` (`digitalisimo-woo-products`) | `product` y `variation` (con `product_id`): list, get, create, update, delete, batch |
| `digitalisimo-woo/catalog` | `category`, `tag`, `brand`, `shipping_class` (`term_id`), `attribute` (`attribute_id`), `attribute_term` (`attribute_id` y `term_id`), `review` (`review_id`): list, get, create, update, delete |
| `digitalisimo-woo/orders` | `order` (`order_id`): list, get, create, update, delete; `note`: list, get, create, delete; `refund`: list, get, create, delete |
| `digitalisimo-woo/customers` | `customer` (`customer_id`): list, get, create, update, delete; `download`: list |
| `digitalisimo-woo/coupons` | `coupon_id`: list, get, create, update, delete, batch |
| `digitalisimo-woo/store-settings` | `setting_group` (list), `setting` (`group_id`, `setting_id`: list, get, update, batch), `shipping_zone` (`zone_id`), `shipping_zone_location` (list y update, que reemplaza todas), `shipping_zone_method` (`zone_id`, `instance_id`), `shipping_method` (`method_id`: list, get), `tax_class` (`tax_class`: list, create, delete), `tax_rate` (`rate_id`, también batch), `payment_gateway` (`gateway_id`: list, get, update) |
| `digitalisimo-woo/reports` | sólo lectura: list, sales, top_sellers, orders_totals, products_totals, customers_totals, coupons_totals, reviews_totals y `log` (la bitácora) |
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

## Modos

«WooCommerce en el MCP», en la pantalla «Rendimiento» (sitio y red), sección propia con su formulario:

- **Apagado:** no se registra ninguna herramienta de tienda.
- **Sólo lectura** (predeterminado): list, get y los informes. create, update, delete y batch devuelven un error que
  dice dónde se cambia el modo.
- **Lectura y escritura:** todo, con la bitácora.

Paridad sitio/red como en «Rendimiento»: la red define el valor (sin «Heredar de la red») y cada sitio lo hereda o lo
personaliza (con «Heredar de la red»). Opciones `digitalisimo_elements_woo_mcp` (sitio, sin carga automática; sólo se lee con el MCP del sitio activo) y
`digitalisimo_elements_woo_mcp_defaults` (red). Sin WooCommerce (o con uno anterior a `Woo\Module::MIN_WC`) o con el
MCP del sitio apagado, no hay herramientas de tienda.

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
  acción → ruta sin inyección (fuzz), modos con herencia, guardas, secretos, bitácora, permisos y registro.
- `tests/playground/woo-mcp.sh` (manual, en la PC): Playground con WooCommerce 11.2.1 fijado y JSON-RPC real contra
  `/wp-json/elementor/mcp`, en un sitio y en una red.
- `tests/elements-woo-skydropx.php` (en `validate.yml` y `release.yml`; PHP 7.4, 8.2 y 8.5): registro sólo con Skydropx
  (y ningún archivo cargado sin él), definición, permisos, modos y bitácora, cada acción, unidades, lote con tope, metas
  del plugin, ningún secreto y, en un subproceso, un plugin de otra versión sin piezas.
- `tests/playground/skydropx-mcp.sh` (manual, en la PC): WooCommerce 11.2.1 y Skydropx 2.1.5 reales con la plataforma
  simulada por `pre_http_request`, JSON-RPC real y la pantalla del pedido y la guía abiertas con la sesión del
  administrador.
