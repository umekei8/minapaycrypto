package com.minapay.pos.ui.screens

import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.minapay.pos.ui.*
import com.minapay.pos.ui.components.PosHeader

@Composable
fun TermsScreen(onBack: () -> Unit) {
    Column(Modifier.fillMaxSize().background(BgScreen)) {
        PosHeader()
        Column(
            Modifier.fillMaxSize().padding(horizontal = 16.dp)
        ) {
            Spacer(Modifier.height(12.dp))
            Row(Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically) {
                Text("利用規約", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = TextDark)
                OutlinedButton(
                    onClick = onBack,
                    modifier = Modifier.height(34.dp),
                    shape = RoundedCornerShape(8.dp),
                    border = BorderStroke(1.dp, BorderCard),
                    contentPadding = PaddingValues(horizontal = 14.dp, vertical = 0.dp),
                    elevation = ButtonDefaults.buttonElevation(0.dp)
                ) { Text("‹ 戻る", fontSize = 13.sp, color = TextMid) }
            }
            Spacer(Modifier.height(12.dp))

            Column(
                Modifier.fillMaxSize().verticalScroll(rememberScrollState()),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                TermsSection("第1条（目的）",
                    "本規約は、みなペイ Crypto POS（以下「本サービス」）の利用条件を定めるものです。加盟店は本規約に同意の上、本サービスをご利用ください。")

                TermsSection("第2条（サービスの内容）",
                    "本サービスは、暗号資産による代金受取を支援するPOSシステムです。本サービスはノンカストディ型であり、運営者は利用者の暗号資産を保管・管理しません。")

                TermsSection("第3条（手数料）",
                    "本サービスの利用手数料は決済金額の6.5%（外税）とします。手数料はお客様の送金額に上乗せされます。加盟店は決済金額の全額を受け取ります。")

                TermsSection("第4条（レートについて）",
                    "暗号資産の円換算レートは、複数の外部APIから取得した参考レートを使用します。実際のウォレット換算レートとは誤差が生じる場合があります。本サービスは当該誤差について責任を負いません。")

                TermsSection("第5条（禁止事項）",
                    "利用者は以下の行為を禁止します。\n・法令または公序良俗に違反する行為\n・不正アクセスその他の行為\n・本サービスの運営を妨害する行為\n・マネーロンダリング等の不正な目的での利用")

                TermsSection("第6条（免責事項）",
                    "運営者は、本サービスの利用により生じた損害について、運営者の故意または重大な過失による場合を除き、一切の責任を負いません。ブロックチェーンネットワークの障害・遅延等についても同様とします。")

                TermsSection("第7条（個人情報）",
                    "本サービスは、決済処理に必要な最小限の情報のみを端末内に保存します。ウォレットアドレス等の情報は外部に送信されません。")

                TermsSection("第8条（サービスの変更・終了）",
                    "運営者は、利用者に事前に通知することにより、本サービスの内容を変更または終了することができます。")

                TermsSection("第9条（準拠法・管轄）",
                    "本規約は日本法に準拠し、東京地方裁判所を第一審の専属的合意管轄裁判所とします。")

                Text("制定日：2024年1月1日\n運営者：みなペイ Crypto / GANECCI",
                    fontSize = 12.sp, color = TextLight)

                Spacer(Modifier.height(24.dp))
            }
        }
    }
}

@Composable
private fun TermsSection(title: String, content: String) {
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(title, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = TextDark)
        Text(content, fontSize = 13.sp, color = TextMid, lineHeight = 22.sp)
        HorizontalDivider(color = BorderCard)
    }
}
