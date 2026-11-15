<?php
declare(strict_types=1);

class DashboardController
{
    public function index(): void
    {
        Auth::require();
        $payment  = new PaymentModel();
        $merchant = new MerchantModel();

        $period   = $_GET['period'] ?? 'today';
        $summary  = $payment->summary($period);
        $byCurrency = $payment->byCurrency($period);
        $recent   = $payment->recent(10);
        $hourly   = $payment->hourly();
        $daily    = $payment->daily();

        $db = Database::getInstance();
        $merchantCount = (int)$db->query("SELECT COUNT(*) FROM merchants WHERE status='active'")->fetchColumn();
        $deviceCount   = (int)$db->query("SELECT COUNT(*) FROM devices WHERE status='online'")->fetchColumn();

        require BASE_DIR . '/src/View/dashboard.php';
    }
}
