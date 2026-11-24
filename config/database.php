<?php
/**
 * Database Configuration
 * Edit the constants below to match your XAMPP / Laragon MySQL setup.
 */

// Database Configuration with Docker and local environment support
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'laporkota');
define('DB_PORT', getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die('Koneksi database gagal: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
