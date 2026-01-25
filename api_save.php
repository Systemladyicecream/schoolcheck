<?php
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// --- 🔒 เพิ่มส่วนนี้: ป้องกัน Admin บันทึกข้อมูล ---
if ($_SESSION['role'] === 'admin') {
    http_response_code(403); // 403 Forbidden
    echo json_encode(['success' => false, 'message' => 'Admin ไม่ได้รับอนุญาตให้แก้ไขผลการตรวจ']);
    exit();
}
// ---------------------------------------------

$input = json_decode(file_get_contents('php://input'), true);
// ... (โค้ดส่วนที่เหลือเหมือนเดิม) ...
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if ($input) {
    $student_id = $input['student_id'];
    $status = $input['status'];
    $violations = $input['violations'] ?? [];
    $inspector_id = $_SESSION['user_id'];
    
    // รับค่าวันที่จาก Client ถ้าไม่มีให้ใช้วันปัจจุบัน
    $inspection_date = isset($input['date']) ? $input['date'] : date('Y-m-d');

    try {
        $conn->beginTransaction();

        // 1. เช็คว่าวันที่นั้น ($inspection_date) เคยตรวจหรือยัง?
        // สำคัญ: ต้องเช็คด้วยวันที่ที่ส่งมา ไม่ใช่ NOW()
        $checkStmt = $conn->prepare("SELECT inspection_id FROM inspections WHERE student_id = ? AND DATE(inspection_date) = ?");
        $checkStmt->execute([$student_id, $inspection_date]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $inspection_id = 0;

        if ($existing) {
            // Update
            $inspection_id = $existing['inspection_id'];
            $updateStmt = $conn->prepare("UPDATE inspections SET result_status = ?, inspector_id = ?, updated_at = NOW() WHERE inspection_id = ?");
            $updateStmt->execute([$status, $inspector_id, $inspection_id]);
            
            // ล้าง Violation เก่าทิ้งก่อนบันทึกใหม่
            $conn->prepare("DELETE FROM inspection_violations WHERE inspection_id = ?")->execute([$inspection_id]);
        } else {
            // Insert New (ระบุวันที่ลงไปด้วย)
            // เราใส่เวลาเป็น 08:00:00 เพื่อให้เป็นเวลามาตรฐานของวันนั้น (หรือจะใช้ H:i:s ปัจจุบันก็ได้ถ้าเป็นวันปัจจุบัน)
            $insert_datetime = $inspection_date . ' ' . date('H:i:s');
            
            $insertStmt = $conn->prepare("INSERT INTO inspections (student_id, inspector_id, result_status, inspection_date) VALUES (?, ?, ?, ?)");
            $insertStmt->execute([$student_id, $inspector_id, $status, $insert_datetime]);
            $inspection_id = $conn->lastInsertId();
        }

        // 2. บันทึก Violations
        if ($status === 'fail' && !empty($violations)) {
            $vioStmt = $conn->prepare("INSERT INTO inspection_violations (inspection_id, rule_id) VALUES (?, ?)");
            foreach ($violations as $rule_id) {
                $vioStmt->execute([$inspection_id, $rule_id]);
            }
        }

        $conn->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $conn->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No data']);
}
?>