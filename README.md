# MinaPay Crypto POS — Demo App

MinaPay Crypto is a cryptocurrency payment service that allows merchants to accept USDT (TRC-20 / BEP-20 / ERC-20), BTC, ETH, and XRP in a non-custodial manner. The terminal holds no private keys — it only generates receive QR codes to merchant-registered wallets.

---

## Setup Instructions

### 1. Place the ZCS SDK

Place the following 3 files in `app/libs/`:

```
app/libs/
  SmartPos_2.0.4_R260318.aar
  emv_2.0.2_R260309.aar
  core-3.2.1.jar
```

The SDK can be obtained from the following path in the ZIP (SmartPos_2_0_4_R260318_SDK.zip):
```
SmartPos_2.0.4_R260318_SDK/lib/
```

### 2. Configure Wallet Information

Edit `app/src/main/assets/config/demo_config.json` and enter each wallet address.

```json
{
  "merchant_name": "Your Store Name",
  "merchant_usdt_trc20_address": "T...",
  "merchant_usdt_bep20_address": "0x...",
  "merchant_usdt_eth_address": "0x...",
  "merchant_xrp_address": "rXXXXXXXXXXXXXXXXXXXXXXXXXXXXX",
  "merchant_eth_address": "0xYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYY",
  "merchant_btc_address": "bc1qZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ",
  "operator_xrp_address": "rAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA",
  "operator_eth_address": "0xBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB",
  "operator_btc_address": "bc1qCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC",
  "xrp_destination_tag": "12345",
  "fee_percent": 5.0,
  "demo_mode": false
}
```

**Note: Never enter private keys. Receiving addresses only.** Operator wallet
addresses (`operator_*`) are admin-only — set them in `demo_config.json`
directly; they are not editable from the app.

In the in-app **Settings** screen, wallet addresses and payment settings are
shown read-only (edit them in `demo_config.json`), while **Demo Mode** can be
toggled directly — that toggle is saved to `filesDir/demo_config.json`, which
overrides the bundled asset on the next launch.

### 3. Build APK

Open the project in Android Studio and run:

```bash
./gradlew assembleDebug
```

The APK will be generated at:
```
app/build/outputs/apk/debug/app-debug.apk
```

### 4. Install on Z108 Terminal

```bash
adb install app/build/outputs/apk/debug/app-debug.apk
```

Alternatively, transfer the APK to the terminal via USB storage and install it from the file manager.

---

## How to Use

### Payment Flow

1. Enter the amount using the numeric keypad
2. Select currency (USDT TRC-20 and XRP recommended — complete in 3–5 seconds).
   USDT is shown first and selected by default.
3. Tap "Start Payment" → Fetch rate → Generate QR
4. QR code is displayed on the customer-facing second screen
5. Customer scans with their wallet and sends payment
6. Auto-detected based on each chain's criteria → Completion screen
7. Tap "Print Receipt" to print receipt

### Demo Mode (When Network is Unstable)

Demo Mode is a config toggle, controlled from the **Settings** screen (or the
`demo_mode` flag in `demo_config.json`).

| | Demo OFF | Demo ON |
|--|----------|---------|
| Top banner | none | Red "⚠ DEMO MODE" bar |
| Blockchain monitor | active (real) | disabled |
| Manual confirm | hidden | visible "Simulate Completion" button on the QR screen |
| History / receipt | normal | tagged `[DEMO]` / `[DEMO MODE]` |

With Demo ON, tap **Simulate Completion** → confirmation dialog → the payment is
force-completed without any on-chain check.

---

## Completion Timing by Currency

| Currency | Time to Complete | Method |
|----------|-----------------|--------|
| USDT TRC-20 | 3–5 seconds | TronGrid API polling (1 block) |
| USDT BEP-20 | 3–5 seconds | BscScan API polling (1 block) |
| USDT ERC-20 | 12–30 seconds | Etherscan API polling (1 block) |
| XRP | 3–5 seconds | XRPL WebSocket, auto-reconnecting (instant finality) |
| ETH | 12–30 seconds | Etherscan API polling (1 block) |
| BTC | 10 min – indefinite | mempool.space API polling (async) |

**API keys**: Etherscan (ETH / USDT ERC-20) and BscScan (USDT BEP-20) default to
the placeholder `"YourApiKeyToken"` in `BlockchainClients.kt` — set real keys for
those chains to work. TronGrid (USDT TRC-20) and XRPL need no key.

**Regarding BTC**: It is difficult to keep customers waiting on-site.  
At the point of deposit detection (DETECTED), advise customers to proceed, and confirmation is recorded after the fact.

---

## Fee Display Rules

| Screen | Display Content |
|--------|----------------|
| Customer second screen | Payment amount (JPY) only. No fees displayed at all |
| Merchant main screen | Displays fee, merchant expected receivable, and unsettled fees |
| Receipt | Fee information included as merchant copy |
| History | Fee information included for merchant reference |

Default operator fee is **5%** (`fee_percent`) and the CoinGecko rate carries a
**1%** markup (`rate_markup_percent`); both are configurable. For USDT the demo
records the merchant/operator split (see `SettlementCalculator`); for BTC/ETH/XRP
the merchant receives the full amount and the operator fee is recorded as
unsettled for later reconciliation.

---

## File Structure

```
app/src/main/
├── assets/config/demo_config.json   ← Wallet & config (requires editing)
├── java/com/minapay/pos/
│   ├── data/
│   │   ├── api/   CoinGeckoService, BlockchainClients
│   │   ├── db/    Room DB (payments table)
│   │   ├── model/ Payment, DemoConfig, CryptoCurrency
│   │   └── repository/ PaymentRepository
│   ├── domain/
│   │   ├── calculator/ FeeCalculator, RateCalculator, SettlementCalculator, WalletRouter
│   │   └── payment/    PaymentMonitor, BlockchainPaymentMonitor, DemoPaymentMonitor
│   ├── device/
│   │   ├── printer/      MinaPayPrinter (ZCS SDK)
│   │   └── secondscreen/ SecondScreenManager, SecondScreenRenderer
│   ├── ui/
│   │   ├── amount/   AmountInputScreen
│   │   ├── payment/  QRDisplayScreen, PaymentCompleteScreen
│   │   ├── history/  HistoryScreen
│   │   ├── settings/ SettingsScreen
│   │   ├── Theme.kt, MainViewModel.kt, DemoModeBanner.kt
│   ├── util/  ConfigLoader, LocaleManager, QrGenerator
│   ├── MainActivity.kt
│   └── MinaPayApplication.kt
└── libs/  ← Place ZCS SDK AARs here
```

---

## Production Readiness Challenges

1. **Direct Split Payment to Operator Wallet**
   - ETH: Splitter smart contract
   - BTC: PSBT multi-output
   - XRP: Payment Channel / Hooks
   - Extend `SettlementCalculator` and `WalletRouter`

2. **BlockchainPaymentMonitor Enhancements**
   - Switch to Infura/Alchemy paid nodes (to handle Etherscan free tier limits)
   - Make BTC confirmation count configurable

3. **Backend Server Integration**
   - Replace `PaymentRepository` with Laravel API
   - Settlement reports & admin dashboard

4. **Security**
   - Migrate `demo_config.json` to encrypted storage
   - Secure management of terminal API keys

---

## Notes

- Do not store private keys in the app (non-custodial design)
- Inform merchant staff that this is a demo version
- Always verify demo mode operation in advance at event venues
