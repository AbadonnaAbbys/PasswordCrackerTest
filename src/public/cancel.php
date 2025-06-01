<?php
// src/public/cancel.php

// Подключаем необходимые конфигурационные файлы и перечисления
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../JobStatus.php';

// Устанавливаем заголовок Content-Type для ответа в формате JSON
header('Content-Type: application/json');

// Проверяем, что метод запроса является POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Неверный метод запроса. Ожидается POST.']);
    exit;
}

// Получаем job_id из JSON-тела запроса
$input = json_decode(file_get_contents('php://input'), true);
$jobId = $input['job_id'] ?? null;

// Проверяем, что job_id был передан
if ($jobId === null) {
    echo json_encode(['status' => 'error', 'message' => 'Параметр job_id не передан.']);
    exit;
}

try {
    // Получаем соединение с базой данных
    $pdo = getDbConnection();

    // Обновляем статус задачи на 'failed_permanently' (или 'cancelled', если такой статус есть)
    // Это остановит воркер от дальнейшей обработки этой задачи.
    // Условие WHERE гарантирует, что мы отменяем только задачи в определенных статусах.
    $stmt = $pdo->prepare(
        "UPDATE crack_jobs SET status = :status, error_message = :error_message, updated_at = NOW() WHERE job_id = :job_id AND (status = :pending_status OR status = :running_status OR status = :failed_status)"
    );
    $stmt->bindValue(':status', JobStatus::FailedPermanently->value); // Используем FailedPermanently для отмены
    $stmt->bindValue(':error_message', 'Задача отменена пользователем.');
    $stmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
    $stmt->bindValue(':pending_status', JobStatus::Pending->value);
    $stmt->bindValue(':running_status', JobStatus::Running->value);
    $stmt->bindValue(':failed_status', JobStatus::Failed->value); // Можно отменить и проваленные, чтобы не было повторных попыток
    $stmt->execute();

    // Проверяем, была ли задача фактически обновлена
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => "Задача ID $jobId успешно отменена."
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => "Задача ID $jobId не найдена или не может быть отменена в текущем статусе."
        ]);
    }

} catch (PDOException $e) {
    // Логируем ошибку базы данных и возвращаем сообщение об ошибке
    error_log("Database error cancelling job: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Ошибка базы данных при отмене задачи.']);
} catch (Exception $e) {
    // Логируем общую ошибку и возвращаем сообщение об ошибке
    error_log("General error cancelling job: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Произошла непредвиденная ошибка.']);
}
