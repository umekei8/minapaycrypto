<?php
declare(strict_types=1);

class SettingsModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(): array
    {
        $rows = $this->db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['setting_key']] = $r['setting_value'];
        }
        return $out;
    }

    public function get(string $key, string $default = ''): string
    {
        $st = $this->db->prepare('SELECT setting_value FROM settings WHERE setting_key=?');
        $st->execute([$key]);
        $val = $st->fetchColumn();
        return $val !== false ? (string)$val : $default;
    }

    public function set(string $key, string $value): void
    {
        $st = $this->db->prepare('
            INSERT INTO settings (setting_key, setting_value)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)
        ');
        $st->execute([':k' => $key, ':v' => $value]);
    }

    public function batchSet(array $kv): void
    {
        $st = $this->db->prepare('
            INSERT INTO settings (setting_key, setting_value)
            VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)
        ');
        foreach ($kv as $k => $v) {
            $st->execute([':k' => $k, ':v' => (string)$v]);
        }
    }
}
