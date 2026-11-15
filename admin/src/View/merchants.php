<?php
$pageTitle = '加盟店管理';
ob_start();
?>
<div class="page-actions">
  <button class="btn btn-primary" onclick="openModal('merchantModal')">＋ 新規加盟店</button>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table table-hover">
      <thead>
        <tr>
          <th>コード</th><th>加盟店名</th><th>メール</th>
          <th>手数料</th><th>ステータス</th><th>登録日</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($merchants as $m): ?>
      <tr onclick="loadMerchantDetail(<?= $m['id'] ?>)" style="cursor:pointer">
        <td><code><?= htmlspecialchars($m['code']) ?></code></td>
        <td><?= htmlspecialchars($m['name']) ?></td>
        <td><?= htmlspecialchars($m['email']) ?></td>
        <td><?= htmlspecialchars($m['fee_percent']) ?>%</td>
        <td><span class="badge badge-<?= $m['status'] ?>"><?= $m['status'] ?></span></td>
        <td><?= date('Y/m/d', strtotime($m['created_at'])) ?></td>
        <td><button class="btn btn-sm" onclick="event.stopPropagation();editMerchant(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)">編集</button></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($merchants)): ?>
      <tr><td colspan="7" class="text-center text-muted">データがありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- New merchant modal -->
<div id="merchantModal" class="modal" hidden>
  <div class="modal-backdrop" onclick="closeModal('merchantModal')"></div>
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="merchantModalTitle">新規加盟店登録</h3>
      <button onclick="closeModal('merchantModal')" class="modal-close">✕</button>
    </div>
    <form id="merchantForm" class="modal-body">
      <?= Auth::csrfInput() ?>
      <input type="hidden" name="id" id="merchantId">
      <div class="form-row">
        <div class="form-group">
          <label>加盟店名 <span class="req">*</span></label>
          <input type="text" name="name" required>
        </div>
        <div class="form-group">
          <label>スラッグ <span class="req">*</span></label>
          <input type="text" name="slug" placeholder="英数字-記号">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>メール <span class="req">*</span></label>
          <input type="email" name="email" required>
        </div>
        <div class="form-group">
          <label>電話</label>
          <input type="text" name="phone">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>手数料(%)</label>
          <input type="number" name="fee_percent" value="6.50" step="0.01" min="0" max="100">
        </div>
        <div class="form-group" id="statusGroup" hidden>
          <label>ステータス</label>
          <select name="status">
            <option value="active">active</option>
            <option value="suspended">suspended</option>
          </select>
        </div>
      </div>
      <div class="form-group">
        <label>メモ</label>
        <textarea name="memo" rows="2"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('merchantModal')">キャンセル</button>
        <button type="submit" class="btn btn-primary" id="merchantSubmit">登録</button>
      </div>
    </form>
  </div>
</div>

<script>
const merchantForm = document.getElementById('merchantForm');
let merchantEditMode = false;

function editMerchant(m) {
  merchantEditMode = true;
  document.getElementById('merchantModalTitle').textContent = '加盟店編集';
  document.getElementById('merchantSubmit').textContent = '更新';
  document.getElementById('statusGroup').hidden = false;
  const f = merchantForm;
  f.id.value = m.id;
  f.name.value = m.name;
  f.slug && (f.slug.value = m.slug||'');
  f.email.value = m.email;
  f.phone.value = m.phone||'';
  f.fee_percent.value = m.fee_percent;
  f.status.value = m.status;
  f.memo.value = m.memo||'';
  openModal('merchantModal');
}

merchantForm.addEventListener('submit', async function(e) {
  e.preventDefault();
  const data = new FormData(this);
  const url = merchantEditMode ? 'index.php?page=merchant-update' : 'index.php?page=merchant-create';
  const r = await fetch(url, {method:'POST', body:data});
  const j = await r.json();
  if (j.success) { showToast(j.message, 'success'); closeModal('merchantModal'); setTimeout(()=>location.reload(),700); }
  else showToast(j.message, 'error');
});

document.getElementById('merchantModal').addEventListener('click', function(e) {
  if (!e.target.closest('.modal-box')) return;
});
// Reset on open
function openModal(id) {
  if (id==='merchantModal' && !merchantEditMode) {
    merchantForm.reset();
    document.getElementById('merchantId').value='';
    document.getElementById('merchantModalTitle').textContent='新規加盟店登録';
    document.getElementById('merchantSubmit').textContent='登録';
    document.getElementById('statusGroup').hidden=true;
    merchantEditMode=false;
  }
  document.getElementById(id).hidden=false;
}
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
