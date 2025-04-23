<?php
define( 'ROOT_PATH', realpath( dirname( __DIR__ ) ) );
define( 'INCLUDES_PATH', ROOT_PATH . '/includes' );
define( 'PUBLIC_PATH', ROOT_PATH . '/public' );

require ROOT_PATH . '/config/database.php';

require ROOT_PATH . '/classes/Database.php';

