-- downloadfrom.site — all admin & site data (MySQL required)
-- Import once: mysql -u USER -p DATABASE < database/schema.sql

CREATE TABLE IF NOT EXISTS app_storage (
    store_key VARCHAR(191) NOT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (store_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Store keys (JSON payload per row):
--
-- settings       Site name, logo, footer, custom codes (head/body),
--                services (header nav), video/audio provider SEO & toggles
-- ads            Ad units, placement map, global ad on/off
-- faq            FAQ questions per page
-- admin          Admin username & password hash
-- analytics      Request statistics
-- rate_limits    IP rate limit counters
-- results/{token} Temporary download session data (expires)
