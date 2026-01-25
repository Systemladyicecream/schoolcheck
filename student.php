<?php
require_once 'db.php';

$student = null;
$today_result = null;
$violations = [];
$history = [];
$stats = ['total' => 0, 'passed' => 0, 'rate' => 0];
$message = "";

// เมื่อมีการค้นหา
if (isset($_GET['code'])) {
    $code = trim($_GET['code']);
    
    // 1. ค้นหานักเรียน
    $stmt = $conn->prepare("SELECT s.*, c.level_name, c.room_number 
                           FROM students s 
                           LEFT JOIN classes c ON s.current_class_id = c.class_id 
                           WHERE s.student_code = ?");
    $stmt->execute([$code]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        $student_id = $student['student_id'];

        // 2. ดูผลตรวจ "วันนี้"
        $today = date('Y-m-d');
        $stmt2 = $conn->prepare("SELECT * FROM inspections WHERE student_id = ? AND DATE(inspection_date) = ?");
        $stmt2->execute([$student_id, $today]);
        $today_result = $stmt2->fetch(PDO::FETCH_ASSOC);

        // 3. ถ้าไม่ผ่าน ให้ดึงข้อหา (Violations)
        if ($today_result && $today_result['result_status'] == 'fail') {
            $stmt3 = $conn->prepare("SELECT r.rule_name, r.score_deduction 
                                    FROM inspection_violations iv 
                                    JOIN inspection_rules r ON iv.rule_id = r.rule_id 
                                    WHERE iv.inspection_id = ?");
            $stmt3->execute([$today_result['inspection_id']]);
            $violations = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        }

        // 4. ดึงประวัติย้อนหลัง 5 รายการล่าสุด
        $stmt4 = $conn->prepare("SELECT * FROM inspections WHERE student_id = ? AND DATE(inspection_date) != ? ORDER BY inspection_date DESC LIMIT 5");
        $stmt4->execute([$student_id, $today]);
        $history = $stmt4->fetchAll(PDO::FETCH_ASSOC);

        // 5. [NEW] คำนวณสถิติภาพรวม
        $stmt5 = $conn->prepare("SELECT 
                                    COUNT(*) as total,
                                    SUM(CASE WHEN result_status = 'pass' THEN 1 ELSE 0 END) as passed
                                 FROM inspections WHERE student_id = ?");
        $stmt5->execute([$student_id]);
        $data_stats = $stmt5->fetch(PDO::FETCH_ASSOC);
        
        $stats['total'] = $data_stats['total'];
        $stats['passed'] = $data_stats['passed'];
        $stats['rate'] = ($stats['total'] > 0) ? round(($stats['passed'] / $stats['total']) * 100) : 0;

    } else {
        $message = "ไม่พบข้อมูลนักเรียนรหัสนี้";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>ผลการตรวจระเบียบ - SchoolCheck</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <style>
        body { font-family: 'Prompt', sans-serif; -webkit-tap-highlight-color: transparent; }
        
        /* Background แบบเดียวกับหน้า Login */
        .bg-mesh {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1;
            background: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                        radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                        radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            background-color: #f3f4f6;
            background-size: 200% 200%;
            animation: gradient-animation 15s ease infinite;
        }

        @keyframes gradient-animation {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
        }

        /* Animations */
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-5px); } 100% { transform: translateY(0px); } }
        .float-anim { animation: float 4s ease-in-out infinite; }
        
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .slide-up { animation: slideUp 0.5s ease-out forwards; }

        input { font-size: 16px !important; } /* Prevent Zoom on iOS */
    </style>
</head>
<body class="min-h-screen flex flex-col items-center py-6 px-4">
    <div class="bg-mesh"></div>

    <div class="mb-6 text-center float-anim">
        <div class="w-14 h-14 mx-auto bg-gradient-to-tr from-indigo-600 to-purple-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-500/30 mb-3">
            <i class='bx bxs-school text-3xl'></i>
        </div>
        <h1 class="text-xl font-bold text-gray-800">SchoolCheck <span class="text-indigo-600">Student</span></h1>
    </div>

    <?php if (!$student): ?>
        <div class="w-full max-w-md glass-card rounded-3xl p-8 text-center slide-up">
            <h2 class="text-xl font-bold mb-2 text-gray-800">ตรวจสอบสถานะ</h2>
            <p class="text-gray-500 text-sm mb-6">กรอกรหัสประจำตัวนักเรียนเพื่อดูผลการตรวจ</p>
            
            <form method="GET" class="space-y-4">
                <div class="relative group">
                    <input type="number" name="code" placeholder="เช่น 67001" 
                           class="w-full bg-white border border-gray-200 text-center text-2xl font-bold tracking-widest py-4 rounded-2xl focus:outline-none focus:ring-4 focus:ring-indigo-100 focus:border-indigo-500 transition-all placeholder-gray-300 shadow-inner" 
                           required autofocus autocomplete="off">
                </div>
                
                <?php if($message): ?>
                    <div class="flex items-center gap-2 justify-center text-red-500 text-sm bg-red-50 py-2 rounded-xl border border-red-100">
                        <i class='bx bxs-error-circle'></i> <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <button class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold py-4 rounded-2xl shadow-lg hover:shadow-indigo-500/30 transition-all transform hover:-translate-y-1 active:translate-y-0 flex items-center justify-center gap-2">
                    <i class='bx bx-search-alt text-xl'></i> ตรวจสอบข้อมูล
                </button>
            </form>
            
            <div class="mt-8 pt-6 border-t border-gray-100/50">
                <a href="login.php" class="text-sm text-indigo-500 font-medium hover:text-indigo-700 transition">
                    สำหรับคุณครูเข้าสู่ระบบ →
                </a>
            </div>
        </div>

    <?php else: ?>

    <div class="w-full max-w-md space-y-5 slide-up pb-10">
        
        <div class="glass-card rounded-3xl p-5 flex items-center gap-5 relative overflow-hidden">
            <a href="student.php" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 bg-white/50 p-2 rounded-full transition z-10">
                <i class='bx bx-x text-xl'></i>
            </a>
            
            <div class="w-16 h-16 bg-gradient-to-br from-indigo-100 to-purple-100 rounded-full flex items-center justify-center text-3xl shadow-inner border-2 border-white relative z-0">
                <span class="relative bottom-0.5">🎓</span>
            </div>
            
            <div>
                <p class="text-xs text-gray-500 font-bold uppercase tracking-wide mb-0.5">Student Profile</p>
                <h2 class="text-lg font-bold text-gray-800 leading-tight">
                    <?php echo $student['prefix'] . $student['first_name'] . ' ' . $student['last_name']; ?>
                </h2>
                <div class="flex items-center gap-2 mt-1">
                    <span class="bg-indigo-100 text-indigo-700 text-xs px-2 py-0.5 rounded-md font-bold">
                        ม.<?php echo $student['level_name'] . $student['room_number']; ?>
                    </span>
                    <span class="text-gray-400 text-xs font-mono tracking-wide">#<?php echo $student['student_code']; ?></span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="glass-card rounded-2xl p-4 flex flex-col justify-center items-center text-center">
                <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-emerald-400 to-green-500 flex items-center justify-center text-white mb-2 shadow-lg shadow-emerald-200">
                    <i class='bx bxs-pie-chart-alt-2 text-2xl'></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo $stats['rate']; ?>%</h3>
                <p class="text-xs text-gray-500">อัตราการผ่านเกณฑ์</p>
            </div>
            
            <div class="glass-card rounded-2xl p-4 flex flex-col justify-center items-center text-center">
                <div class="w-12 h-12 rounded-full bg-gradient-to-tr from-blue-400 to-indigo-500 flex items-center justify-center text-white mb-2 shadow-lg shadow-indigo-200">
                    <i class='bx bxs-calendar-check text-2xl'></i>
                </div>
                <div class="flex items-baseline gap-1">
                    <h3 class="text-2xl font-bold text-gray-800"><?php echo $stats['passed']; ?></h3>
                    <span class="text-xs text-gray-400">/ <?php echo $stats['total']; ?></span>
                </div>
                <p class="text-xs text-gray-500">ครั้งที่ผ่าน</p>
            </div>
        </div>

        <div class="glass-card rounded-3xl p-6 text-center relative overflow-hidden border-2 <?php echo !$today_result ? 'border-gray-100' : ($today_result['result_status']=='pass' ? 'border-emerald-100' : 'border-rose-100'); ?>">
            
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-bold text-gray-500 flex items-center gap-2">
                    <i class='bx bx-calendar'></i> วันนี้ (<?php echo date('d/m/Y'); ?>)
                </h3>
                <?php if($today_result): ?>
                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded-lg">
                        <i class='bx bx-time-five'></i> <?php echo date('H:i', strtotime($today_result['inspection_date'])); ?> น.
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (!$today_result): ?>
                <div class="py-6">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
                        <i class='bx bx-loader-alt text-4xl text-gray-400'></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-400">รอการตรวจสอบ</h2>
                    <p class="text-gray-400 text-sm mt-2">คุณครูยังไม่ได้บันทึกข้อมูลของวันนี้</p>
                </div>

            <?php elseif ($today_result['result_status'] == 'pass'): ?>
                <div class="py-4">
                    <div class="w-24 h-24 bg-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-emerald-100">
                        <i class='bx bxs-check-shield text-6xl text-emerald-500'></i>
                    </div>
                    <h2 class="text-3xl font-bold text-emerald-600 mb-1">ผ่านระเบียบ</h2>
                    <p class="text-emerald-600/70 text-sm">เครื่องแต่งกายเรียบร้อยดีมาก 👍</p>
                    <?php if(!empty($today_result['note'])): ?>
                        <div class="mt-4 text-xs text-emerald-600 bg-emerald-50 p-2 rounded-lg inline-block">
                            "<?php echo htmlspecialchars($today_result['note']); ?>"
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <div class="py-2">
                    <div class="w-24 h-24 bg-rose-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg shadow-rose-100 animate-bounce">
                        <i class='bx bxs-x-circle text-6xl text-rose-500'></i>
                    </div>
                    <h2 class="text-3xl font-bold text-rose-500 mb-4">ต้องแก้ไข</h2>
                    
                    <div class="bg-rose-50 rounded-2xl p-4 text-left border border-rose-100">
                        <p class="text-xs font-bold text-rose-400 uppercase mb-2 flex items-center gap-1">
                            <i class='bx bxs-error'></i> รายการที่พบ:
                        </p>
                        <ul class="space-y-2">
                            <?php foreach($violations as $v): ?>
                            <li class="flex items-start gap-2 text-rose-700 font-medium text-sm">
                                <i class='bx bxs-circle text-[8px] mt-1.5 flex-shrink-0'></i>
                                <span><?php echo $v['rule_name']; ?> <span class="text-rose-400 text-xs">(-<?php echo $v['score_deduction']; ?>)</span></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if(!empty($today_result['note'])): ?>
                            <div class="mt-3 pt-3 border-t border-rose-200 text-xs text-rose-600 italic">
                                หมายเหตุ: <?php echo htmlspecialchars($today_result['note']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if(count($history) > 0): ?>
        <div class="glass-card rounded-3xl p-5">
            <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2 text-sm">
                <i class='bx bx-history text-indigo-500 text-lg'></i> ประวัติการตรวจล่าสุด
            </h3>
            <div class="space-y-3">
                <?php foreach($history as $h): ?>
                <div class="flex items-center justify-between p-3 bg-white/60 rounded-xl border border-white/50 hover:bg-white transition-colors">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center <?php echo $h['result_status']=='pass'?'bg-emerald-100 text-emerald-600':'bg-rose-100 text-rose-600'; ?>">
                            <i class='bx <?php echo $h['result_status']=='pass'?'bx-check':'bx-x'; ?>'></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-700"><?php echo date('d/m/Y', strtotime($h['inspection_date'])); ?></p>
                            <?php if($h['result_status']=='fail'): ?>
                                <p class="text-[10px] text-rose-500">มีรายการแก้ไข</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="<?php echo $h['result_status']=='pass'?'text-emerald-600 bg-emerald-50':'text-rose-600 bg-rose-50'; ?> text-xs px-3 py-1 rounded-lg font-bold">
                        <?php echo $h['result_status']=='pass'?'ผ่าน':'ไม่ผ่าน'; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

</body>
</html>