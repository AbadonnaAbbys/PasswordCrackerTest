<?php
// src/public/jobs.php

// Include necessary configuration files and enums
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../JobStatus.php'; // May be needed for filtering/formatting

// Set Content-Type header for JSON response
header('Content-Type: application/json');

try {
    // Get database connection
    $pdo = getDbConnection();

    // Retrieve all jobs from the crack_jobs table
    // Order by created_at in descending order so new jobs appear at the top
    // Select all necessary fields for frontend display
    $stmt = $pdo->query("SELECT job_id, attack_type, status, progress, last_checked_combination, 
            results_json, error_message, created_at, updated_at, retry_count FROM crack_jobs ORDER BY created_at DESC");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return a successful response with the array of jobs
    echo json_encode([
        'status' => 'success',
        'jobs' => $jobs
    ]);

} catch (PDOException $e) {
    // Log database error and return an error message
    error_log("Database error fetching jobs: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error when retrieving jobs.']);
} catch (Exception $e) {
    // Log unexpected errors
    error_log("Unexpected error fetching jobs: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred when retrieving jobs.']);
}