<?php
// ตรวจสอบสิทธิ์ว่าต้องล็อกอินเป็นแอดมินเท่านั้น
require_once "auth_check.php";
include "db.php";

// ดึงหมวดหมู่หนังสือทั้งหมดมารอไว้ใส่ใน Dropdown (เรียงภาษาไทยขึ้นก่อน)
$category_res = mysqli_query($conn, "SELECT * FROM category ORDER BY (Category_name REGEXP '^[ก-๙]') DESC, Category_name ASC");
$categories = [];
while($cat = mysqli_fetch_assoc($category_res)){
    $categories[] =$cat;
}

// ดึงรายชื่อผู้แต่งที่มีอยู่ทั้งหมด (เรียงภาษาไทยขึ้นก่อน)
$author_res = mysqli_query($conn, "SELECT DISTINCT Author FROM book WHERE Author IS NOT NULL AND Author != '' ORDER BY (Author REGEXP '^[ก-๙]') DESC, Author ASC");
$authors = [];
while($a = mysqli_fetch_assoc($author_res)){
    $authors[] =$a['Author'];
}

// ดึงรายชื่อสำนักพิมพ์ที่มีอยู่ทั้งหมด (เรียงภาษาไทยขึ้นก่อน)
$pub_res = mysqli_query($conn, "SELECT DISTINCT Publisher FROM book WHERE Publisher IS NOT NULL AND Publisher != '' ORDER BY (Publisher REGEXP '^[ก-๙]') DESC, Publisher ASC");
$publishers = [];
while($p = mysqli_fetch_assoc($pub_res)){
    $publishers[] =$p['Publisher'];
}

// =====================================
// เมื่อกดปุ่มบันทึกหนังสือทั้งหมด
// =====================================
if(isset($_POST['submit'])){
    $titles =$_POST['Title'] ?? [];
    $authors_input =$_POST['Author'] ?? [];
    $publishers_input =$_POST['Publisher'] ?? [];
    $blurbs =$_POST['Blurb'] ?? [];
    $images =$_POST['image'] ?? [];
    $category_ids =$_POST['Category_id'] ?? [];

    $duplicate_titles = [];
    $seen_in_form = [];$valid_entries = [];

    // ตรวจสอบข้อมูลก่อนการบันทึก
    for($i = 0; $i < count($titles);$i++){
        $raw_title = trim($titles[$i] ?? '');$raw_author = trim($authors_input[$i] ?? '');
        $raw_pub = trim($publishers_input[$i] ?? '');$raw_blurb = trim($blurbs[$i] ?? '');
        $raw_img = trim($images[$i] ?? '');$cat_id = intval($category_ids[$i] ?? 0);

        if($raw_title === '' ||$raw_author === '' || $raw_pub === '' ||$raw_blurb === '') {
            continue;
        }

        // 1. ตรวจสอบชื่อเรื่องซ้ำกันเองภายในฟอร์มที่กรอกเข้ามาพร้อมกัน
        $lower_title = mb_strtolower($raw_title);
        if(in_array($lower_title,$seen_in_form)){
            $duplicate_titles[] =$raw_title;
            continue;
        }
        $seen_in_form[] =$lower_title;

        // 2. ตรวจสอบชื่อเรื่องซ้ำกับฐานข้อมูล (ทั้งใน book และ add_book)
        $t_escaped = mysqli_real_escape_string($conn,$raw_title);
        $check = mysqli_query($conn, "
            SELECT Title FROM book WHERE Title = '$t_escaped'
            UNION
            SELECT Title FROM add_book WHERE Title = '$t_escaped'
            LIMIT 1
        ");

        if(mysqli_num_rows($check) > 0){
            $duplicate_titles[] =$raw_title;
            continue;
        }

        // เก็บแถวที่ผ่านการตรวจสอบเพื่อรอบันทึก
        $valid_entries[] = [
            'title' => $t_escaped,
            'author' => mysqli_real_escape_string($conn,$raw_author),
            'publisher' => mysqli_real_escape_string($conn,$raw_pub),
            'blurb' => mysqli_real_escape_string($conn,$raw_blurb),
            'image' => mysqli_real_escape_string($conn,$raw_img),
            'cat_id' => $cat_id
        ];
    }

    // กรณีพบชื่อหนังสือซ้ำ และไม่มีเล่มใหม่ที่บันทึกได้เลย
    if(!empty($duplicate_titles) && empty($valid_entries)){
        $dup_list_str = implode(', ', array_unique($duplicate_titles));
        echo "
        <script>
        alert('❌ มีหนังสือเล่มนี้แล้วในระบบ ไม่สามารถบันทึกซ้ำได้:\\n$dup_list_str');
        window.history.back();
        </script>
        ";
        exit;
    }

    $inserted_count = 0;
    $affected_categories = [];

    // บันทึกเฉพาะเล่มที่ไม่ซ้ำเข้าฐานข้อมูล
    // หาค่า id สูงสุดเดิมของทั้ง 2 ตารางเตรียมไว้เพื่อรัน id ต่อเนื่อง
    $res_ab = mysqli_query($conn, "SELECT COALESCE(MAX(add_book_id), 0) AS max_id FROM add_book");
    $row_ab = mysqli_fetch_assoc($res_ab);
    $next_add_book_id = intval($row_ab['max_id']);

    $res_b = mysqli_query($conn, "SELECT COALESCE(MAX(Book_id), 0) AS max_id FROM book");
    $row_b = mysqli_fetch_assoc($res_b);
    $next_book_id = intval($row_b['max_id']);

    // บันทึกเฉพาะเล่มที่ไม่ซ้ำเข้าฐานข้อมูล
    foreach($valid_entries as $entry){
        $t = $entry['title'];
        $a = $entry['author'];
        $p = $entry['publisher'];
        $b = $entry['blurb'];
        $img = $entry['image'];
        $cat_id = $entry['cat_id'];

        $next_add_book_id++;
        $next_book_id++;

        // ใส่ add_book_id เข้าไปด้วยเพื่อไม่ให้ติด Error default value
        mysqli_query($conn, "
            INSERT INTO add_book (add_book_id, Title, Author, Publisher, Blurb, image, Category_id)
            VALUES ($next_add_book_id, '$t', '$a', '$p', '$b', '$img', '$cat_id')
        ");

        // ใส่ Book_id เข้าไปด้วยเพื่อความสมบูรณ์
        mysqli_query($conn, "
            INSERT INTO book (Book_id, Title, Author, Publisher, Blurb, image, Category_id)
            VALUES ($next_book_id, '$t', '$a', '$p', '$b', '$img', '$cat_id')
        ");

        $inserted_count++;
        $affected_categories[$cat_id] = true;
    }

    // ส่งคำขอไปให้ AI API บน Render ประมวลผลเบื้องหลัง
    foreach(array_keys($affected_categories) as $cid){
        $api_url = "https://thai-novel-ai-api.onrender.com/calculate?category_id=" . intval($cid);
        $ctx = stream_context_create([
            'http' => ['timeout' => 1, 'ignore_errors' => true]
        ]);
        @file_get_contents($api_url, false, $ctx);
    }

    // ข้อความแจ้งเตือนสรุปผล
    $msg = "✅ บันทึกสำเร็จ: $inserted_count เล่ม";
    if(!empty($duplicate_titles)){
        $dup_list_str = implode(', ', array_unique($duplicate_titles));
        $msg .= "\\n\\n⚠️ ข้ามหนังสือที่มีชื่อซ้ำในระบบแล้ว:\\n$dup_list_str";
    }

    echo "
    <script>
    alert('$msg');
    window.location='admin_books.php';
    </script>
    ";
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เพิ่มหนังสือใหม่ — THAI Novel Book Admin</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">

<style>
:root {
  --text-primary: #252244;
  --text-muted: #746f91;
  --accent-color: #ff5e7e;
  --glass-bg: rgba(255, 255, 255, 0.55);
  --glass-border: rgba(255, 255, 255, 0.88);
  --card-bg: rgba(255, 255, 255, 0.82);
}

* { box-sizing: border-box; }

body {
  margin: 0;
  font-family: 'Prompt', sans-serif;
  color: var(--text-primary);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  background-color: #f2ecf9;
  background-image: 
    radial-gradient(circle at 8% 15%, rgba(255, 185, 130, 0.82) 0%, transparent 45%),
    radial-gradient(circle at 92% 12%, rgba(246, 194, 255, 0.8) 0%, transparent 45%),
    radial-gradient(circle at 88% 85%, rgba(193, 205, 255, 0.85) 0%, transparent 55%),
    radial-gradient(circle at 10% 90%, rgba(255, 212, 179, 0.75) 0%, transparent 45%),
    radial-gradient(circle at 50% 45%, rgba(244, 238, 253, 0.94) 0%, transparent 100%);
  background-attachment: fixed;
  -webkit-font-smoothing: antialiased;
}

.page-wrapper {
  max-width: 1200px;
  width: 95%;
  margin: 20px auto;
  background: var(--glass-bg);
  backdrop-filter: blur(32px);
  -webkit-backdrop-filter: blur(32px);
  border: 1.5px solid var(--glass-border);
  border-radius: 36px;
  box-shadow: 0 24px 60px rgba(113, 93, 158, 0.14);
  padding: 30px 36px;
  display: flex;
  flex-direction: column;
}

.header-area {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 26px;
  padding-bottom: 18px;
  border-bottom: 1.5px dashed rgba(133, 122, 177, 0.22);
  flex-wrap: wrap;
  gap: 16px;
}

.nav-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #ffffff 0%, #f7f3fd 100%);
  border: 1.5px solid rgba(255, 255, 255, 0.95);
  border-radius: 50px;
  padding: 8.5px 22px;
  text-decoration: none;
  color: var(--text-primary);
  font-size: 14px;
  font-weight: 600;
  box-shadow: 0 4px 14px rgba(150, 135, 185, 0.15);
  transition: all 0.22s ease;
}

.nav-back-btn i {
  font-size: 14px;
  color: #ff758c;
  transition: transform 0.2s ease;
}

.nav-back-btn:hover {
  transform: translateY(-2px);
  background: #ffffff;
  color: #ff758c;
  box-shadow: 0 8px 20px rgba(255, 117, 140, 0.25);
}

.nav-back-btn:hover i {
  transform: translateX(-3px);
}

.header-title-box {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.85) 0%, rgba(255, 255, 255, 0.5) 100%);
  border: 1.5px solid rgba(255, 255, 255, 0.9);
  padding: 6px 20px 6px 8px;
  border-radius: 50px;
  box-shadow: 0 6px 18px rgba(125, 105, 168, 0.08);
}

.section-icon-glow {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 16px;
  background: linear-gradient(135deg, #ffd4b8 0%, #ffa5b9 100%);
  box-shadow: 0 3px 10px rgba(255, 120, 140, 0.35);
  flex-shrink: 0;
}

.header-title-box h2 {
  font-size: 18px;
  font-weight: 700;
  margin: 0;
  color: var(--text-primary);
}

.book-entry {
  background: var(--card-bg);
  border: 1.5px solid var(--glass-border);
  border-radius: 24px;
  padding: 22px 24px;
  margin-bottom: 22px;
  box-shadow: 0 10px 24px rgba(130, 115, 170, 0.08);
  position: relative;
  transition: all 0.25s ease;
}

.book-entry:hover {
  box-shadow: 0 14px 30px rgba(115, 95, 160, 0.12);
}

.entry-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
  padding-bottom: 10px;
  border-bottom: 1px dashed rgba(133, 122, 177, 0.2);
}

.entry-card-title {
  font-size: 13.5px;
  font-weight: 700;
  color: #523a9e;
  display: flex;
  align-items: center;
  gap: 6px;
}

.btn-remove {
  background: #fff0f3;
  color: #e05375;
  border: 1px solid #ffd6df;
  padding: 4px 14px;
  border-radius: 50px;
  cursor: pointer;
  font-family: inherit;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all 0.2s ease;
}

.btn-remove:hover {
  background: #ffe3e8;
  transform: translateY(-1px);
  box-shadow: 0 3px 8px rgba(224, 83, 117, 0.2);
}

.grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.full-width {
  grid-column: 1 / -1;
}

.label-wrap {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 6px;
}

label {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--text-primary);
}

.btn-toggle-input {
  background: linear-gradient(135deg, #ffffff 0%, #f7f3fd 100%);
  border: 1px solid rgba(140, 130, 194, 0.3);
  padding: 3px 10px;
  border-radius: 50px;
  font-size: 11.5px;
  font-weight: 600;
  color: #6c52b5;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s ease;
  box-shadow: 0 2px 6px rgba(130, 115, 175, 0.08);
}

.btn-toggle-input:hover {
  background: #ece3f7;
  border-color: #6c52b5;
  transform: translateY(-1px);
}

.btn-toggle-input.active-cancel {
  background: #fff0f3;
  color: #e05375;
  border-color: #ffd6df;
}

input, select, textarea {
  width: 100%;
  padding: 10px 14px;
  border-radius: 14px;
  border: 1.5px solid rgba(130, 115, 175, 0.28);
  font-family: 'Prompt', sans-serif;
  font-size: 13.5px;
  background: rgba(255, 255, 255, 0.95);
  color: var(--text-primary);
  outline: none;
  box-shadow: 0 2px 6px rgba(110, 95, 155, 0.04);
  transition: all 0.2s ease;
}

input:focus, select:focus, textarea:focus {
  background: #ffffff;
  border-color: #6c52b5;
  box-shadow: 0 4px 14px rgba(108, 82, 181, 0.18);
}

input::placeholder, textarea::placeholder {
  color: #928ca8;
}

textarea {
  resize: vertical;
  min-height: 80px;
  line-height: 1.6;
}

.searchable-select {
  position: relative;
  user-select: none;
}

.searchable-trigger {
  width: 100%;
  padding: 10px 14px;
  border-radius: 14px;
  border: 1.5px solid rgba(130, 115, 175, 0.28);
  background: rgba(255, 255, 255, 0.95);
  color: var(--text-primary);
  font-size: 13.5px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(110, 95, 155, 0.04);
  transition: all 0.2s ease;
}

.searchable-trigger:hover {
  background: #ffffff;
  border-color: #8c82c2;
}

.searchable-trigger i {
  font-size: 11px;
  color: #746f91;
  transition: transform 0.2s ease;
}

.searchable-select.open .searchable-trigger i {
  transform: rotate(180deg);
}

.searchable-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  width: 100%;
  background: #ffffff;
  border: 1.5px solid rgba(130, 115, 175, 0.35);
  border-radius: 18px;
  box-shadow: 0 12px 30px rgba(115, 95, 160, 0.2);
  z-index: 999;
  padding: 8px;
}

.searchable-select.open .searchable-dropdown {
  display: block;
  animation: dropdownFade 0.18s cubic-bezier(0.2, 0.8, 0.2, 1);
}

@keyframes dropdownFade {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}

.searchable-search-box {
  position: relative;
  margin-bottom: 6px;
}

.searchable-search-box i {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 12px;
  color: #8c82a5;
  pointer-events: none;
}

.searchable-search-box input {
  padding: 6.5px 10px 6.5px 30px;
  border-radius: 10px;
  font-size: 12.5px;
  border: 1.2px solid rgba(130, 115, 175, 0.25);
  background: #faf8fd;
}

.searchable-options-list {
  max-height: 200px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.searchable-option {
  padding: 8px 12px;
  font-size: 13px;
  border-radius: 10px;
  cursor: pointer;
  color: var(--text-primary);
  transition: all 0.15s ease;
}

.searchable-option:hover {
  background: #f2eafd;
  color: #6c52b5;
  font-weight: 600;
}

.searchable-option.selected {
  background: #6c52b5;
  color: #ffffff;
  font-weight: 600;
}

.searchable-option.opt-new {
  color: #d96b27;
  font-weight: 700;
  border-top: 1px dashed rgba(133, 122, 177, 0.25);
  margin-top: 4px;
  padding-top: 8px;
}

.searchable-option.opt-new:hover {
  background: #fff3e8;
  color: #d96b27;
}

.action-footer {
  margin-top: 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 14px;
}

.btn-add-row {
  background: linear-gradient(135deg, #ffffff 0%, #f7f3fd 100%);
  color: var(--text-primary);
  border: 1.5px solid rgba(133, 122, 177, 0.35);
  box-shadow: 0 4px 14px rgba(133, 122, 177, 0.12);
  padding: 10px 24px;
  border-radius: 50px;
  cursor: pointer;
  font-family: 'Prompt', sans-serif;
  font-size: 14px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.22s ease;
}

.btn-add-row i {
  color: #ff758c;
  font-size: 15px;
}

.btn-add-row:hover {
  background: #ffffff;
  color: #6c52b5;
  border-color: #6c52b5;
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(108, 82, 181, 0.18);
}

.btn-submit {
  background: linear-gradient(135deg, #302b63 0%, #24243e 100%);
  color: #ffffff;
  border: none;
  box-shadow: 0 4px 14px rgba(48, 43, 99, 0.28);
  padding: 11px 34px;
  border-radius: 50px;
  cursor: pointer;
  font-family: 'Prompt', sans-serif;
  font-size: 14.5px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.22s ease;
}

.btn-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(48, 43, 99, 0.38);
}

@media(max-width: 860px){
  .grid { grid-template-columns: 1fr; }
  .page-wrapper { padding: 22px; border-radius: 26px; }
  .header-area { flex-direction: column-reverse; align-items: stretch; gap: 14px; }
  .btn-submit { width: 100%; justify-content: center; }
  .btn-add-row { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<div class="page-wrapper">
  <header class="header-area">
    <a href="admin_books.php" class="nav-back-btn">
      <i class="fi fi-rr-arrow-left"></i>
      <span>กลับหน้าจัดการ</span>
    </a>
    
    <div class="header-title-box">
      <div class="section-icon-glow">
        <i class="fi fi-rr-book-alt"></i>
      </div>
      <h2>เพิ่มหนังสือใหม่</h2>
    </div>
  </header>

  <form method="post" id="addBookForm">
    <div id="books-container">
      <div class="book-entry">
        <div class="entry-card-header">
          <div class="entry-card-title">
            <i class="fi fi-rr-book-bookmark"></i> ข้อมูลหนังสือเล่มที่ 1
          </div>
        </div>

        <div class="grid">
          <div>
            <label>ชื่อหนังสือ *</label>
            <input type="text" name="Title[]" placeholder="กรอกชื่อเรื่อง" required>
          </div>

          <div>
            <div class="label-wrap">
              <label>ผู้แต่ง *</label>
              <button type="button" class="btn-toggle-input" onclick="toggleField(this, 'author')">
                <i class="fi fi-rr-plus"></i> เพิ่มใหม่
              </button>
            </div>
            
            <div class="field-select-wrap">
              <div class="searchable-select" data-type="author">
                <input type="hidden" name="Author[]" class="hidden-field-val" required>
                <div class="searchable-trigger" onclick="toggleSearchable(this)">
                  <span class="trigger-label">-- เลือกผู้แต่ง --</span>
                  <i class="fi fi-rr-angle-small-down"></i>
                </div>
                <div class="searchable-dropdown">
                  <div class="searchable-search-box">
                    <i class="fi fi-rr-search"></i>
                    <input type="text" placeholder="พิมพ์ค้นหาผู้แต่ง..." oninput="filterSearchableOptions(this)">
                  </div>
                  <div class="searchable-options-list">
                    <?php foreach($authors as$a): ?>
                      <div class="searchable-option" data-value="<?php echo htmlspecialchars($a); ?>" onclick="selectSearchableOption(this, '<?php echo htmlspecialchars(addslashes($a)); ?>')">
                        <?php echo htmlspecialchars($a); ?>
                      </div>
                    <?php endforeach; ?>
                    <div class="searchable-option opt-new" data-value="__NEW__" onclick="selectSearchableOption(this, '__NEW__')">
                      + เพิ่มผู้แต่งใหม่...
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="field-input-wrap" style="display:none;">
              <input type="text" placeholder="พิมพ์ชื่อผู้แต่งใหม่..." disabled>
            </div>
          </div>

          <div>
            <div class="label-wrap">
              <label>สำนักพิมพ์ *</label>
              <button type="button" class="btn-toggle-input" onclick="toggleField(this, 'publisher')">
                <i class="fi fi-rr-plus"></i> เพิ่มใหม่
              </button>
            </div>

            <div class="field-select-wrap">
              <div class="searchable-select" data-type="publisher">
                <input type="hidden" name="Publisher[]" class="hidden-field-val" required>
                <div class="searchable-trigger" onclick="toggleSearchable(this)">
                  <span class="trigger-label">-- เลือกสำนักพิมพ์ --</span>
                  <i class="fi fi-rr-angle-small-down"></i>
                </div>
                <div class="searchable-dropdown">
                  <div class="searchable-search-box">
                    <i class="fi fi-rr-search"></i>
                    <input type="text" placeholder="พิมพ์ค้นหาสำนักพิมพ์..." oninput="filterSearchableOptions(this)">
                  </div>
                  <div class="searchable-options-list">
                    <?php foreach($publishers as$p): ?>
                      <div class="searchable-option" data-value="<?php echo htmlspecialchars($p); ?>" onclick="selectSearchableOption(this, '<?php echo htmlspecialchars(addslashes($p)); ?>')">
                        <?php echo htmlspecialchars($p); ?>
                      </div>
                    <?php endforeach; ?>
                    <div class="searchable-option opt-new" data-value="__NEW__" onclick="selectSearchableOption(this, '__NEW__')">
                      + เพิ่มสำนักพิมพ์ใหม่...
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="field-input-wrap" style="display:none;">
              <input type="text" placeholder="พิมพ์ชื่อสำนักพิมพ์ใหม่..." disabled>
            </div>
          </div>

          <div>
            <label>URL รูปภาพปก *</label>
            <input type="text" name="image[]" placeholder="ใส่ URL รูปภาพปก" required>
          </div>

          <div>
            <label>หมวดหมู่</label>
            <select name="Category_id[]">
              <?php foreach($categories as$c): ?>
                <option value="<?php echo $c['Category_id']; ?>"><?php echo htmlspecialchars($c['Category_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="full-width">
            <label>คำโปรย *</label>
            <textarea name="Blurb[]" placeholder="กรอกคำโปรยหนังสือ..." required></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="action-footer">
      <button type="button" class="btn-add-row" onclick="addRow()">
        <i class="fi fi-rr-plus"></i> เพิ่มช่องหนังสือ
      </button>
      <button type="submit" name="submit" class="btn-submit">
        <i class="fi fi-rr-disk"></i> บันทึก
      </button>
    </div>
  </form>
</div>

<script>
const categoryOptions = `<?php foreach($categories as $c){ echo "<option value='{$c['Category_id']}'>" . htmlspecialchars($c['Category_name']) . "</option>"; } ?>`;
const authorListItems = `
  <?php foreach($authors as $a){ echo "<div class='searchable-option' data-value='" . htmlspecialchars(addslashes($a)) . "' onclick=\\\"selectSearchableOption(this, '" . htmlspecialchars(addslashes($a)) . "')\\\">" . htmlspecialchars($a) . "</div>"; } ?>
  <div class="searchable-option opt-new" data-value="__NEW__" onclick="selectSearchableOption(this, '__NEW__')">+ เพิ่มผู้แต่งใหม่...</div>
`;
const publisherListItems = `
  <?php foreach($publishers as $p){ echo "<div class='searchable-option' data-value='" . htmlspecialchars(addslashes($p)) . "' onclick=\\\"selectSearchableOption(this, '" . htmlspecialchars(addslashes($p)) . "')\\\">" . htmlspecialchars($p) . "</div>"; } ?>
  <div class="searchable-option opt-new" data-value="__NEW__" onclick="selectSearchableOption(this, '__NEW__')">+ เพิ่มสำนักพิมพ์ใหม่...</div>
`;

document.getElementById('addBookForm').addEventListener('submit', function(e) {
  const titleInputs = document.querySelectorAll('input[name="Title[]"]');
  const seenTitles = {};
  for (let input of titleInputs) {
    const val = input.value.trim().toLowerCase();
    if (val !== '') {
      if (seenTitles[val]) {
        e.preventDefault();
        alert('❌ คุณกรอกชื่อหนังสือซ้ำกันในหน้านี้: «' + input.value.trim() + '»\\nกรุณาตรวจสอบก่อนกดบันทึก');
        input.focus();
        return false;
      }
      seenTitles[val] = true;
    }
  }
});

function toggleSearchable(trigger) {
  const selectBox = trigger.closest('.searchable-select');
  const isOpen = selectBox.classList.contains('open');

  document.querySelectorAll('.searchable-select').forEach(el => el.classList.remove('open'));

  if (!isOpen) {
    selectBox.classList.add('open');
    const searchInput = selectBox.querySelector('.searchable-search-box input');
    if (searchInput) {
      searchInput.value = '';
      filterSearchableOptions(searchInput);
      setTimeout(() => searchInput.focus(), 50);
    }
  }
}

function filterSearchableOptions(searchInput) {
  const filter = searchInput.value.trim().toLowerCase();
  const options = searchInput.closest('.searchable-dropdown').querySelectorAll('.searchable-option');

  options.forEach(opt => {
    if (opt.classList.contains('opt-new')) return;
    const text = opt.innerText.trim().toLowerCase();
    opt.style.display = text.startsWith(filter) ? 'block' : 'none';
  });
}

function selectSearchableOption(optionEl, value) {
  const selectBox = optionEl.closest('.searchable-select');
  const hiddenInput = selectBox.querySelector('.hidden-field-val');
  const triggerLabel = selectBox.querySelector('.trigger-label');
  const type = selectBox.getAttribute('data-type');

  if (value === '__NEW__') {
    selectBox.classList.remove('open');
    const parent = selectBox.closest('div[class*="field-select-wrap"]').parentElement;
    const btn = parent.querySelector('.btn-toggle-input');
    toggleField(btn, type);
    return;
  }

  hiddenInput.value = value;
  triggerLabel.innerText = value ? value : (type === 'author' ? '-- เลือกผู้แต่ง --' : '-- เลือกสำนักพิมพ์ --');

  selectBox.querySelectorAll('.searchable-option').forEach(el => el.classList.remove('selected'));
  optionEl.classList.add('selected');
  selectBox.classList.remove('open');
}

document.addEventListener('click', function(e) {
  if (!e.target.closest('.searchable-select')) {
    document.querySelectorAll('.searchable-select').forEach(el => el.classList.remove('open'));
  }
});

function toggleField(btn, type) {
  const parent = btn.closest('div').parentElement;
  const selectWrap = parent.querySelector('.field-select-wrap');
  const inputWrap = parent.querySelector('.field-input-wrap');
  const hiddenInput = selectWrap.querySelector('.hidden-field-val');
  const inputEl = inputWrap.querySelector('input');

  const fieldName = (type === 'author') ? 'Author[]' : 'Publisher[]';
  const isInputVisible = (inputWrap.style.display !== 'none');

  if (isInputVisible) {
    inputWrap.style.display = 'none';
    inputEl.disabled = true;
    inputEl.required = false;
    inputEl.removeAttribute('name');

    selectWrap.style.display = 'block';
    hiddenInput.disabled = false;
    hiddenInput.required = true;
    hiddenInput.name = fieldName;
    hiddenInput.value = '';
    selectWrap.querySelector('.trigger-label').innerText = (type === 'author' ? '-- เลือกผู้แต่ง --' : '-- เลือกสำนักพิมพ์ --');

    btn.innerHTML = '<i class="fi fi-rr-plus"></i> เพิ่มใหม่';
    btn.classList.remove('active-cancel');
  } else {
    selectWrap.style.display = 'none';
    hiddenInput.disabled = true;
    hiddenInput.required = false;
    hiddenInput.removeAttribute('name');

    inputWrap.style.display = 'block';
    inputEl.disabled = false;
    inputEl.required = true;
    inputEl.name = fieldName;
    inputEl.value = '';
    inputEl.focus();

    btn.innerHTML = '<i class="fi fi-rr-cross-small"></i> เลือกจากเดิม';
    btn.classList.add('active-cancel');
  }
}

function addRow() {
  const container = document.getElementById('books-container');
  const currentCount = container.querySelectorAll('.book-entry').length + 1;
  const div = document.createElement('div');
  div.className = 'book-entry';
  div.innerHTML = `
    <div class="entry-card-header">
      <div class="entry-card-title">
        <i class="fi fi-rr-book-bookmark"></i> ข้อมูลหนังสือเล่มที่ ${currentCount}
      </div>
      <button type="button" class="btn-remove" onclick="this.closest('.book-entry').remove()">
        <i class="fi fi-rr-cross-small"></i> ลบ
      </button>
    </div>

    <div class="grid">
      <div>
        <label>ชื่อหนังสือ *</label>
        <input type="text" name="Title[]" placeholder="กรอกชื่อเรื่อง" required>
      </div>

      <div>
        <div class="label-wrap">
          <label>ผู้แต่ง *</label>
          <button type="button" class="btn-toggle-input" onclick="toggleField(this, 'author')">
            <i class="fi fi-rr-plus"></i> เพิ่มใหม่
          </button>
        </div>
        <div class="field-select-wrap">
          <div class="searchable-select" data-type="author">
            <input type="hidden" name="Author[]" class="hidden-field-val" required>
            <div class="searchable-trigger" onclick="toggleSearchable(this)">
              <span class="trigger-label">-- เลือกผู้แต่ง --</span>
              <i class="fi fi-rr-angle-small-down"></i>
            </div>
            <div class="searchable-dropdown">
              <div class="searchable-search-box">
                <i class="fi fi-rr-search"></i>
                <input type="text" placeholder="พิมพ์ค้นหาผู้แต่ง..." oninput="filterSearchableOptions(this)">
              </div>
              <div class="searchable-options-list">
                ${authorListItems}
              </div>
            </div>
          </div>
        </div>
        <div class="field-input-wrap" style="display:none;">
          <input type="text" placeholder="พิมพ์ชื่อผู้แต่งใหม่..." disabled>
        </div>
      </div>

      <div>
        <div class="label-wrap">
          <label>สำนักพิมพ์ *</label>
          <button type="button" class="btn-toggle-input" onclick="toggleField(this, 'publisher')">
            <i class="fi fi-rr-plus"></i> เพิ่มใหม่
          </button>
        </div>
        <div class="field-select-wrap">
          <div class="searchable-select" data-type="publisher">
            <input type="hidden" name="Publisher[]" class="hidden-field-val" required>
            <div class="searchable-trigger" onclick="toggleSearchable(this)">
              <span class="trigger-label">-- เลือกสำนักพิมพ์ --</span>
              <i class="fi fi-rr-angle-small-down"></i>
            </div>
            <div class="searchable-dropdown">
              <div class="searchable-search-box">
                <i class="fi fi-rr-search"></i>
                <input type="text" placeholder="พิมพ์ค้นหาสำนักพิมพ์..." oninput="filterSearchableOptions(this)">
              </div>
              <div class="searchable-options-list">
                ${publisherListItems}
              </div>
            </div>
          </div>
        </div>
        <div class="field-input-wrap" style="display:none;">
          <input type="text" placeholder="พิมพ์ชื่อสำนักพิมพ์ใหม่..." disabled>
        </div>
      </div>

      <div>
        <label>URL รูปภาพปก *</label>
        <input type="text" name="image[]" placeholder="ใส่ URL รูปภาพปก" required>
      </div>

      <div>
        <label>หมวดหมู่</label>
        <select name="Category_id[]">
          ${categoryOptions}
        </select>
      </div>

      <div class="full-width">
        <label>คำโปรย *</label>
        <textarea name="Blurb[]" placeholder="กรอกคำโปรยหนังสือ..." required></textarea>
      </div>
    </div>
  `;
  container.appendChild(div);
}
</script>

</body>
</html>