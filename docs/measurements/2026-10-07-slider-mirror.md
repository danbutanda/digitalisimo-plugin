# Slider Optimizado: onda repetida con espejo

Se comparó el widget migrado con la versión previa de Tools: la alternancia normal/reflejada y el duplicado del bucle eran iguales. En el HTML público de `digitalisimo.mx` con Elements 4.3.0.27 se observaron 20 copias de `fondo-olas-ia.webp`; algunas seguían con `loading="lazy"` durante el recorrido. El modo espejo tampoco anulaba una separación configurada, lo que podía dejar un corte entre los bordes que debían unirse.

La corrección 4.3.0.28 prepara todas las copias de la misma imagen antes de arrancar la onda, aplica separación cero sólo a la combinación **imagen repetida + espejo** y conserva la alternancia en ambas series. Se probaron la salida PHP y el CSS/JS nuevos sobre el HTML público en Chromium a 1440, 768 y 390 px. Con una separación de 37 px forzada en el widget, los tres tamaños calcularon `gap: 0px`, 20/20 imágenes decodificadas, cinco piezas reflejadas por grupo y dos grupos de igual ancho. Los controles y la carga progresiva de los demás modos permanecieron intactos.

La validación visual del WordPress real requiere instalar la nueva Release; la prueba de navegador empleó los archivos locales de la corrección sobre la página pública actual.
