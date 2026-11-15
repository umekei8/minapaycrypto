package com.minapay.pos

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.viewModels
import androidx.compose.foundation.layout.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import com.minapay.pos.data.model.PaymentStatus
import com.minapay.pos.device.printer.MinaPayPrinter
import com.minapay.pos.device.printer.PrintResult
import com.minapay.pos.device.secondscreen.SecondScreenManager
import com.minapay.pos.device.secondscreen.SecondScreenRenderer
import com.minapay.pos.ui.*
import com.minapay.pos.ui.amount.AmountInputScreen
import com.minapay.pos.ui.amount.CurrencySelectScreen
import com.minapay.pos.ui.history.HistoryScreen
import com.minapay.pos.ui.payment.PaymentScreen
import com.minapay.pos.ui.screens.AboutScreen
import com.minapay.pos.ui.screens.SupportFormScreen
import com.minapay.pos.ui.screens.TermsScreen
import com.minapay.pos.ui.settings.SettingsScreen
import com.minapay.pos.util.QrGenerator
import kotlinx.coroutines.*

class MainActivity : ComponentActivity() {

    private val viewModel: MainViewModel by viewModels()
    private lateinit var secondMgr: SecondScreenManager
    private lateinit var secondRdr: SecondScreenRenderer
    private lateinit var printer: MinaPayPrinter

    private val ioScope   = CoroutineScope(Dispatchers.IO + SupervisorJob())
    private val mainScope = CoroutineScope(Dispatchers.Main + SupervisorJob())

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        secondMgr = SecondScreenManager(this)
        secondRdr = SecondScreenRenderer(this)
        printer   = MinaPayPrinter(this)

        // 起動直後にセカンドスクリーンを白・ロゴ表示（真っ黒防止）
        ioScope.launch {
            try { secondMgr.clear(secondRdr) } catch (_: Exception) {}
        }

        setContent {
            MinaPayTheme {
                val screen      by viewModel.screen.collectAsState()
                val amountState by viewModel.amountState.collectAsState()
                val payState    by viewModel.paymentState.collectAsState()
                val payments    by viewModel.paymentsFlow.collectAsState()
                val demoMode    by viewModel.demoMode.collectAsState()

                // payment のキャッシュ
                // ルール: 同じpaymentIdのnull更新は前回値を保持（真っ黒防止）
                //         新しいpaymentIdが来たら即座にリセット（前回完了画面防止）
                val cachedPayment = remember { mutableStateOf<com.minapay.pos.data.model.Payment?>(null) }
                LaunchedEffect(payState.payment) {
                    val newPayment = payState.payment
                    if (newPayment != null) {
                        // 新しいpaymentIdなら必ず更新（前回のCONFIRMEDを持ち越さない）
                        cachedPayment.value = newPayment
                    }
                    // null の場合は保持しない（Screen.Paymentから離れる時にリセット）
                }
                // Screen.CurrencySelectに戻るときcachedPaymentをクリア
                LaunchedEffect(screen) {
                    if (screen is Screen.CurrencySelect || screen is Screen.AmountInput) {
                        cachedPayment.value = null
                    }
                }

                Column(Modifier.fillMaxSize()) {
                    if (demoMode) DemoModeBanner()

                    when (screen) {
                        is Screen.CurrencySelect ->
                            CurrencySelectScreen(
                                onSelectCurrency = viewModel::selectCurrency,
                                onHistory        = viewModel::goToHistory,
                                onSettings       = viewModel::goToSettings,
                                onSupport        = viewModel::goToSupport,
                                onTerms          = viewModel::goToTerms,
                                onAbout          = viewModel::goToAbout
                            )

                        is Screen.AmountInput ->
                            AmountInputScreen(
                                displayAmount       = amountState.displayAmount,
                                selectedCurrency    = amountState.selectedCurrency,
                                previewCryptoAmount = amountState.previewCryptoAmount,
                                previewRate         = amountState.previewRate,
                                isFetchingRate      = amountState.isFetchingRate,
                                feeJpy              = amountState.feeJpy,
                                merchantAmountJpy   = amountState.merchantAmountJpy,
                                isLoading           = amountState.isLoading,
                                error               = amountState.error,
                                onNumKey            = viewModel::onNumKey,
                                onChangeCurrency    = viewModel::goToCurrencySelect,
                                onSubmit            = viewModel::startPayment,
                                onHistory           = viewModel::goToHistory,
                                onSettings          = viewModel::goToSettings,
                                onSupport           = viewModel::goToSupport,
                                onTerms             = viewModel::goToTerms,
                                onAbout             = viewModel::goToAbout
                            )

                        is Screen.Payment -> {
                            // cachedPayment を使うことで null による真っ黒を防ぐ
                            val payment = cachedPayment.value
                            if (payment != null) {
                                PaymentScreen(
                                    payment              = payment,
                                    remainingSeconds     = payState.remainingSeconds,
                                    isDemoMode           = demoMode,
                                    activeAlert          = payState.activeAlert,
                                    receivedAmount       = payState.receivedAmount,
                                    printError           = payState.printError,
                                    onDismissAlert       = viewModel::dismissAlert,
                                    onDismissPrintError  = viewModel::dismissPrintError,
                                    onCancel             = viewModel::cancelPayment,
                                    onReissueQr          = viewModel::reissueQr,
                                    onBackWithMonitoring = viewModel::backWithMonitoring,
                                    onDemoConfirm        = viewModel::triggerDemoConfirm,
                                    onPrint = {
                                        // DEMOモードでも実機なら印刷を試みる
                                        // isSdkAvailable=false の場合はエラーダイアログを表示
                                        ioScope.launch {
                                            val result = printer.printReceipt(payment, viewModel.config)
                                            if (result is PrintResult.Error) {
                                                withContext(Dispatchers.Main) {
                                                    viewModel.setPrintError(result.message)
                                                }
                                            }
                                        }
                                    },
                                    onNewPayment = viewModel::goToCurrencySelect,
                                    onHistory    = viewModel::goToHistory
                                )
                            }
                        }

                        is Screen.History ->
                            HistoryScreen(
                                payments = payments,
                                onBack   = viewModel::goBackFromSub,
                                onPrint  = { p ->
                                    ioScope.launch { printer.printReceipt(p, viewModel.config) }
                                }
                            )

                        is Screen.Settings ->
                            SettingsScreen(
                                config       = viewModel.config,
                                demoMode     = demoMode,
                                onToggleDemo = viewModel::setDemoMode,
                                onBack       = viewModel::goBackFromSub,
                                onSaved      = viewModel::reloadConfig
                            )

                        is Screen.Support ->
                            SupportFormScreen(onBack = viewModel::goBackFromSub)

                        is Screen.Terms ->
                            TermsScreen(onBack = viewModel::goBackFromSub)

                        is Screen.About ->
                            AboutScreen(onBack = viewModel::goBackFromSub)
                    }
                }
            }
        }

        // セカンドスクリーン連動
        // チカチカ防止: 前回と同じステータス+同じpaymentIdなら再描画しない
        var lastSecondScreenStatus: String? = null
        var lastSecondScreenPaymentId: String? = null

        mainScope.launch {
            viewModel.paymentState.collect { state ->
                val payment = state.payment ?: return@collect
                val statusKey = "${payment.paymentId}:${payment.status.name}"

                // 同じ状態の繰り返し更新は無視（チカチカ防止）
                if (statusKey == lastSecondScreenStatus) return@collect
                lastSecondScreenStatus = statusKey

                ioScope.launch {
                    try {
                        when (payment.status) {
                            PaymentStatus.WAITING_PAYMENT -> {
                                val qr  = QrGenerator.generate(payment.qrPayload, 480)
                                val bmp = secondRdr.renderWaiting(payment, qr)
                                secondMgr.show(bmp)
                            }
                            PaymentStatus.DETECTED ->
                                secondMgr.show(secondRdr.renderDetected(payment))
                            PaymentStatus.CONFIRMED -> {
                                secondMgr.show(secondRdr.renderComplete(payment))
                                delay(8000)
                                secondMgr.clear(secondRdr)
                            }
                            else -> { /* 何もしない */ }
                        }
                    } catch (e: Exception) {
                        android.util.Log.w("SecondScreen", "render error: ${e.message}")
                    }
                }
            }
        }

        // 画面遷移時にセカンドスクリーンをクリア
        mainScope.launch {
            viewModel.screen.collect { s ->
                if (s !is Screen.Payment) {
                    ioScope.launch {
                        try { secondMgr.clear(secondRdr) }
                        catch (e: Exception) {
                            android.util.Log.w("SecondScreen", "clear error: ${e.message}")
                        }
                    }
                }
            }
        }
    }

    override fun onStop()    { super.onStop(); secondMgr.restoreMainScreen() }
    override fun onDestroy() {
        super.onDestroy()
        ioScope.cancel()
        mainScope.cancel()
    }
}
