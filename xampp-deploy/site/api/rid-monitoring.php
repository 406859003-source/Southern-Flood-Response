<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

// RID credentials stay in Apache/PHP environment variables and never reach React.
$paths = [
    // Tele RID: river-flow data and station/building locations.
    'water' => '/api/pier-tele-data/',
    'location' => '/api/pier-tele-location-master/',
    // HII rainfall and water feeds are separate SWOC data sources.
    'hiiwater' => '/api/pier-hii-data/',
    'rain1h' => '/api/rainfall-hii-1hr/',
    'rain24h' => '/api/rainfall-hii-24hr/',
];
$type = (string)($_GET['type'] ?? 'water');
if (!isset($paths[$type])) json_response(['ok'=>false,'message'=>'ชนิดข้อมูลไม่ถูกต้อง'], 422);

$configuredBase = getenv('SFR_RID_API_BASE');
if ($configuredBase === false || trim($configuredBase) === '') $configuredBase = $_SERVER['SFR_RID_API_BASE'] ?? $_ENV['SFR_RID_API_BASE'] ?? '';
$base = rtrim((string)($configuredBase ?: 'https://swoc-api-service.rid.go.th'), '/');
$configuredToken = getenv('SFR_RID_API_TOKEN');
if ($configuredToken === false || trim($configuredToken) === '') $configuredToken = $_SERVER['SFR_RID_API_TOKEN'] ?? $_ENV['SFR_RID_API_TOKEN'] ?? '';
$token = trim((string)$configuredToken);
$cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sfr_rid_' . hash('sha256', $type . $base . hash('sha256', $token)) . '.json';
$ttl = 300;
$failureFile = $cacheFile . '.error';
if (is_file($cacheFile) && filemtime($cacheFile) >= time() - $ttl) {
    $cached = json_decode((string)file_get_contents($cacheFile), true);
    if (is_array($cached)) json_response($cached);
}
if (is_file($failureFile) && filemtime($failureFile) >= time() - 60) {
    $failed = json_decode((string)file_get_contents($failureFile), true);
    if (is_array($failed)) {
        if (is_file($cacheFile)) {
            $stale = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($stale)) { $stale['stale'] = true; json_response($stale); }
        }
        json_response($failed, 503);
    }
}

$url = $base . $paths[$type];
$headers = ['Accept: application/json'];
if ($token !== '') $headers[] = 'Authorization: Bearer ' . $token;
$body = false;
$status = 0;
if (function_exists('curl_init')) {
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_HTTPHEADER=>$headers, CURLOPT_USERAGENT=>'SouthernFloodResponse/1.0']);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
} elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
    $context = stream_context_create(['http'=>['timeout'=>15,'ignore_errors'=>true,'header'=>implode("\r\n", $headers) . "\r\nUser-Agent: SouthernFloodResponse/1.0\r\n"], 'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $body = @file_get_contents($url, false, $context);
    foreach ($http_response_header ?? [] as $line) if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $m)) $status = (int)$m[1];
}
$data = is_string($body) ? json_decode($body, true) : null;
if ($status >= 200 && $status < 300 && is_array($data)) {
    $result = ['ok'=>true,'source'=>'กรมชลประทาน · SWOC API','type'=>$type,'source_url'=>$url,'fetched_at'=>date(DATE_ATOM),'data'=>$data];
    @unlink($failureFile);
    @file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
    json_response($result);
}
if (is_file($cacheFile)) {
    $stale = json_decode((string)file_get_contents($cacheFile), true);
    if (is_array($stale)) { $stale['stale'] = true; json_response($stale); }
}
error_log('SFR RID monitoring upstream status: ' . $status);
$failure = ['ok'=>false,'message'=>$status === 401 || $status === 403 ? 'RID ปฏิเสธการเข้าถึง กรุณาตั้งค่า SFR_RID_API_TOKEN ที่ Apache/PHP' : 'เชื่อมต่อข้อมูล RID ไม่สำเร็จ','upstream_status'=>$status ?: null,'type'=>$type];
@file_put_contents($failureFile, json_encode($failure, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
json_response($failure, 503);
