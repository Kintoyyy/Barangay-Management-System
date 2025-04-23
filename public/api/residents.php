<?php
require __DIR__ . '/../../config/bootstrap.php';

header( 'Content-Type: text/html' );

try {
    $db     = Database::getInstance()->getConnection();
    $page   = isset( $_GET[ 'page' ] ) ? (int) $_GET[ 'page' ] : 1;
    $limit  = 10;
    $offset = ( $page - 1 ) * $limit;

    $stmt = $db->prepare( "SELECT * FROM residents LIMIT :limit OFFSET :offset" );
    $stmt->bindValue( ':limit', $limit, PDO::PARAM_INT );
    $stmt->bindValue( ':offset', $offset, PDO::PARAM_INT );
    $stmt->execute();
    $residents = $stmt->fetchAll();

    foreach ( $residents as $resident ) {


        $name = $resident[ 'first_name' ] . ' ' . $resident[ 'last_name' ];

        echo '<tr>';
        echo '<td>' . htmlspecialchars( $resident[ 'resident_id' ] ) . '</td>';
        echo '<td>' . htmlspecialchars( $name ) . ' ' . '</td>';
        echo '<td>' . htmlspecialchars( $resident[ 'birth_date' ] ) . '</td>';
        echo '<td>' . htmlspecialchars( $resident[ 'gender' ] ) . '</td>';
        echo '<td>' . htmlspecialchars( $resident[ 'email' ] ?? '' ) . '</td>';
        echo '<td>' . htmlspecialchars( $resident[ 'contact_number' ] ) . '</td>';
        echo '<td>';
        echo '<button class="btn btn-sm btn-primary"';
        echo ' hx-get="/api/resident_details?id=' . $resident[ 'resident_id' ] . '"';
        echo ' hx-target="#resident-details">';
        echo 'View</button>';
        echo '</td>';
        echo '</tr>';
    }

}
catch ( PDOException $e ) {
    echo '<tr><td colspan="5" class="text-center text-danger">Error loading data</td></tr>';
}