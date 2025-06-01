-- init_crack_jobs_table.sql

USE cracker_db;

CREATE TABLE IF NOT EXISTS `crack_jobs` (
                                            `job_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                                            `attack_type` VARCHAR(50) NOT NULL,
                                            `status` VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'running', 'completed', 'failed', 'failed_permanently'
                                            `progress` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                                            `results_json` JSON NULL, -- Для хранения JSON-объекта с результатами
                                            `start_time` DATETIME NULL,
                                            `end_time` DATETIME NULL,
                                            `error_message` TEXT NULL,
                                            `last_run_attempt` DATETIME NULL,
                                            `retry_count` INT(10) UNSIGNED NOT NULL DEFAULT 0,
                                            `last_checked_combination` VARCHAR(10) NULL, -- НОВОЕ ПОЛЕ: Для сохранения точки останова брутфорса
                                            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                            PRIMARY KEY (`job_id`),
                                            INDEX (`status`),
                                            INDEX (`created_at`),
                                            INDEX (`last_run_attempt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;