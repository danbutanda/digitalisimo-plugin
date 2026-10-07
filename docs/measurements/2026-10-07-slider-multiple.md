# Varias instancias de Slider Optimizado

En el HTML público de la home de `digitalisimo.mx` con Elements 4.3.0.28 hay dos instancias. El script previo quitó la pausa de la primera a los 1131.4 ms y de la segunda a los 1237.5 ms: 106.1 ms de desfase pese a estar ambas próximas al viewport. Cada una esperaba sus propias imágenes y arrancaba en un fotograma diferente.

Con el script nuevo inyectado sólo para la prueba de navegador sobre ese mismo HTML, ambas arrancaron a los 824.6 ms del mismo reloj de página, en el mismo fotograma. Se verificó que la onda tenía 20/20 copias cargadas y que el carrusel de logos había preparado sus imágenes próximas antes del inicio común.

La prueba automatizada de JavaScript crea 30 instancias visibles con decodificación controlada: ninguna comienza antes de la última y las 30 comienzan en el mismo fotograma. Una instancia lejana no descarga ni retrasa a las visibles y se inicia al entrar en vista; otra instancia estática no se modifica. El flujo de movimiento reducido no espera imágenes ni activa animación. La prueba de PHP verifica además que la caché de dimensiones no cruza sitios al cambiar `blog_id`.

La prueba de navegador usó la página pública con el JavaScript local de la corrección; faltará comprobar el WordPress real después de actualizar.
