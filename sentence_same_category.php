<?php
// รับค่า id หมวดหมู่ เช่น sentence_same_category.php?id=111
$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id <= 0) {
    die("กรุณาระบุ Category ID ที่ถูกต้อง เช่น sentence_same_category.php?id=1");
}

// กำหนดคำสั่งรัน Python
$command = escapeshellcmd("python3 sentence_same_category.py " . $category_id);

// รันคำสั่งและดึงผลลัพธ์ log ออกมาแสดง
$output = shell_exec($command . " 2>&1");

echo "<!DOCTYPE html>";
echo "<html lang='th'>";
echo "<head><meta charset='UTF-8'><title>ประมวลผล Recommendation</title></head>";
echo "<body style='font-family: Arial, sans-serif; padding: 20px; background: #fdfaf7;'>";
echo "<h2>ผลการคำนวณ Recommendation (หมวดหมู่ ID: " . htmlspecialchars($category_id) . ")</h2>";
echo "<pre style='background: #2d3748; color: #f7fafc; padding: 15px; border-radius: 8px; font-size: 14px; overflow-x: auto;'>";
echo htmlspecialchars($output ?: "ไม่พบผลลัพธ์จากระบบ (อาจกำลังประมวลผลอยู่เบื้องหลัง)");
echo "</pre>";
echo "<br><a href='index.php' style='text-decoration: none; background: #4a5568; color: white; padding: 8px 16px; border-radius: 5px;'>กลับสู่หน้าหลัก</a>";
echo "</body>";
echo "</html>";
?>
