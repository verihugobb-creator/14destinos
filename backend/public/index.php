<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/security.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../api/memories.php';
require_once __DIR__ . '/../api/plans.php';
require_once __DIR__ . '/../api/countries.php';
require_once __DIR__ . '/../api/places.php';

// ── Always send JSON ──────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// ── Security middleware ───────────────────────────────────────────────────────
Security::applyCorsHeaders();
Security::rateLimit();

// ── Route resolution ──────────────────────────────────────────────────────────
// InfinityFree passes the path via PATH_INFO or REQUEST_URI
$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$path   = parse_url($uri, PHP_URL_PATH);
$path   = preg_replace('#^.*?/api(?=/|$)#', '', $path); // quita todo hasta /api (vale en raíz o subcarpeta)
$path   = rtrim($path, '/') ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

try {
    // ── Auth endpoints ────────────────────────────────────────────────────────
    if ($path === '/auth/register' && $method === 'POST') { AuthController::register(); }
    if ($path === '/auth/login'    && $method === 'POST') { AuthController::login(); }
    if ($path === '/auth/me'       && $method === 'GET')  { AuthController::me(); }

    // ── Memories ──────────────────────────────────────────────────────────────
    if ($path === '/memories'      && $method === 'GET')  { MemoryController::index(); }
    if ($path === '/memories'      && $method === 'POST') { MemoryController::store(); }
    if (preg_match('#^/memories/(\d+)$#', $path, $m) && $method === 'DELETE') {
        MemoryController::destroy((int)$m[1]);
    }
    if (preg_match('#^/memories/(\d+)$#', $path, $m) && ($method === 'POST' || $method === 'PUT')) {
        MemoryController::update((int)$m[1]);
    }
    if (preg_match('#^/memories/country/([A-Za-z]{2})$#', $path, $m)) {
        MemoryController::byCountry(strtoupper($m[1]));
    }

    // ── Plans ─────────────────────────────────────────────────────────────────
    if ($path === '/plans' && $method === 'POST') { PlanController::generate(); }
    if ($path === '/plans' && $method === 'GET')  { PlanController::index(); }

    // ── Countries ─────────────────────────────────────────────────────────────
    if ($path === '/countries' && $method === 'GET') { CountryController::list(); }
    if (preg_match('#^/countries/([A-Za-z]{2})/cities$#', $path, $m)) {
        CountryController::cities(strtoupper($m[1]));
    }

    // ── Health check ──────────────────────────────────────────────────────────
    // Además de "pong", comprueba la conexión a MySQL y que existan las tablas
    if ($path === '/ping') {
        $db = ['host' => DB_HOST, 'name' => DB_NAME, 'user' => DB_USER];
        try {
            DB::query('SELECT 1');
            $db['connected'] = true;
            $tables = array_map(fn($row) => array_values($row)[0], DB::fetchAll('SHOW TABLES'));
            $needed = ['users', 'memories', 'plans', 'rate_limits'];
            $db['missing_tables'] = array_values(array_diff($needed, $tables));
        } catch (Throwable $e) {
            $db['connected'] = false;
            $db['error'] = $e->getMessage();
        }
        jsonResponse(['pong' => true, 'time' => date('c'), 'db' => $db]);
    }

    jsonError(404, 'Ruta no encontrada: ' . $path);

} catch (AuthException $e) {
    jsonError(401, $e->getMessage());
} catch (ValidationException $e) {
    jsonError(422, $e->getMessage(), $e->getErrors());
} catch (PDOException $e) {
    error_log('[DB Error] ' . $e->getMessage());
    jsonError(500, 'Error de base de datos. Inténtalo de nuevo.');
} catch (Throwable $e) {
    error_log('[14 Destinos Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonError(500, 'Error interno del servidor.');
}

// ── Global response helpers ───────────────────────────────────────────────────
function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        ['success' => true, 'data' => $data],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    exit;
}

function jsonError(int $status, string $message, array $errors = []): never
{
    http_response_code($status);
    $body = ['success' => false, 'message' => $message];
    if ($errors) $body['errors'] = $errors;
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Custom exceptions ─────────────────────────────────────────────────────────
class AuthException extends RuntimeException {}

class ValidationException extends RuntimeException
{
    public function __construct(string $msg, private array $errs = [])
    {
        parent::__construct($msg);
    }
    public function getErrors(): array { return $this->errs; }
}
