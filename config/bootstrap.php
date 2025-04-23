<?php
define( 'ROOT_PATH', realpath( dirname( __DIR__ ) ) );
define( 'INCLUDES_PATH', ROOT_PATH . '/includes' );
define( 'TEMPLATE_PATH', ROOT_PATH . '/templates' );
define( 'PUBLIC_PATH', ROOT_PATH . '/public' );

require ROOT_PATH . '/config/database.php';