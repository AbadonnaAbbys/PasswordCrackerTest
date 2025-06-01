<?php
// src/public/jobs.php

// Подключаем необходимые конфигурационные файлы и перечисления
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../JobStatus.php'; // Возможно, понадобится для фильтрации/форматирования

// Устанавливаем заголовок Content-Type для ответа в формате JSON
header('Content-Type: application/json');

try {
    // Получаем соединение с базой данных
    $pdo = getDbConnection();

    // Извлекаем все задачи из таблицы crack_jobs
    // Сортируем по created_at в убывающем порядке, чтобы новые задачи были сверху
    // Выбираем все необходимые поля для отображения на фронтенде
    $stmt = $pdo->query("SELECT job_id, attack_type, status, progress, last_checked_combination, results_json, error_message, created_at, updated_at, retry_count FROM crack_jobs ORDER BY created_at DESC");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Возвращаем успешный ответ с массивом задач
    echo json_encode([
        'status' => 'success',
        'jobs' => $jobs
    ]);

} catch (PDOException $e) {
    // Логируем ошибку базы данных и возвращаем сообщение об ошибке
    error_log("Database error fetching jobs: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Ошибка базы данных при получении задач.']);
} catch (Exception $e) {
    // Логируем общую ошибку и возвращаем сообщение об ошибке
    error_log("General error fetching jobs: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Произошла непредвиденная ошибка.']);
}
