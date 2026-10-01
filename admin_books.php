<?php
require_once "auth_check.php";
include "db.php";

$python = 'python3';
$script = __DIR__ . '/sentence_same_category.py';

// =====================================
// 0. จัดการผู้ดูแลระบบ (Admin Management) - เฉพาะ Superadmin เท่านั้น
// =====================================
$is_superadmin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'superadmin');

if($is_superadmin && isset($_POST['action_admin'])){
    $admin_act = $_POST['action_admin'];
    
    // เพิ่มแอดมินใหม่ (ใช้เฉพาะ Username และ Password)
    if($admin_act === 'add'){
        $new_user = mysqli_real_escape_string($conn, trim($_POST['admin_username']));
        $new_pass = trim($_POST['admin_password']);

        if(!empty($new_user) && !empty($new_pass)){
            $chk = mysqli_query($conn, "SELECT * FROM admin WHERE username='$new_user' LIMIT 1");
            if(mysqli_num_rows($chk) > 0){
                echo "<script>alert('❌ Username \"$new_user\" นี้มีในระบบแล้ว'); window.location='admin_books.php';</script>";
                exit;
            } else {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                mysqli_query($conn, "INSERT INTO admin (name, username, password, role) VALUES ('$new_user', '$new_user', '$hashed', 'admin')");
                echo "<script>alert('✅ เพิ่มแอดมินใหม่เรียบร้อยแล้ว'); window.location='admin_books.php';</script>";
                exit;
            }
        }
    }
}

// ลบแอดมิน (เฉพาะ Superadmin)
if($is_superadmin && isset($_GET['delete_admin_user'])){
    $del_user = mysqli_real_escape_string($conn, trim($_GET['delete_admin_user']));
    
    $check_role = mysqli_query($conn, "SELECT role FROM admin WHERE username='$del_user' LIMIT 1");
    $r = mysqli_fetch_assoc($check_role);
    if($r && $r['role'] !== 'superadmin'){
        mysqli_query($conn, "DELETE FROM admin WHERE username='$del_user'");
        echo "<script>alert('✅ ลบแอดมินเรียบร้อยแล้ว'); window.location='admin_books.php';</script>";
        exit;
    } else {
        echo "<script>alert('❌ ไม่สามารถลบผู้ดูแลระบบหลักได้'); window.location='admin_books.php';</script>";
        exit;
    }
}

// =====================================
// 1. จัดการ เพิ่ม/ลบ/แก้ไข (หมวดหมู่, ผู้แต่ง, สำนักพิมพ์) - ไม่ต้องคำนวณ Sentence
// =====================================

// --- จัดการหมวดหมู่ (Category) ---
if(isset($_POST['action_category'])){
    $act = $_POST['action_category'];
    if($act === 'add'){
        $name = mysqli_real_escape_string($conn, trim($_POST['cat_name']));
        if(!empty($name)){
            mysqli_query($conn, "INSERT INTO category (Category_name) VALUES ('$name')");
        }
    } elseif($act === 'edit'){
        $cat_id = intval($_POST['cat_id']);
        $name = mysqli_real_escape_string($conn, trim($_POST['cat_name']));
        if(!empty($name)){
            mysqli_query($conn, "UPDATE category SET Category_name='$name' WHERE Category_id=$cat_id");
        }
    } elseif($act === 'delete'){
        $cat_id = intval($_POST['cat_id']);
        mysqli_query($conn, "UPDATE book SET Category_id=NULL WHERE Category_id=$cat_id");
        mysqli_query($conn, "DELETE FROM category WHERE Category_id=$cat_id");
    }
    header("Location: admin_books.php");
    exit;
}

// --- จัดการผู้แต่ง (Author) ---
if(isset($_POST['action_author'])){
    $act = $_POST['action_author'];
    $old_name = mysqli_real_escape_string($conn, trim($_POST['old_author']));
    if($act === 'edit'){
        $new_name = mysqli_real_escape_string($conn, trim($_POST['new_author']));
        if(!empty($new_name) && !empty($old_name)){
            mysqli_query($conn, "UPDATE book SET Author='$new_name' WHERE Author='$old_name'");
            mysqli_query($conn, "UPDATE add_book SET Author='$new_name' WHERE Author='$old_name'");
        }
    } elseif($act === 'delete'){
        if(!empty($old_name)){
            mysqli_query($conn, "UPDATE book SET Author='ไม่ระบุผู้แต่ง' WHERE Author='$old_name'");
            mysqli_query($conn, "UPDATE add_book SET Author='ไม่ระบุผู้แต่ง' WHERE Author='$old_name'");
        }
    }
    header("Location: admin_books.php");
    exit;
}

// --- จัดการสำนักพิมพ์ (Publisher) ---
if(isset($_POST['action_publisher'])){
    $act = $_POST['action_publisher'];
    $old_pub = mysqli_real_escape_string($conn, trim($_POST['old_publisher']));
    if($act === 'edit'){
        $new_pub = mysqli_real_escape_string($conn, trim($_POST['new_publisher']));
        if(!empty($new_pub) && !empty($old_pub)){
            mysqli_query($conn, "UPDATE book SET Publisher='$new_pub' WHERE Publisher='$old_pub'");
            mysqli_query($conn, "UPDATE add_book SET Publisher='$new_pub' WHERE Publisher='$old_pub'");
        }
    } elseif($act === 'delete'){
        if(!empty($old_pub)){
            mysqli_query($conn, "UPDATE book SET Publisher='ไม่ระบุสำนักพิมพ์' WHERE Publisher='$old_pub'");
            mysqli_query($conn, "UPDATE add_book SET Publisher='ไม่ระบุสำนักพิมพ์' WHERE Publisher='$old_pub'");
        }
    }
    header("Location: admin_books.php");
    exit;
}

// =====================================
// 2. จัดการลบหนังสือ
// =====================================
if(isset($_GET['delete_id'])){
    $del_id = intval($_GET['delete_id']);
    
    $book_query = mysqli_query($conn, "SELECT Title, Author, Category_id FROM book WHERE Book_id=$del_id");
    if($book_query && mysqli_num_rows($book_query) > 0){
        $book_data = mysqli_fetch_assoc($book_query);
        $target_cat = $book_data['Category_id'];
        $del_title = mysqli_real_escape_string($conn, $book_data['Title']);
        $del_author = mysqli_real_escape_string($conn, $book_data['Author']);

        mysqli_query($conn, "DELETE FROM book WHERE Book_id=$del_id");
        mysqli_query($conn, "DELETE FROM add_book WHERE Title='$del_title' AND Author='$del_author'");
        mysqli_query($conn, "DELETE FROM recommendation_sentence_same_category WHERE book_id=$del_id OR recommend_book_id=$del_id");

        if($target_cat){
            exec(escapeshellcmd($python) . ' ' . escapeshellarg($script) . ' ' . intval($target_cat) . ' 2>&1');
        }
    }
    header("Location: admin_books.php");
    exit;
}

// =====================================
// 3. จัดการแก้ไขหนังสือ (Submit จาก Modal)
// =====================================
if(isset($_POST['update_book'])){
    $edit_id = intval($_POST['edit_book_id']);
    $title = mysqli_real_escape_string($conn, trim($_POST['Title']));
    $author = mysqli_real_escape_string($conn, trim($_POST['Author']));
    $publisher = mysqli_real_escape_string($conn, trim($_POST['Publisher']));
    $blurb = mysqli_real_escape_string($conn, trim($_POST['Blurb']));
    $image = mysqli_real_escape_string($conn, trim($_POST['image']));
    $category_id = intval($_POST['Category_id']);

    $old_query = mysqli_query($conn, "SELECT Title, Blurb, Category_id FROM book WHERE Book_id=$edit_id LIMIT 1");
    $old_data = mysqli_fetch_assoc($old_query);

    $old_title = $old_data ? $old_data['Title'] : '';
    $old_blurb = $old_data ? $old_data['Blurb'] : '';
    $old_cat   = $old_data ? intval($old_data['Category_id']) : $category_id;

    $needs_recalculate = false;
    if ($old_title !== trim($_POST['Title']) || 
        $old_blurb !== trim($_POST['Blurb']) || 
        $old_cat   !== $category_id) {
        $needs_recalculate = true;
    }

    mysqli_query($conn, "
        UPDATE book SET 
            Title='$title',
            Author='$author',
            Publisher='$publisher',
            Blurb='$blurb',
            image='$image',
            Category_id='$category_id'
        WHERE Book_id=$edit_id
    ");

    if($needs_recalculate){
        exec(escapeshellcmd($python) . ' ' . escapeshellarg($script) . ' ' . intval($category_id) . ' 2>&1');
        if($old_cat !== $category_id){
            exec(escapeshellcmd($python) . ' ' . escapeshellarg($script) . ' ' . intval($old_cat) . ' 2>&1');
        }
        echo "<script>alert('✅ บันทึกการแก้ไขสำเร็จ '); window.location='admin_books.php';</script>";
    } else {
        echo "<script>alert('✅ บันทึกการแก้ไขสำเร็จ '); window.location='admin_books.php';</script>";
    }
    exit;
}

// =====================================
// 4. รับค่าตัวกรองและคำค้นหา (เรียงลำดับตามตัวอักษร)
// =====================================
$search = trim($_GET['search'] ?? '');
$filter_cat = trim($_GET['cat'] ?? '');
$filter_author = trim($_GET['author'] ?? '');
$filter_pub = trim($_GET['publisher'] ?? '');

$sql = "
    SELECT b.*, c.Category_name 
    FROM book b
    LEFT JOIN category c ON b.Category_id = c.Category_id
    WHERE 1
";

if($search !== ''){
    $es = mysqli_real_escape_string($conn, $search);
    $sql .= " AND (b.Title LIKE '%$es%' OR b.Author LIKE '%$es%' OR b.Publisher LIKE '%$es%')";
}
if($filter_cat !== ''){
    $sql .= " AND b.Category_id = '" . mysqli_real_escape_string($conn, $filter_cat) . "'";
}
if($filter_author !== ''){
    $sql .= " AND b.Author = '" . mysqli_real_escape_string($conn, $filter_author) . "'";
}
if($filter_pub !== ''){
    $sql .= " AND b.Publisher = '" . mysqli_real_escape_string($conn, $filter_pub) . "'";
}

$sql .= " ORDER BY (b.Title REGEXP '^[ก-๙]') DESC, b.Title ASC";
$books = mysqli_query($conn, $sql);
$total_count = mysqli_num_rows($books);

$category_list = [];
$cat_query = mysqli_query($conn, "SELECT * FROM category ORDER BY (Category_name REGEXP '^[ก-๙]') DESC, Category_name ASC");
while($cat = mysqli_fetch_assoc($cat_query)){
    $category_list[] = $cat;
}

$author_list = [];
$authors = mysqli_query($conn, "SELECT DISTINCT Author FROM book WHERE Author IS NOT NULL AND Author != '' ORDER BY (Author REGEXP '^[ก-๙]') DESC, Author ASC");
while($a = mysqli_fetch_assoc($authors)){
    $author_list[] = $a['Author'];
}

$publisher_list = [];
$publishers = mysqli_query($conn, "SELECT DISTINCT Publisher FROM book WHERE Publisher IS NOT NULL AND Publisher != '' ORDER BY (Publisher REGEXP '^[ก-๙]') DESC, Publisher ASC");
while($p = mysqli_fetch_assoc($publishers)){
    $publisher_list[] = $p['Publisher'];
}

$admin_list_query = null;
if($is_superadmin){
    $admin_list_query = mysqli_query($conn, "SELECT * FROM admin ORDER BY (role='superadmin') DESC, username ASC");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — THAI Novel Book</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/uicons-regular-rounded/css/uicons-regular-rounded.css">

<!-- jQuery & Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
  padding: 24px 28px;
  display: flex;
  gap: 28px;
  flex: 1;
}

.app-sidebar {
  width: 275px;
  background: rgba(255, 255, 255, 0.48);
  border: 1.5px solid rgba(255, 255, 255, 0.85);
  border-radius: 28px;
  padding: 22px 16px;
  flex-shrink: 0;
  box-shadow: 0 12px 32px rgba(120, 100, 160, 0.08);
  display: flex;
  flex-direction: column;
  position: sticky;
  top: 20px;
  height: calc(100vh - 72px);
  overflow-y: auto;
}

.brand-header {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
  margin-bottom: 22px;
  transition: transform 0.22s ease;
}

.brand-header:hover { transform: translateY(-2px); }

.brand-icon-box {
  width: 40px;
  height: 40px;
  background: linear-gradient(135deg, #ffd3b6 0%, #ffaaa6 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 18px;
  box-shadow: 0 6px 16px rgba(255, 170, 166, 0.45);
}

.brand-text {
  font-size: 22px;
  font-weight: 800;
  color: #252244;
  letter-spacing: -0.3px;
  line-height: 1;
}

.sidebar-label {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--text-primary);
  margin: 14px 4px 8px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.sidebar-menu {
  list-style: none;
  padding: 0;
  margin: 0 0 10px;
}

.sidebar-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 14px;
  color: var(--text-primary);
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 600;
  border-radius: 14px;
  border: 1px solid transparent;
  transition: all 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
  margin-bottom: 6px;
  cursor: pointer;
}

.sidebar-link i { font-size: 15px; color: #6c52b5; }

.sidebar-link:hover, .sidebar-link.active {
  background: rgba(255, 255, 255, 0.85);
  border-color: rgba(140, 130, 194, 0.35);
  box-shadow: 0 4px 14px rgba(120, 100, 160, 0.1);
  transform: translateX(3px);
}

.sidebar-link.active {
  font-weight: 700;
  color: #523a9e;
  background: #ffffff;
}

.sidebar-hr {
  border: none;
  border-top: 1.5px dashed rgba(133, 122, 177, 0.25);
  margin: 16px 0;
}

.filter-card-box {
  background: rgba(255, 255, 255, 0.65);
  border: 1.5px solid rgba(255, 255, 255, 0.95);
  border-radius: 20px;
  padding: 14px 12px;
  box-shadow: 0 6px 18px rgba(118, 97, 160, 0.06);
}

.filter-header-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px dashed rgba(133, 122, 177, 0.2);
}

.filter-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 7px;
}

.filter-title i {
  color: #ff5e7e;
  font-size: 14px;
}

.btn-manage-meta {
  background: linear-gradient(135deg, #ffffff 0%, #f7f3fd 100%);
  border: 1px solid rgba(140, 130, 194, 0.35);
  box-shadow: 0 2px 6px rgba(130, 115, 175, 0.08);
  padding: 3.5px 12px;
  border-radius: 50px;
  font-size: 11.5px;
  font-weight: 600;
  color: #523a9e;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  transition: all 0.2s ease;
}
.btn-manage-meta:hover {
  background: #ede6f8;
  border-color: #6c52b5;
  transform: translateY(-1px);
}

.filter-select-group {
  margin-bottom: 12px;
}

.filter-field-label {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 11.5px;
  font-weight: 600;
  color: var(--text-muted);
  margin-bottom: 4px;
  padding-left: 4px;
}

.filter-field-label i {
  font-size: 12px;
  color: #6c52b5;
}

.select2-container {
  width: 100% !important;
}

.select2-container .select2-selection--single {
  height: 38px;
  border-radius: 50px;
  border: 1.5px solid rgba(130, 115, 175, 0.25);
  background: #ffffff;
  display: flex;
  align-items: center;
  padding: 0 12px;
  box-shadow: 0 2px 6px rgba(110, 95, 155, 0.04);
  transition: all 0.2s ease;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
  color: var(--text-primary);
  font-size: 12.5px;
  font-family: 'Prompt', sans-serif;
  line-height: normal;
  padding: 0;
  font-weight: 500;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
  height: 36px;
  right: 10px;
}

.select2-dropdown {
  border-radius: 16px;
  border: 1.5px solid rgba(130, 115, 175, 0.35);
  box-shadow: 0 12px 30px rgba(115, 95, 160, 0.18);
  overflow: hidden;
  background: #ffffff;
  padding: 6px;
  z-index: 9999;
}

.select2-search--dropdown {
  padding: 4px;
}

.select2-search--dropdown .select2-search__field {
  border-radius: 10px;
  border: 1.2px solid rgba(130, 115, 175, 0.3);
  font-family: 'Prompt', sans-serif;
  font-size: 12.5px;
  padding: 6px 12px;
  outline: none;
}

.select2-results__option {
  font-family: 'Prompt', sans-serif;
  font-size: 12.5px;
  padding: 7px 12px;
  border-radius: 10px;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
  background-color: #6c52b5;
  color: #ffffff;
}

.select2-results__option[aria-selected="true"]:empty,
.select2-results__option:not([id*="Category_id"]):not([id*="Author"]):not([id*="Publisher"])[id$="-"] {
  display: none !important;
}

.btn-clear-filter {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  width: 100%;
  padding: 6px 12px;
  background: #fff0f3;
  color: #e05375;
  border: 1px solid #ffd6df;
  border-radius: 50px;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
  margin-top: 6px;
  transition: all 0.2s ease;
}

.btn-clear-filter:hover {
  background: #ffe3e8;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(224, 83, 117, 0.2);
}

.app-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.top-navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 22px;
  gap: 20px;
  flex-wrap: wrap;
}

.search-box-retro {
  display: flex;
  align-items: center;
  background: rgba(255, 255, 255, 0.88);
  border: 1.5px solid rgba(255, 255, 255, 0.95);
  border-radius: 50px;
  padding: 4px 6px 4px 18px;
  flex: 1;
  max-width: 760px;
  box-shadow: 0 6px 20px rgba(133, 122, 177, 0.1);
  gap: 10px;
  transition: all 0.25s;
}

.search-box-retro:focus-within {
  background: #ffffff;
  border-color: #8c82c2;
  box-shadow: 0 8px 25px rgba(140, 130, 194, 0.22);
}

.search-box-icon {
  font-size: 15px;
  color: #8c82a5;
  display: flex;
  align-items: center;
}

.search-box-retro input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 13.5px;
  font-family: 'Prompt', sans-serif;
  color: var(--text-primary);
}

.search-btn-retro {
  background: linear-gradient(135deg, #302b63 0%, #24243e 100%);
  color: #ffffff;
  border: none;
  padding: 8.5px 22px;
  border-radius: 50px;
  font-size: 13.5px;
  font-family: 'Prompt', sans-serif;
  font-weight: 500;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(48, 43, 99, 0.25);
  transition: all 0.2s;
}
.search-btn-retro:hover { transform: translateY(-1px); }

.top-user { 
  display: flex; 
  align-items: center; 
  gap: 12px;
  flex-shrink: 0;
}

.admin-pill {
  font-size: 13px;
  font-weight: 600;
  background: rgba(255, 255, 255, 0.85);
  color: var(--text-primary);
  padding: 7px 16px;
  border-radius: 50px;
  border: 1px solid rgba(255, 255, 255, 0.95);
  box-shadow: 0 4px 12px rgba(130, 115, 165, 0.08);
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-view-site {
  background: linear-gradient(135deg, #ffffff 0%, #f7f3fd 100%);
  color: var(--text-primary);
  text-decoration: none;
  padding: 8px 18px;
  border-radius: 50px;
  font-size: 13.5px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid rgba(255, 255, 255, 0.95);
  box-shadow: 0 4px 12px rgba(150, 135, 185, 0.15);
  transition: all 0.2s;
}
.btn-view-site:hover {
  background: #ffffff;
  color: #ff758c;
  transform: translateY(-2px);
  box-shadow: 0 8px 18px rgba(255, 117, 140, 0.2);
}

.data-card {
  background: var(--card-bg);
  border-radius: 26px;
  border: 1.5px solid var(--glass-border);
  box-shadow: 0 10px 24px rgba(130, 115, 170, 0.08);
  overflow: hidden;
}

.table-header-info {
  padding: 18px 24px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1.5px solid rgba(133, 122, 177, 0.12);
  background: rgba(255, 255, 255, 0.5);
}

.table-header-info h2 {
  font-size: 18px;
  font-weight: 700;
  margin: 0;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 10px;
}

.table-container { overflow-x: auto; }
.dash-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.dash-table thead {
  background: rgba(246, 240, 252, 0.7);
  border-bottom: 1.5px solid rgba(133, 122, 177, 0.15);
}

.dash-table th {
  padding: 13px 18px;
  font-size: 14px;
  font-weight: 700;
  color: #3b3558;
}

.dash-table td {
  padding: 12px 18px;
  font-size: 13.5px;
  border-bottom: 1px solid rgba(133, 122, 177, 0.1);
  vertical-align: middle;
}
.dash-table tr:hover { background-color: rgba(255, 255, 255, 0.65); }

.cover-img {
  width: 44px;
  height: 60px;
  border-radius: 8px;
  object-fit: cover;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
  background: #f1ebf9;
  display: block;
}

.cat-badge {
  background: #f4f0fa;
  color: #6c52b5;
  border: 1px solid #e2d9f2;
  font-size: 11px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 50px;
  white-space: nowrap;
}

.action-group {
  display: flex;
  align-items: center;
  gap: 8px;
  justify-content: center;
}

.btn-action-edit {
  background: linear-gradient(135deg, #ede7f6 0%, #e1d5f5 100%);
  color: #523a9e;
  border: 1.2px solid #d3c4ee;
  padding: 5px 12px;
  border-radius: 50px;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-action-edit:hover {
  background: linear-gradient(135deg, #9575cd 0%, #7e57c2 100%);
  color: #ffffff;
  border-color: transparent;
  transform: translateY(-1px);
}

.btn-action-del {
  background: #fff0f3;
  color: #e05375;
  border: 1px solid #ffd6df;
  padding: 5px 12px;
  border-radius: 50px;
  text-decoration: none;
  font-size: 12px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: all 0.2s;
}
.btn-action-del:hover {
  background: #ffe3e8;
  transform: translateY(-1px);
}

.modal-overlay {
  display: none;
  position: fixed;
  top: 0; left: 0;
  width: 100vw; height: 100vh;
  background: rgba(37, 34, 68, 0.45);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  z-index: 999;
  justify-content: center;
  align-items: center;
}

.modal-box {
  background: rgba(255, 255, 255, 0.95);
  width: 90%;
  max-width: 620px;
  border-radius: 28px;
  border: 1.5px solid var(--glass-border);
  box-shadow: 0 20px 50px rgba(113, 93, 158, 0.22);
  padding: 26px 30px;
  max-height: 90vh;
  overflow-y: auto;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
  border-bottom: 1.5px dashed rgba(133, 122, 177, 0.2);
  padding-bottom: 12px;
}

.modal-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  color: var(--text-primary);
}

.btn-close {
  background: transparent;
  border: none;
  font-size: 22px;
  cursor: pointer;
  color: var(--text-muted);
  transition: color 0.2s;
}
.btn-close:hover { color: #ff5e7e; }

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.form-group { margin-bottom: 10px; }
.form-group.full { grid-column: span 2; }
.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 6px;
  color: var(--text-primary);
}

.form-group input, .form-group select, .form-group textarea {
  width: 100%;
  padding: 9.5px 14px;
  border-radius: 12px;
  border: 1.5px solid rgba(130, 115, 175, 0.28);
  font-family: inherit;
  font-size: 13.5px;
  outline: none;
  background: #ffffff;
  color: var(--text-primary);
}

.form-group input:focus, .form-group select:focus, .form-group textarea:focus {
  border-color: #6c52b5;
  box-shadow: 0 4px 14px rgba(108, 82, 181, 0.18);
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
}

.btn-cancel {
  background: rgba(255, 255, 255, 0.85);
  border: 1.5px solid rgba(133, 122, 177, 0.25);
  padding: 8px 20px;
  border-radius: 50px;
  font-family: inherit;
  font-weight: 600;
  font-size: 13px;
  color: var(--text-primary);
  cursor: pointer;
}

.btn-submit-edit {
  background: linear-gradient(135deg, #302b63 0%, #24243e 100%);
  color: #ffffff;
  border: none;
  padding: 8.5px 22px;
  border-radius: 50px;
  font-family: inherit;
  font-weight: 600;
  font-size: 13px;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(48, 43, 99, 0.25);
  transition: all 0.2s;
}

.tab-btn-group {
  display: flex;
  gap: 8px;
  margin-bottom: 16px;
}
.tab-btn {
  flex: 1;
  padding: 8px;
  border: 1.5px solid rgba(130, 115, 175, 0.25);
  border-radius: 50px;
  background: #ffffff;
  font-family: inherit;
  font-weight: 600;
  font-size: 13px;
  color: var(--text-primary);
  cursor: pointer;
  transition: all 0.2s;
}
.tab-btn.active {
  background: linear-gradient(135deg, #6c52b5 0%, #523a9e 100%);
  color: #ffffff;
  border-color: transparent;
}
.meta-item-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-bottom: 1px solid rgba(133, 122, 177, 0.1);
  gap: 12px;
}
.meta-item-row:hover { background: rgba(246, 240, 252, 0.5); }
.meta-input-inline {
  flex: 1;
  padding: 8px 14px;
  border: 1.5px solid rgba(130, 115, 175, 0.28);
  border-radius: 12px;
  font-family: inherit;
  font-size: 13.5px;
  outline: none;
  background: #ffffff;
}

.modal-search-input {
  width: 100%;
  padding: 8px 16px;
  border-radius: 50px;
  border: 1.5px solid rgba(130, 115, 175, 0.28);
  background: #ffffff;
  font-family: inherit;
  font-size: 13px;
  margin-bottom: 12px;
  outline: none;
}
.modal-search-input:focus {
  border-color: #6c52b5;
  box-shadow: 0 4px 14px rgba(108, 82, 181, 0.18);
}

@media(max-width: 980px){
  .page-wrapper { flex-direction: column; padding: 20px; }
  .app-sidebar { width: 100%; height: auto; position: static; }
}
</style>
</head>

<body>

<div class="page-wrapper">

  <aside class="app-sidebar">
    <a href="admin_books.php" class="brand-header">
      <div class="brand-icon-box">
        <i class="fi fi-rr-book-bookmark"></i>
      </div>
      <span class="brand-text">THAI Novel Book</span>
    </a>

    <div class="sidebar-label">
      <span><i class="fi fi-rr-menu-burger"></i> เมนูหลัก</span>
    </div>
    <ul class="sidebar-menu">
      <li><a href="admin_books.php" class="sidebar-link active"><i class="fi fi-rr-apps"></i> รายการหนังสือ</a></li>
      <li><a href="add_book.php" class="sidebar-link"><i class="fi fi-rr-add"></i> เพิ่มหนังสือใหม่</a></li>
      
      <?php if($is_superadmin): ?>
        <li>
          <a href="javascript:void(0)" class="sidebar-link" onclick="openAdminModal()">
            <i class="fi fi-rr-add"></i> เพิ่มแอดมิน
          </a>
        </li>
      <?php endif; ?>
    </ul>

    <hr class="sidebar-hr">

    <div class="filter-card-box">
      <div class="filter-header-bar">
        <span class="filter-title">
          <i class="fi fi-rr-filter"></i> ตัวกรองระบบ
        </span>
        <button type="button" class="btn-manage-meta" onclick="openMetaModal()">
          <i class="fi fi-rr-settings-sliders"></i> จัดการ
        </button>
      </div>

      <form method="GET" action="admin_books.php">
        <?php if($search): ?>
          <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
        <?php endif; ?>

        <div class="filter-select-group">
          <div class="filter-field-label">
            <i class="fi fi-rr-apps"></i> หมวดหมู่
          </div>
          <select name="cat" class="filter-cat-select" onchange="this.form.submit()">
            <option value="" <?php echo ($filter_cat === '') ? 'selected' : ''; ?>>-- ทั้งหมด --</option>
            <?php foreach($category_list as $c): ?>
              <option value="<?php echo $c['Category_id']; ?>" <?php echo ($filter_cat == $c['Category_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['Category_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-select-group">
          <div class="filter-field-label">
            <i class="fi fi-rr-pencil"></i> ผู้แต่ง
          </div>
          <select name="author" class="filter-search-select" onchange="this.form.submit()">
            <option value="" <?php echo ($filter_author === '') ? 'selected' : ''; ?>>-- ทั้งหมด --</option>
            <?php foreach($author_list as $auth_item): ?>
              <option value="<?php echo htmlspecialchars($auth_item); ?>" <?php echo ($filter_author == $auth_item) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($auth_item); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-select-group">
          <div class="filter-field-label">
            <i class="fi fi-rr-building"></i> สำนักพิมพ์
          </div>
          <select name="publisher" class="filter-search-select" onchange="this.form.submit()">
            <option value="" <?php echo ($filter_pub === '') ? 'selected' : ''; ?>>-- ทั้งหมด --</option>
            <?php foreach($publisher_list as $pub_item): ?>
              <option value="<?php echo htmlspecialchars($pub_item); ?>" <?php echo ($filter_pub == $pub_item) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($pub_item); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if($filter_cat || $filter_author || $filter_pub || $search): ?>
          <a href="admin_books.php" class="btn-clear-filter">
            <i class="fi fi-rr-cross-small"></i> ล้างตัวกรองทั้งหมด
          </a>
        <?php endif; ?>
      </form>
    </div>

    <div style="margin-top:auto; padding-top:16px;">
      <a href="admin_logout.php" class="sidebar-link" style="color:#e05375;" onclick="return confirm('ยืนยันออกจากระบบ?')">
        <i class="fi fi-rr-sign-out-alt" style="color:#e05375;"></i> ออกจากระบบ
      </a>
    </div>
  </aside>

  <main class="app-main">
    <header class="top-navbar">
      <form class="search-box-retro" method="GET" action="admin_books.php">
        <span class="search-box-icon">
          <i class="fi fi-rr-search"></i>
        </span>
        <input type="text" name="search" placeholder="ค้นหาชื่อหนังสือ, ผู้แต่ง, สำนักพิมพ์..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="search-btn-retro">ค้นหา</button>
      </form>

      <div class="top-user">
        <span class="admin-pill" style="<?php echo $is_superadmin ? 'background: #fff6e5; color: #d9822b; border-color: #fee6b8;' : ''; ?>">
          <i class="fi fi-rr-user"></i> 
          <?php echo $is_superadmin ? 'ผู้ดูแลระบบ' : 'แอดมิน'; ?>: 
          <?php echo htmlspecialchars($_SESSION['admin_name']); ?>
        </span>
        <a href="index.php" class="btn-view-site" target="_blank">
          <i class="fi fi-rr-eye"></i> ชมหน้าเว็บไซต์
        </a>
      </div>
    </header>

    <div class="data-card">
      <div class="table-header-info">
        <h2>
          <i class="fi fi-rr-book-alt" style="color: #ff5e7e;"></i> รายการหนังสือในระบบ
        </h2>
        <span class="admin-pill" style="font-size: 12.5px;">
          พบทั้งหมด <?php echo $total_count; ?> เล่ม
        </span>
      </div>

      <div class="table-container">
        <table class="dash-table">
          <thead>
            <tr>
              <th style="width:65px; text-align:center;">เล่ม</th>
              <th style="width:65px; text-align:center;">ปก</th>
              <th>ชื่อเรื่อง</th>
              <th>ผู้แต่ง</th>
              <th>สำนักพิมพ์</th>
              <th>หมวดหมู่</th>
              <th style="width:170px; text-align:center;">การจัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php if($total_count > 0): ?>
              <?php 
                $counter = 1;
                while($row = mysqli_fetch_assoc($books)): 
              ?>
              <tr>
                <td style="text-align:center; font-weight:700; color:#8590aa;"><?php echo $counter++; ?></td>
                <td style="text-align:center;">
                  <img src="<?php echo htmlspecialchars($row['image']); ?>" class="cover-img" onerror="this.src='https://placehold.co/44x60?text=Cover';">
                </td>
                <td><strong style="font-size:14.5px; color:var(--text-primary);"><?php echo htmlspecialchars($row['Title']); ?></strong></td>
                <td><span style="color:var(--text-muted);">✎ <?php echo htmlspecialchars($row['Author']); ?></span></td>
                <td><span style="color:var(--text-muted);"><?php echo htmlspecialchars($row['Publisher'] ?? '-'); ?></span></td>
                <td><span class="cat-badge"><?php echo htmlspecialchars($row['Category_name'] ?? 'ทั่วไป'); ?></span></td>
                <td>
                  <div class="action-group">
                    <button type="button" class="btn-action-edit" onclick='openEditModal(<?php echo json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>)'>
                      <i class="fi fi-rr-edit"></i> แก้ไข
                    </button>

                    <a href="admin_books.php?delete_id=<?php echo $row['Book_id']; ?>" 
                       class="btn-action-del" 
                       onclick="return confirm('ยืนยันที่จะลบหนังสือเรื่อง «<?php echo addslashes($row['Title']); ?>» หรือไม่?')">
                      <i class="fi fi-rr-trash"></i> ลบ
                    </a>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" style="text-align:center; padding:50px; color:var(--text-muted);">
                  ไม่พบข้อมูลหนังสือตามเงื่อนไขที่เลือก
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

</div>

<div id="editModal" class="modal-overlay">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modalTitle">✏️ แก้ไขข้อมูลหนังสือ (เล่มที่ )</h3>
      <button type="button" class="btn-close" onclick="closeEditModal()">&times;</button>
    </div>

    <form method="post">
      <input type="hidden" name="edit_book_id" id="edit_book_id">

      <div class="form-grid">
        <div class="form-group full">
          <label>ชื่อหนังสือ</label>
          <input type="text" name="Title" id="edit_Title" required>
        </div>

        <div class="form-group">
          <label>ผู้แต่ง</label>
          <input type="text" name="Author" id="edit_Author" required>
        </div>

        <div class="form-group">
          <label>สำนักพิมพ์</label>
          <input type="text" name="Publisher" id="edit_Publisher">
        </div>

        <div class="form-group">
          <label>URL รูปปกหนังสือ</label>
          <input type="text" name="image" id="edit_image" required>
        </div>

        <div class="form-group">
          <label>หมวดหมู่</label>
          <select name="Category_id" id="edit_Category_id">
            <?php foreach($category_list as $c): ?>
              <option value="<?php echo $c['Category_id']; ?>">
                <?php echo htmlspecialchars($c['Category_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group full">
          <label>เรื่องย่อ</label>
          <textarea name="Blurb" id="edit_Blurb" rows="4"></textarea>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeEditModal()">ยกเลิก</button>
        <button type="submit" name="update_book" class="btn-submit-edit">💾 บันทึกการเปลี่ยนแปลง</button>
      </div>
    </form>
  </div>
</div>

<div id="metaModal" class="modal-overlay">
  <div class="modal-box" style="max-width: 680px;">
    <div class="modal-header">
      <h3>⚙️ จัดการข้อมูลระบบ (หมวดหมู่ / ผู้แต่ง / สำนักพิมพ์)</h3>
      <button type="button" class="btn-close" onclick="closeMetaModal()">&times;</button>
    </div>

    <div class="tab-btn-group">
      <button type="button" class="tab-btn active" onclick="switchMetaTab('cat')">📚 หมวดหมู่</button>
      <button type="button" class="tab-btn" onclick="switchMetaTab('author')">✍️ ผู้แต่ง</button>
      <button type="button" class="tab-btn" onclick="switchMetaTab('pub')">🏢 สำนักพิมพ์</button>
    </div>

    <div id="tab-cat" class="meta-tab-content">
      <form method="post" style="display:flex; gap:8px; margin-bottom:15px;">
        <input type="hidden" name="action_category" value="add">
        <input type="text" name="cat_name" placeholder="เพิ่มหมวดหมู่ใหม่..." class="meta-input-inline" required>
        <button type="submit" class="btn-submit-edit" style="padding:8px 22px;">+ เพิ่ม</button>
      </form>
      <div style="max-height: 320px; overflow-y: auto;">
        <?php foreach($category_list as $c): ?>
          <div class="meta-item-row">
            <span style="font-weight:600; font-size:14px; flex:1;"><?php echo htmlspecialchars($c['Category_name']); ?></span>
            <div style="display:flex; gap:6px;">
              <button type="button" class="btn-action-edit" onclick="openMetaItemEdit('category', '<?php echo $c['Category_id']; ?>', '<?php echo htmlspecialchars(addslashes($c['Category_name'])); ?>')">
                <i class="fi fi-rr-edit"></i> แก้ไข
              </button>
              <form method="post" onsubmit="return confirm('ยืนยันลบหมวดหมู่นี้?');" style="margin:0;">
                <input type="hidden" name="action_category" value="delete">
                <input type="hidden" name="cat_id" value="<?php echo $c['Category_id']; ?>">
                <button type="submit" class="btn-action-del"><i class="fi fi-rr-trash"></i> ลบ</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div id="tab-author" class="meta-tab-content" style="display:none;">
      <input type="text" id="searchAuthorMeta" class="modal-search-input" placeholder="🔍 พิมพ์ค้นหาชื่อผู้แต่ง..." onkeyup="filterMetaList('author-row', this.value)">
      
      <div id="authorListContainer" style="max-height: 320px; overflow-y: auto;">
        <?php foreach($author_list as $auth_item): ?>
          <div class="meta-item-row author-row" data-text="<?php echo htmlspecialchars(mb_strtolower($auth_item)); ?>">
            <span style="font-weight:600; font-size:14px; flex:1;"><?php echo htmlspecialchars($auth_item); ?></span>
            <div style="display:flex; gap:6px;">
              <button type="button" class="btn-action-edit" onclick="openMetaItemEdit('author', '<?php echo htmlspecialchars(addslashes($auth_item)); ?>', '<?php echo htmlspecialchars(addslashes($auth_item)); ?>')">
                <i class="fi fi-rr-edit"></i> แก้ไข
              </button>
              <form method="post" onsubmit="return confirm('ยืนยันล้างชื่อผู้แต่งนี้ออกจากหนังสือทั้งหมด?');" style="margin:0;">
                <input type="hidden" name="action_author" value="delete">
                <input type="hidden" name="old_author" value="<?php echo htmlspecialchars($auth_item); ?>">
                <button type="submit" class="btn-action-del"><i class="fi fi-rr-trash"></i> ลบ</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div id="tab-pub" class="meta-tab-content" style="display:none;">
      <input type="text" id="searchPubMeta" class="modal-search-input" placeholder="🔍 พิมพ์ค้นหาชื่อสำนักพิมพ์..." onkeyup="filterMetaList('pub-row', this.value)">
      
      <div id="pubListContainer" style="max-height: 320px; overflow-y: auto;">
        <?php foreach($publisher_list as $pub_item): ?>
          <div class="meta-item-row pub-row" data-text="<?php echo htmlspecialchars(mb_strtolower($pub_item)); ?>">
            <span style="font-weight:600; font-size:14px; flex:1;"><?php echo htmlspecialchars($pub_item); ?></span>
            <div style="display:flex; gap:6px;">
              <button type="button" class="btn-action-edit" onclick="openMetaItemEdit('publisher', '<?php echo htmlspecialchars(addslashes($pub_item)); ?>', '<?php echo htmlspecialchars(addslashes($pub_item)); ?>')">
                <i class="fi fi-rr-edit"></i> แก้ไข
              </button>
              <form method="post" onsubmit="return confirm('ยืนยันล้างชื่อสำนักพิมพ์นี้ออกจากหนังสือทั้งหมด?');" style="margin:0;">
                <input type="hidden" name="action_publisher" value="delete">
                <input type="hidden" name="old_publisher" value="<?php echo htmlspecialchars($pub_item); ?>">
                <button type="submit" class="btn-action-del"><i class="fi fi-rr-trash"></i> ลบ</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn-cancel" onclick="closeMetaModal()">ปิดหน้าต่าง</button>
    </div>
  </div>
</div>

<div id="metaItemEditModal" class="modal-overlay" style="z-index: 1050;">
  <div class="modal-box" style="max-width: 450px;">
    <div class="modal-header">
      <h3 id="metaItemEditTitle">✏️ แก้ไขข้อมูล</h3>
      <button type="button" class="btn-close" onclick="closeMetaItemEdit()">&times;</button>
    </div>
    <form method="post">
      <input type="hidden" name="" id="metaActionField" value="edit">
      <input type="hidden" name="" id="metaKeyField">
      
      <div class="form-group full">
        <label id="metaItemInputLabel">ชื่อใหม่</label>
        <input type="text" name="" id="metaItemInputField" required>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn-cancel" onclick="closeMetaItemEdit()">ยกเลิก</button>
        <button type="submit" class="btn-submit-edit">💾 บันทึก</button>
      </div>
    </form>
  </div>
</div>

<?php if($is_superadmin): ?>
<div id="adminModal" class="modal-overlay">
  <div class="modal-box" style="max-width: 650px;">
    <div class="modal-header">
      <h3>👥 เพิ่มและจัดการแอดมิน</h3>
      <button type="button" class="btn-close" onclick="closeAdminModal()">&times;</button>
    </div>

    <div style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid rgba(130, 115, 175, 0.28); border-radius: 18px; padding: 18px; margin-bottom: 20px; box-shadow: 0 4px 14px rgba(120, 100, 160, 0.06);">
      <h4 style="margin: 0 0 12px; font-size: 15px; font-weight: 700; color: var(--text-primary);">➕ เพิ่มแอดมินคนใหม่</h4>
      <form method="post">
        <input type="hidden" name="action_admin" value="add">
        <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: flex-end;">
          <div>
            <label style="font-size: 12.5px; font-weight: 600; margin-bottom: 4px; display: block; color: var(--text-primary);">Username *</label>
            <input type="text" name="admin_username" placeholder="เช่น admin2" required style="width: 100%; padding: 8px 14px; border: 1.5px solid rgba(130, 115, 175, 0.28); border-radius: 12px; font-family: inherit; font-size: 13px; outline: none; background: #fff;">
          </div>
          <div>
            <label style="font-size: 12.5px; font-weight: 600; margin-bottom: 4px; display: block; color: var(--text-primary);">Password *</label>
            <input type="password" name="admin_password" placeholder="ตั้งรหัสผ่าน" required style="width: 100%; padding: 8px 14px; border: 1.5px solid rgba(130, 115, 175, 0.28); border-radius: 12px; font-family: inherit; font-size: 13px; outline: none; background: #fff;">
          </div>
          <button type="submit" class="btn-submit-edit" style="padding: 9px 20px; font-size: 13px;">
            + เพิ่ม
          </button>
        </div>
      </form>
    </div>

    <h4 style="margin: 0 0 10px; font-size: 15px; font-weight: 700; color: var(--text-primary);">รายชื่อแอดมินในระบบ</h4>
    <div style="max-height: 280px; overflow-y: auto; border: 1px solid rgba(133, 122, 177, 0.2); border-radius: 14px; background: #fff;">
      <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
          <tr style="background: rgba(246, 240, 252, 0.7); border-bottom: 1px solid rgba(133, 122, 177, 0.15);">
            <th style="padding: 10px 14px; font-size: 13.5px; font-weight: 700; color: var(--text-primary);">Username</th>
            <th style="padding: 10px 14px; font-size: 13.5px; font-weight: 700; color: var(--text-primary);">ระดับสิทธิ์</th>
            <th style="padding: 10px 14px; font-size: 13.5px; font-weight: 700; color: var(--text-primary); text-align: center; width: 80px;">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if($admin_list_query): ?>
            <?php while($adm = mysqli_fetch_assoc($admin_list_query)): ?>
              <tr style="border-bottom: 1px solid rgba(133, 122, 177, 0.1);">
                <td style="padding: 10px 14px; font-size: 13.5px; font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($adm['username']); ?></td>
                <td style="padding: 10px 14px;">
                  <?php if(($adm['role'] ?? '') === 'superadmin'): ?>
                    <span style="background: #fff0f3; color: #e05375; padding: 3px 10px; border-radius: 50px; font-size: 11px; font-weight: 600; border: 1px solid #ffd6df;">ผู้ดูแลระบบ</span>
                  <?php else: ?>
                    <span style="background: #eaf2fd; color: #3b78c4; padding: 3px 10px; border-radius: 50px; font-size: 11px; font-weight: 600; border: 1px solid #cce0fb;">แอดมิน</span>
                  <?php endif; ?>
                </td>
                <td style="padding: 10px 14px; text-align: center;">
                  <?php if(($adm['role'] ?? '') !== 'superadmin'): ?>
                    <a href="admin_books.php?delete_admin_user=<?php echo urlencode($adm['username']); ?>" 
                       class="btn-action-del" 
                       style="padding: 3px 10px; font-size: 11px;"
                       onclick="return confirm('ยืนยันลบแอดมิน <?php echo htmlspecialchars($adm['username']); ?> ออกจากระบบ?')">
                      ลบ
                    </a>
                  <?php else: ?>
                    <span style="font-size: 12px; color: #aaa;">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="modal-footer">
      <button type="button" class="btn-cancel" onclick="closeAdminModal()">ปิดหน้าต่าง</button>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function matchStartsWith(params, data) {
  if ($.trim(params.term) === '') {
    return data;
  }
  if (typeof data.text === 'undefined') {
    return null;
  }
  const term = params.term.toLowerCase().trim();
  const text = data.text.toLowerCase().trim();

  if (text.startsWith(term)) {
    return data;
  }
  return null;
}

$.fn.select2.amd.require(['select2/dropdown/attachBody'], function (AttachBody) {
  AttachBody.prototype._positionDropdown = function () {
    var $window = $(window);
    var isCurrentlyAbove = this.$dropdown.hasClass('select2-dropdown--above');
    var isCurrentlyBelow = this.$dropdown.hasClass('select2-dropdown--below');
    
    var offset = this.$container.offset();
    offset.bottom = offset.top + this.$container.outerHeight(false);

    var container = {
      height: this.$container.outerHeight(false)
    };

    var css = {
      top: offset.bottom,
      left: offset.left,
      width: this.$container.outerWidth(false)
    };

    this.$dropdownContainer.css(css);
    this.$dropdown.removeClass('select2-dropdown--above').addClass('select2-dropdown--below');
    this.$container.removeClass('select2-container--above').addClass('select2-container--below');
  };

  $(document).ready(function() {
    $('.filter-cat-select').select2({
      width: '100%',
      minimumResultsForSearch: Infinity
    });

    $('.filter-search-select').select2({
      width: '100%',
      matcher: matchStartsWith,
      minimumResultsForSearch: 0
    });
  });
});

function openEditModal(data) {
  document.getElementById('modalTitle').innerText = '✏️ แก้ไขข้อมูลหนังสือ (เล่มที่ ' + data.Book_id + ')';
  document.getElementById('edit_book_id').value = data.Book_id;
  document.getElementById('edit_Title').value = data.Title || '';
  document.getElementById('edit_Author').value = data.Author || '';
  document.getElementById('edit_Publisher').value = data.Publisher || '';
  document.getElementById('edit_image').value = data.image || '';
  document.getElementById('edit_Category_id').value = data.Category_id;
  document.getElementById('edit_Blurb').value = data.Blurb || '';

  document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
  document.getElementById('editModal').style.display = 'none';
}

function openMetaModal() {
  document.getElementById('metaModal').style.display = 'flex';
}

function closeMetaModal() {
  document.getElementById('metaModal').style.display = 'none';
}

function switchMetaTab(tabName) {
  document.querySelectorAll('.meta-tab-content').forEach(el => el.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

  document.getElementById('tab-' + tabName).style.display = 'block';
  event.target.classList.add('active');
}

function filterMetaList(className, query) {
  const val = query.toLowerCase().trim();
  const rows = document.querySelectorAll('.' + className);
  rows.forEach(row => {
    const text = (row.getAttribute('data-text') || '').trim();
    if (text.startsWith(val)) {
      row.style.display = 'flex';
    } else {
      row.style.display = 'none';
    }
  });
}

function openMetaItemEdit(type, keyVal, currentName) {
  const titleElem = document.getElementById('metaItemEditTitle');
  const labelElem = document.getElementById('metaItemInputLabel');
  const actionField = document.getElementById('metaActionField');
  const keyField = document.getElementById('metaKeyField');
  const inputField = document.getElementById('metaItemInputField');

  if(type === 'category'){
    titleElem.innerText = '✏️ แก้ไขชื่อหมวดหมู่';
    labelElem.innerText = 'ชื่อหมวดหมู่ใหม่';
    actionField.name = 'action_category';
    keyField.name = 'cat_id';
    inputField.name = 'cat_name';
  } else if(type === 'author'){
    titleElem.innerText = '✏️ แก้ไขชื่อผู้แต่ง';
    labelElem.innerText = 'ชื่อผู้แต่งใหม่';
    actionField.name = 'action_author';
    keyField.name = 'old_author';
    inputField.name = 'new_author';
  } else if(type === 'publisher'){
    titleElem.innerText = '✏️ แก้ไขชื่อสำนักพิมพ์';
    labelElem.innerText = 'ชื่อสำนักพิมพ์ใหม่';
    actionField.name = 'action_publisher';
    keyField.name = 'old_publisher';
    inputField.name = 'new_publisher';
  }

  actionField.value = 'edit';
  keyField.value = keyVal;
  inputField.value = currentName;

  document.getElementById('metaItemEditModal').style.display = 'flex';
}

function closeMetaItemEdit() {
  document.getElementById('metaItemEditModal').style.display = 'none';
}

function openAdminModal() {
  const modal = document.getElementById('adminModal');
  if(modal) modal.style.display = 'flex';
}

function closeAdminModal() {
  const modal = document.getElementById('adminModal');
  if(modal) modal.style.display = 'none';
}

window.onclick = function(event) {
  const modalEdit = document.getElementById('editModal');
  const modalMeta = document.getElementById('metaModal');
  const modalMetaItem = document.getElementById('metaItemEditModal');
  const modalAdmin = document.getElementById('adminModal');

  if (event.target === modalEdit) closeEditModal();
  if (event.target === modalMeta) closeMetaModal();
  if (event.target === modalMetaItem) closeMetaItemEdit();
  if (event.target === modalAdmin) closeAdminModal();
}
</script>

</body>
</html>