<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ログイン — MinaPay Crypto Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="login-body">

<div class="login-card">
  <div class="login-logo">
    <img src="/assets/img/logo.svg"
         alt="MinaPay Crypto"
         onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='block'">
    <div id="logo-fallback" style="display:none">
      <span class="logo-mark-lg">₿</span>
      <h1>MinaPay Crypto</h1>
      <p>Admin Panel</p>
    </div>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post" action="index.php?page=login" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">

    <div class="form-group">
      <label for="email">メールアドレス</label>
      <input type="email" id="email" name="email" required autofocus
             placeholder="admin@example.com"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>

    <div class="form-group">
      <label for="password">パスワード</label>
      <input type="password" id="password" name="password" required placeholder="••••••••">
    </div>

    <button type="submit" class="btn btn-primary btn-block" style="margin-top:20px;padding:10px;">
      ログイン
    </button>
  </form>

  <p style="text-align:center;margin-top:18px;font-size:11px;color:var(--text-muted)">
    MinaPay Crypto Admin v2.0
  </p>
</div>

</body>
</html>
