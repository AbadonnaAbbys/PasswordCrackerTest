<?php

// Константы для подключения к базе данных MySQL
// Эти значения берутся из docker-compose.yml
define('DB_HOST', getenv('MYSQL_HOST') ?: 'db');
define('DB_USER', getenv('MYSQL_USER') ?: 'user');
define('DB_PASSWORD', getenv('MYSQL_PASSWORD') ?: 'password');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'cracker_db');

// Константы для хеширования паролей
const SALT = 'ThisIs-A-Salt123';

/**
 * Функция для хеширования строки с солью, как указано в задании.
 * @param string $string Исходная строка (пароль).
 * @return string Хеш MD5 с солью.
 */
function salter(string $string): string
{
    return md5($string . SALT);
}

/**
 * Функция для получения соединения с базой данных.
 * Используем PDO для более гибкой и безопасной работы с БД.
 * @return PDO Объект PDO для работы с базой данных.
 * @throws PDOException Если не удается подключиться к базе данных.
 */
function getDbConnection(): PDO
{
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Выбрасывать исключения при ошибках
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Возвращать строки как ассоциативные массивы
        PDO::ATTR_EMULATE_PREPARES   => false,                 // Отключаем эмуляцию подготовленных запросов для повышения безопасности
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASSWORD, $options);
    } catch (PDOException $e) {
        // В продакшене лучше не выводить сообщение об ошибке пользователю,
        // а логировать ее. Но для разработки это полезно.
        die("Ошибка подключения к базе данных: " . $e->getMessage());
    }
}