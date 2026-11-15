package com.minapay.pos.device.secondscreen

import android.content.Context
import android.graphics.*
import com.minapay.pos.R
import com.minapay.pos.data.model.CryptoCurrency
import com.minapay.pos.data.model.Payment

class SecondScreenRenderer(private val context: Context) {
    companion object {
        private const val W = 800
        private const val H = 1280
    }

    private fun tp(color: Int, size: Float, bold: Boolean = false, align: Paint.Align = Paint.Align.CENTER) =
        Paint().apply { this.color=color; textSize=size; textAlign=align; isAntiAlias=true; isFakeBoldText=bold }

    private val BLACK  = Color.parseColor("#1E2027")
    private val GRAY   = Color.parseColor("#5A5D6C")
    private val LIGHT  = Color.parseColor("#9B9EAC")
    private val RED    = Color.parseColor("#E31E52")
    private val ORANGE = Color.parseColor("#F98C04")
    private val GREEN  = Color.parseColor("#16A34A")
    private val WHITE  = Color.WHITE
    private val LINE   = Color.parseColor("#DDDDDD")

    private fun Canvas.tc(t: String, x: Float, y: Float, c: Int, s: Float, b: Boolean=false) = drawText(t,x,y,tp(c,s,b))
    private fun Canvas.tl(t: String, x: Float, y: Float, c: Int, s: Float, b: Boolean=false) = drawText(t,x,y,tp(c,s,b,Paint.Align.LEFT))
    private fun Canvas.tr(t: String, x: Float, y: Float, c: Int, s: Float, b: Boolean=false) = drawText(t,x,y,tp(c,s,b,Paint.Align.RIGHT))
    private fun Canvas.hl(y: Float) = drawLine(40f,y,(W-40).toFloat(),y,Paint().apply{color=LINE;strokeWidth=2f})

    private fun drawMiniLogo(canvas: Canvas, cx: Float, cy: Float, maxW: Float, maxH: Float) {
        try {
            val src=context.resources.getDrawable(R.drawable.ic_minapay_logo,null)
            val sc=minOf(maxW/src.intrinsicWidth, maxH/src.intrinsicHeight)
            val lw=(src.intrinsicWidth*sc).toInt(); val lh=(src.intrinsicHeight*sc).toInt()
            val b=Bitmap.createBitmap(lw,lh,Bitmap.Config.ARGB_8888)
            src.setBounds(0,0,lw,lh); src.draw(Canvas(b))
            canvas.drawBitmap(b,cx-lw/2f,cy-lh/2f,null); b.recycle()
        } catch(_:Exception){ canvas.tc("みなペイ",cx,cy+14f,RED,28f,true) }
    }

    private fun drawCoinLogo(canvas: Canvas, currency: CryptoCurrency, cx: Float, cy: Float, sz: Int) {
        val id = when(currency) {
            CryptoCurrency.BTC->R.drawable.ic_btc; CryptoCurrency.ETH->R.drawable.ic_eth
            CryptoCurrency.SOL->R.drawable.ic_sol; CryptoCurrency.XRP->R.drawable.ic_xrp
            CryptoCurrency.JPYC->R.drawable.ic_jpyc
        }
        try {
            val src=context.resources.getDrawable(id,null)
            val b=Bitmap.createBitmap(sz,sz,Bitmap.Config.ARGB_8888)
            src.setBounds(0,0,sz,sz); src.draw(Canvas(b))
            canvas.drawBitmap(b,cx-sz/2f,cy-sz/2f,null); b.recycle()
        } catch(_:Exception){}
    }

    // ── 待機: 白背景・ロゴ中央 ──────────────────────────────────────────────
    fun renderBlank(): Bitmap {
        val bmp=Bitmap.createBitmap(W,H,Bitmap.Config.ARGB_8888); val c=Canvas(bmp)
        c.drawColor(WHITE)
        drawMiniLogo(c,W/2f,H/2f-50f,W*0.68f,200f)
        c.tc("みなペイ Crypto",W/2f,H/2f+130f,GRAY,28f)
        return bmp
    }

    // ── QR待機画面 ───────────────────────────────────────────────────────────
    // レイアウト:
    //   ヘッダー（細め・赤）
    //   [コインロゴ] 0.00280263 ETH（コインロゴを左横に置いて横並び）
    //   内手数料: 0.000017 ETH（小・グレー）
    //   区切り線
    //   参考価格 商品金額 ¥1,000（手数料6.5%: +¥65）  支払合計 ¥1,065
    //   区切り線
    //   QRコード（残り全部）
    //   フッター
    fun renderWaiting(payment: Payment, qrBitmap: Bitmap): Bitmap {
        val bmp=Bitmap.createBitmap(W,H,Bitmap.Config.ARGB_8888); val c=Canvas(bmp)
        c.drawColor(WHITE)
        val cx=W/2f

        // ── ヘッダー（細め: 54px）────────────────────────────────────────────
        c.drawRect(0f,0f,W.toFloat(),54f,Paint().apply{color=RED})
        drawMiniLogo(c,cx,27f,260f,44f)

        var y=66f

        // ── コインロゴ + コイン数（横並び・中央寄せ）────────────────────────
        val sym=payment.cryptoCurrency.symbol
        val cryptoStr=payment.cryptoAmount.stripTrailingZeros().toPlainString()
        val coinSz=48
        val numSize=when {
            cryptoStr.length>12->44f; cryptoStr.length>8->52f; else->60f
        }
        // テキスト幅を計算して中央揃え
        val numPaint=tp(BLACK,numSize,true)
        val textW=numPaint.measureText("$cryptoStr $sym")
        val totalW=coinSz+12f+textW
        val startX=cx-totalW/2f
        // コインロゴ
        drawCoinLogo(c,payment.cryptoCurrency,startX+coinSz/2f,y+numSize/2f-4f,coinSz)
        // コイン数
        c.tl("$cryptoStr $sym",startX+coinSz+12f,y+numSize,BLACK,numSize,true)
        y+=numSize+14f

        // 内手数料（小・グレー・中央）
        val feeRatio=java.math.BigDecimal("6.5").divide(java.math.BigDecimal("106.5"),8,java.math.RoundingMode.HALF_UP)
        val cryptoFee=payment.cryptoAmount.multiply(feeRatio).setScale(8,java.math.RoundingMode.HALF_UP)
        c.tc("内手数料：${cryptoFee.stripTrailingZeros().toPlainString()} $sym",cx,y+24f,LIGHT,20f)
        y+=40f

        c.hl(y); y+=16f

        // ── 参考価格（1行コンパクト）──────────────────────────────────────────
        val total=payment.fiatAmount+payment.operatorFeeJpy
        val pad=36f
        c.tl("参考価格",pad,y+22f,LIGHT,19f)
        c.tc("商品金額 ¥${"%,d".format(payment.fiatAmount)}（手数料6.5%：+¥${"%,d".format(payment.operatorFeeJpy)}）",
            cx,y+22f,GRAY,19f)
        y+=34f
        c.tl("支払合計",pad,y+26f,BLACK,24f,true)
        c.tr("¥${"%,d".format(total)}",W-pad,y+26f,BLACK,28f,true)
        y+=42f

        c.hl(y); y+=14f

        // XRP Tag
        if(payment.cryptoCurrency==CryptoCurrency.XRP && payment.xrpDestinationTag.isNotBlank()){
            c.tc("Destination Tag（必須）: ${payment.xrpDestinationTag}",cx,y+22f,RED,20f,true)
            y+=36f
        }

        // ── QRコード（残りスペース全部使う）─────────────────────────────────
        val footerH=42f
        val available=(H-y-footerH-16f)
        val qSize=available.toInt().coerceIn(300,500)
        val qX=(W-qSize)/2f

        c.drawRoundRect(RectF(qX-8,y-2,qX+qSize+8,y+qSize+8),12f,12f,
            Paint().apply{color=Color.parseColor("#F0F0F0")})
        c.drawBitmap(qrBitmap,null,RectF(qX,y,qX+qSize,y+qSize),null)

        // ── フッター ─────────────────────────────────────────────────────────
        c.drawRect(0f,(H-footerH),W.toFloat(),H.toFloat(),Paint().apply{color=Color.parseColor("#F5F5F7")})
        c.tc("ウォレットでQRコードを読み取って送金してください",cx,H-12f,GRAY,16f)

        return bmp
    }

    // ── 処理中 ───────────────────────────────────────────────────────────────
    fun renderDetected(payment: Payment): Bitmap {
        val bmp=Bitmap.createBitmap(W,H,Bitmap.Config.ARGB_8888); val c=Canvas(bmp)
        c.drawColor(WHITE)
        c.drawRect(0f,0f,W.toFloat(),54f,Paint().apply{color=RED})
        drawMiniLogo(c,W/2f,27f,260f,44f)
        val cx=W/2f
        c.drawCircle(cx,300f,90f,Paint().apply{color=Color.parseColor("#FEE2E2");isAntiAlias=true})
        c.drawCircle(cx,300f,90f,Paint().apply{color=RED;style=Paint.Style.STROKE;strokeWidth=8f;isAntiAlias=true})
        c.tc("確認中",cx,290f,BLACK,28f,true); c.tc("1/3",cx,330f,RED,24f)
        c.tc(payment.cryptoAmount.stripTrailingZeros().toPlainString(),cx,490f,BLACK,58f,true)
        c.tc(payment.cryptoCurrency.symbol,cx,545f,ORANGE,30f,true)
        c.hl(572f)
        c.tc("¥${"%,d".format(payment.fiatAmount+payment.operatorFeeJpy)}",cx,628f,BLACK,34f,true)
        c.tc("ネットワーク承認待ち / このままお待ちください",cx,670f,GRAY,20f)
        c.drawRect(0f,(H-42f),W.toFloat(),H.toFloat(),Paint().apply{color=Color.parseColor("#F5F5F7")})
        c.tc("みなペイ Crypto POS",cx,H-12f,GRAY,16f)
        return bmp
    }

    // ── 決済完了 ─────────────────────────────────────────────────────────────
    fun renderComplete(payment: Payment): Bitmap {
        val bmp=Bitmap.createBitmap(W,H,Bitmap.Config.ARGB_8888); val c=Canvas(bmp)
        c.drawColor(WHITE)
        c.drawRect(0f,0f,W.toFloat(),54f,Paint().apply{color=GREEN})
        drawMiniLogo(c,W/2f,27f,260f,44f)
        val cx=W/2f
        c.drawCircle(cx,220f,88f,Paint().apply{color=Color.parseColor("#DCFCE7");isAntiAlias=true})
        c.drawCircle(cx,220f,88f,Paint().apply{color=GREEN;style=Paint.Style.STROKE;strokeWidth=6f;isAntiAlias=true})
        c.tc("✓",cx,202f,GREEN,82f,true)
        c.tc("ありがとうございました",cx,370f,BLACK,32f,true)
        c.hl(398f)
        c.tc(payment.cryptoAmount.stripTrailingZeros().toPlainString(),cx,480f,BLACK,60f,true)
        c.tc(payment.cryptoCurrency.symbol,cx,532f,ORANGE,30f,true)
        c.hl(558f)
        val pad=80f
        c.tl("商品金額",pad,610f,GRAY,24f); c.tr("¥${"%,d".format(payment.fiatAmount)}",W-pad,610f,BLACK,24f)
        c.tl("手数料(6.5%)",pad,644f,GRAY,24f); c.tr("+¥${"%,d".format(payment.operatorFeeJpy)}",W-pad,644f,BLACK,24f)
        c.hl(664f)
        c.tl("支払合計",pad,706f,BLACK,26f,true); c.tr("¥${"%,d".format(payment.fiatAmount+payment.operatorFeeJpy)}",W-pad,706f,BLACK,30f,true)
        c.drawRect(0f,(H-42f),W.toFloat(),H.toFloat(),Paint().apply{color=Color.parseColor("#F5F5F7")})
        c.tc("みなペイ Crypto POS",cx,H-12f,GRAY,16f)
        return bmp
    }
}

class SecondScreenManager(private val context: Context) {
    private var sys: Any? = null
    private var executor: java.util.concurrent.ExecutorService? = null
    val isSdkAvailable: Boolean
    init {
        isSdkAvailable = try {
            val dm=Class.forName("com.zcs.sdk.DriverManager").getMethod("getInstance").invoke(null)
            sys=dm.javaClass.getMethod("getBaseSysDevice").invoke(dm)
            executor=dm.javaClass.getMethod("getSingleThreadExecutor").invoke(dm) as java.util.concurrent.ExecutorService
            true
        } catch(_:Exception){ false }
    }
    fun show(bitmap: Bitmap) {
        if(!isSdkAvailable||sys==null) return
        executor?.execute { try { sys!!.javaClass.getMethod("showBitmapOnSecondaryScreen",Bitmap::class.java,Boolean::class.java).invoke(sys,bitmap,true) } catch(_:Exception){} }
    }
    fun clear(renderer: SecondScreenRenderer) {
        if(!isSdkAvailable||sys==null) return
        executor?.execute { try { val b=renderer.renderBlank(); sys!!.javaClass.getMethod("showBitmapOnSecondaryScreen",Bitmap::class.java,Boolean::class.java).invoke(sys,b,true); b.recycle() } catch(_:Exception){} }
    }
    fun restoreMainScreen() {
        if(!isSdkAvailable||sys==null) return
        executor?.execute { try { sys!!.javaClass.getMethod("showLcdMainScreen").invoke(sys) } catch(_:Exception){} }
    }
}
