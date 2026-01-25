<?php
require_once 'db.php';
checkLogin();

// --- 1. เตรียมข้อมูลพื้นฐาน ---
$today = date('Y-m-d');
$role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;
$full_name = $_SESSION['full_name'] ?? 'User';

// --- 2. SQL Query ---
$sql = "SELECT c.*, ay.year_name, 
        (SELECT COUNT(*) FROM students s WHERE s.current_class_id = c.class_id) as total_std,
        (SELECT COUNT(*) FROM inspections i JOIN students s ON i.student_id = s.student_id WHERE s.current_class_id = c.class_id AND DATE(i.inspection_date) = :today) as checked_count,
        (SELECT COUNT(*) FROM inspections i JOIN students s ON i.student_id = s.student_id WHERE s.current_class_id = c.class_id AND DATE(i.inspection_date) = :today AND i.result_status = 'fail') as fail_count
        FROM classes c 
        JOIN academic_years ay ON c.academic_year_id = ay.year_id 
        WHERE ay.is_active = 1 ";

if ($role !== 'admin') {
    $sql .= " AND c.class_id IN (SELECT class_id FROM teacher_class_assignments WHERE user_id = :uid) ";
}

$sql .= " ORDER BY c.level_name, c.room_number";

$stmt = $conn->prepare($sql);
$params = ['today' => $today];
if ($role !== 'admin') $params['uid'] = $user_id;
$stmt->execute($params);

function getCardStyle($index) {
    $styles = [
        ['bg' => 'from-blue-500 to-cyan-400', 'icon' => 'bx-book-reader', 'shadow' => 'shadow-blue-200'],
        ['bg' => 'from-purple-500 to-pink-500', 'icon' => 'bx-pencil', 'shadow' => 'shadow-purple-200'],
        ['bg' => 'from-orange-400 to-amber-400', 'icon' => 'bx-bulb', 'shadow' => 'shadow-orange-200'],
        ['bg' => 'from-emerald-400 to-teal-500', 'icon' => 'bx-globe', 'shadow' => 'shadow-emerald-200'],
        ['bg' => 'from-indigo-500 to-violet-500', 'icon' => 'bx-atom', 'shadow' => 'shadow-indigo-200'],
    ];
    return $styles[$index % count($styles)];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกห้องเรียน - SchoolCheck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; overflow-x: hidden; }
        .glass-nav { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255, 255, 255, 0.3); }
        .hero-pattern { background-color: #ffffff; background-image: radial-gradient(#6366f1 0.5px, transparent 0.5px), radial-gradient(#6366f1 0.5px, #ffffff 0.5px); background-size: 20px 20px; opacity: 0.1; }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }
        .float-img { animation: float 6s ease-in-out infinite; }
        .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .card-hover:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
    </style>
</head>
<body class="min-h-screen text-slate-800">

    <nav class="glass-nav fixed w-full z-50 px-6 py-3 flex justify-between items-center">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-violet-600 rounded-xl flex items-center justify-center text-white shadow-lg">
                <i class='bx bxs-school text-2xl'></i>
            </div>
            <span class="font-bold text-xl tracking-tight text-slate-800 hidden sm:block">SchoolCheck</span>
        </div>
        
        <div class="flex items-center gap-4">
            <a href="history.php" class="flex items-center gap-2 text-slate-500 hover:text-indigo-600 font-medium transition px-3 py-2 rounded-lg hover:bg-slate-50">
                <i class='bx bx-history text-xl'></i> <span class="hidden md:inline">ประวัติการตรวจ</span>
            </a>

            <?php if($role === 'admin'): ?>
            <a href="admin.php" class="hidden md:flex items-center gap-2 bg-slate-800 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-slate-700 transition shadow-md">
                <i class='bx bxs-dashboard'></i> Admin Panel
            </a>
            <?php endif; ?>

            <div class="flex items-center gap-3 pl-4 border-l border-slate-200">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-slate-700"><?php echo $full_name; ?></p>
                    <p class="text-xs text-slate-400 capitalize"><?php echo $role; ?></p>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=random&rounded=true" class="w-10 h-10 rounded-full border-2 border-white shadow-sm">
                <a href="logout.php" class="text-slate-400 hover:text-red-500 transition ml-2" title="ออกจากระบบ">
                    <i class='bx bx-log-out text-2xl'></i>
                </a>
            </div>
        </div>
    </nav>

    <div class="pt-24 pb-12 px-6 max-w-7xl mx-auto relative">
        <div class="absolute inset-0 hero-pattern z-0 h-[500px] pointer-events-none"></div>

        <div class="relative z-10 bg-gradient-to-r from-indigo-600 to-violet-600 rounded-[2.5rem] p-8 md:p-12 mb-12 shadow-2xl text-white overflow-hidden flex flex-col md:flex-row items-center justify-between">
            <div class="max-w-xl">
                <div class="inline-flex items-center gap-2 bg-white/20 backdrop-blur-md px-3 py-1 rounded-full text-xs font-medium mb-4 border border-white/20">
                    <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                    ระบบพร้อมใช้งาน
                </div>
                <h1 class="text-3xl md:text-5xl font-bold mb-4 leading-tight">สวัสดีคุณครู, <br>พร้อมสำหรับการตรวจเช้าวันนี้ไหม?</h1>
                <p class="text-indigo-100 text-lg mb-8 opacity-90">เลือกห้องเรียนด้านล่างเพื่อเริ่มบันทึกการตรวจระเบียบ ระบบจะบันทึกข้อมูลและสรุปผลให้ทันที</p>
                
                <div class="flex items-center gap-6">
                    <div>
                        <p class="text-3xl font-bold"><?php echo date('d'); ?></p>
                        <p class="text-xs opacity-70 uppercase tracking-wide"><?php echo date('F'); ?></p>
                    </div>
                    <div class="h-8 w-px bg-white/20"></div>
                    <div>
                        <p class="text-3xl font-bold"><?php echo date('Y'); ?></p>
                        <p class="text-xs opacity-70 uppercase tracking-wide">Year</p>
                    </div>
                </div>
            </div>
            
            <div class="hidden md:block w-80 h-80 relative -mr-10 float-img">
                <img src="images/index-cover.png"
                style="border-radius: 20px;" width="300">
                
            </div>
            <div class="absolute top-0 right-0 w-64 h-64 bg-white opacity-5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-purple-500 opacity-20 rounded-full blur-2xl translate-y-1/4 -translate-x-1/4"></div>
        </div>

        <div class="flex items-center justify-between mb-8 relative z-10">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                    <i class='bx bxs-grid-alt text-indigo-500'></i> รายการห้องเรียน
                </h2>
                <p class="text-sm text-slate-500 mt-1">
                    <?php if($role === 'admin'): ?>
                        <span class="text-amber-500 font-medium"><i class='bx bxs-lock-alt'></i> Admin View Mode</span> (ดูได้อย่างเดียว)
                    <?php else: ?>
                        แสดงเฉพาะห้องที่คุณได้รับมอบหมาย
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if($stmt->rowCount() > 0): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 relative z-10">
            <?php 
            $i = 0;
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
                $style = getCardStyle($i++);
                $is_admin_view = ($role === 'admin');
                $total = $row['total_std'];
                $checked = $row['checked_count'];
                $fail = $row['fail_count'];
                $pass = $checked - $fail;
                $percent = ($total > 0) ? ($checked / $total) * 100 : 0;
                $is_complete = ($total > 0 && $checked == $total);
            ?>

            <?php if($is_admin_view): ?>
                <div class="group relative cursor-default opacity-90">
            <?php else: ?>
                <a href="inspect.php?class_id=<?php echo $row['class_id']; ?>" class="group block h-full">
            <?php endif; ?>

                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 h-full flex flex-col justify-between card-hover relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r <?php echo $style['bg']; ?>"></div>
                    <div>
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br <?php echo $style['bg']; ?> flex items-center justify-center text-white text-2xl shadow-lg <?php echo $style['shadow']; ?>">
                                <i class='bx <?php echo $style['icon']; ?>'></i>
                            </div>
                            <div class="bg-slate-50 text-slate-500 px-3 py-1 rounded-lg text-xs font-bold uppercase tracking-wider border border-slate-100">
                                Room <?php echo $row['room_number']; ?>
                            </div>
                        </div>
                        <h3 class="text-2xl font-bold text-slate-800 mb-1">ม.<?php echo $row['level_name'] . $row['room_number']; ?></h3>
                        <p class="text-slate-400 text-sm mb-6">นักเรียนทั้งหมด <?php echo $total; ?> คน</p>
                        <div class="space-y-2">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="<?php echo $is_complete ? 'text-emerald-500' : 'text-slate-500'; ?>">
                                    <?php echo $is_complete ? 'ตรวจครบแล้ว' : 'กำลังดำเนินการ'; ?>
                                </span>
                                <span class="text-slate-700"><?php echo round($percent); ?>%</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-700 ease-out <?php echo $is_complete ? 'bg-emerald-500' : 'bg-gradient-to-r ' . $style['bg']; ?>" 
                                     style="width: <?php echo $percent; ?>%"></div>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <div class="flex-1 bg-emerald-50 rounded-xl py-2 px-3 text-center border border-emerald-100">
                                <p class="text-xs text-emerald-400 font-bold uppercase">ผ่าน</p>
                                <p class="text-lg font-bold text-emerald-600"><?php echo $pass; ?></p>
                            </div>
                            <div class="flex-1 bg-rose-50 rounded-xl py-2 px-3 text-center border border-rose-100">
                                <p class="text-xs text-rose-400 font-bold uppercase">แก้ไข</p>
                                <p class="text-lg font-bold text-rose-600"><?php echo $fail; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-50 flex justify-between items-center">
                        <?php if($is_admin_view): ?>
                            <span class="text-xs font-bold text-slate-400 flex items-center gap-1"><i class='bx bxs-lock'></i> View Only</span>
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400"><i class='bx bx-show'></i></div>
                        <?php else: ?>
                            <span class="text-xs font-bold text-indigo-500 group-hover:underline">เข้าสู่ห้องเรียน</span>
                            <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all shadow-sm"><i class='bx bx-right-arrow-alt text-xl'></i></div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php if($is_admin_view): ?>
                </div>
            <?php else: ?>
                </a>
            <?php endif; ?>
            
            <?php endwhile; ?>
        </div>
        
        <?php else: ?>
            <div class="text-center py-20 bg-white/50 rounded-3xl border border-dashed border-slate-300">
                <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-4xl text-slate-400">
                    <i class='bx bx-ghost'></i>
                </div>
                <h3 class="text-xl font-bold text-slate-600">ไม่พบห้องเรียน</h3>
                <p class="text-slate-400 mt-2">คุณยังไม่ได้รับมอบหมายให้ดูแลห้องเรียนใดๆ <br>กรุณาติดต่อผู้ดูแลระบบ</p>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>