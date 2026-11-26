<?php declare(strict_types=1);
/**
 * 売上分析 View (Marketing Analytics)
 * Variables: $cryptoVolume, $dailyTrend, $topMerchants, $statusSummary,
 *            $merchantStatus, $heatmap, $kpi, $period, $dateFrom
 */

$currencyLabel = [
    'BTC'        => ['icon'=>'₿', 'name'=>'Bitcoin',       'color'=>'#f7931a'],
    'ETH'        => ['icon'=>'Ξ', 'name'=>'Ethereum',      'color'=>'#627eea'],
    'XRP'        => ['icon'=>'✕', 'name'=>'XRP',           'color'=>'#346aa9'],
    'USDT_ERC20' => ['icon'=>'₮', 'name'=>'USDT (ERC-20)', 'color'=>'#26a17b'],
    'USDT_TRC20' => ['icon'=>'₮', 'name'=>'USDT (TRC-20)', 'color'=>'#1db999'],
    'SOL'        => ['icon'=>'◎', 'name'=>'Solana',        'color'=>'#9945ff'],
];

$fmtJpy    = fn(float $v): string => '¥' . number_format((int)$v);
$fmtCrypto = function(float $v, string $cur): string {
    if (in_array($cur, ['USDT_ERC20','USDT_TRC20'])) return number_format($v, 2);
    if ($cur === 'BTC') return number_format($v, 6);
    return number_format($v, 4);
};

$periodLabels = ['7d'=>'7日間','30d'=>'30日間','90d'=>'90日間','1y'=>'1年間'];

// Build daily chart data
$labels = [];
$amounts = [];
$counts = [];
foreach ($dailyTrend as $row) {
    $labels[]  = date('m/d', strtotime($row['day']));
    $amounts[] = round((float)$row['total_jpy'], 0);
    $counts[]  = (int)$row['count'];
}

$totalJpy = array_sum(array_column($cryptoVolume, 'total_jpy'));
?>

<!-- Period selector -->
<div class="page-actions">
  <div class="tabs" style="margin-bottom:0">
    <?php foreach ($periodLabels as $k => $lbl): ?>
    <a href="?page=analytics&period=<?= $k ?>"
       class="tab-btn <?= $period===$k ? 'active' : '' ?>"><?= $lbl ?></a>
    <?php endforeach; ?>
  </div>
  <a href="?page=analytics&period=<?= $period ?>&csv=1" class="btn btn-outline btn-sm">📥 CSV</a>
</div>

<!-- KPI Row -->
<div class="kpi-grid" style="margin-top:16px">
  <div class="kpi-card kpi-primary">
    <div class="kpi-label">総取引数</div>
    <div class="kpi-value"><?= number_format((int)($kpi['total_tx'] ?? 0)) ?> <small>件</small></div>
  </div>
  <div class="kpi-card kpi-green">
    <div class="kpi-label">売上合計（JPY換算）</div>
    <div class="kpi-value"><?= $fmtJpy((float)($kpi['total_jpy'] ?? 0)) ?></div>
  </div>
  <div class="kpi-card kpi-blue">
    <div class="kpi-label">平均取引額</div>
    <div class="kpi-value"><?= $fmtJpy((float)($kpi['avg_jpy'] ?? 0)) ?></div>
  </div>
  <div class="kpi-card kpi-green">
    <div class="kpi-label">最大取引額</div>
    <div class="kpi-value"><?= $fmtJpy((float)($kpi['max_jpy'] ?? 0)) ?></div>
  </div>
  <div class="kpi-card kpi-blue">
    <div class="kpi-label">アクティブ加盟店</div>
    <div class="kpi-value"><?= number_format((int)($kpi['active_merchants'] ?? 0)) ?> <small>店</small></div>
  </div>
  <div class="kpi-card kpi-primary">
    <div class="kpi-label">処理成功率</div>
    <?php
      $confirmed = $statusSummary['confirmed'] ?? 0;
      $totalTx   = array_sum($statusSummary);
      $rate      = $totalTx > 0 ? round($confirmed / $totalTx * 100, 1) : 0;
    ?>
    <div class="kpi-value"><?= $rate ?>%</div>
  </div>
</div>

<!-- Daily trend chart -->
<div class="card" style="margin-top:20px">
  <div class="card-header">
    <h3>📈 日別売上推移（<?= $periodLabels[$period] ?>）</h3>
  </div>
  <div class="card-body">
    <?php if (empty($dailyTrend)): ?>
    <div class="text-muted text-center" style="padding:40px">この期間のデータがありません</div>
    <?php else: ?>
    <div style="position:relative;height:220px">
      <canvas id="daily-chart"></canvas>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Currency breakdown + merchant status -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">

  <!-- Currency breakdown -->
  <div class="card">
    <div class="card-header"><h3>🪙 通貨別内訳</h3></div>
    <div class="card-body" style="padding:0">
      <?php if (empty($cryptoVolume)): ?>
      <div class="text-muted text-center" style="padding:24px">データなし</div>
      <?php else: ?>
      <?php foreach ($cryptoVolume as $row):
        $cur  = $row['currency'];
        $meta = $currencyLabel[$cur] ?? ['icon'=>'?','name'=>$cur,'color'=>'#999'];
        $pct  = $totalJpy > 0 ? ($row['total_jpy'] / $totalJpy * 100) : 0;
      ?>
      <div style="padding:14px 16px;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
          <div style="display:flex;align-items:center;gap:8px">
            <span style="color:<?= $meta['color'] ?>;font-size:18px;width:24px;text-align:center"><?= $meta['icon'] ?></span>
            <span style="font-size:14px;font-weight:600"><?= htmlspecialchars($meta['name']) ?></span>
            <span class="badge" style="background:var(--bg-secondary);color:var(--text-secondary)"><?= $row['count'] ?>件</span>
          </div>
          <div style="text-align:right">
            <div style="font-size:14px;font-weight:600"><?= $fmtJpy((float)$row['total_jpy']) ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= $fmtCrypto((float)$row['total_crypto'], $cur) ?> <?= $cur ?></div>
          </div>
        </div>
        <div style="background:var(--bg-secondary);border-radius:4px;height:6px">
          <div style="background:<?= $meta['color'] ?>;height:6px;border-radius:4px;width:<?= round($pct, 1) ?>%"></div>
        </div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:3px"><?= round($pct, 1) ?>%</div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Merchant status + payment status -->
  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card">
      <div class="card-header"><h3>🏪 加盟店ステータス</h3></div>
      <div class="card-body" style="padding:0">
        <?php $mStatusLabels = [
          'active'    => ['加盟店 アクティブ', '#16a34a'],
          'applied'   => ['新規申込',          '#d97706'],
          'reviewing' => ['審査中',            '#2563eb'],
          'suspended' => ['停止中',            '#dc2626'],
          'rejected'  => ['否決',              '#6b7280'],
        ]; ?>
        <?php foreach ($mStatusLabels as $st => [$lbl, $color]): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--border)">
          <span style="font-size:13px"><?= $lbl ?></span>
          <span style="font-size:16px;font-weight:700;color:<?= $color ?>"><?= number_format($merchantStatus[$st] ?? 0) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3>💳 決済ステータス内訳</h3></div>
      <div class="card-body" style="padding:0">
        <?php $pStatusLabels = [
          'confirmed' => ['確認済', '#16a34a'],
          'pending'   => ['待機中', '#d97706'],
          'expired'   => ['期限切', '#6b7280'],
          'failed'    => ['失敗',   '#dc2626'],
        ]; ?>
        <?php foreach ($pStatusLabels as $st => [$lbl, $color]): ?>
        <?php if (isset($statusSummary[$st])): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--border)">
          <span style="font-size:13px"><?= $lbl ?></span>
          <span style="font-size:16px;font-weight:700;color:<?= $color ?>"><?= number_format($statusSummary[$st]) ?></span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Top merchants -->
<?php if (!empty($topMerchants)): ?>
<div class="card" style="margin-top:20px">
  <div class="card-header">
    <h3>🏆 売上上位加盟店（<?= $periodLabels[$period] ?>）</h3>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>#</th>
            <th>コード</th>
            <th>加盟店名</th>
            <th>取引数</th>
            <th>売上合計（JPY換算）</th>
            <th>手数料率</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topMerchants as $i => $m): ?>
          <tr>
            <td style="color:var(--text-muted);font-weight:600"><?= $i + 1 ?></td>
            <td><code><?= htmlspecialchars($m['code']) ?></code></td>
            <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
            <td><?= number_format((int)$m['tx_count']) ?> 件</td>
            <td style="font-weight:600"><?= $fmtJpy((float)$m['total_jpy']) ?></td>
            <td><?= number_format((float)$m['fee_percent'], 2) ?>%</td>
            <td>
              <a href="?page=merchants&action=detail&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline">詳細</a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Chart.js for daily trend -->
<?php if (!empty($dailyTrend)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function(){
  const labels  = <?= json_encode($labels) ?>;
  const amounts = <?= json_encode($amounts) ?>;
  const counts  = <?= json_encode($counts) ?>;

  const isDark = document.documentElement.dataset.theme === 'dark'
    || (!document.documentElement.dataset.theme && window.matchMedia('(prefers-color-scheme:dark)').matches);

  const gridColor  = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
  const textColor  = isDark ? '#9ca3af' : '#6b7280';
  const lineColor  = '#c0392b';

  const ctx = document.getElementById('daily-chart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          type: 'line',
          label: '取引数',
          data: counts,
          borderColor: '#2563eb',
          backgroundColor: 'transparent',
          tension: 0.3,
          pointRadius: 3,
          yAxisID: 'y2',
        },
        {
          label: '売上（JPY換算）',
          data: amounts,
          backgroundColor: lineColor + '33',
          borderColor: lineColor,
          borderWidth: 2,
          borderRadius: 4,
          yAxisID: 'y1',
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { labels: { color: textColor, font: { size: 12 } } },
        tooltip: {
          callbacks: {
            label: ctx => {
              if (ctx.datasetIndex === 0) return ` 取引数: ${ctx.parsed.y}件`;
              return ` 売上: ¥${Number(ctx.parsed.y).toLocaleString()}`;
            }
          }
        }
      },
      scales: {
        x: { ticks: { color: textColor, maxRotation: 45 }, grid: { color: gridColor } },
        y1: {
          position: 'left',
          ticks: { color: textColor, callback: v => '¥' + Number(v).toLocaleString() },
          grid: { color: gridColor },
        },
        y2: {
          position: 'right',
          ticks: { color: '#2563eb', stepSize: 1 },
          grid: { drawOnChartArea: false },
        },
      },
    },
  });
})();
</script>
<?php endif; ?>
