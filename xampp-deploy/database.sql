CREATE DATABASE IF NOT EXISTS southern_flood
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE southern_flood;

CREATE TABLE IF NOT EXISTS shelters (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  province VARCHAR(80) NOT NULL,
  district VARCHAR(100) NOT NULL DEFAULT '',
  address VARCHAR(500) NOT NULL DEFAULT '',
  
  latitude DECIMAL(10,7) NOT NULL DEFAULT 7.0100000,
  longitude DECIMAL(10,7) NOT NULL DEFAULT 100.4700000,
  capacity INT UNSIGNED NOT NULL DEFAULT 0,
  occupied INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('เปิดรับ','เต็มแล้ว','ปิดชั่วคราว') NOT NULL DEFAULT 'เปิดรับ',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  services JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_shelters_province_status (province, status),
  INDEX idx_shelters_name (name)
) ENGINE=InnoDB;

INSERT INTO shelters (code,name,province,district,address,latitude,longitude,capacity,occupied,status,phone,services) VALUES
('SH-001','โรงเรียนเทศบาล 2','สงขลา','หาดใหญ่','ถนนเพชรเกษม ตำบลหาดใหญ่',7.0100000,100.4700000,250,130,'เปิดรับ','074-221-180',JSON_ARRAY('ที่พักชั่วคราว','อาหาร','น้ำดื่ม','ห้องน้ำ','พื้นที่ผู้สูงอายุ')),
('SH-002','มหาวิทยาลัยสงขลานครินทร์','สงขลา','หาดใหญ่','ถนนกาญจนวณิชย์ ตำบลคอหงส์',7.0000000,100.5000000,500,318,'เปิดรับ','074-446-800',JSON_ARRAY('ที่พักชั่วคราว','อาหาร','น้ำดื่ม','ปฐมพยาบาล','จุดชาร์จไฟ')),
('SH-003','ศูนย์กีฬาเทศบาลเมืองยะลา','ยะลา','เมืองยะลา','ถนนสุขยางค์ ตำบลสะเตง',6.5400000,101.2800000,180,96,'เปิดรับ','073-212-100',JSON_ARRAY('ที่พักชั่วคราว','อาหาร','น้ำดื่ม','ห้องน้ำ')),
('SH-004','โรงเรียนปัตตานีวิทยามูลนิธิ','ปัตตานี','เมืองปัตตานี','ถนนปัตตานีภิรมย์ ตำบลอาเนาะรู',6.8700000,101.2500000,200,200,'เต็มแล้ว','073-335-410',JSON_ARRAY('อาหาร','น้ำดื่ม','ปฐมพยาบาล')),
('SH-005','โรงพยาบาลส่งเสริมสุขภาพตำบลควนลัง','สงขลา','หาดใหญ่','ตำบลควนลัง อำเภอหาดใหญ่',7.0500000,100.4300000,80,34,'เปิดรับ','074-305-221',JSON_ARRAY('ปฐมพยาบาล','ยา/เวชภัณฑ์','ผู้สูงอายุ')),
('SH-006','ศูนย์ชุมชนเทศบาลเมืองนครศรีธรรมราช','นครศรีธรรมราช','เมืองนครศรีธรรมราช','ถนนราชดำเนิน ตำบลในเมือง',8.4300000,99.9600000,160,48,'เปิดรับ','075-341-120',JSON_ARRAY('ที่พักชั่วคราว','อาหาร','น้ำดื่ม','ห้องน้ำ')),
('SH-007','อาคารอเนกประสงค์เทศบาลเมืองตรัง','ตรัง','เมืองตรัง','ถนนวิเศษกุล ตำบลทับเที่ยง',7.5600000,99.6100000,120,61,'เปิดรับ','075-218-801',JSON_ARRAY('ที่พักชั่วคราว','อาหาร','น้ำดื่ม','พื้นที่ผู้พิการ')),
('SH-008','โรงเรียนพัทลุง','พัทลุง','เมืองพัทลุง','ถนนราเมศวร์ ตำบลคูหาสวรรค์',7.6200000,100.0800000,150,83,'เปิดรับ','074-613-006',JSON_ARRAY('ที่พักชั่วคราว','อาหาร','น้ำดื่ม','จุดชาร์จไฟ')),
('SH-009','ศูนย์ช่วยเหลือชุมชนสตูล','สตูล','เมืองสตูล','ถนนสตูลธานี ตำบลพิมาน',6.6200000,100.0700000,90,22,'เปิดรับ','074-711-055',JSON_ARRAY('ที่พักชั่วคราว','อาหาร','น้ำดื่ม')),
('SH-010','ศูนย์อพยพเทศบาลนราธิวาส','นราธิวาส','เมืองนราธิวาส','ถนนภูผาภักดี ตำบลบางนาค',6.4300000,101.8200000,140,74,'เปิดรับ','073-511-222',JSON_ARRAY('ที่พักชั่วคราว','อาหาร','น้ำดื่ม','ปฐมพยาบาล'))
ON DUPLICATE KEY UPDATE code=VALUES(code);

CREATE TABLE IF NOT EXISTS flood_reports (
  id VARCHAR(48) PRIMARY KEY,
  event_type VARCHAR(100) NOT NULL,
  water_level VARCHAR(80) NOT NULL DEFAULT '',
  details TEXT NOT NULL,
  affected_count INT UNSIGNED NOT NULL DEFAULT 1,
  province VARCHAR(80) NOT NULL,
  district VARCHAR(100) NOT NULL DEFAULT '',
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  contact_phone VARCHAR(40) NOT NULL DEFAULT '',
  needs_help TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('รอตรวจสอบ','ยืนยันแล้ว','ปฏิเสธ') NOT NULL DEFAULT 'รอตรวจสอบ',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reports_status_created (status, created_at),
  INDEX idx_reports_location (province, district)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS help_requests (
  id VARCHAR(48) PRIMARY KEY,
  needs JSON NOT NULL,
  details TEXT NOT NULL,
  people_count INT UNSIGNED NOT NULL DEFAULT 1,
  urgency ENUM('ปกติ','เร่งด่วน','ฉุกเฉิน') NOT NULL DEFAULT 'ปกติ',
  province VARCHAR(80) NOT NULL,
  district VARCHAR(100) NOT NULL DEFAULT '',
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  contact_phone VARCHAR(40) NOT NULL DEFAULT '',
  status ENUM('รออาสา','รับงานแล้ว','กำลังเดินทาง','กำลังช่วยเหลือ','เสร็จสิ้น','ยกเลิก') NOT NULL DEFAULT 'รออาสา',
  assigned_team VARCHAR(120) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_help_status_urgency (status, urgency, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS volunteer_teams (
  id VARCHAR(48) PRIMARY KEY,
  team_name VARCHAR(160) NOT NULL,
  contact_name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  province VARCHAR(80) NOT NULL,
  district VARCHAR(100) NOT NULL DEFAULT '',
  skills JSON NOT NULL,
  vehicles VARCHAR(500) NOT NULL DEFAULT '',
  service_areas VARCHAR(500) NOT NULL DEFAULT '',
  member_count INT UNSIGNED NOT NULL DEFAULT 1,
  description TEXT NOT NULL,
  status ENUM('รออนุมัติ','อนุมัติแล้ว','ไม่อนุมัติ','ระงับ') NOT NULL DEFAULT 'รออนุมัติ',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_teams_status_province (status, province)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS flood_points (
  id VARCHAR(48) PRIMARY KEY,
  kind VARCHAR(60) NOT NULL,
  name VARCHAR(180) NOT NULL,
  province VARCHAR(80) NOT NULL,
  district VARCHAR(100) NOT NULL DEFAULT '',
  water_level VARCHAR(80) NOT NULL DEFAULT '',
  details TEXT NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  status VARCHAR(60) NOT NULL DEFAULT 'รอตรวจสอบ',
  urgency VARCHAR(40) NOT NULL DEFAULT 'เฝ้าระวัง',
  people_count INT UNSIGNED NOT NULL DEFAULT 0,
  need VARCHAR(300) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_points_kind_province (kind, province)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  content TEXT NOT NULL,
  level VARCHAR(60) NOT NULL DEFAULT 'ข้อมูลทั่วไป',
  province VARCHAR(80) NOT NULL DEFAULT '',
  status ENUM('เผยแพร่','ฉบับร่าง','ยกเลิก') NOT NULL DEFAULT 'เผยแพร่',
  published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_announcements_status_date (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  content TEXT NOT NULL,
  type VARCHAR(60) NOT NULL DEFAULT 'ประกาศ',
  recipient_role ENUM('public','volunteer','admin','all') NOT NULL DEFAULT 'all',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_notifications_role_date (recipient_role, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO flood_points (id,kind,name,province,district,water_level,details,latitude,longitude,status,urgency,people_count,need) VALUES
('FP-DEMO-001','น้ำท่วม','ชุมชนคลองแห','สงขลา','หาดใหญ่','1.8 ม.','รายงานตัวอย่างสำหรับสาธิตระบบ',7.0100000,100.4700000,'ตรวจสอบแล้ว','วิกฤต',18,'อพยพ'),
('FP-DEMO-002','น้ำท่วม','ตลาดปัตตานี','ปัตตานี','เมือง','0.7 ม.','รายงานตัวอย่างสำหรับสาธิตระบบ',6.8700000,101.2500000,'รอตรวจสอบ','เฝ้าระวัง',7,'อาหาร + น้ำ'),
('FP-DEMO-003','ถนนปิด','ถนนสาย 42 กม. 18','ปัตตานี','หนองจิก','—','ข้อมูลตัวอย่าง โปรดตรวจสอบกับเจ้าหน้าที่',6.8200000,101.1500000,'ตรวจสอบแล้ว','เร่งด่วน',0,'ปิดการจราจร');

INSERT IGNORE INTO help_requests (id,needs,details,people_count,urgency,province,district,latitude,longitude,contact_phone,status) VALUES
('HR-DEMO-001',JSON_ARRAY('อาหาร','น้ำดื่ม'),'คำขอตัวอย่างสำหรับสาธิตระบบ',4,'เร่งด่วน','ยะลา','เมืองยะลา',6.5400000,101.2800000,'','รออาสา');

INSERT IGNORE INTO flood_points (id,kind,name,province,district,water_level,details,latitude,longitude,status,urgency,people_count,need) VALUES
('HR-DEMO-001','ขอความช่วยเหลือ','ผู้ขอความช่วยเหลือตัวอย่าง','ยะลา','เมืองยะลา','','ข้อมูลสาธิต ตำแหน่งละเอียดแสดงเฉพาะ Admin',6.5400000,101.2800000,'รออาสา','เร่งด่วน',4,'อาหาร + น้ำดื่ม');

INSERT IGNORE INTO volunteer_teams (id,team_name,contact_name,phone,province,district,skills,vehicles,service_areas,member_count,description,status) VALUES
('VT-DEMO-001','YALA RESCUE','เจ้าหน้าที่ประสานงาน','073-000-000','ยะลา','เมืองยะลา',JSON_ARRAY('กู้ภัยทางน้ำ','ปฐมพยาบาล'),'รถยกสูง, เรือท้องแบน','ยะลา',12,'ทีมตัวอย่างสำหรับสาธิตระบบ','อนุมัติแล้ว');

INSERT INTO announcements (title,content,level,province,status) SELECT 'ตัวอย่างแจ้งเตือนฝนตกหนัก','ข้อมูลตัวอย่างเพื่อสาธิตระบบ โปรดตรวจสอบประกาศจากหน่วยงานทางการ','เฝ้าระวัง','ยะลา','เผยแพร่' WHERE NOT EXISTS (SELECT 1 FROM announcements);
INSERT INTO notifications (title,content,type,recipient_role) SELECT 'ระบบเชื่อมต่อฐานข้อมูลแล้ว','ข้อมูลแจ้งเตือนตัวอย่างสำหรับสาธิตระบบ','ระบบ','all' WHERE NOT EXISTS (SELECT 1 FROM notifications);
