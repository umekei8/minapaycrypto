package com.minapay.pos.util

import android.graphics.Bitmap
import android.graphics.Color
import android.util.Log
import com.google.zxing.BarcodeFormat
import com.google.zxing.EncodeHintType
import com.google.zxing.qrcode.QRCodeWriter

private const val TAG = "QrGenerator"

object QrGenerator {
    fun generate(payload: String, size: Int = 512): Bitmap {
        // デバッグログ: QRに埋め込む文字列を出力
        Log.i(TAG, "=========================")
        Log.i(TAG, "Generated URI: $payload")
        Log.i(TAG, "=========================")

        val hints = mapOf(
            EncodeHintType.MARGIN to 1,
            EncodeHintType.ERROR_CORRECTION to com.google.zxing.qrcode.decoder.ErrorCorrectionLevel.M
        )
        val bits = QRCodeWriter().encode(payload, BarcodeFormat.QR_CODE, size, size, hints)
        val bmp = Bitmap.createBitmap(size, size, Bitmap.Config.ARGB_8888)
        for (x in 0 until size) {
            for (y in 0 until size) {
                bmp.setPixel(x, y, if (bits[x, y]) Color.BLACK else Color.WHITE)
            }
        }
        return bmp
    }

    fun generateWithLog(
        coin: String,
        address: String,
        amount: String,
        payload: String,
        size: Int = 512
    ): Bitmap {
        Log.i(TAG, "=========================")
        Log.i(TAG, "Coin:          $coin")
        Log.i(TAG, "Address:       $address")
        Log.i(TAG, "Amount:        $amount")
        Log.i(TAG, "Generated URI: $payload")
        Log.i(TAG, "=========================")
        return generate(payload, size)
    }
}
