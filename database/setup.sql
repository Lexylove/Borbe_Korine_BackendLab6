-- Optional manual Aiven MySQL schema setup.
-- Recommended: configure the environment and run `php lava migration run` instead.
-- This script creates only the schema. Run migrations after it so migration 004
-- can create the initial admin from environment variables (never hardcode it here).

CREATE TABLE IF NOT EXISTS `migrations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `migration` INT NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration_unique` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','moderator','user') NOT NULL DEFAULT 'user',
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_unique` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_idx` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `refresh_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` TEXT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `jti` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `product_name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Verify the schema after setup:
SHOW TABLES;
DESCRIBE `products`;
DESCRIBE `users`;

-- Optional product CRUD examples (use the API in the application; these are
-- for database inspection only):
-- SELECT id, product_name, description, price, quantity, created_at
--   FROM products ORDER BY id DESC;
-- INSERT INTO products (product_name, description, price, quantity)
--   VALUES ('Sample item', 'Replace with a real description', 19.99, 5);
-- UPDATE products SET product_name = 'Updated item', price = 24.99,
--   quantity = 8 WHERE id = 1;
-- DELETE FROM products WHERE id = 1;

-- To create or change an account, set INITIAL_ADMIN_USERNAME,
-- INITIAL_ADMIN_EMAIL, and INITIAL_ADMIN_PASSWORD in the API environment,
-- then run `php lava migration run`. Never insert a plaintext password here.
