<?php
$pageTitle = 'ダッシュボード';

function fmtJpy(mixed $v): string {
    return '¥' . number_format((float)$v);
}

ob_start();
?>
<!-- Period selector -->
<div class="period-tabs">
  <?php foreach (['today'=>'今日','week'=>'今週','month'=>'今月','quarter'=>'今四半期'] as $k=>$label): ?>
  <a href="index.php?page=dashboard&period=<?= $k ?>"
     class="period-tab <?= ($period??'today')===$k ? 'active':'' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</div>

<!-- KPI cards -->
<div class="kpi-grid">
  <div class="kpi-card">
    <div class="kpi-label">売上合計</div>
    <div class="kpi-value"><?= fmtJpy($summary['sales_jpy'] ?? 0) ?></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">決済件数</div>
    <div class="kpi-value"><?= number_format((int)($summary['total_count'] ?? 0)) ?> <small>件</small></div>
  </div>
  <div class="kpi-card kpi-green">
    <div class="kpi-label">成功</div>
    <div class="kpi-value"><?= number_format((int)($summary['confirmed_count'] ?? 0)) ?> <small>件</small></div>
  </div>
  <div class="kpi-card kpi-red">
    <div class="kpi-label">失敗</div>
    <div class="kpi-value"><?= number_format((int)($summary['failed_count'] ?? 0)) ?> <small>件</small></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">アクティブ加盟店</div>
    <div class="kpi-value"><?= $merchantCount ?? 0 ?> <small>店</small></div>
  </div>
  <div class="kpi-card">
    <div class="kpi-label">オンライン端末</div>
    <div class="kpi-value"><?= $deviceCount ?? 0 ?> <small>台</small></div>
  </div>
</div>

<!-- Charts row -->
<div class="charts-row">
  <div class="chart-card">
    <h3 class="chart-title">時間帯別売上（今日）</h3>
    <canvas id="chartHourly"></canvas>
  </div>
  <div class="chart-card">
    <h3 class="chart-title">通貨別内訳</h3>
    <canvas id="chartCurrency"></canvas>
  </div>
</div>

<!-- Recent payments -->
<div class="card">
  <div class="card-header">
    <h3>最近の決済</h3>
    <a href="index.php?page=payments" class="btn btn-sm">すべて見る</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>決済コード</th><th>加盟店</th><th>通貨</th>
          <th>金額(JPY)</th><th>ステータス</th><th>日時</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($recent as $p): ?>
      <tr>
        <td><code><?= htmlspecialchars($p['payment_code']) ?></code></td>
        <td><?= htmlspecialchars($p['merchant_name']) ?></td>
        <td><span class="badge badge-currency"><?= htmlspecialchars($p['currency']) ?></span></td>
        <td><?= fmtJpy($p['amount_jpy']) ?></td>
        <td><span class="badge badge-<?= $p['status'] ?>"><?= htmlspecialchars($p['status']) ?></span></td>
        <td><?= date('m/d H:i', strtotime($p['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?>
      <tr><td colspan="6" class="text-center text-muted">データがありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function(){
  const hourlyData = <?= json_encode(array_column($hourly ?? [], 'jpy', 'h')) ?>;
  const currencyData = <?= json_encode($byCurrency ?? []) ?>;

  // Build 0-23 hour labels
  const hours = Array.from({length:24},(_,i)=>i);
  const hourlyValues = hours.map(h => parseFloat(hourlyData[h]||0));

  drawBarChart('chartHourly', hours.map(h=>h+'時'), hourlyValues, '#c0392b');

  const cLabels = currencyData.map(r=>r.currency);
  const cValues = currencyData.map(r=>parseFloat(r.jpy));
  const colors = ['#c0392b','#e74c3c','#2980b9','#27ae60','#f39c12'];
  drawDonutChart('chartCurrency', cLabels, cValues, colors);
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
