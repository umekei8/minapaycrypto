<?php
declare(strict_types=1);

class WalletModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allWithMerchant(): array
    {
        return $this->db->query('
            SELECT w.*, m.name AS merchant_name
            FROM wallets w JOIN merchants m ON w.merchant_id=m.id
            ORDER BY m.name, w.currency
        ')->fetchAll();
    }

    public function forMerchant(int $merchantId): array
    {
        $st = $this->db->prepare('SELECT * FROM wallets WHERE merchant_id=? ORDER BY currency');
        $st->execute([$merchantId]);
        return $st->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('SELECT w.*, m.name AS merchant_name FROM wallets w JOIN merchants m ON w.merchant_id=m.id WHERE w.id=?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function save(array $data): void
    {
        // merchant_id + currency でUPSERT
        $st = $this->db->prepare('
            INSERT INTO wallets (merchant_id, currency, address, label, status)
            VALUES (:mid, :cur, :addr, :label, :status)
            ON DUPLICATE KEY UPDATE address=VALUES(address), label=VALUES(label), status=VALUES(status)
        ');
        $st->execute([
            ':mid'    => $data['merchant_id'],
            ':cur'    => $data['currency'],
            ':addr'   => $data['address'],
            ':label'  => $data['label'] ?? null,
            ':status' => $data['status'] ?? 'active',
        ]);
    }

    public function update(int $id, array $data): void
    {
        $st = $this->db->prepare('UPDATE wallets SET address=:addr, label=:label, status=:status WHERE id=:id');
        $st->execute([
            ':addr'   => $data['address'],
            ':label'  => $data['label'] ?? null,
            ':status' => $data['status'] ?? 'active',
            ':id'     => $id,
        ]);
    }
}
