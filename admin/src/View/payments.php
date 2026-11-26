<?php declare(strict_types=1);
/**
 * 決済一覧 View
 * Variables: $payments, $total, $page, $limit, $totalPages,
 *            $q, $status, $currency, $merchantId, $dateFrom, $dateTo, $merchants
 */

$statusLabels = [
    'pending'   => ['label'=>'待機中',   'class'=>'pending'],
    'confirmed' => ['label'=>'確認済',   'class'=>'active'],
    'expired'   => ['label'=>'期限切れ', 'class'=>'suspended'],
    'failed'    => ['label'=>'失敗',     'class'=>'rejected'],
];

$currencyLabel = [
    'BTC'        => ['icon'=>'₿', 'color'=>'#f7931a'],
    'ETH'        => ['icon'=>'Ξ', 'color'=>'#627eea'],
    'XRP'        => ['icon'=>'✕', 'color'=>'#346aa9'],
    'USDT_ERC20' => ['icon'=>'₮', 'color'=>'#26a17b'],
    'USDT_TRC20' => ['icon'=>'₮', 'color'=>'#1db999'],
    'SOL'        => ['icon'=>'◎', 'color'=>'#9945ff'],
];

$fmtJpy    = fn(float $v): string => '¥' . number_format((int)$v);
$fmtCrypto = function(float $v, string $cur): string {
    if (in_array($cur, ['USDT_ERC20','USDT_TRC20'])) return number_format($v, 2);
    if ($cur === 'BTC') return number_format($v, 6);
    return number_format($v, 4);
};

$csvParams = http_build_query([
    'page' => 'payments', 'q' => $q, 'status' => $status,
    'currency' => $currency, 'merchant_id' => $merchantId,
    'date_from' => $dateFrom, 'date_to' => $dateTo, 'csv' => 1,
]);
?>

<!-- Page actions -->
<div class="page-actions">
  <span class="text-muted" style="font-size:13px">全 <?= number_format($total) ?> 件</span>
  <a href="index.php?<?= $csvParams ?>" class="btn btn-outline btn-sm">📥 CSV</a>
</div>

<!-- Search bar -->
<form method="get" action="index.php" class="search-bar">
  <input type="hidden" name="page" value="payments">
  <input type="text" name="q" placeholder="決済コード / Txハッシュ" value="<?= htmlspecialchars($q) ?>" style="flex:1;min-width:160px">
  <select name="status">
    <option value="">全ステータス</option>
    <?php foreach ($statusLabels as $val => $info): ?>
    <option value="<?= $val ?>" <?= $status===$val ? 'selected' : '' ?>><?= $info['label'] ?></option>
    <?php endforeach; ?>
  </select>
  <select name="currency">
    <option value="">全通貨</option>
    <?php foreach (array_keys($currencyLabel) as $c): ?>
    <option value="<?= $c ?>" <?= $currency===$c ? 'selected' : '' ?>><?= $c ?></option>
    <?php endforeach; ?>
  </select>
  <select name="merchant_id">
    <option value="">全加盟店</option>
    <?php foreach ($merchants as $m): ?>
    <option value="<?= $m['id'] ?>" <?= $merchantId===$m['id'] ? 'selected' : '' ?>>
      [<?= htmlspecialchars($m['code']) ?>] <?= htmlspecialchars($m['name']) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
  <span style="color:var(--text-muted)">〜</span>
  <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
  <button type="submit" class="btn btn-outline btn-sm">🔍 絞込</button>
  <a href="?page=payments" class="btn btn-outline btn-sm">クリア</a>
</form>

<!-- Table -->
<div class="card" style="margin-top:0">
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
            <th>Txハッシュ</th>
            <th>日時</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($payments)): ?>
          <tr>
            <td colspan="8" class="text-center text-muted" style="padding:40px">
              該当する決済がありません
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($payments as $p):
            $cur  = $p['currency'];
            $meta = $currencyLabel[$cur] ?? ['icon'=>'?','color'=>'#999'];
            $si   = $statusLabels[$p['status']] ?? ['label'=>$p['status'],'class'=>'pending'];
          ?>
          <tr>
            <td><code style="font-size:11px"><?= htmlspecialchars($p['payment_code']) ?></code></td>
            <td>
              <?php if ($p['merchant_name']): ?>
              <a href="?page=merchants&action=detail&id=<?= $p['merchant_id'] ?>" style="color:inherit">
                <?= htmlspecialchars($p['merchant_name']) ?>
              </a>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge" style="background:<?= $meta['color'] ?>22;color:<?= $meta['color'] ?>">
                <?= $meta['icon'] ?> <?= htmlspecialchars($cur) ?>
              </span>
            </td>
            <td style="font-weight:600">
              <?= $fmtCrypto((float)$p['amount_crypto'], $cur) ?>
              <small class="text-muted"><?= htmlspecialchars($cur) ?></small>
            </td>
            <td><?= $fmtJpy((float)$p['amount_jpy']) ?></td>
            <td><span class="badge badge-<?= $si['class'] ?>"><?= $si['label'] ?></span></td>
            <td>
              <?php if ($p['tx_hash']): ?>
              <code style="font-size:10px" title="<?= htmlspecialchars($p['tx_hash']) ?>">
                <?= substr($p['tx_hash'], 0, 12) ?>…
              </code>
              <?php else: ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap"><?= date('m/d H:i', strtotime($p['created_at'])) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
  <a href="?<?= http_build_query(['page'=>'payments','q'=>$q,'status'=>$status,'currency'=>$currency,'p'=>$i]) ?>"
     class="<?= $i===$page ? 'current' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>
