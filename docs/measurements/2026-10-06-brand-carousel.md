# Carrusel de marcas 4.3.0.16

La implementación comparte un controlador de desplazamiento nativo sin UIkit ni Swiper. En el árbol local, `carousel-engine.js` mide 1,138 B gzip; `carousel-engine.css` y `brand-carousel.css` juntos miden 961 B gzip. Elementor solicita los tres recursos sólo al utilizar `digitalisimo-brand-carousel`. Son tamaños estáticos, no tráfico medido en navegador: aún faltan pruebas reales de peticiones, CLS, tiempo de render y equivalencia visual en escritorio, tableta y móvil.
