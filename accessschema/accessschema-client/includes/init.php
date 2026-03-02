<?php

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/core/init.php';

$classes = glob( __DIR__ . '/classes/*.php' );
if ( $classes ) {
	foreach ( $classes as $class_file ) {
		require_once $class_file;
	}
}

require_once __DIR__ . '/helper/static-data.php';
require_once __DIR__ . '/helper/init.php';

require_once __DIR__ . '/admin/init.php';

require_once __DIR__ . '/hooks/init.php';

require_once __DIR__ . '/render/init.php';

require_once __DIR__ . '/shortcodes/init.php';

require_once __DIR__ . '/templates/init.php';

require_once __DIR__ . '/languages/init.php';

require_once __DIR__ . '/tools/init.php';
require_once __DIR__ . '/utils/init.php';

do_action( 'asc_client_loaded' );
