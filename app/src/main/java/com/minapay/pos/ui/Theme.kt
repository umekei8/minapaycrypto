package com.minapay.pos.ui

import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.sp

// ── POS端末カラー（参考画像から実測） ──────────────────────────────────────
val Primary      = Color(0xFFE31E52)   // ヘッダー・プライマリボタン
val PrimaryLight = Color(0xFFFFE8EF)

val BgScreen     = Color(0xFFF9F9FB)   // 画面背景
val BgCard       = Color(0xFFFFFFFF)   // カード背景
val BorderCard   = Color(0xFFDCDEE4)   // カードborder

val TextDark     = Color(0xFF1E2027)   // メインテキスト
val TextMid      = Color(0xFF5A5D6C)   // サブテキスト
val TextLight    = Color(0xFF9B9EAC)   // キャプション

val ColorSuccess = Color(0xFF16A34A)
val ColorDanger  = Color(0xFFDC2626)
val ColorWarning = Color(0xFFF98C04)   // BTCオレンジ兼用

// 通貨カラー（公式）
val CoinBTC  = Color(0xFFF98C04)
val CoinETH  = Color(0xFF6279EA)
val CoinSOL  = Color(0xFF9945FF)
val CoinXRP  = Color(0xFF0B5CC9)
val CoinJPYC = Color(0xFF1C6CDC)

// ── タイポグラフィ ──────────────────────────────────────────────────────────
val POSTypography = Typography(
    // 画面タイトル
    headlineLarge  = TextStyle(fontSize = 22.sp, fontWeight = FontWeight.Bold,  color = Color(0xFF1E2027)),
    headlineMedium = TextStyle(fontSize = 18.sp, fontWeight = FontWeight.Bold,  color = Color(0xFF1E2027)),
    // カードタイトル
    titleLarge     = TextStyle(fontSize = 17.sp, fontWeight = FontWeight.Bold,  color = Color(0xFF1E2027)),
    titleMedium    = TextStyle(fontSize = 14.sp, fontWeight = FontWeight.Medium,color = Color(0xFF1E2027)),
    // 本文
    bodyLarge      = TextStyle(fontSize = 14.sp, fontWeight = FontWeight.Normal,color = Color(0xFF5A5D6C)),
    bodyMedium     = TextStyle(fontSize = 12.sp, fontWeight = FontWeight.Normal,color = Color(0xFF5A5D6C)),
    // ラベル・キャプション
    labelLarge     = TextStyle(fontSize = 13.sp, fontWeight = FontWeight.Medium,color = Color(0xFF9B9EAC)),
    labelMedium    = TextStyle(fontSize = 11.sp, fontWeight = FontWeight.Normal,color = Color(0xFF9B9EAC)),
    labelSmall     = TextStyle(fontSize = 10.sp, fontWeight = FontWeight.Normal,color = Color(0xFF9B9EAC)),
    // 金額表示
    displayLarge   = TextStyle(fontSize = 44.sp, fontWeight = FontWeight.Bold,  color = Color(0xFF1E2027)),
    displayMedium  = TextStyle(fontSize = 28.sp, fontWeight = FontWeight.Bold,  color = Color(0xFF1E2027)),
)

private val colorScheme = lightColorScheme(
    primary          = Primary,
    onPrimary        = Color.White,
    surface          = BgCard,
    onSurface        = TextDark,
    background       = BgScreen,
    onBackground     = TextDark,
    outline          = BorderCard,
    error            = ColorDanger,
)

@Composable
fun MinaPayTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = colorScheme,
        typography  = POSTypography,
        content     = content
    )
}
