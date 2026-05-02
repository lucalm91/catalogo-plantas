<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function app_root(): string
{
    return dirname(__DIR__);
}

function app_load_env(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $path = $path ?: app_root() . DIRECTORY_SEPARATOR . '.env';
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function app_env(string $key, ?string $default = null): ?string
{
    app_load_env();
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function app_current_user(): ?string
{
    if (!isset($_SESSION['user'])) {
        return null;
    }
    return preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $_SESSION['user']);
}

function app_require_user_json(): string
{
    $user = app_current_user();
    if (!$user) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }
    return $user;
}

function app_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function app_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = app_env('DB_HOST', '127.0.0.1');
    $port = app_env('DB_PORT', '3306');
    $db = app_env('DB_DATABASE');
    $user = app_env('DB_USERNAME');
    $pass = app_env('DB_PASSWORD');
    $charset = app_env('DB_CHARSET', 'utf8mb4');

    if (!$db || !$user || $pass === null || str_starts_with($pass, 'PON_AQUI')) {
        throw new RuntimeException('Configura DB_DATABASE, DB_USERNAME y DB_PASSWORD en .env');
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function app_install_schema(): void
{
    $schemaFile = app_root() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        throw new RuntimeException('No se pudo leer database/schema.sql');
    }

    $statements = array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: []));
    foreach ($statements as $statement) {
        if ($statement !== '') {
            app_db()->exec($statement);
        }
    }
}

function app_normalize_plant(array $row, array $images = []): array
{
    return [
        'num' => (int) $row['legacy_num'],
        'identificacion' => (string) ($row['identificacion'] ?? ''),
        'zona' => (string) ($row['zona'] ?? ''),
        'estado' => (string) ($row['estado'] ?? ''),
        'descripcion' => (string) ($row['descripcion'] ?? ''),
        'riego' => (string) ($row['riego'] ?? ''),
        'sistema_riego' => (string) ($row['sistema_riego'] ?? ''),
        'imagenes' => array_values($images),
        'orden' => (int) ($row['orden'] ?? 0),
        'orden_zona' => (int) ($row['orden_zona'] ?? 9999),
    ];
}

function app_fetch_plants(string $owner): array
{
    $stmt = app_db()->prepare(
        'SELECT * FROM plants WHERE owner = ? ORDER BY orden_zona ASC, zona ASC, orden ASC, legacy_num ASC'
    );
    $stmt->execute([$owner]);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return [];
    }

    $plantIds = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($plantIds), '?'));
    $imgStmt = app_db()->prepare(
        "SELECT plant_id, image_path FROM plant_images WHERE plant_id IN ($placeholders) ORDER BY sort_order ASC, id ASC"
    );
    $imgStmt->execute($plantIds);
    $imagesByPlant = [];
    foreach ($imgStmt->fetchAll() as $imageRow) {
        $imagesByPlant[(int) $imageRow['plant_id']][] = $imageRow['image_path'];
    }

    return array_map(function (array $row) use ($imagesByPlant): array {
        return app_normalize_plant($row, $imagesByPlant[(int) $row['id']] ?? []);
    }, $rows);
}

function app_fetch_plant(string $owner, int $plantNum): ?array
{
    $stmt = app_db()->prepare('SELECT * FROM plants WHERE owner = ? AND legacy_num = ?');
    $stmt->execute([$owner, $plantNum]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $imgStmt = app_db()->prepare('SELECT image_path FROM plant_images WHERE plant_id = ? ORDER BY sort_order ASC, id ASC');
    $imgStmt->execute([(int) $row['id']]);
    return app_normalize_plant($row, array_column($imgStmt->fetchAll(), 'image_path'));
}

function app_fetch_plant_row(string $owner, int $plantNum): ?array
{
    $stmt = app_db()->prepare('SELECT * FROM plants WHERE owner = ? AND legacy_num = ?');
    $stmt->execute([$owner, $plantNum]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function app_next_plant_num(string $owner): int
{
    $stmt = app_db()->prepare('SELECT COALESCE(MAX(legacy_num), 0) + 1 FROM plants WHERE owner = ?');
    $stmt->execute([$owner]);
    return (int) $stmt->fetchColumn();
}

function app_next_order_for_zone(string $owner, string $zone): int
{
    $stmt = app_db()->prepare('SELECT COALESCE(MAX(orden), -1) + 1 FROM plants WHERE owner = ? AND zona = ?');
    $stmt->execute([$owner, $zone]);
    return (int) $stmt->fetchColumn();
}

function app_next_zone_order(string $owner): int
{
    $stmt = app_db()->prepare('SELECT COUNT(DISTINCT zona) FROM plants WHERE owner = ?');
    $stmt->execute([$owner]);
    return (int) $stmt->fetchColumn();
}

function app_create_plant(string $owner, array $data): array
{
    $plantNum = isset($data['num']) ? (int) $data['num'] : app_next_plant_num($owner);
    $zone = trim((string) ($data['zona'] ?? ''));
    $plant = [
        'legacy_num' => $plantNum,
        'identificacion' => trim((string) ($data['identificacion'] ?? '')),
        'zona' => $zone,
        'estado' => trim((string) ($data['estado'] ?? '')),
        'descripcion' => trim((string) ($data['descripcion'] ?? '')),
        'riego' => trim((string) ($data['riego'] ?? '')),
        'sistema_riego' => trim((string) ($data['sistema_riego'] ?? '')),
        'orden' => isset($data['orden']) ? (int) $data['orden'] : app_next_order_for_zone($owner, $zone),
        'orden_zona' => isset($data['orden_zona']) ? (int) $data['orden_zona'] : app_next_zone_order($owner),
    ];

    $stmt = app_db()->prepare(
        'INSERT INTO plants (owner, legacy_num, identificacion, zona, estado, descripcion, riego, sistema_riego, orden, orden_zona)
         VALUES (:owner, :legacy_num, :identificacion, :zona, :estado, :descripcion, :riego, :sistema_riego, :orden, :orden_zona)'
    );
    $stmt->execute(['owner' => $owner] + $plant);
    $plantId = (int) app_db()->lastInsertId();

    $images = array_values(array_filter((array) ($data['imagenes'] ?? []), 'strlen'));
    foreach ($images as $i => $imagePath) {
        app_add_plant_image_by_id($plantId, (string) $imagePath, $i, false);
    }

    return app_fetch_plant($owner, $plantNum) ?: app_normalize_plant($plant, $images);
}

function app_update_plant_field(string $owner, int $plantNum, string $field, string $value): bool
{
    $allowed = ['identificacion', 'estado', 'descripcion', 'zona', 'riego', 'sistema_riego'];
    if (!in_array($field, $allowed, true)) {
        throw new InvalidArgumentException('Campo no permitido');
    }
    $stmt = app_db()->prepare("UPDATE plants SET {$field} = ? WHERE owner = ? AND legacy_num = ?");
    $stmt->execute([$value, $owner, $plantNum]);
    return $stmt->rowCount() > 0;
}

function app_delete_plant(string $owner, int $plantNum): bool
{
    $stmt = app_db()->prepare('DELETE FROM plants WHERE owner = ? AND legacy_num = ?');
    $stmt->execute([$owner, $plantNum]);
    return $stmt->rowCount() > 0;
}

function app_zone_exists(string $owner, string $zone): bool
{
    $stmt = app_db()->prepare('SELECT 1 FROM plants WHERE owner = ? AND zona = ? LIMIT 1');
    $stmt->execute([$owner, $zone]);
    return (bool) $stmt->fetchColumn();
}

function app_rename_zone(string $owner, string $oldZone, string $newZone): bool
{
    $stmt = app_db()->prepare('UPDATE plants SET zona = ? WHERE owner = ? AND zona = ?');
    $stmt->execute([$newZone, $owner, $oldZone]);
    return $stmt->rowCount() > 0;
}

function app_delete_zone(string $owner, string $zone): bool
{
    $stmt = app_db()->prepare('DELETE FROM plants WHERE owner = ? AND zona = ?');
    $stmt->execute([$owner, $zone]);
    return $stmt->rowCount() > 0;
}

function app_update_order(string $owner, array $orderData): void
{
    $stmt = app_db()->prepare('UPDATE plants SET orden = ? WHERE owner = ? AND legacy_num = ?');
    foreach ($orderData as $item) {
        if (!isset($item['plant_num'], $item['orden'])) {
            continue;
        }
        $stmt->execute([(int) $item['orden'], $owner, (int) $item['plant_num']]);
    }
}

function app_update_zone_order(string $owner, array $orderedZones): void
{
    $stmt = app_db()->prepare('UPDATE plants SET orden_zona = ? WHERE owner = ? AND zona = ?');
    foreach ($orderedZones as $i => $zone) {
        $stmt->execute([(int) $i, $owner, (string) $zone]);
    }
}

function app_add_plant_image_by_id(int $plantId, string $imagePath, int $sortOrder = 0, bool $shiftExisting = true): void
{
    if ($shiftExisting) {
        $shift = app_db()->prepare('UPDATE plant_images SET sort_order = sort_order + 1 WHERE plant_id = ?');
        $shift->execute([$plantId]);
    }
    $stmt = app_db()->prepare(
        'INSERT INTO plant_images (plant_id, image_path, sort_order)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)'
    );
    $stmt->execute([$plantId, $imagePath, $sortOrder]);
}

function app_add_plant_image(string $owner, int $plantNum, string $imagePath): bool
{
    $row = app_fetch_plant_row($owner, $plantNum);
    if (!$row) {
        return false;
    }
    app_add_plant_image_by_id((int) $row['id'], $imagePath, 0, true);
    return true;
}

function app_remove_plant_image(string $owner, int $plantNum, string $imagePath): bool
{
    $row = app_fetch_plant_row($owner, $plantNum);
    if (!$row) {
        return false;
    }
    $stmt = app_db()->prepare('DELETE FROM plant_images WHERE plant_id = ? AND image_path = ?');
    $stmt->execute([(int) $row['id'], $imagePath]);
    return $stmt->rowCount() > 0;
}

function app_fetch_history(string $owner, int $plantNum): array
{
    $stmt = app_db()->prepare(
        'SELECT * FROM plant_history WHERE owner = ? AND plant_num = ? ORDER BY fecha DESC, id DESC'
    );
    $stmt->execute([$owner, $plantNum]);
    return $stmt->fetchAll();
}

function app_add_history(
    string $owner,
    int $plantNum,
    string $usuario,
    string $accion,
    string $detalles = '',
    ?string $oldValue = null,
    ?string $newValue = null
): void {
    $stmt = app_db()->prepare(
        'INSERT INTO plant_history (owner, plant_num, fecha, usuario, accion, detalles, old_value, new_value)
         VALUES (?, ?, NOW(), ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$owner, $plantNum, $usuario, $accion, $detalles, $oldValue, $newValue]);
}

function app_delete_history_entry(string $owner, int $plantNum, string $fecha): bool
{
    $rawDate = DateTime::createFromFormat('d/m/Y H:i', $fecha);
    $dateCandidates = [$fecha];
    if ($rawDate) {
        $dateCandidates[] = $rawDate->format('Y-m-d H:i:s');
    }

    foreach ($dateCandidates as $candidate) {
        $stmt = app_db()->prepare(
            'DELETE FROM plant_history WHERE owner = ? AND plant_num = ? AND fecha = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$owner, $plantNum, $candidate]);
        if ($stmt->rowCount() > 0) {
            return true;
        }
    }
    return false;
}

function app_format_action_name(string $action): string
{
    $actionMap = [
        'identificacion' => 'Cambio de nombre',
        'descripcion' => 'Descripción',
        'estado' => 'Estado',
        'riego' => 'Riego',
        'sistema_riego' => 'Sistema de riego',
        'subida_imagen' => 'Nueva imagen',
        'eliminacion_imagen' => 'Imagen eliminada',
        'creacion_planta' => 'Nueva planta',
    ];
    return $actionMap[$action] ?? ucfirst($action);
}

function app_summary(string $txt): string
{
    $txt = trim(str_replace(["\n", "\r"], ' ', $txt));
    return mb_strlen($txt) > 60 ? mb_substr($txt, 0, 57) . '...' : $txt;
}
