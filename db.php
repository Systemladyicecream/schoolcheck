<?php
// db.php - แก้ไขปัญหา Connection Refused

// 1. ตั้งค่าพื้นฐาน (ดู Port ในโปรแกรม MAMP ของคุณ)
$host = 'sql100.byethost6.com';   // เปลี่ยนจาก localhost เป็น 127.0.0.1 (แก้ปัญหา Windows หา IP ไม่เจอ)
$port = '3306';        // **สำคัญ** ดูเลขนี้ใน MAMP (ปกติ MAMP ใช้ 8889, ถ้า XAMPP ใช้ 3306)
$dbname = 'b6_40957347_schoolcheck';
$username = 'b6_40957347';
$password = 'Ladyicecream86420';    // รหัสผ่าน MAMP ปกติคือ root

try {
    // 2. เพิ่ม port=$port เข้าไปใน Connection String
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // (เอาไว้เทส ถ้าเชื่อมติดจะไม่มี error ขึ้น)
    // echo "Connected successfully"; 

} catch(PDOException $e) {
    // 3. แสดง Error ชัดเจน
    echo "<div style='background-color: #fee; color: red; padding: 20px; border: 1px solid red; font-family: sans-serif;'>";
    echo "<h3>⚠️ เชื่อมต่อฐานข้อมูลไม่ได้</h3>";
    echo "<p><b>Error:</b> " . $e->getMessage() . "</p>";
    echo "<hr>";
    echo "<h4>วิธีแก้ไขเบื้องต้น:</h4>";
    echo "<ul>";
    echo "<li>1. เปิดโปรแกรม MAMP ดูตรง <b>MySQL Port</b> ว่าเลขอะไร (เช่น 8889 หรือ 3306)</li>";
    echo "<li>2. กลับมาแก้ไฟล์ <b>db.php</b> บรรทัดที่ <b>\$port = '...';</b> ให้ตรงกับ MAMP</li>";
    echo "<li>3. ตรวจสอบว่ากดปุ่ม <b>Start Servers</b> ใน MAMP ให้ไฟเขียวขึ้นหรือยัง</li>";
    echo "</ul>";
    echo "</div>";
    die();
}

// ฟังก์ชันตรวจสอบ Login
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>