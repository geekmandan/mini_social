<?php
$dsn = "mysql:host=127.0.0.1;dbname=mini_social;charset=utf8mb4";
$dbUser = "user";
$dbPass = "admin123;";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    die("Ошибка подключения к базе: " . $e->getMessage());
}
