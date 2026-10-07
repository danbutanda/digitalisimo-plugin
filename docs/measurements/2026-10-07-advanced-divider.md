# Separador avanzado: validación inicial

La versión 4.3.0.31 incorpora `digitalisimo-advanced-divider` como widget independiente. La comparación es funcional frente a la referencia local Element Pack Pro 9.9.1; **no** demuestra paridad visual con sus múltiples SVG ni prueba el editor de un WordPress instalado.

- El render PHP acepta línea, guiones, puntos, doble línea, círculo, onda e imagen de Medios. Los valores inválidos y adjuntos ausentes vuelven a una línea. La imagen se solicita a WordPress por ID, con ALT vacío porque el separador es decorativo; una URL arbitraria del ajuste no se imprime.
- `get_style_depends()` solicita sólo `digitalisimo-advanced-divider`; `get_script_depends()` está vacío. El CSS usa las formas propias y mide menos de 2 KB sin comprimir. No se cargan UIkit, scripts ni SVG de la referencia.
- En Chromium headless, a anchos de ventana 500, 768 y 1440 px, las seis variantes de prueba conservaron el ancho disponible y `scrollWidth` coincidió con el viewport. La ejecución con `--window-size=390` produjo un viewport mínimo efectivo de 500 px en ese binario; falta una prueba móvil con emulación de dispositivo y la comprobación visual en Elementor real.
- Pasaron `tests/elements-advanced-divider.php`, las pruebas del Slider Optimizado, `unzip -t` del paquete Elements, la verificación de exclusión de la referencia y `tests/validate-suite.mjs` con seis módulos. La paridad de controles avanzados, el adaptador `bdt-*` y Multisite continúan pendientes.
