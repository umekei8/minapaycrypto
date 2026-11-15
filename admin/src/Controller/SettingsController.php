<?php
declare(strict_types=1);

class SettingsController
{
    private SettingsModel $model;

    public function __construct()
    {
        $this->model = new SettingsModel();
    }

    public function index(): void
    {
        Auth::require();
        $settings = $this->model->all();
        require BASE_DIR . '/src/View/settings.php';
    }

    public function save(): void
    {
        Auth::require();
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) { http_response_code(403); exit; }

        $allowed = [
            'site_name', 'default_fee_percent', 'rate_markup_percent',
            'payment_timeout_minutes', 'notify_email', 'supported_currencies',
        ];
        $kv = [];
        foreach ($allowed as $k) {
            if (isset($_POST[$k])) {
                $kv[$k] = trim($_POST[$k]);
            }
        }
        $this->model->batchSet($kv);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => '設定を保存しました。']);
    }

    public function rates(): void
    {
        Auth::require();
        $settings = $this->model->all();
        require BASE_DIR . '/src/View/rates.php';
    }
}
