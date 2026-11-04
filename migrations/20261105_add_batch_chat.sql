CREATE TABLE IF NOT EXISTS `batch_chat_messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `batch_id` INT(11) NOT NULL,
    `sender_type` ENUM('student', 'admin') NOT NULL,
    `sender_id` INT(11) NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_batch_chat_batch_message` (`batch_id`, `id`),
    KEY `idx_batch_chat_batch_created` (`batch_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
