<?php
declare(strict_types=1);

class WalletController
{
    private WalletModel $model;

    public function __construct()
    {
        $this->model = new WalletModel();
    }

    public function index(): void
    {
        Auth::require();
        $wallets   = $this->model->allWithMerchant();
        $merchants = (new MerchantModel())->all();
        require BASE_DIR . '/src/View/wallets.php';
    }

    public function save(): void
    {
        Auth::require();
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) { http_response_code(403); exit; }

        $address = trim($_POST['address'] ?? '');
        if (!$address) { $this->jsonError('アドレスを入力してください。'); return; }

        $this->model->save([
            'merchant_id' => (int)($_POST['merchant_id'] ?? 0),
            'currency'    => $_POST['currency'] ?? '',
            'address'     => $address,
            'label'       => trim($_POST['label'] ?? ''),
            'status'      => $_POST['status'] ?? 'active',
        ]);
        $this->jsonOk(['message' => 'ウォレットアドレスを保存しました。']);
    }

    private function jsonOk(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => true] + $data);
    }

    private function jsonError(string $msg): void
    {
        header('Content-Type: application/json');
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $msg]);
    }
}
