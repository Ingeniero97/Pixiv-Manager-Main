<?php
declare(strict_types=1);

/**
 * Helpers comunes para endpoints API.
 * - Respuestas JSON estandarizadas
 * - Lectura segura de body JSON
 * - Validación de método HTTP
 * - Manejo global de excepciones (evita filtrar stack traces)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// ---------- Manejo global de excepciones no capturadas ----------
set_exception_handler(function (Throwable $e): void {
    error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Error interno del servidor',
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
});

// ---------- Respuestas ----------

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $message, int $status = 400): void
{
    json_response(['success' => false, 'message' => $message], $status);
}

// ---------- Validación de entrada ----------

function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        json_error('Método no permitido', 405);
    }
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Cuerpo JSON inválido', 400);
    }
    return $data;
}

/**
 * Normaliza un array de IDs (strings o ints) eliminando vacíos.
 * Mantiene el orden original y no hace dedupe (el UPDATE ya lo hace).
 */
function parse_ids_array($ids): array
{
    if (!is_array($ids)) {
        return [];
    }
    $out = [];
    foreach ($ids as $id) {
        if ($id === null) continue;
        $id = is_string($id) ? trim($id) : $id;
        if ($id === '' || $id === 0 || $id === '0') continue;
        $out[] = $id;
    }
    return $out;
}