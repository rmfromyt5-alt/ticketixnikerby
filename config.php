<?php
require_once __DIR__ . '/load_env.php';
// Load environment variables from .env if present
ticketix_load_env(__DIR__ . '/.env');

// Database configuration (env first, fallback to local defaults)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USERNAME', getenv('DB_USERNAME') ?: 'root');
// MySQL root password - update this if your MySQL root password is different
// If no password is set in environment, use empty string (no password)
$dbPassword = getenv('DB_PASSWORD');
if ($dbPassword === false || $dbPassword === '') {
	$dbPassword = ''; // No password
}
define('DB_PASSWORD', $dbPassword);
define('DB_NAME', getenv('DB_NAME') ?: 'ticketix');

// Function to create database connection
function getDBConnection() {
	$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
	
	if ($conn->connect_error) {
		if ($conn->connect_errno == 1049) {
			die("Database '" . DB_NAME . "' not found! Please run setup_database.php first to create the database.");
		} else {
			die("Connection failed: " . $conn->connect_error);
		}
	}
	
	return $conn;
}
?>