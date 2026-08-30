<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "absensi_rfid";

$conn = new mysqli($host, $username, $password, $database);

// Tambahkan fungsi logging
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>
