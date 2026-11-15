package com.minapay.pos.data.model

import com.google.gson.annotations.SerializedName

data class DemoConfig(
    @SerializedName("merchant_id")        val merchantId: String = "MERCHANT-001",
    @SerializedName("merchant_name")      val merchantName: String = "Demo Merchant",
    @SerializedName("terminal_id")        val terminalId: String = "Z108-001",

    @SerializedName("merchant_btc_address")  val merchantBtcAddress: String = "",
    @SerializedName("merchant_eth_address")  val merchantEthAddress: String = "",
    @SerializedName("merchant_sol_address")  val merchantSolAddress: String = "",
    @SerializedName("merchant_xrp_address")  val merchantXrpAddress: String = "",
    @SerializedName("merchant_jpyc_address") val merchantJpycAddress: String = "",
    @SerializedName("xrp_destination_tag")   val xrpDestinationTag: String = "",

    @SerializedName("operator_btc_address")  val operatorBtcAddress: String = "",
    @SerializedName("operator_eth_address")  val operatorEthAddress: String = "",
    @SerializedName("operator_sol_address")  val operatorSolAddress: String = "",
    @SerializedName("operator_xrp_address")  val operatorXrpAddress: String = "",
    @SerializedName("operator_jpyc_address") val operatorJpycAddress: String = "",

    @SerializedName("fee_percent")              val feePercent: Double = 6.5,
    @SerializedName("rate_markup_percent")      val rateMarkupPercent: Double = 1.0,
    @SerializedName("payment_timeout_minutes")  val paymentTimeoutMinutes: Int = 10,
    @SerializedName("max_payment_jpy")          val maxPaymentJpy: Int = 100000000,
    @SerializedName("btc_confirm_threshold")    val btcConfirmThreshold: Int = 1,
    @SerializedName("eth_confirm_threshold")    val ethConfirmThreshold: Int = 1,
    @SerializedName("api_base_url")             val apiBaseUrl: String = "",
    @SerializedName("terminal_api_key")         val terminalApiKey: String = "",
    @SerializedName("demo_mode")                val demoMode: Boolean = false
)
