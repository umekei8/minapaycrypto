<?php
declare(strict_types=1);

// Bootstrap (BASE_DIR is defined inside app.php)
require dirname(__DIR__) . '/config/app.php';

// Start session
Auth::start();

// Route
$page = $_GET['page'] ?? 'dashboard';

// Public routes (no auth required)
if ($page === 'login') {
    $c = new AuthController();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $c->loginPost();
    } else {
        $c->loginPage();
    }
    exit;
}

if ($page === 'logout') {
    (new AuthController())->logout();
    exit;
}

// Protected routes
Auth::require();

switch ($page) {
    // Dashboard
    case 'dashboard':
        (new DashboardController())->index();
        break;

    // Payments
    case 'payments':
        (new PaymentController())->index();
        break;
    case 'payment-detail':
        (new PaymentController())->detail();
        break;

    // Merchants
    case 'merchants':
        (new MerchantController())->index();
        break;
    case 'merchant-create':
        (new MerchantController())->create();
        break;
    case 'merchant-update':
        (new MerchantController())->update();
        break;
    case 'merchant-stats':
        (new MerchantController())->stats();
        break;

    // Wallets
    case 'wallets':
        (new WalletController())->index();
        break;
    case 'wallet-save':
        (new WalletController())->save();
        break;

    // Devices
    case 'devices':
        (new DeviceController())->index();
        break;
    case 'device-create':
        (new DeviceController())->create();
        break;
    case 'device-regen':
        (new DeviceController())->regenerateToken();
        break;

    // Rates & Settings
    case 'rates':
        (new SettingsController())->rates();
        break;
    case 'settings':
        (new SettingsController())->index();
        break;
    case 'settings-save':
        (new SettingsController())->save();
        break;
    case 'settings-pw':
        // Password change handler
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) { http_response_code(403); exit; }
        $db = Database::getInstance();
        $st = $db->prepare('SELECT password_hash FROM admins WHERE id=?');
        $st->execute([Auth::id()]);
        $admin = $st->fetch();
        if (!$admin || !password_verify($_POST['current_password'] ?? '', $admin['password_hash'])) {
            http_response_code(422);
            echo json_encode(['success'=>false,'message'=>'現在のパスワードが正しくありません。']);
            exit;
        }
        $new = $_POST['new_password'] ?? '';
        if (strlen($new) < 8) {
            http_response_code(422);
            echo json_encode(['success'=>false,'message'=>'パスワードは8文字以上必要です。']);
            exit;
        }
        $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
        $db->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([$hash, Auth::id()]);
        header('Content-Type: application/json');
        echo json_encode(['success'=>true,'message'=>'パスワードを変更しました。']);
        break;

    default:
        http_response_code(404);
        echo '<p>ページが見つかりません。</p>';
        break;
}
