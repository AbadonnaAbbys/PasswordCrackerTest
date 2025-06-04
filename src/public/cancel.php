<?php
// src/public/cancel.php

// Include necessary configuration files and enums
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../JobStatus.php';

// Set Content-Type header for JSON response
header('Content-Type: application/json');

// Check that the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. POST is expected.']);
    exit;
}

// Get job_id from JSON request body
$input = json_decode(file_get_contents('php://input'), true);
$jobId = $input['job_id'] ?? null;

// Check that job_id was provided
if ($jobId === null) {
    echo json_encode(['status' => 'error', 'message' => 'The job_id parameter was not provided.']);
    exit;
}

try {
    // Get database connection
    $pdo = getDbConnection();

    // Update job status to 'failed_permanently' (or 'cancelled', if such a status exists)
    // This will stop the worker from further processing this job.
    // The WHERE clause ensures that we only cancel jobs in specific statuses.
    $stmt = $pdo->prepare(
        "UPDATE crack_jobs
         SET status = :status, error_message = :error_message, end_time = NOW(), updated_at = NOW()
         WHERE job_id = :job_id AND status IN (:pending_status, :running_status, :failed_status)"
    );
    $stmt->bindValue(':status', JobStatus::FailedPermanently->value); // Use FailedPermanently for cancellation
    $stmt->bindValue(':error_message', 'Task cancelled by user.');
    $stmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
    $stmt->bindValue(':pending_status', JobStatus::Pending->value);
    $stmt->bindValue(':running_status', JobStatus::Running->value);
    $stmt->bindValue(':failed_status', JobStatus::Failed->value); // Failed jobs can also be cancelled to prevent retries
    $stmt->execute();

    // Check if the job was actually updated
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => "Job ID $jobId successfully cancelled."
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => "Job ID $jobId not found or cannot be cancelled in its current status."
        ]);
    }

} catch (PDOException $e) {
    // Log database error and return an error message
    error_log("Database error cancelling job: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error when cancelling job.']);
} catch (Exception $e) {
    // Log unexpected errors
    error_log("Unexpected error cancelling job: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred when cancelling the job.']);
}