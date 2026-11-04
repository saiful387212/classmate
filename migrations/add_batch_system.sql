-- ============================================================
-- Multi-Batch Admin System Migration
-- Run this SQL against the classmate_db database
-- ============================================================

-- 1. Create batches table
CREATE TABLE IF NOT EXISTS `batches` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL COMMENT 'e.g. 2022, 2023, Batch-A',
    `label` VARCHAR(100) NULL COMMENT 'Display label e.g. Batch 2022',
    `department` VARCHAR(100) NULL DEFAULT 'Geography and Environment',
    `session` VARCHAR(20) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_batch_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Add batch_id column to admins (NULL = super admin, not null = batch admin)
ALTER TABLE `admins`
    ADD COLUMN `batch_id` INT(11) NULL DEFAULT NULL AFTER `role`,
    ADD INDEX `idx_admin_batch_id` (`batch_id`);

-- 3. Add batch_id to classes
ALTER TABLE `classes`
    ADD COLUMN `batch_id` INT(11) NULL DEFAULT NULL AFTER `id`,
    ADD INDEX `idx_class_batch_id` (`batch_id`);

-- 4. Add batch_id to announcements
ALTER TABLE `announcements`
    ADD COLUMN `batch_id` INT(11) NULL DEFAULT NULL AFTER `id`,
    ADD INDEX `idx_ann_batch_id` (`batch_id`);

-- 5. Add batch_id to polls
ALTER TABLE `polls`
    ADD COLUMN `batch_id` INT(11) NULL DEFAULT NULL AFTER `id`,
    ADD INDEX `idx_poll_batch_id` (`batch_id`);

-- 6. Add batch_id to fund_events
ALTER TABLE `fund_events`
    ADD COLUMN `batch_id` INT(11) NULL DEFAULT NULL AFTER `id`,
    ADD INDEX `idx_fund_event_batch_id` (`batch_id`);

-- 7. Add batch_id to resources (if table exists)
ALTER TABLE `resources`
    ADD COLUMN `batch_id` INT(11) NULL DEFAULT NULL AFTER `id`,
    ADD INDEX `idx_resource_batch_id` (`batch_id`);

-- 8. Add batch_id reference to students (numeric FK to batches.id)
--    Students already have a `batch` varchar column.
--    We add a batch_id int column that links to batches.id
ALTER TABLE `students`
    ADD COLUMN `batch_id` INT(11) NULL DEFAULT NULL AFTER `batch`,
    ADD INDEX `idx_student_batch_id` (`batch_id`);

-- 9. Add batch_id to registration_requests too
ALTER TABLE `registration_requests`
    ADD COLUMN `batch_id` INT(11) NULL DEFAULT NULL AFTER `batch`,
    ADD INDEX `idx_reg_batch_id` (`batch_id`);

-- 10. Seed existing unique batches from students table
INSERT IGNORE INTO `batches` (`name`, `label`, `department`, `is_active`)
SELECT DISTINCT
    `batch` AS `name`,
    CONCAT('Batch ', `batch`) AS `label`,
    `department`,
    1
FROM `students`
WHERE `batch` IS NOT NULL AND `batch` != ''
ORDER BY `batch`;

-- 11. Update existing students batch_id based on their batch name
UPDATE `students` s
JOIN `batches` b ON b.`name` COLLATE utf8mb4_unicode_ci = s.`batch` COLLATE utf8mb4_unicode_ci
SET s.`batch_id` = b.`id`;

-- 12. Update existing registration_requests batch_id
UPDATE `registration_requests` r
JOIN `batches` b ON b.`name` COLLATE utf8mb4_unicode_ci = r.`batch` COLLATE utf8mb4_unicode_ci
SET r.`batch_id` = b.`id`;
