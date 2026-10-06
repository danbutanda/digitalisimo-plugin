# Mapa inicial de dependencias

Derivado de análisis estático; los handles de Elementor y las dependencias transitivas deben comprobarse en runtime. Ninguna ruta del plugin de referencia debe ejecutarse desde Elements.

- Clases base de referencia: 6
- Archivos de controles personalizados: 3
- Archivos de skins: 80
- Archivos CSS/JS de módulos asociados: 343

| Familia | Widgets | Dependencias o motores candidatos |
| --- | ---: | --- |
| carousel | 34 | motor compartido de carrusel; Swiper sólo si la funcionalidad lo exige |
| commerce | 10 | consulta/componente WooCommerce por sitio |
| content-and-layout | 115 | HTML semántico + CSS aislado del widget |
| data-visualization | 6 | datos + presentación accesible; librería sólo si es imprescindible |
| forms | 26 | motor de formularios existente + validación y protección del endpoint |
| interaction | 3 | APIs del navegador y JS nativo aislado |
| media | 17 | medios de WordPress + tamaños y carga diferida seguros |
| navigation | 15 | estado accesible + controles nativos de Elementor |
| query | 37 | motor de consultas existente + caché y contexto del sitio |

## Primeras decisiones de dependencia

- `ElementPack\Base\Module_Base` y clases relacionadas: **REIMPLEMENT**, sin herencia del paquete original.
- UIkit global: **REMOVE / REIMPLEMENT** para cada función necesaria.
- Swiper: **KEEP** sólo en widgets que lo requieran y mediante un único motor compartido.
- Administración, sistema de activación y licencia originales: **REMOVE**.
- Integraciones de terceros: registrar únicamente cuando esté presente el plugin correspondiente.

## Control de duplicados

El escaneo encontró 14 coincidencias exactas de nombre (sin prefijo `bdt-`) con widgets ya existentes en Elements. Es una señal para revisar, no prueba equivalencia funcional. Los candidatos figuran en el JSON y no deben registrarse dos veces sin decidir compatibilidad de datos.

## Ejemplo: Testimonial Slider

`bdt-testimonial-slider` → clases base, controles de consulta, skins y Swiper originales. Propuesta: compartir motor de carrusel y consulta de Elements; adaptar controles y skins; validar HTML, accesibilidad y assets en editor/frontend. Todavía no hay implementación ni medición comparativa.
