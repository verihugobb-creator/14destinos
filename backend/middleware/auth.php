<?php
declare(strict_types=1);

class Auth
{
    private static ?array $current = null;

    public static function require(): array
    {
        if (self::$current !== null) return self::$current;

        $header = self::getAuthHeader();

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            throw new AuthException('No se encontró el token de autenticación.');
        }

        $payload = jwtDecode($m[1]);
        $user    = DB::fetchOne(
            'SELECT id, email, display_name FROM users WHERE id = ?',
            [$payload['sub']]
        );
        if (!$user) throw new AuthException('Usuario no encontrado.');

        self::$current = $user;
        return $user;
    }

    public static function optional(): ?array
    {
        try { return self::require(); } catch (Throwable) { return null; }
    }

    private static function getAuthHeader(): string
    {
        // Try all possible locations Apache might put the header
        $checks = [
            $_SERVER['HTTP_AUTHORIZATION']          ?? '',
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
            $_SERVER['HTTP_AUTHORIZATION']          ?? '',
        ];

        foreach ($checks as $h) {
            if (!empty($h)) return $h;
        }

        // Apache with CGI/FastCGI sometimes needs getallheaders()
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') return $value;
            }
        }

        // Fallback: query param ?token=xxx or ?_t=xxx
        if (!empty($_GET['token'])) {
            return 'Bearer ' . $_GET['token'];
        }
        if (!empty($_GET['_t'])) {
            return 'Bearer ' . $_GET['_t'];
        }

        return '';
    }
}
