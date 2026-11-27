<?php
declare(strict_types=1);

/**
 * ApiController — REST API endpoints for POS terminals (Android app).
 *
 * Authentication: Every request must include a valid device API token.
 *   Header:  Authorization: Bearer <api_token>
 *   OR query: ?device_token=<api_token>
 *
 * All responses are JSON.
 *
 * Endpoints (routed via index.php under ?page=api/*)
 *   POST /api/payment/create   — create a payment record from the terminal
 *   POST /api/payment/update   — update payment status (confirmed / expired)
 *   GET  /api/payment/status   — query status of a payment by payment_code
 *   GET  /api/config           — fetch merchant wallet addresses and fee config
 */
class ApiController
{
    private \PDO $db;
    private array $device; // authenticated device row

    // ── Authentication ─────────────────────────────────────────────────────

    /**
     * Validate the device token from Authorization header or query string.
     * Returns true on success; sends 401 JSON and returns false on failure.
     */
    private function authenticate(): bool
    {
        $token = '';
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $token = trim($m[1]);
        } elseif (!empty($_GET['device_token'])) {
            $token = trim($_GET['device_token']);
        } elseif (!empty($_POST['device_token'])) {
            $token = trim($_POST['device_token']);
        }

        if ($token === '') {
            $this->json(['success' => false, 'error' => 'missing_token',
                         'message' => 'API token required.'], 401);
            return false;
        }

        $st = $this->db->prepare(
            "SELECT d.*, m.id AS mid, m.code AS merchant_code,
                    m.name AS merchant_name, m.status AS merchant_status,
                    m.fee_percent
             FROM devices d
             JOIN merchants m ON m.id = d.merchant_id
             WHERE d.api_token = ? AND d.status = 'active'"
        );
        $st->execute([$token]);
        $device = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$device) {
            $this->json(['success' => false, 'error' => 'invalid_token',
                         'message' => 'Invalid or inactive device token.'], 401);
            return false;
        }

        if ($device['merchant_status'] !== 'active') {
            $this->json(['success' => false, 'error' => 'merchant_inactive',
                         'message' => 'Merchant account is not active.'], 403);
            return false;
        }

        $this->device = $device;

        // Record last-seen time
        $this->db->prepare("UPDATE devices SET last_seen_at=NOW() WHERE id=?")
                  ->execute([$device['id']]);

        return true;
    }

    // ── Constructor ────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Route dispatcher ───────────────────────────────────────────────────

    /**
     * Called from index.php with the sub-path after "api/".
     * Examples: "payment/create", "payment/update", "payment/status", "config"
     */
    public function dispatch(string $subPath): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        if (!$this->authenticate()) {
            return;
        }

        switch ($subPath) {
            case 'payment/create':
                $this->requireMethod('POST');
                $this->paymentCreate();
                break;

            case 'payment/update':
                $this->requireMethod('POST');
                $this->paymentUpdate();
                break;

            case 'payment/status':
                $this->requireMethod('GET');
                $this->paymentStatus();
                break;

            case 'config':
                $this->requireMethod('GET');
                $this->config();
                break;

            default:
                $this->json(['success' => false, 'error' => 'not_found',
                             'message' => "Unknown API endpoint: {$subPath}"], 404);
        }
    }

    // ── POST /api/payment/create ───────────────────────────────────────────

    /**
     * Android app calls this when a payment is initiated on the terminal.
     *
     * Body (JSON or form-encoded):
     *   payment_code      string  e.g. "PAY-20261127-001234"
     *   currency          string  BTC | ETH | XRP | USDT_ERC20 | USDT_TRC20
     *   amount_jpy        int     fiat amount in JPY
     *   amount_crypto     string  crypto amount as decimal string
     *   rate_jpy          string  exchange rate used (JPY per 1 coin)
     *   receiving_address string  merchant's receiving wallet address
     *   fee_percent       float   (optional, defaults to merchant's fee_percent)
     *   expires_at        int     Unix timestamp in ms when QR expires (optional)
     */
    private function paymentCreate(): void
    {
        $body = $this->parseBody();

        $required = ['payment_code', 'currency', 'amount_jpy', 'amount_crypto',
                     'rate_jpy', 'receiving_address'];
        foreach ($required as $field) {
            if (!isset($body[$field]) || $body[$field] === '') {
                $this->json(['success' => false, 'error' => 'missing_field',
                             'message' => "Required field missing: {$field}"], 422);
                return;
            }
        }

        $paymentCode  = trim($body['payment_code']);
        $currency     = strtoupper(trim($body['currency']));
        $amountJpy    = (int)$body['amount_jpy'];
        $amountCrypto = trim($body['amount_crypto']);
        $rateJpy      = trim($body['rate_jpy']);
        $address      = trim($body['receiving_address']);
        $feePercent   = isset($body['fee_percent'])
            ? (float)$body['fee_percent']
            : (float)$this->device['fee_percent'];
        $feeJpy       = (int)round($amountJpy * $feePercent / 100);

        $expiresMs = isset($body['expires_at']) ? (int)$body['expires_at'] : 0;
        $expiresAt = $expiresMs > 0
            ? date('Y-m-d H:i:s', (int)($expiresMs / 1000))
            : date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Idempotency: return current state if already exists
        $stExist = $this->db->prepare(
            "SELECT id, status FROM payments WHERE payment_code=?"
        );
        $stExist->execute([$paymentCode]);
        if ($existing = $stExist->fetch(\PDO::FETCH_ASSOC)) {
            $this->json(['success' => true, 'created' => false,
                         'payment_id' => (int)$existing['id'],
                         'status'     => $existing['status'],
                         'message'    => 'Payment already exists.']);
            return;
        }

        $this->db->prepare(
            "INSERT INTO payments
             (payment_code, merchant_id, device_id, currency,
              amount_jpy, amount_crypto, rate_jpy,
              fee_percent, fee_jpy, receiving_address,
              status, expires_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,'pending',?)"
        )->execute([
            $paymentCode,
            (int)$this->device['merchant_id'],
            (int)$this->device['id'],
            $currency,
            $amountJpy,
            $amountCrypto,
            $rateJpy,
            $feePercent,
            $feeJpy,
            $address,
            $expiresAt,
        ]);

        $newId = (int)$this->db->lastInsertId();
        $this->json(['success'      => true,
                     'created'      => true,
                     'payment_id'   => $newId,
                     'payment_code' => $paymentCode,
                     'status'       => 'pending']);
    }

    // ── POST /api/payment/update ───────────────────────────────────────────

    /**
     * Android app calls this when blockchain confirms or expires a payment.
     *
     * Body:
     *   payment_code   string
     *   status         string   confirmed | expired | failed
     *   tx_hash        string   (optional, blockchain transaction hash)
     *   confirmations  int      (optional)
     *   memo           string   (optional)
     */
    private function paymentUpdate(): void
    {
        $body = $this->parseBody();

        $paymentCode = trim($body['payment_code'] ?? '');
        $status      = trim($body['status'] ?? '');
        $allowed     = ['confirmed', 'expired', 'failed'];

        if (!$paymentCode || !in_array($status, $allowed, true)) {
            $this->json(['success' => false, 'error' => 'invalid_params',
                         'message' => 'payment_code and valid status (confirmed/expired/failed) required.'], 422);
            return;
        }

        $stFind = $this->db->prepare(
            "SELECT id, merchant_id, status FROM payments WHERE payment_code=?"
        );
        $stFind->execute([$paymentCode]);
        $payment = $stFind->fetch(\PDO::FETCH_ASSOC);

        if (!$payment) {
            $this->json(['success' => false, 'error' => 'not_found',
                         'message' => 'Payment not found.'], 404);
            return;
        }

        // Security: device must belong to the same merchant as the payment
        if ((int)$payment['merchant_id'] !== (int)$this->device['merchant_id']) {
            $this->json(['success' => false, 'error' => 'forbidden',
                         'message' => 'Access denied.'], 403);
            return;
        }

        $txHash        = trim($body['tx_hash'] ?? '');
        $confirmations = (int)($body['confirmations'] ?? 0);
        $memo          = trim($body['memo'] ?? '');

        $set    = ['status=?', 'updated_at=NOW()'];
        $params = [$status];

        if ($txHash !== '') {
            $set[]    = 'tx_hash=?';
            $params[] = $txHash;
        }
        if ($confirmations > 0) {
            $set[]    = 'confirmations=?';
            $params[] = $confirmations;
        }
        if ($status === 'confirmed') {
            $set[] = 'confirmed_at=NOW()';
        }
        if ($memo !== '') {
            $set[]    = 'memo=?';
            $params[] = $memo;
        }

        $params[] = (int)$payment['id'];
        $this->db->prepare(
            "UPDATE payments SET " . implode(', ', $set) . " WHERE id=?"
        )->execute($params);

        $this->json(['success'    => true,
                     'payment_id' => (int)$payment['id'],
                     'status'     => $status]);
    }

    // ── GET /api/payment/status ────────────────────────────────────────────

    /**
     * Query: ?payment_code=PAY-20261127-001234
     */
    private function paymentStatus(): void
    {
        $paymentCode = trim($_GET['payment_code'] ?? '');
        if ($paymentCode === '') {
            $this->json(['success' => false, 'error' => 'missing_param',
                         'message' => 'payment_code query parameter required.'], 422);
            return;
        }

        $st = $this->db->prepare(
            "SELECT id, payment_code, status, tx_hash, confirmations,
                    amount_jpy, amount_crypto, currency,
                    confirmed_at, expires_at, created_at
             FROM payments
             WHERE payment_code=? AND merchant_id=?"
        );
        $st->execute([$paymentCode, (int)$this->device['merchant_id']]);
        $payment = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$payment) {
            $this->json(['success' => false, 'error' => 'not_found',
                         'message' => 'Payment not found.'], 404);
            return;
        }

        $this->json(['success' => true, 'payment' => $payment]);
    }

    // ── GET /api/config ────────────────────────────────────────────────────

    /**
     * Returns the merchant's wallet addresses and fee configuration.
     * The Android app can use this to override or supplement local demo_config.json.
     */
    private function config(): void
    {
        $stW = $this->db->prepare(
            "SELECT currency, address, network
             FROM wallets
             WHERE merchant_id=? AND status='active'
             ORDER BY currency"
        );
        $stW->execute([(int)$this->device['merchant_id']]);
        $wallets = $stW->fetchAll(\PDO::FETCH_ASSOC);

        $this->json([
            'success'       => true,
            'merchant_code' => $this->device['merchant_code'],
            'merchant_name' => $this->device['merchant_name'],
            'device_code'   => $this->device['device_code'],
            'fee_percent'   => (float)$this->device['fee_percent'],
            'wallets'       => $wallets,
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function requireMethod(string $method): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== $method) {
            $this->json(['success' => false, 'error' => 'method_not_allowed',
                         'message' => "{$method} required."], 405);
            exit;
        }
    }

    /**
     * Parse request body: JSON preferred, then form-encoded.
     */
    private function parseBody(): array
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $raw     = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST;
    }
}
