package com.minapay.pos.domain.calculator

import com.minapay.pos.data.model.*
import java.math.BigDecimal
import java.math.RoundingMode

private const val FEE_PERCENT = 6.5
private val FEE_DECIMAL = BigDecimal("6.5")
private val FEE_DIVISOR = BigDecimal("100")
private val FEE_FACTOR  = BigDecimal("1.065")

object RateCalculator {
    fun adjustedRate(rawRateJpy: BigDecimal, markupPercent: Double): BigDecimal =
        rawRateJpy.setScale(8, RoundingMode.HALF_UP)

    fun cryptoAmount(
        fiatJpy: Int,
        adjustedRate: BigDecimal,
        feePercent: Double = FEE_PERCENT,
        scale: Int = 8
    ): BigDecimal {
        if (adjustedRate <= BigDecimal.ZERO) return BigDecimal.ZERO
        val totalJpy = BigDecimal(fiatJpy).multiply(FEE_FACTOR)
        return totalJpy.divide(adjustedRate, scale, RoundingMode.CEILING)
    }

    fun ethToWei(eth: BigDecimal): BigDecimal =
        eth.multiply(BigDecimal("1000000000000000000")).setScale(0, RoundingMode.CEILING)
}

object FeeCalculator {
    fun calculate(fiatAmount: Int, feePercent: Double = FEE_PERCENT): FeeResult {
        val fee = BigDecimal(fiatAmount)
            .multiply(FEE_DECIMAL)
            .divide(FEE_DIVISOR, 0, RoundingMode.HALF_UP)
            .toInt()
        return FeeResult(merchantNetJpy = fiatAmount, operatorFeeJpy = fee)
    }
}

class WalletRouter(private val config: DemoConfig) {
    fun getMerchantWallet(c: CryptoCurrency) = when(c) {
        CryptoCurrency.BTC  -> config.merchantBtcAddress
        CryptoCurrency.ETH  -> config.merchantEthAddress
        CryptoCurrency.SOL  -> config.merchantSolAddress
        CryptoCurrency.XRP  -> config.merchantXrpAddress
        CryptoCurrency.JPYC -> config.merchantJpycAddress
    }
    fun getOperatorWallet(c: CryptoCurrency) = when(c) {
        CryptoCurrency.BTC  -> config.operatorBtcAddress
        CryptoCurrency.ETH  -> config.operatorEthAddress
        CryptoCurrency.SOL  -> config.operatorSolAddress
        CryptoCurrency.XRP  -> config.operatorXrpAddress
        CryptoCurrency.JPYC -> config.operatorJpycAddress
    }

    fun buildQrPayload(
        currency: CryptoCurrency,
        address: String,
        amount: BigDecimal,
        paymentId: String,
        destinationTag: String
    ): String = when (currency) {

        // BTC: BIP-21（amount=BTC単位・小数）
        CryptoCurrency.BTC ->
            "bitcoin:$address?amount=${amount.stripTrailingZeros().toPlainString()}&label=MinaPay"

        // ETH: EIP-681（value=wei単位・整数）
        // Bitget/Trust/MetaMask全対応
        CryptoCurrency.ETH -> {
            // EIP-681: @1 = Ethereum Mainnet (chainId=1)
            // Bitget/Trust/MetaMaskでchainを正しく認識させるために必須
            val wei = RateCalculator.ethToWei(amount)
            "ethereum:$address?value=${wei.toPlainString()}"
        }

        // SOL: Solana Pay URI（amount=SOL単位・小数）
        // Trust Wallet / Phantom / Backpack / Solflare 対応
        CryptoCurrency.SOL -> {
            val solAmt = amount.stripTrailingZeros().toPlainString()
            "solana:$address?amount=$solAmt&label=MinaPay"
        }


        // XRP: XRPL URI（amount=XRP単位・Destination Tag含む）
        // Trust Wallet / XUMM / Bitget 対応
        CryptoCurrency.XRP -> {
            val xrpAmt = amount.stripTrailingZeros().toPlainString()
            val tag    = if (destinationTag.isNotBlank()) "&dt=$destinationTag" else ""
            "ripple:$address?amount=$xrpAmt$tag"
        }

        // JPYC: EIP-681 ERC-20 transfer（uint256=JPYC単位×10^18）
        CryptoCurrency.JPYC -> {
            // EIP-681 ERC-20: @1 = Ethereum Mainnet
            val wei = amount.multiply(BigDecimal("1000000000000000000"))
                .setScale(0, RoundingMode.CEILING).toLong()
            "ethereum:0x2370f9d504c7a6E775bf6E14B3F12846b594cD53" +
            "/transfer?address=$address&uint256=$wei"
        }
    }
}
