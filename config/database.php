<?php
/**
 * Database Configuration & PDO Connection
 * 
 * This file establishes a secure connection to the MySQL database
 * using PHP Data Objects (PDO) with prepared statements enabled.
 */

// Database credentials for local XAMPP environment
$db_host = 'localhost';
$db_name = 'clothing_store';
$db_user = 'root';
$db_pass = '';
$db_charset = 'utf8mb4';

// Data Source Name (DSN)
$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";

// PDO configuration options for security and usability
$options = [
    // Throw exceptions when SQL errors occur so we can catch them cleanly
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    
    // Fetch associative arrays by default (e.g. $row['name'] instead of $row[0])
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    
    // Disable emulated prepares to enforce native prepared statements against SQL injection
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    // Create the global PDO instance
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Stop execution and display a clear, helpful message if the connection fails
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
