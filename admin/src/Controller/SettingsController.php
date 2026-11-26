<?php
declare(strict_types=1);

class SettingsController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── 為替レートページ ───────────────────────────────────────────────────
    public function rates(): void
    {
        $supported = $this->getSupportedCurrencies();
        $rates     = $this->fetchMultiSourceRates($supported);
        $markup    = (float)($this->getSetting('rate_markup_percent') ?? 1.0);

        ob_start();
        require BASE_DIR . '/src/View/rates.php';
        $pageBody   = ob_get_clean();
        $pageTitle  = '為替レート';
        $activePage = 'rates';
        require BASE_DIR . '/src/View/layout.php';
    }

    // ── 設定ページ ─────────────────────────────────────────────────────────
    public function index(): void
    {
        $settings = [];
        foreach ($this->db->query("SELECT `key`, `value` FROM settings ORDER BY `key`")->fetchAll() as $row) {
            $settings[$row['key']] = $row['value'];
        }

        ob_start();
        require BASE_DIR . '/src/View/settings.php';
        $pageBody   = ob_get_clean();
        $pageTitle  = '設定';
        $activePage = 'settings';
        require BASE_DIR . '/src/View/layout.php';
    }

    // ── 設定保存 ──────────────────────────────────────────────────────────
    public function save(): void
    {
        if (!Auth::verifyCsrf($_POST['_csrf'] ?? '')) {
            http_response_code(403); exit('CSRF error');
        }

        $allowed = [
            'default_fee_percent', 'rate_markup_percent', 'payment_timeout_minutes',
            'min_payment_jpy', 'coingecko_api_key', 'notify_email',
            'notify_on_fail', 'notify_on_rate_spike', 'notify_daily_report',
            'notify_new_merchant', 'supported_currencies',
        ];

        $st = $this->db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?");

        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                $st->execute([$key, $val, $val]);
            }
        }

        $_SESSION['flash'] = ['type' => 'success', 'message' => '設定を保存しました。'];
        header('Location: index.php?page=settings');
        exit;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────
    private function getSupportedCurrencies(): array
    {
        $v = $this->getSetting('supported_currencies') ?? 'BTC,ETH,XRP,USDT_ERC20,USDT_TRC20';
        return array_map('trim', explode(',', $v));
    }

    private function getSetting(string $key): ?string
    {
        $st = $this->db->prepare("SELECT `value` FROM settings WHERE `key`=?");
        $st->execute([$key]);
        $r = $st->fetch();
        return $r ? $r['value'] : null;
    }

    public function fetchMultiSourceRates(array $currencies): array
    {
        $coinGeckoIds = [
            'BTC'        => 'bitcoin',
            'ETH'        => 'ethereum',
            'XRP'        => 'ripple',
            'USDT_ERC20' => 'tether',
            'USDT_TRC20' => 'tether',
            'SOL'        => 'solana',
        ];
        $binanceSymbols = [
            'BTC' => 'BTCJPY', 'ETH' => 'ETHJPY',
            'XRP' => 'XRPJPY', 'SOL' => 'SOLJPY',
        ];
        $krakenPairMap = [
            'BTC' => 'XBTJPY', 'ETH' => 'ETHJPY',
            'XRP' => 'XRPJPY', 'SOL' => 'SOLJPY',
        ];

        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);

        // CoinGecko
        $cgData = [];
        $ids = implode(',', array_unique(array_filter(
            array_map(fn($c) => $coinGeckoIds[$c] ?? null, $currencies)
        )));
        if ($ids) {
            $json = @file_get_contents(
                "https://api.coingecko.com/api/v3/simple/price?ids={$ids}&vs_currencies=jpy",
                false, $ctx
            );
            if ($json) $cgData = json_decode($json, true) ?? [];
        }

        // Binance
        $binanceData = [];
        $json = @file_get_contents('https://api.binance.com/api/v3/ticker/price', false, $ctx);
        if ($json) {
            foreach (json_decode($json, true) ?? [] as $t) {
                $binanceData[$t['symbol']] = (float)$t['price'];
            }
        }

        // Kraken
        $krakenData = [];
        $pairs = implode(',', array_unique(array_filter(
            array_map(fn($c) => $krakenPairMap[$c] ?? null, $currencies)
        )));
        if ($pairs) {
            $json = @file_get_contents(
                "https://api.kraken.com/0/public/Ticker?pair={$pairs}",
                false, $ctx
            );
            if ($json) {
                foreach (json_decode($json, true)['result'] ?? [] as $pair => $info) {
                    $krakenData[$pair] = (float)($info['c'][0] ?? 0);
                }
            }
        }

        $results = [];
        foreach ($currencies as $currency) {
            $values  = [];
            $sources = [];

            // CoinGecko
            $cgId = $coinGeckoIds[$currency] ?? null;
            if ($cgId && isset($cgData[$cgId]['jpy']) && $cgData[$cgId]['jpy'] > 0) {
                $values[] = (float)$cgData[$cgId]['jpy'];
                $sources['CoinGecko'] = round((float)$cgData[$cgId]['jpy'], 2);
            }

            // Binance
            $bSym = $binanceSymbols[$currency] ?? null;
            if ($bSym && isset($binanceData[$bSym]) && $binanceData[$bSym] > 0) {
                $values[] = $binanceData[$bSym];
                $sources['Binance'] = round($binanceData[$bSym], 2);
            }

            // Kraken
            $kSym = $krakenPairMap[$currency] ?? null;
            if ($kSym && isset($krakenData[$kSym]) && $krakenData[$kSym] > 0) {
                $values[] = $krakenData[$kSym];
                $sources['Kraken'] = round($krakenData[$kSym], 2);
            }

            // USDT fallback: Binance USDTJPY
            if (in_array($currency, ['USDT_ERC20', 'USDT_TRC20']) && empty($values)) {
                if (isset($binanceData['USDTJPY']) && $binanceData['USDTJPY'] > 0) {
                    $values[] = $binanceData['USDTJPY'];
                    $sources['Binance(USDT)'] = round($binanceData['USDTJPY'], 2);
                }
            }

            $avg = $values ? array_sum($values) / count($values) : 0;
            $results[$currency] = [
                'avg'          => round($avg, 2),
                'sources'      => $sources,
                'source_count' => count($values),
                'fetched_at'   => date('H:i:s'),
            ];
        }

        return $results;
    }
}
