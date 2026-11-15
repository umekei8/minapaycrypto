<?php
declare(strict_types=1);

class MerchantModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(): array
    {
        return $this->db->query(
            'SELECT * FROM merchants ORDER BY created_at DESC'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM merchants WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $st = $this->db->prepare('SELECT * FROM merchants WHERE slug = ?');
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $code  = $this->nextCode();
        $token = $this->generateToken();

        $st = $this->db->prepare('
            INSERT INTO merchants (code, name, email, phone, slug, fee_percent, api_token, memo)
            VALUES (:code,:name,:email,:phone,:slug,:fee,:token,:memo)
        ');
        $st->execute([
            ':code'  => $code,
            ':name'  => $data['name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'] ?? null,
            ':slug'  => $data['slug'],
            ':fee'   => $data['fee_percent'] ?? 6.50,
            ':token' => $token,
            ':memo'  => $data['memo'] ?? null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $st = $this->db->prepare('
            UPDATE merchants
            SET name=:name, email=:email, phone=:phone,
                fee_percent=:fee, status=:status, memo=:memo
            WHERE id=:id
        ');
        $st->execute([
            ':name'   => $data['name'],
            ':email'  => $data['email'],
            ':phone'  => $data['phone'] ?? null,
            ':fee'    => $data['fee_percent'],
            ':status' => $data['status'] ?? 'active',
            ':memo'   => $data['memo'] ?? null,
            ':id'     => $id,
        ]);
    }

    public function stats(int $id): array
    {
        $st = $this->db->prepare('
            SELECT
                COUNT(*)                             AS total_count,
                COALESCE(SUM(amount_jpy),0)          AS total_jpy,
                SUM(status = "confirmed")            AS confirmed_count,
                SUM(status = "failed")               AS failed_count
            FROM payments
            WHERE merchant_id = ?
              AND YEAR(created_at) = YEAR(CURDATE())
              AND MONTH(created_at) = MONTH(CURDATE())
        ');
        $st->execute([$id]);
        return $st->fetch() ?: [];
    }

    public function deviceCount(int $id): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM devices WHERE merchant_id=? AND status != 'disabled'"
        );
        $st->execute([$id]);
        return (int)$st->fetchColumn();
    }

    private function nextCode(): string
    {
        $row = $this->db->query(
            "SELECT code FROM merchants ORDER BY id DESC LIMIT 1"
        )->fetch();
        if (!$row) return 'M001';
        $n = (int)ltrim(substr($row['code'], 1), '0') + 1;
        return 'M' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
    }

    private function generateToken(): string
    {
        return 'mp_live_' . bin2hex(random_bytes(16));
    }
}
