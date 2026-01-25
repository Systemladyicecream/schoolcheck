<?php
require_once 'db.php';

// ข้อมูล Admin ที่ต้องการ (ตามที่คุณส่งมา)
$username = 'Admin';
$password = 'Admin1234'; // รหัสผ่าน
$fullname = 'วรเมธ คำตั้งหน้า';
$role = 'admin';
$status = 'active';

echo "<html><body style='font-family: sans-serif; padding: 40px; background-color: #f4f6f8;'>";
echo "<div style='background: white; padding: 30px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; text-align: center;'>";

try {
    // 1. เช็คก่อนว่ามี User นี้หรือยัง
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        // --- กรณีมีแล้ว: อัปเดต (Update) ---
        $sql = "UPDATE users SET 
                password_hash = :pass,
                full_name = :fname,
                role = :role,
                status = :status,
                last_login = NOW()
                WHERE username = :username";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'pass' => $password,
            'fname' => $fullname,
            'role' => $role,
            'status' => $status,
            'username' => $username
        ]);
        
        echo "<h2 style='color: #d97706;'>🛠️ อัปเดตข้อมูลสำเร็จ</h2>";
        echo "<p>พบผู้ใช้ '<b>$username</b>' เดิมอยู่แล้ว ระบบได้ปรับปรุงสิทธิ์และรหัสผ่านให้ใหม่ครับ</p>";

    } else {
        // --- กรณีไม่มี: สร้างใหม่ (Insert) ---
        // ไม่ต้องใส่ user_id หรือ NULL ปล่อยให้ Database จัดการ Auto Increment เอง
        $sql = "INSERT INTO users (username, password_hash, full_name, role, status, created_at, last_login) 
                VALUES (:username, :pass, :fname, :role, :status, NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'username' => $username,
            'pass' => $password,
            'fname' => $fullname,
            'role' => $role,
            'status' => $status
        ]);

        echo "<h2 style='color: #059669;'>✅ สร้าง Admin สำเร็จ</h2>";
        echo "<p>เพิ่มผู้ใช้ '<b>$username</b>' เรียบร้อยครับ</p>";
    }

    echo "<hr style='margin: 20px 0; border: 0; border-top: 1px solid #eee;'>";
    echo "<p style='margin-bottom: 5px;'>ชื่อผู้ใช้: <b>$username</b></p>";
    echo "<p style='margin-bottom: 20px;'>รหัสผ่าน: <b>$password</b></p>";
    echo "<a href='login.php' style='display: inline-block; padding: 12px 25px; background-color: #4f46e5; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>ไปหน้าเข้าสู่ระบบ</a>";

} catch (PDOException $e) {
    echo "<h2 style='color: #dc2626;'>❌ เกิดข้อผิดพลาด</h2>";
    echo "<p style='color: #666;'>" . $e->getMessage() . "</p>";
}

echo "</div></body></html>";
?>