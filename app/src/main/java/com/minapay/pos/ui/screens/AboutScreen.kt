package com.minapay.pos.ui.screens

import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.minapay.pos.BuildConfig
import com.minapay.pos.R
import com.minapay.pos.ui.*
import com.minapay.pos.ui.components.PosHeader

@Composable
fun AboutScreen(onBack: () -> Unit) {
    Column(Modifier.fillMaxSize().background(BgScreen)) {
        PosHeader()
        Column(
            Modifier.fillMaxSize().padding(horizontal = 16.dp)
        ) {
            Spacer(Modifier.height(12.dp))
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("アプリについて",
                    fontSize = 18.sp, fontWeight = FontWeight.Bold, color = TextDark)
                OutlinedButton(
                    onClick = onBack,
                    modifier = Modifier.height(34.dp),
                    shape = RoundedCornerShape(8.dp),
                    border = BorderStroke(1.dp, BorderCard),
                    contentPadding = PaddingValues(horizontal = 14.dp, vertical = 0.dp),
                    elevation = ButtonDefaults.buttonElevation(0.dp)
                ) { Text("‹ 戻る", fontSize = 13.sp, color = TextMid) }
            }

            Spacer(Modifier.height(40.dp))

            // ロゴ（中央）
            Box(
                modifier = Modifier.fillMaxWidth(),
                contentAlignment = Alignment.Center
            ) {
                Image(
                    painter = painterResource(id = R.drawable.ic_minapay_logo),
                    contentDescription = "MinaPay Crypto",
                    modifier = Modifier
                        .fillMaxWidth(0.72f)
                        .heightIn(max = 120.dp),
                    contentScale = ContentScale.Fit
                )
            }

            Spacer(Modifier.height(32.dp))

            // バージョン情報
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .background(BgCard, RoundedCornerShape(12.dp))
                    .border(1.dp, BorderCard, RoundedCornerShape(12.dp))
                    .padding(20.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(12.dp)
            ) {
                Text(
                    "みなペイ Crypto POS",
                    fontSize = 18.sp,
                    fontWeight = FontWeight.Bold,
                    color = TextDark
                )

                // バージョン番号（大きく）
                Box(
                    modifier = Modifier
                        .background(Color(0xFFF0F1F4), RoundedCornerShape(8.dp))
                        .padding(horizontal = 24.dp, vertical = 8.dp)
                ) {
                    Text(
                        "Version ${BuildConfig.VERSION_NAME}",
                        fontSize = 22.sp,
                        fontWeight = FontWeight.Bold,
                        color = Primary
                    )
                }

                Text(
                    "Build ${BuildConfig.VERSION_CODE}",
                    fontSize = 13.sp,
                    color = TextLight
                )

                HorizontalDivider(color = BorderCard)

                AboutRow("対応端末",   "ZCS Z108")
                AboutRow("OS",        "Android 14")
                AboutRow("対応通貨",   "BTC / ETH / SOL / XRP / JPYC")
                AboutRow("手数料",     "6.5%（外乗せ）")
                AboutRow("開発元",     "GANECCI")
                AboutRow("お問合せ",   "support@minapay.jp")
            }

            Spacer(Modifier.height(24.dp))

            // コピーライト
            Text(
                "© 2024 GANECCI / みなペイ Crypto\nAll rights reserved.",
                fontSize = 11.sp,
                color = TextLight,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth(),
                lineHeight = 18.sp
            )

            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun AboutRow(label: String, value: String) {
    Row(
        Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Text(label, fontSize = 13.sp, color = TextMid)
        Text(value,  fontSize = 13.sp, color = TextDark, fontWeight = FontWeight.Medium)
    }
}
