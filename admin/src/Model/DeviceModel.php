<?php
declare(strict_types=1);

class DeviceModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allWithMerchant(): array
    {
        return $this->db->query('
            SELECT d.*, m.name AS merchant_name
            FROM devices d JOIN merchants m ON d.merchant_id=m.id
            ORDER BY d.created_at DESC
        ')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare('
            SELECT d.*, m.name AS merchant_name
            FROM devices d JOIN merchants m ON d.merchant_id=m.id
            WHERE d.id=?
        ');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $code  = $this->nextCode();
        $token = 'mp_live_' . bin2hex(random_bytes(16));

        $st = $this->db->prepare('
            INSERT INTO devices (device_code, merchant_id, name, model, location, api_token)
            VALUES (:code, :mid, :name, :model, :loc, :token)
        ');
        $st->execute([
            ':code'  => $code,
            ':mid'   => $data['merchant_id'],
            ':name'  => $data['name'],
            ':model' => $data['model'] ?? 'ZCS Z108',
            ':loc'   => $data['location'] ?? null,
            ':token' => $token,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $st = $this->db->prepare('
            UPDATE devices SET name=:name, location=:loc, status=:status WHERE id=:id
        ');
        $st->execute([
            ':name'   => $data['name'],
            ':loc'    => $data['location'] ?? null,
            ':status' => $data['status'] ?? 'offline',
            ':id'     => $id,
        ]);
    }

    public function regenerateToken(int $id): string
    {
        $token = 'mp_live_' . bin2hex(random_bytes(16));
        $st    = $this->db->prepare('UPDATE devices SET api_token=? WHERE id=?');
        $st->execute([$token, $id]);
        return $token;
    }

    private function nextCode(): string
    {
        $row = $this->db->query('SELECT device_code FROM devices ORDER BY id DESC LIMIT 1')->fetch();
        if (!$row) return 'DEV001';
        $n = (int)ltrim(substr($row['device_code'], 3), '0') + 1;
        return 'DEV' . str_pad((string)$n, 3, '0', STR_PAD_LEFT);
    }
}
