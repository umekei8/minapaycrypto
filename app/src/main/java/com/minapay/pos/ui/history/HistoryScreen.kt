package com.minapay.pos.ui.history

import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.minapay.pos.data.model.*
import com.minapay.pos.ui.*
import com.minapay.pos.ui.components.*
import java.text.SimpleDateFormat
import java.util.*

@Composable
fun HistoryScreen(
    payments: List<Payment>,
    onBack: () -> Unit,
    onPrint: (Payment) -> Unit
) {
    Column(Modifier.fillMaxSize().background(BgScreen)) {
        PosHeader()
        Column(Modifier.fillMaxSize().padding(horizontal = 16.dp)) {
            Spacer(Modifier.height(12.dp))
            Row(
                Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("取引履歴", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = TextDark)
                OutlinedButton(
                    onClick = onBack,
                    modifier = Modifier.height(34.dp),
                    shape = RoundedCornerShape(8.dp),
                    border = BorderStroke(1.dp, BorderCard),
                    contentPadding = PaddingValues(horizontal = 14.dp, vertical = 0.dp),
                    elevation = ButtonDefaults.buttonElevation(0.dp)
                ) {
                    Text("‹ 戻る", fontSize = 13.sp, color = TextMid)
                }
            }
            Spacer(Modifier.height(12.dp))

            if (payments.isEmpty()) {
                Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                    Text("取引履歴がありません", color = TextLight, fontSize = 14.sp)
                }
                return@Column
            }

            LazyColumn(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                items(payments) { payment ->
                    HistoryCard(payment = payment, onPrint = { onPrint(payment) })
                }
            }
        }
    }
}

@Composable
private fun HistoryCard(payment: Payment, onPrint: () -> Unit) {
    val fmt = SimpleDateFormat("MM/dd HH:mm", Locale.getDefault())
    val (statusLabel, statusTextColor, statusBg, statusBorder) = when {
        payment.status == PaymentStatus.CONFIRMED && payment.isDemoConfirmed ->
            listOf("デモ決済", Color(0xFF7C3AED), Color(0xFFF3E8FF), Color(0xFFC4B5FD))
        payment.status == PaymentStatus.CONFIRMED ->
            listOf("確認済み", ColorSuccess, Color(0xFFDCFCE7), Color(0xFF86EFAC))
        payment.status == PaymentStatus.EXPIRED ->
            listOf("期限切れ", ColorDanger, Color(0xFFFFE4E6), Color(0xFFFCA5A5))
        payment.status == PaymentStatus.CANCELLED ->
            listOf("キャンセル", ColorDanger, Color(0xFFFFE4E6), Color(0xFFFCA5A5))
        payment.status == PaymentStatus.DETECTED ->
            listOf("確認中", ColorWarning, Color(0xFFFFF7E6), Color(0xFFFDBA74))
        else -> listOf("受付中", TextMid, Color(0xFFF3F4F6), BorderCard)
    }

    Column(
        modifier = Modifier
            .fillMaxWidth()
            .background(BgCard, RoundedCornerShape(12.dp))
            .border(1.dp, BorderCard, RoundedCornerShape(12.dp))
            .clickable(onClick = onPrint)
            .padding(12.dp)
    ) {
        Row(
            Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            CryptoLogo(payment.cryptoCurrency, size = 40.dp)
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(fmt.format(Date(payment.createdAt)),
                    fontSize = 11.sp, color = TextLight)
                Text("¥${"%,d".format(payment.fiatAmount)}",
                    fontSize = 20.sp, fontWeight = FontWeight.Bold, color = TextDark)
                Text("${payment.cryptoAmount.stripTrailingZeros().toPlainString()} ${payment.cryptoCurrency.symbol}",
                    fontSize = 13.sp, fontWeight = FontWeight.Bold,
                    color = when(payment.cryptoCurrency) {
                        CryptoCurrency.BTC -> CoinBTC
                        CryptoCurrency.ETH -> CoinETH
                        CryptoCurrency.SOL -> CoinSOL
                        CryptoCurrency.XRP -> CoinXRP
                        CryptoCurrency.JPYC -> CoinJPYC
                    })
            }
            // ステータスバッジ
            Box(
                modifier = Modifier
                    .background(statusBg as Color, RoundedCornerShape(10.dp))
                    .border(1.dp, statusBorder as Color, RoundedCornerShape(10.dp))
                    .padding(horizontal = 10.dp, vertical = 4.dp)
            ) {
                Text(statusLabel as String, color = statusTextColor as Color,
                    fontSize = 11.sp, fontWeight = FontWeight.Bold)
            }
        }
        Spacer(Modifier.height(8.dp))
        HorizontalDivider(color = BorderCard)
        Spacer(Modifier.height(8.dp))
        Row(
            Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Text("お支払い合計", fontSize = 12.sp, color = TextLight)
            Text(
                if (payment.status == PaymentStatus.CONFIRMED)
                    "¥${"%,d".format(payment.merchantAmountJpy)}"
                else "-",
                fontSize = 13.sp, fontWeight = FontWeight.Bold,
                color = if (payment.status == PaymentStatus.CONFIRMED) ColorSuccess else TextLight
            )
        }
    }
}
