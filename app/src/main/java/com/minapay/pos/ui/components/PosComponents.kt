package com.minapay.pos.ui.components

import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.minapay.pos.R
import com.minapay.pos.data.model.CryptoCurrency
import com.minapay.pos.ui.*

// ── POSカード ────────────────────────────────────────────────────────────────
@Composable
fun PosCard(
    modifier: Modifier = Modifier,
    borderColor: Color = BorderCard,
    content: @Composable ColumnScope.() -> Unit
) {
    Column(
        modifier = modifier
            .fillMaxWidth()
            .background(BgCard, RoundedCornerShape(12.dp))
            .border(1.dp, borderColor, RoundedCornerShape(12.dp))
            .padding(horizontal = 16.dp, vertical = 12.dp),
        content = content
    )
}

// ── プライマリボタン ──────────────────────────────────────────────────────────
@Composable
fun PrimaryButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true
) {
    Button(
        onClick = onClick,
        enabled = enabled,
        modifier = modifier.fillMaxWidth().height(56.dp),
        shape = RoundedCornerShape(10.dp),
        colors = ButtonDefaults.buttonColors(
            containerColor         = Color(0xFFE31E52),
            contentColor           = Color.White,
            disabledContainerColor = Color(0xFFCCA0B0),
            disabledContentColor   = Color.White
        ),
        elevation = ButtonDefaults.buttonElevation(0.dp, 0.dp, 0.dp)
    ) {
        Text(text, fontSize = 17.sp, fontWeight = FontWeight.Bold, color = Color.White)
    }
}

// ── セカンダリボタン ──────────────────────────────────────────────────────────
@Composable
fun SecondaryButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    textColor: Color = TextDark
) {
    OutlinedButton(
        onClick = onClick,
        modifier = modifier.fillMaxWidth().height(48.dp),
        shape = RoundedCornerShape(10.dp),
        colors = ButtonDefaults.outlinedButtonColors(contentColor = textColor),
        border = BorderStroke(1.dp, BorderCard),
        elevation = ButtonDefaults.buttonElevation(0.dp, 0.dp, 0.dp)
    ) {
        Text(text, fontSize = 14.sp, fontWeight = FontWeight.Medium, color = textColor)
    }
}

// ── POSヘッダー（実際のロゴ画像を使用） ─────────────────────────────────────
@Composable
fun PosHeader(modifier: Modifier = Modifier) {
    Box(
        modifier = modifier
            .fillMaxWidth()
            .height(64.dp)
            .background(Primary)
            .padding(horizontal = 16.dp),
        contentAlignment = Alignment.CenterStart
    ) {
        Image(
            painter = painterResource(id = R.drawable.ic_minapay_logo),
            contentDescription = "MinaPay Crypto",
            modifier = Modifier.height(40.dp),
            contentScale = ContentScale.FillHeight
        )
    }
}

// ── 通貨ロゴ（実際の公式ロゴPNG） ────────────────────────────────────────────
@Composable
fun CryptoLogo(currency: CryptoCurrency, size: Dp = 44.dp) {
    val resId = when (currency) {
        CryptoCurrency.BTC  -> R.drawable.ic_btc
        CryptoCurrency.ETH  -> R.drawable.ic_eth
        CryptoCurrency.SOL  -> R.drawable.ic_sol
        CryptoCurrency.XRP  -> R.drawable.ic_xrp
        CryptoCurrency.JPYC -> R.drawable.ic_jpyc
    }
    Image(
        painter = painterResource(id = resId),
        contentDescription = currency.displayName,
        modifier = Modifier.size(size).clip(CircleShape),
        contentScale = ContentScale.Crop
    )
}

// ── 通貨カード（選択リスト用・高さ68dp） ────────────────────────────────────
@Composable
fun CurrencyCard(
    currency: CryptoCurrency,
    onClick: () -> Unit
) {
    val borderColor = when (currency) {
        CryptoCurrency.BTC  -> CoinBTC
        CryptoCurrency.ETH  -> CoinETH
        CryptoCurrency.SOL  -> CoinSOL
        CryptoCurrency.XRP  -> CoinXRP
        CryptoCurrency.JPYC -> CoinJPYC
    }
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .height(68.dp)
            .background(BgCard, RoundedCornerShape(12.dp))
            .border(1.dp, borderColor, RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
            .padding(horizontal = 16.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        CryptoLogo(currency, size = 44.dp)
        Spacer(Modifier.width(16.dp))
        Column(Modifier.weight(1f)) {
            Text(currency.displayName, fontSize = 16.sp,
                fontWeight = FontWeight.Bold, color = TextDark)
            Text(currency.symbol, fontSize = 13.sp, color = TextMid)
        }
        Text("›", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = borderColor)
    }
}

// ── ステータスバッジ ──────────────────────────────────────────────────────────
@Composable
fun StatusBadge(label: String, textColor: Color, bgColor: Color, borderColor: Color) {
    Box(
        modifier = Modifier
            .background(bgColor, RoundedCornerShape(14.dp))
            .border(1.dp, borderColor, RoundedCornerShape(14.dp))
            .padding(horizontal = 12.dp, vertical = 5.dp)
    ) {
        Text(label, color = textColor, fontSize = 12.sp, fontWeight = FontWeight.Bold)
    }
}

// ── 情報行 ───────────────────────────────────────────────────────────────────
@Composable
fun InfoRow(
    label: String,
    value: String,
    labelColor: Color = TextMid,
    valueColor: Color = TextDark,
    valueBold: Boolean = false,
    fontSize: Int = 13
) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(label, color = labelColor, fontSize = fontSize.sp)
        Text(value, color = valueColor, fontSize = fontSize.sp,
            fontWeight = if (valueBold) FontWeight.Bold else FontWeight.Normal)
    }
}

// ── テンキー（Square Terminal風） ────────────────────────────────────────────
@Composable
fun NumericPad(onKey: (String) -> Unit) {
    val rows = listOf(
        listOf("1","2","3"),
        listOf("4","5","6"),
        listOf("7","8","9"),
        listOf("CLR","0","⌫"),
    )
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        rows.forEach { row ->
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                row.forEach { key ->
                    val isFn = key in listOf("CLR","⌫")
                    Box(
                        modifier = Modifier
                            .weight(1f)
                            .height(60.dp)
                            .background(
                                if (isFn) Color(0xFFF0F1F4) else BgCard,
                                RoundedCornerShape(8.dp)
                            )
                            .border(1.dp, BorderCard, RoundedCornerShape(8.dp))
                            .clickable { onKey(key) },
                        contentAlignment = Alignment.Center
                    ) {
                        Text(
                            key,
                            fontSize = if (isFn) 14.sp else 24.sp,
                            fontWeight = if (isFn) FontWeight.Normal else FontWeight.Bold,
                            color = if (isFn) TextMid else TextDark
                        )
                    }
                }
            }
        }
    }
}
