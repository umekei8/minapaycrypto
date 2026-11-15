<?php
/**
 * MinaPay Crypto Admin — インストーラー
 * XSERVER対応: PHP 8.x + MySQL
 * アクセス: https://admin.minapay-crypto.com/install/install.php
 */
declare(strict_types=1);

define('INSTALL_VERSION', '1.0.0');
define('CONFIG_FILE', __DIR__ . '/../config/database.php');
define('LOCK_FILE',   __DIR__ . '/installed.lock');

// すでにインストール済みなら拒否
if (file_exists(LOCK_FILE)) {
    die('<h2>インストール済みです</h2><p>セキュリティのためこのファイルを削除するか、installed.lock を確認してください。</p>');
}

$error   = '';
$success = '';
$step    = (int)($_POST['step'] ?? 1);

// ─── Step 2: 設定保存 & DB初期化 ───────────────────────────────────────
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host  = trim($_POST['db_host']  ?? 'localhost');
    $db_name  = trim($_POST['db_name']  ?? '');
    $db_user  = trim($_POST['db_user']  ?? '');
    $db_pass  = $_POST['db_pass']  ?? '';
    $db_prefix = trim($_POST['db_prefix'] ?? '');

    $admin_name  = trim($_POST['admin_name']  ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_pass  = $_POST['admin_pass'] ?? '';
    $app_url     = rtrim(trim($_POST['app_url'] ?? ''), '/');

    if (!$db_name || !$db_user || !$admin_email || !$admin_pass) {
        $error = '必須項目をすべて入力してください。';
        $step  = 1;
    } elseif (strlen($admin_pass) < 8) {
        $error = '管理者パスワードは8文字以上にしてください。';
        $step  = 1;
    } else {
        try {
            $pdo = new PDO(
                "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
                $db_user, $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // スキーマ実行
            $sql = file_get_contents(__DIR__ . '/schema.sql');
            foreach (explode(';', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt !== '') {
                    $pdo->exec($stmt);
                }
            }

            // 管理者作成
            $hash = password_hash($admin_pass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("INSERT INTO admins (name,email,password,role) VALUES (?,?,?,'super') ON DUPLICATE KEY UPDATE password=VALUES(password)")
                ->execute([$admin_name ?: 'Admin', $admin_email, $hash]);

            // app_url 設定
            $pdo->prepare("INSERT INTO settings (`key`,`value`) VALUES ('app_url',?) ON DUPLICATE KEY UPDATE value=VALUES(value)")
                ->execute([$app_url]);

            // config ファイル生成
            $config = "<?php\nreturn [\n"
                . "    'host'     => " . var_export($db_host, true) . ",\n"
                . "    'dbname'   => " . var_export($db_name, true) . ",\n"
                . "    'username' => " . var_export($db_user, true) . ",\n"
                . "    'password' => " . var_export($db_pass, true) . ",\n"
                . "    'charset'  => 'utf8mb4',\n"
                . "];\n";

            if (!is_dir(dirname(CONFIG_FILE))) {
                mkdir(dirname(CONFIG_FILE), 0755, true);
            }
            file_put_contents(CONFIG_FILE, $config, LOCK_EX);

            // ロックファイル作成
            file_put_contents(LOCK_FILE, date('Y-m-d H:i:s') . "\n", LOCK_EX);

            $success = 'インストール完了！';
            $step    = 3;

        } catch (PDOException $e) {
            $error = 'データベースエラー: ' . htmlspecialchars($e->getMessage());
            $step  = 1;
        } catch (Throwable $e) {
            $error = 'エラー: ' . htmlspecialchars($e->getMessage());
            $step  = 1;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MinaPay Crypto — インストール</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f4f5f7;color:#1f2933;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.wrap{background:#fff;border-radius:10px;box-shadow:0 4px 24px rgba(0,0,0,.12);width:min(520px,100%);overflow:hidden}
.head{background:#c0392b;padding:28px 32px;color:#fff}
.head h1{font-size:1.4rem;font-weight:700;margin-bottom:4px}
.head p{font-size:.85rem;opacity:.85}
.body{padding:28px 32px}
.steps{display:flex;gap:0;margin-bottom:28px}
.step{flex:1;text-align:center;padding:8px 4px;font-size:.72rem;font-weight:700;color:#9aa5b1;border-bottom:2px solid #e2e6ea;position:relative}
.step.done{color:#1a7f4e;border-color:#1a7f4e}
.step.active{color:#c0392b;border-color:#c0392b}
.fr{margin-bottom:16px}
label{display:block;font-size:.78rem;font-weight:600;color:#3d4d60;margin-bottom:5px}
input{width:100%;padding:9px 11px;border:1px solid #ced4da;border-radius:6px;font-size:.85rem;outline:none;transition:.15s}
input:focus{border-color:#c0392b;box-shadow:0 0 0 3px rgba(192,57,43,.12)}
.hint{font-size:.7rem;color:#9aa5b1;margin-top:3px}
.sep{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#9aa5b1;margin:20px 0 14px;padding-bottom:5px;border-bottom:1px solid #e2e6ea}
.btn{width:100%;padding:11px;background:#c0392b;color:#fff;border:none;border-radius:6px;font-size:.88rem;font-weight:700;cursor:pointer;margin-top:8px;transition:.15s}
.btn:hover{background:#e74c3c}
.err{background:#fdf2f2;border:1px solid #f5b7b1;border-radius:6px;padding:10px 14px;font-size:.8rem;color:#a93226;margin-bottom:16px}
.ok{background:#f0faf5;border:1px solid #b7e4cc;border-radius:6px;padding:14px 18px;font-size:.88rem;color:#1a7f4e;text-align:center}
.ok a{display:inline-block;margin-top:12px;padding:10px 24px;background:#c0392b;color:#fff;border-radius:6px;text-decoration:none;font-weight:700}
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <h1>MinaPay Crypto Admin</h1>
    <p>インストールウィザード v<?= INSTALL_VERSION ?></p>
  </div>
  <div class="body">
    <div class="steps">
      <div class="step <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">① DB設定</div>
      <div class="step <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">② インストール</div>
      <div class="step <?= $step >= 3 ? 'done' : '' ?>">③ 完了</div>
    </div>

    <?php if ($error): ?>
      <div class="err"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($step === 3): ?>
      <div class="ok">
        <strong>✓ インストール完了</strong><br>
        管理画面にログインしてください。<br>
        <small style="color:#616e7c;font-size:.75rem">セキュリティのため <code>install/</code> ディレクトリへのアクセスを制限してください。</small>
        <br><a href="../public/index.php">管理画面へ →</a>
      </div>

    <?php else: ?>
      <form method="POST">
        <input type="hidden" name="step" value="2">

        <div class="sep">データベース設定 (XSERVER)</div>
        <div class="fr">
          <label>DBホスト *</label>
          <input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" placeholder="localhost">
          <div class="hint">XSERVERは通常 localhost</div>
        </div>
        <div class="fr">
          <label>DB名 *</label>
          <input name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" placeholder="xs123456_minapay">
        </div>
        <div class="fr">
          <label>DBユーザー名 *</label>
          <input name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" placeholder="xs123456_minapay">
        </div>
        <div class="fr">
          <label>DBパスワード *</label>
          <input type="password" name="db_pass" placeholder="••••••••••••">
        </div>

        <div class="sep">管理者アカウント</div>
        <div class="fr">
          <label>管理者名</label>
          <input name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'Admin') ?>">
        </div>
        <div class="fr">
          <label>メールアドレス *</label>
          <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>" placeholder="admin@minapay-crypto.com">
        </div>
        <div class="fr">
          <label>パスワード（8文字以上）*</label>
          <input type="password" name="admin_pass" placeholder="••••••••">
        </div>

        <div class="sep">アプリ設定</div>
        <div class="fr">
          <label>サービスURL</label>
          <input name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? 'https://minapay-crypto.com') ?>">
        </div>

        <button class="btn" type="submit">インストール実行</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
