package com.minapay.pos.data.repository

import com.minapay.pos.data.api.CoinGeckoClient
import com.minapay.pos.data.db.MinaPayDatabase
import com.minapay.pos.data.db.PaymentEntity
import com.minapay.pos.data.model.*
import com.minapay.pos.domain.calculator.*
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import java.math.BigDecimal
import java.text.SimpleDateFormat
import java.util.*

class PaymentRepository(private val db: MinaPayDatabase) {
    private val dao = db.paymentDao()

    suspend fun fetchRates(): Map<CryptoCurrency, BigDecimal> {
        val rates = CoinGeckoClient.fetchJpyRates()

        if (rates.isEmpty()) {
            // 両方のAPIが失敗した場合、エラーを投げる
            throw Exception("レート取得失敗: ネットワーク接続を確認してください")
        }

        // 個別通貨が取得できなかった場合もエラーに
        fun require(key: String, currency: String): BigDecimal =
            rates[key] ?: throw Exception("${currency}のレート取得に失敗しました")

        return mapOf(
            CryptoCurrency.BTC  to require("bitcoin",  "BTC"),
            CryptoCurrency.ETH  to require("ethereum", "ETH"),
            CryptoCurrency.SOL  to require("solana",   "SOL"),
            CryptoCurrency.XRP  to require("ripple",   "XRP"),
            CryptoCurrency.JPYC to BigDecimal("1")
        )
    }

    suspend fun createPayment(
        fiatAmount: Int,
        currency: CryptoCurrency,
        config: DemoConfig,
        rateJpy: BigDecimal,
        isDemoMode: Boolean = false
    ): Payment {
        val router      = WalletRouter(config)
        // レート調整
        val adjusted = RateCalculator.adjustedRate(rateJpy, config.rateMarkupPercent)
        // 通貨ごとの桁数: XRP=6桁(drops最小単位)、その他=8桁
        val scale    = when (currency) {
            CryptoCurrency.XRP -> 6
            CryptoCurrency.BTC -> 8
            else               -> 8
        }
        val cryptoAmt = RateCalculator.cryptoAmount(fiatAmount, adjusted, config.feePercent, scale)
        // 加盟店受取 = 表示金額全額
        val fee         = FeeCalculator.calculate(fiatAmount, config.feePercent)
        val wallet      = router.getMerchantWallet(currency)
        val paymentId   = "PAY-${SimpleDateFormat("yyyyMMdd",Locale.getDefault()).format(Date())}-${(System.currentTimeMillis()%1000000).toString().padStart(6,'0')}"
        val qr          = router.buildQrPayload(currency, wallet, cryptoAmt, paymentId, config.xrpDestinationTag)

        val payment = Payment(
            paymentId=paymentId, merchantId=config.merchantId, terminalId=config.terminalId,
            fiatAmount=fiatAmount, cryptoCurrency=currency, cryptoAmount=cryptoAmt,
            rateJpy=rateJpy, adjustedRateJpy=adjusted,
            rateMarkupPercent=config.rateMarkupPercent, feePercent=config.feePercent,
            merchantAmountJpy=fee.merchantNetJpy, operatorFeeJpy=fee.operatorFeeJpy,
            merchantWallet=wallet, operatorWallet=router.getOperatorWallet(currency),
            xrpDestinationTag=config.xrpDestinationTag,
            qrPayload=qr, status=PaymentStatus.WAITING_PAYMENT,
            expiresAt=System.currentTimeMillis()+config.paymentTimeoutMinutes*60_000L
        )
        dao.insert(payment.toEntity())  // DEMOモードも含めて全て保存
        return payment
    }

    suspend fun detectPayment(id: String, tx: String, confirms: Int) =
        dao.detect(id, PaymentStatus.DETECTED.name, tx, confirms, System.currentTimeMillis())

    suspend fun confirmPayment(id: String, tx: String, confirms: Int, demo: Boolean) =
        dao.confirm(id, PaymentStatus.CONFIRMED.name, tx, confirms, System.currentTimeMillis(), demo)

    suspend fun cancelPayment(id: String, memo: String?) =
        dao.cancel(id, System.currentTimeMillis(), memo)

    suspend fun expireOldPayments() = dao.expireOld(System.currentTimeMillis())

    // 過去に使用済みのTXハッシュセット（前回決済のTXを誤検知しない）
    suspend fun getUsedTxHashes(): Set<String> =
        dao.getUsedTxHashes().toSet()

    fun getPaymentsFlow(terminalId: String): Flow<List<Payment>> =
        dao.getAllFlow(terminalId).map { it.map { e -> e.toPayment() } }

    suspend fun getPayment(id: String): Payment? = dao.getById(id)?.toPayment()
}

fun Payment.toEntity() = PaymentEntity(
    paymentId=paymentId, merchantId=merchantId, terminalId=terminalId,
    fiatAmount=fiatAmount, cryptoCurrency=cryptoCurrency.name,
    cryptoAmount=cryptoAmount.toPlainString(), rateJpy=rateJpy.toPlainString(),
    adjustedRateJpy=adjustedRateJpy.toPlainString(),
    rateMarkupPercent=rateMarkupPercent, feePercent=feePercent,
    merchantAmountJpy=merchantAmountJpy, operatorFeeJpy=operatorFeeJpy,
    merchantWallet=merchantWallet, operatorWallet=operatorWallet,
    xrpDestinationTag=xrpDestinationTag, qrPayload=qrPayload,
    status=status.name, txHash=txHash, confirmations=confirmations,
    isDemoConfirmed=isDemoConfirmed, expiresAt=expiresAt,
    detectedAt=detectedAt, confirmedAt=confirmedAt,
    cancelledAt=cancelledAt, cancelMemo=cancelMemo, createdAt=createdAt
)

fun PaymentEntity.toPayment() = Payment(
    paymentId=paymentId, merchantId=merchantId, terminalId=terminalId,
    fiatAmount=fiatAmount, cryptoCurrency=CryptoCurrency.valueOf(cryptoCurrency),
    cryptoAmount=BigDecimal(cryptoAmount), rateJpy=BigDecimal(rateJpy),
    adjustedRateJpy=BigDecimal(adjustedRateJpy),
    rateMarkupPercent=rateMarkupPercent, feePercent=feePercent,
    merchantAmountJpy=merchantAmountJpy, operatorFeeJpy=operatorFeeJpy,
    merchantWallet=merchantWallet, operatorWallet=operatorWallet,
    xrpDestinationTag=xrpDestinationTag, qrPayload=qrPayload,
    status=PaymentStatus.valueOf(status), txHash=txHash, confirmations=confirmations,
    isDemoConfirmed=isDemoConfirmed, expiresAt=expiresAt,
    detectedAt=detectedAt, confirmedAt=confirmedAt,
    cancelledAt=cancelledAt, cancelMemo=cancelMemo, createdAt=createdAt
)
