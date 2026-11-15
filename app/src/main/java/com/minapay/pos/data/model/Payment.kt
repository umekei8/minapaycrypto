package com.minapay.pos.data.model

import java.math.BigDecimal

enum class PaymentStatus {
    CREATED, WAITING_PAYMENT, DETECTED, CONFIRMED, EXPIRED, CANCELLED, FAILED
}

// 新通貨: USDT削除、BTC/ETH/SOL/XRP/JPYC
enum class CryptoCurrency(
    val displayName: String,
    val symbol: String,
    val network: String
) {
    BTC  ("Bitcoin",  "BTC",  "Bitcoin"),
    ETH  ("Ethereum", "ETH",  "Ethereum"),
    SOL  ("Solana",   "SOL",  "Solana"),
    XRP  ("Ripple",   "XRP",  "XRP Ledger"),
    JPYC ("JPYC",     "JPYC", "Ethereum")
}

data class Payment(
    val paymentId: String,
    val merchantId: String,
    val terminalId: String,
    val fiatAmount: Int,
    val cryptoCurrency: CryptoCurrency,
    val cryptoAmount: BigDecimal,
    val rateJpy: BigDecimal,
    val adjustedRateJpy: BigDecimal,
    val rateMarkupPercent: Double,
    val feePercent: Double,
    val merchantAmountJpy: Int,
    val operatorFeeJpy: Int,
    val merchantWallet: String,
    val operatorWallet: String,
    val xrpDestinationTag: String = "",
    val qrPayload: String,
    var status: PaymentStatus,
    var txHash: String? = null,
    var confirmations: Int = 0,
    var isDemoConfirmed: Boolean = false,
    val expiresAt: Long,
    var detectedAt: Long? = null,
    var confirmedAt: Long? = null,
    var cancelledAt: Long? = null,
    var cancelMemo: String? = null,
    val createdAt: Long = System.currentTimeMillis()
)

data class FeeResult(val merchantNetJpy: Int, val operatorFeeJpy: Int)
