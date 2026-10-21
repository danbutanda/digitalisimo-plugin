# Informe: Template Kits de Envato en la etapa K

> Estado del 20 de octubre de 2026: **sintético**. K convierte los Template Kits de Envato a un kit 3.0 y los importa siempre en «sólo agregar» ([k-kits.md](k-kits.md), §9), pero hasta hoy sólo se probó con el fixture sintético `tests/sources/kits/spec/tercero-envato.json`. El riesgo queda abierto mientras no haya kits reales. Este informe dice cómo cerrarlo y guarda el resumen anonimizado cuando exista.

## 1. Por qué hacen falta kits reales

El adaptador del núcleo lee el tipo de plantilla y las condiciones del Theme Builder (`template_type` y `elementor_pro_conditions`) en sitios distintos: unas veces del manifiesto y otras del archivo de cada plantilla (`digitalisimo-elements/elementor/app/modules/import-export-customization/compatibility/envato.php:25,71-75`). K lee los dos, pero un fixture escrito desde la misma lectura del código es circular: no demuestra qué traen los kits que se venden. Quedan **a confirmar** con kits reales:

- cuál de las dos lecturas manda cuando no coinciden;
- si `type` es de verdad el tipo de documento: hoy manda `type`, como en el adaptador del núcleo (`compatibility/envato.php:43`), y el editorial `template_type` sólo decide si falta `type`; un `template_type` que el sitio no conoce se conserva como tipo original (`_digitalisimo_original_type`) y la plantilla no se publica desde «Kits» (`Envato_Kit::document_type()`, integración de K, 2026-10-20);
- si `wp_page_template` debe pasar a `settings.template` del documento;
- qué otras claves del formato llegan en `metadata` y deben quitarse para que no acaben como metas;
- qué plugins piden (`required_plugins`) y cómo se ven en el informe (los de la familia del constructor nunca se nombran: «funciones incluidas en DIGITALÍSIMO Elements»);
- cómo se comporta un ZIP de marketplace real: carpeta de documentación, ZIP interior y capturas.

## 2. Procedimiento (obligatorio si el propietario aporta 3 kits o más)

1. **Los kits nunca entran en Git** (el repositorio es público). Se guardan fuera del repositorio y no se suben a ningún servicio.
2. Con cada kit, en un Playground de pruebas (nunca en un sitio real):
   - `wp digitalisimo-elements kit inspect <kit.zip> --format=json`: guardián, normalizador y análisis, sin escribir fuera de los temporales;
   - `wp digitalisimo-elements kit import <kit.zip>` en «agregar» sobre un sitio vacío, y «Aplicar los ajustes del kit»;
   - revisar el informe: `failed` vacío en cada runner, sin entradas `error`, condiciones del Theme Builder como plantillas en borrador, colores y tipografías en el kit activo tras aplicar, y 0 peticiones al proveedor.
3. Anotar aquí el **resumen anonimizado** (§3): estructura y claves, nunca nombres de autor, de producto, URL, textos ni imágenes del kit.
4. Derivar de ese resumen el fixture sintético `tests/sources/kits/spec/tercero-envato.json`, regenerar con `node tests/sources/kits/build.mjs` y actualizar `fixtures.lock.json`.
5. Si una lectura «a confirmar» cambia el conversor (`digitalisimo-elements/modules/digitalisimo-import/sources/class-envato-kit.php`), se publica como una versión de Elements, y la fila de Envato de la matriz de `FUSION_OBJECTIVE.md` pasa de «sintético» a «real».

## 3. Resumen anonimizado de los kits reales

Sin kits todavía. Plantilla para cada uno:

| Kit | Versión del formato | Plantillas por tipo | Dónde están las condiciones | Claves de `metadata` | Plugins pedidos (por familia) | Estructura del ZIP | Resultado de `inspect` e importación |
|---|---|---|---|---|---|---|---|
| A | — | — | — | — | — | — | — |

## 4. Decisiones posteriores

Ninguna todavía.
