package com.minapay.pos.ui.settings

import android.content.Context
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.QrCodeScanner
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.google.gson.Gson
import com.minapay.pos.data.model.DemoConfig
import com.minapay.pos.ui.*
import com.minapay.pos.ui.components.*
import com.minapay.pos.util.ConfigLoader
import java.io.File

@Composable
fun SettingsScreen(
    config: DemoConfig,
    demoMode: Boolean,
    onToggleDemo: (Boolean) -> Unit,
    onBack: () -> Unit,
    onSaved: () -> Unit,
    onLanguageChange: (String) -> Unit = {}
) {
    val ctx = LocalContext.current

    var btcAddr  by remember { mutableStateOf(config.merchantBtcAddress) }
    var ethAddr  by remember { mutableStateOf(config.merchantEthAddress) }
    var solAddr  by remember { mutableStateOf(config.merchantSolAddress) }
    var xrpAddr  by remember { mutableStateOf(config.merchantXrpAddress) }
    var jpycAddr by remember { mutableStateOf(config.merchantJpycAddress) }
    var xrpTag   by remember { mutableStateOf(config.xrpDestinationTag) }
    var saved    by remember { mutableStateOf(false) }

    // スキャナー制御: どのフィールドをスキャン中か
    var scanningField by remember { mutableStateOf<String?>(null) }

    // QRスキャン結果をフィールドに反映
    fun onAddressScanned(field: String, address: String) {
        when (field) {
            "BTC"  -> btcAddr  = address
            "ETH"  -> ethAddr  = address
            "SOL"  -> solAddr  = address
            "XRP"  -> xrpAddr  = address
            "JPYC" -> jpycAddr = address
        }
        scanningField = null
        saved = false
    }

    // スキャナーダイアログ
    scanningField?.let { field ->
        QrScannerDialog(
            title    = "$field ウォレットアドレスをスキャン",
            onScanned = { address -> onAddressScanned(field, address) },
            onDismiss = { scanningField = null }
        )
    }

    Column(Modifier.fillMaxSize().background(BgScreen)) {
        PosHeader()
        Column(
            Modifier.fillMaxSize()
                .padding(horizontal = 16.dp)
                .verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            Spacer(Modifier.height(12.dp))
            Row(Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically) {
                Text("設定", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = TextDark)
                OutlinedButton(onClick = onBack,
                    modifier = Modifier.height(34.dp),
                    shape = RoundedCornerShape(8.dp),
                    border = BorderStroke(1.dp, BorderCard),
                    contentPadding = PaddingValues(horizontal = 14.dp, vertical = 0.dp),
                    elevation = ButtonDefaults.buttonElevation(0.dp)
                ) { Text("‹ 戻る", fontSize = 13.sp, color = TextMid) }
            }

            if (saved) {
                Box(Modifier.fillMaxWidth()
                    .background(Color(0xFFDCFCE7), RoundedCornerShape(10.dp))
                    .border(1.dp, Color(0xFF86EFAC), RoundedCornerShape(10.dp))
                    .padding(12.dp)) {
                    Text("✓ 保存しました", color = ColorSuccess, fontWeight = FontWeight.Bold)
                }
            }

            // DEMOモード
            PosCard(borderColor = if (demoMode) ColorDanger else BorderCard) {
                Row(Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween,
                    verticalAlignment = Alignment.CenterVertically) {
                    Column {
                        Text("DEMO MODE", fontSize = 15.sp, fontWeight = FontWeight.Bold,
                            color = if (demoMode) ColorDanger else TextDark)
                        Text(if (demoMode) "ONです。実際の決済は行われません。"
                            else "OFFです。実際のブロックチェーン監視を行います。",
                            fontSize = 11.sp, color = TextMid)
                    }
                    Switch(checked = demoMode, onCheckedChange = onToggleDemo,
                        colors = SwitchDefaults.colors(
                            checkedTrackColor   = ColorDanger,
                            checkedThumbColor   = Color.White,
                            uncheckedTrackColor = BorderCard,
                            uncheckedThumbColor = Color.White
                        ))
                }
            }

            // 加盟店ウォレット（QRスキャン対応）
            SettingsGroup(title = "加盟店 受取ウォレット") {
                Text("📷 カメラアイコンをタップするとQRコードでアドレスを読み取れます",
                    fontSize = 11.sp, color = TextLight,
                    modifier = Modifier.padding(bottom = 8.dp))

                ScanField("BTC",  btcAddr,  onScan = { scanningField = "BTC"  }) { btcAddr  = it; saved = false }
                ScanField("ETH",  ethAddr,  onScan = { scanningField = "ETH"  }) { ethAddr  = it; saved = false }
                ScanField("SOL",  solAddr,  onScan = { scanningField = "SOL"  }) { solAddr  = it; saved = false }
                ScanField("XRP",  xrpAddr,  onScan = { scanningField = "XRP"  }) { xrpAddr  = it; saved = false }
                ScanField("JPYC", jpycAddr, onScan = { scanningField = "JPYC" }) { jpycAddr = it; saved = false }

                // XRP Destination Tag（テキストのみ）
                Spacer(Modifier.height(4.dp))
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text("XRP Destination Tag（任意）", fontSize = 11.sp, color = TextLight)
                    OutlinedTextField(
                        value = xrpTag, onValueChange = { xrpTag = it; saved = false },
                        modifier = Modifier.fillMaxWidth(), singleLine = true,
                        textStyle = androidx.compose.ui.text.TextStyle(color = TextDark, fontSize = 12.sp),
                        colors = OutlinedTextFieldDefaults.colors(
                            focusedBorderColor   = Primary,
                            unfocusedBorderColor = BorderCard),
                        shape = RoundedCornerShape(8.dp)
                    )
                }
            }

            // 決済設定（表示のみ）
            SettingsGroup(title = "決済設定") {
                ReadRow("手数料率",  "${config.feePercent}%（外乗せ）")
                ReadRow("QR有効時間", "${config.paymentTimeoutMinutes}分")
                ReadRow("最大金額",  "¥${"%,d".format(config.maxPaymentJpy)}（設定変更可）")
            }

            // 運営社ウォレット（読み取り専用）
            SettingsGroup(title = "運営社ウォレット（admin管理）") {
                listOf(
                    "BTC"  to config.operatorBtcAddress,
                    "ETH"  to config.operatorEthAddress,
                    "XRP"  to config.operatorXrpAddress,
                ).forEach { (label, addr) ->
                    Row(Modifier.fillMaxWidth().padding(vertical = 3.dp),
                        horizontalArrangement = Arrangement.SpaceBetween) {
                        Text(label, fontSize = 12.sp, color = TextMid)
                        Text(if (addr.isBlank()) "未設定" else addr.take(16)+"…",
                            fontSize = 11.sp, color = TextLight)
                    }
                }
            }

            // 保存ボタン
            PrimaryButton(
                text = "設定を保存",
                onClick = {
                    val updated = config.copy(
                        merchantBtcAddress  = btcAddr,
                        merchantEthAddress  = ethAddr,
                        merchantSolAddress  = solAddr,
                        merchantXrpAddress  = xrpAddr,
                        merchantJpycAddress = jpycAddr,
                        xrpDestinationTag   = xrpTag,
                        demoMode            = demoMode
                    )
                    saveConfig(ctx, updated)
                    ConfigLoader.invalidate()
                    onSaved()
                    saved = true
                }
            )
            Spacer(Modifier.height(20.dp))
        }
    }
}

// ── QRスキャンボタン付き入力フィールド ────────────────────────────────────────
@Composable
private fun ScanField(
    label: String,
    value: String,
    onScan: () -> Unit,
    onChange: (String) -> Unit
) {
    Column(verticalArrangement = Arrangement.spacedBy(4.dp),
        modifier = Modifier.padding(bottom = 8.dp)) {
        Text(label, fontSize = 11.sp, color = TextLight)
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            OutlinedTextField(
                value = value,
                onValueChange = onChange,
                modifier = Modifier.weight(1f),
                singleLine = true,
                textStyle = androidx.compose.ui.text.TextStyle(
                    color = TextDark, fontSize = 12.sp),
                placeholder = {
                    Text("アドレスを入力またはQRスキャン",
                        fontSize = 11.sp, color = TextLight)
                },
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor   = Primary,
                    unfocusedBorderColor = BorderCard),
                shape = RoundedCornerShape(8.dp)
            )
            // QRスキャンボタン
            IconButton(
                onClick = onScan,
                modifier = Modifier
                    .size(48.dp)
                    .background(Primary, RoundedCornerShape(8.dp))
            ) {
                Icon(
                    Icons.Default.QrCodeScanner,
                    contentDescription = "$label QRスキャン",
                    tint = Color.White,
                    modifier = Modifier.size(24.dp)
                )
            }
        }
    }
}

@Composable
private fun SettingsGroup(title: String, content: @Composable ColumnScope.() -> Unit) {
    Column(modifier = Modifier.fillMaxWidth()
        .background(BgCard, RoundedCornerShape(12.dp))
        .border(1.dp, BorderCard, RoundedCornerShape(12.dp))
        .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(title, color = Primary, fontSize = 13.sp, fontWeight = FontWeight.Bold)
        HorizontalDivider(color = BorderCard)
        content()
    }
}

@Composable
private fun ReadRow(label: String, value: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
        Text(label, fontSize = 13.sp, color = TextMid)
        Text(value,  fontSize = 13.sp, fontWeight = FontWeight.Medium, color = TextDark)
    }
}

private fun saveConfig(ctx: Context, config: DemoConfig) {
    runCatching { File(ctx.filesDir, "demo_config.json").writeText(Gson().toJson(config)) }
}
