package com.minapay.pos.ui.amount

import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Menu
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalUriHandler
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.minapay.pos.data.model.CryptoCurrency
import com.minapay.pos.ui.*
import com.minapay.pos.ui.components.*

// ── ハンバーガーメニュー付きヘッダー ────────────────────────────────────────
@Composable
fun PosHeaderWithMenu(
    onHistory: () -> Unit,
    onSettings: () -> Unit,
    onSupport: () -> Unit = {},
    onTerms: () -> Unit = {},
    onAbout: () -> Unit = {}
) {
    var showMenu by remember { mutableStateOf(false) }

    Box(
        modifier = Modifier
            .fillMaxWidth()
            .height(64.dp)
            .background(Primary)
            .padding(horizontal = 16.dp),
    ) {
        // ロゴ（左）
        Row(
            Modifier.fillMaxHeight(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            androidx.compose.foundation.Image(
                painter = androidx.compose.ui.res.painterResource(
                    id = com.minapay.pos.R.drawable.ic_minapay_logo),
                contentDescription = "MinaPay",
                modifier = Modifier.height(38.dp),
                contentScale = androidx.compose.ui.layout.ContentScale.FillHeight
            )
        }

        // 右側ボタン群
        Row(
            modifier = Modifier.fillMaxHeight().align(Alignment.CenterEnd),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            // 履歴ボタン（常時表示）
            TextButton(
                onClick = onHistory,
                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 0.dp)
            ) {
                Text("履歴", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Medium)
            }
            // 設定ボタン（常時表示）
            TextButton(
                onClick = onSettings,
                contentPadding = PaddingValues(horizontal = 8.dp, vertical = 0.dp)
            ) {
                Text("設定", color = Color.White, fontSize = 13.sp, fontWeight = FontWeight.Medium)
            }
            // ハンバーガーメニュー
            Box {
                IconButton(onClick = { showMenu = true }) {
                    Icon(Icons.Default.Menu, contentDescription = "メニュー", tint = Color.White)
                }
                DropdownMenu(
                    expanded = showMenu,
                    onDismissRequest = { showMenu = false },
                    modifier = Modifier.background(BgCard)
                ) {
                    DropdownMenuItem(
                        text = { MenuItemRow("📋", "取引履歴") },
                        onClick = { showMenu = false; onHistory() }
                    )
                    DropdownMenuItem(
                        text = { MenuItemRow("⚙️", "設定") },
                        onClick = { showMenu = false; onSettings() }
                    )
                    HorizontalDivider(color = BorderCard)
                    DropdownMenuItem(
                        text = { MenuItemRow("💬", "サポートフォーム") },
                        onClick = { showMenu = false; onSupport() }
                    )
                    DropdownMenuItem(
                        text = { MenuItemRow("📜", "利用規約") },
                        onClick = { showMenu = false; onTerms() }
                    )
                    HorizontalDivider(color = BorderCard)
                    DropdownMenuItem(
                        text = { MenuItemRow("ℹ️", "アプリについて") },
                        onClick = { showMenu = false; onAbout() }
                    )
                }
            }
        }
    }
}

@Composable
private fun MenuItemRow(icon: String, label: String) {
    Row(
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp)
    ) {
        Text(icon, fontSize = 16.sp)
        Text(label, fontSize = 15.sp, color = TextDark)
    }
}

// ── 01 通貨選択画面 ──────────────────────────────────────────────────────────
@Composable
fun CurrencySelectScreen(
    onSelectCurrency: (CryptoCurrency) -> Unit,
    onHistory: () -> Unit,
    onSettings: () -> Unit,
    onSupport: () -> Unit = {},
    onTerms: () -> Unit = {},
    onAbout: () -> Unit = {}
) {
    Column(Modifier.fillMaxSize().background(BgScreen)) {
        PosHeaderWithMenu(onHistory = onHistory, onSettings = onSettings,
            onSupport = onSupport, onTerms = onTerms, onAbout = onAbout)
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 16.dp)
                .verticalScroll(rememberScrollState()),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Spacer(Modifier.height(20.dp))
            Text("お支払い方法を選択してください",
                fontSize = 17.sp, fontWeight = FontWeight.Bold,
                color = TextDark, textAlign = TextAlign.Center)
            Text("暗号通貨を選択してください",
                fontSize = 12.sp, color = TextMid,
                modifier = Modifier.padding(top = 6.dp))
            Spacer(Modifier.height(20.dp))

            CryptoCurrency.values().forEach { currency ->
                CurrencyCard(currency = currency, onClick = { onSelectCurrency(currency) })
                Spacer(Modifier.height(12.dp))
            }

            Spacer(Modifier.height(16.dp))

            // Powered by GANECCI（右寄せ・最下部）
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 16.dp, end = 4.dp),
                horizontalArrangement = Arrangement.End,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("powered by ", fontSize = 10.sp, color = TextLight)
                Image(
                    painter = painterResource(id = com.minapay.pos.R.drawable.ic_ganecci),
                    contentDescription = "GANECCI",
                    modifier = Modifier.height(18.dp),
                    contentScale = ContentScale.FillHeight
                )
            }
        }
    }
}

// ── 02 金額入力画面 ──────────────────────────────────────────────────────────
@Composable
fun AmountInputScreen(
    displayAmount: String,
    selectedCurrency: CryptoCurrency,
    previewCryptoAmount: String?,
    previewRate: String?,
    isFetchingRate: Boolean,
    feeJpy: Int,
    merchantAmountJpy: Int,
    isLoading: Boolean,
    error: String?,
    onNumKey: (String) -> Unit,
    onChangeCurrency: () -> Unit,
    onSubmit: () -> Unit,
    onHistory: () -> Unit,
    onSettings: () -> Unit,
    onSupport: () -> Unit = {},
    onTerms: () -> Unit = {},
    onAbout: () -> Unit = {}
) {
    val coinColor = when (selectedCurrency) {
        CryptoCurrency.BTC  -> CoinBTC
        CryptoCurrency.ETH  -> CoinETH
        CryptoCurrency.SOL  -> CoinSOL
        CryptoCurrency.XRP  -> CoinXRP
        CryptoCurrency.JPYC -> CoinJPYC
    }

    Column(Modifier.fillMaxSize().background(BgScreen)) {
        PosHeaderWithMenu(onHistory = onHistory, onSettings = onSettings,
            onSupport = onSupport, onTerms = onTerms, onAbout = onAbout)
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 16.dp)
                .verticalScroll(rememberScrollState()),
        ) {
            Spacer(Modifier.height(12.dp))

            // 選択通貨バー
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .height(52.dp)
                    .background(BgCard, RoundedCornerShape(12.dp))
                    .border(1.dp, coinColor, RoundedCornerShape(12.dp))
                    .clickable(onClick = onChangeCurrency)
                    .padding(horizontal = 14.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                CryptoLogo(selectedCurrency, size = 32.dp)
                Spacer(Modifier.width(12.dp))
                Text("${selectedCurrency.displayName} / ${selectedCurrency.symbol}",
                    fontSize = 15.sp, fontWeight = FontWeight.Bold,
                    color = TextDark, modifier = Modifier.weight(1f))
                Text("›", fontSize = 18.sp, color = coinColor, fontWeight = FontWeight.Bold)
            }

            Spacer(Modifier.height(10.dp))

            // 金額カード
            PosCard {
                Text("日本円での金額", fontSize = 11.sp, color = TextLight)
                Spacer(Modifier.height(6.dp))
                Text(
                    "¥${if (displayAmount=="0") "0" else "%,d".format(displayAmount.toLongOrNull()?:0)}",
                    fontSize = 44.sp, fontWeight = FontWeight.Bold,
                    color = TextDark, textAlign = TextAlign.End,
                    modifier = Modifier.fillMaxWidth()
                )
                Spacer(Modifier.height(8.dp))
                HorizontalDivider(color = BorderCard)
                Spacer(Modifier.height(8.dp))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text("受取数量", fontSize = 12.sp, color = TextLight)
                    if (isFetchingRate) {
                        CircularProgressIndicator(Modifier.size(14.dp), color = coinColor, strokeWidth = 2.dp)
                    } else {
                        Text(previewCryptoAmount ?: "---",
                            fontSize = 14.sp, fontWeight = FontWeight.Bold, color = coinColor)
                    }
                }
                if (previewRate != null) {
                    Text(previewRate, fontSize = 10.sp, color = TextLight,
                        modifier = Modifier.fillMaxWidth(), textAlign = TextAlign.End)
                }
                Spacer(Modifier.height(8.dp))
                HorizontalDivider(color = BorderCard)
                Spacer(Modifier.height(8.dp))
                InfoRow("手数料 (6.5%・外乗せ)", "+¥${"%,d".format(feeJpy)}", valueColor = ColorWarning)
                Spacer(Modifier.height(4.dp))
                InfoRow("お客様ご負担合計", "¥${"%,d".format(merchantAmountJpy + feeJpy)}",
                    valueColor = TextDark, valueBold = true, fontSize = 13)
            }

            Spacer(Modifier.height(12.dp))
            NumericPad(onKey = onNumKey)
            Spacer(Modifier.height(10.dp))

            error?.let {
                Text(it, color = ColorDanger, fontSize = 13.sp,
                    modifier = Modifier.padding(bottom = 6.dp))
            }

            val canSubmit = (displayAmount.toIntOrNull() ?: 0) > 0
            PrimaryButton(
                text = if (isLoading) "処理中..." else "QRコードを表示",
                onClick = onSubmit,
                enabled = canSubmit && !isLoading
            )
            Spacer(Modifier.height(8.dp))
            SecondaryButton(
                text = "‹  戻る",
                onClick = onChangeCurrency
            )
            Spacer(Modifier.height(16.dp))
        }
    }
}
