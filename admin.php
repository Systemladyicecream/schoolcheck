<?php
require_once 'db.php';

// 1. ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = "";
$msg_type = ""; 

// --- ฟังก์ชันตรวจสอบความปลอดภัยรหัสผ่าน (Password Policy) ---
function isStrongPassword($password) {
    // 1. ยาวอย่างน้อย 8 ตัว
    if (strlen($password) < 8) return false;
    // 2. มีตัวพิมพ์ใหญ่
    if (!preg_match('/[A-Z]/', $password)) return false;
    // 3. มีตัวพิมพ์เล็ก
    if (!preg_match('/[a-z]/', $password)) return false;
    // 4. มีตัวเลข
    if (!preg_match('/[0-9]/', $password)) return false;
    
    return true;
}

// 2. Logic การจัดการข้อมูล
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        $conn->beginTransaction();

        // --- User Actions ---
        if ($_POST['action'] == 'add_user' || $_POST['action'] == 'edit_user') {
            $username = trim($_POST['username']);
            $fullname = trim($_POST['fullname']);
            $role = $_POST['role'];
            $status = $_POST['status'] ?? 'active';
            
            if ($_POST['action'] == 'add_user') {
                $password = $_POST['password']; 
                
                // [SECURITY] ตรวจสอบความปลอดภัยรหัสผ่าน
                if (!isStrongPassword($password)) {
                    throw new Exception("รหัสผ่านไม่ปลอดภัย! ต้องยาวอย่างน้อย 8 ตัว, มีตัวพิมพ์ใหญ่, ตัวพิมพ์เล็ก และตัวเลข");
                }

                // [SECURITY] เข้ารหัสรหัสผ่านก่อนบันทึก
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO users (username, password_hash, full_name, role, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $password_hash, $fullname, $role, $status]);
                $user_id = $conn->lastInsertId();
                $message = "เพิ่มผู้ใช้งานสำเร็จ";

            } else {
                $user_id = $_POST['user_id'];
                
                // กรณีมีการเปลี่ยนรหัสผ่าน
                if (!empty($_POST['password'])) {
                    $password = $_POST['password'];

                    // [SECURITY] ตรวจสอบความปลอดภัยรหัสผ่านใหม่
                    if (!isStrongPassword($password)) {
                        throw new Exception("รหัสผ่านใหม่ไม่ปลอดภัย! ต้องยาวอย่างน้อย 8 ตัว, มีตัวพิมพ์ใหญ่, ตัวพิมพ์เล็ก และตัวเลข");
                    }

                    // [SECURITY] เข้ารหัสรหัสผ่านใหม่
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $conn->prepare("UPDATE users SET username=?, password_hash=?, full_name=?, role=?, status=? WHERE user_id=?");
                    $stmt->execute([$username, $password_hash, $fullname, $role, $status, $user_id]);
                } else {
                    // กรณีไม่เปลี่ยนรหัสผ่าน
                    $stmt = $conn->prepare("UPDATE users SET username=?, full_name=?, role=?, status=? WHERE user_id=?");
                    $stmt->execute([$username, $fullname, $role, $status, $user_id]);
                }
                $message = "แก้ไขข้อมูลสำเร็จ";
            }

            // จัดการ Class Assignment
            $conn->prepare("DELETE FROM teacher_class_assignments WHERE user_id = ?")->execute([$user_id]);
            if (isset($_POST['class_ids']) && is_array($_POST['class_ids'])) {
                $ins = $conn->prepare("INSERT INTO teacher_class_assignments (user_id, class_id) VALUES (?, ?)");
                foreach ($_POST['class_ids'] as $cid) $ins->execute([$user_id, $cid]);
            }
            $msg_type = "success";

        } elseif ($_POST['action'] == 'delete_user') {
            if ($_POST['id'] == $_SESSION['user_id']) throw new Exception("ไม่สามารถลบบัญชีตัวเองได้");
            $conn->prepare("DELETE FROM users WHERE user_id = ?")->execute([$_POST['id']]);
            $message = "ลบผู้ใช้งานแล้ว"; $msg_type = "success";
        }

        // --- Class Actions ---
        elseif ($_POST['action'] == 'add_class' || $_POST['action'] == 'edit_class') {
            if ($_POST['action'] == 'add_class') {
                // ดึงปีการศึกษาที่ Active อยู่
                $ay = $conn->query("SELECT year_id FROM academic_years WHERE is_active = 1 LIMIT 1")->fetchColumn() ?: 1;
                $conn->prepare("INSERT INTO classes (level_name, room_number, academic_year_id) VALUES (?, ?, ?)")
                     ->execute([$_POST['level'], $_POST['room'], $ay]);
                $message = "เพิ่มห้องเรียนสำเร็จ";
            } else {
                $conn->prepare("UPDATE classes SET level_name=?, room_number=? WHERE class_id=?")
                     ->execute([$_POST['level'], $_POST['room'], $_POST['class_id']]);
                $message = "แก้ไขห้องเรียนสำเร็จ";
            }
            $msg_type = "success";
        } elseif ($_POST['action'] == 'delete_class') {
            $check = $conn->prepare("SELECT COUNT(*) FROM students WHERE current_class_id=?");
            $check->execute([$_POST['id']]);
            if ($check->fetchColumn() > 0) throw new Exception("ลบไม่ได้: มีนักเรียนอยู่ในห้องนี้");
            $conn->prepare("DELETE FROM classes WHERE class_id=?")->execute([$_POST['id']]);
            $message = "ลบห้องเรียนแล้ว"; $msg_type = "success";
        }

        // --- CSV Import Action ---
        elseif ($_POST['action'] == 'import_students') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, "r");
                
                $target_class_id = $_POST['target_class_id']; 

                $success = 0;
                $skipped = 0;
                $row = 1;

                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM students WHERE student_code = ?");
                $insertStmt = $conn->prepare("INSERT INTO students (student_code, prefix, first_name, last_name, current_class_id) VALUES (?, ?, ?, ?, ?)");

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if ($row == 1 && !is_numeric($data[0])) { $row++; continue; } 
                    if (empty($data[0])) continue;

                    $code = trim($data[0] ?? '');
                    $prefix = trim($data[1] ?? '');
                    $fname = trim($data[2] ?? '');
                    $lname = trim($data[3] ?? '');

                    if (!$code || !$fname) continue;

                    $checkStmt->execute([$code]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $skipped++; continue;
                    }

                    $insertStmt->execute([$code, $prefix, $fname, $lname, $target_class_id]);
                    $success++;
                }
                fclose($handle);
                $message = "นำเข้าสำเร็จ $success ราย (ข้าม/ซ้ำ $skipped ราย)";
                $msg_type = ($success > 0) ? "success" : "error";
            } else {
                throw new Exception("กรุณาเลือกไฟล์ CSV");
            }
        }

        // --- Student Actions ---
        elseif ($_POST['action'] == 'add_student' || $_POST['action'] == 'edit_student') {
             if ($_POST['action'] == 'add_student') {
                $conn->prepare("INSERT INTO students (student_code, prefix, first_name, last_name, current_class_id) VALUES (?, ?, ?, ?, ?)")
                     ->execute([$_POST['code'], $_POST['prefix'], $_POST['fname'], $_POST['lname'], $_POST['class_id']]);
                $message = "เพิ่มนักเรียนสำเร็จ";
             } else {
                $conn->prepare("UPDATE students SET student_code=?, prefix=?, first_name=?, last_name=?, current_class_id=? WHERE student_id=?")
                     ->execute([$_POST['code'], $_POST['prefix'], $_POST['fname'], $_POST['lname'], $_POST['class_id'], $_POST['student_id']]);
                $message = "แก้ไขนักเรียนสำเร็จ";
             }
             $msg_type = "success";
        } elseif ($_POST['action'] == 'delete_student') {
            $conn->prepare("DELETE FROM inspections WHERE student_id=?")->execute([$_POST['id']]);
            $conn->prepare("DELETE FROM students WHERE student_id=?")->execute([$_POST['id']]);
            $message = "ลบนักเรียนแล้ว"; $msg_type = "success";
        }

        // --- Academic Year Actions (ระบบปีการศึกษา) ---
        elseif ($_POST['action'] == 'add_year' || $_POST['action'] == 'edit_year') {
            $year_name = $_POST['year_name'];
            $term = $_POST['term'];
            $inspection_count = isset($_POST['inspection_count']) ? intval($_POST['inspection_count']) : 20; 
            
            if ($_POST['action'] == 'add_year') {
                $check = $conn->prepare("SELECT COUNT(*) FROM academic_years WHERE year_name = ? AND term = ?");
                $check->execute([$year_name, $term]);
                if ($check->fetchColumn() > 0) throw new Exception("ปีการศึกษานี้มีอยู่ในระบบแล้ว");

                $stmt = $conn->prepare("INSERT INTO academic_years (year_name, term, inspection_count, is_active) VALUES (?, ?, ?, 0)");
                $stmt->execute([$year_name, $term, $inspection_count]);
                $message = "เพิ่มปีการศึกษา $year_name/$term สำเร็จ";

            } else {
                $year_id = $_POST['year_id'];
                $check = $conn->prepare("SELECT COUNT(*) FROM academic_years WHERE year_name = ? AND term = ? AND year_id != ?");
                $check->execute([$year_name, $term, $year_id]);
                if ($check->fetchColumn() > 0) throw new Exception("ปีการศึกษานี้มีอยู่ในระบบแล้ว");

                $stmt = $conn->prepare("UPDATE academic_years SET year_name = ?, term = ?, inspection_count = ? WHERE year_id = ?");
                $stmt->execute([$year_name, $term, $inspection_count, $year_id]);
                $message = "แก้ไขปีการศึกษาสำเร็จ";
            }
            $msg_type = "success";

        } elseif ($_POST['action'] == 'set_active_year') {
            $year_id = $_POST['year_id'];
            $conn->query("UPDATE academic_years SET is_active = 0");
            $conn->prepare("UPDATE academic_years SET is_active = 1 WHERE year_id = ?")->execute([$year_id]);
            $message = "ตั้งค่าปีการศึกษาปัจจุบันเรียบร้อยแล้ว";
            $msg_type = "success";

        } elseif ($_POST['action'] == 'delete_year') {
            $check = $conn->prepare("SELECT is_active FROM academic_years WHERE year_id = ?");
            $check->execute([$_POST['id']]);
            if ($check->fetchColumn() == 1) throw new Exception("ไม่สามารถลบปีการศึกษาที่กำลังใช้งานอยู่ได้");

            $conn->prepare("DELETE FROM academic_years WHERE year_id = ?")->execute([$_POST['id']]);
            $message = "ลบปีการศึกษาแล้ว";
            $msg_type = "success";
        }

        // *** ส่วนจัดการ Rules ถูกลบออกแล้ว ***

        $conn->commit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $message = "เกิดข้อผิดพลาด: " . $e->getMessage();
        $msg_type = "error";
    }
}

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$allClasses = $conn->query("SELECT * FROM classes ORDER BY level_name, room_number")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Admin Panel - SchoolCheck</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f1f5f9; overflow-x: hidden; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .glass-sidebar { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.5); }
        .nav-item.active { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3); }
        .hover-card { transition: all 0.3s ease; }
        .hover-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1); }
        [x-cloak] { display: none !important; }
        .label { display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase; }
        .input-field { width: 100%; padding: 0.75rem 1rem; border-radius: 0.75rem; border: 1px solid #cbd5e1; outline: none; transition: all 0.2s; }
        .input-field:focus { border-color: #6366f1; ring: 2px solid #e0e7ff; }
        .btn-primary { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 0.75rem; border-radius: 0.75rem; font-weight: 600; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); transition: transform 0.1s; }
        .btn-primary:active { transform: scale(0.98); }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body x-data="{ showEditModal: false, editData: {}, editType: '', assignedClasses: [] }" class="flex h-screen text-slate-700">

    <aside class="w-64 glass-sidebar h-full hidden md:flex flex-col fixed z-20 transition-all duration-300">
        <div class="h-20 flex items-center px-8 border-b border-gray-100">
            <div class="w-10 h-10 bg-gradient-to-tr from-indigo-500 to-purple-500 rounded-xl flex items-center justify-center text-white mr-3 shadow-lg">
                <i class='bx bxs-school text-2xl'></i>
            </div>
            <div>
                <h1 class="font-bold text-lg text-slate-800 tracking-tight">SchoolCheck</h1>
                <p class="text-xs text-slate-400">Admin Panel</p>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
            <?php 
            $menu = [
                'dashboard'  => ['icon'=>'bxs-home', 'label'=>'ภาพรวมระบบ'],
                'users'      => ['icon'=>'bxs-user-account', 'label'=>'จัดการผู้ใช้งาน'],
                'classes'    => ['icon'=>'bxs-school', 'label'=>'จัดการห้องเรียน'],
                'years'      => ['icon'=>'bxs-calendar', 'label'=>'ปีการศึกษา'], // Moved here
                'login_logs' => ['icon'=>'bx-time-five', 'label'=>'ประวัติการเข้าใช้งาน']
                // 'rules' has been removed
            ];
            foreach($menu as $k=>$v): 
                $isActive = ($page==$k || ($k=='classes' && $page=='students'));
            ?>
            <a href="?page=<?php echo $k; ?>" class="nav-item flex items-center gap-3 px-4 py-3.5 rounded-xl font-medium text-slate-600 hover:bg-slate-50 transition-all <?php echo $isActive?'active':''; ?>">
                <i class='bx <?php echo $v['icon']; ?> text-xl'></i> <?php echo $v['label']; ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <div class="p-4 border-t border-gray-100">
            <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-gray-500 hover:bg-red-50 hover:text-red-500 transition-colors">
                <i class='bx bx-log-out text-xl'></i> <span class="font-medium">ออกจากระบบ</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 md:ml-64 relative overflow-y-auto h-full">
        <div class="absolute top-0 left-0 w-full h-96 bg-gradient-to-b from-indigo-100/50 to-transparent z-0"></div>

        <div class="md:hidden h-16 bg-white/80 backdrop-blur flex items-center justify-between px-4 sticky top-0 z-30 border-b">
            <span class="font-bold text-indigo-600">SchoolCheck Admin</span>
            <button class="text-gray-600"><i class='bx bx-menu text-2xl'></i></button>
        </div>

        <div class="relative z-10 p-6 md:p-10 max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-3xl font-bold text-slate-800">
                        <?php 
                            if($page=='dashboard') echo 'Dashboard Overview';
                            elseif($page=='years') echo 'Academic Years'; 
                            elseif($page=='users') echo 'User Management';
                            elseif($page=='classes') echo 'Class Management';
                            elseif($page=='students') echo 'Student List';
                            elseif($page=='login_logs') echo 'Login History';
                        ?>
                    </h2>
                    <p class="text-slate-500 mt-1">ยินดีต้อนรับคุณ <?php echo $_SESSION['full_name']; ?> (Admin)</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right hidden md:block">
                        <p class="text-sm font-bold text-slate-700"><?php echo date('d F Y'); ?></p>
                        <p class="text-xs text-slate-400">System Time</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-white border p-1 shadow-sm">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['full_name']); ?>&background=random" class="rounded-full w-full h-full" alt="Admin">
                    </div>
                </div>
            </div>

            <?php if($message): ?>
            <div x-data="{ show: true }" x-show="show" class="mb-6 p-4 rounded-2xl flex items-center gap-3 shadow-lg transform transition-all hover:scale-[1.01] <?php echo $msg_type=='success'?'bg-emerald-500 text-white shadow-emerald-200':'bg-red-500 text-white shadow-red-200'; ?>">
                <i class='bx <?php echo $msg_type=='success'?'bx-check-circle':'bx-error'; ?> text-2xl'></i>
                <span class="font-medium flex-1"><?php echo $message; ?></span>
                <button @click="show = false" class="opacity-70 hover:opacity-100"><i class='bx bx-x text-2xl'></i></button>
            </div>
            <?php endif; ?>

            <?php if ($page == 'dashboard'): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover-card relative overflow-hidden">
                        <div class="absolute right-0 top-0 p-4 opacity-10"><i class='bx bxs-user-account text-8xl text-indigo-600'></i></div>
                        <p class="text-slate-400 text-sm font-medium uppercase">Total Users</p>
                        <h3 class="text-4xl font-bold text-indigo-600 mt-2"><?php echo $conn->query("SELECT COUNT(*) FROM users")->fetchColumn(); ?></h3>
                        <p class="text-xs text-green-500 mt-2 flex items-center gap-1"><i class='bx bx-up-arrow-alt'></i> ครูและเจ้าหน้าที่</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover-card relative overflow-hidden">
                        <div class="absolute right-0 top-0 p-4 opacity-10"><i class='bx bxs-graduation text-8xl text-purple-600'></i></div>
                        <p class="text-slate-400 text-sm font-medium uppercase">Students</p>
                        <h3 class="text-4xl font-bold text-purple-600 mt-2"><?php echo $conn->query("SELECT COUNT(*) FROM students")->fetchColumn(); ?></h3>
                        <p class="text-xs text-purple-400 mt-2 flex items-center gap-1"><i class='bx bx-group'></i> จำนวนนักเรียนทั้งหมด</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover-card relative overflow-hidden">
                        <div class="absolute right-0 top-0 p-4 opacity-10"><i class='bx bxs-school text-8xl text-pink-600'></i></div>
                        <p class="text-slate-400 text-sm font-medium uppercase">Classes</p>
                        <h3 class="text-4xl font-bold text-pink-600 mt-2"><?php echo $conn->query("SELECT COUNT(*) FROM classes")->fetchColumn(); ?></h3>
                        <p class="text-xs text-pink-400 mt-2 flex items-center gap-1"><i class='bx bx-building'></i> ห้องเรียนในปีนี้</p>
                    </div>
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 hover-card relative overflow-hidden">
                        <div class="absolute right-0 top-0 p-4 opacity-10"><i class='bx bxs-check-shield text-8xl text-emerald-600'></i></div>
                        <p class="text-slate-400 text-sm font-medium uppercase">Today Checked</p>
                        <h3 class="text-4xl font-bold text-emerald-600 mt-2">
                            <?php echo $conn->query("SELECT COUNT(DISTINCT student_id) FROM inspections WHERE DATE(inspection_date) = CURDATE()")->fetchColumn(); ?>
                        </h3>
                        <p class="text-xs text-emerald-500 mt-2 flex items-center gap-1"><i class='bx bx-time-five'></i> ตรวจแล้ววันนี้</p>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-3xl p-8 text-white relative overflow-hidden shadow-xl mb-8">
                    <div class="relative z-10 max-w-lg">
                        <h3 class="text-2xl font-bold mb-2">ระบบพร้อมใช้งาน 100%</h3>
                        <p class="opacity-90 mb-6">คุณสามารถจัดการข้อมูลพื้นฐานทั้งหมดได้จากแถบเมนูด้านซ้าย</p>
                        <a href="?page=classes" class="bg-white text-indigo-600 px-6 py-2.5 rounded-xl font-bold shadow-lg hover:bg-gray-100 transition inline-block">จัดการห้องเรียน</a>
                    </div>
                    <div class="absolute -bottom-10 -right-10 w-64 h-64 bg-white opacity-10 rounded-full blur-3xl"></div>
                    <div class="hidden md:block absolute right-10 bottom-0 w-48">
                         <img src="https://cdn3d.iconscout.com/3d/premium/thumb/web-development-3d-illustration-download-in-png-blend-fbx-gltf-file-formats--coding-programming-code-language-optimization-pack-seo-illustrations-4545532.png" alt="3D Worker" class="drop-shadow-2xl">
                    </div>
                </div>

            <?php elseif ($page == 'years'): ?>
                <?php $years = $conn->query("SELECT * FROM academic_years ORDER BY year_name DESC, term DESC")->fetchAll(PDO::FETCH_ASSOC); ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 h-fit">
                        <h3 class="font-bold text-lg text-slate-800 mb-4 flex items-center gap-2"><i class='bx bx-plus-circle text-indigo-500'></i> เพิ่ม/แก้ไขปีการศึกษา</h3>
                        <p class="text-xs text-slate-400 mb-4">ใช้ปุ่ม "แก้ไข" ในตารางเพื่อแก้ไขข้อมูลเดิม</p>
                        <button @click="showEditModal=true; editType='year'; editData={inspection_count: 20}" class="btn-primary w-full mt-2 flex items-center justify-center gap-2">
                            <i class='bx bx-plus'></i> เพิ่มปีการศึกษาใหม่
                        </button>
                    </div>

                    <div class="md:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-6 border-b border-slate-100 bg-gray-50/50">
                            <h3 class="font-bold text-lg text-slate-800">รายการปีการศึกษา</h3>
                            <p class="text-xs text-slate-400">เลือกปีปัจจุบัน (Active) เพื่อใช้งานในระบบ</p>
                        </div>
                        <table class="w-full text-left">
                            <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold">
                                <tr>
                                    <th class="p-4">ปีการศึกษา</th>
                                    <th class="p-4">เป้าหมายการตรวจ</th>
                                    <th class="p-4">สถานะ</th>
                                    <th class="p-4 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm">
                                <?php foreach($years as $y): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="p-4 font-bold text-slate-700">
                                        <i class='bx bxs-calendar text-indigo-300 mr-2'></i> <?php echo $y['year_name']; ?> / <?php echo $y['term']; ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="bg-indigo-50 text-indigo-700 px-2 py-1 rounded text-xs font-bold">
                                            <?php echo isset($y['inspection_count']) ? $y['inspection_count'] : '20'; ?> ครั้ง
                                        </span>
                                    </td>
                                    <td class="p-4">
                                        <?php if($y['is_active']): ?>
                                            <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold flex items-center w-fit gap-1"><i class='bx bxs-check-circle'></i> ใช้งานอยู่</span>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-xs">ประวัติเก่า</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-right flex justify-end gap-2">
                                        <button @click="showEditModal=true; editType='year'; editData=<?php echo htmlspecialchars(json_encode($y)); ?>" class="text-indigo-500 hover:bg-indigo-50 p-1.5 rounded-lg transition"><i class='bx bx-edit text-lg'></i></button>
                                        
                                        <?php if(!$y['is_active']): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="set_active_year">
                                                <input type="hidden" name="year_id" value="<?php echo $y['year_id']; ?>">
                                                <button class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-3 py-1.5 rounded-lg text-xs font-bold transition">ตั้งเป็นปัจจุบัน</button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('ลบปีการศึกษานี้?');">
                                                <input type="hidden" name="action" value="delete_year">
                                                <input type="hidden" name="id" value="<?php echo $y['year_id']; ?>">
                                                <button class="text-red-400 hover:bg-red-50 p-1.5 rounded-lg transition"><i class='bx bx-trash text-lg'></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php elseif ($page == 'users'): 
                $sql = "SELECT u.*, GROUP_CONCAT(tca.class_id) as assigned_class_ids FROM users u LEFT JOIN teacher_class_assignments tca ON u.user_id = tca.user_id GROUP BY u.user_id ORDER BY u.user_id DESC";
                $users = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-gray-50/50">
                        <div>
                            <h3 class="font-bold text-lg text-slate-800">รายชื่อผู้ใช้งาน</h3>
                            <p class="text-xs text-slate-400">จัดการบัญชีครูและสิทธิ์การเข้าถึง</p>
                        </div>
                        <button @click="showEditModal=true; editType='user'; editData={}; assignedClasses=[]" class="bg-indigo-600 text-white px-5 py-2.5 rounded-xl shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition flex items-center gap-2">
                            <i class='bx bx-plus'></i> เพิ่มผู้ใช้
                        </button>
                    </div>
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold tracking-wider">
                            <tr>
                                <th class="p-5">Profile</th>
                                <th class="p-5">Role</th>
                                <th class="p-5">Assigned Classes</th>
                                <th class="p-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach($users as $u): ?>
                            <tr class="hover:bg-indigo-50/30 transition-colors">
                                <td class="p-5 flex items-center gap-4">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($u['full_name']); ?>&background=random&rounded=true" class="w-10 h-10 rounded-full shadow-sm">
                                    <div>
                                        <p class="font-bold text-slate-700"><?php echo $u['full_name']; ?></p>
                                        <p class="text-xs text-slate-400">@<?php echo $u['username']; ?></p>
                                    </div>
                                </td>
                                <td class="p-5">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $u['role']=='admin'?'bg-purple-100 text-purple-600':'bg-blue-100 text-blue-600'; ?>">
                                        <?php echo strtoupper($u['role']); ?>
                                    </span>
                                </td>
                                <td class="p-5">
                                    <?php if($u['role'] == 'admin'): ?>
                                        <span class="text-gray-400 text-xs italic"><i class='bx bx-check-double'></i> Full Access</span>
                                    <?php elseif($u['assigned_class_ids']): ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach(explode(',', $u['assigned_class_ids']) as $cid): 
                                                foreach($allClasses as $ac) if($ac['class_id'] == $cid) echo "<span class='bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded'>ม.{$ac['level_name']}{$ac['room_number']}</span> ";
                                            endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-red-400 text-xs bg-red-50 px-2 py-1 rounded">ยังไม่ระบุห้อง</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-5 text-right">
                                    <button @click="showEditModal=true; editType='user'; editData=<?php echo htmlspecialchars(json_encode($u)); ?>; assignedClasses = editData.assigned_class_ids ? editData.assigned_class_ids.split(',') : []" class="text-indigo-500 hover:bg-indigo-50 p-2 rounded-lg transition"><i class='bx bx-edit text-xl'></i></button>
                                    <form method="POST" class="inline" onsubmit="return confirm('ยืนยันการลบ?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?php echo $u['user_id']; ?>">
                                        <button class="text-red-400 hover:bg-red-50 p-2 rounded-lg transition"><i class='bx bx-trash text-xl'></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($page == 'classes'): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <button @click="showEditModal=true; editType='class'; editData={}" class="border-2 border-dashed border-slate-300 rounded-2xl flex flex-col items-center justify-center p-6 text-slate-400 hover:border-indigo-500 hover:text-indigo-500 hover:bg-indigo-50 transition h-full min-h-[150px]">
                        <i class='bx bx-plus text-4xl mb-2'></i>
                        <span class="font-bold">เพิ่มห้องเรียน</span>
                    </button>
                    <?php foreach($allClasses as $c): ?>
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 relative group hover:-translate-y-1 transition-transform duration-300 h-full flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <div class="p-2 bg-indigo-50 rounded-lg text-indigo-600">
                                    <i class='bx bxs-building text-2xl'></i>
                                </div>
                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button @click="showEditModal=true; editType='class'; editData=<?php echo htmlspecialchars(json_encode($c)); ?>" class="text-slate-400 hover:text-indigo-600"><i class='bx bx-edit'></i></button>
                                    <form method="POST" onsubmit="return confirm('ลบ?');">
                                        <input type="hidden" name="action" value="delete_class"><input type="hidden" name="id" value="<?php echo $c['class_id']; ?>">
                                        <button class="text-slate-400 hover:text-red-500"><i class='bx bx-trash'></i></button>
                                    </form>
                                </div>
                            </div>
                            <h3 class="text-2xl font-bold text-slate-800">ม.<?php echo $c['level_name'].$c['room_number']; ?></h3>
                            <p class="text-slate-400 text-sm mt-1">ปีการศึกษาปัจจุบัน</p>
                        </div>
                        <div class="mt-4 pt-4 border-t border-slate-50">
                            <a href="?page=students&class_id=<?php echo $c['class_id']; ?>" class="flex items-center justify-center gap-2 w-full bg-indigo-50 text-indigo-600 py-2 rounded-lg hover:bg-indigo-100 transition font-medium text-sm">
                                <i class='bx bxs-user-detail'></i> รายชื่อนักเรียน
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($page == 'students'): 
                 // Handle filtering by class
                 $filter_class_id = isset($_GET['class_id']) ? $_GET['class_id'] : null;
                 
                 // ถ้าไม่มี class_id ให้ดีดกลับไปหน้า classes (เพื่อบังคับให้เลือกห้องก่อน)
                 if (!$filter_class_id) {
                     echo "<script>window.location.href='?page=classes';</script>";
                     exit;
                 }
                 
                 $sql = "SELECT s.*, c.level_name, c.room_number FROM students s LEFT JOIN classes c ON s.current_class_id=c.class_id ";
                 if ($filter_class_id) {
                     $sql .= " WHERE s.current_class_id = $filter_class_id ";
                 }
                 $sql .= " ORDER BY s.student_code ASC";
                 
                 $students = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC); 
                 
                 // Get class name for display
                 $className = "";
                 if($filter_class_id) {
                     foreach($allClasses as $c) {
                         if($c['class_id'] == $filter_class_id) $className = "ม.".$c['level_name'].$c['room_number'];
                     }
                 }
                 ?>
                 
                 <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center bg-gray-50/50 gap-4">
                        <div>
                            <a href="?page=classes" class="text-slate-400 hover:text-slate-600 text-sm mb-1 inline-flex items-center gap-1"><i class='bx bx-arrow-back'></i> ย้อนกลับ</a>
                            <h3 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                                รายชื่อนักเรียน 
                                <span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-sm"><?php echo $className; ?></span>
                            </h3>
                        </div>
                        
                        <div class="flex gap-2">
                            <button @click="showEditModal=true; editType='import_csv'; editData={target_class_id: '<?php echo $filter_class_id; ?>'}" class="bg-emerald-500 text-white px-4 py-2.5 rounded-xl shadow-lg shadow-emerald-200 hover:bg-emerald-600 transition flex items-center gap-2 font-medium text-sm">
                                <i class='bx bxs-file-import text-lg'></i> Import CSV
                            </button>

                            <button @click="showEditModal=true; editType='student'; editData={current_class_id: '<?php echo $filter_class_id; ?>'}" class="bg-indigo-600 text-white px-4 py-2.5 rounded-xl shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition flex items-center gap-2 font-medium text-sm">
                                <i class='bx bx-plus text-lg'></i> เพิ่มนักเรียน
                            </button>
                        </div>
                    </div>
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase font-bold"><tr><th class="p-4">ID</th><th class="p-4">Name</th><th class="p-4">Class</th><th class="p-4 text-right">Action</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            <?php foreach($students as $s): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="p-4 font-mono text-slate-500"><?php echo $s['student_code']; ?></td>
                                <td class="p-4 font-bold text-slate-700"><?php echo $s['first_name'].' '.$s['last_name']; ?></td>
                                <td class="p-4"><span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs">ม.<?php echo $s['level_name'].$s['room_number']; ?></span></td>
                                <td class="p-4 text-right">
                                    <button @click="showEditModal=true; editType='student'; editData=<?php echo htmlspecialchars(json_encode($s)); ?>" class="text-indigo-500 hover:underline mr-2">แก้ไข</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('ลบ?');"><input type="hidden" name="action" value="delete_student"><input type="hidden" name="id" value="<?php echo $s['student_id']; ?>"><button class="text-red-400 hover:underline">ลบ</button></form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($students)): ?>
                                <tr><td colspan="4" class="p-8 text-center text-slate-400">ไม่พบข้อมูลนักเรียนในห้องนี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif($page=='login_logs'): 
                $logs = $conn->query("SELECT l.*, u.username, u.full_name, u.role 
                                      FROM login_logs l 
                                      LEFT JOIN users u ON l.user_id = u.user_id 
                                      ORDER BY l.login_time DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
            ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                        <h3 class="font-bold text-slate-700 flex items-center gap-2">
                            <i class='bx bx-time-five text-indigo-500'></i> ประวัติการเข้าใช้งานล่าสุด
                        </h3>
                        <span class="text-xs text-slate-400">แสดง 100 รายการล่าสุด</span>
                    </div>
                    <div class="table-responsive">
                        <table class="w-full text-left text-sm whitespace-nowrap">
                            <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-xs">
                                <tr>
                                    <th class="p-4">เวลา</th>
                                    <th class="p-4">ผู้ใช้งาน</th>
                                    <th class="p-4">สิทธิ์</th>
                                    <th class="p-4">IP Address</th>
                                    <th class="p-4">อุปกรณ์/Browser</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach($logs as $log): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="p-4 text-slate-500 font-mono text-xs">
                                        <?php echo date('d/m/Y H:i:s', strtotime($log['login_time'])); ?>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-bold text-slate-700"><?php echo $log['full_name']; ?></div>
                                        <div class="text-xs text-slate-400">@<?php echo $log['username']; ?></div>
                                    </td>
                                    <td class="p-4">
                                        <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded text-[10px] font-bold uppercase">
                                            <?php echo $log['role']; ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-slate-600 font-mono text-xs"><?php echo $log['ip_address']; ?></td>
                                    <td class="p-4 text-slate-400 text-xs max-w-xs truncate" title="<?php echo $log['user_agent']; ?>">
                                        <?php echo $log['user_agent']; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="showEditModal = false"></div>
            
            <div class="bg-white rounded-3xl shadow-2xl transform transition-all sm:max-w-lg w-full p-8 relative z-10 scale-100">
                <div class="absolute top-4 right-4">
                    <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-600"><i class='bx bx-x text-2xl'></i></button>
                </div>

                <div x-show="editType === 'user'">
                    <h3 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2"><i class='bx bxs-user-detail text-indigo-500'></i> จัดการผู้ใช้</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" x-bind:value="editData.user_id ? 'edit_user' : 'add_user'">
                        <input type="hidden" name="user_id" x-model="editData.user_id">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="label">Username</label><input type="text" name="username" x-model="editData.username" class="input-field" required></div>
                            <div><label class="label">Password</label><input type="text" name="password" class="input-field" placeholder="เว้นว่างถ้าไม่แก้"></div>
                        </div>
                        <div class="text-xs text-slate-500 bg-slate-50 p-2 rounded-lg border border-slate-100">
                            <span class="font-bold text-indigo-500">ความปลอดภัยรหัสผ่าน:</span> ต้องมีอย่างน้อย 8 ตัว, ตัวพิมพ์ใหญ่, ตัวพิมพ์เล็ก และตัวเลข
                        </div>

                        <div><label class="label">Full Name</label><input type="text" name="fullname" x-model="editData.full_name" class="input-field" required></div>
                        <div>
                            <label class="label">Role</label>
                            <select name="role" x-model="editData.role" class="input-field bg-white">
                                <option value="teacher">Teacher (ครู)</option>
                                <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                                <option value="director">Director (ผู้อำนวยการ)</option>
                                <option value="deputy_director">Deputy Director (รองผู้อำนวยการ)</option>
                            </select>
                        </div>
                        <div x-show="editData.role !== 'admin'" class="bg-slate-50 p-4 rounded-xl border border-slate-200 mt-2">
                            <label class="label mb-2">มอบหมายห้องเรียน</label>
                            <div class="h-32 overflow-y-auto grid grid-cols-2 gap-2">
                                <?php foreach($allClasses as $c): ?>
                                <label class="flex items-center space-x-2 text-sm cursor-pointer p-1 hover:bg-white rounded">
                                    <input type="checkbox" name="class_ids[]" value="<?php echo $c['class_id']; ?>" class="rounded text-indigo-600" x-model="assignedClasses">
                                    <span>ม.<?php echo $c['level_name'].$c['room_number']; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <button class="btn-primary w-full mt-4">บันทึกข้อมูล</button>
                    </form>
                </div>

                <div x-show="editType === 'class'">
                     <h3 class="text-2xl font-bold text-slate-800 mb-6">ห้องเรียน</h3>
                     <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" x-bind:value="editData.class_id ? 'edit_class' : 'add_class'">
                        <input type="hidden" name="class_id" x-model="editData.class_id">
                        <div><label class="label">ระดับชั้น (เช่น 1)</label><input type="text" name="level" x-model="editData.level_name" class="input-field" required></div>
                        <div><label class="label">ห้อง (เช่น /1)</label><input type="text" name="room" x-model="editData.room_number" class="input-field" required></div>
                        <button class="btn-primary w-full mt-4">บันทึก</button>
                     </form>
                </div>

                <div x-show="editType === 'year'">
                     <h3 class="text-2xl font-bold text-slate-800 mb-6">จัดการปีการศึกษา</h3>
                     <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" x-bind:value="editData.year_id ? 'edit_year' : 'add_year'">
                        <input type="hidden" name="year_id" x-model="editData.year_id">
                        <div><label class="label">ปี พ.ศ. (เช่น 2567)</label><input type="number" name="year_name" x-model="editData.year_name" class="input-field" required></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="label">ภาคเรียนที่</label><input type="number" name="term" x-model="editData.term" class="input-field" placeholder="1" required></div>
                            <div><label class="label">จำนวนครั้งที่ต้องตรวจ (เป้าหมาย)</label><input type="number" name="inspection_count" x-model="editData.inspection_count" class="input-field" placeholder="20" required></div>
                        </div>
                        <button class="btn-primary w-full mt-4">บันทึก</button>
                     </form>
                </div>

                <div x-show="editType === 'import_csv'">
                     <h3 class="text-2xl font-bold text-slate-800 mb-2 flex items-center gap-2">
                        <i class='bx bxs-file-import text-emerald-500'></i> นำเข้าข้อมูลนักเรียน
                     </h3>
                     <p class="text-sm text-slate-500 mb-6">อัปโหลดไฟล์ CSV เพื่อเพิ่มนักเรียนทีละหลายคน</p>
                     
                     <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-sm text-amber-800">
                        <p class="font-bold mb-2">📋 รูปแบบไฟล์ที่รองรับ (เรียงตามคอลัมน์):</p>
                        <p class="font-mono bg-white p-2 rounded border border-amber-200 text-xs">
                            รหัสนักเรียน, คำนำหน้า, ชื่อ, นามสกุล<br>
                            67001, ด.ช., สมชาย, ใจดี
                        </p>
                        <p class="mt-2 text-xs">* ต้องบันทึกไฟล์เป็น <strong>CSV (Comma delimited) UTF-8</strong> เท่านั้น</p>
                        <p class="mt-1 text-xs">* ข้อมูลจะถูกนำเข้าสู่ห้องเรียนที่เลือกไว้โดยอัตโนมัติ</p>
                     </div>

                     <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="import_students">
                        <input type="hidden" name="target_class_id" x-model="editData.target_class_id">
                        
                        <div class="border-2 border-dashed border-slate-300 rounded-xl p-8 text-center hover:bg-slate-50 transition cursor-pointer relative">
                            <input type="file" name="csv_file" accept=".csv" required 
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            <i class='bx bxs-cloud-upload text-4xl text-slate-400 mb-2'></i>
                            <p class="text-slate-600 font-medium">คลิกเพื่อเลือกไฟล์ CSV</p>
                            <p class="text-xs text-slate-400">(.csv only)</p>
                        </div>

                        <button class="w-full bg-emerald-500 text-white font-bold py-3 rounded-xl shadow-lg hover:bg-emerald-600 transition">
                            เริ่มนำเข้าข้อมูล
                        </button>
                     </form>
                </div>

                <div x-show="editType === 'student'">
                     <h3 class="text-2xl font-bold text-slate-800 mb-6">ข้อมูลนักเรียน</h3>
                     <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" x-bind:value="editData.student_id ? 'edit_student' : 'add_student'">
                        <input type="hidden" name="student_id" x-model="editData.student_id">
                        <input type="hidden" name="class_id" x-model="editData.current_class_id">

                        <div><label class="label">รหัสประจำตัว</label><input type="text" name="code" x-model="editData.student_code" class="input-field" required></div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-1"><label class="label">คำนำหน้า</label><input type="text" name="prefix" x-model="editData.prefix" class="input-field"></div>
                            <div class="col-span-2"><label class="label">ชื่อจริง</label><input type="text" name="fname" x-model="editData.first_name" class="input-field" required></div>
                        </div>
                        <div><label class="label">นามสกุล</label><input type="text" name="lname" x-model="editData.last_name" class="input-field" required></div>
                        
                        <button class="btn-primary w-full mt-4">บันทึก</button>
                     </form>
                </div>

            </div>
        </div>
    </div>
    
    <?php include 'manual.php'; ?>

    <style>
        .label { display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem; text-transform: uppercase; }
        .input-field { width: 100%; padding: 0.75rem 1rem; border-radius: 0.75rem; border: 1px solid #cbd5e1; outline: none; transition: all 0.2s; }
        .input-field:focus { border-color: #6366f1; ring: 2px solid #e0e7ff; }
        .btn-primary { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; padding: 0.75rem; border-radius: 0.75rem; font-weight: 600; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); transition: transform 0.1s; }
        .btn-primary:active { transform: scale(0.98); }
    </style>
</body>
</html>