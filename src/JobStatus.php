<?php
// src/JobStatus.php

enum JobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case FailedPermanently = 'failed_permanently'; // For jobs that have reached MAX_RETRY_ATTEMPTS
}