<?php declare(strict_types=1);
/**
 * 為替レート View
 * Variables: $rates (array), $markup (float)
 */

$currencyLabel = [
    'BTC'        => ['icon'=>'₿',  'name'=>'Bitcoin',       'color'=>'#f7931a'],
    'ETH'        => ['icon'=>'Ξ',  'name'=>'Ethereum',      'color'=>'#627eea'],
    'XRP'        => ['icon'=>'✕',  'name'=>'XRP',           'color'=>'#346aa9'],
    'USDT_ERC20' => ['icon'=>'₮',  'name'=>'USDT (ERC-20)', 'color'=>'#26a17b'],
    'USDT_TRC20' => ['icon'=>'₮',  'name'=>'USDT (TRC-20)', 'color'=>'#26a17b'],
    'SOL'        => ['icon'=>'◎',  'name'=>'Solana',        'color'=>'#9945ff'],
];

$fmtJpy = fn(float $v): string => '¥' . number_format((int)$v);
?>

<div class="page-actions">
  <span class="text-muted" style="font-size:13px">
    レートは3ソース（CoinGecko・Binance・Kraken）から取得し平均します。
    表示マークアップ: +<?= number_format($markup, 2) ?>%
  </span>
  <a href="?page=rates" class="btn btn-outline btn-sm">🔄 再取得</a>
</div>

<!-- Rate cards -->
<div class="rate-grid" style="margin-top:16px">
<?php foreach ($rates as $currency => $rate):
  $meta    = $currencyLabel[$currency] ?? ['icon'=>'?','name'=>$currency,'color'=>'#999'];
  $avg     = $rate['avg'];
  $sources = $rate['sources'];
  $count   = $rate['source_count'];
  $markupAmt = $avg * $markup / 100;
  $displayRate = $avg + $markupAmt;
?>
  <div class="rate-card" style="padding:20px">
    <div class="rate-label" style="margin-bottom:12px">
      <span class="crypto-icon" style="background:<?= $meta['color'] ?>22;color:<?= $meta['color'] ?>;width:36px;height:36px;font-size:18px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%">
        <?= $meta['icon'] ?>
      </span>
      <span style="font-size:16px;font-weight:600;margin-left:8px"><?= htmlspecialchars($meta['name']) ?></span>
      <code style="margin-left:6px;font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($currency) ?></code>
    </div>

    <?php if ($avg > 0): ?>
    <div style="margin-bottom:16px">
      <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">平均レート (<?= $count ?>ソース)</div>
      <div class="rate-value" style="font-size:28px;font-weight:700"><?= $fmtJpy($avg) ?></div>
      <?php if ($markup > 0): ?>
      <div style="font-size:13px;color:var(--text-muted);margin-top:4px">
        顧客提示レート (+<?= number_format($markup, 2) ?>%): <strong><?= $fmtJpy($displayRate) ?></strong>
      </div>
      <?php endif; ?>
    </div>

    <!-- Source breakdown -->
    <div style="border-top:1px solid var(--border);padding-top:12px">
      <div style="font-size:11px;color:var(--text-muted);margin-bottom:8px">ソース別レート</div>
      <?php foreach ($sources as $src => $val): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
        <span style="font-size:12px;color:var(--text-secondary)"><?= htmlspecialchars($src) ?></span>
        <span style="font-size:13px;font-weight:500"><?= $fmtJpy($val) ?></span>
      </div>
      <?php endforeach; ?>

      <?php if ($count >= 2): ?>
      <?php
        $vals = array_values($sources);
        $spread = max($vals) - min($vals);
        $spreadPct = $avg > 0 ? ($spread / $avg * 100) : 0;
      ?>
      <div style="margin-top:8px;font-size:11px;color:var(--text-muted)">
        スプレッド: ¥<?= number_format($spread, 0) ?>
        (<?= number_format($spreadPct, 3) ?>%)
      </div>
      <?php endif; ?>
    </div>

    <?php else: ?>
    <div style="color:var(--text-muted);font-size:14px;padding:12px 0">
      ⚠️ レート取得失敗 — 外部APIに接続できませんでした
    </div>
    <?php endif; ?>

    <div style="font-size:11px;color:var(--text-muted);margin-top:12px">
      取得時刻: <?= htmlspecialchars($rate['fetched_at']) ?> JST
    </div>
  </div>
<?php endforeach; ?>
</div>

<!-- Source info -->
<div class="card" style="margin-top:24px">
  <div class="card-header"><h3>📡 レートソース情報</h3></div>
  <div class="card-body">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>ソース</th>
            <th>エンドポイント</th>
            <th>対応通貨</th>
            <th>更新頻度</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>CoinGecko</strong></td>
            <td><code>api.coingecko.com/api/v3/simple/price</code></td>
            <td>BTC, ETH, XRP, USDT, SOL</td>
            <td>リアルタイム（無料プラン: ~30秒）</td>
          </tr>
          <tr>
            <td><strong>Binance</strong></td>
            <td><code>api.binance.com/api/v3/ticker/price</code></td>
            <td>BTC, ETH, XRP, SOL, USDT</td>
            <td>リアルタイム</td>
          </tr>
          <tr>
            <td><strong>Kraken</strong></td>
            <td><code>api.kraken.com/0/public/Ticker</code></td>
            <td>BTC (XBT), ETH, XRP, SOL</td>
            <td>リアルタイム</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="alert alert-info" style="margin-top:16px">
      <strong>注意:</strong> USDT (ERC-20/TRC-20) のレートはCoinGeckoの "tether" IDとBinanceのUSDT/JPYペアから取得します。
      Krakenは現時点でUSDTのJPYペアを提供していないため、2ソース平均になる場合があります。
    </div>
  </div>
</div>
