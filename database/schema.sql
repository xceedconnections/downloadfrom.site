-- downloadfrom.site application storage schema (MySQL 5.7+ / MariaDB 10.2+)
-- Create database in aaPanel, then import:
--   mysql -u downloadfrom.site -p downloadfrom.site < database/schema.sql

CREATE TABLE IF NOT EXISTS app_storage (
    store_key VARCHAR(191) NOT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (store_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logical keys used by the app:
--   settings
--   faq
--   ads
--   admin
--   analytics
--   rate_limits
--   results/{32-char-hex-token}
