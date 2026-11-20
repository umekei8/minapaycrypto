<?php
/**
 * MinaPay Crypto — XSERVER Web Installer
 * ----------------------------------------
 * このファイルを XSERVER の公開ディレクトリにアップロードして
 * ブラウザから開き、ZIP をアップロードするだけでサイトを展開できます。
 *
 * 使い方:
 *   1. このファイル (install.php) を XSERVER の公開ディレクトリへ FTP でアップロード
 *   2. ブラウザで https://yourdomain.com/install.php を開く
 *   3. GitHub Actions でダウンロードした minapaycrypto-web.zip をアップロード
 *   4. 展開完了後、このファイルを FTP で削除してセキュリティを確保
 */

define('INSTALLER_VERSION', '1.0.0');
define('INSTALL_DIR', dirname(__FILE__));

// --- セキュリティ: 完了フラグ ---
$lock_file = INSTALL_DIR . '/.installer_done';
$already_installed = file_exists($lock_file);

// --- POST 処理 ---
$message = '';
$message_type = '';
$install_success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_installed) {
    if (isset($_FILES['deploy_zip']) && $_FILES['deploy_zip']['error'] === UPLOAD_ERR_OK) {
        $tmp   = $_FILES['deploy_zip']['tmp_name'];
        $name  = $_FILES['deploy_zip']['name'];
        $size  = $_FILES['deploy_zip']['size'];

        // バリデーション
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== 'zip') {
            $message = 'ZIPファイルのみアップロード可能です。';
            $message_type = 'error';
        } elseif ($size > 50 * 1024 * 1024) {
            $message = 'ファイルサイズが大きすぎます（上限 50MB）。';
            $message_type = 'error';
        } else {
            $zip = new ZipArchive();
            if ($zip->open($tmp) === true) {
                $extracted = 0;
                $skipped   = 0;
                $errors    = [];

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->getNameIndex($i);

                    // パストラバーサル防止
                    if (strpos($entry, '..') !== false || strpos($entry, '//') !== false) {
                        $skipped++;
                        continue;
                    }

                    $dest = INSTALL_DIR . '/' . $entry;

                    if (substr($entry, -1) === '/') {
                        // ディレクトリ
                        if (!is_dir($dest)) {
                            mkdir($dest, 0755, true);
                        }
                    } else {
                        // ファイル: install.php 自体は上書きしない
                        if (basename($dest) === 'install.php') {
                            $skipped++;
                            continue;
                        }
                        $dir = dirname($dest);
                        if (!is_dir($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        if (file_put_contents($dest, $zip->getFromIndex($i)) === false) {
                            $errors[] = $entry;
                        } else {
                            $extracted++;
                        }
                    }
                }
                $zip->close();

                if (empty($errors)) {
                    // 完了フラグを作成（再実行防止）
                    file_put_contents($lock_file, date('Y-m-d H:i:s'));
                    $install_success = true;
                    $message = "{$extracted} ファイルを展開しました。インストール完了です！";
                    $message_type = 'success';
                } else {
                    $message = "{$extracted} ファイル展開済み。エラー: " . implode(', ', array_slice($errors, 0, 5));
                    $message_type = 'warning';
                }

                if ($skipped > 0) {
                    $message .= "（{$skipped} ファイルはスキップ）";
                }
            } else {
                $message = 'ZIPファイルを開けませんでした。ファイルが壊れている可能性があります。';
                $message_type = 'error';
            }
        }
    } else {
        $err_code = $_FILES['deploy_zip']['error'] ?? -1;
        if ($err_code === UPLOAD_ERR_INI_SIZE || $err_code === UPLOAD_ERR_FORM_SIZE) {
            $message = 'ファイルサイズがサーバーの上限を超えています。php.ini の upload_max_filesize を確認してください。';
        } else {
            $message = 'ファイルのアップロードに失敗しました。（エラーコード: ' . $err_code . '）';
        }
        $message_type = 'error';
    }
}

// --- PHP 設定チェック ---
$checks = [
    'PHP バージョン ≥ 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'ZipArchive 拡張'      => class_exists('ZipArchive'),
    'ディレクトリ書き込み権限' => is_writable(INSTALL_DIR),
    'ファイルアップロード有効'  => ini_get('file_uploads'),
];
$upload_max = ini_get('upload_max_filesize');
$post_max   = ini_get('post_max_size');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MinaPay Crypto — Webインストーラー</title>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  :root{
    --primary:#c0392b;--primary-dark:#96281b;
    --bg:#f8f8fa;--card:#fff;
    --text:#1a1a2e;--muted:#6b7280;
    --border:#e5e7eb;--radius:12px;
    --success:#065f46;--success-bg:#d1fae5;--success-border:#a7f3d0;
    --error:#991b1b;--error-bg:#fee2e2;--error-border:#fca5a5;
    --warning:#92400e;--warning-bg:#fef3c7;--warning-border:#fcd34d;
  }
  body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:40px 16px}
  .logo{display:flex;align-items:center;gap:10px;margin-bottom:32px}
  .logo-mark{width:40px;height:40px;background:var(--primary);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;font-weight:800}
  .logo-name{font-size:22px;font-weight:800;color:var(--text)}
  .logo-name span{color:var(--primary)}
  .card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:36px 40px;width:100%;max-width:560px;box-shadow:0 4px 24px rgba(0,0,0,.07)}
  h1{font-size:20px;font-weight:700;margin-bottom:6px}
  .subtitle{color:var(--muted);font-size:14px;margin-bottom:28px;line-height:1.6}
  /* Checks */
  .checks{margin-bottom:24px;border:1px solid var(--border);border-radius:8px;overflow:hidden}
  .check-row{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;font-size:14px;border-bottom:1px solid var(--border)}
  .check-row:last-child{border-bottom:none}
  .check-label{color:var(--muted)}
  .check-ok{color:#059669;font-weight:600}
  .check-ng{color:#dc2626;font-weight:600}
  .check-info{color:var(--primary);font-weight:600}
  /* Upload */
  .upload-area{border:2px dashed var(--border);border-radius:8px;padding:32px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:16px;position:relative}
  .upload-area:hover,.upload-area.drag{border-color:var(--primary);background:rgba(192,57,43,.04)}
  .upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
  .upload-icon{font-size:36px;margin-bottom:8px}
  .upload-title{font-size:15px;font-weight:600;margin-bottom:4px}
  .upload-hint{font-size:13px;color:var(--muted)}
  .file-name{font-size:13px;color:var(--primary);margin-top:8px;font-weight:500}
  /* Button */
  .btn{display:block;width:100%;padding:14px;background:var(--primary);color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:700;cursor:pointer;transition:background .2s}
  .btn:hover{background:var(--primary-dark)}
  .btn:disabled{opacity:.5;cursor:default}
  /* Messages */
  .msg{padding:14px 16px;border-radius:8px;font-size:14px;font-weight:500;margin-bottom:20px;line-height:1.6}
  .msg.success{background:var(--success-bg);color:var(--success);border:1px solid var(--success-border)}
  .msg.error{background:var(--error-bg);color:var(--error);border:1px solid var(--error-border)}
  .msg.warning{background:var(--warning-bg);color:var(--warning);border:1px solid var(--warning-border)}
  /* Steps */
  .steps{margin-top:28px;border-top:1px solid var(--border);padding-top:24px}
  .steps h3{font-size:14px;font-weight:700;color:var(--muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:16px}
  .step{display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;font-size:14px;line-height:1.6}
  .step-num{flex-shrink:0;width:22px;height:22px;border-radius:50%;background:var(--primary);color:#fff;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;margin-top:1px}
  .step-done .step-num{background:#059669}
  code{background:#f3f4f6;padding:1px 5px;border-radius:4px;font-size:12px;font-family:monospace}
  .warning-box{background:var(--warning-bg);border:1px solid var(--warning-border);border-radius:8px;padding:12px 14px;font-size:13px;color:var(--warning);margin-top:16px;line-height:1.7}
  .locked{text-align:center;padding:16px 0}
  .locked-icon{font-size:48px;margin-bottom:12px}
  .progress{height:4px;background:var(--border);border-radius:2px;margin-top:12px;overflow:hidden;display:none}
  .progress-bar{height:100%;background:var(--primary);border-radius:2px;width:0;transition:width 1s linear}
</style>
</head>
<body>

<div class="logo">
  <div class="logo-mark">₿</div>
  <div class="logo-name">MinaPay <span>Crypto</span></div>
</div>

<div class="card">
  <h1>🚀 Webインストーラー</h1>
  <p class="subtitle">GitHub Actions でビルドした <code>minapaycrypto-web.zip</code> をアップロードするだけで、XSERVER に自動展開します。</p>

  <?php if ($already_installed && !$install_success): ?>
  <!-- インストール済み -->
  <div class="locked">
    <div class="locked-icon">✅</div>
    <div class="msg success" style="text-align:left">インストール済みです。このファイルを FTP で削除してセキュリティを確保してください。</div>
  </div>

  <?php else: ?>

  <?php if ($message): ?>
  <div class="msg <?= htmlspecialchars($message_type) ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if ($install_success): ?>
  <div style="text-align:center;padding:8px 0 16px">
    <div style="font-size:48px;margin-bottom:8px">🎉</div>
    <p style="font-weight:700;font-size:16px;margin-bottom:4px">インストール完了！</p>
    <p style="color:var(--muted);font-size:14px">サイトが正常に展開されました。</p>
    <p style="color:var(--error);font-size:13px;margin-top:12px;font-weight:600">⚠️ セキュリティのため、このファイル（install.php）を FTP で削除してください。</p>
  </div>

  <?php else: ?>

  <!-- 環境チェック -->
  <div class="checks">
    <?php foreach ($checks as $label => $ok): ?>
    <div class="check-row">
      <span class="check-label"><?= htmlspecialchars($label) ?></span>
      <span class="<?= $ok ? 'check-ok' : 'check-ng' ?>"><?= $ok ? '✓ OK' : '✗ NG' ?></span>
    </div>
    <?php endforeach; ?>
    <div class="check-row">
      <span class="check-label">アップロード上限</span>
      <span class="check-info"><?= htmlspecialchars($upload_max) ?> / POST: <?= htmlspecialchars($post_max) ?></span>
    </div>
  </div>

  <?php
  $all_ok = !in_array(false, array_values($checks), true);
  if ($all_ok):
  ?>
  <!-- アップロードフォーム -->
  <form method="post" enctype="multipart/form-data" id="installForm">
    <div class="upload-area" id="dropZone">
      <input type="file" name="deploy_zip" id="zipFile" accept=".zip" required>
      <div class="upload-icon">📦</div>
      <div class="upload-title">クリックまたはドロップ</div>
      <div class="upload-hint">minapaycrypto-web.zip を選択してください</div>
      <div class="file-name" id="fileName"></div>
    </div>
    <div class="progress" id="progressWrap">
      <div class="progress-bar" id="progressBar"></div>
    </div>
    <button type="submit" class="btn" id="submitBtn">展開してインストール</button>
  </form>
  <?php else: ?>
  <div class="msg error">環境チェックに失敗した項目があります。サーバー設定を確認してください。</div>
  <?php endif; ?>

  <?php endif; // install_success ?>

  <?php endif; // already_installed ?>

  <!-- 手順ガイド -->
  <div class="steps">
    <h3>導入手順</h3>
    <div class="step <?= $already_installed || $install_success ? 'step-done' : '' ?>">
      <div class="step-num">1</div>
      <div>この <code>install.php</code> を FTP で XSERVER の公開ディレクトリ（<code>public_html</code> など）にアップロード</div>
    </div>
    <div class="step <?= $already_installed || $install_success ? 'step-done' : '' ?>">
      <div class="step-num">2</div>
      <div>GitHub Actions の <strong>Build Web ZIP</strong> からアーティファクト（<code>minapaycrypto-web.zip</code>）をダウンロード</div>
    </div>
    <div class="step <?= $already_installed || $install_success ? 'step-done' : '' ?>">
      <div class="step-num">3</div>
      <div>ブラウザで <code>https://yourdomain.com/install.php</code> を開き、ZIP をアップロード</div>
    </div>
    <div class="step <?= $install_success ? 'step-done' : '' ?>">
      <div class="step-num">4</div>
      <div>インストール完了後、<code>install.php</code> を FTP で削除</div>
    </div>
  </div>

  <div class="warning-box">
    ⚠️ <strong>セキュリティ注意：</strong> インストール完了後は必ずこのファイルを削除してください。放置すると第三者に任意のファイルを展開される危険があります。
  </div>
</div><!-- .card -->

<script>
const fileInput = document.getElementById('zipFile');
const fileName  = document.getElementById('fileName');
const submitBtn = document.getElementById('submitBtn');
const progressWrap = document.getElementById('progressWrap');
const progressBar  = document.getElementById('progressBar');
const dropZone  = document.getElementById('dropZone');

if (fileInput) {
  fileInput.addEventListener('change', () => {
    const f = fileInput.files[0];
    if (f) {
      fileName.textContent = f.name + ' (' + (f.size / 1024).toFixed(0) + ' KB)';
    }
  });
}

if (dropZone) {
  ['dragenter','dragover'].forEach(e => {
    dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.add('drag'); });
  });
  ['dragleave','drop'].forEach(e => {
    dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.remove('drag'); });
  });
}

const form = document.getElementById('installForm');
if (form) {
  form.addEventListener('submit', () => {
    submitBtn.disabled = true;
    submitBtn.textContent = '展開中...';
    progressWrap.style.display = 'block';
    let w = 0;
    const timer = setInterval(() => {
      w = Math.min(w + Math.random() * 8, 90);
      progressBar.style.width = w + '%';
    }, 400);
  });
}
</script>
</body>
</html>
