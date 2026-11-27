# DIGITALÍSIMO Hosting en el MCP de Elements y el dominio antes de pagar

Pedido del propietario del 2026-11-26: el agente que arma las landings por MCP es administrador pero no podía configurar
`digitalisimo_hosting_options` (sólo el formulario de wp-admin), así que el buscador de dominios quedaba sin productos; y
el flujo de compra no distinguía «dominio nuevo», «uno que ya tengo» ni la transferencia.

## Herramientas MCP (`digitalisimo-elements/modules/digitalisimo-hosting/`)

Sólo existen con el MCP del sitio activo y DIGITALÍSIMO Hosting cargado con su API (`Tools::hosting_ready()`). Se
registran como las de tienda y del kit de ventas (registro de abilities del núcleo o, sin él, `wp_register_ability()` y
`elementor/mcp/server/tools`), fuera de la REST de abilities.

| Herramienta | Permiso | Acciones |
| --- | --- | --- |
| `digitalisimo-hosting-settings` | `manage_options` | `get`, `update`, `log`, `manual-payment` |
| `digitalisimo-hosting-domain-product` | `manage_woocommerce` o `manage_options` | `list`, `mark`, `unmark` |

- **get** devuelve todos los ajustes que usa el sitio, sin ningún secreto: de las claves (`api_key`, `webuzo_api_key`,
  `banxico_token`) y de la cuenta de Namecheap sólo si están puestas (`secrets_set`, `namecheap.configured`). Con
  «Heredar», el servidor Webuzo y los nameservers de la red no se muestran (`inherited`), como en la pantalla. Trae lo
  detectado (2026-11-27, sin IDs ni «|»): `domains` (extensión, registro o transferencia, producto y precio),
  `hosting_products` (paquete de Webuzo y periodo), `duplicates` e `incomplete`. Registradores: `registrar`
  (predeterminado, `namecheap` o `dynadot`) y `registrar_by_tld` (objeto por extensión: `{".mx": "dynadot"}`) en
  `settings`; `dynadot.configured` y `secrets_set.dynadot_api_key` dicen si la clave de Dynadot está puesta, nunca
  su valor; `registrars` trae el predeterminado, el de cada extensión que se vende (`effective`) y cuáles tienen cuenta.
- **update** combina con lo guardado: lo que no llega no cambia (la misma lección que `update-page-settings` antes de
  Elements 4.3.0.195). Valida con el mismo `Digitalisimo_Hosting::store()` que el formulario, escribe sólo la opción del
  sitio (nunca la de red) y devuelve el resultado, `changed` y `adjusted` (lo que la validación guardó distinto).
  `registrar` y `registrar_by_tld` se escriben: un registrador desconocido o una extensión que los registradores no
  venden detiene todo. Las listas del buscador son `finder_order` (lista), `finder_requirements` (objeto por extensión)
  y `finder_upsell` (lista de `product_id` de los planes detectados). `domain_products`, `hosting_product_ids`,
  `hosting_category_ids` y `finder_upsell_ids` ya no existen: enviarlas da `digitalisimo_hosting_removed_key`.
  Sólo lectura (`MCP_READONLY`): la cuenta de Namecheap, la clave de Dynadot y «Heredar», el servidor, el usuario y la clave de Webuzo, los
  nameservers, el token de Banxico, el interruptor del módulo (`enabled`), la venta automática (`provision_enabled`,
  `own_domain_auto`), los días de gracia y los porcentajes de la fórmula de precios (colchón, margen, comisión, IVA y
  margen mínimo: el freno contra vender con pérdida). Una clave desconocida, de sólo lectura o con otro tipo detiene
  todo sin guardar nada. Las dos herramientas se anuncian como escrituras destructivas y no idempotentes, para que el
  cliente pida confirmación.
- **mark** marca el producto como Dominio (`_digitalisimo_hosting_type` = `domain`, `_digitalisimo_tld` y
  `_digitalisimo_domain_kind` = `register` o `transfer`), con la extensión validada contra la lista de los registradores;
  **unmark** lo deja en «Ninguno».
- **manual-payment** (`manage_woocommerce` o `manage_options`) registra un pago recibido por otro lado (`service`,
  `months`, `method` cash|transfer|deposit|other, `amount`, `note`, `renew_domain` —sin decirlo, un servicio que es sólo
  el dominio se renueva—, `renewed_elsewhere`, `request_id`) con `Digitalisimo_Hosting_Provisioning::manual_payment()`: el mismo que
  el botón «Registrar pago manual» de Servicios de hosting (idempotente, con candado por servicio). La bitácora anota medio,
  periodo y monto como nombres de claves.
- **Bitácora** `digitalisimo_elements_hosting_mcp_log` (opción del sitio, sin carga automática, 200 entradas): fecha
  UTC, ID del usuario, herramienta, acción, los nombres de las claves cambiadas o el ID del producto, resultado y código.
  Nunca valores ni datos personales; también lo denegado.

## Productos de dominio y de hosting (`includes/class-hosting-products.php`, `class-hosting-product-editor.php`)

Desde el 2026-11-27 no hay IDs en los ajustes. Cada producto (simple o variable) y cada variación lleva en su editor la
pestaña «DIGITALÍSIMO Hosting»: Ninguno, Dominio u Hosting (en una variación, también «Igual que el producto»).

- **Dominio**: la extensión de un desplegable con buscador (`Digitalisimo_Hosting_Lists::tlds()`: la lista de los
  registradores con cuenta —`namecheap.domains.getTldList` y `tld_price` de Dynadot—, guardada un día; sin conexión, la
  guardada o una base de extensiones comunes, con aviso; nunca texto libre) y «Registro» o «Transferencia» (`*` =
  cualquier extensión, sólo en transferencia). Siempre anual.
- **Hosting**: el paquete de Webuzo (`act=plans` después de la consulta de cuentas de sólo lectura, guardado un día; sin
  conexión, los paquetes que ya usan los productos) y el periodo de cobro (`_digitalisimo_hosting_period`: 1, 3, 6, 12,
  24 o 36 meses).

Lo detectado (`Digitalisimo_Hosting_Products::catalog()`, en un transitorio que se invalida al guardar un producto o una
marca) reemplaza a «Productos por extensión»: los ajustes lo muestran como lista, con los repetidos (misma extensión y
acción en dos productos: se usa el de menor ID) y las extensiones sin precio del registrador. El Escritorio, Plugins, los
productos y la pantalla Hosting avisan con la X de los repetidos o de un sitio que vende hosting sin dominios.

**Migración** (`Digitalisimo_Hosting_Products::migrate()`, en `init`, una vez por sitio): las líneas `.com|ID`, la marca
vieja, el SKU `dominio-com` y el de transferencia, los IDs y categorías de hosting y los productos con paquete pasan a
marcas de los productos (el hosting de antes queda anual); `registrar_by_tld`, `finder_requirements` y `finder_order`
pasan a filas por extensión y `finder_upsell_ids` a `finder_upsell`. Lo viejo se copia tal cual en
`digitalisimo_hosting_legacy_settings` y sale de los ajustes. Nunca toca un producto que ya tiene tipo.

## El dominio antes de pagar (`includes/class-hosting-checkout.php`)

- **Plan de hosting o correo** (producto o variación marcado como «Hosting»): con `ask_domain` (encendido
  de fábrica) no se paga sin «Dominio nuevo» (un producto de dominio en el carrito) o «Uno que ya tengo» (el nombre
  exacto, que queda en la línea del plan como «Dominio»). «Exigir dominio y hosting juntos» cuenta el dominio propio.
  Con «Dominio nuevo», el campo de «Uno que ya tengo» se desactiva en el navegador y el servidor lo ignora. Con
  `ask_domain` apagado no se pintan el panel ni los campos del plan.
- **Registro (B1)**: el nombre llega con el buscador (`?domain_name=`), el campo del producto o el panel del carrito, y se
  comprueba con `namecheap.domains.check` antes de pagar (disponible, no premium, de la extensión del producto).
- **Transferencia**: el dominio y el código EPP antes de pagar; formato, registrado, extensión transferible por la API
  (`namecheap.domains.getTldList`) y que no esté ya en nuestra cuenta (con un texto neutro que no lo revela). Sólo se
  vende para una extensión configurada con producto de registro (con él se renueva; `*` = cualquiera de ésas) y con el
  mismo freno de pérdida que el registro (`Digitalisimo_Hosting_Pricing::guard()`) sobre el costo de transferencia de
  Namecheap (o el de registro si no lo da), antes de pagar y otra vez antes de aprovisionar. El EPP se cifra al entrar al carrito (el mismo
  cifrado que la contraseña de la cuenta), sólo se acepta en el cuerpo de la petición (nunca en la URL), pasa cifrado a
  la meta del pedido `_digitalisimo_epp` y se retira de la REST de pedidos (v3 y v4, y por tanto del MCP de la tienda).
  «Ver código EPP» (caja del pedido, `manage_woocommerce`, nonce por pedido y dominio, sin caché) es la única forma de
  verlo, y deja una nota privada de quién lo consultó. En el aprovisionamiento pasa por la misma revisión de propiedad
  que un dominio propio y queda para hacerse a mano; cuando el dominio llega a la cuenta de Namecheap, la revisión
  diaria la da por terminada, el servicio se renueva con el producto de registro de su extensión y el EPP se borra.
  Si al renovar la transferencia no ha terminado, la renovación no cobra el dominio y avisa al administrador. El EPP
  también se borra al cancelar, reembolsar o fallar el pedido y, al reintentar con otro dominio, el del anterior.
- **Dónde se elige**: el panel «Tu dominio» (carrito y checkout clásicos, también con errores del carrito, y los de
  bloques por `render_block`), los campos de la página del producto y el cuerpo de `cart/add-item` de la Store API
  (`digitalisimo_domain`, `digitalisimo_domain_mode`, `digitalisimo_epp`).
- Las consultas hechas mientras el visitante espera tardan 15 s como mucho, se guardan unos minutos (una falla, un
  minuto) y tienen su propio tope por visitante.

## Buscador (`assets/domain-form.js`)

Un solo script para el formulario de Elements clásico (`.elementor-form`), el nuevo del editor atómico (`e-form`) y
`[digitalisimo_domain_search]`, que ahora es un `<div>` (funciona dentro de un formulario atómico). Toma el envío en la
fase de captura, antes que jQuery o Alpine; usa siempre las extensiones configuradas (sustituye las opciones fijas del
campo «extensión»; sin ese campo consulta todas) y pinta todo con `textContent`. Toma un formulario con un campo
`dominio`/`domain` si la página tiene `[resultado_dominio]` o si lleva la clase `digitalisimo-domain-form`.

## Pruebas

`tests/hosting-mcp-settings.php`, `tests/hosting-domain-checkout.php`, `tests/elements-hosting-mcp.php`,
`tests/hosting-domain-search-chrome.mjs` (en los dos workflows) y `tests/playground/hosting-checkout.sh` (MCP real,
compras de invitada por la Store API y página atómica con el buscador).
