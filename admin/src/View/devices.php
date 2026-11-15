<?php
$pageTitle = 'POS端末管理';
ob_start();
?>
<div class="page-actions">
  <button class="btn btn-primary" onclick="document.getElementById('deviceModal').hidden=false">＋ 端末登録</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>端末コード</th><th>端末名</th><th>加盟店</th>
          <th>モデル</th><th>設置場所</th><th>ステータス</th>
          <th>最終接続</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($devices as $d): ?>
      <tr>
        <td><code><?= htmlspecialchars($d['device_code']) ?></code></td>
        <td><?= htmlspecialchars($d['name']) ?></td>
        <td><?= htmlspecialchars($d['merchant_name']) ?></td>
        <td><?= htmlspecialchars($d['model'] ?? 'ZCS Z108') ?></td>
        <td><?= htmlspecialchars($d['location'] ?? '—') ?></td>
        <td><span class="badge badge-<?= $d['status'] ?>"><?= $d['status'] ?></span></td>
        <td><?= $d['last_seen_at'] ? date('m/d H:i', strtotime($d['last_seen_at'])) : '—' ?></td>
        <td>
          <button class="btn btn-sm" onclick="showToken(<?= $d['id'] ?>,<?= htmlspecialchars("'".$d['api_token']."'",ENT_QUOTES) ?>)">
            APIトークン
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($devices)): ?>
      <tr><td colspan="8" class="text-center text-muted">データがありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- New device modal -->
<div id="deviceModal" class="modal" hidden>
  <div class="modal-backdrop" onclick="document.getElementById('deviceModal').hidden=true"></div>
  <div class="modal-box">
    <div class="modal-header">
      <h3>端末登録</h3>
      <button onclick="document.getElementById('deviceModal').hidden=true" class="modal-close">✕</button>
    </div>
    <form id="deviceForm" class="modal-body">
      <?= Auth::csrfInput() ?>
      <div class="form-group">
        <label>加盟店 <span class="req">*</span></label>
        <select name="merchant_id" required>
          <option value="">選択</option>
          <?php foreach ($merchants as $m): ?>
          <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>端末名 <span class="req">*</span></label>
        <input type="text" name="name" required placeholder="例: レジ1">
      </div>
      <div class="form-group">
        <label>モデル</label>
        <input type="text" name="model" value="ZCS Z108">
      </div>
      <div class="form-group">
        <label>設置場所</label>
        <input type="text" name="location" placeholder="例: 1Fフロント">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('deviceModal').hidden=true">キャンセル</button>
        <button type="submit" class="btn btn-primary">登録</button>
      </div>
    </form>
  </div>
</div>

<!-- Token modal -->
<div id="tokenModal" class="modal" hidden>
  <div class="modal-backdrop" onclick="document.getElementById('tokenModal').hidden=true"></div>
  <div class="modal-box modal-sm">
    <div class="modal-header">
      <h3>APIトークン</h3>
      <button onclick="document.getElementById('tokenModal').hidden=true" class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <p class="text-muted text-sm">端末認証に使用します。第三者に見せないでください。</p>
      <div class="token-display">
        <code id="tokenValue"></code>
        <button class="btn btn-sm" onclick="copyToken()">コピー</button>
      </div>
      <input type="hidden" id="tokenDeviceId">
      <div class="modal-footer">
        <button class="btn btn-danger" onclick="regenToken()">トークン再発行</button>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('deviceForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const r = await fetch('index.php?page=device-create', {method:'POST', body:new FormData(this)});
  const j = await r.json();
  if (j.success) { showToast(j.message,'success'); setTimeout(()=>location.reload(),700); }
  else showToast(j.message,'error');
});

function showToken(id, token) {
  document.getElementById('tokenValue').textContent = token;
  document.getElementById('tokenDeviceId').value = id;
  document.getElementById('tokenModal').hidden=false;
}

function copyToken() {
  navigator.clipboard.writeText(document.getElementById('tokenValue').textContent)
    .then(()=>showToast('コピーしました','success'))
    .catch(()=>showToast('コピー失敗','error'));
}

async function regenToken() {
  if (!confirm('APIトークンを再発行しますか？既存の端末は再接続が必要になります。')) return;
  const fd = new FormData();
  fd.append('id', document.getElementById('tokenDeviceId').value);
  fd.append('_csrf', document.querySelector('[name="_csrf"]').value);
  const r = await fetch('index.php?page=device-regen', {method:'POST', body:fd});
  const j = await r.json();
  if (j.success) {
    document.getElementById('tokenValue').textContent = j.token;
    showToast(j.message,'success');
  }
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
