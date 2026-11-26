<?php
declare(strict_types=1);

class DashboardController
{
    public function index(): void
    {
        $db = Database::getInstance();

        // --- Crypto-first totals (confirmed payments, today / 7d / 30d) ---
        $periods = [
            'today'   => 'DATE(created_at) = CURDATE()',
            'week'    => 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
            'month'   => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            'alltime' => '1=1',
        ];

        // Supported currencies from settings
        $supported = $this->getSupportedCurrencies($db);

        // Build crypto breakdown per period
        $cryptoStats = [];
        foreach ($periods as $key => $where) {
            $rows = $db->query(
                "SELECT currency,
                        COUNT(*) AS count,
                        SUM(amount_crypto) AS total_crypto,
                        SUM(amount_jpy) AS total_jpy,
                        SUM(fee_jpy) AS total_fee
                 FROM payments
                 WHERE status='confirmed' AND {$where}
                 GROUP BY currency
                 ORDER BY total_jpy DESC"
            )->fetchAll();
            $cryptoStats[$key] = $rows;
        }

        // --- KPI summary (all confirmed) ---
        $kpi = $db->query(
            "SELECT
                (SELECT COUNT(*) FROM payments WHERE status='confirmed' AND DATE(created_at)=CURDATE()) AS payments_today,
                (SELECT COUNT(*) FROM payments WHERE status='pending') AS payments_pending,
                (SELECT SUM(amount_jpy) FROM payments WHERE status='confirmed' AND DATE(created_at)=CURDATE()) AS jpy_today,
                (SELECT COUNT(*) FROM merchants WHERE status='active') AS merchants_active,
                (SELECT COUNT(*) FROM merchants WHERE status='applied' OR status='reviewing') AS merchants_pending,
                (SELECT COUNT(*) FROM merchants) AS merchants_total,
                (SELECT COUNT(*) FROM devices WHERE status='active') AS devices_active,
                (SELECT COUNT(*) FROM payments WHERE status='pending' AND expires_at < NOW()) AS payments_expired
            "
        )->fetch();

        // --- Live exchange rates (3 sources averaged) ---
        $rates = $this->fetchRates($supported);

        // --- Daily totals for sparkline (last 14 days) ---
        $daily = $db->query(
            "SELECT DATE(created_at) AS day,
                    COUNT(*) AS count,
                    COALESCE(SUM(amount_jpy),0) AS jpy
             FROM payments
             WHERE status='confirmed' AND created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC"
        )->fetchAll();

        // --- Recent transactions ---
        $recent = $db->query(
            "SELECT p.*, m.name AS merchant_name
             FROM payments p
             LEFT JOIN merchants m ON m.id=p.merchant_id
             ORDER BY p.created_at DESC LIMIT 10"
        )->fetchAll();

        // --- Review queue ---
        $reviewQueue = $db->query(
            "SELECT * FROM merchants WHERE status IN ('applied','reviewing') ORDER BY created_at ASC LIMIT 5"
        )->fetchAll();

        ob_start();
        require BASE_DIR . '/src/View/dashboard.php';
        $pageBody   = ob_get_clean();
        $pageTitle  = 'ダッシュボード';
        $activePage = 'dashboard';
        require BASE_DIR . '/src/View/layout.php';
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function getSupportedCurrencies($db): array
    {
        $row = $db->query("SELECT value FROM settings WHERE `key`='supported_currencies'")->fetch();
        $str = $row['value'] ?? 'BTC,ETH,XRP,USDT_ERC20,USDT_TRC20';
        return array_map('trim', explode(',', $str));
    }

    /**
     * Fetch JPY rates from CoinGecko, Binance, and Kraken and return average.
     * Returns ['BTC'=>['avg'=>7000000,'sources'=>[...]], ...]
     */
    private function fetchRates(array $currencies): array
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
            'BTC'        => 'BTCJPY',
            'ETH'        => 'ETHJPY',
            'XRP'        => 'XRPJPY',
            'USDT_ERC20' => null,
            'USDT_TRC20' => null,
            'SOL'        => 'SOLJPY',
        ];

        $results = [];

        // CoinGecko (free, no key)
        $ids = implode(',', array_unique(array_filter(
            array_map(fn($c) => $coinGeckoIds[$c] ?? null, $currencies)
        )));
        $cgData = [];
        if ($ids) {
            $json = @file_get_contents(
                "https://api.coingecko.com/api/v3/simple/price?ids={$ids}&vs_currencies=jpy",
                false,
                stream_context_create(['http' => ['timeout' => 5]])
            );
            if ($json) {
                $cgData = json_decode($json, true) ?? [];
            }
        }

        // Binance
        $binanceData = [];
        $json = @file_get_contents(
            'https://api.binance.com/api/v3/ticker/price',
            false,
            stream_context_create(['http' => ['timeout' => 5]])
        );
        if ($json) {
            $tickers = json_decode($json, true) ?? [];
            foreach ($tickers as $t) {
                $binanceData[$t['symbol']] = (float)$t['price'];
            }
        }

        // Kraken (BTC=XBT, ETH, XRP, SOL vs JPY)
        $krakenData = [];
        $json = @file_get_contents(
            'https://api.kraken.com/0/public/Ticker?pair=XBTJPY,ETHJPY,XRPJPY,SOLJPY',
            false,
            stream_context_create(['http' => ['timeout' => 5]])
        );
        if ($json) {
            $kr = json_decode($json, true)['result'] ?? [];
            foreach ($kr as $pair => $info) {
                $krakenData[$pair] = (float)($info['c'][0] ?? 0);
            }
        }

        $krakenMap = [
            'BTC' => 'XBTJPY',
            'ETH' => 'ETHJPY',
            'XRP' => 'XRPJPY',
            'SOL' => 'SOLJPY',
        ];

        foreach ($currencies as $currency) {
            $values  = [];
            $sources = [];

            // CoinGecko
            $cgId = $coinGeckoIds[$currency] ?? null;
            if ($cgId && isset($cgData[$cgId]['jpy'])) {
                $v = (float)$cgData[$cgId]['jpy'];
                if ($v > 0) { $values[] = $v; $sources[] = 'CoinGecko'; }
            }

            // Binance
            $bSym = $binanceSymbols[$currency] ?? null;
            if ($bSym && isset($binanceData[$bSym])) {
                $v = $binanceData[$bSym];
                if ($v > 0) { $values[] = $v; $sources[] = 'Binance'; }
            }

            // Kraken
            $kSym = $krakenMap[$currency] ?? null;
            if ($kSym && isset($krakenData[$kSym])) {
                $v = $krakenData[$kSym];
                if ($v > 0) { $values[] = $v; $sources[] = 'Kraken'; }
            }

            // USDT special: 1 USD ≈ JPY from Binance USDTJPY or fallback
            if (in_array($currency, ['USDT_ERC20', 'USDT_TRC20']) && empty($values)) {
                // Try USDTJPY on Binance
                if (isset($binanceData['USDTJPY'])) {
                    $v = $binanceData['USDTJPY'];
                    if ($v > 0) { $values[] = $v; $sources[] = 'Binance'; }
                }
            }

            $avg = $values ? array_sum($values) / count($values) : 0;
            $results[$currency] = [
                'avg'     => round($avg, 2),
                'sources' => $sources,
                'values'  => $values,
            ];
        }

        return $results;
    }
}
