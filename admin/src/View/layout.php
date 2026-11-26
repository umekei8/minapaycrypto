<?php
/**
 * Shared admin layout (sidebar + topbar wrapper)
 *
 * Usage:
 *   ob_start();
 *   // ... page body HTML ...
 *   $pageBody = ob_get_clean();
 *   $pageTitle = 'ダッシュボード';
 *   $activePage = 'dashboard';
 *   require BASE_DIR . '/src/View/layout.php';
 */
declare(strict_types=1);

$pageTitle  = $pageTitle  ?? 'Admin';
$activePage = $activePage ?? '';
$adminName  = Auth::name();

$nav = [
    ['page'=>'dashboard',  'icon'=>'📊', 'label'=>'ダッシュボード'],
    ['page'=>'payments',   'icon'=>'💳', 'label'=>'決済管理'],
    ['page'=>'merchants',  'icon'=>'🏪', 'label'=>'加盟店管理'],
    ['page'=>'rates',      'icon'=>'📈', 'label'=>'為替レート'],
    ['page'=>'analytics',  'icon'=>'📉', 'label'=>'マーケティング分析'],
    ['page'=>'settings',   'icon'=>'⚙️', 'label'=>'設定'],
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> — MinaPay Crypto Admin</title>
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <img src="/assets/img/logo.svg"
         alt="MinaPay Crypto"
         onerror="this.style.display='none'; document.getElementById('sb-fallback').style.display='flex'">
    <div id="sb-fallback" class="logo-text-fallback" style="display:none">
      <span class="logo-mark">₿</span>
      <span>MinaPay<br><small style="font-weight:400;font-size:11px">Crypto Admin</small></span>
    </div>
  </div>

  <div class="nav-section-label">メインメニュー</div>
  <ul class="nav-list">
    <?php foreach ($nav as $item): ?>
    <li class="nav-item <?= $activePage === $item['page'] ? 'active' : '' ?>">
      <a href="index.php?page=<?= $item['page'] ?>">
        <span class="nav-icon"><?= $item['icon'] ?></span>
        <?= $item['label'] ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>

  <div class="sidebar-footer">
    <span class="admin-name">👤 <?= htmlspecialchars($adminName) ?></span>
    <a href="index.php?page=logout" class="logout-link">ログアウト →</a>
  </div>
</nav>

<!-- Main area -->
<div class="main-wrap">
  <header class="topbar">
    <button class="menu-toggle" onclick="toggleSidebar()" aria-label="メニュー">☰</button>
    <div class="topbar-logo">
      <img src="/assets/img/logo.svg" alt="MinaPay Crypto">
    </div>
    <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
    <span style="font-size:12px;color:var(--text-muted)">
      <?= date('Y/m/d H:i') ?>
    </span>
  </header>

  <main class="content">
    <!-- Toast container -->
    <div id="toast-container"></div>

    <?php if (isset($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?>">
      <?= htmlspecialchars($_SESSION['flash']['message']) ?>
    </div>
    <?php unset($_SESSION['flash']); endif; ?>

    <?= $pageBody ?? '' ?>
  </main>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
document.addEventListener('click', e => {
  const sb = document.getElementById('sidebar');
  if (sb.classList.contains('open') && !sb.contains(e.target) && !e.target.closest('.menu-toggle')) {
    sb.classList.remove('open');
  }
});

// Tab switching
function switchTab(group, name) {
  document.querySelectorAll(`[data-tab-group="${group}"]`).forEach(el => {
    el.classList.toggle('active', el.dataset.tab === name);
  });
  document.querySelectorAll(`[data-tabpane-group="${group}"]`).forEach(el => {
    el.classList.toggle('active', el.dataset.pane === name);
  });
}

// Toast
function showToast(message, type = 'success') {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.textContent = message;
  c.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}

// CSRF helper
const CSRF = document.querySelector('meta[name=csrf]')?.content ?? '';

// Generic fetch helper
async function postJson(url, data) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({...data, _csrf: CSRF}),
  });
  return res.json();
}
</script>
<meta name="csrf" content="<?= Auth::csrfToken() ?>">
</body>
</html>
