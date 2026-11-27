package com.minapay.pos.data.api

import android.util.Log
import okhttp3.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.util.concurrent.TimeUnit

private const val TAG = "AdminAPI"
private val JSON_MEDIA = "application/json; charset=utf-8".toMediaType()

/**
 * AdminApiClient — sends payment events from the POS terminal to the
 * MinaPay Crypto Admin server for centralized reporting.
 *
 * All calls are fire-and-forget best-effort:
 *   - Failures are logged but never thrown to callers.
 *   - The terminal continues operating even when the Admin server is unreachable.
 *
 * Authentication uses the per-device api_token stored in demo_config.json
 * under "terminal_api_key". If the key is empty, all calls are silently skipped.
 */
object AdminApiClient {

    private fun buildClient() = OkHttpClient.Builder()
        .connectTimeout(10, TimeUnit.SECONDS)
        .readTimeout(10, TimeUnit.SECONDS)
        .writeTimeout(10, TimeUnit.SECONDS)
        .retryOnConnectionFailure(false)
        .build()

    // ── Helpers ─────────────────────────────────────────────────────────────

    private fun post(baseUrl: String, apiKey: String, path: String, body: JSONObject): JSONObject? {
        if (baseUrl.isBlank() || apiKey.isBlank()) return null
        val url = baseUrl.trimEnd('/') + path
        return try {
            val request = Request.Builder()
                .url(url)
                .addHeader("Authorization", "Bearer $apiKey")
                .addHeader("Accept", "application/json")
                .post(body.toString().toRequestBody(JSON_MEDIA))
                .build()
            val resp = buildClient().newCall(request).execute()
            val respBody = resp.body?.string() ?: ""
            Log.d(TAG, "POST $path → ${resp.code}")
            if (resp.isSuccessful) {
                runCatching { JSONObject(respBody) }.getOrNull()
            } else {
                Log.w(TAG, "POST $path HTTP ${resp.code}: ${respBody.take(200)}")
                null
            }
        } catch (e: Exception) {
            Log.w(TAG, "POST $path failed: ${e.javaClass.simpleName}: ${e.message}")
            null
        }
    }

    private fun get(baseUrl: String, apiKey: String, path: String): JSONObject? {
        if (baseUrl.isBlank() || apiKey.isBlank()) return null
        val url = baseUrl.trimEnd('/') + path
        return try {
            val request = Request.Builder()
                .url(url)
                .addHeader("Authorization", "Bearer $apiKey")
                .addHeader("Accept", "application/json")
                .build()
            val resp = buildClient().newCall(request).execute()
            val respBody = resp.body?.string() ?: ""
            Log.d(TAG, "GET $path → ${resp.code}")
            if (resp.isSuccessful) {
                runCatching { JSONObject(respBody) }.getOrNull()
            } else {
                Log.w(TAG, "GET $path HTTP ${resp.code}: ${respBody.take(200)}")
                null
            }
        } catch (e: Exception) {
            Log.w(TAG, "GET $path failed: ${e.javaClass.simpleName}: ${e.message}")
            null
        }
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Notify the Admin server that a new payment was created on this terminal.
     *
     * Called right after the QR code is displayed to the customer.
     * Returns true if the Admin server acknowledged the creation.
     */
    fun notifyPaymentCreated(
        baseUrl: String,
        apiKey: String,
        paymentCode: String,
        currency: String,
        amountJpy: Int,
        amountCrypto: String,
        rateJpy: String,
        receivingAddress: String,
        expiresAtMs: Long,
        feePercent: Double
    ): Boolean {
        val body = JSONObject().apply {
            put("payment_code",       paymentCode)
            put("currency",           currency)
            put("amount_jpy",         amountJpy)
            put("amount_crypto",      amountCrypto)
            put("rate_jpy",           rateJpy)
            put("receiving_address",  receivingAddress)
            put("expires_at",         expiresAtMs)
            put("fee_percent",        feePercent)
        }
        val result = post(baseUrl, apiKey, "?page=api/payment/create", body)
        val ok = result?.optBoolean("success", false) ?: false
        if (ok) Log.i(TAG, "Payment $paymentCode reported to Admin")
        else    Log.w(TAG, "Payment $paymentCode not recorded on Admin (non-critical)")
        return ok
    }

    /**
     * Notify the Admin server that a payment's status changed
     * (confirmed by blockchain, expired, or failed).
     */
    fun notifyPaymentUpdate(
        baseUrl: String,
        apiKey: String,
        paymentCode: String,
        status: String,          // "confirmed" | "expired" | "failed"
        txHash: String = "",
        confirmations: Int = 0,
        memo: String = ""
    ): Boolean {
        val body = JSONObject().apply {
            put("payment_code",  paymentCode)
            put("status",        status)
            if (txHash.isNotBlank())   put("tx_hash",       txHash)
            if (confirmations > 0)     put("confirmations", confirmations)
            if (memo.isNotBlank())     put("memo",          memo)
        }
        val result = post(baseUrl, apiKey, "?page=api/payment/update", body)
        val ok = result?.optBoolean("success", false) ?: false
        if (ok) Log.i(TAG, "Payment $paymentCode status=$status reported to Admin")
        else    Log.w(TAG, "Payment $paymentCode update=$status not sent to Admin (non-critical)")
        return ok
    }

    /**
     * Fetch merchant wallet configuration from the Admin server.
     * Returns null if the Admin server is unreachable or the key is not configured.
     * The caller should fall back to local demo_config.json wallets on null.
     */
    fun fetchConfig(baseUrl: String, apiKey: String): JSONObject? {
        return get(baseUrl, apiKey, "?page=api/config")
    }
}
