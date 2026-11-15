<?php
$pageTitle = '決済履歴';
ob_start();
?>
<!-- Filters -->
<form method="get" class="filter-bar">
  <input type="hidden" name="page" value="payments">
  <input type="text" name="q" placeholder="決済コード・加盟店名" value="<?= htmlspecialchars($filters['q']??'') ?>">
  <select name="status">
    <option value="">全ステータス</option>
    <?php foreach (['pending','confirmed','failed','expired'] as $s): ?>
    <option value="<?= $s ?>" <?= ($filters['status']??'')===$s?'selected':'' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <select name="currency">
    <option value="">全通貨</option>
    <?php foreach (['BTC','ETH','XRP','USDT'] as $c): ?>
    <option value="<?= $c ?>" <?= ($filters['currency']??'')===$c?'selected':'' ?>><?= $c ?></option>
    <?php endforeach; ?>
  </select>
  <select name="merchant_id">
    <option value="">全加盟店</option>
    <?php foreach ($merchants as $m): ?>
    <option value="<?= $m['id'] ?>" <?= ($filters['merchant_id']??'')==$m['id']?'selected':'' ?>>
      <?= htmlspecialchars($m['name']) ?>
    </option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']??'') ?>">
  <span>〜</span>
  <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']??'') ?>">
  <button type="submit" class="btn btn-primary">検索</button>
  <a href="index.php?page=payments" class="btn btn-outline">クリア</a>
</form>

<div class="card">
  <div class="card-header">
    <h3>決済一覧 <small class="text-muted"><?= number_format($total) ?> 件</small></h3>
  </div>
  <div class="table-wrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>決済コード</th><th>加盟店</th><th>通貨</th>
          <th>暗号資産額</th><th>JPY換算</th><th>ステータス</th>
          <th>受取アドレス</th><th>日時</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
      <tr>
        <td><code><?= htmlspecialchars($p['payment_code']) ?></code></td>
        <td><?= htmlspecialchars($p['merchant_name']) ?></td>
        <td><span class="badge badge-currency"><?= htmlspecialchars($p['currency']) ?></span></td>
        <td><?= htmlspecialchars($p['crypto_amount'] ?? '—') ?></td>
        <td>¥<?= number_format((float)($p['amount_jpy']??0)) ?></td>
        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] ?></span></td>
        <td class="addr-cell" title="<?= htmlspecialchars($p['receiving_address']??'') ?>">
          <?= htmlspecialchars(substr($p['receiving_address']??'',0,12)) ?>…
        </td>
        <td><?= date('m/d H:i', strtotime($p['created_at'])) ?></td>
        <td>
          <button class="btn btn-sm" onclick="showPaymentDetail(<?= $p['id'] ?>)">詳細</button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($payments)): ?>
      <tr><td colspan="9" class="text-center text-muted">データがありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i=1;$i<=$totalPages;$i++): ?>
  <a href="?<?= http_build_query(array_merge($_GET,['p'=>$i])) ?>"
     class="page-link <?= $page===$i?'active':'' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<!-- Detail modal -->
<div id="paymentModal" class="modal" hidden>
  <div class="modal-backdrop" onclick="closeModal('paymentModal')"></div>
  <div class="modal-box">
    <div class="modal-header">
      <h3>決済詳細</h3>
      <button onclick="closeModal('paymentModal')" class="modal-close">✕</button>
    </div>
    <div class="modal-body" id="paymentDetail"></div>
  </div>
</div>

<script>
function showPaymentDetail(id) {
  fetch('index.php?page=payment-detail&id='+id)
    .then(r=>r.json())
    .then(p=>{
      const el = document.getElementById('paymentDetail');
      el.innerHTML = `
        <dl class="detail-list">
          <dt>決済コード</dt><dd>${esc(p.payment_code)}</dd>
          <dt>加盟店</dt><dd>${esc(p.merchant_name)}</dd>
          <dt>通貨</dt><dd>${esc(p.currency)}</dd>
          <dt>JPY換算</dt><dd>¥${Number(p.amount_jpy).toLocaleString()}</dd>
          <dt>暗号資産額</dt><dd>${esc(p.crypto_amount||'—')}</dd>
          <dt>為替レート</dt><dd>${esc(p.exchange_rate||'—')}</dd>
          <dt>手数料(%)</dt><dd>${esc(p.fee_percent||'—')}</dd>
          <dt>ステータス</dt><dd>${esc(p.status)}</dd>
          <dt>受取アドレス</dt><dd style="word-break:break-all">${esc(p.receiving_address||'—')}</dd>
          <dt>TXハッシュ</dt><dd style="word-break:break-all">${esc(p.tx_hash||'—')}</dd>
          <dt>QR有効期限</dt><dd>${esc(p.expires_at||'—')}</dd>
          <dt>作成日時</dt><dd>${esc(p.created_at||'—')}</dd>
        </dl>`;
      document.getElementById('paymentModal').hidden=false;
    });
}
function esc(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
