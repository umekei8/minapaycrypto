<?php declare(strict_types=1);
/**
 * 加盟店管理 一覧 View
 * Variables: $merchants (array), $totalCount (int), $page (int), $limit (int), $q (string), $status (string)
 */

$statusLabels = [
    'applied'   => ['label'=>'新規申込','class'=>'applied'],
    'reviewing' => ['label'=>'審査中',  'class'=>'reviewing'],
    'active'    => ['label'=>'審査通過','class'=>'active'],
    'suspended' => ['label'=>'停止中',  'class'=>'suspended'],
    'rejected'  => ['label'=>'否決',    'class'=>'rejected'],
];

$pages = (int)ceil($totalCount / $limit);
?>

<!-- Page actions -->
<div class="page-actions">
  <a href="?page=merchants&<?= http_build_query(['q'=>$q,'status'=>$status,'csv'=>1]) ?>"
     class="btn btn-outline btn-sm">📥 CSV</a>
  <button class="btn btn-primary btn-sm" onclick="openCreateModal()">＋ 新規登録</button>
</div>

<!-- Search bar -->
<form method="get" action="index.php" class="search-bar">
  <input type="hidden" name="page" value="merchants">
  <input type="text" name="q" placeholder="加盟店名 / コード / メール" value="<?= htmlspecialchars($q) ?>" style="flex:1;min-width:200px">
  <select name="status">
    <option value="">すべてのステータス</option>
    <?php foreach ($statusLabels as $val => $info): ?>
    <option value="<?= $val ?>" <?= $status===$val ? 'selected' : '' ?>><?= $info['label'] ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-outline btn-sm">🔍 絞込</button>
  <a href="?page=merchants" class="btn btn-outline btn-sm">クリア</a>
</form>

<!-- Status tabs -->
<div class="tabs" style="margin-bottom:0">
  <a href="?page=merchants" class="tab-btn <?= $status==='' ? 'active' : '' ?>">
    全件 (<?= $totalCount ?>)
  </a>
  <?php foreach ($statusLabels as $val => $info): ?>
  <a href="?page=merchants&status=<?= $val ?>" class="tab-btn <?= $status===$val ? 'active' : '' ?>">
    <?= $info['label'] ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Table -->
<div class="card" style="margin-top:0;border-radius:0 var(--radius) var(--radius) var(--radius)">
  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="table table-hover">
        <thead>
          <tr>
            <th>コード</th>
            <th>加盟店名</th>
            <th>メール</th>
            <th>ステータス</th>
            <th>手数料</th>
            <th>ウォレット</th>
            <th>端末</th>
            <th>登録日</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($merchants)): ?>
          <tr>
            <td colspan="9" class="text-center text-muted" style="padding:32px">
              該当する加盟店はありません
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($merchants as $m): ?>
          <tr>
            <td><code><?= htmlspecialchars($m['code']) ?></code></td>
            <td>
              <strong><?= htmlspecialchars($m['name']) ?></strong>
              <?php if ($m['name_kana']): ?>
              <br><small class="text-muted"><?= htmlspecialchars($m['name_kana']) ?></small>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($m['email']) ?></td>
            <td>
              <?php $si = $statusLabels[$m['status']] ?? ['label'=>$m['status'],'class'=>'pending']; ?>
              <span class="badge badge-<?= $si['class'] ?>"><?= $si['label'] ?></span>
              <?php if ($m['portal_enabled']): ?>
              <span class="badge" style="background:#ede9fe;color:#6d28d9;margin-left:4px" title="ポータル有効">🔑</span>
              <?php endif; ?>
            </td>
            <td><?= number_format((float)$m['fee_percent'], 2) ?>%</td>
            <td>
              <?= (int)$m['wallet_count'] ?>
              <?= (int)$m['wallet_count'] === 0 ? ' <span class="badge badge-pending">未設定</span>' : '' ?>
            </td>
            <td><?= (int)$m['device_count'] ?></td>
            <td style="white-space:nowrap"><?= date('Y/m/d', strtotime($m['created_at'])) ?></td>
            <td>
              <div class="btn-group">
                <a href="?page=merchants&action=detail&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline">詳細</a>
                <button class="btn btn-sm btn-primary" onclick="openEditModal(<?= htmlspecialchars(json_encode($m)) ?>)">編集</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= $pages; $i++): ?>
  <a href="?<?= http_build_query(['page'=>'merchants','q'=>$q,'status'=>$status,'p'=>$i]) ?>"
     class="<?= $i===$page ? 'current' : '' ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>


<!-- ── 新規登録 / 編集 Modal ──────────────────────── -->
<div id="merchant-modal" class="modal" hidden>
  <div class="modal-backdrop" onclick="closeMerchantModal()"></div>
  <div class="modal-box modal-box-lg">
    <div class="modal-header">
      <h3 id="modal-title">加盟店 新規登録</h3>
      <button class="modal-close" onclick="closeMerchantModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="merchant-form">
        <input type="hidden" name="_csrf" id="fm-csrf" value="">
        <input type="hidden" name="id" id="fm-id" value="0">

        <div class="tabs" id="form-tabs">
          <button type="button" class="tab-btn active" data-tab-group="form" data-tab="basic" onclick="switchTab('form','basic')">加盟店情報</button>
          <button type="button" class="tab-btn" data-tab-group="form" data-tab="contract" onclick="switchTab('form','contract')">契約条件</button>
          <button type="button" class="tab-btn" data-tab-group="form" data-tab="memo" onclick="switchTab('form','memo')">メモ</button>
        </div>

        <!-- 加盟店情報 -->
        <div class="tab-pane active" data-tabpane-group="form" data-pane="basic">
          <div class="form-row">
            <div class="form-group">
              <label>加盟店名 <span class="req">*</span></label>
              <input type="text" name="name" id="fm-name" required placeholder="株式会社○○">
            </div>
            <div class="form-group">
              <label>フリガナ</label>
              <input type="text" name="name_kana" id="fm-name-kana" placeholder="カブシキガイシャ○○">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>事業者区分</label>
              <select name="business_type" id="fm-business-type">
                <option value="individual">個人事業主</option>
                <option value="corporation">法人</option>
              </select>
            </div>
            <div class="form-group">
              <label>代表者名</label>
              <input type="text" name="representative" id="fm-representative" placeholder="山田 太郎">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>メールアドレス <span class="req">*</span></label>
              <input type="email" name="email" id="fm-email" required placeholder="contact@example.com">
            </div>
            <div class="form-group">
              <label>電話番号</label>
              <input type="tel" name="phone" id="fm-phone" placeholder="03-1234-5678">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>郵便番号</label>
              <input type="text" name="postal_code" id="fm-postal" placeholder="000-0000">
            </div>
            <div class="form-group">
              <label>業種</label>
              <input type="text" name="business_category" id="fm-category" placeholder="飲食業 / EC / IT など">
            </div>
          </div>
          <div class="form-group">
            <label>住所</label>
            <input type="text" name="address" id="fm-address" placeholder="東京都渋谷区○○1-2-3">
          </div>
          <div class="form-group">
            <label>Webサイト</label>
            <input type="text" name="website" id="fm-website" placeholder="https://example.com">
          </div>
        </div>

        <!-- 契約条件 -->
        <div class="tab-pane" data-tabpane-group="form" data-pane="contract">
          <div class="form-row">
            <div class="form-group">
              <label>加盟店手数料率 (%)</label>
              <input type="number" name="fee_percent" id="fm-fee" step="0.01" min="0" max="100" value="6.50">
              <div class="form-hint">加盟店に請求する手数料率</div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>契約開始日</label>
              <input type="date" name="contract_start" id="fm-contract-start">
            </div>
            <div class="form-group">
              <label>契約終了日</label>
              <input type="date" name="contract_end" id="fm-contract-end">
              <div class="form-hint">空欄は無期限</div>
            </div>
          </div>
        </div>

        <!-- メモ -->
        <div class="tab-pane" data-tabpane-group="form" data-pane="memo">
          <div class="form-group">
            <label>メモ（内部用）</label>
            <textarea name="memo" id="fm-memo" rows="6" placeholder="審査メモや特記事項を入力…"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeMerchantModal()">キャンセル</button>
          <button type="submit" class="btn btn-primary">保存する</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name=csrf]')?.content ?? '';

function openCreateModal() {
  document.getElementById('modal-title').textContent = '加盟店 新規登録';
  document.getElementById('merchant-form').reset();
  document.getElementById('fm-id').value = '0';
  document.getElementById('fm-csrf').value = CSRF_TOKEN;
  document.getElementById('fm-fee').value = '6.50';
  document.getElementById('merchant-modal').hidden = false;
}

function openEditModal(m) {
  document.getElementById('modal-title').textContent = '加盟店編集: ' + m.name;
  document.getElementById('fm-id').value           = m.id;
  document.getElementById('fm-csrf').value         = CSRF_TOKEN;
  document.getElementById('fm-name').value         = m.name ?? '';
  document.getElementById('fm-name-kana').value    = m.name_kana ?? '';
  document.getElementById('fm-business-type').value = m.business_type ?? 'individual';
  document.getElementById('fm-representative').value = m.representative ?? '';
  document.getElementById('fm-email').value        = m.email ?? '';
  document.getElementById('fm-phone').value        = m.phone ?? '';
  document.getElementById('fm-postal').value       = m.postal_code ?? '';
  document.getElementById('fm-address').value      = m.address ?? '';
  document.getElementById('fm-website').value      = m.website ?? '';
  document.getElementById('fm-category').value     = m.business_category ?? '';
  document.getElementById('fm-fee').value          = m.fee_percent ?? '6.50';
  document.getElementById('fm-contract-start').value = m.contract_start ?? '';
  document.getElementById('fm-contract-end').value   = m.contract_end ?? '';
  document.getElementById('fm-memo').value         = m.memo ?? '';
  document.getElementById('merchant-modal').hidden = false;
}

function closeMerchantModal() {
  document.getElementById('merchant-modal').hidden = true;
}

document.getElementById('merchant-form').addEventListener('submit', async e => {
  e.preventDefault();
  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true; btn.textContent = '保存中…';
  const data = new URLSearchParams(new FormData(e.target));
  try {
    const res = await fetch('index.php?page=merchant-save', {method:'POST', body:data});
    const json = await res.json();
    if (json.success) {
      showToast(json.message, 'success');
      setTimeout(() => location.reload(), 1200);
    } else {
      showToast(json.message || 'エラーが発生しました', 'error');
    }
  } catch(err) {
    showToast('通信エラーが発生しました', 'error');
  } finally {
    btn.disabled = false; btn.textContent = '保存する';
  }
});
</script>
