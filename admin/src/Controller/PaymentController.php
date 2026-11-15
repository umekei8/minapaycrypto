<?php
declare(strict_types=1);

class PaymentController
{
    private PaymentModel $model;

    public function __construct()
    {
        $this->model = new PaymentModel();
    }

    public function index(): void
    {
        Auth::require();
        $filters = [
            'q'           => trim($_GET['q'] ?? ''),
            'status'      => $_GET['status'] ?? '',
            'currency'    => $_GET['currency'] ?? '',
            'merchant_id' => (int)($_GET['merchant_id'] ?? 0) ?: null,
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
        ];
        $page     = max(1, (int)($_GET['p'] ?? 1));
        $limit    = 50;
        $offset   = ($page - 1) * $limit;
        $total    = $this->model->count($filters);
        $payments = $this->model->list($filters, $limit, $offset);
        $totalPages = (int)ceil($total / $limit);

        $merchants = (new MerchantModel())->all();

        require BASE_DIR . '/src/View/payments.php';
    }

    public function detail(): void
    {
        Auth::require();
        header('Content-Type: application/json');
        $id      = (int)($_GET['id'] ?? 0);
        $payment = $this->model->find($id);
        echo json_encode($payment ?: ['error' => 'Not found']);
    }
}
