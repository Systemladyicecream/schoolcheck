<?php
require_once 'db.php';
checkLogin();

// ==========================================
// ส่วนที่ 1: ระบบบันทึกข้อมูล (API Endpoint)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] == 'save') {
    header('Content-Type: application/json');
    
    try {
        // รับข้อมูล JSON ที่ส่งมาจาก JavaScript
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);

        if (!$input) {
            throw new Exception("ไม่ได้รับข้อมูล หรือรูปแบบข้อมูลไม่ถูกต้อง");
        }

        $class_id = $input['class_id'];
        $inspection_date = $input['inspection_date'];
        $inspection_topic = isset($input['inspection_topic']) ? trim($input['inspection_topic']) : 'ตรวจระเบียบทั่วไป'; // รับหัวข้อการตรวจ
        $students = $input['students'];
        $inspector_id = $_SESSION['user_id'];

        $conn->beginTransaction();

        // 1. ลบข้อมูลเก่าของวันนี้ ห้องนี้ ออกก่อน (เพื่อป้องกันการซ้ำซ้อนเวลาบันทึกทับ)
        // ลบ Violations เก่า
        $sql_del_vio = "DELETE iv FROM inspection_violations iv 
                        JOIN inspections i ON iv.inspection_id = i.inspection_id 
                        JOIN students s ON i.student_id = s.student_id
                        WHERE s.current_class_id = ? AND DATE(i.inspection_date) = ?";
        $stmt_del_vio = $conn->prepare($sql_del_vio);
        $stmt_del_vio->execute([$class_id, $inspection_date]);

        // ลบ Inspections เก่า
        $sql_del_ins = "DELETE i FROM inspections i 
                        JOIN students s ON i.student_id = s.student_id
                        WHERE s.current_class_id = ? AND DATE(i.inspection_date) = ?";
        $stmt_del_ins = $conn->prepare($sql_del_ins);
        $stmt_del_ins->execute([$class_id, $inspection_date]);

        // 2. เตรียมคำสั่ง SQL สำหรับบันทึกใหม่
        $stmt_ins = $conn->prepare("INSERT INTO inspections (student_id, inspection_date, result_status, note, inspector_id) VALUES (?, ?, ?, ?, ?)");

        // 3. วนลูปบันทึกข้อมูลทีละคน
        foreach ($students as $s) {
            if (empty($s['student_id'])) {
                continue;
            }

            $status = $s['status']; 
            $user_note = isset($s['note']) ? trim($s['note']) : '';
            
            // [ปรับปรุง] รวมหัวข้อการตรวจเข้ากับหมายเหตุ เพื่อให้บันทึกเหตุผลลงไปด้วย
            // รูปแบบ: [หัวข้อ] หมายเหตุเพิ่มเติม
            $final_note = "[" . $inspection_topic . "]";
            if (!empty($user_note)) {
                $final_note .= " " . $user_note;
            }
            
            // Insert ลงตาราง inspections
            $stmt_ins->execute([$s['student_id'], $inspection_date, $status, $final_note, $inspector_id]);
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit(); // จบการทำงานของ PHP ทันทีเมื่อเป็นโหมดบันทึก
}

// ==========================================
// ส่วนที่ 2: หน้าจอแสดงผล (UI)
// ==========================================

// 1. ตรวจสอบสิทธิ์และการเข้าถึง
$class_id = isset($_GET['class_id']) ? $_GET['class_id'] : 0;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); 

// [NEW] กำหนดสถานะ View Only (Admin และ ผู้บริหาร ดูได้แต่แก้ไม่ได้)
$is_view_only = false;
if ($_SESSION['role'] === 'admin' || in_array($_SESSION['role'], ['executive', 'director', 'deputy_director'])) {
    $is_view_only = true;
}

// ตรวจสอบสิทธิ์การเข้าถึงห้องเรียน
if (!$is_view_only) {
    $checkAccess = $conn->prepare("SELECT 1 FROM teacher_class_assignments WHERE user_id = ? AND class_id = ?");
    $checkAccess->execute([$_SESSION['user_id'], $class_id]);
    if (!$checkAccess->fetch()) {
        echo "<script>alert('⛔ คุณไม่มีสิทธิ์เข้าถึงห้องเรียนนี้'); window.location.href='index.php';</script>";
        exit();
    }
}

// 2. ดึงข้อมูลห้องเรียน
$stmtClass = $conn->prepare("SELECT * FROM classes WHERE class_id = ?");
$stmtClass->execute([$class_id]);
$classInfo = $stmtClass->fetch(PDO::FETCH_ASSOC);

if (!$classInfo) {
    die("ไม่พบข้อมูลห้องเรียน (Class ID: $class_id)");
}

// 3. ดึงข้อมูลนักเรียนและผลการตรวจ (ถ้ามี)
$sql = "SELECT s.*, 
        i.result_status, i.inspection_id, i.note
        FROM students s 
        LEFT JOIN inspections i ON s.student_id = i.student_id AND DATE(i.inspection_date) = :idate
        WHERE s.current_class_id = :cid 
        ORDER BY s.student_code ASC";

$stmt = $conn->prepare($sql);
$stmt->execute(['cid' => $class_id, 'idate' => $date]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงหัวข้อการตรวจล่าสุดมาแสดง (ถ้ามี) จากคนแรก
$current_topic = 'ตรวจระเบียบประจำวัน'; // ค่าเริ่มต้น
if (!empty($students[0]['note'])) {
    // พยายามดึงข้อความในวงเล็บ [] ออกมา
    if (preg_match('/^\[(.*?)\]/', $students[0]['note'], $matches)) {
        $current_topic = $matches[1];
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>ตรวจระเบียบ - SchoolCheck</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; -webkit-tap-highlight-color: transparent; }
        .glass-nav { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(12px); 
            border-bottom: 1px solid #e2e8f0;
            padding-top: env(safe-area-inset-top); 
        }
        
        input[type="radio"]:checked + .radio-content { 
            background-color: #f0fdf4; border-color: #22c55e; color: #15803d; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);
        }
        input[type="radio"].fail:checked + .radio-content { 
            background-color: #fef2f2; border-color: #ef4444; color: #b91c1c; box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.2);
        }
        
        textarea, input { font-size: 16px !important; }
        .safe-area-bottom { padding-bottom: env(safe-area-inset-bottom); }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="text-slate-800 pb-32" x-data="inspectionApp()"> 

    <nav class="glass-nav fixed w-full z-40 px-4 py-3 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <?php 
                $backLink = 'index.php';
                if (in_array($_SESSION['role'], ['admin', 'executive', 'director', 'deputy_director'])) {
                    $backLink = 'history.php?class_id='.$class_id.'&date='.$date;
                }
            ?>
            <a href="<?php echo $backLink; ?>" class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-600 active:scale-95 transition shadow-sm">
                <i class='bx bx-chevron-left text-3xl'></i>
            </a>
            <div>
                <h1 class="font-bold text-base text-slate-800 leading-tight">ม.<?php echo $classInfo['level_name'].$classInfo['room_number']; ?></h1>
                <p class="text-xs text-slate-500"><?php echo date('d M Y', strtotime($date)); ?></p>
            </div>
        </div>
        <div class="text-xs font-bold bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-full">
            <span x-text="students.length"></span> คน
        </div>
    </nav>

    <div class="pt-24 px-4 max-w-2xl mx-auto">
        
        <div x-show="students.length === 0" x-cloak class="text-center py-16">
            <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 text-5xl text-slate-300">
                <i class='bx bx-user-x'></i>
            </div>
            <p class="text-slate-400">ไม่พบรายชื่อนักเรียน</p>
        </div>

        <div x-show="students.length > 0">
            
            <?php if(!$is_view_only): ?>
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-indigo-100 mb-4">
                <label class="block text-xs font-bold text-indigo-500 uppercase mb-2 flex items-center gap-1">
                    <i class='bx bxs-edit-alt'></i> หัวข้อ/เหตุผลการตรวจ
                </label>
                <input type="text" x-model="inspectionTopic" 
                       class="w-full bg-indigo-50/50 border border-indigo-200 text-slate-700 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block p-3 font-bold" 
                       placeholder="เช่น ตรวจผมประจำเดือน, ตรวจเล็บ">
            </div>

            <div class="flex justify-end mb-4 sticky top-20 z-30 pointer-events-none">
                <button @click="markAll('pass')" class="pointer-events-auto bg-white/90 backdrop-blur border border-emerald-200 text-emerald-700 px-4 py-2 rounded-full text-xs font-bold shadow-lg active:scale-95 transition flex items-center gap-1">
                    <i class='bx bx-check-double text-lg'></i> ให้ผ่านทุกคน
                </button>
            </div>
            <?php else: ?>
                <div class="bg-indigo-50 p-3 rounded-xl border border-indigo-100 mb-4 text-center">
                    <p class="text-xs text-indigo-400">หัวข้อการตรวจ</p>
                    <p class="font-bold text-indigo-800 text-lg"><?php echo htmlspecialchars($current_topic); ?></p>
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <template x-for="(s, index) in students" :key="s.student_id">
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 relative transition-all duration-200" 
                         :class="{'ring-2 ring-rose-500 ring-offset-2': s.status === 'fail', 'ring-2 ring-emerald-500 ring-offset-1': s.status === 'pass'}">
                        
                        <div class="flex flex-col gap-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold text-sm">
                                        <span x-text="index + 1"></span>
                                    </div>
                                    <div>
                                        <p class="font-bold text-base text-slate-800" x-text="s.prefix + s.first_name + ' ' + s.last_name"></p>
                                        <p class="text-slate-400 font-mono text-xs" x-text="s.student_code"></p>
                                    </div>
                                </div>
                            </div>

                            <?php if(!$is_view_only): ?>
                            <div class="grid grid-cols-2 gap-3 mt-1">
                                <label class="cursor-pointer relative group">
                                    <input type="radio" :name="'status_'+s.student_id" value="pass" x-model="s.status" class="peer sr-only">
                                    <div class="radio-content border-2 border-slate-100 rounded-xl p-3 text-center transition-all duration-200 active:scale-95">
                                        <i class='bx bx-check-circle text-3xl mb-1 block'></i>
                                        <span class="font-bold text-sm">ผ่าน</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer relative group">
                                    <input type="radio" :name="'status_'+s.student_id" value="fail" x-model="s.status" class="peer sr-only fail">
                                    <div class="radio-content border-2 border-slate-100 rounded-xl p-3 text-center transition-all duration-200 active:scale-95">
                                        <i class='bx bx-x-circle text-3xl mb-1 block'></i>
                                        <span class="font-bold text-sm">แก้ไข</span>
                                    </div>
                                </label>
                            </div>

                            <div x-show="s.status === 'fail'" x-collapse class="mt-2">
                                <textarea 
                                    x-model="s.note" 
                                    rows="2"
                                    placeholder="ระบุสาเหตุ (เช่น ผมยาว, เล็บยาว)..." 
                                    class="w-full text-base border-rose-200 rounded-xl focus:ring-rose-500 focus:border-rose-500 bg-rose-50 p-3 placeholder-rose-300 text-slate-700 resize-none"
                                ></textarea>
                            </div>
                            <?php else: ?>
                                <div class="mt-2 text-center py-2 bg-slate-50 rounded-lg text-sm font-bold" 
                                     :class="s.status === 'pass' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50'">
                                    <span x-text="s.status === 'pass' ? 'ผ่านเกณฑ์' : 'ไม่ผ่านเกณฑ์'"></span>
                                    <p x-show="s.status === 'fail' && s.note" class="text-xs font-normal mt-1 text-slate-500" x-text="s.note.replace(/^\[.*?\]\s*/, '')"></p> 
                                    </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div x-show="students.length > 0 && !isViewOnly" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full"
         x-transition:enter-end="translate-y-0"
         class="fixed bottom-0 left-0 w-full bg-white/90 backdrop-blur border-t border-slate-200 z-50 shadow-[0_-5px_15px_rgba(0,0,0,0.05)] safe-area-bottom">
        
        <div class="max-w-2xl mx-auto p-4 flex gap-3 items-center justify-between">
            <div class="flex flex-col text-xs font-medium text-slate-500">
                <span><span x-text="students.filter(s => s.status === 'pass').length" class="text-emerald-600 font-bold text-lg"></span> ผ่าน</span>
                <span><span x-text="students.filter(s => s.status === 'fail').length" class="text-rose-600 font-bold text-lg"></span> แก้ไข</span>
            </div>
            <button @click="openConfirmModal()" class="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3.5 rounded-2xl font-bold shadow-lg shadow-indigo-200 active:scale-95 transition flex justify-center items-center gap-2 text-base">
                <i class='bx bx-save text-xl'></i> บันทึกผล
            </button>
        </div>
    </div>

    <div x-show="showModal" x-cloak class="fixed inset-0 z-[60] overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showModal = false"></div>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md w-full relative">
                <div class="bg-indigo-600 px-4 py-4 sm:px-6">
                    <h3 class="text-lg leading-6 font-bold text-white flex items-center gap-2">
                        <i class='bx bx-check-shield'></i> ยืนยันผลการตรวจ
                    </h3>
                </div>
                <div class="px-6 py-6">
                    <div class="mb-4 text-center">
                        <p class="text-sm text-slate-400">หัวข้อการตรวจ</p>
                        <p class="text-lg font-bold text-indigo-600" x-text="inspectionTopic"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 text-center">
                            <p class="text-xs text-emerald-500 font-bold uppercase">ผ่านเกณฑ์</p>
                            <p class="text-3xl font-bold text-emerald-600" x-text="countPass"></p>
                        </div>
                        <div class="bg-rose-50 border border-rose-100 rounded-xl p-4 text-center">
                            <p class="text-xs text-rose-500 font-bold uppercase">ต้องแก้ไข</p>
                            <p class="text-3xl font-bold text-rose-600" x-text="countFail"></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 bg-slate-50 p-3 rounded-lg border border-slate-200 cursor-pointer" @click="isConfirmed = !isConfirmed">
                        <div class="flex items-center h-5">
                            <input type="checkbox" x-model="isConfirmed" class="focus:ring-indigo-500 h-5 w-5 text-indigo-600 border-gray-300 rounded cursor-pointer pointer-events-none">
                        </div>
                        <div class="text-sm select-none">
                            <label class="font-medium text-slate-700 cursor-pointer pointer-events-none">ข้าพเจ้ายืนยันความถูกต้อง</label>
                            <p class="text-slate-500 text-xs pointer-events-none">ตรวจสอบข้อมูลเรียบร้อยแล้ว</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                    <button type="button" @click="submitData()" :disabled="!isConfirmed" :class="{'opacity-50 cursor-not-allowed': !isConfirmed, 'hover:bg-indigo-700': isConfirmed}" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white focus:outline-none sm:ml-3 sm:w-auto sm:text-sm transition-all">ยืนยันและบันทึก</button>
                    <button type="button" @click="showModal = false" class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">ยกเลิก</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function inspectionApp() {
            return {
                isViewOnly: <?php echo $is_view_only ? 'true' : 'false'; ?>,
                inspectionTopic: '<?php echo htmlspecialchars($current_topic); ?>', // ค่าเริ่มต้น
                
                // รับข้อมูลจาก PHP และแปลงเป็น JSON อย่างปลอดภัย
                students: <?php 
                    $jsStudents = array_map(function($s) {
                        // ตัด Topic [xxx] ออกจาก note เพื่อให้เหลือแค่ข้อความ user ตอนแสดงผลใน textarea
                        $cleanNote = $s['note'] ?? '';
                        $cleanNote = preg_replace('/^\[.*?\]\s*/', '', $cleanNote);
                        
                        return [
                            'student_id' => $s['student_id'],
                            'student_code' => $s['student_code'],
                            'prefix' => $s['prefix'],
                            'first_name' => $s['first_name'],
                            'last_name' => $s['last_name'],
                            'status' => $s['result_status'] ? $s['result_status'] : 'pass',
                            'note' => $cleanNote
                        ];
                    }, $students);
                    echo !empty($jsStudents) ? json_encode($jsStudents, JSON_UNESCAPED_UNICODE) : '[]'; 
                ?>,
                
                showModal: false,
                isConfirmed: false,
                countPass: 0,
                countFail: 0,

                markAll(status) {
                    this.students.forEach(s => {
                        s.status = status;
                        if(status === 'pass') { s.note = ''; }
                    });
                },

                openConfirmModal() {
                    this.countPass = this.students.filter(s => s.status === 'pass').length;
                    this.countFail = this.students.filter(s => s.status === 'fail').length;
                    this.isConfirmed = false;
                    
                    if(this.inspectionTopic.trim() === '') {
                        Swal.fire('กรุณาระบุหัวข้อการตรวจ', '', 'warning');
                        return;
                    }
                    
                    this.showModal = true;
                },

                submitData() {
                    this.showModal = false;
                    
                    Swal.fire({
                        title: 'กำลังบันทึก...',
                        html: 'กรุณารอสักครู่ ห้ามปิดหน้านี้',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading() }
                    });

                    // ส่งข้อมูลกลับมาที่ไฟล์เดิม (inspect.php?action=save)
                    fetch('inspect.php?action=save', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            class_id: <?php echo $class_id; ?>,
                            inspection_date: '<?php echo $date; ?>',
                            inspection_topic: this.inspectionTopic,
                            students: this.students
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'บันทึกสำเร็จ!',
                                text: 'ระบบได้บันทึกข้อมูลเรียบร้อยแล้ว',
                                confirmButtonText: 'ตกลง',
                                confirmButtonColor: '#4f46e5'
                            }).then(() => {
                                <?php if (in_array($_SESSION['role'], ['admin', 'executive', 'director', 'deputy_director'])): ?>
                                    window.location.href = 'history.php?class_id=<?php echo $class_id; ?>&date=<?php echo $date; ?>';
                                <?php else: ?>
                                    window.location.href = 'index.php';
                                <?php endif; ?>
                            });
                        } else {
                            throw new Error(data.message || 'Server Error');
                        }
                    })
                    .catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'เกิดข้อผิดพลาด',
                            text: error.message
                        });
                    });
                }
            }
        }
    </script>
</body>
</html>