-- BASELINE — frozen; every later change is a migration (see database/migrations/ + database/migrate.php).
CREATE DATABASE IF NOT EXISTS business_panel
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE business_panel;

-- ============================================================
-- Panel users (owner and staff members)
-- ============================================================
CREATE TABLE panel_users (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name         VARCHAR(150) NOT NULL,
    username          VARCHAR(60)  NOT NULL UNIQUE,
    email             VARCHAR(190) NOT NULL UNIQUE,
    phone             VARCHAR(20)  DEFAULT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    avatar_path       VARCHAR(500) DEFAULT NULL,
    role              ENUM('owner','staff') NOT NULL DEFAULT 'staff',
    status            ENUM('active','suspended') NOT NULL DEFAULT 'active',
    theme_preference  ENUM('light','dark') NOT NULL DEFAULT 'light',
    last_login_at     DATETIME DEFAULT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- System modules and each staff user's access to each one
-- ============================================================
CREATE TABLE permissions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_key   VARCHAR(50)  NOT NULL UNIQUE,
    module_label VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE panel_user_permissions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    panel_user_id   INT UNSIGNED NOT NULL,
    permission_id   INT UNSIGNED NOT NULL,
    can_view        TINYINT(1) NOT NULL DEFAULT 1,
    can_edit        TINYINT(1) NOT NULL DEFAULT 0,
    can_delete      TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_user_permission (panel_user_id, permission_id),
    CONSTRAINT fk_pup_user
        FOREIGN KEY (panel_user_id) REFERENCES panel_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_pup_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Login attempts (for brute-force rate limiting)
-- ============================================================
CREATE TABLE login_attempts (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier     VARCHAR(190) NOT NULL,
    ip_address     VARCHAR(45)  NOT NULL,
    is_successful  TINYINT(1)   NOT NULL DEFAULT 0,
    attempted_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_login_attempts_identifier ON login_attempts (identifier, attempted_at);

-- ============================================================
-- Site template categories and the template list
-- ============================================================
CREATE TABLE template_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    icon        VARCHAR(50)  DEFAULT NULL,
    sort_order  INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE templates (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id      INT UNSIGNED DEFAULT NULL,
    name             VARCHAR(150) NOT NULL,
    version          VARCHAR(20)  NOT NULL DEFAULT '1.0.0',
    thumbnail_path   VARCHAR(500) DEFAULT NULL,
    preview_url      VARCHAR(500) DEFAULT NULL,
    source           ENUM('system','uploaded') NOT NULL DEFAULT 'system',
    is_installed     TINYINT(1) NOT NULL DEFAULT 0,
    is_active        TINYINT(1) NOT NULL DEFAULT 0,
    installed_at     DATETIME DEFAULT NULL,
    uploaded_by      INT UNSIGNED DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_templates_category
        FOREIGN KEY (category_id) REFERENCES template_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_templates_uploader
        FOREIGN KEY (uploaded_by) REFERENCES panel_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_templates_category  ON templates (category_id);
CREATE INDEX idx_templates_active    ON templates (is_active);

-- ============================================================
-- Global site settings (single row)
-- ============================================================
CREATE TABLE site_settings (
    id                  TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    site_name           VARCHAR(150) NOT NULL,
    site_domain         VARCHAR(190) DEFAULT NULL,
    logo_path           VARCHAR(500) DEFAULT NULL,
    favicon_path        VARCHAR(500) DEFAULT NULL,
    panel_plan          ENUM('free','standard','professional') NOT NULL DEFAULT 'free',
    active_template_id  INT UNSIGNED DEFAULT NULL,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_template
        FOREIGN KEY (active_template_id) REFERENCES templates(id) ON DELETE SET NULL,
    CONSTRAINT chk_settings_single_row CHECK (id = 1)
) ENGINE=InnoDB;

-- ============================================================
-- Site pages
-- ============================================================
CREATE TABLE pages (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title             VARCHAR(255) NOT NULL,
    slug              VARCHAR(255) NOT NULL UNIQUE,
    status            ENUM('published','draft','archived') NOT NULL DEFAULT 'draft',
    meta_title        VARCHAR(255) DEFAULT NULL,
    meta_description  VARCHAR(500) DEFAULT NULL,
    is_system_page    TINYINT(1) NOT NULL DEFAULT 0,
    created_by        INT UNSIGNED NOT NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pages_creator
        FOREIGN KEY (created_by) REFERENCES panel_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_pages_status ON pages (status);

CREATE TABLE page_blocks (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_id       INT UNSIGNED NOT NULL,
    block_order   INT UNSIGNED NOT NULL,
    block_type    ENUM('text','image','table') NOT NULL,
    text_content  MEDIUMTEXT DEFAULT NULL,
    image_path    VARCHAR(500) DEFAULT NULL,
    image_alt     VARCHAR(255) DEFAULT NULL,
    table_data    JSON DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_page_blocks_page
        FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_page_blocks_order ON page_blocks (page_id, block_order);

-- ============================================================
-- Articles and their categories
-- ============================================================
CREATE TABLE article_categories (
    id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(100) NOT NULL,
    slug  VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE articles (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id        INT UNSIGNED DEFAULT NULL,
    title              VARCHAR(255) NOT NULL,
    slug               VARCHAR(255) NOT NULL UNIQUE,
    cover_image_path   VARCHAR(500) DEFAULT NULL,
    status             ENUM('published','draft','archived') NOT NULL DEFAULT 'draft',
    views_count        INT UNSIGNED NOT NULL DEFAULT 0,
    author_id          INT UNSIGNED NOT NULL,
    published_at       DATETIME DEFAULT NULL,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_articles_category
        FOREIGN KEY (category_id) REFERENCES article_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_articles_author
        FOREIGN KEY (author_id) REFERENCES panel_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_articles_status    ON articles (status);
CREATE INDEX idx_articles_category  ON articles (category_id);

CREATE TABLE article_blocks (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id    INT UNSIGNED NOT NULL,
    block_order   INT UNSIGNED NOT NULL,
    block_type    ENUM('text','image','table') NOT NULL,
    text_content  MEDIUMTEXT DEFAULT NULL,
    image_path    VARCHAR(500) DEFAULT NULL,
    image_alt     VARCHAR(255) DEFAULT NULL,
    table_data    JSON DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_article_blocks_article
        FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_article_blocks_order ON article_blocks (article_id, block_order);

-- ============================================================
-- Marketplaces and their connections
-- ============================================================
CREATE TABLE marketplaces (
    id            TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(30)  NOT NULL UNIQUE,
    display_name  VARCHAR(100) NOT NULL,
    logo_path     VARCHAR(500) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE marketplace_connections (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    marketplace_id     TINYINT UNSIGNED NOT NULL,
    is_connected       TINYINT(1) NOT NULL DEFAULT 0,
    api_key            VARCHAR(255) DEFAULT NULL,
    api_secret         VARCHAR(255) DEFAULT NULL,
    sync_products      TINYINT(1) NOT NULL DEFAULT 0,
    sync_price_stock   TINYINT(1) NOT NULL DEFAULT 0,
    receive_orders     TINYINT(1) NOT NULL DEFAULT 0,
    sales_report       TINYINT(1) NOT NULL DEFAULT 0,
    connected_at       DATETIME DEFAULT NULL,
    connected_by       INT UNSIGNED DEFAULT NULL,
    UNIQUE KEY uq_marketplace (marketplace_id),
    CONSTRAINT fk_mc_marketplace
        FOREIGN KEY (marketplace_id) REFERENCES marketplaces(id) ON DELETE CASCADE,
    CONSTRAINT fk_mc_user
        FOREIGN KEY (connected_by) REFERENCES panel_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Media library and where each file is used
-- ============================================================
CREATE TABLE media_library (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name      VARCHAR(255) NOT NULL,
    file_path      VARCHAR(500) NOT NULL,
    file_type      ENUM('image','video') NOT NULL,
    mime_type      VARCHAR(100) NOT NULL,
    file_size_kb   INT UNSIGNED NOT NULL,
    width          INT UNSIGNED DEFAULT NULL,
    height         INT UNSIGNED DEFAULT NULL,
    uploaded_by    INT UNSIGNED NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_uploader
        FOREIGN KEY (uploaded_by) REFERENCES panel_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_media_type ON media_library (file_type);

CREATE TABLE media_usage (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    media_id      INT UNSIGNED NOT NULL,
    usable_type   ENUM('page','article') NOT NULL,
    usable_id     INT UNSIGNED NOT NULL,
    CONSTRAINT fk_media_usage_media
        FOREIGN KEY (media_id) REFERENCES media_library(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_media_usage_lookup ON media_usage (usable_type, usable_id);

-- ============================================================
-- Licenses (Enamad, ZarinPal, online business license)
-- ============================================================
CREATE TABLE licenses (
    id                TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_type      ENUM('enamad','zarinpal','business_license') NOT NULL UNIQUE,
    display_name      VARCHAR(100) NOT NULL,
    status            ENUM('not_connected','pending','verified','rejected') NOT NULL DEFAULT 'not_connected',
    credentials       JSON DEFAULT NULL,
    certificate_file  VARCHAR(500) DEFAULT NULL,
    verified_at       DATETIME DEFAULT NULL,
    updated_by        INT UNSIGNED DEFAULT NULL,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_licenses_user
        FOREIGN KEY (updated_by) REFERENCES panel_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- Notifications
-- ============================================================
CREATE TABLE notifications (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    panel_user_id  INT UNSIGNED NOT NULL,
    title          VARCHAR(255) NOT NULL,
    message        VARCHAR(500) DEFAULT NULL,
    type           ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    is_read        TINYINT(1) NOT NULL DEFAULT 0,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (panel_user_id) REFERENCES panel_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_notifications_user_read ON notifications (panel_user_id, is_read);

-- ============================================================
-- Panel user activity log (for transparency and auditing)
-- ============================================================
CREATE TABLE activity_logs (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    panel_user_id  INT UNSIGNED NOT NULL,
    action_type    VARCHAR(60) NOT NULL,
    module_key     VARCHAR(50) NOT NULL,
    description    VARCHAR(500) DEFAULT NULL,
    ip_address     VARCHAR(45) DEFAULT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_logs_user
        FOREIGN KEY (panel_user_id) REFERENCES panel_users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_logs_module ON activity_logs (module_key);

-- ============================================================
-- Daily dashboard stat snapshot
-- ============================================================
CREATE TABLE dashboard_snapshots (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    snapshot_date    DATE NOT NULL UNIQUE,
    pages_count      INT UNSIGNED NOT NULL DEFAULT 0,
    articles_count   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- Mandatory seed data
-- ============================================================
INSERT INTO permissions (module_key, module_label) VALUES
    ('dashboard','پیشخوان'), ('pages','ساخت سایت'), ('articles','صفحات ساخته شده'),
    ('templates','قالب سایت'), ('sales','افزایش فروش'),
    ('media','رسانه و عکس‌ها'), ('licenses','مجوزات'), ('settings','تنظیمات پنل');

INSERT INTO marketplaces (slug, display_name) VALUES
    ('digikala','دیجی‌کالا'), ('torob','ترب'), ('emalls','ایمالز'), ('snapp','اسنپ!');

INSERT INTO licenses (license_type, display_name) VALUES
    ('enamad','اینماد (نماد اعتماد الکترونیکی)'),
    ('zarinpal','زرین‌پال (درگاه پرداخت)'),
    ('business_license','اعتبار کسب‌وکار آنلاین');

INSERT INTO site_settings (id, site_name, panel_plan) VALUES (1, 'وب‌سایت من', 'free');

INSERT INTO template_categories (name, slug, sort_order) VALUES
    ('همه','all',0),('فروشگاهی','store',1),('شرکتی','corporate',2),('خبری و مجله‌ای','news',3),
    ('وبلاگ شخصی','personal-blog',4),('نمونه کار','portfolio',5),('آموزشی','educational',6),
    ('تک‌صفحه','single-page',7),('سایر','other',8);

-- نمونه قالب‌ها
INSERT INTO templates (category_id, name, version, is_installed, is_active) VALUES
    (2, 'قالب فروشگاهی پیش‌فرض', '1.0.0', 1, 1),
    (3, 'قالب شرکتی مدرن', '1.2.0', 1, 0),
    (4, 'قالب خبری', '2.0.0', 0, 0);

UPDATE site_settings SET active_template_id = 1 WHERE id = 1;

-- کاربر مالک پیش‌فرض (رمز: Admin123! )
-- password_hash('Admin123!', PASSWORD_BCRYPT)
INSERT INTO panel_users (full_name, username, email, password_hash, role, status) VALUES
('مدیر کل', 'admin', 'admin@example.com', '$2y$10$cnvXc0McHVEK5Nml0ZvMwepHQ8jERISPDuFgn9BuwBsO6CZ5utTO.', 'owner', 'active');

-- ===== BEGIN: visual page builder (Part 0) =====
CREATE TABLE builder_canvases (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buildable_type  ENUM('page','template') NOT NULL,
    template_id     INT UNSIGNED DEFAULT NULL,
    title           VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) DEFAULT NULL,
    status          ENUM('draft','published') NOT NULL DEFAULT 'draft',
    published_at    DATETIME DEFAULT NULL,
    created_by      INT UNSIGNED NOT NULL,
    updated_by      INT UNSIGNED DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_builder_canvases_slug (slug),
    UNIQUE KEY uq_builder_canvases_template (template_id),
    CONSTRAINT fk_builder_canvases_template
        FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL,
    CONSTRAINT fk_builder_canvases_creator
        FOREIGN KEY (created_by) REFERENCES panel_users(id) ON DELETE CASCADE,
    CONSTRAINT fk_builder_canvases_updater
        FOREIGN KEY (updated_by) REFERENCES panel_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE INDEX idx_builder_canvases_type_status ON builder_canvases (buildable_type, status);

CREATE TABLE builder_blocks (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    canvas_id    INT UNSIGNED NOT NULL,
    parent_id    INT UNSIGNED DEFAULT NULL,
    block_uid    CHAR(32) NOT NULL,
    block_type   VARCHAR(50) NOT NULL,
    sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    data         JSON NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_builder_blocks_uid (canvas_id, block_uid),
    CONSTRAINT fk_builder_blocks_canvas
        FOREIGN KEY (canvas_id) REFERENCES builder_canvases(id) ON DELETE CASCADE,
    CONSTRAINT fk_builder_blocks_parent
        FOREIGN KEY (parent_id) REFERENCES builder_blocks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_builder_blocks_tree ON builder_blocks (canvas_id, parent_id, sort_order);
CREATE INDEX idx_builder_blocks_type ON builder_blocks (canvas_id, block_type);
-- ===== END: visual page builder (Part 0) =====
