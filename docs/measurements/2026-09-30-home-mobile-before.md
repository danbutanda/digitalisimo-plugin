# Portada móvil antes de instalar SEO 1.0.148

Medición de laboratorio local con Lighthouse y Chromium headless el 2026-09-30 21:05:49 UTC. URL final `https://digitalisimo.mx/`. El sitio público todavía reportaba DIGITALÍSIMO SEO 1.0.147 mediante la API de plugins de WordPress. [Captura final](2026-09-30-home-mobile-before.jpg).

| Métrica | Resultado |
| --- | ---: |
| Performance | 73 |
| FCP | 1.6 s |
| LCP | 2.1 s |
| TBT | 960 ms |
| CLS | 0.005 |
| Speed Index | 5.1 s |
| Respuesta documento raíz | 1,052 ms |

Comando reproducible:

```bash
CHROME_PATH=/opt/digitalisimo/playwright-browsers/chromium-1243/chrome-linux64/chrome npx --yes lighthouse https://digitalisimo.mx/ --chrome-flags='--headless --no-sandbox --disable-dev-shm-usage' --form-factor=mobile --only-categories=performance --output=json --output-path=/tmp/digitalisimo-baseline-lighthouse.json --quiet
```

Esta corrida difiere de la referencia PageSpeed proporcionada por el propietario (Performance 61, FCP 3.1 s, LCP 7.4 s, TBT 220 ms, CLS 0). La diferencia puede deberse al momento, contenido, caché, red y entorno de laboratorio; se necesita una corrida comparable después de instalar la nueva versión. No se atribuye ninguna mejora al módulo todavía.

Prueba de navegador previa (Chromium headless, 390×844 y 1440×900): el H1 fue «Agencia de Marketing Digital en México para empresas que quieren trascender»; se encontraron nueve contenedores `.swiper` y los nueve tenían instancia activa, una forma, fuentes cargadas, cero errores JavaScript y cero respuestas 404. El ancho del documento coincidió con el viewport en ambos tamaños. La portada actual no expone elementos `.elementor-menu-toggle` ni `.elementor-nav-menu`, así que esta prueba no verifica el menú; requiere otra página o un sitio de pruebas.

En 390×844, el H1 calculado usa `"Source Serif 4", sans-serif`, peso 400, 36 px y altura de línea 40 px. Las reglas de esa familia expuestas por `document.fonts` tienen `display: auto`; la hoja CSS local no declara `font-display`. La opción nueva de swap está apagada por defecto y exige regenerar CSS/caché de fuentes de Elementor al activarla.

Auditoría visual de imágenes en 390×844: se observaron 122 elementos `<img>` en el DOM (incluidas copias de carruseles); 103 no tenían `width`/`height` HTML y 103 no tenían `srcset`. Varios logotipos cargaban archivos de 300×300 px para cajas de 72×72 px, por ejemplo `CANACO-Torreon-300x300.webp`. Esto es un diagnóstico, no una orden de sustituir archivos: hay que comprobar el tamaño adecuado para pantallas de alta densidad y cómo Elementor genera cada etiqueta antes de modificarla.
