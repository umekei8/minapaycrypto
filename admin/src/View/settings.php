<?php declare(strict_types=1);
/**
 * 設定 View
 * Variables: $settings (array key=>value)
 */
$get = fn(string $k, mixed $default = '') => $settings[$k] ?? $default;
?>

<form method="post" action="index.php?page=settings-save">
  <input type="hidden" name="_csrf" value="<?= Auth::csrfToken() ?>">

  <div class="tabs" id="settings-tabs">
    <button type="button" class="tab-btn active" onclick="switchTab('stab','general')">基本設定</button>
    <button type="button" class="tab-btn" onclick="switchTab('stab','payment')">決済設定</button>
    <button type="button" class="tab-btn" onclick="switchTab('stab','notify')">通知設定</button>
    <button type="button" class="tab-btn" onclick="switchTab('stab','api')">API設定</button>
  </div>

  <!-- 基本設定 -->
  <div class="tab-pane active card" data-tabpane-group="stab" data-pane="general"
       style="border-radius:0 var(--radius) var(--radius) var(--radius);margin-top:0">
    <div class="card-body">
      <h3 class="section-title">基本設定</h3>

      <div class="form-row">
        <div class="form-group">
          <label>デフォルト加盟店手数料率 (%)</label>
          <input type="number" name="default_fee_percent" step="0.01" min="0" max="100"
                 value="<?= htmlspecialchars($get('default_fee_percent', '6.50')) ?>">
          <div class="form-hint">加盟店登録時のデフォルト手数料率</div>
        </div>
        <div class="form-group">
          <label>レートマークアップ (%)</label>
          <input type="number" name="rate_markup_percent" step="0.1" min="0" max="20"
                 value="<?= htmlspecialchars($get('rate_markup_percent', '1.0')) ?>">
          <div class="form-hint">顧客提示レートに加算するマークアップ</div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>対応通貨（カンマ区切り）</label>
          <input type="text" name="supported_currencies"
                 value="<?= htmlspecialchars($get('supported_currencies', 'BTC,ETH,XRP,USDT_ERC20,USDT_TRC20')) ?>"
                 placeholder="BTC,ETH,XRP,USDT_ERC20,USDT_TRC20">
          <div class="form-hint">BTC, ETH, XRP, USDT_ERC20, USDT_TRC20, SOL</div>
        </div>
      </div>
    </div>
  </div>

  <!-- 決済設定 -->
  <div class="tab-pane card" data-tabpane-group="stab" data-pane="payment"
       style="border-radius:0 var(--radius) var(--radius) var(--radius);margin-top:0">
    <div class="card-body">
      <h3 class="section-title">決済設定</h3>

      <div class="form-row">
        <div class="form-group">
          <label>QR有効期限（分）</label>
          <input type="number" name="payment_timeout_minutes" step="1" min="1" max="60"
                 value="<?= htmlspecialchars($get('payment_timeout_minutes', '10')) ?>">
          <div class="form-hint">QRコードの有効期限（標準: 10分）</div>
        </div>
        <div class="form-group">
          <label>最小決済金額（JPY）</label>
          <input type="number" name="min_payment_jpy" step="1" min="0"
                 value="<?= htmlspecialchars($get('min_payment_jpy', '100')) ?>">
          <div class="form-hint">この金額未満の決済は拒否されます</div>
        </div>
      </div>

      <div class="alert" style="background:#fef9c3;border-color:#fde047;color:#713f12;margin-top:8px">
        <strong>⚠️ セキュリティ注意事項</strong><br>
        本システムはノンカストディ設計です。加盟店ウォレットの受取アドレスのみを管理し、
        秘密鍵や送金機能は一切実装していません。
      </div>
    </div>
  </div>

  <!-- 通知設定 -->
  <div class="tab-pane card" data-tabpane-group="stab" data-pane="notify"
       style="border-radius:0 var(--radius) var(--radius) var(--radius);margin-top:0">
    <div class="card-body">
      <h3 class="section-title">通知設定</h3>

      <div class="form-group">
        <label>通知メールアドレス</label>
        <input type="email" name="notify_email"
               value="<?= htmlspecialchars($get('notify_email', '')) ?>"
               placeholder="admin@example.com">
      </div>

      <div style="display:flex;flex-direction:column;gap:12px;margin-top:16px">
        <?php $checks = [
          'notify_on_fail'       => '決済失敗時に通知',
          'notify_on_rate_spike' => 'レート急変時に通知（±5%以上）',
          'notify_daily_report'  => '日次レポートをメール送信',
          'notify_new_merchant'  => '加盟店新規申込時に通知',
        ]; ?>
        <?php foreach ($checks as $key => $label): ?>
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
          <input type="checkbox" name="<?= $key ?>" value="1"
                 <?= $get($key) ? 'checked' : '' ?>
                 style="width:16px;height:16px">
          <span><?= htmlspecialchars($label) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- API設定 -->
  <div class="tab-pane card" data-tabpane-group="stab" data-pane="api"
       style="border-radius:0 var(--radius) var(--radius) var(--radius);margin-top:0">
    <div class="card-body">
      <h3 class="section-title">API設定</h3>

      <div class="form-group">
        <label>CoinGecko APIキー（オプション）</label>
        <input type="password" name="coingecko_api_key"
               value="<?= htmlspecialchars($get('coingecko_api_key', '')) ?>"
               placeholder="有料プランのAPIキー（無料プランは不要）"
               autocomplete="new-password">
        <div class="form-hint">
          無料プランの場合は空欄で構いません。有料プランを利用するとレート制限が緩和されます。
        </div>
      </div>

      <div class="alert" style="background:#fef3c7;border-color:#fbbf24;color:#78350f;margin-top:16px">
        <strong>🔐 APIキー管理に関する注意</strong><br>
        APIキーや秘密鍵をソースコードに直書きしないでください。
        本画面で設定した値はデータベースに暗号化せず保存されます。
        本番環境では環境変数での管理を推奨します。
      </div>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end;margin-top:20px">
    <button type="submit" class="btn btn-primary">設定を保存する</button>
  </div>
</form>
