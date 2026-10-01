# Portada pública · referencia de laboratorio del 2026-09-30

Se midió `https://digitalisimo.mx/` con Lighthouse 13.5.0 y Chrome for Testing 153.0.8010.12 en modo headless. La API autenticada de WordPress reportó DIGITALÍSIMO SEO **1.0.160** activo en la red durante esta observación. Los Releases 1.0.161 y 1.0.162 estaban publicados, pero esta medición **no representa su resultado después de instalarlos**.

| Corrida | Performance | FCP | LCP | TBT | CLS | Speed Index |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Móvil 1 | 57 | 2.1 s | 4.8 s | 690 ms | 0 | 6.5 s |
| Móvil 2 | 61 | 2.1 s | 6.6 s | 390 ms | 0 | 5.8 s |
| Móvil 3 | 89 | 1.7 s | 2.9 s | 260 ms | 0 | 2.9 s |
| Escritorio 1 | 98 | 0.5 s | 0.7 s | 10 ms | 0.0002 | 1.6 s |

Mediana de las tres corridas móviles: Performance **61**, FCP **2.1 s**, LCP **4.8 s**, TBT **390 ms**, CLS **0** y Speed Index **5.8 s**. La dispersión (57–89 puntos) impide concluir que un cambio del plugin causó una mejora o regresión. La comparación [anterior con SEO 1.0.149](2026-09-30-home-mobile-after-149.md) utilizó otra versión del sitio y una sola corrida; tampoco es un experimento controlado.

En la primera corrida móvil Lighthouse registró 114 solicitudes y aproximadamente 826 KiB transferidos, dos fuentes WOFF2 locales (`Source Serif 4` e `Inter`) y ninguna solicitud a `fonts.googleapis.com`. Se solicitaron las cuatro hojas candidatas: `widget-form`, `widget-divider`, `widget-social-icons` y `widget-blockquote`. El diagnóstico de recursos que bloquean el renderizado estimó 500 ms de ahorro para esa corrida; en las dos siguientes estimó 270 y 280 ms. Son estimaciones de Lighthouse, no ahorros comprobados. No se observaron solicitudes de Google Tag en esta captura, lo cual no demuestra que el tracking esté ausente en otras condiciones de consentimiento o navegación.

Una comprobación de lectura con Chromium a 390×844 y 1440×900 encontró H1 correcto, un formulario, diez instancias Swiper inicializadas, un componente Nested Tabs, dos counters, diez carruseles, enlaces de lightbox, seis iconos sociales y un elemento sticky. La segunda pestaña Nested Tabs respondió al clic con `aria-selected="true"`. No hubo errores JavaScript, respuestas HTTP ≥400 ni desbordamiento horizontal en esas dos vistas. No se encontró botón de menú móvil en esta portada; el menú sigue sin validarse. Estas comprobaciones muestran la página **con SEO 1.0.160 y sin activar las optimizaciones nuevas**, no prueban que el CSS diferido o el blindaje de fuentes de 1.0.162 sean seguros.

Comando reproducible (cambiar `--preset=desktop` para escritorio):

```bash
CHROME_PATH=/opt/digitalisimo/playwright-browsers/chromium-1243/chrome-linux64/chrome npx --yes lighthouse https://digitalisimo.mx/ --chrome-flags='--headless --no-sandbox' --output=json --output-path=/tmp/digitalisimo-lighthouse.json --only-categories=performance --quiet
```

Para una comparación antes/después válida todavía hace falta un staging Multisite representativo con Elementor Pro, Site Kit y Redis, activar cada función por separado y repetir las mediciones en las mismas páginas y condiciones. También faltan pruebas de editor, menús, formularios, carruseles, widgets y fuentes en Network del navegador.
