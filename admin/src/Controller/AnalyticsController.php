<?php
declare(strict_types=1);

class AnalyticsController
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $period = $_GET['period'] ?? '30d';
        $dateFrom = match($period) {
            '7d'   => date('Y-m-d', strtotime('-7 days')),
            '30d'  => date('Y-m-d', strtotime('-30 days')),
            '90d'  => date('Y-m-d', strtotime('-90 days')),
            '1y'   => date('Y-m-d', strtotime('-1 year')),
            default => date('Y-m-d', strtotime('-30 days')),
        };

        // Total volume by currency
        $stCrypto = $this->db->prepare(
            "SELECT currency, COUNT(*) AS count,
                    SUM(amount_crypto) AS total_crypto,
                    SUM(amount_jpy) AS total_jpy,
                    AVG(amount_jpy) AS avg_jpy
             FROM payments
             WHERE status='confirmed' AND created_at >= ?
             GROUP BY currency ORDER BY total_jpy DESC"
        );
        $stCrypto->execute([$dateFrom]);
        $cryptoVolume = $stCrypto->fetchAll();

        // Daily trend (last N days)
        $stDaily = $this->db->prepare(
            "SELECT DATE(created_at) AS day,
                    COUNT(*) AS count,
                    SUM(amount_jpy) AS total_jpy
             FROM payments
             WHERE status='confirmed' AND created_at >= ?
             GROUP BY DATE(created_at) ORDER BY day ASC"
        );
        $stDaily->execute([$dateFrom]);
        $dailyTrend = $stDaily->fetchAll();

        // Top merchants by volume
        $stTop = $this->db->prepare(
            "SELECT m.id, m.code, m.name, m.fee_percent,
                    COUNT(p.id) AS tx_count,
                    SUM(p.amount_jpy) AS total_jpy
             FROM merchants m
             JOIN payments p ON p.merchant_id = m.id
             WHERE p.status='confirmed' AND p.created_at >= ?
             GROUP BY m.id ORDER BY total_jpy DESC LIMIT 10"
        );
        $stTop->execute([$dateFrom]);
        $topMerchants = $stTop->fetchAll();

        // Status summary
        $stStatus = $this->db->prepare(
            "SELECT status, COUNT(*) AS count
             FROM payments WHERE created_at >= ?
             GROUP BY status"
        );
        $stStatus->execute([$dateFrom]);
        $statusSummary = [];
        foreach ($stStatus->fetchAll() as $r) {
            $statusSummary[$r['status']] = (int)$r['count'];
        }

        // Merchant status counts
        $stMerchants = $this->db->query(
            "SELECT status, COUNT(*) AS count FROM merchants GROUP BY status"
        )->fetchAll();
        $merchantStatus = [];
        foreach ($stMerchants as $r) {
            $merchantStatus[$r['status']] = (int)$r['count'];
        }

        // Hourly heatmap (hour of day × day of week)
        $stHeat = $this->db->prepare(
            "SELECT HOUR(created_at) AS hr, DAYOFWEEK(created_at) AS dow, COUNT(*) AS cnt
             FROM payments
             WHERE status='confirmed' AND created_at >= ?
             GROUP BY hr, dow"
        );
        $stHeat->execute([$dateFrom]);
        $heatmapRaw = $stHeat->fetchAll();
        $heatmap = [];
        foreach ($heatmapRaw as $r) {
            $heatmap[(int)$r['hr']][(int)$r['dow']] = (int)$r['cnt'];
        }

        // KPI summary
        $stKpi = $this->db->prepare(
            "SELECT
               COUNT(*) AS total_tx,
               SUM(amount_jpy) AS total_jpy,
               AVG(amount_jpy) AS avg_jpy,
               MAX(amount_jpy) AS max_jpy,
               COUNT(DISTINCT merchant_id) AS active_merchants
             FROM payments WHERE status='confirmed' AND created_at >= ?"
        );
        $stKpi->execute([$dateFrom]);
        $kpi = $stKpi->fetch();

        ob_start();
        require BASE_DIR . '/src/View/analytics.php';
        $pageBody   = ob_get_clean();
        $pageTitle  = '売上分析';
        $activePage = 'analytics';
        require BASE_DIR . '/src/View/layout.php';
    }
}
