-- MinaPay Crypto Admin — Database Schema
-- MySQL 5.7+ / MariaDB 10.3+
-- Character set: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────
-- 管理者テーブル
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(100)  NOT NULL,
  `email`        VARCHAR(255)  NOT NULL UNIQUE,
  `password`     VARCHAR(255)  NOT NULL COMMENT 'bcrypt hash',
  `role`         ENUM('super','admin') NOT NULL DEFAULT 'admin',
  `last_login_at` DATETIME     NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 加盟店テーブル
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `merchants` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`         VARCHAR(20)   NOT NULL UNIQUE COMMENT 'M001 形式',
  `name`         VARCHAR(200)  NOT NULL,
  `email`        VARCHAR(255)  NOT NULL,
  `phone`        VARCHAR(50)   NULL,
  `slug`         VARCHAR(100)  NOT NULL UNIQUE COMMENT '決済ページURL用スラッグ',
  `fee_percent`  DECIMAL(5,2)  NOT NULL DEFAULT 6.50,
  `api_token`    VARCHAR(64)   NOT NULL UNIQUE COMMENT '加盟店/POS端末用APIトークン',
  `status`       ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `memo`         TEXT          NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 受取ウォレットアドレス（非カストディ）
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `wallets` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `merchant_id`  INT UNSIGNED  NOT NULL,
  `currency`     VARCHAR(10)   NOT NULL COMMENT 'BTC/ETH/USDT/SOL/XRP',
  `address`      VARCHAR(200)  NOT NULL COMMENT '受取アドレスのみ。秘密鍵は保存しない',
  `label`        VARCHAR(100)  NULL,
  `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_merchant_currency` (`merchant_id`, `currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- POS端末テーブル
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `devices` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `device_code`  VARCHAR(20)   NOT NULL UNIQUE COMMENT 'DEV001 形式',
  `merchant_id`  INT UNSIGNED  NOT NULL,
  `name`         VARCHAR(100)  NOT NULL,
  `model`        VARCHAR(100)  NULL DEFAULT 'ZCS Z108',
  `location`     VARCHAR(200)  NULL,
  `api_token`    VARCHAR(64)   NOT NULL UNIQUE,
  `last_seen_at` DATETIME      NULL,
  `status`       ENUM('online','offline','disabled') NOT NULL DEFAULT 'offline',
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 決済テーブル
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payments` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payment_code`    VARCHAR(20)   NOT NULL UNIQUE COMMENT 'PAY001 形式',
  `merchant_id`     INT UNSIGNED  NOT NULL,
  `device_id`       INT UNSIGNED  NULL,
  `currency`        VARCHAR(10)   NOT NULL,
  `amount_jpy`      DECIMAL(12,0) NOT NULL COMMENT '日本円換算金額',
  `amount_crypto`   DECIMAL(24,8) NOT NULL COMMENT '暗号通貨金額',
  `rate_jpy`        DECIMAL(16,2) NOT NULL COMMENT '決済時レート(JPY/1コイン)',
  `fee_percent`     DECIMAL(5,2)  NOT NULL,
  `fee_jpy`         DECIMAL(12,0) NOT NULL DEFAULT 0,
  `receiving_address` VARCHAR(200) NOT NULL COMMENT '受取アドレス（秘密鍵なし）',
  `tx_hash`         VARCHAR(200)  NULL,
  `confirmations`   INT           NOT NULL DEFAULT 0,
  `status`          ENUM('pending','confirmed','failed','expired') NOT NULL DEFAULT 'pending',
  `expires_at`      DATETIME      NOT NULL,
  `confirmed_at`    DATETIME      NULL,
  `memo`            TEXT          NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`),
  FOREIGN KEY (`device_id`)   REFERENCES `devices`(`id`) ON DELETE SET NULL,
  INDEX `idx_status`      (`status`),
  INDEX `idx_merchant_id` (`merchant_id`),
  INDEX `idx_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- システム設定テーブル
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `key`          VARCHAR(100)  NOT NULL PRIMARY KEY,
  `value`        TEXT          NULL,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- デフォルト設定
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
  ('app_name',               'MinaPay Crypto'),
  ('app_url',                'https://minapay-crypto.com'),
  ('admin_url',              'https://admin.minapay-crypto.com'),
  ('timezone',               'Asia/Tokyo'),
  ('default_fee_percent',    '6.50'),
  ('payment_timeout_minutes','15'),
  ('min_payment_jpy',        '100'),
  ('coingecko_api_key',      ''),
  ('notify_email',           ''),
  ('notify_on_fail',         '1'),
  ('notify_on_rate_spike',   '1'),
  ('notify_daily_report',    '0'),
  ('notify_new_merchant',    '1');

SET FOREIGN_KEY_CHECKS = 1;
