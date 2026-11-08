CREATE TABLE IF NOT EXISTS `memory_categories` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(80) NOT NULL,
    `slug` VARCHAR(90) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_memory_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `memory_categories` (`name`, `slug`) VALUES
    ('Campus life', 'campus-life'),
    ('Celebrations', 'celebrations'),
    ('Class moments', 'class-moments'),
    ('Trips & events', 'trips-events');

CREATE TABLE IF NOT EXISTS `memory_albums` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `batch_id` INT NULL,
    `created_by_type` ENUM('admin','student') NOT NULL,
    `created_by_id` INT NOT NULL,
    `category_id` INT NULL,
    `title` VARCHAR(160) NOT NULL,
    `description` TEXT NULL,
    `album_type` ENUM('department','session','event') NOT NULL DEFAULT 'event',
    `session_label` VARCHAR(100) NULL,
    `event_date` DATE NULL,
    `visibility` ENUM('all','batch','private') NOT NULL DEFAULT 'batch',
    `allow_submissions` TINYINT(1) NOT NULL DEFAULT 1,
    `submission_token` CHAR(64) NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('active','deleted') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_memory_albums_submission_token` (`submission_token`),
    KEY `idx_memory_albums_batch_status` (`batch_id`,`status`),
    KEY `idx_memory_albums_date` (`event_date`),
    KEY `idx_memory_albums_featured` (`is_featured`,`status`),
    CONSTRAINT `fk_memory_albums_category` FOREIGN KEY (`category_id`)
        REFERENCES `memory_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `memory_media` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `album_id` INT NOT NULL,
    `uploader_type` ENUM('admin','student') NOT NULL,
    `uploader_id` INT NOT NULL,
    `media_type` ENUM('image','video','youtube') NOT NULL,
    `file_path` VARCHAR(255) NULL,
    `thumb_path` VARCHAR(255) NULL,
    `youtube_id` CHAR(11) NULL,
    `caption` VARCHAR(500) NULL,
    `status` ENUM('pending','approved','rejected','deleted') NOT NULL DEFAULT 'pending',
    `reviewed_by` INT NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_memory_media_album_status` (`album_id`,`status`,`created_at`),
    KEY `idx_memory_media_uploader` (`uploader_type`,`uploader_id`,`status`),
    CONSTRAINT `fk_memory_media_album` FOREIGN KEY (`album_id`)
        REFERENCES `memory_albums` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `memory_tags` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(40) NOT NULL,
    `slug` VARCHAR(45) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_memory_tags_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `memory_media_tags` (
    `media_id` BIGINT NOT NULL,
    `tag_id` INT NOT NULL,
    PRIMARY KEY (`media_id`,`tag_id`),
    CONSTRAINT `fk_memory_media_tags_media` FOREIGN KEY (`media_id`)
        REFERENCES `memory_media` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_memory_media_tags_tag` FOREIGN KEY (`tag_id`)
        REFERENCES `memory_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `memory_likes` (
    `media_id` BIGINT NOT NULL,
    `user_type` ENUM('admin','student') NOT NULL,
    `user_id` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`media_id`,`user_type`,`user_id`),
    CONSTRAINT `fk_memory_likes_media` FOREIGN KEY (`media_id`)
        REFERENCES `memory_media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `memory_comments` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `media_id` BIGINT NOT NULL,
    `user_type` ENUM('admin','student') NOT NULL,
    `user_id` INT NOT NULL,
    `body` VARCHAR(1000) NOT NULL,
    `status` ENUM('published','hidden','deleted') NOT NULL DEFAULT 'published',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_memory_comments_media_status` (`media_id`,`status`,`created_at`),
    CONSTRAINT `fk_memory_comments_media` FOREIGN KEY (`media_id`)
        REFERENCES `memory_media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `memory_reports` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `media_id` BIGINT NOT NULL,
    `reporter_type` ENUM('admin','student') NOT NULL,
    `reporter_id` INT NOT NULL,
    `reason` VARCHAR(500) NOT NULL,
    `status` ENUM('open','reviewed','dismissed') NOT NULL DEFAULT 'open',
    `reviewed_by` INT NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_memory_reports_reporter` (`media_id`,`reporter_type`,`reporter_id`),
    KEY `idx_memory_reports_status` (`status`,`created_at`),
    CONSTRAINT `fk_memory_reports_media` FOREIGN KEY (`media_id`)
        REFERENCES `memory_media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `memory_audit_logs` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `actor_type` ENUM('admin','student') NOT NULL,
    `actor_id` INT NOT NULL,
    `action` VARCHAR(40) NOT NULL,
    `entity_type` VARCHAR(30) NOT NULL,
    `entity_id` BIGINT NOT NULL,
    `details` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_memory_audit_entity` (`entity_type`,`entity_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
