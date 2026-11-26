<?php declare(strict_types=1);
/**
 * Dashboard view — crypto-first display
 *
 * Variables: $cryptoStats, $kpi, $rates, $daily, $recent, $reviewQueue
 */

$currencyLabel = [
    'BTC'        => ['icon'=>'₿',  'name'=>'Bitcoin',    'color'=>'#f7931a'],
    'ETH'        => ['icon'=>'Ξ',  'name'=>'Ethereum',   'color'=>'#627eea'],
    'XRP'        => ['icon'=>'✕',  'name'=>'XRP',        'color'=>'#346aa9'],
    'USDT_ERC20' => ['icon'=>'₮',  'name'=>'USDT (ERC)', 'color'=>'#26a17b'],
    'USDT_TRC20' => ['icon'=>'₮',  'name'=>'USDT (TRC)', 'color'=>'#26a17b'],
    'SOL'        => ['icon'=>'◎',  'name'=>'Solana',     'color'=>'#9945ff'],
];

$fmtCrypto = function(float $v, string $currency): string {
    if (in_array($currency, ['USDT_ERC20','USDT_TRC20'])) return number_format($v, 2);
    if ($currency === 'XRP') return number_format($v, 4);
    if ($currency === 'BTC') return number_format($v, 6);
    return number_format($v, 4);
};

$fmtJpy = fn(float $v): string => '¥' . number_format((int)$v);

// period selector
$period = $_GET['period'] ?? 'today';
$periodLabels = ['today'=>'今日','week'=>'7日間','month'=>'30日間','alltime'=>'全期間'];
?>

<!-- Period selector -->
<div class="period-tabs">
  <?php foreach ($periodLabels as $k => $lbl): ?>
  <a href="?page=dashboard&period=<?= $k ?>"
     class="period-tab <?= $period===$k ? 'active' : '' ?>">
    <?= $lbl ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- ── 暗号通貨別売上 ─────────────────────────────── -->
<div class="section-title">🪙 暗号通貨別売上（<?= $periodLabels[$period] ?? $period ?>）</div>
<div class="kpi-crypto-grid">
<?php
$periodData = $cryptoStats[$period] ?? [];
if (empty($periodData)): ?>
  <div class="card-body text-muted" style="grid-column:1/-1">この期間の確認済み決済はありません。</div>
<?php else:
  foreach ($periodData as $row):
    $cur = $row['currency'];
    $meta = $currencyLabel[$cur] ?? ['icon'=>'?','name'=>$cur,'color'=>'#999'];
?>
  <div class="kpi-crypto-card">
    <div class="kpi-crypto-header">
      <div class="crypto-icon" style="background:<?= $meta['color'] ?>22;color:<?= $meta['color'] ?>">
        <?= $meta['icon'] ?>
      </div>
      <div>
        <div class="crypto-name"><?= htmlspecialchars($meta['name']) ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><?= $row['count'] ?> 件</div>
      </div>
    </div>
    <div class="crypto-amount">
      <?= $fmtCrypto((float)$row['total_crypto'], $cur) ?>
      <small style="font-size:12px;font-weight:400"><?= htmlspecialchars($cur) ?></small>
    </div>
    <div class="crypto-jpy"><?= $fmtJpy((float)$row['total_jpy']) ?> 相当（JPY換算）</div>
  </div>
<?php endforeach; endif; ?>
</div>

<!-- ── KPI ──────────────────────────────────────────── -->
<div class="section-title">📋 サマリー</div>
<div class="kpi-grid">
  <div class="kpi-card kpi-green">
    <div class="kpi-label">今日の決済</div>
    <div class="kpi-value"><?= number_format((int)($kpi['payments_today'] ?? 0)) ?> <small>件</small></div>
  </div>
  <div class="kpi-card kpi-primary">
    <div class="kpi-label">今日の売上（JPY換算）</div>
    <div class="kpi-value"><?= $fmtJpy((float)($kpi['jpy_today'] ?? 0)) ?></div>
  </div>
  <div class="kpi-card kpi-blue">
    <div class="kpi-label">処理待ち決済</div>
    <div class="kpi-value"><?= number_format((int)($kpi['payments_pending'] ?? 0)) ?> <small>件</small></div>
  </div>
  <div class="kpi-card kpi-green">
    <div class="kpi-label">アクティブ加盟店</div>
    <div class="kpi-value"><?= number_format((int)($kpi['merchants_active'] ?? 0)) ?> <small>店</small></div>
  </div>
  <div class="kpi-card kpi-primary">
    <div class="kpi-label">審査待ち加盟店</div>
    <div class="kpi-value"><?= number_format((int)($kpi['merchants_pending'] ?? 0)) ?> <small>件</small></div>
  </div>
  <div class="kpi-card kpi-blue">
    <div class="kpi-label">稼働中端末</div>
    <div class="kpi-value"><?= number_format((int)($kpi['devices_active'] ?? 0)) ?> <small>台</small></div>
  </div>
</div>

<!-- ── 為替レート（3ソース平均） ───────────────────── -->
<div class="section-title">💱 現在の為替レート（複数ソース平均）</div>
<div class="rate-grid">
<?php foreach ($rates as $currency => $rate):
  $meta = $currencyLabel[$currency] ?? ['icon'=>'?','name'=>$currency,'color'=>'#999'];
  $avg  = $rate['avg'];
  $srcs = implode(' · ', $rate['sources']);
?>
  <div class="rate-card">
    <div class="rate-label">
      <span style="color:<?= $meta['color'] ?>"><?= $meta['icon'] ?></span>
      <?= htmlspecialchars($meta['name']) ?>
    </div>
    <div class="rate-value">
      <?= $avg > 0 ? $fmtJpy($avg) : '—' ?>
    </div>
    <div class="rate-sources">
      <?= $srcs ?: '取得失敗' ?>（平均 <?= count($rate['values']) ?> ソース）
    </div>
  </div>
<?php endforeach; ?>
</div>

<!-- ── 審査キュー ──────────────────────────────────── -->
<?php if (!empty($reviewQueue)): ?>
<div class="section-title">🔔 審査待ち加盟店</div>
<div class="card">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>加盟店コード</th>
            <th>加盟店名</th>
            <th>ステータス</th>
            <th>登録日</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($reviewQueue as $m): ?>
          <tr>
            <td><code><?= htmlspecialchars($m['code']) ?></code></td>
            <td><?= htmlspecialchars($m['name']) ?></td>
            <td><span class="badge badge-<?= $m['status'] ?>"><?= htmlspecialchars($m['status']) ?></span></td>
            <td><?= date('Y/m/d', strtotime($m['created_at'])) ?></td>
            <td>
              <a href="?page=merchants&action=detail&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline">詳細 →</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── 直近の決済 ──────────────────────────────────── -->
<div class="section-title">🕒 直近の決済</div>
<div class="card">
  <div class="card-header">
    <h3>直近10件</h3>
    <a href="?page=payments" class="btn btn-sm btn-outline">すべて見る →</a>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>決済コード</th>
            <th>加盟店</th>
            <th>通貨</th>
            <th>暗号通貨額</th>
            <th>JPY換算</th>
            <th>ステータス</th>
            <th>日時</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:24px">決済データがありません</td></tr>
        <?php else: ?>
          <?php foreach ($recent as $p):
            $cur  = $p['currency'];
            $meta = $currencyLabel[$cur] ?? ['icon'=>'?','name'=>$cur,'color'=>'#999'];
          ?>
          <tr>
            <td><code><?= htmlspecialchars($p['payment_code']) ?></code></td>
            <td><?= htmlspecialchars($p['merchant_name'] ?? '—') ?></td>
            <td>
              <span class="badge badge-currency" style="background:<?= $meta['color'] ?>22;color:<?= $meta['color'] ?>">
                <?= $meta['icon'] ?> <?= htmlspecialchars($cur) ?>
              </span>
            </td>
            <td><?= $fmtCrypto((float)$p['amount_crypto'], $cur) ?> <?= htmlspecialchars($cur) ?></td>
            <td><?= $fmtJpy((float)$p['amount_jpy']) ?></td>
            <td><span class="badge badge-<?= $p['status'] ?>"><?= htmlspecialchars($p['status']) ?></span></td>
            <td style="white-space:nowrap"><?= date('m/d H:i', strtotime($p['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
