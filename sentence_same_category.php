<?php
// กำหนดการเชื่อมต่อฐานข้อมูล TiDB Cloud
$db_host = getenv("DB_HOST") ?: "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$db_user = getenv("DB_USER") ?: "4JodNqEkbc1nEbH.root";
$db_pass = getenv("DB_PASS") ?: "zF4DHIXiUrHylslj";
$db_name = getenv("DB_NAME") ?: "thai_novel";
$db_port = getenv("DB_PORT") ?: 4000;

$conn = mysqli_init();
if ($db_host !== "localhost" && $db_host !== "127.0.0.1") {
    mysqli_ssl_set($conn, NULL, NULL, "/etc/ssl/certs/ca-certificates.crt", NULL, NULL);
}

if (!@mysqli_real_connect($conn, $db_host, $db_user, $db_pass, $db_name, (int)$db_port, NULL, MYSQLI_CLIENT_SSL)) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// ตรวจสอบจำนวนข้อมูล Recommendation ในตาราง
$count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM recommendation_sentence_same_category");
$count_row = mysqli_fetch_assoc($count_query);
$total_recommendations = $count_row['total'];

// รับค่า book_id เพื่อแสดงตัวอย่างรายการแนะนำ (ถ้ามี)
$book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สถานะระบบแนะนำนิยาย (Sentence Transformers)</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; padding: 30px; color: #1e293b; }
        .card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); max-width: 800px; margin: auto; }
        .badge { background: #10b981; color: white; padding: 4px 10px; border-radius: 9999px; font-size: 13px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        th { background: #f1f5f9; color: #475569; }
        .btn { display: inline-block; padding: 8px 16px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin-top: 15px; }
    </style>
</head>
<body>

<div class="card">
    <h2>✨ ระบบแนะนำหนังสือนิยายในหมวดหมู่เดียวกัน</h2>
    <p>โมเดล: <code>paraphrase-multilingual-MiniLM-L12-v2</code></p>
    <p>สถานะฐานข้อมูล: <span class="badge">พร้อมใช้งาน</span> (มีข้อมูลแนะนำในระบบทั้งหมด <strong><?= number_format($total_recommendations) ?></strong> รายการ)</p>

    <?php if ($book_id > 0): ?>
        <?php
        // ดึงชื่อหนังสือเล่มหลัก
        $book_res = mysqli_query($conn, "SELECT Title, Category_id FROM book WHERE Book_id = $book_id");
        $book_data = mysqli_fetch_assoc($book_res);
        ?>
        <hr style="margin: 20px 0; border: none; border-top: 1px solid #e2e8f0;">
        <?php if ($book_data): ?>
            <h3>รายการแนะนำสำหรับเรื่อง: <span style="color: #2563eb;"><?= htmlspecialchars($book_data['Title']) ?></span> (รหัสเล่ม: <?= $book_id ?>)</h3>
            <table>
                <thead>
                    <tr>
                        <th>รหัสแนะนำ</th>
                        <th>ชื่อหนังสือที่แนะนำ (หมวดเดียวกัน)</th>
                        <th>คะแนนความคล้าย (Similarity)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rec_query = mysqli_query($conn, "
                        SELECT r.recommend_book_id, r.similarity, b.Title 
                        FROM recommendation_sentence_same_category r
                        JOIN book b ON r.recommend_book_id = b.Book_id
                        WHERE r.book_id = $book_id
                        ORDER BY r.similarity DESC
                    ");
                    if (mysqli_num_rows($rec_query) > 0):
                        while ($row = mysqli_fetch_assoc($rec_query)):
                    ?>
                        <tr>
                            <td><?= $row['recommend_book_id'] ?></td>
                            <td><?= htmlspecialchars($row['Title']) ?></td>
                            <td><strong><?= number_format($row['similarity'] * 100, 2) ?>%</strong> (<?= $row['similarity'] ?>)</td>
                        </tr>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                        <tr><td colspan="3" style="text-align: center; color: #94a3b8;">ไม่พบรายการแนะนำสำหรับหนังสือเล่มนี้</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color: #ef4444;">ไม่พบหนังสือนิยายรหัส ID: <?= $book_id ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <br>
    <a href="index.php" class="btn">กลับหน้าหลัก</a>
</div>

</body>
</html>
