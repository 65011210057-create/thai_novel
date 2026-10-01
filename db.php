<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 3306;
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'thai_novel';

$conn = mysqli_init();

if (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
    mysqli_ssl_set($conn, NULL, NULL, '/etc/ssl/certs/ca-certificates.crt', NULL, NULL);
} else {
    mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
}

$connected = mysqli_real_connect(
    $conn,
    $host,
    $user,
    $pass,
    $db,
    (int)$port,
    NULL,
    MYSQLI_CLIENT_SSL
);

if (!$connected) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>
