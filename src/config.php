<?php

// Constants for MySQL database connection
// These values are taken from docker-compose.yml
define('DB_HOST', getenv('MYSQL_HOST') ?: 'db');
define('DB_USER', getenv('MYSQL_USER') ?: 'user');
define('DB_PASSWORD', getenv('MYSQL_PASSWORD') ?: 'password');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'cracker_db');

// Constants for password hashing
const SALT = 'ThisIs-A-Salt123';

/**
 * Function for hashing a string with salt, as specified in the task.
 * @param string $string The original string (password).
 * @return string The MD5 hash with salt.
 */
function salter(string $string): string
{
    return md5($string . SALT);
}

/**
 * Function for getting a database connection.
 * Uses PDO for more flexible and secure DB interaction.
 * @return PDO PDO object for database interaction.
 * @throws PDOException If the database connection fails.
 */
function getDbConnection(): PDO
{
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Return rows as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                 // Disable emulation of prepared statements for enhanced security
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    } catch (PDOException $e) {
        // In production, it's better not to display the error message to the user,
        // but to log it. However, for development, this is useful.
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection error");
    }
}