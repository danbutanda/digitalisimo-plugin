# Prioridad de la migración de Element Pack

El usuario fijó el siguiente **primer bloque de 63 componentes**, en este orden. Se terminan y validan antes de continuar con los demás componentes del inventario. Los primeros 53 son widgets Elementor; los últimos 10 son funciones o extensiones sin identificador de widget. `scripts/inventory-element-pack.py` comprueba que todos existan en la referencia local y graba `user_priority_tier` y `user_priority_order` en `element-pack-inventory.json`. El orden técnico dentro de un lote puede agrupar widgets que comparten motor, pero ninguno del segundo bloque desplaza a uno de esta lista.

1. Accordion — base propia con plantillas e iconos; paridad visual y Multisite pendientes
2. Advanced Button — base propia con enlace, icono, distintivo y efectos CSS; paridad visual y Multisite pendientes
3. Advanced Divider — base propia con variantes nativas e imagen de Medios; paridad visual y Multisite pendientes
4. Advanced Heading — base propia; paridad pendiente
5. Advanced Icon Box — base propia con icono o imagen, título, descripción, enlaces y distintivo; paridad visual y Multisite pendientes
6. Animated Heading — base propia con rotación progresiva y primer texto visible; variantes avanzadas, paridad visual y Multisite pendientes
7. Brand Grid — base propia; paridad pendiente
8. Brand Carousel — base propia; paridad pendiente
9. Breadcrumbs — base propia con jerarquía de WordPress sin depender de Yoast; paridad visual y Multisite pendientes
10. Dual Button — base propia con dos acciones, iconos y separador; paridad visual y Multisite pendientes
11. Call Out — base propia con título, descripción y botón; paridad visual y Multisite pendientes
12. Comparison List — base propia en tabla semántica; variantes y Multisite pendientes
13. Content Switcher — base propia en pestañas accesibles; contenidos avanzados y Multisite pendientes
14. Custom Gallery — base propia con selección múltiple y datos individuales; lightbox y Multisite pendientes
15. Creative Button — base propia con cinco efectos CSS; skins de referencia y Multisite pendientes
16. Device Slider — base propia sobre motor de carrusel compartido; marcos avanzados y Multisite pendientes
17. Fancy Card — base propia con imagen o icono y CTA; skins y Multisite pendientes
18. Fancy List — base propia; paridad pendiente
19. Fancy Icons — base propia con enlaces accesibles y CSS condicional; fondos avanzados, paridad visual y Multisite pendientes
20. Fancy Slider — base propia editorial con motor compartido; transiciones avanzadas, paridad visual y Multisite pendientes
21. Fancy Tabs — base propia accesible con icono/imagen y script compartido; skins, paridad visual y Multisite pendientes
22. Featured Box
23. Google Reviews
24. Icon Mobile Menu
25. Icon Nav
26. Lottie Image
27. Logo Grid — base propia; paridad pendiente
28. Navbar
29. Notification
30. Offcanvas
31. Price List
32. Price Table
33. Product Grid
34. Post Grid
35. Post List
36. Profile Card
37. QR Code
38. Slider — aprovechar el Slider Optimizado existente tras comparar controles y salida
39. Slinky Vertical Menu
40. Search
41. Single Post
42. Social Share
43. Sub Menu
44. Switcher
45. Tabs
46. Table
47. Table Of Content
48. Tags Cloud
49. Total Count
50. User Login
51. User Register
52. Vertical Menu
53. Video Player

Funciones y extensiones del mismo bloque prioritario:

54. Backdrop Filter
55. Floating Effects
56. Notation
57. Shape Builder
58. Text Gradient Background
59. Realistic Image Shadow
60. Visibility Controls
61. Wrapper Link
62. Duplicator — base editorial propia para posts, páginas, CPT públicos y plantillas Elementor; pendiente de prueba real en Multisite
63. SVG Support

Las bases propias señaladas usan IDs `digitalisimo-*`, pero **ninguna se considera migración completa** hasta verificar los controles, casos de error, edición, Multisite y equivalencia funcional/visual. El Slider Optimizado también existe, pero no se declara compatible con `bdt-slider` sin esa comparación. Los IDs antiguos permanecen libres mientras falte paridad. Las extensiones 54–63 se auditan por sus efectos sobre Elementor y WordPress; no se cuentan como widgets ni se cargan globalmente por defecto.

Después de terminar este bloque se retoman los otros 210 widgets del inventario de 263 IDs. La carpeta `bdthemes-element-pack/` sigue siendo únicamente fuente de investigación: no se ejecuta ni entra en los ZIP publicados.
