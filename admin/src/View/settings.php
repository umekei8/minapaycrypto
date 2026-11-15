<?php
$pageTitle = 'システム設定';
ob_start();
?>
<div class="tabs" id="settingsTabs">
  <button class="tab active" data-tab="basic">基本設定</button>
  <button class="tab" data-tab="notify">通知設定</button>
  <button class="tab" data-tab="admin">管理者アカウント</button>
</div>

<!-- Basic settings -->
<div class="tab-panel active" id="tab-basic">
  <div class="card" style="max-width:560px">
    <form id="basicForm" class="card-body">
      <?= Auth::csrfInput() ?>
      <div class="form-group">
        <label>サイト名</label>
        <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? 'MinaPay Crypto Admin') ?>">
      </div>
      <div class="form-group">
        <label>対応通貨（カンマ区切り）</label>
        <input type="text" name="supported_currencies"
               value="<?= htmlspecialchars($settings['supported_currencies'] ?? 'BTC,ETH,XRP,USDT') ?>">
      </div>
      <button type="submit" class="btn btn-primary">保存</button>
    </form>
  </div>
</div>

<!-- Notify settings -->
<div class="tab-panel" id="tab-notify">
  <div class="card" style="max-width:560px">
    <form id="notifyForm" class="card-body">
      <?= Auth::csrfInput() ?>
      <div class="form-group">
        <label>通知メールアドレス</label>
        <input type="email" name="notify_email" value="<?= htmlspecialchars($settings['notify_email'] ?? '') ?>">
        <small class="form-hint">決済完了・エラー時に通知を送ります</small>
      </div>
      <button type="submit" class="btn btn-primary">保存</button>
    </form>
  </div>
</div>

<!-- Admin account -->
<div class="tab-panel" id="tab-admin">
  <div class="card" style="max-width:480px">
    <div class="card-header"><h3>パスワード変更</h3></div>
    <form id="pwForm" class="card-body">
      <?= Auth::csrfInput() ?>
      <div class="form-group">
        <label>現在のパスワード</label>
        <input type="password" name="current_password" required>
      </div>
      <div class="form-group">
        <label>新しいパスワード</label>
        <input type="password" name="new_password" required minlength="8">
      </div>
      <div class="form-group">
        <label>新しいパスワード（確認）</label>
        <input type="password" name="new_password_confirm" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary">変更</button>
    </form>
  </div>
</div>

<script>
// Tabs
document.querySelectorAll('.tab').forEach(btn=>{
  btn.addEventListener('click',function(){
    document.querySelectorAll('.tab').forEach(b=>b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('tab-'+this.dataset.tab).classList.add('active');
  });
});

// Generic form submit
['basicForm','notifyForm'].forEach(id=>{
  document.getElementById(id).addEventListener('submit', async function(e){
    e.preventDefault();
    const r = await fetch('index.php?page=settings-save',{method:'POST',body:new FormData(this)});
    const j = await r.json();
    showToast(j.message, j.success?'success':'error');
  });
});

document.getElementById('pwForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const fd = new FormData(this);
  if (fd.get('new_password') !== fd.get('new_password_confirm')) {
    showToast('新しいパスワードが一致しません。','error'); return;
  }
  const r = await fetch('index.php?page=settings-pw',{method:'POST',body:fd});
  const j = await r.json();
  showToast(j.message, j.success?'success':'error');
  if (j.success) this.reset();
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
