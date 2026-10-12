<?php
/**
 * ORBITDESK - Online Jobs Portal for Virtual Assistants
 * File   : db/config.php
 * Purpose: Single point of database configuration and connection.
 *
 * OOP concepts demonstrated:
 *   - Encapsulation : credentials are private, no outside code can read them
 *   - Singleton     : one PDO connection is reused by every model class
 *   - Static factory: Database::getInstance()
 */

class Database
{
    /* =================================================================
     * DATABASE SETTINGS - this is the only block you may need to edit.
     *
     * MAMP on macOS (default):   $username = 'root';  $password = 'root';  $port = '8889';
     * XAMPP on Windows (default):$username = 'root';  $password = '';      $port = '3306';
     *
     * Not sure about the port? Open the MAMP start page and look under
     * "MySQL" for the port number, then put it below.
     * ================================================================= */
    /*
     * On a web host (Railway, Render, etc.) the database details are supplied
     * as environment variables, so they are read from the environment first.
     * On a local machine (MAMP / XAMPP) no such variables exist, so it falls
     * back to the MAMP defaults below and nothing needs to change locally.
     */
    private $host;
    private $port;
    private $dbName;
    private $username;
    private $password;
    private $charset  = 'utf8mb4';

    private function loadSettings()
    {
        $this->host     = getenv('MYSQLHOST')     ?: getenv('DB_HOST')     ?: 'localhost';
        $this->port     = getenv('MYSQLPORT')     ?: getenv('DB_PORT')     ?: '8889';
        $this->dbName   = getenv('MYSQLDATABASE') ?: getenv('DB_NAME')     ?: 'orbitdesk';
        $this->username = getenv('MYSQLUSER')     ?: getenv('DB_USER')     ?: 'root';
        $pass = getenv('MYSQLPASSWORD');
        if ($pass === false) { $pass = getenv('DB_PASS'); }
        $this->password = ($pass === false) ? 'root' : $pass;
    }

    /** @var Database|null The one and only instance */
    private static $instance = null;

    /** @var PDO The live connection handle */
    private $connection;

    /**
     * Private on purpose. Nobody outside this class may call "new Database()".
     */
    private function __construct()
    {
        $this->loadSettings();

        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbName};charset={$this->charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ];

        try {
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            $this->ensureSchema();
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Database connection failed. Check the username, password, and port '
                           . 'in db/config.php, and make sure MySQL is running. '
                           . 'Details: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * On a fresh web host the database starts empty. If the tables are not
     * there yet, import sql/orbitdesk.sql once, automatically. The CREATE
     * DATABASE and USE lines are stripped because the host already gives us
     * a named database to work inside. Locally this does nothing, because
     * MAMP already has the tables from your manual import.
     */
    private function ensureSchema()
    {
        try {
            $this->connection->query('SELECT 1 FROM tbl_admins LIMIT 1');
            return; // tables already present
        } catch (PDOException $e) {
            // tables missing - fall through and import
        }

        $sqlFile = __DIR__ . '/../sql/orbitdesk.sql';
        if (!is_readable($sqlFile)) { return; }

        $sql = file_get_contents($sqlFile);
        // Remove the three lines that assume a brand new database of their own.
        $sql = preg_replace('/^\s*(DROP\s+DATABASE|CREATE\s+DATABASE|USE)\b.*;$/im', '', $sql);

        try {
            $this->connection->exec($sql); // MySQL runs the batch in one call
        } catch (PDOException $e) {
            // If the driver refuses a multi-statement batch, run them one by one.
            foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
                if ($stmt === '' || strpos($stmt, '--') === 0) { continue; }
                try { $this->connection->exec($stmt); } catch (PDOException $e2) { /* skip */ }
            }
        }
    }

    /**
     * Returns the shared Database object, creating it only on first call.
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /**
     * Hands the PDO handle to the model classes.
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /** Blocks cloning of the singleton. */
    private function __clone() {}
}
