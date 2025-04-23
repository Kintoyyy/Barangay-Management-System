<?php
class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $dsn     = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [ 
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->pdo = new PDO( $dsn, DB_USER, DB_PASS, $options );
        }
        catch ( PDOException $e ) {
            error_log( "Database connection failed: " . $e->getMessage() );
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