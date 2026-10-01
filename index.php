<?php
include "db.php";

$keyword     = $_GET['keyword'] ?? '';
$search_type = $_GET['search_type'] ?? 'keyword';
$category    = $_GET['category'] ?? '';

// รองรับทั้งแบบ array หรือ comma-separated string จาก URL
$authors_param    = $_GET['authors'] ?? ($_GET['author'] ?? '');
$publishers_param = $_GET['publishers'] ?? ($_GET['publisher'] ?? '');

$selected_authors = array_filter(array_map('trim', explode(',', $authors_param)));
$selected_publishers = array_filter(array_map('trim', explode(',', $publishers_param)));

// จัดการหน้าปัจจุบัน (Pagination)
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

$where_sql = " WHERE 1 ";

// ประมวลผลการค้นหาตามประเภทที่เลือกจาก Dropdown
if($keyword != ""){
    $escaped_keyword = mysqli_real_escape_string($conn, $keyword);
    if($search_type == "title"){
        $where_sql .= " AND book.Title LIKE '%$escaped_keyword%'";
    } elseif($search_type == "author"){
        $where_sql .= " AND book.Author LIKE '%$escaped_keyword%'";
    } elseif($search_type == "publisher"){
        $where_sql .= " AND book.Publisher LIKE '%$escaped_keyword%'";
    } else {
        $where_sql .= " AND (
            book.Title LIKE '%$escaped_keyword%' 
            OR book.Blurb LIKE '%$escaped_keyword%' 
            OR book.Author LIKE '%$escaped_keyword%' 
            OR book.Publisher LIKE '%$escaped_keyword%' 
            OR category.Category_name LIKE '%$escaped_keyword%'
        )";
    }
}

if($category != ""){
    $escaped_category = mysqli_real_escape_string($conn, $category);
    $where_sql .= " AND book.Category_id='$escaped_category'";
}

// กรองผู้แต่งได้หลายคนพร้อมกัน (IN)
if(!empty($selected_authors)){
    $escaped_authors = array_map(function($a) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $a) . "'";
    }, $selected_authors);
    $where_sql .= " AND book.Author IN (" . implode(',', $escaped_authors) . ")";
}

// กรองสำนักพิมพ์ได้หลายแห่งพร้อมกัน (IN)
if(!empty($selected_publishers)){
    $escaped_publishers = array_map(function($p) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $p) . "'";
    }, $selected_publishers);
    $where_sql .= " AND book.Publisher IN (" . implode(',', $escaped_publishers) . ")";
}

$is_home = ($keyword == "" && $category == "" && empty($selected_authors) && empty($selected_publishers) && !isset($_GET['all']));

// นับจำนวนหนังสือทั้งหมด
$count_sql = "
SELECT COUNT(*) AS total
FROM book
LEFT JOIN category ON book.Category_id = category.Category_id
$where_sql
";
$count_res = mysqli_query($conn, $count_sql);
$total_books = $count_res ? (int)mysqli_fetch_assoc($count_res)['total'] : 0;
$total_pages = ceil($total_books / $per_page);

// ดึงรายการหนังสือ
$sql = "
SELECT 
book.*,
category.Category_name
FROM book
LEFT JOIN category
ON book.Category_id = category.Category_id
$where_sql
";

if($is_home){
    $sql .= " ORDER BY RAND() LIMIT 10";
} else {
    // เรียงตัวอักษรไทยขึ้นก่อน แล้วต่อด้วยอังกฤษ/ตัวเลข พร้อมตัดแบ่งหน้าละ 10 เล่ม
    $sql .= " ORDER BY (book.Title REGEXP '^[ก-๙]') DESC, book.Title ASC LIMIT $offset, $per_page";
}

$result = mysqli_query($conn, $sql);

$current_category_name = '';
if($category != ""){
    $cat_query = mysqli_query($conn, "SELECT Category_name FROM category WHERE Category_id='" . mysqli_real_escape_string($conn, $category) . "' LIMIT 1");
    if($cat_row = mysqli_fetch_assoc($cat_query)){
        $current_category_name = $cat_row['Category_name'];
    }
}

$categories = mysqli_query($conn, "SELECT * FROM category ORDER BY (Category_name REGEXP '^[ก-๙]') DESC, Category_name");

$filter_cat_sql = "";
if($category != ""){
    $filter_cat_sql = " AND Category_id='" . mysqli_real_escape_string($conn, $category) . "'";
}

$authors_sql = "
    SELECT DISTINCT Author 
    FROM book 
    WHERE Author IS NOT NULL AND Author != '' $filter_cat_sql 
    ORDER BY (Author REGEXP '^[ก-๙]') DESC, Author ASC
";
$authors = mysqli_query($conn, $authors_sql);

$publishers_sql = "
    SELECT DISTINCT Publisher 
    FROM book 
    WHERE Publisher IS NOT NULL AND Publisher != '' $filter_cat_sql 
    ORDER BY (Publisher REGEXP '^[ก-๙]') DESC, Publisher ASC
";
$publishers = mysqli_query($conn, $publishers_sql);

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

$current_heading_icon = 'fi fi-rr-book-alt';
$current_heading_gradient = 'linear-gradient(135deg, #ffd4b8 0%, #ffa5b9 100%)';
$current_highlight_color = '#ff5277';

if ($category != "" && !empty($current_category_name)) {
    $active_style = getCategoryStyle($current_category_name);
    $current_heading_icon = $active_style['icon'];
    $current_heading_gradient = $active_style['gradient'];
    $current_highlight_color = $active_style['text'];
}

// ฟังก์ชันสร้าง URL สำหรับ Pagination โดยเก็บค่าตัวกรองเดิมไว้ทั้งหมด
function getPageUrl($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>THAI Novel Book — ค้นพบหนังสือเล่มโปรด</title>

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

.header-area {
  display: flex;
  align-items: center;
  gap: 30px;
  margin-bottom: 26px;
  width: 100%;
}

.brand-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-shrink: 0;
  text-decoration: none;
  transition: all 0.25s ease;
}

.brand-logo:hover {
  transform: translateY(-2px);
}

.brand-icon-box {
  width: 44px;
  height: 44px;
  background: linear-gradient(135deg, #ffd3b6 0%, #ffaaa6 100%);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 20px;
  box-shadow: 0 6px 16px rgba(255, 170, 166, 0.45);
  transition: transform 0.25s ease;
}

.brand-logo:hover .brand-icon-box {
  transform: rotate(-6deg) scale(1.05);
}

.brand-text {
  font-size: 27px;
  font-weight: 800;
  color: #252244;
  letter-spacing: -0.3px;
  line-height: 1;
  user-select: none;
}

.search-wrapper {
  flex: 1;
  max-width: 680px;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
  background: rgba(255, 255, 255, 0.85);
  border: 1.5px solid rgba(255, 255, 255, 0.95);
  border-radius: 50px;
  padding: 4px 6px 4px 6px;
  box-shadow: 0 6px 20px rgba(133, 122, 177, 0.1);
  transition: all 0.25s;
  gap: 10px;
}

.search-box:focus-within {
  background: rgba(255, 255, 255, 0.98);
  border-color: #8c82c2;
  box-shadow: 0 8px 25px rgba(140, 130, 194, 0.22);
}

.custom-select-wrapper {
  position: relative;
  user-select: none;
}

.custom-select-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #f3ecfb;
  padding: 7px 16px 7px 14px;
  border-radius: 50px;
  border: 1px solid rgba(140, 130, 194, 0.25);
  cursor: pointer;
  transition: all 0.22s ease;
  font-size: 13.5px;
  font-weight: 600;
  color: #2e2850;
  box-shadow: 0 2px 6px rgba(130, 115, 175, 0.08);
}

.custom-select-btn:hover {
  background: #ece3f7;
  border-color: #7b65c2;
}

.custom-select-btn i.icon-prefix {
  font-size: 14px;
  color: #6c52b5;
}

.custom-select-btn i.icon-arrow {
  font-size: 11px;
  color: #746f91;
  transition: transform 0.25s ease;
}

.custom-select-wrapper.open .custom-select-btn i.icon-arrow {
  transform: rotate(180deg);
}

.custom-select-options {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  min-width: 170px;
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-radius: 18px;
  border: 1.5px solid rgba(255, 255, 255, 0.95);
  box-shadow: 0 14px 34px rgba(115, 95, 160, 0.18);
  padding: 6px;
  display: none;
  flex-direction: column;
  gap: 4px;
  z-index: 9999;
}

.custom-select-wrapper.open .custom-select-options {
  display: flex;
  animation: dropdownFade 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
}

@keyframes dropdownFade {
  from { opacity: 0; transform: translateY(-6px); }
  to { opacity: 1; transform: translateY(0); }
}

.custom-option {
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 500;
  color: #2c274b;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.18s ease;
  display: flex;
  align-items: center;
  gap: 8px;
}

.custom-option:hover {
  background: #f2eafd;
  color: #6c52b5;
  font-weight: 600;
  transform: translateX(3px);
}

.custom-option.selected {
  background: linear-gradient(135deg, #6c52b5 0%, #523a9e 100%);
  color: #ffffff;
  font-weight: 600;
}

.search-input-group {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  padding-left: 2px;
}

.search-input-group i.input-search-icon {
  font-size: 14px;
  color: #8c82a5;
  flex-shrink: 0;
  display: flex;
  align-items: center;
}

.search-input-group input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  font-family: 'Prompt', sans-serif;
  color: var(--text-primary);
  padding: 6px 0;
}

.search-input-group input::placeholder {
  color: #928ca8;
}

.search-btn {
  background: linear-gradient(135deg, #302b63 0%, #24243e 100%);
  color: #ffffff;
  border: none;
  padding: 9px 24px;
  border-radius: 50px;
  font-size: 14px;
  font-family: 'Prompt', sans-serif;
  font-weight: 500;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(48, 43, 99, 0.25);
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.search-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(48, 43, 99, 0.35);
}

.main-layout {
  display: flex;
  gap: 28px;
  align-items: flex-start;
  flex: 1;
  width: 100%;
}

.sidebar {
  width: 275px;
  background: rgba(255, 255, 255, 0.48);
  border: 1.5px solid rgba(255, 255, 255, 0.85);
  border-radius: 28px;
  padding: 22px 16px;
  position: sticky;
  top: 20px;
  flex-shrink: 0;
  box-shadow: 0 12px 32px rgba(120, 100, 160, 0.08);
}

.nav-home {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: linear-gradient(135deg, #ffffff 0%, #f7f3fd 100%);
  border: 1px solid rgba(255, 255, 255, 0.95);
  border-radius: 50px;
  padding: 10px 14px;
  text-decoration: none;
  color: var(--text-primary);
  font-size: 14.5px;
  font-weight: 600;
  margin-bottom: 20px;
  box-shadow: 0 6px 18px rgba(150, 135, 185, 0.15);
  transition: all 0.25s;
}

.nav-home i {
  font-size: 16px;
  color: #ff758c;
}

.nav-home:hover {
  transform: translateY(-2px);
  background: #ffffff;
  color: #ff758c;
  box-shadow: 0 10px 24px rgba(255, 117, 140, 0.22);
}

.sidebar h3 {
  font-size: 14.5px;
  font-weight: 700;
  margin: 0 0 12px;
  display: flex;
  align-items: center;
  gap: 9px;
  color: var(--text-primary);
}

.title-icon-badge {
  width: 28px;
  height: 28px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13.5px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.badge-cat { background: #ffe3e3; color: #ff5e7e; }
.badge-author { background: #e0ecff; color: #3574e3; }
.badge-pub { background: #daf4e0; color: #219653; }

.category-tags {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.cat-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
  padding: 8.5px 13px;
  border-radius: 15px;
  font-size: 13px;
  font-weight: 500;
  border: 1.5px solid transparent;
  transition: all 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.cat-btn i {
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cat-btn:hover {
  transform: translateX(3px);
  box-shadow: 0 4px 12px rgba(120, 100, 160, 0.1);
  filter: brightness(0.97);
}

.cat-btn.active {
  font-weight: 700;
  box-shadow: 0 6px 16px rgba(120, 100, 160, 0.16);
  outline: 2px solid currentColor;
}

.sidebar hr {
  border: none;
  border-top: 1.5px dashed rgba(133, 122, 177, 0.2);
  margin: 18px 0;
}

.filter-section-card {
  background: rgba(255, 255, 255, 0.55);
  border: 1.5px solid rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  padding: 14px;
  box-shadow: 0 8px 20px rgba(118, 97, 160, 0.07);
}

.filter-search-box {
  position: relative;
  display: flex;
  align-items: center;
  margin-bottom: 10px;
}

.filter-search-box i {
  position: absolute;
  left: 14px;
  font-size: 13.5px;
  color: #635599;
  pointer-events: none;
}

.author-search-input {
  width: 100%;
  padding: 9px 14px 9px 36px;
  border-radius: 50px;
  border: 1.5px solid rgba(130, 115, 175, 0.35);
  outline: none;
  font-size: 12.5px;
  font-family: inherit;
  font-weight: 500;
  background: #ffffff;
  color: var(--text-primary);
  box-shadow: 0 2px 8px rgba(110, 95, 155, 0.08);
  transition: all 0.22s ease;
}

.author-search-input::placeholder {
  color: #8b83a6;
}

.author-search-input:focus {
  background: #ffffff;
  border-color: #6c52b5;
  box-shadow: 0 4px 16px rgba(108, 82, 181, 0.25);
}

.author-list {
  max-height: 185px;
  overflow-y: auto;
  padding-right: 4px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.author-list::-webkit-scrollbar {
  width: 7px;
}

.author-list::-webkit-scrollbar-track {
  background: transparent;
}

.author-list::-webkit-scrollbar-thumb {
  background: #9d8ec2;
  border-radius: 10px;
}

.author-list::-webkit-scrollbar-thumb:hover {
  background: #8472af;
}

.author-list::-webkit-scrollbar-button:single-button {
  display: block;
  height: 12px;
  width: 7px;
  background-size: 7px 6px;
  background-repeat: no-repeat;
  background-position: center;
}

.author-list::-webkit-scrollbar-button:single-button:vertical:decrement {
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='8' viewBox='0 0 10 8'><polygon points='5,0 10,8 0,8' fill='%239d8ec2'/></svg>");
}

.author-list::-webkit-scrollbar-button:single-button:vertical:increment {
  background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='10' height='8' viewBox='0 0 10 8'><polygon points='0,0 10,0 5,8' fill='%239d8ec2'/></svg>");
}

.author-label {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  font-weight: 500;
  padding: 7px 11px;
  border-radius: 12px;
  cursor: pointer;
  color: #2c274b;
  background: #ffffff;
  border: 1.5px solid rgba(220, 214, 235, 0.85);
  box-shadow: 0 2px 6px rgba(115, 95, 160, 0.05);
  transition: all 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
  user-select: none;
}

.author-label input[type="checkbox"] {
  appearance: none;
  -webkit-appearance: none;
  width: 18px;
  height: 18px;
  border: 1.8px solid #8e80be;
  border-radius: 5px;
  outline: none;
  cursor: pointer;
  position: relative;
  background: #fbf9fe;
  transition: all 0.18s ease;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.author-label input[type="checkbox"]:checked {
  background: linear-gradient(135deg, #6c52b5 0%, #523a9e 100%) !important;
  border-color: #523a9e !important;
  box-shadow: 0 2px 6px rgba(82, 58, 158, 0.35);
}

.author-label input[type="checkbox"]:checked::after {
  content: '';
  display: block;
  width: 4px;
  height: 8px;
  border: solid #ffffff;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg);
  margin-top: -2px;
}

.author-label:hover {
  background: #ffffff;
  color: #1a1636;
  border-color: #7b65c2;
  transform: translateX(3px);
  box-shadow: 0 4px 12px rgba(108, 82, 181, 0.14);
}

.author-label.active {
  background: #ffffff;
  border-color: #6c52b5;
  color: #1a1636;
  font-weight: 700;
  box-shadow: 0 4px 14px rgba(108, 82, 181, 0.2);
}

.content-area {
  flex: 1;
  min-width: 0;
}

.section-title-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 22px;
  gap: 14px;
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
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}

.section-heading span.highlight {
  font-weight: 800;
}

.section-badge {
  font-size: 13px;
  font-weight: 600;
  background: rgba(255, 255, 255, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.95);
  padding: 6px 16px;
  border-radius: 50px;
  color: var(--text-muted);
  box-shadow: 0 4px 12px rgba(130, 115, 165, 0.08);
}

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

/* ==================== สไตล์ปุ่มเปลี่ยนหน้า Pagination ตามรูปตัวอย่าง ==================== */
.pagination-container {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 38px;
  user-select: none;
}

.page-circle-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  color: #2c274b;
  background: #ffffff;
  border: 1.2px solid #2c274b;
  transition: all 0.2s ease;
}

.page-circle-btn:hover {
  transform: translateY(-2px);
  border-color: #6c52b5;
  color: #6c52b5;
  box-shadow: 0 4px 10px rgba(108, 82, 181, 0.15);
}

.page-circle-btn.active {
  border-color: #88c0ec;
  color: #88c0ec;
  font-weight: 700;
  background: #ffffff;
  box-shadow: 0 2px 8px rgba(136, 192, 236, 0.25);
}

.page-circle-btn.page-action-btn {
  background: #95a383;
  color: #ffffff;
  border: none;
  box-shadow: 0 2px 6px rgba(149, 163, 131, 0.35);
  font-size: 16px;
}

.page-circle-btn.page-action-btn:hover {
  background: #849372;
  color: #ffffff;
  transform: translateY(-2px);
}

.page-dots {
  font-size: 15px;
  color: #746f91;
  padding: 0 4px;
}

.site-footer {
  margin-top: 36px;
  text-align: center;
  padding: 14px 0 0;
  border-top: 1px solid rgba(133, 122, 177, 0.15);
}

.footer-copy {
  font-size: 13px;
  color: var(--text-muted);
}

@media(max-width: 980px){
  .book-grid { grid-template-columns: repeat(3, minmax(0, 1fr)) !important; }
  .page-wrapper { padding: 20px; border-radius: 26px; }
  .header-area { flex-direction: column; align-items: stretch; gap: 16px; }
  .main-layout { flex-direction: column; }
  .sidebar { width: 100%; position: static; }
}

@media(max-width: 680px){
  .book-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
  .search-box { flex-wrap: wrap; border-radius: 24px; padding: 10px; }
  .custom-select-wrapper { width: 100%; }
  .custom-select-btn { justify-content: space-between; width: 100%; }
  .search-btn { width: 100%; }
  .pagination-container { gap: 4px; flex-wrap: wrap; }
  .page-circle-btn { width: 30px; height: 30px; font-size: 12px; }
}

@media(max-width: 440px){
  .book-grid { grid-template-columns: 1fr !important; }
}
</style>
</head>

<body>

<div class="page-wrapper">

  <!-- Main Header & Search -->
  <div class="header-area">
    <!-- โค้ดใหม่ที่แก้แล้ว -->
<a href="admin_login.php" class="brand-logo" title="เข้าสู่ระบบผู้ดูแล">
  <div class="brand-icon-box">
    <i class="fi fi-rr-book-bookmark"></i>
  </div>
  <span class="brand-text">THAI Novel Book</span>
</a>

    <div class="search-wrapper">
      <form class="search-box" method="GET" action="index.php">
        <input type="hidden" name="search_type" id="hiddenSearchType" value="<?php echo htmlspecialchars($search_type); ?>">
        
        <div class="custom-select-wrapper" id="customSelectWrapper">
          <div class="custom-select-btn" id="customSelectBtn">
            <i class="fi fi-rr-settings-sliders icon-prefix"></i>
            <span id="selectedTypeText">
              <?php 
                $types_map = ['keyword' => 'Keyword', 'title' => 'Title', 'author' => 'Author', 'publisher' => 'Publisher'];
                echo $types_map[$search_type] ?? 'Keyword';
              ?>
            </span>
            <i class="fi fi-rr-angle-small-down icon-arrow"></i>
          </div>

          <div class="custom-select-options">
            <div class="custom-option <?php echo ($search_type == 'keyword') ? 'selected' : ''; ?>" data-value="keyword">
              <i class="fi fi-rr-apps"></i> Keyword
            </div>
            <div class="custom-option <?php echo ($search_type == 'title') ? 'selected' : ''; ?>" data-value="title">
              <i class="fi fi-rr-book-alt"></i> Title
            </div>
            <div class="custom-option <?php echo ($search_type == 'author') ? 'selected' : ''; ?>" data-value="author">
              <i class="fi fi-rr-pencil"></i> Author
            </div>
            <div class="custom-option <?php echo ($search_type == 'publisher') ? 'selected' : ''; ?>" data-value="publisher">
              <i class="fi fi-rr-building"></i> Publisher
            </div>
          </div>
        </div>

        <div class="search-input-group">
          <i class="fi fi-rr-search input-search-icon"></i>
          <input 
            name="keyword" 
            placeholder="ค้นหาชื่อเรื่อง คำโปรย ผู้แต่ง หรือสำนักพิมพ์..." 
            value="<?php echo htmlspecialchars($keyword); ?>"
          >
        </div>

        <button type="submit" class="search-btn">ค้นหา</button>
      </form>
    </div>
  </div>

  <!-- Layout หลัก -->
  <div class="main-layout">

    <!-- Sidebar ซ้าย -->
    <aside class="sidebar">
      <a href="index.php" class="nav-home">
        <i class="fi fi-rr-home"></i> หน้าแรก
      </a>

      <h3>
        <span class="title-icon-badge badge-cat"><i class="fi fi-rr-apps"></i></span>
        หมวดหมู่
      </h3>
      <div class="category-tags">
        <a href="index.php?all=1" 
           class="cat-btn <?php echo isset($_GET['all']) ? 'active' : ''; ?>"
           style="background: #fdfbf7; color: #4a4563; border-color: #eee9df;">
          <i class="fi fi-rr-list-check" style="color: #635d82;"></i>
          <span>ทั้งหมด</span>
        </a>

        <?php while($c = mysqli_fetch_assoc($categories)){ 
          $isActive = ($category == $c['Category_id']) ? 'active' : '';
          $style = getCategoryStyle($c['Category_name']);
        ?>
          <a href="?category=<?php echo $c['Category_id']; ?>" 
             class="cat-btn <?php echo $isActive; ?>"
             style="background: <?php echo $style['bg']; ?>; color: <?php echo $style['text']; ?>; border-color: <?php echo $style['border']; ?>;">
            <i class="<?php echo $style['icon']; ?>" style="color: <?php echo $style['text']; ?>;"></i>
            <span><?php echo htmlspecialchars($c['Category_name']); ?></span>
          </a>
        <?php } ?>
      </div>

      <hr>

      <!-- การ์ดโซนผู้แต่ง (Multiple Selection) -->
      <div class="filter-section-card">
        <h3>
          <span class="title-icon-badge badge-author"><i class="fi fi-rr-pencil"></i></span>
          ผู้แต่ง
        </h3>
        
        <div class="filter-search-box">
          <i class="fi fi-rr-search"></i>
          <input 
            id="authorSearch" 
            class="author-search-input" 
            placeholder="พิมพ์ค้นหาผู้แต่ง..."
            autocomplete="off"
          >
        </div>

        <div class="author-list" id="authorListContainer">
          <?php 
          if(mysqli_num_rows($authors) > 0){
            while($a = mysqli_fetch_assoc($authors)){ 
              $isAuthorChecked = in_array(trim($a['Author']), $selected_authors);
            ?>
              <label class="author-label <?php echo $isAuthorChecked ? 'active' : ''; ?>">
                <input 
                  type="checkbox" 
                  class="author-checkbox"
                  value="<?php echo htmlspecialchars($a['Author']); ?>" 
                  onclick="toggleAuthorFilter(this)"
                  <?php echo $isAuthorChecked ? 'checked' : ''; ?>
                >
                <span><?php echo htmlspecialchars($a['Author']); ?></span>
              </label>
            <?php } 
          } else { ?>
            <div style="font-size: 12px; color: var(--text-muted); text-align: center; padding: 10px;">
              ไม่มีข้อมูลผู้แต่งในหมวดนี้
            </div>
          <?php } ?>
        </div>
      </div>

      <hr>

      <!-- การ์ดโซนสำนักพิมพ์ (Multiple Selection) -->
      <div class="filter-section-card">
        <h3>
          <span class="title-icon-badge badge-pub"><i class="fi fi-rr-book"></i></span>
          สำนักพิมพ์
        </h3>
        
        <div class="filter-search-box">
          <i class="fi fi-rr-search"></i>
          <input 
            id="publisherSearch" 
            class="author-search-input" 
            placeholder="พิมพ์ค้นหาสำนักพิมพ์..."
            autocomplete="off"
          >
        </div>

        <div class="author-list publisher-list" id="publisherListContainer">
          <?php 
          if(mysqli_num_rows($publishers) > 0){
            while($p = mysqli_fetch_assoc($publishers)){ 
              $isPublisherChecked = in_array(trim($p['Publisher']), $selected_publishers);
            ?>
              <label class="author-label publisher-label <?php echo $isPublisherChecked ? 'active' : ''; ?>">
                <input 
                  type="checkbox" 
                  class="publisher-checkbox"
                  value="<?php echo htmlspecialchars($p['Publisher']); ?>" 
                  onclick="togglePublisherFilter(this)"
                  <?php echo $isPublisherChecked ? 'checked' : ''; ?>
                >
                <span><?php echo htmlspecialchars($p['Publisher']); ?></span>
              </label>
            <?php } 
          } else { ?>
            <div style="font-size: 12px; color: var(--text-muted); text-align: center; padding: 10px;">
              ไม่มีข้อมูลสำนักพิมพ์ในหมวดนี้
            </div>
          <?php } ?>
        </div>
      </div>
    </aside>

    <!-- ส่วนแสดงผลรายการหนังสือ -->
    <main class="content-area">
      <div class="section-title-bar">
        <div class="section-heading-box">
          <div class="section-icon-glow" style="background: <?php echo $current_heading_gradient; ?>;">
            <i class="<?php echo $current_heading_icon; ?>"></i>
          </div>
          <h2 class="section-heading">
            <?php 
              if(!empty($selected_authors) && !empty($selected_publishers)) {
                echo "ผู้แต่ง: <span class='highlight'>" . htmlspecialchars(implode(', ', $selected_authors)) . "</span> & สำนักพิมพ์: <span class='highlight'>" . htmlspecialchars(implode(', ', $selected_publishers)) . "</span>";
              } elseif(!empty($selected_authors)) {
                echo "ผลงานของ: <span class='highlight'>" . htmlspecialchars(implode(', ', $selected_authors)) . "</span>";
              } elseif(!empty($selected_publishers)) {
                echo "สำนักพิมพ์: <span class='highlight'>" . htmlspecialchars(implode(', ', $selected_publishers)) . "</span>";
              } elseif($category != "") {
                echo "หมวดหมู่: <span class='highlight' style='color: " . $current_highlight_color . ";'>" . htmlspecialchars($current_category_name) . "</span>";
              } elseif($keyword != "") {
                $type_labels = [
                    'keyword'   => 'Keyword',
                    'title'     => 'Title',
                    'author'    => 'Author',
                    'publisher' => 'Publisher'
                ];
                $label = $type_labels[$search_type] ?? 'Keyword';
                echo htmlspecialchars($label) . " : <span class='highlight'>" . htmlspecialchars($keyword) . "</span>";
              } elseif(isset($_GET['all'])) {
                echo "หนังสือทั้งหมด";
              } else {
                echo "หนังสือแนะนำ";
              }
            ?>
          </h2>
        </div>

        <?php if(!$is_home){ ?>
          <div class="section-badge">
            พบ <?php echo $total_books; ?> เล่ม
          </div>
        <?php } ?>
      </div>

      <div class="book-grid">
        <?php if($result && mysqli_num_rows($result) > 0){ ?>
          <?php while($row = mysqli_fetch_assoc($result)){ 
            $catName = $row['Category_name'] ?? 'ทั่วไป';
            $bookCatStyle = getCategoryStyle($catName);
          ?>
            <article class="retro-card">
              <div class="card-thumb">
                <img src="<?php echo htmlspecialchars($row['image'] ?? ''); ?>" alt="cover">
              </div>

              <span class="tag-badge" style="background: <?php echo $bookCatStyle['bg']; ?>; color: <?php echo $bookCatStyle['text']; ?>; border: 1px solid <?php echo $bookCatStyle['border']; ?>;">
                <?php echo htmlspecialchars($catName); ?>
              </span>

              <div class="card-title" title="<?php echo htmlspecialchars($row['Title'] ?? ''); ?>">
                <?php echo htmlspecialchars($row['Title'] ?? ''); ?>
              </div>

              <div class="card-author">
                <span>✎</span>
                <span><?php echo htmlspecialchars($row['Author'] ?? ''); ?></span>
              </div>

              <div class="card-author">
                <i class="fi fi-rr-book"></i>
                <span><?php echo htmlspecialchars($row['Publisher'] ?? 'ไม่ระบุสำนักพิมพ์'); ?></span>
              </div>

              <a href="sentence_same_category.php?id=<?php echo (int)$row['Book_id']; ?>" class="card-btn">
                ดูรายละเอียด
              </a>
            </article>
          <?php } ?>
        <?php } else { ?>
          <div style="grid-column: 1 / -1; background: rgba(255,255,255,0.6); border-radius: 26px; padding: 60px; text-align: center; color: var(--text-muted); font-size: 17px;">
            ไม่พบรายการหนังสือที่คุณต้องการค้นหา
          </div>
        <?php } ?>
      </div>

      <!-- แถบแสดงเลขหน้า Pagination แบบเลื่อนตามหน้าปัจจุบัน (Sliding Window) -->
      <?php if(!$is_home && $total_pages > 1){ ?>
        <div class="pagination-container">
          <!-- ปุ่มย้อนกลับ (วงกลมสีเขียว) -->
          <?php if($current_page > 1){ ?>
            <a href="<?php echo getPageUrl($current_page - 1); ?>" class="page-circle-btn page-action-btn" title="หน้าก่อนหน้า">
              <i class="fi fi-rr-angle-small-left" style="display: flex;"></i>
            </a>
          <?php } ?>

          <?php 
            $pages_to_show = [];
            $range = 3;

            if ($total_pages <= 12) {
                for ($i = 1; $i <= $total_pages; $i++) {
                    $pages_to_show[] = $i;
                }
            } else {
                $pages_to_show[] = 1;
                $pages_to_show[] = 2;

                if ($current_page - $range > 3) {
                    $pages_to_show[] = '...';
                }

                $start = max(3, $current_page - $range);
                $end   = min($total_pages - 2, $current_page + $range);

                for ($i = $start; $i <= $end; $i++) {
                    $pages_to_show[] = $i;
                }

                if ($current_page + $range < $total_pages - 2) {
                    $pages_to_show[] = '...';
                }

                $pages_to_show[] = $total_pages - 1;
                $pages_to_show[] = $total_pages;
            }

            foreach($pages_to_show as $p){
              if($p === '...'){
                echo '<span class="page-dots">...</span>';
              } else {
                $isActive = ($current_page == $p) ? 'active' : '';
                echo '<a href="' . getPageUrl($p) . '" class="page-circle-btn ' . $isActive . '">' . $p . '</a>';
              }
            }
          ?>

          <!-- ปุ่มถัดไป (วงกลมสีเขียว) -->
          <?php if($current_page < $total_pages){ ?>
            <a href="<?php echo getPageUrl($current_page + 1); ?>" class="page-circle-btn page-action-btn" title="หน้าถัดไป">
              <i class="fi fi-rr-angle-small-right" style="display: flex;"></i>
            </a>
          <?php } ?>
        </div>
      <?php } ?>

    </main>

  </div>

  <!-- Footer -->
  <footer class="site-footer">
    <div class="footer-copy">
      © <?php echo date('Y'); ?> THAI Novel Book
    </div>
  </footer>

</div>

<script>
// จัดการ Custom Dropdown
const selectWrapper = document.getElementById("customSelectWrapper");
const selectBtn = document.getElementById("customSelectBtn");
const hiddenInput = document.getElementById("hiddenSearchType");
const selectedText = document.getElementById("selectedTypeText");
const options = document.querySelectorAll(".custom-option");

selectBtn.addEventListener("click", function(e) {
  e.stopPropagation();
  selectWrapper.classList.toggle("open");
});

options.forEach(option => {
  option.addEventListener("click", function() {
    options.forEach(opt => opt.classList.remove("selected"));
    this.classList.add("selected");
    
    const val = this.getAttribute("data-value");
    hiddenInput.value = val;
    selectedText.innerText = this.innerText.trim();
    
    selectWrapper.classList.remove("open");
  });
});

// ปิด Dropdown เมื่อคลิกพื้นที่อื่น
document.addEventListener("click", function(e) {
  if (!selectWrapper.contains(e.target)) {
    selectWrapper.classList.remove("open");
  }
});

// กรองผู้แต่ง: แสดงเฉพาะชื่อที่ขึ้นต้นด้วยตัวอักษรที่พิมพ์เท่านั้น (startsWith)
const authorSearchInput = document.getElementById("authorSearch");
if (authorSearchInput) {
  authorSearchInput.addEventListener("input", function() {
    const filter = this.value.trim();
    const authorLabels = document.querySelectorAll("#authorListContainer .author-label");

    authorLabels.forEach(function(label) {
      const span = label.querySelector("span");
      const text = span ? span.innerText.trim() : label.innerText.trim();

      // เช็กเฉพาะรายการที่ขึ้นต้นด้วยตัวอักษรที่พิมพ์เท่านั้น
      if (filter === "" || text.startsWith(filter)) {
        label.style.setProperty("display", "flex", "important");
      } else {
        label.style.setProperty("display", "none", "important");
      }
    });
  });
}

// กรองสำนักพิมพ์: แสดงเฉพาะชื่อที่ขึ้นต้นด้วยตัวอักษรที่พิมพ์เท่านั้น (startsWith)
const publisherSearchInput = document.getElementById("publisherSearch");
if (publisherSearchInput) {
  publisherSearchInput.addEventListener("input", function() {
    const filter = this.value.trim();
    const publisherLabels = document.querySelectorAll("#publisherListContainer .publisher-label");

    publisherLabels.forEach(function(label) {
      const span = label.querySelector("span");
      const text = span ? span.innerText.trim() : label.innerText.trim();

      // เช็กเฉพาะรายการที่ขึ้นต้นด้วยตัวอักษรที่พิมพ์เท่านั้น
      if (filter === "" || text.startsWith(filter)) {
        label.style.setProperty("display", "flex", "important");
      } else {
        label.style.setProperty("display", "none", "important");
      }
    });
  });
}

// จัดการเลือกผู้แต่งได้หลายคนพร้อมกัน
function toggleAuthorFilter(checkbox) {
  let url = new URL(window.location.href);
  let rawAuthors = url.searchParams.get("authors") || "";
  let currentAuthors = rawAuthors ? rawAuthors.split(",").map(decodeURIComponent).map(s => s.trim()).filter(Boolean) : [];

  url.searchParams.delete("author");
  url.searchParams.delete("page");

  let val = checkbox.value.trim();
  if (checkbox.checked) {
    if (!currentAuthors.includes(val)) {
      currentAuthors.push(val);
    }
  } else {
    currentAuthors = currentAuthors.filter(a => a !== val);
  }

  if (currentAuthors.length > 0) {
    url.searchParams.set("authors", currentAuthors.join(","));
  } else {
    url.searchParams.delete("authors");
  }

  window.location.href = url.toString();
}

// จัดการเลือกสำนักพิมพ์ได้หลายแห่งพร้อมกัน
function togglePublisherFilter(checkbox) {
  let url = new URL(window.location.href);
  let rawPublishers = url.searchParams.get("publishers") || "";
  let currentPublishers = rawPublishers ? rawPublishers.split(",").map(decodeURIComponent).map(s => s.trim()).filter(Boolean) : [];

  url.searchParams.delete("publisher");
  url.searchParams.delete("page");

  let val = checkbox.value.trim();
  if (checkbox.checked) {
    if (!currentPublishers.includes(val)) {
      currentPublishers.push(val);
    }
  } else {
    currentPublishers = currentPublishers.filter(p => p !== val);
  }

  if (currentPublishers.length > 0) {
    url.searchParams.set("publishers", currentPublishers.join(","));
  } else {
    url.searchParams.delete("publishers");
  }

  window.location.href = url.toString();
}
</script>

</body>
</html>
