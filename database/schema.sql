-- downloadfrom.site — relational schema (MySQL required)
-- Tables are created automatically by DatabaseInstaller on first request.

CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(128) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (migration)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(64) NOT NULL,
    setting_value TEXT NOT NULL,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS services (
    service_id VARCHAR(64) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    name VARCHAR(255) NOT NULL DEFAULT '',
    nav_label VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_providers (
    service_id VARCHAR(64) NOT NULL,
    provider_id VARCHAR(64) NOT NULL,
    provider_type ENUM('video', 'audio') NOT NULL,
    PRIMARY KEY (service_id, provider_id, provider_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS video_providers (
    provider_id VARCHAR(64) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    show_as_new TINYINT(1) NOT NULL DEFAULT 0,
    proxy_enabled TINYINT(1) NOT NULL DEFAULT 0,
    title VARCHAR(512) NOT NULL DEFAULT '',
    h1 VARCHAR(255) NOT NULL DEFAULT '',
    meta_description TEXT,
    description TEXT,
    keywords TEXT,
    slug VARCHAR(128) NOT NULL DEFAULT '',
    blocked_channels JSON NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audio_providers (
    provider_id VARCHAR(64) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    show_as_new TINYINT(1) NOT NULL DEFAULT 0,
    proxy_enabled TINYINT(1) NOT NULL DEFAULT 0,
    title VARCHAR(512) NOT NULL DEFAULT '',
    h1 VARCHAR(255) NOT NULL DEFAULT '',
    meta_description TEXT,
    description TEXT,
    keywords TEXT,
    slug VARCHAR(128) NOT NULL DEFAULT '',
    blocked_channels JSON NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_settings (
    id TINYINT NOT NULL DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    download_modal_countdown INT NOT NULL DEFAULT 5,
    download_opener_mode VARCHAR(16) NOT NULL DEFAULT 'random',
    download_opener_count TINYINT NOT NULL DEFAULT 1,
    download_opener_containers MEDIUMTEXT,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ads (
    id VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    source VARCHAR(32) NOT NULL DEFAULT 'own',
    type VARCHAR(32) NOT NULL DEFAULT 'banner',
    network VARCHAR(64) NOT NULL DEFAULT 'custom',
    priority INT NOT NULL DEFAULT 0,
    content_title VARCHAR(255) NOT NULL DEFAULT '',
    content_text TEXT,
    content_html MEDIUMTEXT,
    content_image_url VARCHAR(1024) NOT NULL DEFAULT '',
    content_video_url VARCHAR(1024) NOT NULL DEFAULT '',
    content_link_url VARCHAR(1024) NOT NULL DEFAULT '',
    content_alt VARCHAR(255) NOT NULL DEFAULT '',
    content_client_id VARCHAR(128) NOT NULL DEFAULT '',
    content_slot_id VARCHAR(128) NOT NULL DEFAULT '',
    content_network_code MEDIUMTEXT,
    content_width INT NOT NULL DEFAULT 728,
    content_height INT NOT NULL DEFAULT 90,
    popup_delay_seconds INT NOT NULL DEFAULT 3,
    popup_show_once TINYINT(1) NOT NULL DEFAULT 0,
    popup_closable TINYINT(1) NOT NULL DEFAULT 1,
    popup_display VARCHAR(16) NOT NULL DEFAULT 'modal',
    popup_content_mode VARCHAR(16) NOT NULL DEFAULT 'html',
    impression_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_placements (
    ad_id VARCHAR(32) NOT NULL,
    placement VARCHAR(128) NOT NULL,
    PRIMARY KEY (ad_id, placement),
    CONSTRAINT fk_ad_placements_ad FOREIGN KEY (ad_id) REFERENCES ads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_pages (
    ad_id VARCHAR(32) NOT NULL,
    page_type VARCHAR(64) NOT NULL,
    PRIMARY KEY (ad_id, page_type),
    CONSTRAINT fk_ad_pages_ad FOREIGN KEY (ad_id) REFERENCES ads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_zone_assignments (
    placement VARCHAR(128) NOT NULL,
    ad_id VARCHAR(32) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (placement, ad_id),
    KEY idx_zone_placement_order (placement, sort_order),
    CONSTRAINT fk_ad_zone_assignments_ad FOREIGN KEY (ad_id) REFERENCES ads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS page_seo (
    page_key VARCHAR(128) NOT NULL,
    page_label VARCHAR(255) NOT NULL DEFAULT '',
    page_type VARCHAR(32) NOT NULL DEFAULT 'core',
    title VARCHAR(512) NOT NULL DEFAULT '',
    h1 VARCHAR(255) NOT NULL DEFAULT '',
    meta_description TEXT,
    description TEXT,
    keywords TEXT,
    og_image VARCHAR(1024) NOT NULL DEFAULT '',
    robots VARCHAR(64) NOT NULL DEFAULT 'index, follow',
    seo_content MEDIUMTEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (page_key),
    KEY idx_page_seo_type (page_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faq_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    section VARCHAR(64) NOT NULL DEFAULT 'home',
    sort_order INT NOT NULL DEFAULT 0,
    question TEXT NOT NULL,
    answer MEDIUMTEXT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_faq_section_order (section, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_daily (
    stat_date DATE NOT NULL,
    total INT UNSIGNED NOT NULL DEFAULT 0,
    success INT UNSIGNED NOT NULL DEFAULT 0,
    failed INT UNSIGNED NOT NULL DEFAULT 0,
    response_sum DOUBLE NOT NULL DEFAULT 0,
    avg_response_ms DOUBLE NOT NULL DEFAULT 0,
    PRIMARY KEY (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS analytics_platform_daily (
    stat_date DATE NOT NULL,
    platform VARCHAR(64) NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (stat_date, platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visitor_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_key VARCHAR(64) NOT NULL DEFAULT '',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    country_code CHAR(2) NOT NULL DEFAULT '',
    country_name VARCHAR(100) NOT NULL DEFAULT '',
    page_url VARCHAR(2048) NOT NULL DEFAULT '',
    page_path VARCHAR(512) NOT NULL DEFAULT '',
    page_title VARCHAR(255) NOT NULL DEFAULT '',
    referrer_url VARCHAR(2048) NOT NULL DEFAULT '',
    referrer_source VARCHAR(128) NOT NULL DEFAULT '',
    user_agent TEXT,
    browser VARCHAR(64) NOT NULL DEFAULT '',
    os_name VARCHAR(64) NOT NULL DEFAULT '',
    device_type VARCHAR(32) NOT NULL DEFAULT 'desktop',
    duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    visited_at INT UNSIGNED NOT NULL DEFAULT 0,
    left_at INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_visitor_visited_at (visited_at),
    KEY idx_visitor_session_key (session_key),
    KEY idx_visitor_country_code (country_code),
    KEY idx_visitor_page_path (page_path(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket_hash CHAR(64) NOT NULL,
    requested_at INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_rate_bucket_time (bucket_hash, requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS download_sessions (
    token CHAR(32) NOT NULL,
    payload JSON NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    expires_at INT UNSIGNED NOT NULL,
    PRIMARY KEY (token),
    KEY idx_download_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Legacy key-value store (used only for one-time migration from older installs)
CREATE TABLE IF NOT EXISTS app_storage (
    store_key VARCHAR(191) NOT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (store_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
