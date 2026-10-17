<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

try {
    $pdo = db();
    $period = (string)($_GET['period'] ?? '7d');
    $days = ['24h'=>1, '7d'=>7, '30d'=>30][$period] ?? 7;
    $interval = $days === 1 ? 'INTERVAL 24 HOUR' : 'INTERVAL ' . $days . ' DAY';
    $counts = [];
    foreach ([
        'reports' => ['flood_reports', 'created_at'],
        'requests' => ['help_requests', 'created_at'],
        'teams' => ['volunteer_teams', 'created_at'],
        'points' => ['flood_points', 'created_at'],
    ] as $key => [$table, $column]) {
        $counts[$key] = (int)$pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$column} >= NOW() - {$interval}")->fetchColumn();
    }
    $counts['affected'] = (int)$pdo->query("SELECT COALESCE(SUM(affected_count),0) FROM flood_reports WHERE created_at >= NOW() - {$interval} AND status <> 'ปฏิเสธ'")->fetchColumn();
    $counts['closed_roads'] = (int)$pdo->query("SELECT COUNT(*) FROM flood_points WHERE kind='ถนนปิด' AND status NOT IN ('ปิดแล้ว','ยกเลิก')")->fetchColumn();
    $stmt = $pdo->query("SELECT province, COUNT(*) AS reports FROM flood_reports WHERE created_at >= NOW() - {$interval} AND status <> 'ปฏิเสธ' GROUP BY province ORDER BY reports DESC LIMIT 8");
    $byProvince = array_map(static fn(array $row): array => ['province'=>$row['province'], 'reports'=>(int)$row['reports']], $stmt->fetchAll());
    json_response(['ok'=>true,'period'=>$period,'updated_at'=>date(DATE_ATOM),'counts'=>$counts,'by_province'=>$byProvince,'source'=>'ฐานข้อมูลรายงาน SFR']);
} catch (Throwable $e) {
    error_log('SFR statistics API: ' . $e->getMessage());
    json_response(['ok'=>false,'message'=>'อ่านสถิติจากฐานข้อมูลไม่สำเร็จ'], 503);
}
