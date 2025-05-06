<?php
define( 'ROOT_PATH', realpath( dirname( __DIR__ ) ) );
define( 'INCLUDES_PATH', ROOT_PATH . '/includes' );
define( 'TEMPLATE_PATH', ROOT_PATH . '/templates' );
define( 'PUBLIC_PATH', ROOT_PATH . '/public' );

require ROOT_PATH . '/config/database.php';
if ( session_status() == PHP_SESSION_NONE ) {
    session_start();
}
function is_logged_in()
{
    return isset( $_SESSION[ 'user_id' ] );
}

function get_current_user_id()
{
    return $_SESSION[ 'user_id' ] ?? null;
}

function get_current_official_id()
{
    return $_SESSION[ 'official_id' ] ?? null;
}

function has_role( $required_role )
{
    return isset( $_SESSION[ 'role' ] ) && $_SESSION[ 'role' ] === $required_role;
}
