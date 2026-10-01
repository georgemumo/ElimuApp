CREATE TABLE IF NOT EXISTS `message_poll_votes` (
    `message_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `option_index` TINYINT UNSIGNED NOT NULL,
    `voted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`, `user_id`),
    KEY `idx_poll_votes_option` (`message_id`, `option_index`),
    FOREIGN KEY (`message_id`) REFERENCES `messages`(`message_id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;