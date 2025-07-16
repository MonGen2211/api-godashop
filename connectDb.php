<?php
// Create connection
// $conn = new mysqli(SERVERNAME, USERNAME, PASSWORD, DBNAME);
$conn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}