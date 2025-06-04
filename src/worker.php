<?php
// src/worker.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/PasswordCracker.php';
require_once __DIR__ . '/AttackType.php';
require_once __DIR__ . '/JobStatus.php';

// No need for 'use JobStatus;' if JobStatus is not in a namespace

if (php_sapi_name() !== 'cli') {
    die("This script is intended to be run from the command line.");
}

echo "PasswordCracker worker started.\n";

$pdo = getDbConnection();

const MAX_RETRY_ATTEMPTS = 3;
const STUCK_JOB_TIMEOUT_MINUTES = 5;

while (true) {
    try {
        $pdo->beginTransaction(); // Start a transaction to select and lock the job

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
            $pdo->commit(); // Conclude the job selection transaction

            // Update status to 'running' in a separate transaction
            $pdo->beginTransaction();
            $attackTypeString = $job['attack_type'];
            $currentRetryCount = (int)$job['retry_count'];
            $lastCheckedCombination = $job['last_checked_combination'];

            echo "Found job ID: $jobId, Type: $attackTypeString, Attempt No: " . ($currentRetryCount + 1) . ". Starting processing";
            if ($lastCheckedCombination) {
                echo " (resuming from: $lastCheckedCombination)";
            }
            echo ".\n";

            $updateStmt = $pdo->prepare(
                "UPDATE crack_jobs SET status = :status, start_time = NOW(), last_run_attempt = NOW(), retry_count = retry_count + 1, updated_at = NOW() WHERE job_id = :job_id"
            );
            $updateStmt->bindValue(':status', JobStatus::Running->value);
            $updateStmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
            $updateStmt->execute();
            $pdo->commit();

            // Initialise variables for final update
            $finalStatus = JobStatus::Failed->value;
            $progressForFinalUpdate = (float)($job['progress'] ?? 0.00); // Only used on success (will be set to 100)
            $resultsJsonForFinalUpdate = $job['results_json'] ?? null;
            $errorMessageForFinalUpdate = "Task processing was not successfully completed.";

            try {
                $attackType = AttackType::from($attackTypeString);
                $cracker = new PasswordCracker();
                $cracker->setCurrentJobId($jobId); // Ensure the method is PUBLIC

                $results = $cracker->crack($attackType, $lastCheckedCombination);

                $finalStatus = JobStatus::Completed->value;
                $errorMessageForFinalUpdate = null;
                $resultsJsonForFinalUpdate = json_encode($results['results'] ?? []);
                $progressForFinalUpdate = 100.00;

                echo "Job ID: $jobId completed successfully. Found: " . count($results['results'][$attackType->value] ?? []) . " passwords.\n";

            } catch (Throwable $t) { // Catch all errors and exceptions
                $errorMessageForFinalUpdate = $t->getMessage();
                $resultsJsonForFinalUpdate = null; // Do not save results on error

                $finalStatus = ($currentRetryCount + 1 >= MAX_RETRY_ATTEMPTS)
                    ? JobStatus::FailedPermanently->value
                    : JobStatus::Failed->value;

                // Do not change progress here to avoid overwriting the actual value updated by PasswordCracker

                echo "Job ID: $jobId failed with error: " . $errorMessageForFinalUpdate . ". Status: " . $finalStatus . "\n";
                error_log("Worker error for job $jobId: " . $t->getMessage() . "\n" . $t->getTraceAsString());
            } finally {
                $pdo->beginTransaction(); // Transaction for final job update

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
            $pdo->commit(); // Conclude transaction if no jobs found
            echo "No new jobs. Waiting...\n";
            sleep(5);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "Database error in worker: " . $e->getMessage() . "\n";
        error_log("Worker PDO error: " . $e->getMessage());
        sleep(10);
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "Unexpected worker error: " . $t->getMessage() . "\n";
        error_log("Worker general error: " . $t->getMessage() . "\n" . $t->getTraceAsString());
        sleep(10);
    }
}