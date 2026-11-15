<?php
$pageTitle = 'ウォレット管理';
ob_start();
?>
<div class="notice notice-info">
  🔒 ノンカストディ設計：<strong>受取アドレスのみ</strong>を管理します。秘密鍵は保存しません。
</div>

<div class="page-actions">
  <button class="btn btn-primary" onclick="document.getElementById('walletModal').hidden=false">＋ アドレス登録</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>加盟店</th><th>通貨</th><th>受取アドレス</th>
          <th>ラベル</th><th>ステータス</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($wallets as $w): ?>
      <tr>
        <td><?= htmlspecialchars($w['merchant_name']) ?></td>
        <td><span class="badge badge-currency"><?= htmlspecialchars($w['currency']) ?></span></td>
        <td class="font-mono" style="word-break:break-all;font-size:0.8em">
          <?= htmlspecialchars($w['address']) ?>
        </td>
        <td><?= htmlspecialchars($w['label'] ?? '—') ?></td>
        <td><span class="badge badge-<?= $w['status'] ?>"><?= $w['status'] ?></span></td>
        <td>
          <button class="btn btn-sm" onclick="editWallet(<?= htmlspecialchars(json_encode($w),ENT_QUOTES) ?>)">編集</button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($wallets)): ?>
      <tr><td colspan="6" class="text-center text-muted">データがありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Wallet modal -->
<div id="walletModal" class="modal" hidden>
  <div class="modal-backdrop" onclick="document.getElementById('walletModal').hidden=true"></div>
  <div class="modal-box">
    <div class="modal-header">
      <h3>ウォレットアドレス登録</h3>
      <button onclick="document.getElementById('walletModal').hidden=true" class="modal-close">✕</button>
    </div>
    <form id="walletForm" class="modal-body">
      <?= Auth::csrfInput() ?>
      <div class="form-group">
        <label>加盟店 <span class="req">*</span></label>
        <select name="merchant_id" required>
          <option value="">選択してください</option>
          <?php foreach ($merchants as $m): ?>
          <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>通貨 <span class="req">*</span></label>
        <select name="currency" required>
          <?php foreach (['BTC','ETH','XRP','USDT_ERC20','USDT_TRC20','USDT_BEP20'] as $c): ?>
          <option value="<?= $c ?>"><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>受取アドレス <span class="req">*</span></label>
        <input type="text" name="address" required placeholder="受取専用アドレスのみ入力">
        <small class="form-hint text-danger">⚠ 秘密鍵は絶対に入力しないでください</small>
      </div>
      <div class="form-group">
        <label>ラベル</label>
        <input type="text" name="label" placeholder="例: メインウォレット">
      </div>
      <div class="form-group">
        <label>ステータス</label>
        <select name="status">
          <option value="active">active</option>
          <option value="inactive">inactive</option>
        </select>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('walletModal').hidden=true">キャンセル</button>
        <button type="submit" class="btn btn-primary">保存</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('walletForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const r = await fetch('index.php?page=wallet-save', {method:'POST', body:new FormData(this)});
  const j = await r.json();
  if (j.success) { showToast(j.message,'success'); setTimeout(()=>location.reload(),700); }
  else showToast(j.message,'error');
});

function editWallet(w) {
  const f = document.getElementById('walletForm');
  f.merchant_id.value = w.merchant_id;
  f.currency.value = w.currency;
  f.address.value = w.address;
  f.label.value = w.label||'';
  f.status.value = w.status;
  document.getElementById('walletModal').hidden=false;
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
