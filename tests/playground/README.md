# A/B de adaptadores de Element Pack

`legacy-ab.sh` comprueba en WordPress Playground (red Multisite con un subsitio) que una página guardada con un widget `bdt-*` se ve igual con Element Pack activo que sólo con DIGITALÍSIMO Elements. Necesita la copia local de referencia en `digitalisimo-elements/bdthemes-element-pack/`, que no se versiona ni se publica.

```bash
bash tests/playground/legacy-ab.sh                  # todos los widgets con casos en tests/legacy/
bash tests/playground/legacy-ab.sh bdt-accordion    # uno o varios, separados por comas
```

Fases por widget, caso y sitio:

- **A**: Element Pack 9.9.1 activo con todos sus módulos encendidos; se guarda la firma de contenido (textos visibles, enlaces e imágenes) y el `_elementor_data` original.
- **B**: Element Pack desactivado en la red; la misma página se muestra con el adaptador. Se compara la firma, se verifica que el editor recibe los ajustes ya traducidos y que el CSS regenerado contiene los fragmentos de `_expect_css`.
- **C**: la herramienta de migración traduce el documento; la firma debe seguir igual.
- **D**: se restaura la copia y el `_elementor_data` debe ser idéntico al original.

Cada `tests/legacy/bdt-*.json` es un objeto `caso → ajustes guardados`. `_expect_css` lista fragmentos de CSS esperados y `_known` documenta diferencias deliberadas por tipo (`text`, `links`, `images`), que se informan pero no fallan. La caché de elementos de Elementor se desactiva en la prueba: Element Pack no marca como dinámicos sus widgets.

Opciones de los casos (`tests/legacy/*.json`):

- `_widget`: tipo de widget del caso cuando el archivo no es `bdt-*` (extensiones `ext-*.json`).
- `_section`: ajustes de la sección que contiene el widget (figuras del Shape Builder en secciones).
- `_expect_html`: fragmentos que deben aparecer en el HTML de la fase B.
- `__TEMPLATE__` y `__POST__` en cualquier valor se sustituyen por el ID de la plantilla de Elementor y de la entrada de prueba que crea `legacy-setup.php` en cada sitio, junto con el menú «Principal».

Con `FAKE_PLUGINS=1`, `legacy-ab.sh` monta los plugins de `tests/playground/fake-plugins/` en las rutas que Element Pack exige a sus widgets de integración (Contact Form 7, Give, Charitable, EDD, WPForms…). Sólo registran el shortcode del plugin e imprimen sus atributos, así que la fase A y la B se comparan por el shortcode exacto que arma cada una. No se publican.
