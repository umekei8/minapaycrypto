# MinaPay Crypto — XSERVER インストール手順

SSH 不要。FTP + ブラウザだけで XSERVER にデプロイできます。

## ZIPのダウンロード方法

### 方法A: GitHub Actions から
1. GitHub リポジトリ → **Actions** タブを開く
2. **Build Web ZIP** ワークフローを選択
3. 最新の実行結果をクリック
4. **Artifacts** セクションから `minapaycrypto-web` をダウンロード
5. ダウンロードした ZIP を解凍すると `minapaycrypto-web.zip` が入っています

### 方法B: このリポジトリ（Claude アーティファクト）から
Claude のアーティファクトページのダウンロードボタンから取得できます。

---

## XSERVER へのインストール手順

### Step 1 — install.php をアップロード
FTP クライアント（FileZilla など）で以下を接続先に接続:

| 項目 | 値 |
|------|-----|
| ホスト | sv〇〇〇〇.xserver.jp |
| ユーザー名 | XSERVER アカウント ID |
| ポート | 21 |

`web/installer/install.php` を XSERVER の公開ディレクトリ（`public_html` または `yourdomain.com/public_html`）にアップロード。

### Step 2 — ブラウザでインストーラーを開く

```
https://yourdomain.com/install.php
```

### Step 3 — ZIP をアップロード

画面に `minapaycrypto-web.zip` をドラッグ＆ドロップ（またはクリックして選択）→「展開してインストール」ボタンを押す。

### Step 4 — install.php を削除（必須）

インストール完了後、セキュリティのため FTP で `install.php` を削除してください。

---

## 注意事項

- `install.php` はインストール後に **必ず削除**してください
- 展開済みの場合は `.installer_done` ファイルが存在し、再実行はブロックされます
- ZIP の中身は `web/public/` のファイル群（index.html, assets/ など）です
- XSERVER の PHP バージョンを 7.4 以上に設定してください（管理パネル → PHP バージョン切替）
