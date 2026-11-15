package com.minapay.pos.ui

import android.app.Application
import android.util.Log
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.minapay.pos.MinaPayApplication
import com.minapay.pos.data.model.*
import com.minapay.pos.data.repository.PaymentRepository
import com.minapay.pos.domain.calculator.FeeCalculator
import com.minapay.pos.domain.calculator.RateCalculator
import com.minapay.pos.domain.payment.*
import com.minapay.pos.util.ConfigLoader
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.*
import java.math.BigDecimal

private const val TAG = "MinaPayVM"
private const val POST_EXPIRE_MS = 30 * 60 * 1000L

sealed class Screen {
    object CurrencySelect : Screen()
    data class AmountInput(val currency: CryptoCurrency) : Screen()
    data class Payment(val paymentId: String) : Screen()
    object History : Screen()
    object Settings : Screen()
    object Support : Screen()
    object Terms : Screen()
    object About : Screen()
}

data class AmountUiState(
    val displayAmount: String = "0",
    val selectedCurrency: CryptoCurrency = CryptoCurrency.BTC,
    val isLoading: Boolean = false,
    val error: String? = null,
    val previewCryptoAmount: String? = null,
    val previewRate: String? = null,
    val isFetchingRate: Boolean = false,
    val feeJpy: Int = 0,
    val merchantAmountJpy: Int = 0
)

data class PaymentUiState(
    val payment: Payment? = null,
    val remainingSeconds: Int = 600,
    val error: String? = null,
    val activeAlert: PaymentAlert = PaymentAlert.NONE,
    val receivedAmount: BigDecimal = BigDecimal.ZERO,
    val printError: String? = null
)

class MainViewModel(app: Application) : AndroidViewModel(app) {

    private val ctx = app.applicationContext
    var config = ConfigLoader.load(ctx); private set
    private val repo by lazy { PaymentRepository((app as MinaPayApplication).database) }

    private val _screen       = MutableStateFlow<Screen>(Screen.CurrencySelect)
    val screen: StateFlow<Screen> = _screen.asStateFlow()

    private val _amountState  = MutableStateFlow(AmountUiState())
    val amountState: StateFlow<AmountUiState> = _amountState.asStateFlow()

    private val _paymentState = MutableStateFlow(PaymentUiState())
    val paymentState: StateFlow<PaymentUiState> = _paymentState.asStateFlow()

    private val _demoMode     = MutableStateFlow(false)
    val demoMode: StateFlow<Boolean> = _demoMode.asStateFlow()

    fun setDemoMode(v: Boolean) { _demoMode.value = v; ConfigLoader.save(ctx, config.copy(demoMode = v)) }

    val paymentsFlow: StateFlow<List<Payment>> =
        repo.getPaymentsFlow(config.terminalId)
            .stateIn(viewModelScope, SharingStarted.Lazily, emptyList())

    // デモモード用固定レート（実際のレートに近い概算値・ネットワーク不要）
    // ※ 定期的に手動更新してください
    private val demoRates = mapOf(
        CryptoCurrency.BTC  to BigDecimal("14500000"),  // ¥14,500,000/BTC
        CryptoCurrency.ETH  to BigDecimal("380000"),    // ¥380,000/ETH
        CryptoCurrency.SOL  to BigDecimal("19000"),     // ¥19,000/SOL
        CryptoCurrency.XRP  to BigDecimal("300"),       // ¥300/XRP
        CryptoCurrency.JPYC to BigDecimal("1")          // ¥1/JPYC（固定）
    )

    private var monitor: PaymentMonitor? = null
    private val demoMonitor = DemoPaymentMonitor()
    private var monitorJob: Job? = null
    private var demoMonitorJob: Job? = null
    private var countdownJob: Job? = null
    private var previewJob: Job? = null
    private var postExpireJob: Job? = null

    init { _demoMode.value = config.demoMode }

    // ── 通貨選択 ─────────────────────────────────────────────────────────────
    fun selectCurrency(currency: CryptoCurrency) {
        // viewModelScope.launch で同一フレームにまとめて更新
        // Composeが中間状態を描画しないようにする
        viewModelScope.launch(Dispatchers.Main.immediate) {
            _amountState.value = AmountUiState(selectedCurrency = currency)
            _screen.value = Screen.AmountInput(currency)
        }
    }

    // ── 数字入力 ─────────────────────────────────────────────────────────────
    fun onNumKey(key: String) {
        val cur = _amountState.value.displayAmount
        val next = when (key) {
            "⌫"   -> if (cur.length <= 1) "0" else cur.dropLast(1)
            "CLR" -> "0"
            else  -> { val r = if (cur == "0") key else cur + key
                       if (config.maxPaymentJpy > 0 && (r.toLongOrNull() ?: 0L) > config.maxPaymentJpy) cur else r }
        }
        _amountState.update { it.copy(displayAmount = next, error = null) }
        schedulePreview(next)
    }

    private fun schedulePreview(amount: String) {
        previewJob?.cancel()
        val jpy = amount.toIntOrNull() ?: 0
        if (jpy <= 0) {
            _amountState.update { it.copy(
                previewCryptoAmount = null, previewRate = null,
                isFetchingRate = false, feeJpy = 0, merchantAmountJpy = 0) }
            return
        }
        _amountState.update { it.copy(isFetchingRate = true) }
        previewJob = viewModelScope.launch {
            delay(400)
            val currency = _amountState.value.selectedCurrency
            try {
                val rate = if (_demoMode.value) demoRates[currency]!!
                           else withContext(Dispatchers.IO) { repo.fetchRates()[currency]!! }
                val adj   = RateCalculator.adjustedRate(rate, config.rateMarkupPercent)
                val scale = when (currency) { CryptoCurrency.XRP -> 6; else -> 8 }
                val cry   = RateCalculator.cryptoAmount(jpy, adj, config.feePercent, scale)
                // 加盟店受取 = 表示金額全額
                val fee  = FeeCalculator.calculate(jpy, config.feePercent)
                _amountState.update { it.copy(
                    previewCryptoAmount = "${cry.stripTrailingZeros().toPlainString()} ${currency.symbol}",
                    previewRate = "レート：¥${"%,d".format(adj.toLong())} / ${currency.symbol}（2ソース平均）",
                    isFetchingRate = false,
                    feeJpy = fee.operatorFeeJpy,
                    merchantAmountJpy = fee.merchantNetJpy
                )}
            } catch (e: Exception) {
                Log.w(TAG, "preview error: ${e.message}")
                _amountState.update { it.copy(
                    isFetchingRate = false,
                    error = if ("レート" in (e.message ?: "")) "レート取得失敗: ネットワークを確認してください" else null
                )}
            }
        }
    }

    // ── 決済開始 ─────────────────────────────────────────────────────────────
    fun startPayment() {
        val amount = _amountState.value.displayAmount.toIntOrNull() ?: 0
        if (amount <= 0) { _amountState.update { it.copy(error = "金額を入力してください") }; return }
        val currency = _amountState.value.selectedCurrency

        viewModelScope.launch {
            _amountState.update { it.copy(isLoading = true, error = null) }
            try {
                val rate = if (_demoMode.value) demoRates[currency]!!
                           else withContext(Dispatchers.IO) {
                               repo.fetchRates()[currency] ?: throw Exception("レート取得失敗")
                           }
                // ウォレットアドレス未設定チェック
                val router = com.minapay.pos.domain.calculator.WalletRouter(config)
                val wallet = router.getMerchantWallet(currency)
                if (wallet.isBlank()) {
                    _amountState.update { it.copy(
                        error = "${currency.symbol}のウォレットアドレスが設定されていません。\n設定画面で登録してください。",
                        isLoading = false
                    )}
                    return@launch
                }

                val payment = withContext(Dispatchers.IO) {
                    repo.createPayment(amount, currency, config, rate, _demoMode.value)
                }
                Log.i(TAG, "Payment created: ${payment.paymentId} ${currency.name} wallet=${payment.merchantWallet}")
                _paymentState.value = PaymentUiState(payment = payment)
                _screen.value = Screen.Payment(payment.paymentId)
                if (_demoMode.value) startDemoMonitor(payment) else startBlockchainMonitor(payment)
                startCountdown(payment)
            } catch (e: Exception) {
                Log.e(TAG, "startPayment error: ${e.message}", e)
                _amountState.update { it.copy(error = "エラー: ${e.message}") }
            } finally {
                _amountState.update { it.copy(isLoading = false) }
            }
        }
    }

    // ── Demo監視 ─────────────────────────────────────────────────────────────
    private fun startDemoMonitor(payment: Payment) {
        demoMonitorJob?.cancel()
        demoMonitorJob = viewModelScope.launch {
            demoMonitor.startMonitoring(payment).collect { event ->
                handleMonitorEvent(payment.paymentId, event)
            }
        }
    }

    // ── Blockchain監視（最重要・IO dispatcherで実行） ─────────────────────────
    private fun startBlockchainMonitor(payment: Payment) {
        monitor?.stopMonitoring()
        monitorJob?.cancel()
        monitor = BlockchainPaymentMonitor()

        monitorJob = viewModelScope.launch(Dispatchers.IO) {
            // 使用済みTXハッシュを取得（前回決済のTXを誤検知しない）
            val usedHashes = try { repo.getUsedTxHashes() } catch (_: Exception) { emptySet() }
            Log.i(TAG, "Starting monitor with ${usedHashes.size} used TX hashes to exclude")
            try {
                monitor!!.startMonitoring(payment, usedHashes).collect { event ->
                    Log.i(TAG, "📡 Monitor event: ${event.status} tx=${event.txHash} " +
                            "amount=${event.receivedAmount} alert=${event.alert}")
                    // UIの更新はMainスレッドで
                    withContext(Dispatchers.Main) {
                        handleMonitorEvent(payment.paymentId, event)
                    }
                }
            } catch (e: CancellationException) {
                Log.i(TAG, "Monitor cancelled for ${payment.paymentId}")
            } catch (e: Exception) {
                Log.e(TAG, "Monitor fatal error: ${e.message}", e)
            }
        }
        Log.i(TAG, "Blockchain monitor started for ${payment.paymentId}")
    }

    // ── モニターイベント処理（必ずMainスレッドで呼ばれる） ────────────────────
    private suspend fun handleMonitorEvent(paymentId: String, event: MonitorEvent) {
        val currentPayment = _paymentState.value.payment ?: return

        // 既にCONFIRMEDなら何もしない
        if (currentPayment.status == PaymentStatus.CONFIRMED &&
            event.status != PaymentStatus.CONFIRMED) return

        when (event.status) {
            PaymentStatus.DETECTED -> {
                Log.i(TAG, "▶ Handling DETECTED for $paymentId")
                withContext(Dispatchers.IO) {
                    repo.detectPayment(paymentId, event.txHash ?: "", event.confirmations)
                }
                val updated = withContext(Dispatchers.IO) { repo.getPayment(paymentId) }
                Log.i(TAG, "▶ DB updated: status=${updated?.status}")
                // DBがnull（DEMOモードでDB未保存）でも現在のpaymentを維持
                val detectedPayment = updated
                    ?: _paymentState.value.payment?.copy(
                        status = PaymentStatus.DETECTED,
                        txHash = event.txHash
                    )
                _paymentState.update { it.copy(
                    payment        = detectedPayment,
                    activeAlert    = event.alert,
                    receivedAmount = event.receivedAmount
                )}
                Log.i(TAG, "✅ UI updated to DETECTED")
            }

            PaymentStatus.CONFIRMED -> {
                Log.i(TAG, "▶ Handling CONFIRMED for $paymentId")
                // DBに保存（DEMOモードでも保存 → getPaymentでnullにならない）
                withContext(Dispatchers.IO) {
                    repo.confirmPayment(
                        paymentId, event.txHash ?: "",
                        event.confirmations, event.isDemoConfirmed
                    )
                }
                val updated = withContext(Dispatchers.IO) { repo.getPayment(paymentId) }
                Log.i(TAG, "▶ DB updated: status=${updated?.status}")

                // updatedがnullの場合（DEMOでDB未保存など）は現在のpaymentを使って
                // statusだけCONFIRMEDに更新する → 絶対にnullにしない
                val confirmedPayment = updated
                    ?: _paymentState.value.payment?.copy(
                        status          = PaymentStatus.CONFIRMED,
                        txHash          = event.txHash ?: "DEMO_TX_${System.currentTimeMillis()}",
                        confirmations   = event.confirmations,
                        isDemoConfirmed = event.isDemoConfirmed,
                        confirmedAt     = System.currentTimeMillis()
                    )

                _paymentState.update { it.copy(
                    payment        = confirmedPayment,  // 絶対にnullにならない
                    activeAlert    = event.alert,
                    receivedAmount = event.receivedAmount
                )}
                Log.i(TAG, "✅ UI updated to CONFIRMED (payment=${confirmedPayment?.paymentId})")
                // 全監視を停止
                stopAllMonitoring()
            }

            else -> {}
        }
    }

    private fun stopAllMonitoring() {
        countdownJob?.cancel()
        monitorJob?.cancel()
        demoMonitorJob?.cancel()
        postExpireJob?.cancel()
        monitor?.stopMonitoring()
        Log.i(TAG, "All monitoring stopped")
    }

    // ── カウントダウン ────────────────────────────────────────────────────────
    private fun startCountdown(payment: Payment) {
        countdownJob?.cancel()
        countdownJob = viewModelScope.launch {
            var remaining = ((payment.expiresAt - System.currentTimeMillis()) / 1000)
                .toInt().coerceAtLeast(0)

            while (isActive) {
                val st = _paymentState.value.payment?.status
                if (st == PaymentStatus.CONFIRMED || st == PaymentStatus.CANCELLED) break

                _paymentState.update { it.copy(remainingSeconds = remaining) }

                if (remaining <= 0) {
                    // タイムアウト直前にCONFIRMEDになっていないか再確認
                    val latest = withContext(Dispatchers.IO) { repo.getPayment(payment.paymentId) }
                    if (latest?.status == PaymentStatus.CONFIRMED ||
                        latest?.status == PaymentStatus.CANCELLED) {
                        _paymentState.update { it.copy(payment = latest) }
                        break
                    }
                    // EXPIRED書き込み
                    withContext(Dispatchers.IO) { repo.expireOldPayments() }
                    val expired = withContext(Dispatchers.IO) { repo.getPayment(payment.paymentId) }
                    _paymentState.update { it.copy(payment = expired, remainingSeconds = 0) }
                    Log.i(TAG, "Payment EXPIRED: ${payment.paymentId}")

                    // 30分間監視継続（monitorJobはそのまま継続）
                    postExpireJob = viewModelScope.launch {
                        delay(POST_EXPIRE_MS)
                        val finalSt = withContext(Dispatchers.IO) {
                            repo.getPayment(payment.paymentId)?.status
                        }
                        if (finalSt != PaymentStatus.CONFIRMED) {
                            monitor?.stopMonitoring()
                            monitorJob?.cancel()
                            Log.i(TAG, "Post-expire monitoring ended")
                        }
                    }
                    break
                }
                delay(1000)
                remaining--
            }
        }
    }

    // ── 期限切れ後の3アクション ──────────────────────────────────────────────
    fun reissueQr() {
        countdownJob?.cancel()
        val currency = _amountState.value.selectedCurrency
        _amountState.update { it.copy(displayAmount = "0") }
        _screen.value = Screen.AmountInput(currency)
    }

    fun backWithMonitoring() {
        countdownJob?.cancel()
        _screen.value = Screen.CurrencySelect
    }

    fun cancelPayment() {
        val p = _paymentState.value.payment ?: return
        viewModelScope.launch {
            withContext(Dispatchers.IO) { repo.cancelPayment(p.paymentId, null) }
            stopAllMonitoring()
            _screen.value = Screen.CurrencySelect
            Log.i(TAG, "Payment CANCELLED: ${p.paymentId}")
        }
    }

    fun dismissAlert() { _paymentState.update { it.copy(activeAlert = PaymentAlert.NONE) } }
    fun dismissPrintError() { _paymentState.update { it.copy(printError = null) } }
    fun setPrintError(msg: String) { _paymentState.update { it.copy(printError = msg) } }
    fun triggerDemoConfirm() { if (_demoMode.value) demoMonitor.triggerManualConfirm() }
    fun reloadConfig() { ConfigLoader.invalidate(); config = ConfigLoader.load(ctx) }
    fun goToCurrencySelect() {
        previewJob?.cancel()
        _amountState.value = AmountUiState()
        // 前回の決済状態を完全クリア（前回のCONFIRMED paymentが残らないように）
        _paymentState.value = PaymentUiState()
        _screen.value = Screen.CurrencySelect
    }
    fun goToHistory()  { _screen.value = Screen.History }
    fun goToSettings() { _screen.value = Screen.Settings }
    fun goBackFromSub() { _screen.value = Screen.CurrencySelect }
    fun goToSupport()  { _screen.value = Screen.Support }
    fun goToTerms()    { _screen.value = Screen.Terms }
    fun goToAbout()    { _screen.value = Screen.About }
}
