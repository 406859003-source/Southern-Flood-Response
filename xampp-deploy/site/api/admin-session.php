<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
start_sfr_session();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'DELETE') {
    unset($_SESSION['sfr_admin']);
    json_response(['ok'=>true]);
}
if ($method === 'GET') json_response(['ok'=>!empty($_SESSION['sfr_admin'])]);
if ($method !== 'POST') json_response(['ok'=>false,'message'=>'Method not allowed'], 405);
$pin = (string)(request_body()['pin'] ?? '');
$now = time();
$_SESSION['sfr_login_attempts'] = array_values(array_filter(
    $_SESSION['sfr_login_attempts'] ?? [],
    static fn($attempt): bool => is_int($attempt) && $attempt > $now - 900
));
if (count($_SESSION['sfr_login_attempts']) >= 5) {
    header('Retry-After: 900');
    json_response(['ok'=>false,'message'=>'ลองเข้าสู่ระบบหลายครั้งเกินไป กรุณารอ 15 นาที'], 429);
}
$expected = getenv('SFR_ADMIN_PIN');
if (!is_string($expected) || $expected === '') {
    json_response(['ok'=>false,'message'=>'ผู้ดูแลระบบยังไม่ได้ตั้งค่า SFR_ADMIN_PIN'], 503);
}
if (!hash_equals($expected, $pin)) {
    $_SESSION['sfr_login_attempts'][] = $now;
    json_response(['ok'=>false,'message'=>'รหัสผ่านไม่ถูกต้อง'], 401);
}
unset($_SESSION['sfr_login_attempts']);
session_regenerate_id(true);
$_SESSION['sfr_admin'] = true;
json_response(['ok'=>true]);
