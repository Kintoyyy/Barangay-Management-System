<?php


require __DIR__ . '/../../config/bootstrap.php';

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare( "SELECT * FROM residents WHERE purok_id = :id" );
    $stmt->execute( [ ':id' => 0 ] );
    $residents = $stmt->fetchAll();

    echo "<pre>";
    print_r( $residents );
    echo "</pre>";
}
catch ( PDOException $e ) {
    error_log( "Database error: " . $e->getMessage() );
    print_r( $e->getMessage() );
    echo "Error loading residents";
}