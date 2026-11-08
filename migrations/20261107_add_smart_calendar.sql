CREATE TABLE IF NOT EXISTS `academic_events` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `department` VARCHAR(100) NULL,
    `batch_id` INT(11) NULL DEFAULT NULL,
    `course_id` INT(11) NULL DEFAULT NULL,
    `created_by` INT(11) NULL DEFAULT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `event_type` ENUM('examination', 'presentation', 'department', 'seminar', 'holiday', 'academic') NOT NULL DEFAULT 'department',
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME NULL,
    `location` VARCHAR(200) NULL,
    `is_all_day` TINYINT(1) NOT NULL DEFAULT 0,
    `visibility` ENUM('all', 'batch') NOT NULL DEFAULT 'batch',
    `status` ENUM('scheduled', 'cancelled') NOT NULL DEFAULT 'scheduled',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_academic_events_batch_start` (`batch_id`, `start_datetime`),
    KEY `idx_academic_events_type_start` (`event_type`, `start_datetime`),
    CONSTRAINT `fk_academic_events_batch`
        FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_academic_events_course`
        FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_academic_events_creator`
        FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `personal_events` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_type` ENUM('admin', 'student') NOT NULL,
    `user_id` INT(11) NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME NULL,
    `location` VARCHAR(200) NULL,
    `is_all_day` TINYINT(1) NOT NULL DEFAULT 0,
    `reminder_minutes` SMALLINT(5) UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_personal_events_owner_start` (`user_type`, `user_id`, `start_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `calendar_preferences` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_type` ENUM('admin', 'student') NOT NULL,
    `user_id` INT(11) NOT NULL,
    `default_view` ENUM('month', 'week', 'day', 'agenda') NOT NULL DEFAULT 'month',
    `visible_categories` VARCHAR(500) NOT NULL DEFAULT 'class,assignment,examination,presentation,department,seminar,holiday,academic,personal',
    `reminder_minutes` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 1440,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_calendar_preferences_owner` (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
