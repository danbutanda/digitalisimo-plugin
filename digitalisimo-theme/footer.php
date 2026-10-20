<?php
defined( 'ABSPATH' ) || exit;
do_action( 'digitalisimo_before_footer' );
do_action( 'digitalisimo_footer' ); // 5: plantilla del constructor; 10: respaldo (retirado si hubo plantilla).
do_action( 'digitalisimo_after_footer' );
wp_footer();
?>
</body>
</html>
