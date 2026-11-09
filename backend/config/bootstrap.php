<?php
declare(strict_types=1);

// ═══════════════════════════════════════════════════════════════════════════
//  14 Destinos — Configuración
//  Credenciales reales del panel de control
// ═══════════════════════════════════════════════════════════════════════════

// ── Base de datos ──────────────────────────────────────────────────────────
// En Docker se usan las variables de entorno de docker-compose.yml.
// En el servidor se usan los valores de después de "?:" → pon aquí los de tu base de datos.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'NOMBRE_BASE_DE_DATOS');
define('DB_USER', getenv('DB_USER') ?: 'USUARIO');
define('DB_PASS', getenv('DB_PASS') ?: 'CONTRASEÑA');

// ── Seguridad ──────────────────────────────────────────────────────────────
// Clave secreta para firmar los tokens de sesión (JWT). No la compartas.
define('JWT_SECRET', 'he6H3-1hx1TLDInFQR-QWLYxl8CYFNIo7AxzocSjHRSAZs_sI8CDEQkYm8MNwNa3');

// ── Uploads ────────────────────────────────────────────────────────────────
// InfinityFree: el directorio raíz del sitio es /htdocs
// Los uploads van en /htdocs/uploads/ (fuera del alcance de PHP directo)
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
// Carpeta base de la web ("" en la raíz, "/14dest" en una subcarpeta), calculada desde backend/public/index.php
define('BASE_PATH', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/', 3)), '/'));
define('UPLOAD_URL', BASE_PATH . '/uploads/');
define('MAX_UPLOAD_MB', 8); // InfinityFree tiene límite bajo

// ── Entorno ────────────────────────────────────────────────────────────────
define('APP_ENV', 'production');
define('ALLOWED_ORIGINS', '*'); // Cambia a tu dominio real cuando lo tengas

// ── OpenAI (opcional — InfinityFree bloquea curl externo en plan gratuito) ─
define('OPENAI_KEY', ''); // Déjalo vacío; usará el plan de fallback

// ── Errores ────────────────────────────────────────────────────────────────
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(0);

// ═══════════════════════════════════════════════════════════════════════════
//  Helpers JWT
// ═══════════════════════════════════════════════════════════════════════════

function jwtEncode(array $payload): string
{
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload['exp'] = $payload['exp'] ?? time() + 86400 * 30; // 30 días
    $body    = base64url_encode(json_encode($payload));
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    return "$header.$body.$sig";
}

function jwtDecode(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) throw new AuthException('Token inválido');
    [$header, $body, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$body", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) throw new AuthException('Firma del token incorrecta');
    $payload = json_decode(base64url_decode($body), true);
    if (!$payload || ($payload['exp'] ?? 0) < time()) throw new AuthException('Token expirado');
    return $payload;
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $pad  = strlen($data) % 4;
    $data = strtr($data, '-_', '+/');
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode($data);
}
