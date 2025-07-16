<?php
// Create connection
// $conn = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);
$conn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
mysqli_set_charset($conn, "utf8");