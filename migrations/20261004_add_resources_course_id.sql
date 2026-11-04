ALTER TABLE `resources`
    ADD COLUMN `course_id` INT NULL AFTER `id`,
    ADD INDEX `idx_resources_course_id` (`course_id`);