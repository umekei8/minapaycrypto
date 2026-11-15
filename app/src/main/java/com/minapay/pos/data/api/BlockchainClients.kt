package com.minapay.pos.data.api

import android.util.Log
import okhttp3.*
import org.json.JSONArray
import org.json.JSONObject
import java.math.BigDecimal
import java.util.concurrent.TimeUnit

private const val TAG = "BlockchainAPI"

private fun httpClient() = OkHttpClient.Builder()
    .connectTimeout(15, TimeUnit.SECONDS)
    .readTimeout(15, TimeUnit.SECONDS)
    .retryOnConnectionFailure(true)
    .build()

private fun get(url: String): String? = try {
    val resp = httpClient().newCall(
        Request.Builder().url(url)
            .addHeader("User-Agent", "MinaPayCrypto/1.0 Android")
            .addHeader("Accept", "application/json")
            .build()
    ).execute()
    val body = resp.body?.string()
    Log.d(TAG, "GET ${url.take(80)} → ${resp.code}")
    if (resp.isSuccessful) body
    else { Log.w(TAG,"HTTP ${resp.code}: ${body?.take(100)}"); null }
} catch (e: Exception) { Log.w(TAG,"GET ${e.javaClass.simpleName}: ${e.message}"); null }

private fun postJson(url: String, body: String): String? = try {
    val resp = httpClient().newCall(
        Request.Builder().url(url)
            .addHeader("User-Agent","MinaPayCrypto/1.0 Android")
            .addHeader("Content-Type","application/json")
            .addHeader("Accept","application/json")
            .post(RequestBody.create(MediaType.parse("application/json"), body))
            .build()
    ).execute()
    val rb = resp.body?.string()
    Log.d(TAG, "POST ${url.take(60)} → ${resp.code}")
    if (resp.isSuccessful) rb
    else { Log.w(TAG,"HTTP ${resp.code}: ${rb?.take(100)}"); null }
} catch (e: Exception) { Log.w(TAG,"POST ${e.javaClass.simpleName}: ${e.message}"); null }

private fun parseIso8601Ms(s: String): Long {
    if (s.isBlank()) return 0L
    return try {
        val fmt = if (s.contains("."))
            java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", java.util.Locale.US)
        else
            java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss'Z'", java.util.Locale.US)
        fmt.timeZone = java.util.TimeZone.getTimeZone("UTC")
        fmt.parse(s)?.time ?: 0L
    } catch (_: Exception) { 0L }
}

data class TxResult(
    val hash: String,
    val receivedAmount: BigDecimal,
    val confirmations: Int,
    val blockTimeMs: Long = 0L
)

// ══════════════════════════════════════════════════════════════════════════════
// BTC — mempool.space（confirmed=trueで1承認確定）
// ══════════════════════════════════════════════════════════════════════════════
suspend fun fetchBtcTx(address: String): TxResult? = try {
    val arr = JSONArray(get("https://mempool.space/api/address/$address/txs") ?: return null)
    for (i in 0 until arr.length()) {
        val tx   = arr.getJSONObject(i)
        val txid = tx.optString("txid","")
        val vout = tx.optJSONArray("vout") ?: continue
        var sat  = 0L
        for (j in 0 until vout.length())
            if (vout.getJSONObject(j).optString("scriptpubkey_address","") == address)
                sat += vout.getJSONObject(j).optLong("value", 0L)
        if (sat == 0L) continue
        val status      = tx.optJSONObject("status")
        val confirmed   = status?.optBoolean("confirmed",false) ?: false
        val blockTimeSec= status?.optLong("block_time",0L) ?: 0L
        val amount      = BigDecimal(sat).divide(BigDecimal("100000000"))
        Log.i(TAG,"BTC tx=${txid.take(16)} confirmed=$confirmed sat=$sat")
        return TxResult(txid, amount, if (confirmed) 1 else 0, blockTimeSec * 1000L)
    }
    null
} catch (e: Exception) { Log.w(TAG,"BTC: ${e.message}"); null }

// ══════════════════════════════════════════════════════════════════════════════
// ETH — 5段フォールバック（どれか1つでも動けばOK）
// blockTimeMsフィルターより同一ハッシュチェックで古いTXを弾く
// ══════════════════════════════════════════════════════════════════════════════
suspend fun fetchEthTx(address: String, etherscanApiKey: String = ""): TxResult? {

    // 方法1: Alchemy getAssetTransfers（最高信頼性）
    val alchemyEndpoints = listOf(
        "https://eth-mainnet.g.alchemy.com/v2/demo",
        "https://ethereum.publicnode.com",
        "https://rpc.ankr.com/eth"
    )
    for (ep in alchemyEndpoints) {
        try {
            val rb = postJson(ep, """{"id":1,"jsonrpc":"2.0",
                "method":"alchemy_getAssetTransfers","params":[{
                "fromBlock":"0x0","toBlock":"latest",
                "toAddress":"${address.lowercase()}",
                "category":["external","internal"],
                "withMetadata":true,"excludeZeroValue":true,
                "maxCount":"0x5","order":"desc"}]}""") ?: continue

            val json = JSONObject(rb)
            if (json.has("error")) { Log.d(TAG,"ETH Alchemy $ep: ${json.optJSONObject("error")?.optString("message")}"); continue }
            val transfers = json.optJSONObject("result")?.optJSONArray("transfers") ?: continue
            if (transfers.length() == 0) {
                Log.d(TAG,"ETH(Alchemy/$ep) empty → no incoming TX")
                return null  // 受信なし確定
            }
            // 全転送をループして最新のものを返す（transfers[0]だけでなく全件確認）
            var bestResult: TxResult? = null
            for (i in 0 until transfers.length()) {
                val tx   = transfers.getJSONObject(i)
                val hash = tx.optString("hash","")
                if (hash.isBlank()) continue
                val value       = tx.optDouble("value",0.0)
                if (value <= 0.0) continue
                val blockN      = tx.optString("blockNum","")
                val conf        = if (blockN.isNotBlank() && blockN != "0x0") 1 else 0
                val tsStr       = tx.optJSONObject("metadata")?.optString("blockTimestamp","") ?: ""
                val blockTimeMs = parseIso8601Ms(tsStr)
                Log.i(TAG,"ETH(Alchemy/$ep) [$i] hash=${hash.take(16)} value=$value conf=$conf blockTimeMs=$blockTimeMs")
                // 最初の有効なTXを返す（desc順なので最新が先頭）
                if (bestResult == null) {
                    bestResult = TxResult(hash, BigDecimal(value.toString()), conf, blockTimeMs)
                }
            }
            if (bestResult != null) return bestResult
        } catch (e: Exception) { Log.w(TAG,"ETH Alchemy $ep: ${e.message}") }
    }

    // 方法2: eth_getTransactionCount で残高変化を検知
    // + eth_getBalance で残高確認 → 残高増加でTX受信を確認
    // ただしどのTXかは分からないので、eth_getBlockByNumber でTXを探す
    val rpcEndpoints = listOf(
        "https://rpc.ankr.com/eth",
        "https://ethereum.publicnode.com",
        "https://cloudflare-eth.com"
    )
    for (ep in rpcEndpoints) {
        try {
            // 最新20ブロックのTXを確認（約4分）
            val blockNumResp = postJson(ep,
                """{"jsonrpc":"2.0","id":1,"method":"eth_blockNumber","params":[]}""") ?: continue
            val latestHex   = JSONObject(blockNumResp).optString("result","0x0")
            val latest      = latestHex.removePrefix("0x").toLongOrNull(16) ?: continue

            // 最新5ブロックを確認（最近の送金を検知）
            for (blockOffset in 0..4) {
                val blockHex  = "0x${(latest - blockOffset).toString(16)}"
                val blockResp = postJson(ep,
                    """{"jsonrpc":"2.0","id":1,"method":"eth_getBlockByNumber","params":["$blockHex",true]}""")
                    ?: continue
                val txns = JSONObject(blockResp).optJSONObject("result")?.optJSONArray("transactions")
                    ?: continue
                val blockTimeSec = JSONObject(blockResp).optJSONObject("result")
                    ?.optString("timestamp","0x0")
                    ?.removePrefix("0x")?.toLongOrNull(16) ?: 0L

                for (i in 0 until txns.length()) {
                    val tx  = txns.getJSONObject(i)
                    val to  = tx.optString("to","")
                    if (!to.equals(address, ignoreCase=true)) continue
                    val hash    = tx.optString("hash","")
                    val valHex  = tx.optString("value","0x0")
                    val weiLong = valHex.removePrefix("0x").toLongOrNull(16) ?: 0L
                    if (weiLong == 0L) continue
                    val amount  = BigDecimal(weiLong).divide(BigDecimal("1000000000000000000"))
                    Log.i(TAG,"ETH(blockScan/$ep) hash=${hash.take(16)} amount=$amount block=$blockHex")
                    return TxResult(hash, amount, 1, blockTimeSec * 1000L)
                }
            }
        } catch (e: Exception) { Log.w(TAG,"ETH RPC $ep: ${e.message}") }
    }

    Log.w(TAG,"ETH: all methods failed")
    return null
}

// ══════════════════════════════════════════════════════════════════════════════
// SOL — Solana RPC（複数エンドポイント）
// ══════════════════════════════════════════════════════════════════════════════
suspend fun fetchSolTx(address: String): TxResult? {
    val endpoints = listOf(
        "https://api.mainnet-beta.solana.com",
        "https://rpc.ankr.com/solana",
        "https://solana.publicnode.com"
    )
    for (ep in endpoints) {
        try {
            val body = postJson(ep, """{"jsonrpc":"2.0","id":1,
                "method":"getSignaturesForAddress",
                "params":["$address",{"limit":5,"commitment":"confirmed"}]}""") ?: continue
            val result = JSONObject(body).optJSONArray("result") ?: continue
            if (result.length() == 0) return null
            val tx  = result.getJSONObject(0)
            val sig = tx.optString("signature","")
            if (sig.isBlank()) continue
            if (!tx.isNull("err") && tx.get("err") != JSONObject.NULL) {
                Log.d(TAG,"SOL: top TX has error → skip"); continue
            }
            val confSt      = tx.optString("confirmationStatus","")
            val conf        = if (confSt=="confirmed"||confSt=="finalized") 1 else 0
            val blockTimeSec= tx.optLong("blockTime",0L)
            Log.i(TAG,"SOL sig=${sig.take(16)} conf=$conf blockTime=$blockTimeSec ep=$ep")
            return TxResult(sig, BigDecimal.ZERO, conf, blockTimeSec * 1000L)
        } catch (e: Exception) { Log.w(TAG,"SOL $ep: ${e.message}") }
    }
    return null
}

// ══════════════════════════════════════════════════════════════════════════════
// XRP — XRPL WebSocket + REST APIフォールバック
// ══════════════════════════════════════════════════════════════════════════════
class XrplWebSocketMonitor(
    private val address: String,
    private val paymentCreatedAt: Long = 0L,
    private val onDetected:  (hash: String, amount: BigDecimal) -> Unit,
    private val onConfirmed: (hash: String, amount: BigDecimal) -> Unit
) {
    private val client = OkHttpClient.Builder()
        .connectTimeout(20, TimeUnit.SECONDS)
        .readTimeout(0, TimeUnit.SECONDS)
        .pingInterval(20, TimeUnit.SECONDS)
        .build()
    private var ws: WebSocket? = null
    @Volatile private var started = false
    @Volatile var lastSeenHash = ""  // 最後に検知したTXハッシュ

    fun start() { if (started) return; started = true; connect() }

    private fun connect() {
        Log.i(TAG,"XRP WS connecting: $address")
        ws = client.newWebSocket(
            Request.Builder().url("wss://xrplcluster.com").build(),
            object : WebSocketListener() {
                override fun onOpen(ws: WebSocket, r: Response) {
                    // subscribeと同時に直近TXも取得
                    ws.send("""{"command":"subscribe","accounts":["$address"]}""")
                    // 直近TX確認（接続時に未払いTXがある場合に対応）
                    ws.send("""{"command":"account_tx","account":"$address","limit":5}""")
                    Log.i(TAG,"XRP WS subscribed + account_tx requested")
                }
                override fun onMessage(ws: WebSocket, text: String) {
                    try {
                        val j = JSONObject(text)
                        val type = j.optString("type","")
                        // リアルタイムTX
                        if (type == "transaction") {
                            handleTx(j.optJSONObject("transaction"), j.optBoolean("validated",false))
                        }
                        // 過去TX一覧（接続時に取得）
                        if (j.has("transactions")) {
                            val txs = j.optJSONArray("transactions")
                            if (txs != null && txs.length() > 0) {
                                val latest = txs.getJSONObject(0)
                                handleTx(latest.optJSONObject("tx"), latest.optBoolean("validated",false))
                            }
                        }
                    } catch (_: Exception) {}
                }
                private fun handleTx(tx: JSONObject?, validated: Boolean) {
                    if (tx == null) return
                    if (tx.optString("TransactionType") != "Payment") return
                    if (tx.optString("Destination") != address) return
                    val hash = tx.optString("hash","").ifBlank { return }
                    if (hash == lastSeenHash) return  // 同じTXを重複処理しない
                    val drops  = tx.optString("Amount","0").toLongOrNull() ?: 0L
                    val amount = BigDecimal(drops).divide(BigDecimal("1000000"))
                    // 古いTXをフィルタ
                    val rippleDate = tx.optLong("date",0L)
                    val txTimeMs   = if (rippleDate > 0L) (rippleDate + 946684800L) * 1000L else 0L
                    if (paymentCreatedAt > 0L && txTimeMs > 0L && txTimeMs < paymentCreatedAt) {
                        Log.d(TAG,"XRP: old TX ${hash.take(12)} → skip"); return
                    }
                    lastSeenHash = hash
                    Log.i(TAG,"XRP ${if (validated) "CONFIRMED" else "DETECTED"} hash=${hash.take(12)} amount=$amount XRP")
                    onDetected(hash, amount)
                    if (validated) onConfirmed(hash, amount)
                }
                override fun onFailure(ws: WebSocket, t: Throwable, r: Response?) {
                    Log.w(TAG,"XRP WS failure: ${t.message}"); scheduleReconnect()
                }
                override fun onClosed(ws: WebSocket, code: Int, reason: String) {
                    if (started) scheduleReconnect()
                }
            }
        )
    }

    private fun scheduleReconnect() {
        if (!started) return
        android.os.Handler(android.os.Looper.getMainLooper())
            .postDelayed({ if (started) connect() }, 3000)
    }

    fun stop() { started = false; ws?.close(1000,"done"); ws = null }
}

// XRP REST APIポーリング（WebSocket失敗時のフォールバック）
suspend fun fetchXrpTx(address: String): TxResult? = try {
    val body = get("https://data.ripple.com/v2/accounts/$address/payments?limit=5&descending=true")
        ?: return null
    val payments = JSONObject(body).optJSONArray("payments") ?: return null
    if (payments.length() == 0) return null
    val tx         = payments.getJSONObject(0)
    val hash       = tx.optString("tx_hash","")
    val executed   = tx.optString("executed_time","")
    val dropsStr   = tx.optJSONObject("payment")?.optString("delivered_amount","0") ?: "0"
    val drops      = dropsStr.toLongOrNull() ?: 0L
    val amount     = BigDecimal(drops).divide(BigDecimal("1000000"))
    val timeMs     = if (executed.isNotBlank()) parseIso8601Ms(executed) else 0L
    Log.i(TAG,"XRP(REST) hash=${hash.take(16)} amount=$amount XRP")
    TxResult(hash, amount, 1, timeMs)
} catch (e: Exception) { Log.w(TAG,"XRP REST: ${e.message}"); null }



// ══════════════════════════════════════════════════════════════════════════════
// JPYC — ERC-20 on Ethereum
// ══════════════════════════════════════════════════════════════════════════════
suspend fun fetchJpycTx(address: String, etherscanApiKey: String = ""): TxResult? {
    val contract      = "0x2370f9d504c7a6E775bf6E14B3F12846b594cD53"
    val transferTopic = "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef"
    val addrPadded    = "0x${address.removePrefix("0x").lowercase().padStart(64,'0')}"

    // 1次: Alchemy
    val alchemyEps = listOf("https://eth-mainnet.g.alchemy.com/v2/demo","https://rpc.ankr.com/eth")
    for (ep in alchemyEps) {
        try {
            val rb = postJson(ep, """{"id":1,"jsonrpc":"2.0",
                "method":"alchemy_getAssetTransfers","params":[{
                "fromBlock":"0x0","toBlock":"latest",
                "toAddress":"${address.lowercase()}",
                "contractAddresses":["$contract"],
                "category":["erc20"],
                "withMetadata":true,"excludeZeroValue":true,
                "maxCount":"0x5","order":"desc"}]}""") ?: continue
            val json = JSONObject(rb)
            if (json.has("error")) continue
            val transfers = json.optJSONObject("result")?.optJSONArray("transfers") ?: continue
            if (transfers.length() == 0) return null
            val tx          = transfers.getJSONObject(0)
            val hash        = tx.optString("hash","")
            if (hash.isBlank()) continue
            val value       = tx.optDouble("value",0.0)
            val blockN      = tx.optString("blockNum","")
            val conf        = if (blockN.isNotBlank() && blockN != "0x0") 1 else 0
            val tsStr       = tx.optJSONObject("metadata")?.optString("blockTimestamp","") ?: ""
            val blockTimeMs = parseIso8601Ms(tsStr)
            Log.i(TAG,"JPYC(Alchemy/$ep) hash=${hash.take(16)} value=$value")
            return TxResult(hash, BigDecimal(value.toString()), conf, blockTimeMs)
        } catch (e: Exception) { Log.w(TAG,"JPYC Alchemy $ep: ${e.message}") }
    }

    // 2次: eth_getLogs（直近200ブロック）
    val rpcEps = listOf("https://rpc.ankr.com/eth","https://cloudflare-eth.com")
    for (ep in rpcEps) {
        try {
            val blockNumResp = postJson(ep,
                """{"jsonrpc":"2.0","id":1,"method":"eth_blockNumber","params":[]}""") ?: continue
            val latest  = JSONObject(blockNumResp).optString("result","0x0")
                .removePrefix("0x").toLongOrNull(16) ?: continue
            val from    = "0x${(latest-200).coerceAtLeast(0).toString(16)}"
            val to      = "0x${latest.toString(16)}"
            val logsBody= postJson(ep, """{"jsonrpc":"2.0","id":1,"method":"eth_getLogs","params":[{
                "fromBlock":"$from","toBlock":"$to",
                "address":"$contract",
                "topics":["$transferTopic",null,"$addrPadded"]}]}""") ?: continue
            val logs = JSONObject(logsBody).optJSONArray("result")
            if (logs != null && logs.length() > 0) {
                val log      = logs.getJSONObject(logs.length()-1)
                val hash     = log.optString("transactionHash","")
                val blockHex = log.optString("blockNumber","0x0")
                val block    = blockHex.removePrefix("0x").toLongOrNull(16) ?: 0L
                val dataHex  = log.optString("data","0x").removePrefix("0x")
                val amount   = runCatching {
                    if (dataHex.length >= 64)
                        BigDecimal(java.math.BigInteger(dataHex.take(64),16))
                            .divide(BigDecimal("1000000000000000000"))
                    else BigDecimal.ZERO
                }.getOrDefault(BigDecimal.ZERO)
                var blockTimeMs = 0L
                try {
                    val blkResp = postJson(ep,"""{"jsonrpc":"2.0","id":1,
                        "method":"eth_getBlockByNumber","params":["$blockHex",false]}""")
                    val tsSec = JSONObject(blkResp?:"{}").optJSONObject("result")
                        ?.optString("timestamp","0x0")
                        ?.removePrefix("0x")?.toLongOrNull(16) ?: 0L
                    blockTimeMs = tsSec * 1000L
                } catch (_: Exception) {}
                if (hash.isNotBlank()) {
                    Log.i(TAG,"JPYC(getLogs/$ep) hash=${hash.take(16)} block=$block amount=$amount")
                    return TxResult(hash, amount, if (block>0) 1 else 0, blockTimeMs)
                }
            }
        } catch (e: Exception) { Log.w(TAG,"JPYC getLogs $ep: ${e.message}") }
    }
    return null
}
