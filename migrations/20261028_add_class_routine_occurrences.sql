CREATE TABLE IF NOT EXISTS `class_routine_occurrences` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `routine_id` INT(11) NOT NULL,
    `class_date` DATE NOT NULL,
    `status` ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    `updated_by` INT(11) DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_routine_occurrence_date` (`routine_id`, `class_date`),
    KEY `idx_routine_occurrence_date` (`class_date`),
    CONSTRAINT `fk_routine_occurrence_routine`
        FOREIGN KEY (`routine_id`) REFERENCES `class_routines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
