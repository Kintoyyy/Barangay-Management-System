<?php
define( 'DB_PATH', 'db.sqlite' );
define( 'DB_HOST', 'localhost' );
define( 'DB_NAME', 'brgy_mgmt_db' );
define( 'DB_USER', 'root' );
define( 'DB_PASS', '' );
define( 'DB_CHARSET', 'utf8mb4' );


class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $options = [ 
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // $dsn     = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            // $this->pdo = new PDO( $dsn, DB_USER, DB_PASS, $options );

            $dsn       = "sqlite:" . __DIR__ . '/../db/db.sqlite';
            $this->pdo = new PDO( $dsn, null, null, $options );
        }
        catch ( PDOException $e ) {
            error_log( "Database connection failed: " . $e->getMessage() );
            print_r( $e->getMessage() );
            throw new Exception( "Database connection error" );
        }
    }

    public static function getInstance()
    {
        if ( !self::$instance ) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    private function __clone()
    {
    }
    public function __wakeup()
    {
        throw new Exception( "Cannot unserialize singleton" );
    }
}