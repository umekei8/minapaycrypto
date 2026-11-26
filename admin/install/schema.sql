-- MinaPay Crypto Admin — Database Schema v2.0
-- MySQL 5.7+ / MariaDB 10.3+
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────
-- 管理者テーブル
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`          VARCHAR(100)  NOT NULL,
  `email`         VARCHAR(255)  NOT NULL UNIQUE,
  `password`      VARCHAR(255)  NOT NULL COMMENT 'bcrypt hash',
  `role`          ENUM('super','admin') NOT NULL DEFAULT 'admin',
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME     NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 加盟店テーブル（審査フロー付き）
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `merchants` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code`            VARCHAR(20)   NOT NULL UNIQUE COMMENT 'M001形式',
  -- 基本情報
  `name`            VARCHAR(200)  NOT NULL COMMENT '屋号/法人名',
  `name_kana`       VARCHAR(200)  NULL COMMENT 'フリガナ',
  `business_type`   ENUM('individual','corporation') NOT NULL DEFAULT 'individual',
  `representative`  VARCHAR(100)  NULL COMMENT '代表者名',
  `email`           VARCHAR(255)  NOT NULL,
  `phone`           VARCHAR(50)   NULL,
  `postal_code`     VARCHAR(10)   NULL,
  `address`         VARCHAR(400)  NULL,
  `website`         VARCHAR(400)  NULL,
  `business_category` VARCHAR(100) NULL COMMENT '業種',
  -- 契約条件
  `fee_percent`     DECIMAL(5,2)  NOT NULL DEFAULT 6.50 COMMENT '加盟店手数料率',
  `contract_start`  DATE          NULL,
  `contract_end`    DATE          NULL COMMENT 'NULLは無期限',
  -- 審査ステータス
  `status`          ENUM('applied','reviewing','active','suspended','rejected') NOT NULL DEFAULT 'applied',
  `reviewed_by`     INT UNSIGNED  NULL COMMENT '審査者admin id',
  `reviewed_at`     DATETIME      NULL,
  `review_note`     TEXT          NULL COMMENT '審査メモ',
  -- API
  `api_token`       VARCHAR(64)   NOT NULL UNIQUE,
  -- 加盟店ポータルアカウント
  `portal_email`    VARCHAR(255)  NULL COMMENT '加盟店ログイン用メール',
  `portal_password` VARCHAR(255)  NULL COMMENT 'bcrypt',
  `portal_enabled`  TINYINT(1)    NOT NULL DEFAULT 0,
  -- メモ
  `memo`            TEXT          NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 受取ウォレットアドレス（加盟店が設定）
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `wallets` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `merchant_id`  INT UNSIGNED  NOT NULL,
  `currency`     VARCHAR(10)   NOT NULL COMMENT 'BTC/ETH/USDT_ERC20/USDT_TRC20/XRP/SOL',
  `network`      VARCHAR(30)   NULL COMMENT 'mainnet/ERC20/TRC20 etc',
  `address`      VARCHAR(200)  NOT NULL COMMENT '受取アドレスのみ。秘密鍵は保存しない',
  `label`        VARCHAR(100)  NULL,
  `status`       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `set_by`       ENUM('merchant','admin') NOT NULL DEFAULT 'merchant',
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_merchant_currency` (`merchant_id`, `currency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- POS端末テーブル（加盟店に紐付く）
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `devices` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `device_code`  VARCHAR(20)   NOT NULL UNIQUE COMMENT 'DEV001形式',
  `merchant_id`  INT UNSIGNED  NOT NULL,
  `name`         VARCHAR(100)  NOT NULL,
  `model`        VARCHAR(100)  NULL DEFAULT 'ZCS Z108',
  `serial_number` VARCHAR(100) NULL,
  `location`     VARCHAR(200)  NULL COMMENT '設置場所',
  `api_token`    VARCHAR(64)   NOT NULL UNIQUE,
  `last_seen_at` DATETIME      NULL,
  `status`       ENUM('active','offline','disabled') NOT NULL DEFAULT 'offline',
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- 決済テーブル
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payments` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `payment_code`      VARCHAR(20)    NOT NULL UNIQUE COMMENT 'PAY001形式',
  `merchant_id`       INT UNSIGNED   NOT NULL,
  `device_id`         INT UNSIGNED   NULL,
  `currency`          VARCHAR(20)    NOT NULL COMMENT 'BTC/ETH/XRP/USDT_ERC20/USDT_TRC20/SOL',
  `amount_jpy`        DECIMAL(14,0)  NOT NULL COMMENT '日本円換算金額',
  `amount_crypto`     DECIMAL(26,10) NOT NULL COMMENT '暗号通貨金額',
  `rate_jpy`          DECIMAL(18,2)  NOT NULL COMMENT '決済時レート(JPY/1コイン)',
  `rate_source`       VARCHAR(100)   NULL COMMENT 'レート取得元(avg:CoinGecko+Binance+Kraken等)',
  `fee_percent`       DECIMAL(5,2)   NOT NULL,
  `fee_jpy`           DECIMAL(14,0)  NOT NULL DEFAULT 0,
  `receiving_address` VARCHAR(300)   NOT NULL COMMENT '受取アドレス（秘密鍵なし）',
  `tx_hash`           VARCHAR(300)   NULL,
  `confirmations`     INT            NOT NULL DEFAULT 0,
  `status`            ENUM('pending','confirmed','failed','expired') NOT NULL DEFAULT 'pending',
  `expires_at`        DATETIME       NOT NULL,
  `confirmed_at`      DATETIME       NULL,
  `memo`              TEXT           NULL,
  `created_at`        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`),
  FOREIGN KEY (`device_id`)   REFERENCES `devices`(`id`) ON DELETE SET NULL,
  INDEX `idx_status`      (`status`),
  INDEX `idx_merchant_id` (`merchant_id`),
  INDEX `idx_currency`    (`currency`),
  INDEX `idx_created_at`  (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────
-- システム設定テーブル
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100)  NOT NULL PRIMARY KEY,
  `value`      TEXT          NULL,
  `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- デフォルト設定
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
  ('app_name',               'MinaPay Crypto'),
  ('app_url',                'https://minapay-crypto.com'),
  ('admin_url',              'https://admin.minapay-crypto.com'),
  ('timezone',               'Asia/Tokyo'),
  ('default_fee_percent',    '6.50'),
  ('rate_markup_percent',    '1.00'),
  ('payment_timeout_minutes','15'),
  ('min_payment_jpy',        '100'),
  ('supported_currencies',   'BTC,ETH,XRP,USDT_ERC20,USDT_TRC20'),
  ('coingecko_api_key',      ''),
  ('notify_email',           ''),
  ('notify_on_fail',         '1'),
  ('notify_on_rate_spike',   '1'),
  ('notify_daily_report',    '0'),
  ('notify_new_merchant',    '1');

SET FOREIGN_KEY_CHECKS = 1;
