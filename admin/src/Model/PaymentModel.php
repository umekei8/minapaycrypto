<?php
declare(strict_types=1);

class PaymentModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * 決済一覧（フィルタ付き）
     */
    public function list(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[]    = '(p.payment_code LIKE :q OR m.name LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[]          = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['currency'])) {
            $where[]            = 'p.currency = :currency';
            $params[':currency'] = $filters['currency'];
        }
        if (!empty($filters['merchant_id'])) {
            $where[]               = 'p.merchant_id = :merchant_id';
            $params[':merchant_id'] = $filters['merchant_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[]              = 'DATE(p.created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[]            = 'DATE(p.created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $sql = 'SELECT p.*, m.name AS merchant_name
                FROM payments p
                JOIN merchants m ON p.merchant_id = m.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset';

        $st = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }

    public function count(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['q'])) {
            $where[]    = '(p.payment_code LIKE :q OR m.name LIKE :q)';
            $params[':q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['status'])) {
            $where[]          = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['currency'])) {
            $where[]            = 'p.currency = :currency';
            $params[':currency'] = $filters['currency'];
        }
        $sql = 'SELECT COUNT(*) FROM payments p JOIN merchants m ON p.merchant_id=m.id WHERE ' . implode(' AND ', $where);
        $st  = $this->db->prepare($sql);
        $st->execute($params);
        return (int)$st->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('
            SELECT p.*, m.name AS merchant_name
            FROM payments p JOIN merchants m ON p.merchant_id=m.id
            WHERE p.id=?
        ');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /**
     * ダッシュボード集計
     */
    public function summary(string $period = 'today'): array
    {
        $cond = match($period) {
            'today'   => "DATE(created_at) = CURDATE()",
            'week'    => "YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1)",
            'month'   => "YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())",
            'quarter' => "QUARTER(created_at)=QUARTER(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())",
            default   => "DATE(created_at) = CURDATE()",
        };
        $row = $this->db->query("
            SELECT
                COALESCE(SUM(CASE WHEN status='confirmed' THEN amount_jpy ELSE 0 END),0) AS sales_jpy,
                COUNT(*)                       AS total_count,
                SUM(status='confirmed')        AS confirmed_count,
                SUM(status='failed')           AS failed_count,
                SUM(status='pending')          AS pending_count,
                COUNT(DISTINCT merchant_id)    AS merchant_count
            FROM payments
            WHERE {$cond}
        ")->fetch();
        return $row ?: [];
    }

    /**
     * 通貨別集計
     */
    public function byCurrency(string $period = 'today'): array
    {
        $cond = match($period) {
            'today'   => "DATE(created_at) = CURDATE()",
            'week'    => "YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1)",
            'month'   => "YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())",
            default   => "DATE(created_at) = CURDATE()",
        };
        return $this->db->query("
            SELECT currency,
                   COUNT(*) AS cnt,
                   COALESCE(SUM(CASE WHEN status='confirmed' THEN amount_jpy ELSE 0 END),0) AS jpy
            FROM payments
            WHERE {$cond}
            GROUP BY currency ORDER BY jpy DESC
        ")->fetchAll();
    }

    /**
     * 時間帯別売上（今日）
     */
    public function hourly(): array
    {
        return $this->db->query("
            SELECT HOUR(created_at) AS h,
                   COALESCE(SUM(CASE WHEN status='confirmed' THEN amount_jpy ELSE 0 END),0) AS jpy
            FROM payments
            WHERE DATE(created_at) = CURDATE()
            GROUP BY h ORDER BY h
        ")->fetchAll();
    }

    /**
     * 日別売上（今月）
     */
    public function daily(): array
    {
        return $this->db->query("
            SELECT DAY(created_at) AS d,
                   COALESCE(SUM(CASE WHEN status='confirmed' THEN amount_jpy ELSE 0 END),0) AS jpy
            FROM payments
            WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())
            GROUP BY d ORDER BY d
        ")->fetchAll();
    }

    /**
     * 最近N件
     */
    public function recent(int $n = 10): array
    {
        $st = $this->db->prepare('
            SELECT p.*, m.name AS merchant_name
            FROM payments p JOIN merchants m ON p.merchant_id=m.id
            ORDER BY p.created_at DESC LIMIT ?
        ');
        $st->execute([$n]);
        return $st->fetchAll();
    }
}
