<?php
// src/public/index.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../AttackType.php';
require_once __DIR__ . '/../JobStatus.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. POST is expected.']);
    exit;
}

// Get attack type from JSON request body
$input = json_decode(file_get_contents('php://input'), true);
$attackTypeString = $input['attackType'] ?? null;

if ($attackTypeString === null) {
    echo json_encode(['status' => 'error', 'message' => 'The attackType parameter was not provided.']);
    exit;
}

try {
    $attackType = AttackType::from($attackTypeString);

    // Get DB connection
    $pdo = getDbConnection();

    // Create a new job record in the crack_jobs table
    $stmt = $pdo->prepare("INSERT INTO crack_jobs (attack_type, status, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$attackType->value, JobStatus::Pending->value]);

    $jobId = $pdo->lastInsertId(); // Get the ID of the newly created job

    // Return the job ID to the client
    echo json_encode([
        'status' => 'success',
        'message' => 'Cracking job accepted for processing.',
        'job_id' => $jobId,
        'attack_type' => $attackType->value
    ]);

} catch (ValueError $e) {
    // Occurs if $attackTypeString does not match any AttackType
    error_log("Input error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Unknown or invalid attack type: ' . htmlspecialchars($attackTypeString)]);
    exit;
} catch (PDOException $e) {
    // Log the error, but show a general message to the user
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error when submitting job.']);
} catch (Exception $e) {
    // Catch any other unexpected errors
    error_log("Unexpected error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred.']);
}