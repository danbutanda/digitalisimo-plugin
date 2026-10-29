# F4-Woo · WooCommerce visual en DIGITALÍSIMO Elements

Evidencia y contratos de la etapa F4-Woo de `docs/FUSION_OBJECTIVE.md`. La especificación vive fuera del repositorio; este documento recoge lo verificado. Cada frente añade su sección.

## 1. Hechos de WooCommerce y de la WP Consent API (frente 0)

Copias oficiales de wordpress.org, sólo leídas y nunca ejecutadas, anotadas en `reference/OFFICIAL-SOURCES.txt` (fuera de Git):

| Copia | Origen | sha256 del ZIP | Archivos |
|---|---|---|---|
| WooCommerce 11.2.1 | `https://downloads.wordpress.org/plugin/woocommerce.11.2.1.zip` | `5317434cb381b5fdc7c1dd6ee258324f08c390976aaf8d1199f2a0e9a3ab8586` | 5 488, todos iguales a `plugin-checksums/woocommerce/11.2.1.json` |
| WP Consent API 2.1.0 | `https://downloads.wordpress.org/plugin/wp-consent-api.2.1.0.zip` | `f4fb971b3f47134ad67b7efdba8150a32e0bc5ca14328ae3cd2c4aebd85f7892` | 17, todos iguales a `plugin-checksums/wp-consent-api/2.1.0.json` |

En esta sección, `W:` es una ruta de WooCommerce 11.2.1 y `C:` una de la WP Consent API 2.1.0.

### 1.1 Firma de `wp_add_cookie_info()` (V.4.6; deja de ser [C])

`C:inc/api-functions.php:392` (y el método que envuelve, `C:inc/class-wp-consent-api-cookie-info.php:85`):

```php
function wp_add_cookie_info( string $name, string $plugin_or_service, string $category, string $expires,
	string $cookie_function, string $collected_personal_data = '', bool $member_cookie = false,
	bool $administrator_cookie = false, string $type = 'HTTP', string $domain = '' ): void
```

- `$type` es el **noveno** argumento y admite `'HTTP'`, `'LOCALSTORAGE'` o `'API'` (`C:inc/api-functions.php:389`); se guarda con `sanitize_text_field()` (`C:inc/class-wp-consent-api-cookie-info.php:101`). La categoría pasa por `wp_validate_consent_category()` (`:94`); `functional` es válida (`C:inc/api-functions.php:383`; una categoría desconocida pasaría a `functional`, `:127-133`).
- Llamada de F4-Woo (`Woo\Privacy::register_cookie_info()`, en `init`): `wp_add_cookie_info( Privacy::storage_key(), 'DIGITALÍSIMO Elements', 'functional', 'Hasta 365 días', …, 'Números de productos', false, false, 'LOCALSTORAGE' )`. La clave es `digi_wishlist` o, en una red, `digi_wishlist_<sitio>`.
- Una versión de la API sin `$type` en esa posición (se comprueba con `ReflectionFunction`) no recibe la declaración: el texto de la política ya describe el almacenamiento local. `tests/elements-woo-module.php` prueba la firma de 2.1.0.
- La API requiere PHP 7.4 y WordPress 5.0 (`C:readme.txt:4,8`), igual que Elements.

### 1.2 Versiones (D3: WooCommerce ≥ 10.0; sólo 11.2.1 probado)

Sólo existe la copia 11.2.1, así que «existe en 10.0» se comprueba con los `@since` de 11.2.1 y, donde no hay `@since`, con que el símbolo es anterior a las funciones experimentales (todos los que siguen aparecen en WooCommerce 3.x o antes). Una copia oficial de 10.0 permitiría un `WOO_MIN` (V.16.1).

**Sin detección (existen antes de 10.0):**

| Símbolo | Lugar en 11.2.1 | `@since` |
|---|---|---|
| `wc_load_cart()` | `W:includes/wc-core-functions.php:2539` | 3.6.4 |
| `wc_coupons_enabled()` | `W:includes/wc-coupon-functions.php:71` | 2.5.0 |
| `wc_dropdown_variation_attribute_options()` y su filtro `woocommerce_dropdown_variation_attribute_options_html` | `W:includes/wc-template-functions.php:3736,3807` | 2.4.0 |
| `wc_get_attribute_taxonomies()`, `wc_attribute_taxonomy_name()`, `wc_attribute_taxonomy_id_by_name()` | `W:includes/wc-attribute-functions.php:55,143,194` | 3.6.0 (la versión en caché), — , 2.6.0 |
| `wc_get_attribute()`, `wc_create_attribute()`, `wc_update_attribute()`, `wc_delete_attribute()` | `W:includes/wc-attribute-functions.php:472,511,696,738` | 3.2.0 |
| `wc_get_attribute_types()` | `W:includes/wc-attribute-functions.php:272` | 2.4.0 (la entrada `wc-visual`, desde 10.9: §1.3) |
| `woocommerce_attribute_added`, `…_updated` (con `$old_slug`), `…_deleted` | `W:includes/wc-attribute-functions.php:591,615,778` | — |
| `woocommerce_after_add_attribute_fields`, `woocommerce_after_edit_attribute_fields` | `W:includes/admin/class-wc-admin-attributes.php:621,389` | — |
| `wc_delete_product_transients()`, `wc_product_has_unique_sku()`, `wc_get_product()` | `W:includes/wc-product-functions.php:130,1045,71` | —, 2.2, 2.2.0 |
| `wc_get_product_category_list()` | `W:includes/wc-product-functions.php:1746` | la función es antigua; `@since 11.2.0` sólo anota el argumento `$orderby`, que F4-Woo no usa |
| `wc_get_rating_html()`, `wc_get_stock_html()` | `W:includes/wc-template-functions.php:4377,4342` | 3.0.0 |
| `wc_price()`, `wc_get_price_decimals()`, `wc_print_notices()`, `wc_get_template()` | `W:includes/wc-formatting-functions.php:597,568`; `W:includes/wc-notice-functions.php:143`; `W:includes/wc-core-functions.php:303` | —, 2.3, 3.5.0 (el argumento `$return`), — |
| `woocommerce_variable_add_to_cart()`, `woocommerce_template_single_add_to_cart()` | `W:includes/wc-template-functions.php:2226,2165` | — |
| `WC_Product::is_visible()` (filtro `woocommerce_product_is_visible`), `get_price_html()`, `add_to_cart_url()`, `set_parent_id()` | `W:includes/abstracts/abstract-wc-product.php:1748,2050,2169,1185` | — |
| `WC_Product_Variable::sync()` | `W:includes/class-wc-product-variable.php:710` | — |
| `WC_Shipping_Zones::get_zone_matching_package()`, `WC_Shipping_Zone::get_shipping_methods()` | `W:includes/class-wc-shipping-zones.php:395`; `W:includes/class-wc-shipping-zone.php:159` | 2.6.0, — |
| `WC_Cart::needs_shipping()`, `show_shipping()`, `get_shipping_packages()`, `get_applied_coupons()` | `W:includes/class-wc-cart.php:1827,1858,1724,190` | 4.3.0, —, 1.5.4, — |
| `WC_Shipping::calculate_shipping()`, `calculate_shipping_for_package()`, `get_packages()`; filtro `woocommerce_package_rates` | `W:includes/class-wc-shipping.php:255,311,458,390` | —, —, —, 2.0.0 |
| `WC_Customer::has_calculated_shipping()` | `W:includes/class-wc-customer.php:259` | — |
| `WC_Shipping_Free_Shipping::is_available()`; filtros dinámicos `woocommerce_shipping_free_shipping_is_available` y `…_instance_option` | `W:includes/shipping/free-shipping/class-wc-shipping-free-shipping.php:160,212`; `W:includes/abstracts/abstract-wc-shipping-method.php:456,553` | — |
| `WC_Frontend_Scripts::load_scripts()` y `localize_printed_scripts()` (públicos y estáticos); `localize_script()` y `get_script_data()` son **privados** | `W:includes/class-wc-frontend-scripts.php:475,826,589,609` | — |
| `WC_Form_Handler::add_to_cart_action()` (en `wp_loaded`, 20) | `W:includes/class-wc-form-handler.php:1020,60` | — |
| `woocommerce_add_to_cart_form_action` | `W:templates/single-product/add-to-cart/simple.php:32`, `variable.php:33`, `grouped.php:24` | — (el `@since 10.0.0` de `AddToCartWithOptions.php:570` es el del bloque) |
| `woocommerce_add_to_cart_fragments` | `W:includes/class-wc-ajax.php:274` | — |
| `WC_Query::price_filter_post_clauses()`; `woocommerce_enable_post_clause_filtering` | `W:includes/class-wc-query.php:789,800` | 3.6.0, 9.9.0 |
| `woocommerce_product_attributes_filterer_hide_out_of_stock` | `W:src/Internal/ProductAttributesLookup/Filterer.php:88` | 9.8.0 |
| `woocommerce_ajax_variation_threshold` | `W:includes/wc-template-functions.php:2253` | — |
| `Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled()` (pública, no `Internal`) | `W:src/Utilities/FeaturesUtil.php:41` | — |

**Con detección (posteriores a 10.0, o experimentales):**

| Símbolo | Lugar | Desde | Cómo se detecta |
|---|---|---|---|
| `WC_Product_Data_Store_CPT::refresh_product_lookup_table()` | `W:includes/data-stores/class-wc-product-data-store-cpt.php:986` | 10.8.0 | `method_exists`; si falta, `wc_get_product( $id )->save()` (V.11.3) |
| `wc_get_product_gallery_html()` | `W:includes/wc-template-functions.php:1911` | 10.8.0 | `function_exists` (V.5.1) |
| Metas `color`/`image` de `wc-visual` (`VisualAttributeTermMeta`, interna) | `W:src/Internal/ProductAttributes/VisualAttributeTermMeta.php:17` | 10.9.0 | No se llama: se leen y escriben las metas con sus reglas (§1.3); el tipo `wc-visual` se reconoce por `attribute_type` |
| Store API `shopper-lists` y función `product_wishlist` | `W:src/StoreApi/RoutesController.php:96`; `W:src/Internal/Features/FeaturesController.php:676` | experimental | `class_exists( FeaturesUtil )`, `feature_is_enabled( 'product_wishlist' )` y la ruta registrada (V.4.1) |
| Guarda de `ProductUtil::append_product_sorting_table_join()` (interna) | `W:src/Internal/Utilities/ProductUtil.php:151,156-170` | 11.2.0 | No se llama: F copia su regla `\bwc_product_meta_lookup\b` y el alias (V.8.4) |
| Handle de blockUI | `W:includes/class-wc-frontend-scripts.php:305-309` (`wc-jquery-blockui`, `legacy_handle` `jquery-blockui`) | cambia entre versiones | Dependencias resueltas desde `wp_scripts()->registered` (V-M24) |

### 1.3 Metas de `wc-visual` (V.6.1; fixtures en `tests/fixtures/woo-visual-11.2.1.json`)

- **Claves:** `image` y `color` en el término (`W:src/Internal/ProductAttributes/VisualAttributeTermMeta.php:24-34` son los tipos `color`, `image` y `none`).
- **Escritura excluyente** (`save_term_visual()`, `:247-263`): con un adjunto imagen (`wp_attachment_is_image()`) se escribe `image = absint( $id )` y se borra `color` (`:248-251`); si no, con `sanitize_hex_color( $color )` válido se escribe `color` y se borra `image` (`:254-258`); si no, se borran las dos (`:261-262`).
- **Lectura** (`build_term_visual()`, `:124-147`): primero la imagen, si es un adjunto imagen con URL para el tamaño (`:125-136`); si no, `sanitize_hex_color()` del color (`:138-145`); si no, `none`.
- **Formato del color:** el de `sanitize_hex_color()` de WordPress: `#rgb` o `#rrggbb`, mayúsculas o minúsculas **tal cual** (sin normalizar), **sin canal alfa**; otro valor no se guarda.
- **Precarga** (`prime_term_visual_caches()`, `:91-115`): `update_meta_cache( 'term', … )` y `_prime_post_caches( $imágenes, false, true )`.
- **Pantalla** de WooCommerce: sólo en atributos `wc-visual` y sólo en la administración, en `created_term`/`edit_term` (`W:src/Internal/ProductAttributes/VisualAttributeTermAdmin.php:29-58,107-130`), con los campos `wc_visual_attribute_type`, `term_color` y `term_image` (`VisualAttributeTermMeta.php:183-198`).
- **Corrección a V.6.1 y V.11.5.** La función se llama `wc-visual-attribute` (`W:src/Internal/Features/FeaturesController.php:563`), no `wc-visual`. `wc_get_attribute_types()` ofrece el tipo `wc-visual` con un tema de bloques **y** la función activa, **o** si ya existe algún atributo `wc-visual` en el sitio, con cualquier tema (`W:includes/wc-attribute-functions.php:277-290`). Así:
  - «con la función apagada, WooCommerce no ofrece ese tipo» sólo es cierto si el sitio no tiene ningún atributo `wc-visual`;
  - en el caso de kits de V.11.5 (A con un `wc-visual`, B con la función apagada), el tipo vuelve a `select` (`W:includes/wc-attribute-functions.php:554-556`) **sólo si B no tiene ya un atributo `wc-visual`**. El caso debe preparar B sin ninguno.

### 1.4 Veredictos de V.2, comprobados contra 11.2.1

Las citas de V.2 se revisaron archivo por archivo (que existe y que la línea dice lo que V afirma). Rutas sin `W:` dentro de un grupo siguen a la anterior. Las rutas de `digitalisimo-elements/` (`modules/…`, `elementor/…`, `assets/js/…`) son del plugin, no de WooCommerce.

**Matriz corregida (V.2)**

| Función | WooCommerce 11.2.1 | Constructor (hoy) | F4-Woo |
|---|---|---|---|
| Lista de deseos | **Sí, experimental**: función `product_wishlist`, apagada por defecto, «requiere el bloque Add to Cart + Options» (`W:src/Internal/Features/FeaturesController.php:676-686`); sólo con sesión; almacén por sitio en la meta `_wc_shopper_list_wishlist_<prefijo>` (`W:src/Internal/ShopperLists/ShopperList.php:15,98`; `W:src/Internal/Utilities/Users.php:169-172`); rutas de la Store API sólo con la función activa (`W:src/StoreApi/RoutesController.php:79-96`); exportador y borrador **siempre** registrados (`W:src/Internal/ShopperLists/ShopperListsController.php:83-88`; `Privacy/Privacy.php:31-46`) | No | Widgets para el constructor y modo invitado; con la función activa, usa su Store API (V.4.1) |
| Vista rápida | No | No | Widget y parte de Productos |
| Muestras | **Sí, experimental** para temas de bloques: tipo `wc-visual` («Color / image»), metas `color` e `image` (`W:src/Internal/Features/FeaturesController.php:563-575,882-884`; `W:includes/wc-attribute-functions.php:272-300`; `W:src/Internal/ProductAttributes/VisualAttributeTermMeta.php:24-34,124-147,247-263`); las lee el selector de variaciones del bloque «Añadir al carrito con opciones», que no pasa por el filtro del formulario clásico (`W:src/Blocks/BlockTypes/AddToCartWithOptions/VariationSelectorAttribute.php:171-175`) | No | Muestras en el formulario clásico con **esas mismas metas** (V.6) |
| Barra de envío gratis | Método «Envío gratuito» con `min_amount`, `requires` e `ignore_discounts`; no muestra cuánto falta | No | Widget y rasgo, con las tarifas reales (V.7) |
| Cupón en el mini carrito | En carrito y pago (`wc-ajax=apply_coupon`); el bloque «Mini carrito» usa la Store API, no los fragmentos (`W:src/Blocks/BlockTypes/MiniCart.php:379-386`) | Sólo en el widget Carrito (`modules/woocommerce/widgets/cart.php:185`) | Rasgo del Menú del carrito (V.7) |
| Filtro de precio y de atributo | Widgets clásicos «Filtrar productos por precio» y «por atributo» (`W:includes/wc-widget-functions.php:38-39`) y bloques «Product Filters», **sólo sobre la consulta principal** (`woocommerce_enable_post_clause_filtering`, por defecto `is_main_query()`, `W:includes/class-wc-query.php:800`) | `taxonomy-filter` sirve los `pa_*` salvo `pa_color` y `pa_size` (`modules/loop-filter/traits/taxonomy-filter-trait.php:189-193`) | Widgets para el Loop Grid (AJAX) y para la consulta principal (GET), con las presentaciones de F4-Woo (V.8) |


**Veredicto de cada hecho de la v1 (V.2), con la comprobación del frente 0**

| # | Hecho | Veredicto en 11.2.1 | Evidencia | Uso en V | Frente 0 |
|---|---|---|---|---|---|
| W-1 | Fragmentos (`woocommerce_add_to_cart_fragments`, `wc-cart-fragments`, `get_refreshed_fragments`, `sessionStorage`, `wc_fragment_refresh`) | Correcto. Desde 7.8 `wc-cart-fragments` no se encola por defecto; depende de `jquery` y `wc-js-cookie`; cada fragmento se aplica con `$( key ).replaceWith( value )`; la copia de `sessionStorage` se reaplica si coincide el *hash* y caduca a las 24 h; `wc_fragment_refresh` sólo tiene manejador si hay almacenamiento | `W:includes/class-wc-ajax.php:265-284`; `W:includes/class-wc-frontend-scripts.php:258-262,729-736`; `W:assets/js/frontend/cart-fragments.js:38-67,46-50,75-82,117-157` | V.7; regla de eventos en V.12.3 | Sí. Las líneas citadas se sostienen (`includes/class-wc-ajax.php:265-284`, filtro en `:274`). |
| W-2 | Envío gratuito | Correcto en la regla (`init()` en `W:includes/shipping/free-shipping/class-wc-shipping-free-shipping.php:70-79`; `is_available()` en `:160-212`, con el filtro `woocommerce_shipping_free_shipping_is_available`); `min_amount` pasa por `woocommerce_shipping_free_shipping_instance_option` (`W:includes/abstracts/abstract-wc-shipping-method.php:553`); sin destino, la zona 0 (`W:includes/class-wc-shipping-zones.php:395-409`); `get_shipping_methods( true )` consulta la base en cada llamada | Ídem | V.7.1 | Sí. Los dos filtros son dinámicos: `woocommerce_shipping_free_shipping_is_available` sale de `'woocommerce_shipping_' . $this->id . '_is_available'` (`includes/shipping/free-shipping/class-wc-shipping-free-shipping.php:212`; también `abstract-wc-shipping-method.php:456`) y `…_instance_option` de `abstract-wc-shipping-method.php:553`. Hay que buscarlos así, no por su nombre literal. |
| W-3 | Cupón por `wc-ajax` | Parcial: `apply_coupon` lee `coupon_code`, `remove_coupon` lee **`coupon`**; respuesta = HTML de `wc_print_notices()`; nonce caducado = `-1` con 403 (`W:includes/class-wc-ajax.php:291-334`). El nonce de un invitado **no** depende de su sesión: `nonce_user_logged_out` sólo cambia acciones que empiezan por `woocommerce` (`W:includes/class-wc-session-handler.php:615-620`) | Ídem | V.7.2; D15 corregida | Sí. |
| W-4 | Formulario de variaciones | Correcto en la API; `onUpdateAttributes()` **borra** las opciones no asociadas y deshabilita las no `.enabled` (`W:assets/js/frontend/add-to-cart-variation.js:644-660`); en modo AJAX (más de 30 variaciones, `W:includes/wc-template-functions.php:2253`) sale antes (`:500-502`); «Limpiar» hace `.val('').trigger('change')` (`:113-117`) | Ídem | V.6.4: puente jQuery obligatorio en modo AJAX | Sí. |
| W-5 | Preselección y filtro del desplegable | Correcto; `$html` empieza siempre por `<select`; puede llevar `required` (`W:includes/wc-template-functions.php:3754-3759,3767,3776,3819`) | Ídem | V.6.3 | Sí; el filtro `woocommerce_dropdown_variation_attribute_options_html` está en `includes/wc-template-functions.php:3807`. |
| W-6 | Tipos y ganchos de atributos | Requiere corrección por `wc-visual` (arriba). Ganchos verificados: `woocommerce_after_add_attribute_fields` y `…_edit_…` sin argumentos (`W:includes/admin/class-wc-admin-attributes.php:389,621`); `woocommerce_attribute_added`, `…_updated` (con `$old_slug`) y `…_deleted` (`W:includes/wc-attribute-functions.php:591,615,778`), también desde REST, CSV y K; la página exige `manage_product_terms` (`W:includes/admin/class-wc-admin-menus.php:101`). La razón de no usar `attribute_type` propio se sostiene (`W:includes/wc-attribute-functions.php:554-556`) | Ídem | V.6 | Sí, con la corrección de `wc-visual` de §1.3 (el tipo se ofrece también sin la función si ya existe un atributo `wc-visual`). |
| W-7 | Parámetros nativos de la tienda | Correcto, sólo en la consulta principal (`W:includes/class-wc-query.php:566-620,789-834`; `W:src/Internal/ProductAttributesLookup/Filterer.php:40-140`) | Ídem | V.8.5 | Sí. |
| W-8 | «Ocultar productos agotados» | Correcto; con la tabla de atributos, `in_stock = 1` por variación (`Filterer.php:59-67`); la tabla se actualiza por Action Scheduler salvo `woocommerce_attribute_lookup_direct_updates` (`W:src/Internal/ProductAttributesLookup/LookupDataStore.php:159-185,796-797`) | Ídem | V.8.4, V.16.4 | **Corregir la línea:** `Filterer.php:59-67` es el inicio de `filter_by_attribute_post_clauses()`; el filtro `woocommerce_product_attributes_filterer_hide_out_of_stock` y `in_stock = 1` están en `:81-92` (y `:283-284` para los recuentos). `woocommerce_attribute_lookup_direct_updates` en `LookupDataStore.php:160`. |
| W-9 | Bloques «Mini carrito» y «Carrito y pago» | No usan fragmentos; el pago que crea WooCommerce es el bloque (`W:includes/class-wc-install.php:3260`) | Ídem | V.7.4, V.16.4 | Sí. |
| W-10 | Exportador y borrador | API de WordPress; el borrador devuelve `items_removed`, `items_retained`, `messages` y `done` | `modules/forms/submissions/personal-data.php:160-180` | V.4.5 | Sí (API de WordPress). |
| W-11 | Acción del formulario y `add_to_cart_action` | Correcto; sin redirección tras el `POST` salvo con la opción o el filtro (`W:templates/single-product/add-to-cart/simple.php:32`, `variable.php:33`; `W:includes/class-wc-form-handler.php:60,1020-1066`) | Ídem | V.5.5 | Sí; el filtro de la acción también está en `templates/single-product/add-to-cart/grouped.php:24`. |
| W-12 | `wc_product_meta_lookup` y `price_filter_post_clauses()` | Correcto (`W:includes/class-wc-query.php:789-834`); el JOIN va con el alias `wc_product_meta_lookup` y la guarda `\bwc_product_meta_lookup\b` de `ProductUtil::append_product_sorting_table_join()` (`W:src/Internal/Utilities/ProductUtil.php:150-168`, `@since 11.2.0`) | Ídem | V.8.4 | Sí; `ProductUtil` es `Automattic\WooCommerce\Internal\Utilities\ProductUtil` (`@since 11.2.0`, `:151`): **interna**, se copia su guarda `\bwc_product_meta_lookup\b` (`:166-167`), nunca se llama. |
| W-13 | Registrar `pa_*` en la misma petición | Correcto (`W:includes/import/abstract-wc-product-importer.php:787-803`); `wc_create_attribute()` borra el transitorio y la caché de atributos (`W:includes/wc-attribute-functions.php:678-683`) | Ídem | V.11.1 | Sí. |
| W-14 | Variaciones y ayudantes | Correcto; además, `refresh_product_lookup_table( $id )` del almacén de productos (`W:includes/data-stores/class-wc-product-data-store-cpt.php:979-988`, `@since 10.8.0`) | Ídem | V.11.1, V.11.3 | Sí; `refresh_product_lookup_table()` en `:986` con `@since 10.8.0`: necesita detección (§1.2). |
| W-15 | Sesión de WooCommerce en las rutas REST propias | **Falso:** sólo se carga el carrito en peticiones *frontend*, y REST no lo es (`W:includes/class-woocommerce.php:708-716,1010-1015`) | Ídem | `wc_load_cart()` en las rutas (V.4.3, V.5.1) | Sí: `is_request( 'frontend' )` excluye REST (`includes/class-woocommerce.php:713-714`) y sólo entonces `wc_load_cart()` (`:1013-1015`). |

**Otros hechos de V.2 comprobados**

- Lista de deseos de WooCommerce: el controlador se registra siempre (`W:includes/class-woocommerce.php:434`) y su `Privacy` registra exportador y borrador en `init` también con la función apagada (`W:src/Internal/ShopperLists/Privacy/Privacy.php:31-47`, no `Privacy/Privacy.php` a secas); las rutas de la Store API sólo si está activa (`W:src/StoreApi/RoutesController.php:79-96`).
- Store API: `is_user_logged_in()` en las rutas `shopper-lists` (`W:src/StoreApi/Routes/V1/ShopperListItems.php:48-49,66-80`), nonce `wc_store_api` (`ShopperListsNonceCheck.php:24,65-107`) y autenticación (`W:src/StoreApi/Authentication.php:160-168`): se sostienen.
- Variaciones en REST: `localize_printed_scripts()` en `wp_print_scripts` y `wp_print_footer_scripts` con prioridad 5 (`W:includes/class-wc-frontend-scripts.php:55-58`); plantillas `tmpl-variation-template` y `tmpl-unavailable-variation-template` (`W:templates/single-product/add-to-cart/variation.php:16-23`); `wc_variation_gallery_defaults` (`W:includes/wc-template-functions.php:2226-2256`): se sostienen.
- Carrito y envío (`W:includes/class-wc-cart.php:1647-1660,1858-1880`; `W:includes/class-wc-shipping.php:311-330,458-460`; `W:includes/class-wc-customer.php:259-261`): se sostienen. `get_packages()` no tiene `@since` propio (el `11.0.0` cercano es de otro método).
- `has_archives` (`W:includes/class-wc-post-types.php:266-314`) y el pago de bloques (`W:includes/class-wc-install.php:3260`): se sostienen.

## 2. Regla de eventos (V.12.3)

Los scripts de F4-Woo no dependen de jQuery. Con los eventos de WooCommerce se sigue esta regla fija, que también aplica la revisión estática:

- Un `dispatchEvent( new Event( 'x' ) )` nativo **sí** llega a un manejador de jQuery (`.on( 'x' )`), porque jQuery escucha con `addEventListener`. Así piden `cart-extras.js` y la lista de deseos un `wc_fragment_refresh`.
- Lo que WooCommerce dispara con `.trigger()` (`wc_fragments_refreshed`, `added_to_cart`, `found_variation`, `reset_data`) **nunca** llega a un `addEventListener` propio. Se observa con `MutationObserver`; por ejemplo, la barra y el cupón miran los nodos que WooCommerce reemplaza con `$( clave ).replaceWith()`. Sólo donde es imprescindible se usa el puente `window.jQuery`, sin declararlo como dependencia: el formulario de variaciones de la vista rápida (V.5.3) y el modo AJAX de las muestras (V.6.4).
- `wc_fragment_refresh` sólo tiene manejador si el navegador tiene almacenamiento (`W:assets/js/frontend/cart-fragments.js:75-82`). Sin él, la barra y el cupón se quedan como los pintó el servidor.
- La configuración en línea va siempre con `wp_add_inline_script( <handle propio>, …, 'before' )`, nunca con un `<script>` escrito a mano, para que una CSP con `nonce` pueda ponérselo.

## 3. Contrato del coordinador de filtros con el paquete de PRO (V.8.3, frente F)

`assets/js/filters.js` (8 161 B, `defer`, depende de `elementor-frontend`) se carga sólo en las páginas con un filtro de precio o de atributo de F4-Woo, y sólo actúa en los bucles que uno de esos filtros tiene como destino. Nunca edita el paquete compilado (`assets/js/elements-handlers.js`, sha256 fija): se engancha a lo público.

- **Dónde se engancha.** `elementorFrontend.hooks.addFilter( 'elementor-pro/frontend/handlers', h => …, 20 )`, después del filtro con el que `elements-handlers.js` aporta sus módulos (prioridad 10) y antes de que `frontend.js` cree los módulos en `initModules()`. Si en `h.taxonomyFilter` faltan `refreshLoopWidget()`, `getQueryStringInObjectForm()`, `setFilterDataForLoopWidget()` o `removeFilterFromLoopWidget()`, devuelve `h` sin tocar y los filtros de F4-Woo funcionan por GET.
- **Cuándo se engancha (corrección a V.8.3).** V.8.3 daba por hecho que `elementorFrontend.hooks` existe al evaluarse el script. No es así: `hooks` nace en `elementorFrontend.init()`, en el *DOM ready*, y en Chrome el oyente jQuery de PRO en `elementor/frontend/init` crea los módulos antes que cualquier oyente nativo del mismo aviso, incluso en captura. Con la versión evaluada en el navegador el coordinador no se activaba nunca y los filtros sólo funcionaban por GET (W9.8a lo detectó). Ahora `filters.js` registra el filtro de inmediato si `hooks` ya existe y, si no, en el aviso `elementor-pro/modules/init/before`, que `frontend.js` lanza en `initModules()` justo antes de aplicar el filtro (es como se engancha el propio `elements-handlers.js`); si `elementorProFrontend` aún no existe, espera a `DOMContentLoaded` para suscribirse. `filters-form.js` registra sus `element_ready` tras `elementor/frontend/init` y vuelve a recorrer los formularios al cargar la página. `tests/elements-woo-filters.mjs` (caso 1b) reproduce los dos órdenes de arranque y `tests/elements-pro-inventory.mjs` vigila que `frontend.js` siga lanzando ese aviso.
- **Qué sustituye.** Una subclase de `taxonomyFilter` cuyo `refreshLoopWidget( bucle, filtro )` llama al coordinador cuando el bucle está coordinado y a `super` en los demás. El coordinador hace **su propia petición** a `elementor-pro/v1/refresh-loop` con `AbortController` (la respuesta vieja se descarta), *debounce* (cinco cambios en 200 ms son una petición), `post_id` del **documento del filtro** (un bucle en una plantilla del Theme Builder se encuentra desde la página que lo muestra), `aria-busy` y región viva durante la carga, foco conservado, `popstate` que rehace la URL, el bucle y los controles, y `elementorFrontend.elementsHandler.runReadyTrigger()` tras insertar el HTML. Lee del almacén de PRO con `getQueryStringInObjectForm()` y sólo añade a la URL sus propias claves (`e-filter-<bucle>-…`).
- **Qué no cambia.** Un bucle sin filtros F4-Woo pasa por `super.refreshLoopWidget()` y se comporta como en 4.3.0.165; una página sin F4-Woo no carga el coordinador. Es la excepción declarada en `DIGITALISIMO_CHANGES.md` a «ningún widget existente cambia».
- **Nombres vigilados** (`tests/elements-pro-inventory.mjs`): el filtro y su clave `taxonomyFilter`, los cuatro métodos, el marcado `.e-filter-item[data-filter][aria-pressed]`, la ruta `elementor-pro/v1/refresh-loop` con `ElementorProFrontendConfig.urls.rest` y el aviso `elementor-pro/modules/init/before`.

## 4. Formatos y ganchos nuevos

- **Rutas REST** (`Digitalisimo\Elements\Woo\Rest`, espacio `digitalisimo-elements/v1`): `woo/wishlist` (lista con sesión), `woo/cards` (`POST`: las tarjetas de una lista de invitado a partir de IDs, sin guardarlos) y `woo/quick-view`. Cada respuesta lleva `Cache-Control: no-store, private` y las cabeceras de `nocache_headers()` (nunca el filtro global `rest_send_nocache_headers`); el cuerpo se limita a 8 KB y 64 niveles; `wc_load_cart()` al principio de las rutas que pintan precios o formularios, porque WooCommerce no carga el carrito en REST. Sin WooCommerce las rutas no existen (404).
- **Filtros propios:** `digitalisimo_elements_wishlist_limit` (100), `digitalisimo_elements_wishlist_guest_storage`, `digitalisimo_elements_wishlist_login_url`, `digitalisimo_elements_loop_filter_types`, `digitalisimo_elements_loop_filter_query` y `digitalisimo_elements_loop_filter_param_type` (los tres del derivado `loop-filter`, V.8.2), `digitalisimo_elements_privacy_items` (en `Page_Visits::privacy_text()`), y el punto genérico de K para los contadores de «no exportadas» (`docs/fusion/k-kits.md` §16).
- **Ganchos de WooCommerce que se usan:** `woocommerce_add_to_cart_fragments`, `woocommerce_add_to_cart_form_action`, `woocommerce_add_to_cart_redirect`, `woocommerce_after_add_to_cart_button`, `woocommerce_dropdown_variation_attribute_options_html`, `woocommerce_after_add_attribute_fields`, `woocommerce_after_edit_attribute_fields`, `woocommerce_attribute_added`, `woocommerce_attribute_updated` (con `$old_slug`: la configuración de muestras se mueve al renombrar) y `woocommerce_attribute_deleted`. No se engancha `woocommerce_enable_post_clause_filtering` ni se llama ninguna clase `Automattic\WooCommerce\Internal\…`.
- **Lista de deseos:** en el navegador, `localStorage[ 'digi_wishlist' ]` (o `digi_wishlist_<sitio>`) con `{ v: 1, items: [ [ id, alta ] ] }`; en la cuenta, la opción de usuario `digitalisimo_wishlist` con el prefijo del sitio y el mismo formato; con `product_wishlist` de WooCommerce activa, la Store API `shopper-lists` con el nonce `wc_store_api`.
- **Muestras:** opción del sitio `digitalisimo_elements_swatches` (`{ <atributo>: { type, shape, size } }`) y metas de término `digitalisimo_swatch_color` (`#rrggbb` o `#rrggbbaa`), `digitalisimo_swatch_image` (ID de adjunto del sitio) y `digitalisimo_swatch_label` (≤ 24 caracteres); en un atributo `wc-visual`, las metas `color` e `image` de WooCommerce con sus reglas (§1.3).
- **Carrito:** la bandera `digitalisimo_elements_woo_fragments` (0/1, carga automática) decide si se engancha `woocommerce_add_to_cart_fragments`; los nodos `div.digi-ship__state` y `div.digi-coupon__state` llevan el estado en `data-*` y el mismo HTML para todas las instancias. El filtro `woocommerce_cart_hash` (sólo con la bandera) añade al *hash* del carrito si el destino es conocido y su país, provincia, código postal y ciudad: `cart-fragments.js` sólo vuelve a pedir los fragmentos cuando cambia ese hash, y dar la dirección no cambia el total si la ubicación por defecto ya tenía la misma tarifa.
- **Filtros:** tipo `digitalisimo_price` del registro `digitalisimo_elements_loop_filter_types`, con `digi_price_min`/`digi_price_max` como claves del almacén (`e-filter-<bucle>-digi_price_min=10`); en «Tienda», los parámetros nativos de WooCommerce (`min_price`, `max_price`, `filter_<atributo>`, `query_type_<atributo>`), que sólo filtran la consulta principal.
- **Vista rápida:** `window.digitalisimoQuickView` en línea (antes del script) con la ruta y, si la página no trae `wc-add-to-cart-variation`, la configuración de variaciones: `scripts` (`handle` y `src` con `?ver=`, relativa a la raíz cuando el archivo está en este sitio) y `params` capturados de `wc_add_to_cart_variation_params`. Las plantillas `tmpl-variation-*` viajan en la respuesta de la ruta.

## 5. Privacidad de la lista de deseos (V.4.5)

- **Sin cookies.** La lista de invitado vive en `localStorage`, se escribe sólo tras pulsar el botón y no viaja con las peticiones; para pintar la lista, el navegador envía los IDs por `POST` a `woo/cards` y el sitio no los guarda. Ningún ID de producto va en una URL.
- **Texto único.** `Page_Visits::privacy_text()` (Ajustes → Privacidad) tiene un encabezado genérico y lee `digitalisimo_elements_privacy_items`; F4-Woo añade el elemento de `digi_wishlist` (el texto está en `readme.txt`, «Privacidad»).
- **WP Consent API.** Con la 2.1.0, `wp_add_cookie_info()` recibe la clave como almacenamiento local (`$type = 'LOCALSTORAGE'`, noveno argumento, comprobado con `ReflectionFunction`) en la categoría `functional`, como servicio pedido expresamente por el visitante.
- **Con sesión.** La lista se guarda en la cuenta del sitio sólo si el visitante acepta «Añadir» en el aviso; «Descartar» borra la copia local sin enviarla. Exportador y borrador de datos personales propios (sólo con datos propios; con `product_wishlist` activa, los de WooCommerce). Al quitar a un usuario del sitio, su lista de ese sitio se borra.

## 6. Presupuesto medido (`front-budget.sh WOO=1`, V.16.5)

Comparación con 4.3.0.165 (ZIP publicado, extraído en `ELEMENTS_DIR`, corrida en las mismas condiciones): los casos sin `since` (`woo-empty`, `all-own`, `woo-loop-filter-pro`, `woo-pro-existing`) dan el mismo HTML y CSS en normal, `CACHE=1`, `CORE=plugin` y `V4=1`; los tres primeros también los mismos bytes, peticiones, handles y consultas. En el modo normal, las dos mitades de los casos anteriores (41 y 34) coinciden caso a caso. Sólo `woo-pro-existing` cambia en bytes: +168 B en la respuesta de `get_refreshed_fragments` (§7).

| Caso (`since` 4.3.0.166) | CSS propio | JS propio | Peticiones frente a `woo-empty` | CLS relativo | Handles |
|---|---:|---:|---:|---:|---|
| `woo-wishlist-button` | 2 558 B | 8 153 B | +4 | 0 (0,018 antes de reservar el hueco) | `digitalisimo-woo-wishlist` |
| `woo-wishlist` | 2 558 B | 8 153 B | +4 | 0 (0,029 antes) | `digitalisimo-woo-wishlist` |
| `woo-quick-view` | 2 497 B | 8 174 B; configuración de variaciones en línea 921 B (1 034 B antes del recorte) | +4 | 0 | `digitalisimo-woo-quick-view`; al abrir, 37 059 B de respuesta REST con variaciones |
| `woo-quick-view-template` | 2 497 B | 8 174 B | +8 | 0 | ídem; 371 769 B de PRO, el coste declarado del modo plantilla |
| `woo-swatches` | 2 609 B | 5 520 B | +9 | 0 | `digitalisimo-woo-swatches`; PRO por el Loop Grid |
| `woo-swatches-product` | 2 609 B | 5 520 B | +11 | 0,001 (hoja en `<head>`) | `digitalisimo-woo-swatches` |
| `woo-shipping-bar` | 1 417 B | 7 173 B | +4 | 0 | `digitalisimo-woo-cart-extras` |
| `woo-mini-cart-extras` | 1 417 B | 7 173 B | +10 | 0 | `digitalisimo-woo-cart-extras`; PRO por el Menú del carrito |
| `woo-price-filter`, `woo-attribute-filter`, `woo-filters` | 3 940 B | 8 161 B + 5 673 B | +8 | 0 | `digitalisimo-woo-filters`, `digitalisimo-woo-filters-form`; PRO por el Loop Grid |
| `woo-products-parts` | 7 493 B (tres capacidades) | 21 847 B (tres capacidades) | +6 | 0 | `digitalisimo-woo-quick-view`, `-swatches`, `-wishlist` |

Sin tareas largas en ningún caso; las comprobaciones funcionales tras las acciones (producto guardado y en la lista, diálogo con formulario o plantilla, estado `progress` de la barra, el clic en una muestra que filtra el bucle, la muestra que elige su `<option>`) pasan en los cuatro modos. En gzip -9: `wishlist.css` 907 B, `wishlist.js` 3 276 B, `quick-view.css` 1 081 B, `quick-view.js` 3 276 B, `swatches.css` 1 029 B, `swatches.js` 2 356 B, `cart-extras.css` 603 B, `cart-extras.js` 2 659 B, `filters.css` 1 172 B, `filters.js` 3 653 B, `filters-form.js` 2 420 B.

## 7. Desviaciones de la especificación y hallazgos de la validación

| Punto | Lo que decía V | Lo que hay y por qué |
|---|---|---|
| V.8.3 · arranque del coordinador | `filters.js` registra el filtro al evaluarse | `hooks` no existe entonces (§3); el filtro se registra en `elementor-pro/modules/init/before`. Sin esta corrección el coordinador nunca se activaba en un navegador real |
| §5.6 f · un solo `filters.js` ≤ 6,5 KB | Coordinador y los dos widgets en un archivo | Dos archivos: `filters.js` (8 161 B, el coordinador, una vez por página) y `filters-form.js` (5 673 B, los controles de los formularios: dinero, rango, envío sin JS). Cada uno cumple su techo de 8 KB; los 13 834 B por página con filtros son la excepción declarada en `docs/ELEMENTS_FRONT_OBJECTIVE.md` |
| V.16.5 · configuración de variaciones ≤ 1 KB | — | Medida en 1 034 B con un origen de 21 caracteres. Se recortó: `src` relativa a la raíz cuando el archivo está en este sitio y sin la clave `ver` (ya viaja en `src`). Re-medido: 921 B. Los textos de WooCommerce (`i18n_*`) son los que pesan, y dependen del idioma del sitio |
| V.17 · criterio 2 en `woo-pro-existing` | Mismos bytes | +168 B sólo en la respuesta de `?wc-ajax=get_refreshed_fragments`: el sitio de prueba usa la barra en otras páginas y la bandera de fragmentos está encendida. La página no cambia; un sitio sin barra ni cupón no lo paga |
| V.8.5 · aviso del destino «Tienda» | «Este destino no filtra un Loop Grid» | Sin la tabla de atributos, WooCommerce mete el atributo en la consulta principal y un Loop Grid con «Consulta actual» lo hereda (el precio, nunca). El aviso añade que no se cuente con ello |
| V.16.4 · W9 y la tabla de atributos | La tabla se actualiza en diferido | La preparación activa las actualizaciones directas y regenera la tabla; `summary.json` anota `woocommerce_attribute_lookup_enabled`. Los ocho productos del calzado los crea la operación `filters_catalog` al empezar W9 o W10, no la preparación, para que W0.4 siga contando siete |
| Vista rápida y precarga especulativa | — | El enlace `digi-qv__open` lleva `no-prefetch`: las reglas `conservative` de WordPress pedían la ficha entera al pulsar |
| Lista de deseos y CLS | ≤ 0,01 | El botón salía con `hidden` y se mostraba tarde (0,018 y 0,029): se reserva el hueco desde el servidor; re-medido: 0 en los dos casos |
| Playground | WordPress 7.1.3 | Desde el 22 de octubre Playground sirve 7.1.4: las comparaciones usan corridas de 4.3.0.165 hechas en las mismas condiciones, no una línea base guardada. Las fuentes de Google que piden las páginas son las del kit por defecto, no de F4-Woo |
| PRO y `?e-filter-abc1234-x[]=1` | — | `TypeError` del paquete de PRO con un parámetro en forma de arreglo: límite del núcleo, fuera de alcance |
| V.11.3 · parte `woo-variations` | Exporta las variaciones de los padres exportados | **Defecto corregido:** `children()` pedía `get_posts( 'fields' => 'id=>parent' )` y esperaba objetos; WordPress devuelve `ID => padre` y, desde la caché en la misma petición, `'post_parent:<ID>' => padre`, así que no se exportaba ninguna variación y `not_exported.variations` no bajaba. Ahora pide las entradas completas sin precargar metas ni términos; `tests/elements-woo-kit.php` usa el formato real de la caché |
| V.11.5 · R8 conserva la variación editada | Código `variation-edited` en la reversión | No puede ocurrir: el núcleo borra del todo los productos que creó la sesión y WooCommerce borra sus variaciones antes (`WC_Post_Data::delete_post_data()`); R8 lo comprueba y lo anota. En R8 WooCommerce deja además una fila en `wc_category_lookup`, aceptada con su motivo |
| K · exportación por defecto | — | **Defecto de K, previo a F4-Woo:** la App sin «Editar contenido» y WP-CLI sin `--post-types` exportan los tipos propios (productos) pero el runner `taxonomies` del núcleo sólo exporta las taxonomías de los tipos nativos, así que se pierden `product_cat`, `pa_*`, sus metas de término y los términos sin productos, y la plantilla de producto llega en borrador sin condiciones. Con `WOO=1` el arnés exporta como «Editar contenido» (`EXPORT_POST_TYPES=post,product`). Propuesta para K: sustituir por nombre el runner `taxonomies` para que siga a los tipos que exporta el contenido |
| K/PRO · «nuevo» y las páginas de WooCommerce | — | `Settings_Woocommerce::on_save()` escribe en el kit los seis `woocommerce_*_page_id` sin traducir y el runner `woocommerce-settings` sólo traduce las páginas de Elementor: en «nuevo» quedan los IDs de A (`unresolved` en el informe). Propuesta: declararlos como referencias `post` del kit. Además, «nuevo» se rechaza si WooCommerce ya creó sus páginas (`not-empty`); el arnés las borra en B (`woo-empty`). Propuesta: que `site_is_empty()` ignore las páginas que WooCommerce crea al instalarse |
| Inventario de K · `wc-products` de PRO | — | Controles de consulta sin declarar (`posts_posts_ids`, `posts_authors`, `posts_<taxonomía>_ids`), previos a F4-Woo: con `WOO=1` hacen fallar A1 y esos valores no se traducen al importar |
| A13 · ninguna petición REST > 25 s | — | Con WooCommerce en Playground el runner del núcleo `elementor-content` tarda 31–39 s, el final de K 30–41 s y la reversión 44 s; sin WooCommerce el mismo runner también supera los 25 s esta noche, con 4.3.0.165 y con el árbol actual por igual: entorno, no F4-Woo |
| V.7.1 · barra tras conocer el destino | La barra aparece al conocerse el destino | **Defecto corregido (W11):** WooCommerce guarda los fragmentos en `sessionStorage` y sólo los vuelve a pedir si cambia el *hash* del carrito (contenido y total); la calculadora o el pago no cambian el total cuando la ubicación por defecto ya tenía la misma tarifa, así que el mini carrito de las demás páginas seguía sin barra hasta el siguiente cambio del carrito. El módulo del carrito filtra `woocommerce_cart_hash` con el destino (sólo con la bandera de fragmentos activa); `tests/elements-woo-cart.php` lo comprueba |
| `design.sh` DS13 (F7a) · vuelta a 4.3.0.163 | 25/25 en F7a | 24/25: la comprobación «presets: los vinculados vuelven a sus valores locales» no encuentra en la página ni el elemento vinculado ni su gemelo local (`falta el primero falta el segundo`), con y sin WooCommerce. **Previo a F4-Woo:** desde `main` con Elements 4.3.0.165 publicado falla igual, y en ese punto el sitio corre 4.3.0.163, sin código de F4-Woo. Los otros 24 pasos de DS13 y DS1–DS8 pasan. Queda anotado para F7a/K; el sospechoso es el entorno (Playground sirve WordPress 7.1.4 desde el 22 de octubre; F7a lo validó con 7.1.3) |
