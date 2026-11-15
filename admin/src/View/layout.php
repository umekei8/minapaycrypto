<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'MinaPay Crypto Admin') ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <span class="logo-mark">₿</span>
    <span class="logo-text">MinaPay<br><small>Crypto Admin</small></span>
  </div>
  <ul class="nav-list">
    <?php
    $page = $_GET['page'] ?? 'dashboard';
    $navItems = [
      'dashboard' => ['icon' => '📊', 'label' => 'ダッシュボード'],
      'payments'  => ['icon' => '💳', 'label' => '決済履歴'],
      'merchants' => ['icon' => '🏪', 'label' => '加盟店管理'],
      'wallets'   => ['icon' => '👛', 'label' => 'ウォレット管理'],
      'devices'   => ['icon' => '📱', 'label' => 'POS端末'],
      'rates'     => ['icon' => '📈', 'label' => '為替レート'],
      'settings'  => ['icon' => '⚙️', 'label' => 'システム設定'],
    ];
    foreach ($navItems as $key => $item):
    ?>
    <li class="nav-item <?= $page === $key ? 'active' : '' ?>">
      <a href="index.php?page=<?= $key ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <span class="nav-label"><?= $item['label'] ?></span>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
  <div class="sidebar-footer">
    <span class="admin-name"><?= htmlspecialchars(Auth::name()) ?></span>
    <a href="index.php?page=logout" class="logout-link">ログアウト</a>
  </div>
</nav>

<!-- Main content -->
<div class="main-wrap">
  <header class="topbar">
    <button class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
    <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? '') ?></h1>
  </header>
  <main class="content">
    <?= $content ?? '' ?>
  </main>
</div>

<script src="/assets/js/app.js"></script>
</body>
</html>
