<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $host = getenv('SFR_DB_HOST') ?: '127.0.0.1';
    $name = getenv('SFR_DB_NAME') ?: 'southern_flood';
    $user = getenv('SFR_DB_USER') ?: 'root';
    $pass = getenv('SFR_DB_PASSWORD') ?: '';
    $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function json_response(array $body, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_body(): array {
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($data) ? $data : [];
}

function start_sfr_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params(['httponly'=>true, 'secure'=>$https, 'samesite'=>'Lax']);
    session_start();
}

function require_admin(): void {
    start_sfr_session();
    if (empty($_SESSION['sfr_admin'])) json_response(['ok'=>false,'message'=>'กรุณาเข้าสู่ระบบผู้ดูแลก่อน'], 401);
}

function shelter_row(array $row): array {
    $row['id'] = (string)$row['code'];
    unset($row['code']);
    $row['lat'] = (float)$row['latitude'];
    $row['lng'] = (float)$row['longitude'];
    $row['capacity'] = (int)$row['capacity'];
    $row['occupied'] = (int)$row['occupied'];
    $row['services'] = json_decode((string)$row['services'], true) ?: [];
    $row['updated'] = date('H:i', strtotime((string)$row['updated_at'])) . ' น.';
    unset($row['latitude'], $row['longitude'], $row['updated_at'], $row['created_at']);
    return $row;
}
