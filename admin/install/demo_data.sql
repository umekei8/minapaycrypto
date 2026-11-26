-- MinaPay Crypto Admin — Demo Data for staging.minapay-crypto.com
-- Generates realistic demo data for UI verification
-- Run AFTER schema.sql
-- WARNING: Do NOT run on production database

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────
-- デモ加盟店データ（6件）
-- ─────────────────────────────────────────────
INSERT INTO `merchants`
  (code, name, name_kana, business_type, representative, email, phone, postal_code, address,
   website, business_category, fee_percent, contract_start,
   status, reviewed_by, reviewed_at, review_note, api_token,
   portal_email, portal_password, portal_enabled, memo)
VALUES
-- 1: アクティブ / 東京カフェ
('M001', '東京クリプトカフェ', 'トウキョウクリプトカフェ', 'individual', '山田 太郎',
 'yamada@tokyo-cafe.example', '03-1234-5678', '150-0001', '東京都渋谷区神宮前1-1-1',
 'https://tokyo-cafe.example', 'カフェ・飲食', 6.50, '2026-01-15',
 'active', 1, '2026-01-20 10:30:00', '書類確認済み。問題なし。', 'tok_m001_a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6',
 'yamada@tokyo-cafe.example', '$2y$12$demohashedpassword001xxxxxxxxxxxxxxxxxxxx', 1, 'メイン加盟店。VIP対応'),

-- 2: アクティブ / 大阪電器
('M002', '大阪デジタル電器', 'オオサカデジタルデンキ', 'corporation', '鈴木 花子',
 'suzuki@osaka-denki.example', '06-9876-5432', '530-0001', '大阪府大阪市北区梅田2-2-2',
 'https://osaka-denki.example', '家電・電子機器', 5.50, '2026-02-01',
 'active', 1, '2026-02-05 14:00:00', '法人登記確認済み', 'tok_m002_b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7',
 'suzuki@osaka-denki.example', '$2y$12$demohashedpassword002xxxxxxxxxxxxxxxxxxxx', 1, NULL),

-- 3: アクティブ / 福岡宿泊
('M003', 'フクオカステイホテル', 'フクオカステイホテル', 'corporation', '佐藤 次郎',
 'sato@fukuoka-hotel.example', '092-1111-2222', '810-0001', '福岡県福岡市博多区博多駅前3-3-3',
 'https://fukuoka-hotel.example', 'ホテル・宿泊', 7.00, '2026-02-15',
 'active', 1, '2026-02-20 09:00:00', 'ホテル業免許確認済み', 'tok_m003_c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8',
 'sato@fukuoka-hotel.example', '$2y$12$demohashedpassword003xxxxxxxxxxxxxxxxxxxx', 1, NULL),

-- 4: 審査中
('M004', 'ナゴヤアニメショップ', 'ナゴヤアニメショップ', 'individual', '田中 三郎',
 'tanaka@nagoya-anime.example', '052-3333-4444', '460-0001', '愛知県名古屋市中区栄4-4-4',
 'https://nagoya-anime.example', 'アニメ・マンガグッズ', 6.00, NULL,
 'reviewing', 1, '2026-11-20 11:00:00', '身分証確認中', 'tok_m004_d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9',
 NULL, NULL, 0, NULL),

-- 5: 新規申込
('M005', 'サッポロオーガニックマーケット', 'サッポロオーガニックマーケット', 'individual', '伊藤 四郎',
 'ito@sapporo-organic.example', '011-5555-6666', '060-0001', '北海道札幌市中央区北1条西5-5-5',
 NULL, 'オーガニック食品', 6.50, NULL,
 'applied', NULL, NULL, NULL, 'tok_m005_e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0',
 NULL, NULL, 0, NULL),

-- 6: 停止
('M006', '旧東京オンラインショップ', 'キュウトウキョウオンラインショップ', 'corporation', '中村 五郎',
 'nakamura@old-shop.example', '03-7777-8888', '100-0001', '東京都千代田区丸の内1-1-1',
 'https://old-shop.example', 'オンラインショッピング', 8.00, '2025-06-01',
 'suspended', 1, '2026-09-01 10:00:00', '規約違反のため一時停止', 'tok_m006_f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1',
 'nakamura@old-shop.example', '$2y$12$demohashedpassword006xxxxxxxxxxxxxxxxxxxx', 0, '規約違反履歴あり');

-- ─────────────────────────────────────────────
-- ウォレットアドレス（受取専用）
-- ─────────────────────────────────────────────
INSERT INTO `wallets` (merchant_id, currency, network, address, label, status, set_by) VALUES
-- M001 東京クリプトカフェ
(1, 'BTC',        'mainnet', '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna', 'BTC受取',       'active', 'merchant'),
(1, 'ETH',        'mainnet', '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'ETH受取', 'active', 'merchant'),
(1, 'XRP',        'mainnet', 'rHb9CJAWyB4rj91VRWn96DkukG4bwdtyTh', 'XRP受取',       'active', 'merchant'),
(1, 'USDT_ERC20', 'ERC20',   '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'USDT(ERC20)', 'active', 'merchant'),
-- M002 大阪デジタル電器
(2, 'BTC',        'mainnet', '1CounterpartyXXXXXXXXXXXXXXXUWLpVr', 'BTC受取',       'active', 'merchant'),
(2, 'ETH',        'mainnet', '0xAbC123456789DEF0abcdef123456789ABCDEF01', 'ETH受取',   'active', 'merchant'),
(2, 'USDT_TRC20', 'TRC20',   'TLhqouqwDxr2BwAXCgdkFfF1kHmxE1JGLV', 'USDT(TRC20)',   'active', 'merchant'),
-- M003 フクオカステイホテル
(3, 'BTC',        'mainnet', '1BoatSLRHtKNngkdXEeobR76b53LETtpyT', 'BTC受取',       'active', 'merchant'),
(3, 'XRP',        'mainnet', 'r3AdznfMEhcCqJgNnMwQhBM5zBFBgCDxaP', 'XRP受取',       'active', 'merchant');

-- ─────────────────────────────────────────────
-- POS端末
-- ─────────────────────────────────────────────
INSERT INTO `devices` (device_code, merchant_id, name, model, serial_number, location, api_token, last_seen_at, status) VALUES
('DEV001', 1, 'カフェ レジ1', 'ZCS Z108', 'SN-Z108-00001', '渋谷店 1階カウンター',
 'dev_tok_001_a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', '2026-11-25 18:30:00', 'active'),
('DEV002', 1, 'カフェ レジ2', 'ZCS Z108', 'SN-Z108-00002', '渋谷店 2階テーブル',
 'dev_tok_002_b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7', '2026-11-26 09:00:00', 'active'),
('DEV003', 2, '電器店 POS', 'ZCS Z108', 'SN-Z108-00003', '大阪梅田店 1階',
 'dev_tok_003_c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8', '2026-11-25 20:00:00', 'active'),
('DEV004', 3, 'ホテル フロント', 'ZCS Z108', 'SN-Z108-00004', '福岡博多店 フロント',
 'dev_tok_004_d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9', '2026-11-26 08:00:00', 'active'),
('DEV005', 3, 'ホテル 売店', 'ZCS Z108', 'SN-Z108-00005', '福岡博多店 売店',
 'dev_tok_005_e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0', '2026-11-20 15:00:00', 'offline');

-- ─────────────────────────────────────────────
-- 決済データ（過去90日分のデモデータ）
-- ─────────────────────────────────────────────

-- helper: confirmed payments, various currencies and amounts
-- M001 東京クリプトカフェ — BTC/ETH主体
INSERT INTO `payments`
  (payment_code, merchant_id, device_id, currency, amount_jpy, amount_crypto, rate_jpy, rate_source,
   fee_percent, fee_jpy, receiving_address, tx_hash, confirmations, status, expires_at, confirmed_at, created_at)
VALUES
('PAY000001', 1, 1, 'BTC',  3200,   0.000044,  72727272, 'avg:CoinGecko+Binance+Kraken', 6.50, 208, '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna', 'abc001tx0000000000000000000000000000000000000000000000000000000000', 2, 'confirmed', DATE_ADD(NOW(), INTERVAL -88 DAY), DATE_ADD(NOW(), INTERVAL -88 DAY), DATE_ADD(NOW(), INTERVAL -89 DAY)),
('PAY000002', 1, 1, 'ETH',  8500,   0.0138,    616000,   'avg:CoinGecko+Binance+Kraken', 6.50, 553, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'eth002tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -85 DAY), DATE_ADD(NOW(), INTERVAL -85 DAY), DATE_ADD(NOW(), INTERVAL -86 DAY)),
('PAY000003', 1, 2, 'BTC',  5600,   0.000077,  72727272, 'avg:CoinGecko+Binance+Kraken', 6.50, 364, '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna', 'abc003tx0000000000000000000000000000000000000000000000000000000000', 3, 'confirmed', DATE_ADD(NOW(), INTERVAL -80 DAY), DATE_ADD(NOW(), INTERVAL -80 DAY), DATE_ADD(NOW(), INTERVAL -81 DAY)),
('PAY000004', 1, 1, 'XRP',  1800,   7.2,       250,      'avg:CoinGecko+Binance+Kraken', 6.50, 117, 'rHb9CJAWyB4rj91VRWn96DkukG4bwdtyTh',      'xrp004tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -75 DAY), DATE_ADD(NOW(), INTERVAL -75 DAY), DATE_ADD(NOW(), INTERVAL -76 DAY)),
('PAY000005', 1, 2, 'ETH',  12000,  0.0195,    615384,   'avg:CoinGecko+Binance+Kraken', 6.50, 780, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'eth005tx0000000000000000000000000000000000000000000000000000000000', 2, 'confirmed', DATE_ADD(NOW(), INTERVAL -70 DAY), DATE_ADD(NOW(), INTERVAL -70 DAY), DATE_ADD(NOW(), INTERVAL -71 DAY)),
('PAY000006', 1, 1, 'USDT_ERC20', 6500, 43.33,  150,     'avg:CoinGecko+Binance',       6.50, 423, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'usd006tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -65 DAY), DATE_ADD(NOW(), INTERVAL -65 DAY), DATE_ADD(NOW(), INTERVAL -66 DAY)),
('PAY000007', 1, 1, 'BTC',  9800,   0.000135,  72592592, 'avg:CoinGecko+Binance+Kraken', 6.50, 637, '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna', 'abc007tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -60 DAY), DATE_ADD(NOW(), INTERVAL -60 DAY), DATE_ADD(NOW(), INTERVAL -61 DAY)),
('PAY000008', 1, 2, 'ETH',  4300,   0.0070,    614285,   'avg:CoinGecko+Binance+Kraken', 6.50, 280, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'eth008tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -55 DAY), DATE_ADD(NOW(), INTERVAL -55 DAY), DATE_ADD(NOW(), INTERVAL -56 DAY)),
('PAY000009', 1, 1, 'BTC',  15000,  0.000207,  72463768, 'avg:CoinGecko+Binance+Kraken', 6.50, 975, '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna', 'abc009tx0000000000000000000000000000000000000000000000000000000000', 2, 'confirmed', DATE_ADD(NOW(), INTERVAL -50 DAY), DATE_ADD(NOW(), INTERVAL -50 DAY), DATE_ADD(NOW(), INTERVAL -51 DAY)),
('PAY000010', 1, 2, 'XRP',  2400,   9.6,       250,      'avg:CoinGecko+Binance+Kraken', 6.50, 156, 'rHb9CJAWyB4rj91VRWn96DkukG4bwdtyTh',      'xrp010tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -45 DAY), DATE_ADD(NOW(), INTERVAL -45 DAY), DATE_ADD(NOW(), INTERVAL -46 DAY)),
('PAY000011', 1, 1, 'ETH',  7200,   0.0117,    615384,   'avg:CoinGecko+Binance+Kraken', 6.50, 468, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'eth011tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -40 DAY), DATE_ADD(NOW(), INTERVAL -40 DAY), DATE_ADD(NOW(), INTERVAL -41 DAY)),
('PAY000012', 1, 2, 'BTC',  21000,  0.000289,  72664359, 'avg:CoinGecko+Binance+Kraken', 6.50, 1365,  '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna', 'abc012tx0000000000000000000000000000000000000000000000000000000000', 3, 'confirmed', DATE_ADD(NOW(), INTERVAL -35 DAY), DATE_ADD(NOW(), INTERVAL -35 DAY), DATE_ADD(NOW(), INTERVAL -36 DAY)),
('PAY000013', 1, 1, 'USDT_ERC20', 11000, 73.33, 150,    'avg:CoinGecko+Binance',       6.50, 715, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'usd013tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -30 DAY), DATE_ADD(NOW(), INTERVAL -30 DAY), DATE_ADD(NOW(), INTERVAL -31 DAY)),
('PAY000014', 1, 1, 'ETH',  5500,   0.0089,    617977,   'avg:CoinGecko+Binance+Kraken', 6.50, 358, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'eth014tx0000000000000000000000000000000000000000000000000000000000', 2, 'confirmed', DATE_ADD(NOW(), INTERVAL -25 DAY), DATE_ADD(NOW(), INTERVAL -25 DAY), DATE_ADD(NOW(), INTERVAL -26 DAY)),
('PAY000015', 1, 2, 'BTC',  8900,   0.000122,  72950819, 'avg:CoinGecko+Binance+Kraken', 6.50, 579, '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna', 'abc015tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -20 DAY), DATE_ADD(NOW(), INTERVAL -20 DAY), DATE_ADD(NOW(), INTERVAL -21 DAY)),
('PAY000016', 1, 1, 'XRP',  3600,   14.4,      250,      'avg:CoinGecko+Binance+Kraken', 6.50, 234, 'rHb9CJAWyB4rj91VRWn96DkukG4bwdtyTh',      'xrp016tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -15 DAY), DATE_ADD(NOW(), INTERVAL -15 DAY), DATE_ADD(NOW(), INTERVAL -16 DAY)),
('PAY000017', 1, 2, 'ETH',  18000,  0.0291,    618556,   'avg:CoinGecko+Binance+Kraken', 6.50, 1170, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'eth017tx0000000000000000000000000000000000000000000000000000000000', 2, 'confirmed', DATE_ADD(NOW(), INTERVAL -10 DAY), DATE_ADD(NOW(), INTERVAL -10 DAY), DATE_ADD(NOW(), INTERVAL -11 DAY)),
('PAY000018', 1, 1, 'BTC',  6700,   0.0000917, 73064320, 'avg:CoinGecko+Binance+Kraken', 6.50, 436, '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna', 'abc018tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -7 DAY), DATE_ADD(NOW(), INTERVAL -7 DAY), DATE_ADD(NOW(), INTERVAL -8 DAY)),
('PAY000019', 1, 2, 'USDT_ERC20', 4800, 32.00, 150,     'avg:CoinGecko+Binance',       6.50, 312, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'usd019tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -5 DAY), DATE_ADD(NOW(), INTERVAL -5 DAY), DATE_ADD(NOW(), INTERVAL -6 DAY)),
('PAY000020', 1, 1, 'ETH',  13500,  0.0218,    619266,   'avg:CoinGecko+Binance+Kraken', 6.50, 878, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'eth020tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -3 DAY), DATE_ADD(NOW(), INTERVAL -3 DAY), DATE_ADD(NOW(), INTERVAL -4 DAY)),
('PAY000021', 1, 1, 'BTC',  28000,  0.000383,  73107438, 'avg:CoinGecko+Binance+Kraken', 6.50, 1820, '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna', 'abc021tx0000000000000000000000000000000000000000000000000000000000', 2, 'confirmed', DATE_ADD(NOW(), INTERVAL -1 DAY), DATE_ADD(NOW(), INTERVAL -1 DAY), DATE_ADD(NOW(), INTERVAL -2 DAY)),
('PAY000022', 1, 2, 'ETH',  4100,   0.0066,    621212,   'avg:CoinGecko+Binance+Kraken', 6.50, 267, '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', NULL, 0, 'pending', DATE_ADD(NOW(), INTERVAL 10 MINUTE), NULL, NOW()),
('PAY000023', 1, 1, 'BTC',  7700,   0.000105,  73333333, 'avg:CoinGecko+Binance+Kraken', 6.50, 501, '1A1zP1eP5QGefi2DMPTfTL5SLmv7Divfna', NULL, 0, 'expired', DATE_ADD(NOW(), INTERVAL -1 HOUR), NULL, DATE_ADD(NOW(), INTERVAL -2 HOUR)),

-- M002 大阪デジタル電器 — BTC/USDT
('PAY000030', 2, 3, 'BTC',  45000,  0.000617,  72932415, 'avg:CoinGecko+Binance+Kraken', 5.50, 2475, '1CounterpartyXXXXXXXXXXXXXXXUWLpVr', 'abc030tx0000000000000000000000000000000000000000000000000000000000', 2, 'confirmed', DATE_ADD(NOW(), INTERVAL -88 DAY), DATE_ADD(NOW(), INTERVAL -88 DAY), DATE_ADD(NOW(), INTERVAL -89 DAY)),
('PAY000031', 2, 3, 'USDT_TRC20', 32000, 213.33, 150,   'avg:CoinGecko+Binance',       5.50, 1760, 'TLhqouqwDxr2BwAXCgdkFfF1kHmxE1JGLV', 'usd031tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -75 DAY), DATE_ADD(NOW(), INTERVAL -75 DAY), DATE_ADD(NOW(), INTERVAL -76 DAY)),
('PAY000032', 2, 3, 'BTC',  89000,  0.001221,  72892547, 'avg:CoinGecko+Binance+Kraken', 5.50, 4895, '1CounterpartyXXXXXXXXXXXXXXXUWLpVr', 'abc032tx0000000000000000000000000000000000000000000000000000000000', 3, 'confirmed', DATE_ADD(NOW(), INTERVAL -60 DAY), DATE_ADD(NOW(), INTERVAL -60 DAY), DATE_ADD(NOW(), INTERVAL -61 DAY)),
('PAY000033', 2, 3, 'ETH',  67000,  0.1083,    618651,   'avg:CoinGecko+Binance+Kraken', 5.50, 3685, '0xAbC123456789DEF0abcdef123456789ABCDEF01', 'eth033tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -45 DAY), DATE_ADD(NOW(), INTERVAL -45 DAY), DATE_ADD(NOW(), INTERVAL -46 DAY)),
('PAY000034', 2, 3, 'USDT_TRC20', 54000, 360.00, 150,  'avg:CoinGecko+Binance',       5.50, 2970, 'TLhqouqwDxr2BwAXCgdkFfF1kHmxE1JGLV', 'usd034tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -30 DAY), DATE_ADD(NOW(), INTERVAL -30 DAY), DATE_ADD(NOW(), INTERVAL -31 DAY)),
('PAY000035', 2, 3, 'BTC',  120000, 0.001643,  73037736, 'avg:CoinGecko+Binance+Kraken', 5.50, 6600, '1CounterpartyXXXXXXXXXXXXXXXUWLpVr', 'abc035tx0000000000000000000000000000000000000000000000000000000000', 2, 'confirmed', DATE_ADD(NOW(), INTERVAL -15 DAY), DATE_ADD(NOW(), INTERVAL -15 DAY), DATE_ADD(NOW(), INTERVAL -16 DAY)),
('PAY000036', 2, 3, 'ETH',  38000,  0.0614,    619000,   'avg:CoinGecko+Binance+Kraken', 5.50, 2090, '0xAbC123456789DEF0abcdef123456789ABCDEF01', 'eth036tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -7 DAY), DATE_ADD(NOW(), INTERVAL -7 DAY), DATE_ADD(NOW(), INTERVAL -8 DAY)),
('PAY000037', 2, 3, 'BTC',  75000,  0.001025,  73170731, 'avg:CoinGecko+Binance+Kraken', 5.50, 4125, '1CounterpartyXXXXXXXXXXXXXXXUWLpVr', 'abc037tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -2 DAY), DATE_ADD(NOW(), INTERVAL -2 DAY), DATE_ADD(NOW(), INTERVAL -3 DAY)),
('PAY000038', 2, 3, 'USDT_TRC20', 18000, 120.00, 150,  'avg:CoinGecko+Binance',       5.50, 990, 'TLhqouqwDxr2BwAXCgdkFfF1kHmxE1JGLV', NULL, 0, 'pending', DATE_ADD(NOW(), INTERVAL 8 MINUTE), NULL, NOW()),

-- M003 フクオカステイホテル — BTC/XRP
('PAY000050', 3, 4, 'BTC',  55000,  0.000754,  72944162, 'avg:CoinGecko+Binance+Kraken', 7.00, 3850, '1BoatSLRHtKNngkdXEeobR76b53LETtpyT', 'abc050tx0000000000000000000000000000000000000000000000000000000000', 2, 'confirmed', DATE_ADD(NOW(), INTERVAL -85 DAY), DATE_ADD(NOW(), INTERVAL -85 DAY), DATE_ADD(NOW(), INTERVAL -86 DAY)),
('PAY000051', 3, 4, 'XRP',  38000,  152.0,     250,      'avg:CoinGecko+Binance+Kraken', 7.00, 2660, 'r3AdznfMEhcCqJgNnMwQhBM5zBFBgCDxaP',      'xrp051tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -70 DAY), DATE_ADD(NOW(), INTERVAL -70 DAY), DATE_ADD(NOW(), INTERVAL -71 DAY)),
('PAY000052', 3, 4, 'BTC',  78000,  0.001069,  72993451, 'avg:CoinGecko+Binance+Kraken', 7.00, 5460, '1BoatSLRHtKNngkdXEeobR76b53LETtpyT', 'abc052tx0000000000000000000000000000000000000000000000000000000000', 3, 'confirmed', DATE_ADD(NOW(), INTERVAL -55 DAY), DATE_ADD(NOW(), INTERVAL -55 DAY), DATE_ADD(NOW(), INTERVAL -56 DAY)),
('PAY000053', 3, 5, 'XRP',  12000,  48.0,      250,      'avg:CoinGecko+Binance+Kraken', 7.00, 840, 'r3AdznfMEhcCqJgNnMwQhBM5zBFBgCDxaP',       'xrp053tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -40 DAY), DATE_ADD(NOW(), INTERVAL -40 DAY), DATE_ADD(NOW(), INTERVAL -41 DAY)),
('PAY000054', 3, 4, 'BTC',  145000, 0.001983,  73122037, 'avg:CoinGecko+Binance+Kraken', 7.00, 10150,'1BoatSLRHtKNngkdXEeobR76b53LETtpyT', 'abc054tx0000000000000000000000000000000000000000000000000000000000', 2, 'confirmed', DATE_ADD(NOW(), INTERVAL -25 DAY), DATE_ADD(NOW(), INTERVAL -25 DAY), DATE_ADD(NOW(), INTERVAL -26 DAY)),
('PAY000055', 3, 4, 'XRP',  25000,  100.0,     250,      'avg:CoinGecko+Binance+Kraken', 7.00, 1750, 'r3AdznfMEhcCqJgNnMwQhBM5zBFBgCDxaP',      'xrp055tx0000000000000000000000000000000000000000000000000000000000', 1, 'confirmed', DATE_ADD(NOW(), INTERVAL -10 DAY), DATE_ADD(NOW(), INTERVAL -10 DAY), DATE_ADD(NOW(), INTERVAL -11 DAY)),
('PAY000056', 3, 4, 'BTC',  62000,  0.000848,  73113207, 'avg:CoinGecko+Binance+Kraken', 7.00, 4340, '1BoatSLRHtKNngkdXEeobR76b53LETtpyT', NULL, 0, 'pending', DATE_ADD(NOW(), INTERVAL 12 MINUTE), NULL, NOW()),
('PAY000057', 3, 4, 'XRP',  9500,   38.0,      250,      'avg:CoinGecko+Binance+Kraken', 7.00, 665, 'r3AdznfMEhcCqJgNnMwQhBM5zBFBgCDxaP',       NULL, 0, 'failed', DATE_ADD(NOW(), INTERVAL -4 DAY), NULL, DATE_ADD(NOW(), INTERVAL -5 DAY));

-- ─────────────────────────────────────────────
-- 設定値の更新（デモ用）
-- ─────────────────────────────────────────────
INSERT INTO `settings` (`key`, `value`) VALUES
  ('app_url',    'https://staging.minapay-crypto.com'),
  ('admin_url',  'https://staging.minapay-crypto.com')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────────────────────────
-- 確認クエリ（実行後に結果確認）
-- ─────────────────────────────────────────────
-- SELECT 'merchants' AS tbl, COUNT(*) AS cnt FROM merchants
-- UNION ALL SELECT 'wallets', COUNT(*) FROM wallets
-- UNION ALL SELECT 'devices', COUNT(*) FROM devices
-- UNION ALL SELECT 'payments', COUNT(*) FROM payments
-- UNION ALL SELECT 'confirmed', COUNT(*) FROM payments WHERE status='confirmed';
