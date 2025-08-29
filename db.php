<?php
$host = 'localhost';       // or your DB host
$db   = 'fireknott';   // replace with your DB name
$user = 'root';   // replace with your DB username
$pass = '';   // replace with your DB password
$charset = 'utf8mb4';

// DSN and PDO setup
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // fetch results as associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // use native prepares if supported
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
