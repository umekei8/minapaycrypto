<?php
$pageTitle = '為替レート';
ob_start();
$feePercent    = $settings['default_fee_percent'] ?? '6.50';
$markupPercent = $settings['rate_markup_percent'] ?? '1.00';
?>
<div class="kpi-grid" id="rateCards">
  <div class="kpi-card" id="rate-BTC">
    <div class="kpi-label">BTC / JPY</div>
    <div class="kpi-value rate-value">読込中…</div>
    <div class="kpi-sub rate-sub"></div>
  </div>
  <div class="kpi-card" id="rate-ETH">
    <div class="kpi-label">ETH / JPY</div>
    <div class="kpi-value rate-value">読込中…</div>
    <div class="kpi-sub rate-sub"></div>
  </div>
  <div class="kpi-card" id="rate-XRP">
    <div class="kpi-label">XRP / JPY</div>
    <div class="kpi-value rate-value">読込中…</div>
    <div class="kpi-sub rate-sub"></div>
  </div>
  <div class="kpi-card" id="rate-USDT">
    <div class="kpi-label">USDT / JPY</div>
    <div class="kpi-value rate-value">読込中…</div>
    <div class="kpi-sub rate-sub"></div>
  </div>
</div>

<div class="card" style="max-width:480px">
  <div class="card-header"><h3>手数料・マークアップ設定</h3></div>
  <form id="feeForm" class="card-body">
    <?= Auth::csrfInput() ?>
    <div class="form-group">
      <label>デフォルト加盟店手数料 (%)</label>
      <input type="number" name="default_fee_percent" value="<?= htmlspecialchars($feePercent) ?>"
             step="0.01" min="0" max="100">
      <small class="form-hint">加盟店ごとに上書き可能</small>
    </div>
    <div class="form-group">
      <label>レートマークアップ (%)</label>
      <input type="number" name="rate_markup_percent" value="<?= htmlspecialchars($markupPercent) ?>"
             step="0.01" min="0" max="20">
      <small class="form-hint">CoinGeckoレートに上乗せして顧客に表示する率</small>
    </div>
    <div class="form-group">
      <label>QR有効期限 (分)</label>
      <input type="number" name="payment_timeout_minutes"
             value="<?= htmlspecialchars($settings['payment_timeout_minutes'] ?? '15') ?>" min="1" max="60">
    </div>
    <button type="submit" class="btn btn-primary">保存</button>
  </form>
</div>

<script>
// Fetch live rates from CoinGecko
async function fetchRates() {
  try {
    const r = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum,ripple,tether&vs_currencies=jpy');
    const d = await r.json();
    const map = {BTC: d.bitcoin?.jpy, ETH: d.ethereum?.jpy, XRP: d.ripple?.jpy, USDT: d.tether?.jpy};
    const markup = <?= (float)$markupPercent ?> / 100;
    for (const [sym, raw] of Object.entries(map)) {
      const card = document.getElementById('rate-'+sym);
      if (!card || !raw) continue;
      const applied = raw * (1 + markup);
      card.querySelector('.rate-value').textContent = '¥' + Number(applied.toFixed(0)).toLocaleString();
      card.querySelector('.rate-sub').textContent = 'CoinGecko: ¥' + Number(raw.toFixed(0)).toLocaleString();
    }
  } catch(e) {
    document.querySelectorAll('.rate-value').forEach(el=>el.textContent='取得失敗');
  }
}
fetchRates();
setInterval(fetchRates, 60000);

document.getElementById('feeForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const r = await fetch('index.php?page=settings-save', {method:'POST', body:new FormData(this)});
  const j = await r.json();
  showToast(j.message, j.success?'success':'error');
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
