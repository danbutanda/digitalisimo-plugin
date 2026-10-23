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
