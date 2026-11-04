ALTER TABLE `resources`
    ADD COLUMN IF NOT EXISTS `average_rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS `views_count` INT(11) UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `download_count` INT(11) UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `resource_bookmarks` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `resource_id` INT(11) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_resource_bookmark_student_resource` (`student_id`, `resource_id`),
    KEY `idx_resource_bookmarks_resource_id` (`resource_id`),
    CONSTRAINT `fk_resource_bookmarks_student`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_resource_bookmarks_resource`
        FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `resource_reviews` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `resource_id` INT(11) NOT NULL,
    `rating` TINYINT(1) UNSIGNED NOT NULL,
    `review_text` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_resource_review_student_resource` (`student_id`, `resource_id`),
    KEY `idx_resource_reviews_resource_id` (`resource_id`),
    CONSTRAINT `fk_resource_reviews_student`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_resource_reviews_resource`
        FOREIGN KEY (`resource_id`) REFERENCES `resources` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
