package com.minapay.pos.ui.components

import android.Manifest
import android.content.pm.PackageManager
import android.util.Log
import android.view.ViewGroup
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.camera.core.*
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Close
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.LocalLifecycleOwner
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.ui.window.Dialog
import androidx.compose.ui.window.DialogProperties
import androidx.core.content.ContextCompat
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import com.minapay.pos.ui.*
import java.util.concurrent.Executors

private const val TAG = "QrScanner"

@Composable
fun QrScannerDialog(
    title: String = "ウォレットアドレスをスキャン",
    onScanned: (String) -> Unit,
    onDismiss: () -> Unit
) {
    val context      = LocalContext.current
    val lifecycle    = LocalLifecycleOwner.current
    var hasPermission by remember {
        mutableStateOf(
            ContextCompat.checkSelfPermission(context, Manifest.permission.CAMERA)
                    == PackageManager.PERMISSION_GRANTED
        )
    }
    var errorMsg by remember { mutableStateOf<String?>(null) }
    var scanned  by remember { mutableStateOf(false) }

    val launcher = rememberLauncherForActivityResult(
        ActivityResultContracts.RequestPermission()
    ) { granted -> hasPermission = granted }

    LaunchedEffect(Unit) {
        if (!hasPermission) launcher.launch(Manifest.permission.CAMERA)
    }

    Dialog(
        onDismissRequest = onDismiss,
        properties = DialogProperties(usePlatformDefaultWidth = false)
    ) {
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(Color.Black)
        ) {
            if (hasPermission && !scanned) {
                // ── カメラプレビュー ──────────────────────────────────────
                val executor = remember { Executors.newSingleThreadExecutor() }
                val scanner  = remember { BarcodeScanning.getClient() }

                AndroidView(
                    modifier = Modifier.fillMaxSize(),
                    factory  = { ctx ->
                        val previewView = PreviewView(ctx).apply {
                            layoutParams = ViewGroup.LayoutParams(
                                ViewGroup.LayoutParams.MATCH_PARENT,
                                ViewGroup.LayoutParams.MATCH_PARENT
                            )
                            scaleType = PreviewView.ScaleType.FILL_CENTER
                        }

                        val cameraProviderFuture = ProcessCameraProvider.getInstance(ctx)
                        cameraProviderFuture.addListener({
                            try {
                                val cameraProvider = cameraProviderFuture.get()

                                val preview = Preview.Builder().build().also {
                                    it.surfaceProvider = previewView.surfaceProvider
                                }

                                val imageAnalysis = ImageAnalysis.Builder()
                                    .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                                    .build()

                                imageAnalysis.setAnalyzer(executor) { imageProxy ->
                                    if (scanned) {
                                        imageProxy.close()
                                        return@setAnalyzer
                                    }
                                    val mediaImage = imageProxy.image
                                    if (mediaImage != null) {
                                        val image = InputImage.fromMediaImage(
                                            mediaImage, imageProxy.imageInfo.rotationDegrees)
                                        scanner.process(image)
                                            .addOnSuccessListener { barcodes ->
                                                for (barcode in barcodes) {
                                                    if (barcode.valueType == Barcode.TYPE_TEXT ||
                                                        barcode.valueType == Barcode.TYPE_URL ||
                                                        barcode.rawValue != null) {
                                                        val raw = barcode.rawValue ?: continue
                                                        // ウォレットアドレス抽出
                                                        val address = extractAddress(raw)
                                                        if (address != null && !scanned) {
                                                            scanned = true
                                                            Log.i(TAG, "Scanned: $address")
                                                            onScanned(address)
                                                        }
                                                    }
                                                }
                                            }
                                            .addOnCompleteListener { imageProxy.close() }
                                    } else {
                                        imageProxy.close()
                                    }
                                }

                                cameraProvider.unbindAll()
                                cameraProvider.bindToLifecycle(
                                    lifecycle,
                                    CameraSelector.DEFAULT_BACK_CAMERA,
                                    preview,
                                    imageAnalysis
                                )
                            } catch (e: Exception) {
                                Log.e(TAG, "Camera error: ${e.message}", e)
                                errorMsg = "カメラエラー: ${e.message}"
                            }
                        }, ContextCompat.getMainExecutor(ctx))

                        previewView
                    }
                )

                // ── スキャン枠オーバーレイ ───────────────────────────────
                Column(
                    modifier = Modifier.fillMaxSize(),
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.SpaceBetween
                ) {
                    // ヘッダー
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(Color.Black.copy(alpha = 0.65f))
                            .padding(horizontal = 16.dp, vertical = 14.dp)
                    ) {
                        Text(
                            title,
                            color = Color.White,
                            fontSize = 16.sp,
                            fontWeight = FontWeight.Bold,
                            modifier = Modifier.align(Alignment.Center)
                        )
                        IconButton(
                            onClick = onDismiss,
                            modifier = Modifier.align(Alignment.CenterEnd)
                        ) {
                            Icon(Icons.Default.Close, contentDescription = "閉じる",
                                tint = Color.White)
                        }
                    }

                    // 中央: スキャン枠
                    Box(
                        modifier = Modifier
                            .size(260.dp)
                            .border(2.dp, Primary, RoundedCornerShape(12.dp))
                    ) {
                        // 四隅のL型マーカー
                        val cornerColor = Primary
                        val cornerSize  = 28.dp
                        val cornerWidth = 3.dp
                        // 左上
                        Box(Modifier.size(cornerSize).align(Alignment.TopStart)) {
                            Box(Modifier.fillMaxWidth().height(cornerWidth).background(cornerColor))
                            Box(Modifier.width(cornerWidth).fillMaxHeight().background(cornerColor))
                        }
                        // 右上
                        Box(Modifier.size(cornerSize).align(Alignment.TopEnd)) {
                            Box(Modifier.fillMaxWidth().height(cornerWidth).background(cornerColor))
                            Box(Modifier.width(cornerWidth).fillMaxHeight().background(cornerColor))
                        }
                        // 左下
                        Box(Modifier.size(cornerSize).align(Alignment.BottomStart)) {
                            Box(Modifier.fillMaxWidth().height(cornerWidth).background(cornerColor))
                            Box(Modifier.width(cornerWidth).fillMaxHeight().background(cornerColor))
                        }
                        // 右下
                        Box(Modifier.size(cornerSize).align(Alignment.BottomEnd)) {
                            Box(Modifier.fillMaxWidth().height(cornerWidth).background(cornerColor))
                            Box(Modifier.width(cornerWidth).fillMaxHeight().background(cornerColor))
                        }
                    }

                    // フッター
                    Box(
                        modifier = Modifier
                            .fillMaxWidth()
                            .background(Color.Black.copy(alpha = 0.65f))
                            .padding(24.dp),
                        contentAlignment = Alignment.Center
                    ) {
                        Column(horizontalAlignment = Alignment.CenterHorizontally) {
                            Text(
                                "QRコードを枠内に合わせてください",
                                color = Color.White, fontSize = 14.sp,
                                textAlign = TextAlign.Center
                            )
                            Spacer(Modifier.height(6.dp))
                            Text(
                                "ウォレットアドレスのQRコードに対応しています",
                                color = Color.White.copy(alpha = 0.6f),
                                fontSize = 12.sp, textAlign = TextAlign.Center
                            )
                        }
                    }
                }

            } else if (!hasPermission) {
                // ── 権限未許可 ───────────────────────────────────────────
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally,
                        verticalArrangement = Arrangement.spacedBy(16.dp),
                        modifier = Modifier.padding(32.dp)
                    ) {
                        Text("📷", fontSize = 48.sp)
                        Text("カメラの許可が必要です",
                            color = Color.White, fontSize = 16.sp,
                            fontWeight = FontWeight.Bold, textAlign = TextAlign.Center)
                        Text("設定からカメラのアクセスを許可してください",
                            color = Color.White.copy(alpha = 0.7f),
                            fontSize = 13.sp, textAlign = TextAlign.Center)
                        Button(
                            onClick = { launcher.launch(Manifest.permission.CAMERA) },
                            colors = ButtonDefaults.buttonColors(containerColor = Primary)
                        ) { Text("許可する", color = Color.White, fontWeight = FontWeight.Bold) }
                        TextButton(onClick = onDismiss) {
                            Text("キャンセル", color = Color.White.copy(alpha = 0.7f))
                        }
                    }
                }
            }

            // エラー表示
            errorMsg?.let { msg ->
                Box(
                    modifier = Modifier
                        .align(Alignment.Center)
                        .background(Color.Black.copy(alpha = 0.85f), RoundedCornerShape(12.dp))
                        .padding(20.dp)
                ) {
                    Column(horizontalAlignment = Alignment.CenterHorizontally) {
                        Text(msg, color = Color.White, fontSize = 13.sp,
                            textAlign = TextAlign.Center)
                        Spacer(Modifier.height(12.dp))
                        TextButton(onClick = onDismiss) {
                            Text("閉じる", color = Primary)
                        }
                    }
                }
            }
        }
    }
}

/**
 * QRコードの生文字列からウォレットアドレスを抽出
 *
 * 対応フォーマット:
 *   bitcoin:bc1p...?amount=...  → bc1p...
 *   ethereum:0x...?value=...    → 0x...
 *   ripple:r...?amount=...      → r...
 *   solana:HN7c...?amount=...   → HN7c...
 *   ethereum:0xContract/transfer?address=0x...  → 0x...
 *   プレーンアドレス (0x..., bc1..., r..., T...)
 */
fun extractAddress(raw: String): String? {
    // URIスキーム付き
    val schemes = listOf("bitcoin:", "ethereum:", "ripple:", "solana:", "tron:")
    for (scheme in schemes) {
        if (raw.startsWith(scheme, ignoreCase = true)) {
            var addr = raw.removePrefix(scheme).removePrefix("//")
            // /transfer?address=0x... 形式（ERC-20）
            if (addr.contains("/transfer?address=")) {
                val m = Regex("address=(0x[0-9a-fA-F]{40})").find(addr)
                if (m != null) return m.groupValues[1]
            }
            // ?以降を除去
            addr = addr.substringBefore("?").substringBefore("/")
            if (addr.isNotBlank()) return addr
        }
    }

    // プレーンアドレス判定
    return when {
        // ETH / JPYC: 0x + 40 hex
        raw.matches(Regex("0x[0-9a-fA-F]{40}"))            -> raw
        // BTC legacy / segwit
        raw.matches(Regex("[13][a-km-zA-HJ-NP-Z1-9]{25,34}")) -> raw
        // BTC bech32 / taproot
        raw.matches(Regex("bc1[a-z0-9]{6,87}"))             -> raw
        // XRP
        raw.matches(Regex("r[a-zA-Z0-9]{24,33}"))           -> raw
        // SOL (base58, 32-44 chars)
        raw.matches(Regex("[1-9A-HJ-NP-Za-km-z]{32,44}"))   -> raw
        // TRON
        raw.matches(Regex("T[a-zA-Z0-9]{33}"))              -> raw
        else -> null
    }
}
