package com.minapay.pos.ui.screens

import android.util.Log
import android.util.Patterns
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.minapay.pos.ui.*
import com.minapay.pos.ui.components.PosHeader
import com.minapay.pos.ui.components.PrimaryButton
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import okhttp3.MediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody
import okhttp3.MediaType.Companion.toMediaType
import org.json.JSONObject

@Composable
fun SupportFormScreen(onBack: () -> Unit) {
    val scope   = rememberCoroutineScope()

    var title   by remember { mutableStateOf("") }
    var body    by remember { mutableStateOf("") }
    var email   by remember { mutableStateOf("") }
    var sending by remember { mutableStateOf(false) }
    var result  by remember { mutableStateOf<String?>(null) }
    var isError by remember { mutableStateOf(false) }

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
                Text("サポートフォーム", fontSize = 18.sp, fontWeight = FontWeight.Bold, color = TextDark)
                OutlinedButton(
                    onClick = onBack,
                    modifier = Modifier.height(34.dp),
                    shape = RoundedCornerShape(8.dp),
                    border = BorderStroke(1.dp, BorderCard),
                    contentPadding = PaddingValues(horizontal = 14.dp, vertical = 0.dp),
                    elevation = ButtonDefaults.buttonElevation(0.dp)
                ) { Text("‹ 戻る", fontSize = 13.sp, color = TextMid) }
            }

            Text("お問い合わせ内容をご入力ください。",
                fontSize = 13.sp, color = TextMid, lineHeight = 20.sp)

            // 結果メッセージ
            result?.let { msg ->
                Box(Modifier.fillMaxWidth()
                    .background(
                        if (isError) Color(0xFFFFE4E6) else Color(0xFFDCFCE7),
                        RoundedCornerShape(10.dp))
                    .border(1.dp,
                        if (isError) ColorDanger else ColorSuccess,
                        RoundedCornerShape(10.dp))
                    .padding(14.dp)) {
                    Text(msg, color = if (isError) ColorDanger else ColorSuccess,
                        fontSize = 13.sp, fontWeight = FontWeight.Medium)
                }
            }

            SupportField("タイトル *", title, { title = it }, "お問い合わせの件名", true)
            SupportField("メールアドレス *", email, { email = it }, "your@email.com", true, KeyboardType.Email)
            SupportField("お問い合わせ内容 *", body, { body = it }, "詳しくご記入ください", false)

            val canSend = title.isNotBlank() && body.isNotBlank() &&
                    email.isNotBlank() && Patterns.EMAIL_ADDRESS.matcher(email).matches()

            PrimaryButton(
                text    = if (sending) "送信中..." else "送信する",
                enabled = canSend && !sending,
                onClick = {
                    sending = true; result = null
                    scope.launch {
                        val ok = sendToFormspree(title, body, email)
                        sending = false
                        if (ok) {
                            result  = "✓ 送信しました。返信をお待ちください。"
                            isError = false
                            title = ""; body = ""; email = ""
                        } else {
                            result  = "送信に失敗しました。\nsupport@minapay.jp へ直接ご連絡ください。"
                            isError = true
                        }
                    }
                }
            )

            Text("または: support@minapay.jp", fontSize = 12.sp, color = TextLight)
            Spacer(Modifier.height(24.dp))
        }
    }
}

@Composable
private fun SupportField(
    label: String, value: String, onValueChange: (String) -> Unit,
    placeholder: String, singleLine: Boolean,
    keyboardType: KeyboardType = KeyboardType.Text
) {
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(label, fontSize = 13.sp, fontWeight = FontWeight.Medium, color = TextDark)
        OutlinedTextField(
            value = value, onValueChange = onValueChange,
            modifier = Modifier.fillMaxWidth(),
            singleLine = singleLine,
            minLines  = if (!singleLine) 4 else 1,
            placeholder = { Text(placeholder, color = TextLight, fontSize = 13.sp) },
            textStyle = TextStyle(fontSize = 14.sp, color = TextDark),
            keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
            colors = OutlinedTextFieldDefaults.colors(
                focusedBorderColor   = Primary,
                unfocusedBorderColor = BorderCard),
            shape = RoundedCornerShape(8.dp)
        )
    }
}

private suspend fun sendToFormspree(subject: String, body: String, fromEmail: String): Boolean =
    withContext(Dispatchers.IO) {
        try {
            val json = JSONObject().apply {
                put("subject", "[MinaPay Support] $subject")
                put("email",   fromEmail)
                put("message", body)
            }.toString()
            val client = OkHttpClient()
            val req = Request.Builder()
                .url("https://formspree.io/f/xpwaqkny")
                .addHeader("Content-Type", "application/json")
                .addHeader("Accept", "application/json")
                .post(RequestBody.create("application/json".toMediaType(), json))
                .build()
            val resp = client.newCall(req).execute()
            Log.i("SupportForm", "Send result: ${resp.code}")
            resp.isSuccessful
        } catch (e: Exception) {
            Log.e("SupportForm", "Send error: ${e.message}")
            false
        }
    }
