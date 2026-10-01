<?php
// =====================================
// การเชื่อมต่อฐานข้อมูล TiDB Cloud / Local
// =====================================
$db_host = getenv("DB_HOST") ?: "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$db_user = getenv("DB_USER") ?: "4JodNqEkbc1nEbH.root";
$db_pass = getenv("DB_PASS") ?: "zF4DHIXiUrHylslj";
$db_name = getenv("DB_NAME") ?: "thai_novel";
$db_port = getenv("DB_PORT") ?: 4000;

$conn = mysqli_init();

// ตรวจสอบและตั้งค่า SSL สำหรับ TiDB Cloud
if ($db_host !== "localhost" && $db_host !== "127.0.0.1") {
    $ca_cert = "/etc/ssl/certs/ca-certificates.crt";
    if (file_exists($ca_cert)) {
        mysqli_ssl_set($conn, NULL, NULL, $ca_cert, NULL, NULL);
    }
}

if (!@mysqli_real_connect($conn, $db_host, $db_user, $db_pass, $db_name, (int)$db_port, NULL, MYSQLI_CLIENT_SSL)) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// =====================================
// รับค่า id หนังสือ (Book_id)
// =====================================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = (int)$_GET['id'];

// =====================================
// ดึงข้อมูลหนังสือหลัก
// =====================================
$book_sql = "
SELECT 
    book.*,
    category.Category_name
FROM book
LEFT JOIN category 
    ON book.Category_id = category.Category_id
WHERE book.Book_id = $id
";

$book_result = mysqli_query($conn, $book_sql);

if (!$book_result || mysqli_num_rows($book_result) == 0) {
    echo "ไม่พบข้อมูลหนังสือ";
    exit();
}

$book = mysqli_fetch_assoc($book_result);

// =====================================
// ดึงข้อมูลหนังสือแนะนำ (Sentence Transformer หมวดหมู่เดียวกัน)
// =====================================
$table = "recommendation_sentence_same_category";
$book_col = "book_id";
$rec_col = "recommend_book_id";
$score_col = "similarity";

$sql = "
SELECT 
    b.Book_id,
    b.Title,
    b.Author,
    b.Publisher,
    b.image,
    c.Category_name,
    r.$score_col AS score
FROM $table r
INNER JOIN book b 
    ON r.$rec_col = b.Book_id
LEFT JOIN category c 
    ON b.Category_id = c.Category_id
WHERE r.$book_col = $id
ORDER BY r.$score_col DESC
LIMIT 10
";

$result = mysqli_query($conn, $sql);

// ฟังก์ชันชุดสไตล์และสีประจำแต่ละหมวดหมู่
function getCategoryStyle($cat_name) {
    if (strpos($cat_name, 'รัก') !== false) {
        return [
            'icon'     => 'fi fi-rr-heart',
            'bg'       => '#fff0f3',
            'text'     => '#e05375',
            'border'   => '#ffd6df',
            'gradient' => 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 99%)'
        ];
    }
    if (strpos($cat_name, 'สยอง') !== false || strpos($cat_name, 'ลึกลับ') !== false) {
        return [
            'icon'     => 'fi fi-rr-ghost',
            'bg'       => '#f3eff8',
            'text'     => '#57476e',
            'border'   => '#57476e',
            'gradient' => 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)'
        ];
    }
    if (strpos($cat_name, 'สืบสวน') !== false) {
        return [
            'icon'     => 'fi fi-rr-search',
            'bg'       => '#eaf2fd',
            'text'     => '#3b78c4',
            'border'   => '#cce0fb',
            'gradient' => 'linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)'
        ];
    }
    if (strpos($cat_name, 'เยาวชน') !== false) {
        return [
            'icon'     => 'fi fi-rr-sparkles',
            'bg'       => '#fff6e5',
            'text'     => '#d9822b',
            'border'   => '#fee6b8',
            'gradient' => 'linear-gradient(135deg, #fbc2eb 0%, #a6c1ee 100%)'
        ];
    }
    if (strpos($cat_name, 'แฟนตาซี') !== false) {
        return [
            'icon'     => 'fi fi-rr-magic-wand',
            'bg'       => '#fbf0ff',
            'text'     => '#9b51e0',
            'border'   => '#f0d3ff',
            'gradient' => 'linear-gradient(135deg, #e0c3fc 0%, #8ec5fc 100%)'
        ];
    }
    if (strpos($cat_name, 'ไซไฟ') !== false) {
        return [
            'icon'     => 'fi fi-rr-rocket-lunch',
            'bg'       => '#e8fbf6',
            'text'     => '#1f9d85',
            'border'   => '#bbf3e5',
            'gradient' => 'linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%)'
        ];
    }
    if (strpos($cat_name, 'วาย') !== false || strpos($cat_name, 'Y') !== false) {
        return [
            'icon'     => 'fi fi-rr-crown',
            'bg'       => '#fff3e8',
            'text'     => '#d96b27',
            'border'   => '#fedbc4',
            'gradient' => 'linear-gradient(135deg, #f6d365 0%, #fda085 100%)'
        ];
    }
    return [
        'icon'     => 'fi fi-rr-bookmark',
        'bg'       => '#f4f4fa',
        'text'     => '#615f8a',
        'border'   => '#e2e1f2',
        'gradient' => 'linear-gradient(135deg, #cfd9df 0%, #e2ebf0 100%)'
    ];
}

$mainCatStyle = getCategoryStyle($book['Category_name'] ?? 'ทั่วไป');
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($book['Title'] ?? 'รายละเอียดหนังสือ'); ?> — THAI Novel Book</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Flaticon UIicons CDN -->
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

* {
  box-sizing: border-box;
}

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
  max-width: 1820px;
  width: 98%;
  margin: 16px auto;
  background: var(--glass-bg);
  backdrop-filter: blur(32px);
  -webkit-backdrop-filter: blur(32px);
  border: 1.5px solid var(--glass-border);
  border-radius: 36px;
  box-shadow: 0 24px 60px rgba(113, 93, 158, 0.14);
  padding: 28px 32px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

/* Header & แถบย้อนกลับ */
.header-area {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 26px;
  width: 100%;
}

.brand-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  transition: all 0.25s ease;
}

.brand-logo:hover {
  transform: translateY(-2px);
}

.brand-icon-box {
  width: 34px;
  height: 34px;
  background: linear-gradient(135deg, #ffd3b6 0%, #ffaaa6 100%);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 15px;
  box-shadow: 0 4px 12px rgba(255, 170, 166, 0.4);
}

.brand-text {
  font-size: 19px;
  font-weight: 800;
  color: #252244;
  letter-spacing: -0.3px;
  line-height: 1;
}

.nav-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  background: linear-gradient(135deg, #ffffff 0%, #f7f3fd 100%);
  border: 1.5px solid rgba(255, 255, 255, 0.95);
  border-radius: 50px;
  padding: 8px 20px;
  text-decoration: none;
  color: var(--text-primary);
  font-size: 13.5px;
  font-weight: 600;
  box-shadow: 0 4px 14px rgba(150, 135, 185, 0.16);
  transition: all 0.25s ease;
}

.nav-back-btn i {
  font-size: 13px;
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

/* กล่องรายละเอียดหนังสือหลัก */
.detail-card {
  background: var(--card-bg);
  border: 1.5px solid var(--glass-border);
  border-radius: 28px;
  padding: 34px;
  display: flex;
  gap: 36px;
  margin-bottom: 38px;
  box-shadow: 0 14px 36px rgba(130, 115, 170, 0.1);
  backdrop-filter: blur(10px);
}

.book-cover-wrapper {
  width: 270px;
  flex-shrink: 0;
}

.book-cover-img {
  width: 100%;
  aspect-ratio: 3 / 4.3;
  border-radius: 20px;
  overflow: hidden;
  background: #f1ebf9;
  object-fit: cover;
  display: block;
  box-shadow: 0 10px 24px rgba(110, 95, 155, 0.18);
  border: 2px solid #ffffff;
}

.book-detail-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.book-main-title {
  font-size: 26px;
  font-weight: 800;
  line-height: 1.35;
  color: var(--text-primary);
  margin: 0 0 16px;
  letter-spacing: -0.2px;
}

.meta-tags-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 16px;
}

.meta-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.9);
  border: 1.2px solid rgba(133, 122, 177, 0.25);
  padding: 7px 18px;
  border-radius: 50px;
  font-size: 13.5px;
  font-weight: 500;
  color: #3b3558;
  text-decoration: none;
  box-shadow: 0 2px 8px rgba(133, 122, 177, 0.08);
  transition: all 0.22s cubic-bezier(0.2, 0.8, 0.2, 1);
  cursor: pointer;
}

.meta-pill:hover {
  transform: translateY(-2px);
  background: #ffffff;
  border-color: #6c52b5;
  color: #6c52b5;
  box-shadow: 0 6px 16px rgba(108, 82, 181, 0.18);
}

.meta-pill b {
  font-weight: 700;
  color: inherit;
}

.meta-pill i {
  font-size: 14px;
  color: #6c52b5;
}

.meta-pill.category-pill {
  border-color: transparent;
  font-weight: 600;
}

.meta-pill.category-pill:hover {
  filter: brightness(0.96);
  border-color: currentColor;
}

.meta-pill.category-pill i {
  color: inherit;
}

/* กล่องคำโปรย */
.blurb-box {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 244, 255, 0.92) 100%);
  border: 1.5px solid rgba(140, 125, 185, 0.45);
  border-radius: 20px;
  padding: 22px 26px;
  margin-top: 6px;
  box-shadow: 0 6px 20px rgba(125, 105, 168, 0.12);
}

.blurb-title {
  font-size: 16.5px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 12px;
  color: #2b244d;
}

.blurb-title i {
  color: #ff5277;
  font-size: 16px;
}

.blurb-text {
  font-size: 15.5px;
  line-height: 1.9;
  color: #373154;
  margin: 0;
  text-align: left;
  word-break: break-word;
  white-space: normal;
  letter-spacing: 0.1px;
}

/* หัวข้อหนังสือแนะนำ */
.section-title-bar {
  display: flex;
  align-items: center;
  margin-bottom: 22px;
}

.section-heading-box {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.85) 0%, rgba(255, 255, 255, 0.5) 100%);
  border: 1.5px solid rgba(255, 255, 255, 0.9);
  padding: 5px 18px 5px 8px;
  border-radius: 50px;
  box-shadow: 0 6px 18px rgba(125, 105, 168, 0.08);
}

.section-icon-glow {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 14px;
  box-shadow: 0 3px 10px rgba(255, 120, 140, 0.3);
  flex-shrink: 0;
}

.section-heading {
  font-size: 18px;
  font-weight: 700;
  margin: 0;
  color: var(--text-primary);
}

/* Grid หนังสือแนะนำ */
.book-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr)) !important;
  column-gap: 16px;
  row-gap: 28px;
  align-items: stretch;
}

.retro-card {
  background: var(--card-bg);
  border: 1.5px solid var(--glass-border);
  border-radius: 22px;
  padding: 14px;
  display: flex;
  flex-direction: column;
  height: 100%;
  transition: all 0.28s cubic-bezier(0.2, 0.8, 0.2, 1);
  box-shadow: 0 10px 24px rgba(130, 115, 170, 0.08);
}

.retro-card:hover {
  transform: translateY(-6px);
  background: rgba(255, 255, 255, 0.94);
  box-shadow: 0 18px 36px rgba(115, 95, 160, 0.18);
}

.card-thumb {
  width: 100%;
  aspect-ratio: 3 / 4.3;
  border-radius: 16px;
  overflow: hidden;
  background: #f1ebf9;
  margin-bottom: 12px;
  box-shadow: 0 6px 14px rgba(0, 0, 0, 0.07);
  flex-shrink: 0;
}

.card-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.tag-badge {
  display: inline-flex;
  align-items: center;
  align-self: flex-start;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 50px;
  margin-bottom: 8px;
  flex-shrink: 0;
}

.card-title {
  font-size: 14.5px;
  font-weight: 600;
  line-height: 20px;
  height: 40px;
  color: var(--text-primary);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  margin-bottom: 8px;
  flex-shrink: 0;
}

.card-author {
  font-size: 12px;
  line-height: 18px;
  height: 18px;
  color: var(--text-muted);
  margin-bottom: 5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: flex;
  align-items: center;
  gap: 5px;
  flex-shrink: 0;
}

.card-btn {
  margin-top: auto;
  display: block;
  text-align: center;
  background: linear-gradient(135deg, #ede7f6 0%, #e1d5f5 100%);
  color: #523a9e;
  border: 1.2px solid #d3c4ee;
  border-radius: 50px;
  padding: 8.5px 12px;
  text-decoration: none;
  font-weight: 600;
  font-size: 13px;
  box-shadow: 0 4px 12px rgba(135, 110, 185, 0.12);
  transition: all 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);
  flex-shrink: 0;
}

.card-btn:hover {
  background: linear-gradient(135deg, #9575cd 0%, #7e57c2 100%);
  color: #ffffff;
  border-color: transparent;
  transform: translateY(-2px);
  box-shadow: 0 8px 18px rgba(126, 87, 194, 0.35);
}

.empty-state {
  grid-column: 1 / -1;
  background: rgba(255, 255, 255, 0.6);
  border-radius: 26px;
  padding: 50px;
  text-align: center;
  color: var(--text-muted);
  font-size: 16px;
}

/* Footer */
.site-footer {
  margin-top: 40px;
  text-align: center;
  padding: 16px 0 0;
  border-top: 1px solid rgba(133, 122, 177, 0.15);
}

.footer-copy {
  font-size: 13px;
  color: var(--text-muted);
}

@media(max-width: 980px){
  .book-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
  .page-wrapper { padding: 20px; border-radius: 26px; }
  .detail-card { flex-direction: column; align-items: center; padding: 24px; gap: 24px; }
  .book-cover-wrapper { width: 200px; }
}

@media(max-width: 680px){
  .book-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
  .header-area { flex-direction: column-reverse; align-items: stretch; gap: 14px; }
  .brand-logo { justify-content: center; }
  .nav-back-btn { justify-content: center; }
}

@media(max-width: 440px){
  .book-grid { grid-template-columns: 1fr !important; }
}
</style>
</head>

<body>

<div class="page-wrapper">

  <!-- Header ด้านบน -->
  <header class="header-area">
    <a href="index.php" class="nav-back-btn">
      <i class="fi fi-rr-arrow-left"></i>
      <span>กลับหน้าหลัก</span>
    </a>

    <a href="index.php" class="brand-logo">
      <div class="brand-icon-box">
        <i class="fi fi-rr-book-bookmark"></i>
      </div>
      <span class="brand-text">THAI Novel Book</span>
    </a>
  </header>

  <!-- รายละเอียดหนังสือหลัก -->
  <section class="detail-card">
    <div class="book-cover-wrapper">
      <img 
        src="<?php echo htmlspecialchars($book['image'] ?? ''); ?>" 
        alt="<?php echo htmlspecialchars($book['Title'] ?? 'ปกหนังสือ'); ?>" 
        class="book-cover-img"
      >
    </div>

    <div class="book-detail-content">
      <h1 class="book-main-title">
        <?php echo htmlspecialchars($book['Title'] ?? ''); ?>
      </h1>

      <div class="meta-tags-row">
        <?php if (!empty($book['Category_id'])) { ?>
          <a href="index.php?category=<?php echo urlencode($book['Category_id']); ?>" 
             class="meta-pill category-pill" 
             style="background: <?php echo $mainCatStyle['bg']; ?>; color: <?php echo $mainCatStyle['text']; ?>; border: 1.2px solid <?php echo $mainCatStyle['border']; ?>;"
             title="คลิกเพื่อดูหนังสือในหมวดหมู่นี้ทั้งหมด">
            <i class="<?php echo $mainCatStyle['icon']; ?>"></i>
            <span><b>หมวดหมู่:</b> <?php echo htmlspecialchars($book['Category_name'] ?? 'ทั่วไป'); ?></span>
          </a>
        <?php } ?>

        <?php if (!empty($book['Author'])) { ?>
          <a href="index.php?authors=<?php echo urlencode($book['Author']); ?>" 
             class="meta-pill"
             title="คลิกเพื่อดูผลงานของผู้แต่งนี้ทั้งหมด">
            <i class="fi fi-rr-pencil"></i>
            <span><b>ผู้แต่ง:</b> <?php echo htmlspecialchars($book['Author']); ?></span>
          </a>
        <?php } ?>

        <?php if (!empty($book['Publisher'])) { ?>
          <a href="index.php?publishers=<?php echo urlencode($book['Publisher']); ?>" 
             class="meta-pill"
             title="คลิกเพื่อดูหนังสือของสำนักพิมพ์นี้ทั้งหมด">
            <i class="fi fi-rr-book"></i>
            <span><b>สำนักพิมพ์:</b> <?php echo htmlspecialchars($book['Publisher']); ?></span>
          </a>
        <?php } ?>
      </div>

      <div class="blurb-box">
        <div class="blurb-title">
          <i class="fi fi-rr-document"></i> คำโปรย
        </div>
        <p class="blurb-text">
          <?php echo nl2br(htmlspecialchars($book['Blurb'] ?? 'ไม่มีข้อมูลเรื่องย่อ')); ?>
        </p>
      </div>
    </div>
  </section>

  <!-- หัวข้อหนังสือแนะนำ -->
  <div class="section-title-bar">
    <div class="section-heading-box">
      <div class="section-icon-glow" style="background: linear-gradient(135deg, #ffd4b8 0%, #ffa5b9 100%);">
        <i class="fi fi-rr-sparkles"></i>
      </div>
      <h2 class="section-heading">หนังสือที่คุณน่าจะสนใจ</h2>
    </div>
  </div>

  <!-- แสดง Grid หนังสือแนะนำ -->
  <div class="book-grid">
    <?php if ($result && mysqli_num_rows($result) > 0) { ?>
      <?php while ($rec = mysqli_fetch_assoc($result)) { 
        $recCatName = $rec['Category_name'] ?? 'ทั่วไป';
        $recCatStyle = getCategoryStyle($recCatName);
      ?>
        <article class="retro-card">
          <div class="card-thumb">
            <img 
              src="<?php echo htmlspecialchars($rec['image'] ?? ''); ?>" 
              alt="<?php echo htmlspecialchars($rec['Title'] ?? 'ปก'); ?>"
            >
          </div>

          <span class="tag-badge" style="background: <?php echo $recCatStyle['bg']; ?>; color: <?php echo $recCatStyle['text']; ?>; border: 1px solid <?php echo $recCatStyle['border']; ?>;">
            <?php echo htmlspecialchars($recCatName); ?>
          </span>

          <div class="card-title" title="<?php echo htmlspecialchars($rec['Title'] ?? ''); ?>">
            <?php echo htmlspecialchars($rec['Title'] ?? ''); ?>
          </div>

          <div class="card-author">
            <span>✎</span>
            <span><?php echo htmlspecialchars($rec['Author'] ?? 'ไม่ระบุ'); ?></span>
          </div>

          <div class="card-author">
            <i class="fi fi-rr-book"></i>
            <span><?php echo htmlspecialchars($rec['Publisher'] ?? 'ไม่ระบุสำนักพิมพ์'); ?></span>
          </div>

          <a href="sentence_same_category.php?id=<?php echo (int)$rec['Book_id']; ?>" class="card-btn">
            ดูรายละเอียด
          </a>
        </article>
      <?php } ?>
    <?php } else { ?>
      <div class="empty-state">
        ยังไม่มีข้อมูลหนังสือแนะนำสำหรับเล่มนี้
      </div>
    <?php } ?>
  </div>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="footer-copy">
      © <?php echo date('Y'); ?> THAI Novel Book
    </div>
  </footer>

</div>

</body>
</html>
