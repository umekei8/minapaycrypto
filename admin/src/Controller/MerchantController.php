<?php
declare(strict_types=1);

class MerchantController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── 一覧 ───────────────────────────────────────────────────────────────
    public function index(): void
    {
        $q      = trim($_GET['q'] ?? '');
        $status = $_GET['status'] ?? '';
        $page   = max(1, (int)($_GET['p'] ?? 1));
        $limit  = 25;
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[]  = '(m.name LIKE ? OR m.code LIKE ? OR m.email LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        if ($status !== '') {
            $where[]  = 'm.status = ?';
            $params[] = $status;
        }

        $whereStr = implode(' AND ', $where);

        $total = $this->db->prepare("SELECT COUNT(*) FROM merchants m WHERE {$whereStr}");
        $total->execute($params);
        $totalCount = (int)$total->fetchColumn();

        $st = $this->db->prepare(
            "SELECT m.*,
                    a.name AS reviewer_name,
                    (SELECT COUNT(*) FROM wallets WHERE merchant_id=m.id AND status='active') AS wallet_count,
                    (SELECT COUNT(*) FROM devices WHERE merchant_id=m.id AND status='active') AS device_count,
                    (SELECT SUM(amount_jpy) FROM payments WHERE merchant_id=m.id AND status='confirmed') AS total_jpy
             FROM merchants m
             LEFT JOIN admins a ON a.id = m.reviewed_by
             WHERE {$whereStr}
             ORDER BY m.created_at DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $st->execute($params);
        $merchants = $st->fetchAll();

        // CSV download
        if (isset($_GET['csv'])) {
            $this->exportCsv($merchants);
            return;
        }

        ob_start();
        require BASE_DIR . '/src/View/merchants.php';
        $pageBody   = ob_get_clean();
        $pageTitle  = '加盟店管理';
        $activePage = 'merchants';
        require BASE_DIR . '/src/View/layout.php';
    }

    // ── 詳細 ───────────────────────────────────────────────────────────────
    public function detail(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { $this->redirect('merchants'); }

        $merchant = $this->findOrAbort($id);
        $wallets  = $this->db->prepare("SELECT * FROM wallets WHERE merchant_id=? ORDER BY currency")->execute([$id]) ? null : null;
        $stW = $this->db->prepare("SELECT * FROM wallets WHERE merchant_id=? ORDER BY currency");
        $stW->execute([$id]);
        $wallets = $stW->fetchAll();

        $stD = $this->db->prepare("SELECT * FROM devices WHERE merchant_id=? ORDER BY created_at DESC");
        $stD->execute([$id]);
        $devices = $stD->fetchAll();

        $stP = $this->db->prepare("SELECT * FROM payments WHERE merchant_id=? ORDER BY created_at DESC LIMIT 20");
        $stP->execute([$id]);
        $payments = $stP->fetchAll();

        ob_start();
        require BASE_DIR . '/src/View/merchant_detail.php';
        $pageBody   = ob_get_clean();
        $pageTitle  = '加盟店詳細: ' . $merchant['name'];
        $activePage = 'merchants';
        require BASE_DIR . '/src/View/layout.php';
    }

    // ── 新規作成 / 更新 (POST) ─────────────────────────────────────────────
    public function save(): void
    {
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
            http_response_code(403); exit('CSRF error');
        }

        $id = (int)($_POST['id'] ?? 0);
        $fields = $this->validateMerchantFields();
        if (is_array($fields) && isset($fields['error'])) {
            echo json_encode(['success' => false, 'message' => $fields['error']]);
            return;
        }

        if ($id) {
            // Update
            $this->findOrAbort($id);
            $set    = implode(', ', array_map(fn($k) => "`{$k}`=?", array_keys($fields)));
            $values = array_values($fields);
            $values[] = $id;
            $this->db->prepare("UPDATE merchants SET {$set}, updated_at=NOW() WHERE id=?")->execute($values);
            echo json_encode(['success' => true, 'message' => '加盟店情報を更新しました。', 'id' => $id]);
        } else {
            // Insert
            $fields['code']      = $this->generateCode();
            $fields['api_token'] = bin2hex(random_bytes(32));
            $cols   = implode(',', array_map(fn($k) => "`{$k}`", array_keys($fields)));
            $phs    = implode(',', array_fill(0, count($fields), '?'));
            $this->db->prepare("INSERT INTO merchants ({$cols}) VALUES ({$phs})")->execute(array_values($fields));
            $newId = (int)$this->db->lastInsertId();
            echo json_encode(['success' => true, 'message' => '加盟店を登録しました。', 'id' => $newId]);
        }
    }

    // ── 審査ステータス変更 ─────────────────────────────────────────────────
    public function review(): void
    {
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
            http_response_code(403); exit;
        }

        $id     = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $note   = trim($_POST['review_note'] ?? '');

        $allowed = ['reviewing', 'active', 'suspended', 'rejected'];
        if (!$id || !in_array($status, $allowed, true)) {
            echo json_encode(['success' => false, 'message' => '不正なリクエスト']);
            return;
        }

        $merchant = $this->findOrAbort($id);

        $this->db->prepare(
            "UPDATE merchants SET status=?, review_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?"
        )->execute([$status, $note, Auth::id(), $id]);

        // 審査通過 → ポータルアカウント自動発行
        $issued = false;
        if ($status === 'active' && !$merchant['portal_enabled']) {
            $portalEmail = $merchant['portal_email'] ?: $merchant['email'];
            $tmpPw       = $this->randomPassword();
            $hash        = password_hash($tmpPw, PASSWORD_BCRYPT, ['cost' => 12]);

            $this->db->prepare(
                "UPDATE merchants SET portal_email=?, portal_password=?, portal_enabled=1 WHERE id=?"
            )->execute([$portalEmail, $hash, $id]);

            $issued = true;
            $issuedEmail    = $portalEmail;
            $issuedPassword = $tmpPw;
        }

        $response = [
            'success' => true,
            'message' => "ステータスを「{$status}」に変更しました。",
        ];
        if ($issued) {
            $response['portal_issued']   = true;
            $response['portal_email']    = $issuedEmail;
            $response['portal_password'] = $issuedPassword;
            $response['message']       .= " 加盟店ポータルアカウントを発行しました。";
        }

        echo json_encode($response);
    }

    // ── ウォレット保存 ─────────────────────────────────────────────────────
    public function saveWallet(): void
    {
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
            http_response_code(403); exit;
        }

        $merchantId = (int)($_POST['merchant_id'] ?? 0);
        $currency   = trim($_POST['currency'] ?? '');
        $address    = trim($_POST['address'] ?? '');
        $network    = trim($_POST['network'] ?? '');
        $label      = trim($_POST['label'] ?? '');

        if (!$merchantId || !$currency || !$address) {
            echo json_encode(['success' => false, 'message' => '必須項目が不足しています。']);
            return;
        }

        $this->findOrAbort($merchantId);

        // Upsert
        $existing = $this->db->prepare("SELECT id FROM wallets WHERE merchant_id=? AND currency=?");
        $existing->execute([$merchantId, $currency]);
        $row = $existing->fetch();

        if ($row) {
            $this->db->prepare(
                "UPDATE wallets SET address=?, network=?, label=?, status='active', set_by='admin', updated_at=NOW() WHERE id=?"
            )->execute([$address, $network, $label, $row['id']]);
        } else {
            $this->db->prepare(
                "INSERT INTO wallets (merchant_id,currency,network,address,label,set_by) VALUES (?,?,?,?,?,'admin')"
            )->execute([$merchantId, $currency, $network, $address, $label]);
        }

        echo json_encode(['success' => true, 'message' => 'ウォレットアドレスを保存しました。']);
    }

    // ── 端末保存 ───────────────────────────────────────────────────────────
    public function saveDevice(): void
    {
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
            http_response_code(403); exit;
        }

        $merchantId    = (int)($_POST['merchant_id'] ?? 0);
        $devId         = (int)($_POST['device_id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $model         = trim($_POST['model'] ?? 'ZCS Z108');
        $serialNumber  = trim($_POST['serial_number'] ?? '');
        $location      = trim($_POST['location'] ?? '');

        if (!$merchantId || !$name) {
            echo json_encode(['success' => false, 'message' => '必須項目が不足しています。']);
            return;
        }

        $this->findOrAbort($merchantId);

        if ($devId) {
            $this->db->prepare(
                "UPDATE devices SET name=?, model=?, serial_number=?, location=? WHERE id=? AND merchant_id=?"
            )->execute([$name, $model, $serialNumber, $location, $devId, $merchantId]);
            echo json_encode(['success' => true, 'message' => '端末情報を更新しました。']);
        } else {
            $code  = $this->generateDeviceCode();
            $token = bin2hex(random_bytes(32));
            $this->db->prepare(
                "INSERT INTO devices (device_code,merchant_id,name,model,serial_number,location,api_token) VALUES (?,?,?,?,?,?,?)"
            )->execute([$code, $merchantId, $name, $model, $serialNumber, $location, $token]);
            $newId = (int)$this->db->lastInsertId();
            echo json_encode(['success' => true, 'message' => '端末を登録しました。', 'device_id' => $newId, 'api_token' => $token]);
        }
    }

    // ── 削除 ───────────────────────────────────────────────────────────────
    public function delete(): void
    {
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
            http_response_code(403); exit;
        }
        $id = (int)($_POST['id'] ?? 0);
        $this->findOrAbort($id);
        $this->db->prepare("DELETE FROM merchants WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true, 'message' => '加盟店を削除しました。']);
    }

    // ── 統計 (加盟店別) ────────────────────────────────────────────────────
    public function stats(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $merchant = $this->findOrAbort($id);

        $stats = $this->db->prepare(
            "SELECT currency, COUNT(*) AS count,
                    SUM(amount_crypto) AS total_crypto,
                    SUM(amount_jpy) AS total_jpy
             FROM payments
             WHERE merchant_id=? AND status='confirmed'
             GROUP BY currency"
        );
        $stats->execute([$id]);
        $cryptoStats = $stats->fetchAll();

        header('Content-Type: application/json');
        echo json_encode(['merchant' => $merchant, 'stats' => $cryptoStats]);
    }

    // ── CSV エクスポート ────────────────────────────────────────────────────
    private function exportCsv(array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="merchants_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        // BOM for Excel
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['加盟店コード','加盟店名','メール','電話','ステータス','手数料率','ウォレット数','端末数','登録日']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['code'], $r['name'], $r['email'], $r['phone'] ?? '',
                $r['status'], $r['fee_percent'] . '%',
                $r['wallet_count'] ?? 0, $r['device_count'] ?? 0,
                date('Y/m/d', strtotime($r['created_at'])),
            ]);
        }
        fclose($out);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    private function findOrAbort(int $id): array
    {
        $st = $this->db->prepare("SELECT * FROM merchants WHERE id=?");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) { http_response_code(404); exit('Merchant not found'); }
        return $row;
    }

    private function validateMerchantFields(): array
    {
        $required = ['name', 'email'];
        foreach ($required as $f) {
            if (empty($_POST[$f])) {
                return ['error' => "「{$f}」は必須です。"];
            }
        }

        return [
            'name'              => trim($_POST['name']),
            'name_kana'         => trim($_POST['name_kana'] ?? ''),
            'business_type'     => in_array($_POST['business_type'] ?? '', ['individual','corporation']) ? $_POST['business_type'] : 'individual',
            'representative'    => trim($_POST['representative'] ?? ''),
            'email'             => trim($_POST['email']),
            'phone'             => trim($_POST['phone'] ?? ''),
            'postal_code'       => trim($_POST['postal_code'] ?? ''),
            'address'           => trim($_POST['address'] ?? ''),
            'website'           => trim($_POST['website'] ?? ''),
            'business_category' => trim($_POST['business_category'] ?? ''),
            'fee_percent'       => max(0, min(100, (float)($_POST['fee_percent'] ?? 6.5))),
            'contract_start'    => $_POST['contract_start'] ?: null,
            'contract_end'      => $_POST['contract_end'] ?: null,
            'memo'              => trim($_POST['memo'] ?? ''),
        ];
    }

    private function generateCode(): string
    {
        $row = $this->db->query("SELECT MAX(CAST(SUBSTRING(code,2) AS UNSIGNED)) AS n FROM merchants WHERE code LIKE 'M%'")->fetch();
        $n   = (int)($row['n'] ?? 0) + 1;
        return 'M' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
    }

    private function generateDeviceCode(): string
    {
        $row = $this->db->query("SELECT MAX(CAST(SUBSTRING(device_code,4) AS UNSIGNED)) AS n FROM devices WHERE device_code LIKE 'DEV%'")->fetch();
        $n   = (int)($row['n'] ?? 0) + 1;
        return 'DEV' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
    }

    private function randomPassword(int $len = 12): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
        $pw    = '';
        for ($i = 0; $i < $len; $i++) {
            $pw .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pw;
    }
}
