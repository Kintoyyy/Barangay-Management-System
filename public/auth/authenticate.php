<?php
error_reporting( E_ALL ); // Report all errors
ini_set( 'display_errors', 1 ); // Display errors in the browser


session_start();

require __DIR__ . '/../../config/bootstrap.php';

$db = Database::getInstance()->getConnection();

if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' ) {
    $username = trim( $_POST[ 'username' ] ?? '' );
    $password = $_POST[ 'password' ] ?? '';

    if ( empty( $username ) || empty( $password ) ) {
        $_SESSION[ 'message' ] = [ 'text' => 'Please enter both username and password.', 'type' => 'warning' ];
        header( 'Location: login.php' );
        exit;
    }

    try {
        print_r( $_POST );
        $stmt = $db->prepare( "SELECT user_id, username, password, official_id, role, is_active FROM users WHERE username = :username LIMIT 1" );
        $stmt->bindParam( ':username', $username );
        $stmt->execute();
        $user = $stmt->fetch( PDO::FETCH_ASSOC );

        if ( $user && $user[ 'is_active' ] && password_verify( $password, $user[ 'password' ] ) ) {
            $_SESSION[ 'user_id' ]     = $user[ 'user_id' ];
            $_SESSION[ 'username' ]    = $user[ 'username' ];
            $_SESSION[ 'role' ]        = $user[ 'role' ];
            $_SESSION[ 'official_id' ] = $user[ 'official_id' ];
            header( 'Location: ../' );
            exit;
        }
        else {
            $_SESSION[ 'message' ] = [ 'text' => 'Invalid username or password.', 'type' => 'danger' ];
            header( 'Location: login.php' );
            exit;
        }

    }
    catch ( PDOException $e ) {
        error_log( "Authentication Error: " . $e->getMessage() );
        $_SESSION[ 'message' ] = [ 'text' => 'An error occurred during login. Please try again.', 'type' => 'danger' ];
        header( 'Location: login.php' );
        exit;
    }

}
else {
    header( 'Location: login.php' );
    exit;
}
