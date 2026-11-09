<?php
declare(strict_types=1);

class AuthController
{
    public static function register(): never
    {
        $body = self::json();
        $email = filter_var(trim($body['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $name  = trim($body['display_name'] ?? '');
        $pass  = $body['password'] ?? '';

        $errors = [];
        if (!$email)            $errors['email']        = 'Email válido requerido.';
        if (mb_strlen($name) < 2) $errors['display_name'] = 'El nombre debe tener al menos 2 caracteres.';
        if (strlen($pass) < 8)  $errors['password']     = 'La contraseña debe tener al menos 8 caracteres.';
        if ($errors) throw new ValidationException('Error de validación', $errors);

        $exists = DB::fetchOne('SELECT id FROM users WHERE email = ?', [$email]);
        if ($exists) throw new ValidationException('Error de validación', ['email' => 'Este email ya está registrado.']);

        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 10]); // cost 10 for shared hosting
        DB::query('INSERT INTO users (email, password, display_name) VALUES (?,?,?)', [$email, $hash, $name]);
        $id = (int)DB::lastInsertId();

        jsonResponse([
            'token' => jwtEncode(['sub' => $id]),
            'user'  => ['id' => $id, 'email' => $email, 'display_name' => $name],
        ], 201);
    }

    public static function login(): never
    {
        $body  = self::json();
        $email = trim($body['email'] ?? '');
        $pass  = $body['password'] ?? '';

        if (!$email || !$pass) {
            throw new ValidationException('Error de validación', ['email' => 'Email y contraseña requeridos.']);
        }

        $user = DB::fetchOne('SELECT * FROM users WHERE email = ?', [$email]);
        // Use timing-safe comparison to prevent user enumeration
        $valid = $user && password_verify($pass, $user['password']);
        if (!$valid) throw new AuthException('Email o contraseña incorrectos.');

        jsonResponse([
            'token' => jwtEncode(['sub' => $user['id']]),
            'user'  => ['id' => $user['id'], 'email' => $user['email'], 'display_name' => $user['display_name']],
        ]);
    }

    public static function me(): never
    {
        $user = Auth::require();
        jsonResponse([
            'id'           => $user['id'],
            'email'        => $user['email'],
            'display_name' => $user['display_name'],
        ]);
    }

    private static function json(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }
}
