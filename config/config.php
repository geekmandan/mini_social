<?php
$dsn = "mysql:host=127.0.0.1;dbname=mini_social;charset=utf8mb4";
$dbUser = "root";
$dbPass = "";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    die("Error connecting to the database: " . $e->getMessage());
}
