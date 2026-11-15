package com.minapay.pos.ui.payment

import androidx.compose.animation.core.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.rotate
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.window.Dialog
import com.minapay.pos.data.model.*
import com.minapay.pos.domain.payment.PaymentAlert
import com.minapay.pos.ui.*
import com.minapay.pos.ui.components.*
import com.minapay.pos.util.QrGenerator

// ── 画面振り分け ────────────────────────────────────────────────────────────
@Composable
fun PaymentScreen(
    payment: Payment,
    remainingSeconds: Int,
    isDemoMode: Boolean,
    activeAlert: PaymentAlert,
    receivedAmount: java.math.BigDecimal,
    printError: String?,
    onDismissAlert: () -> Unit,
    onDismissPrintError: () -> Unit,
    onCancel: () -> Unit,
    onReissueQr: () -> Unit,
    onBackWithMonitoring: () -> Unit,
    onDemoConfirm: () -> Unit,
    onPrint: () -> Unit,
    onNewPayment: () -> Unit,
    onHistory: () -> Unit
) {
    // 印刷エラーダイアログ
    printError?.let { msg ->
        AlertDialog(
            onDismissRequest = onDismissPrintError,
            title = { Text("印刷エラー", fontWeight = FontWeight.Bold) },
            text  = {
                Text(
                    if (msg.contains("利用できません"))
                        "この端末ではプリンターが利用できません。\nZ108実機でのみ印刷できます。"
                    else msg,
                    fontSize = 14.sp
                )
            },
            confirmButton = {
                TextButton(onClick = onDismissPrintError) {
                    Text("OK", color = Primary, fontWeight = FontWeight.Bold)
                }
            },
            containerColor = BgCard
        )
    }

    // アラートダイアログ
    if (activeAlert != PaymentAlert.NONE) {
        PaymentAlertDialog(
            alert          = activeAlert,
            expectedAmount = payment.cryptoAmount,
            receivedAmount = receivedAmount,
            symbol         = payment.cryptoCurrency.symbol,
            onDismiss      = onDismissAlert
        )
    }

    when (payment.status) {
        PaymentStatus.CONFIRMED -> CompleteScreen(payment, onPrint, onNewPayment, onHistory)
        PaymentStatus.DETECTED  -> DetectedScreen(payment)
        PaymentStatus.EXPIRED   -> ExpiredScreen(payment, onReissueQr, onBackWithMonitoring, onCancel)
        else -> QrScreen(payment, remainingSeconds, isDemoMode, onCancel, onDemoConfirm)
    }
}

// ── アラートダイアログ ───────────────────────────────────────────────────────
@Composable
private fun PaymentAlertDialog(
    alert: PaymentAlert,
    expectedAmount: java.math.BigDecimal,
    receivedAmount: java.math.BigDecimal,
    symbol: String,
    onDismiss: () -> Unit
) {
    val (title, message, color) = when (alert) {
        PaymentAlert.AMOUNT_SHORTAGE -> Triple(
            "⚠️ 金額不足",
            "送金金額が不足しています。\n\n" +
            "請求金額: ${expectedAmount.stripTrailingZeros().toPlainString()} $symbol\n" +
            "受信金額: ${receivedAmount.stripTrailingZeros().toPlainString()} $symbol\n\n" +
            "お客様に差額の追加送金をお願いしてください。",
            Color(0xFFEA8B00)
        )
        PaymentAlert.AMOUNT_EXCESS -> Triple(
            "⚠️ 過払い",
            "送金金額が超過しています。\n\n" +
            "請求金額: ${expectedAmount.stripTrailingZeros().toPlainString()} $symbol\n" +
            "受信金額: ${receivedAmount.stripTrailingZeros().toPlainString()} $symbol\n\n" +
            "差額の返金が必要な場合があります。",
            Color(0xFF2563EB)
        )
        PaymentAlert.DUPLICATE_PAYMENT -> Triple(
            "🚨 二重支払い検知",
            "同じ決済に対して複数の入金を検知しました。\n\n" +
            "受信金額: ${receivedAmount.stripTrailingZeros().toPlainString()} $symbol\n\n" +
            "重複した送金がある可能性があります。\n担当者にご確認ください。",
            Color(0xFFDC2626)
        )
        PaymentAlert.NONE -> Triple("","",Color.Black)
    }

    AlertDialog(
        onDismissRequest = onDismiss,
        title = {
            Text(title, fontSize = 18.sp, fontWeight = FontWeight.Bold, color = color)
        },
        text = {
            Text(message, fontSize = 14.sp, lineHeight = 22.sp, color = TextDark)
        },
        confirmButton = {
            Button(
                onClick = onDismiss,
                colors = ButtonDefaults.buttonColors(containerColor = color)
            ) {
                Text("確認", color = Color.White, fontWeight = FontWeight.Bold)
            }
        },
        containerColor = BgCard
    )
}

// ── QRコード表示画面 ─────────────────────────────────────────────────────────
@Composable
private fun QrScreen(
    payment: Payment,
    remainingSeconds: Int,
    isDemoMode: Boolean,
    onCancel: () -> Unit,
    onDemoConfirm: () -> Unit
) {
    val qrBitmap = remember(payment.qrPayload) { QrGenerator.generate(payment.qrPayload, 512) }
    var showDemoDialog by remember { mutableStateOf(false) }
    val coinColor = coinColor(payment.cryptoCurrency)

    Column(Modifier.fillMaxSize().background(BgScreen)) {
        PosHeader()
        Column(
            Modifier.fillMaxSize()
                .padding(horizontal = 16.dp)
                .verticalScroll(rememberScrollState())
        ) {
            Spacer(Modifier.height(10.dp))

            // ステータス + タイマー
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                StatusBadge("● 受付中", ColorSuccess, Color(0xFFDCFCE7), Color(0xFF86EFAC))
                val mm = remainingSeconds / 60; val ss = remainingSeconds % 60
                Text(
                    "□ %02d:%02d".format(mm, ss),
                    fontSize = 13.sp,
                    color = if (remainingSeconds < 60) ColorDanger else TextMid,
                    fontWeight = FontWeight.Medium
                )
            }

            Spacer(Modifier.height(10.dp))

            // 金額ブロック（被りなし・縦積みレイアウト）
            PosCard(borderColor = BorderCard) {
                // 商品金額
                InfoRow("商品金額",
                    "¥${"%,d".format(payment.fiatAmount)}",
                    fontSize = 13)
                Spacer(Modifier.height(4.dp))
                // 手数料
                InfoRow("手数料 (6.5%)",
                    "+¥${"%,d".format(payment.operatorFeeJpy)}",
                    valueColor = ColorWarning, fontSize = 13)
                HorizontalDivider(color = BorderCard, modifier = Modifier.padding(vertical = 8.dp))
                // お支払い合計
                InfoRow("お支払い合計",
                    "¥${"%,d".format(payment.fiatAmount + payment.operatorFeeJpy)}",
                    valueColor = TextDark, valueBold = true, fontSize = 15)
            }
            Text(
                "ウォレット「送信」→「スキャン」で金額が自動入力されます",
                fontSize = 10.sp, color = TextLight, textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth()
            )


            // XRP: Destination Tagのみ表示（金額はQRに含まれる）
            if (payment.cryptoCurrency == CryptoCurrency.XRP
                && payment.xrpDestinationTag.isNotBlank()) {
                Spacer(Modifier.height(6.dp))
                PosCard(borderColor = coinColor) {
                    Text("Destination Tag（取引所送金時は必須）",
                        fontSize = 11.sp, color = TextLight)
                    Spacer(Modifier.height(4.dp))
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Text(payment.xrpDestinationTag,
                            fontSize = 22.sp, fontWeight = FontWeight.Bold,
                            color = coinColor, modifier = Modifier.weight(1f))
                        IconButton(
                            onClick = { clipboardManager.setText(
                                AnnotatedString(payment.xrpDestinationTag)
                            )},
                            modifier = Modifier.size(28.dp)
                        ) { Text("📋", fontSize = 14.sp) }
                    }
                }
            }

            Spacer(Modifier.height(8.dp))

            // アドレス（横にコピーアイコン）
            PosCard {
                Text("${payment.cryptoCurrency.symbol} 受取アドレス",
                    fontSize = 10.sp, color = TextLight)
                Spacer(Modifier.height(3.dp))
                Row(
                    Modifier.fillMaxWidth(),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Text(
                        payment.merchantWallet,
                        fontSize = 11.sp, color = TextMid,
                        fontFamily = FontFamily.Monospace,
                        modifier = Modifier.weight(1f)
                    )
                    IconButton(
                        onClick = { clipboardManager.setText(AnnotatedString(payment.merchantWallet)) },
                        modifier = Modifier.size(28.dp)
                    ) {
                        Text("📋", fontSize = 14.sp)
                    }
                }
            }

            Spacer(Modifier.height(6.dp))

            // 手数料情報
            PosCard(borderColor = Color(0xFFBBF7D0)) {
                InfoRow("手数料 (6.5%・外乗せ)", "+¥${"%,d".format(payment.operatorFeeJpy)}", valueColor = ColorWarning)
                Spacer(Modifier.height(4.dp))
                InfoRow("お客様ご負担合計",
                    "¥${"%,d".format(payment.fiatAmount + payment.operatorFeeJpy)}",
                    valueColor = TextDark, valueBold = true, fontSize = 13)
            }

            Spacer(Modifier.height(10.dp))

            if (isDemoMode) {
                SecondaryButton("【DEMO】疑似決済完了", onClick = { showDemoDialog = true },
                    textColor = Primary)
                Spacer(Modifier.height(8.dp))
            }

            SecondaryButton("キャンセル", onClick = onCancel, textColor = ColorDanger)
            Spacer(Modifier.height(16.dp))
        }
    }

    if (showDemoDialog) {
        AlertDialog(
            onDismissRequest = { showDemoDialog = false },
            title = { Text("DEMO: 疑似決済完了", fontWeight = FontWeight.Bold) },
            text  = { Text("オンチェーン確認なしに完了状態にします。") },
            confirmButton = {
                TextButton(onClick = { onDemoConfirm(); showDemoDialog = false }) {
                    Text("完了にする", color = Primary, fontWeight = FontWeight.Bold)
                }
            },
            dismissButton = {
                TextButton(onClick = { showDemoDialog = false }) {
                    Text("戻る", color = TextMid)
                }
            }
        )
    }
}

// ── 送金検知中（QRを非表示にしてスピナー表示） ──────────────────────────────
@Composable
private fun DetectedScreen(payment: Payment) {
    val infinite = rememberInfiniteTransition(label = "spin")
    val rotation by infinite.animateFloat(
        0f, 360f,
        infiniteRepeatable(tween(1400, easing = LinearEasing)),
        label = "rotation"
    )

    Column(Modifier.fillMaxSize().background(BgScreen)) {
        PosHeader()
        Column(
            Modifier.fillMaxSize().padding(horizontal = 16.dp)
                .verticalScroll(rememberScrollState()),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(10.dp))
            StatusBadge("● 決済処理中", ColorWarning, Color(0xFFFFF7E6), Color(0xFFFDBA74))

            Spacer(Modifier.height(16.dp))

            // 金額
            PosCard {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    CryptoLogo(payment.cryptoCurrency, size = 36.dp)
                    Spacer(Modifier.width(12.dp))
                    Column {
                        Text("¥${"%,d".format(payment.fiatAmount)}",
                            fontSize = 22.sp, fontWeight = FontWeight.Bold, color = TextDark)
                        Text("${payment.cryptoAmount.stripTrailingZeros().toPlainString()} ${payment.cryptoCurrency.symbol}",
                            fontSize = 13.sp, fontWeight = FontWeight.Bold, color = ColorWarning)
                    }
                }
            }

            Spacer(Modifier.height(32.dp))

            // スピナー
            Box(contentAlignment = Alignment.Center, modifier = Modifier.size(140.dp)) {
                CircularProgressIndicator(
                    modifier    = Modifier.size(120.dp).rotate(rotation),
                    color       = Primary,
                    trackColor  = Color(0xFFFFE4EF),
                    strokeWidth = 8.dp,
                    strokeCap   = StrokeCap.Round
                )
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Text("確認待ち", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = TextDark)
                    Text("1 / 3", fontSize = 12.sp, fontWeight = FontWeight.Bold, color = Primary)
                }
            }

            Spacer(Modifier.height(16.dp))
            Text("ブロックチェーンで確認中です", fontSize = 14.sp,
                color = TextMid, textAlign = TextAlign.Center)
            Text("このままお待ちください",
                fontSize = 12.sp, color = TextLight, textAlign = TextAlign.Center)

            payment.txHash?.let { hash ->
                Spacer(Modifier.height(16.dp))
                PosCard {
                    Text("Transaction ID", fontSize = 10.sp, color = TextLight)
                    Text("${hash.take(32)}...",
                        fontSize = 11.sp, color = TextMid, fontFamily = FontFamily.Monospace)
                }
            }

            Spacer(Modifier.height(16.dp))
            PosCard(borderColor = Color(0xFFBBF7D0)) {
                InfoRow("お支払い合計", "¥${"%,d".format(payment.merchantAmountJpy)}",
                    valueColor = ColorSuccess, valueBold = true, fontSize = 14)
            }
        }
    }
}

// ── 期限切れ画面（3ボタン） ──────────────────────────────────────────────────
@Composable
private fun ExpiredScreen(
    payment: Payment,
    onReissueQr: () -> Unit,
    onBackWithMonitoring: () -> Unit,
    onCancel: () -> Unit
) {
    Column(Modifier.fillMaxSize().background(BgScreen)) {
        PosHeader()
        Column(
            Modifier.fillMaxSize().padding(horizontal = 16.dp)
                .verticalScroll(rememberScrollState()),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(16.dp))
            StatusBadge("● QR期限切れ", ColorDanger, Color(0xFFFFE4E6), Color(0xFFFCA5A5))

            Spacer(Modifier.height(16.dp))

            // 金額
            PosCard {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    CryptoLogo(payment.cryptoCurrency, size = 36.dp)
                    Spacer(Modifier.width(12.dp))
                    Column {
                        Text("¥${"%,d".format(payment.fiatAmount)}",
                            fontSize = 22.sp, fontWeight = FontWeight.Bold, color = TextDark)
                        Text(payment.cryptoCurrency.symbol, fontSize = 13.sp, color = TextMid)
                    }
                }
            }

            Spacer(Modifier.height(24.dp))

            // 継続監視の説明
            Box(
                Modifier.fillMaxWidth()
                    .background(Color(0xFFFFF7E6), RoundedCornerShape(12.dp))
                    .border(1.dp, Color(0xFFFDBA74), RoundedCornerShape(12.dp))
                    .padding(14.dp)
            ) {
                Text(
                    "⚠️ QRコードの有効期限が切れました\n\n" +
                    "送金済みの場合、ブロックチェーンの監視は\n" +
                    "引き続き30分間継続しています。",
                    fontSize = 13.sp, color = TextDark, lineHeight = 20.sp
                )
            }

            Spacer(Modifier.height(24.dp))

            // ① 新しいQRコードを発行
            PrimaryButton(
                text = "新しいQRコードを発行",
                onClick = onReissueQr
            )

            Spacer(Modifier.height(10.dp))

            // ② 戻る（継続監視）
            SecondaryButton(
                text = "戻る（継続監視）",
                onClick = onBackWithMonitoring
            )

            Spacer(Modifier.height(10.dp))

            // ③ キャンセル
            SecondaryButton(
                text = "キャンセル（監視停止）",
                onClick = onCancel,
                textColor = ColorDanger
            )

            Spacer(Modifier.height(16.dp))
        }
    }
}

// ── 決済完了画面 ─────────────────────────────────────────────────────────────
@Composable
private fun CompleteScreen(
    payment: Payment,
    onPrint: () -> Unit,
    onNewPayment: () -> Unit,
    onHistory: () -> Unit
) {
    Column(Modifier.fillMaxSize().background(BgScreen)) {
        PosHeader()
        Column(
            Modifier.fillMaxSize().padding(horizontal = 16.dp)
                .verticalScroll(rememberScrollState()),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(24.dp))

            Box(
                modifier = Modifier.size(96.dp)
                    .background(Color(0xFFDCFCE7), CircleShape)
                    .border(2.dp, ColorSuccess, CircleShape),
                contentAlignment = Alignment.Center
            ) {
                Text("✓", fontSize = 46.sp, fontWeight = FontWeight.Bold, color = ColorSuccess)
            }

            Spacer(Modifier.height(14.dp))
            Text("決済完了", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = TextDark)
            Text("お支払いが完了しました",
                fontSize = 12.sp, color = TextMid, modifier = Modifier.padding(top = 4.dp))

            Spacer(Modifier.height(20.dp))

            PosCard {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    CryptoLogo(payment.cryptoCurrency, size = 40.dp)
                    Spacer(Modifier.width(12.dp))
                    Column {
                        Text("¥${"%,d".format(payment.fiatAmount)}",
                            fontSize = 22.sp, fontWeight = FontWeight.Bold, color = TextDark)
                        Text("${payment.cryptoAmount.stripTrailingZeros().toPlainString()} ${payment.cryptoCurrency.symbol}",
                            fontSize = 13.sp, fontWeight = FontWeight.Bold, color = ColorWarning)
                    }
                }
                Spacer(Modifier.height(10.dp))
                HorizontalDivider(color = BorderCard)
                Spacer(Modifier.height(10.dp))
                InfoRow("手数料 (6.5%・外乗せ)", "+¥${"%,d".format(payment.operatorFeeJpy)}", valueColor = ColorWarning)
                Spacer(Modifier.height(4.dp))
                InfoRow("加盟店受取", "¥${"%,d".format(payment.fiatAmount)}",
                    valueColor = ColorSuccess, valueBold = true, fontSize = 14)
            }

            Spacer(Modifier.height(10.dp))

            PosCard {
                Text("Transaction ID", fontSize = 10.sp, color = TextLight)
                Text(payment.txHash?.let { "${it.take(32)}..." } ?: "---",
                    fontSize = 11.sp, color = TextMid, fontFamily = FontFamily.Monospace)
                Spacer(Modifier.height(6.dp))
                Text("確認数", fontSize = 10.sp, color = TextLight)
                Text("${payment.confirmations}",
                    fontSize = 13.sp, fontWeight = FontWeight.Bold, color = TextDark)
            }

            Spacer(Modifier.height(20.dp))

            PrimaryButton("レシートを印刷", onClick = onPrint)
            Spacer(Modifier.height(8.dp))
            SecondaryButton("履歴を確認する", onClick = onHistory)
            Spacer(Modifier.height(8.dp))
            SecondaryButton("ホームへ戻る", onClick = onNewPayment)
            Spacer(Modifier.height(20.dp))
        }
    }
}

private fun coinColor(c: CryptoCurrency) = when(c) {
    CryptoCurrency.BTC  -> CoinBTC
    CryptoCurrency.ETH  -> CoinETH
    CryptoCurrency.SOL  -> CoinSOL
    CryptoCurrency.XRP  -> CoinXRP
    CryptoCurrency.JPYC -> CoinJPYC
}

