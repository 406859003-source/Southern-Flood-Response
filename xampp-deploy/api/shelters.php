<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

try {
    $pdo = db();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $where = [];
        $params = [];
        if (!empty($_GET['province']) && $_GET['province'] !== 'ทุกจังหวัด') {
            $where[] = 'province = :province';
            $params['province'] = trim((string)$_GET['province']);
        }
        if (!empty($_GET['open_only'])) $where[] = "status = 'เปิดรับ'";
        if (!empty($_GET['q'])) {
            $where[] = '(name LIKE :query OR province LIKE :query OR district LIKE :query OR address LIKE :query)';
            $params['query'] = '%' . trim((string)$_GET['q']) . '%';
        }
        $sql = 'SELECT * FROM shelters' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY province, name';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(['ok'=>true,'shelters'=>array_map('shelter_row', $stmt->fetchAll())]);
    }

    require_admin();
    if ($method === 'POST') {
        $data = request_body();
        $name = trim((string)($data['name'] ?? ''));
        $province = trim((string)($data['province'] ?? ''));
        if ($name === '' || $province === '') json_response(['ok'=>false,'message'=>'กรุณากรอกชื่อศูนย์และจังหวัด'], 422);
        validate_shelter_coordinates($data);
        $code = 'SH-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare('INSERT INTO shelters (code,name,province,district,address,latitude,longitude,capacity,occupied,status,phone,services) VALUES (:code,:name,:province,:district,:address,:lat,:lng,:capacity,:occupied,:status,:phone,:services)');
        $stmt->execute(shelter_values($data) + ['code'=>$code]);
        $stmt = $pdo->prepare('SELECT * FROM shelters WHERE code = ?');
        $stmt->execute([$code]);
        json_response(['ok'=>true,'shelter'=>shelter_row($stmt->fetch())], 201);
    }

    $data = request_body();
    $code = trim((string)($_GET['id'] ?? $data['id'] ?? ''));
    if ($code === '') json_response(['ok'=>false,'message'=>'ไม่พบรหัสศูนย์พักพิง'], 422);
    if ($method === 'PUT' || $method === 'PATCH') {
        validate_shelter_coordinates($data);
        $values = shelter_values($data);
        $stmt = $pdo->prepare('UPDATE shelters SET name=:name, province=:province, district=:district, address=:address, latitude=:lat, longitude=:lng, capacity=:capacity, occupied=:occupied, status=:status, phone=:phone, services=:services WHERE code=:code');
        $stmt->execute($values + ['code'=>$code]);
        if ($stmt->rowCount() === 0) {
            $check = $pdo->prepare('SELECT 1 FROM shelters WHERE code=?'); $check->execute([$code]);
            if (!$check->fetchColumn()) json_response(['ok'=>false,'message'=>'ไม่พบศูนย์พักพิงนี้'], 404);
        }
        $stmt = $pdo->prepare('SELECT * FROM shelters WHERE code = ?'); $stmt->execute([$code]);
        json_response(['ok'=>true,'shelter'=>shelter_row($stmt->fetch())]);
    }
    if ($method === 'DELETE') {
        $stmt = $pdo->prepare('DELETE FROM shelters WHERE code=?'); $stmt->execute([$code]);
        if (!$stmt->rowCount()) json_response(['ok'=>false,'message'=>'ไม่พบศูนย์พักพิงนี้'], 404);
        json_response(['ok'=>true]);
    }
    header('Allow: GET, POST, PUT, PATCH, DELETE');
    json_response(['ok'=>false,'message'=>'Method not allowed'], 405);
} catch (Throwable $e) {
    error_log('SFR shelters API: ' . $e->getMessage());
    json_response(['ok'=>false,'message'=>'เชื่อมฐานข้อมูลไม่สำเร็จ ตรวจสอบ MySQL และการ import database.sql'], 503);
}

function shelter_values(array $data): array {
    $status = (string)($data['status'] ?? 'เปิดรับ');
    if (!in_array($status, ['เปิดรับ','เต็มแล้ว','ปิดชั่วคราว'], true)) $status = 'เปิดรับ';
    $services = $data['services'] ?? ['ที่พักชั่วคราว','อาหาร','น้ำดื่ม'];
    if (!is_array($services)) $services = [];
    return [
        'name'=>trim((string)($data['name'] ?? '')),
        'province'=>trim((string)($data['province'] ?? '')),
        'district'=>trim((string)($data['district'] ?? '')),
        'address'=>trim((string)($data['address'] ?? '')),
        'lat'=>(float)$data['lat'],
        'lng'=>(float)$data['lng'],
        'capacity'=>max(0,(int)($data['capacity'] ?? 0)),
        'occupied'=>max(0,(int)($data['occupied'] ?? 0)),
        'status'=>$status,
        'phone'=>trim((string)($data['phone'] ?? '')),
        'services'=>json_encode(array_values($services), JSON_UNESCAPED_UNICODE),
    ];
}

function validate_shelter_coordinates(array $data): void {
    if (!isset($data['lat'], $data['lng']) || !is_numeric($data['lat']) || !is_numeric($data['lng'])) {
        json_response(['ok'=>false,'message'=>'กรุณาระบุพิกัด Latitude และ Longitude ให้ครบ'], 422);
    }
    $lat = (float)$data['lat'];
    $lng = (float)$data['lng'];
    if ($lat < 5.5 || $lat > 11.0 || $lng < 97.0 || $lng > 103.0) {
        json_response(['ok'=>false,'message'=>'พิกัดอยู่นอกขอบเขตภาคใต้ของประเทศไทย'], 422);
    }
}
