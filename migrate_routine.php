<?php
require_once 'includes/config.php';
Auth::requireAdmin();
$db = Database::getInstance();

$sql = "
CREATE TABLE IF NOT EXISTS `class_routines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `course_id` int(11) NOT NULL,
  `day_of_week` varchar(20) NOT NULL COMMENT 'Sunday, Monday, etc.',
  `class_code` varchar(50) NOT NULL,
  `class_title` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `instructor` varchar(100) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_routine_batch_id` (`batch_id`),
  FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $connection = $db->getConnection();
    $connection->exec($sql);

    $column = $db->query("SHOW COLUMNS FROM `class_routines` LIKE 'batch_id'");
    if (!$column->fetch()) {
        $connection->exec("ALTER TABLE `class_routines` ADD COLUMN `batch_id` int(11) DEFAULT NULL AFTER `id`");
    }

    $index = $db->query("SHOW INDEX FROM `class_routines` WHERE `Key_name` = 'idx_routine_batch_id'");
    if (!$index->fetch()) {
        $connection->exec("ALTER TABLE `class_routines` ADD INDEX `idx_routine_batch_id` (`batch_id`)");
    }

    $connection->exec("
        CREATE TABLE IF NOT EXISTS `class_routine_occurrences` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `routine_id` int(11) NOT NULL,
            `class_date` date NOT NULL,
            `status` enum('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
            `updated_by` int(11) DEFAULT NULL,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_routine_occurrence_date` (`routine_id`, `class_date`),
            KEY `idx_routine_occurrence_date` (`class_date`),
            CONSTRAINT `fk_routine_occurrence_routine`
                FOREIGN KEY (`routine_id`) REFERENCES `class_routines` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    echo "Routine tables are ready.";
} catch(Throwable $e) {
    echo "Error: " . $e->getMessage();
}
