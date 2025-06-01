<?php
// src/worker.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PasswordCracker.php';
require_once __DIR__ . '/AttackType.php';
require_once __DIR__ . '/JobStatus.php';

// Нет необходимости в 'use JobStatus;' если JobStatus не находится в пространстве имен

if (php_sapi_name() !== 'cli') {
    die("Этот скрипт предназначен для запуска из командной строки.");
}

echo "Воркер PasswordCracker запущен.\n";

$pdo = getDbConnection();

const MAX_RETRY_ATTEMPTS = 3;
const STUCK_JOB_TIMEOUT_MINUTES = 5;

while (true) {
    try {
        $pdo->beginTransaction(); // Начинаем транзакцию для выбора и блокировки задачи

        $stmt = $pdo->prepare(
            "SELECT * FROM crack_jobs
             WHERE status = :pending_status
                OR (status = :running_status AND last_run_attempt < NOW() - INTERVAL :stuck_timeout MINUTE)
                OR (status = :failed_status AND retry_count < :max_retries)
             ORDER BY created_at ASC
             LIMIT 1 FOR UPDATE"
        );
        $stmt->bindValue(':pending_status', JobStatus::Pending->value);
        $stmt->bindValue(':running_status', JobStatus::Running->value);
        $stmt->bindValue(':failed_status', JobStatus::Failed->value);
        $stmt->bindValue(':stuck_timeout', STUCK_JOB_TIMEOUT_MINUTES, PDO::PARAM_INT);
        $stmt->bindValue(':max_retries', MAX_RETRY_ATTEMPTS, PDO::PARAM_INT);
        $stmt->execute();
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($job) {
            $jobId = $job['job_id'];
            $pdo->commit(); // Завершаем транзакцию выбора задачи

            // Обновляем статус на 'running' в отдельной транзакции
            $pdo->beginTransaction();
            $attackTypeString = $job['attack_type'];
            $currentRetryCount = (int)$job['retry_count'];
            $lastCheckedCombination = $job['last_checked_combination'];

            echo "Найдено задание ID: $jobId, Тип: $attackTypeString, Попытка №: " . ($currentRetryCount + 1) . ". Начинаем обработку";
            if ($lastCheckedCombination) {
                echo " (продолжаем с: $lastCheckedCombination)";
            }
            echo ".\n";

            $updateStmt = $pdo->prepare(
                "UPDATE crack_jobs SET status = :status, start_time = NOW(), last_run_attempt = NOW(), retry_count = retry_count + 1, updated_at = NOW() WHERE job_id = :job_id"
            );
            $updateStmt->bindValue(':status', JobStatus::Running->value);
            $updateStmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
            $updateStmt->execute();
            $pdo->commit();

            // Инициализация переменных для финального обновления
            $finalStatus = JobStatus::Failed->value;
            $progressForFinalUpdate = (float)($job['progress'] ?? 0.00); // Используется только при успехе (установится в 100)
            $resultsJsonForFinalUpdate = $job['results_json'] ?? null;
            $errorMessageForFinalUpdate = "Обработка задачи не была успешно завершена.";

            try {
                $attackType = AttackType::from($attackTypeString);
                $cracker = new PasswordCracker();
                $cracker->setCurrentJobId($jobId); // Убедитесь, что метод ПУБЛИЧНЫЙ

                $results = $cracker->crack($attackType, $lastCheckedCombination);

                $finalStatus = JobStatus::Completed->value;
                $errorMessageForFinalUpdate = null;
                $resultsJsonForFinalUpdate = json_encode($results['results'] ?? []);
                $progressForFinalUpdate = 100.00;

                echo "Задание ID: $jobId завершено успешно. Найдено: " . count($results['results'][$attackType->value] ?? []) . " паролей.\n";

            } catch (Throwable $t) { // Ловим все ошибки и исключения
                $errorMessageForFinalUpdate = $t->getMessage();
                $resultsJsonForFinalUpdate = null; // При ошибке результаты не сохраняем

                $finalStatus = ($currentRetryCount + 1 >= MAX_RETRY_ATTEMPTS)
                    ? JobStatus::FailedPermanently->value
                    : JobStatus::Failed->value;

                // Прогресс не меняем здесь, чтобы не затереть актуальное значение, обновляемое PasswordCracker

                echo "Задание ID: $jobId завершилось с ошибкой: " . $errorMessageForFinalUpdate . ". Статус: " . $finalStatus . "\n";
                error_log("Worker error for job $jobId: " . $t->getMessage() . "\n" . $t->getTraceAsString());
            } finally {
                $pdo->beginTransaction(); // Транзакция для финального обновления задачи

                $sqlSetParts = [
                    "status = :status",
                    "results_json = :results_json",
                    "end_time = NOW()",
                    "error_message = :error_message",
                    "updated_at = NOW()"
                ];

                if ($finalStatus === JobStatus::Completed->value) {
                    $sqlSetParts[] = "progress = :progress";
                }

                $sql = "UPDATE crack_jobs SET " . implode(", ", $sqlSetParts) . " WHERE job_id = :job_id";
                $updateFinalStmt = $pdo->prepare($sql);

                $updateFinalStmt->bindValue(':status', $finalStatus);
                $updateFinalStmt->bindValue(':results_json', $resultsJsonForFinalUpdate);
                $updateFinalStmt->bindValue(':error_message', $errorMessageForFinalUpdate);
                $updateFinalStmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);

                if ($finalStatus === JobStatus::Completed->value) {
                    $updateFinalStmt->bindValue(':progress', $progressForFinalUpdate);
                }
                $updateFinalStmt->execute();
                $pdo->commit();
            }
        } else {
            $pdo->commit(); // Завершаем транзакцию, если задач не найдено
            echo "Нет новых заданий. Ожидание...\n";
            sleep(5);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "Ошибка базы данных в воркере: " . $e->getMessage() . "\n";
        error_log("Worker PDO error: " . $e->getMessage());
        sleep(10);
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "Непредвиденная ошибка воркера: " . $t->getMessage() . "\n";
        error_log("Worker general error: " . $t->getMessage() . "\n" . $t->getTraceAsString());
        sleep(10);
    }
}