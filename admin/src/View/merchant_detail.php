<?php declare(strict_types=1);
/**
 * 加盟店詳細 View
 * Variables: $merchant, $wallets, $devices, $payments
 */

$statusLabels = [
    'applied'   => ['label'=>'新規申込','class'=>'applied'],
    'reviewing' => ['label'=>'審査中',  'class'=>'reviewing'],
    'active'    => ['label'=>'審査通過(Active)','class'=>'active'],
    'suspended' => ['label'=>'停止中',  'class'=>'suspended'],
    'rejected'  => ['label'=>'否決',    'class'=>'rejected'],
];
$currencies = ['BTC','ETH','XRP','USDT_ERC20','USDT_TRC20'];

$si = $statusLabels[$merchant['status']] ?? ['label'=>$merchant['status'],'class'=>'pending'];
?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap">
  <a href="?page=merchants" class="btn btn-outline btn-sm">← 一覧に戻る</a>
  <span class="badge badge-<?= $si['class'] ?>" style="font-size:13px;padding:4px 12px"><?= $si['label'] ?></span>
  <span style="color:var(--text-muted);font-size:12px"><code><?= htmlspecialchars($merchant['code']) ?></code></span>
  <div style="margin-left:auto" class="btn-group">
    <?php if ($merchant['status'] !== 'active'): ?>
    <button class="btn btn-success btn-sm" onclick="changeStatus('active')">✓ 審査通過</button>
    <?php endif; ?>
    <?php if ($merchant['status'] === 'active'): ?>
    <button class="btn btn-warning btn-sm" onclick="changeStatus('suspended')">停止</button>
    <?php else: ?>
    <button class="btn btn-info btn-sm" onclick="changeStatus('reviewing')">審査中に変更</button>
    <?php endif; ?>
    <button class="btn btn-danger btn-sm" onclick="changeStatus('rejected')">否決</button>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab-btn active" data-tab-group="detail" data-tab="info" onclick="switchTab('detail','info')">加盟店情報</button>
  <button class="tab-btn" data-tab-group="detail" data-tab="contract" onclick="switchTab('detail','contract')">契約条件</button>
  <button class="tab-btn" data-tab-group="detail" data-tab="wallet" onclick="switchTab('detail','wallet')">ウォレット設定</button>
  <button class="tab-btn" data-tab-group="detail" data-tab="device" onclick="switchTab('detail','device')">端末設定</button>
  <button class="tab-btn" data-tab-group="detail" data-tab="payments" onclick="switchTab('detail','payments')">決済履歴</button>
</div>

<!-- ── 加盟店情報 ─────────────────────────────────── -->
<div class="tab-pane active" data-tabpane-group="detail" data-pane="info">
  <div class="card">
    <div class="card-header">
      <h3>基本情報</h3>
      <button class="btn btn-sm btn-outline" onclick="openEditModal(<?= htmlspecialchars(json_encode($merchant)) ?>)">✏️ 編集</button>
    </div>
    <div class="card-body">
      <div class="detail-grid">
        <div class="detail-label">加盟店名</div>
        <div class="detail-value"><strong><?= htmlspecialchars($merchant['name']) ?></strong></div>

        <div class="detail-label">フリガナ</div>
        <div class="detail-value"><?= htmlspecialchars($merchant['name_kana'] ?? '—') ?></div>

        <div class="detail-label">事業者区分</div>
        <div class="detail-value"><?= $merchant['business_type'] === 'corporation' ? '法人' : '個人事業主' ?></div>

        <div class="detail-label">代表者名</div>
        <div class="detail-value"><?= htmlspecialchars($merchant['representative'] ?? '—') ?></div>

        <div class="detail-label">メール</div>
        <div class="detail-value"><?= htmlspecialchars($merchant['email']) ?></div>

        <div class="detail-label">電話番号</div>
        <div class="detail-value"><?= htmlspecialchars($merchant['phone'] ?? '—') ?></div>

        <div class="detail-label">郵便番号</div>
        <div class="detail-value"><?= htmlspecialchars($merchant['postal_code'] ?? '—') ?></div>

        <div class="detail-label">住所</div>
        <div class="detail-value"><?= htmlspecialchars($merchant['address'] ?? '—') ?></div>

        <div class="detail-label">Webサイト</div>
        <div class="detail-value">
          <?php if ($merchant['website']): ?>
          <a href="<?= htmlspecialchars($merchant['website']) ?>" target="_blank"><?= htmlspecialchars($merchant['website']) ?></a>
          <?php else: ?>—<?php endif; ?>
        </div>

        <div class="detail-label">業種</div>
        <div class="detail-value"><?= htmlspecialchars($merchant['business_category'] ?? '—') ?></div>

        <div class="detail-label">APIトークン</div>
        <div class="detail-value"><code style="font-size:11px"><?= htmlspecialchars($merchant['api_token']) ?></code></div>

        <div class="detail-label">登録日</div>
        <div class="detail-value"><?= date('Y/m/d H:i', strtotime($merchant['created_at'])) ?></div>
      </div>

      <?php if ($merchant['memo']): ?>
      <div style="margin-top:16px">
        <div class="section-title">メモ</div>
        <div style="background:#f9fafb;padding:12px;border-radius:6px;font-size:13px;white-space:pre-wrap"><?= htmlspecialchars($merchant['memo']) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- 審査情報 -->
  <div class="card">
    <div class="card-header"><h3>審査情報</h3></div>
    <div class="card-body">
      <div class="detail-grid">
        <div class="detail-label">ステータス</div>
        <div class="detail-value"><span class="badge badge-<?= $si['class'] ?>"><?= $si['label'] ?></span></div>

        <div class="detail-label">審査者</div>
        <div class="detail-value"><?= htmlspecialchars($merchant['reviewer_name'] ?? '—') ?></div>

        <div class="detail-label">審査日時</div>
        <div class="detail-value"><?= $merchant['reviewed_at'] ? date('Y/m/d H:i', strtotime($merchant['reviewed_at'])) : '—' ?></div>

        <div class="detail-label">審査メモ</div>
        <div class="detail-value"><?= htmlspecialchars($merchant['review_note'] ?? '—') ?></div>
      </div>
    </div>
  </div>

  <!-- ポータルアカウント -->
  <div class="card">
    <div class="card-header"><h3>加盟店ポータルアカウント</h3></div>
    <div class="card-body">
      <?php if ($merchant['portal_enabled']): ?>
      <div class="alert alert-success">ポータルアカウント発行済み</div>
      <div class="detail-grid">
        <div class="detail-label">ログインメール</div>
        <div class="detail-value"><code><?= htmlspecialchars($merchant['portal_email']) ?></code></div>
        <div class="detail-label">ステータス</div>
        <div class="detail-value"><span class="badge badge-active">有効</span></div>
      </div>
      <?php else: ?>
      <div class="alert alert-warning">ポータルアカウント未発行。審査通過時に自動発行されます。</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── 契約条件 ─────────────────────────────────── -->
<div class="tab-pane" data-tabpane-group="detail" data-pane="contract">
  <div class="card">
    <div class="card-header">
      <h3>契約条件</h3>
      <button class="btn btn-sm btn-outline" onclick="openEditModal(<?= htmlspecialchars(json_encode($merchant)) ?>, 'contract')">✏️ 編集</button>
    </div>
    <div class="card-body">
      <div class="detail-grid">
        <div class="detail-label">加盟店手数料率</div>
        <div class="detail-value"><strong><?= number_format((float)$merchant['fee_percent'], 2) ?>%</strong></div>

        <div class="detail-label">契約開始日</div>
        <div class="detail-value"><?= $merchant['contract_start'] ? date('Y/m/d', strtotime($merchant['contract_start'])) : '—' ?></div>

        <div class="detail-label">契約終了日</div>
        <div class="detail-value"><?= $merchant['contract_end'] ? date('Y/m/d', strtotime($merchant['contract_end'])) : '無期限' ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ── ウォレット設定 ─────────────────────────────── -->
<div class="tab-pane" data-tabpane-group="detail" data-pane="wallet">
  <div class="card">
    <div class="card-header">
      <h3>受取ウォレットアドレス</h3>
      <button class="btn btn-sm btn-primary" onclick="openWalletModal()">＋ 追加 / 編集</button>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>通貨</th><th>ネットワーク</th><th>アドレス</th><th>ラベル</th><th>設定者</th><th>ステータス</th></tr>
          </thead>
          <tbody>
          <?php if (empty($wallets)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:24px">ウォレットアドレスが設定されていません</td></tr>
          <?php else: ?>
            <?php foreach ($wallets as $w): ?>
            <tr>
              <td><span class="badge badge-currency"><?= htmlspecialchars($w['currency']) ?></span></td>
              <td><?= htmlspecialchars($w['network'] ?? '—') ?></td>
              <td><code style="font-size:11px;word-break:break-all"><?= htmlspecialchars($w['address']) ?></code></td>
              <td><?= htmlspecialchars($w['label'] ?? '—') ?></td>
              <td><?= $w['set_by'] === 'merchant' ? '加盟店' : '管理者' ?></td>
              <td><span class="badge badge-<?= $w['status'] ?>"><?= $w['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="alert alert-info" style="font-size:12px">
    ⚠️ 受取アドレスのみ管理します。秘密鍵はシステムに保存されません（ノンカストディ設計）。
  </div>
</div>

<!-- ── 端末設定 ─────────────────────────────────── -->
<div class="tab-pane" data-tabpane-group="detail" data-pane="device">
  <div class="card">
    <div class="card-header">
      <h3>POS端末</h3>
      <button class="btn btn-sm btn-primary" onclick="openDeviceModal()">＋ 追加</button>
    </div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="table table-hover">
          <thead>
            <tr><th>端末コード</th><th>端末名</th><th>モデル</th><th>設置場所</th><th>ステータス</th><th>最終接続</th><th></th></tr>
          </thead>
          <tbody>
          <?php if (empty($devices)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:24px">端末が登録されていません</td></tr>
          <?php else: ?>
            <?php foreach ($devices as $d): ?>
            <tr>
              <td><code><?= htmlspecialchars($d['device_code']) ?></code></td>
              <td><?= htmlspecialchars($d['name']) ?></td>
              <td><?= htmlspecialchars($d['model'] ?? 'ZCS Z108') ?></td>
              <td><?= htmlspecialchars($d['location'] ?? '—') ?></td>
              <td><span class="badge badge-<?= $d['status'] === 'active' ? 'active' : ($d['status'] === 'offline' ? 'pending' : 'expired') ?>"><?= htmlspecialchars($d['status']) ?></span></td>
              <td><?= $d['last_seen_at'] ? date('m/d H:i', strtotime($d['last_seen_at'])) : '—' ?></td>
              <td>
                <button class="btn btn-sm btn-outline" onclick="openDeviceModal(<?= htmlspecialchars(json_encode($d)) ?>)">編集</button>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ── 決済履歴 ─────────────────────────────────── -->
<div class="tab-pane" data-tabpane-group="detail" data-pane="payments">
  <div class="card">
    <div class="card-header"><h3>直近20件</h3></div>
    <div class="card-body" style="padding:0">
      <div class="table-wrap">
        <table class="table table-hover">
          <thead>
            <tr><th>決済コード</th><th>通貨</th><th>暗号通貨額</th><th>JPY換算</th><th>ステータス</th><th>日時</th></tr>
          </thead>
          <tbody>
          <?php if (empty($payments)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:24px">決済データなし</td></tr>
          <?php else: ?>
            <?php foreach ($payments as $p): ?>
            <tr>
              <td><code><?= htmlspecialchars($p['payment_code']) ?></code></td>
              <td><span class="badge badge-currency"><?= htmlspecialchars($p['currency']) ?></span></td>
              <td><?= number_format((float)$p['amount_crypto'], 6) ?></td>
              <td>¥<?= number_format((int)$p['amount_jpy']) ?></td>
              <td><span class="badge badge-<?= $p['status'] ?>"><?= htmlspecialchars($p['status']) ?></span></td>
              <td><?= date('Y/m/d H:i', strtotime($p['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


<!-- ── 審査モーダル ─────────────────────────────── -->
<div id="review-modal" class="modal" hidden>
  <div class="modal-backdrop" onclick="closeReviewModal()"></div>
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="review-modal-title">ステータス変更</h3>
      <button class="modal-close" onclick="closeReviewModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="review-form">
        <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="id" value="<?= $merchant['id'] ?>">
        <input type="hidden" name="status" id="review-status" value="">
        <div class="form-group">
          <label>審査メモ</label>
          <textarea name="review_note" rows="4" placeholder="審査結果のメモを入力（任意）"><?= htmlspecialchars($merchant['review_note'] ?? '') ?></textarea>
        </div>
        <div id="portal-notice" class="alert alert-info" style="display:none">
          ✅ 審査通過にすると加盟店ポータルアカウントを自動発行します。
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeReviewModal()">キャンセル</button>
          <button type="submit" class="btn btn-primary" id="review-submit-btn">変更する</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── ウォレットモーダル ─────────────────────────── -->
<div id="wallet-modal" class="modal" hidden>
  <div class="modal-backdrop" onclick="document.getElementById('wallet-modal').hidden=true"></div>
  <div class="modal-box">
    <div class="modal-header">
      <h3>ウォレットアドレス設定</h3>
      <button class="modal-close" onclick="document.getElementById('wallet-modal').hidden=true">✕</button>
    </div>
    <div class="modal-body">
      <form id="wallet-form">
        <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="merchant_id" value="<?= $merchant['id'] ?>">
        <div class="form-group">
          <label>通貨 <span class="req">*</span></label>
          <select name="currency" required>
            <?php foreach ($currencies as $c): ?>
            <option value="<?= $c ?>"><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>ネットワーク</label>
          <input type="text" name="network" placeholder="mainnet / ERC20 / TRC20 等">
        </div>
        <div class="form-group">
          <label>受取アドレス <span class="req">*</span></label>
          <input type="text" name="address" required placeholder="0x... / bc1... / r...">
          <div class="form-hint">受取専用アドレスのみ入力してください。秘密鍵は入力しないでください。</div>
        </div>
        <div class="form-group">
          <label>ラベル</label>
          <input type="text" name="label" placeholder="ホットウォレット など">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="document.getElementById('wallet-modal').hidden=true">キャンセル</button>
          <button type="submit" class="btn btn-primary">保存する</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── 端末モーダル ───────────────────────────────── -->
<div id="device-modal" class="modal" hidden>
  <div class="modal-backdrop" onclick="document.getElementById('device-modal').hidden=true"></div>
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="device-modal-title">端末 追加</h3>
      <button class="modal-close" onclick="document.getElementById('device-modal').hidden=true">✕</button>
    </div>
    <div class="modal-body">
      <form id="device-form">
        <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="merchant_id" value="<?= $merchant['id'] ?>">
        <input type="hidden" name="device_id" id="dv-id" value="0">
        <div class="form-group">
          <label>端末名 <span class="req">*</span></label>
          <input type="text" name="name" id="dv-name" required placeholder="レジ1 / フロント端末">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>モデル</label>
            <input type="text" name="model" id="dv-model" value="ZCS Z108">
          </div>
          <div class="form-group">
            <label>シリアル番号</label>
            <input type="text" name="serial_number" id="dv-serial" placeholder="S/N">
          </div>
        </div>
        <div class="form-group">
          <label>設置場所</label>
          <input type="text" name="location" id="dv-location" placeholder="1F レジカウンター">
        </div>
        <div id="dv-token-box" class="alert alert-success" style="display:none">
          <strong>APIトークン（初回表示のみ）：</strong><br>
          <code id="dv-token-value" style="word-break:break-all"></code>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="document.getElementById('device-modal').hidden=true">キャンセル</button>
          <button type="submit" class="btn btn-primary">保存する</button>
        </div>
      </form>
    </div>
  </div>
</div>


<script>
const CSRF_TOKEN = document.querySelector('meta[name=csrf]')?.content ?? '';

// ── 審査ステータス変更
function changeStatus(status) {
  const titles = {
    reviewing: '審査中に変更',
    active:    '審査通過（Active）にする',
    suspended: '停止する',
    rejected:  '否決する',
  };
  document.getElementById('review-modal-title').textContent = titles[status] || status;
  document.getElementById('review-status').value = status;
  document.getElementById('portal-notice').style.display = status === 'active' ? '' : 'none';
  document.getElementById('review-modal').hidden = false;
}
function closeReviewModal() { document.getElementById('review-modal').hidden = true; }

document.getElementById('review-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true;
  const data = new URLSearchParams(new FormData(e.target));
  try {
    const res  = await fetch('index.php?page=merchant-review', {method:'POST', body:data});
    const json = await res.json();
    if (json.success) {
      if (json.portal_issued) {
        alert(`加盟店ポータルアカウントを発行しました。\n\nメール: ${json.portal_email}\n仮パスワード: ${json.portal_password}\n\n※ 加盟店にお知らせください。`);
      } else {
        showToast(json.message, 'success');
      }
      setTimeout(() => location.reload(), 1000);
    } else {
      showToast(json.message || 'エラー', 'error');
    }
  } finally {
    btn.disabled = false;
  }
});

// ── ウォレット
function openWalletModal() { document.getElementById('wallet-modal').hidden = false; }

document.getElementById('wallet-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true;
  const data = new URLSearchParams(new FormData(e.target));
  try {
    const res  = await fetch('index.php?page=wallet-save', {method:'POST', body:data});
    const json = await res.json();
    showToast(json.message, json.success ? 'success' : 'error');
    if (json.success) setTimeout(() => location.reload(), 1200);
  } finally { btn.disabled = false; }
});

// ── 端末
function openDeviceModal(d) {
  const modal = document.getElementById('device-modal');
  document.getElementById('device-modal-title').textContent = d ? '端末編集' : '端末 追加';
  document.getElementById('dv-id').value       = d?.id ?? '0';
  document.getElementById('dv-name').value     = d?.name ?? '';
  document.getElementById('dv-model').value    = d?.model ?? 'ZCS Z108';
  document.getElementById('dv-serial').value   = d?.serial_number ?? '';
  document.getElementById('dv-location').value = d?.location ?? '';
  document.getElementById('dv-token-box').style.display = 'none';
  modal.hidden = false;
}

document.getElementById('device-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true;
  const data = new URLSearchParams(new FormData(e.target));
  try {
    const res  = await fetch('index.php?page=device-save', {method:'POST', body:data});
    const json = await res.json();
    if (json.success) {
      if (json.api_token) {
        document.getElementById('dv-token-value').textContent = json.api_token;
        document.getElementById('dv-token-box').style.display = '';
      }
      showToast(json.message, 'success');
      if (!json.api_token) setTimeout(() => location.reload(), 1200);
    } else {
      showToast(json.message || 'エラー', 'error');
    }
  } finally { btn.disabled = false; }
});

// ── 編集モーダル（情報タブ / 契約タブへのジャンプ）
function openEditModal(m, tab) {
  // 一覧ページのモーダル関数が必要なので、一覧ページへの簡易リンク
  window.location.href = `?page=merchants`;
}
</script>
