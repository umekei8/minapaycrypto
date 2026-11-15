package com.minapay.pos.device.printer

import android.content.Context
import android.graphics.*
import android.util.Log
import com.minapay.pos.R
import com.minapay.pos.data.model.DemoConfig
import com.minapay.pos.data.model.Payment
import java.text.SimpleDateFormat
import java.util.*

private const val TAG = "MinaPayPrinter"

sealed class PrintResult {
    object Success : PrintResult()
    data class Error(val message: String) : PrintResult()
}

class MinaPayPrinter(private val context: Context) {

    private var printer: Any? = null
    val isSdkAvailable: Boolean

    private val FMT  = "com.zcs.sdk.print.PrnStrFormat"
    private val ALI  = "android.text.Layout\$Alignment"
    private val PAPER_OUT = 3

    init {
        isSdkAvailable = try {
            val dm = Class.forName("com.zcs.sdk.DriverManager")
                .getMethod("getInstance").invoke(null)
            printer = dm.javaClass.getMethod("getPrinter").invoke(dm)
            Log.i(TAG, "ZCS Printer OK")
            true
        } catch (e: Exception) { Log.w(TAG, "SDK N/A: ${e.message}"); false }
    }

    private fun logoBitmap(): Bitmap? = try {
        val src = context.resources.getDrawable(R.drawable.ic_minapay_logo, null)
        val lw  = 250
        val lh  = src.intrinsicHeight * lw / src.intrinsicWidth
        val bmp = Bitmap.createBitmap(lw, lh, Bitmap.Config.ARGB_8888)
        src.setBounds(0, 0, lw, lh); src.draw(Canvas(bmp))
        val gray = Bitmap.createBitmap(lw, lh, Bitmap.Config.ARGB_8888)
        Canvas(gray).drawBitmap(bmp, 0f, 0f, Paint().apply {
            colorFilter = ColorMatrixColorFilter(ColorMatrix().also { it.setSaturation(0f) })
        })
        bmp.recycle()
        gray
    } catch (e: Exception) { Log.w(TAG, "Logo: ${e.message}"); null }

    fun printReceipt(payment: Payment, config: DemoConfig): PrintResult {
        if (!isSdkAvailable || printer == null)
            return PrintResult.Error("プリンターSDKが利用できません")

        return try {
            val p        = printer!!
            val fmtClass = Class.forName(FMT)
            val aliClass = Class.forName(ALI)

            val status = p.javaClass.getMethod("getPrinterStatus").invoke(p) as Int
            if (status == PAPER_OUT) return PrintResult.Error("用紙がありません")

            val aliN = aliClass.enumConstants!![0]  // ALIGN_NORMAL   = 左
            val aliR = aliClass.enumConstants!![1]  // ALIGN_OPPOSITE = 右
            val aliC = aliClass.enumConstants!![2]  // ALIGN_CENTER   = 中央

            fun fmt(size: Int, ali: Any, bold: Boolean = false): Any {
                val f = fmtClass.newInstance()
                fmtClass.getMethod("setTextSize", Int::class.java).invoke(f, size)
                fmtClass.getMethod("setAli", aliClass).invoke(f, ali)
                try {
                    val st = Class.forName("com.zcs.sdk.print.PrnTextStyle")
                    val s  = fmtClass.getMethod("getStyle").invoke(f)
                    st.getMethod("setFakeBoldText", Boolean::class.java).invoke(s, bold)
                } catch (_: Exception) {}
                return f
            }

            // 全て addStr(1行全幅) のみ使用
            // センタリングは aliC, 左は aliN, 右は aliR で制御
            val addStr  = p.javaClass.getMethod(
                "setPrintAppendString", String::class.java, fmtClass)
            val fArrCls = java.lang.reflect.Array.newInstance(fmtClass, 0).javaClass
            val addStrs = p.javaClass.getMethod(
                "setPrintAppendStrings", Array<String>::class.java, IntArray::class.java, fArrCls)
            val addBmp = try {
                p.javaClass.getMethod("setPrintAppendBitmap", Bitmap::class.java, aliClass)
            } catch (_: Exception) { null }
            val doPrint = { p.javaClass.getMethod("setPrintStart").invoke(p) }

            // フォント定義
            val fBigC  = fmt(30, aliC, true)   // 大・中央
            val fHdrC  = fmt(26, aliC, true)   // ヘッダー・中央
            val fCtrN  = fmt(24, aliC)         // 中央・通常
            val fCtrS  = fmt(20, aliC)         // 中央・小
            val fSepF  = fmt(16, aliC)         // 区切り線専用（中央・全幅）
            val fLeft  = fmt(24, aliN)         // 左
            val fLeftB = fmt(24, aliN, true)   // 左・Bold
            val fRight = fmt(24, aliR)         // 右
            val fRightB= fmt(24, aliR, true)   // 右・Bold
            val fSmall = fmt(20, aliN)         // 左・小

            fun s(t: String, f: Any) = addStr.invoke(p, t, f)
            fun br()  = s("", fCtrS)

            // 2カラム印字（左:ラベル 右:値 を同じ行に）
            fun fArr(vararg fs: Any): Any {
                val arr = java.lang.reflect.Array.newInstance(fmtClass, fs.size)
                fs.forEachIndexed { i, v -> java.lang.reflect.Array.set(arr, i, v) }
                return arr
            }
            fun row(left: String, right: String,
                    lf: Any = fLeft, rf: Any = fRight) {
                addStrs.invoke(p, arrayOf(left, right), intArrayOf(5, 5), fArr(lf, rf))
            }
            fun rowB(left: String, right: String) = row(left, right, fLeftB, fRightB)
            fun sep() = s("==================================================", fSepF)  // 全幅48文字
            fun sep2()= s("--------------------------------------------------", fSepF)  // 全幅48文字

            val date = SimpleDateFormat("yyyy/MM/dd  HH:mm", Locale.JAPAN)
                .format(Date(payment.confirmedAt ?: System.currentTimeMillis()))
            val sym  = payment.cryptoCurrency.symbol
            val name = payment.cryptoCurrency.displayName
            val total= payment.fiatAmount + payment.operatorFeeJpy

            val feeR  = java.math.BigDecimal("6.5")
                .divide(java.math.BigDecimal("106.5"), 8, java.math.RoundingMode.HALF_UP)
            val cFee  = payment.cryptoAmount.multiply(feeR)
                .setScale(8, java.math.RoundingMode.HALF_UP)
            val cNet  = payment.cryptoAmount.subtract(cFee)
                .setScale(8, java.math.RoundingMode.HALF_UP)

            // ═══════════════════════════════════════
            // ブロック1: ヘッダー（全て1カラム・中央）
            br()
            val logo = logoBitmap()
            if (logo != null && addBmp != null) {
                try { addBmp.invoke(p, logo, aliC) }
                catch (_: Exception) { s("みなペイ Crypto POS", fBigC) }
                logo.recycle()
            } else {
                s("みなペイ Crypto POS", fBigC)
            }
            br()
            sep()                              // ===（1カラム・全幅・中央）
            s("領  収  書", fBigC)             // （1カラム・全幅・中央）
            sep()                              // ===（1カラム・全幅・中央）
            br()
            // 日時（中央）
            s(date, fCtrN)
            br()
            // 店舗情報（左）
            s("加盟店：${config.merchantName}", fLeft)
            s("端末ID：${config.terminalId}", fSmall)
            br()

            doPrint(); Thread.sleep(200)

            // ═══════════════════════════════════════
            // ブロック2: 暗号資産
            sep2()
            s("お支払い暗号資産", fHdrC)        // （1カラム・中央）
            br()
            s(payment.cryptoAmount.stripTrailingZeros().toPlainString(), fBigC)  // （1カラム・中央・大）
            s("$sym  ($name)", fCtrN)           // （1カラム・中央）
            br()
            sep2()
            // 内訳（左に項目・右に値 → addStrで右揃えフォントを使う）
            row("加盟店受取",   cNet.stripTrailingZeros().toPlainString() + " $sym")
            br()
            row("手数料 (6.5%)", "+${cFee.stripTrailingZeros().toPlainString()} $sym")
            sep2()
            rowB("送金合計", "${payment.cryptoAmount.stripTrailingZeros().toPlainString()} $sym")
            br()

            doPrint(); Thread.sleep(200)

            // ═══════════════════════════════════════
            // ブロック3: 参考価格・取引情報
            sep()
            s("参考価格（円換算）", fHdrC)       // （1カラム・中央）
            s("※決済時レートによる参考価格です", fCtrS)
            br()
            row("商品金額",      "¥${"%,d".format(payment.fiatAmount)}")
            br()
            row("手数料 (6.5%)", "+¥${"%,d".format(payment.operatorFeeJpy)}")
            sep2()
            rowB("お客様合計",   "¥${"%,d".format(total)}")
            br()
            sep()
            br()
            s("取引情報", fHdrC)                // （1カラム・中央）
            br()
            s("Transaction ID", fSmall)
            s(payment.txHash ?: "---", fSmall)
            br()
            row("確認数", "${payment.confirmations} 承認")
            br()
            sep()

            if (payment.isDemoConfirmed) {
                br()
                s("[ D E M O  M O D E ]", fHdrC)  // （1カラム・中央）
                s("実際の決済ではありません", fCtrS)
                br()
                sep()
            }

            doPrint(); Thread.sleep(200)

            // ═══════════════════════════════════════
            // ブロック4: フッター（全て1カラム・中央）
            br(); br()
            s("ありがとうございました", fBigC)   // （1カラム・中央）
            br()
            s("みなペイ Crypto", fCtrS)         // （1カラム・中央）
            br(); br(); br(); br(); br(); br()

            doPrint()
            Log.i(TAG, "Print success")
            PrintResult.Success

        } catch (e: Exception) {
            Log.e(TAG, "Print error: ${e.javaClass.simpleName}: ${e.message}", e)
            PrintResult.Error("印字エラー: ${e.message}")
        }
    }
}
