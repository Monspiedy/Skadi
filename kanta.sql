-- Skadi database schema and deterministic development data
-- Target: MariaDB 10.4. Times are stored in UTC; the app presents Europe/Helsinki time.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
CREATE DATABASE IF NOT EXISTS `skadi` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `skadi`;

SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW IF EXISTS `admin_content_report_queue`, `admin_catalog_overview`, `admin_user_overview`;
DROP TABLE IF EXISTS `content_reports`, `community_comment_likes`, `community_comments`,
    `community_post_likes`, `community_posts`, `discussion_replies`, `discussion_topics`,
    `discussion_categories`, `guide_comments`, `guide_favorites`, `guide_votes`, `guides`,
    `message_user_states`, `message_attachments`, `messages`, `conversations`, `user_blocks`,
    `friendships`, `reviews`, `wishlist_items`, `library_items`, `wallet_transactions`,
    `order_items`, `orders`, `cart_items`, `weekly_discounts`, `weekly_sales`, `featured_games`,
    `game_media`, `game_genres`, `genres`, `games`, `user_profiles`, `users`, `roles`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `roles` (
    `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(30) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_roles_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` TINYINT UNSIGNED NOT NULL,
    `login_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(254) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `skadi_id` CHAR(13) NOT NULL,
    `display_name` VARCHAR(80) NOT NULL,
    `wallet_balance` DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 100.00,
    `status` ENUM('active','suspended','blocked') NOT NULL DEFAULT 'active',
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `last_activity_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_login_name` (`login_name`),
    UNIQUE KEY `uq_users_email` (`email`),
    UNIQUE KEY `uq_users_skadi_id` (`skadi_id`),
    KEY `idx_users_display_name` (`display_name`),
    KEY `idx_users_role_status` (`role_id`,`status`),
    CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
    CONSTRAINT `chk_users_wallet_balance` CHECK (`wallet_balance` >= 0),
    CONSTRAINT `chk_users_password_flag` CHECK (`must_change_password` IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_profiles` (
    `user_id` BIGINT UNSIGNED NOT NULL,
    `biography` VARCHAR(1000) NULL,
    `avatar_path` VARCHAR(255) NULL,
    `theme` ENUM('skadi','dark','light') NOT NULL DEFAULT 'skadi',
    `profile_visibility` ENUM('public','friends_only','private') NOT NULL DEFAULT 'public',
    `friend_request_setting` ENUM('everyone','friends_of_friends','nobody') NOT NULL DEFAULT 'everyone',
    `show_online_status` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`), UNIQUE KEY `uq_user_profiles_avatar` (`avatar_path`),
    CONSTRAINT `fk_user_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_user_profiles_online` CHECK (`show_online_status` IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `games` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(160) NOT NULL,
    `title` VARCHAR(160) NOT NULL,
    `short_description` VARCHAR(500) NOT NULL,
    `description` TEXT NOT NULL,
    `developer` VARCHAR(160) NOT NULL,
    `publisher` VARCHAR(160) NOT NULL,
    `release_date` DATE NOT NULL,
    `base_price` DECIMAL(10,2) UNSIGNED NOT NULL DEFAULT 0.00,
    `system_requirements` TEXT NOT NULL,
    `status` ENUM('draft','published','hidden') NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_games_slug` (`slug`),
    KEY `idx_games_title` (`title`), KEY `idx_games_catalog` (`status`,`release_date`,`base_price`),
    CONSTRAINT `chk_games_price` CHECK (`base_price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `genres` (
    `id` SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `slug` VARCHAR(80) NOT NULL, `name` VARCHAR(80) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_genres_slug` (`slug`), UNIQUE KEY `uq_genres_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `game_genres` (
    `game_id` BIGINT UNSIGNED NOT NULL, `genre_id` SMALLINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`game_id`,`genre_id`), KEY `idx_game_genres_genre` (`genre_id`,`game_id`),
    CONSTRAINT `fk_game_genres_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_game_genres_genre` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `game_media` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_id` BIGINT UNSIGNED NOT NULL,
    `media_type` ENUM('cover','screenshot') NOT NULL,
    `storage_path` VARCHAR(255) NOT NULL, `alt_text` VARCHAR(255) NOT NULL,
    `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_game_media_path` (`storage_path`),
    UNIQUE KEY `uq_game_media_position` (`game_id`,`media_type`,`sort_order`),
    CONSTRAINT `fk_game_media_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_game_media_position` CHECK ((`media_type`='cover' AND `sort_order`=0) OR `media_type`='screenshot')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `featured_games` (
    `game_id` BIGINT UNSIGNED NOT NULL, `position` TINYINT UNSIGNED NOT NULL,
    `featured_by_user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`game_id`), UNIQUE KEY `uq_featured_games_position` (`position`),
    CONSTRAINT `fk_featured_games_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_featured_games_user` FOREIGN KEY (`featured_by_user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `chk_featured_games_position` CHECK (`position` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `weekly_sales` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `starts_at` DATETIME NOT NULL, `ends_at` DATETIME NOT NULL,
    `generation_number` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `status` ENUM('scheduled','active','superseded','ended') NOT NULL DEFAULT 'scheduled',
    `generated_by_user_id` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_weekly_sales_generation` (`starts_at`,`ends_at`,`generation_number`),
    KEY `idx_weekly_sales_period` (`starts_at`,`ends_at`,`status`),
    CONSTRAINT `fk_weekly_sales_generator` FOREIGN KEY (`generated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_weekly_sales_period` CHECK (`ends_at` > `starts_at`),
    CONSTRAINT `chk_weekly_sales_generation` CHECK (`generation_number` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `weekly_discounts` (
    `sale_id` BIGINT UNSIGNED NOT NULL, `game_id` BIGINT UNSIGNED NOT NULL,
    `discount_percent` TINYINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`sale_id`,`game_id`), KEY `idx_weekly_discounts_game` (`game_id`,`sale_id`),
    CONSTRAINT `fk_weekly_discounts_sale` FOREIGN KEY (`sale_id`) REFERENCES `weekly_sales` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_weekly_discounts_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_weekly_discounts_percent` CHECK (`discount_percent` BETWEEN 10 AND 75 AND MOD(`discount_percent`,5)=0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cart_items` (
    `user_id` BIGINT UNSIGNED NOT NULL, `game_id` BIGINT UNSIGNED NOT NULL,
    `price_when_added` DECIMAL(10,2) UNSIGNED NOT NULL,
    `added_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`,`game_id`), KEY `idx_cart_items_game` (`game_id`),
    CONSTRAINT `fk_cart_items_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cart_items_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `order_number` CHAR(20) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('pending','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
    `subtotal` DECIMAL(12,2) UNSIGNED NOT NULL,
    `discount_total` DECIMAL(12,2) UNSIGNED NOT NULL DEFAULT 0.00,
    `total` DECIMAL(12,2) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `completed_at` DATETIME NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_orders_number` (`order_number`),
    KEY `idx_orders_user_created` (`user_id`,`created_at`), KEY `idx_orders_status_created` (`status`,`created_at`),
    CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `chk_orders_amounts` CHECK (`subtotal` >= `discount_total` AND `total` = `subtotal` - `discount_total`),
    CONSTRAINT `chk_orders_completion` CHECK ((`status`='completed' AND `completed_at` IS NOT NULL) OR `status`<>'completed')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `order_id` BIGINT UNSIGNED NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL, `game_title` VARCHAR(160) NOT NULL,
    `base_price` DECIMAL(10,2) UNSIGNED NOT NULL,
    `discount_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `paid_price` DECIMAL(10,2) UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_order_items_game` (`order_id`,`game_id`), KEY `idx_order_items_game` (`game_id`),
    CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_order_items_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
    CONSTRAINT `chk_order_items_discount` CHECK (`discount_percent` BETWEEN 0 AND 75 AND MOD(`discount_percent`,5)=0),
    CONSTRAINT `chk_order_items_prices` CHECK (`paid_price` <= `base_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wallet_transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL,
    `transaction_type` ENUM('initial_credit','purchase','admin_adjustment') NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL, `balance_after` DECIMAL(12,2) UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NULL, `performed_by_user_id` BIGINT UNSIGNED NULL,
    `reason` VARCHAR(500) NOT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_wallet_transactions_order` (`order_id`),
    KEY `idx_wallet_transactions_user` (`user_id`,`created_at`), KEY `idx_wallet_transactions_actor` (`performed_by_user_id`,`created_at`),
    CONSTRAINT `fk_wallet_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_wallet_transactions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
    CONSTRAINT `fk_wallet_transactions_actor` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_wallet_transactions_amount` CHECK (`amount` <> 0),
    CONSTRAINT `chk_wallet_transactions_source` CHECK (
        (`transaction_type`='purchase' AND `order_id` IS NOT NULL AND `amount`<0) OR
        (`transaction_type`='admin_adjustment' AND `performed_by_user_id` IS NOT NULL) OR
        (`transaction_type`='initial_credit' AND `order_id` IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `library_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL, `acquired_order_item_id` BIGINT UNSIGNED NULL,
    `acquired_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `hidden_at` DATETIME NULL, `removed_at` DATETIME NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_library_ownership` (`user_id`,`game_id`),
    UNIQUE KEY `uq_library_order_item` (`acquired_order_item_id`), KEY `idx_library_game` (`game_id`),
    CONSTRAINT `fk_library_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_library_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
    CONSTRAINT `fk_library_order_item` FOREIGN KEY (`acquired_order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_library_visibility` CHECK (`removed_at` IS NULL OR `hidden_at` IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wishlist_items` (
    `user_id` BIGINT UNSIGNED NOT NULL, `game_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`,`game_id`), KEY `idx_wishlist_game` (`game_id`),
    CONSTRAINT `fk_wishlist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wishlist_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `reviews` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL,
    `game_id` BIGINT UNSIGNED NOT NULL, `rating` TINYINT UNSIGNED NOT NULL, `body` TEXT NULL,
    `status` ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active',
    `moderated_by_user_id` BIGINT UNSIGNED NULL, `moderation_reason` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_reviews_user_game` (`user_id`,`game_id`),
    KEY `idx_reviews_game_status` (`game_id`,`status`,`rating`),
    CONSTRAINT `fk_reviews_ownership` FOREIGN KEY (`user_id`,`game_id`) REFERENCES `library_items` (`user_id`,`game_id`),
    CONSTRAINT `fk_reviews_moderator` FOREIGN KEY (`moderated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_reviews_rating` CHECK (`rating` BETWEEN 1 AND 10),
    CONSTRAINT `chk_reviews_deletion` CHECK ((`status`='deleted' AND `deleted_at` IS NOT NULL) OR `status`<>'deleted')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `friendships` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_low_id` BIGINT UNSIGNED NOT NULL, `user_high_id` BIGINT UNSIGNED NOT NULL,
    `requested_by_user_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('pending','accepted','declined','cancelled','removed') NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `responded_at` DATETIME NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_friendships_pair` (`user_low_id`,`user_high_id`),
    KEY `idx_friendships_high_status` (`user_high_id`,`status`),
    KEY `idx_friendships_requester` (`requested_by_user_id`,`status`),
    CONSTRAINT `fk_friendships_low` FOREIGN KEY (`user_low_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_friendships_high` FOREIGN KEY (`user_high_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_friendships_requester` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_friendships_pair` CHECK (`user_low_id` < `user_high_id`),
    CONSTRAINT `chk_friendships_requester` CHECK (`requested_by_user_id` IN (`user_low_id`,`user_high_id`)),
    CONSTRAINT `chk_friendships_response` CHECK ((`status`='pending' AND `responded_at` IS NULL) OR `status`<>'pending')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_blocks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_low_id` BIGINT UNSIGNED NOT NULL, `user_high_id` BIGINT UNSIGNED NOT NULL,
    `blocked_by_user_id` BIGINT UNSIGNED NOT NULL, `reason` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_user_blocks_pair` (`user_low_id`,`user_high_id`),
    KEY `idx_user_blocks_blocker` (`blocked_by_user_id`),
    CONSTRAINT `fk_user_blocks_low` FOREIGN KEY (`user_low_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_blocks_high` FOREIGN KEY (`user_high_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_blocks_blocker` FOREIGN KEY (`blocked_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_user_blocks_pair` CHECK (`user_low_id` < `user_high_id`),
    CONSTRAINT `chk_user_blocks_actor` CHECK (`blocked_by_user_id` IN (`user_low_id`,`user_high_id`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_low_id` BIGINT UNSIGNED NOT NULL, `user_high_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_conversations_pair` (`user_low_id`,`user_high_id`),
    KEY `idx_conversations_high_updated` (`user_high_id`,`updated_at`),
    CONSTRAINT `fk_conversations_low` FOREIGN KEY (`user_low_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_conversations_high` FOREIGN KEY (`user_high_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_conversations_pair` CHECK (`user_low_id` < `user_high_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `conversation_id` BIGINT UNSIGNED NOT NULL,
    `sender_user_id` BIGINT UNSIGNED NOT NULL, `body` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `edited_at` DATETIME NULL,
    PRIMARY KEY (`id`), KEY `idx_messages_conversation` (`conversation_id`,`created_at`,`id`),
    KEY `idx_messages_sender` (`sender_user_id`,`created_at`),
    CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_attachments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `message_id` BIGINT UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) NOT NULL, `storage_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL, `extension` VARCHAR(10) NOT NULL,
    `size_bytes` INT UNSIGNED NOT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_message_attachments_message` (`message_id`),
    UNIQUE KEY `uq_message_attachments_storage` (`storage_name`),
    CONSTRAINT `fk_message_attachments_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_message_attachments_size` CHECK (`size_bytes` BETWEEN 1 AND 5242880),
    CONSTRAINT `chk_message_attachments_extension` CHECK (`extension` IN ('jpg','jpeg','png','webp','pdf','txt')),
    CONSTRAINT `chk_message_attachments_mime` CHECK (`mime_type` IN ('image/jpeg','image/png','image/webp','application/pdf','text/plain'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `message_user_states` (
    `message_id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL,
    `read_at` DATETIME NULL, `hidden_at` DATETIME NULL,
    PRIMARY KEY (`message_id`,`user_id`), KEY `idx_message_states_user` (`user_id`,`read_at`,`hidden_at`),
    CONSTRAINT `fk_message_states_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_message_states_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `guides` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `game_id` BIGINT UNSIGNED NOT NULL,
    `author_user_id` BIGINT UNSIGNED NOT NULL, `title` VARCHAR(180) NOT NULL,
    `summary` VARCHAR(500) NOT NULL,
    `category` ENUM('general','walkthrough','strategy','build','modding','other') NOT NULL DEFAULT 'general',
    `thumbnail_path` VARCHAR(255) NULL, `body_source` MEDIUMTEXT NOT NULL,
    `status` ENUM('draft','published','hidden','deleted') NOT NULL DEFAULT 'draft',
    `moderated_by_user_id` BIGINT UNSIGNED NULL, `moderation_reason` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `published_at` DATETIME NULL, `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_guides_thumbnail` (`thumbnail_path`),
    KEY `idx_guides_game_status` (`game_id`,`status`,`published_at`), KEY `idx_guides_author` (`author_user_id`,`status`),
    CONSTRAINT `fk_guides_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
    CONSTRAINT `fk_guides_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_guides_moderator` FOREIGN KEY (`moderated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_guides_publication` CHECK ((`status`='published' AND `published_at` IS NOT NULL) OR `status`<>'published'),
    CONSTRAINT `chk_guides_deletion` CHECK ((`status`='deleted' AND `deleted_at` IS NOT NULL) OR `status`<>'deleted')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `guide_votes` (
    `guide_id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL,
    `vote_value` TINYINT NOT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`guide_id`,`user_id`), KEY `idx_guide_votes_user` (`user_id`),
    CONSTRAINT `fk_guide_votes_guide` FOREIGN KEY (`guide_id`) REFERENCES `guides` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_guide_votes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_guide_votes_value` CHECK (`vote_value` IN (-1,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `guide_favorites` (
    `guide_id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`guide_id`,`user_id`), KEY `idx_guide_favorites_user` (`user_id`,`created_at`),
    CONSTRAINT `fk_guide_favorites_guide` FOREIGN KEY (`guide_id`) REFERENCES `guides` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_guide_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `guide_comments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `guide_id` BIGINT UNSIGNED NOT NULL,
    `author_user_id` BIGINT UNSIGNED NOT NULL, `body` TEXT NOT NULL,
    `status` ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`), KEY `idx_guide_comments_guide` (`guide_id`,`status`,`created_at`),
    KEY `idx_guide_comments_author` (`author_user_id`),
    CONSTRAINT `fk_guide_comments_guide` FOREIGN KEY (`guide_id`) REFERENCES `guides` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_guide_comments_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `chk_guide_comments_deletion` CHECK ((`status`='deleted' AND `deleted_at` IS NOT NULL) OR `status`<>'deleted')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `discussion_categories` (
    `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT, `slug` VARCHAR(40) NOT NULL,
    `name` VARCHAR(60) NOT NULL, `sort_order` TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_discussion_categories_slug` (`slug`),
    UNIQUE KEY `uq_discussion_categories_name` (`name`), UNIQUE KEY `uq_discussion_categories_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `discussion_topics` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `game_id` BIGINT UNSIGNED NOT NULL,
    `category_id` TINYINT UNSIGNED NOT NULL, `author_user_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(180) NOT NULL, `body` MEDIUMTEXT NOT NULL,
    `status` ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active',
    `is_pinned` TINYINT(1) NOT NULL DEFAULT 0, `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
    `moderated_by_user_id` BIGINT UNSIGNED NULL, `moderation_reason` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`), KEY `idx_topics_game_activity` (`game_id`,`status`,`updated_at`),
    KEY `idx_topics_category` (`category_id`,`status`), KEY `idx_topics_author` (`author_user_id`),
    CONSTRAINT `fk_topics_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
    CONSTRAINT `fk_topics_category` FOREIGN KEY (`category_id`) REFERENCES `discussion_categories` (`id`),
    CONSTRAINT `fk_topics_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_topics_moderator` FOREIGN KEY (`moderated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_topics_flags` CHECK (`is_pinned` IN (0,1) AND `is_locked` IN (0,1)),
    CONSTRAINT `chk_topics_deletion` CHECK ((`status`='deleted' AND `deleted_at` IS NOT NULL) OR `status`<>'deleted')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `discussion_replies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `topic_id` BIGINT UNSIGNED NOT NULL,
    `author_user_id` BIGINT UNSIGNED NOT NULL, `body` MEDIUMTEXT NOT NULL,
    `status` ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active',
    `moderated_by_user_id` BIGINT UNSIGNED NULL, `moderation_reason` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`), KEY `idx_replies_topic` (`topic_id`,`status`,`created_at`), KEY `idx_replies_author` (`author_user_id`),
    CONSTRAINT `fk_replies_topic` FOREIGN KEY (`topic_id`) REFERENCES `discussion_topics` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_replies_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_replies_moderator` FOREIGN KEY (`moderated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_replies_deletion` CHECK ((`status`='deleted' AND `deleted_at` IS NOT NULL) OR `status`<>'deleted')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `community_posts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `game_id` BIGINT UNSIGNED NOT NULL,
    `author_user_id` BIGINT UNSIGNED NOT NULL, `body` TEXT NOT NULL, `image_path` VARCHAR(255) NULL,
    `status` ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active',
    `moderated_by_user_id` BIGINT UNSIGNED NULL, `moderation_reason` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_community_posts_image` (`image_path`),
    KEY `idx_community_posts_game` (`game_id`,`status`,`created_at`), KEY `idx_community_posts_author` (`author_user_id`),
    CONSTRAINT `fk_community_posts_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
    CONSTRAINT `fk_community_posts_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_community_posts_moderator` FOREIGN KEY (`moderated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_community_posts_content` CHECK (`body`<>'' OR `image_path` IS NOT NULL),
    CONSTRAINT `chk_community_posts_deletion` CHECK ((`status`='deleted' AND `deleted_at` IS NOT NULL) OR `status`<>'deleted')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `community_post_likes` (
    `post_id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`post_id`,`user_id`), KEY `idx_post_likes_user` (`user_id`),
    CONSTRAINT `fk_post_likes_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_post_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `community_comments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `post_id` BIGINT UNSIGNED NOT NULL,
    `author_user_id` BIGINT UNSIGNED NOT NULL, `body` TEXT NOT NULL,
    `status` ENUM('active','hidden','deleted') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id`), KEY `idx_community_comments_post` (`post_id`,`status`,`created_at`),
    KEY `idx_community_comments_author` (`author_user_id`),
    CONSTRAINT `fk_community_comments_post` FOREIGN KEY (`post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_community_comments_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `chk_community_comments_deletion` CHECK ((`status`='deleted' AND `deleted_at` IS NOT NULL) OR `status`<>'deleted')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `community_comment_likes` (
    `comment_id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`comment_id`,`user_id`), KEY `idx_comment_likes_user` (`user_id`),
    CONSTRAINT `fk_comment_likes_comment` FOREIGN KEY (`comment_id`) REFERENCES `community_comments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comment_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `content_reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `reporter_user_id` BIGINT UNSIGNED NOT NULL,
    `review_id` BIGINT UNSIGNED NULL, `guide_id` BIGINT UNSIGNED NULL,
    `guide_comment_id` BIGINT UNSIGNED NULL, `discussion_topic_id` BIGINT UNSIGNED NULL,
    `discussion_reply_id` BIGINT UNSIGNED NULL, `community_post_id` BIGINT UNSIGNED NULL,
    `community_comment_id` BIGINT UNSIGNED NULL,
    `target_type_code` TINYINT UNSIGNED AS (CASE
        WHEN `review_id` IS NOT NULL THEN 1 WHEN `guide_id` IS NOT NULL THEN 2
        WHEN `guide_comment_id` IS NOT NULL THEN 3 WHEN `discussion_topic_id` IS NOT NULL THEN 4
        WHEN `discussion_reply_id` IS NOT NULL THEN 5 WHEN `community_post_id` IS NOT NULL THEN 6
        WHEN `community_comment_id` IS NOT NULL THEN 7 END) PERSISTENT,
    `target_record_id` BIGINT UNSIGNED AS (COALESCE(`review_id`,`guide_id`,`guide_comment_id`,
        `discussion_topic_id`,`discussion_reply_id`,`community_post_id`,`community_comment_id`)) PERSISTENT,
    `reason` ENUM('spam','harassment','inappropriate','misinformation','other') NOT NULL,
    `details` VARCHAR(1000) NULL, `status` ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
    `handled_by_user_id` BIGINT UNSIGNED NULL, `resolution_note` VARCHAR(1000) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `resolved_at` DATETIME NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_reports_reporter_target` (`reporter_user_id`,`target_type_code`,`target_record_id`),
    KEY `idx_reports_queue` (`status`,`created_at`), KEY `idx_reports_review` (`review_id`),
    KEY `idx_reports_guide` (`guide_id`), KEY `idx_reports_guide_comment` (`guide_comment_id`),
    KEY `idx_reports_topic` (`discussion_topic_id`), KEY `idx_reports_reply` (`discussion_reply_id`),
    KEY `idx_reports_post` (`community_post_id`), KEY `idx_reports_comment` (`community_comment_id`),
    CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`),
    CONSTRAINT `fk_reports_review` FOREIGN KEY (`review_id`) REFERENCES `reviews` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_guide` FOREIGN KEY (`guide_id`) REFERENCES `guides` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_guide_comment` FOREIGN KEY (`guide_comment_id`) REFERENCES `guide_comments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_topic` FOREIGN KEY (`discussion_topic_id`) REFERENCES `discussion_topics` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_reply` FOREIGN KEY (`discussion_reply_id`) REFERENCES `discussion_replies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_post` FOREIGN KEY (`community_post_id`) REFERENCES `community_posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_comment` FOREIGN KEY (`community_comment_id`) REFERENCES `community_comments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reports_handler` FOREIGN KEY (`handled_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_reports_one_target` CHECK ((`review_id` IS NOT NULL)+(`guide_id` IS NOT NULL)+
        (`guide_comment_id` IS NOT NULL)+(`discussion_topic_id` IS NOT NULL)+(`discussion_reply_id` IS NOT NULL)+
        (`community_post_id` IS NOT NULL)+(`community_comment_id` IS NOT NULL)=1),
    CONSTRAINT `chk_reports_resolution` CHECK (
        (`status` IN ('resolved','dismissed') AND `resolved_at` IS NOT NULL AND `handled_by_user_id` IS NOT NULL)
        OR `status` IN ('open','reviewing'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin views intentionally exclude login names, password hashes and all private-chat tables.
CREATE VIEW `admin_user_overview` AS
SELECT u.id, u.skadi_id, u.display_name, u.email, r.name AS role_name, u.status,
       u.wallet_balance, u.created_at, u.last_activity_at
FROM users u INNER JOIN roles r ON r.id=u.role_id;

CREATE VIEW `admin_catalog_overview` AS
SELECT g.id, g.slug, g.title, g.base_price, g.status,
       COUNT(DISTINCT li.user_id) AS owner_count,
       COUNT(DISTINCT CASE WHEN rv.status='active' THEN rv.id END) AS review_count,
       ROUND(AVG(CASE WHEN rv.status='active' THEN rv.rating END),2) AS average_rating
FROM games g
LEFT JOIN library_items li ON li.game_id=g.id AND li.removed_at IS NULL
LEFT JOIN reviews rv ON rv.game_id=g.id
GROUP BY g.id,g.slug,g.title,g.base_price,g.status;

CREATE VIEW `admin_content_report_queue` AS
SELECT cr.id, cr.target_type_code, cr.target_record_id, cr.reason, cr.details, cr.status,
       cr.created_at, reporter.skadi_id AS reporter_skadi_id,
       reporter.display_name AS reporter_display_name, handler.skadi_id AS handler_skadi_id,
       cr.resolution_note, cr.resolved_at
FROM content_reports cr
INNER JOIN users reporter ON reporter.id=cr.reporter_user_id
LEFT JOIN users handler ON handler.id=cr.handled_by_user_id;

-- Deterministic local development data. Seed accounts must change password at first login.
INSERT INTO `roles` (`id`,`name`) VALUES (1,'user'),(2,'admin');

INSERT INTO `users` (`id`,`role_id`,`login_name`,`email`,`password_hash`,`skadi_id`,`display_name`,
    `wallet_balance`,`status`,`must_change_password`) VALUES
    (1,2,'skadi_admin','admin@skadi.example.invalid','$2y$10$UMdDAgWerN8IB35A3kUA4eDN/9WyketRvoDxUY5mhBw0pwCvUrnpa','SKD-ADMIN-001','Skadi Admin',100.00,'active',1),
    (2,1,'demo_player','player@skadi.example.invalid','$2y$10$UMdDAgWerN8IB35A3kUA4eDN/9WyketRvoDxUY5mhBw0pwCvUrnpa','SKD-DEMO-0001','Demo Player',100.00,'active',1);

INSERT INTO `user_profiles` (`user_id`,`biography`,`theme`,`profile_visibility`,`friend_request_setting`,`show_online_status`) VALUES
    (1,'Local development administrator account.','skadi','private','nobody',0),
    (2,'Demo player for local development.','skadi','public','everyone',1);

INSERT INTO `wallet_transactions` (`user_id`,`transaction_type`,`amount`,`balance_after`,`reason`) VALUES
    (1,'initial_credit',100.00,100.00,'Initial demonstration balance'),
    (2,'initial_credit',100.00,100.00,'Initial demonstration balance');

INSERT INTO `genres` (`id`,`slug`,`name`) VALUES
    (1,'action','Action'),(2,'adventure','Adventure'),(3,'rpg','RPG'),
    (4,'strategy','Strategy'),(5,'shooter','Shooter'),(6,'multiplayer','Multiplayer'),
    (7,'simulation','Simulation'),(8,'indie','Indie'),(9,'survival','Survival'),
    (10,'open-world','Open World');

INSERT INTO `games` (`id`,`slug`,`title`,`short_description`,`description`,`developer`,`publisher`,
    `release_date`,`base_price`,`system_requirements`,`status`) VALUES
    (1,'counter-strike-2','Counter-Strike 2','A competitive team-based tactical shooter.','Counter-Strike 2 focuses on objective-based matches between two teams.','Valve','Valve','2023-09-27',0.00,'64-bit processor and operating system; Windows 10; DirectX 11 graphics.','published'),
    (2,'dota-2','Dota 2','A team strategy game built around five-versus-five matches.','Dota 2 challenges two teams to defend their base while coordinating heroes and items.','Valve','Valve','2013-07-09',0.00,'Dual-core processor; 4 GB RAM; DirectX 11 compatible graphics.','published'),
    (3,'cyberpunk-2077','Cyberpunk 2077','An open-world action RPG set in Night City.','Cyberpunk 2077 follows a mercenary through a story-driven futuristic open world.','CD PROJEKT RED','CD PROJEKT RED','2020-12-10',59.99,'64-bit processor; 12 GB RAM; DirectX 12 graphics.','published'),
    (4,'baldurs-gate-3','Baldur''s Gate 3','A party-based role-playing adventure with tactical combat.','Baldur''s Gate 3 combines exploration, choices and turn-based encounters.','Larian Studios','Larian Studios','2023-08-03',59.99,'64-bit processor; 8 GB RAM; DirectX 11 graphics.','published'),
    (5,'red-dead-redemption-2','Red Dead Redemption 2','A story-driven open-world western adventure.','Red Dead Redemption 2 follows an outlaw gang during the decline of the American frontier.','Rockstar Games','Rockstar Games','2019-11-05',59.99,'64-bit processor; 8 GB RAM; DirectX 12 graphics.','published'),
    (6,'the-witcher-3-wild-hunt','The Witcher 3: Wild Hunt','An open-world fantasy role-playing game.','The Witcher 3 follows monster hunter Geralt through a large story-rich world.','CD PROJEKT RED','CD PROJEKT RED','2015-05-18',39.99,'64-bit processor; 6 GB RAM; DirectX 11 graphics.','published'),
    (7,'grand-theft-auto-v','Grand Theft Auto V','An open-world action game set in Los Santos.','Grand Theft Auto V combines three character stories with an expansive open world.','Rockstar North','Rockstar Games','2015-04-14',29.99,'64-bit processor; 8 GB RAM; DirectX 11 graphics.','published'),
    (8,'elden-ring','Elden Ring','An action RPG focused on exploration and challenging combat.','Elden Ring presents a dark fantasy world with open-ended exploration.','FromSoftware','Bandai Namco Entertainment','2022-02-25',59.99,'64-bit processor; 12 GB RAM; DirectX 12 graphics.','published'),
    (9,'helldivers-2','HELLDIVERS 2','A cooperative third-person squad shooter.','HELLDIVERS 2 sends squads on objective-based missions across hostile planets.','Arrowhead Game Studios','PlayStation Publishing LLC','2024-02-08',39.99,'64-bit processor; 8 GB RAM; DirectX 12 graphics.','published'),
    (10,'rust','Rust','A multiplayer survival game about gathering, building and defending.','Rust places players in a shared survival world with crafting and base building.','Facepunch Studios','Facepunch Studios','2018-02-08',39.99,'64-bit processor; 10 GB RAM; DirectX 11 graphics.','published'),
    (11,'stardew-valley','Stardew Valley','A farming and community life simulation.','Stardew Valley combines farming, exploration, crafting and relationships.','ConcernedApe','ConcernedApe','2016-02-26',13.99,'2 GHz processor; 2 GB RAM; 256 MB video memory.','published'),
    (12,'terraria','Terraria','A sandbox adventure built around exploration, crafting and combat.','Terraria offers a procedurally generated world with building and progression.','Re-Logic','Re-Logic','2011-05-16',9.99,'2 GHz processor; 2.5 GB RAM; 128 MB video memory.','published');

INSERT INTO `game_genres` (`game_id`,`genre_id`) VALUES
    (1,1),(1,5),(1,6),(2,4),(2,6),(3,1),(3,3),(3,10),
    (4,2),(4,3),(4,4),(4,6),(5,1),(5,2),(5,10),(6,2),(6,3),(6,10),
    (7,1),(7,10),(8,1),(8,3),(8,10),(9,1),(9,5),(9,6),
    (10,1),(10,6),(10,9),(11,3),(11,7),(11,8),(12,1),(12,2),(12,8),(12,9);

INSERT INTO `featured_games` (`game_id`,`position`,`featured_by_user_id`) VALUES
    (1,1,1),(2,2,1),(4,3,1),(8,4,1),(11,5,1);

INSERT INTO `weekly_sales` (`id`,`starts_at`,`ends_at`,`generation_number`,`status`,`generated_by_user_id`) VALUES
    (1,'2026-10-30 00:00:00','2026-11-02 00:00:00',1,'scheduled',1);

INSERT INTO `weekly_discounts` (`sale_id`,`game_id`,`discount_percent`) VALUES
    (1,3,25),(1,4,15),(1,5,30),(1,6,40),(1,7,20),
    (1,8,10),(1,9,35),(1,10,45),(1,11,50),(1,12,60);

INSERT INTO `discussion_categories` (`id`,`slug`,`name`,`sort_order`) VALUES
    (1,'general','General',1),(2,'help','Help',2),(3,'bug','Bug',3),
    (4,'trading','Trading',4),(5,'off-topic','Off-topic',5);

-- The application canonicalizes social pairs with LEAST/GREATEST before writes.
-- Checkout locks affected rows and changes orders, wallet and library in one transaction.
