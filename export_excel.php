<?php
require_once 'db.php';

// 1. ตรวจสอบสิทธิ์ (ต้องล็อกอินเท่านั้น)
if (!isset($_SESSION['user_id'])) {
    exit('Access Denied');
}

// 2. รับค่าจาก URL
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : '';
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

if (empty($class_id)) {
    exit('กรุณาระบุห้องเรียน');
}

// 3. ดึงข้อมูลห้องเรียน (เพื่อเอาไปตั้งชื่อไฟล์)
$stmtClass = $conn->prepare("SELECT * FROM classes WHERE class_id = ?");
$stmtClass->execute([$class_id]);
$classInfo = $stmtClass->fetch(PDO::FETCH_ASSOC);
$className = "ม." . $classInfo['level_name'] . $classInfo['room_number'];
$filename = "Report_" . $className . "_" . $date . ".csv";

// 4. ตั้งค่า Header เพื่อแจ้ง Browser ว่าเป็นไฟล์ดาวน์โหลด
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// 5. สร้าง Pointer สำหรับเขียนไฟล์
$output = fopen('php://output', 'w');

// 6. [สำคัญ] เขียน BOM (Byte Order Mark) เพื่อให้ Excel อ่านภาษาไทยออก
fputs($output, "\xEF\xBB\xBF");

// 7. เขียนหัวตาราง (Header Row)
fputcsv($output, [
    'ลำดับ',
    'รหัสนักเรียน', 
    'คำนำหน้า', 
    'ชื่อ', 
    'นามสกุล', 
    'สถานะ', 
    'รายละเอียดการผิดระเบียบ', 
    'หมายเหตุ',
    'ผู้ตรวจ',
    'เวลาที่ตรวจ'
]);

// 8. ดึงข้อมูลนักเรียนและผลการตรวจ
$sql = "SELECT s.*, 
        i.result_status, i.inspection_date, i.note,
        u.full_name as inspector_name,
        (SELECT GROUP_CONCAT(r.rule_name SEPARATOR ', ') 
         FROM inspection_violations iv 
         JOIN inspection_rules r ON iv.rule_id = r.rule_id 
         WHERE iv.inspection_id = i.inspection_id) as violations
        FROM students s
        LEFT JOIN inspections i ON s.student_id = i.student_id AND DATE(i.inspection_date) = :filter_date
        LEFT JOIN users u ON i.inspector_id = u.user_id
        WHERE s.current_class_id = :class_id
        ORDER BY s.student_code ASC";

$stmt = $conn->prepare($sql);
$stmt->execute(['filter_date' => $date, 'class_id' => $class_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. วนลูปเขียนข้อมูลทีละแถว
$i = 1;
foreach ($rows as $row) {
    // แปลงสถานะเป็นภาษาไทย
    $statusText = 'ยังไม่ตรวจ';
    if ($row['result_status'] == 'pass') $statusText = 'ผ่าน';
    elseif ($row['result_status'] == 'fail') $statusText = 'ไม่ผ่าน';

    // จัดเตรียมข้อมูลลง Array
    $lineData = [
        $i++, // ลำดับ
        $row['student_code'],
        $row['prefix'],
        $row['first_name'],
        $row['last_name'],
        $statusText,
        $row['violations'] ? $row['violations'] : '-', // ถ้าไม่มีให้ขีดละ
        $row['note'],
        $row['inspector_name'] ? $row['inspector_name'] : '-',
        $row['inspection_date'] ? date('H:i:s', strtotime($row['inspection_date'])) : '-'
    ];

    fputcsv($output, $lineData);
}

// 10. ปิดไฟล์
fclose($output);
exit();
?>