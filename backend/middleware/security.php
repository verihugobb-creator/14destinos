<?php
declare(strict_types=1);

class Security
{
    public static function applyCorsHeaders(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowed = ALLOWED_ORIGINS;

        if ($allowed === '*' || $origin === $allowed || in_array($origin, explode(',', $allowed), true)) {
            header("Access-Control-Allow-Origin: " . ($allowed === '*' ? '*' : $origin));
        }
        header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    /**
     * Rate limiting using MySQL only (no Redis on InfinityFree free plan)
     */
    public static function rateLimit(int $maxPerMinute = 30): void
    {
        $ip   = self::clientIp();
        $hash = hash('sha256', $ip . ($_SERVER['REQUEST_URI'] ?? ''));

        try {
            // Clean old entries occasionally (1 in 20 chance to avoid overhead)
            if (rand(1, 20) === 1) {
                DB::query("DELETE FROM rate_limits WHERE window_at < NOW() - INTERVAL 2 MINUTE");
            }

            DB::query(
                'INSERT INTO rate_limits (key_hash, hits, window_at) VALUES (?, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                   hits      = IF(window_at < NOW() - INTERVAL 1 MINUTE, 1, hits + 1),
                   window_at = IF(window_at < NOW() - INTERVAL 1 MINUTE, NOW(), window_at)',
                [$hash]
            );

            $row = DB::fetchOne('SELECT hits FROM rate_limits WHERE key_hash = ?', [$hash]);
            if ($row && (int)$row['hits'] > $maxPerMinute) {
                header('Retry-After: 60');
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Demasiadas peticiones. Espera un momento.']);
                exit;
            }
        } catch (Throwable) {
            // If rate limit table doesn't exist yet, allow the request
        }
    }

    public static function clientIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                // Take first IP if comma-separated
                return trim(explode(',', $_SERVER[$k])[0]);
            }
        }
        return '0.0.0.0';
    }

    public static function validateImageUpload(array $file): void
    {
        $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxBytes = MAX_UPLOAD_MB * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msgs = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite del servidor.',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario.',
                UPLOAD_ERR_PARTIAL    => 'El archivo se subió parcialmente.',
                UPLOAD_ERR_NO_TMP_DIR => 'Error interno: directorio temporal no encontrado.',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo.',
            ];
            throw new ValidationException($msgs[$file['error']] ?? 'Error al subir el archivo.');
        }

        if ($file['size'] > $maxBytes) {
            throw new ValidationException('Archivo demasiado grande. Máximo ' . MAX_UPLOAD_MB . ' MB.');
        }

        // Verify by magic bytes — don't trust the extension
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            throw new ValidationException('Tipo de archivo no permitido. Solo JPG, PNG, GIF o WebP.');
        }
    }
}
