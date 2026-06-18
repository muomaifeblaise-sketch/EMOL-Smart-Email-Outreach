-- ============================================================
--  EMOL - Smart Email Outreach Platform
--  Database Schema
--  Compatible with MySQL 5.7+ / XAMPP
-- ============================================================

CREATE DATABASE IF NOT EXISTS emol_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE emol_db;

-- ============================================================
--  USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(100) NOT NULL,
    username        VARCHAR(50)  NOT NULL UNIQUE,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('user','admin') DEFAULT 'user',
    avatar_initials VARCHAR(3)   DEFAULT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login      TIMESTAMP NULL
);

-- ============================================================
--  SESSIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS sessions (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    token         VARCHAR(255) NOT NULL UNIQUE,
    ip_address    VARCHAR(45)  DEFAULT NULL,
    user_agent    TEXT         DEFAULT NULL,
    expires_at    TIMESTAMP    NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
--  CAMPAIGNS
-- ============================================================
CREATE TABLE IF NOT EXISTS campaigns (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    name          VARCHAR(150) NOT NULL,
    category      VARCHAR(100) DEFAULT NULL,
    location      VARCHAR(150) DEFAULT NULL,
    goal          VARCHAR(100) DEFAULT NULL,
    status        ENUM('draft','sent','partial','scheduled') DEFAULT 'draft',
    emails_sent   INT DEFAULT 0,
    emails_opened INT DEFAULT 0,
    replies       INT DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at       TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
--  CONTACTS
-- ============================================================
CREATE TABLE IF NOT EXISTS contacts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    business_name VARCHAR(200) DEFAULT NULL,
    contact_name  VARCHAR(100) DEFAULT NULL,
    email         VARCHAR(150) NOT NULL,
    phone         VARCHAR(30)  DEFAULT NULL,
    category      VARCHAR(100) DEFAULT NULL,
    location      VARCHAR(150) DEFAULT NULL,
    has_website   TINYINT(1)   DEFAULT 1,
    notes         TEXT         DEFAULT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
--  SENT EMAILS
-- ============================================================
CREATE TABLE IF NOT EXISTS sent_emails (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    campaign_id   INT DEFAULT NULL,
    to_email      VARCHAR(150) NOT NULL,
    to_name       VARCHAR(150) DEFAULT NULL,
    subject       VARCHAR(255) NOT NULL,
    body          TEXT         NOT NULL,
    status        ENUM('sent','failed','opened','clicked') DEFAULT 'sent',
    sent_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    opened_at     TIMESTAMP    NULL,
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
);

-- ============================================================
--  AB TESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS ab_tests (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL,
    campaign_id    INT DEFAULT NULL,
    variant_a      TEXT DEFAULT NULL,
    variant_b      TEXT DEFAULT NULL,
    opens_a        INT  DEFAULT 0,
    opens_b        INT  DEFAULT 0,
    winner         ENUM('a','b','none') DEFAULT 'none',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id)     ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL
);

-- ============================================================
--  TEMPLATES
-- ============================================================
CREATE TABLE IF NOT EXISTS templates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    name        VARCHAR(150) NOT NULL,
    category    VARCHAR(100) DEFAULT NULL,
    subject     VARCHAR(255) DEFAULT NULL,
    body        TEXT         NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
--  LOGIN ATTEMPTS (rate limiting)
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    identifier  VARCHAR(150) NOT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    attempted_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
--  AUDIT LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          DEFAULT NULL,
    action      VARCHAR(100) NOT NULL,
    detail      TEXT         DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
--  DEFAULT ADMIN USER
--  password: Admin@1234  (change after first login!)
-- ============================================================
INSERT INTO users (full_name, username, email, password, role) VALUES
('EMOL Admin', 'admin', 'admin@emol.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
