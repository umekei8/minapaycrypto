<?php
declare(strict_types=1);

class PaymentController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $q          = trim($_GET['q'] ?? '');
        $status     = $_GET['status'] ?? '';
        $currency   = $_GET['currency'] ?? '';
        $merchantId = (int)($_GET['merchant_id'] ?? 0);
        $dateFrom   = $_GET['date_from'] ?? '';
        $dateTo     = $_GET['date_to'] ?? '';
        $page       = max(1, (int)($_GET['p'] ?? 1));
        $limit      = 50;
        $offset     = ($page - 1) * $limit;

        [$where, $params] = $this->buildWhere($q, $status, $currency, $merchantId, $dateFrom, $dateTo);
        $whereStr = implode(' AND ', $where);

        $total = (int)$this->db->prepare("SELECT COUNT(*) FROM payments p WHERE {$whereStr}")
            ->execute($params) ? $this->countQuery($whereStr, $params) : 0;

        $stP = $this->db->prepare(
            "SELECT p.*, m.name AS merchant_name, m.code AS merchant_code
             FROM payments p
             LEFT JOIN merchants m ON m.id = p.merchant_id
             WHERE {$whereStr}
             ORDER BY p.created_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stP->execute($params);
        $payments = $stP->fetchAll();

        $totalPages = (int)ceil($total / $limit);

        // Merchant list for filter dropdown
        $merchants = $this->db->query(
            "SELECT id, name, code FROM merchants WHERE status='active' ORDER BY name"
        )->fetchAll();

        // CSV export
        if (isset($_GET['csv'])) {
            $this->exportCsv($payments);
            return;
        }

        ob_start();
        require BASE_DIR . '/src/View/payments.php';
        $pageBody   = ob_get_clean();
        $pageTitle  = '決済一覧';
        $activePage = 'payments';
        require BASE_DIR . '/src/View/layout.php';
    }

    public function detail(): void
    {
        header('Content-Type: application/json');
        $id = (int)($_GET['id'] ?? 0);
        $st = $this->db->prepare(
            "SELECT p.*, m.name AS merchant_name FROM payments p
             LEFT JOIN merchants m ON m.id = p.merchant_id
             WHERE p.id=?"
        );
        $st->execute([$id]);
        $payment = $st->fetch();
        echo json_encode($payment ?: ['error' => 'Not found']);
    }

    private function buildWhere(string $q, string $status, string $currency, int $merchantId, string $dateFrom, string $dateTo): array
    {
        $where  = ['1=1'];
        $params = [];

        if ($q !== '') {
            $where[]  = '(p.payment_code LIKE ? OR p.tx_hash LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        if ($status !== '') {
            $where[]  = 'p.status = ?';
            $params[] = $status;
        }
        if ($currency !== '') {
            $where[]  = 'p.currency = ?';
            $params[] = $currency;
        }
        if ($merchantId) {
            $where[]  = 'p.merchant_id = ?';
            $params[] = $merchantId;
        }
        if ($dateFrom !== '') {
            $where[]  = 'DATE(p.created_at) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[]  = 'DATE(p.created_at) <= ?';
            $params[] = $dateTo;
        }

        return [$where, $params];
    }

    private function countQuery(string $whereStr, array $params): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM payments p WHERE {$whereStr}");
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    private function exportCsv(array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="payments_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['決済コード','加盟店','通貨','暗号通貨額','JPY換算','ステータス','Txハッシュ','作成日時']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['payment_code'],
                $r['merchant_name'] ?? '',
                $r['currency'],
                $r['amount_crypto'],
                $r['amount_jpy'],
                $r['status'],
                $r['tx_hash'] ?? '',
                $r['created_at'],
            ]);
        }
        fclose($out);
    }
}
