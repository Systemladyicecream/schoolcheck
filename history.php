<?php
require_once 'db.php';
checkLogin();

// 1. เตรียมข้อมูลพื้นฐาน
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// ==========================================
// ส่วนที่ 1: API Endpoints (AJAX Requests)
// ==========================================

// 1.1 ดึงประวัติรายบุคคล (Get Individual History)
if (isset($_GET['action']) && $_GET['action'] == 'get_student_history') {
    ob_clean(); 
    header('Content-Type: application/json');

    try {
        $std_id = $_GET['student_id'];
        
        $sql_hist = "SELECT i.inspection_id, i.inspection_date, i.result_status, i.note, u.full_name as inspector,
                     GROUP_CONCAT(r.rule_name SEPARATOR ', ') as violations
                     FROM inspections i
                     LEFT JOIN inspection_violations iv ON i.inspection_id = iv.inspection_id
                     LEFT JOIN inspection_rules r ON iv.rule_id = r.rule_id
                     LEFT JOIN users u ON i.inspector_id = u.user_id
                     WHERE i.student_id = ?
                     GROUP BY i.inspection_id
                     ORDER BY i.inspection_date DESC";
        
        $stmt = $conn->prepare($sql_hist);
        $stmt->execute([$std_id]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($history);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// 1.2 ลบประวัติรายรายการ (Delete Individual History)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_individual_history') {
    header('Content-Type: application/json');
    
    if ($role !== 'teacher') {
        echo json_encode(['success' => false, 'message' => 'Admin ไม่ได้รับอนุญาตให้แก้ไขข้อมูล']);
        exit;
    }

    try {
        $inspection_id = $_POST['inspection_id'];
        $conn->beginTransaction();
        $conn->prepare("DELETE FROM inspection_violations WHERE inspection_id = ?")->execute([$inspection_id]);
        $conn->prepare("DELETE FROM inspections WHERE inspection_id = ?")->execute([$inspection_id]);
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// 1.3 ล้างข้อมูลทั้งห้อง (Reset Class Inspection)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'reset_inspection') {
    if ($role === 'teacher') {
        $target_class = $_POST['class_id'];
        $target_date = $_POST['date'];

        try {
            $sql_reset = "DELETE i FROM inspections i 
                          JOIN students s ON i.student_id = s.student_id 
                          WHERE s.current_class_id = ? AND DATE(i.inspection_date) = ?";
            $stmt_reset = $conn->prepare($sql_reset);
            $stmt_reset->execute([$target_class, $target_date]);

            echo "<script>alert('✅ ล้างข้อมูลการตรวจเรียบร้อยแล้ว'); window.location.href='history.php?class_id=$target_class&date=$target_date';</script>";
            exit();
        } catch (PDOException $e) {
            echo "<script>alert('❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "');</script>";
        }
    }
}

// ==========================================
// ส่วนที่ 2: การแสดงผล (UI Logic)
// ==========================================

$back_url = 'index.php'; 
if ($role === 'admin') $back_url = 'admin.php'; 
elseif (in_array($role, ['executive', 'director', 'deputy_director'])) $back_url = 'executive.php';

$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filter_class = isset($_GET['class_id']) ? $_GET['class_id'] : '';

// ดึงรายชื่อห้องเรียน
$sql_classes = "SELECT c.* FROM classes c JOIN academic_years ay ON c.academic_year_id = ay.year_id WHERE ay.is_active = 1";
if ($role !== 'admin' && !in_array($role, ['executive', 'director', 'deputy_director'])) {
    $sql_classes .= " AND c.class_id IN (SELECT class_id FROM teacher_class_assignments WHERE user_id = $user_id)";
}
$sql_classes .= " ORDER BY c.level_name, c.room_number";
$classes = $conn->query($sql_classes)->fetchAll(PDO::FETCH_ASSOC);

if (empty($filter_class) && count($classes) > 0) {
    $filter_class = $classes[0]['class_id'];
}

// เตรียมตัวแปรสถิติ
$students = [];
$stats = [
    'total' => 0, 
    'checked' => 0, 
    'pass' => 0, 
    'fail' => 0,
    'pct_pass' => 0,
    'pct_fail' => 0,
    'pct_progress' => 0
];

if ($filter_class) {
    $sql = "SELECT s.*, 
            i.inspection_id, i.result_status, i.inspection_date, i.note,
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
    $stmt->execute(['filter_date' => $filter_date, 'class_id' => $filter_class]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // คำนวณสถิติ
    $stats['total'] = count($students);
    foreach ($students as $s) {
        if ($s['inspection_id']) {
            $stats['checked']++;
            if ($s['result_status'] == 'pass') $stats['pass']++;
            else $stats['fail']++;
        }
    }

    if ($stats['checked'] > 0) {
        $stats['pct_pass'] = round(($stats['pass'] / $stats['checked']) * 100, 1);
        $stats['pct_fail'] = round(($stats['fail'] / $stats['checked']) * 100, 1);
    }
    if ($stats['total'] > 0) {
        $stats['pct_progress'] = round(($stats['checked'] / $stats['total']) * 100, 1);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการตรวจ - SchoolCheck</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; }
        .glass-nav { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-bottom: 1px solid #e2e8f0; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="text-slate-800 min-h-screen pb-10" 
      x-data="{ 
          showHistoryModal: false, 
          historyData: [], 
          currentStudentName: '',
          currentStudentId: null,
          isLoading: false,
          userRole: '<?php echo $role; ?>',
          
          async loadHistory(studentId, name) {
              this.currentStudentName = name;
              this.currentStudentId = studentId;
              this.showHistoryModal = true;
              this.isLoading = true;
              this.historyData = [];

              try {
                  const response = await fetch(`history.php?action=get_student_history&student_id=${studentId}`);
                  const data = await response.json();
                  this.historyData = data;
              } catch (error) {
                  console.error('Error:', error);
                  alert('ไม่สามารถดึงข้อมูลประวัติได้');
              } finally {
                  this.isLoading = false;
              }
          },

          async deleteInspection(inspectionId) {
              if (this.userRole !== 'teacher') return;

              const result = await Swal.fire({
                  title: 'ยืนยันการลบ?',
                  text: 'ข้อมูลการตรวจครั้งนี้จะถูกลบถาวร',
                  icon: 'warning',
                  showCancelButton: true,
                  confirmButtonColor: '#ef4444',
                  cancelButtonColor: '#cbd5e1',
                  confirmButtonText: 'ลบข้อมูล',
                  cancelButtonText: 'ยกเลิก'
              });

              if (result.isConfirmed) {
                  const formData = new FormData();
                  formData.append('action', 'delete_individual_history');
                  formData.append('inspection_id', inspectionId);

                  try {
                      const response = await fetch('history.php', { method: 'POST', body: formData });
                      const data = await response.json();

                      if (data.success) {
                          this.loadHistory(this.currentStudentId, this.currentStudentName);
                          Swal.fire('ลบสำเร็จ', '', 'success').then(() => { location.reload(); });
                      } else {
                          Swal.fire('เกิดข้อผิดพลาด', data.message, 'error');
                      }
                  } catch (error) {
                      Swal.fire('Error', 'Server Connection Error', 'error');
                  }
              }
          },

          formatDate(dateString) {
              if(!dateString) return '-';
              const date = new Date(dateString);
              return date.toLocaleDateString('th-TH', { day: 'numeric', month: 'long', year: 'numeric' });
          }
      }">

    <nav class="glass-nav fixed w-full z-50 px-6 py-3 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <a href="<?php echo $back_url; ?>" class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center text-slate-500 hover:bg-slate-200 transition">
                <i class='bx bx-arrow-back text-2xl'></i>
            </a>
            <span class="font-bold text-xl tracking-tight text-slate-800">ประวัติการตรวจ</span>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <p class="text-sm font-bold"><?php echo $full_name; ?></p>
                <p class="text-xs text-slate-400 capitalize"><?php echo ucfirst($role); ?></p>
            </div>
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=random&rounded=true" class="w-9 h-9 rounded-full">
        </div>
    </nav>

    <div class="pt-24 px-4 max-w-6xl mx-auto">

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100 mb-6">
            <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                <div class="w-full md:w-auto">
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">เลือกวันที่</label>
                    <input type="date" name="date" value="<?php echo $filter_date; ?>" 
                           class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                </div>
                <div class="w-full md:w-64">
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">เลือกห้องเรียน</label>
                    <select name="class_id" class="w-full bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-xl focus:ring-indigo-500 focus:border-indigo-500 block p-2.5">
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php echo ($filter_class == $c['class_id']) ? 'selected' : ''; ?>>
                                ม.<?php echo $c['level_name'] . $c['room_number']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="w-full md:w-auto bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl text-sm px-5 py-2.5 transition flex items-center justify-center gap-2">
                    <i class='bx bx-search-alt'></i> ดูข้อมูล
                </button>
            </form>
        </div>

        <?php if ($filter_class): ?>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            
            <div class="md:col-span-2 grid grid-cols-2 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm relative overflow-hidden">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase">ตรวจแล้ว</p>
                            <div class="flex items-baseline gap-2 mt-1">
                                <h3 class="text-3xl font-bold text-blue-600"><?php echo $stats['checked']; ?></h3>
                                <span class="text-xs text-slate-400">/ <?php echo $stats['total']; ?> คน</span>
                            </div>
                        </div>
                        <div class="p-2 bg-blue-50 rounded-lg text-blue-500"><i class='bx bx-user-check text-2xl'></i></div>
                    </div>
                    <div class="mt-4">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-500">ความคืบหน้า</span>
                            <span class="font-bold text-blue-600"><?php echo $stats['pct_progress']; ?>%</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-1000" style="width: <?php echo $stats['pct_progress']; ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex flex-col justify-center">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                            <span class="text-sm font-bold text-slate-600">ผ่าน</span>
                        </div>
                        <span class="text-lg font-bold text-emerald-600"><?php echo $stats['pass']; ?> <span class="text-xs text-slate-400 font-normal">(<?php echo $stats['pct_pass']; ?>%)</span></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-rose-500"></div>
                            <span class="text-sm font-bold text-slate-600">ไม่ผ่าน</span>
                        </div>
                        <span class="text-lg font-bold text-rose-600"><?php echo $stats['fail']; ?> <span class="text-xs text-slate-400 font-normal">(<?php echo $stats['pct_fail']; ?>%)</span></span>
                    </div>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-center">
                <div class="relative w-32 h-32">
                    <canvas id="summaryChart"></canvas>
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
                        <span class="text-sm font-bold text-slate-400">Total<br><?php echo $stats['checked']; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row justify-between items-center gap-3">
                <h3 class="font-bold text-slate-700 flex items-center gap-2">
                    <i class='bx bxs-file-find text-indigo-500'></i> 
                    ผลการตรวจรายห้อง (<?php echo count($students); ?> คน)
                </h3>
                
                <div class="flex items-center gap-2">
                    <a href="export_excel.php?class_id=<?php echo $filter_class; ?>&date=<?php echo $filter_date; ?>" 
                       target="_blank"
                       class="bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <i class='bx bxs-file-export'></i> Export Excel
                    </a>

                    <?php if ($role === 'teacher' && $stats['checked'] > 0): ?>
                    <form method="POST" onsubmit="return confirm('⚠️ ล้างข้อมูลทั้งหมดของวันนี้?');" style="display:inline;">
                        <input type="hidden" name="action" value="reset_inspection">
                        <input type="hidden" name="class_id" value="<?php echo $filter_class; ?>">
                        <input type="hidden" name="date" value="<?php echo $filter_date; ?>">
                        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white text-xs font-bold px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                            <i class='bx bxs-trash'></i> Reset
                        </button>
                    </form>
                    <?php endif; ?>

                    <?php if ($filter_date != date('Y-m-d') && $role === 'teacher'): ?>
                    <a href="inspect.php?class_id=<?php echo $filter_class; ?>&date=<?php echo $filter_date; ?>" 
                       class="text-xs text-indigo-600 hover:underline flex items-center gap-1 border border-indigo-200 px-3 py-2 rounded-lg bg-white">
                       <i class='bx bx-edit'></i> แก้ไขวันเก่า
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 uppercase text-xs font-bold">
                        <tr>
                            <th class="p-4">รหัส</th>
                            <th class="p-4">ชื่อ-สกุล</th>
                            <th class="p-4 text-center">สถานะ</th>
                            <th class="p-4">รายละเอียด</th>
                            <th class="p-4 text-right">ประวัติ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($students as $s): 
                            $status_class = 'bg-slate-100 text-slate-400';
                            $status_text = 'ยังไม่ตรวจ';
                            $icon = 'bx-minus';

                            if ($s['result_status'] == 'pass') {
                                $status_class = 'bg-emerald-100 text-emerald-700';
                                $status_text = 'ผ่าน';
                                $icon = 'bx-check';
                            } elseif ($s['result_status'] == 'fail') {
                                $status_class = 'bg-rose-100 text-rose-700';
                                $status_text = 'ไม่ผ่าน';
                                $icon = 'bx-x';
                            }
                        ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-4 font-mono text-slate-500"><?php echo $s['student_code']; ?></td>
                            <td class="p-4 font-medium text-slate-700">
                                <?php echo $s['prefix'] . $s['first_name'] . ' ' . $s['last_name']; ?>
                            </td>
                            <td class="p-4 text-center">
                                <span class="px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1 <?php echo $status_class; ?>">
                                    <i class='bx <?php echo $icon; ?> text-sm'></i> <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td class="p-4">
                                <?php if ($s['result_status'] == 'fail'): ?>
                                    <span class="text-rose-600 font-medium"><?php echo $s['violations']; ?></span>
                                    <?php if ($s['note']): ?>
                                        <div class="text-xs text-slate-400 mt-1">Note: <?php echo $s['note']; ?></div>
                                    <?php endif; ?>
                                <?php elseif ($s['result_status'] == 'pass'): ?>
                                    <span class="text-emerald-500 text-xs"><i class='bx bxs-star'></i> เรียบร้อยดีมาก</span>
                                <?php else: ?>
                                    <span class="text-slate-300">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-right">
                                <button @click="loadHistory(<?php echo $s['student_id']; ?>, '<?php echo $s['first_name']; ?>')" 
                                        class="text-indigo-500 hover:bg-indigo-50 px-3 py-1.5 rounded-lg text-xs font-bold transition">
                                    <i class='bx bx-history'></i> ดู
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php else: ?>
            <div class="text-center py-20">
                <div class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400 text-3xl">
                    <i class='bx bx-search'></i>
                </div>
                <h3 class="text-lg font-bold text-slate-600">กรุณาเลือกห้องเรียน</h3>
                <p class="text-slate-400">เลือกห้องเรียนด้านบนเพื่อดูประวัติการตรวจ</p>
            </div>
        <?php endif; ?>

    </div>

    <div x-show="showHistoryModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showHistoryModal = false"></div>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full relative">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">ประวัติการตรวจ</h3>
                        <p class="text-sm text-indigo-600 font-medium" x-text="currentStudentName"></p>
                    </div>
                    <button @click="showHistoryModal = false" class="text-slate-400 hover:text-slate-600"><i class='bx bx-x text-2xl'></i></button>
                </div>
                <div class="px-6 py-4 max-h-[60vh] overflow-y-auto">
                    <div x-show="isLoading" class="text-center py-8"><i class='bx bx-loader-alt bx-spin text-4xl text-indigo-500'></i></div>
                    <div x-show="!isLoading && historyData.length === 0" class="text-center py-8 text-slate-400">ไม่มีประวัติ</div>
                    <div x-show="!isLoading && historyData.length > 0" class="space-y-4">
                        <template x-for="h in historyData" :key="h.inspection_id">
                            <div class="flex gap-4 relative group">
                                <div class="absolute left-[15px] top-8 bottom-[-16px] w-0.5 bg-slate-100 last:hidden"></div>
                                <div class="relative z-10 w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" :class="h.result_status === 'pass' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'">
                                    <i class='bx' :class="h.result_status === 'pass' ? 'bx-check' : 'bx-x'"></i>
                                </div>
                                <div class="flex-1 pb-2">
                                    <div class="flex justify-between items-start">
                                        <p class="font-bold text-slate-700 text-sm" x-text="formatDate(h.inspection_date)"></p>
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full" x-text="h.inspector"></span>
                                            <button x-show="userRole === 'teacher'" @click="deleteInspection(h.inspection_id)" class="text-slate-300 hover:text-red-500"><i class='bx bxs-trash-alt'></i></button>
                                        </div>
                                    </div>
                                    <div class="mt-1 text-sm text-slate-600">
                                        <p x-show="h.result_status === 'pass'" class="text-emerald-600">ผ่านเรียบร้อย</p>
                                        <div x-show="h.result_status === 'fail'">
                                            <p class="text-rose-600 font-medium" x-text="h.violations"></p>
                                            <p x-show="h.note" class="text-xs text-slate-400 mt-1">Note: <span x-text="h.note"></span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'manual.php'; ?>

    <script>
        // สร้างกราฟวงกลม
        const ctx = document.getElementById('summaryChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['ผ่าน', 'ไม่ผ่าน'],
                    datasets: [{
                        data: [<?php echo $stats['pass']; ?>, <?php echo $stats['fail']; ?>],
                        backgroundColor: ['#10b981', '#f43f5e'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    cutout: '75%'
                }
            });
        }
    </script>
</body>
</html>