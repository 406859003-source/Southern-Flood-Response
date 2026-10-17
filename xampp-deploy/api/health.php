<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db.php';
try {
    $pdo = db();
    $count = (int)$pdo->query('SELECT COUNT(*) FROM shelters')->fetchColumn();
    $tables = ['flood_reports','help_requests','volunteer_teams','flood_points','shelters','announcements','notifications'];
    $counts = [];
    foreach ($tables as $table) {
        // Names come from this fixed allowlist, never from request input.
        $counts[$table] = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    }
    json_response(['ok'=>true,'database'=>'southern_flood','shelter_count'=>$count,'tables'=>$counts]);
} catch (Throwable $e) {
    error_log('SFR health check: ' . $e->getMessage());
    json_response(['ok'=>false,'message'=>'MySQL ยังไม่พร้อมหรือยังไม่ได้ import database.sql'], 503);
}
