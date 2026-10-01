<?php

/*
 * Load Composer
 */

require_once __DIR__ . "/vendor/autoload.php";


/*
 * Load .env
 */

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);

$dotenv->safeLoad();


/*
 * Database configuration
 */

$host = $_ENV["DB_HOST"] ?? "127.0.0.1";

$port = $_ENV["DB_PORT"] ?? "3306";

$dbname = $_ENV["DB_NAME"] ?? "myschool";

$username = $_ENV["DB_USER"] ?? "root";

$password = $_ENV["DB_PASSWORD"] ?? "";


/*
 * Create PDO connection
 */

try {

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

    $conn = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    die(
        "Database connection failed: "
        . htmlspecialchars($e->getMessage())
    );
}