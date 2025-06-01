<?php
// src/JobStatus.php

enum JobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case FailedPermanently = 'failed_permanently'; // Для заданий, достигших MAX_RETRY_ATTEMPTS
}