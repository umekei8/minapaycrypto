<?php
declare(strict_types=1);

class DeviceController
{
    private DeviceModel $model;

    public function __construct()
    {
        $this->model = new DeviceModel();
    }

    public function index(): void
    {
        Auth::require();
        $devices   = $this->model->allWithMerchant();
        $merchants = (new MerchantModel())->all();
        require BASE_DIR . '/src/View/devices.php';
    }

    public function create(): void
    {
        Auth::require();
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) { http_response_code(403); exit; }

        $id = $this->model->create([
            'merchant_id' => (int)($_POST['merchant_id'] ?? 0),
            'name'        => trim($_POST['name'] ?? ''),
            'model'       => trim($_POST['model'] ?? 'ZCS Z108'),
            'location'    => trim($_POST['location'] ?? ''),
        ]);
        $device = $this->model->find($id);
        $this->jsonOk(['device' => $device, 'message' => '端末を登録しました。']);
    }

    public function regenerateToken(): void
    {
        Auth::require();
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) { http_response_code(403); exit; }

        $id    = (int)($_POST['id'] ?? 0);
        $token = $this->model->regenerateToken($id);
        $this->jsonOk(['token' => $token, 'message' => 'APIトークンを再発行しました。']);
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
