<?php
// src/public/index.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../AttackType.php';
require_once __DIR__ . '/../JobStatus.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Неверный метод запроса. Ожидается POST.']);
    exit;
}

// Получаем тип атаки из JSON-тела запроса
$input = json_decode(file_get_contents('php://input'), true);
$attackTypeString = $input['attackType'] ?? null;

if ($attackTypeString === null) {
    echo json_encode(['status' => 'error', 'message' => 'Параметр attackType не передан.']);
    exit;
}

try {
    $attackType = AttackType::from($attackTypeString);

    // Получаем соединение с БД
    $pdo = getDbConnection();

    // Создаем новую запись о задании в таблице crack_jobs
    $stmt = $pdo->prepare("INSERT INTO crack_jobs (attack_type, status, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$attackType->value, JobStatus::Pending->value]);

    $jobId = $pdo->lastInsertId(); // Получаем ID только что созданного задания

    // Возвращаем клиенту ID задания
    echo json_encode([
        'status' => 'success',
        'message' => 'Задача по взлому принята в обработку.',
        'job_id' => $jobId,
        'attack_type' => $attackType->value
    ]);

} catch (ValueError $e) {
    // Возникает, если $attackTypeString не соответствует ни одному AttackType
    error_log("Input error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Неизвестный или невалидный тип атаки: ' . htmlspecialchars($attackTypeString)]);
    exit;
} catch (PDOException $e) {
    // Логируем ошибку, но пользователю показываем общее сообщение
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Ошибка базы данных. Пожалуйста, попробуйте позже.']);
} catch (Exception $e) {
    // Общая ошибка
    error_log("General error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Произошла непредвиденная ошибка.']);
}