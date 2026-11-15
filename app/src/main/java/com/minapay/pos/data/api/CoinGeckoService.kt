package com.minapay.pos.data.api

import android.util.Log
import okhttp3.OkHttpClient
import okhttp3.Request
import org.json.JSONObject
import java.math.BigDecimal
import java.math.RoundingMode
import java.util.concurrent.TimeUnit

private const val TAG = "RateFetcher"

object CoinGeckoClient {

    private const val CACHE_MS    = 30_000L
    private const val DIVERGE_PCT = 3.0
    private const val USD_JPY_FB  = 150.0  // fallback

    private var cachedRates: Map<String, BigDecimal>? = null
    private var cacheTime: Long = 0L

    private fun client() = OkHttpClient.Builder()
        .connectTimeout(15, TimeUnit.SECONDS)
        .readTimeout(15, TimeUnit.SECONDS)
        .build()

    private fun get(url: String): String? = try {
        val resp = client().newCall(
            Request.Builder().url(url)
                .addHeader("User-Agent", "MinaPayCrypto/1.0 Android")
                .addHeader("Accept", "application/json")
                .build()
        ).execute()
        Log.d(TAG, "GET ${url.take(70)} → HTTP ${resp.code}")
        val body = resp.body?.string()
        if (resp.isSuccessful) body
        else { Log.w(TAG,"HTTP ${resp.code}: ${body?.take(100)}"); null }
    } catch (e: Exception) { Log.w(TAG,"GET error: ${e.message}"); null }

    suspend fun fetchJpyRates(): Map<String, BigDecimal> {
        val now = System.currentTimeMillis()
        cachedRates?.let {
            if (now - cacheTime < CACHE_MS) {
                Log.d(TAG,"Cache hit")
                return it
            }
        }

        val cg = fetchCoinGecko()
        val cc = fetchCoinCap()
        Log.i(TAG,"CoinGecko: $cg")
        Log.i(TAG,"CoinCap:   $cc")

        val merged = mergeRates(cg, cc)
        return if (merged.isNotEmpty()) {
            cachedRates = merged; cacheTime = now
            Log.i(TAG,"Final rates: $merged")
            merged
        } else {
            Log.w(TAG,"Both sources failed → cache")
            cachedRates ?: emptyMap()
        }
    }

    private fun fetchCoinGecko(): Map<String, BigDecimal> = try {
        val body = get("https://api.coingecko.com/api/v3/simple/price" +
                "?ids=bitcoin,ethereum,ripple,solana&vs_currencies=jpy&precision=0")
            ?: return emptyMap()
        val json = JSONObject(body)
        mapOf(
            "bitcoin"  to json.optJSONObject("bitcoin") ?.optLong("jpy",0L),
            "ethereum" to json.optJSONObject("ethereum")?.optLong("jpy",0L),
            "ripple"   to json.optJSONObject("ripple")  ?.optLong("jpy",0L),
            "solana"   to json.optJSONObject("solana")  ?.optLong("jpy",0L),
        ).filterValues { it != null && it > 0L }
            .mapValues { BigDecimal(it.value.toString()) }
    } catch (e: Exception) { Log.w(TAG,"CoinGecko error: ${e.message}"); emptyMap() }

    private fun fetchCoinCap(): Map<String, BigDecimal> = try {
        val usdJpy = fetchUsdJpy()
        val body   = get("https://api.coincap.io/v2/assets?ids=bitcoin,ethereum,ripple,solana")
            ?: return emptyMap()
        val data   = JSONObject(body).optJSONArray("data") ?: return emptyMap()
        val result = mutableMapOf<String, BigDecimal>()
        for (i in 0 until data.length()) {
            val asset  = data.getJSONObject(i)
            val id     = asset.optString("id","")
            val usdStr = asset.optString("priceUsd","0")
            val usd    = BigDecimal(usdStr.ifBlank{"0"})
            if (usd > BigDecimal.ZERO) {
                result[id] = usd.multiply(BigDecimal(usdJpy.toString()))
                    .setScale(0, RoundingMode.HALF_UP)
            }
        }
        result
    } catch (e: Exception) { Log.w(TAG,"CoinCap error: ${e.message}"); emptyMap() }

    private fun fetchUsdJpy(): Double = try {
        val body = get("https://open.er-api.com/v6/latest/USD") ?: return USD_JPY_FB
        JSONObject(body).optJSONObject("rates")?.optDouble("JPY", USD_JPY_FB) ?: USD_JPY_FB
    } catch (e: Exception) { USD_JPY_FB }

    private fun mergeRates(s1: Map<String,BigDecimal>, s2: Map<String,BigDecimal>)
            : Map<String,BigDecimal> {
        val keys   = setOf("bitcoin","ethereum","ripple","solana")
        val result = mutableMapOf<String,BigDecimal>()
        for (key in keys) {
            val r1 = s1[key]; val r2 = s2[key]
            result[key] = when {
                r1 != null && r2 != null -> {
                    val avg    = r1.add(r2).divide(BigDecimal("2"),0,RoundingMode.HALF_UP)
                    val diffPct= r1.subtract(r2).abs()
                        .divide(avg,4,RoundingMode.HALF_UP)
                        .multiply(BigDecimal("100")).toDouble()
                    if (diffPct > DIVERGE_PCT)
                        Log.w(TAG,"⚠ $key divergence ${diffPct}%: $r1 vs $r2")
                    Log.d(TAG,"$key avg=¥$avg (${String.format("%.2f",diffPct)}% diff)")
                    avg
                }
                r1 != null -> { Log.w(TAG,"$key: CoinCap missing"); r1 }
                r2 != null -> { Log.w(TAG,"$key: CoinGecko missing"); r2 }
                else -> { Log.e(TAG,"$key: BOTH failed"); continue }
            }
        }
        return result
    }
}
