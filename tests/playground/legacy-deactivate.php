<?php
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
deactivate_plugins( 'bdthemes-element-pack/bdthemes-element-pack.php', false, true );
