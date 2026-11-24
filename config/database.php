<?php
declare(strict_types=1);

/**
 * Conexión PDO singleton a MariaDB.
 * Config vía variables de entorno (fallback a valores locales).
 */

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = getenv('DB_HOST') ?: '127.0.0.1';
    $port    = getenv('DB_PORT') ?: '3308';
    $name    = getenv('DB_NAME') ?: 'pix';
    $user    = getenv('DB_USER') ?: 'root';
    $pass    = getenv('DB_PASS') ?: '203011';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        // En producción: loguear, no exponer.
        error_log('[DB] ' . $e->getMessage());
        http_response_code(500);
        exit('Error de conexión a la base de datos.');
    }

    return $pdo;
}