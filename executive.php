<?php
require_once 'db.php';

// 1. ตรวจสอบสิทธิ์ (Admin และ กลุ่มผู้บริหาร)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'executive', 'director', 'deputy_director'])) {
    header("Location: login.php");
    exit();
}

$today = date('Y-m-d');
$full_name = $_SESSION['full_name'];

// --- DATA QUERY ZONE ---

// 1. [School Overview] สรุปยอดรวมทั้งโรงเรียน
$stats = $conn->query("SELECT 
    (SELECT COUNT(*) FROM students) as total_students,
    (SELECT COUNT(DISTINCT student_id) FROM inspections WHERE DATE(inspection_date) = '$today') as checked_today,
    (SELECT COUNT(DISTINCT student_id) FROM inspections WHERE DATE(inspection_date) = '$today' AND result_status = 'pass') as pass_today,
    (SELECT COUNT(DISTINCT student_id) FROM inspections WHERE DATE(inspection_date) = '$today' AND result_status = 'fail') as fail_today
")->fetch(PDO::FETCH_ASSOC);

// คำนวณเปอร์เซ็นต์ภาพรวม
$percent_checked = ($stats['total_students'] > 0) ? ($stats['checked_today'] / $stats['total_students']) * 100 : 0;
$percent_pass = ($stats['checked_today'] > 0) ? ($stats['pass_today'] / $stats['checked_today']) * 100 : 0;
$percent_fail = ($stats['checked_today'] > 0) ? ($stats['fail_today'] / $stats['checked_today']) * 100 : 0;

// 2. [Grade Level Summary] สรุปรายระดับชั้น (สำหรับกราฟ)
// ดึงข้อมูลแยกตามระดับชั้น (ม.1, ม.2...)
$grade_stats = $conn->query("SELECT c.level_name, 
    COUNT(DISTINCT s.student_id) as total,
    COUNT(DISTINCT CASE WHEN DATE(i.inspection_date) = '$today' THEN i.inspection_id END) as checked,
    COUNT(DISTINCT CASE WHEN DATE(i.inspection_date) = '$today' AND i.result_status = 'pass' THEN i.inspection_id END) as passed,
    COUNT(DISTINCT CASE WHEN DATE(i.inspection_date) = '$today' AND i.result_status = 'fail' THEN i.inspection_id END) as failed
    FROM classes c
    LEFT JOIN students s ON c.class_id = s.current_class_id
    LEFT JOIN inspections i ON s.student_id = i.student_id
    GROUP BY c.level_name
    ORDER BY c.level_name
")->fetchAll(PDO::FETCH_ASSOC);

// เตรียมข้อมูล JSON สำหรับ Chart.js
$chart_labels = [];
$chart_pass = [];
$chart_fail = [];
$chart_unchecked = [];

foreach ($grade_stats as $g) {
    $chart_labels[] = "ม." . $g['level_name'];
    $chart_pass[] = $g['passed'];
    $chart_fail[] = $g['failed'];
    $chart_unchecked[] = $g['total'] - $g['checked'];
}

// 3. [Classroom Summary] สรุปรายห้องเรียน (สำหรับตาราง)
$room_stats = $conn->query("SELECT c.class_id, c.level_name, c.room_number,
    COUNT(DISTINCT s.student_id) as total,
    COUNT(DISTINCT CASE WHEN DATE(i.inspection_date) = '$today' THEN i.inspection_id END) as checked,
    COUNT(DISTINCT CASE WHEN DATE(i.inspection_date) = '$today' AND i.result_status = 'pass' THEN i.inspection_id END) as passed,
    COUNT(DISTINCT CASE WHEN DATE(i.inspection_date) = '$today' AND i.result_status = 'fail' THEN i.inspection_id END) as failed
    FROM classes c
    LEFT JOIN students s ON c.class_id = s.current_class_id
    LEFT JOIN inspections i ON s.student_id = i.student_id
    GROUP BY c.class_id
    ORDER BY c.level_name, c.room_number
")->fetchAll(PDO::FETCH_ASSOC);

// 4. [Individual Summary] รายชื่อนักเรียนที่ถูกตรวจวันนี้
$students_today = $conn->query("SELECT s.student_code, s.prefix, s.first_name, s.last_name, 
    c.level_name, c.room_number, i.result_status, i.note, i.inspection_date,
    (SELECT GROUP_CONCAT(r.rule_name SEPARATOR ', ') FROM inspection_violations iv JOIN inspection_rules r ON iv.rule_id = r.rule_id WHERE iv.inspection_id = i.inspection_id) as violations
    FROM inspections i
    JOIN students s ON i.student_id = s.student_id
    JOIN classes c ON s.current_class_id = c.class_id
    WHERE DATE(i.inspection_date) = '$today'
    ORDER BY c.level_name, c.room_number, s.student_code
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard ผู้บริหาร - SchoolCheck</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="text-slate-800 bg-slate-50 pb-20">

    <nav class="fixed w-full z-50 bg-white/90 backdrop-blur border-b border-slate-200 px-6 py-4 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                <i class='bx bxs-pie-chart-alt-2 text-2xl'></i>
            </div>
            <div>
                <h1 class="font-bold text-lg leading-tight">Executive Dashboard</h1>
                <p class="text-xs text-slate-500">รายงานสรุปผลการตรวจระเบียบ</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right hidden md:block">
                <p class="font-bold text-sm text-slate-700"><?php echo $full_name; ?></p>
                <p class="text-xs text-slate-400"><?php echo date('d F Y'); ?></p>
            </div>
            <a href="logout.php" class="bg-slate-100 text-slate-500 w-10 h-10 flex items-center justify-center rounded-xl hover:bg-red-50 hover:text-red-500 transition">
                <i class='bx bx-power-off text-xl'></i>
            </a>
        </div>
    </nav>

    <div class="pt-24 px-4 max-w-7xl mx-auto space-y-8">

        <div>
            <h2 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class='bx bxs-school text-indigo-500'></i> สรุปภาพรวมทั้งโรงเรียน
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <div class="glass-card rounded-2xl p-5 relative overflow-hidden">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">ตรวจแล้ววันนี้</p>
                            <h3 class="text-3xl font-bold text-blue-600 mt-1"><?php echo number_format($stats['checked_today']); ?></h3>
                            <p class="text-xs text-slate-500 mt-1">จากทั้งหมด <?php echo number_format($stats['total_students']); ?> คน</p>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-xl text-blue-600"><i class='bx bx-scan text-2xl'></i></div>
                    </div>
                    <div class="w-full bg-slate-100 h-1.5 mt-4 rounded-full overflow-hidden">
                        <div class="bg-blue-500 h-full" style="width: <?php echo $percent_checked; ?>%"></div>
                    </div>
                    <p class="text-right text-[10px] text-blue-500 font-bold mt-1"><?php echo number_format($percent_checked, 1); ?>% ความคืบหน้า</p>
                </div>

                <div class="glass-card rounded-2xl p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">ผ่านเกณฑ์</p>
                            <h3 class="text-3xl font-bold text-emerald-600 mt-1"><?php echo number_format($stats['pass_today']); ?></h3>
                            <p class="text-xs text-emerald-600 font-medium mt-1">+<?php echo number_format($percent_pass, 1); ?>% ของที่ตรวจแล้ว</p>
                        </div>
                        <div class="p-3 bg-emerald-50 rounded-xl text-emerald-600"><i class='bx bx-check-shield text-2xl'></i></div>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-5">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">ต้องแก้ไข</p>
                            <h3 class="text-3xl font-bold text-rose-600 mt-1"><?php echo number_format($stats['fail_today']); ?></h3>
                            <p class="text-xs text-rose-600 font-medium mt-1"><?php echo number_format($percent_fail, 1); ?>% ของที่ตรวจแล้ว</p>
                        </div>
                        <div class="p-3 bg-rose-50 rounded-xl text-rose-600"><i class='bx bx-error-circle text-2xl'></i></div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-indigo-600 to-violet-600 rounded-2xl p-5 text-white shadow-lg">
                    <div class="flex flex-col h-full justify-between">
                        <div>
                            <p class="text-indigo-200 text-xs font-bold uppercase tracking-wider">อัตราส่วน ผ่าน : ไม่ผ่าน</p>
                            <div class="flex items-baseline gap-1 mt-2">
                                <span class="text-3xl font-bold"><?php echo $stats['pass_today']; ?></span>
                                <span class="text-lg text-indigo-300">:</span>
                                <span class="text-3xl font-bold text-rose-200"><?php echo $stats['fail_today']; ?></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-2 bg-white/10 rounded-lg p-2 backdrop-blur-sm">
                            <i class='bx bxs-info-circle'></i>
                            <span class="text-xs">ข้อมูล ณ วันที่ <?php echo date('d/m/Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 glass-card rounded-2xl p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <i class='bx bxs-bar-chart-alt-2 text-indigo-500'></i> สรุปผลการตรวจรายระดับชั้น
                </h3>
                <div class="h-80 w-full">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>
            
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-center items-center text-center">
                <div class="w-40 h-40 relative mb-4">
                    <canvas id="doughnutChart"></canvas>
                    <div class="absolute inset-0 flex items-center justify-center flex-col pointer-events-none">
                        <span class="text-3xl font-bold text-slate-700"><?php echo $stats['checked_today']; ?></span>
                        <span class="text-xs text-slate-400">คน</span>
                    </div>
                </div>
                <h4 class="font-bold text-slate-700">สัดส่วนภาพรวม</h4>
                <div class="flex gap-4 mt-4 text-sm">
                    <div class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-emerald-500"></span> ผ่าน</div>
                    <div class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-rose-500"></span> ไม่ผ่าน</div>
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
                <i class='bx bxs-grid-alt text-indigo-500'></i> สรุปรายห้องเรียน
            </h3>
            <div class="glass-card rounded-2xl overflow-hidden border border-slate-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-slate-500 text-xs font-bold uppercase">
                            <tr>
                                <th class="p-4 border-b">ห้องเรียน</th>
                                <th class="p-4 border-b text-center">นักเรียนทั้งหมด</th>
                                <th class="p-4 border-b text-center">ตรวจแล้ว</th>
                                <th class="p-4 border-b text-center text-emerald-600">ผ่าน</th>
                                <th class="p-4 border-b text-center text-rose-600">ไม่ผ่าน</th>
                                <th class="p-4 border-b">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-100">
                            <?php foreach ($room_stats as $room): 
                                $r_total = $room['total'];
                                $r_checked = $room['checked'];
                                $r_pass = $room['passed'];
                                $r_fail = $room['failed'];
                                $r_progress = ($r_total > 0) ? ($r_checked / $r_total) * 100 : 0;
                            ?>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="p-4 font-bold text-slate-700">ม.<?php echo $room['level_name'] . $room['room_number']; ?></td>
                                <td class="p-4 text-center text-slate-500"><?php echo number_format($r_total); ?></td>
                                <td class="p-4 text-center font-bold text-blue-600"><?php echo number_format($r_checked); ?></td>
                                <td class="p-4 text-center font-bold text-emerald-600"><?php echo number_format($r_pass); ?></td>
                                <td class="p-4 text-center font-bold text-rose-600"><?php echo number_format($r_fail); ?></td>
                                <td class="p-4 w-48">
                                    <div class="flex items-center gap-2">
                                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                            <div class="bg-indigo-500 h-full rounded-full" style="width: <?php echo $r_progress; ?>%"></div>
                                        </div>
                                        <span class="text-xs text-slate-400 w-8 text-right"><?php echo round($r_progress); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div x-data="{ search: '', filter: 'all' }">
            <div class="flex flex-col md:flex-row justify-between items-end md:items-center mb-4 gap-4">
                <h3 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <i class='bx bxs-user-detail text-indigo-500'></i> รายชื่อนักเรียนที่ตรวจวันนี้
                </h3>
                
                <div class="flex gap-2">
                    <select x-model="filter" class="bg-white border border-slate-300 text-slate-700 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                        <option value="all">ทั้งหมด</option>
                        <option value="pass">ผ่าน</option>
                        <option value="fail">ไม่ผ่าน</option>
                    </select>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                            <i class='bx bx-search text-slate-400'></i>
                        </div>
                        <input type="text" x-model="search" class="bg-white border border-slate-300 text-slate-700 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 p-2.5" placeholder="ค้นหาชื่อ...">
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-2xl overflow-hidden border border-slate-200">
                <div class="overflow-x-auto max-h-[500px]">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-slate-50 text-slate-500 font-bold uppercase sticky top-0 z-10 shadow-sm">
                            <tr>
                                <th class="p-4">เวลา</th>
                                <th class="p-4">รหัส</th>
                                <th class="p-4">ชื่อ-สกุล</th>
                                <th class="p-4">ชั้น</th>
                                <th class="p-4 text-center">สถานะ</th>
                                <th class="p-4">รายละเอียด / หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($students_today as $s): ?>
                            <tr class="hover:bg-slate-50 transition" 
                                x-show="(filter === 'all' || filter === '<?php echo $s['result_status']; ?>') && 
                                        ('<?php echo $s['first_name'].$s['last_name'].$s['student_code']; ?>'.toLowerCase().includes(search.toLowerCase()))">
                                <td class="p-4 text-slate-400 font-mono text-xs"><?php echo date('H:i', strtotime($s['inspection_date'])); ?></td>
                                <td class="p-4 font-mono text-slate-500"><?php echo $s['student_code']; ?></td>
                                <td class="p-4 font-bold text-slate-700"><?php echo $s['prefix'] . $s['first_name'] . ' ' . $s['last_name']; ?></td>
                                <td class="p-4"><span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-xs">ม.<?php echo $s['level_name'] . $s['room_number']; ?></span></td>
                                <td class="p-4 text-center">
                                    <?php if ($s['result_status'] == 'pass'): ?>
                                        <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1">
                                            <i class='bx bx-check'></i> ผ่าน
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-rose-100 text-rose-700 px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1">
                                            <i class='bx bx-x'></i> ไม่ผ่าน
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-slate-600">
                                    <?php if ($s['result_status'] == 'fail'): ?>
                                        <span class="text-rose-600 font-medium"><?php echo $s['violations']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($s['note']): ?>
                                        <span class="text-slate-400 text-xs ml-2 border-l pl-2 border-slate-300 italic"><?php echo $s['note']; ?></span>
                                    <?php endif; ?>
                                    <?php if($s['result_status'] == 'pass' && !$s['note']) echo '<span class="text-emerald-400 text-xs"><i class="bx bxs-star"></i> เรียบร้อยดี</span>'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (count($students_today) == 0): ?>
                                <tr><td colspan="6" class="p-8 text-center text-slate-400">ยังไม่มีข้อมูลการตรวจในวันนี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Data from PHP
        const labels = <?php echo json_encode($chart_labels); ?>;
        const dataPass = <?php echo json_encode($chart_pass); ?>;
        const dataFail = <?php echo json_encode($chart_fail); ?>;
        const dataUnchecked = <?php echo json_encode($chart_unchecked); ?>;

        // 1. Stacked Bar Chart (Grade Level)
        const ctxGrade = document.getElementById('gradeChart').getContext('2d');
        new Chart(ctxGrade, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'ผ่านเกณฑ์',
                        data: dataPass,
                        backgroundColor: '#10b981', // Emerald 500
                        borderRadius: 4,
                    },
                    {
                        label: 'ไม่ผ่าน',
                        data: dataFail,
                        backgroundColor: '#f43f5e', // Rose 500
                        borderRadius: 4,
                    },
                    {
                        label: 'ยังไม่ตรวจ',
                        data: dataUnchecked,
                        backgroundColor: '#e2e8f0', // Slate 200
                        borderRadius: 4,
                        hidden: true // ซ่อนไว้ก่อน เพื่อให้เน้นผลตรวจ
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, grid: { borderDash: [2, 4] } }
                }
            }
        });

        // 2. Doughnut Chart (Overview)
        const ctxDoughnut = document.getElementById('doughnutChart').getContext('2d');
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: ['ผ่าน', 'ไม่ผ่าน'],
                datasets: [{
                    data: [<?php echo $stats['pass_today']; ?>, <?php echo $stats['fail_today']; ?>],
                    backgroundColor: ['#10b981', '#f43f5e'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { display: false } }
            }
        });
    </script>
</body>
</html>