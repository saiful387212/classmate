-- Run after migrations/add_batch_system.sql and migrate_routine.php.
ALTER TABLE `class_routines`
    ADD COLUMN `batch_id` INT(11) NULL DEFAULT NULL AFTER `id`,
    ADD INDEX `idx_routine_batch_id` (`batch_id`);

UPDATE `class_routines`
SET `batch_id` = (SELECT MIN(`id`) FROM `batches`)
WHERE `batch_id` IS NULL AND (SELECT COUNT(*) FROM `batches`) = 1;

UPDATE `classes`
SET `batch_id` = (SELECT MIN(`id`) FROM `batches`)
WHERE `batch_id` IS NULL AND (SELECT COUNT(*) FROM `batches`) = 1;

UPDATE `announcements`
SET `batch_id` = (SELECT MIN(`id`) FROM `batches`)
WHERE `batch_id` IS NULL AND (SELECT COUNT(*) FROM `batches`) = 1;

UPDATE `polls`
SET `batch_id` = (SELECT MIN(`id`) FROM `batches`)
WHERE `batch_id` IS NULL AND (SELECT COUNT(*) FROM `batches`) = 1;

UPDATE `fund_events`
SET `batch_id` = (SELECT MIN(`id`) FROM `batches`)
WHERE `batch_id` IS NULL AND (SELECT COUNT(*) FROM `batches`) = 1;

UPDATE `resources`
SET `batch_id` = (SELECT MIN(`id`) FROM `batches`)
WHERE `batch_id` IS NULL AND (SELECT COUNT(*) FROM `batches`) = 1;

INSERT IGNORE INTO `batches` (`name`, `label`, `department`, `is_active`)
VALUES
    ('First year', 'First year', 'Geography and Environment', 1),
    ('Second year', 'Second year', 'Geography and Environment', 1),
    ('Third Year', 'Third Year', 'Geography and Environment', 1),
    ('Fourth year', 'Fourth year', 'Geography and Environment', 1);

UPDATE `batches`
SET
    `name` = CASE LOWER(TRIM(`name`))
        WHEN 'first year' THEN 'First year'
        WHEN 'second year' THEN 'Second year'
        WHEN 'third year' THEN 'Third Year'
        WHEN 'fourth year' THEN 'Fourth year'
        ELSE `name`
    END,
    `label` = CASE LOWER(TRIM(`name`))
        WHEN 'first year' THEN 'First year'
        WHEN 'second year' THEN 'Second year'
        WHEN 'third year' THEN 'Third Year'
        WHEN 'fourth year' THEN 'Fourth year'
        ELSE `label`
    END,
    `is_active` = CASE
        WHEN LOWER(TRIM(`name`)) IN ('first year', 'second year', 'third year', 'fourth year') THEN 1
        ELSE 0
    END;