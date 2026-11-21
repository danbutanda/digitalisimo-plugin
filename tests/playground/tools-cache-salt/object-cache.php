<?php
/**
 * Drop-in falso para la prueba: la caché de objetos del núcleo (en memoria) presentada como externa, como un
 * Redis/Memcached sin sal. Sólo hace que wp_using_ext_object_cache() dé true; no comparte nada.
 */
require_once ABSPATH . WPINC . '/class-wp-object-cache.php';
require_once ABSPATH . WPINC . '/cache.php';
