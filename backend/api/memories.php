<?php
declare(strict_types=1);

class MemoryController
{
    public static function index(): never
    {
        $user = Auth::require();
        $rows = DB::fetchAll(
            'SELECT id, country_code, country_name, city, latitude, longitude,
                    visit_date, title, story, photo_path, created_at
             FROM memories
             WHERE user_id = ?
             ORDER BY visit_date DESC
             LIMIT 200',
            [$user['id']]
        );
        foreach ($rows as &$r) {
            $r['photo_url'] = $r['photo_path'] ? UPLOAD_URL . $r['photo_path'] : null;
        }
        jsonResponse($rows);
    }

    public static function byCountry(string $code): never
    {
        $user = Auth::require();
        $rows = DB::fetchAll(
            'SELECT id, country_code, country_name, city, latitude, longitude,
                    visit_date, title, story, photo_path, created_at
             FROM memories
             WHERE user_id = ? AND country_code = ?
             ORDER BY visit_date DESC',
            [$user['id'], $code]
        );
        foreach ($rows as &$r) {
            $r['photo_url'] = $r['photo_path'] ? UPLOAD_URL . $r['photo_path'] : null;
        }
        jsonResponse($rows);
    }

    public static function store(): never
    {
        $user = Auth::require();

        // Support both multipart/form-data and application/json
        $data = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input') ?: '{}', true) ?? []);

        $title   = trim($data['title']        ?? '');
        $city    = trim($data['city']         ?? '');
        $country = strtoupper(trim($data['country_code']  ?? ''));
        $cName   = trim($data['country_name'] ?? $country);
        $date    = trim($data['visit_date']   ?? '');
        $story   = trim($data['story']        ?? '');
        $lat     = isset($data['latitude'])   ? (float)$data['latitude']  : null;
        $lng     = isset($data['longitude'])  ? (float)$data['longitude'] : null;

        $errors = [];
        if (mb_strlen($title) < 2)                     $errors['title']        = 'El título es requerido.';
        if (mb_strlen($city)  < 1)                     $errors['city']         = 'La ciudad es requerida.';
        if (!preg_match('/^[A-Z]{2}$/', $country))     $errors['country_code'] = 'Código de país inválido.';
        if (!DateTime::createFromFormat('Y-m-d', $date)) $errors['visit_date'] = 'Fecha inválida (usa YYYY-MM-DD).';
        if ($errors) throw new ValidationException('Error de validación', $errors);

        // Handle photo upload
        $photoPath = null;
        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            Security::validateImageUpload($_FILES['photo']);

            $ext   = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $fname = $user['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

            if (!is_dir(UPLOAD_DIR)) {
                mkdir(UPLOAD_DIR, 0755, true);
            }

            // Protect upload dir
            $htaccess = UPLOAD_DIR . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Options -ExecCGI\nAddType text/plain .php .php3 .phtml\n");
            }

            if (!move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_DIR . $fname)) {
                throw new RuntimeException('No se pudo guardar la imagen.');
            }
            $photoPath = $fname;
        }

        DB::query(
            'INSERT INTO memories
               (user_id, country_code, country_name, city, latitude, longitude, visit_date, title, story, photo_path)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$user['id'], $country, $cName, $city, $lat, $lng, $date, $title, $story, $photoPath]
        );

        $id  = (int)DB::lastInsertId();
        $row = DB::fetchOne('SELECT * FROM memories WHERE id = ?', [$id]);
        $row['photo_url'] = $row['photo_path'] ? UPLOAD_URL . $row['photo_path'] : null;
        jsonResponse($row, 201);
    }

    public static function destroy(int $id): never
    {
        $user = Auth::require();
        $row  = DB::fetchOne(
            'SELECT photo_path FROM memories WHERE id = ? AND user_id = ?',
            [$id, $user['id']]
        );
        if (!$row) jsonError(404, 'Recuerdo no encontrado.');

        // Delete photo file
        if ($row['photo_path']) {
            $file = UPLOAD_DIR . $row['photo_path'];
            if (file_exists($file)) @unlink($file);
        }

        DB::query('DELETE FROM memories WHERE id = ? AND user_id = ?', [$id, $user['id']]);
        jsonResponse(['deleted' => true]);
    }

    public static function update(int $id): never
    {
        $user = Auth::require();

        // Verify ownership
        $existing = DB::fetchOne(
            'SELECT * FROM memories WHERE id = ? AND user_id = ?',
            [$id, $user['id']]
        );
        if (!$existing) jsonError(404, 'Recuerdo no encontrado.');

        // Parse fields (multipart or JSON)
        $data = !empty($_POST) ? $_POST : (json_decode(file_get_contents('php://input') ?: '{}', true) ?? []);

        $title   = trim($data['title']        ?? $existing['title']);
        $city    = trim($data['city']         ?? $existing['city']);
        $country = strtoupper(trim($data['country_code']  ?? $existing['country_code']));
        $cName   = trim($data['country_name'] ?? $existing['country_name']);
        $date    = trim($data['visit_date']   ?? $existing['visit_date']);
        $story   = trim($data['story']        ?? $existing['story'] ?? '');
        $lat     = isset($data['latitude'])   ? (float)$data['latitude']  : $existing['latitude'];
        $lng     = isset($data['longitude'])  ? (float)$data['longitude'] : $existing['longitude'];

        $errors = [];
        if (mb_strlen($title) < 2)                       $errors['title']      = 'El título es requerido.';
        if (!DateTime::createFromFormat('Y-m-d', $date)) $errors['visit_date'] = 'Fecha inválida.';
        if ($errors) throw new ValidationException('Error de validación', $errors);

        // Handle photo: new upload, keep existing, or remove
        $photoPath = $existing['photo_path'];

        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            // New photo uploaded
            Security::validateImageUpload($_FILES['photo']);
            $ext   = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $fname = $user['id'] . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_DIR . $fname)) {
                throw new RuntimeException('No se pudo guardar la imagen.');
            }
            // Delete old photo
            if ($photoPath && file_exists(UPLOAD_DIR . $photoPath)) @unlink(UPLOAD_DIR . $photoPath);
            $photoPath = $fname;
        } elseif (isset($data['keep_photo']) && $data['keep_photo'] === '1') {
            // Keep existing photo — do nothing
        } elseif (isset($data['remove_photo'])) {
            // Explicitly remove photo
            if ($photoPath && file_exists(UPLOAD_DIR . $photoPath)) @unlink(UPLOAD_DIR . $photoPath);
            $photoPath = null;
        }

        DB::query(
            'UPDATE memories SET title=?, city=?, country_code=?, country_name=?, latitude=?,
             longitude=?, visit_date=?, story=?, photo_path=?, updated_at=NOW()
             WHERE id=? AND user_id=?',
            [$title, $city, $country, $cName, $lat, $lng, $date, $story, $photoPath, $id, $user['id']]
        );

        $row = DB::fetchOne('SELECT * FROM memories WHERE id = ?', [$id]);
        $row['photo_url'] = $row['photo_path'] ? UPLOAD_URL . $row['photo_path'] : null;
        jsonResponse($row);
    }
}