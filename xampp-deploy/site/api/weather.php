<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/external-data.php';

$locations = [
    'ชุมพร'=>[10.493,99.180], 'ระนอง'=>[9.966,98.634], 'สุราษฎร์ธานี'=>[9.140,99.333],
    'พังงา'=>[8.451,98.525], 'ภูเก็ต'=>[7.880,98.392], 'กระบี่'=>[8.086,98.906],
    'นครศรีธรรมราช'=>[8.430,99.963], 'ตรัง'=>[7.556,99.611], 'พัทลุง'=>[7.616,100.075],
    'สตูล'=>[6.623,100.067], 'สงขลา'=>[7.190,100.595], 'ปัตตานี'=>[6.869,101.250],
    'ยะลา'=>[6.541,101.281], 'นราธิวาส'=>[6.426,101.823],
];
$province = trim((string)($_GET['province'] ?? 'สงขลา'));
if (!isset($locations[$province])) json_response(['ok'=>false,'message'=>'ไม่พบจังหวัดที่เลือก'], 422);
[$lat,$lng] = $locations[$province];
$query = http_build_query([
    'latitude'=>$lat, 'longitude'=>$lng,
    'current'=>'temperature_2m,relative_humidity_2m,precipitation,wind_speed_10m,weather_code',
    'hourly'=>'precipitation_probability',
    'daily'=>'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,precipitation_sum',
    'forecast_days'=>7, 'timezone'=>'Asia/Bangkok', 'temperature_unit'=>'celsius',
    'wind_speed_unit'=>'kmh', 'precipitation_unit'=>'mm',
]);
try {
    $data = external_json('https://api.open-meteo.com/v1/forecast?' . $query, 'weather_' . $province, 1800);
    $current = $data['current'] ?? [];
    $daily = $data['daily'] ?? [];
    if (!isset($current['time'], $daily['time']) || !is_array($daily['time'])) throw new RuntimeException('รูปแบบพยากรณ์อากาศไม่ถูกต้อง');
    $forecast = [];
    foreach ($daily['time'] as $i=>$date) {
        $forecast[] = [
            'date'=>$date,
            'code'=>$daily['weather_code'][$i] ?? null,
            'min'=>$daily['temperature_2m_min'][$i] ?? null,
            'max'=>$daily['temperature_2m_max'][$i] ?? null,
            'rain_probability'=>$daily['precipitation_probability_max'][$i] ?? null,
            'rain_mm'=>$daily['precipitation_sum'][$i] ?? null,
        ];
    }
    json_response(['ok'=>true,'province'=>$province,'current'=>[
        'time'=>$current['time'], 'temperature'=>$current['temperature_2m'] ?? null,
        'humidity'=>$current['relative_humidity_2m'] ?? null, 'precipitation'=>$current['precipitation'] ?? null,
        'wind_kmh'=>$current['wind_speed_10m'] ?? null, 'code'=>$current['weather_code'] ?? null,
        'rain_probability'=>$data['hourly']['precipitation_probability'][0] ?? null,
    ],'forecast'=>$forecast,'source'=>'Open-Meteo · แบบจำลองพยากรณ์อากาศ','source_url'=>'https://open-meteo.com/','updated_at'=>$data['_sfr_fetched_at'] ?? date(DATE_ATOM),'stale'=>(bool)($data['_sfr_stale'] ?? false),'timezone'=>$data['timezone'] ?? 'Asia/Bangkok']);
} catch (Throwable $e) {
    error_log('SFR weather API: ' . $e->getMessage());
    json_response(['ok'=>false,'message'=>'ดึงพยากรณ์อากาศไม่สำเร็จ กรุณาลองใหม่ภายหลัง'], 503);
}
