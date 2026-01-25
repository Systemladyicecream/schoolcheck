<?php
session_start();
require_once 'db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND status = 'active'");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && ($password === $user['password_hash'] || password_verify($password, $user['password_hash']))) { 
            // 1. ตั้งค่า Session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // 2. อัปเดตเวลา Login ล่าสุดในตาราง users
            $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")->execute([$user['user_id']]);

            // 3. [เพิ่มใหม่] บันทึกประวัติการเข้าสู่ระบบลงตาราง login_logs
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT']; // ข้อมูล Browser/Device
            
            $logStmt = $conn->prepare("INSERT INTO login_logs (user_id, login_time, ip_address, user_agent) VALUES (?, NOW(), ?, ?)");
            $logStmt->execute([$user['user_id'], $ip_address, $user_agent]);

            // 4. ตรวจสอบสิทธิ์และเปลี่ยนหน้า
            if ($user['role'] === 'admin') header("Location: admin.php");
            elseif (in_array($user['role'], ['executive', 'director', 'deputy_director'])) header("Location: executive.php");
            else header("Location: index.php");
            exit();
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    } catch (PDOException $e) {
        $error = "System Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>เข้าสู่ระบบ - SchoolCheck</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; -webkit-tap-highlight-color: transparent; }
        .bg-mesh {
            background-color: #f3f4f6;
            background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            background-size: 200% 200%;
            animation: mesh 15s ease infinite;
        }
        @keyframes mesh { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
        .glass { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.5); }
        input { font-size: 16px !important; } /* No Zoom on iOS */
    </style>
</head>
<body class="bg-mesh min-h-screen flex items-center justify-center p-4 selection:bg-indigo-500 selection:text-white">
    <div class="w-full max-w-md glass rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh] overflow-y-auto">
        <div class="p-8">
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-gradient-to-tr from-indigo-600 to-purple-600 rounded-2xl mx-auto flex items-center justify-center text-white text-3xl shadow-lg shadow-indigo-500/30 mb-4 transform hover:scale-105 transition duration-300">
                    <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 tracking-tight">SchoolCheck</h1>
                <p class="text-gray-500 text-sm mt-1">ระบบตรวจระเบียบนักเรียนดิจิทัล</p>
            </div>

            <?php if($error): ?>
                <div class="bg-red-50 border border-red-100 text-red-600 px-4 py-3 rounded-xl mb-6 text-sm flex items-center gap-3 animate-pulse">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div class="space-y-1">
                    <label class="text-xs font-bold text-gray-500 uppercase ml-1">Username</label>
                    <input type="text" name="username" class="w-full px-5 py-3.5 bg-gray-50/50 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all" placeholder="ชื่อผู้ใช้งาน" required>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-bold text-gray-500 uppercase ml-1">Password</label>
                    <input type="password" name="password" class="w-full px-5 py-3.5 bg-gray-50/50 border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition-all" placeholder="รหัสผ่าน" required>
                </div>
                <button class="w-full bg-indigo-600 text-white font-bold py-4 rounded-2xl shadow-lg shadow-indigo-500/30 hover:bg-indigo-700 active:scale-95 transition-all duration-200 flex items-center justify-center gap-2 mt-4">
                    <span>เข้าสู่ระบบ</span>
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </button>
            </form>
        </div>
        
        <div class="bg-gray-50/80 p-6 text-center border-t border-gray-100">
            <a href="student.php" class="inline-flex items-center gap-2 text-indigo-600 font-bold bg-white border border-indigo-100 px-5 py-2.5 rounded-xl hover:bg-indigo-50 transition-all shadow-sm text-sm">
                <span>🎓</span> สำหรับนักเรียนตรวจสอบผล
            </a>
        </div>
    </div>
</body>
</html>