<?php
session_start();

// =====================================
// การเชื่อมต่อฐานข้อมูล TiDB Cloud / Local
// =====================================
$db_host = getenv("DB_HOST") ?: "gateway01.ap-southeast-1.prod.aws.tidbcloud.com";
$db_user = getenv("DB_USER") ?: "4JodNqEkbc1nEbH.root";
$db_pass = getenv("DB_PASS") ?: "zF4DHIXiUrHylslj";
$db_name = getenv("DB_NAME") ?: "thai_novel";
$db_port = getenv("DB_PORT") ?: 4000;

$conn = mysqli_init();

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

// ถ้าล็อกอินอยู่แล้วให้ไปหน้า admin_books.php ทันที
if(isset($_SESSION['admin_logged_in'])){
    header("Location: admin_books.php");
    exit;
}

$error = "";
if(isset($_POST['login'])){
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = trim($_POST['password']);

    $res = mysqli_query($conn, "SELECT * FROM admin WHERE username='$username' LIMIT 1");
    if($res && mysqli_num_rows($res) > 0){
        $admin = mysqli_fetch_assoc($res);
        $db_pass = trim($admin['password']);

        if(password_verify($password, $db_pass) || $password === $db_pass){
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_name'] = $admin['name'];
            $_SESSION['admin_role'] = $admin['role'] ?? 'admin';
            header("Location: admin_books.php");
            exit;
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
        }
    } else {
        $error = "ไม่พบบัญชีผู้ใช้งาน ($username) ในระบบ";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>เข้าสู่ระบบผู้ดูแลระบบ — THAI Novel Book</title>

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
  --glass-bg: rgba(255, 255, 255, 0.6);
  --glass-border: rgba(255, 255, 255, 0.9);
  --card-bg: rgba(255, 255, 255, 0.88);
}

* { box-sizing: border-box; }

body {
  margin: 0;
  font-family: 'Prompt', sans-serif;
  color: var(--text-primary);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: 24px 16px;
  background-color: #f2ecf9;
  background-image: 
    radial-gradient(circle at 10% 15%, rgba(255, 185, 130, 0.82) 0%, transparent 45%),
    radial-gradient(circle at 90% 12%, rgba(246, 194, 255, 0.8) 0%, transparent 45%),
    radial-gradient(circle at 85% 85%, rgba(193, 205, 255, 0.85) 0%, transparent 55%),
    radial-gradient(circle at 12% 88%, rgba(255, 212, 179, 0.75) 0%, transparent 45%),
    radial-gradient(circle at 50% 50%, rgba(244, 238, 253, 0.94) 0%, transparent 100%);
  background-attachment: fixed;
  -webkit-font-smoothing: antialiased;
}

/* ปุ่มย้อนกลับหน้าแรก */
.nav-back-home {
  position: absolute;
  top: 24px;
  left: 24px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.85);
  border: 1.5px solid rgba(255, 255, 255, 0.95);
  border-radius: 50px;
  padding: 8px 18px;
  text-decoration: none;
  color: var(--text-primary);
  font-size: 13.5px;
  font-weight: 600;
  box-shadow: 0 4px 14px rgba(150, 135, 185, 0.16);
  transition: all 0.25s ease;
}

.nav-back-home i {
  color: #ff758c;
  font-size: 13px;
  transition: transform 0.2s ease;
}

.nav-back-home:hover {
  transform: translateY(-2px);
  background: #ffffff;
  color: #ff758c;
  box-shadow: 0 8px 20px rgba(255, 117, 140, 0.25);
}

.nav-back-home:hover i {
  transform: translateX(-3px);
}

/* กล่องการ์ดล็อกอิน */
.login-card {
  background: var(--glass-bg);
  backdrop-filter: blur(32px);
  -webkit-backdrop-filter: blur(32px);
  border: 1.5px solid var(--glass-border);
  border-radius: 36px;
  box-shadow: 0 24px 60px rgba(113, 93, 158, 0.16);
  padding: 40px 36px;
  width: 100%;
  max-width: 420px;
  text-align: left;
  position: relative;
}

/* ส่วนหัวแบรนด์ */
.brand-title-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-bottom: 28px;
  text-decoration: none;
  transition: transform 0.2s ease;
}

.brand-title-wrap:hover {
  transform: translateY(-2px);
}

.brand-icon-box {
  width: 50px;
  height: 50px;
  background: linear-gradient(135deg, #ffd3b6 0%, #ffaaa6 100%);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;
  font-size: 22px;
  box-shadow: 0 8px 18px rgba(255, 170, 166, 0.45);
}

.brand-text {
  font-size: 24px;
  font-weight: 800;
  color: #252244;
  letter-spacing: -0.3px;
  line-height: 1;
  text-align: center;
}

/* กล่องแจ้งเตือน Error */
.alert-error {
  background: #fff0f3;
  border: 1.2px solid #ffd6df;
  box-shadow: 0 4px 14px rgba(224, 83, 117, 0.12);
  border-radius: 16px;
  padding: 11px 16px;
  font-size: 13px;
  font-weight: 600;
  color: #e05375;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 9px;
}

.alert-error i {
  font-size: 15px;
  flex-shrink: 0;
}

.form-group {
  margin-bottom: 18px;
}

label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  margin-bottom: 7px;
  color: var(--text-primary);
  padding-left: 2px;
}

label i {
  color: #6c52b5;
  font-size: 13px;
}

.input-retro {
  width: 100%;
  box-sizing: border-box;
  padding: 11px 18px;
  border-radius: 50px;
  border: 1.5px solid rgba(130, 115, 175, 0.28);
  background: rgba(255, 255, 255, 0.95);
  font-family: 'Prompt', sans-serif;
  font-size: 14px;
  color: var(--text-primary);
  outline: none;
  box-shadow: 0 2px 8px rgba(110, 95, 155, 0.05);
  transition: all 0.22s ease;
}

.input-retro::placeholder {
  color: #928ca8;
}

.input-retro:focus {
  background: #ffffff;
  border-color: #6c52b5;
  box-shadow: 0 4px 16px rgba(108, 82, 181, 0.2);
}

.btn-login-submit {
  width: 100%;
  background: linear-gradient(135deg, #302b63 0%, #24243e 100%);
  color: #ffffff;
  border: none;
  box-shadow: 0 6px 18px rgba(48, 43, 99, 0.3);
  border-radius: 50px;
  padding: 12px;
  font-family: 'Prompt', sans-serif;
  font-size: 14.5px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.22s ease;
}

.btn-login-submit:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 22px rgba(48, 43, 99, 0.4);
}

@media (max-width: 580px) {
  .nav-back-home {
    position: static;
    margin-bottom: 16px;
    align-self: flex-start;
  }
}
</style>
</head>
<body>

<!-- ปุ่มลัดกลับหน้าหลัก -->
<a href="index.php" class="nav-back-home">
  <i class="fi fi-rr-arrow-left"></i>
  <span>กลับหน้าหลัก</span>
</a>

<div class="login-card">
  <!-- คลิกโลโก้เพื่อกลับหน้าหลัก -->
  <a href="index.php" class="brand-title-wrap" title="กลับสู่หน้าหลัก">
    <div class="brand-icon-box">
      <i class="fi fi-rr-book-bookmark"></i>
    </div>
    <div class="brand-text">THAI Novel Book</div>
  </a>

  <?php if($error): ?>
    <div class="alert-error">
      <i class="fi fi-rr-cross-circle"></i>
      <span><?php echo htmlspecialchars($error); ?></span>
    </div>
  <?php endif; ?>

  <form method="post">
    <div class="form-group">
      <label>
        <i class="fi fi-rr-user"></i>
        <span>ชื่อผู้ใช้งาน (Username)</span>
      </label>
      <input type="text" name="username" class="input-retro" placeholder="กรอก Username ผู้ดูแล" required autofocus>
    </div>

    <div class="form-group">
      <label>
        <i class="fi fi-rr-lock"></i>
        <span>รหัสผ่าน (Password)</span>
      </label>
      <input type="password" name="password" class="input-retro" placeholder="••••••••" required>
    </div>

    <button type="submit" name="login" class="btn-login-submit">
      <i class="fi fi-rr-sign-in-alt"></i> เข้าสู่ระบบ
    </button>
  </form>
</div>

</body>
</html>
