<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/external-data.php';

$provinces = ['ชุมพร','ระนอง','สุราษฎร์ธานี','พังงา','ภูเก็ต','กระบี่','นครศรีธรรมราช','ตรัง','พัทลุง','สตูล','สงขลา','ปัตตานี','ยะลา','นราธิวาส'];
$province = trim((string)($_GET['province'] ?? 'ทุกจังหวัด'));
if ($province !== 'ทุกจังหวัด' && !in_array($province, $provinces, true)) json_response(['ok'=>false,'message'=>'ไม่พบจังหวัดที่เลือก'], 422);

try {
    $data = external_json('https://api-v3.thaiwater.net/api/v1/thaiwater30/public/waterlevel_load', 'thaiwater_level', 300);
    $rows = $data['waterlevel_data']['data'] ?? $data['data'] ?? [];
    if (!is_array($rows)) throw new RuntimeException('รูปแบบข้อมูลสถานีวัดน้ำไม่ถูกต้อง');
    $stations = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $station = $row['station'] ?? [];
        $geo = $row['geocode'] ?? [];
        $stationProvince = thai_scalar($geo['province_name'] ?? $row['province'] ?? '');
        if (!in_array($stationProvince, $provinces, true) || ($province !== 'ทุกจังหวัด' && $stationProvince !== $province)) continue;
        $level = $row['waterlevel_m'] ?? $row['waterlevel_msl'] ?? $row['water_level'] ?? $row['value'] ?? null;
        $time = $row['waterlevel_datetime'] ?? $row['measure_datetime'] ?? $row['measureTime'] ?? $row['update_datetime'] ?? null;
        $situation = (int)($row['situation_level'] ?? 0);
        $bankText = thai_scalar($row['diff_wl_bank_text'] ?? '');
        $warning = $row['warning_level_m'] ?? $station['warning_level_m'] ?? null;
        $critical = $row['critical_level_m'] ?? $station['critical_level_m'] ?? null;
        $status = str_starts_with($bankText, 'ล้นตลิ่ง') || $situation >= 5 ? 'ล้นตลิ่ง' : ($situation >= 4 ? 'วิกฤต' : ($situation === 3 ? 'เตือนภัย' : ($situation === 2 ? 'เฝ้าระวัง' : 'ปกติ')));
        if ($situation === 0 && is_numeric($level)) {
            if (is_numeric($critical) && (float)$level >= (float)$critical) $status = 'วิกฤต';
            elseif (is_numeric($warning) && (float)$level >= (float)$warning) $status = 'เฝ้าระวัง';
            elseif (!is_numeric($warning) && !is_numeric($critical)) $status = 'ไม่ระบุ';
        }
        $stations[] = [
            'id'=>(string)($station['tele_station_oldcode'] ?? $station['tele_station_id'] ?? $row['station_id'] ?? ''),
            'name'=>thai_scalar($station['tele_station_name'] ?? $row['station_name'] ?? 'สถานีวัดระดับน้ำ'),
            'province'=>$stationProvince,
            'district'=>thai_scalar($geo['amphoe_name'] ?? $geo['district_name'] ?? ''),
            'level'=>is_numeric($level) ? (float)$level : null,
            'unit'=>thai_scalar($row['waterlevel_unit'] ?? $row['unit'] ?? 'ม.'),
            'time'=>is_string($time) ? $time : null,
            'status'=>$status,
            'situation_level'=>$situation ?: null,
            'warning_level'=>is_numeric($warning) ? (float)$warning : null,
            'critical_level'=>is_numeric($critical) ? (float)$critical : null,
            'bank_status'=>$bankText,
            'lat'=>is_numeric($station['tele_station_lat'] ?? $station['latitude'] ?? $row['latitude'] ?? null) ? (float)($station['tele_station_lat'] ?? $station['latitude'] ?? $row['latitude']) : null,
            'lng'=>is_numeric($station['tele_station_long'] ?? $station['longitude'] ?? $row['longitude'] ?? null) ? (float)($station['tele_station_long'] ?? $station['longitude'] ?? $row['longitude']) : null,
        ];
    }
    usort($stations, static function(array $a,array $b): int {
        $rank=['ล้นตลิ่ง'=>5,'วิกฤต'=>4,'เตือนภัย'=>3,'เฝ้าระวัง'=>2,'ปกติ'=>1];
        return (($rank[$b['status']] ?? 0) <=> ($rank[$a['status']] ?? 0)) ?: strcmp((string)$b['time'], (string)$a['time']);
    });
    $stations = array_slice($stations, 0, 120);
    json_response(['ok'=>true,'province'=>$province,'stations'=>$stations,'count'=>count($stations),'source'=>'คลังข้อมูลน้ำแห่งชาติ · ThaiWater','source_url'=>'https://www.thaiwater.net/','updated_at'=>$data['_sfr_fetched_at'] ?? date(DATE_ATOM),'stale'=>(bool)($data['_sfr_stale'] ?? false),'cached_seconds'=>300]);
} catch (Throwable $e) {
    error_log('SFR water stations API: ' . $e->getMessage());
    json_response(['ok'=>false,'message'=>'ดึงข้อมูลสถานีวัดน้ำไม่สำเร็จ กรุณาลองใหม่ภายหลัง'], 503);
}
