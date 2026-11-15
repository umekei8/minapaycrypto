<?php
declare(strict_types=1);

class MerchantController
{
    private MerchantModel $model;

    public function __construct()
    {
        $this->model = new MerchantModel();
    }

    public function index(): void
    {
        Auth::require();
        $merchants = $this->model->all();
        require BASE_DIR . '/src/View/merchants.php';
    }

    public function create(): void
    {
        Auth::require();
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) { http_response_code(403); exit; }

        $data = [
            'name'        => trim($_POST['name'] ?? ''),
            'email'       => trim($_POST['email'] ?? ''),
            'phone'       => trim($_POST['phone'] ?? ''),
            'slug'        => trim($_POST['slug'] ?? ''),
            'fee_percent' => (float)($_POST['fee_percent'] ?? 6.50),
            'memo'        => trim($_POST['memo'] ?? ''),
        ];

        if (!$data['name'] || !$data['email'] || !$data['slug']) {
            $this->jsonError('必須項目を入力してください。');
            return;
        }

        $id = $this->model->create($data);
        $this->jsonOk(['id' => $id, 'message' => '加盟店を登録しました。']);
    }

    public function update(): void
    {
        Auth::require();
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) { http_response_code(403); exit; }

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { $this->jsonError('IDが不正です。'); return; }

        $this->model->update($id, [
            'name'        => trim($_POST['name'] ?? ''),
            'email'       => trim($_POST['email'] ?? ''),
            'phone'       => trim($_POST['phone'] ?? ''),
            'fee_percent' => (float)($_POST['fee_percent'] ?? 6.50),
            'status'      => $_POST['status'] ?? 'active',
            'memo'        => trim($_POST['memo'] ?? ''),
        ]);
        $this->jsonOk(['message' => '加盟店情報を更新しました。']);
    }

    public function stats(): void
    {
        Auth::require();
        $id    = (int)($_GET['id'] ?? 0);
        $stats = $this->model->stats($id);
        $devCnt = $this->model->deviceCount($id);
        header('Content-Type: application/json');
        echo json_encode(['stats' => $stats, 'device_count' => $devCnt]);
    }

    // ─── helpers ────────────────────────────────────────────────────────────

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
