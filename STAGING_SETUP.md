# staging.minapay-crypto.com セットアップ手順

## 前提条件
- XSERVERで `staging.minapay-crypto.com` のサブドメイン作成済み
- そのドメインのドキュメントルートが決まっている（例: `/public_html/staging/`）
- phpMyAdmin または MySQL アクセスあり

---

## 手順1: ファイルのアップロード

FTPまたはXSERVERファイルマネージャーで以下を `staging.minapay-crypto.com` のドキュメントルートにアップロード：

```
admin/
├── config/
├── install/
│   ├── schema.sql
│   ├── demo_data.sql
│   └── install.php        ← インストール後に削除必須
├── public/
│   └── index.php
├── src/
└── assets/
```

**アップロード先の例:**
```
/home/lybiasoft/staging.minapay-crypto.com/public_html/admin/
```

---

## 手順2: `.htaccess` 設定

`staging.minapay-crypto.com` のルート（`public_html/`）に `.htaccess` を配置:

```apache
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/admin/public/
RewriteRule ^(.*)$ /admin/public/$1 [L,QSA]
```

または、ドキュメントルートを `public/` に直接向けている場合はそのまま。

---

## 手順3: データベース作成

XSERVERのphpMyAdmin、またはMySQLコンソールで：

```sql
CREATE DATABASE minpaycrypto_staging DEFAULT CHARACTER SET utf8mb4;
CREATE USER 'staging_user'@'localhost' IDENTIFIED BY '強いパスワード';
GRANT ALL PRIVILEGES ON minpaycrypto_staging.* TO 'staging_user'@'localhost';
FLUSH PRIVILEGES;
```

---

## 手順4: install.php 実行

ブラウザで以下にアクセス:
```
https://staging.minapay-crypto.com/admin/install/install.php
```

フォームに入力:
- **DB Host**: `localhost`
- **DB Name**: `minpaycrypto_staging`
- **DB User**: `staging_user`
- **DB Password**: 手順3で設定したもの
- **Admin Email**: `admin@staging.example.com`
- **Admin Password**: 任意の強いパスワード

「インストール実行」をクリック → `config/database.php` が自動生成される。

---

## 手順5: デモデータ投入

phpMyAdmin で `minpaycrypto_staging` を選択し、以下を順番に実行:

1. `admin/install/schema.sql` をインポート（またはSQLタブに貼り付けて実行）
2. `admin/install/demo_data.sql` をインポート（またはSQLタブに貼り付けて実行）

**確認クエリ（demo_data.sql 末尾のコメントを外して実行）:**
```sql
SELECT 'merchants' AS tbl, COUNT(*) AS cnt FROM merchants
UNION ALL SELECT 'wallets', COUNT(*) FROM wallets
UNION ALL SELECT 'devices', COUNT(*) FROM devices
UNION ALL SELECT 'payments', COUNT(*) FROM payments
UNION ALL SELECT 'confirmed', COUNT(*) FROM payments WHERE status='confirmed';
```

期待される結果:
| tbl       | cnt |
|-----------|-----|
| merchants | 6   |
| wallets   | 9   |
| devices   | 5   |
| payments  | 57  |
| confirmed | 50  |

---

## 手順6: install.php の削除（必須）

```
XSERVERファイルマネージャー → admin/install/install.php を削除
```

または FTP で削除。**セキュリティ上必須です。**

---

## 手順7: 動作確認

ブラウザで `https://staging.minapay-crypto.com` にアクセス:

### ログイン画面
- [ ] MinaPay Crypto ロゴが表示される
- [ ] install.phpで設定したadmin/パスワードでログインできる

### ダッシュボード
- [ ] 左上サイドバーに MinaPay Crypto ロゴ
- [ ] KPIカード: BTC/ETH/XRP/USDT の取引量が表示される
- [ ] 各通貨のJPY換算額が下に表示される
- [ ] 為替レートカード: 3ソース平均が表示される
- [ ] 審査キュー: M004 ナゴヤアニメショップ（審査中）が表示される

### 決済一覧
- [ ] 57件の決済が一覧表示される
- [ ] 通貨・ステータス・加盟店でフィルタできる
- [ ] CSVダウンロードが機能する

### 加盟店管理
- [ ] 6件の加盟店が表示される（各ステータス）
- [ ] 東京クリプトカフェ（M001）の詳細を開く
  - [ ] 5タブが表示される（加盟店情報/契約条件/ウォレット設定/端末設定/決済履歴）
  - [ ] ウォレット: BTC/ETH/XRP/USDT_ERC20 のアドレスが登録済み
  - [ ] 端末: DEV001/DEV002 が表示される
  - [ ] 決済履歴: 過去90日分が表示される

### 審査ワークフロー
- [ ] M005（新規申込）→「審査開始」ボタンを押す → `reviewing` に変わる
- [ ] M004（審査中）→「審査通過」を押す → `active` に変わり、ポータルアカウント発行ダイアログが出る

### 売上分析
- [ ] KPI 6カード表示
- [ ] 日別グラフ（Chart.js）が表示される
- [ ] 通貨別内訳（カラーバー）が表示される
- [ ] 上位加盟店テーブル

### 為替レート
- [ ] BTC/ETH/XRP/USDT の3ソース平均レートが表示される
- [ ] エラーがないこと

---

## デモデータ概要

| 加盟店 | コード | ステータス | 決済件数 |
|--------|--------|-----------|---------|
| 東京クリプトカフェ | M001 | active | 23件 |
| 大阪デジタル電器 | M002 | active | 9件 |
| フクオカステイホテル | M003 | active | 8件 |
| ナゴヤアニメショップ | M004 | reviewing | 0件 |
| サッポロオーガニックマーケット | M005 | applied | 0件 |
| 旧東京オンラインショップ | M006 | suspended | 0件 |

**Admin ログイン情報（install.php で設定したもの）**  
- URL: `https://staging.minapay-crypto.com`
- Email: install時に設定
- Password: install時に設定

---

---

## 手順8: Android アプリとの API 連携設定

Admin サーバーが稼働したら、POS 端末（Android アプリ）と連携させます。

### 8-1: 端末 API トークンの確認

Admin ダッシュボードで対象加盟店の**端末設定タブ**を開き、端末の `api_token` を確認します。

phpMyAdmin で直接確認する場合:
```sql
SELECT d.device_code, d.name, d.api_token, m.name AS merchant_name
FROM devices d
JOIN merchants m ON m.id = d.merchant_id
WHERE d.status = 'active';
```

### 8-2: demo_config.json の更新

`app/src/main/assets/config/demo_config.json` の 2 フィールドを設定します:

```json
{
  "api_base_url": "https://staging.minapay-crypto.com",
  "terminal_api_key": "ここにdevicesテーブルのapi_tokenを貼る"
}
```

**注意:** `api_base_url` はすでに `https://staging.minapay-crypto.com` が設定済みです。
`terminal_api_key` のみ実際のトークンを入力してください。

### 8-3: APK ビルド & インストール

```bash
./gradlew assembleDebug
adb install app/build/outputs/apk/debug/app-debug.apk
```

### 8-4: 動作確認

端末で決済を行い、Admin ダッシュボードの**決済一覧**に記録が表示されることを確認します。

| 確認項目 | 期待値 |
|---------|--------|
| 決済作成 | QR表示後、Admin の payments テーブルに `status=pending` で記録される |
| 決済確認 | ブロックチェーン確認後、`status=confirmed` に更新される |
| /api/config | `Authorization: Bearer <api_token>` でアクセスでき、ウォレット情報が返る |

### API エンドポイント一覧

| メソッド | URL | 説明 |
|---------|-----|------|
| POST | `?page=api/payment/create` | 決済作成 |
| POST | `?page=api/payment/update` | ステータス更新 |
| GET  | `?page=api/payment/status&payment_code=PAY-...` | ステータス確認 |
| GET  | `?page=api/config` | 加盟店設定取得 |

**認証:** 全エンドポイントに `Authorization: Bearer <api_token>` ヘッダーが必要です。

---

## トラブルシューティング

### 500 Internal Server Error
- `admin/config/app.php` の `BASE_DIR` パスを確認
- PHP エラーログを確認: `error_log` や XSERVER のエラーログ

### ログインできない
- `admins` テーブルに管理者レコードがあるか確認
- パスワードハッシュが正しいか（install.phpが正常完了したか）

### 為替レートが取得できない
- XSERVERの `allow_url_fopen` が有効か確認
- CoinGecko/Binance/Krakenへのアウトバウンド接続が許可されているか

### 画像が表示されない
- `admin/assets/img/logo.svg` がアップロードされているか確認
- パスのマッピング: `index.php?page=...` → `../assets/img/logo.svg`
