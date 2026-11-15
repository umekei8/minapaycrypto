<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ログイン — MinaPay Crypto Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-body">
<div class="login-card">
  <div class="login-logo">
    <span class="logo-mark-lg">₿</span>
    <h1>MinaPay Crypto</h1>
    <p>管理画面</p>
  </div>

  <?php if (!empty($error)): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" action="index.php?page=login" class="login-form">
    <?= Auth::csrfInput() ?>
    <div class="form-group">
      <label for="email">メールアドレス</label>
      <input type="email" id="email" name="email" required autofocus
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="password">パスワード</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">ログイン</button>
  </form>
</div>
</body>
</html>
