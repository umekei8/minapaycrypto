package com.minapay.pos.domain.payment

import android.util.Log
import com.minapay.pos.data.api.*
import com.minapay.pos.data.model.*
import kotlinx.coroutines.*
import kotlinx.coroutines.channels.ProducerScope
import kotlinx.coroutines.channels.awaitClose
import kotlinx.coroutines.flow.*
import java.math.BigDecimal

private const val TAG = "PaymentMonitor"

data class MonitorEvent(
    val status: PaymentStatus,
    val txHash: String? = null,
    val confirmations: Int = 0,
    val receivedAmount: BigDecimal = BigDecimal.ZERO,
    val isDemoConfirmed: Boolean = false,
    val alert: PaymentAlert = PaymentAlert.NONE
)

enum class PaymentAlert { NONE, AMOUNT_SHORTAGE, AMOUNT_EXCESS, DUPLICATE_PAYMENT }

interface PaymentMonitor {
    fun startMonitoring(payment: Payment, usedTxHashes: Set<String> = emptySet()): Flow<MonitorEvent>
    fun stopMonitoring()
}

// ── Demo ─────────────────────────────────────────────────────────────────────
class DemoPaymentMonitor : PaymentMonitor {
    private val trigger = MutableSharedFlow<Unit>(extraBufferCapacity = 1)

    fun triggerManualConfirm() { trigger.tryEmit(Unit) }

    override fun startMonitoring(payment: Payment, usedTxHashes: Set<String>): Flow<MonitorEvent> =
        trigger.map {
            MonitorEvent(
                status          = PaymentStatus.CONFIRMED,
                txHash          = "DEMO_TX_${System.currentTimeMillis()}",
                confirmations   = 1,
                receivedAmount  = payment.cryptoAmount,
                isDemoConfirmed = true
            )
        }

    override fun stopMonitoring() {}
}

// ── Blockchain ────────────────────────────────────────────────────────────────
class BlockchainPaymentMonitor : PaymentMonitor {

    private var xrplMonitor: XrplWebSocketMonitor? = null

    override fun startMonitoring(
        payment: Payment,
        usedTxHashes: Set<String>
    ): Flow<MonitorEvent> = channelFlow {

        Log.i(TAG, "Start ${payment.cryptoCurrency.name} wallet=${payment.merchantWallet}")

        when (payment.cryptoCurrency) {
            CryptoCurrency.BTC ->
                poll("BTC", 10_000, 1, payment, usedTxHashes) { fetchBtcTx(payment.merchantWallet) }
            CryptoCurrency.ETH ->
                poll("ETH", 5_000, 1, payment, usedTxHashes) { fetchEthTx(payment.merchantWallet) }
            CryptoCurrency.SOL ->
                poll("SOL", 4_000, 1, payment, usedTxHashes) { fetchSolTx(payment.merchantWallet) }
            CryptoCurrency.JPYC ->
                poll("JPYC", 5_000, 1, payment, usedTxHashes) { fetchJpycTx(payment.merchantWallet) }
            CryptoCurrency.XRP -> {
                xrplMonitor = XrplWebSocketMonitor(
                    address          = payment.merchantWallet,
                    paymentCreatedAt = payment.createdAt,
                    onDetected  = { hash, amount ->
                        val alert = checkAmt(payment.cryptoAmount, amount)
                        trySend(MonitorEvent(PaymentStatus.DETECTED, hash, 0, amount, alert = alert))
                    },
                    onConfirmed = { hash, amount ->
                        val alert = checkAmt(payment.cryptoAmount, amount)
                        trySend(MonitorEvent(PaymentStatus.CONFIRMED, hash, 1, amount, alert = alert))
                    }
                )
                xrplMonitor?.start()

                // REST バックアップ（WebSocket失敗時）
                launch {
                    delay(10_000)
                    while (currentCoroutineContext().isActive) {
                        if (xrplMonitor?.lastSeenHash?.isNotBlank() == true) break
                        val result = try { fetchXrpTx(payment.merchantWallet) } catch (_: Exception) { null }
                        if (result == null) { delay(10_000); continue }
                        if (usedTxHashes.contains(result.hash)) { delay(10_000); continue }
                        val margin = 60_000L
                        if (result.blockTimeMs > 0L && result.blockTimeMs < payment.createdAt - margin) {
                            delay(10_000); continue
                        }
                        val alert = checkAmt(payment.cryptoAmount, result.receivedAmount)
                        trySend(MonitorEvent(PaymentStatus.CONFIRMED, result.hash, 1,
                            result.receivedAmount, alert = alert))
                        break
                    }
                }

                awaitClose { xrplMonitor?.stop() }
            }
        }
    }

    override fun stopMonitoring() {
        xrplMonitor?.stop()
        xrplMonitor = null
        Log.i(TAG, "Monitor stopped")
    }

    private fun checkAmt(expected: BigDecimal, received: BigDecimal): PaymentAlert {
        if (received == BigDecimal.ZERO) return PaymentAlert.NONE
        val tol = expected.multiply(BigDecimal("0.01"))
        return when {
            received < expected.subtract(tol) -> PaymentAlert.AMOUNT_SHORTAGE
            received > expected.add(tol)      -> PaymentAlert.AMOUNT_EXCESS
            else                              -> PaymentAlert.NONE
        }
    }

    private suspend fun ProducerScope<MonitorEvent>.poll(
        label: String,
        intervalMs: Long,
        requiredConfirms: Int,
        payment: Payment,
        usedTxHashes: Set<String>,
        fetch: suspend () -> TxResult?
    ) {
        var errors   = 0
        var detected = false
        var lastHash = ""

        Log.i(TAG, "$label poll start interval=${intervalMs}ms")

        while (currentCoroutineContext().isActive) {
            try {
                val result = fetch()
                errors = 0

                if (result == null) {
                    delay(intervalMs)
                    continue
                }

                // フィルター1: 使用済みTX
                if (result.hash.isNotBlank() && usedTxHashes.contains(result.hash)) {
                    delay(intervalMs)
                    continue
                }

                // フィルター2: 古いTX（1分以上前）
                if (result.blockTimeMs > 0L && result.blockTimeMs < payment.createdAt - 60_000L) {
                    delay(intervalMs)
                    continue
                }

                val alert = checkAmt(payment.cryptoAmount, result.receivedAmount)

                // 二重支払い
                if (detected && result.hash.isNotBlank() && result.hash != lastHash) {
                    send(MonitorEvent(PaymentStatus.DETECTED, result.hash, 0,
                        result.receivedAmount, alert = PaymentAlert.DUPLICATE_PAYMENT))
                    delay(intervalMs)
                    continue
                }

                when {
                    // CONFIRMED: 金額が正しいTXが確認された場合のみ完了
                    result.confirmations >= requiredConfirms && alert != PaymentAlert.AMOUNT_SHORTAGE -> {
                        Log.i(TAG, "✅ $label CONFIRMED ${result.hash.take(16)}")
                        send(MonitorEvent(PaymentStatus.CONFIRMED, result.hash,
                            result.confirmations, result.receivedAmount, alert = alert))
                        return
                    }
                    // CONFIRMED: 金額不足でも確認済みの場合は警告付きで完了
                    result.confirmations >= requiredConfirms && alert == PaymentAlert.AMOUNT_SHORTAGE -> {
                        // 金額不足のTXは無視して次のTXを待つ
                        Log.w(TAG, "▶ $label AMOUNT_SHORTAGE TX ignored, waiting for correct TX")
                        delay(intervalMs); continue
                    }
                    !detected -> {
                        detected = true
                        lastHash = result.hash
                        Log.i(TAG, "▶ $label DETECTED ${result.hash.take(16)}")
                        send(MonitorEvent(PaymentStatus.DETECTED, result.hash,
                            0, result.receivedAmount, alert = alert))
                    }
                    else ->
                        Log.d(TAG, "$label waiting conf=${result.confirmations}/$requiredConfirms")
                }

            } catch (e: CancellationException) {
                throw e
            } catch (e: Exception) {
                errors++
                Log.w(TAG, "$label error #$errors: ${e.message}")
                delay(minOf(intervalMs * errors, 30_000L))
                continue
            }
            delay(intervalMs)
        }
    }
}
