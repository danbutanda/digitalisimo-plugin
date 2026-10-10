# Prioridad de la migración de Element Pack

El usuario fijó el siguiente **primer bloque de 63 componentes**, en este orden. Se terminan y validan antes de continuar con los demás componentes del inventario. Los primeros 53 son widgets Elementor; los últimos 10 son funciones o extensiones sin identificador de widget. `scripts/inventory-element-pack.py` comprueba que todos existan en la referencia local y graba `user_priority_tier` y `user_priority_order` en `element-pack-inventory.json`. El orden técnico dentro de un lote puede agrupar widgets que comparten motor, pero ninguno del segundo bloque desplaza a uno de esta lista.

1. Accordion — base propia con plantillas e iconos; grupos únicos por renderizado desde 4.3.0.63, encabezados válidos desde 4.3.0.65 y apertura múltiple inicial, filtrado de plantillas inválidas y prueba de aislamiento por sitio desde 4.3.0.70; renderizado instalado Multisite comprobado, paridad visual pendiente
2. Advanced Button — base propia con enlace, icono, distintivo y efectos CSS; vista previa de atributos mejorada en 4.3.0.63, icono inferior corregido en 4.3.0.64 y borde, ancho, tipografía y sombra configurables desde 4.3.0.71; renderizado instalado Multisite comprobado, paridad visual pendiente
3. Advanced Divider — base propia con variantes nativas e imagen de Medios; círculo responsivo desde 4.3.0.66 y cruz y estrella propias desde 4.3.0.72; renderizado instalado Multisite comprobado, paridad visual restante pendiente
4. Advanced Heading — base propia con tamaño y posición responsivos del texto decorativo desde 4.3.0.67 y tipografía opcional por parte desde 4.3.0.73; renderizado instalado Multisite comprobado, paridad visual pendiente
5. Advanced Icon Box — base propia con icono o imagen, título, descripción, enlaces y distintivo; enlaces y accesibilidad de la vista previa alineados desde 4.3.0.68 y tipografía/sombra optativas desde 4.3.0.74; renderizado Multisite comprobado, skins y efectos pendientes
6. Animated Heading — base propia con rotación progresiva y primer texto visible; listeners globales compartidos desde 4.3.0.69 y enlace externo seguro desde 4.3.0.74; renderizado Multisite comprobado, variantes avanzadas y paridad visual pendientes
7. Brand Grid — base propia de logos y enlaces; desde 4.3.0.74 no fuerza la carga inmediata del primer logo y deja esa decisión a WordPress; paridad visual pendiente
8. Brand Carousel — base propia sobre motor nativo compartido; desde 4.3.0.75 no fuerza `loading` de los logos por índice y su renderizado Multisite está comprobado; paridad visual pendiente
9. Breadcrumbs — base propia con jerarquía de WordPress sin depender de Yoast; desde 4.3.0.76 incluye padres de CPT jerárquicos y está probado en sitio principal y subsitio Multisite; paridad visual pendiente
10. Dual Button — base propia con dos acciones, iconos y separador; editor y enlaces externos alineados desde 4.3.0.77, renderizado Multisite comprobado; paridad visual pendiente
11. Call Out — base propia con título, descripción y botón; editor y enlaces externos alineados desde 4.3.0.78, renderizado Multisite comprobado; paridad visual pendiente
12. Comparison List — base propia en tabla semántica; editor y enlaces externos alineados desde 4.3.0.79, renderizado Multisite comprobado; variantes y paridad visual pendientes
13. Content Switcher — base propia en pestañas accesibles; editor con todos los paneles e iconos desde 4.3.0.83, renderizado Multisite comprobado; contenidos avanzados y paridad visual pendientes
14. Custom Gallery — base propia con selección múltiple y datos individuales; ALT y enlaces del editor alineados desde 4.3.0.84; lightbox y Multisite pendientes
15. Creative Button — base propia con cinco efectos CSS; skins de referencia y Multisite pendientes
16. Device Slider — base propia sobre motor de carrusel compartido; marcos avanzados y Multisite pendientes
17. Fancy Card — base propia con imagen o icono y CTA; skins y Multisite pendientes
18. Fancy List — base propia; paridad pendiente
19. Fancy Icons — base propia con enlaces accesibles y CSS condicional; fondos avanzados, paridad visual y Multisite pendientes
20. Fancy Slider — base propia editorial con motor compartido; transiciones avanzadas, paridad visual y Multisite pendientes
21. Fancy Tabs — base propia accesible con icono/imagen y script compartido; skins, paridad visual y Multisite pendientes
22. Featured Box — base propia con diseños sobre imagen/dividido y contenido semántico; skins, paridad visual y Multisite pendientes
23. Google Reviews — base propia con Places API (New), clave sitio/red y carga diferida; paridad visual y pruebas reales en Multisite pendientes
24. Icon Mobile Menu — base propia con cuatro estilos y tooltips CSS; paridad visual y Multisite pendientes
25. Icon Nav — base propia con enlaces verticales y menú del sitio opcional; skins, paridad visual y Multisite pendientes
26. Lottie Image — para contenido nuevo usar el widget `lottie` ya incluido en Elements; conversión de documentos `bdt-lottie-image` pendiente
27. Logo Grid — base propia; paridad pendiente
28. Navbar — para contenido nuevo usar el widget `nav-menu` ya incluido en Elements; autoocultación, skins y conversión legacy pendientes
29. Notification — base propia con avisos flotantes o fijos y activación configurable; plantillas, skins y Multisite pendientes
30. Offcanvas — para contenido nuevo usar el widget `off-canvas` ya incluido en Elements; conversión de documentos `bdt-offcanvas` pendiente
31. Price List — para contenido nuevo usar el widget `price-list` ya incluido en Elements; distintivos, precio anterior y conversión legacy pendientes
32. Price Table — para contenido nuevo usar el widget `price-table` ya incluido en Elements; nueve layouts, integraciones y conversión legacy pendientes
33. Product Grid — base propia de fichas manuales; skins, paridad visual y Multisite pendientes
34. Post Grid — para contenido nuevo usar `posts` ya incluido; nueve skins y conversión legacy pendientes
35. Post List — `posts` en una columna sirve de base; layouts, términos y conversión legacy pendientes
36. Profile Card — `author-box` cubre autor y perfil básico; tarjeta social, menú y conversión legacy pendientes
37. QR Code — base propia con generación local y fallback legible; etiquetas internas, paridad visual y Multisite pendientes
38. Slider — para diapositivas editoriales usar `slides` ya incluido; Slider Optimizado sigue separado y la conversión legacy pendiente
39. Slinky Vertical Menu — `nav-menu` cubre jerarquía básica; transición deslizante y conversión legacy pendientes
40. Search — `search-form` ya incluido cubre búsquedas nuevas; filtros por tipo y conversión legacy pendientes
41. Single Post — `posts` puede consultar una sola entrada; diseño de metadatos y conversión legacy pendientes
42. Social Share — `share-buttons` ya incluido cubre botones nuevos; contadores y conversión legacy pendientes
43. Sub Menu — `nav-menu` ya incluido cubre submenús WordPress; repetidor estático y conversión legacy pendientes
44. Switcher — Content Switcher propio sirve de base; plantillas y conversión legacy pendientes
45. Tabs — Fancy Tabs propio sirve de base accesible; fuentes, skins y conversión legacy pendientes
46. Table — base propia de CSV estático accesible; fuentes externas, skins y Multisite pendientes
47. Table Of Content — `table-of-contents` ya incluido; paridad de controles y conversión legacy pendientes
48. Tags Cloud — base propia de términos públicos por sitio; paridad visual y Multisite pendientes
49. Total Count — base propia con conteos públicos por sitio, sin animación ni JS; paridad visual y legacy pendientes
50. User Login — `login` ya incluido cubre el formulario básico; skins, sociales y conversión legacy pendientes
51. User Register — base propia con enlace al registro nativo y política sitio/red; formulario embebido, skins y legacy pendientes
52. Vertical Menu — `nav-menu` cubre menú WordPress básico; diseños verticales y conversión legacy pendientes
53. Video Player — base propia con video HTML nativo y carga bajo demanda; skins y legacy pendientes

Funciones y extensiones del mismo bloque prioritario:

54. Backdrop Filter — base CSS opcional con desenfoque, brillo y saturación; efecto líquido y paridad visual pendientes
55. Floating Effects — base propia opcional y condicional; paridad visual y Multisite real pendientes
56. Notation — base CSS para Heading clásico; marcas SVG, repetidores y paridad pendientes
57. Shape Builder — base decorativa para Heading clásico; repetidores, figuras avanzadas y paridad pendientes
58. Text Gradient Background — base CSS optativa para Heading clásico; otros widgets y paridad pendientes
59. Realistic Image Shadow — base CSS optativa para Imagen clásico; hover y paridad pendientes
60. Visibility Controls — Display Conditions incluido en Elements suma desde 4.3.0.81 las condiciones de Element Pack (visitante, URL, contenido, shortcode y WooCommerce) en el mismo motor; renderizado Multisite comprobado; desde 4.3.0.82 los documentos con `ep_display_conditions` siguen funcionando igual sin Element Pack (A/B Multisite idéntico) y son editables; conversión opcional al formato nativo pendiente
61. Wrapper Link — para contenedores nuevos usar el enlace nativo de Elementor; widgets/secciones y conversión legacy pendientes
62. Duplicator — base editorial propia para posts, páginas, CPT públicos y plantillas Elementor; pendiente de prueba real en Multisite
63. SVG Support — usar la carga y el saneamiento SVG nativos de Elementor; habilitación global de la biblioteca no se duplica

Las bases propias señaladas usan IDs `digitalisimo-*`, pero **ninguna se considera migración completa** hasta verificar los controles, casos de error, edición, Multisite y equivalencia funcional/visual. El Slider Optimizado también existe, pero no se declara compatible con `bdt-slider` sin esa comparación. Los IDs antiguos permanecen libres mientras falte paridad. Las extensiones 54–63 se auditan por sus efectos sobre Elementor y WordPress; no se cuentan como widgets ni se cargan globalmente por defecto.

Después de terminar este bloque se retoman los otros 210 widgets del inventario de 263 IDs. La carpeta `bdthemes-element-pack/` sigue siendo únicamente fuente de investigación: no se ejecuta ni entra en los ZIP publicados.
