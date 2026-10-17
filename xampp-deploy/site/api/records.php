<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function admin_only(): void { require_admin(); }
function json_list(?string $value): array { $v = json_decode($value ?? '[]', true); return is_array($v) ? $v : []; }
function id_value(): string { return trim((string)($_GET['id'] ?? request_body()['id'] ?? '')); }
function new_id(string $prefix): string { return $prefix . '-' . strtoupper(bin2hex(random_bytes(5))); }
function validate_southern_coordinates(array $data, bool $required = false): void {
    $hasLat = isset($data['lat']) && $data['lat'] !== '';
    $hasLng = isset($data['lng']) && $data['lng'] !== '';
    if ($hasLat !== $hasLng || ($required && (!$hasLat || !$hasLng))) {
        json_response(['ok'=>false,'message'=>'กรุณาระบุพิกัด Latitude และ Longitude ให้ครบ'], 422);
    }
    if (!$hasLat) return;
    if (!is_numeric($data['lat']) || !is_numeric($data['lng'])) json_response(['ok'=>false,'message'=>'พิกัดไม่ถูกต้อง'], 422);
    $lat = (float)$data['lat']; $lng = (float)$data['lng'];
    if ($lat < 5.5 || $lat > 11.0 || $lng < 97.0 || $lng > 103.0) {
        json_response(['ok'=>false,'message'=>'พิกัดอยู่นอกขอบเขตภาคใต้ของประเทศไทย'], 422);
    }
}

try {
    $pdo = db();
    $resource = (string)($_GET['resource'] ?? '');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $admin = false;
    start_sfr_session();
    $admin = !empty($_SESSION['sfr_admin']);

    $tables = ['flood_reports'=>'flood_reports','help_requests'=>'help_requests','volunteer_teams'=>'volunteer_teams','flood_points'=>'flood_points','announcements'=>'announcements','notifications'=>'notifications'];
    if (!isset($tables[$resource])) json_response(['ok'=>false,'message'=>'ไม่รู้จัก resource นี้'], 404);
    $table = $tables[$resource];

    if ($method === 'GET') {
        if (in_array($resource, ['flood_reports','help_requests','volunteer_teams'], true) && !$admin) json_response(['ok'=>false,'message'=>'กรุณาเข้าสู่ระบบผู้ดูแลก่อน'], 401);
        if ($resource === 'flood_points') {
            $sql = 'SELECT * FROM flood_points' . ($admin ? '' : " WHERE status IN ('ตรวจสอบแล้ว','ยืนยันแล้ว','เปิดให้บริการ')") . ' ORDER BY created_at DESC';
            $rows = $pdo->query($sql)->fetchAll();
            foreach ($rows as &$r) { $r['lat']=(float)$r['latitude']; $r['lng']=(float)$r['longitude']; if (!$admin) { $r['lat']=round($r['lat'],2); $r['lng']=round($r['lng'],2); } $r['people']=(int)$r['people_count']; $r['water']=$r['water_level']; $r['time']=date('H:i',strtotime($r['created_at'])) . ' น.'; unset($r['latitude'],$r['longitude'],$r['people_count'],$r['water_level'],$r['created_at']); }
        } elseif ($resource === 'flood_reports') {
            $rows=$pdo->query('SELECT * FROM flood_reports ORDER BY created_at DESC')->fetchAll();
            foreach ($rows as &$r) { $r['type']=$r['event_type']; $r['place']=trim($r['district'].' '.$r['province']); $r['level']=$r['water_level']; $r['time']=date('H:i',strtotime($r['created_at'])).' น.'; unset($r['event_type'],$r['water_level'],$r['created_at']); }
        } elseif ($resource === 'help_requests') {
            $rows=$pdo->query('SELECT * FROM help_requests ORDER BY FIELD(urgency,"ฉุกเฉิน","เร่งด่วน","ปกติ"), created_at DESC')->fetchAll();
            foreach ($rows as &$r) { $r['needs']=json_list($r['needs']); $r['title']='ผู้ขอความช่วยเหลือ'; $r['need']=implode(' + ',$r['needs']); $r['people']=(int)$r['people_count']; $r['priority']=$r['urgency']; $r['lat']=$r['latitude']===null?null:(float)$r['latitude']; $r['lng']=$r['longitude']===null?null:(float)$r['longitude']; $r['where']=trim($r['district'].' '.$r['province']); $r['id']=$r['id']; unset($r['people_count'],$r['latitude'],$r['longitude'],$r['created_at']); }
        } elseif ($resource === 'volunteer_teams') {
            $rows=$pdo->query('SELECT * FROM volunteer_teams ORDER BY created_at DESC')->fetchAll();
            foreach ($rows as &$r) { $r['name']=$r['team_name']; $r['province']=$r['province']; $r['people']=(int)$r['member_count']; $r['vehicles']=$r['vehicles']; $r['skills']=json_list($r['skills']); unset($r['team_name'],$r['member_count'],$r['created_at']); }
        } elseif ($resource === 'announcements') {
            $rows=$pdo->query('SELECT * FROM announcements ' . ($admin ? '' : "WHERE status='เผยแพร่' ") . 'ORDER BY published_at DESC')->fetchAll();
            foreach ($rows as &$r) { $r['time']=date('H:i',strtotime($r['published_at'])).' น.'; unset($r['published_at']); }
        } else {
            $roles=$admin ? "'all','public','admin'" : "'all','public'"; $rows=$pdo->query("SELECT * FROM notifications WHERE read_at IS NULL AND recipient_role IN ({$roles}) ORDER BY created_at DESC LIMIT 30")->fetchAll();
            foreach ($rows as &$r) { $r['time']=date('H:i',strtotime($r['created_at'])).' น.'; unset($r['created_at'],$r['read_at']); }
        }
        json_response(['ok'=>true,'records'=>$rows]);
    }

    $data=request_body();
    if ($method === 'POST') {
        if (in_array($resource,['flood_reports','help_requests','volunteer_teams'],true)) {
            if ($resource !== 'volunteer_teams') validate_southern_coordinates($data);
            $id=new_id(['flood_reports'=>'FR','help_requests'=>'HR','volunteer_teams'=>'VT'][$resource]);
            $pdo->beginTransaction();
            if ($resource==='flood_reports') {
                $province=trim((string)($data['province']??'')); if ($province==='') json_response(['ok'=>false,'message'=>'กรุณาระบุจังหวัด'],422);
                $stmt=$pdo->prepare('INSERT INTO flood_reports (id,event_type,water_level,details,affected_count,province,district,latitude,longitude,contact_phone,needs_help) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$id,trim((string)($data['event_type']??'น้ำท่วม')),trim((string)($data['water_level']??'')),trim((string)($data['details']??'')),max(1,(int)($data['affected_count']??1)),$province,trim((string)($data['district']??'')),$data['lat']??null,$data['lng']??null,trim((string)($data['contact_phone']??'')),!empty($data['needs_help'])?1:0]);
                if (isset($data['lat'],$data['lng'])) { $p=$pdo->prepare("INSERT INTO flood_points (id,kind,name,province,district,water_level,details,latitude,longitude,status,urgency,people_count,need) VALUES (?,?,?,?,?,?,?,?,?,'รอตรวจสอบ','เฝ้าระวัง',?,?)"); $p->execute([$id,'น้ำท่วม','รายงานจากประชาชน',$province,(string)($data['district']??''),(string)($data['water_level']??''),(string)($data['details']??''),(float)$data['lat'],(float)$data['lng'],(int)($data['affected_count']??1),!empty($data['needs_help'])?'ต้องการความช่วยเหลือ':'']); }
                $title='มีรายงานน้ำท่วมใหม่'; $type='รายงาน';
            } elseif ($resource==='help_requests') {
                $province=trim((string)($data['province']??'')); if ($province==='') json_response(['ok'=>false,'message'=>'กรุณาระบุจังหวัด'],422);
                $needs=is_array($data['needs']??null)?$data['needs']:[];
                $stmt=$pdo->prepare('INSERT INTO help_requests (id,needs,details,people_count,urgency,province,district,latitude,longitude,contact_phone) VALUES (?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$id,json_encode(array_values($needs),JSON_UNESCAPED_UNICODE),trim((string)($data['details']??'')),max(1,(int)($data['people_count']??1)),in_array($data['urgency']??'', ['ปกติ','เร่งด่วน','ฉุกเฉิน'],true)?$data['urgency']:'ปกติ',$province,trim((string)($data['district']??'')),$data['lat']??null,$data['lng']??null,trim((string)($data['contact_phone']??''))]);
                if (isset($data['lat'],$data['lng'])) { $p=$pdo->prepare("INSERT INTO flood_points (id,kind,name,province,district,water_level,details,latitude,longitude,status,urgency,people_count,need) VALUES (?,?,?,?,?,'',?,?,?, 'รออาสา',?,?,?)"); $p->execute([$id,'ขอความช่วยเหลือ','ผู้ขอความช่วยเหลือ',$province,(string)($data['district']??''),trim((string)($data['details']??'')),(float)$data['lat'],(float)$data['lng'],$data['urgency']??'ปกติ',(int)($data['people_count']??1),implode(' + ',$needs)]); }
                $title='มีคำขอความช่วยเหลือใหม่'; $type='คำขอช่วยเหลือ';
            } else {
                $team=trim((string)($data['team_name']??'')); $phone=trim((string)($data['phone']??'')); $province=trim((string)($data['province']??''));
                if ($team===''||$phone===''||$province==='') json_response(['ok'=>false,'message'=>'กรุณากรอกชื่อทีม เบอร์ติดต่อ และจังหวัด'],422);
                $stmt=$pdo->prepare('INSERT INTO volunteer_teams (id,team_name,contact_name,phone,province,district,skills,vehicles,service_areas,member_count,description) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$id,$team,trim((string)($data['contact_name']??'')),$phone,$province,trim((string)($data['district']??'')),json_encode(is_array($data['skills']??null)?array_values($data['skills']):[],JSON_UNESCAPED_UNICODE),trim((string)($data['vehicles']??'')),trim((string)($data['service_areas']??'')),max(1,(int)($data['member_count']??1)),trim((string)($data['description']??''))]);
                $title='มีใบสมัครทีมอาสาสมัครใหม่'; $type='อาสาสมัคร';
            }
            $n=$pdo->prepare("INSERT INTO notifications (title,content,type,recipient_role) VALUES (?,?,?,'admin')"); $n->execute([$title,(string)($data['province']??''),$type]);
            $pdo->commit();
            json_response(['ok'=>true,'id'=>$id,'status'=>$resource==='volunteer_teams'?'รออนุมัติ':($resource==='help_requests'?'รออาสา':'รอตรวจสอบ')],201);
        }
        admin_only();
        if ($resource==='flood_points') {
            validate_southern_coordinates($data, true);
            $id=new_id('FP'); $s=$pdo->prepare('INSERT INTO flood_points (id,kind,name,province,district,water_level,details,latitude,longitude,status,urgency,people_count,need) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $s->execute([$id,$data['kind']??'น้ำท่วม',$data['name']??'จุดใหม่',$data['province']??'', $data['district']??'', $data['water']??'', $data['details']??'',(float)($data['lat']??0),(float)($data['lng']??0),$data['status']??'รอตรวจสอบ',$data['urgency']??'เฝ้าระวัง',(int)($data['people']??0),$data['need']??'']);
        } elseif ($resource==='announcements') {
            $title=trim((string)($data['title']??'')); if ($title==='') json_response(['ok'=>false,'message'=>'กรุณาระบุหัวข้อประกาศ'],422);
            $s=$pdo->prepare("INSERT INTO announcements (title,content,level,province,status) VALUES (?,?,?,?,'เผยแพร่')"); $s->execute([$title,(string)($data['content']??''),(string)($data['level']??'ข้อมูลทั่วไป'),(string)($data['province']??'')]);
            $n=$pdo->prepare("INSERT INTO notifications (title,content,type,recipient_role) VALUES (?,?, 'ประกาศ','all')"); $n->execute([$title,(string)($data['content']??'')]);
        } elseif ($resource==='notifications') {
            $s=$pdo->prepare('INSERT INTO notifications (title,content,type,recipient_role) VALUES (?,?,?,?)'); $s->execute([(string)($data['title']??''),(string)($data['content']??''),(string)($data['type']??'ระบบ'),(string)($data['recipient_role']??'all')]);
        } else json_response(['ok'=>false,'message'=>'ไม่อนุญาตให้สร้างรายการใน resource นี้'],405);
        json_response(['ok'=>true,'id'=>$id??(string)$pdo->lastInsertId()],201);
    }

    // Notification read state is private account data. This demo only has an
    // admin session, so require it until per-user notification ownership exists.
    admin_only();
    $id=id_value();
    if ($method==='PATCH') {
        if ($id==='') json_response(['ok'=>false,'message'=>'ต้องระบุ id'],422);
        if ($resource==='flood_reports') { $status=$data['status']??'รอตรวจสอบ'; if (!in_array($status,['รอตรวจสอบ','ยืนยันแล้ว','ปฏิเสธ'],true)) json_response(['ok'=>false,'message'=>'สถานะรายงานไม่ถูกต้อง'],422); $s=$pdo->prepare('UPDATE flood_reports SET status=? WHERE id=?'); $s->execute([$status,$id]); $p=$pdo->prepare('UPDATE flood_points SET status=? WHERE id=?'); $p->execute([$status,$id]); }
        elseif ($resource==='help_requests') { $s=$pdo->prepare('UPDATE help_requests SET status=COALESCE(?,status), assigned_team=COALESCE(?,assigned_team) WHERE id=?'); $s->execute([$data['status']??null,$data['assigned_team']??null,$id]); }
        elseif ($resource==='volunteer_teams') { $s=$pdo->prepare('UPDATE volunteer_teams SET status=? WHERE id=?'); $s->execute([$data['status']??'รออนุมัติ',$id]); }
        elseif ($resource==='flood_points') { $s=$pdo->prepare('UPDATE flood_points SET status=COALESCE(?,status), urgency=COALESCE(?,urgency) WHERE id=?'); $s->execute([$data['status']??null,$data['urgency']??null,$id]); }
        elseif ($resource==='notifications') { if ($id==='all') { $pdo->exec("UPDATE notifications SET read_at=CURRENT_TIMESTAMP WHERE read_at IS NULL AND recipient_role IN ('all','public','admin')"); } else { $s=$pdo->prepare("UPDATE notifications SET read_at=CURRENT_TIMESTAMP WHERE id=? AND recipient_role IN ('all','public','admin')"); $s->execute([(int)$id]); } }
        else json_response(['ok'=>false,'message'=>'ไม่รองรับการแก้ไขรายการนี้'],405);
        json_response(['ok'=>true]);
    }
    if ($method==='DELETE' && $resource==='announcements') { $s=$pdo->prepare("UPDATE announcements SET status='ยกเลิก' WHERE id=?"); $s->execute([(int)$id]); json_response(['ok'=>true]); }
    header('Allow: GET, POST, PATCH, DELETE');
    json_response(['ok'=>false,'message'=>'Method not allowed'],405);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('SFR records API: '.$e->getMessage());
    json_response(['ok'=>false,'message'=>'ดำเนินการกับฐานข้อมูลไม่สำเร็จ'],503);
}
